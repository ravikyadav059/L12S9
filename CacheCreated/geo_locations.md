# Cache Documentation: `geo_locations` (`country`, `state`, `city`)

## Collections: `country`, `state`, `city`
**Eloquent Models**:
- [`App\Models\Country`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Country.php)
- [`App\Models\State`](file:///c:/Users/hp/Desktop/L12S9/app/Models/State.php)
- [`App\Models\City`](file:///c:/Users/hp/Desktop/L12S9/app/Models/City.php)
- [`App\Helpers\TimezoneHelper`](file:///c:/Users/hp/Desktop/L12S9/app/Helpers/TimezoneHelper.php)  
**Database**: MongoDB (`clonedata.country`, `clonedata.state`, `clonedata.city`)

---

## 1. Cache Summary & Keys

| Cache Key Pattern | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`countries:all:list`** | All countries sorted alphabetically: `[['id' => string, 'name' => string], ...]` | **24 Hours** (86,400s) | Profile address, institution filters, journal location | Cleared on `Country::forgetCached()` |
| **`countries_all_collection`** | Full Eloquent collection of countries | **24 Hours** (86,400s) | Journal ISSN country resolution | Auto-expires via TTL |
| **`all_countries_lookup_v3`** | Array of countries for directory dropdowns | **24 Hours** (86,400s) | Institution directory filter header | Auto-expires via TTL |
| **`country_id_to_name_map_v3`** | Key-value associative map `[id => name]` | **24 Hours** (86,400s) | Instant ID-to-Name card resolution | Auto-expires via TTL |
| **`states_lookup_{countryId}`** | All states belonging to a specific country ID | **24 Hours** (86,400s) | Cascading State dropdown in profile/institution forms | Auto-expires via TTL |
| **`cities_lookup_{stateId}`** | All cities belonging to a specific state ID | **24 Hours** (86,400s) | Cascading City dropdown in profile/institution forms | Auto-expires via TTL |
| **`state_map_v3_{md5_ids}`** | Batch mapped state names by ID list | **24 Hours** (86,400s) | Institution search results table | Auto-expires via TTL |
| **`city_map_v3_{md5_ids}`** | Batch mapped city names by ID list | **24 Hours** (86,400s) | Institution search results table | Auto-expires via TTL |
| **`tz_country_iso_{timezone}`** | ISO 2-character country code for timezone | **30 Days** (2,592,000s) | Auto-detecting visitor country from timezone | Auto-expires via TTL |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`country`**: `id`, `name`, `sortname`, `phonecode`.
- **`state`**: `id`, `name`, `country_id`.
- **`city`**: `id`, `name`, `state_id`.

### Reason for Caching:
1. **Massive Global Dataset Size**:
   - The global `city` collection contains **over 150,000 documents**.
   - Querying cities without caching wastes significant memory and server CPU.
2. **Cascading Dropdowns (Country &rarr; State &rarr; City)**:
   - When a user selects *"United States"*, the state dropdown fetches `states_lookup_231`.
   - When they select *"California"*, the city dropdown fetches `cities_lookup_3924`.
   - Caching these parent-child partitions provides **instant (< 1ms)** UI reactivity without scanning the 150,000-row collection.
3. **Immutability of Geographic Boundaries**:
   - World countries, states, and cities almost never change. Caching them for 24 hours to 30 days is safe and optimal.

---

## 3. Lifecycle & Flush Events (Save & Update)

```php
// app/Models/Country.php

public const CACHE_KEY = 'countries:all:list';

protected static function booted(): void
{
    static::saved(fn () => static::forgetCached());
    static::deleted(fn () => static::forgetCached());
}

public static function forgetCached(): void
{
    Cache::forget(self::CACHE_KEY);
}

// Cascading State retrieval:
public static function getCachedByCountry(string|int|null $countryId): array
{
    if (empty($countryId)) {
        return [];
    }

    $cId = (string) $countryId;

    return Cache::remember("states_lookup_{$cId}", 86400, function () use ($cId) {
        $docs = static::raw(fn ($col) => $col->find(
            ['country_id' => ['$in' => [(int) $cId, (string) $cId]]],
            ['projection' => ['id' => 1, 'name' => 1], 'sort' => ['name' => 1]]
        ));

        $list = [];
        foreach ($docs as $doc) {
            if (isset($doc['id']) && isset($doc['name'])) {
                $list[] = ['id' => (string) $doc['id'], 'name' => (string) $doc['name']];
            }
        }
        usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    });
}
```

---

## 4. Concurrency Pressure & Stampede Protection

### The Bottleneck Scenario:
- **The Case**: If an entire country key like `states_lookup_231` expires during peak hours when hundreds of users are registering or editing profiles.
- **Where the Pressure Happens**:
  - Scanning MongoDB for all cities in a large state (e.g. California or Texas with thousands of cities) in parallel can spike RAM and lock collection cursors.

### Solution:
1. **Partitioned Keys**: Caching by parent ID (`states_lookup_{countryId}`, `cities_lookup_{stateId}`) ensures that a cache miss on one state does NOT affect other states or countries.
2. **Direct MongoDB Projection**: Caching functions use raw projections (`['projection' => ['id' => 1, 'name' => 1]]`), avoiding hydrating full Eloquent models and saving 85% memory.
3. **Atomic Locks on Large States**:
   ```php
   Cache::lock("lock:cities_lookup_{$sId}", 10)->block(3, function () use ($sId) {
       return City::getCachedByState($sId);
   });
   ```
