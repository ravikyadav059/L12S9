# Lookup, Location, and Time-Zone Caches

| Key / pattern | TTL | Used by | Invalidation |
| --- | --- | --- | --- |
| `designations:active` | 2 days | Designation lookup | `Designation` save/delete |
| `degree-types:active` | 2 days | Degree-type lookup | `DegreeType` save/delete |
| `fields-of-study:active` | 2 days | Field-of-study lookup | `FieldOfStudy` save/delete |
| `grades:active` | 7 days | Grade lookup | `Grade` save/delete |
| `categories:active` | 7 days | Patent category lookup | `Category` save/delete |
| `indexing_agencies:active` (or `:list`) | 24 hours | Journal lookup | `IndexingAgency` save/delete |
| `indexing_agencies_active_all` | 24 hours | Journal detail | `IndexingAgency` save/delete |
| `journal_roles:active` (or `:list`) | 24 hours | Journal-role lookup | `JournalRole` save/delete |
| `countries:all` (or `:list`) | 24 hours | Country lookup | `Country` save/delete |
| `countries_all_collection` | 24 hours | Journal country lookup | TTL only |
| `states_lookup_{countryId}` | 24 hours | State lookup | `State` save/delete |
| `cities_lookup_{stateId}` | 24 hours | City lookup | `City` save/delete |
| `tz_country_iso_{timezone}` | 30 days | Timezone-to-country ISO lookup | TTL only |
| `tz_country_model_{iso2}` | 30 days | ISO-to-country lookup | TTL only |

These shared values reduce repeat collection reads for forms. A warm key is served to all users. Dependent country, state, and city keys now invalidate their respective parent lookup caches on model save/delete.
