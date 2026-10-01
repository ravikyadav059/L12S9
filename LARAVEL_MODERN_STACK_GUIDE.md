# Modern Laravel (Volt + Livewire + Flux UI + MongoDB) Complete Guide

A comprehensive reference and learning guide covering modern Laravel architecture, Livewire Volt, Flux UI, MongoDB integration, authentication flow, and Blade layout systems.

---

## Table of Contents
1. [Core Concepts: Modern Laravel vs. Traditional MVC](#1-core-concepts-modern-laravel-vs-traditional-mvc)
2. [Project Architecture & File Structure](#2-project-architecture--file-structure)
3. [Building a Full CRUD with Volt & Flux UI](#3-building-a-full-crud-with-volt--flux-ui)
4. [How Authentication (Register, Login, Logout) Works](#4-how-authentication-register-login-logout-works)
5. [Troubleshooting: MongoDB Integration & The `prepare() on null` Fix](#5-troubleshooting-mongodb-integration--the-prepare-on-null-fix)
6. [Master Layouts & Blade Component Architecture (Auth vs. Guest)](#6-master-layouts--blade-component-architecture-auth-vs-guest)
7. [Essential Directives Cheat Sheet](#7-essential-directives-cheat-sheet)

---

## 1. Core Concepts: Modern Laravel vs. Traditional MVC

If you already know traditional Laravel (Routes $\to$ Controller $\to$ Blade View $\to$ Form POST), here is how the modern full-stack tools fit in:

| Technology | What It Is in Plain English | Classic Laravel Equivalent |
| :--- | :--- | :--- |
| **Livewire 3** | Allows you to write dynamic, reactive interfaces using **only PHP and Blade**. When a user clicks or types, Livewire updates the page seamlessly via AJAX without full page reloads. | Standard `<form action="..." method="POST">` with full-page reloads, or custom Vue/React/jQuery APIs. |
| **Volt** | **Single-file Livewire components**. Instead of having two separate files (`app/Livewire/Counter.php` and `resources/views/livewire/counter.blade.php`), Volt lets you write the PHP class and the Blade HTML in the **same file**. | Controller (`PostController.php`) + View (`posts/index.blade.php`). |
| **Flux UI** | A pre-built library of modern UI components (`<flux:button>`, `<flux:input>`, `<flux:modal>`, `<flux:table>`) crafted for Livewire and styled with Tailwind CSS. | Raw HTML tags or building custom Blade components from scratch. |
| **Alpine.js** | A tiny JavaScript library bundled with Livewire for micro-interactions (e.g., opening/closing dropdowns, modals, toggles) directly in the browser without server requests. | Custom JavaScript or jQuery in `<script>` tags. |
| **Tailwind CSS v4** | Utility-first CSS framework (`flex`, `p-4`, `text-sm`, `rounded-lg`). | Bootstrap or custom CSS stylesheets. |

---

## 2. Project Architecture & File Structure

```text
app/
 ├── Models/                   <-- Eloquent Models (User.php, Task.php, etc.)
config/
 ├── auth.php                  <-- Defines auth guards and providers
 └── database.php              <-- Database connection configs (MongoDB, SQLite, MySQL)
database/
 └── migrations/               <-- Database schema migrations
resources/
 └── views/
      ├── components/
      │    └── layouts/        <-- Master layout components (Parent files)
      │         ├── app.blade.php          <-- Authenticated app wrapper
      │         ├── app/sidebar.blade.php  <-- App layout with sidebar
      │         ├── app/header.blade.php   <-- App layout with top header
      │         ├── auth.blade.php         <-- Auth wrapper for Login/Register
      │         └── auth/simple.blade.php  <-- Centered card layout for auth
      ├── livewire/            <-- All Volt components live here (Child files)
      │    ├── auth/
      │    │    ├── login.blade.php        <-- Login logic & form in ONE file
      │    │    └── register.blade.php     <-- Register logic & form in ONE file
      │    └── settings/
      │         └── profile.blade.php      <-- Profile settings component
      ├── dashboard.blade.php  <-- Authenticated dashboard page
      └── welcome.blade.php    <-- Public landing page
routes/
 ├── web.php                   <-- Main web routes & Volt::route() definitions
 └── auth.php                  <-- Authentication routes
```

---

## 3. Building a Full CRUD with Volt & Flux UI

### Step 1: Model & Migration

```bash
php artisan make:model Task -m
```

**Migration:**
```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->boolean('is_completed')->default(false);
    $table->timestamps();
});
```

**Model (`app/Models/Task.php`):**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'is_completed'];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }
}
```

### Step 2: The Single-File Volt Component (`resources/views/livewire/tasks/index.blade.php`)

```blade
<?php

use App\Models\Task;
use Livewire\Volt\Component;

new class extends Component {
    // 1. Reactive State
    public string $title = '';
    public ?string $editingTaskId = null;
    public string $editingTitle = '';

    // 2. Data provider (automatically passed to Blade)
    public function with(): array
    {
        return [
            'tasks' => Task::latest()->get(),
        ];
    }

    // 3. CREATE
    public function addTask(): void
    {
        $this->validate([
            'title' => 'required|string|min:3|max:255',
        ]);

        Task::create(['title' => $this->title]);
        $this->reset('title');
    }

    // 4. UPDATE (Toggle Complete)
    public function toggleComplete(string $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $task->update(['is_completed' => ! $task->is_completed]);
    }

    // 5. UPDATE (Edit Title)
    public function startEdit(string $taskId, string $title): void
    {
        $this->editingTaskId = $taskId;
        $this->editingTitle = $title;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editingTitle' => 'required|string|min:3|max:255',
        ]);

        Task::findOrFail($this->editingTaskId)->update([
            'title' => $this->editingTitle,
        ]);

        $this->editingTaskId = null;
    }

    // 6. DELETE
    public function deleteTask(string $taskId): void
    {
        Task::findOrFail($taskId)->delete();
    }
}; ?>

<x-layouts.app>
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <flux:heading size="xl">Tasks Manager</flux:heading>
            <flux:subheading>Manage your daily todos with Livewire Volt and Flux UI</flux:subheading>
        </div>

        <!-- Create Form -->
        <flux:card>
            <form wire:submit="addTask" class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:input 
                        wire:model="title" 
                        label="New Task" 
                        placeholder="What needs to be done?" 
                        required 
                    />
                </div>
                <flux:button variant="primary" type="submit" icon="plus">
                    Add Task
                </flux:button>
            </form>
        </flux:card>

        <!-- Read / Table -->
        <flux:card class="space-y-3">
            <flux:heading size="lg">All Tasks ({{ $tasks->count() }})</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Task</flux:table.column>
                    <flux:table.column class="text-right">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($tasks as $task)
                        <flux:table.row :key="$task->id">
                            <flux:table.cell>
                                <flux:checkbox 
                                    :checked="$task->is_completed" 
                                    wire:click="toggleComplete('{{ $task->id }}')" 
                                />
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($editingTaskId === (string) $task->id)
                                    <form wire:submit="saveEdit" class="flex gap-2">
                                        <flux:input wire:model="editingTitle" size="sm" class="flex-1" />
                                        <flux:button type="submit" variant="primary" size="sm">Save</flux:button>
                                        <flux:button wire:click="$set('editingTaskId', null)" variant="subtle" size="sm">Cancel</flux:button>
                                    </form>
                                @else
                                    <span class="{{ $task->is_completed ? 'line-through text-zinc-400' : '' }}">
                                        {{ $task->title }}
                                    </span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-right space-x-2">
                                @if ($editingTaskId !== (string) $task->id)
                                    <flux:button 
                                        wire:click="startEdit('{{ $task->id }}', '{{ addslashes($task->title) }}')" 
                                        variant="subtle" 
                                        size="sm" 
                                        icon="pencil-square"
                                    >
                                        Edit
                                    </flux:button>

                                    <flux:button 
                                        wire:click="deleteTask('{{ $task->id }}')" 
                                        wire:confirm="Are you sure you want to delete this task?"
                                        variant="danger" 
                                        size="sm" 
                                        icon="trash"
                                    >
                                        Delete
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center py-6 text-zinc-500">
                                No tasks yet.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts.app>
```

### Step 3: Register Route & Sidebar Link

In `routes/web.php`:
```php
use Livewire\Volt\Volt;

Route::middleware(['auth'])->group(function () {
    Volt::route('tasks', 'tasks.index')->name('tasks.index');
});
```

In `resources/views/components/layouts/app/sidebar.blade.php`:
```blade
<flux:navlist.item 
    icon="check-circle" 
    :href="route('tasks.index')" 
    :current="request()->routeIs('tasks.index')" 
    wire:navigate
>
    Tasks
</flux:navlist.item>
```

---

## 4. How Authentication (Register, Login, Logout) Works

Authentication still uses standard Laravel core logic (`User::create()`, `Auth::attempt()`, `Auth::login()`, `Hash::make()`), but handles forms dynamically inside Volt single-file components.

### A. Routes (`routes/auth.php`)
```php
Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')->name('login');
    Volt::route('register', 'auth.register')->name('register');
});

Route::post('logout', App\Livewire\Actions\Logout::class)->name('logout');
```

### B. Registration Flow (`resources/views/livewire/auth/register.blade.php`)
1. **Form Submission**: `<form wire:submit="register">` triggers `register()` method.
2. **Validation**: `$this->validate([...])` validates name, email, password confirmation.
3. **Password Hashing**: `Hash::make($validated['password'])`.
4. **Database Record**: `User::create($validated)` stores user in database.
5. **Session Login**: `Auth::login($user)` starts the authenticated session.
6. **SPA Redirect**: `$this->redirect(route('dashboard'), navigate: true)`.

### C. Login Flow (`resources/views/livewire/auth/login.blade.php`)
1. **Form Submission**: `<form wire:submit="login">` triggers `login()` method.
2. **Rate Limiting**: `ensureIsNotRateLimited()` checks brute-force attempts.
3. **Database Check (`Auth::attempt()`)**:
   - Checks `config/auth.php` $\to$ provider `users` $\to$ `App\Models\User`.
   - Queries database: `SELECT * FROM users WHERE email = ? LIMIT 1`.
   - Verifies hashed password using `Hash::check($password, $user->password)`.
4. **On Failure**: Throws `ValidationException::withMessages(['email' => __('auth.failed')])` displayed without page refresh.
5. **On Success**: Regenerates session ID and redirects via `$this->redirectIntended(route('dashboard'), navigate: true)`.

---

## 5. Troubleshooting: MongoDB Integration & The `prepare() on null` Fix

### The Problem
When setting `DB_CONNECTION=mongodb`, submitting the login form resulted in:
```text
Call to a member function prepare() on null
at vendor\laravel\framework\src\Illuminate\Database\Connection.php:420
```
along with queue crashes:
```text
Call to a member function getAttribute() on null
at vendor\laravel\framework\src\Illuminate\Queue\DatabaseQueue.php:341
```

### The Root Cause
1. Standard Laravel models extend `Illuminate\Foundation\Auth\User`, which is designed for **relational SQL databases** and expects a **PDO connection** (`$pdo->prepare()`).
2. MongoDB does not use PDO. Calling `$this->getPdo()` returns `null`.
3. Calling `null->prepare()` crashes with `Call to a member function prepare() on null`.
4. Similarly, `QUEUE_CONNECTION=database` requires SQL PDO for popping jobs.

### The Solution

#### 1. Update `app/Models/User.php`:
Change the Authenticatable class from SQL to MongoDB's Authenticatable:
```diff
- use Illuminate\Foundation\Auth\User as Authenticatable;
+ use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
```

#### 2. Update `config/database.php`:
Allow the MongoDB connection to read `DB_DATABASE` (`clonedata2`), `DB_HOST`, and `DB_PORT`:
```php
'mongodb' => [
    'driver' => 'mongodb',
    'dsn' => env('MONGODB_URI', 'mongodb://'.env('DB_HOST', '127.0.0.1').':'.env('DB_PORT', '27017')),
    'database' => env('MONGODB_DATABASE', env('DB_DATABASE', 'clonedata2')),
],
```

#### 3. Update `.env`:
Set queue connection to `sync` to avoid relational table requirements:
```env
QUEUE_CONNECTION=sync
```

---

## 6. Master Layouts & Blade Component Architecture (Auth vs. Guest)

### How Parent & Child Work in Modern Blade

* **Parent (Master Layout)**: Defines the outer HTML shell and contains a **`{{ $slot }}`** placeholder.
* **Child (Page)**: Wraps its content in `<x-layouts.name> ... </x-layouts.name>`. Everything inside gets injected into `{{ $slot }}`.

### Custom Master Layout Example (`resources/views/components/layouts/main.blade.php`)

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen flex flex-col bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">

    <!-- ================= HEADER ================= -->
    <header class="border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <!-- Logo & Navigation -->
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-lg" wire:navigate>
                    <x-app-logo-icon class="size-7 fill-current" />
                    <span>{{ config('app.name') }}</span>
                </a>
                <nav class="hidden md:flex gap-4 text-sm font-medium">
                    <a href="{{ route('home') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Home</a>
                    <a href="#" class="hover:text-blue-600 dark:hover:text-blue-400">About</a>
                </nav>
            </div>

            <!-- Auth State Switcher: @auth vs @guest -->
            <div class="flex items-center gap-4">
                @auth
                    <!-- LOGGED IN USER -->
                    <div class="flex items-center gap-3">
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium hover:underline" wire:navigate>
                            Dashboard
                        </a>

                        <flux:dropdown position="bottom" align="end">
                            <flux:profile 
                                :name="auth()->user()->name" 
                                :initials="auth()->user()->initials()" 
                                class="cursor-pointer" 
                            />
                            <flux:menu class="w-48">
                                <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                        Log Out
                                    </flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                @else
                    <!-- GUEST / LOGGED OUT -->
                    <div class="flex items-center gap-3">
                        <flux:button href="{{ route('login') }}" variant="subtle" size="sm" wire:navigate>
                            Log in
                        </flux:button>
                        @if (Route::has('register'))
                            <flux:button href="{{ route('register') }}" variant="primary" size="sm" wire:navigate>
                                Register
                            </flux:button>
                        @endif
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <!-- ================= CHILD CONTENT INJECTION ================= -->
    <main class="flex-1 max-w-7xl mx-auto w-full px-6 py-8">
        {{ $slot }}
    </main>

    <!-- ================= FOOTER ================= -->
    <footer class="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-6 py-6 text-center text-sm text-zinc-500">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="#" class="hover:underline">Privacy Policy</a>
                <a href="#" class="hover:underline">Terms of Service</a>
            </div>
        </div>
    </footer>

    @fluxScripts
</body>
</html>
```

### Using the Master Layout in Any Child View (`resources/views/about.blade.php`)
```blade
<x-layouts.main>
    <div class="space-y-4">
        <flux:heading size="xl">About Us</flux:heading>
        <p class="text-zinc-600 dark:text-zinc-400">
            This page automatically inherits the Header, Footer, and Auth state!
        </p>
    </div>
</x-layouts.main>
```

---

## 7. Essential Directives Cheat Sheet

| Directive / Tag | Purpose | Example |
| :--- | :--- | :--- |
| `wire:model="property"` | Two-way reactive data binding between input and PHP property | `<flux:input wire:model="title" />` |
| `wire:submit="method"` | Submits form via AJAX, prevents default browser reload | `<form wire:submit="save">` |
| `wire:click="method(param)"` | Triggers a PHP method on click | `<flux:button wire:click="delete('{{ $id }}')">` |
| `wire:confirm="Message"` | Shows confirmation prompt before executing action | `<flux:button wire:confirm="Are you sure?">` |
| `wire:navigate` | Single Page App (SPA) page transitions without page reloads | `<a href="/dashboard" wire:navigate>` |
| `@auth ... @else ... @endauth` | Conditionally renders elements based on user login state | Render user avatar vs. login button |
| `{{ $slot }}` | In master layouts, declares where child view content is inserted | `<main>{{ $slot }}</main>` |
| `<flux:button>` | Pre-styled modern button (`primary`, `subtle`, `danger`) | `<flux:button variant="primary">` |
| `<flux:input>` | Pre-styled input with labels and inline validation error support | `<flux:input wire:model="email" />` |
| `<flux:table>` | Responsive data table with columns and rows | `<flux:table>...</flux:table>` |
