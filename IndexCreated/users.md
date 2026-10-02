# MongoDB Indexes Documentation

## Collection: `users`

This document records all MongoDB indexes created and utilized for the **`users`** collection in the Scholar Admin, Author Profiles, and Authentication system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key (used by batch author resolution `User::whereIn('_id', $coAuthorIds)`) |
| **`email_1`** | `{"email": 1}` | Unique (Sparse) | Enforces unique email constraint across all users, fast authentication lookup, email queries, and author auto-complete search |
| **`slug_1`** | `{"slug": 1}` | Unique (Sparse) | Enforces unique scholar profile URL routing (`/profile/{slug}`) |
| **`unique_id_1`** | `{"unique_id": 1}` | Unique (Sparse) | Enforces unique institutional scholar ID (`S9-MMYYYY-XXXXXXX`) |
| **`sequence_number_1`** | `{"sequence_number": 1}` | Unique (Sparse) | Enforces unique auto-incrementing integer sequence identifier |
| **`status_1`** | `{"status": 1}` | Single Field | Filters active user accounts |
| **`type_1`** | `{"type": 1}` | Single Field | Admin vs Scholar user role filtering (`type = 0`) |
| **`fullname_1`** | `{"fullname": 1}` | Single Field | Author name searches and alphabetical sorting |
| **`first_name_1`** | `{"first_name": 1}` | Single Field | High-speed author name auto-complete in Deposit form |
| **`last_name_1`** | `{"last_name": 1}` | Single Field | High-speed author surname auto-complete in Deposit form |

---

### Detailed Index Configurations

#### 1. `_id_` (Default Primary Index)
- **Fields**: `{ "_id": 1 }`
- **Used by**: `User::whereIn('_id', $coAuthorIds)->select(['_id', 'first_name', 'last_name', 'slug', 'fullname'])` (<1ms execution).

#### 2. `slug_1` (Unique Constraint)
- **Fields**: `{ "slug": 1 }`
- **Options**: `unique: true`, `sparse: true`, `background: true`
- **Used by**: Author profile page resolution (`Volt::route('profile/{slug}', 'user')->name('userscholar')`).

#### 3. `unique_id_1` & `sequence_number_1` (Unique Constraints)
- **Fields**: `{ "unique_id": 1 }`, `{ "sequence_number": 1 }`
- **Options**: `unique: true`, `sparse: true`, `background: true`
- **Used by**: Scholar uniqueness validation, institutional identification, and sequential numbering.

#### 4. `email_1` (Unique Constraint)
- **Fields**: `{ "email": 1 }`
- **Options**: `unique: true`, `sparse: true`, `background: true`
- **Used by**: Enforcing unique lowercase email addresses, Login, Password Reset, Authentication, and Author lookup workflows.

#### 5. `status_1` & `type_1`
- **Fields**: `{ "status": 1 }`, `{ "type": 1 }`
- **Options**: `background: true`
- **Used by**: Admin user management panel and permission verification (`type = 0` / scholar filtering).

#### 6. `first_name_1` & `last_name_1` & `fullname_1`
- **Fields**: `{ "first_name": 1 }`, `{ "last_name": 1 }`, `{ "fullname": 1 }`
- **Options**: `background: true`
- **Used by**: Live author auto-complete search dropdown in Deposit Article workflow (`/deposit-article`).

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.users.createIndex({ "slug": 1 }, { unique: true, sparse: true, background: true });
db.users.createIndex({ "unique_id": 1 }, { unique: true, sparse: true, background: true });
db.users.createIndex({ "sequence_number": 1 }, { unique: true, sparse: true, background: true });
db.users.createIndex({ "email": 1 }, { unique: true, sparse: true, background: true });
db.users.createIndex({ "status": 1 }, { background: true });
db.users.createIndex({ "type": 1 }, { background: true });
db.users.createIndex({ "fullname": 1 }, { background: true });
db.users.createIndex({ "first_name": 1 }, { background: true });
db.users.createIndex({ "last_name": 1 }, { background: true });
```
