# Cache Documentation: `skills`

## Collection: `skills`
**Eloquent Model**: [`App\Models\Skill`](file:///c:/Users/hp/Desktop/L12S9/app/Models/Skill.php)  
**Database**: MongoDB (`clonedata.skills`)

---

## 1. Cache Summary & Keys

| Cache Key | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`qa_available_skills_v2`** | List of all approved skill tags for question tagging and filtering | **30 Mins** (1,800s) to **1 Hour** (3,600s) | Q&A Forum Tag Filters, Question Ask Modal | Auto-expires via TTL |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`_id`**: String MongoDB ID.
- **`skills_title`**: Name of the technical skill or research topic (e.g. *"Machine Learning"*, *"CRISPR"*, *"Quantum Computing"*).
- **`slug`**: URL slug.
- **`skills_status`**: Active status filter (`skills_status ∈ [1, '1']`).

### Reason for Caching:
1. **Instant Tag Autocomplete**:
   - As community members post questions or add skills to their profile, typing tag names searches against this cached dictionary with zero database latency.
2. **Pre-sorted Alphabetical Order**:
   - Eliminates in-memory sorting penalties in MongoDB.

---

## 3. Lifecycle & Flush Events (Save & Update)

```php
// resources/views/livewire/question/index.blade.php
$availableSkills = Cache::remember('qa_available_skills_v2', 1800, function () {
    return Skill::query()
        ->whereIn('skills_status', [1, '1'])
        ->select(['_id', 'skills_title', 'slug'])
        ->orderBy('skills_title', 'asc')
        ->get()
        ->map(fn ($s) => [
            'id'    => (string) $s->_id,
            'title' => (string) $s->skills_title,
            'slug'  => (string) $s->slug,
        ])
        ->toArray();
});
```

---

## 4. Concurrency Pressure & Stampede Solution

### Solution:
Utilize Laravel's **`Cache::lock`** mutex pattern:

```php
public static function getAvailableSkillsSafe(): array
{
    return Cache::lock('lock:qa_available_skills_v2', 10)->block(3, function () {
        return Cache::remember('qa_available_skills_v2', 1800, function () {
            return Skill::whereIn('skills_status', [1, '1'])
                ->orderBy('skills_title', 'asc')
                ->get(['_id', 'skills_title', 'slug'])
                ->toArray();
        });
    });
}
```
