# Cache Documentation: `reviewer_profile`

## Collection: `reviewer_profile`
**Eloquent Model**: [`App\Models\ReviewerProfile`](file:///c:/Users/hp/Desktop/L12S9/app/Models/ReviewerProfile.php)  
**Database**: MongoDB (`clonedata.reviewer_profile`)

---

## 1. Cache Summary & Keys

| Cache Key Pattern | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **Compiled in `org_metrics_v6_{orgId}`** | Aggregated publication and conference IDs linked to affiliated scholars | **30 Minutes** (1,800s) | Institution Detail & Directory Metrics | Auto-expires via TTL (1,800s) |
| **`scholar_profile_metrics_{userId}`** | Cached scholar publication count, h-index, and citation stats | **1 Hour** (3,600s) | Public Scholar Profile ([`/profile/{slug}`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/userprofile.blade.php)) | Flushed on new paper submission or edit |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`user_id`**: Reference to `users._id`.
- **`publication`**: Multikey array of `publications._id` references.
- **`seminar`**: Multikey array of conference & seminar references.
- **`RoleInResearchJournal`**: Multikey array of journal appointments.
- **`active_publication_count`**: Integer count of active articles.

### Reason for Caching:
1. **Multikey Array Traversal Cost**:
   - In MongoDB, traversing nested arrays across thousands of reviewer profiles to sum paper citations is CPU-intensive.
   - Caching pre-calculated counts prevents repeated `$unwind` and multikey scans.

---

## 3. Concurrency Pressure & Stampede Solution

### The Bottleneck:
High-profile scholar pages (e.g. university department heads with hundreds of papers) can experience sudden traffic spikes. If cache expires during traffic, parallel requests will execute heavy array operations.

### Solution:
Employ **Atomic Mutex Locking** (`Cache::lock`) to ensure only one worker calculates reviewer profile metrics.
