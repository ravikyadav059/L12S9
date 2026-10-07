# Laravel Configuration & Environment Setup for Redis

## 1. Environment Variables (`.env`)

In your Laravel `.env` file, the following variables configure caching, sessions, and Redis connections:

```env
# Cache Store (Options: file, redis, database, array)
CACHE_STORE=file

# Session Driver (Options: file, redis, database, cookie)
SESSION_DRIVER=file

# Queue Connection (Options: sync, redis, database)
QUEUE_CONNECTION=sync

# Redis Connection Settings
REDIS_CLIENT=predis       # Or phpredis (if php redis extension dll is installed)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CACHE_DB=1
REDIS_DB=0
```

---

## 2. What happens when changing `CACHE_STORE=file` to `CACHE_STORE=redis`?

### ❓ What gets affected:
1. **Application Cache (`Cache::get`, `Cache::put`, `Cache::remember`)**:
   - Instead of writing thousands of small cache files inside `storage/framework/cache/data/`, Laravel writes directly to **Memurai / Redis RAM**.
   - Cache reads and writes become **10x to 50x faster** because RAM memory has 0ms disk I/O latency.
2. **Rate Limiters & Throttling**:
   - Login rate limiters and API request throttlers store attempt counts in Redis keys (with automatic millisecond expiration).
3. **Tagged Caching (`Cache::tags(['questions'])`)**:
   - The `file` driver does NOT support cache tags (`BadMethodCallException`).
   - The `redis` driver **fully supports cache tagging and selective flushing**.

### ❓ What happens to old file caches:
- The old files in `storage/framework/cache/data/` are simply ignored. Nothing is deleted or broken.
- You can clear them anytime via `php artisan cache:clear`.

### ⚠️ What will STOP working if Memurai/Redis is NOT running:
- If `CACHE_STORE=redis` is set in `.env` but Memurai/Redis is stopped or not running on port `6379`, any code executing `Cache::...` will throw a **Connection Exception**:
  ```
  Connection refused [tcp://127.0.0.1:6379]
  ```
- **Rule of Thumb:** Keep Memurai running as a background service before switching `CACHE_STORE=redis`.

---

## 3. Redis Client Drivers in Laravel: `predis` vs `phpredis`

Laravel supports two client drivers:

1. **`predis` (Pure PHP Package - Recommended for Easy Setup on Windows)**:
   - Installed via Composer: `composer require predis/predis`.
   - Requires NO special C/C++ `.dll` extension installation in `php.ini`.
   - Set in `.env`: `REDIS_CLIENT=predis`.

2. **`phpredis` (Compiled C Extension)**:
   - Faster performance in high-load production benchmarks.
   - Requires downloading the matching PHP version `.dll` for Windows and enabling `extension=redis` in `php.ini`.
   - Set in `.env`: `REDIS_CLIENT=phpredis`.

---

## 4. Separation of Redis Databases

By default, Redis has 16 logical databases (numbered `0` through `15`).
Laravel's `config/database.php` automatically separates concerns:
- **DB 0 (`default`)**: Used for direct `Redis::` facade commands and models.
- **DB 1 (`cache`)**: Used for `Cache::` operations (prevents `Cache::flush()` from wiping your session or queue data).
