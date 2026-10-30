# Cache Documentation: `grade`

## Collection: `grade`
**Eloquent Model**: [`App\Models\Grade`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Grade.php)  
**Database**: MongoDB (`clonedata.grade`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Storage Driver | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`grades:all_active_v1`** | Array of active academic grades: `[['id' => string, 'name' => string], ...]` | **7 Days** (604,800 seconds) | Redis / Cache Store | Cleared on `Grade::saved()`, `Grade::deleted()`, or `Grade::clearCache()` |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`_id`**: String MongoDB ID mapped for foreign reference in user education records.
- **`grade`**: The grade label or grading scheme (e.g. *"First Class with Distinction"*, *"Grade A"*, *"4.0 GPA Scale"*).
- **`status`**: Active status filter (`status ∈ [1, '1', true]`).

### Reason for Caching:
1. **Near-Zero Mutation Frequency**:
   - Academic grade classifications are practically static reference data. They are defined once during setup and almost never altered.
2. **Instant Modal Rendering**:
   - Used inside the User Profile Education section ([`education.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/admin/users/partials/education.blade.php)).
   - Storing in Redis with a 7-day TTL delivers sub-millisecond dropdown hydration with zero MongoDB query overhead.

---

## 3. Lifecycle & Flush Events (Save & Update)

The cache is flushed instantly whenever a grade document is inserted, updated, or removed:

```php
// app/Models/Grade.php

public const CACHE_KEY_ALL = 'grades:all_active_v1';

protected static function booted(): void
{
    static::saved(function () {
        self::clearCache();
    });

    static::deleted(function () {
        self::clearCache();
    });
}

public static function clearCache(): void
{
    Cache::forget(self::CACHE_KEY_ALL);
}

public static function getAllCached(): array
{
    return Cache::remember(self::CACHE_KEY_ALL, 604800, function () {
        $records = self::query()
            ->select(['_id', 'grade', 'status'])
            ->whereIn('status', [1, '1', true])
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->get();

        $list = [];
        foreach ($records as $record) {
            $list[] = [
                'id'   => (string) $record->_id,
                'name' => trim((string) $record->grade),
            ];
        }

        return $list;
    });
}
```

---

## 4. Concurrency Pressure & Stampede Solution

### The Scenario:
- Because the TTL is 7 days, cache misses are exceptionally rare.
- However, if an administrator saves an updated grade during business hours, `clearCache()` purges the key.
- If several users open the Education modal at that moment, all will execute the query.

### Solution:
Utilize Laravel's **`Cache::lock`** mutex or proactive cache pre-warming on model save:

```php
// app/Models/Grade.php
protected static function booted(): void
{
    static::saved(function () {
        // Proactive Pre-Warming: Overwrite instead of forget!
        self::clearCache();
        self::getAllCached(); // Instantly repopulates Redis
    });
}
```
