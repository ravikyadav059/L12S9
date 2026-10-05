# MongoDB Indexes Documentation

## Collection: `question`

This document records all MongoDB indexes created and utilized for the **`question`** collection in the Scholar9 Q&A, Knowledge Base, Questions Indexing, and Skills Filtering system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key (used by `Question::find()` and relationship resolution) |
| **`status_1_created_at_-1`** | `{"status": 1, "created_at": -1}` | Compound | High-speed retrieval of active questions (`status != 0`) ordered from newest to oldest on the main Q&A feed |
| **`status_1_votes_count_-1`** | `{"status": 1, "votes_count": -1}` | Compound | Instant sorting and pagination for "Most Voted" active questions |
| **`status_1_views_count_-1`** | `{"status": 1, "views_count": -1}` | Compound | High-performance querying of top popular active questions for the sidebar widget |
| **`status_1_tags_1`** | `{"status": 1, "tags": 1}` | Compound Multikey | Accelerates filtering active questions by tag / skill array pills and user expertise matching |
| **`user_id_1_status_1`** | `{"user_id": 1, "status": 1}` | Compound | Fast resolution of questions authored by specific users and following feed queries |
| **`slug_1_unique`** | `{"slug": 1}` | Single Field (Unique, Sparse) | Direct route parameter lookup and unique slug resolution (`/questions/{slug}`) |
| **`title_1`** | `{"title": 1}` | Single Field | Rapid title search and case-insensitive uniqueness verification |
| **`status_1`** | `{"status": 1}` | Single Field | Rapid counting and filtering of active questions (`Question::whereNotIn('status', [0, '0', false])`) |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval (`Question::find($id)`), vote toggling, follow toggling, bookmarking, and answer relations.

---

#### 2. `status_1_created_at_-1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "created_at": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Main Q&A feed default "Newest" sort:
    ```php
    Question::query()
        ->whereNotIn('status', [0, '0', false])
        ->latest()
        ->paginate(10);
    ```

---

#### 3. `status_1_votes_count_-1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "votes_count": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Q&A Feed "Most Voted" tab:
    ```php
    Question::query()
        ->whereNotIn('status', [0, '0', false])
        ->orderByDesc('votes_count')
        ->paginate(10);
    ```

---

#### 4. `status_1_views_count_-1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "views_count": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Sidebar "Popular Questions" widget:
    ```php
    Question::query()
        ->whereNotIn('status', [0, '0', false])
        ->orderByDesc('views_count')
        ->limit(4)
        ->get();
    ```

---

#### 5. `status_1_tags_1` (Compound Multikey Index)
- **Fields**:
  ```json
  { "status": 1, "tags": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Tag pill filtering and user expertise skill matching:
    ```php
    Question::query()
        ->whereNotIn('status', [0, '0', false])
        ->where('tags', 'like', '%' . $tag . '%')
        ->paginate(10);
    ```

---

#### 6. `user_id_1_status_1` (Compound Index)
- **Fields**:
  ```json
  { "user_id": 1, "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Filter by followed authors:
    ```php
    Question::query()
        ->whereNotIn('status', [0, '0', false])
        ->whereIn('user_id', $followingUserIds)
        ->paginate(10);
    ```

---

#### 7. `slug_1` (Single Field Sparse Index)
- **Fields**:
  ```json
  { "slug": 1 }
  ```
- **Options**: Background: `true`, Sparse: `true`
- **Used by**:
  - Question detail page resolution by URL slug.

---

#### 8. `status_1` (Single Field Index)
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Global Q&A stats counting (`Question::whereNotIn('status', [0, '0', false])->count()`).

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`question`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Set the field configurations:
   - For **`status_1_created_at_-1`**:
     - Field 1: `status` &rarr; `1` (Ascending)
     - Field 2: `created_at` &rarr; `-1` (Descending)
   - For **`status_1_votes_count_-1`**:
     - Field 1: `status` &rarr; `1` (Ascending)
     - Field 2: `votes_count` &rarr; `-1` (Descending)
   - For **`status_1_views_count_-1`**:
     - Field 1: `status` &rarr; `1` (Ascending)
     - Field 2: `views_count` &rarr; `-1` (Descending)
   - For **`status_1_tags_1`**:
     - Field 1: `status` &rarr; `1` (Ascending)
     - Field 2: `tags` &rarr; `1` (Ascending)
   - For **`user_id_1_status_1`**:
     - Field 1: `user_id` &rarr; `1` (Ascending)
     - Field 2: `status` &rarr; `1` (Ascending)
   - For **`slug_1`**:
     - Field 1: `slug` &rarr; `1` (Ascending), check **Sparse**
   - For **`status_1`**:
     - Field 1: `status` &rarr; `1` (Ascending)
6. Check **Create index in the background** and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

If creating via the MongoDB Shell terminal, run:

```javascript
// Switch to your application database
use your_database_name;

// 1. Compound Index: Active Status & Newest Sort
db.question.createIndex(
    { "status": 1, "created_at": -1 },
    { name: "status_1_created_at_-1", background: true }
);

// 2. Compound Index: Active Status & Most Voted Sort
db.question.createIndex(
    { "status": 1, "votes_count": -1 },
    { name: "status_1_votes_count_-1", background: true }
);

// 3. Compound Index: Active Status & Popular Views Sort
db.question.createIndex(
    { "status": 1, "views_count": -1 },
    { name: "status_1_views_count_-1", background: true }
);

// 4. Compound Multikey Index: Active Status & Tags
db.question.createIndex(
    { "status": 1, "tags": 1 },
    { name: "status_1_tags_1", background: true }
);

// 5. Compound Index: Author User ID & Active Status
db.question.createIndex(
    { "user_id": 1, "status": 1 },
    { name: "user_id_1_status_1", background: true }
);

// 6. Unique Sparse Index on Slug for Route Resolution (Requires unique slugs)
db.question.createIndex(
    { "slug": 1 },
    { name: "slug_1_unique", background: true, unique: true, sparse: true }
);

// 7. Case-Insensitive / Exact Index on Title for Uniqueness Lookups
db.question.createIndex(
    { "title": 1 },
    { name: "title_1", background: true }
);

// 8. Single Field Index on Status
db.question.createIndex(
    { "status": 1 },
    { name: "status_1", background: true }
);
```

---

### Handling Existing Duplicate Slugs (If E11000 Error Occurs)

If you have legacy questions with duplicate slugs and receive an `E11000 duplicate key error`, you can safely resolve all duplicates without deleting any questions by running this script in Mongosh:

```javascript
// Auto-rename existing duplicate slugs in place (e.g. 'title-slug' -> 'title-slug-1', 'title-slug-2')
var seen = {};
db.question.find({ slug: { $exists: true, $ne: "" } }).forEach(function(doc) {
    if (seen[doc.slug]) {
        var newSlug = doc.slug + "-" + seen[doc.slug];
        seen[doc.slug]++;
        db.question.updateOne({ _id: doc._id }, { $set: { slug: newSlug } });
        print("Updated duplicate slug to: " + newSlug);
    } else {
        seen[doc.slug] = 1;
    }
});

// Now create the unique index:
db.question.createIndex(
    { "slug": 1 },
    { name: "slug_1_unique", background: true, unique: true, sparse: true }
);
```

---

### Non-Unique Alternative (If you prefer not to modify legacy slugs)

```javascript
db.question.createIndex(
    { "slug": 1 },
    { name: "slug_1", background: true, sparse: true }
);
```

---

### Verifying Active Indexes

To verify all active indexes on the `question` collection:

```javascript
db.question.getIndexes();
```
