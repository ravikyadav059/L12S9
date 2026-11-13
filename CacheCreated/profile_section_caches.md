# Per-Profile Section Caches

| Key pattern | TTL | Data / use | Invalidation |
| --- | --- | --- | --- |
| `awards:user:{id}:{active|all}` | 24 hours | Awards section | `Award` save/delete |
| `membership:user:{id}:{active|all}` | 24 hours | Membership section | `Membership` save/delete |
| `phd:user:{id}:{active|all}` | 24 hours | Thesis/PhD section | `Phd` save/delete |
| `invited_position:user:{id}:{active|all}` | 24 hours | Invited-position section | `InvitedPosition` save/delete |
| `patent:user:{id}:{active|all}` | 24 hours | Patent section | `Patent` save/delete |
| `publication:user:{id}:{active|all}` | 2 hours | Publications, journal/type relations, author enrichment | `Publication` save/delete for owner and registered co-authors |
| `role_in_research_journals:user:{id}:{active|all}` | 24 hours | Journal-role section | `RoleInResearchJournals` save/delete |

The ID belongs to the profile subject, not the visitor. All visitors to one profile safely reuse the same section data; a different profile always has another key. `active` isolates public/status-filtered data from `all` data.

At 100k warm requests to one profile, MongoDB section queries are avoided but every HTTP/Livewire response is still rendered. At a cold miss, the current code can run the same query many times because it has no lock or stale fallback. At 100k different profiles, keys warm independently and Redis memory/MongoDB concurrency become the main risk.
