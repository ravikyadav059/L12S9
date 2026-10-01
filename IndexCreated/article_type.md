# MongoDB Indexes Documentation

## Collection: `article_type`

This document records all MongoDB indexes created and utilized for the **`article_type`** collection in the Scholar classification and Article Management system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key (used by batch article type lookup `Article::whereIn('_id', $typeIds)`) |
| **`name_1`** | `{"name": 1}` | Single Field | Fast sorting and lookup by article category / type name |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Index)
- **Fields**: `{ "_id": 1 }`
- **Used by**: `Article::whereIn('_id', $typeIds)->select(['_id', 'name'])` (<1ms execution).

#### 2. `name_1`
- **Fields**: `{ "name": 1 }`
- **Options**: `background: true`
- **Used by**: Fast alphabetical category filtering and dropdown population.

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.article_type.createIndex({ "name": 1 }, { background: true });
```
