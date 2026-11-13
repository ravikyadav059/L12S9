# Security — HTTP / JSON API Routes

Scope: closure and controller routes in [routes/web.php:26-102](../routes/web.php#L26-L102), [LinkPreviewController.php](../app/Http/Controllers/LinkPreviewController.php), [LinkPreviewService.php](../app/Services/LinkPreviewService.php), CSRF config in [bootstrap/app.php](../bootstrap/app.php).

| Route | Auth | Throttle | Status |
| --- | --- | --- | --- |
| `POST /api/link-preview` | none | 60/min | **API-01, API-02** |
| `GET /api/research-tags` | none | 60/min | API-04 |
| `GET /search-journals` | none | 120/min | OK (titles only) |
| `GET /api/designations`, `organizations`, `degree-types`, `fields-of-study`, `grades` | auth | 120/min | OK (`preg_quote` used) |
| `GET /api/authors` | auth | 120/min | **API-03** |
| `GET /api/publications/retrieve-doi` | auth | 120/min | OK (fixed Crossref host) |

---

## API-01 — Server-Side Request Forgery in link preview — **High**

- **Where:** route [web.php:28](../routes/web.php#L28); fetch at [LinkPreviewService.php:35-41](../app/Services/LinkPreviewService.php#L35-L41)
- **Problem:** Anyone (no login) can make the server `GET` any `http(s)` URL. There is no block for `localhost`, `127.0.0.0/8`, `10/8`, `172.16/12`, `192.168/16`, `169.254.169.254` (cloud metadata), or internal hostnames, and redirects are followed (a public URL can redirect to an internal one).
- **What leaks:**
  - `<title>`, `og:title`, `og:description` of internal pages are returned in the JSON (partial-read SSRF: admin panels, Redis/Mongo web UIs, metadata endpoints that return HTML).
  - The raw exception text is returned to the client ([line 65](../app/Services/LinkPreviewService.php#L65)): "Connection refused" vs. "timed out" lets an attacker **port-scan the internal network**.
  - The HTTP status of the internal URL is returned (`Unable to fetch URL (HTTP 403)`).
- **Extra:** Every distinct URL creates a 24-hour Redis entry (`link_preview_<md5>`) — 60 req/min/IP can fill the cache store.
- **Fix:** Resolve DNS and reject private/reserved IPs before the request **and** after every redirect (or disable redirects); allow only ports 80/443; return a generic error message; require auth if the feature is only used by logged-in users.

## API-02 — Blanket CSRF exemption for `api/*` on session-authenticated routes — **Medium**

- **Where:** [bootstrap/app.php:20-22](../bootstrap/app.php#L20-L22) `validateCsrfTokens(except: ['api/*'])`
- **Problem:** These "api" routes live in `routes/web.php`, so they run with the web session and cookies. Today only `POST /api/link-preview` is affected (cross-site pages can use a victim's browser to trigger SSRF requests). Any future `POST/PUT/DELETE` added under `/api/` that changes data will be CSRF-able automatically.
- **Fix:** Remove the exemption (send the `X-CSRF-TOKEN` header from JS), or move true stateless APIs to `routes/api.php` with token auth.

## API-03 — Author search returns every user's email address — **High**

- **Where:** [HandlesPublicationSection.php:302-415](../app/Livewire/Concerns/HandlesPublicationSection.php#L302-L415) `searchAuthorsQuery()` returns `'email' => $user->email`, used by:
  - `GET /api/authors` ([web.php:88-94](../routes/web.php#L88-L94)) — any logged-in user.
  - Livewire `searchAuthors()` ([HandlesPublicationSection.php:292](../app/Livewire/Concerns/HandlesPublicationSection.php#L292)) on the `profile.publications` component — that component is rendered on the **public** profile page, so **guests** can call it too.
- **Problem:** The query also matches the `email` field, so searching `@gmail`, `@yahoo`, `a`, `b`, … pages through every non-admin user's email (10 per call, cached 5 min). Same issue as PUB-02 on the deposit page.
- **Fix:** Remove `email` from the response (send it only after the user picks an author, server-side); do not match on email for partial queries; require auth + owner for the Livewire method.

## API-04 — `research-tags` crashes on array input — **Low**

- **Where:** [web.php:32](../routes/web.php#L32) `array_map('trim', (array) $request->input('exclude', []))`
- **Problem:** `?exclude[0][]=x` passes a nested array to `trim()` → `TypeError` → HTTP 500. With `APP_DEBUG=true` (CFG-01) the full stack trace, file paths and environment details are shown to the anonymous caller.
- **Fix:** Validate: `$request->validate(['q' => 'nullable|string|max:100', 'exclude' => 'array', 'exclude.*' => 'string|max:100'])`.

## API-05 — Search inputs are not length-limited — **Low**

- **Where:** `q` / `search` in all lookup routes (e.g. [web.php:41-45](../routes/web.php#L41-L45)).
- **Problem:** Very long strings become long regexes run against whole collections, and also become cache keys (`authors:search:v1:<md5>`). Throttling limits the rate but not the cost per call.
- **Fix:** `max:100` validation on all search parameters.

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| API-01 | Read controller (only `url` validation) and service (no IP checks, raw `getMessage()`); Laravel's `Http` client follows redirects by default. Guzzle restricts schemes to http/https, so `file://`/`gopher://` are not possible. |
| API-02 | Listed all `/api/*` routes — only one POST today. |
| API-03 | Confirmed `profile/publications.blade.php` uses `HandlesPublicationSection` (line 21) and has no auth guard on `searchAuthors`. |
| Lookup routes | Checked `searchActive()` in DegreeType, Designation, FieldOfStudy, Organization, Research — all use `preg_quote`; `ReviewerJournal::searchTitles` uses `like`, which laravel-mongodb escapes with `preg_quote` (`Query/Builder.php:1381-1399`). |
