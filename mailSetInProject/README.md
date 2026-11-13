# Email System & Notification Flows in Scholar9

This directory documents all locations, conditions, triggers, file flows, and configurations for emails sent in the Scholar9 application.

---

## 📌 Summary of Current Mail Flows

| # | Email Purpose | Trigger / Condition | Source File | Mechanism / Class | Recipient | Destination File | Status |
|---|---|---|---|---|---|---|---|
| **1** | **Password Reset Link** | User enters their email on the "Forgot Password" page and submits the form. | [`resources/views/livewire/auth/forgot-password.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/auth/forgot-password.blade.php#L19) | `Illuminate\Support\Facades\Password::sendResetLink()` &rarr; `ResetPassword` Notification | Registered user with matching email | [`resources/views/livewire/auth/reset-password.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/auth/reset-password.blade.php) | **Active** |
| **2** | **Email Verification (Notice Screen)** | Authenticated user on `/verify-email` clicks **"Resend verification email"**. | [`resources/views/livewire/auth/verify-email.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/auth/verify-email.blade.php#L21) | `Auth::user()->sendEmailVerificationNotification()` &rarr; `VerifyEmail` Notification | Logged-in user's email (`Auth::user()->email`) | [`app/Http/Controllers/Auth/VerifyEmailController.php`](file:///c:/Users/hp/Desktop/L12S9/app/Http/Controllers/Auth/VerifyEmailController.php) | **Active** |
| **3** | **Email Verification (Settings Profile)** | Authenticated user on `/settings/profile` has an unverified or changed email and clicks **"Click here to re-send the verification email"**. | [`resources/views/livewire/settings/profile.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/settings/profile.blade.php#L67) | `$user->sendEmailVerificationNotification()` &rarr; `VerifyEmail` Notification | Authenticated user's email (`$user->email`) | [`app/Http/Controllers/Auth/VerifyEmailController.php`](file:///c:/Users/hp/Desktop/L12S9/app/Http/Controllers/Auth/VerifyEmailController.php) | **Conditionally Visible** *(requires `MustVerifyEmail`)* |
| **4** | **Email Verification on Registration** | A new guest registers via `/register`. Form submission fires `Registered` event. | [`resources/views/livewire/auth/register.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/auth/register.blade.php#L30) | `event(new Registered($user))` | Newly registered user's email | `Illuminate\Auth\Listeners\SendEmailVerificationNotification` | **Disabled** *(in `User.php`, `implements MustVerifyEmail` is commented out)* |

---

## 📂 Detailed Documentation Sections

- [Section 1: Password Reset Mail](file:///c:/Users/hp/Desktop/L12S9/mailSetInProject/01-password-reset-mail.md)
- [Section 2: Email Verification Mail](file:///c:/Users/hp/Desktop/L12S9/mailSetInProject/02-email-verification-mail.md)
- [Section 3: Mail Configuration & Drivers](file:///c:/Users/hp/Desktop/L12S9/mailSetInProject/03-mail-configuration-and-drivers.md)
- [Section 4: Adding Custom Mailables / Notifications](file:///c:/Users/hp/Desktop/L12S9/mailSetInProject/04-how-to-add-custom-mail.md)

---

## 🔍 Key Architecture Findings

1. **No custom Mailable or Notification classes currently exist in `app/Mail/` or `app/Notifications/`:**
   - The application relies on Laravel's core authentication notifications (`Illuminate\Auth\Notifications\ResetPassword` and `Illuminate\Auth\Notifications\VerifyEmail`).
2. **`MustVerifyEmail` interface on User model:**
   - In [`app/Models/User.php`](file:///c:/Users/hp/Desktop/L12S9/app/Models/User.php#L16), `implements MustVerifyEmail` is currently commented out:
     ```php
     class User extends Authenticatable // implements MustVerifyEmail
     ```
   - When this interface is commented out, Laravel's default `SendEmailVerificationNotification` listener ignores the `Registered` event during sign up. However, explicit calls to `$user->sendEmailVerificationNotification()` still work because `MongoDB\Laravel\Auth\User` inherits the trait methods.
3. **HTTP User-Agent Notice (Non-mail):**
   - [`app/Services/JournalVerificationService.php`](file:///c:/Users/hp/Desktop/L12S9/app/Services/JournalVerificationService.php#L1413) contains a `mailto:admin@scholar9.com` address. This is **not** an outbound email, but a polite User-Agent string sent to external REST APIs (Crossref & OpenAlex).

