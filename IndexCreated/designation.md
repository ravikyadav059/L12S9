# MongoDB Indexes Documentation

## Collection: `designation`

This document records all MongoDB indexes created and utilized for the **`designation`** collection in the Scholar system and Profile Work Experience components.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`status_1_designation_1`** | `{"status": 1, "designation": 1}` | Compound | High-speed autocomplete, search, and alphabetical listing of active designations |
| **`designation_1`** | `{"designation": 1}` | Single Field | Rapid case-insensitive title lookups, duplicate detection, and exact matching |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval and MongoDB operations.

---

#### 2. `status_1_designation_1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "designation": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - User and Admin Profile Experience designation autocomplete dropdown & pagination:
    ```php
    Designation::query()
        ->whereIn('status', [1, '1', true])
        ->whereNotNull('designation')
        ->orderBy('designation', 'asc')
        ->skip(0)->take(20)->get();
    ```

---

#### 3. `designation_1` (Single Field Index)
- **Fields**:
  ```json
  { "designation": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Exact designation lookups, usage tracking, uniqueness checks, and auto-promotion tracking (`submission_count >= 3`).

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// 1. Compound Index for Active Designation and Autocomplete
db.designation.createIndex(
    { "status": 1, "designation": 1 },
    { name: "status_1_designation_1", background: true }
);

// 2. Single Field Index on Designation
db.designation.createIndex(
    { "designation": 1 },
    { name: "designation_1", background: true }
);
```

