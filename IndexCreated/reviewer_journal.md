# MongoDB Indexes Documentation

## Collection: `reviewer_journal`

This document records all MongoDB indexes created and utilized for the **`reviewer_journal`** collection in the Scholar Admin, Journals, and Article Management system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key (used by `Publication::belongsTo` relation) |
| **`journal_title_1`** | `{"journal_title": 1}` | Single Field | High-speed journal title lookups and relationship resolution |
| **`title_1`** | `{"title": 1}` | Single Field | Fast fallback title search and alphabetical journal queries |
| **`slug_1`** | `{"slug": 1}` | Single Field | Journal URL slug resolution (`/journal/{slug}`) |
| **`issn_1`** | `{"issn": 1}` | Single Field | Fast ISSN lookup and cross-referencing |
| **`status_1`** | `{"status": 1}` | Single Field | Filters active and verified journals |
| **`created_at_-1`** | `{"created_at": -1}` | Single Field | High-speed sorting for newest added journals |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - `Publication` relation: `$this->belongsTo(ReviewerJournal::class, 'journal_title', '_id')`

---

#### 2. `journal_title_1`
- **Fields**:
  ```json
  { "journal_title": 1 }
  ```
- **Options**: Default / Background: `true`
- **Used by**:
  - Journal Name rendering on Article cards
  - Journal Autocomplete & Search filters

---

#### 3. `title_1`
- **Fields**:
  ```json
  { "title": 1 }
  ```
- **Options**: Default / Background: `true`
- **Used by**:
  - Secondary title searches and index sorting

---

#### 4. `slug_1`
- **Fields**:
  ```json
  { "slug": 1 }
  ```
- **Options**: Default / Background: `true`
- **Used by**:
  - Public journal page routing (`/journal/{slug}`)

---

#### 5. `issn_1`
- **Fields**:
  ```json
  { "issn": 1 }
  ```
- **Options**: Default / Background: `true`
- **Used by**:
  - Metadata verification and ISSN searches

---

#### 6. `status_1`
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Default / Background: `true`
- **Used by**:
  - Active / Verified journal filtering

---

#### 7. `created_at_-1`
- **Fields**:
  ```json
  { "created_at": -1 }
  ```
- **Options**: Default / Background: `true`
- **Used by**:
  - Newest journal listings and admin browsing

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`reviewer_journal`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Paste the field definition JSON (e.g. `{ "journal_title": 1 }`) and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

If creating via the MongoDB Shell terminal:

```javascript
use clonedata;

db.reviewer_journal.createIndex({ "journal_title": 1 }, { background: true });
db.reviewer_journal.createIndex({ "title": 1 }, { background: true });
db.reviewer_journal.createIndex({ "slug": 1 }, { background: true });
db.reviewer_journal.createIndex({ "issn": 1 }, { background: true });
db.reviewer_journal.createIndex({ "status": 1 }, { background: true });
db.reviewer_journal.createIndex({ "created_at": -1 }, { background: true });
```
