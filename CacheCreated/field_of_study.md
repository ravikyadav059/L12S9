# Cache Documentation: `field_of_study`

## Collection: `field_of_study`
**Eloquent Model**: [`App\Models\FieldOfStudy`](file:///c:/Users/hp/Desktop/L12S9/app/Models/FieldOfStudy.php)  
**Database**: MongoDB (`clonedata.field_of_study`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Storage Driver | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`fields_of_study:all_active_v1`** | Deduplicated array of active academic disciplines: `[['id' => string, 'name' => string], ...]` | **2 Days** (172,800 seconds) | Redis / Cache Store | Cleared on `FieldOfStudy::saved()`, `FieldOfStudy::deleted()`, or `clearActiveCache()` |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`_id`**: String MongoDB ID linked to `education.field_of_study`.
- **`field_of_study`**: Name of the academic specialization (e.g. *"Computational Linguistics"*, *"Astrophysics"*, *"Biotechnology"*).
- **`status`**: Filtered for active entries (`status ∈ [1, '1', true]`).

### Reason for Caching:
1. **Case-Insensitive Deduplication**:
   - Cleans duplicate or mixed-cased submissions in RAM during initial load.
2. **High-Frequency Autocomplete**:
   - Used inside the User Profile Education section ([`education.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/admin/users/partials/education.blade.php)).
   - Speeds up student and researcher education profile entry by delivering cached suggestions instantly.

---

## 3. Lifecycle & Flush Events (Save & Update)

The cache is flushed instantly whenever a discipline document is added, edited, or deleted:

```php
// app/Models/FieldOfStudy.php

public const CACHE_KEY_ACTIVE = 'fields_of_study:all_active_v1';

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
            ->select(['_id', 'field_of_study', 'status'])
            ->whereIn('status', [1, '1', true])
            ->whereNotNull('field_of_study')
            ->where('field_of_study', '!=', '')
            ->orderBy('field_of_study', 'asc')
            ->get();

        $list = [];
        $seen = [];
        foreach ($records as $record) {
            $name = trim((string) $record->field_of_study);
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

## 4. Concurrency Pressure & Stampede Protection

### The Bottleneck:
High concurrent traffic in profile editors when this cache key expires can trigger dozens of unindexed sorting queries in MongoDB.

### Solution:
Utilize Laravel's **`Cache::lock`** mutex pattern:

```php
public static function getAllActiveCachedSafe(): array
{
    return Cache::lock('lock:' . self::CACHE_KEY_ACTIVE, 10)->block(3, function () {
        return self::getAllActiveCached();
    });
}
```
