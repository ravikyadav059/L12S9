# MongoDB Indexes Documentation

## Collection: `skills`

This document records all MongoDB indexes created and utilized for the **`skills`** collection in the Scholar9 Q&A, Questions, User Profile, and Skills & Expertise tagging system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`skills_status_1_skills_title_1`** | `{"skills_status": 1, "skills_title": 1}` | Compound | High-speed autocomplete, search, and retrieval of active skills for Q&A tag pills and user expertise selection |
| **`skills_title_1`** | `{"skills_title": 1}` | Single Field | Rapid title lookups, case matching, and search suggestions |
| **`slug_1`** | `{"slug": 1}` | Single Field (Sparse) | Direct route parameter lookup and unique slug resolution (`/skills/{slug}`) |

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

#### 2. `skills_status_1_skills_title_1` (Compound Index)
- **Fields**:
  ```json
  { "skills_status": 1, "skills_title": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Q&A Ask Question and Filter tags autocomplete:
    ```php
    Skill::query()
        ->where('skills_status', 1)
        ->where('skills_title', 'like', '%' . $query . '%')
        ->limit(10)
        ->pluck('skills_title');
    ```

---

#### 3. `skills_title_1` (Single Field Index)
- **Fields**:
  ```json
  { "skills_title": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Exact skill name lookups, case sorting, and tag suggestions.

---

#### 4. `slug_1` (Single Field Index)
- **Fields**:
  ```json
  { "slug": 1 }
  ```
- **Options**: Background: `true`, Sparse: `true`
- **Used by**:
  - Skill detail page resolution by URL slug.

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`skills`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Set the field configuration:
   - Field 1: `skills_status` &rarr; `1` (Ascending)
   - Field 2: `skills_title` &rarr; `1` (Ascending)
6. Check **Create index in the background** and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

If creating via the MongoDB Shell terminal, run:

```javascript
// Switch to your application database
use your_database_name;

// 1. Compound Index for Active Skills and Autocomplete
db.skills.createIndex(
    { "skills_status": 1, "skills_title": 1 },
    { name: "skills_status_1_skills_title_1", background: true }
);

// 2. Single Field Index on Title
db.skills.createIndex(
    { "skills_title": 1 },
    { name: "skills_title_1", background: true }
);

// 3. Sparse Index on Slug
db.skills.createIndex(
    { "slug": 1 },
    { name: "slug_1", background: true, sparse: true }
);
```

---

### Verifying Active Indexes

To verify all active indexes on the `skills` collection:

```javascript
db.skills.getIndexes();
```
