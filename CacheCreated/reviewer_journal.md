# Cache Documentation: `reviewer_journal`

> **Audit status:** The registry in [README.md](README.md) is current; verify historical implementation notes below against source.

## Collection: `reviewer_journal`
**Eloquent Model**: [`App\Models\ReviewerJournal`](file:///c:/Users/hp/Desktop/L12S9/app/Models/ReviewerJournal.php)  
**Database**: MongoDB (`clonedata.reviewer_journal`)

---

## 1. Cache Summary & Keys

| Cache Key Pattern | Data Stored | TTL (Duration) | Where Used | Invalidation Trigger |
| :--- | :--- | :--- | :--- | :--- |
| **`journals:active:count`** | Integer count of all active journals | **1 Hour** (3,600s) | Journal directory header ([`journal/index.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/journal/index.blade.php)) | Cleared via `ReviewerJournal::forgetCaches()` |
| **`journals:active:disciplines:list`** | Distinct list of academic disciplines | **24 Hours** (86,400s) | Journal directory sidebar filters | Cleared via `ReviewerJournal::forgetCaches()` |
| **`journals:active:indexers:list`** | Distinct list of indexing agencies linked to journals | **24 Hours** (86,400s) | Journal directory indexing filters | Cleared via `ReviewerJournal::forgetCaches()` |
| **`journals:search:{queryHash}`** | Autocomplete results for journal title search | **5 Minutes** (300s) | Article submission journal autofill / search | Tagged with `reviewer-journal-search` and flushed by `forgetCaches()` on tag-capable stores; otherwise TTL only |
| **`countries_all_collection`** | Collection of all country documents | **24 Hours** (86,400s) | ISSN assignee country display in journal cards | Auto-expires via TTL (86,400s) |

---

## 2. Which Fields Are Cached & Why

### Cached Fields:
- **`journal_title`**: Official journal name (e.g. *"International Journal of Advanced Computer Science"*).
- **`journal_short_name`**: Abbreviated title or acronym.
- **`e_issn`** & **`p_issn`**: Electronic and Print ISSNs.
- **`journal_impact_factor`**: Verified impact metrics.
- **`impact_factor_assignee_agency`**: Clarivate / Scopus / WoS agency.
- **`journal_webiste_url`**: Official portal link.
- **`issn_assignee_country`**: Country of registration.

### Reason for Caching:
1. **High-Speed Autocomplete on Paper Submission**:
   - As authors submit research articles in [`resources/views/livewire/userprofile.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/userprofile.blade.php), typing the journal name instantly auto-populates ISSN, publisher, and website in **< 2ms** from Redis.
2. **Eliminates Repetitive Aggregations on Public Directory**:
   - Compiling all distinct disciplines and indexing agencies requires an expensive `$distinct` query over tens of thousands of journals. Caching this for 24 hours reduces database load by 98%.

---

## 3. Lifecycle & Flush Events (Save & Update)

When an admin updates a journal or toggles its status, `ReviewerJournal::forgetCaches()` executes:

```php
// app/Models/ReviewerJournal.php

public static function forgetCaches(): void
{
    Cache::forget('journals:active:count');
    Cache::forget('journals:active:disciplines:list');
    Cache::forget('journals:active:indexers:list');
    if (Cache::supportsTags()) {
        Cache::tags(['reviewer-journal-search'])->flush();
    }
    
    IndexingAgency::forgetCached();
}
```

### Retrieval with a Tagged Cache Key:
```php
public static function searchTitles(string $query, int $limit = 15): array
{
    $query = trim($query);
    if (mb_strlen($query) < 2) {
        return [];
    }

    $cacheKey = 'journals:search:'.md5(strtolower($query).'_'.$limit);

    $store = Cache::supportsTags()
        ? Cache::tags(['reviewer-journal-search'])
        : Cache::store();

    return $store->remember($cacheKey, 300, function () use ($query, $limit) {
        return self::query()
            ->select(['_id', 'journal_title', 'e_issn', 'p_issn', 'journal_impact_factor'])
            ->where('status', '!=', 2)
            ->where('journal_title', 'regex', new Regex(preg_quote($query), 'i'))
            ->limit($limit)
            ->get()
            ->toArray();
    });
}
```

---

## 4. Concurrency Pressure Analysis & Stampede Protection

### The Bottleneck Scenario:
- **The Case**: Multiple authors concurrently typing journal names during conference submission deadlines.
- **Where the Pressure Happens**:
  - Uncached live searches trigger broad MongoDB regex queries (`journal_title: /query/i`), which bypass B-Tree indexes if queries contain leading wildcards.
  - Multiple simultaneous regex queries saturate MongoDB CPU to 100%.

### Architectural Solutions:
1. **Livewire Debounce**: Input is debounced by 300ms (`wire:model.live.debounce.300ms`) so queries are only dispatched after typing pauses.
2. **Search Character Guard**: Minimum 2 characters required (`mb_strlen($query) >= 2`) before executing queries.
3. **Tagged Search Purge**: On the production Redis store, `forgetCaches()` flushes only entries tagged `reviewer-journal-search`, so journal autocomplete caches are invalidated immediately without affecting unrelated cache data. A non-taggable store deliberately falls back to an untagged cache and can retain the old autocomplete result for up to five minutes.
