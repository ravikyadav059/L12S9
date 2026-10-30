# Cache Documentation: `designation`

## Collection: `designation`
**Eloquent Model**: [`App\Models\Designation`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Designation.php)  
**Database**: MongoDB (`clonedata.designation`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Storage Driver | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`designations:all_active_v1`** | Array of active designations: `[['id' => string, 'name' => string], ...]` | **2 Days** (172,800 seconds) | Redis / Cache Store | Cleared on `Designation::saved()`, `Designation::deleted()`, or `clearActiveCache()` |
| **`designations:default:list`** | Default paginated top active designations for dropdowns | **1 Hour** (3,600 seconds) | Redis / Cache Store | Cleared alongside `all_active_v1` on model updates |
| **`designations:default:list:p:1:l:20`** | Paginated top-20 active designations buffer | **1 Hour** (3,600 seconds) | Redis / Cache Store | Cleared alongside `all_active_v1` on model updates |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`_id`**: Converted to string representation for foreign key assignment in `experience.designation`.
- **`designation`**: The human-readable job title (e.g. *"Associate Professor"*, *"Postdoctoral Fellow"*).
- **`status`**: Filtered for active entries (`status ∈ [1, '1', true]`).

### Reason for Caching:
1. **High Read-to-Write Ratio (> 1000:1)**:
   - Thousands of scholars and researchers access user profiles and the **Work Experience modal** daily.
   - The list of official designations changes very rarely (only when an administrator approves a new job title).
2. **Eliminates Repetitive Database Hits**:
   - Every time a user opens the "Add Experience" modal in [`resources/views/livewire/userprofile.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/userprofile.blade.php) or [`resources/views/livewire/admin/users/edit.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/admin/users/edit.blade.php), the active list is rendered instantly from RAM (< 1ms) rather than hitting MongoDB.
3. **Alphabetical Pre-Sorting**:
   - Results are pre-sorted in memory (`orderBy('designation', 'asc')`), removing MongoDB in-memory sorting overhead.

---

## 3. Lifecycle & Flush Events (Save & Update)

The cache is automatically invalidated whenever a designation document is created, edited, approved, or deleted:

```php
// app/Models/Designation.php

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
    Cache::forget('designations:default:list');
    Cache::forget('designations:default:list:p:1:l:20');
}
```

### Retrieval Implementation:
```php
public static function getAllActiveCached(): array
{
    return Cache::remember(self::CACHE_KEY_ACTIVE, now()->addDays(2), function (): array {
        return self::query()
            ->select(['_id', 'designation'])
            ->whereIn('status', [1, '1', true])
            ->whereNotNull('designation')
            ->where('designation', '!=', '')
            ->orderBy('designation', 'asc')
            ->get()
            ->map(fn ($r) => ['id' => (string) $r->_id, 'name' => trim((string) $r->designation)])
            ->values()
            ->toArray();
    });
}
```

---

## 4. Concurrency Pressure Analysis & Stampede Protection

### The Bottleneck Scenario:
- **What Happens**: If an administrator edits a designation during peak traffic hours, `clearActiveCache()` purges `designations:all_active_v1`.
- **Where the Pressure Happens**:
  - 200 users opening work experience modals at the exact same second will encounter a **Cache Miss**.
  - All 200 requests execute `Designation::query()->whereIn('status', ...)->orderBy('designation', 'asc')->get()` concurrently against MongoDB.
  - While MongoDB handles this via the compound index `{"status": 1, "designation": 1}`, 200 simultaneous PHP workers spend memory hydrating Eloquent collections and then issue 200 simultaneous `Cache::put()` operations to Redis.

### Architectural Solution:
Implement the **Atomic Double-Check Lock** pattern during cache rebuilds:

```php
public static function getAllActiveCachedSafe(): array
{
    $cached = Cache::get(self::CACHE_KEY_ACTIVE);
    if ($cached !== null) {
        return $cached;
    }

    // Only ONE worker acquires the lock to query MongoDB
    return Cache::lock('lock:' . self::CACHE_KEY_ACTIVE, 10)->block(3, function () {
        return Cache::remember(self::CACHE_KEY_ACTIVE, now()->addDays(2), function (): array {
            return self::query()
                ->select(['_id', 'designation'])
                ->whereIn('status', [1, '1', true])
                ->whereNotNull('designation')
                ->where('designation', '!=', '')
                ->orderBy('designation', 'asc')
                ->get()
                ->map(fn ($r) => ['id' => (string) $r->_id, 'name' => trim((string) $r->designation)])
                ->values()
                ->toArray();
        });
    });
}
```
