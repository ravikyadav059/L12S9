# Cache Documentation: `experience`

> **Audit status:** The registry in [README.md](README.md) is current; verify historical implementation notes below against source.

## Collection: `experience`
**Eloquent Model**: [`App\Models\Experience`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Experience.php)  
**Database**: MongoDB (`clonedata.experience`)

---

## 1. Cache Summary & Keys

| Cache Key Pattern | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **Compiled in `organizations:metrics:{orgId}`** | Current faculty scholar IDs (`current_organization = 1`) and alumni user IDs | **30 Minutes** (1,800s) | Institution Detail & Directory Metrics | Auto-expires via TTL (1,800s) |
| **User Profile Experience List** | Cached array of experience timeline entries for public scholar profiles | **1 Hour** (3,600s) | Scholar Public Profile ([`/profile/{slug}`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/userprofile.blade.php)) | Cleared when user saves or deletes an experience record |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`organization_id`**: String MongoDB ID linking to `organization._id`.
- **`designation`**: Reference to `designation._id` or custom job title.
- **`current_organization`**: Boolean/Integer flag (`1` = Current Scholar, `0` = Alumnus).
- **`user_id`**: Reference to `users._id`.
- **`status`**: Active status filter (`status ∈ [1, '1']`).

### Reason for Caching:
1. **Critical Bridge in Institution Affiliation**:
   - `experience` acts as the junction collection between `users` and `organizations`.
   - To determine which faculty members belong to Harvard or Stanford, the application must query `experience.organization_id`.
   - Caching these affiliated user IDs inside `organizations:metrics:{orgId}` avoids querying tens of thousands of work experience documents on every institution page load.

---

## 3. Lifecycle & Flush Events (Save & Update)

When a user adds or updates their work experience:
```php
// app/Models/Experience.php
protected static function booted(): void
{
    static::saved(function ($model) {
        if (! empty($model->organization_id)) {
            // Invalidate metrics cache for the associated organization
            Cache::forget('organizations:metrics:' . (string) $model->organization_id);
        }
    });

    static::deleted(function ($model) {
        if (! empty($model->organization_id)) {
            Cache::forget('organizations:metrics:' . (string) $model->organization_id);
        }
    });
}
```

---

## 4. Concurrency Pressure & Stampede Solution

### Solution:
When updating an experience record, use asynchronous queue jobs to re-warm `organizations:metrics:{orgId}` so institution visitors never hit a cold cache miss.
