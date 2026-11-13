# Security Review — Index

Security review of the current working tree (branch `journaladmin`), done on **13 November 2026** by reading the code section by section. Each file in this folder covers one part of the application and lists its issues one by one with location, problem, attack scenario and fix. Every file ends with a **cross-check log** describing how each finding was re-verified.

Stack reviewed: Laravel 12.67, Livewire 4.4 + Volt 1.11, Flux, MongoDB (`mongodb/laravel-mongodb` 5.9), Redis cache, Reverb.

## Files

| File | Section | Findings |
| --- | --- | --- |
| [profile_sections.md](profile_sections.md) | Public profile page and its 13 editable sections | PRF-01 … PRF-08 |
| [publications_and_articles.md](publications_and_articles.md) | Publications, public article deposit, article pages | PUB-01 … PUB-09 |
| [questions_and_answers.md](questions_and_answers.md) | Q&A list, question page, answers, comments, votes | QA-01 … QA-09 |
| [journals_and_institutions.md](journals_and_institutions.md) | Journals, journal roles, certificates, institutions, verification service | JRN-01 … JRN-06 |
| [admin_panel_and_authorization.md](admin_panel_and_authorization.md) | `/admin`, impersonation, admin role model, DB fixers | ADM-01 … ADM-10 |
| [public_api_endpoints.md](public_api_endpoints.md) | JSON routes in `routes/web.php`, link preview | API-01 … API-05 |
| [authentication_and_accounts.md](authentication_and_accounts.md) | Login, register, password, email, settings | AUTH-01 … AUTH-08 |
| [configuration_and_infrastructure.md](configuration_and_infrastructure.md) | `.env`, session, headers, upload folders, Livewire-wide issues | CFG-01 … CFG-09 |
| [dependencies.md](dependencies.md) | `composer audit` / `npm audit` results | DEP-01, DEP-02 |

## Severity scale

- **Critical** — exploitable by anyone (often without login) to take over content/accounts of other users or run script for all visitors.
- **High** — serious impact, needs a normal account, a specific setup, or leaks private data at scale.
- **Medium** — real weakness with limited impact or needing other conditions.
- **Low** — hardening, defence-in-depth, or small impact.
- **Info** — checked and found acceptable; listed so it is not re-reviewed.

## All findings

| ID | Severity | Title |
| --- | --- | --- |
| PRF-01 | Critical | Guests are treated as owner of every profile (`isOwnProfile = true`) |
| PRF-02 | Critical | `isOwnProfile` is a client-writable property used as the only owner check |
| PRF-03 | Critical | Certificates save/delete have no authorization at all |
| QA-01 | Critical | Stored XSS in question description (`{!! !!}`) |
| QA-02 | Critical | Stored XSS in answers (`{!! !!}`) |
| PRF-04 | High | IDOR: profile record IDs not checked against owner (10 sections) |
| PRF-05 | High | `javascript:` links in profile social URLs → stored XSS |
| PRF-07 | High | Certificate upload keeps client extension in `public/` |
| PUB-01 | High | IDOR: edit/delete any publication |
| PUB-02 | High | Guests can harvest user emails on deposit page |
| PUB-03 | High | Guests can create active user accounts for any email |
| PUB-04 | High | Deposit upload keeps client filename/extension on public disk |
| QA-03 | High | Question author can delete answers on other questions |
| JRN-01 | High | `</script>` breakout in JSON-LD (journal & certificate pages) |
| ADM-01 | High | `admin` middleware not applied to Livewire actions |
| API-01 | High | SSRF + internal port scan via `/api/link-preview` |
| API-03 | High | Author search returns every user's email (also to guests) |
| DEP-01 | High | Composer packages with published advisories |
| PRF-06 | Medium | `javascript:` in project URL |
| PUB-05 | Medium | Anyone can attach any user as co-author |
| PUB-06 | Medium | Guest deposits / journal creation unthrottled |
| QA-04 | Medium | Accept answers from other questions |
| QA-05 | Medium | `slug` not locked on question page |
| QA-06 | Medium | Questions published immediately, no moderation |
| QA-07 | Medium | No rate limit on Q&A writes |
| JRN-02 | Medium | Regex injection / ReDoS in public institution search |
| JRN-03 | Medium | Certificates issued for any journal, written to `public/` on GET |
| JRN-04 | Medium | Unauthenticated journal creation triggers many outbound calls |
| ADM-02 | Medium | Privilege fields in `User::$fillable` |
| ADM-07 | Medium | Admin uploads keep client extension / allow SVG |
| API-02 | Medium | Blanket CSRF exemption for `api/*` |
| AUTH-01 | Medium | Email ownership never verified |
| AUTH-02 | Medium | Email change without current password |
| CFG-01 | Medium | `APP_DEBUG=true`, `LOG_LEVEL=debug` |
| CFG-02 | Medium | No security headers / CSP |
| CFG-04 | Medium | Uploads stored in web root without restrictions |
| CFG-05 | Medium | No rate limiting on Livewire actions |
| CFG-09 | Medium | Only one `#[Locked]` property in the app |
| DEP-02 | Medium | npm packages with advisories (2 critical, 9 high) |
| PRF-08 | Low | Guest-callable helper methods |
| PUB-07 | Low | Article view counter inflation |
| QA-08 | Low | Question view counter inflation |
| JRN-05 | Low | Second-order SSRF in website scraping |
| ADM-03 | Low | `toggleAdmin` cannot revoke `type = 1` admins |
| ADM-04 | Low | Impersonation: no audit, admin→admin allowed |
| ADM-05 | Low | Admin password reset allows 6 characters |
| ADM-06 | Low | Admin email edit has no uniqueness check |
| ADM-08 | Low | Base64 photo saved without image check |
| ADM-09 | Low | DB fixers trust client-writable field names / raw regex |
| ADM-10 | Low | Unbounded `perPage` |
| API-04 | Low | `research-tags` 500 on array input |
| API-05 | Low | Search inputs not length-limited |
| AUTH-03 | Low | No throttling on registration |
| AUTH-04 | Low | Password confirmation brute-force |
| AUTH-05 | Low | Login throttle per email only |
| AUTH-06 | Low | Weak default password rule |
| AUTH-07 | Low | Account deletion leaves personal data |
| AUTH-08 | Low | Unvalidated timezone stored at login |
| CFG-03 | Low | Session cookie `secure` / encryption not set |
| CFG-06 | Low | Reset links written to log (`MAIL_MAILER=log`) |
| CFG-07 | Low | Stray dev files (`public/hot`, scratch, copies) |
| CFG-08 | Low | Cache keys from attacker-controlled strings |
| PUB-08, PUB-09, QA-09, JRN-06 | Info | Checked and acceptable |

**Totals:** 5 Critical · 13 High · 21 Medium · 23 Low · 4 Info.

## Suggested fix order

1. **Profile write access** (PRF-01, PRF-02, PRF-03, PRF-04, PUB-01): one server-side owner check in every save/delete + record lookups scoped by `user_id`. This closes the largest hole: today a logged-out visitor can edit or delete anyone's profile.
2. **Stored XSS** (QA-01, QA-02, JRN-01, PRF-05, PRF-06): sanitize rich text, use `@json` for JSON-LD, validate link schemes.
3. **Uploads** (PRF-07, PUB-04, ADM-07, CFG-04): server-detected extensions, private storage or locked-down upload folders.
4. **Data exposure & abuse** (PUB-02, API-03, PUB-03, API-01): stop sending emails to the browser, require login for author creation, block internal IPs in link preview.
5. **Admin hardening** (ADM-01, CFG-09): persistent `admin` middleware + `#[Locked]` properties.
6. Then the Medium/Low items and dependency upgrades (DEP-01, DEP-02).

## Method and re-check

- **Pass 1 — mapping:** listed every route (`routes/*.php`), middleware, provider, model, Livewire/Volt component and trait; grouped them into the sections above.
- **Pass 2 — per-section review:** for each section read the PHP part of every component and traced user input → validation → storage → output. Searched project-wide for known sinks: `{!! !!}`, `new Regex(` without `preg_quote`, `getClientOriginalExtension`, `public_path`, `Http::`, `find($id)` without owner filter, `#[Locked]`, `RateLimiter`, `$fillable`.
- **Pass 3 — re-check (cross-check):** every finding was re-opened at its exact file and line, and framework behaviour it depends on was confirmed in `vendor/` (Laravel `validateMimes`, Livewire `PersistentMiddleware`, laravel-mongodb `like` escaping, Laravel `Str::isUrl` protocol list). Two first-draft conclusions were corrected during this pass:
  - The JSON-LD `{!! json_encode() !!}` blocks were first marked safe; re-check proved they are exploitable → **JRN-01** (confirmed with a one-off `php -r` run of the exact normalizer and JSON flags).
  - QA-06 first assumed new questions start as pending; the code creates them with `status => 1`, so the finding was rewritten.
- **Not done:** no live penetration test or automated scanner run against a running server; Reverb/WebSocket server config and the front-end JavaScript bundle were not reviewed in depth; findings are from code reading plus `composer audit` / `npm audit`.
