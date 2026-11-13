<?php

use App\Livewire\Concerns\HandlesJournalRoleSection;
use App\Models\JournalRole;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Admin User Edit â€” Role in Research Journals Section Component
 *
 * Allows administrators to add, edit, verify, filter, toggle status (1-click pill),
 * and delete research journal role records for a user.
 */
new class extends Component
{
    use HandlesJournalRoleSection;

    /**
     * User being edited in admin panel.
     */
    public ?User $user = null;

    /**
     * Filter for displaying records: 'all', '1', '0'.
     */
    public string $statusFilter = 'all';

    /**
     * ID of record currently open for inline edit.
     */
    public ?string $editingId = null;

    /**
     * Flag indicating whether Add form is open at bottom.
     */
    public bool $showAddForm = false;

    /**
     * Error boundary flag.
     */
    public int $limit = 5;

    public bool $hasError = false;

    public function loadMore(): void
    {
        $this->limit += 5;
    }

    /**
     * Mount hook.
     */
    public function mount(?User $user = null): void
    {
        $this->user = $user ?? $this->user;
    }

    /**
     * Catch unexpected runtime errors.
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        report($e);
        $stopPropagation();
        $this->hasError = true;
    }

    /**
     * Provide cached journal roles list.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'journalRoles' => JournalRole::getCachedActive(),
        ];
    }

    /**
     * Retrieve filtered research journal role records for this user (Admin sees all statuses).
     *
     * @return list<array<string, mixed>>
     */
    public function getJournalRoleRecordsProperty(): array
    {
        try {
            if (! $this->user) {
                return [];
            }

            $all = RoleInResearchJournals::getCachedForUser((string) $this->user->_id, false);

            if ($this->statusFilter === '1') {
                return array_values(array_filter($all, fn ($item) => (int) $item['status'] === 1));
            }
            if ($this->statusFilter === '0') {
                return array_values(array_filter($all, fn ($item) => (int) $item['status'] === 0));
            }

            return $all;
        } catch (\Throwable $e) {
            report($e);
            $this->hasError = true;

            return [];
        }
    }

    /**
     * Retrieve counts across statuses for filter badges.
     *
     * @return array{all: int, active: int, inactive: int}
     */
    public function getCountsProperty(): array
    {
        if (! $this->user) {
            return ['all' => 0, 'active' => 0, 'inactive' => 0];
        }

        $all = RoleInResearchJournals::getCachedForUser((string) $this->user->_id, false);
        $active = count(array_filter($all, fn ($item) => (int) $item['status'] === 1));
        $inactive = count($all) - $active;

        return [
            'all' => count($all),
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    /**
     * Toggle record active/inactive status cleanly.
     */
    public function toggleRoleStatus(string $id): void
    {
        if (! $this->user) {
            return;
        }

        try {
            $record = RoleInResearchJournals::find($id);
            if (! $record) {
                $this->dispatch('toast', message: 'Journal role record not found.', type: 'danger');

                return;
            }

            $currentStatus = (int) ($record->status ?? 0);
            $newStatus = $currentStatus === 1 ? 0 : 1;
            $record->status = $newStatus;
            $record->save();

            RoleInResearchJournals::forgetUserCache((string) $this->user->_id);

            $statusText = $newStatus === 1 ? 'Active' : 'Inactive';
            $this->dispatch('toast', message: "Journal role status changed to {$statusText}.", type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to update status.', type: 'danger');
        }
    }

    /**
     * Save or update a research journal role record (Admin).
     *
     * @return array{ok: bool, errors?: array<string, list<string>>}
     */
    public function saveJournalRole(array $payload): array
    {
        if (! $this->user) {
            return ['ok' => false, 'errors' => ['general' => ['User context is missing.']]];
        }

        return $this->persistJournalRole($this->user, $payload, true);
    }

    /**
     * Delete a research journal role record (Admin).
     */
    public function deleteJournalRole(string $id): bool
    {
        if (! $this->user) {
            return false;
        }

        return $this->removeJournalRole($this->user, $id, true);
    }
}; ?>

<div>
    {{-- Error boundary fallback --}}
    @if($hasError)
        <div class="p-4 mb-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-200 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            <p class="text-xs">An error occurred while loading the Admin Role in Research Journals section.</p>
        </div>
    @endif

    <div 
        id="admin-editorial-roles"
        class="bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xs overflow-hidden"
        x-on:journal-role-autofilled.window="applyAutoFilledJournal($event.detail)"
        x-data="{
            showAddForm: @entangle('showAddForm'),
            editingId: @entangle('editingId'),
            isSaving: false,
            isVerifyingJournal: false,
            showUnsavedModal: false,
            pendingAction: null,
            originalSnapshot: '',

            // Journal Name Autocomplete State
            journalDropdownOpen: false,
            journalResults: [],
            journalLoading: false,
            hasSearchedJournals: false,
            lastJournalQuery: '',
            journalCache: {},
            journalController: null,

            // Modal 2: ISSN Found in DB
            showIssnFoundModal: false,
            foundIssnJournal: null,
            checkedIssns: {},
            dismissedIssns: {},

            // Modal 4: Official Registry Search by Title
            showRegistrySearchModal: false,
            registrySearchInput: '',

            form: {
                id: null,
                role: '',
                journal_id: null,
                journal_title: '',
                journal_short_name: '',
                e_issn: '',
                p_issn: '',
                organization_name: '',
                journal_webiste_url: '',
                status: 1
            },

            errors: {
                role: false,
                journal_title: false,
                journal_short_name: false,
                e_issn: false,
                p_issn: false,
                organization_name: false,
                journal_webiste_url: false
            },

            errorMessages: {
                role: '',
                journal_title: '',
                journal_short_name: '',
                e_issn: '',
                p_issn: '',
                organization_name: '',
                journal_webiste_url: ''
            },

            resetErrors() {
                this.errors = {
                    role: false,
                    journal_title: false,
                    journal_short_name: false,
                    e_issn: false,
                    p_issn: false,
                    organization_name: false,
                    journal_webiste_url: false
                };
                this.errorMessages = {
                    role: '',
                    journal_title: '',
                    journal_short_name: '',
                    e_issn: '',
                    p_issn: '',
                    organization_name: '',
                    journal_webiste_url: ''
                };
            },

            takeSnapshot() {
                return JSON.stringify({ form: this.form });
            },

            isDirty() {
                return this.originalSnapshot !== '' && this.takeSnapshot() !== this.originalSnapshot;
            },

            getVerificationSnapshot() {
                return {
                    journal_id: this.form.journal_id,
                    journal_title: this.form.journal_title,
                    journal_short_name: this.form.journal_short_name,
                    e_issn: this.form.e_issn,
                    p_issn: this.form.p_issn,
                    organization_name: this.form.organization_name,
                    journal_webiste_url: this.form.journal_webiste_url
                };
            },

            applyAutoFilledJournal(detail) {
                const d = Array.isArray(detail) ? detail[0] : detail;
                if (!d) return;
                if (d.journal_id !== undefined) this.form.journal_id = d.journal_id || null;
                if (d.journal_title) this.form.journal_title = d.journal_title;
                if (d.journal_short_name !== undefined && d.journal_short_name !== null) {
                    this.form.journal_short_name = d.journal_short_name;
                }
                if (d.e_issn !== undefined && d.e_issn !== null) this.form.e_issn = d.e_issn;
                if (d.p_issn !== undefined && d.p_issn !== null) this.form.p_issn = d.p_issn;
                if (d.organization_name !== undefined && d.organization_name !== null) {
                    this.form.organization_name = d.organization_name;
                }
                if (d.journal_webiste_url !== undefined && d.journal_webiste_url !== null) {
                    this.form.journal_webiste_url = d.journal_webiste_url;
                }
                this.journalDropdownOpen = false;
                this.journalCache = {};
                this.errors.journal_title = false;
                this.errors.e_issn = false;
                this.errors.p_issn = false;
                this.errors.journal_webiste_url = false;
            },

            hasExactJournalMatch() {
                const q = (this.form.journal_title || '').trim().toLowerCase();
                if (!q || !Array.isArray(this.journalResults)) return false;
                return this.journalResults.some(item =>
                    (item.journal_title || '').trim().toLowerCase() === q
                    || (item.journal_short_name || '').trim().toLowerCase() === q
                );
            },

            openAddNewJournalModal() {
                this.journalDropdownOpen = false;
                this.$dispatch('open-add-journal-modal', { title: (this.form.journal_title || '').trim() });
            },

            onJournalTitleInput() {
                this.errors.journal_title = false;
                this.errorMessages.journal_title = '';
                this.form.journal_id = null;
                this.searchJournals();
            },

            async searchJournals() {
                const q = (this.form.journal_title || '').trim();
                if (q.length < 2) {
                    this.journalResults = [];
                    this.journalDropdownOpen = false;
                    this.journalLoading = false;
                    this.hasSearchedJournals = false;
                    if (this.journalController) {
                        this.journalController.abort();
                    }
                    return;
                }
                this.journalDropdownOpen = true;
                if (q === this.lastJournalQuery && this.journalResults.length > 0) {
                    this.hasSearchedJournals = true;
                    return;
                }
                if (this.journalCache[q]) {
                    this.journalResults = this.journalCache[q];
                    this.journalLoading = false;
                    this.hasSearchedJournals = true;
                    this.lastJournalQuery = q;
                    return;
                }
                if (this.journalController) {
                    this.journalController.abort();
                }
                this.journalController = new AbortController();
                this.journalLoading = true;
                this.lastJournalQuery = q;
                try {
                    const resp = await fetch('{{ route('search-journals') }}?search=' + encodeURIComponent(q), {
                        signal: this.journalController.signal,
                        headers: { 'Accept': 'application/json' }
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        const list = Array.isArray(data.journals) ? data.journals : [];
                        this.journalCache[q] = list;
                        if ((this.form.journal_title || '').trim() === q) {
                            this.journalResults = list;
                            this.hasSearchedJournals = true;
                        }
                    }
                } catch (e) {
                    if (e.name !== 'AbortError') {
                        console.error('Journal search error:', e);
                    }
                } finally {
                    if ((this.form.journal_title || '').trim() === q) {
                        this.journalLoading = false;
                    }
                }
            },

            selectExistingJournalFromDropdown(item) {
                if (!item) return;
                this.form.journal_id = item.id || null;
                this.form.journal_title = item.journal_title || '';
                this.form.journal_short_name = item.journal_short_name || '';
                this.form.e_issn = item.e_issn || '';
                this.form.p_issn = item.p_issn || '';
                this.form.organization_name = item.organization_name || '';
                this.form.journal_webiste_url = item.journal_webiste_url || '';
                this.journalDropdownOpen = false;
                this.lastJournalQuery = (this.form.journal_title || '').trim();
                this.resetErrors();
            },

            selectNewJournal() {
                const q = (this.form.journal_title || '').trim().toLowerCase();
                if (q && Array.isArray(this.journalResults)) {
                    const exact = this.journalResults.find(item => (item.journal_title || '').trim().toLowerCase() === q);
                    if (exact) {
                        this.selectExistingJournalFromDropdown(exact);
                        return;
                    }
                }
                if (this.hasSearchedJournals && q.length >= 2) {
                    this.openAddNewJournalModal();
                    return;
                }
                this.journalDropdownOpen = false;
                this.lastJournalQuery = (this.form.journal_title || '').trim();
            },

            formatIssnString(value) {
                const raw = (value || '').toUpperCase();
                let clean = '';
                for (let i = 0; i < raw.length && clean.length < 8; i++) {
                    const ch = raw[i];
                    if (clean.length < 7) {
                        if (/[0-9]/.test(ch)) clean += ch;
                    } else {
                        if (/[0-9X]/.test(ch)) clean += ch;
                    }
                }
                if (clean.length > 4) {
                    clean = clean.substring(0, 4) + '-' + clean.substring(4);
                }
                return clean;
            },

            isValidIssnFormatAndChecksum(value) {
                const clean = (value || '').trim().toUpperCase();
                if (!/^\d{4}-\d{3}[0-9X]$/.test(clean)) {
                    return { valid: false, message: 'Must match valid ISSN format (e.g., 1234-5679 or 1757-790X).' };
                }
                const digits = clean.replace('-', '');
                let sum = 0;
                for (let i = 0; i < 7; i++) {
                    sum += parseInt(digits[i], 10) * (8 - i);
                }
                const remainder = sum % 11;
                const check = 11 - remainder;
                const expected = check === 11 ? '0' : (check === 10 ? 'X' : String(check));
                if (digits[7] !== expected) {
                    return { valid: false, message: 'Invalid ISSN checksum digit (expected ' + expected + ', got ' + digits[7] + ').' };
                }
                return { valid: true, message: '' };
            },

            onIssnInput(event, field) {
                const clean = this.formatIssnString(event.target.value);
                event.target.value = clean;
                this.form[field] = clean;
                this.errors[field] = false;
                this.errorMessages[field] = '';
                if (field === 'e_issn' || field === 'p_issn') {
                    this.errors.e_issn = false;
                    this.errors.p_issn = false;
                }
                if (clean.length === 9) {
                    this.checkIssnInput(clean, field);
                }
            },

            async checkIssnInput(value, fieldType) {
                if (!value) return;
                const clean = value.trim().toUpperCase();
                if (!/^\d{4}-\d{3}[\dX]$/.test(clean)) return;
                if (this.checkedIssns[clean] || this.dismissedIssns[clean]) return;
                this.checkedIssns[clean] = true;

                try {
                    const result = await this.$wire.checkIssn(clean);
                    if (result && result.journal_title) {
                        this.foundIssnJournal = result;
                        this.showIssnFoundModal = true;
                    }
                } catch (e) {
                    console.error('Error checking ISSN:', e);
                }
            },

            confirmUseIssnJournal() {
                if (this.foundIssnJournal) {
                    this.selectExistingJournalFromDropdown(this.foundIssnJournal);
                }
                this.showIssnFoundModal = false;
                this.foundIssnJournal = null;
            },

            dismissIssnPrompt() {
                if (this.foundIssnJournal) {
                    const eIssn = (this.foundIssnJournal.e_issn || '').toUpperCase();
                    const pIssn = (this.foundIssnJournal.p_issn || '').toUpperCase();
                    if (eIssn) this.dismissedIssns[eIssn] = true;
                    if (pIssn) this.dismissedIssns[pIssn] = true;
                }
                this.showIssnFoundModal = false;
                this.foundIssnJournal = null;
            },

            openRegistrySearchModal(seed = '') {
                const titleVal = (seed || this.form.journal_title || '').trim();
                this.registrySearchInput = titleVal;
                this.showRegistrySearchModal = true;
                if (titleVal && titleVal.length >= 2) {
                    this.$wire.set('searchRegistryTitle', titleVal);
                    this.$wire.searchRegistryByTitle();
                }
            },

            closeRegistrySearchModal() {
                this.showRegistrySearchModal = false;
            },

            triggerRegistrySearch() {
                const q = (this.registrySearchInput || '').trim();
                if (q) {
                    this.$wire.set('searchRegistryTitle', q);
                    this.$wire.searchRegistryByTitle();
                }
            },

            async verifyCurrentJournal() {
                const hasE = (this.form.e_issn || '').trim() !== '';
                const hasP = (this.form.p_issn || '').trim() !== '';
                if (!hasE && !hasP) {
                    this.errors.e_issn = true;
                    this.errorMessages.e_issn = 'Please enter an e-ISSN or p-ISSN before verifying.';
                    return;
                }

                this.isVerifyingJournal = true;
                try {
                    const res = await this.$wire.verifyJournalFromForm(this.getVerificationSnapshot());
                    if (res && res.errors) {
                        for (const [k, msgs] of Object.entries(res.errors)) {
                            if (this.errors.hasOwnProperty(k) && Array.isArray(msgs) && msgs.length > 0) {
                                this.errors[k] = true;
                                this.errorMessages[k] = msgs[0];
                            }
                        }
                    }
                } finally {
                    this.isVerifyingJournal = false;
                }
            },

            openAddForm() {
                this.$dispatch('close-popovers');
                if ((this.showAddForm || this.editingId) && this.isDirty()) {
                    this.pendingAction = 'openAdd';
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();
                this.journalDropdownOpen = false;
                this.checkedIssns = {};
                this.dismissedIssns = {};
                this.$wire.resetOnlineVerification();

                this.form = {
                    id: null,
                    role: '',
                    journal_id: null,
                    journal_title: '',
                    journal_short_name: '',
                    e_issn: '',
                    p_issn: '',
                    organization_name: '',
                    journal_webiste_url: '',
                    status: 1
                };

                this.editingId = null;
                this.showAddForm = true;

                this.$nextTick(() => {
                    this.originalSnapshot = this.takeSnapshot();
                });
            },

            openEditForm(item) {
                this.$dispatch('close-popovers');
                if ((this.showAddForm || this.editingId) && this.isDirty()) {
                    this.pendingAction = { type: 'edit', item: item };
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();
                this.journalDropdownOpen = false;
                this.checkedIssns = {};
                this.dismissedIssns = {};
                this.$wire.resetOnlineVerification();

                this.form = {
                    id: item.id,
                    role: String(item.role || ''),
                    journal_id: item.journal_id || null,
                    journal_title: item.journal_title || '',
                    journal_short_name: item.journal_short_name || '',
                    e_issn: item.e_issn || '',
                    p_issn: item.p_issn || '',
                    organization_name: item.organization_name || '',
                    journal_webiste_url: item.journal_webiste_url || '',
                    status: item.status !== undefined ? parseInt(item.status) : 1
                };

                this.showAddForm = false;
                this.editingId = item.id;

                this.$nextTick(() => {
                    this.originalSnapshot = this.takeSnapshot();
                });
            },

            closeForm(force = false) {
                this.$dispatch('close-popovers');
                if (!force && this.isDirty()) {
                    this.pendingAction = 'close';
                    this.showUnsavedModal = true;
                    return;
                }
                this.showUnsavedModal = false;
                this.showAddForm = false;
                this.editingId = null;
                this.journalDropdownOpen = false;
                this.resetErrors();
                this.originalSnapshot = '';
            },

            confirmDiscard() {
                this.showUnsavedModal = false;
                const act = this.pendingAction;
                this.pendingAction = null;
                this.originalSnapshot = '';

                if (act === 'openAdd') {
                    this.closeForm(true);
                    this.openAddForm();
                } else if (act && act.type === 'edit') {
                    this.closeForm(true);
                    this.openEditForm(act.item);
                } else {
                    this.closeForm(true);
                }
            },

            cancelDiscard() {
                this.pendingAction = null;
                this.showUnsavedModal = false;
            },

            validate() {
                this.resetErrors();
                let valid = true;

                if (!this.form.role || String(this.form.role).trim() === '') {
                    this.errors.role = true;
                    this.errorMessages.role = 'Role is required.';
                    valid = false;
                }

                const titleTrim = (this.form.journal_title || '').trim();
                if (titleTrim === '') {
                    this.errors.journal_title = true;
                    this.errorMessages.journal_title = 'Journal full name is required.';
                    valid = false;
                } else if (titleTrim.length < 2) {
                    this.errors.journal_title = true;
                    this.errorMessages.journal_title = 'Journal name must be at least 2 characters.';
                    valid = false;
                }

                const eTrim = (this.form.e_issn || '').trim().toUpperCase();
                const pTrim = (this.form.p_issn || '').trim().toUpperCase();

                if (eTrim === '' && pTrim === '') {
                    this.errors.e_issn = true;
                    this.errorMessages.e_issn = 'At least one of e-ISSN or p-ISSN is required.';
                    valid = false;
                } else {
                    if (eTrim !== '') {
                        const resE = this.isValidIssnFormatAndChecksum(eTrim);
                        if (!resE.valid) {
                            this.errors.e_issn = true;
                            this.errorMessages.e_issn = resE.message;
                            valid = false;
                        }
                    }
                    if (pTrim !== '') {
                        const resP = this.isValidIssnFormatAndChecksum(pTrim);
                        if (!resP.valid) {
                            this.errors.p_issn = true;
                            this.errorMessages.p_issn = resP.message;
                            valid = false;
                        }
                    }
                    if (eTrim !== '' && pTrim !== '' && eTrim === pTrim) {
                        this.errors.p_issn = true;
                        this.errorMessages.p_issn = 'p-ISSN cannot be identical to e-ISSN.';
                        valid = false;
                    }
                }

                const urlTrim = (this.form.journal_webiste_url || '').trim();
                if (urlTrim !== '') {
                    try {
                        const parsed = new URL(urlTrim);
                        if (!['http:', 'https:'].includes(parsed.protocol)) {
                            throw new Error('Invalid protocol');
                        }
                    } catch (_) {
                        this.errors.journal_webiste_url = true;
                        this.errorMessages.journal_webiste_url = 'Please enter a valid website URL (including http:// or https://).';
                        valid = false;
                    }
                }

                return valid;
            },

            saveItem() {
                if (!this.validate()) {
                    return;
                }

                this.isSaving = true;

                const payload = {
                    id: this.form.id,
                    role: this.form.role,
                    journal_id: this.form.journal_id,
                    journal_title: this.form.journal_title,
                    journal_short_name: this.form.journal_short_name,
                    e_issn: this.form.e_issn,
                    p_issn: this.form.p_issn,
                    organization_name: this.form.organization_name,
                    journal_webiste_url: this.form.journal_webiste_url,
                    status: this.form.status
                };

                this.$wire.saveJournalRole(payload).then((res) => {
                    this.isSaving = false;
                    if (res && res.ok) {
                        this.originalSnapshot = '';
                        this.closeForm(true);
                    } else if (res && res.errors) {
                        for (const [field, msgs] of Object.entries(res.errors)) {
                            if (this.errors.hasOwnProperty(field) && Array.isArray(msgs) && msgs.length > 0) {
                                this.errors[field] = true;
                                this.errorMessages[field] = msgs[0];
                            }
                        }
                    }
                }).catch(() => {
                    this.isSaving = false;
                });
            },

            deleteItem(id) {
                if (confirm('Are you sure you want to delete this research journal role record?')) {
                    this.$wire.deleteJournalRole(id).then((res) => {
                        if (res && this.editingId === id) {
                            this.originalSnapshot = '';
                            this.closeForm(true);
                        }
                    });
                }
            }
        }"
    >
        @php $counts = $this->counts; @endphp

        {{-- Admin Section Header & Status Filter Bar --}}
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg shadow-2xs shrink-0">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <h2 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                        <span>Role in Research Journals</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200/80 dark:border-blue-800/60">
                            {{ $counts['all'] }}
                        </span>
                    </h2>
                    <p class="text-[11px] text-zinc-400 mt-0.5">Manage editorial board memberships, peer review roles, and journal leadership positions</p>
                </div>
            </div>

            {{-- Status Filter Tabs & Counts --}}
            <div class="flex items-center gap-2 flex-wrap">
                <div class="inline-flex items-center p-1 rounded-xl bg-zinc-100 dark:bg-zinc-800/80 text-[11px] font-medium">
                    <button 
                        type="button" 
                        wire:click="$set('statusFilter', 'all')"
                        :class="'{{ $statusFilter }}' === 'all' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                    >
                        All ({{ $counts['all'] }})
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('statusFilter', '1')"
                        :class="'{{ $statusFilter }}' === '1' ? 'bg-white dark:bg-zinc-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                    >
                        Active ({{ $counts['active'] }})
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('statusFilter', '0')"
                        :class="'{{ $statusFilter }}' === '0' ? 'bg-white dark:bg-zinc-900 text-red-600 dark:text-red-400 shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                    >
                        Inactive ({{ $counts['inactive'] }})
                    </button>
                </div>
            </div>
        </div>

        {{-- Admin Section Body / List --}}
        <div class="p-5 sm:p-6 sm:pt-4">
            @php $roleList = $this->journalRoleRecords; @endphp

            @if(count($roleList) === 0 && !$showAddForm)
                {{-- Empty state --}}
                <div class="text-center py-10 px-4">
                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800/80 text-zinc-400 dark:text-zinc-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-book-open text-lg"></i>
                    </div>
                    <h4 class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">No research journal role records found</h4>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1 max-w-sm mx-auto">
                        @if($statusFilter !== 'all')
                            No journal roles match the current {{ $statusFilter === '1' ? 'Active' : 'Inactive' }} filter.
                        @else
                            This user does not have any roles in research journals listed yet. Click "Add Journal Role" below to add one.
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach(array_slice($roleList, 0, $limit) as $item)
                        <div 
                            wire:key="admin-journal-role-item-{{ $item['id'] }}" 
                            class="p-4 sm:py-3.5 sm:px-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 hover:border-blue-500 dark:hover:border-blue-500 hover:border-l-4 hover:border-l-blue-500 hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                        >
                            {{-- Read-Only Display Mode --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                    {{-- Book Icon Badge --}}
                                    <div class="size-10 sm:size-11 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60 flex items-center justify-center shrink-0 shadow-2xs select-none">
                                        <i class="fa-solid fa-book-open text-base sm:text-lg"></i>
                                    </div>

                                    <div class="space-y-1 flex-1 min-w-0">
                                        {{-- Title Row: Role, 1-Click Status Pill, Short Title Badge --}}
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-white leading-snug break-words">
                                                {{ $item['role_name'] }}
                                            </h4>

                                            {{-- 1-Click Status Toggle Pill Badge --}}
                                            <button 
                                                type="button" 
                                                wire:click="toggleRoleStatus('{{ $item['id'] }}')" 
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold cursor-pointer transition-all hover:scale-105 select-none {{ ((int) $item['status'] === 1) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60' : 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-400 border border-red-200 dark:border-red-800/60' }}"
                                                title="Click to toggle status (Active / Inactive)"
                                            >
                                                <span class="size-1.5 rounded-full {{ ((int) $item['status'] === 1) ? 'bg-emerald-500 animate-pulse' : 'bg-red-500' }}"></span>
                                                <span>{{ ((int) $item['status'] === 1) ? 'Active' : 'Inactive' }}</span>
                                            </button>

                                            @if(!empty($item['journal_short_name']))
                                                <span class="px-2 py-0.5 rounded-md text-[10.5px] font-mono font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                                    {{ $item['journal_short_name'] }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Row 1: Journal Name & ID --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            <span class="inline-flex items-center gap-1.5 text-zinc-800 dark:text-zinc-200">
                                                <i class="fa-solid fa-newspaper text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Journal:</strong> {{ $item['journal_title'] ?: 'Not Specified' }}</span>
                                            </span>

                                            <span class="text-zinc-300 dark:text-zinc-600 select-none">Â·</span>
                                            <span class="text-[10.5px] font-mono text-zinc-400 select-all" title="Role Record MongoDB Object ID">
                                                ID: {{ $item['id'] }}
                                            </span>
                                        </div>

                                        {{-- Row 2: e-ISSN, p-ISSN, Publisher Name & Journal URL --}}
                                        @if(!empty($item['e_issn']) || !empty($item['p_issn']) || !empty($item['organization_name']) || !empty($item['journal_webiste_url']))
                                            <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                                @php $hasPrevMeta = false; @endphp

                                                @if(!empty($item['e_issn']))
                                                    <span class="inline-flex items-center gap-1 text-zinc-700 dark:text-zinc-300 font-mono text-[11px]">
                                                        <strong class="font-sans font-semibold text-zinc-700 dark:text-zinc-300">e-ISSN:</strong> {{ $item['e_issn'] }}
                                                    </span>
                                                    @php $hasPrevMeta = true; @endphp
                                                @endif

                                                @if(!empty($item['p_issn']))
                                                    @if($hasPrevMeta)<span class="text-zinc-300 dark:text-zinc-600 select-none">Â·</span>@endif
                                                    <span class="inline-flex items-center gap-1 text-zinc-700 dark:text-zinc-300 font-mono text-[11px]">
                                                        <strong class="font-sans font-semibold text-zinc-700 dark:text-zinc-300">p-ISSN:</strong> {{ $item['p_issn'] }}
                                                    </span>
                                                    @php $hasPrevMeta = true; @endphp
                                                @endif

                                                @if(!empty($item['organization_name']))
                                                    @if($hasPrevMeta)<span class="text-zinc-300 dark:text-zinc-600 select-none">Â·</span>@endif
                                                    <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                        <i class="fa-solid fa-building text-[11px] text-zinc-400"></i>
                                                        <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Publisher:</strong> {{ $item['organization_name'] }}</span>
                                                    </span>
                                                    @php $hasPrevMeta = true; @endphp
                                                @endif

                                                @if(!empty($item['journal_webiste_url']))
                                                    @if($hasPrevMeta)<span class="text-zinc-300 dark:text-zinc-600 select-none">Â·</span>@endif
                                                    @php
                                                        $url = $item['journal_webiste_url'];
                                                        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                                                            $url = 'https://' . $url;
                                                        }
                                                    @endphp
                                                    <a 
                                                        href="{{ $url }}" 
                                                        target="_blank" 
                                                        rel="noopener noreferrer" 
                                                        class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:underline font-medium text-xs"
                                                    >
                                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                                        <span>Journal URL</span>
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Edit Icon Button (Delete is inside form only) --}}
                                <div class="flex items-center gap-1 shrink-0 ml-2">
                                    <button 
                                        type="button" 
                                        x-show="editingId !== '{{ $item['id'] }}'"
                                        @click="openEditForm({{ json_encode($item) }})"
                                        class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        title="Edit Journal Role"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Inline Edit Form Container --}}
                            <div x-show="editingId === '{{ $item['id'] }}'" x-cloak class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                                <x-profile.journal-role-form 
                                    :show-status="true" 
                                    :item-id="$item['id']" 
                                    :journal-roles="$journalRoles" 
                                    :is-journal-verified-online="$isJournalVerifiedOnline"
                                    :verified-source-label="$verifiedSourceLabel"
                                    :verification-message="$verificationMessage"
                                    :verified-journal-data="$verifiedJournalData"
                                    :auto-fill-success-message="$autoFillSuccessMessage"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>

            @endif

            {{-- Inline Add Form at Bottom --}}
            <div x-show="showAddForm" x-cloak class="mt-4">
                <x-profile.journal-role-form 
                    :show-status="true" 
                    item-id="new" 
                    :journal-roles="$journalRoles" 
                    :is-journal-verified-online="$isJournalVerifiedOnline"
                    :verified-source-label="$verifiedSourceLabel"
                    :verification-message="$verificationMessage"
                    :verified-journal-data="$verifiedJournalData"
                    :auto-fill-success-message="$autoFillSuccessMessage"
                />
            </div>

            {{-- BOTTOM ADD BUTTON (Same Design as Patents & Invited Positions) --}}
            <div class="mt-6 pt-2 relative flex items-center justify-between gap-3 flex-wrap min-h-[34px]" x-show="!showAddForm">
                <button 
                    type="button" 
                    @click="openAddForm()" 
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                >
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Journal Role</span>
                </button>
                @if(count($roleList) > $limit)
                    <button 
                        type="button" 
                        wire:click="loadMore"
                        wire:loading.attr="disabled"
                        class="md:absolute md:left-1/2 md:-translate-x-1/2 inline-flex items-center gap-2 px-4 py-1.5 text-xs font-bold text-brand bg-brand/5 hover:bg-brand/10 border border-brand/20 rounded-xl transition cursor-pointer disabled:opacity-60"
                    >
                        <i wire:loading.remove wire:target="loadMore" class="fa-solid fa-chevron-down text-[10px]"></i>
                        <i wire:loading wire:target="loadMore" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Load More ({{ count($roleList) - $limit }})</span>
                    </button>
                @endif
                <span class="hidden md:block"></span>
            </div>
        </div>

        {{-- UNSAVED CHANGES CONFIRMATION MODAL (Frontend Only inside x-data) --}}
        <div 
            x-show="showUnsavedModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/60 backdrop-blur-xs"
            @keydown.escape.window="cancelDiscard()"
        >
            <div 
                @click.outside="cancelDiscard()"
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl max-w-sm w-full p-5 shadow-xl space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm text-zinc-900 dark:text-white">Unsaved Changes</h4>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">You have unsaved changes in this form. Discard changes and proceed?</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <button 
                        type="button" 
                        @click="cancelDiscard()" 
                        class="px-3.5 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white bg-zinc-100 dark:bg-zinc-800 rounded-xl transition cursor-pointer"
                    >
                        Keep Editing
                    </button>
                    <button 
                        type="button" 
                        @click="confirmDiscard()" 
                        class="px-3.5 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition cursor-pointer shadow-xs"
                    >
                        Discard Changes
                    </button>
                </div>
            </div>
        </div>

        {{-- JOURNAL VERIFICATION & LOOKUP MODALS (Inside x-data scope) --}}
        <x-profile.journal-verification-modals 
            :show-auto-fill-modal="$showAutoFillModal" 
            :auto-fill-diff="$autoFillDiff" 
            :existing-db-journal-info="$existingDbJournalInfo" 
            :verified-source-label="$verifiedSourceLabel" 
            :show-registry-search-modal="$showRegistrySearchModal"
            :search-registry-title="$searchRegistryTitle"
            :registry-title-candidates="$registryTitleCandidates"
        />

        {{-- REUSABLE ADD NEW JOURNAL POPUP MODAL (Same form as /deposit-article) --}}
        <x-modals.add-journal />

    </div>
</div>

