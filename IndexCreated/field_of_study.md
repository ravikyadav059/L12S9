# MongoDB Indexes Documentation

## Collection: `field_of_study`

This document records all MongoDB indexes created and utilized for the **`field_of_study`** collection in the Scholar system and Profile Education components.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key, used for direct lookups |
| **`field_of_study_1`** | `{"field_of_study": 1}` | Single Field | Rapid case-insensitive field of study lookups, duplicate detection, and matching |
| **`status_1_field_of_study_1`** | `{"status": 1, "field_of_study": 1}` | Compound | High-speed active field of study listing, autocomplete, and dropdown selection |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval and relationship matching (`$education->field_of_study`).

---

#### 2. `field_of_study_1` (Single Field Index)
- **Fields**:
  ```json
  { "field_of_study": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Case-insensitive field of study lookups, legacy text-to-ID conversion tools, and exact matching:
    ```php
    FieldOfStudy::where('field_of_study', 'regex', new Regex('^' . preg_quote($name) . '$', 'i'))->first();
    ```

---

#### 3. `status_1_field_of_study_1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "field_of_study": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Profile education field of study dropdown list, search autocomplete, and filtering active records:
    ```php
    FieldOfStudy::whereIn('status', [1, '1', true])->orderBy('field_of_study')->get();
    ```

---

### Mongosh Creation Commands

```javascript
// 1. Single field index for field_of_study search and matching
db.field_of_study.createIndex({ "field_of_study": 1 }, { name: "field_of_study_1", background: true });

// 2. Compound index for active status filtering and alphabetical sorting
db.field_of_study.createIndex({ "status": 1, "field_of_study": 1 }, { name: "status_1_field_of_study_1", background: true });
```
