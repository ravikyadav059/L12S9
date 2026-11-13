# Security — Journals, Journal Roles, Certificates & Institutions

Scope: public pages [journal/index](../resources/views/livewire/journal/index.blade.php), [journal/show](../resources/views/livewire/journal/show.blade.php), [journal/peerReviewCertificate](../resources/views/livewire/journal/peerReviewCertificate.blade.php), [institution/index](../resources/views/livewire/institution/index.blade.php), [institution/show](../resources/views/livewire/institution/show.blade.php); traits [HandlesAddJournalModal.php](../app/Livewire/Concerns/HandlesAddJournalModal.php), [HandlesJournalRoleSection.php](../app/Livewire/Concerns/HandlesJournalRoleSection.php), [HandlesJournalVerification.php](../app/Livewire/Concerns/HandlesJournalVerification.php); service [JournalVerificationService.php](../app/Services/JournalVerificationService.php).

---

## JRN-01 — Stored XSS via `</script>` in JSON-LD blocks — **High**

- **Sinks:**
  - [journal/show.blade.php:242-244](../resources/views/livewire/journal/show.blade.php#L242-L244)
  - [journal/peerReviewCertificate.blade.php:232-234](../resources/views/livewire/journal/peerReviewCertificate.blade.php#L232-L234)
  ```blade
  <script type="application/ld+json">
  {!! json_encode(array_filter($jsonLd), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
  </script>
  ```
- **Problem:** `json_encode` without `JSON_HEX_TAG` leaves `<` and `>` as-is, and `JSON_UNESCAPED_SLASHES` leaves `/` as-is, so a value containing `</script>` closes the script block. The array contains `journal_title`, `journal_short_name` and `organization_name`.
- **How a user controls the title:** `saveNewJournal()` (reachable from the **guest** deposit page) checks the entered title against the registry with `isJournalTitleMatchingOfficial()` ([HandlesAddJournalModal.php:336-363](../app/Livewire/Concerns/HandlesAddJournalModal.php#L336-L363)). That function strips all punctuation and accepts ≥ 75 % `similar_text`. Verified during review: `"<official title></script><svg/onload=alert(1)>"` scores **86.6 %** against the official title and passes. The raw (un-normalized) title is then saved.
- **Impact:** Script runs for every visitor of `/journal/{slug}` and `/journal-certificate/{slug}`.
- **Fix:** Use Blade's `@json($jsonLd)` / `Js::from()` or add `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` and drop `JSON_UNESCAPED_SLASHES`. Also save the **official** registry title, not the user-typed one.

## JRN-02 — Regex injection / ReDoS on the public institutions search — **Medium**

- **Where:** [institution/index.blade.php:106-115](../resources/views/livewire/institution/index.blade.php#L106-L115)
  ```php
  $regex = new \MongoDB\BSON\Regex($searchClean, 'i');
  ```
- **Problem:** The search text is used as a raw regular expression (every other search in the app uses `preg_quote`). It is applied to 6 fields with `$or`, on a public page with no throttling.
- **Attack:** Search `(a+)+$`, `(.*a){20}` etc. to make MongoDB do catastrophic backtracking on every organization document → high DB CPU / slow site (DoS). An invalid pattern like `(` causes a server error.
- **Fix:** `new Regex(preg_quote($searchClean, '/'), 'i')`.

## JRN-03 — Peer-review certificate issued for any journal, written to `public/` on GET — **Medium**

- **Where:** [peerReviewCertificate.blade.php:19-62](../resources/views/livewire/journal/peerReviewCertificate.blade.php#L19-L62)
- **Problem:**
  1. The journal is loaded with `ReviewerJournal::where('slug', $slug)->first()` — **no status / verification filter**. Pending, rejected (`status 3`) or unverified journals receive an official-looking "verified peer-reviewed" certificate.
  2. Any anonymous GET generates a PDF with Dompdf and writes it to `public/assets/pdf/`. The file is never regenerated, so later corrections never reach the certificate.
  3. Dompdf runs with `isRemoteEnabled = true` ([line 69](../resources/views/livewire/journal/peerReviewCertificate.blade.php#L69)). Inputs are `htmlspecialchars`-escaped today, so this is not exploitable now, but it lets any future unescaped value make the server fetch remote/internal URLs.
- **Fix:** Filter `status = 1` and verified; disable `isRemoteEnabled` (all images are already base64); regenerate on journal update.

## JRN-04 — Unauthenticated, unthrottled journal creation triggers many outbound calls — **Medium**

- **Where:** `saveNewJournal()` [HandlesAddJournalModal.php:93-270](../app/Livewire/Concerns/HandlesAddJournalModal.php#L93-L270); created with `'user_id' => Auth::id()` (null for guests).
- **Problem:** Each call runs `JournalVerificationService::verify()` up to twice, which calls Crossref, OpenAlex, DOAJ and the ISSN portal, and may scrape the journal's website (several more requests). There is no login or rate limit, so the server can be used to send lots of traffic to these APIs (risking an IP ban) and to keep PHP workers busy.
- **Fix:** Require auth; `RateLimiter` per user/IP; cache registry lookups per ISSN.

## JRN-05 — Second-order SSRF in website scraping — **Low**

- **Where:** `scrapeJournalWebsite()` [JournalVerificationService.php:1055-1135](../app/Services/JournalVerificationService.php#L1055-L1135)
- **Problem:** The homepage URL comes from registry data, but contact/indexing/editorial links found **inside the fetched HTML** are followed as-is (`$client->get($contactUrl)` at line 1107). A hostile journal website can point those links at `http://127.0.0.1:…` or cloud metadata IPs. Results are partly reflected into the form (emails/addresses).
- **Fix:** Resolve the host and block private, loopback, link-local and metadata ranges; only follow links on the same host as the homepage; disable redirects to other hosts.

## JRN-06 — Journal role records: OK (info)

- `persistJournalRole` / `removeJournalRole` correctly compare `$record->user_id` with the target user ([HandlesJournalRoleSection.php:376](../app/Livewire/Concerns/HandlesJournalRoleSection.php#L376), [519](../app/Livewire/Concerns/HandlesJournalRoleSection.php#L519)). The weak point is the caller (`profile/journal-roles`) trusting `isOwnProfile` — see PRF-01/PRF-02.

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| JRN-01 | Read both JSON-LD blocks and their variables; ran the normalizer + `similar_text` + `json_encode` flags in a one-off `php -r` to confirm the 86.6 % match and unescaped `</script>`. |
| JRN-02 | `grep 'Regex($'` across app/views; this is the only public search without `preg_quote`. |
| JRN-03 | Read `with()` and `generatePdf()`; confirmed escaping of every interpolated value. |
| JRN-05 | Read link-discovery loop; only `filter_var(FILTER_VALIDATE_URL)` on the start URL. |
