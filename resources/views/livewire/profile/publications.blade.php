<?php

use App\Livewire\Concerns\HandlesPublicationSection;
use App\Models\Article;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Profile Publications Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's published research papers, Crossref DOI retrieval,
 * duplicate title checking, inline add/edit form, registered journal search & Add New Journal modal,
 * ISSN checksum validation, PDF upload, keywords, and atomic sync with ReviewerProfile.publication.
 */
new class extends Component
{
    use HandlesPublicationSection;
    use WithFileUploads;

    /**
     * User Eloquent model instance whose profile is being viewed.
     */
    public ?User $user = null;

    /**
     * Flag indicating whether the viewing visitor is the profile owner.
     */
    public bool $isOwnProfile = false;

    /**
     * ID of the publication record currently being edited inline.
     */
    public ?string $editingId = null;

    /**
     * Flag indicating whether Add form is open at bottom.
     */
    public bool $showAddForm = false;

    /**
     * Number of publication records currently displayed (Load More increments by 5).
     */
    public int $limit = 5;

    /**
     * Error boundary flag.
     */
    public bool $hasError = false;

    /**
     * Load 5 more publication records.
     */
    public function loadMore(): void
    {
        $this->limit += 5;
    }

    /**
     * Component mount lifecycle hook.
     */
    public function mount(?User $user = null): void
    {
        $this->user = $user ?? $this->user;
    }

    /**
     * Catch and stop propagation of any unexpected runtime errors.
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        report($e);
        $stopPropagation();
        $this->hasError = true;
    }

    /**
     * Provide cached article types list for the searchable select.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $articleTypes = Article::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Article $a): array => [
                'id' => (string) $a->_id,
                'name' => (string) $a->name,
            ])
            ->values()
            ->all();

        return [
            'articleTypes' => $articleTypes,
        ];
    }

    /**
     * Get active publication records for this user profile.
     *
     * @return list<array<string, mixed>>
     */
    public function getRecordsProperty(): array
    {
        if (! $this->user) {
            return [];
        }

        return Publication::getCachedForUser((string) $this->user->_id, activeOnly: true);
    }

    /**
     * Authorize owner and persist a new or edited publication record.
     *
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function saveRecord(array $formData): array
    {
        if (! $this->isOwnProfile || ! Auth::check() || (string) Auth::id() !== (string) $this->user?->_id) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $this->persistPublication($formData, (string) $this->user->_id, allowStatusChange: false);
            $this->editingId = null;
            $this->showAddForm = false;
            unset($this->records);

            return [
                'ok' => true,
                'records' => $this->records,
            ];
        } catch (ValidationException $e) {
            return [
                'ok' => false,
                'errors' => $e->errors(),
            ];
        }
    }

    /**
     * Delete a publication record (invoked from inside the edit form).
     *
     * @return list<array<string, mixed>>
     */
    public function deleteRecord(string $id): array
    {
        if (! $this->isOwnProfile || ! Auth::check() || (string) Auth::id() !== (string) $this->user?->_id) {
            abort(403, 'Unauthorized action.');
        }

        $this->deletePublicationRecord($id, (string) $this->user->_id);
        $this->editingId = null;
        $this->showAddForm = false;
        unset($this->records);

        return $this->records;
    }
}; ?>

@php
    $records = $this->records;
    $totalCount = count($records);
    $visibleRecords = array_slice($records, 0, $limit);
    $remainingCount = max(0, $totalCount - count($visibleRecords));
@endphp

<div 
    id="publications"
    class="bg-white dark:bg-[#111c26] border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-xs scroll-mt-28"
    x-data="{
        records: {{ \Illuminate\Support\Js::from($visibleRecords) }},
        showAddForm: false,
        editingId: null,
        form: {
            id: null,
            doi: '',
            title: '',
            url: '',
            type: '',
            authors: [],
            journal_title: '',
            journal_name: '',
            issn_type: 'both',
            e_issn: '',
            p_issn: '',
            published_date: '',
            citations: '',
            volume: '',
            issue: '',
            page_no: '',
            image: '',
            remove_existing_pdf: false,
            publication_keywords: [],
            description: '',
            status: 1
        },
        originalSnapshot: '',
        errors: {},
        errorMessages: {},
        isSaving: false,
        isRetrievingDoi: false,
        doiFeedback: { ok: true, message: '' },
        journalDropdownOpen: false,
        journalResults: [],
        isSearchingJournals: false,
        journalCache: {},
        journalController: null,
        authorSearchQuery: '',
        authorDropdownOpen: false,
        authorSearchResults: [],
        isSearchingAuthor: false,
        authorSearchSeq: 0,
        authorCache: {},
        authorController: null,
        keywordInput: '',
        selectedPdfName: '',
        showDiscardModal: false,
        pendingAction: null,

        emptyForm() {
            return {
                id: null,
                doi: '',
                title: '',
                url: '',
                type: '',
                authors: [],
                journal_title: '',
                journal_name: '',
                issn_type: 'both',
                e_issn: '',
                p_issn: '',
                published_date: '',
                citations: '',
                volume: '',
                issue: '',
                page_no: '',
                image: '',
                remove_existing_pdf: false,
                publication_keywords: [],
                description: '',
                status: 1
            };
        },

        init() {
            this.originalSnapshot = JSON.stringify(this.form);
        },

        isDirty() {
            return JSON.stringify(this.form) !== this.originalSnapshot;
        },

        resetErrors() {
            this.errors = {};
            this.errorMessages = {};
            this.doiFeedback = { ok: true, message: '' };
        },

        openAddForm() {
            if ((this.showAddForm || this.editingId) && this.isDirty()) {
                this.pendingAction = () => this.performOpenAdd();
                this.showDiscardModal = true;
                return;
            }
            this.performOpenAdd();
        },

        performOpenAdd() {
            this.resetErrors();
            this.form = this.emptyForm();
            const defaultAuthorName = '{{ addslashes((string) ($user?->name ?? '')) }}'.trim();
            if (defaultAuthorName) {
                this.form.authors = [{
                    id: '{{ (string) ($user?->_id ?? '') }}',
                    name: defaultAuthorName,
                    affiliation: '{{ addslashes((string) ($user?->affiliation ?? '')) }}'.trim(),
                    email: '{{ addslashes((string) ($user?->email ?? '')) }}'.trim()
                }];
            }
            this.originalSnapshot = JSON.stringify(this.form);
            this.selectedPdfName = '';
            this.keywordInput = '';
            this.editingId = null;
            this.showAddForm = true;
            this.$nextTick(() => {
                const slot = this.$refs.addSlot;
                if (slot && this.$refs.sharedForm) {
                    slot.appendChild(this.$refs.sharedForm);
                }
                this.$dispatch('pub-date-updated', '');
            });
        },

        openEditForm(idOrItem) {
            const id = typeof idOrItem === 'object' && idOrItem !== null ? idOrItem.id : idOrItem;
            if ((this.showAddForm || this.editingId) && this.editingId !== id && this.isDirty()) {
                this.pendingAction = () => this.performOpenEdit(idOrItem);
                this.showDiscardModal = true;
                return;
            }
            this.performOpenEdit(idOrItem);
        },

        performOpenEdit(idOrItem) {
            const rec = typeof idOrItem === 'object' && idOrItem !== null
                ? idOrItem
                : this.records.find(r => r.id === idOrItem);
            if (!rec) return;
            const id = rec.id;
            this.resetErrors();

            let issnType = 'both';
            if (rec.e_issn && !rec.p_issn) issnType = 'e_issn';
            else if (rec.p_issn && !rec.e_issn) issnType = 'p_issn';

            const rawAuthors = Array.isArray(rec.authors) ? rec.authors : [];
            const mappedAuthors = rawAuthors
                .map(a => typeof a === 'string'
                    ? { id: null, name: a.trim(), affiliation: '', email: '' }
                    : {
                        id: a.id || a._id || null,
                        name: (a.name || a.fullname || a.full_name || '').trim(),
                        affiliation: a.affiliation || '',
                        email: a.email || ''
                    }
                )
                .filter(a => a.name !== '');

            this.form = {
                id: rec.id,
                doi: rec.doi || '',
                title: rec.title || '',
                url: rec.url || '',
                type: rec.type || '',
                authors: mappedAuthors,
                journal_title: rec.journal_title || '',
                journal_name: rec.journal_name || '',
                issn_type: issnType,
                e_issn: rec.e_issn || '',
                p_issn: rec.p_issn || '',
                published_date: rec.published_date || '',
                citations: rec.citations ?? '',
                volume: rec.volume ?? '',
                issue: rec.issue || '',
                page_no: rec.page_no || '',
                image: rec.image || '',
                remove_existing_pdf: false,
                publication_keywords: Array.isArray(rec.publication_keywords) ? [...rec.publication_keywords] : [],
                description: rec.description || '',
                status: rec.status ?? 1
            };
            this.originalSnapshot = JSON.stringify(this.form);
            this.selectedPdfName = '';
            this.keywordInput = '';
            this.showAddForm = false;
            this.editingId = id;
            this.$nextTick(() => {
                const slot = document.getElementById('pub-edit-slot-' + id);
                if (slot && this.$refs.sharedForm) {
                    slot.appendChild(this.$refs.sharedForm);
                }
                this.$dispatch('pub-date-updated', this.form.published_date);
            });
        },

        closeForm() {
            if (this.isDirty()) {
                this.pendingAction = () => this.performClose();
                this.showDiscardModal = true;
                return;
            }
            this.performClose();
        },

        performClose() {
            this.showAddForm = false;
            this.editingId = null;
            if (this.$refs.addSlot && this.$refs.sharedForm && this.$refs.sharedForm.parentElement !== this.$refs.addSlot.parentElement) {
                this.$refs.addSlot.parentElement.insertBefore(this.$refs.sharedForm, this.$refs.addSlot.nextSibling);
            }
            this.form = this.emptyForm();
            this.originalSnapshot = JSON.stringify(this.form);
            this.selectedPdfName = '';
            this.keywordInput = '';
            this.resetErrors();
        },

        cancelDiscard() {
            this.showDiscardModal = false;
            this.pendingAction = null;
        },

        confirmDiscard() {
            this.showDiscardModal = false;
            const fn = this.pendingAction;
            this.pendingAction = null;
            if (typeof fn === 'function') fn();
        },

        async retrieveDoi() {
            const doi = (this.form.doi || '').trim();
            if (!doi) {
                this.doiFeedback = { ok: false, message: 'Please enter a DOI before clicking Retrieve.' };
                return;
            }
            this.isRetrievingDoi = true;
            this.doiFeedback = { ok: true, message: '' };
            try {
                const resp = await fetch('/api/publications/retrieve-doi?doi=' + encodeURIComponent(doi), {
                    headers: { 'Accept': 'application/json' }
                });
                const res = await resp.json();
                if (!res || !res.found) {
                    this.doiFeedback = { ok: false, message: (res && res.message) ? res.message : 'Publication not found for this DOI.' };
                    return;
                }
                const d = res.data || {};
                if (d.doi) this.form.doi = d.doi;
                if (d.title) this.form.title = d.title;
                if (d.url) this.form.url = d.url;
                if (d.type) this.form.type = d.type;
                if (Array.isArray(d.authors) && d.authors.length > 0) this.form.authors = d.authors;
                if (d.journal_title) this.form.journal_title = d.journal_title;
                if (d.journal_name) this.form.journal_name = d.journal_name;
                if (d.issn_type) this.form.issn_type = d.issn_type;
                if (d.e_issn) this.form.e_issn = d.e_issn;
                if (d.p_issn) this.form.p_issn = d.p_issn;
                if (d.published_date) {
                    this.form.published_date = d.published_date;
                    this.$dispatch('pub-date-updated', d.published_date);
                }
                if (d.citations !== undefined && d.citations !== '') this.form.citations = d.citations;
                if (d.volume) this.form.volume = d.volume;
                if (d.issue) this.form.issue = d.issue;
                if (d.page_no) this.form.page_no = d.page_no;
                if (Array.isArray(d.publication_keywords) && d.publication_keywords.length > 0) {
                    this.form.publication_keywords = d.publication_keywords;
                }
                if (d.description) this.form.description = d.description;
                this.doiFeedback = { ok: true, message: 'Publication metadata retrieved successfully.' };
            } finally {
                this.isRetrievingDoi = false;
            }
        },

        async checkTitleDuplicate() {
            const title = (this.form.title || '').trim();
            if (!title) return;
            const isDup = await this.$wire.checkTitleDuplicate(title, this.form.id);
            if (isDup) {
                this.form.title = '';
                this.errors.title = true;
                this.errorMessages.title = 'A publication with this title already exists.';
            }
        },

        async searchAuthorsCompact() {
            const q = (this.authorSearchQuery || '').trim();
            if (q.length < 2) {
                this.authorSearchResults = [];
                this.authorDropdownOpen = false;
                this.isSearchingAuthor = false;
                return;
            }
            const key = q.toLowerCase();
            if (this.authorCache[key]) {
                this.authorSearchResults = this.authorCache[key];
                this.authorDropdownOpen = true;
                this.isSearchingAuthor = false;
                return;
            }
            if (this.authorController) {
                this.authorController.abort();
            }
            this.authorController = new AbortController();
            const seq = ++this.authorSearchSeq;
            this.isSearchingAuthor = true;
            this.authorDropdownOpen = true;
            try {
                const resp = await fetch('/api/authors?q=' + encodeURIComponent(q), {
                    signal: this.authorController.signal,
                    headers: { 'Accept': 'application/json' }
                });
                const json = await resp.json();
                if (seq !== this.authorSearchSeq) return;
                const list = Array.isArray(json.authors) ? json.authors : [];
                this.authorCache[key] = list;
                this.authorSearchResults = list;
            } catch (e) {
                if (e.name !== 'AbortError' && seq === this.authorSearchSeq) {
                    this.authorSearchResults = [];
                }
            } finally {
                if (seq === this.authorSearchSeq) {
                    this.isSearchingAuthor = false;
                }
            }
        },

        selectAuthorChip(res) {
            const name = (res.name || '').trim();
            if (!name) return;
            const exists = this.form.authors.some(a => (a.name || '').toLowerCase() === name.toLowerCase());
            if (!exists) {
                this.form.authors.push({
                    id: res.id || res._id || null,
                    name: name,
                    affiliation: res.affiliation || '',
                    email: res.email || ''
                });
            }
            this.authorSearchQuery = '';
            this.authorSearchResults = [];
            this.authorDropdownOpen = false;
            this.errors.authors = false;
            this.errorMessages.authors = '';
        },

        openAddAuthorModal(name) {
            this.authorDropdownOpen = false;
            this.$wire.openAddAuthorModal(null, name || this.authorSearchQuery);
        },

        addAuthorFromModal(author) {
            if (!author || !author.name) return;
            this.selectAuthorChip(author);
        },

        removeAuthorChip(idx) {
            this.form.authors.splice(idx, 1);
        },

        async searchJournals() {
            const q = (this.form.journal_name || '').trim();
            if (q.length < 2) {
                this.journalResults = [];
                this.journalDropdownOpen = false;
                return;
            }
            const key = q.toLowerCase();
            if (this.journalCache[key]) {
                this.journalResults = this.journalCache[key];
                this.journalDropdownOpen = true;
                return;
            }
            if (this.journalController) {
                this.journalController.abort();
            }
            this.journalController = new AbortController();
            this.isSearchingJournals = true;
            this.journalDropdownOpen = true;
            try {
                const resp = await fetch('/search-journals?q=' + encodeURIComponent(q) + '&limit=15', {
                    signal: this.journalController.signal,
                    headers: { 'Accept': 'application/json' }
                });
                const json = await resp.json();
                const list = Array.isArray(json.journals) ? json.journals : [];
                this.journalCache[key] = list;
                this.journalResults = list;
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.journalResults = [];
                }
            } finally {
                this.isSearchingJournals = false;
            }
        },

        selectJournal(j) {
            this.form.journal_title = j.id || j._id || '';
            this.form.journal_name = j.journal_title || '';
            this.form.e_issn = j.e_issn || '';
            this.form.p_issn = j.p_issn || '';
            if (this.form.e_issn && this.form.p_issn) this.form.issn_type = 'both';
            else if (this.form.e_issn) this.form.issn_type = 'e_issn';
            else if (this.form.p_issn) this.form.issn_type = 'p_issn';
            this.journalDropdownOpen = false;
            this.errors.journal_title = false;
            this.errors.e_issn = false;
            this.errors.p_issn = false;
        },

        openAddJournalModalJs() {
            this.journalDropdownOpen = false;
            this.$dispatch('open-add-journal-modal', { title: this.form.journal_name, target: 'publications' });
        },

        handleJournalCreated(detail) {
            if ((!this.showAddForm && !this.editingId) || !detail) return;
            this.form.journal_title = detail.id || '';
            this.form.journal_name = detail.journal_title || '';
            this.form.e_issn = detail.e_issn || '';
            this.form.p_issn = detail.p_issn || '';
            if (this.form.e_issn && this.form.p_issn) this.form.issn_type = 'both';
            else if (this.form.e_issn) this.form.issn_type = 'e_issn';
            else if (this.form.p_issn) this.form.issn_type = 'p_issn';
            this.errors.journal_title = false;
            this.errors.e_issn = false;
            this.errors.p_issn = false;
        },

        setIssnType(type) {
            this.form.issn_type = type;
            this.errors.e_issn = false;
            this.errors.p_issn = false;
            this.errorMessages.e_issn = '';
            this.errorMessages.p_issn = '';
            if (type === 'e_issn') {
                this.form.p_issn = '';
            } else if (type === 'p_issn') {
                this.form.e_issn = '';
            }
        },

        formatIssnField(field) {
            let clean = (this.form[field] || '').toUpperCase().replace(/[^0-9X]/g, '');
            if (clean.length > 8) clean = clean.slice(0, 8);
            this.form[field] = clean.length > 4 ? clean.slice(0, 4) + '-' + clean.slice(4) : clean;
        },

        isValidIssnChecksum(val) {
            const clean = (val || '').toUpperCase().replace(/[^0-9X]/g, '');
            if (clean.length !== 8) return false;
            let sum = 0;
            for (let i = 0; i < 7; i++) {
                const digit = parseInt(clean[i], 10);
                if (isNaN(digit)) return false;
                sum += digit * (8 - i);
            }
            const checkChar = clean[7];
            const checkVal = checkChar === 'X' ? 10 : parseInt(checkChar, 10);
            if (isNaN(checkVal)) return false;
            return (sum + checkVal) % 11 === 0;
        },

        commitKeywordInput() {
            const raw = (this.keywordInput || '').trim();
            if (!raw) return;
            raw.split(',').map(s => s.trim()).filter(Boolean).forEach(kw => {
                if (!this.form.publication_keywords.includes(kw)) {
                    this.form.publication_keywords.push(kw);
                }
            });
            this.keywordInput = '';
        },

        handleKeywordPaste(e) {
            const pasted = (e.clipboardData || window.clipboardData).getData('text') || '';
            this.keywordInput = (this.keywordInput ? this.keywordInput + ',' : '') + pasted;
            this.commitKeywordInput();
        },

        removeKeyword(idx) {
            this.form.publication_keywords.splice(idx, 1);
        },

        isFuturePublishedDate(val) {
            if (!val || !val.trim()) return false;
            const str = val.trim();
            const now = new Date();
            const curYear = now.getFullYear();
            const curMonth = now.getMonth();

            const parts = str.split(/\s+/);
            const months = ['january','february','march','april','may','june','july','august','september','october','november','december'];
            if (parts.length === 2) {
                const mIdx = months.indexOf(parts[0].toLowerCase());
                const y = parseInt(parts[1], 10);
                if (mIdx !== -1 && !isNaN(y)) {
                    if (y > curYear) return true;
                    if (y === curYear && mIdx > curMonth) return true;
                    return false;
                }
            }

            const parsed = Date.parse(str.length === 7 && str.includes('-') ? str + '-01' : str);
            if (!isNaN(parsed)) {
                const d = new Date(parsed);
                if (d.getFullYear() > curYear) return true;
                if (d.getFullYear() === curYear && d.getMonth() > curMonth) return true;
                if (d > now) return true;
            }
            return false;
        },

        validateClient() {
            this.errors = {};
            this.errorMessages = {};
            let valid = true;

            if (this.form.issn_type === 'e_issn') {
                this.form.p_issn = '';
            } else if (this.form.issn_type === 'p_issn') {
                this.form.e_issn = '';
            }

            if (!(this.form.title || '').trim()) {
                this.errors.title = true;
                this.errorMessages.title = 'Title is required.';
                valid = false;
            }
            if (!(this.form.type || '').trim()) {
                this.errors.type = true;
                this.errorMessages.type = 'Article Type is required.';
                valid = false;
            }
            if (!(this.form.journal_title || '').trim()) {
                this.errors.journal_title = true;
                this.errorMessages.journal_title = 'Please select a journal from the list or add a new journal.';
                valid = false;
            }
            if (this.form.issn_type === 'both' || this.form.issn_type === 'e_issn') {
                if (!(this.form.e_issn || '').trim()) {
                    this.errors.e_issn = true;
                    this.errorMessages.e_issn = 'E-ISSN is required.';
                    valid = false;
                } else if (!this.isValidIssnChecksum(this.form.e_issn)) {
                    this.errors.e_issn = true;
                    this.errorMessages.e_issn = 'The E-ISSN is not a valid ISSN checksum.';
                    valid = false;
                }
            }
            if (this.form.issn_type === 'both' || this.form.issn_type === 'p_issn') {
                if (!(this.form.p_issn || '').trim()) {
                    this.errors.p_issn = true;
                    this.errorMessages.p_issn = 'P-ISSN is required.';
                    valid = false;
                } else if (!this.isValidIssnChecksum(this.form.p_issn)) {
                    this.errors.p_issn = true;
                    this.errorMessages.p_issn = 'The P-ISSN is not a valid ISSN checksum.';
                    valid = false;
                }
            }
            if (!(this.form.published_date || '').trim()) {
                this.errors.published_date = true;
                this.errorMessages.published_date = 'Publication Date is required.';
                valid = false;
            } else if (this.isFuturePublishedDate(this.form.published_date)) {
                this.errors.published_date = true;
                this.errorMessages.published_date = 'Publication Date cannot be in the future.';
                valid = false;
            }
            if (!(this.form.description || '').trim()) {
                this.errors.description = true;
                this.errorMessages.description = 'Abstract/Description is required.';
                valid = false;
            }
            if (Array.isArray(this.form.authors)) {
                this.form.authors = this.form.authors.filter(a => a && (a.name || '').trim() !== '');
            }
            if (!Array.isArray(this.form.authors) || this.form.authors.length === 0) {
                this.errors.authors = true;
                this.errorMessages.authors = 'At least one author is required.';
                valid = false;
            }

            return valid;
        },

        scrollToFirstError() {
            this.$nextTick(() => {
                const formEl = this.$refs.sharedForm;
                if (!formEl) return;
                const candidates = Array.from(formEl.querySelectorAll('.border-red-500, p.text-red-500'));
                const firstErrorEl = candidates.find(el => el.offsetParent !== null || el.getClientRects().length > 0);
                if (firstErrorEl) {
                    firstErrorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const input = firstErrorEl.tagName === 'INPUT' || firstErrorEl.tagName === 'TEXTAREA' || firstErrorEl.tagName === 'SELECT'
                        ? firstErrorEl
                        : firstErrorEl.querySelector('input, textarea, select');
                    if (input) input.focus();
                }
            });
        },

        async saveItem() {
            this.commitKeywordInput();
            if (!this.validateClient()) {
                this.scrollToFirstError();
                return;
            }

            this.isSaving = true;
            try {
                const res = await this.$wire.saveRecord(this.form);
                if (res && res.ok) {
                    if (Array.isArray(res.records)) {
                        this.records = res.records;
                    }
                    this.performClose();
                } else if (res && res.errors) {
                    Object.keys(res.errors).forEach(key => {
                        this.errors[key] = true;
                        this.errorMessages[key] = Array.isArray(res.errors[key]) ? res.errors[key][0] : res.errors[key];
                        if (key.startsWith('authors.') || key === 'authors') {
                            this.errors.authors = true;
                            this.errorMessages.authors = this.errorMessages[key];
                        }
                    });
                    this.scrollToFirstError();
                }
            } finally {
                this.isSaving = false;
            }
        },

        async deleteItem(id) {
            const updated = await this.$wire.deleteRecord(id);
            if (Array.isArray(updated)) {
                this.records = updated;
            }
            this.performClose();
        }
    }"
    @journal-created.window="handleJournalCreated($event.detail)"
    @author-added.window="if (showAddForm || editingId !== null) addAuthorFromModal($event.detail.author)"
>
    @if($hasError)
        <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-xs text-red-700 dark:text-red-300">
            Unable to load Publications right now. Please refresh the page.
        </div>
    @else
        {{-- Section Header (No Add Button in Header) --}}
        <div class="flex items-start justify-between gap-4 pb-5 border-b border-zinc-100 dark:border-zinc-800/80">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">&#x1F4DA;</span>
                <div>
                    <h2 class="font-bold font-space text-base text-zinc-900 dark:text-white">
                        Publications ({{ $totalCount }})
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Journal articles, research papers, and scholarly publications
                    </p>
                </div>
            </div>
        </div>

        {{-- Records List (5 Initially + Load More) --}}
        <div class="mt-5 space-y-4">
            @forelse($visibleRecords as $item)
                @php
                    $detailUrl = ! empty($item['slug'])
                        ? route('publication.detail', ['slug' => $item['slug']])
                        : (! empty($item['url']) ? $item['url'] : '#');
                    $keywords = is_array($item['publication_keywords'] ?? null) ? $item['publication_keywords'] : [];
                    $visibleKeywords = array_slice($keywords, 0, 3);
                    $remainingKeywordsCount = max(0, count($keywords) - 3);
                @endphp
                <div wire:key="pub-card-{{ $item['id'] }}">
                        <div class="group p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 hover:border-brand/40 transition-all">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1 space-y-2">
                                    {{-- Publication Title (Opens Detail Page) --}}
                                    <h4 class="font-bold text-sm sm:text-[15px] leading-snug">
                                        <a href="{{ $detailUrl }}" class="text-zinc-900 dark:text-white hover:text-[#198BEA] dark:hover:text-[#198BEA] transition-colors">
                                            {{ $item['title'] }}
                                        </a>
                                    </h4>

                                    {{-- Journal with label & Published Date --}}
                                    @if(!empty($item['journal_name']) || !empty($item['published_date']))
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            @if(!empty($item['journal_name']))
                                                <span>
                                                    <span class="font-semibold text-zinc-500 dark:text-zinc-400">Journal:</span>
                                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item['journal_name'] }}</span>
                                                </span>
                                            @endif
                                            @if(!empty($item['journal_name']) && !empty($item['published_date']))
                                                <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                            @endif
                                            @if(!empty($item['published_date']))
                                                <span>
                                                    <span class="font-semibold text-zinc-500 dark:text-zinc-400">Published:</span>
                                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item['published_date'] }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Description (2 lines max with ellipsis) --}}
                                    @if(!empty($item['description']))
                                        <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed line-clamp-2">
                                            <span class="font-semibold text-zinc-500 dark:text-zinc-400">Description:</span>
                                            {{ $item['description'] }}
                                        </p>
                                    @endif

                                    {{-- Keywords (Max 3 + "+X MORE" linking to detail page) --}}
                                    @if(!empty($visibleKeywords))
                                        <div class="flex flex-wrap items-center gap-2 pt-1">
                                            @foreach($visibleKeywords as $kw)
                                                <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide bg-sky-50 text-[#0284c7] dark:bg-sky-950/50 dark:text-sky-300">
                                                    {{ $kw }}
                                                </span>
                                            @endforeach
                                            @if($remainingKeywordsCount > 0)
                                                <a href="{{ $detailUrl }}" class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide bg-sky-50 text-[#0284c7] dark:bg-sky-950/50 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900/60 transition-colors">
                                                    +{{ $remainingKeywordsCount }} more
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if($isOwnProfile)
                                    <button 
                                        type="button" 
                                        x-show="editingId !== '{{ $item['id'] }}'"
                                        @click="openEditForm({{ \Illuminate\Support\Js::from($item) }})"
                                        class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        title="Edit Publication"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                    </button>
                                @endif
                            </div>

                            {{-- Inline Edit Form Slot (Single Shared Form Teleported Here) --}}
                            @if($isOwnProfile)
                                <div id="pub-edit-slot-{{ $item['id'] }}" x-show="editingId === '{{ $item['id'] }}'" x-cloak></div>
                            @endif
                        </div>
                </div>
            @empty
                <div x-show="!showAddForm" class="py-10 text-center border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl">
                    <i class="fa-solid fa-book-bookmark text-2xl text-zinc-300 dark:text-zinc-700 mb-2"></i>
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">No publications added yet.</p>
                </div>
            @endforelse
        </div>

        {{-- Single Shared Add/Edit Form (0 Server Round-Trips) & Bottom Row (+ Add Publication & Load More) --}}
        @if($isOwnProfile || $remainingCount > 0)
            <div class="mt-5">
                @if($isOwnProfile)
                    <div x-ref="addSlot" x-show="showAddForm" x-cloak></div>
                    <div x-ref="sharedForm" x-show="showAddForm || editingId !== null" x-cloak wire:ignore.self>
                        <x-profile.publication-form :show-status="false" :article-types="$articleTypes" />
                    </div>
                @endif
                <div class="relative flex items-center justify-between gap-3 flex-wrap min-h-[34px]" x-show="!showAddForm">
                    @if($isOwnProfile)
                        <button 
                            type="button" 
                            @click="openAddForm()"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:text-brand-600 transition cursor-pointer"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Add Publication</span>
                        </button>
                    @else
                        <span></span>
                    @endif
                    @if($remainingCount > 0)
                        <button 
                            type="button" 
                            wire:click="loadMore"
                            wire:loading.attr="disabled"
                            class="md:absolute md:left-1/2 md:-translate-x-1/2 inline-flex items-center gap-2 px-4 py-1.5 text-xs font-bold text-brand bg-brand/5 hover:bg-brand/10 border border-brand/20 rounded-xl transition cursor-pointer disabled:opacity-60"
                        >
                            <i wire:loading.remove wire:target="loadMore" class="fa-solid fa-chevron-down text-[10px]"></i>
                            <i wire:loading wire:target="loadMore" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <span>Load More ({{ $remainingCount }})</span>
                        </button>
                    @endif
                    <span class="hidden md:block"></span>
                </div>
            </div>
        @endif

        {{-- 100% Frontend Unsaved Changes Modal --}}
        <div 
            x-show="showDiscardModal" 
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
            @keydown.escape.window="if (showDiscardModal) { cancelDiscard(); }"
        >
            <div 
                @click.outside="cancelDiscard()"
                class="w-full max-w-sm bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-2xl space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-base"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm text-zinc-900 dark:text-white">Unsaved Changes</h4>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">You have unsaved changes in this form. Discard changes and proceed?</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button 
                        type="button" 
                        @click="cancelDiscard()"
                        class="px-4 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-700 transition cursor-pointer"
                    >
                        Keep Editing
                    </button>
                    <button 
                        type="button" 
                        @click="confirmDiscard()"
                        class="px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition cursor-pointer"
                    >
                        Discard Changes
                    </button>
                </div>
            </div>
        </div>

        {{-- Shared Add New Journal Modal --}}
        <x-modals.add-journal scope="publications" />

        {{-- Shared Add New Author Modal --}}
        <x-modals.add-author />

        {{-- Shared Duplicate Article Modal --}}
        <x-modals.duplicate-article target-input="#publications input" />
    @endif
</div>
