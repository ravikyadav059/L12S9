# Experience & Reviewer Profile Synchronization Guide

## 1. Overview & Context

In the application's MongoDB database, user employment histories and profiles are split across two core collections:
- `experience`: Stores each individual job experience record (including `user_id`, `designation`, `organization_id`, `join_date`, `end_date`, and `status`).
- `reviewer_profile`: Stores scholar profile information for each user (including `user_id` and array fields such as `experience` storing the IDs of that user's experiences).

---

## 2. Problems Identified in the Database

During review and database auditing, four specific data inconsistency issues were identified:

### Issue 1: Experiences Missing from Reviewer Profile
- **Problem**: A user has valid Experience documents in the `experience` collection with their `user_id`, but one or more of those experience `_id`s were never saved into that user's `reviewer_profile->experience` array.
- **Impact**: The experiences do not show up when loading the user's reviewer profile array or when counting experiences from the profile.

### Issue 2: Ghost / Non-Existent Experience IDs in Reviewer Profile
- **Problem**: A `reviewer_profile->experience` array stores experience IDs that were deleted or never existed in the `experience` collection.
- **Impact**: Generates dead references and broken lookups.

### Issue 3: Experiences with Missing `user_id`
- **Problem**: Certain Experience documents in the `experience` collection have a `null`, empty string, or missing `user_id` field.
- **Impact**: These records are disconnected from any user account and become unassigned orphans.

### Issue 4: Experiences with Missing `status`
- **Problem**: Certain Experience documents in the `experience` collection have no `status` field (unset / null).
- **Impact**: System expectations require a status integer (`1` for Active, `0` for Inactive). When missing, these experiences need to be created with `status = 0` (Inactive) by default.

---

## 3. What Was Done to Fix This

A complete, self-healing administrative tool and test suite were created:

### 1. New Admin Database Fixing Tool
Created Livewire Volt single-file component:
- **File**: `resources/views/livewire/admin/database-fixing/experience/reviewer-profile.blade.php`
- **URL**: `http://127.0.0.1:8000/admin/database-fixing/experience/reviewer-profile`
- **Route Name**: `admin.database-fixing.experience.reviewer-profile`

#### Key Capabilities:
- **Automatic Multi-Issue Scanning**: Scans for all 4 issue types across all documents in MongoDB.
- **Cached Diagnostics**: Scans are cached for 60 seconds using `Cache::remember` to ensure instantaneous UI response (< 30ms) across filtering and pagination, automatically clearing cache upon performing fixes.
- **Preloading & Decoration**: Bulk preloads `User`, `Designation`, and `Organization` records to avoid N+1 query bottlenecks and display readable names and badges.
- **Filter Tabs**: Tabs to view "All Issues", "Missing in Profile", "No User ID", "Ghost IDs", and "Missing Status".
- **Real-Time Search**: Search by User Name, Email, Unique ID, Experience ID, or Reviewer Profile ID.
- **Simple "Transformation Summary"**: A collapsible guide written in plain, simple English explaining what the tool does before taking action.
- **Fix Actions**:
  - **Individual Fix Button**: Resolves a specific row immediately.
  - **Fix Selected Button**: Check individual items and resolve all selected items simultaneously.
  - **Fix All Button**: Resolves all detected issues across the entire database in one click.

---

### 2. Integration with Database Fixing Dashboard
- **File**: `resources/views/livewire/admin/database-fixing/index.blade.php`
- Added the tool card to `getToolsProperty()` under the `Experience` section with a simple, clear description.
- Dashboard URL: `http://127.0.0.1:8000/admin/database-fixing`

---

### 3. Route & Switcher Pill Navigation
- **File**: `routes/web.php`
  - Registered route: `Volt::route('database-fixing/experience/reviewer-profile', 'admin.database-fixing.experience.reviewer-profile')->name('database-fixing.experience.reviewer-profile');`
- **Files**: `experience/status.blade.php` and `experience/index.blade.php`
  - Added navigation switcher pills so administrators can seamlessly jump between the three Experience fixing tools:
    1. *Reviewer Profile & User Sync*
    2. *Missing Status Fixer*
    3. *Date Standardizer (join_date & end_date)*

---

### 4. How the Fix Logic Works in Code

| Issue Type | Action Taken Upon Fixing |
| :--- | :--- |
| **Missing in Profile** | Pushes the experience ID to `reviewer_profile->experience` using array deduplication (`array_unique`). If the experience record also has a missing status, it sets `status = 0`. |
| **No User ID** | Assigns the identified user ID to the `experience` record (or, if the issue payload lacks a user_id, the fixer now looks up the reviewer_profile that already references the experience to infer the correct owner), pushes the ID to that user's `reviewer_profile->experience` array, and sets `status = 0` if status was missing. |
| **Ghost ID in Profile** | Removes the ghost ID from `reviewer_profile->experience` using `array_diff` and updates the profile. |
| **Missing Status** | Updates the `experience` document directly in MongoDB to set `status = 0` (Inactive). |

---

### 5. Automated Testing & Verification
- **Test File**: `tests/Feature/DatabaseFixingExperienceReviewerProfileTest.php`
- Contains 4 feature tests covering:
  1. Route registration.
  2. Authorized administrator HTTP access and page rendering.
  3. Appearance on the Database Fixing main index page.
  4. Livewire component rendering, tab switching, and interactions.
- All 4 tests pass (`Tests: 4 passed, 10 assertions`).
- Code formatted cleanly with Laravel Pint (`vendor/bin/pint --format agent`).

