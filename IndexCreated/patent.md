# MongoDB Indexes Documentation

## Collection: `patent`

This document records all MongoDB indexes created and utilized for the **`patent`** collection in the Scholar system and User Profile Intellectual Property & Patents module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookups for all patents belonging to a user (`$user->patents()`) |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | High-performance filtering of a user's active patents on profile pages |
| **`category_id_1`** | `{"category_id": 1}` | Single Field | Rapid filtering of patents by research discipline/category (`$category->patents()`) |
| **`status_1`** | `{"status": 1}` | Single Field | Active status filtering (`status = 1` or `status = 0`) across administrative queries |
| **`patent_no_1`** | `{"patent_no": 1}` | Single Field (Sparse) | Rapid exact lookup and duplicate detection by registered patent number |
| **`application_no_1`** | `{"application_no": 1}` | Single Field (Sparse) | Fast lookup and validation by patent application number |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval by ID (`Patent::find($id)`), model saves, and deletions.

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
    $user->patents()->get();
    // Patent::where('user_id', $userId)->get();
    ```

---

#### 3. `user_id_1_status_1` (Compound Index)
- **Fields**:
  ```json
  { "user_id": 1, "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Public profile and scholar view of active patents:
    ```php
    Patent::query()
        ->where('user_id', $userId)
        ->whereIn('status', [1, '1'])
        ->get();
    ```

---

#### 4. `category_id_1` (Single Field Index)
- **Fields**:
  ```json
  { "category_id": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Filtering patents under a specific discipline/category:
    ```php
    Patent::where('category_id', $categoryId)->get();
    // $category->patents()->get();
    ```

---

#### 5. `status_1` (Single Field Index)
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Administrative queries and active patent counts:
    ```php
    Patent::where('status', 1)->count();
    ```

---

#### 6. `patent_no_1` (Sparse Index)
- **Fields**:
  ```json
  { "patent_no": 1 }
  ```
- **Options**: Background: `true`, Sparse: `true`
- **Used by**:
  - Lookup and duplicate detection by patent number:
    ```php
    Patent::where('patent_no', $patentNo)->first();
    ```

---

#### 7. `application_no_1` (Sparse Index)
- **Fields**:
  ```json
  { "application_no": 1 }
  ```
- **Options**: Background: `true`, Sparse: `true`
- **Used by**:
  - Fast resolution by application number:
    ```php
    Patent::where('application_no', $appNo)->first();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// 1. User patent lookups
db.patent.createIndex({ "user_id": 1 }, { background: true });

// 2. User active patent filter
db.patent.createIndex({ "user_id": 1, "status": 1 }, { background: true });

// 3. Category filter
db.patent.createIndex({ "category_id": 1 }, { background: true });

// 4. Status filter
db.patent.createIndex({ "status": 1 }, { background: true });

// 5. Patent number lookup (sparse)
db.patent.createIndex({ "patent_no": 1 }, { background: true, sparse: true });

// 6. Application number lookup (sparse)
db.patent.createIndex({ "application_no": 1 }, { background: true, sparse: true });
```

