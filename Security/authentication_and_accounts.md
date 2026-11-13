# Security — Authentication & Account Settings

Scope: [routes/auth.php](../routes/auth.php), [livewire/auth/*](../resources/views/livewire/auth/), [livewire/settings/*](../resources/views/livewire/settings/), [VerifyEmailController.php](../app/Http/Controllers/Auth/VerifyEmailController.php), [Logout.php](../app/Livewire/Actions/Logout.php), [User.php](../app/Models/User.php).

What is already done well: login rate limit (5 tries per email+IP) and `Session::regenerate()` on login; logout invalidates the session and CSRF token; password reset uses Laravel's broker with a `#[Locked]` token and a generic "link will be sent if the account exists" message; signed + throttled email-verification links; `current_password` check before password change and account deletion.

---

## AUTH-01 — Email ownership is never verified — **Medium**

- **Where:** [User.php:16](../app/Models/User.php#L16) `class User extends Authenticatable // implements MustVerifyEmail` (interface commented out).
- **Problem:** Because `User` does not implement `MustVerifyEmail`, the `verified` middleware on `/dashboard` lets everyone through and no verification mail is sent on register. Anyone can register with an email they do not own.
- **Why it matters here:** Publications and deposits link co-authors **by email** (see PUB-05). An attacker who registers `victim@university.edu` before the victim does is shown as the author of every paper deposited with that email, and the real person can no longer register.
- **Fix:** Implement `MustVerifyEmail` and put `verified` on every route/feature that relies on the email identity.

## AUTH-02 — Email can be changed without the current password — **Medium**

- **Where:** `updateProfileInformation()` [settings/profile.blade.php:26-50](../resources/views/livewire/settings/profile.blade.php#L26-L50)
- **Problem:** A stolen session (e.g. via QA-01 XSS) can change the email to the attacker's address and then use "Forgot password" to take over the account permanently. Clearing `email_verified_at` does not help because verification is not enforced (AUTH-01).
- **Fix:** Require `current_password` (or `password.confirm` middleware) for email changes and notify the old address.

## AUTH-03 — No throttling / bot protection on registration — **Low**

- **Where:** `register()` [auth/register.blade.php:20-35](../resources/views/livewire/auth/register.blade.php#L20-L35)
- **Problem:** No rate limit or CAPTCHA. Combined with AUTH-01, bots can mass-register and reserve emails.
- **Fix:** `RateLimiter` per IP; CAPTCHA if abuse appears.

## AUTH-04 — Password confirmation can be brute-forced — **Low**

- **Where:** `confirmPassword()` [auth/confirm-password.blade.php:14-34](../resources/views/livewire/auth/confirm-password.blade.php#L14-L34)
- **Problem:** No attempt counter (unlike login). Someone with a hijacked session can guess the password without lockout.
- **Fix:** Reuse the login `RateLimiter` pattern keyed by user ID.

## AUTH-05 — Login throttling is per email only — **Low**

- **Where:** `throttleKey()` [auth/login.blade.php:97-100](../resources/views/livewire/auth/login.blade.php#L97-L100) = `email|ip`.
- **Problem:** One IP can try 5 passwords against **every** email (credential stuffing / password spraying) without being slowed.
- **Fix:** Add a second limiter keyed only by IP (e.g. 30/min).

## AUTH-06 — Weak default password rule — **Low**

- **Where:** register, reset and settings use `Rules\Password::defaults()`; no `Password::defaults()` is configured in [AppServiceProvider.php](../app/Providers/AppServiceProvider.php), so the rule is only "min 8 characters". Admin resets allow 6 (see ADM-05).
- **Fix:** In `AppServiceProvider::boot()`: `Password::defaults(fn () => Password::min(10)->letters()->numbers()->uncompromised());` (production only if needed).

## AUTH-07 — Account deletion leaves personal data behind — **Low (privacy)**

- **Where:** [settings/delete-user-form.blade.php:19](../resources/views/livewire/settings/delete-user-form.blade.php#L19) deletes only the `users` document.
- **Problem:** `ReviewerProfile`, experiences, education, certificates (and their files in `public/certificate/`), publications, questions, answers, votes and follow lists stay in the database and some stay publicly reachable. Admin `deleteUser` behaves the same.
- **Fix:** Delete or anonymize related documents and files in a `User::deleting` handler or a dedicated action.

## AUTH-08 — Login stores client-supplied timezone and overwrites `last_login_with` — **Low**

- **Where:** [auth/login.blade.php:51-55](../resources/views/livewire/auth/login.blade.php#L51-L55) → [TimezoneHelper.php:142-175](../app/Helpers/TimezoneHelper.php#L142-L175)
- **Problem:** The hidden `timezone` field is saved to the user without checking it against `DateTimeZone::listIdentifiers()`. `last_login_with` (which the admin "python users" filter also reads) is overwritten with the client IP.
- **Fix:** Validate with `Rule::in(timezone_identifiers_list())`; store login IP in its own field.

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| AUTH-01 | `User` class declaration; `dashboard` route uses `verified`, which is a no-op for non-`MustVerifyEmail` users. |
| AUTH-02 | Read `updateProfileInformation`: only `name`/`email` validation. |
| AUTH-03/04/05 | `grep RateLimiter` → only `auth/login`. |
| AUTH-06 | `AppServiceProvider::boot()` is empty. |
| AUTH-07 | `deleteUser` calls only `->delete()` on the user model; no model `deleting` hooks clean up relations. |
