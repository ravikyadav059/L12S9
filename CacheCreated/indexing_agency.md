# Cache Documentation: `indexing_agency`

## Collection: `indexing_agency`
**Eloquent Model**: [`App\Models\IndexingAgency`](file:///c:/Users/hp/Desktop/L12S9/app/Models/IndexingAgency.php)  
**Database**: MongoDB (`clonedata.indexing_agency`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`indexing_agencies:active:list`** | Array of active agencies sorted by `serial_number`: `[['name' => string, 'key' => string, 'image' => ?string], ...]` | **24 Hours** (86,400 seconds) | Journal submission checklists, Admin verification | Cleared on `IndexingAgency::forgetCached()` |
| **`indexing_agencies_active_all`** | Formatted array of active agencies with asset URLs | **24 Hours** (86,400 seconds) | Public Journal Detail ([`journal/show.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/journal/show.blade.php)) | Cleared on `IndexingAgency::forgetCached()` |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`agency_name`**: Name of the indexing body (e.g. *"Scopus"*, *"Web of Science"*, *"PubMed"*, *"DOAJ"*, *"Google Scholar"*).
- **`key`**: Sanitized lowercase/underscore identifier for checkbox binding (e.g. `"scopus"`, `"web_of_science"`).
- **`image`**: Resolved logo URL for UI display.
- **`serial_number`**: Display ordering integer.
- **`status`**: Active status filter (`status ∈ [1, '1']`).

### Reason for Caching:
1. **Appears Across Thousands of Journal Detail Pages**:
   - Every journal display page ([`journal/show.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/journal/show.blade.php)) renders the indexing agency badges.
   - Without caching, loading journal pages would repeatedly query `indexing_agency` for every visitor.
2. **Deterministic Display Order**:
   - Agencies are pre-sorted in memory according to `serial_number`, guaranteeing clean presentation without database sort penalties.

---

## 3. Lifecycle & Flush Events (Save & Update)

The cache is flushed instantly whenever an indexing agency is created, updated, or removed:

```php
// app/Models/IndexingAgency.php

public const CACHE_KEY = 'indexing_agencies:active:list';

public static function forgetCached(): void
{
    Cache::forget(self::CACHE_KEY);
    Cache::forget('indexing_agencies_active_all');
}

public static function getCachedActive(): array
{
    $cached = Cache::get(self::CACHE_KEY);
    if (is_array($cached) && ! empty($cached)) {
        return $cached;
    }

    $items = static::whereIn('status', [1, '1'])
        ->select('agency_name', 'image', 'serial_number')
        ->orderBy('serial_number')
        ->get()
        ->map(function ($item) {
            return [
                'name'  => (string) $item->agency_name,
                'key'   => str_replace(' ', '_', trim((string) $item->agency_name)),
                'image' => $item->image ? asset($item->image) : null,
            ];
        })
        ->values()
        ->toArray();

    if (! empty($items)) {
        Cache::put(self::CACHE_KEY, $items, 86400);
    }

    return $items;
}
```

---

## 4. Concurrency Pressure & Stampede Solution

### The Bottleneck:
If an agency logo is updated, flushing this key while multiple high-traffic journals are being viewed concurrently can trigger simultaneous queries for the agencies list.

### Solution:
Pre-warm the cache during the model save event so the cache is immediately re-populated before any visitor experiences a cache miss:

```php
protected static function booted(): void
{
    static::saved(function () {
        static::forgetCached();
        static::getCachedActive(); // Proactively re-warms cache
    });
}
```
