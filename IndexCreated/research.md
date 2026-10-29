# MongoDB Indexes Documentation

## Collection: `research`

This document records all MongoDB indexes designed and utilized for the **`research`** collection in the Scholar9 User Profile, About & Research Interests tagging system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`research_status_1_research_title_1`** | `{"research_status": 1, "research_title": 1}` | Compound | High-speed autocomplete, search, and retrieval of active research interests with zero in-memory sort |
| **`research_title_1`** | `{"research_title": 1}` | Single Field | Rapid case-insensitive title lookups, duplicate detection, and tag suggestion lookups |
| **`usage_count_-1`** | `{"usage_count": -1}` | Single Field | Rapid retrieval of trending and top-used research interests across the platform |

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

#### 2. `research_status_1_research_title_1` (Compound Index)
- **Fields**:
  ```json
  { "research_status": 1, "research_title": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - User profile Research Interests dropdown & autocomplete:
    ```php
    Research::query()
        ->where('research_status', 1)
        ->where('research_title', 'regex', $regex)
        ->limit(15)
        ->get(['_id', 'research_title']);
    ```

---

#### 3. `research_title_1` (Single Field Index)
- **Fields**:
  ```json
  { "research_title": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Exact title lookups, case matching, uniqueness checks before insert, and auto-promotion tracking.

---

#### 4. `usage_count_-1` (Single Field Index)
- **Fields**:
  ```json
  { "usage_count": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Suggesting popular research interests when focus is placed on the input before typing.

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`research`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Set the field configuration:
   - Field 1: `research_status` &rarr; `1` (Ascending)
   - Field 2: `research_title` &rarr; `1` (Ascending)
6. Check **Create index in the background** and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

If creating via the MongoDB Shell terminal, run:

```javascript
// Switch to your application database
use your_database_name;

// 1. Compound Index for Active Research and Autocomplete
db.research.createIndex(
    { "research_status": 1, "research_title": 1 },
    { name: "research_status_1_research_title_1", background: true }
);

// 2. Single Field Index on Title
db.research.createIndex(
    { "research_title": 1 },
    { name: "research_title_1", background: true }
);

// 3. Single Field Index on Usage Count
db.research.createIndex(
    { "usage_count": -1 },
    { name: "usage_count_-1", background: true }
);
```
