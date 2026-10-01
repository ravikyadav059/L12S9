# MongoDB Indexes Documentation

## Collection: `role_in_research_journals`

This document records all MongoDB indexes created and utilized for the **`role_in_research_journals`** collection in the Scholar Admin, Journal Editorial Board, and Reviewer Role Management system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`journal_title_1`** | `{"journal_title": 1}` | Single Field | High-speed retrieval of all members and roles associated with a journal ID / title |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Fast lookup of journals/roles associated with a specific user profile |
| **`status_1`** | `{"status": 1}` | Single Field | Filters active and approved editorial/reviewer roles |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct record identification and edit operations.

---

#### 2. `journal_title_1`
- **Fields**:
  ```json
  { "journal_title": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Journal detail page query:
    ```php
    RoleInResearchJournals::where('journal_title', (string) $journal->_id)->whereIn('status', [1, '1', true])->get();
    ```

---

#### 3. `user_id_1`
- **Fields**:
  ```json
  { "user_id": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Scholar Profile page editorial experience and roles lookup.

---

#### 4. `status_1`
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Filtering active / verified members (`status = 1`).

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`role_in_research_journals`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Paste the field definition JSON (e.g. `{ "journal_title": 1 }`) and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

If creating via the MongoDB Shell terminal:

```javascript
use clonedata;

db.role_in_research_journals.createIndex({ "journal_title": 1 }, { background: true });
db.role_in_research_journals.createIndex({ "user_id": 1 }, { background: true });
db.role_in_research_journals.createIndex({ "status": 1 }, { background: true });
```
