# Project Analysis & Architecture Guide

This document summarizes the full technical analysis, database configuration, authentication architecture comparison, and design decisions for the **L12S9** Laravel 12 project.

---

## 1. Project Overview & Technology Stack

The project is built on modern Laravel 12 architecture:

* **Backend Framework:** Laravel 12.x (Streamlined directory structure, middleware defined in `bootstrap/app.php`).
* **Frontend / Component Layer:** Livewire Volt 1.6+ (Single-file declarative components).
* **UI Component Library:** Livewire Flux 2.0 (Installed, optional).
* **Styling & Assets:** Tailwind CSS & Vite.
* **Real-time Engine:** Laravel Reverb (WebSocket server).
* **Testing Suite:** Pest PHP 3.x.
* **Database Driver:** `mongodb/laravel-mongodb` v5.9+.

---

## 2. Database Architecture & Configuration

### Active Configuration: MongoDB
The application is configured to connect to **MongoDB** using the database name `clonedata`.

#### `.env` Settings
```env
DB_CONNECTION=mongodb
MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=clonedata
```

#### `config/database.php` Connection Block
```php
'mongodb' => [
    'driver' => 'mongodb',
    'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
    'database' => env('MONGODB_DATABASE', 'clonedata'),
],
```

#### Authenticatable User Model (`app/Models/User.php`)
The `User` model extends MongoDB's authenticatable class instead of standard relational Eloquent:
```php
namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

#### Protection for Existing Database
To ensure that existing collections and data in MongoDB are not accidentally dropped or overwritten:
* Default migrations in `database/migrations/` were removed.
* Factories (`UserFactory.php`) and Seeders (`DatabaseSeeder.php`) were removed.

---

## 3. AI Agent Integration & Rules

The project configuration defines agent guidelines in `boost.json` and `AGENTS.md`.

### Supported Agents (`boost.json`)
* **Antigravity**
* **Codex**
* **Kiro**

### How Agents Access Data & Guidelines
* **Database Agnostic:** Guidelines in `AGENTS.md` apply to SQL (SQLite, MySQL, Postgres) as well as MongoDB.
* **Unified Tooling:** All three agents connect via the `laravel-boost` MCP server (`php artisan boost:mcp`).
* **Shared Context:** All agents share access to the same project environment and active database specified in `.env`.
* **Git Tracking:** The configuration directories (`.agents/`, `.codex/`, `.kiro/`) contain MCP definitions and agent skill documents without sensitive credentials, making them safe and recommended to commit to Git.

---

## 4. Registration & Request Lifecycle Flow

When a user registers on the application:

1. **User Interaction:**
   The user submits the registration form in `resources/views/livewire/auth/register.blade.php`.
2. **Asynchronous Request:**
   Livewire intercepts `wire:submit="register"` and sends an asynchronous AJAX request without full page reload.
3. **Validation & Hashing:**
   The Volt component validates user input, hashes the password via `Hash::make()`, and calls `User::create($validated)`.
4. **Data Persistence:**
   The MongoDB Eloquent driver writes the document directly to the `users` collection inside the `clonedata` database.
5. **Session Login & Redirect:**
   `Auth::login($user)` initiates the session, and Livewire redirects the user to `/dashboard`.
6. **Email Notifications:**
   Configured with `MAIL_MAILER=log`, all outgoing emails are logged locally in `storage/logs/laravel.log` rather than sent over SMTP.

---

## 5. Authentication Comparison: Old vs. Current Project

| Aspect | Old Project Setup | Current Setup (`L12S9`) | Rationale & Recommendation |
| :--- | :--- | :--- | :--- |
| **Framework** | Older Laravel (7/8/9) | **Laravel 12.x** | Modern streamlined core and PHP 8.2+ performance. |
| **Web Auth** | `laravel/ui` (Classic controllers: `LoginController.php`, `RegisterController.php`) | **Livewire Volt** (Single-file Blade components) | Dynamic, AJAX-powered, no full-page reloads. |
| **Token Auth** | `tymon/jwt-auth` | **Laravel Sanctum** | Sanctum provides lightweight, revocable database/cookie tokens natively. |
| **Cross-System Logins** | Custom `/login-with-token/{token}` using JWT | **Laravel Signed URLs** | `URL::temporarySignedRoute()` provides secure, tamper-proof, time-limited login links natively without external JWT overhead. |

---

## 6. UI & Styling Strategy: Custom Tailwind vs. Flux UI

While `livewire/flux` is available in the dependencies:

* **Decision:** Build a **100% custom UI with Tailwind CSS** and **Alpine.js**.
* **Benefits:**
  * Complete design freedom to match existing publisher design mockups (`For Publiseher design full`).
  * Cleaner code without proprietary component abstractions (`<flux:*>` tags).
  * No vendor lock-in; standard Tailwind utility classes across all Blade views.
* **Interactive Elements:**
  * Modals, dropdowns, and mobile navigations are handled smoothly using Alpine.js (bundled with Livewire).
