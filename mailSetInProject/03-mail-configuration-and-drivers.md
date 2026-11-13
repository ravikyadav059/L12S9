# Section 3: Mail Configuration & Drivers

## 1. Overview
Laravel provides clean abstraction over various mail sending transports. All email configuration is defined in [`config/mail.php`](file:///c:/Users/hp/Desktop/L12S9/config/mail.php) and dynamically controlled via environment variables in `.env`.

---

## 2. Mail Configuration Settings

From [`config/mail.php`](file:///c:/Users/hp/Desktop/L12S9/config/mail.php):

| Configuration Key | Env Variable | Default in Config | Description |
|---|---|---|---|
| `default` | `MAIL_MAILER` | `log` | The active mail driver used by default. |
| `mailers.smtp.host` | `MAIL_HOST` | `127.0.0.1` | SMTP server host. |
| `mailers.smtp.port` | `MAIL_PORT` | `2525` | SMTP server port (e.g. 587, 465, 2525). |
| `mailers.smtp.username` | `MAIL_USERNAME` | `null` | SMTP authentication username. |
| `mailers.smtp.password` | `MAIL_PASSWORD` | `null` | SMTP authentication password. |
| `mailers.smtp.scheme` | `MAIL_SCHEME` | `null` | `tls` or `ssl`. |
| `from.address` | `MAIL_FROM_ADDRESS` | `hello@example.com` | Default "From" sender email address. |
| `from.name` | `MAIL_FROM_NAME` | `Example` | Default "From" sender display name. |

---

## 3. Supported Drivers

1. **`log` (Development & Testing):**
   - Emails are written directly to [`storage/logs/laravel.log`](file:///c:/Users/hp/Desktop/L12S9/storage/logs/laravel.log).
   - No external network connection is required. Perfect for testing email bodies and links locally.

2. **`smtp` (Production / Staging):**
   - Sends real emails via services such as Gmail, SendGrid, Amazon SES SMTP, Brevo, or Mailgun.

3. **`ses` / `postmark` / `resend`:**
   - Dedicated API-driven mail services for high-deliverability transactional emails.

---

## 4. Setting up Production / SMTP in `.env`

To configure live email sending in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@scholar9.com"
MAIL_FROM_NAME="Scholar9"
```

After modifying `.env`, run:
```bash
php artisan config:clear
```

