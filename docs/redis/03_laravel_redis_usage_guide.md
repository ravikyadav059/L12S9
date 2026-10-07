# Laravel Redis Usage Guide & Code Patterns

## 1. High-Level Caching: Using the `Cache` Facade

The `Cache` facade is driver-agnostic. When `CACHE_STORE=redis`, all these operations run on Redis in RAM.

### A. Cache with Fallback (`Cache::remember`)
Fetches the cached value if present; otherwise executes the database query, stores it for 1 hour (3600s), and returns it:
```php
use Illuminate\Support\Facades\Cache;

$popularQuestions = Cache::remember('popular_questions_widget', 3600, function () {
    return Question::whereNotIn('status', [0, '0', false])
        ->orderByDesc('views_count')
        ->limit(5)
        ->get();
});
```

### B. Manually Storing & Retrieving Values
```php
// Store for 10 minutes
Cache::put('scholar_stats_' . $userId, $statsArray, now()->addMinutes(10));

// Retrieve value (returns null if expired or missing)
$stats = Cache::get('scholar_stats_' . $userId);

// Delete specific cache key
Cache::forget('scholar_stats_' . $userId);

// Clear entire application cache
Cache::flush();
```

### C. Cache Tagging (Exclusive to Redis & Memcached)
Group related cache keys together so they can be invalidated in one operation:
```php
// Cache questions tagged under 'questions_feed'
Cache::tags(['questions_feed'])->put('feed_page_1', $questions, 1800);

// Invalidate all caches tagged with 'questions_feed' when a new question is published
Cache::tags(['questions_feed'])->flush();
```

---

## 2. Low-Level Power: Using the `Redis` Facade (Direct Redis Commands)

When you need advanced data structures (like Sets for User Bookmarks or Followers), use the `Redis` facade directly:

```php
use Illuminate\Support\Facades\Redis;
```

### A. Working with Sets (Bookmarks, Followers, Followed Questions)
Redis Sets are unordered collections of unique elements. Perfect for IDs.

```php
$userId = '67bc8a1b249227590c057070';
$questionId = '6ac39b3e249227590c057073';
$key = "user:{$userId}:saved_questions";

// 1. Add question ID to Set (SADD)
Redis::sadd($key, $questionId);

// 2. Check if question ID is in Set (SISMEMBER -> returns 1 if true, 0 if false)
$isSaved = (bool) Redis::sismember($key, $questionId);

// 3. Remove question ID from Set (SREM)
Redis::srem($key, $questionId);

// 4. Get all saved question IDs (SMEMBERS -> returns array of strings)
$savedQuestionIds = Redis::smembers($key);

// 5. Total count of saved items (SCARD)
$totalSaved = Redis::scard($key);
```

### B. Atomic Counters (Instant Views & Upvote Counters)
```php
$viewsKey = "question:{$questionId}:views";

// Increment view count by +1 atomically
$currentViews = Redis::incr($viewsKey);

// Increment by custom number
Redis::incrby($viewsKey, 5);
```

### C. Hashes (Object-like Storage)
```php
$userProfileKey = "user:{$userId}:profile";

// Store multiple fields
Redis::hset($userProfileKey, [
    'name' => 'Dr. Jane Smith',
    'score' => 4500,
    'role' => 'Reviewer',
]);

// Retrieve single field
$name = Redis::hget($userProfileKey, 'name');

// Retrieve all fields as array
$profile = Redis::hgetall($userProfileKey);
```

---

## 3. How to Inspect Your Data in Redis Insight

1. Open **Redis Insight** on your computer.
2. Click **"Add Redis Database"** -> Host: `127.0.0.1`, Port: `6379`, Name: `Scholar9 Local`.
3. Click on the connected database:
   - You will see the key tree (e.g. `laravel_cache:...`, `user:...:saved_questions`).
   - Click on any key to inspect its current memory consumption, value, TTL, and data type.
   - You can edit, delete, or add test keys directly in the visual editor.
