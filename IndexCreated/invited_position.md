# MongoDB Indexes Documentation

## Collection: `invited_position`

This document records all MongoDB indexes created and utilized for the **`invited_position`** collection in the Scholar system and User Profile Invited Positions & Guest Roles module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for all invited positions belonging to a user (`$user->invitedPositions()`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | High-performance filtering of a user's active invited positions on profile pages |
| **`organization_id_1`** | `{"organization_id": 1}` | Single Field | Quick resolution of all invited positions affiliated with an Organization (`$org->invitedPositions()`) |
| **`status_1`** | `{"status": 1}` | Single Field | Active status filtering (`status = 1` or `status = 0`) across administrative queries |
| **`role_1`** | `{"role": 1}` | Single Field | Search, filtering, and autocomplete by role (e.g. `'Keynote Speaker'`, `'Industry Expert'`) |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval by ID (`InvitedPosition::find($id)`), model updates, and deletions.

---

#### 2. `user_id_1` (Single Field Index)
- **Fields**:
  ```json
  { "user_id": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Eloquent relation resolution from the `User` model:
    ```php
    $user->invitedPositions()->get();
    // InvitedPosition::where('user_id', $userId)->get();
    ```

---

#### 3. `user_id_1_status_1` (Compound Index)
- **Fields**:
  ```json
  { "user_id": 1, "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Public profile and scholar view of active invited positions:
    ```php
    InvitedPosition::query()
        ->where('user_id', $userId)
        ->whereIn('status', [1, '1'])
        ->get();
    ```

---

#### 4. `organization_id_1` (Single Field Index)
- **Fields**:
  ```json
  { "organization_id": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Institutional invited positions and affiliations resolution:
    ```php
    InvitedPosition::where('organization_id', $organizationId)->get();
    // $organization->invitedPositions()->get();
    ```

---

#### 5. `status_1` (Single Field Index)
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Administrative queries and active position counts:
    ```php
    InvitedPosition::where('status', 1)->count();
    ```

---

#### 6. `role_1` (Single Field Index)
- **Fields**:
  ```json
  { "role": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Autocomplete search and duplicate role matching:
    ```php
    InvitedPosition::where('role', 'like', "%{$search}%")->get();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// 1. User lookups
db.invited_position.createIndex({ "user_id": 1 }, { background: true });

// 2. User active positions filter
db.invited_position.createIndex({ "user_id": 1, "status": 1 }, { background: true });

// 3. Organization affiliation lookups
db.invited_position.createIndex({ "organization_id": 1 }, { background: true });

// 4. Status filter
db.invited_position.createIndex({ "status": 1 }, { background: true });

// 5. Role filter & search
db.invited_position.createIndex({ "role": 1 }, { background: true });
```

