# MongoDB Indexes Documentation

## Collection: `certificate`

This document records all MongoDB indexes created and utilized for the **`certificate`** collection in the Scholar system and User Profile Licenses & Certifications module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for a user's certificates (`$user->certificates()`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | Instant resolution of active certificates on scholar profile pages |
| **`status_1`** | `{"status": 1}` | Single Field | Fast status filtering across administrative fixers and directory queries |
| **`certificate_1`** | `{"certificate": 1}` | Single Field | Rapid certificate title search, autocomplete, and duplicate detection |
| **`post_id_1`** | `{"post_id": 1}` | Single Field (Sparse) | Sparse index for certificate records linked to public feed posts |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**: `{ "_id": 1 }`
- **Used by**: Direct document retrieval by ID (`Certificate::find($id)`), saves, and deletions.

#### 2. `user_id_1` (Single Field Index)
- **Fields**: `{ "user_id": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - Eloquent relation resolution from the `User` model:
    ```php
    $user->certificates()->get();
    // Certificate::where('user_id', $userId)->get();
    ```

#### 3. `user_id_1_status_1` (Compound Index)
- **Fields**: `{ "user_id": 1, "status": 1 }`
- **Options**: Background: `true`
- **Used by**:
  - User profile public view and active certificate feed:
    ```php
    Certificate::where('user_id', $userId)->whereIn('status', [1, '1'])->get();
    ```

#### 4. `status_1` (Single Field Index)
- **Fields**: `{ "status": 1 }`
- **Options**: Background: `true`
- **Used by**: Status validation and administrative fixing queries.

#### 5. `certificate_1` (Single Field Index)
- **Fields**: `{ "certificate": 1 }`
- **Options**: Background: `true`
- **Used by**: Certificate title search and autocomplete.

#### 6. `post_id_1` (Sparse Index)
- **Fields**: `{ "post_id": 1 }`
- **Options**: Background: `true`, Sparse: `true`
- **Used by**: Linking social posts to their associated certificate record without indexing documents where `post_id` is null.

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.certificate.createIndex({ "user_id": 1 }, { background: true });
db.certificate.createIndex({ "user_id": 1, "status": 1 }, { background: true });
db.certificate.createIndex({ "status": 1 }, { background: true });
db.certificate.createIndex({ "certificate": 1 }, { background: true });
db.certificate.createIndex({ "post_id": 1 }, { background: true, sparse: true });
```

