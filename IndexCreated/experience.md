# MongoDB Indexes Documentation

## Collection: `experience`

This document records all MongoDB indexes created and utilized for the **`experience`** collection in the Scholar system and User Profile Work Experience module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`organization_id_1_current_organization_1`** | `{"organization_id": 1, "current_organization": 1}` | Compound Index | High-speed batch resolution of current scholars (`current_organization: 1`) and alumni (`current_organization: 0`) per institution |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for a user's work experiences (`$user->experiences()`, `where('user_id', $userId)`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound Index | High-performance resolution of active employment history on user scholar profiles |
| **`status_1`** | `{"status": 1}` | Single Field | Fast status filtering across administrative fixers, audits, and directory queries |
| **`designation_1`** | `{"designation": 1}` | Single Field | Foreign key resolution linking to the `designation` collection and position lookups |
| **`current_organization_1`** | `{"current_organization": 1}` | Single Field | Instant filtering of scholars currently employed at an institution |
| **`post_id_1`** | `{"post_id": 1}` | Single Field (Sparse) | Sparse index for work experience records linked to public feed posts |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**: `{ "_id": 1 }`
- **Used by**: Direct document retrieval by ID (`Experience::find($id)`), saves, and deletions.

#### 2. `organization_id_1_current_organization_1` (Compound Index)
- **Fields**: `{ "organization_id": 1, "current_organization": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Batch metrics resolution for Academic & Research Institutions directory and detail pages:
    ```php
    Experience::where('organization_id', $orgId)
        ->whereIn('current_organization', [1, '1', true])
        ->get();
    ```

#### 3. `user_id_1` (Single Field Index)
- **Fields**: `{ "user_id": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Eloquent relation resolution from the `User` model:
    ```php
    $user->experiences()->get();
    // Experience::where('user_id', $userId)->get();
    ```

#### 4. `user_id_1_status_1` (Compound Index)
- **Fields**: `{ "user_id": 1, "status": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Scholar profile public view and active experience timeline:
    ```php
    Experience::where('user_id', $userId)->whereIn('status', [1, '1'])->get();
    ```

#### 5. `status_1` (Single Field Index)
- **Fields**: `{ "status": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Administrative status fixing tools and active count queries:
    ```php
    Experience::where('status', 1)->count();
    ```

#### 6. `designation_1` (Single Field Index)
- **Fields**: `{ "designation": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Resolving scholars who hold a specific designation:
    ```php
    Experience::where('designation', $designationId)->get();
    ```

#### 7. `current_organization_1` (Single Field Index)
- **Fields**: `{ "current_organization": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Finding currently active positions across all users:
    ```php
    Experience::where('current_organization', 1)->get();
    ```

#### 8. `post_id_1` (Sparse Index)
- **Fields**: `{ "post_id": 1 }`
- **Options**: Background: `true`, Sparse: `true`
- **Used by**: Linking social posts to their associated work experience record.

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.experience.createIndex({ "organization_id": 1, "current_organization": 1 }, { background: true });
db.experience.createIndex({ "user_id": 1 }, { background: true });
db.experience.createIndex({ "user_id": 1, "status": 1 }, { background: true });
db.experience.createIndex({ "status": 1 }, { background: true });
db.experience.createIndex({ "designation": 1 }, { background: true });
db.experience.createIndex({ "current_organization": 1 }, { background: true });
db.experience.createIndex({ "post_id": 1 }, { background: true, sparse: true });
```
