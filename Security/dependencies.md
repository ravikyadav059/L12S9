# Security — Third-Party Dependencies

Results of `composer audit` and `npm audit --omit=dev`, run on **13 Nov 2026** against the current `composer.lock` / `package-lock.json`. "Relevant here?" is based on how this app actually uses each package.

## DEP-01 — Composer packages with published advisories — **High**

| Package (installed) | Severity | Advisory | Relevant here? |
| --- | --- | --- | --- |
| laravel/framework 12.67.0 | critical | CVE-2026-114509 TokenGuard auth bypass via non-string credentials | Low — app uses the session guard, not `TokenGuard`. Upgrade anyway. |
| laravel/framework | medium | CVE-2026-114508 relationship inclusion can trigger data destruction | Check after upgrade. |
| laravel/framework | low | CVE-2026-102279 XSS in debug page | Yes while `APP_DEBUG=true` (CFG-01). |
| livewire/livewire 4.4.1 | medium | `#[Authorize]` bypass via reordered params / `#[Json]` order | Not used today; matters if `#[Authorize]` is adopted for the fixes in this folder. |
| mongodb/mongodb | high | CVE-2026-81525 "." and NUL bytes in DB/collection names | Low — collection names are hard-coded. |
| mongodb/mongodb | medium | CVE-2026-88023 GridFS query-operator injection | No — GridFS not used. |
| symfony/http-foundation | high | CVE-2026-108082 ReDoS in `HeaderUtils::split()` via `Cache-Control` header | **Yes** — reachable by any anonymous request. |
| symfony/http-foundation | — | trusted-proxy `private_ranges`, generated URLs pointing to client-chosen host, path-rule bypass, HEAD bypass (CVE-2026-118514) | Depends on proxy/server config; upgrade. |
| symfony/http-kernel | — | CVE-2026-123339 forwarded headers set host/scheme of generated URLs | Affects password-reset link host if proxies are trusted. |
| symfony/routing | — | CVE-2026-118516 host placeholders bypass path-based access control | Low. |
| symfony/mime | medium | CVE-2026-45070 email header injection | Low — mail params are not user-controlled. |
| symfony/yaml 7.4.15 | high | CVE-2026-109413, CVE-2026-123792 parser DoS | No — only pulled in by dev tooling (`laravel/sail`, `laravel/roster`). |
| league/commonmark | high / medium | GFM table quadratic DoS; `DisallowedRawHtml` bypass | No current Markdown rendering of user input; becomes relevant if Markdown is used to fix QA-01. |
| league/flysystem | low | CVE-2026-102601 path normalizer bypass | Low. |

- **Fix:** `composer update laravel/framework livewire/livewire mongodb/laravel-mongodb symfony/* league/* --with-all-dependencies`, then run the test suite. (Dependency changes need approval per project rules.)

## DEP-02 — npm packages with published advisories — **Medium**

13 vulnerable packages (2 critical, 9 high, 2 moderate). Direct dependencies: **axios** (high) and **vite** (high). Transitive: `form-data` and `shell-quote` (critical), `browserslist`, `lodash`, `nanoid`, `picomatch`, `postcss`, `rollup`, `source-map-js` (high), `esbuild`, `follow-redirects` (moderate). All have fixes available via `npm audit fix`.

- **Relevant here?** Mostly build-time (Vite/rollup/esbuild/postcss) — they affect developer machines and CI, not visitors. `axios` (and its `follow-redirects` / `form-data`) is shipped to the browser bundle; its advisories mainly affect server-side Node use, so browser impact is low.
- **Fix:** `npm audit fix`, then `npm run build` and a smoke test.

---

### Cross-check log

Ran both audits twice (table and JSON output) and matched every row above to the JSON. `composer why symfony/yaml` confirms it is only required by `laravel/sail` and `laravel/roster` (dev tools).
