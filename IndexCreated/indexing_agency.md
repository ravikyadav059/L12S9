# MongoDB Indexes Documentation

## Collection: `indexing_agency`

This document records all MongoDB indexes created and utilized for the **`indexing_agency`** collection in the Scholar Admin and Journal Indexing system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`status_1_serial_number_1`** | `{"status": 1, "serial_number": 1}` | Compound | High-speed retrieval of active agencies sorted by display order (used in `Cache::remember`) |
| **`agency_name_1`** | `{"agency_name": 1}` | Single Field | Rapid lookups, search, and dynamic agency field matching |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval and CMS admin operations.

---

#### 2. `status_1_serial_number_1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "serial_number": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Public journal page indexing agencies query:
    ```php
    IndexingAgency::whereIn('status', [1, '1', true])
        ->orderBy('serial_number')
        ->get();
    ```

---

#### 3. `agency_name_1`
- **Fields**:
  ```json
  { "agency_name": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Agency lookups, search, and admin agency management.

---

### How to Create in MongoDB Compass

1. Open **MongoDB Compass** and connect to your database.
2. Select the **`indexing_agency`** collection under your database.
3. Click the **Indexes** tab.
4. Click **Create Index**.
5. Paste the field definition JSON (e.g. `{ "status": 1, "serial_number": 1 }`) and click **Create Index**.

---

### One-Click Creation via Mongosh (MongoDB Shell)

If creating via the MongoDB Shell terminal:

```javascript
use clonedata;

db.indexing_agency.createIndex({ "status": 1, "serial_number": 1 }, { background: true });
db.indexing_agency.createIndex({ "agency_name": 1 }, { background: true });
```
