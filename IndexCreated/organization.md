# MongoDB Indexes Documentation

## Collection: `organization`

This document records all MongoDB indexes created and utilized for the **`organization`** collection in the Scholar Institutions system and Admin Institutes Management.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key, used for direct lookups and point search |
| **`slug_1`** | `{"slug": 1}` | Single Field (Sparse) | High-speed routing for public institution detail pages (`/institution/{slug}`) and slug search in admin |
| **`serial_number_-1`** | `{"serial_number": -1}` | Single Field | Rapid calculation of next serial number (`max('serial_number')`), numeric ID search, and sorting |
| **`organization_name_1`** | `{"organization_name": 1}` | Single Field | Alphabetical sorting (A-Z / Z-A) and institute name search |
| **`organization_type_1`** | `{"organization_type": 1}` | Single Field | Filtering by institution type (`education`, `corporate`) |
| **`country_1`** | `{"country": 1}` | Single Field | Filtering institutions by country identifier |
| **`status_1`** | `{"status": 1}` | Single Field | Filtering active vs inactive institutions (`status = '1'`) |
| **`created_at_-1`** | `{"created_at": -1}` | Single Field | Default reverse-chronological sorting in admin institute lists |
| **`org_type_status_idx`** | `{"organization_type": 1, "status": 1}` | Compound | Combined filtering by Type and Status in Admin Institutes management |
| **`status_1_organization_name_1`** | `{"status": 1, "organization_name": 1}` | Compound | High-speed active organization search and alphabetical listing for experience autocomplete |

---

### Admin Institutes Filter Optimization

The Admin Institutes component (`resources/views/livewire/admin/institutes/index.blade.php`) specifically utilizes these indexes:

1. **Point Search by `_id`**:
   When a 24-character hex ID is searched, it performs an exact lookup on the `_id_` primary B-Tree index ($O(1)$).
2. **Point Search by `serial_number`**:
   When a numeric ID is searched, it performs an exact match utilizing the `serial_number_-1` index ($O(\log N)$).
3. **Point Search by `slug`**:
   Exact slug matches directly utilize the sparse index `slug_1`.
4. **Name and Slug Pattern Search**:
   Partial text queries match against indexed `organization_name` and `slug`.
5. **Type & Status Filters**:
   Directly hit the `organization_type_1`, `status_1`, and compound `org_type_status_idx` indexes.
6. **Sorting**:
   - By ID / Serial Number: utilizes `serial_number_-1`
   - By Name: utilizes `organization_name_1`
   - By Date: utilizes `created_at_-1`

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Key)
- **Fields**:
  ```json
  { "_id": 1 }
  ```
- **Used by**:
  - Direct document retrieval, admin point search, and CMS edit/delete operations.

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
  - Admin institute search by slug:
    ```php
    $query->where('slug', $searchClean);
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
  - Admin numeric ID search:
    ```php
    $query->where('serial_number', (int) $searchClean);
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

#### 8. `created_at_-1`
- **Fields**:
  ```json
  { "created_at": -1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Default chronological sorting in Admin Institutes CMS.

---

#### 9. `org_type_status_idx` (Compound Index)
- **Fields**:
  ```json
  { "organization_type": 1, "status": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Combined filter dropdowns (Type + Status) in the Admin Institutes table.

---

#### 10. `status_1_organization_name_1` (Compound Index)
- **Fields**:
  ```json
  { "status": 1, "organization_name": 1 }
  ```
- **Options**: Background: `true`
- **Used by**:
  - Work Experience organization autocomplete and default listing with instant B-tree scan.

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
db.organization.createIndex({ "created_at": -1 }, { background: true });
db.organization.createIndex({ "organization_type": 1, "status": 1 }, { name: "org_type_status_idx", background: true });
db.organization.createIndex({ "status": 1, "organization_name": 1 }, { name: "status_1_organization_name_1", background: true });
```
