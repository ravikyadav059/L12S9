# Cache Documentation: `journal_role`

## Collection: `journal_role`
**Eloquent Model**: [`App\Models\JournalRole`](file:///c:/Users/hp/Desktop/L12S9/app/Models/JournalRole.php)  
**Database**: MongoDB (`clonedata.journal_role`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`journal_roles:active:list`** | Array of active editorial roles: `[['id' => string, 'name' => string], ...]` | **24 Hours** (86,400 seconds) | Scholar Profile editorial section, Admin user edit | Cleared on `JournalRole::forgetCached()` |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`_id`**: String MongoDB ID mapped for foreign relation `role_in_research_journals.role`.
- **`name`**: Editorial title (e.g. *"Editor-in-Chief"*, *"Associate Editor"*, *"Managing Editor"*, *"Peer Reviewer"*).
- **`status`**: Filtered for active entries (`status ∈ [1, '1']`).

### Reason for Caching:
1. **Static Lookup Category**:
   - Journal editorial roles represent standardized academic titles that seldom change.
2. **Fast Modal Hydration**:
   - Scholars managing their editorial appointments in [`resources/views/livewire/userprofile.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/userprofile.blade.php) receive the dropdown options instantly without querying MongoDB.

---

## 3. Lifecycle & Flush Events (Save & Update)

The cache is cleared whenever an admin creates, updates, or deletes an editorial role:

```php
// app/Models/JournalRole.php

public const CACHE_KEY = 'journal_roles:active:list';

protected static function booted(): void
{
    static::saved(function () {
        static::forgetCached();
    });

    static::deleted(function () {
        static::forgetCached();
    });
}

public static function forgetCached(): void
{
    Cache::forget(self::CACHE_KEY);
}

public static function getCachedActive(): array
{
    $cached = Cache::get(self::CACHE_KEY);
    if (is_array($cached) && ! empty($cached)) {
        return $cached;
    }

    $roles = static::whereIn('status', [1, '1'])
        ->get()
        ->map(fn ($r) => [
            'id'   => (string) ($r->_id ?? $r->id),
            'name' => (string) $r->name,
        ])
        ->values()
        ->toArray();

    if (! empty($roles)) {
        Cache::put(self::CACHE_KEY, $roles, 86400);
    }

    return $roles;
}
```

---

## 4. Concurrency Pressure & Stampede Solution

### The Bottleneck:
If multiple users are updating their scholar editorial appointments simultaneously during conference deadlines, cache miss on this key will trigger identical queries.

### Solution:
Employ **Atomic Mutex Locking** to ensure a single thread rebuilds the role cache:

```php
public static function getCachedActiveSafe(): array
{
    return Cache::lock('lock:' . self::CACHE_KEY, 10)->block(3, function () {
        return self::getCachedActive();
    });
}
```
