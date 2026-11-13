# Reviewer Profile Synchronization & Database Audit Status

## 1. Executive Summary

This document tracks the synchronization and data integrity status across all 12 collections linked to scholar profiles in `reviewer_profile`.

To protect live data and prioritize where users actively interact with scholar profiles, the synchronization tools are rolled out based on **whether active profile management forms are currently implemented for both Administrators and End-Users**.

Sections that already have full Admin and User profile editing forms are scheduled for immediate repair in **Phase 2**, while sections without user-facing forms remain **Pending / Deferred** until their corresponding interfaces are built.

---

## 2. Global Reviewer Profile Status Matrix

| Section / Collection | Total Documents | Missing `user_id` | Ghost IDs in Profile | Admin & User Form Created? | Implementation Status | Phase / Roadmap |
| :--- | :--- | :--- | :--- | :---: | :--- | :--- |
| **`experience`** | 14,435 | 5 | 0 | Yes | **Completed** | Production (`experience/reviewer-profile`) |
| **`award`** | 5,004 | 30 *(marked 0)* | 0 | Yes | **Completed** | Production (`award/reviewer-profile`) |
| **`phd` (thesis)** | 2,119 | 2 *(marked 0)* | 0 | Yes | **Completed** | Production (`phd/reviewer-profile`) |
| **`patent`** | 505 | 500 *(493 recoverable)* | 0 | Yes | **Completed** | Production (`patent/category` & `patent/reviewer-profile`) |
| **`membership`** | 5,379 | 5,364 *(5,362 recoverable)* | 1 | **Yes** | **Completed** | Production (`membership/organization` & `membership/reviewer-profile`) |
| **`seminar`** | 23,624 | 23,619 *(23,611 recoverable)* | 0 | **Yes** | **Completed** | Production (`seminar/index` & `seminar/reviewer-profile`) |
| **`education`** | 17,693 | 55 *(6 recoverable)* | 0 | **Yes** | **Ready for Phase 2B** | Next Immediate Candidate (55 orphans) |
| **`project`** | 3,753 | 11 | 0 | **Yes** | **Ready for Phase 2** | Phase 2B (Contributor fixer done, sync next) |
| **`certificate`** | 4,953 | 5 | 0 | **Yes** | **Ready for Phase 2** | Phase 2B (Clean array, ready for sync) |
| **`RoleInResearchJournals`** | 319 | 80 *(75 recoverable)* | 86 | **Yes** | **Completed** | Production (`role-in-research-journals/role` & `role-in-research-journals/reviewer-profile`) |
| **`invited_position`** | 376 | 373 *(372 recoverable)* | 0 | **No** (Direct DB module) | **Completed** | Production (`invited-position/organization` & `invited-position/reviewer-profile`) |
| **`publication`** | 27,056 | 26,954 | 0 | **No** (Dedicated module) | **Pending / Deferred** | Managed via dedicated Publications module |

---

## 3. Section Details

### A. Completed Sync Tools (In Production)
1. **Experience** (`experience` collection)
   - Route: `/admin/database-fixing/experience/reviewer-profile`
   - Form locations:
     - Admin: `resources/views/livewire/admin/users/partials/experience.blade.php`
     - User: `resources/views/livewire/profile/experience.blade.php`
   - Status: Complete. Bidirectional linking, ghost purge, and status standardizer in production.

2. **Award** (`award` collection)
   - Route: `/admin/database-fixing/award/reviewer-profile`
   - Form locations:
     - Admin: `resources/views/livewire/admin/users/partials/awards.blade.php`
     - User: `resources/views/livewire/profile/awards.blade.php`
   - Status: Complete. Bidirectional linking, orphan status normalization (`status = 0`), and ghost purge in production.

3. **PhD / Thesis** (`phd` collection)
   - Route: `/admin/database-fixing/phd/reviewer-profile`
   - Form locations:
     - Admin: `resources/views/livewire/admin/users/partials/thesis.blade.php`
     - User: `resources/views/livewire/profile/thesis.blade.php`
   - Status: Complete. Bidirectional linking, orphan status normalization (`status = 0`), and ghost purge in production.

4. **Membership** (`membership` collection)
   - Route: `/admin/database-fixing/membership/reviewer-profile`
   - Form locations:
     - Admin: `resources/views/livewire/admin/users/partials/memberships.blade.php`
     - User: `resources/views/livewire/profile/memberships.blade.php`
   - Status: Complete. Bidirectional linking, 5,362 user IDs recoverable, ghost purge, and status normalizer in production.
   - Additional Tool: Organization ID Fixer (`membership/organization`).

5. **Seminar** (`seminar` collection)
   - Route: `/admin/database-fixing/seminar/reviewer-profile`
   - Form locations:
     - Admin: `resources/views/livewire/admin/users/partials/seminars.blade.php`
     - User: `resources/views/livewire/profile/seminars.blade.php`
   - Status: Complete. Bidirectional linking, 23,611 user IDs recoverable, ghost purge, and status normalizer in production.
   - Additional Tool: Date Standardizer (`seminar/index`).

---

### B. Phase 1: Patent (Completed in Production)
- **Phase 1A: Patent Category Fixer** (`patent/category.blade.php`)
  - Route: `/admin/database-fixing/patent/category`
  - Validates MongoDB ObjectIds on `category_id`.
  - Target `"63bd3558bbb88ffa4c09ecf5"` verified as valid category `"Art & Humanities"`.
  - Fixes legacy text strings by matching case-insensitively against `category.category_title`.
  - Resolves empty/null categories.
- **Phase 1B: Patent ↔ Reviewer Profile Sync** (`patent/reviewer-profile.blade.php`)
  - Route: `/admin/database-fixing/patent/reviewer-profile`
  - Recovers `user_id` on 493 patents from `reviewer_profile.patent` arrays.
  - Marks remaining 7 unattached records as inactive orphans (`status = 0`).
  - Purges ghost references and synchronizes bidirectional relationships.

---

### C. Invited Position (Completed in Production)
1. **Invited Position Organization Fixer** (`invited-position/organization.blade.php`)
   - Route: `/admin/database-fixing/invited-position/organization`
   - Scans 376 records for 8 ghost `organization_id` values and 43 missing/null values.
   - Auto-matches organization from `website_url` against `organization.website_url` / domain names.
   - Live interactive search and 1-click modal assignment across 37,000+ organizations.
   - 1-click batch and individual cleaning of ghost IDs to `null`.
2. **Invited Position ↔ Reviewer Profile Sync** (`invited-position/reviewer-profile.blade.php`)
   - Route: `/admin/database-fixing/invited-position/reviewer-profile`
   - Recovers missing `user_id` on 372 records from `reviewer_profile.invited_position` arrays (status set to 1).
   - Marks 1 true unlinked orphan as `status = 0` (Inactive orphan).
   - Removes ghost position IDs from reviewer profiles and adds missing records.
   - Dual status support (`1`, `'1'` for active; `0`, `'0'` for inactive).

---

### C. Phase 2: Sections with Completed Admin & User Profile Forms

These collections have live profile management forms for both Admin and User in the application, and are scheduled for Phase 2B:

1. **Education** (`education` collection) — **Immediate Next**
   - **Form locations**:
     - Admin: `resources/views/livewire/admin/users/partials/education.blade.php`
     - User: `resources/views/livewire/profile/education.blade.php`
   - **Current Data**: 17,693 docs, 55 missing `user_id`, 0 ghost IDs.
   - **Prerequisite Done**: Degree, grade, institution, and field-of-study cleaners.

4. **Project** (`projects` collection)
   - **Form locations**:
     - Admin: `resources/views/livewire/admin/users/partials/projects.blade.php`
     - User: `resources/views/livewire/profile/projects.blade.php`
   - **Current Data**: 3,753 docs, 11 missing `user_id`, 0 ghost IDs.
   - **Prerequisite Done**: Registered contributors fixer.

5. **Certificate** (`certificate` collection)
   - **Form locations**:
     - Admin: `resources/views/livewire/admin/users/partials/certificates.blade.php`
     - User: `resources/views/livewire/profile/certificates.blade.php`
   - **Current Data**: 4,953 docs, 5 missing `user_id`, 0 ghost IDs.

---

### D. Pending / Deferred Sections (No Admin/User Profile Form Yet)

These collections do NOT have an active inline profile management form in `resources/views/livewire/profile/` or `resources/views/livewire/admin/users/partials/`:

1. **Publication** (`publication` collection)
   - **Reason**: Handled by dedicated Publications management module (`resources/views/livewire/admin/publications`). Requires chunked batching due to 27,056 records.

---

## 4. Verification & Testing Standards

All database synchronization tools must adhere to:
1. Dual status compatibility: Support both string (`'1'`, `'0'`) and integer (`1`, `0`) statuses.
2. Null-safe property access: `$doc->field ?? null` for all MongoDB documents.
3. Cached diagnostics (`Cache::remember`) to keep UI snappy (< 30ms).
4. Feature tests written with Pest PHP (`tests/Feature/DatabaseFixing...`).
5. Code style formatted via `vendor/bin/pint --format agent`.

