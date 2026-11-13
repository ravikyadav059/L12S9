# Security — Publications, Article Deposit & Article Pages

Scope: [HandlesPublicationSection.php](../app/Livewire/Concerns/HandlesPublicationSection.php), [profile/publications.blade.php](../resources/views/livewire/profile/publications.blade.php), the **public** deposit page [article/deposit.blade.php](../resources/views/livewire/article/deposit.blade.php) (`/deposit-article`, no `auth`), [HandlesAddAuthorModal.php](../app/Livewire/Concerns/HandlesAddAuthorModal.php), [article/detail.blade.php](../resources/views/livewire/article/detail.blade.php), [article/index.blade.php](../resources/views/livewire/article/index.blade.php).

---

## PUB-01 — IDOR: any logged-in user can edit or delete any publication — **High**

- **Where:** [HandlesPublicationSection.php:557](../app/Livewire/Concerns/HandlesPublicationSection.php#L557) (`Publication::findOrFail($recordId)`), [HandlesPublicationSection.php:651-664](../app/Livewire/Concerns/HandlesPublicationSection.php#L651-L664) (`deletePublicationRecord`).
- **Problem:** `saveRecord` / `deleteRecord` in [publications.blade.php:122-157](../resources/views/livewire/profile/publications.blade.php#L122-L157) correctly check that the caller owns the **profile**, but the record ID from the form is never checked against `user_id`. Delete also removes the stored PDF from the public disk.
- **Attack:** On your own profile, call `deleteRecord('<someone else's publication id>')` or `saveRecord(['id' => '<other id>', ...])`.
- **Fix:** `Publication::where('user_id', $ownerUserId)->findOrFail($recordId)` in both paths (admins use a separate path).

## PUB-02 — Unauthenticated harvesting of user email addresses (deposit page) — **High**

- **Where:** `searchAuthor()` [deposit.blade.php:231-415](../resources/views/livewire/article/deposit.blade.php#L231-L415) returns `'email' => $user->email` ([line 403](../resources/views/livewire/article/deposit.blade.php#L403)) for up to 25 users per call; `lookupAuthorByEmail()` [deposit.blade.php:185](../resources/views/livewire/article/deposit.blade.php#L185) confirms whether an email is registered and returns name + affiliation.
- **Problem:** `/deposit-article` is public and these are public Livewire actions with no rate limit. The search matches on first name, last name **and email substring**.
- **Attack:** A script logged out calls `searchAuthor(0, 'a')`, `'b'`, … `'@gmail'` and collects the email list of all scholars (spam/phishing, privacy breach).
- **Fix:** Never send emails to the browser for autocomplete — return `id`, `name`, `affiliation` only. Require login for author search and throttle it.

## PUB-03 — Guests can create user accounts for any email (account squatting) — **High**

- **Where:** [HandlesAddAuthorModal.php:100-150](../app/Livewire/Concerns/HandlesAddAuthorModal.php#L100-L150), `User::create([... 'type' => 0, 'status' => 1])` at [line 136](../app/Livewire/Concerns/HandlesAddAuthorModal.php#L136). Used by the public deposit page.
- **Problem:** No login, no email confirmation, no rate limit. The new account is **active** (`status 1`) and the email becomes taken.
- **Impact:** The real owner of that email can no longer register (`unique:users` on register). An attacker can mass-create fake "registered" scholars. The error message also reveals the registered name for an existing email (enumeration).
- **Fix:** Require auth to add authors; store unregistered co-authors as plain author entries (name/email inside the publication) instead of creating `User` documents; add throttling.

## PUB-04 — Deposit upload keeps the client's filename and extension on the public disk — **High**

- **Where:** [deposit.blade.php:841-844](../resources/views/livewire/article/deposit.blade.php#L841-L844)
  ```php
  $origName = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
  $ext = $this->file->getClientOriginalExtension() ?: 'pdf';
  $imagePath = $this->file->storeAs('publication', "{$origName}_".time().".{$ext}", 'public');
  ```
- **Problem:** `mimes:pdf` checks file **content** only. A PDF-magic polyglot named `paper.html` or `paper.svg` is stored as `storage/publication/paper_<time>.html` and served by the web server as HTML → stored XSS on the app origin. Works for guests. The client filename is also reflected into a public URL.
- **Fix:** Use `$this->file->store('publication', 'public')` (random name + server-detected extension), or force `.pdf`; serve downloads with `Content-Disposition: attachment`.

## PUB-05 — Anyone can attach any registered user as co-author — **Medium**

- **Where:** deposit `deposit()` email → `registered_co_author` matching ([deposit.blade.php:789-821](../resources/views/livewire/article/deposit.blade.php#L789-L821)); profile path [HandlesPublicationSection.php:586-596](../app/Livewire/Concerns/HandlesPublicationSection.php#L586-L596) accepts `author['id']` directly.
- **Problem:** Typing someone's email (or ID) makes the publication appear as theirs without their consent. A guest deposit with fake/spam content can be linked to a real scholar.
- **Fix:** Store invited co-authors as "pending" and require the co-author to accept before linking.

## PUB-06 — Guest deposits and journal creation are unthrottled — **Medium**

- **Where:** `deposit()` [deposit.blade.php:749](../resources/views/livewire/article/deposit.blade.php#L749); `saveNewJournal()` from `HandlesAddJournalModal` (see JRN-04).
- **Problem:** No login, CAPTCHA, or rate limit. Each deposit can upload up to 15 MB to the public disk. Bots can fill storage and the moderation queue.
- **Fix:** Require login (or CAPTCHA + `RateLimiter`) for deposits.

## PUB-07 — View counters can be inflated — **Low**

- **Where:** [article/detail.blade.php:19](../resources/views/livewire/article/detail.blade.php#L19) `increment('view_counts')` on every mount, before checking that the slug exists.
- **Fix:** Count once per session/IP (e.g. cache key with TTL).

## PUB-08 — Profile publication PDF upload — acceptable (info)

- [HandlesPublicationSection.php:525](../app/Livewire/Concerns/HandlesPublicationSection.php#L525) uses `mimes:pdf` and `->store('publications', 'public')`, which gives a random name and a **server-detected** extension. No issue; listed so the contrast with PUB-04 is clear.

## PUB-09 — DOI lookup is safe from SSRF (info)

- [HandlesPublicationSection.php:91-95](../app/Livewire/Concerns/HandlesPublicationSection.php#L91-L95) always calls `https://api.crossref.org/works/` with `rawurlencode($doi)`. The host is fixed, so this is not SSRF. Missing: per-user throttling (the `/api/publications/retrieve-doi` route has `throttle:120,1`, but the Livewire `retrieveFromCrossref` method has none).

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| PUB-01 | Read `persistPublication` / `deletePublicationRecord`; no `user_id` comparison on the looked-up record. |
| PUB-02 | Read `searchAuthor` result builder (returns `email`) and confirmed `deposit-article` route has no middleware. |
| PUB-03 | Read `saveNewAuthor`; `User::create` with `status => 1`, no `Auth::check()`. |
| PUB-04 | Read `deposit()` file block + Laravel `validateMimes` (content-based). |
| PUB-05 | Read co-author resolution loops in both deposit and profile flows. |
