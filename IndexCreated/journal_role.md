# MongoDB Indexes Documentation

## Collection: `journal_role`

This document records all MongoDB indexes created and utilized for the **`journal_role`** collection in the Scholar Admin, Editorial Board, and Reviewer Role Management system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key (referenced by `RoleInResearchJournals::role`) |
| **`name_1`** | `{"name": 1}` | Single Field | High-speed role name search and database fixing string-to-ObjectId lookup |
| **`status_1`** | `{"status": 1}` | Single Field | Filters active and valid roles |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - `RoleInResearchJournals` relation: `$this->belongsTo(JournalRole::class, 'role', '_id')`

---

#### 2. `name_1`
- **Fields**:
  ```json
  { "name": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Role lookups, search, and database schema fixer matching.

---

#### 3. `status_1`
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Active role filtering (`status = 1`).

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`journal_role`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Paste the field definition JSON (e.g. `{ "name": 1 }`) and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

If creating via the MongoDB Shell terminal:

```javascript
use clonedata;

db.journal_role.createIndex({ "name": 1 }, { background: true });
db.journal_role.createIndex({ "status": 1 }, { background: true });
```
