# Education Components Architecture & High-Scale Design

> **Scope**: Architecture, component breakdown, caching strategy, and implementation reference for the **Education** profile and admin section.

---

## 1. Overview & Requirements

The Education section captures a user's academic history with high-performance searchable inputs:

1. **Institution \***: Searchable autocomplete with infinite scrolling against the MongoDB `organizations` collection (shared with Experience).
2. **Degree \***: Searchable dropdown against the `degree_types` collection (3,055+ records). Supports custom degree creation with case-insensitive deduplication and usage counts.
3. **Field of Study**: Searchable dropdown against the `fields_of_study` collection (3,453+ records). Supports custom entry with usage tracking.
4. **Grade**: Searchable dropdown against the `grades` collection (30 records, fully in-memory cached).
5. **Passout Year \***: Searchable dropdown containing years from `current_year` down to `1950` (future years excluded), with integer or string handling normalized via `Education::formatYear()`.
6. **Pursuing Checkbox**: Inline toggle right next to Passout Year. When checked, Passout Year is disabled and cleared.
7. **Description**: Textarea supporting between 50 and 1,500 characters.

---

## 2. Component Hierarchy & File Mapping

To eliminate code duplication and adhere to the project's single-responsibility design:

```
resources/views/
├── components/
│   └── profile/
│       ├── education-form.blade.php             ← Reusable Form Component (Shared between User & Admin)
│       └── unsaved-modal.blade.php              ← Shared Unsaved Changes Warning Modal
│
└── livewire/
    ├── profile/
    │   └── education.blade.php                  ← Public & Own User Profile Volt Child Component
    │
    └── admin/
        └── users/
            └── partials/
                └── education.blade.php          ← Admin User Edit Volt Child Component
```

### Parent Embeds:
- **User Profile Page** (`resources/views/livewire/userprofile.blade.php`):
  ```blade
  <livewire:profile.education 
      :user="$user" 
      :is-own-profile="$isOwnProfile" 
      :key="'profile-education-'.($user ? $user->_id : 'guest')" 
  />
  ```
- **Admin User Edit Page** (`resources/views/livewire/admin/users/edit.blade.php`):
  ```blade
  <livewire:admin.users.partials.education 
      :user="$user" 
      :key="'admin-user-education-'.$user->_id" 
  />
  ```

---

## 3. High-Scale Caching & Database Optimization

Adhering to `HIGH_SCALE_OPTIMIZATION_GUIDE.md`:

1. **In-Memory & Redis Caching**:
   - `Grade::getAllCached()`: Caches all 30 grades for 24 hours (`grade:all_cached`).
   - `DegreeType::getAllActiveCached()` & `FieldOfStudy::getAllActiveCached()`: Top active records cached for 1 hour.
   - Cache keys automatically flushed on `created`, `updated`, `deleted` model events.

2. **Paginated Endpoints**:
   - `GET /api/degree-types?q={query}&page={page}&limit=20`
   - `GET /api/fields-of-study?q={query}&page={page}&limit=20`
   - `GET /api/grades`
   - `GET /api/organizations?q={query}&page={page}&limit=20`

3. **Atomic User Profile Sync**:
   - Education documents are stored in the MongoDB `education` collection.
   - Referenced in `ReviewerProfile->education` as an array of 24-character hexadecimal IDs.
   - New IDs added using MongoDB `$push` (`ReviewerProfile::where(...)->push('education', $strId, true)`).
   - Deleted IDs removed using MongoDB `$pull` (`ReviewerProfile::where(...)->pull('education', $eduId)`).

---

## 4. Error Boundaries

Both `livewire.profile.education` and `livewire.admin.users.partials.education` implement Volt error boundaries:
```php
public bool $hasError = false;

public function exception(\Throwable $e, callable $stopPropagation): void
{
    report($e);
    $stopPropagation();
    $this->hasError = true;
}
```
If an exception occurs within this section, the component isolates the failure into a graceful amber alert card without breaking the rest of the profile or admin page.
