# MongoDB Indexes Documentation

## Collection: `users`

This document records all MongoDB indexes created and utilized for the **`users`** collection in the Scholar Admin, Author Profiles, and Authentication system.

---

### Index Summary Table

| Index Name | Key Definition (JSON) | Type | Purpose / Query Optimization |
| :--- | :--- | :--- | :--- |
| **`_id_`** *(Default)* | `{"_id": 1}` | Single Field | Primary Unique MongoDB Object ID key (used by batch author resolution `User::whereIn('_id', $coAuthorIds)`) |
| **`email_1`** | `{"email": 1}` | Single Field (Sparse) | Fast authentication lookup, email queries, and author auto-complete search |
| **`slug_1`** | `{"slug": 1}` | Single Field (Sparse) | Fast public scholar profile lookup (`/profile/{slug}`) |
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

#### 2. `slug_1`
- **Fields**: `{ "slug": 1 }`
- **Options**: `sparse: true`, `background: true`
- **Used by**: Author profile page resolution (`Volt::route('profile/{slug}', 'user')->name('userscholar')`).

#### 3. `email_1`
- **Fields**: `{ "email": 1 }`
- **Options**: `sparse: true`, `background: true`
- **Used by**: Login, Password Reset, Authentication, and Author lookup workflows.

#### 4. `status_1` & `type_1`
- **Fields**: `{ "status": 1 }`, `{ "type": 1 }`
- **Options**: `background: true`
- **Used by**: Admin user management panel and permission verification (`type = 0` / scholar filtering).

#### 5. `first_name_1` & `last_name_1` & `fullname_1`
- **Fields**: `{ "first_name": 1 }`, `{ "last_name": 1 }`, `{ "fullname": 1 }`
- **Options**: `background: true`
- **Used by**: Live author auto-complete search dropdown in Deposit Article workflow (`/deposit-article`).

---

### One-Click Creation via Mongosh (MongoDB Shell)

```javascript
use clonedata;

db.users.createIndex({ "slug": 1 }, { sparse: true, background: true });
db.users.createIndex({ "email": 1 }, { sparse: true, background: true });
db.users.createIndex({ "status": 1 }, { background: true });
db.users.createIndex({ "type": 1 }, { background: true });
db.users.createIndex({ "fullname": 1 }, { background: true });
db.users.createIndex({ "first_name": 1 }, { background: true });
db.users.createIndex({ "last_name": 1 }, { background: true });
```
