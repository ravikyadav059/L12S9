# MongoDB Indexes Documentation

## Collection: `organization`

This document records all MongoDB indexes created and utilized for the **`organization`** collection in the Scholar Institutions system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key |
| **`slug_1`** | `{"slug": 1}` | Single Field (Sparse) | High-speed routing for public institution detail pages (`/institution/{slug}`) |
| **`serial_number_-1`** | `{"serial_number": -1}` | Single Field | Rapid calculation of next serial number (`max('serial_number')`) and sorting |
| **`organization_name_1`** | `{"organization_name": 1}` | Single Field | Alphabetical sorting (A-Z / Z-A) and institution search |
| **`organization_type_1`** | `{"organization_type": 1}` | Single Field | Filtering by institution type (Education, Corporate, Research, Govt, etc.) |
| **`country_1`** | `{"country": 1}` | Single Field | Filtering institutions by country identifier |
| **`status_1`** | `{"status": 1}` | Single Field | Filtering active institutions (`status = 1`) |

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

#### 2. `slug_1` (Sparse Index)
- **Fields**:
  ```json
  { "slug": 1 }
  ```
- **Options**: Background: `true`, Sparse: `true`
- **Used by**:
  - Public institution profile view route:
    ```php
    Organization::where('slug', $slug)->first();
    ```

---

#### 3. `serial_number_-1`
- **Fields**:
  ```json
  { "serial_number": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Auto-increment sequential ID calculation on creation and sorting:
    ```php
    Organization::max('serial_number');
    Organization::orderByDesc('serial_number')->get();
    ```

---

#### 4. `organization_name_1`
- **Fields**:
  ```json
  { "organization_name": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Institution directory search and alphabetical ordering.

---

#### 5. `organization_type_1`
- **Fields**:
  ```json
  { "organization_type": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Type-based directory filters (`education`, `corporate`, `research`, etc.).

---

#### 6. `country_1`
- **Fields**:
  ```json
  { "country": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Geographic directory filters by country code/ID.

---

#### 7. `status_1`
- **Fields**:
  ```json
  { "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Filtering active vs inactive organizations (`Organization::active()`).

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.organization.createIndex({ "slug": 1 }, { background: true, sparse: true });
db.organization.createIndex({ "serial_number": -1 }, { background: true });
db.organization.createIndex({ "organization_name": 1 }, { background: true });
db.organization.createIndex({ "organization_type": 1 }, { background: true });
db.organization.createIndex({ "country": 1 }, { background: true });
db.organization.createIndex({ "status": 1 }, { background: true });
```
