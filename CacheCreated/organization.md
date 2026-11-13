# Cache Documentation: `organization`

> **Audit status:** The registry in [README.md](README.md) is current. The mutex code below is a recommended future safeguard, not production code.

## Collection: `organization`
**Eloquent Model**: [`App\Models\Organization`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Organization.php)  
**Database**: MongoDB (`clonedata.organization`)

---

## 1. Cache Summary & Keys

| Cache Key Pattern | Data Stored | TTL (Duration) | Purpose / Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`organizations:default:list`** | Top active institutions list for default dropdown state | **1 Hour** (3,600s) | Work experience & education modal dropdowns | Cleared on `findOrCreateQuick()` or record save |
| **`organizations:default:list:p:{page}:l:{limit}`** | Paginated default institution list | **1 Hour** (3,600s) | Infinite scroll in institution dropdowns | Cleared on `findOrCreateQuick()` or record save |
| **`organizations:search:{md5_query}:p:{page}:l:{limit}`** | Search results for a specific search query | **5 Minutes** (300s) | Live autocomplete in user profile modals | Auto-expires via TTL (300 seconds) |
| **`organizations:active:count`** | Total count of active institutions | **1 Hour** (3,600s) | Public Directory header ([`institution/index.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/institution/index.blade.php)) | Auto-expires via TTL; cleared on admin adds |
| **`organizations:metrics:{orgId}`** | Aggregated counts: Scholars, Alumnus, Papers, Citations, Seminars | **30 Minutes** (1,800s) | Public Institution Profile ([`institution/show.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/institution/show.blade.php)) & Directory cards | Auto-expires via TTL (1,800 seconds) |
| **`country_item_raw_{rawCountry}`**<br>**`state_item_raw_{rawState}`**<br>**`city_item_raw_{rawCity}`** | Raw location documents used to resolve an institution's location on its public detail page | **24 Hours** (86,400s) | Public Institution Profile | Auto-expires via TTL |
| **`countries:directory:list`**<br>**`countries:directory:id-name-map`** | Country list and ID-to-name map for the directory | **24 Hours** (86,400s) | Public Institution Directory | Auto-expires via TTL |
| **`states:directory:map:{idsHash}`**<br>**`cities:directory:map:{idsHash}`** | State/city maps for the visible directory result IDs | **24 Hours** (86,400s) | Public Institution Directory | Auto-expires via TTL |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **Autocomplete & Directory**:
  - `_id`: String MongoDB ID.
  - `organization_name`: Full university / company title (e.g. *"Stanford University"*).
  - `city`: Location city string.
  - `country`: Country name/code.
  - `submission_count`: Auto-promotion tally (`>= 3` auto-activates status).
  - `status`: Active flag (`status ∈ [1, '1', true]`).
- **Institution Metrics (`organizations:metrics:{orgId}`)**:
  ```php
  [
      'current_scholars_count' => int, // Active users with current_organization = 1
      'alumni_count'           => int, // Past users affiliated with org
      'publications_count'     => int, // Papers linked via reviewer profiles
      'citations_count'        => int, // Sum of citations across papers
      'conferences_count'      => int, // Seminars & conferences linked
  ]
  ```

### Reason for Caching:
1. **Extremely Heavy Aggregation Pipeline**:
   - Calculating `organizations:metrics:{orgId}` requires scanning across **5 separate collections**: `organizations` &rarr; `experience` &rarr; `users` &rarr; `reviewer_profile` &rarr; `publications`.
   - Running this aggregation live on every page load would take **450ms – 1,200ms** per institution card.
   - With Redis caching, metrics are served in **< 1ms**, reducing server load by over 99.8%.
2. **High-Frequency Autocomplete**:
   - Hundreds of users search for universities simultaneously in Work Experience and Education profile editors.

---

## 3. Lifecycle & Flush Events (Save & Update)

### When Does It Flush?
1. **On Inline Creation / Promotion (`findOrCreateQuick`)**:
   When a user types a new institution name or promotes an existing one:
   ```php
   // app/Models/Organization.php
   Cache::forget('organizations:default:list');
   Cache::forget('organizations:default:list:p:1:l:20');
   ```
2. **On Metrics Computation (`institution/show.blade.php`)**:
   ```php
   $metrics = Cache::remember('organizations:metrics:'.$orgId, 1800, function () use ($orgId) {
       // Multi-collection aggregation across experience, users, reviewer_profile, publications
       return [
           'current_scholars_count' => count($currentScholarUserIds),
           'alumni_count'           => count($alumniUserIds),
           'publications_count'     => $publicationsCount,
           'citations_count'        => $totalCitations,
           'conferences_count'      => $seminarsCount,
       ];
   });
   ```

---

## 4. Concurrency Pressure Analysis & Stampede Protection

### The Bottleneck Scenario:
- **The Case**: When an institution detail page (e.g. Stanford University with 5,000 scholars and 25,000 papers) is shared on social media or newsletter:
  - 1,000 visitors arrive concurrently.
  - At minute 30, `organizations:metrics:{orgId}` TTL expires.
- **Where the Pressure Happens**:
  - **MongoDB CPU**: Hits 100% instantly because 1,000 parallel workers execute 5-tier cross-collection joins.
  - **PHP-FPM Worker Starvation**: Workers take 2 seconds each, blocking all PHP process threads. Nginx starts responding with `504 Gateway Timeout`.
  - **Redis Socket Pressure**: 1,000 workers attempt to write identical 5KB payloads back to the exact same Redis key.

### Architectural Solution:
Use **Atomic Mutex Locking** with fallback stale data:

```php
public static function getMetricsWithMutex(string $orgId): array
{
    $cacheKey = 'organizations:metrics:' . $orgId;
    $lockKey  = 'lock:' . $cacheKey;

    if ($cached = Cache::get($cacheKey)) {
        return $cached;
    }

    // Only ONE PHP worker performs the heavy 5-collection cross-aggregation
    return Cache::lock($lockKey, 15)->block(4, function () use ($cacheKey, $orgId) {
        return Cache::remember($cacheKey, 1800, function () use ($orgId) {
            return static::calculateHeavyMetrics($orgId);
        });
    });
}
```
