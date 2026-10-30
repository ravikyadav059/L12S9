# MongoDB Indexes Documentation

## Collection: `degree_type`

This document records all MongoDB indexes created and utilized for the **`degree_type`** collection in the Scholar system and Profile Education components.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key, used for direct lookups |
| **`degree_type_1`** | `{"degree_type": 1}` | Single Field | Rapid case-insensitive degree name lookups, duplicate detection, and matching |
| **`status_1_degree_type_1`** | `{"status": 1, "degree_type": 1}` | Compound | High-speed active degree type listing and dropdown selection |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval and relationship matching (`$education->degree_type`).

---

#### 2. `degree_type_1` (Single Field Index)
- **Fields**:
  ```json
  { "degree_type": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Case-insensitive degree name lookups, legacy text-to-ID conversion tools, and exact matching:
    ```php
    DegreeType::where('degree_type', 'regex', new Regex('^' . preg_quote($name) . '$', 'i'))->first();
    ```

---

#### 3. `status_1_degree_type_1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "degree_type": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Profile education degree type dropdown list and filtering active degrees:
    ```php
    DegreeType::whereIn('status', [1, '1', true])->orderBy('degree_type')->get();
    ```

---

### Mongosh Creation Commands

```javascript
use scholar_db; // or current database

db.degree_type.createIndex({ "degree_type": 1 }, { name: "degree_type_1", background: true });
db.degree_type.createIndex({ "status": 1, "degree_type": 1 }, { name: "status_1_degree_type_1", background: true });
```
