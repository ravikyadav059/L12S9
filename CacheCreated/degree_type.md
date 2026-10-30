# Cache Documentation: `degree_type`

## Collection: `degree_type`
**Eloquent Model**: [`App\Models\DegreeType`](file:///c:/Users/hp/Desktop/L12S9/app/Models/DegreeType.php)  
**Database**: MongoDB (`clonedata.degree_type`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Storage Driver | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`degree_types:all_active_v1`** | Deduplicated list of active degrees: `[['id' => string, 'name' => string], ...]` | **2 Days** (172,800 seconds) | Redis / Cache Store | Cleared on `DegreeType::saved()`, `DegreeType::deleted()`, or `clearActiveCache()` |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`_id`**: String representation of MongoDB Object ID.
- **`degree_type`**: Name of the academic qualification (e.g. *"Ph.D. in Computer Science"*, *"Master of Science (M.Sc.)"*, *"Bachelor of Engineering (B.E.)"*).
- **`status`**: Filtered for active records (`status ∈ [1, '1', true]`).

### Reason for Caching:
1. **Deduplication in Memory**:
   - Legacy collections often have varying cases or minor whitespace variations. The cache method cleans and deduplicates names once via an in-memory hash set, caching clean data.
2. **Instant Profile Hydration**:
   - Used inside the User Profile Education section ([`education.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/admin/users/partials/education.blade.php)).
   - Eliminates database round-trips when scholars add or view educational qualifications.

---

## 3. Lifecycle & Flush Events (Save & Update)

The cache is flushed instantly whenever a degree type is added, modified, or removed:

```php
// app/Models/DegreeType.php

public const CACHE_KEY_ACTIVE = 'degree_types:all_active_v1';

protected static function booted(): void
{
    static::saved(function () {
        self::clearActiveCache();
    });

    static::deleted(function () {
        self::clearActiveCache();
    });
}

public static function clearActiveCache(): void
{
    Cache::forget(self::CACHE_KEY_ACTIVE);
}

public static function getAllActiveCached(): array
{
    return Cache::remember(self::CACHE_KEY_ACTIVE, 172800, function () {
        $records = self::query()
            ->select(['_id', 'degree_type', 'status'])
            ->whereIn('status', [1, '1', true])
            ->whereNotNull('degree_type')
            ->where('degree_type', '!=', '')
            ->orderBy('degree_type', 'asc')
            ->get();

        $list = [];
        $seen = [];
        foreach ($records as $record) {
            $name = trim((string) $record->degree_type);
            $key = strtolower($name);
            if ($name !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $list[] = [
                    'id'   => (string) $record->_id,
                    'name' => $name,
                ];
            }
        }

        return $list;
    });
}
```

---

## 4. Concurrency Pressure & Stampede Solution

### The Bottleneck:
If multiple users simultaneously search or add educational records immediately after an administrative edit, MongoDB faces concurrent unindexed case-insensitive sorting.

### Solution:
Protect via Redis Mutex Lock to ensure a single worker rebuilds the deduplicated cache:

```php
public static function getAllActiveCachedSafe(): array
{
    return Cache::lock('lock:' . self::CACHE_KEY_ACTIVE, 10)->block(3, function () {
        return self::getAllActiveCached();
    });
}
```
