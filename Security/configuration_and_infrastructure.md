# Security — Configuration, Storage & Infrastructure

Scope: `.env` (values read locally, **not** copied here), [config/session.php](../config/session.php), [bootstrap/app.php](../bootstrap/app.php), [public/](../public/), project root files, cross-cutting Livewire behaviour.

> `.env` and `.env copy` are git-ignored and were never committed (`git log --all -- .env ".env copy"` is empty). No real secrets were found in tracked files (the only hit, `sk_live_abc123`, is a placeholder inside skill docs).

---

## CFG-01 — Debug mode and debug logging are on — **Medium (Critical if deployed like this)**

- **Where:** `.env` → `APP_DEBUG=true`, `LOG_LEVEL=debug`, `APP_ENV=local`.
- **Problem:** Fine on a laptop, but if this `.env` reaches a server, every exception (e.g. API-04, JRN-02 invalid regex) shows a full Ignition page with stack trace, file paths, queries and request data to anonymous users.
- **Fix:** Production: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning` or `error`.

## CFG-02 — No security headers / Content-Security-Policy — **Medium**

- **Where:** no header middleware in [bootstrap/app.php](../bootstrap/app.php); [public/.htaccess](../public/.htaccess) sets none.
- **Missing:** `Content-Security-Policy`, `X-Frame-Options` / `frame-ancestors` (admin panel can be framed → clickjacking), `X-Content-Type-Options: nosniff` (helps against the upload issues PRF-07/PUB-04/ADM-07), `Strict-Transport-Security`, `Referrer-Policy`.
- **Fix:** Add a small middleware appended in `withMiddleware()` that sets these headers; start CSP in report-only mode because Alpine/Livewire need `unsafe-eval` unless the CSP build is used.

## CFG-03 — Session cookie hardening not configured — **Low**

- **Where:** [config/session.php:50](../config/session.php#L50) `SESSION_ENCRYPT=false`; [line 172](../config/session.php#L172) `'secure' => env('SESSION_SECURE_COOKIE')` and the variable is not set in `.env`.
- **Problem:** Without `SESSION_SECURE_COOKIE=true` the session cookie is also sent over plain HTTP. Session files on disk are stored unencrypted.
- **Fix:** Production: `SESSION_SECURE_COOKIE=true`, consider `SESSION_ENCRYPT=true`; keep `http_only=true` and `same_site=lax` (already default).

## CFG-04 — User uploads are stored inside the web root with no execution/serving restrictions — **Medium**

- **Where:** `public/certificate/`, `public/uploads/`, `public/uploads/journalphotos/`, `public/uploads/indexingAgency/`, `public/assets/pdf/`, plus `public/storage` → `storage/app/public` (publications, thumbnails).
- **Problem:** These folders are served directly by Apache/Nginx. There is no `.htaccess` in them to stop script handlers or force downloads, so any file that gets through with a dangerous extension (`.html`, `.svg`, and on misconfigured servers `.phtml`/`.phar`/`.pht`) is executed or rendered on the main domain. Root cause of the impact of PRF-07, PUB-04, ADM-07.
- **Fix:** Store user files on a private disk and stream them through a controller; or at least drop an `.htaccess` in each upload folder with `RemoveHandler .php .phtml .phar .pht`, `php_flag engine off`, and `Header set Content-Disposition attachment` + `X-Content-Type-Options nosniff` for non-image types.

## CFG-05 — No rate limiting on Livewire actions — **Medium**

- **Where:** app-wide; `grep RateLimiter` finds only `auth/login`.
- **Problem:** Every Livewire method (deposits, author creation, journal verification with outbound calls, Q&A posting, votes, searches) can be called as fast as the client wants through `/livewire/update`. Route-level `throttle` only protects the first page load.
- **Fix:** Use `RateLimiter::attempt()` inside expensive or write actions, or a Livewire middleware that throttles `/livewire/update` per user/IP.

## CFG-06 — Password-reset links are written to the log — **Low**

- **Where:** `.env` → `MAIL_MAILER=log`.
- **Problem:** Reset and verification links land in `storage/logs/laravel.log`. Anyone with log access (shared hosting, log shipping) can reset any password.
- **Fix:** Real mailer in production; restrict log access.

## CFG-07 — Stray development files — **Low**

- **Where:** `public/hot` (exists on disk), `scratch/*.php` (debug scripts), root files `step194.blade.php`, `restored_*.blade.php`, `version_180.blade.php`, `app/Models/old user model`, `.env copy`, `*.blade copy.php` inside `resources/views/livewire/`.
- **Problem:** Only `public/hot` is inside the web root: if deployed, Laravel's Vite helper loads all JS/CSS from the dev-server URL written in that file instead of the built assets (broken site, or scripts loaded from whatever is listening there). The others are outside `public/` and not web-reachable, but they increase the chance of shipping secrets (`.env copy`) or dead code with old vulnerabilities.
- **Fix:** Delete `public/hot` before deploy (it is normally created by `npm run dev`); exclude `scratch/`, copies and `.env copy` from deployments.

## CFG-08 — Cache keys built from attacker-controlled strings — **Low**

- **Where:** `tz_country_iso_{$tz}` for 30 days ([TimezoneHelper.php:70](../app/Helpers/TimezoneHelper.php#L70), reachable by guests through `seminars::autoDetectCountry`); `link_preview_<md5(url)>` for 24 h (API-01); `authors:search:v1:<md5>` for 5 min.
- **Problem:** Each new value creates a new Redis key; the shared `CACHE_PREFIX` is empty. An attacker can grow Redis memory and evict useful cache entries.
- **Fix:** Validate inputs against known lists before caching (timezones), lower TTLs, set a Redis `maxmemory-policy` such as `allkeys-lru`, and set a `CACHE_PREFIX`.

## CFG-09 — Only one `#[Locked]` property in the whole app — **Medium (root cause)**

- **Where:** `grep '#[Locked]'` → only `auth/reset-password.blade.php`.
- **Problem:** Livewire public properties are client-writable unless locked. Many components keep IDs, ownership flags, slugs, field names and page sizes in public properties and use them for authorization or queries: `isOwnProfile` (PRF-02), `user` (all profile sections), `slug` (QA-05), `editingQuestionId`/`editingAnswerId`, `activeField`/`availableFields` (ADM-09), `perPage` (ADM-10), `selectedJournalId` (deposit).
- **Fix:** Add `#[Locked]` to every property the user should not change, and always re-check permissions on the server inside the action.

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| CFG-01/03/06 | Read `.env` keys with secret values redacted. |
| CFG-02 | No header-setting middleware in `bootstrap/app.php`; `.htaccess` only has rewrite rules. |
| CFG-04 | `ls public/`; `public/storage` is a symlink to `storage/app/public`; no `.htaccess` in upload folders. |
| CFG-07 | `ls public/` shows `hot`; `git ls-files` shows it is not tracked. |
