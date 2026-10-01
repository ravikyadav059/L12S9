# MongoDB Indexes Documentation

## Collection: `publications`

This document records all MongoDB indexes created and utilized for the **`publications`** collection in the Scholar Admin & Article Management system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`status_1`** | `{"status": 1}` | Single Field | Filters Active (`1`) and Inactive (`0`) status records |
| **`title_1`** | `{"title": 1}` | Single Field | Fast alphabetical sorting and exact title relevance matching |
| **`slug_1`** | `{"slug": 1}` | Single Field | Publication URL slug lookups and search relevance |
| **`serial_number_1`** | `{"serial_number": 1}` | Single Field | Serial number searches and direct number queries |
| **`status_1_created_at_-1`** | `{"status": 1, "created_at": -1}` | Compound | High-speed paginated browsing with status filter + latest sort |
| **`created_at_1`** | `{"created_at": 1}` | Single Field | High-speed chronological sorting (Oldest first) on Articles page |
| **`created_at_-1`** | `{"created_at": -1}` | Single Field | High-speed reverse chronological sorting (Newest first) on Articles page |
| **`option_1_created_at_1`** | `{"option": 1, "created_at": 1}` | Compound | High-speed filtering by type (Published / Preprint) with chronological sort |
| **`view_counts_-1`** | `{"view_counts": -1}` | Single Field | Instant sorting by Most Viewed |
| **`citation_-1`** | `{"citation": -1}` | Single Field | Instant sorting by Citation Count (High to Low) |
| **`doi_1`** | `{"doi": 1}` | Single Field | Fast DOI (Digital Object Identifier) searches and validation |
| **`registered_co_author_1`** | `{"registered_co_author": 1}` | Multikey | High-speed lookup of all articles associated with an author / scholar profile |
| **`user_id_1`** | `{"user_id": 1}` | Single Field | Fast filtering of publications submitted by a specific user |
| **`type_1`** | `{"type": 1}` | Single Field | Fast filtering by article category / `article_type` foreign key ID |
| **`article_type_1`** | `{"article_type": 1}` | Single Field | Fast filtering by article classification (`pre-print`, `research_article`, etc.) |
| **`journal_title_1`** | `{"journal_title": 1}` | Single Field | Foreign key index for `Publication::reviewerJournal()` relationship, distinct scans, and orphan journal detection |
| **`journal_title_1_status_1`** | `{"journal_title": 1, "status": 1}` | Compound | High-speed filtering of active articles belonging to a specific `ReviewerJournal` |

---

### Detailed Index Configurations

#### 1. `status_1`
- **Fields**: `{ "status": 1 }`
- **Used by**: Publication Table Status Filtering (`where('status', 1)`), Status Formatter Tool.

#### 2. `title_1`
- **Fields**: `{ "title": 1 }`
- **Used by**: Title Alphabetical Sorting (`orderBy('title', 'asc')`), Search Relevance Top-Tier Matching.

#### 3. `slug_1`
- **Fields**: `{ "slug": 1 }`
- **Used by**: Route Slug Resolution (`/publication-detail/{slug}`), Search Relevance Match.

#### 4. `serial_number_1`
- **Fields**: `{ "serial_number": 1 }`
- **Used by**: Direct Serial Code search in Admin table.

#### 5. `status_1_created_at_-1` (Compound Index)
- **Fields**: `{ "status": 1, "created_at": -1 }`
- **Used by**: Admin Publication table default view (Active status + Latest first pagination).

#### 6. `created_at_1` & `created_at_-1`
- **Fields**: `{ "created_at": 1 }`, `{ "created_at": -1 }`
- **Used by**: Chronological (Oldest / Newest) article sorting on Public Articles list.

#### 7. `option_1_created_at_1` (Compound Category Index)
- **Fields**: `{ "option": 1, "created_at": 1 }`
- **Used by**: Published & Preprints filter tabs combined with chronological sorting.

#### 8. `view_counts_-1` & `citation_-1`
- **Fields**: `{ "view_counts": -1 }`, `{ "citation": -1 }`
- **Used by**: Most Viewed & Citation Count High-to-Low sort dropdowns.

#### 9. `doi_1`
- **Fields**: `{ "doi": 1 }`
- **Used by**: Article DOI Lookup & Duplicate Verification.

#### 10. `registered_co_author_1` (Multikey Index)
- **Fields**: `{ "registered_co_author": 1 }`
- **Used by**: High-speed resolution of all articles co-authored by a specific user on their Scholar Profile page (`/profile/{slug}`).

#### 11. `user_id_1`
- **Fields**: `{ "user_id": 1 }`
- **Used by**: Author dashboard and author profile article filtering.

#### 12. `type_1` & `article_type_1`
- **Fields**: `{ "type": 1 }`, `{ "article_type": 1 }`
- **Used by**: Rapid filtering and category association with the `article_type` collection.

#### 13. `journal_title_1` & `journal_title_1_status_1` (Foreign Key & Status Filter)
- **Fields**: `{ "journal_title": 1 }`, `{ "journal_title": 1, "status": 1 }`
- **Used by**: 
  - `Publication::reviewerJournal()` Eloquent relationship (`belongsTo(ReviewerJournal::class, 'journal_title', '_id')`).
  - Public journal page filtering all active articles for `/journal/{slug}`.
  - Admin Database Fixing tool for fast orphan journal scanning.

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`publications`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Paste the field definition JSON (e.g. `{ "registered_co_author": 1 }` or `{ "journal_title": 1 }`) and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

// Core publication sorting & filtering indexes
db.publications.createIndex({ "created_at": 1 }, { background: true });
db.publications.createIndex({ "created_at": -1 }, { background: true });
db.publications.createIndex({ "option": 1, "created_at": 1 }, { background: true });
db.publications.createIndex({ "view_counts": -1 }, { background: true });
db.publications.createIndex({ "citation": -1 }, { background: true });
db.publications.createIndex({ "status": 1 }, { background: true });
db.publications.createIndex({ "title": 1 }, { background: true });
db.publications.createIndex({ "slug": 1 }, { background: true });
db.publications.createIndex({ "serial_number": 1 }, { background: true });
db.publications.createIndex({ "status": 1, "created_at": -1 }, { background: true });
db.publications.createIndex({ "doi": 1 }, { background: true });

// Author & Article Type indexes
db.publications.createIndex({ "registered_co_author": 1 }, { background: true });
db.publications.createIndex({ "user_id": 1 }, { background: true });
db.publications.createIndex({ "type": 1 }, { background: true });
db.publications.createIndex({ "article_type": 1 }, { background: true });

// Journal Relationship Indexes
db.publications.createIndex({ "journal_title": 1 }, { background: true });
db.publications.createIndex({ "journal_title": 1, "status": 1 }, { background: true });

// Users and Article Type collection indexes
db.users.createIndex({ "slug": 1 }, { sparse: true, background: true });
db.article_type.createIndex({ "name": 1 }, { background: true });
```

