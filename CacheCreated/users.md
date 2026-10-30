# Cache Documentation: `users`

## Collection: `users`
**Eloquent Model**: [`App\Models\User`](file:///c:/Users/hp/Desktop/L12S9/app/Models/User.php)  
**Database**: MongoDB (`clonedata.users`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`users:publishers:active:list`** | Array of active verified academic publishers: `[['id' => string, 'name' => string, 'email' => string], ...]` | **1 Hour** (3,600 seconds) | Journal registration form, Publisher selector dropdown | Cleared on `User::forgetCachedPublishers()` |
| **`qa_active_contributors`** | Top active community contributors with answer counts and badges | **15 Minutes** (900 seconds) | Q&A Community Sidebar ([`question/index.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/question/index.blade.php)) | Auto-expires via TTL |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **Publishers List**:
  - `_id`: String MongoDB ID.
  - `first_name`, `last_name`, `fullname`: Formatted display name.
  - `email`: Publisher correspondence email.
  - `publisher_status`: Verified active publisher flag (`publisher_status ∈ [1, '1']`).
- **Active Contributors**:
  - `_id`, `slug`, `fullname`, `profile_picture`, answer count, upvotes count.

### Reason for Caching:
1. **Prevents Scanning Large `users` Collection**:
   - The `users` collection contains thousands of registered scholars and researchers.
   - Filtering for `publisher_status = 1` requires scanning or compound indexing across active user accounts.
2. **Fast Journal Setup Flow**:
   - When journal editors or authors register a journal, the publisher list is populated in **< 1ms** from Redis.

---

## 3. Lifecycle & Flush Events (Save & Update)

When an admin approves or revokes a publisher account:

```php
// app/Models/User.php

public const CACHE_KEY_PUBLISHERS = 'users:publishers:active:list';

public static function forgetCachedPublishers(): void
{
    Cache::forget(self::CACHE_KEY_PUBLISHERS);
}

public static function getInitialPublishers(int $limit = 20, ?string $selectedId = null): array
{
    $cached = Cache::get(self::CACHE_KEY_PUBLISHERS);

    if (is_array($cached) && ! empty($cached)) {
        $list = $cached;
    } else {
        $list = static::whereIn('status', [1, '1'])
            ->whereIn('publisher_status', [1, '1'])
            ->whereNotNull('slug')
            ->select('_id', 'first_name', 'last_name', 'fullname', 'email')
            ->limit(50)
            ->get()
            ->map(function ($u) {
                $fullName = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                if ($fullName === '' && ! empty($u->fullname)) {
                    $fullName = trim((string) $u->fullname);
                }

                return [
                    'id'    => (string) $u->_id,
                    'name'  => $fullName !== '' ? $fullName : 'Publisher',
                    'email' => (string) ($u->email ?? ''),
                ];
            })
            ->values()
            ->toArray();

        if (! empty($list)) {
            Cache::put(self::CACHE_KEY_PUBLISHERS, $list, 3600);
        }
    }

    return array_slice($list, 0, $limit);
}
```

---

## 4. Concurrency Pressure & Stampede Solution

### The Bottleneck:
If multiple users are registering journals simultaneously when the publisher cache expires, parallel queries will hit the `users` table.

### Solution:
Protect via Redis Mutex Lock:

```php
public static function getPublishersSafe(): array
{
    return Cache::lock('lock:' . self::CACHE_KEY_PUBLISHERS, 10)->block(3, function () {
        return self::getInitialPublishers();
    });
}
```
