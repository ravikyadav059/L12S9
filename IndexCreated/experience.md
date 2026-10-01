# MongoDB Indexes Documentation

## Collection: `experience`

This document records all MongoDB indexes created and utilized for the **`experience`** collection in the Scholar system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`organization_id_1_current_organization_1`** | `{"organization_id": 1, "current_organization": 1}` | Compound Index | High-speed batch resolution of current scholars (`current_organization: 1`) and alumni (`current_organization: 0`) per institution |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval and CRUD operations.

---

#### 2. `organization_id_1_current_organization_1` (Compound Index)
- **Fields**:
  ```json
  { "organization_id": 1, "current_organization": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Batch metrics resolution for Academic & Research Institutions directory and detail pages:
    ```php
    Experience::where('organization_id', $orgId)
        ->whereIn('current_organization', [1, '1', true])
        ->get();
    ```

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.experience.createIndex({ "organization_id": 1, "current_organization": 1 }, { background: true });
```
