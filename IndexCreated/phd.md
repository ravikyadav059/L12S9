# MongoDB Indexes Documentation

## Collection: `phd`

This document records all MongoDB indexes created and utilized for the **`phd`** collection in the Scholar system and User Profile PhD / Thesis Supervision & Guidance module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for a user's supervised or completed PhD/thesis records (`$user->phds()`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | High-performance filtering of a user's active PhD/thesis records on profile pages |
| **`institution_id_1`** | `{"institution_id": 1}` | Single Field | Quick resolution of all PhD theses conducted or guided under an Academic Institution (`$org->phds()`) |
| **`status_1`** | `{"status": 1}` | Single Field | Active status filtering (`status = 1` or `status = 0`) across administrative queries |
| **`phd_status_1`** | `{"phd_status": 1}` | Single Field | Filtering by thesis progression status (e.g. `'Ongoing'` vs `'Completed'`) |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval by ID (`Phd::find($id)`), model updates, and deletions.

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
    $user->phds()->get();
    // Phd::where('user_id', $userId)->get();
    ```

---

#### 3. `user_id_1_status_1` (Compound Index)
- **Fields**:
  ```json
  { "user_id": 1, "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Public profile and scholar view of active thesis records:
    ```php
    Phd::query()
        ->where('user_id', $userId)
        ->whereIn('status', [1, '1'])
        ->get();
    ```

---

#### 4. `institution_id_1` (Single Field Index)
- **Fields**:
  ```json
  { "institution_id": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Institutional profile thesis directories and metrics:
    ```php
    Phd::where('institution_id', $institutionId)->get();
    // $organization->phds()->get();
    ```

---

#### 5. `status_1` (Single Field Index)
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Administrative queries and active counts:
    ```php
    Phd::where('status', 1)->count();
    ```

---

#### 6. `phd_status_1` (Single Field Index)
- **Fields**:
  ```json
  { "phd_status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Filtering theses by progress state:
    ```php
    Phd::where('phd_status', 'Ongoing')->get();
    Phd::where('phd_status', 'Completed')->get();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// 1. User PhD lookups
db.phd.createIndex({ "user_id": 1 }, { background: true });

// 2. User active PhD filter
db.phd.createIndex({ "user_id": 1, "status": 1 }, { background: true });

// 3. Institution affiliation lookups
db.phd.createIndex({ "institution_id": 1 }, { background: true });

// 4. Status filter
db.phd.createIndex({ "status": 1 }, { background: true });

// 5. PhD progress status filter
db.phd.createIndex({ "phd_status": 1 }, { background: true });
```

