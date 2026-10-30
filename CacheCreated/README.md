# Cache Registry & Concurrency Architecture Guide (`CacheCreated`)

This directory serves as the centralized knowledge base, architectural catalog, and concurrency blueprint for all caching mechanisms across the application.

---

## 1. Registered Collections & Cache Catalogs

| Collection / Domain | Dedicated Documentation | Primary Cache Keys | Default TTL | Invalidation / Flush Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`designation`** | [designation.md](./designation.md) | `designations:all_active_v1`<br>`designations:default:list`<br>`designations:default:list:p:1:l:20` | **2 Days** (172,800s) / 1 Hr | Cleared on `saved` & `deleted` model events via `clearActiveCache()` |
| **`organization`** | [organization.md](./organization.md) | `organizations:default:list:p:{page}:l:{limit}`<br>`organizations:search:{md5}:p:{p}:l:{l}`<br>`org_metrics_v6_{orgId}`<br>`total_active_organizations_count_v4` | **1 Hr** (3,600s)<br>5 Mins (300s)<br>30 Mins (1,800s) | Flushed on `findOrCreateQuick()`, save, update, or sub-entity changes |
| **`grade`** | [grade.md](./grade.md) | `grades:all_active_v1` | **7 Days** (604,800s) | Cleared on `saved` & `deleted` model events via `clearCache()` |
| **`degree_type`** | [degree_type.md](./degree_type.md) | `degree_types:all_active_v1` | **2 Days** (172,800s) | Cleared on `saved` & `deleted` model events via `clearActiveCache()` |
| **`field_of_study`** | [field_of_study.md](./field_of_study.md) | `fields_of_study:all_active_v1` | **2 Days** (172,800s) | Cleared on `saved` & `deleted` model events via `clearActiveCache()` |
| **`reviewer_journal`** | [reviewer_journal.md](./reviewer_journal.md) | `total_active_journals_count`<br>`journal_disciplines_list`<br>`journal_indexers_list`<br>`j_search_version`<br>`j_search_v3_{ver}_{hash}` | **24 Hrs** (86,400s)<br>1 Hr (3,600s)<br>5 Mins (300s) | Cleared via `forgetCaches()`; search keys version-busted via `j_search_version` timestamp |
| **`indexing_agency`** | [indexing_agency.md](./indexing_agency.md) | `indexing_agencies:active:list`<br>`indexing_agencies_active_all` | **24 Hrs** (86,400s) | Cleared via `forgetCached()` on save/update/delete |
| **`journal_role`** | [journal_role.md](./journal_role.md) | `journal_roles:active:list` | **24 Hrs** (86,400s) | Cleared via `forgetCached()` on save/update/delete |
| **`geo_locations`** (`country`, `state`, `city`) | [geo_locations.md](./geo_locations.md) | `countries:all:list`<br>`countries_all_collection`<br>`states_lookup_{countryId}`<br>`cities_lookup_{stateId}`<br>`tz_country_iso_{tz}` | **24 Hrs** (86,400s) up to **30 Days** | Flushed via `Country::forgetCached()` on admin updates |
| **`users`** | [users.md](./users.md) | `users:publishers:active:list`<br>`qa_active_contributors` | **1 Hr** (3,600s)<br>15 Mins (900s) | Cleared via `forgetCachedPublishers()` on role/status changes |
| **`question`** | [question.md](./question.md) | `qa_popular_questions`<br>`qa_popular_questions_detail`<br>`qa_community_stats`<br>`admin_questions_counts` | **15 Mins** (900s)<br>10 Mins (600s)<br>60 Seconds (Admin) | Expired by TTL; admin counters flushed via `Cache::forget('admin_questions_counts')` |
| **`skills`** | [skills.md](./skills.md) | `qa_available_skills_v2` | **30 Mins** (1,800s) to 1 Hr | Expired by TTL; updated on skill tagging |
| **`publications`** | [publications.md](./publications.md) | `orphan_journal_missing_stats`<br>Aggregated in `org_metrics_v6_{id}` | **5 Mins** (300s) to 30 Mins | Flushed on publication cleanup or paper status update |
| **`reviewer_profile`** & **`experience`** | [reviewer_profile.md](./reviewer_profile.md)<br>[experience.md](./experience.md) | Sub-aggregates compiled into `org_metrics_v6_{orgId}` | **30 Mins** (1,800s) | Flushed when affiliated users change organizations |

---

## 2. What Happens When Multiple Caches Are Created Simultaneously?

### The Phenomenon: **Cache Stampede** (*Thundering Herd* / *Dog-Piling*)

When a high-demand cache key expires (TTL ends) or is suddenly invalidated (`Cache::forget()` or `Cache::flush()`), a vacuum is created. 

If hundreds or thousands of concurrent users request that data at the exact same millisecond:

```
                  [ 10,000 Concurrent Requests ]
                                |
                                v
                   Is Key in Cache? (Redis)
                                |
                      NO (Key Just Expired)
                                |
         +----------------------+----------------------+
         |                      |                      |
         v                      v                      v
   Request #1              Request #2             Request #10,000
 (Executes Query)       (Executes Query)       (Executes Query)
         |                      |                      |
         +----------------------+----------------------+
                                |
                                v
               💥 DATABASE DROWNING UNDER 10,000 IDENTICAL
                   COMPLEX QUERIES SIMULTANEOUSLY!
```

---

## 3. Where Does the Pressure and Bottleneck Happen?

When a stampede occurs, pressure cascades across four major architectural tiers:

```
[ Clients / Users ]
       │
       ▼
┌────────────────────────────────────────────────────────┐
│ 1. Application Layer (Web Server & PHP-FPM Pool)       │
│    - Worker Exhaustion: pm.max_children maxed out      │
│    - Memory Spikes: 1,000 workers holding large arrays │
│    - Cascading HTTP 502 / 504 Gateway Timeouts         │
└──────────────────────────┬─────────────────────────────┘
                           │
             ┌─────────────┴─────────────┐
             ▼                           ▼
┌──────────────────────────┐┌─────────────────────────────┐
│ 2. Cache Layer (Redis)   ││ 3. Database Layer (MongoDB) │
│ - Socket write storm     ││ - CPU spikes to 100%        │
│ - Last-write race wars   ││ - Connection pool depleted  │
│ - Ephemeral lock clashes ││ - Unindexed scans freeze I/O│
└──────────────────────────┘└─────────────────────────────┘
```

### A. Database Layer Bottleneck (MongoDB / MySQL)
1. **Connection Pool Depletion**: MongoDB has a maximum incoming connection limit (`maxIncomingConnections`). 500 simultaneous workers instantly exhaust the connection pool, throwing `No suitable servers found` or `Connection refused`.
2. **100% CPU Spike**: Expensive queries (like regex searches on `organization_name`, `$facet` paginations, or multi-collection lookups for institution metrics) running 500 times in parallel peg server CPU at 100%.
3. **I/O Schedulers & Disk Bottlenecks**: High volume read/write requests cause disk I/O wait queues to explode, delaying even trivial primary-key lookups across unrelated features.

### B. Application Server Layer Bottleneck (PHP-FPM / Nginx / Octane)
1. **PHP-FPM Worker Pool Starvation**: PHP workers are synchronous. If a query normally taking 5ms slows down to 4,000ms under load, all available PHP-FPM processes (`pm.max_children`, typically 50–200 workers) remain locked waiting for the database.
2. **Nginx 502 Bad Gateway / 504 Gateway Timeout**: New visitors cannot get a PHP worker assigned, immediately receiving 502 or 504 errors.
3. **Out of Memory (OOM) Crashes**: 200 concurrent PHP processes each hydrating 1,000 MongoDB documents into memory can exceed the physical RAM of the web server, triggering the Linux OOM Killer.

### C. Cache Layer Bottleneck (Redis / Memcached)
1. **Write Contention Storm**: Once all 500 PHP processes finally finish the query, they all attempt to execute `Cache::put($key, $payload)` at the exact same microsecond, saturating network bandwidth and CPU.
2. **Race Conditions ("Last Writer Wins")**: Parallel workers writing slightly different states back to cache cause data inconsistency.

---

## 4. Under What Scenarios Does This Happen?

| Scenario | Trigger / Cause | Risk Level | Real Example in System |
| :--- | :--- | :--- | :--- |
| **Peak-Hour TTL Expiry** | A fixed TTL (e.g. 3,600s) expires during peak traffic (12:00 PM - 4:00 PM). | 🔴 **CRITICAL** | `organizations:default:list` expires while hundreds of scholars are editing work experience. |
| **Aggressive Model `Cache::forget()`** | An admin edits a single record, triggering `Cache::forget()`. Active users instantly swamp the cold key. | 🔴 **HIGH** | Admin adds a new `Designation`; observer clears `designations:all_active_v1`; all active users trigger query simultaneously. |
| **Cold Starts & Deployments** | Deploying new code or running `php artisan cache:clear` empties all keys at once. | 🟠 **HIGH** | Production cache flush during daytime deployment. |
| **Un-debounced Autocomplete Inputs** | Fast-typing users sending raw search requests per keystroke on large collections. | 🟠 **MEDIUM** | User types "Harvard Medical School" &rarr; sends 22 separate Livewire search queries in 3 seconds. |
| **Synchronized Expiration ("Herd Expiry")** | Multiple cache keys created at the exact same batch time with the same TTL expiring together. | 🟡 **MEDIUM** | Hundreds of lookup tables expiring at the same minute. |

---

## 5. Architectural Solutions & Best Practices

To permanently eliminate cache stampedes and server bottlenecks, we use five industry-standard strategies:

```
                                  INCOMING REQUEST
                                         │
                                         ▼
                            Key Exists in Cache?
                                   /    \
                             YES  /      \  NO (Cold Miss)
                                 /        \
                    Serve Instant Cache    Try to Acquire Atomic Lock
                    (< 1ms latency)        (Cache::lock)
                                            /          \
                             Lock Acquired /            \ Lock NOT Acquired
                                          /              \ (Another worker is computing)
                            Compute Query &               │
                            Update Cache;                 ├─ Opt A: Return Stale Cache (< 1ms)
                            Release Lock                  └─ Opt B: Block/Wait 250ms & Re-read Cache
```

### Strategy 1: Atomic Mutex Locks (`Cache::lock`)
Only **one** worker is allowed to recompute the cache. All other incoming concurrent requests either wait briefly or return stale data.

```php
use Illuminate\Support\Facades\Cache;

public static function getWithMutexLock(): array
{
    $cacheKey = 'organizations:default:list';
    $lockKey  = 'lock:' . $cacheKey;

    // 1. Return immediately if present
    if ($cached = Cache::get($cacheKey)) {
        return $cached;
    }

    // 2. Acquire an atomic lock for 10 seconds
    $lock = Cache::lock($lockKey, 10);

    if ($lock->get()) {
        try {
            // Re-verify cache inside lock (Double-Check Pattern)
            if ($cached = Cache::get($cacheKey)) {
                return $cached;
            }

            // Exactly ONE worker computes the heavy database query
            $data = self::query()
                ->whereIn('status', [1, '1'])
                ->orderBy('organization_name', 'asc')
                ->limit(50)
                ->get()
                ->toArray();

            Cache::put($cacheKey, $data, 3600);

            return $data;
        } finally {
            $lock->release();
        }
    }

    // 3. If another worker holds the lock, wait up to 3 seconds for them to finish
    return $lock->block(3, function () use ($cacheKey) {
        return Cache::get($cacheKey, []);
    });
}
```

---

### Strategy 2: Stale-While-Revalidate (Soft TTL + Hard TTL)
Serve previous data **instantly** to users (0ms latency), while an asynchronous background queue job refreshes the cache before users notice any staleness.

```php
use Illuminate\Support\Facades\Cache;
use App\Jobs\WarmOrganizationCacheJob;

public static function getStaleWhileRevalidate(): array
{
    $cacheKey = 'organizations:default:payload';
    $payload  = Cache::get($cacheKey);

    // Payload structure: ['data' => [...], 'soft_expires_at' => timestamp]
    if ($payload) {
        // If soft TTL has expired, dispatch async background refresh job
        if (now()->timestamp > $payload['soft_expires_at']) {
            WarmOrganizationCacheJob::dispatch();
        }

        // Return cached data immediately without making user wait
        return $payload['data'];
    }

    // Cold start fallback
    return self::recomputeAndSave($cacheKey);
}
```

---

### Strategy 3: Asynchronous Cache Pre-Warming (Proactive Warming)
Instead of deleting keys with `Cache::forget()` when an Admin modifies a record, **proactively recompute and overwrite** the key with `Cache::put()` via a queued listener. The cache is **never empty** for public users!

```
[ Admin Updates Record ]
          │
          ▼
Dispatch Event: DesignationUpdated
          │
          ▼
Queue Worker: WarmDesignationCacheJob (Background)
          │
          ▼
Executes Cache::put('designations:all_active_v1', $freshData, 172800)
(No public user ever encounters a cache miss!)
```

---

### Strategy 4: TTL Jitter (Randomized Expiration)
Prevent synchronized herd expiration by adding a random time delta to every TTL.

```php
// Instead of fixed 3600 seconds:
$jitter = rand(0, 300); // 0 to 5 minutes random variance
Cache::put($cacheKey, $data, 3600 + $jitter);
```

---

### Strategy 5: Livewire Search Debouncing & Minimum Character Threshold
Prevent frivolous database queries from live search boxes:
1. `wire:model.live.debounce.300ms` &rarr; Reduces server requests by 90%+.
2. `mb_strlen($search) >= 3` &rarr; Prevents expensive 1-character regex scans.

---

## 6. Directory Structure Reference

```
CacheCreated/
├── README.md                      # This master architecture & concurrency guide
├── designation.md                 # Designations cache catalog & TTL lifecycle
├── organization.md                # Organizations, directory, & metrics caching
├── grade.md                       # Education grade caching & invalidation
├── degree_type.md                 # Degree types cache & lifecycle
├── field_of_study.md              # Field of study cache & lifecycle
├── reviewer_journal.md            # Journals, disciplines, & search versioning
├── indexing_agency.md             # Indexing agency cache & sort lifecycle
├── journal_role.md                # Journal roles cache & invalidation
├── geo_locations.md               # Country, State, City cascading caches
├── users.md                       # Publishers & author contributors caching
├── question.md                    # Q&A community stats, counts, & popular feeds
├── skills.md                      # Skill tags & autocomplete caching
├── publications.md                # Publications & orphan stats caching
├── reviewer_profile.md            # Reviewer profile multikey cache aggregation
└── experience.md                  # Work experience organization linking cache
```
