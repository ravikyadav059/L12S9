# Redis Troubleshooting & Frequently Asked Questions (FAQ)

## 1. Quick Connection Test

To test whether Laravel can talk to your Memurai / Redis instance:

### Option A: Using Tinker
Run in terminal:
```bash
php artisan tinker --execute="dump(Illuminate\Support\Facades\Redis::ping());"
```
- **Expected Output:** `"+PONG"` or `true`.
- If it throws `Connection refused`, Memurai is not running on port `6379`.

### Option B: Using Artisan Cache Commands
```bash
# Store a test value in cache
php artisan tinker --execute="Illuminate\Support\Facades\Cache::put('test_key', 'hello_redis', 60);"

# Retrieve it
php artisan tinker --execute="dump(Illuminate\Support\Facades\Cache::get('test_key'));"
```

---

## 2. Common Errors & Solutions

### Error 1: `Class "Redis" not found` or `Please install the predis package`
* **Cause:** `.env` is set to `REDIS_CLIENT=predis` or `phpredis`, but the client package/extension is missing.
* **Fix:**
  - Install Predis: `composer require predis/predis`
  - In `.env`: `REDIS_CLIENT=predis`

---

### Error 2: `Connection refused [tcp://127.0.0.1:6379]`
* **Cause:** The Memurai service on Windows is stopped.
* **Fix:**
  - Open PowerShell as Administrator and run:
    ```powershell
    net start memurai
    ```
  - Or check Windows Services (`services.msc`), find **Memurai**, and click **Start**.

---

### Error 3: `WRONGTYPE Operation against a key holding the wrong kind of value`
* **Cause:** You attempted to run a Set command (like `SADD`) on a key that was already created as a String (e.g. via `Cache::put`).
* **Fix:**
  - In Redis Insight or CLI, delete the offending key: `DEL <key_name>`
  - Or flush the cache database: `php artisan cache:clear`.

---

## 3. Best Practices & Standing Rules

1. **Always ask the User first before introducing Redis into any new model, controller, or Livewire component.**
2. **Never store authoritative relational data purely in Redis** without a database backup in MongoDB. Redis is an in-memory acceleration layer.
3. **Always set a TTL (expiration)** for standard cache keys (`Cache::put($key, $val, $seconds)` or `Redis::expire($key, $seconds)`) to prevent unbounded RAM growth.
4. **Use clear prefix namespaces for Redis keys** (e.g., `user:{id}:saved_questions`, `question:{id}:views_counter`).
