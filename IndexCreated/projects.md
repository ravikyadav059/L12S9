# MongoDB Indexes Documentation

## Collection: `projects`

This document records all MongoDB indexes created and utilized for the **`projects`** collection in the Scholar system and User Profile Research & Development Projects module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for a user's projects (`$user->projects()`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | Instant resolution of active projects on scholar profile pages |
| **`status_1`** | `{"status": 1}` | Single Field | Fast status filtering across administrative fixers and directory queries |
| **`category_id_1`** | `{"category_id": 1}` | Single Field | Category-based project filtering across research domain portals |
| **`project_name_1`** | `{"project_name": 1}` | Single Field | Rapid project title search, autocomplete, and duplicate detection |
| **`registered_contributors_1`** | `{"registered_contributors": 1}` | Multikey (Array) | High-speed lookup of all projects where a given scholar is a registered contributor |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**: `{ "_id": 1 }`
- **Used by**: Direct document retrieval by ID (`Project::find($id)`), saves, and deletions.

#### 2. `user_id_1` (Single Field Index)
- **Fields**: `{ "user_id": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Eloquent relation resolution from the `User` model:
    ```php
    $user->projects()->get();
    // Project::where('user_id', $userId)->get();
    ```

#### 3. `user_id_1_status_1` (Compound Index)
- **Fields**: `{ "user_id": 1, "status": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - User profile public view and active projects feed:
    ```php
    Project::where('user_id', $userId)->whereIn('status', [1, '1'])->get();
    ```

#### 4. `status_1` (Single Field Index)
- **Fields**: `{ "status": 1 }`
- **Options**: Background: `true`
- **Used by**: Project status validation and administrative queries.

#### 5. `category_id_1` (Single Field Index)
- **Fields**: `{ "category_id": 1 }`
- **Options**: Background: `true`
- **Used by**: Project directory category navigation.

#### 6. `project_name_1` (Single Field Index)
- **Fields**: `{ "project_name": 1 }`
- **Options**: Background: `true`
- **Used by**: Real-time project title autocomplete and search.

#### 7. `registered_contributors_1` (Multikey Array Index)
- **Fields**: `{ "registered_contributors": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Locating projects where a user is credited as a co-researcher / contributor:
    ```php
    Project::where('registered_contributors', $userId)->get();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.projects.createIndex({ "user_id": 1 }, { background: true });
db.projects.createIndex({ "user_id": 1, "status": 1 }, { background: true });
db.projects.createIndex({ "status": 1 }, { background: true });
db.projects.createIndex({ "category_id": 1 }, { background: true });
db.projects.createIndex({ "project_name": 1 }, { background: true });
db.projects.createIndex({ "registered_contributors": 1 }, { background: true });
```

