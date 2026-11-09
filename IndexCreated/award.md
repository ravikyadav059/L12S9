# MongoDB Indexes Documentation

## Collection: `award`

This document records all MongoDB indexes created and utilized for the **`award`** collection in the Scholar system and User Profile Honors & Awards module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for a user's awards and relational queries (`$user->awards()`) |
| **`user_id_1_status_1_award_year_-1`** | `{"user_id": 1, "status": 1, "award_year": -1}` | Compound | High-performance user profile award listing sorted by most recent year |
| **`status_1`** | `{"status": 1}` | Single Field | Active status filtering (`status = 1` or `status = 0`) across administrative queries |
| **`status_1_award_year_-1`** | `{"status": 1, "award_year": -1}` | Compound | Global active award timeline feeds and public directory sorting |
| **`post_id_1`** | `{"post_id": 1}` | Single Field (Sparse) | Sparse index for award records linked to public feed posts |
| **`award_name_1`** | `{"award_name": 1}` | Single Field | Rapid name search, autocomplete, and duplicate award detection |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval by ID (`Award::find($id)`), model saves, and deletions.

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
    $user->awards()->get();
    // Award::where('user_id', $userId)->get();
    ```

---

#### 3. `user_id_1_status_1_award_year_-1` (Compound Index)
- **Fields**:
  ```json
  { "user_id": 1, "status": 1, "award_year": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Profile page public & private view of scholar awards sorted newest to oldest:
    ```php
    Award::query()
        ->where('user_id', $userId)
        ->whereIn('status', [1, '1'])
        ->orderBy('award_year', 'desc')
        ->get();
    ```

---

#### 4. `status_1` (Single Field Index)
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - General status filtering and counting:
    ```php
    Award::where('status', 1)->count();
    ```

---

#### 5. `status_1_award_year_-1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "award_year": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Administrative index tables and global feeds sorted by award year:
    ```php
    Award::query()
        ->where('status', 1)
        ->orderBy('award_year', 'desc')
        ->paginate(20);
    ```

---

#### 6. `post_id_1` (Sparse Index)
- **Fields**:
  ```json
  { "post_id": 1 }
  ```
- **Options**: Background: `true`, Sparse: `true`
- **Used by**:
  - Linking timeline/feed posts to their associated award record without indexing documents where `post_id` is null:
    ```php
    Award::where('post_id', $postId)->first();
    ```

---

#### 7. `award_name_1` (Single Field Index)
- **Fields**:
  ```json
  { "award_name": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Autocomplete search and duplicate title detection:
    ```php
    Award::where('award_name', 'like', "%{$search}%")->limit(10)->get();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// 1. User lookups
db.award.createIndex({ "user_id": 1 }, { background: true });

// 2. User profile awards (status + year sort)
db.award.createIndex({ "user_id": 1, "status": 1, "award_year": -1 }, { background: true });

// 3. Status filter
db.award.createIndex({ "status": 1 }, { background: true });

// 4. Global active awards sort
db.award.createIndex({ "status": 1, "award_year": -1 }, { background: true });

// 5. Sparse Post ID linking
db.award.createIndex({ "post_id": 1 }, { background: true, sparse: true });

// 6. Award name search & duplicate check
db.award.createIndex({ "award_name": 1 }, { background: true });
```

