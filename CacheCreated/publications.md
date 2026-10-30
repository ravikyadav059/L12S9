# Cache Documentation: `publications`

## Collection: `publications`
**Eloquent Model**: [`App\Models\Publication`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Publication.php)  
**Database**: MongoDB (`clonedata.publications`)

---

## 1. Cache Summary & Keys

| Cache Key Pattern | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`orphan_journal_missing_stats`** | Count of publications with unresolved `journal_title` foreign keys | **5 Minutes** (300 seconds) | Admin Database Fixing ([`orphan-journal.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/admin/database-fixing/publication/orphan-journal.blade.php)) | Cleared via `Cache::forget('orphan_journal_missing_stats')` on batch fix |
| **`link_preview_{md5_url}`** | OpenGraph & DOI metadata for article URLs | **24 Hours** (86,400 seconds) | Publication submission link preview card | Auto-expires via TTL |
| **Compiled in `org_metrics_v6_{id}`** | Aggregated publication count & citation totals per institution | **30 Minutes** (1,800 seconds) | Public Institution Profile & Directory | Auto-expires via TTL (1,800s) |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`citations`**: Cumulative citation count across papers.
- **`journal_title`**: Reference to `reviewer_journal._id`.
- **`status`**: Active publication status (`status ∈ [1, '1']`).
- **`url`, `doi`**: External paper identifiers and preview metadata.

### Reason for Caching:
1. **Massive Collection Scale**:
   - The `publications` collection is the largest dataset in the application, containing hundreds of thousands to millions of research articles.
   - Live calculations like summing total citations (`Publication::whereIn('_id', $ids)->sum('citations')`) across hundreds of affiliated scholars require substantial disk I/O.
   - Caching aggregated metrics shields MongoDB from repetitive scans.

---

## 3. Lifecycle & Flush Events (Save & Update)

```php
// resources/views/livewire/admin/database-fixing/publication/orphan-journal.blade.php
public function executeFix(): void
{
    // ... batch update publication records ...
    Cache::forget('orphan_journal_missing_stats');
}

public function getStatsProperty()
{
    return Cache::remember('orphan_journal_missing_stats', 300, function () {
        return [
            'total_publications' => Publication::count(),
            'missing_journals'   => Publication::whereNull('journal_title')->count(),
        ];
    });
}
```

---

## 4. Concurrency Pressure & Stampede Solution

### The Bottleneck:
If multiple administrators run database diagnostic tools or institution metric pages simultaneously without cache, MongoDB scans millions of publication records concurrently, causing query timeouts.

### Solution:
Use **Atomic Mutex Locks** (`Cache::lock`) and batch cursor aggregation pipelines (`$in` chunking with max 1,000 IDs per batch) to prevent memory saturation.
