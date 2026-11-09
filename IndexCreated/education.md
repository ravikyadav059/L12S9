# MongoDB Indexes Documentation

## Collection: `education`

This document records all MongoDB indexes created and utilized for the **`education`** collection in the Scholar system and User Profile Education module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for a user's education history (`$user->education()`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | Instant resolution of active education records for user scholar profiles |
| **`status_1`** | `{"status": 1}` | Single Field | Fast status filtering across administrative fixers and directory listings |
| **`institution_id_1`** | `{"institution_id": 1}` | Single Field | Relational lookup for university/institution alumni and institutional metric aggregations |
| **`degree_type_1`** | `{"degree_type": 1}` | Single Field | Foreign key resolution linking to the `degree_type` collection |
| **`field_of_study_1`** | `{"field_of_study": 1}` | Single Field | Foreign key resolution linking to the `field_of_study` collection |
| **`passout_year_-1`** | `{"passout_year": -1}` | Single Field | Sorted graduation year timelines and education history chronology |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**: `{ "_id": 1 }`
- **Used by**: Direct document retrieval by ID (`Education::find($id)`), saves, and deletions.

#### 2. `user_id_1` (Single Field Index)
- **Fields**: `{ "user_id": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Eloquent relation resolution from the `User` model:
    ```php
    $user->education()->get();
    // Education::where('user_id', $userId)->get();
    ```

#### 3. `user_id_1_status_1` (Compound Index)
- **Fields**: `{ "user_id": 1, "status": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - User profile public view and active education listings:
    ```php
    Education::where('user_id', $userId)->whereIn('status', [1, '1'])->get();
    ```

#### 4. `status_1` (Single Field Index)
- **Fields**: `{ "status": 1 }`
- **Options**: Background: `true`
- **Used by**: Administrative status filtering and data fixing tools.

#### 5. `institution_id_1` (Single Field Index)
- **Fields**: `{ "institution_id": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Organization alumni queries:
    ```php
    Education::where('institution_id', $organizationId)->get();
    ```

#### 6. `degree_type_1` & `field_of_study_1`
- **Fields**: `{ "degree_type": 1 }` and `{ "field_of_study": 1 }`
- **Options**: Background: `true`
- **Used by**: Relational degree and field matching algorithms.

#### 7. `passout_year_-1` (Single Field Index)
- **Fields**: `{ "passout_year": -1 }`
- **Options**: Background: `true`
- **Used by**: Chronological sorting on scholar profiles.

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.education.createIndex({ "user_id": 1 }, { background: true });
db.education.createIndex({ "user_id": 1, "status": 1 }, { background: true });
db.education.createIndex({ "status": 1 }, { background: true });
db.education.createIndex({ "institution_id": 1 }, { background: true });
db.education.createIndex({ "degree_type": 1 }, { background: true });
db.education.createIndex({ "field_of_study": 1 }, { background: true });
db.education.createIndex({ "passout_year": -1 }, { background: true });
```

