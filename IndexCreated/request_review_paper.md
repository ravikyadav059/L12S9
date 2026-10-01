# MongoDB Indexes Documentation

## Collection: `request_review_paper`

This document records all MongoDB indexes created and utilized for the **`request_review_paper`** collection in the Scholar Paper Review, Peer Review Workflow, and Journal Submission system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`journal_id_1`** | `{"journal_id": 1}` | Single Field | High-speed retrieval of transparent peer review papers for a specific journal |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Rapid lookup of papers submitted by or assigned to a user |
| **`status_1`** | `{"status": 1}` | Single Field | Filters active, verified, and pending review papers |
| **`paper_id_1`** | `{"paper_id": 1}` | Single Field | Fast lookup by custom alphanumeric paper ID |
| **`created_at_-1`** | `{"created_at": -1}` | Single Field | Sorting newest submitted review papers |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```

---

#### 2. `journal_id_1`
- **Fields**:
  ```json
  { "journal_id": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Journal details page transparent peer reviews section:
    ```php
    RequestReviewPaper::where('journal_id', (string) $journal->_id)->whereIn('status', [1, '1', true])->get();
    ```

---

#### 3. `user_id_1`
- **Fields**:
  ```json
  { "user_id": 1 }
  ```
- **Options**: Background: `true`

---

#### 4. `status_1`
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.request_review_paper.createIndex({ "journal_id": 1 }, { background: true });
db.request_review_paper.createIndex({ "user_id": 1 }, { background: true });
db.request_review_paper.createIndex({ "status": 1 }, { background: true });
db.request_review_paper.createIndex({ "created_at": -1 }, { background: true });
```
