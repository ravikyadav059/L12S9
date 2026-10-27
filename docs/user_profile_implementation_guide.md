# Scholar9 — User Profile Feature Implementation Guide

> **AI Agent Notice**: When this file is mentioned in chat, read it fully before writing any code.
> This document defines the **complete rules, goals, architecture, and constraints** for building the User Profile feature.

---

## 1. Goals & Overview

We are building the **User Profile page** for Scholar9. This single Volt component serves **two modes**:

| Mode | URL | Who sees it | CRUD? |
|---|---|---|---|
| **Shareable / Public Profile** | `/profile/{slug}` (other user's slug) | Anyone | ❌ No edit/delete/add |
| **Own Profile View** | `/profile/{slug}` (logged-in user's own slug) | Logged-in user | ✅ Full CRUD via modals |
| **Admin Panel Edit** | `/admin/users/{id}/edit` (separate admin page) | Admin only | ✅ Full CRUD |

> **Key point**: The public profile and own-profile view are the **same Livewire Volt component and same URL**. We detect the mode in the component by comparing `auth()->id()` with `$user->_id`. The admin has a completely **separate page** under the admin layout.

---

## 2. Architecture — One Page, Two Modes

### 2.1 Volt Component Logic

```php
new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';
    public ?User $user = null;
    public bool $isOwnProfile = false;

    public function mount(string $slug): void
    {
        $this->slug  = $slug;
        $this->user  = User::where('slug', $slug)
            ->with(['experiences']) // eager load — no N+1
            ->first();

        // Mode detection: same page, different behaviour
        $this->isOwnProfile = auth()->check()
            && $this->user
            && (string) auth()->id() === (string) $this->user->_id;
    }
};
```

### 2.2 Template Mode Guard

Use a single Blade conditional everywhere CRUD buttons/controls appear:

```blade
@if($isOwnProfile)
    {{-- Edit, Add, Delete buttons visible here only --}}
    <button @click="openEditModal('about')">Edit</button>
@endif
```

**Never** show CRUD controls to other visitors or guests.

---

## 3. CRUD Rules — What Can Be Done Where

### User Profile (Own View — Same Page)

| Section | Add | Edit | Delete |
|---|---|---|---|
| About / Bio | ✅ | ✅ | — |
| Experience | ✅ | ✅ | ✅ |
| Education | ✅ | ✅ | ✅ |
| Projects | ✅ | ✅ | ✅ |
| Conferences | ✅ | ✅ | ✅ |
| Certificates | ✅ | ✅ | ✅ |
| Achievements | ✅ | ✅ | ✅ |
| Thesis Guided | ✅ | ✅ | ✅ |
| Memberships | ✅ | ✅ | ✅ |
| Patents | ✅ | ✅ | ✅ |
| Invited Position | ✅ | ✅ | ✅ |
| Role in Research Journals | ✅ | ✅ | ✅ |

### Admin Panel (Separate Page — Admin Layout)

- Admin can **edit any field** of any user: name, email, status, role, type, affiliation, etc.
- Admin can **view the same user profile data** (same DB, same queries).
- Admin page lives at `/admin/users/{id}/edit` under `components.layouts.admin`.
- Admin and user **never share the same Volt component** — they are separate files.

### Shareable / Public Profile (Other User)

- **Zero CRUD controls rendered** — no buttons, no modals triggered.
- Read-only display of all sections.
- `[Follow]` button visible (this is a Livewire action, not a modal).

---

## 4. Modal Rules — Client-Side Only (Alpine.js)

> **STRICT RULE**: Modals for Add / Edit / Delete MUST open and close using **Alpine.js only** (`x-data`, `x-show`, `@click`).
> **NEVER** trigger a Livewire/server network request just to open or close a modal.
> Only send a server request when **submitting** the form inside the modal (`wire:submit`).

### Correct Pattern

```blade
{{-- Alpine controls open/close state — zero server requests --}}
<div x-data="{ showModal: false, editingItem: null }">

    {{-- Trigger button — no wire: directive here --}}
    <button @click="showModal = true; editingItem = {{ json_encode($item) }}">
        Edit
    </button>

    {{-- Modal — pure Alpine visibility --}}
    <div x-show="showModal" x-cloak @keydown.escape.window="showModal = false">
        <div @click.outside="showModal = false">

            {{-- Form — only HERE does the server get called --}}
            <form wire:submit="saveExperience">
                <input wire:model="form.title" />
                <button type="submit">Save</button>
            </form>

            <button @click="showModal = false">Cancel</button>
        </div>
    </div>
</div>
```

### Closing Modal After Server Action

After a successful Livewire save, dispatch a browser event to close Alpine:

```php
// In the Volt component method:
public function saveExperience(): void
{
    // ... save to DB ...
    $this->dispatch('close-modal'); // closes Alpine modal on client
}
```

```blade
{{-- Alpine listens for the close event --}}
<div x-data="{ showModal: false }"
     @close-modal.window="showModal = false">
```

---

## 5. Layout & Design System

### 5.1 Two Separate Layouts

| View | Layout Component | File |
|---|---|---|
| User Profile (public + own) | `components.layouts.app` | `resources/views/components/layouts/app.blade.php` |
| Admin Panel | `components.layouts.admin` | `resources/views/components/layouts/admin.blade.php` |

Both layouts pull data from the **same MongoDB database**, but render entirely different chrome (header, sidebar, footer).

### 5.2 Tech Stack (Existing — Do Not Change)

| Layer | Technology |
|---|---|
| Backend | Laravel 12 + PHP 8.2 |
| DB | MongoDB (`mongodb/laravel-mongodb`) |
| Component | Livewire Volt (single-file) |
| Styling | Tailwind CSS (existing utility classes) |
| JS interactivity | Alpine.js |
| UI Components | Flux UI (`<flux:*>`) where existing components already exist |
| Modals | **Plain Alpine.js `x-show`** — no Flux modal for CRUD modals |

### 5.3 Design Reference

The visual design target is the HTML file at:
```
Design page/user_profile/Hitesh Patel Research Profile _ Scholar9.html
```

Open this file in a browser to verify UI alignment during development.

**Key design rules from that reference:**

| Element | Rule |
|---|---|
| Hero banner | Full-width solid sky-blue (`#1d8deb`) background |
| Avatar | **Circle** `rounded-full`, real photo via `$user->avatar`, ~96px |
| Verified badge | Green circle checkmark beside name (`$user->verified`) |
| Stats bar | White card, 4-col grid: Publications · Followers · Following · Questions |
| Section nav | Sticky anchor nav with 13 links, blue underline = active |
| Page body layout | 2-column: Main content (~70%) + Sidebar (~30%) |
| Card shell | `bg-white rounded-xl border border-zinc-200 shadow-sm p-6` |
| Dark mode card | `dark:bg-[#111c26]` (not `dark:bg-zinc-800`) |
| Timeline items | Vertical blue dots + institution logo + content (Experience & Education) |
| Section heading | Emoji icon + bold title + item count in parentheses |
| Brand color | `text-brand`, `bg-brand/10`, `border-brand/20` (sky blue `#198BEA`) |

---

## 6. Page Sections to Build (13 Main + Sidebar Widgets)

### Main Content Column
1. **About** — Bio text (`$user->scholar_profile`)
2. **Experience** — Timeline, from `$user->experiences()` relation
3. **Education** — Timeline, embedded or new relation
4. **Publications** — Article model relation (paginated)
5. **Projects** — Embedded or new relation
6. **Conferences** — Embedded or new relation
7. **Certificates** — Embedded or new relation
8. **Awards & Achievements** — Embedded or new relation
9. **Thesis Guided** — Embedded or new relation
10. **Memberships** — Embedded or new relation
11. **Patents** — Embedded or new relation
12. **Invited Position** — Embedded or new relation
13. **Role in Research Journals** — Reviewer profile / journal role relation

### Sidebar Column (Widgets)
- **Quick Navigation** — Anchor links to all 13 sections, active highlighted in blue
- **You May Know** — Suggested users with Follow buttons
- **Followers** — List of this user's followers with photos/avatars
- **Questions I Asked** — Recent questions by this user

---

## 7. Database & Data Model

### 7.1 User Model Fields (Key Fields for Profile)

| Field | Type | Used In |
|---|---|---|
| `slug` | string | Route lookup — `/profile/{slug}` |
| `unique_id` | string | Scholar9 ID display e.g. `S9-052023-0500004` |
| `first_name`, `last_name`, `fullname` | string | Display name |
| `photo` | string | Avatar — always use `$user->avatar` accessor |
| `email` | string | Shown below name |
| `affiliation` | string | Job title / institution |
| `scholar_profile` | string | Bio / About text |
| `mobile` | string | Contact info |
| `countryiso2` | string | Country flag display |
| `followers` | array | Array of follower user IDs |
| `following` | array | Array of following user IDs |
| `verified` | mixed | Verified badge flag |
| `badge_type` | string | Badge level |
| `role` | string | Role pill (e.g. publisher, reviewer) |
| `publisher_name` | string | Publisher organization affiliation |

### 7.2 Model Helper Methods

| Method | Returns | Notes |
|---|---|---|
| `$user->avatar` | string (URL or SVG) | Photo or auto-generated SVG initials |
| `$user->initials()` | string (1-2 chars) | From first+last name |
| `$user->name` | string | Accessor: first_name + last_name |
| `$user->followersCount()` | int | `count($this->followers)` |
| `$user->followingCount()` | int | `count($this->following)` |
| `$user->isFollowing($user)` | bool | Check if auth user follows this profile |
| `$user->experiences()` | HasMany | Experience records |
| `$user->reviewerProfile()` | HasOne | ReviewerProfile record |

---

## 8. Performance Rules — from HIGH_SCALE_OPTIMIZATION_GUIDE.md

> **This project handles 1,000,000+ records. These rules are mandatory, not optional.**
> Full reference: `HIGH_SCALE_OPTIMIZATION_GUIDE.md` (project root).

### 8.1 Core Four Rules

1. **Strict Pagination & Projection** — Never `get()` or `all()` on large collections without limit. Always use `paginate()`, `limit()`, and `select([...])` to project only needed fields.
2. **Minimal Network Round-Trips** — Use MongoDB `$facet` aggregation to get count + data in a single DB call (not two separate queries). See §2 of the optimization guide.
3. **Zero N+1 Queries** — Eager-load all relations in `mount()`. Never query inside a `foreach` loop.
4. **Client-Side Reactive UI** — Alpine.js handles all UI state (selections, modal open/close, tab switching). Server requests only for real data mutations.

### 8.2 Publications Query — Single-Pass Aggregation Pattern

When loading paginated publications on the profile page, use `$facet` for one round-trip:

```php
$pipeline = [
    ['$match' => ['author_id' => $this->user->_id, 'status' => '1']],
    [
        '$facet' => [
            'metadata' => [['$count' => 'total']],
            'data'     => [
                ['$sort'   => ['created_at' => -1]],
                ['$skip'   => ($this->page - 1) * $this->perPage],
                ['$limit'  => $this->perPage],
                ['$project' => [
                    'title'         => 1,
                    'journal_title' => 1,
                    'abstract'      => 1,
                    'created_at'    => 1,
                    'slug'          => 1,
                ]],
            ],
        ],
    ],
];
$result = Article::raw(fn ($col) => $col->aggregate($pipeline))->first();
```

### 8.3 Search / Filter Debouncing

Any search input on the profile (e.g. filtering publications) must debounce:
```blade
<input wire:model.live.debounce.400ms="search" />
```

---

## 9. Admin Panel — Separate Component, Same DB

The admin user edit is a **separate Volt file** (not this component):

- **Route**: `/admin/users/{id}/edit`
- **Layout**: `components.layouts.admin`
- **Volt file**: inside `resources/views/livewire/admin/users/`
- **Can edit**: All User model fields (name, email, status, type, role, affiliation, etc.)
- **Same MongoDB `users` collection** — same `User` model — different UI and layout
- Admin modals follow the **same Alpine.js-only rule** — no server calls to open/close

> Do not mix admin functionality into the public/own-profile component.

---

## 10. File Locations Reference

| File | Purpose |
|---|---|
| `resources/views/livewire/userprofile.blade.php` | Main profile Volt component (public + own view) |
| `resources/views/livewire/admin/users/` | Admin user management area (separate) |
| `app/Models/User.php` | User model — do not change schema without review |
| `app/Models/Experience.php` | Experience model (verify exists or create) |
| `routes/web.php` (line 134) | `Volt::route('profile/{slug}', 'userprofile')->name('userscholar')` |
| `Design page/user_profile/*.html` | Visual design reference — open in browser to verify |
| `HIGH_SCALE_OPTIMIZATION_GUIDE.md` | Performance patterns — always follow §1, §2, §3, §4 |
| `docs/user_profile_implementation_guide.md` | **This file** |

---

## 11. Quick Decision Reference

| Question | Answer |
|---|---|
| Admin and user share the same Volt component? | **No** — separate files, same DB |
| Profile page detects own vs. public mode? | **Yes** — compare `auth()->id()` with `$user->_id` |
| CRUD buttons on shareable/public profile? | **No** — always hidden for other users |
| Modals open via Livewire server call? | **No** — Alpine.js `x-show` only |
| Modals submit via Livewire? | **Yes** — `wire:submit` on form inside modal |
| Use `get()` on large collections? | **No** — always paginate or limit |
| Layout for user-facing profile? | `components.layouts.app` |
| Layout for admin? | `components.layouts.admin` |
| Avatar shape? | **Circle** `rounded-full` (per design reference) |
| Dark mode card background? | `dark:bg-[#111c26]` (not `dark:bg-zinc-800`) |
| DB connection for both? | Same MongoDB `users` collection |
| All 13 sections in one Blade file? | **No** — separate Blade component per section |
| Separate modals for Add and Edit? | **No** — one shared modal per section, Alpine detects mode |

---

## 12. File Structure — Separate Blade Component Per Section

> **Rule**: Never put all 13 profile sections into one Blade file. Each section lives in its own
> Blade component file under `resources/views/components/profile/`.
> The main Volt file stays clean — it only loads data and calls section components.

### Directory Layout

```
resources/views/
│
├── livewire/
│   └── userprofile.blade.php          ← Main Volt file (data loading + mode detection only)
│
└── components/
    └── profile/                       ← All section Blade components live here
        ├── hero.blade.php             ← Hero banner (avatar, name, verified, job title)
        ├── stats-bar.blade.php        ← Publications / Followers / Following / Questions
        ├── section-nav.blade.php      ← Sticky anchor navigation (13 tabs)
        ├── about.blade.php
        ├── experience.blade.php
        ├── education.blade.php
        ├── publications.blade.php
        ├── projects.blade.php
        ├── conferences.blade.php
        ├── certificates.blade.php
        ├── achievements.blade.php
        ├── thesis-guided.blade.php
        ├── memberships.blade.php
        ├── patents.blade.php
        ├── invited-position.blade.php
        ├── research-journals.blade.php
        └── sidebar/
            ├── quick-navigation.blade.php
            ├── you-may-know.blade.php
            ├── followers.blade.php
            └── questions.blade.php
```

### Main Volt File (Clean Orchestrator)

The `userprofile.blade.php` Volt file only orchestrates — it passes `$user` and `$isOwnProfile`
down to each component:

```blade
{{-- Hero --}}
<x-profile.hero :user="$user" :is-own-profile="$isOwnProfile" />

{{-- Stats --}}
<x-profile.stats-bar :user="$user" />

{{-- Sticky Section Nav --}}
<x-profile.section-nav />

{{-- 2-Column Body --}}
<div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 max-w-7xl mx-auto px-4 py-6">

    {{-- Main Column --}}
    <div class="space-y-4">
        <x-profile.about          :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.experience     :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.education      :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.publications   :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.projects       :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.conferences    :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.certificates   :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.achievements   :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.thesis-guided  :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.memberships    :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.patents        :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.invited-position    :user="$user" :is-own-profile="$isOwnProfile" />
        <x-profile.research-journals   :user="$user" :is-own-profile="$isOwnProfile" />
    </div>

    {{-- Sidebar Column --}}
    <aside class="space-y-4">
        <x-profile.sidebar.quick-navigation />
        <x-profile.sidebar.you-may-know :user="$user" />
        <x-profile.sidebar.followers :user="$user" />
        <x-profile.sidebar.questions :user="$user" />
    </aside>

</div>
```

### Each Section Component Skeleton

Every section component follows the same shell:

```blade
{{-- resources/views/components/profile/experience.blade.php --}}
@props(['user', 'isOwnProfile' => false])

<div id="experience" class="bg-white dark:bg-[#111c26] rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm p-6">

    {{-- Section Header --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="flex items-center gap-2 text-base font-bold text-zinc-900 dark:text-white">
            💼 Experience ({{ $user->experiences->count() }})
        </h2>
        @if($isOwnProfile)
            <button ...>+ Add</button>  {{-- triggers Alpine modal --}}
        @endif
    </div>

    {{-- Section Content --}}
    @forelse($user->experiences as $exp)
        ...timeline item...
        @if($isOwnProfile)
            <button ...>Edit</button>   {{-- triggers Alpine modal with item data --}}
            <button ...>Delete</button>
        @endif
    @empty
        <p class="text-sm text-zinc-400">No experience added yet.</p>
    @endforelse

    {{-- Add / Edit Modal (own profile only) --}}
    @if($isOwnProfile)
        {{-- See Section 13 for the full modal pattern --}}
        <x-profile.modals.experience />
    @endif

</div>
```

---

## 13. Add + Edit — One Shared Modal Per Section

> **Rule**: Do NOT create two separate modals for Add and Edit.
> Use **one shared modal** per section. Alpine.js `editingItem` controls which mode it is in.
> `editingItem = null` → Add mode. `editingItem = { data }` → Edit mode.

### How It Works

```
User clicks "+ Add"       → Alpine sets editingItem = null  → Livewire resets form  → modal opens
User clicks "Edit" on row → Alpine sets editingItem = item  → Livewire fills form   → modal opens
User submits form         → wire:submit fires               → Livewire save() runs  → modal closes
```

### Alpine State (on the section component)

```blade
<div x-data="{
    showModal: false,
    editingItem: null,

    openAdd() {
        this.editingItem = null;
        this.$wire.resetExperienceForm();   {{-- clear Livewire form properties --}}
        this.showModal = true;
    },

    openEdit(item) {
        this.editingItem = item;
        this.$wire.fillExperienceForm(item); {{-- pre-fill Livewire form with item data --}}
        this.showModal = true;
    },

    closeModal() {
        this.showModal = false;
        this.editingItem = null;
    }
}"
@close-experience-modal.window="closeModal()"
>

    {{-- Add button --}}
    @if($isOwnProfile)
        <button @click="openAdd()">+ Add Experience</button>
    @endif

    {{-- Each item row --}}
    @foreach($user->experiences as $exp)
        <div>
            {{ $exp->title }} ...
            @if($isOwnProfile)
                <button @click="openEdit({{ $exp->toJson() }})">Edit</button>
                <button wire:click="deleteExperience('{{ $exp->_id }}')">Delete</button>
            @endif
        </div>
    @endforeach

    {{-- ONE shared modal — title and button text change dynamically --}}
    <div x-show="showModal" x-cloak @keydown.escape.window="closeModal()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div @click.outside="closeModal()"
             class="bg-white dark:bg-[#111c26] rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4">

            {{-- Dynamic title --}}
            <h3 class="text-lg font-bold mb-4"
                x-text="editingItem ? 'Edit Experience' : 'Add Experience'">
            </h3>

            {{-- Form — fires server only on submit --}}
            <form wire:submit="saveExperience">
                <input wire:model="expForm.title"        placeholder="Job Title" />
                <input wire:model="expForm.organization" placeholder="Organization" />
                <input wire:model="expForm.date_from"    placeholder="From (e.g. Jan 2020)" />
                <input wire:model="expForm.date_to"      placeholder="To (or Present)" />
                <textarea wire:model="expForm.description"></textarea>

                <div class="flex gap-2 mt-4">
                    {{-- Dynamic button text --}}
                    <button type="submit" class="btn-primary"
                            x-text="editingItem ? 'Update' : 'Add'">
                    </button>
                    <button type="button" @click="closeModal()" class="btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
```

### Livewire (Volt) Methods for One Section

In the Volt file (`userprofile.blade.php`), add these methods for each section:

```php
// Form state — public properties for the form fields
public array $expForm = [
    'id'           => null,   // null = Add mode, string = Edit mode
    'title'        => '',
    'organization' => '',
    'date_from'    => '',
    'date_to'      => '',
    'description'  => '',
];

// Called by Alpine when "+ Add" is clicked — clears the form
public function resetExperienceForm(): void
{
    $this->expForm = ['id' => null, 'title' => '', 'organization' => '',
                      'date_from' => '', 'date_to' => '', 'description' => ''];
}

// Called by Alpine when "Edit" is clicked — fills form with existing data
public function fillExperienceForm(array $item): void
{
    $this->expForm = [
        'id'           => $item['_id'] ?? $item['id'] ?? null,
        'title'        => $item['title'] ?? '',
        'organization' => $item['organization'] ?? '',
        'date_from'    => $item['date_from'] ?? '',
        'date_to'      => $item['date_to'] ?? '',
        'description'  => $item['description'] ?? '',
    ];
}

// Handles BOTH Add and Update — checks if ID is present
public function saveExperience(): void
{
    $this->validate([
        'expForm.title'        => 'required|string|max:200',
        'expForm.organization' => 'required|string|max:200',
        'expForm.date_from'    => 'required|string',
    ]);

    $data = [
        'user_id'      => (string) $this->user->_id,
        'title'        => $this->expForm['title'],
        'organization' => $this->expForm['organization'],
        'date_from'    => $this->expForm['date_from'],
        'date_to'      => $this->expForm['date_to'],
        'description'  => $this->expForm['description'],
    ];

    if (!empty($this->expForm['id'])) {
        // Edit mode — update existing record
        Experience::where('_id', $this->expForm['id'])
                  ->where('user_id', (string) $this->user->_id) // security: own record only
                  ->update($data);
    } else {
        // Add mode — create new record
        Experience::create($data);
    }

    // Refresh the relation on the user object (no full page reload)
    $this->user->setRelation('experiences', $this->user->experiences()->get());

    // Dispatch event → Alpine closes the modal (no server round-trip)
    $this->dispatch('close-experience-modal');
}

// Delete
public function deleteExperience(string $id): void
{
    Experience::where('_id', $id)
              ->where('user_id', (string) $this->user->_id) // security check
              ->delete();

    $this->user->setRelation('experiences', $this->user->experiences()->get());
}
```

### Summary of Shared Modal Pattern

| | Detail |
|---|---|
| **Modals per section** | **1** (shared for Add + Edit) |
| **Mode detection** | `editingItem === null` = Add, `editingItem = object` = Edit |
| **Form fill (Add)** | `resetExperienceForm()` — called via `$wire` from Alpine |
| **Form fill (Edit)** | `fillExperienceForm(item)` — called via `$wire` from Alpine |
| **Save logic** | `saveExperience()` — checks `expForm.id` to decide insert vs update |
| **Modal close** | `$this->dispatch('close-experience-modal')` → Alpine closes |
| **Security** | Always filter by `user_id = auth()->id()` in save + delete |
| **Server calls for open/close?** | **Never** — Alpine only |

---

## 14. Development Workflow — How We Build Each Section

> **AI Agent Notice**: Follow this workflow strictly for every section. Do not skip steps or jump ahead.

### 14.1 No Automated Tests

> ⛔ **ABSOLUTE RULE**: Do NOT write or run any tests — ever — unless the user explicitly
> types the words **"test this"** in the chat. This rule has no exceptions.

- **Never** create `*Test.php` or `*test.php` files for this feature.
- **Never** run `php artisan test` on your own initiative.
- **Never** run `vendor/bin/pest` on your own initiative.
- We verify everything **manually by looking at the browser**.
- If you feel the urge to write a test — **stop** and move on to the next step instead.

### 14.2 Section-by-Section Order

Work on **one section at a time**. Do not start the next section until the current one is
confirmed complete by the user.

**Section build order** (follow this sequence):
1. Hero Banner + Stats Bar + Sticky Nav (foundation — build first)
2. About
3. Experience
4. Education
5. Publications
6. Projects
7. Conferences
8. Certificates
9. Awards & Achievements
10. Thesis Guided
11. Memberships
12. Patents
13. Invited Position
14. Role in Research Journals
15. Sidebar widgets (Quick Navigation, You May Know, Followers, Questions)

### 14.3 UI First, Backend Second (Per Section)

For **each section**, always follow this two-step order:

```
STEP 1 — UI Only
─────────────────────────────────────────────────────
  • Build the Blade component file for the section
  • Use hardcoded/static dummy data (no DB queries yet)
  • Get the layout, design, and modal working perfectly
  • Verify it matches the design reference (Design page/user_profile/*.html)
  • User confirms: "UI looks good"

STEP 2 — Backend Wiring
─────────────────────────────────────────────────────
  • Connect real data ($user->relation or DB query)
  • Add Livewire methods: resetForm(), fillForm(), save(), delete()
  • Wire the modal form to Livewire properties
  • Test manually in browser with real data
  • User confirms: "Working correctly"

Only then → move to the next section
```

### 14.4 Dummy Data Pattern for UI Step

When building the UI step, use simple hardcoded arrays so the design can be verified
without needing a database:

```blade
{{-- Temporary dummy data for UI verification only --}}
@php
$experiences = [
    ['title' => 'Director', 'organization' => 'Janvi Wave LLP',
     'date_from' => 'Aug 2025', 'date_to' => 'Present',
     'description' => 'Director of Research Journal and Publication company'],

    ['title' => 'Assistant Professor', 'organization' => 'Gandhinagar Institute of Technology',
     'date_from' => 'Feb 2013', 'date_to' => 'Aug 2025',
     'description' => 'Working as Assistant Professor'],
];
@endphp

@foreach($experiences as $exp)
    {{-- render timeline item --}}
@endforeach
```

Remove the dummy data in Step 2 and replace with `$user->experiences`.

### 14.5 Summary Table

| Rule | Value |
|---|---|
| Write automated tests? | **No** — manual browser verification only |
| Work on multiple sections at once? | **No** — one section at a time |
| Build order within a section? | **UI first → confirmed → then backend** |
| Use dummy data during UI step? | **Yes** — hardcoded `@php` arrays |
| Move to next section when? | Only after user confirms current section is done |
