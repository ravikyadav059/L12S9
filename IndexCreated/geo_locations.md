# MongoDB Indexes Documentation

## Collections: `country`, `state`, `city`

This document records all MongoDB indexes created and utilized for geographical location collections.

---

### Index Summary Table

| Collection | Index Name | Key Definition (JSON) | Type | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **`country`** | **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique ObjectId |
| **`country`** | **`status_1`** | `{"status": 1}` | Single Field | Active country filtering |
| **`state`** | **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique ObjectId |
| **`state`** | **`country_id_1`** | `{"country_id": 1}` | Single Field | Fetching states by country |
| **`city`** | **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique ObjectId |
| **`city`** | **`state_id_1`** | `{"state_id": 1}` | Single Field | Fetching cities by state |

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.country.createIndex({ "status": 1 }, { background: true });
db.state.createIndex({ "country_id": 1 }, { background: true });
db.city.createIndex({ "state_id": 1 }, { background: true });
```
