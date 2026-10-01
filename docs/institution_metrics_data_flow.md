# 🏛️ Institution Metrics & Data Flow Technical Guide

A comprehensive, step-by-step guide explaining how **Scholars**, **Alumnus**, **Publications**, **Citations**, and **Conferences/Seminars** are discovered, linked across MongoDB collections, and rendered in [`resources/views/livewire/institution/show.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/institution/show.blade.php) and [`resources/views/livewire/institution/index.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/institution/index.blade.php).

---

## 📑 Table of Contents
1. [System Architecture Diagram](#1-system-architecture-diagram)
2. [MongoDB Schema Reference Dictionary](#2-mongodb-schema-reference-dictionary)
3. [Step-by-Step Data Retrieval Pipelines](#3-step-by-step-data-retrieval-pipelines)
   - [Pipeline A: Scholars (Current Faculty)](#pipeline-a-scholars-current-faculty)
   - [Pipeline B: Alumnus (Past Affiliated)](#pipeline-b-alumnus-past-affiliated)
   - [Pipeline C: Publications & Citations](#pipeline-c-publications--citations)
   - [Pipeline D: Conferences & Seminars](#pipeline-d-conferences--seminars)
4. [Step-by-Step Practical Data Walkthrough (Example)](#4-step-by-step-practical-data-walkthrough-example)
5. [UI Mapping (Where Data Appears in Blade)](#5-ui-mapping-where-data-appears-in-blade)
6. [Edge Cases & Data Integrity Rules](#6-edge-cases--data-integrity-rules)
7. [Caching & Performance Optimization](#7-caching--performance-optimization)

---

## 1. System Architecture Diagram

```mermaid
flowchart TD
    classDef org fill:#3b82f6,stroke:#1d4ed8,stroke-width:2px,color:#fff;
    classDef exp fill:#8b5cf6,stroke:#6d28d9,stroke-width:2px,color:#fff;
    classDef usr fill:#10b981,stroke:#047857,stroke-width:2px,color:#fff;
    classDef rp fill:#f59e0b,stroke:#d97706,stroke-width:2px,color:#fff;
    classDef pub fill:#ef4444,stroke:#b91c1c,stroke-width:2px,color:#fff;
    classDef out fill:#1e293b,stroke:#0f172a,stroke-width:2px,color:#fff;

    Org["🏛️ 1. organizations<br/><b>Key:</b> _id / slug<br/><b>Filter:</b> status ∈ [1, '1']"]:::org
    Exp["📋 2. experience<br/><b>Foreign Key:</b> organization_id<br/><b>Filter:</b> status ∈ [1, '1']"]:::exp

    Org -->|1. Find Experiences by org_id| Exp

    CurExp{"current_organization?<br/>∈ [1, '1', true]"}
    Exp --> CurExp

    CurUsers["👤 3a. users (Current Scholars)<br/><b>Key:</b> _id ∈ experience.user_id<br/><b>Filter:</b> status ∈ [1, '1']"]:::usr
    AlmUsers["🎓 3b. users (Alumnus)<br/><b>Key:</b> _id ∈ experience.user_id<br/><b>Filter:</b> status ∈ [1, '1'] & NOT in Scholars"]:::usr

    CurExp -->|YES (Current)| CurUsers
    CurExp -->|NO (Past)| AlmUsers

    RP["🔬 4. reviewer_profile<br/><b>Foreign Key:</b> user_id ∈ Scholars<br/><b>Filter:</b> status ∈ [1, '1']"]:::rp

    CurUsers -->|2. Find Reviewer Profiles of Scholars| RP

    PubArray["📚 Array: publication[]<br/>List of publication _ids"]
    SemArray["🎤 Array: seminar[]<br/>List of seminar _ids"]

    RP --> PubArray
    RP --> SemArray

    Pubs["📄 5. publications<br/><b>Key:</b> _id ∈ publication[]<br/><b>Filter:</b> status ∈ [1, '1']"]:::pub

    PubArray -->|3. Query active papers| Pubs

    OutPub["🔢 Metric: PUBLICATION - XX<br/>📊 Tab: Publications Table"]:::out
    OutCit["🔢 Metric: CITATIONS - YY<br/>(Sum of publications.citations)"]:::out
    OutSem["🔢 Metric: CONFERENCES/SEMINAR - ZZ<br/>(Count of unique seminar IDs)"]:::out
    OutSch["👥 Tab: Scholars Grid Cards"]:::out
    OutAlm["🎓 Tab: Alumnus Grid Cards"]:::out

    Pubs --> OutPub
    Pubs --> OutCit
    SemArray --> OutSem
    CurUsers --> OutSch
    AlmUsers --> OutAlm
```

---

## 2. MongoDB Schema Reference Dictionary

The application inspects and links **5 distinct collections**:

### 1. `organizations`
| Field | Type | Description | Filter Used |
| :--- | :--- | :--- | :--- |
| `_id` | `ObjectId / String` | Unique identifier of the institution | Primary match |
| `slug` | `String` | URL friendly identifier (e.g. `tata-consultancy-services-tcs`) | Route lookup |
| `status` | `Integer / String` | Active status | `status in [1, '1']` |

### 2. `experience`
| Field | Type | Description | Filter Used |
| :--- | :--- | :--- | :--- |
| `_id` | `ObjectId / String` | Unique identifier of the work experience | Internal key |
| `organization_id` | `String` | Links to `organizations._id` | `organization_id == $orgId` |
| `user_id` | `String` | Links to `users._id` | Must be non-empty string |
| `current_organization` | `Integer / String / Bool` | `1`/`true` = Currently working; `0`/`false` = Past | Used to split Scholars vs Alumnus |
| `status` | `Integer / String` | Active status of experience record | `status in [1, '1']` |

### 3. `users`
| Field | Type | Description | Filter Used |
| :--- | :--- | :--- | :--- |
| `_id` | `ObjectId / String` | Unique user ID | Matched against `user_id` |
| `name` | `String` | Full name of the researcher | Display |
| `email` | `String` | User email | Identification |
| `slug` | `String` | User public profile slug | User link generation |
| `avatar` | `String / Null` | Custom profile image | Rendered via `$user->avatar` |
| `status` | `Integer / String` | Active status of account | `status in [1, '1']` |

### 4. `reviewer_profile`
| Field | Type | Description | Filter Used |
| :--- | :--- | :--- | :--- |
| `_id` | `ObjectId / String` | Profile identifier | Internal key |
| `user_id` | `String` | Links to `users._id` of the scholar | `user_id in [scholar_ids]` |
| `publication` | `Array of Strings` | List of valid `publications._id` (both active & inactive) | Flattened & queried |
| `publication_active_count` | `Integer` | Count of existing active publications (`status in [1, '1']`) | Metrics & quick lookup |
| `publication_inactive_count` | `Integer` | Count of existing inactive publications (`status in [0, '0']`) | Metrics & quick lookup |
| `seminar` | `Array of Strings` | List of seminar/conference IDs attended | Counted |
| `status` | `Integer / String` | Active status of profile | `status in [1, '1']` |

### 5. `publications`
| Field | Type | Description | Filter Used |
| :--- | :--- | :--- | :--- |
| `_id` | `ObjectId / String` | Unique publication ID | `_id in [extracted_pub_ids]` |
| `title` | `String` | Title of research paper | Display |
| `doi` | `String` | Digital Object Identifier | Display & external link |
| `citations` | `Integer` | Total citation count for this paper | Summed for total citations |
| `authors` | `Array / String` | Paper authors | Formatted for display |
| `journal_name` | `String` | Journal or publication venue | Display |
| `publication_date` | `Date / String` | When paper was published | Display |
| `status` | `Integer / String` | Active status of paper | `status in [1, '1']` |

---

## 3. Step-by-Step Data Retrieval Pipelines

### Pipeline A: Scholars (Current Faculty)
> **Goal**: Retrieve all active users who are currently working/researching at this institution.

```php
// Step 1: Query experience collection for current affiliations
$currentExperiences = Experience::where('organization_id', $orgId)
    ->whereIn('current_organization', [1, '1', true])
    ->whereIn('status', [1, '1'])
    ->whereNotNull('user_id')
    ->where('user_id', '!=', '')
    ->get(['user_id']);

$currentScholarIds = $currentExperiences->pluck('user_id')->unique()->values()->all();

// Step 2: Query users collection to ensure users are active
$scholars = User::whereIn('_id', $currentScholarIds)
    ->whereIn('status', [1, '1'])
    ->get(['_id', 'name', 'email', 'slug', 'avatar', 'status']);
```

---

### Pipeline B: Alumnus (Past Affiliated)
> **Goal**: Retrieve all active users who previously worked at this institution but are NOT currently scholars here.

```php
// Step 1: Query experience collection for past affiliations
$pastExperiences = Experience::where('organization_id', $orgId)
    ->whereIn('current_organization', [0, '0', false, null])
    ->whereIn('status', [1, '1'])
    ->whereNotNull('user_id')
    ->where('user_id', '!=', '')
    ->get(['user_id']);

$pastUserIds = $pastExperiences->pluck('user_id')
    ->diff($currentScholarIds) // EXCLUDE current scholars
    ->unique()
    ->values()
    ->all();

// Step 2: Query users collection
$alumnus = User::whereIn('_id', $pastUserIds)
    ->whereIn('status', [1, '1'])
    ->get(['_id', 'name', 'email', 'slug', 'avatar', 'status']);
```

---

### Pipeline C: Publications & Citations
> **Goal**: Find all valid, active published papers authored by the current scholars of this institution.

```php
// Step 1: Get reviewer profiles of current scholars
$reviewerProfiles = ReviewerProfile::whereIn('user_id', $scholars->pluck('_id'))
    ->whereIn('status', [1, '1'])
    ->get(['publication', 'seminar']);

// Step 2: Extract all publication IDs
$rawPubIds = [];
foreach ($reviewerProfiles as $rp) {
    if (!empty($rp->publication) && is_array($rp->publication)) {
        foreach ($rp->publication as $pid) {
            if (!empty($pid)) {
                $rawPubIds[] = (string) $pid;
            }
        }
    }
}
$uniquePubIds = array_values(array_unique($rawPubIds));

// Step 3: Verify and load active publication records from MongoDB
$publications = Publication::whereIn('_id', $uniquePubIds)
    ->whereIn('status', [1, '1'])
    ->get();

// Step 4: Calculate metrics
$publicationCount = $publications->count();
$totalCitations   = (int) $publications->sum('citations');
```

---

### Pipeline D: Conferences & Seminars
> **Goal**: Calculate total conferences/seminars attended or contributed by current scholars.

```php
$rawSeminarIds = [];
foreach ($reviewerProfiles as $rp) {
    if (!empty($rp->seminar) && is_array($rp->seminar)) {
        foreach ($rp->seminar as $sid) {
            if (!empty($sid)) {
                $rawSeminarIds[] = (string) $sid;
            }
        }
    }
}
$seminarCount = count(array_unique($rawSeminarIds));
```

---

## 4. Step-by-Step Practical Data Walkthrough (Example)

Let's trace an organization named **"Tata Consultancy Services(TCS)"** (`organization_id = "org_100"`):

### Scenario Database State:

```json
// 1. experience collection
[
  { "organization_id": "org_100", "user_id": "u_1", "current_organization": 1, "status": 1 },
  { "organization_id": "org_100", "user_id": "u_2", "current_organization": 1, "status": 1 },
  { "organization_id": "org_100", "user_id": "u_3", "current_organization": 0, "status": 1 },
  { "organization_id": "org_100", "user_id": "u_4", "current_organization": 1, "status": 0 } // INACTIVE
]

// 2. users collection
[
  { "_id": "u_1", "name": "Dr. Alice Smith", "status": 1 },
  { "_id": "u_2", "name": "Prof. Bob Jones", "status": 1 },
  { "_id": "u_3", "name": "Charlie Ray",     "status": 1 }
]

// 3. reviewer_profile collection
[
  { "user_id": "u_1", "publication": ["pub_A", "pub_B"], "seminar": ["sem_1", "sem_2"], "status": 1 },
  { "user_id": "u_2", "publication": ["pub_B", "pub_C", "pub_DELETED"], "seminar": ["sem_2"], "status": 1 }
]

// 4. publications collection
[
  { "_id": "pub_A", "title": "Quantum ML in Banking", "citations": 12, "status": 1 },
  { "_id": "pub_B", "title": "Scalable Cloud Data",    "citations": 8,  "status": 1 },
  { "_id": "pub_C", "title": "Distributed Ledgers",   "citations": 5,  "status": 1 }
  // "pub_DELETED" does not exist in publications collection or has status = 0
]
```

### Resulting Output Metrics:
| Metric | Calculation | Final Value |
| :--- | :--- | :--- |
| **Scholars** | Active current users `u_1`, `u_2` (`u_4` skipped due to status 0) | **2 Scholars** |
| **Alumnus** | Past active users `u_3` | **1 Alumnus** |
| **Publications** | `pub_A`, `pub_B`, `pub_C` (all verified status 1) | **3 Publications** |
| **Citations** | 12 (`pub_A`) + 8 (`pub_B`) + 5 (`pub_C`) | **25 Citations** |
| **Conferences/Seminar** | Unique seminar IDs `sem_1`, `sem_2` | **2 Seminars** |

---

## 5. UI Mapping (Where Data Appears in Blade)

In [`resources/views/livewire/institution/show.blade.php`](file:///c:/Users/hp/Desktop/L12S9/resources/views/livewire/institution/show.blade.php):

| UI Position | Visual Representation | Underlying Variable |
| :--- | :--- | :--- |
| **Hero Stats Cards** | `PUBLICATION - X`<br>`CITATIONS - Y`<br>`CONFERENCES/SEMINAR - Z` | `$metrics['publication_count']`<br>`$metrics['citations_count']`<br>`$metrics['seminar_count']` |
| **Tab Navigation** | `Scholars (Count)`<br>`Alumnus (Count)`<br>`Publications (Count)` | `$activeTab`<br>`$scholars->count()`<br>`$alumni->count()`<br>`$publications->count()` |
| **Scholars Tab Content** | Grid of Scholar Cards with name, role, and avatar | `@foreach($scholars as $scholar)`<br>`$scholar->avatar` |
| **Alumnus Tab Content** | Grid of Alumnus Cards with name, role, and avatar | `@foreach($alumni as $alum)`<br>`$alum->avatar` |
| **Publications Tab Content** | Modern Card Feed List with Publication Title, Author(s) (only status=1), and Published Date | `@forelse($publications as $pub)` |

---

## 6. Edge Cases & Data Integrity Rules

1. **Status Flexibility (`[1, '1', true]`)**:
   MongoDB stores status variously as integer `1` or string `"1"`. Queries always use `whereIn('status', [1, '1'])` to guarantee no valid records are omitted.
2. **Exclusion of Current Scholars from Alumnus**:
   If a user had a past role and a current role at the same organization, `diff($currentScholarIds)` ensures they are displayed once as a **Scholar**, not duplicated into **Alumnus**.
3. **Dangling Publication IDs Prevention**:
   `ReviewerProfile.publication` contains an array of string IDs. If a paper was deleted from `publications` or marked `status=0`, the second verification query `Publication::whereIn('_id', $uniquePubIds)->whereIn('status', [1, '1'])` filters out ghost IDs so the count and the table always match 100%.
4. **User Avatar Fallback**:
   If a user has no custom uploaded avatar, [`app/Models/User.php`](file:///c:/Users/hp/Desktop/L12S9/app/Models/User.php) generates a sleek SVG initial avatar (`ui-avatars.com` or inline fallback).

---

## 7. Caching & Performance Optimization

To handle high traffic with sub-millisecond response times:
- **Cache Key**: `org_metrics_v6_{organization_id}`
- **TTL**: 1800 seconds (30 minutes)
- **Batch Processing**: All sub-queries (`Experience`, `User`, `ReviewerProfile`, `Publication`) use `whereIn` batching. No N+1 queries occur inside loops.
- **Cache Invalidation**: Handled when modifying organization or experience records.

---
*Created and maintained under `docs/institution_metrics_data_flow.md`.*
