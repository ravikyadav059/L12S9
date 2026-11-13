# Admin Database-Fixing Diagnostic Caches

All values below are used by the named admin database-fixing Livewire screens. They avoid repeating inconsistency scans on pagination/filtering, not public traffic. Every `db_fixing_*` entry has a 60-second TTL and is forgotten after its relevant fix action; `orphan_journal_missing_stats` has a five-minute TTL and is forgotten after the batch fix.

| Keys |
| --- |
| `db_fixing_award_issues`, `db_fixing_education_issues`, `db_fixing_exp_issues`, `db_fixing_membership_issues`, `db_fixing_membership_org_issues` |
| `db_fixing_invited_position_profile_issues`, `db_fixing_invited_position_org_issues`, `db_fixing_patent_issues`, `db_fixing_patent_category_issues` |
| `db_fixing_phd_issues`, `db_fixing_publication_profile_issues`, `db_fixing_role_journal_profile_issues`, `db_fixing_seminar_issues`, `orphan_journal_missing_stats` |

Concurrent admin misses can repeat the diagnostic query because no lock is currently used. Short TTLs prioritise operational freshness.
