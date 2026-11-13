# 100k Traffic and Cache Concurrency Q&A

## Does cache mean only one request is processed?

No. It only reuses a data fragment. All requests still use the load balancer, PHP/Laravel/Livewire, and Blade response path. Full-page/CDN caching is separate and not found in the audited code.

## What is safe for 100k users on the same page?

With warm shared values, Redis serves cached data instead of MongoDB recomputing it. The remaining limits are Redis operations/network, PHP worker capacity, HTTP connections, and bandwidth. Test the full request path, not Redis alone.

## What is unsafe?

On a cold key, concurrent `Cache::remember` calls can all execute their callbacks. That cache stampede can multiply MongoDB work or outbound link-preview traffic. Current code has no lock, stale-while-revalidate, or miss coalescing.

## What should be protected first?

`organizations:metrics:{id}`, `link_preview_{urlHash}`, journal/author autocomplete, Q&A aggregate keys, and per-profile publications. A future implementation should use a short Redis `Cache::lock` with a second cache check, or `Cache::flexible`; callers must handle lock timeouts. Apply TTL jitter and queue-based warming after mutations.

## What must be monitored?

Redis hit/miss/eviction/memory/latency; MongoDB query time, CPU, I/O, and connection waits; PHP worker queue and memory; HTTP p95/p99 and errors. Run separate warm same-key, cold same-key, and high-cardinality different-profile load tests, and verify profile isolation in every result.
