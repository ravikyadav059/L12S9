# Cache Registry and High-Traffic Q&A

This is the source-of-truth cache registry for the current working tree, refreshed on 13 November 2026. It records implemented cache calls, not proposed patterns.

## Runtime facts

| Question | Answer |
| --- | --- |
| Active store | `.env` sets `CACHE_STORE=redis`; all audited calls use Laravel's default `Cache` store. |
| Full-page cache? | No. These are data/query fragments. Every request still runs Laravel, Livewire, authorization where applicable, and Blade rendering. |
| Scope | Public keys are shared. Profile keys include the target user's ID, so all viewers of one profile share data but different profiles are isolated. |
| Stampede protection | Institution detail (`institution/show`) and directory cards (`institution/index`) use `Organization::getMetrics()` / `Organization::getMetricsWithLock()` for atomic mutex stampede protection. Other cache families currently use standard `remember`/`get`/`put` without active locks or `Cache::flexible`. |

## Current cache families

| Family | Keys / patterns | TTL | Used for | Detail |
| --- | --- | --- | --- | --- |
| Lookups | `designations:active`, `degree-types:active`, `fields-of-study:active`, `grades:active`, `categories:active`, `indexing_agencies:active:list`, `journal_roles:active:list` | 2 days, 7 days, or 24 hours | Form and journal selects | [lookup_caches.md](lookup_caches.md) |
| Location | `countries:all:list`, `countries_all_collection`, `states_lookup_{countryId}`, `cities_lookup_{stateId}`, `tz_country_iso_{timezone}`, `tz_country_model_{iso2}` | 24 hours or 30 days | Location and timezone lookups | [lookup_caches.md](lookup_caches.md) |
| Institutions | `organizations:default:list:p:{page}:l:{limit}`, `organizations:search:{queryHash}:p:{page}:l:{limit}`, `organizations:metrics:{id}`, `country_item_raw_*`, and directory location maps | 5 minutes to 24 hours | Autocomplete, directory, public profile | [organization.md](organization.md) |
| Journals | `journals:active:count`, `journals:active:disciplines:list`, `journals:active:indexers:list`, `journals:search:{queryHash}` | 5 minutes to 24 hours | Directory, filters, autocomplete | [reviewer_journal.md](reviewer_journal.md) |
| Q&A | `qa_community_stats`, `qa_sidebar_community_stats`, `qa_popular_questions`, `qa_popular_questions_detail`, `qa_active_contributors`, `qa_available_skills`, `admin_questions_counts` | 60 seconds to 30 minutes | Q&A pages and admin list | [question.md](question.md) |
| Profile sections | `awards`, `membership`, `phd`, `invited_position`, `patent`, `publication`, `role_in_research_journals` keys in `:user:{id}:{active|all}` form | 2 or 24 hours | Public profile and profile editor | [profile_section_caches.md](profile_section_caches.md) |
| Services | `authors:search:{queryHash}`, `link_preview_{urlHash}` | 5 minutes; previews: 24 hours on success / 60 seconds on failure | Author autocomplete, URL previews | [service_caches.md](service_caches.md) |
| Admin diagnostics | `db_fixing_*_issues`, `orphan_journal_missing_stats` | 60 seconds or 5 minutes | Database-fixing Livewire screens | [admin_diagnostic_caches.md](admin_diagnostic_caches.md) |

`journal/index.blade copy.php` defines separately named `journals:all:*` caches for its all-status data, but is excluded from this active registry unless it is deliberately routed or included.

## Questions and answers

### What benefit does a warm cache provide?

It avoids repeat MongoDB reads, sorts, joins, aggregate counts, and outbound link-preview HTTP requests. For example, all viewers of profile 42 reuse `publication:user:42:active`; this is data reuse, not a cached full HTTP response.

### What if 100,000 users open the same page while its cache is warm?

MongoDB does not repeat the cached query 100,000 times. Redis receives those reads, while the web tier still renders 100,000 responses. Redis network/CPU, PHP/Livewire workers, connection limits, and response bandwidth must therefore still be sized for that load.

### What if the 100,000 users arrive on a cold or expired key?

This is the present risk. `Cache::remember` does not coalesce concurrent cache misses. Many workers can run the same callback and race to write the same value: a cache stampede. The highest-risk keys are institution metrics, journal/author autocomplete, Q&A aggregates, link previews, and enriched profile sections.

### What if users open different pages or different profiles?

Each key warms independently. This distributes a same-key stampede but raises total Redis memory use and MongoDB concurrency. Per-profile keys are intentionally high-cardinality: 100,000 distinct profiles may create 100,000 separate entries per section. The user ID prevents one profile's data from being served to another.

### What happens after a profile update?

Model events forget the changed user's `active` and `all` section variants. Publication changes also forget registered co-author variants. The next request rebuilds the data; concurrent rebuilds are currently unprotected.

### What should be added for sustained 100k-scale traffic?

Use a shared Redis deployment on every web node, full-page/CDN caching for genuinely public responses, short `Cache::lock` protection with a second cache check, or Laravel `Cache::flexible` stale-while-revalidate. Add TTL jitter, debounce/cap search, warm expensive keys after writes, and monitor Redis hit/miss/eviction, MongoDB latency, PHP worker saturation, and p95/p99 HTTP latency. These are recommendations, not current implementation.

## New registry files

- [lookup_caches.md](lookup_caches.md): previously scattered lookup, location, and timezone entries.
- [profile_section_caches.md](profile_section_caches.md): previously undocumented per-profile caches.
- [service_caches.md](service_caches.md): previously undocumented author-search and link-preview caches.
- [admin_diagnostic_caches.md](admin_diagnostic_caches.md): all database-fixing diagnostic caches.
- [traffic_and_concurrency_qa.md](traffic_and_concurrency_qa.md): detailed 100k-traffic operational answers.
- [cache_understanding_guide.md](cache_understanding_guide.md): a reusable guide for designing safe caches in future projects.
