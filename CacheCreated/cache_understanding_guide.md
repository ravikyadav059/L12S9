# Cache Understanding Guide: Safe Caching for Future Projects

Use this guide before adding a new cache to any project. It is written for developers who know the feature they want to speed up but want to avoid duplicate keys, stale data, database overload, or Redis memory failures.

## 1. What a cache is — and what it is not

A cache is a temporary copy of data that is expensive to obtain or calculate. Instead of asking the database or an external API for the same result repeatedly, the application reads the saved copy from a fast store such as Redis.

Caching a query result does **not** automatically cache the whole page. Laravel, authorization, Livewire, Blade rendering, and HTTP response delivery can still run for every request. Full-page or CDN caching is a separate decision.

## 2. Essential words

| Term | Plain meaning | Typical result |
| --- | --- | --- |
| Cache key | The unique name of a cached value, for example `organizations:metrics:{organizationId}`. | Determines whether two requests share the same cached result. |
| Cache value / payload | The data stored under the key. | A list, array, count, rendered fragment, API response, etc. |
| TTL (time to live) | How long the value remains valid. | When it expires, the next request must rebuild it. |
| Cache hit | The key exists and is returned. | Fast; the original query is skipped. |
| Cache miss | The key does not exist, has expired, or was removed. | The application must calculate or fetch the value. |
| Warm cache | A useful value is already present. | Most requests are fast cache hits. |
| Cold cache | No useful value exists yet. | The first request must create it. |
| Invalidation | Removing or replacing cached data after its source changes. | Prevents users seeing old data. |
| Cache stampede | Many requests miss one key at the same time and all rebuild it. | Database/API overload and slow requests. |
| Negative caching | Caching a known failure or empty result for a short time. | Stops repeated failed work, but must use a short TTL. |

## 3. How one request behaves

```text
Request arrives
     |
     v
Does the exact cache key exist?
     | yes                         | no
     v                             v
Return cached data             Run query / API call / calculation
     |                             |
     v                             v
Render response                Store result with TTL, then render response
```

Typical timing is environment-dependent, but this explains why caching matters:

| Work | Typical order of magnitude | Notes |
| --- | --- | --- |
| In-memory value in the same PHP request | microseconds | Does not survive the request. |
| Redis read on the same network | usually under a few milliseconds | Network, payload size, and Redis load matter. |
| Indexed database query | often a few to tens of milliseconds | Varies with indexes, data size, and load. |
| Aggregate / regex / multi-collection query | tens of milliseconds to seconds | Usually a strong cache candidate. |
| External HTTP request | tens of milliseconds to seconds | Needs timeout, retry policy, and failure handling. |

Never promise an exact time such as “Redis is always 1ms”. Measure p50, p95, and p99 latency in the actual production environment.

## 4. Design a cache before writing it

Answer these questions first:

1. **What exact data is being cached?** Write the payload shape and source query.
2. **Who may share it?** All visitors, one organization, one profile, one authenticated user, or one session?
3. **What belongs in the key?** Include every input that changes the result: ID, locale, filter, page, limit, permission scope, and search text hash where needed.
4. **What TTL is acceptable?** Choose the maximum time users may see old data.
5. **What event makes it stale?** Create, update, delete, role change, moderation action, import, or external sync.
6. **How expensive is a miss?** Decide whether a normal `remember`, a lock, stale-while-revalidate, or queue warming is required.
7. **How many unique keys can exist?** Estimate memory before release.

### Good key names

Use `domain:scope:data:{variable}` names that explain both the payload and its boundary:

```text
organizations:metrics:{organizationId}
organizations:search:{queryHash}:page:{page}:limit:{limit}
publications:user:{userId}:active
journals:active:disciplines:list
authors:search:{queryHash}
```

Avoid ambiguous keys such as `data`, `list`, `cache1`, or `users_v2`. A key name should reveal whether it contains active-only data, all-status data, a profile-specific result, or a filtered search result.

## 5. Cache key name mismatches

### The problem

Two pages ask for the same logical data but use different keys:

```php
Cache::remember('skills:list', 1800, $callback);
Cache::remember('skills:list:v2', 3600, $callback);
```

This creates two Redis entries. The first page can warm one entry for 30 minutes while the second page warms another for one hour. Data, expiry, and invalidation become inconsistent.

### How to stop it

- Put the key name and TTL in one model/service method or constant.
- Call that one method from every page that needs the same data.
- Use a different key only when the payload, filters, permissions, or intended freshness are truly different.
- Prefer descriptive scopes (`journals:active:*` versus `journals:all:*`) over anonymous version suffixes.

## 6. Same key with different expiry times

### The problem

`Cache::remember()` does not renew a key’s TTL on a cache hit. If two code paths use the same key with different TTLs, whichever request creates the missing key first decides the expiry for that cache cycle.

### How to stop it

Define the key and TTL in one place:

```php
final class SkillCache
{
    public const Key = 'skills:active:list';
    public const Ttl = 1800;
}
```

Or expose one retrieval method on the relevant model/service. Do not repeat a literal key and TTL in several pages.

## 7. Cache stampede (Thundering Herd)

### The problem point

At the exact moment a popular key expires, 10,000 users request it:

```text
10,000 requests -> key missing -> 10,000 expensive queries -> database/API overload
```

`Cache::remember()` is a good cache-aside helper, but it does not make all concurrent misses share one callback. Without extra protection, many PHP workers can run the same expensive work.

### When it is likely

- A popular key expires at a fixed time during peak traffic.
- An admin calls `Cache::forget()` on a popular key.
- A deployment or `cache:clear` empties many keys together.
- A public URL is shared and receives a burst of traffic.
- Search/autocomplete has no debounce or minimum character limit.

### How to stop it

Choose one of these patterns based on the cost of a miss:

1. **Atomic lock:** One worker recalculates; others wait briefly and then reread the cache. Use a shared Redis lock and always reread inside the lock.
2. **Stale-while-revalidate:** Serve a slightly old value immediately while Laravel refreshes it in the background. Laravel provides `Cache::flexible()` for this pattern.
3. **Queue warming:** After a write, queue a job that calculates and replaces expensive public keys before visitors need them.
4. **TTL jitter:** Add a small random amount to bulk-created TTLs so unrelated keys do not all expire in the same second.
5. **Request reduction:** Debounce live search and require a useful minimum search length.

Do not add a lock to every cheap lookup automatically. Locks have their own waiting cost. Use them for expensive, high-traffic misses.

## 8. Missing invalidation (stale data)

### The problem

The database record changes, but the cache remains valid for another hour. Users see yesterday’s name, status, count, or permission result.

### How to stop it

- Identify model events that change the cached result: `saved`, `deleted`, status changes, relationship changes, imports, and bulk updates.
- Forget or replace every affected key after a successful change.
- For a group of related search keys, use Redis cache tags and flush only that tag.
- When exact invalidation is not practical, choose a short TTL and clearly accept the temporary staleness.
- Test both: “data is cached” and “data changes remove or refresh the cache”.

Never cache authorization decisions under a key that can be reused by a different user. Include the authorization scope in the key, or do not cache the result.

## 9. Failure and negative caching

### The problem

An external service is slow, unavailable, or returns “not found”. Every request retries it immediately, creating more traffic and a worse outage.

### Helpful use

Cache a known failure or empty result briefly:

```text
Successful link preview: 24 hours
Temporary HTTP failure: 1–5 minutes
No search results: 1–5 minutes when that is acceptable
```

### Danger

Do not cache a temporary failure for the same long TTL as a success. For example, storing a failed link preview for 24 hours means users may keep seeing the failure long after the remote site recovers.

Store a clear result shape, such as `success`, `data`, `error`, and `cached_at`, so code can distinguish an empty valid result from an error.

## 10. High-cardinality profile scaling and Redis memory

### The problem point

100,000 unique scholar profiles × about 7 cached sections (`awards`, `publications`, `patents`, memberships, and others) can create **700,000+ keys**. If each payload is large, Redis memory rises quickly.

The actual memory use is more than the JSON/PHP payload alone: Redis stores key metadata, value metadata, expiry metadata, allocator overhead, and any tag metadata. Estimate conservatively:

```text
estimated memory = unique keys × (average serialized payload bytes + key/metadata overhead)
```

Example: 700,000 keys × an average 4 KB payload is already about 2.8 GB of payload before overhead. Large publication arrays can make this much larger.

### What happens when memory is exhausted

Redis behavior depends on `maxmemory` and `maxmemory-policy`:

| Setting | Result |
| --- | --- |
| No sensible memory limit | Redis can consume host memory and put the whole server at risk. |
| `maxmemory` with `noeviction` | Redis rejects new writes with OOM errors once the limit is reached. |
| `volatile-lru` | Redis may evict only keys with a TTL; persistent keys cannot be selected. |
| `allkeys-lru` | Redis may evict any least-recently-used key, including a key without a TTL. |

### How to size and protect Redis

1. Measure average serialized payload size and projected unique-key count.
2. Reserve headroom; do not plan to operate at 100% memory.
3. Configure `maxmemory` below available machine/container RAM so the operating system, Redis overhead, and other services remain safe.
4. Choose an eviction policy intentionally. Cache-only Redis commonly uses an `allkeys-*` or `volatile-*` policy according to whether every cache key has a TTL. A `noeviction` policy is only safe when write failures are expected and handled.
5. Give every ordinary cache entry a TTL; avoid permanent user-specific entries unless they have a strict cleanup plan.
6. Cache small projections, counts, IDs, or paginated slices instead of entire profile histories where possible.
7. Monitor `used_memory`, `maxmemory`, eviction count, rejected writes, hit rate, and largest keys.
8. Scale Redis vertically (more RAM) or to a managed/cluster deployment only after measuring memory growth and request rate.

More Redis memory does not fix an unbounded key design. First control cardinality, payload size, and TTL.

## 11. Admin diagnostic screens and whole-collection scans

### The problem point

When two or more administrators open or refresh a diagnostic page at the same time, a cache miss can start multiple full-collection scans and unindexed foreign-key integrity checks. These multi-second scans compete with normal application queries and can make the entire database slow.

### How to stop it

- Cache diagnostic summaries for a short TTL, such as 60 seconds, because administrators need reasonably fresh results.
- Use a lock or stale-while-revalidate for expensive scans so one scan refreshes the result.
- Invalidate the specific diagnostic key after a repair action.
- Add database indexes for fields used by the diagnostic query before relying on caching.
- Prefer cursor/chunk processing for repair jobs; do not load an entire collection into PHP memory.
- Consider a queued scan with a stored progress/result record for very expensive diagnostics.
- Restrict these screens to authorized administrators and avoid automatic refresh intervals that create background load.

## 12. Redis capacity versus application capacity

Increasing Redis memory helps only when the cache is useful and the cache server is the real bottleneck.

| Symptom | First check | Likely action |
| --- | --- | --- |
| Redis evictions or OOM write errors | `maxmemory`, policy, key count, payload size | Reduce cache footprint; then increase Redis RAM/capacity if required. |
| Low cache hit rate | Key mismatches, short TTL, unnecessary unique inputs | Normalize keys, centralize access, review TTL. |
| Database spikes at expiry | Cold misses/stampedes | Add locks, stale values, jitter, or warming. |
| PHP workers exhausted while Redis is healthy | Full pages still render expensively | Optimize rendering, use queues, add CDN/full-page caching for public pages. |
| External API overload | Repeated failures or cache misses | Add timeout, short negative cache, lock, and rate limit. |

## 13. New-cache checklist

Before merge/release, confirm every item:

- [ ] The key name describes domain, scope, and variables.
- [ ] The same logical payload has one key and one TTL definition.
- [ ] User, organization, locale, filters, pagination, and permissions are included when they change the result.
- [ ] The payload is minimal and does not contain secrets or data for another user.
- [ ] A TTL exists and is justified.
- [ ] Create/update/delete/import events invalidate or refresh the cache correctly.
- [ ] Expensive shared misses have stampede protection.
- [ ] External failures use a short negative-cache TTL, not the success TTL.
- [ ] Key cardinality and worst-case memory use are estimated.
- [ ] Redis memory limit, eviction policy, and alerts are configured intentionally.
- [ ] Tests cover a cache hit, cache invalidation, and no cross-user data leak.
- [ ] A load test covers warm traffic, cold traffic, and many unique keys.

## 14. Safe default decisions

| Data type | Starting approach |
| --- | --- |
| Small shared lookup changed rarely | Shared key, 1–24 hour TTL, model-event invalidation. |
| Popular public aggregate | Short TTL, lock or stale-while-revalidate, queue warming after writes. |
| Search/autocomplete | Query-hash key, short TTL, debounce, minimum input length, result limit. |
| Per-user/profile section | Key includes subject ID and state (`active`/`all`), TTL, mutation invalidation, memory/cardinality monitoring. |
| External API/metadata | URL/request-hash key, success TTL, much shorter failure TTL, timeout, and rate limit. |
| Admin integrity scan | Short TTL, precise invalidation after repairs, lock, indexes, and preferably a queued scan for large datasets. |

## 15. Final rule

Caching is a data-correctness and capacity feature, not merely a speed feature. A cache design is complete only when its key scope, TTL, invalidation, failure behavior, concurrent-miss behavior, and memory limit are all known.
