# Cache Documentation: `question`

## Collection: `question`
**Eloquent Model**: [`App\Models\Question`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Question.php)  
**Database**: MongoDB (`clonedata.question`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`qa_popular_questions`** | Top 5 most voted and viewed questions | **15 Minutes** (900 seconds) | Q&A Forum Home Sidebar ([`question/index.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/question/index.blade.php)) | Auto-expires via TTL |
| **`qa_popular_questions_detail`** | Trending questions for article sidebars | **15 Minutes** (900 seconds) | Question Detail Sidebar ([`question/show.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/question/show.blade.php)) | Auto-expires via TTL |
| **`qa_community_stats`** | Aggregated counts: total questions, total answers, answered questions | **15 Minutes** (900 seconds) | Q&A Community Header & Stat Widgets | Auto-expires via TTL |
| **`qa_sidebar_community_stats`** | Compact stats widget for question detail view | **10 Minutes** (600 seconds) | Question Detail Sidebar | Auto-expires via TTL |
| **`admin_questions_counts`** | Counts of questions grouped by status (active, pending, reported) | **60 Seconds** (1 minute) | Admin Questions Index ([`admin/questions/index.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/admin/questions/index.blade.php)) | Flushed on admin status change via `Cache::forget()` |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`title`**: Question headline string.
- **`slug`**: SEO-friendly routing slug (`/question/{slug}`).
- **`votes_count`**: Net votes (upvotes - downvotes).
- **`views_count`**: Total visitor impressions.
- **`answer_count`**: Total responses received.
- **`tags`**: Multikey array of skill tags.

### Reason for Caching:
1. **High-Frequency Page Views**:
   - The Q&A section receives high concurrent traffic from students and researchers reading discussion threads.
   - Re-computing total platform stats (`Question::count()`, `Answer::count()`) on every page view would crush MongoDB CPU.
2. **Decouples High-Velocity Interactions from Aggregations**:
   - Upvotes and views are incremented frequently. Caching the popular sidebar for 15 minutes prevents real-time counter updates from invalidating the entire page cache.

---

## 3. Lifecycle & Flush Events (Save & Update)

```php
// Admin actions clear the admin counters immediately:
// resources/views/livewire/admin/questions/index.blade.php
public function updateStatus(string $id, int $newStatus): void
{
    Question::where('_id', $id)->update(['status' => $newStatus]);
    Cache::forget('admin_questions_counts');
}

// Public stats use high-scale Cache::remember:
// resources/views/livewire/question/index.blade.php
$popularQuestions = Cache::remember('qa_popular_questions', 900, function () {
    return Question::where('status', 1)
        ->orderBy('votes_count', 'desc')
        ->orderBy('views_count', 'desc')
        ->take(5)
        ->get(['_id', 'title', 'slug', 'votes_count', 'answer_count', 'views_count']);
});
```

---

## 4. Concurrency Pressure & Stampede Solution

### The Bottleneck Scenario:
- **The Case**: When a trending question goes viral, thousands of concurrent users view the forum at the exact moment the 15-minute `qa_popular_questions` key expires.
- **Where the Pressure Happens**:
  - Multiple parallel MongoDB aggregation queries sorting thousands of questions by `votes_count` and `views_count`.
  - Temporary lock contention on the `question` collection.

### Architectural Solution:
Implement **Stale-While-Revalidate** or an **Atomic Mutex Lock**:

```php
public static function getPopularQuestionsSafe(): mixed
{
    return Cache::lock('lock:qa_popular_questions', 10)->block(2, function () {
        return Cache::remember('qa_popular_questions', 900, function () {
            return Question::where('status', 1)
                ->orderBy('votes_count', 'desc')
                ->take(5)
                ->get(['_id', 'title', 'slug', 'votes_count', 'answer_count', 'views_count']);
        });
    });
}
```
