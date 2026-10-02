# Database Indexes Registry & Architectural Guide (`IndexCreated`)

This directory serves as the centralized catalog and knowledge base for all MongoDB database indexes created across the application.

---

## 1. Registered Collections & Index Catalogs

| Collection | Dedicated Documentation | Active Indexes | Key Coverage |
| :--- | :--- | :--- | :--- |
| **`publications`** | [publications.md](./publications.md) | 19 Indexes | Status filtering, title search/sorting, multikey author lookups, article categories, journal relationship lookups (`journal_title`), DOI, views, and citations. |
| **`reviewer_journal`** | [reviewer_journal.md](./reviewer_journal.md) | 7 Indexes | Journal title resolution, slug routing (`/journal/{slug}`), ISSN verification, status, and created date sorting. |
| **`users`** | [users.md](./users.md) | 10 Indexes | Primary `_id_`, unique author slug routing (`/profile/{slug}`), unique institutional scholar ID (`unique_id`), unique `sequence_number`, unique `email` authentication, status, author fullname, and author first/last name auto-complete lookup. |
| **`article_type`** | [article_type.md](./article_type.md) | 2 Indexes | Primary `_id_` and category name sorting/filtering. |
| **`indexing_agency`** | [indexing_agency.md](./indexing_agency.md) | 3 Indexes | Primary `_id_`, compound status & serial number sort, and agency name lookup. |
| **`role_in_research_journals`** | [role_in_research_journals.md](./role_in_research_journals.md) | 4 Indexes | Primary `_id_`, journal title/ID resolution, user ID lookups, and status filtering. |
| **`journal_role`** | [journal_role.md](./journal_role.md) | 3 Indexes | Primary `_id_`, role name lookup, and status filtering. |
| **`request_review_paper`** | [request_review_paper.md](./request_review_paper.md) | 5 Indexes | Primary `_id_`, journal ID lookups, user ID queries, status, and created date sorting. |
| **`country`, `state`, `city`** | [geo_locations.md](./geo_locations.md) | 6 Indexes | Hierarchical lookups (country -> state -> city) and status filtering. |
| **`organization`** | [organization.md](./organization.md) | 7 Indexes | Primary `_id_`, slug routing (`/institution/{slug}`), serial number calculation/sorting, name search, and type & country filtering. |
| **`experience`** | [experience.md](./experience.md) | 2 Indexes | Primary `_id_`, compound organization ID & current organization filter for scholar/alumni calculations. |
| **`reviewer_profile`** | [reviewer_profile.md](./reviewer_profile.md) | 7 Indexes | Primary `_id_`, compound user ID & status lookup, active publication counts sorting, and multikey array indexes on publication, experience, seminar, and RoleInResearchJournal. |

---

## 2. What is a Database Index? (Simple Explanation)

> **Book Analogy**: Imagine a 1,000-page encyclopedia. 
> - **Without an Index (COLLSCAN / Full Table Scan)**: To find information about "Quantum Physics", you must read every single page from page 1 to 1000. For 1,000,000 documents, the database must scan 1,000,000 records from disk.
> - **With an Index (IXSCAN / B-Tree Lookup)**: You flip straight to the alphabetical index at the back of the book, find "Quantum Physics &rarr; Page 412", and jump directly to that exact page in **< 1 millisecond**.

In MongoDB, an **Index** is a dedicated, sorted B-Tree data structure that stores a small portion of the collection's data (the indexed field values and their physical storage pointer) in RAM.

---

## 3. Advantages vs. Disadvantages of Indexing

| Aspect | ✅ Advantages | ⚠️ Disadvantages / Costs |
| :--- | :--- | :--- |
| **Query Speed** | **Sub-millisecond read times** (`< 1ms` instead of `500ms - 5000ms` full collection scans). | **Write Latency**: Every `insert`, `update`, or `delete` must also update all associated B-Trees. |
| **Sorting Speed** | Sorts in-memory directly from pre-ordered B-Tree without hitting MongoDB's 32MB in-memory sort limit. | **Server RAM (Working Set)**: Every index consumes precious server RAM. If indexes exceed RAM, MongoDB must swap to disk, degrading overall server speed. |
| **Server CPU Load** | Reduces server CPU utilization by 90%+ during heavy concurrent traffic (10K+ users). | **Disk Storage Overhead**: Additional disk space is required to store index files. |
| **Scalability** | Essential for collections containing **1,000K+ documents**. | **Risk of Over-Indexing**: Having 20+ arbitrary indexes on a single collection slows down data writes unnecessarily. |

---

## 4. Types of MongoDB Indexes with Simple Examples

### A. Single Field Index
Indexes a single scalar field (ascending `1` or descending `-1`).
- **Example**: `{ "slug": 1 }`
- **Use Case**: Finding an article or user by unique slug (`/publication-detail/my-article-title`).
- **Query**: `Publication::where('slug', 'my-article-title')->first()`

### B. Compound Index (Multi-Field)
Indexes multiple fields together following the **ESR Rule** (Equality &rarr; Sort &rarr; Range).
- **Example**: `{ "status": 1, "created_at": -1 }`
- **Use Case**: Filtering active articles while sorting them from newest to oldest on admin or public pages.
- **Query**: `Publication::where('status', 1)->latest()->paginate(10)`

### C. Multikey Index (Arrays)
Indexes an array of values where MongoDB creates an index entry for every element in the array.
- **Example**: `{ "registered_co_author": 1 }` (where `registered_co_author = ["id1", "id2", "id3"]`)
- **Use Case**: Instantly finding all articles co-authored by a specific user on their Scholar Profile page.
- **Query**: `Publication::where('registered_co_author', $userId)->get()`

### D. Sparse Index
Only indexes documents that actually contain the indexed field, skipping documents where the field is `null` or missing.
- **Example**: `{ "doi": 1 }` with `{ sparse: true, background: true }`
- **Use Case**: Keeps index size small and prevents duplicate key errors on documents where DOI is optional.

---

## 5. When to USE an Index vs. When NOT to USE

### ✅ When You SHOULD Create an Index:
1. **High-Frequency Filter Fields**: Fields used constantly in `where()` clauses (e.g. `status`, `option`, `email`).
2. **URL Slugs & Unique Identifiers**: Fields used for direct lookup by route parameters (e.g. `slug`, `serial_number`).
3. **Sorting on Large Collections**: Fields used in `orderBy()` or `latest()` on collections with 10,000+ records (e.g. `created_at`, `view_counts`, `citation`).
4. **Foreign Key Relationships**: Fields used in `belongsTo` or `hasMany` relationships (e.g. `journal_title`, `type`, `user_id`).
5. **Array Elements in Lookups**: Array fields containing foreign IDs (e.g. `registered_co_author`).

---

### ❌ When You SHOULD NOT Create an Index:
1. **Small Collections**: Collections with fewer than 1,000 documents (e.g. static lookups or small settings) where MongoDB can scan the entire collection in RAM instantly.
2. **Rarely Queried Fields**: Fields only queried once a month in manual admin export scripts.
3. **High-Write / Low-Read Collections**: Collections like raw audit logs, error logs, or tracking beacons where thousands of writes happen every second and reads are rare.
4. **Redundant Prefix Indexes**: 
   - *Example*: If you already have a compound index `{ "status": 1, "created_at": -1 }`, you **do NOT need** a separate single index on `{ "status": 1 }` because MongoDB can use the compound index's prefix.
5. **Large Unbounded Text Blobs**: Never index entire description or article content fields directly with standard single-field B-Tree indexes (use Atlas Search or text indexes instead).

---

## 6. Production Index Governance & Best Practices

1. **Always Use `{ background: true }`**:
   - In live production environments, creating an index without `background: true` can lock the collection against concurrent read/write operations.
2. **Keep All Indexes in RAM (Working Set)**:
   - Ensure the total size of all collection indexes fits comfortably inside the server's available RAM.
3. **Verify Index Usage with `explain()`**:
   - In MongoDB Shell, run `db.collection.find(...).explain("executionStats")` to verify that the query uses `IXSCAN` (Index Scan) and **not** `COLLSCAN` (Collection Scan).
4. **Master Automation Script**:
   - To apply and verify all application indexes in one click on any server, run:
     ```bash
     php scratch/create_indexes.php
     ```

### General Indexing Guidelines for MongoDB

1. **Anti-Over-Indexing & Server Protection**:
   - Every index consumes MongoDB RAM working set and incurs a slight write overhead on inserts/updates.
   - Never create duplicate, redundant, or unused indexes.
   - Before adding any new index, check `IndexCreated/` documentation to verify if an existing index (or prefix of a compound index) already covers the query path.
2. **Background Creation**:
   - Always create indexes with `{ background: true }` in production to prevent blocking concurrent reads/writes and ensure zero server downtime.
3. **Compound Indexes (ESR Rule)**:
   - For multi-field queries, structure compound indexes using the **Equality, Sort, Range (ESR)** principle (e.g. `{ "status": 1, "created_at": -1 }`).
4. **Sparse Indexes on Optional Fields**:
   - Fields that are not present in every document (e.g. `slug`, `doi`) should use `{ sparse: true }` to keep the B-Tree compact.