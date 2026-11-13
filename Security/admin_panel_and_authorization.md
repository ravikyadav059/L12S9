# Security — Admin Panel, Impersonation & Authorization Model

Scope: [AdminMiddleware.php](../app/Http/Middleware/AdminMiddleware.php), admin routes in [routes/web.php](../routes/web.php) (`/admin/*`), all components under [resources/views/livewire/admin/](../resources/views/livewire/admin/), `User::isAdmin()` in [User.php](../app/Models/User.php).

---

## ADM-01 — `admin` middleware is not re-applied to Livewire actions — **High**

- **Where:** alias registered in [bootstrap/app.php:16-18](../bootstrap/app.php#L16-L18); Livewire's default persistent list in `vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php` only contains `Authenticate`, `Authorize`, `SubstituteBindings`, etc. `App\Http\Middleware\AdminMiddleware` is **not** added (`AppServiceProvider::boot()` is empty).
- **Problem:** The `admin` check runs only when the admin page is first loaded. Later calls to `/livewire/update` (every button click: `toggleAdmin`, `deleteUser`, `updateUserStatus`, `updatePassword`, all database fixers, all admin user partials) run **without** it. Almost no admin method re-checks `isAdmin()` itself — only `users/index::impersonate()` and `dashboard` do.
- **Attack:** A user who was an admin, or who once captured an admin component snapshot (e.g. a demoted admin, an admin's shared browser, or via an XSS from QA-01/JRN-01 that grabs a snapshot from the admin page), can keep replaying snapshots and call `toggleAdmin`, `deleteUser`, `updatePassword` after losing admin rights. Snapshots do not expire.
- **Fix:** In `AppServiceProvider::boot()`: `Livewire::addPersistentMiddleware([AdminMiddleware::class]);`. Also add an explicit `abort_unless(auth()->user()?->isAdmin(), 403)` (or a Policy / `Gate`) at the top of destructive admin methods.

## ADM-02 — Privilege flags are mass-assignable on `User` — **Medium**

- **Where:** `$fillable` in [User.php:28-71](../app/Models/User.php#L28-L71) includes `type`, `admin_permission`, `status`, `verified`, `user_role`, `profile_status`, `reviewer_status`, `badge_type`, `verification_request_status`.
- **Problem:** `isAdmin()` ([User.php:173-194](../app/Models/User.php#L173-L194)) treats `type == 1`, `type == "1"` and `type == "admin"` as admin. No current code passes raw request data into `User::create/fill`, so this is **not exploitable today**, but any future `$user->fill($request->all())` or `User::create($validated)` with extra keys becomes an instant privilege escalation.
- **Fix:** Remove privilege fields from `$fillable` and set them explicitly (`$user->type = ...`) only in admin code.

## ADM-03 — Inconsistent admin flags (`type`, `is_admin`, `role`) — **Low**

- **Where:** `toggleAdmin()` [users/index.blade.php:149-162](../resources/views/livewire/admin/users/index.blade.php#L149-L162) writes `is_admin` and `role`; `isAdmin()` returns `true` for `type == 1` **before** looking at `is_admin`.
- **Problem:** Removing admin rights from a user whose `type` is `1` does nothing — `toggleAdmin` sets `is_admin = false`, but `type == 1` still makes them admin. Admins may believe they revoked access when they did not.
- **Fix:** Use one source of truth for the admin role and update it in `toggleAdmin`.

## ADM-04 — Impersonation has no audit trail and allows admin→admin — **Low**

- **Where:** `impersonate()` [users/index.blade.php:189-211](../resources/views/livewire/admin/users/index.blade.php#L189-L211); `impersonate/leave` [routes/web.php:106-119](../routes/web.php#L106-L119).
- **Problem:** No log entry is written, an admin can impersonate another admin, and the session ID is not regenerated on switch. `impersonate/leave` is safe (it re-checks that the stored ID is an admin).
- **Fix:** Log who impersonated whom and when; block impersonating other admins; call `session()->regenerate()` after `Auth::login()`.

## ADM-05 — Admin password reset allows 6-character passwords — **Low**

- **Where:** [users/edit.blade.php:523-533](../resources/views/livewire/admin/users/edit.blade.php#L523-L533) `'new_password' => 'required|min:6|confirmed'`
- **Fix:** Use `Password::defaults()` like the user-facing forms; optionally force a reset on next login.

## ADM-06 — Admin email edit has no uniqueness check — **Low**

- **Where:** [users/edit.blade.php:67](../resources/views/livewire/admin/users/edit.blade.php#L67) `#[Validate('required|email|max:255')]`, saved at line 558.
- **Problem:** Two accounts can end up with the same email → login/password-reset confusion (the wrong account may receive the reset link).
- **Fix:** `Rule::unique('users', 'email')->ignore($this->user)`.

## ADM-07 — Admin uploads keep the client extension and allow SVG in `public/` — **Medium**

- **Where:**
  - Institutes logo: [institutes/create.blade.php:112-116](../resources/views/livewire/admin/institutes/create.blade.php#L112-L116) (same in `edit`) → `public/uploads/`
  - Journal photo: [journals/create.blade.php:205-216](../resources/views/livewire/admin/journals/create.blade.php#L205-L216) (same in `edit`) → `public/uploads/journalphotos/`
  - Indexing agency image: [indexing-agencies/create.blade.php:23](../resources/views/livewire/admin/indexing-agencies/create.blade.php#L23) allows `svg`; filename uses client extension at [line 66](../resources/views/livewire/admin/indexing-agencies/create.blade.php#L66) (same in `edit`).
- **Problem:** The `image`/`mimes` rules check file content; the saved name uses `getClientOriginalExtension()`, so an image polyglot named `.html` is saved as HTML in `public/`. SVG is allowed outright and can contain script when opened directly. Admin-only, but combined with ADM-01 or a stolen admin session it gives persistent XSS.
- **Fix:** Use `$file->extension()` (server-detected) or `->store()`; drop `svg` or sanitize it.

## ADM-08 — Admin base64 photo upload writes any bytes as `.png` — **Low**

- **Where:** `saveCroppedPhoto()` [users/edit.blade.php:357-394](../resources/views/livewire/admin/users/edit.blade.php#L357-L394)
- **Problem:** The decoded data is written with `file_put_contents` without checking it is an image, and there is no size limit. Low risk because the extension is fixed to `.png`.
- **Fix:** Validate with `getimagesizefromstring()` and a max size.

## ADM-09 — Database fixers trust client-writable field names and regexes — **Low**

- **Where:**
  - [database-fixing/reviewer-profile/index.blade.php:14-15](../resources/views/livewire/admin/database-fixing/reviewer-profile/index.blade.php#L14-L15) `#[Url] public string $activeField` is only whitelisted in `mount()`/`setField()`; `fixSingle`, `fixSelected`, `fixActiveFieldAll` (e.g. line 177, 208) use it directly as a field name in `update([$this->activeField => ...])`. `$availableFields` is also a public, writable array. Same pattern in [database-fixing/experience/index.blade.php:16-17](../resources/views/livewire/admin/database-fixing/experience/index.blade.php#L16-L17) (updates at lines 194, 234 on the `experience` collection).
  - Raw `new Regex($term, 'i')` from the search box in `database-fixing/question/counts.blade.php:160` and the four `reviewer-profile/orphan-cleaner/*.blade.php` files (line ~154).
- **Problem:** A tampered request can overwrite any field (e.g. `user_id`) of the `reviewer_profile` / `experience` documents being fixed with `null`/arrays; bad regex can error or slow the DB. Admin-only, bulk, no confirmation, no audit log.
- **Fix:** Re-check `array_key_exists($this->activeField, self::FIELDS)` (use a constant, not a public array) inside every action; `preg_quote` the search; add `#[Locked]`.

## ADM-10 — Admin list `perPage` is unbounded — **Low**

- **Where:** [users/index.blade.php:23](../resources/views/livewire/admin/users/index.blade.php#L23) `public int $perPage` (bound to a `<select>`), used in `paginate($this->perPage)`.
- **Problem:** The client can set `perPage` to 1 000 000 and load every user into memory.
- **Fix:** Clamp with `min(100, max(1, $this->perPage))`.

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| ADM-01 | Read Livewire 4.4.1 `PersistentMiddleware::$persistentMiddleware` and `AppServiceProvider`; `grep -l "isAdmin()\|abort(403" resources/views/livewire/admin` → only `dashboard` and `users/index`. |
| ADM-02 | Searched for `->all()`, `fill(`, `create($validated)` sinks; register uses `User::create($validated)` with only `name/email/password` keys, so not exploitable now. |
| ADM-03 | Read `isAdmin()` order of checks vs. `toggleAdmin()` writes. |
| ADM-07 | Read every `getClientOriginalExtension()` call in admin components and their `#[Validate]` rules. |
| ADM-09 | Read fixer actions; whitelist only applied on mount/setField. |
