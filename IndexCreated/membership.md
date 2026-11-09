# MongoDB Indexes Documentation

## Collection: `membership`

This document records all MongoDB indexes created and utilized for the **`membership`** collection in the Scholar system and User Profile Professional Memberships module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for a user's memberships and relational queries (`$user->memberships()`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | High-performance filtering of a user's active membership records on profile pages |
| **`organization_id_1`** | `{"organization_id": 1}` | Single Field | Quick resolution of all professional memberships affiliated with an Organization (`$org->memberships()`) |
| **`status_1`** | `{"status": 1}` | Single Field | Active status filtering (`status = 1` or `status = 0`) across administrative queries |
| **`membership_type_1`** | `{"membership_type": 1}` | Single Field | Autocomplete and grouping by membership title/type (e.g. `'Associate Membership'`, `'IEEE Membership'`) |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval by ID (`Membership::find($id)`), model updates, and deletions.

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
    $user->memberships()->get();
    // Membership::where('user_id', $userId)->get();
    ```

---

#### 3. `user_id_1_status_1` (Compound Index)
- **Fields**:
  ```json
  { "user_id": 1, "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Public profile and scholar view of active memberships:
    ```php
    Membership::query()
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
  - Institutional member resolution and affiliation tracking:
    ```php
    Membership::where('organization_id', $organizationId)->get();
    // $organization->memberships()->get();
    ```

---

#### 5. `status_1` (Single Field Index)
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Administrative queries and active membership counts:
    ```php
    Membership::where('status', 1)->count();
    ```

---

#### 6. `membership_type_1` (Single Field Index)
- **Fields**:
  ```json
  { "membership_type": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Autocomplete search and duplicate type matching:
    ```php
    Membership::where('membership_type', 'like', "%{$search}%")->get();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// 1. User memberships lookups
db.membership.createIndex({ "user_id": 1 }, { background: true });

// 2. User active memberships filter
db.membership.createIndex({ "user_id": 1, "status": 1 }, { background: true });

// 3. Organization affiliation lookups
db.membership.createIndex({ "organization_id": 1 }, { background: true });

// 4. Status filter
db.membership.createIndex({ "status": 1 }, { background: true });

// 5. Membership type filter & search
db.membership.createIndex({ "membership_type": 1 }, { background: true });
```

