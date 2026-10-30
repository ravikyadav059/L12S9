# MongoDB Indexes Documentation

## Collection: `grade`

This document records all MongoDB indexes created and utilized for the **`grade`** collection in the Scholar system and Profile Education components.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key, used for direct lookups |
| **`grade_1`** | `{"grade": 1}` | Single Field | Rapid case-insensitive grade name lookups, duplicate detection, and matching |
| **`status_1_grade_1`** | `{"status": 1, "grade": 1}` | Compound | High-speed active grade listing and dropdown selection |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval and relationship matching (`$education->grade`).

---

#### 2. `grade_1` (Single Field Index)
- **Fields**:
  ```json
  { "grade": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Case-insensitive grade name lookups, legacy text-to-ID conversion tools, and exact matching:
    ```php
    Grade::where('grade', 'regex', new Regex('^' . preg_quote($name) . '$', 'i'))->first();
    ```

---

#### 3. `status_1_grade_1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "grade": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Profile education grade dropdown list and filtering active grades:
    ```php
    Grade::whereIn('status', [1, '1', true])->orderBy('grade')->get();
    ```

---

### Mongosh Creation Commands

```javascript
use scholar_db; // or current database

db.grade.createIndex({ "grade": 1 }, { name: "grade_1", background: true });
db.grade.createIndex({ "status": 1, "grade": 1 }, { name: "status_1_grade_1", background: true });
```
