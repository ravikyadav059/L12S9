<?php

use App\Models\Designation;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Admin User Experience Component (Livewire Volt Child Component)
 *
 * Provides isolated experience management for the admin user edit screen.
 * Displays all records (active and inactive), allows real-time status toggling,
 * editing, adding, and deletion with error boundary protection.
 */
new class extends Component {
    /**
     * User Eloquent model instance being edited.
     */
    public User $user;

    /**
     * Error boundary flag.
     */
    public bool $hasError = false;

    /**
     * Catch and stop propagation of unhandled exceptions in the experience section.
     *
     * @param  \Throwable  $e
     * @param  callable  $stopPropagation
     * @return void
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        report($e);
        $stopPropagation();
        $this->hasError = true;
    }

    /**
     * Get experiences for the user being edited from ReviewerProfile.
     * Admin view shows both active and inactive records.
     *
     * @return list<array<string, mixed>>
     */
    public function getExperiencesProperty(): array
    {
        try {
            $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            $expIds = (array) ($reviewerProfile->experience ?? []);
            if (empty($expIds)) {
                return [];
            }

            $records = Experience::whereIn('_id', $expIds)
                ->orderBy('_id', 'desc')
                ->with(['designationRelation', 'organization'])
                ->get();

            $formatted = [];
            foreach ($records as $exp) {
                $designationTitle = $exp->designationRelation?->designation ?? 'Untitled Position';
                $orgName = $exp->organization?->organization_name ?? 'Organization';
                $desigVal = (string) ($exp->designationRelation?->_id ?? $exp->designation ?? '');
                $orgVal = (string) ($exp->organization?->_id ?? $exp->organization_id ?? '');

                // Generate acronym for fallback logo
                $words = preg_split('/[\s,\-\(\)]+/', $orgName, -1, PREG_SPLIT_NO_EMPTY);
                $acronym = '';
                foreach ($words as $w) {
                    if (ctype_alnum($w[0]) && ! in_array(strtolower($w), ['of', 'and', 'the', 'for', 'in', 'at'], true)) {
                        $acronym .= strtoupper($w[0]);
                    }
                }
                if (strlen($acronym) < 2) {
                    $acronym = strtoupper(substr($orgName, 0, 3));
                }

                $formatted[] = [
                    'id' => (string) $exp->_id,
                    'designation' => $designationTitle,
                    'designation_id' => $desigVal,
                    'organization' => $orgName,
                    'organization_id' => $orgVal,
                    'start_date' => (string) ($exp->join_date ?? ''),
                    'end_date' => (string) ($exp->end_date ?? ''),
                    'is_current' => (int) ($exp->current_organization ?? 0) === 1,
                    'description' => (string) ($exp->description ?? ''),
                    'status' => ($exp->status === 0 || $exp->status === '0' || $exp->status === false) ? 0 : 1,
                    'logo_title' => strtoupper($orgName),
                    'logo_sub' => substr($acronym, 0, 4),
                ];
            }

            return $formatted;
        } catch (\Throwable $e) {
            report($e);
            $this->hasError = true;

            return [];
        }
    }

    /**
     * Save an experience record:
     * - Case-insensitive deduplication and usage promotion for designation & organization.
     * - Atomically pushes new experience ID to ReviewerProfile->experience.
     *
     * @param  array<string, mixed>  $payload
     * @return void
     */
    public function saveExperience(array $payload): void
    {
        $designationTitle = trim((string) ($payload['designation'] ?? ''));
        $orgName = trim((string) ($payload['organization'] ?? ''));
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        $isCurrent = (bool) ($payload['is_current'] ?? false);
        $description = trim((string) ($payload['description'] ?? ''));
        $expId = ! empty($payload['id']) ? trim((string) $payload['id']) : null;
        $statusVal = isset($payload['status']) ? (($payload['status'] === 1 || $payload['status'] === '1' || $payload['status'] === true) ? 1 : 0) : 1;

        if ($designationTitle === '') {
            $this->dispatch('toast', message: 'Designation is required.', type: 'danger', position: 'bottom');

            return;
        }

        if ($orgName === '') {
            $this->dispatch('toast', message: 'Organization is required.', type: 'danger', position: 'bottom');

            return;
        }

        if ($startDate === '') {
            $this->dispatch('toast', message: 'Start date is required.', type: 'danger', position: 'bottom');

            return;
        }

        if (! $isCurrent && $endDate === '') {
            $this->dispatch('toast', message: 'End date is required for past roles.', type: 'danger', position: 'bottom');

            return;
        }

        $now = new \DateTime('first day of this month 00:00:00');
        if ($startDate !== '') {
            $parsedStart = \DateTime::createFromFormat('F, Y', $startDate) ?: \DateTime::createFromFormat('F Y', $startDate);
            if ($parsedStart && $parsedStart->setTime(0, 0) > $now) {
                $this->dispatch('toast', message: 'Start date cannot be in the future.', type: 'danger', position: 'bottom');

                return;
            }
        }

        if (! $isCurrent && $endDate !== '') {
            $parsedEnd = \DateTime::createFromFormat('F, Y', $endDate) ?: \DateTime::createFromFormat('F Y', $endDate);
            if ($parsedEnd && $parsedEnd->setTime(0, 0) > $now) {
                $this->dispatch('toast', message: 'End date cannot be in the future.', type: 'danger', position: 'bottom');

                return;
            }
        }

        if ($description !== '' && (mb_strlen($description) < 50 || mb_strlen($description) > 1500)) {
            $this->dispatch('toast', message: 'Description must be between 50 and 1,500 characters.', type: 'danger', position: 'bottom');

            return;
        }

        // Enforce: User can only have one experience marked as currently working (checks both active and inactive in admin)
        if ($isCurrent) {
            $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            $expIds = (array) ($reviewerProfile->experience ?? []);

            $existingCurrentQuery = Experience::where(function ($q) use ($expIds) {
                $q->where('user_id', (string) $this->user->_id);
                if (! empty($expIds)) {
                    $q->orWhereIn('_id', $expIds);
                }
            })->where(function ($q) {
                $q->whereIn('current_organization', [1, '1', true])
                  ->orWhere('end_date', 'regex', new \MongoDB\BSON\Regex('^present$', 'i'));
            });

            if ($expId) {
                $existingCurrentQuery->where('_id', '!=', $expId);
            }

            if ($existingCurrentQuery->exists()) {
                $this->dispatch('toast', message: 'This user already has an experience marked as currently working. Only one experience can be set as current.', type: 'danger', position: 'bottom');

                return;
            }
        }

        // Deduplicate and promote designation & organization
        $designationModel = Designation::recordUsage($designationTitle);
        $orgModel = Organization::recordOrFind($orgName);

        if ($expId) {
            $exp = Experience::findOrFail($expId);
        } else {
            $exp = new Experience();
            $exp->user_id = (string) $this->user->_id;
        }

        $exp->status = $statusVal;
        $exp->designation = (string) $designationModel->_id;
        $exp->organization_id = (string) $orgModel->_id;
        $exp->join_date = Experience::formatMonthYear($startDate);
        $exp->end_date = $isCurrent ? null : Experience::formatMonthYear($endDate);
        $exp->current_organization = $isCurrent ? 1 : 0;
        $exp->description = $description;
        $exp->save();

        // Atomically append _id to ReviewerProfile->experience if not present
        $strId = (string) $exp->_id;
        $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        if (! $profile) {
            ReviewerProfile::create([
                'user_id' => (string) $this->user->_id,
                'unique_id' => (string) ($this->user->unique_id ?? ''),
                'status' => (string) $this->user->getStatusCode(),
                'experience' => [$strId],
            ]);
        } else {
            $currentIds = (array) ($profile->experience ?? []);
            if (! in_array($strId, $currentIds, true)) {
                ReviewerProfile::where('user_id', (string) $this->user->_id)->push('experience', $strId, true);
            }
        }

        $this->dispatch('toast', message: 'Experience saved successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
    }

    /**
     * Delete an experience record and atomically remove its ID from ReviewerProfile->experience.
     *
     * @param  string  $expId
     * @return void
     */
    public function deleteExperience(string $expId): void
    {
        $exp = Experience::find($expId);
        if ($exp) {
            $exp->delete();
        }

        ReviewerProfile::where('user_id', (string) $this->user->_id)->pull('experience', $expId);

        $this->dispatch('toast', message: 'Experience deleted successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
    }

    /**
     * Toggle experience status between active (1) and inactive (0).
     *
     * @param  string  $expId
     * @return void
     */
    public function toggleExperienceStatus(string $expId): void
    {
        $exp = Experience::findOrFail($expId);
        $isActive = ($exp->status === 1 || $exp->status === '1' || $exp->status === true || (string) $exp->status === '1');
        $newStatus = $isActive ? 0 : 1;

        if ($newStatus === 1 && $exp->isCurrent()) {
            $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            $expIds = (array) ($reviewerProfile->experience ?? []);

            $alreadyActiveCurrent = Experience::where(function ($q) use ($expIds) {
                $q->where('user_id', (string) $this->user->_id);
                if (! empty($expIds)) {
                    $q->orWhereIn('_id', $expIds);
                }
            })->where('_id', '!=', $expId)
              ->whereIn('status', [1, '1', true])
              ->where(function ($q) {
                  $q->whereIn('current_organization', [1, '1', true])
                    ->orWhere('end_date', 'regex', new \MongoDB\BSON\Regex('^present$', 'i'));
              })->exists();

            if ($alreadyActiveCurrent) {
                $this->dispatch('toast', message: 'Cannot activate this experience because another active role is already marked as currently working.', type: 'danger', position: 'bottom');

                return;
            }
        }

        $exp->status = $newStatus;
        $exp->save();

        $label = $newStatus === 1 ? 'Active' : 'Inactive';
        $this->dispatch('toast', message: "Experience set to {$label}.", type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
    }

    /**
     * Set experience status directly (1 for active, 0 for inactive).
     *
     * @param  string  $expId
     * @param  int|string  $status
     * @return void
     */
    public function setExperienceStatus(string $expId, int|string $status): void
    {
        $exp = Experience::findOrFail($expId);
        $newStatus = ((int) $status === 1) ? 1 : 0;
        $exp->status = $newStatus;
        $exp->save();

        $label = $newStatus === 1 ? 'Active' : 'Inactive';
        $this->dispatch('toast', message: "Experience set to {$label}.", type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
    }
}; ?>

<div>
    @if($hasError)
    {{-- GRACEFUL ERROR FALLBACK CARD FOR ADMIN --}}
    <div id="experience-profile-card" class="bg-white dark:bg-[#111c26] rounded-2xl p-6 border border-amber-200 dark:border-amber-800/60 shadow-xs text-center mt-6">
        <div class="size-12 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-500 mx-auto flex items-center justify-center text-xl mb-3">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Unable to load Work Experience</h3>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-md mx-auto">
            Something went wrong while loading the experience records for this user. Please refresh the page or contact the system administrator.
        </p>
    </div>
@else
    @php
        $adminExperiences = !empty($this->experiences) ? $this->experiences : [];
    @endphp

    <div 
        id="experience-profile-card"
        x-data="{
            statusFilter: 'all',
            isAdding: false,
            editingId: null,
            showUnsavedModal: false,
            originalSnapshot: '',

            // Form fields
            form: {
                id: null,
                designation: '',
                organization: '',
                start_date: '',
                end_date: '',
                is_current: false,
                description: '',
                status: 1,
            },

            // Errors state
            errors: {
                designation: false,
                organization: false,
                start_date: false,
                end_date: false,
                description: false,
                descriptionMessage: '',
            },

            // Dynamic API dropdown suggestions & state
            designationOptions: [],
            organizationOptions: [],
            isSearchingDesig: false,
            isSearchingOrg: false,
            isLoadingMoreDesig: false,
            isLoadingMoreOrg: false,
            desigPage: 1,
            orgPage: 1,
            desigHasMore: true,
            orgHasMore: true,
            desigSearchQuery: '',
            orgSearchQuery: '',
            showDesignationDropdown: false,
            showOrgDropdown: false,
            desigDebounceTimer: null,
            orgDebounceTimer: null,

            // Sequence counters & Abort controllers (prevent previous results from overwriting new searches)
            desigSearchSeq: 0,
            orgSearchSeq: 0,
            desigAbortController: null,
            orgAbortController: null,

            init() {
                this.fetchDesignations('');
                this.fetchOrganizations('');
            },

            fetchDesignations(query = '', page = 1) {
                const cleanQuery = (query || '').trim();
                if (page === 1) {
                    clearTimeout(this.desigDebounceTimer);
                    this.desigPage = 1;
                    this.desigSearchQuery = query;
                    this.isSearchingDesig = true;

                    if (this.desigAbortController) {
                        this.desigAbortController.abort();
                    }
                    this.desigAbortController = new AbortController();
                    const currentSeq = ++this.desigSearchSeq;

                    fetch('/api/designations?q=' + encodeURIComponent(cleanQuery) + '&page=1&limit=20', {
                        signal: this.desigAbortController.signal
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (currentSeq !== this.desigSearchSeq) return;
                            const items = Array.isArray(data) ? data : (data.data || []);
                            this.designationOptions = items.map(item => item.name);
                            this.desigHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                            this.isSearchingDesig = false;
                        })
                        .catch(err => {
                            if (err && err.name === 'AbortError') return;
                            if (currentSeq === this.desigSearchSeq) {
                                this.isSearchingDesig = false;
                            }
                        });
                } else {
                    if (this.isLoadingMoreDesig || !this.desigHasMore) return;
                    this.isLoadingMoreDesig = true;
                    const currentSeq = this.desigSearchSeq;

                    fetch('/api/designations?q=' + encodeURIComponent((this.desigSearchQuery || '').trim()) + '&page=' + page + '&limit=20')
                        .then(res => res.json())
                        .then(data => {
                            if (currentSeq !== this.desigSearchSeq) return;
                            const items = Array.isArray(data) ? data : (data.data || []);
                            if (items.length > 0) {
                                for (const item of items) {
                                    if (!this.designationOptions.includes(item.name)) {
                                        this.designationOptions.push(item.name);
                                    }
                                }
                                this.desigHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                                this.desigPage = page;
                            } else {
                                this.desigHasMore = false;
                            }
                            this.isLoadingMoreDesig = false;
                        })
                        .catch(err => {
                            if (err && err.name === 'AbortError') return;
                            if (currentSeq === this.desigSearchSeq) {
                                this.isLoadingMoreDesig = false;
                            }
                        });
                }
            },

            fetchOrganizations(query = '', page = 1) {
                const cleanQuery = (query || '').trim();
                if (page === 1) {
                    clearTimeout(this.orgDebounceTimer);
                    this.orgPage = 1;
                    this.orgSearchQuery = query;
                    this.isSearchingOrg = true;

                    if (this.orgAbortController) {
                        this.orgAbortController.abort();
                    }
                    this.orgAbortController = new AbortController();
                    const currentSeq = ++this.orgSearchSeq;

                    fetch('/api/organizations?q=' + encodeURIComponent(cleanQuery) + '&page=1&limit=20', {
                        signal: this.orgAbortController.signal
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (currentSeq !== this.orgSearchSeq) return;
                            const items = Array.isArray(data) ? data : (data.data || []);
                            this.organizationOptions = items.map(item => item.name);
                            this.orgHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                            this.isSearchingOrg = false;
                        })
                        .catch(err => {
                            if (err && err.name === 'AbortError') return;
                            if (currentSeq === this.orgSearchSeq) {
                                this.isSearchingOrg = false;
                            }
                        });
                } else {
                    if (this.isLoadingMoreOrg || !this.orgHasMore) return;
                    this.isLoadingMoreOrg = true;
                    const currentSeq = this.orgSearchSeq;

                    fetch('/api/organizations?q=' + encodeURIComponent((this.orgSearchQuery || '').trim()) + '&page=' + page + '&limit=20')
                        .then(res => res.json())
                        .then(data => {
                            if (currentSeq !== this.orgSearchSeq) return;
                            const items = Array.isArray(data) ? data : (data.data || []);
                            if (items.length > 0) {
                                for (const item of items) {
                                    if (!this.organizationOptions.includes(item.name)) {
                                        this.organizationOptions.push(item.name);
                                    }
                                }
                                this.orgHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                                this.orgPage = page;
                            } else {
                                this.orgHasMore = false;
                            }
                            this.isLoadingMoreOrg = false;
                        })
                        .catch(err => {
                            if (err && err.name === 'AbortError') return;
                            if (currentSeq === this.orgSearchSeq) {
                                this.isLoadingMoreOrg = false;
                            }
                        });
                }
            },

            onDesignationScroll(e) {
                const el = e.target;
                if (el.scrollTop + el.clientHeight >= el.scrollHeight - 50) {
                    if (this.desigHasMore && !this.isLoadingMoreDesig && !this.isSearchingDesig) {
                        this.fetchDesignations(this.desigSearchQuery, this.desigPage + 1);
                    }
                }
            },

            onOrgScroll(e) {
                const el = e.target;
                if (el.scrollTop + el.clientHeight >= el.scrollHeight - 50) {
                    if (this.orgHasMore && !this.isLoadingMoreOrg && !this.isSearchingOrg) {
                        this.fetchOrganizations(this.orgSearchQuery, this.orgPage + 1);
                    }
                }
            },

            openDesignationDropdown() {
                this.showDesignationDropdown = !this.showDesignationDropdown;
                this.showOrgDropdown = false;
                if (this.showDesignationDropdown) {
                    this.desigSearchQuery = '';
                    if (!this.designationOptions.length) {
                        this.fetchDesignations('');
                    }
                    this.$nextTick(() => {
                        this.$refs.desigSearchInput?.focus();
                    });
                }
            },

            searchDesignations(query) {
                this.desigSearchQuery = query;
                clearTimeout(this.desigDebounceTimer);
                this.desigDebounceTimer = setTimeout(() => {
                    this.fetchDesignations(query);
                }, 500);
            },

            openOrgDropdown() {
                this.showOrgDropdown = !this.showOrgDropdown;
                this.showDesignationDropdown = false;
                if (this.showOrgDropdown) {
                    this.orgSearchQuery = '';
                    if (!this.organizationOptions.length) {
                        this.fetchOrganizations('');
                    }
                    this.$nextTick(() => {
                        this.$refs.orgSearchInput?.focus();
                    });
                }
            },

            searchOrganizations(query) {
                this.orgSearchQuery = query;
                clearTimeout(this.orgDebounceTimer);
                this.orgDebounceTimer = setTimeout(() => {
                    this.fetchOrganizations(query);
                }, 500);
            },

            clearDesignation() {
                this.form.designation = '';
                this.desigSearchQuery = '';
                this.errors.designation = false;
                this.fetchDesignations('');
            },

            clearOrganization() {
                this.form.organization = '';
                this.orgSearchQuery = '';
                this.errors.organization = false;
                this.fetchOrganizations('');
            },

            selectDesignation(val) {
                const trimmed = (val || '').trim();
                if (trimmed) {
                    this.form.designation = trimmed;
                    this.errors.designation = false;
                }
                this.showDesignationDropdown = false;
                this.desigSearchQuery = '';
                this.fetchDesignations('');
            },

            selectOrganization(val) {
                const trimmed = (val || '').trim();
                if (trimmed) {
                    this.form.organization = trimmed;
                    this.errors.organization = false;
                }
                this.showOrgDropdown = false;
                this.orgSearchQuery = '';
                this.fetchOrganizations('');
            },

            hasExactDesignationMatch() {
                const q = (this.desigSearchQuery || '').trim().toLowerCase();
                if (!q) return true;
                return this.designationOptions.some(opt => (opt || '').toLowerCase() === q);
            },

            hasExactOrgMatch() {
                const q = (this.orgSearchQuery || '').trim().toLowerCase();
                if (!q) return true;
                return this.organizationOptions.some(opt => (opt || '').toLowerCase() === q);
            },

            getSnapshot() {
                return JSON.stringify(this.form);
            },

            isFormDirty() {
                return this.getSnapshot() !== this.originalSnapshot;
            },

            resetForm() {
                this.form = {
                    id: null,
                    designation: '',
                    organization: '',
                    start_date: '',
                    end_date: '',
                    is_current: false,
                    description: '',
                    status: 1,
                };
                this.resetErrors();
                this.showDesignationDropdown = false;
                this.showOrgDropdown = false;
                this.desigSearchQuery = '';
                this.orgSearchQuery = '';
                this.fetchDesignations('');
                this.fetchOrganizations('');
                this.originalSnapshot = this.getSnapshot();
            },

            resetErrors() {
                this.errors = {
                    designation: false,
                    organization: false,
                    start_date: false,
                    end_date: false,
                    description: false,
                    descriptionMessage: '',
                };
            },

            openAddForm() {
                if (this.isAdding || this.editingId) {
                    if (this.isFormDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                }
                this.editingId = null;
                this.resetForm();
                this.isAdding = true;
                this.$nextTick(() => {
                    const el = document.getElementById('admin-experience-add-form');
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            },

            toggleEdit(item) {
                if (this.editingId === item.id) {
                    this.handleCancel();
                    return;
                }

                if (this.isAdding || this.editingId) {
                    if (this.isFormDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                }

                this.isAdding = false;
                this.resetErrors();
                this.editingId = item.id;

                const s = item.status;
                const itemStatus = (s === 0 || s === '0' || s === false || s === 'false') ? 0 : 1;

                this.form = {
                    id: item.id,
                    designation: item.designation || '',
                    organization: item.organization || '',
                    start_date: item.start_date || '',
                    end_date: item.is_current ? '' : (item.end_date || ''),
                    is_current: !!item.is_current,
                    description: item.description || '',
                    status: itemStatus,
                };
                this.originalSnapshot = this.getSnapshot();
            },

            handleCancel() {
                if (this.isFormDirty()) {
                    this.showUnsavedModal = true;
                } else {
                    this.closeForm();
                }
            },

            confirmDiscard() {
                this.showUnsavedModal = false;
                this.closeForm();
            },

            keepEditing() {
                this.showUnsavedModal = false;
            },

            closeForm() {
                this.isAdding = false;
                this.editingId = null;
                this.resetForm();
            },

            validateForm() {
                this.resetErrors();
                let hasError = false;

                if (!this.form.designation.trim()) {
                    this.errors.designation = true;
                    hasError = true;
                }

                if (!this.form.organization.trim()) {
                    this.errors.organization = true;
                    hasError = true;
                }

                const parseMonthYear = (val) => {
                    if (!val) return null;
                    const parts = val.replace(',', '').trim().split(/\s+/);
                    if (parts.length >= 2) {
                        const mIdx = ['january','february','march','april','may','june','july','august','september','october','november','december'].indexOf(parts[0].toLowerCase());
                        const y = parseInt(parts[1], 10);
                        if (mIdx !== -1 && !isNaN(y)) return new Date(y, mIdx, 1);
                    }
                    return null;
                };

                const now = new Date();
                const curMonthStart = new Date(now.getFullYear(), now.getMonth(), 1);

                if (!this.form.start_date.trim()) {
                    this.errors.start_date = true;
                    hasError = true;
                } else {
                    const startDateObj = parseMonthYear(this.form.start_date);
                    if (startDateObj && startDateObj > curMonthStart) {
                        this.errors.start_date = true;
                        this.triggerToast('Start date cannot be in the future', 'warning');
                        hasError = true;
                    }
                }

                if (!this.form.is_current) {
                    if (!this.form.end_date.trim()) {
                        this.errors.end_date = true;
                        hasError = true;
                    } else {
                        const endDateObj = parseMonthYear(this.form.end_date);
                        if (endDateObj && endDateObj > curMonthStart) {
                            this.errors.end_date = true;
                            this.triggerToast('End date cannot be in the future', 'warning');
                            hasError = true;
                        } else {
                            const startDateObj = parseMonthYear(this.form.start_date);
                            if (startDateObj && endDateObj && endDateObj < startDateObj) {
                                this.errors.end_date = true;
                                this.triggerToast('End date cannot be before start date', 'warning');
                                hasError = true;
                            }
                        }
                    }
                }

                const desc = (this.form.description || '').trim();
                if (desc.length > 0 && desc.length < 50) {
                    this.errors.description = true;
                    this.errors.descriptionMessage = 'Please write at least 50 characters';
                    hasError = true;
                } else if (desc.length > 1500) {
                    this.errors.description = true;
                    this.errors.descriptionMessage = 'Description must not exceed 1500 characters';
                    hasError = true;
                }

                if (this.form.current_role) {
                    const existingList = @js($adminExperiences);
                    const hasOtherCurrent = existingList.some(e => String(e.id) !== String(this.form.id) && Boolean(e.is_current));
                    if (hasOtherCurrent) {
                        this.triggerToast('This user already has an experience marked as currently working. Only one experience can be set as current.', 'danger');
                        return false;
                    }
                }

                if (hasError) {
                    this.triggerToast('Please fill all required fields', 'warning');
                }

                return !hasError;
            },

            submitForm() {
                if (!this.validateForm()) {
                    return;
                }

                $wire.saveExperience(this.form).then(() => {
                    this.closeForm();
                });
            },

            deleteItem(id) {
                $wire.deleteExperience(id).then(() => {
                    if (this.editingId === id) {
                        this.closeForm();
                    }
                });
            },

            triggerToast(msg, type = 'warning') {
                window.dispatchEvent(new CustomEvent('toast', { 
                    detail: { 
                        message: msg, 
                        type: type, 
                        position: 'bottom' 
                    } 
                }));
            }
        }"
        class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden mt-6 scroll-mt-24"
    >
        <!-- CARD HEADER -->
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between bg-white dark:bg-[#111c26] flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-brand border border-blue-100 dark:border-blue-900/40 flex items-center justify-center font-bold text-base shadow-2xs">
                    <i class="fa-solid fa-briefcase text-[15px]"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold font-space text-zinc-900 dark:text-white flex items-center gap-2">
                        <span>Work Experience</span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                            {{ count($adminExperiences) }}
                        </span>
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Manage user's employment history, designations, and organizational affiliations.
                    </p>
                </div>
            </div>

            <!-- STATUS FILTER SELECT OPPOSITE TITLE -->
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Status:</label>
                <div class="relative">
                    <select 
                        x-model="statusFilter"
                        class="text-xs font-bold py-1.5 pl-3 pr-8 rounded-xl bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-brand cursor-pointer transition shadow-2xs"
                    >
                        <option value="all">All ({{ count($adminExperiences) }})</option>
                        <option value="1">Active Only ({{ count(array_filter($adminExperiences, fn($e) => ($e['status'] === 1 || $e['status'] === '1')) ) }})</option>
                        <option value="0">Inactive Only ({{ count(array_filter($adminExperiences, fn($e) => ($e['status'] === 0 || $e['status'] === '0')) ) }})</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- CARD BODY -->
        <div class="p-6">
            
            <div class="relative">
                
                {{-- Continuous Left Vertical Line --}}
                @if(count($adminExperiences) > 0)
                    <div 
                        class="absolute left-[7.5px] top-2 bottom-2 w-[3px] rounded-[2px] pointer-events-none"
                        style="background: linear-gradient(to bottom, var(--color-brand), var(--color-brand-50));"
                    ></div>
                @endif

                <div class="space-y-7">
                    @forelse($adminExperiences as $exp)
                        <div 
                            class="relative pl-8" 
                            wire:key="exp-admin-{{ $exp['id'] }}"
                            x-show="statusFilter === 'all' || statusFilter == '{{ $exp['status'] }}'"
                        >
                            
                            {{-- Timeline Dot --}}
                            <div 
                                class="absolute left-0 top-0 size-[18px] rounded-full bg-brand border-[3.5px] border-white dark:border-[#111c26] z-10 shrink-0"
                                style="box-shadow: 0 0 0 2.5px var(--color-brand-50), 0 2px 8px color-mix(in srgb, var(--color-brand) 35%, transparent);"
                            ></div>

                            {{-- Item Row --}}
                            <div class="flex items-start justify-between gap-4">
                                
                                {{-- Logo + Info Group --}}
                                <div class="flex items-start gap-4 min-w-0 flex-1">
                                    
                                    {{-- Logo Box --}}
                                    <div class="size-[74px] rounded-2xl border-[2px] border-blue-400 dark:border-blue-500/50 bg-white dark:bg-zinc-800 flex flex-col items-center justify-center p-2 shrink-0 shadow-2xs overflow-hidden select-none">
                                        @if(!empty($exp['logo_title']))
                                            <span class="text-[8.5px] font-extrabold uppercase leading-tight text-center text-zinc-700 dark:text-zinc-200">{{ $exp['logo_title'] }}</span>
                                        @endif
                                        <span class="text-[17px] font-black tracking-tight text-[#111827] dark:text-white uppercase">{{ $exp['logo_sub'] }}</span>
                                    </div>

                                    {{-- Details --}}
                                    <div class="min-w-0 flex-1 space-y-1 pt-0.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="text-[15px] font-bold text-[#111827] dark:text-white leading-snug truncate">{{ $exp['designation'] }}</h3>
                                            
                                            {{-- Database ID with select-all --}}
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Database ID (Click to select)">
                                                <span class="text-zinc-400 select-none">ID:</span>
                                                <span>{{ $exp['id'] }}</span>
                                            </span>
                                        </div>
                                        
                                        <p class="text-[13px] text-zinc-600 dark:text-zinc-400 font-normal leading-snug">
                                            <span class="font-semibold text-brand">{{ $exp['organization'] }}</span>
                                            <span class="mx-1">·</span>
                                            <span>{{ $exp['start_date'] }} - {{ $exp['is_current'] ? 'Present' : $exp['end_date'] }}</span>
                                        </p>

                                        @if(!empty($exp['description']))
                                            <div 
                                                x-data="{ 
                                                    expanded: false, 
                                                    isClamped: {{ mb_strlen(trim($exp['description'])) > 140 ? 'true' : 'false' }},
                                                    checkClamped() {
                                                        if (this.$refs.descContainer) {
                                                            this.isClamped = this.$refs.descContainer.scrollHeight > this.$refs.descContainer.clientHeight + 2;
                                                        }
                                                    }
                                                }" 
                                                x-init="
                                                    $nextTick(() => checkClamped());
                                                    window.addEventListener('resize', () => { if (!expanded) checkClamped(); });
                                                " 
                                                class="mt-1"
                                            >
                                                <div 
                                                    x-ref="descContainer" 
                                                    :class="{ 'line-clamp-2': !expanded, 'whitespace-pre-line': expanded }" 
                                                    class="text-[13px] text-zinc-600 dark:text-zinc-400 leading-snug break-words"
                                                ><strong class="font-semibold text-zinc-800 dark:text-zinc-200">Description:</strong> {{ trim($exp['description']) }}</div>
                                                <button 
                                                    type="button" 
                                                    x-show="isClamped || expanded" 
                                                    x-cloak 
                                                    @click="expanded = !expanded; if (!expanded) $nextTick(() => checkClamped())" 
                                                    class="text-[12px] font-semibold text-brand hover:underline mt-0.5 inline-flex items-center gap-1 cursor-pointer transition-colors"
                                                >
                                                    <span x-text="expanded ? 'View Less' : 'View More'"></span>
                                                    <i class="fa-solid text-[9px]" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Action Group: Active/Inactive Toggle Badge + Edit Icon Button --}}
                                <div class="flex items-center gap-2.5 shrink-0 pt-0.5">
                                    <button 
                                        type="button"
                                        @click.stop="$wire.toggleExperienceStatus('{{ $exp['id'] }}')"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ ($exp['status'] === 1 || $exp['status'] === '1') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/50' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200 dark:hover:bg-zinc-700' }} transition select-none shadow-2xs cursor-pointer"
                                        title="Click to toggle status"
                                    >
                                        <span class="size-1.5 rounded-full {{ ($exp['status'] === 1 || $exp['status'] === '1') ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                        <span>{{ ($exp['status'] === 1 || $exp['status'] === '1') ? 'Active' : 'Inactive' }}</span>
                                    </button>

                                    {{-- Edit Icon Button --}}
                                    <button 
                                        type="button" 
                                        x-show="editingId !== '{{ $exp['id'] }}'"
                                        @click="toggleEdit(@js($exp))"
                                        class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        title="Edit Experience"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                    </button>
                                </div>

                            </div>

                            {{-- Inline Edit Form --}}
                            <div 
                                x-show="editingId === '{{ $exp['id'] }}'" 
                                x-cloak
                                x-transition:enter="ease-out duration-200" 
                                x-transition:enter-start="opacity-0 -translate-y-2" 
                                x-transition:enter-end="opacity-100 translate-y-0"
                            >
                                <x-profile.experience-form :show-status="true" :item-id="$exp['id']" />
                            </div>

                        </div>
                    @empty
                        <p class="text-xs text-zinc-700 dark:text-zinc-300 py-1 font-normal">
                            No experience found.
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Add Experience Form --}}
            <div 
                id="admin-experience-add-form"
                x-show="isAdding" 
                x-cloak
                x-transition:enter="ease-out duration-200" 
                x-transition:enter-start="opacity-0 -translate-y-2" 
                x-transition:enter-end="opacity-100 translate-y-0"
            >
                <x-profile.experience-form :show-status="true" item-id="new" />
            </div>

            {{-- Add Experience Button --}}
            <div class="mt-6 pt-2" x-show="!isAdding">
                <button 
                    type="button" 
                    @click="openAddForm()" 
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                >
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Experience</span>
                </button>
            </div>

        </div>

        {{-- Unsaved Changes Confirmation Modal --}}
        <x-profile.unsaved-modal />
    </div>
@endif
</div>
