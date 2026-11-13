# Security — Questions & Answers

Scope: [question/index.blade.php](../resources/views/livewire/question/index.blade.php), [question/show.blade.php](../resources/views/livewire/question/show.blade.php), [components/question/answer-card.blade.php](../resources/views/components/question/answer-card.blade.php), [HandlesQuestionForms.php](../app/Livewire/Concerns/HandlesQuestionForms.php), [HandlesQuestionInteractions.php](../app/Livewire/Concerns/HandlesQuestionInteractions.php), models `Question`, `Answer`, `Replies`, `Vote`.

---

## QA-01 — Stored XSS in question description — **Critical**

- **Sink:** [show.blade.php:793](../resources/views/livewire/question/show.blade.php#L793) `{!! $question->description !!}`
- **Source:** [HandlesQuestionForms.php:137](../app/Livewire/Concerns/HandlesQuestionForms.php#L137) (create) and [line 304](../app/Livewire/Concerns/HandlesQuestionForms.php#L304) (edit) store `$this->newDescription` / `$this->editDescription` exactly as sent. Validation is only `required|min:10`.
- **Attack:** Any registered user posts a question whose description is `<img src=x onerror="...">`. Every visitor of `/question/{slug}` (public page, admins included) runs the script → session riding, admin actions on behalf of an admin, phishing overlays.
- **Fix:** Sanitize rich text on save with an allow-list HTML purifier (e.g. `mews/purifier` / `ezyang/htmlpurifier`) — needs dependency approval — or render as Markdown with HTML disabled. Do not rely on client-side editor sanitization.

## QA-02 — Stored XSS in answers — **Critical**

- **Sink:** [answer-card.blade.php:212](../resources/views/components/question/answer-card.blade.php#L212) `{!! $ans->content !!}`
- **Source:** [show.blade.php:195](../resources/views/livewire/question/show.blade.php#L195) (submit) and [show.blade.php:523](../resources/views/livewire/question/show.blade.php#L523) (edit) store raw content. Only `strip_tags()` is used to check the text is not empty — the stored value keeps the HTML.
- **Fix:** Same as QA-01.

## QA-03 — A question author can delete answers on **other** questions — **High**

- **Where:** `deleteAnswer()` [show.blade.php:450-480](../resources/views/livewire/question/show.blade.php#L450-L480)
- **Problem:** The answer is loaded by ID ([line 456](../resources/views/livewire/question/show.blade.php#L456)) and permission is granted if the caller is the author of the question identified by `$this->slug`. The code never checks that the answer belongs to that question.
- **Attack:** User A opens their own question page and calls `deleteAnswer('<answer id from any other question>')`; the check "current user = question author" passes and the foreign answer (plus its replies) is deleted. It also decrements the wrong question's `answer_count`.
- **Fix:** `Answer::where('question_id', (string) $question->_id)->findOrFail($answerId)`.

## QA-04 — A question author can accept any answer from any question — **Medium**

- **Where:** `toggleAcceptAnswer()` [show.blade.php:211-251](../resources/views/livewire/question/show.blade.php#L211-L251), lookup at [line 233](../resources/views/livewire/question/show.blade.php#L233)
- **Problem:** Same missing `question_id` check as QA-03. An author can mark an answer on another question as "accepted".
- **Fix:** Scope the lookup to the current question.

## QA-05 — `$slug` is a client-writable property used for authorization — **Medium**

- **Where:** [show.blade.php:21](../resources/views/livewire/question/show.blade.php#L21) `public string $slug` (not `#[Locked]`); used by `toggleAcceptAnswer`, `deleteAnswer`, `deleteQuestion`, `toggleVote`.
- **Problem:** The client can change `slug` during a request, which changes which question is used for the permission check. Together with QA-03/QA-04 it makes cross-question actions easier.
- **Fix:** Add `#[Locked]` to `slug` (and to `editingAnswerId`, `editingQuestionId`).

## QA-06 — New questions are published immediately, with no moderation — **Medium**

- **Where:** [HandlesQuestionForms.php:134-150](../app/Livewire/Concerns/HandlesQuestionForms.php#L134-L150) creates the question with `'status' => 1`.
- **Problem:** The pending-status gate in `mount()` ([show.blade.php:56-65](../resources/views/livewire/question/show.blade.php#L56-L65)) exists, but new questions never use it. A QA-01 payload is live for every visitor the moment it is posted, with no review step before it is served.
- **Fix:** Covered by QA-01. Optionally create new questions as pending (`status 0`) for new/unverified accounts.

## QA-07 — No rate limiting on posting questions, answers, comments, votes — **Medium**

- **Where:** `submitQuestion` ([HandlesQuestionForms.php:89](../app/Livewire/Concerns/HandlesQuestionForms.php#L89)), `submitAnswer` ([show.blade.php:142](../resources/views/livewire/question/show.blade.php#L142)), `addComment` ([show.blade.php:365](../resources/views/livewire/question/show.blade.php#L365)), `toggleVote`, `toggleFollowUser`.
- **Problem:** The only guard is a 5-second duplicate check on identical answer text. `App\Traits\DetectsSpam` exists but is only used by profile models, not Q&A. A bot account can flood content or vote-toggle at high speed.
- **Fix:** `RateLimiter::attempt("qa:{$userId}", 10, ...)` around write actions; reuse `DetectsSpam` for Q&A text.

## QA-08 — View count inflation — **Low**

- **Where:** [show.blade.php:67](../resources/views/livewire/question/show.blade.php#L67) `$question->increment('views_count')` on every mount.
- **Fix:** Deduplicate per session/IP.

## QA-09 — Thumbnails — acceptable (info)

- `newThumbnail` / `editThumbnail` use `nullable|image|max:5120` and `->store('thumbnails', 'public')` (random name, server-detected extension). Note that the `image` rule allows **SVG**; SVG served from `/storage/thumbnails/*.svg` and opened directly can run script. Consider `mimes:jpg,jpeg,png,webp` only.

---

### Cross-check log

| ID | How it was verified |
| --- | --- |
| QA-01/02 | `grep '{!!'` over `resources/views` → 5 hits; 2 render user HTML. Traced both to raw `create`/`update` calls. `nl2br(e(...))` in article detail is safe. The two `json_encode` JSON-LD blocks are **not** safe — see JRN-01 in [journals_and_institutions.md](journals_and_institutions.md). |
| QA-06 | Re-checked during review: first draft said questions start pending; code shows `'status' => 1`, so the finding was rewritten. |
| QA-03/04 | Read the full method bodies; no `question_id` filter on `Answer::find`. |
| QA-05 | Only one `#[Locked]` in the codebase (reset-password). |
| QA-07 | `grep RateLimiter\|throttle` over `app/` and views → only `auth/login`. |
