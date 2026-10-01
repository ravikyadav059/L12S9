# MongoDB Indexes Documentation

## Collection: `reviewer_profile`

This document records all MongoDB indexes created and recommended for the **`reviewer_profile`** collection in the Scholar system to optimize scholar profiles, institution metric lookups, orphan cleaners, and array relationship lookups.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Fast profile retrieval by user ID and batch extraction of profiles |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | Instant resolution of active reviewer profiles for current scholars |
| **`publication_active_count_-1`** | `{"publication_active_count": -1}` | Single Field | Instant sorting of top researchers/scholars by active publication count |
| **`publication_inactive_count_1`** | `{"publication_inactive_count": 1}` | Single Field | Filtering reviewer profiles with draft/inactive publications |
| **`publication_1`** | `{"publication": 1}` | Multikey (Array) | High-speed reverse lookup: find scholar profiles that reference a publication ID |
| **`experience_1`** | `{"experience": 1}` | Multikey (Array) | High-speed lookup of scholar profiles that reference an experience record |
| **`seminar_1`** | `{"seminar": 1}` | Multikey (Array) | Fast lookup of scholar profiles referencing a seminar/conference |
| **`RoleInResearchJournal_1`** | `{"RoleInResearchJournal": 1}` | Multikey (Array) | Fast lookup of scholar profiles referencing a journal editorial role |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**: `{ "_id": 1 }`
- **Used by**: Direct document retrieval by MongoDB ObjectId / string ID.

#### 2. `user_id_1` & `user_id_1_status_1` (Compound Index)
- **Fields**: `{ "user_id": 1, "status": 1 }`
- **Used by**: Scholar profile lookups, user dashboard, and Institution metrics pipeline:
  ```php
  ReviewerProfile::whereIn('user_id', $scholarUserIds)
      ->whereIn('status', [1, '1'])
      ->get(['publication', 'seminar']);
  ```

#### 3. `publication_active_count_-1`
- **Fields**: `{ "publication_active_count": -1 }`
- **Used by**: Ranking top scholars, profile sorting, and fast pagination on scholar directories.

#### 4. `publication_1` (Multikey Array Index)
- **Fields**: `{ "publication": 1 }`
- **Used by**: Reverse relational lookup to locate all profiles linked to a given publication ID without table scans.

#### 5. `experience_1` (Multikey Array Index)
- **Fields**: `{ "experience": 1 }`
- **Used by**: Reverse relational lookup to find reviewer profiles associated with a work experience record.

#### 6. `seminar_1` (Multikey Array Index)
- **Fields**: `{ "seminar": 1 }`
- **Used by**: Reverse relational lookup to find reviewer profiles attending or speaking at a specific seminar.

#### 7. `RoleInResearchJournal_1` (Multikey Array Index)
- **Fields**: `{ "RoleInResearchJournal": 1 }`
- **Used by**: Reverse relational lookup for editors and reviewers linked to journals.

---

### One-Click Creation via Mongosh (MongoDB Shell)

Run the following commands in your `mongosh` terminal or MongoDB Compass shell:

```javascript
use clonedata;

// 1. User ID & Status compound lookup
db.reviewer_profile.createIndex({ "user_id": 1, "status": 1 }, { background: true });

// 2. Publication Counts
db.reviewer_profile.createIndex({ "publication_active_count": -1 }, { background: true });
db.reviewer_profile.createIndex({ "publication_inactive_count": 1 }, { background: true });

// 3. Array Multikey Indexes
db.reviewer_profile.createIndex({ "publication": 1 }, { background: true });
db.reviewer_profile.createIndex({ "experience": 1 }, { background: true });
db.reviewer_profile.createIndex({ "seminar": 1 }, { background: true });
db.reviewer_profile.createIndex({ "RoleInResearchJournal": 1 }, { background: true });
```

---

### How to Create in MongoDB Compass UI

1. Open **MongoDB Compass** and connect to your cluster.
2. Navigate to your database (e.g. `clonedata`) and select the **`reviewer_profile`** collection.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Paste any of the following definitions and click **Create Index**:
   - `{ "user_id": 1, "status": 1 }`
   - `{ "publication_active_count": -1 }`
   - `{ "publication": 1 }`
   - `{ "experience": 1 }`
   - `{ "seminar": 1 }`
   - `{ "RoleInResearchJournal": 1 }`
