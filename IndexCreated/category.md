# MongoDB Indexes Documentation

## Collection: `category`

This document records all MongoDB indexes created and utilized for the **`category`** collection in the Scholar system and Research Disciplines / Subject Categories module.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`category_slug_1`** | `{"category_slug": 1}` | Single Field (Unique, Sparse) | Enforces global uniqueness on category slugs and provides instantaneous URL resolution (`/category/{slug}`) |
| **`serial_number_1`** | `{"serial_number": 1}` | Single Field (Unique, Sparse) | Enforces global uniqueness on category display sequences and powers numerical ordering |
| **`category_status_1`** | `{"category_status": 1}` | Single Field | Fast filtering of active categories (`category_status: 1`) across dropdowns and public listings |
| **`category_title_1`** | `{"category_title": 1}` | Single Field | Rapid title search, autocomplete, and duplicate name checks |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval by ID (`Category::find($id)`), model saves, and deletions.

---

#### 2. `category_slug_1` (Unique Sparse Index)
- **Fields**:
  ```json
  { "category_slug": 1 }
  ```
- **Options**: Unique: `true`, Sparse: `true`, Background: `true`
- **Used by**:
  - Routing and slug-based lookup:
    ```php
    Category::where('category_slug', $slug)->first();
    ```

---

#### 3. `serial_number_1` (Unique Sparse Index)
- **Fields**:
  ```json
  { "serial_number": 1 }
  ```
- **Options**: Unique: `true`, Sparse: `true`, Background: `true`
- **Used by**:
  - Sequential ordering and duplicate prevention:
    ```php
    Category::orderBy('serial_number', 'asc')->get();
    ```

---

#### 4. `category_status_1` (Single Field Index)
- **Fields**:
  ```json
  { "category_status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Filtering active categories in dropdowns, forms, and catalogs:
    ```php
    Category::whereIn('category_status', [1, '1'])->get();
    ```

---

#### 5. `category_title_1` (Single Field Index)
- **Fields**:
  ```json
  { "category_title": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Title autocomplete and search:
    ```php
    Category::where('category_title', 'like', "%{$search}%")->get();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// 1. Unique category slug index (sparse)
db.category.createIndex({ "category_slug": 1 }, { unique: true, sparse: true, background: true });

// 2. Unique serial number index (sparse)
db.category.createIndex({ "serial_number": 1 }, { unique: true, sparse: true, background: true });

// 3. Status filter
db.category.createIndex({ "category_status": 1 }, { background: true });

// 4. Category title lookup & search
db.category.createIndex({ "category_title": 1 }, { background: true });
```

