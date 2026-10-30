<?php

use App\Models\Designation;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Profile Experience Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's work experience timeline, inline add/edit modal,
 * and experience deletion logic into an independent child component.
 *
 * Isolated error boundary: Catches and reports exceptions to ensure a failure here
 * never crashes the main user profile.
 */
new class extends Component {
    /**
     * User Eloquent model instance whose profile is being viewed.
     */
    public ?User $user = null;

    /**
     * Flag indicating whether current authenticated user owns this profile.
     */
    public bool $isOwnProfile = true;

    /**
     * Error boundary flag: Set to true if an unexpected error occurs.
     */
    public bool $hasError = false;

    /**
     * Catch and stop propagation of any unexpected runtime errors
     * to keep other sections of the profile fully operational.
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
     * Get active experiences for this user profile.
     * Scholar/user view ONLY displays records with status 1.
     *
     * @return list<array<string, mixed>>
     */
    public function getExperiencesProperty(): array
    {
        try {
            $reviewerProfile = $this->user?->reviewerProfile ?? ($this->user ? ReviewerProfile::where('user_id', (string) $this->user->_id)->first() : null);
            $expIds = (array) ($reviewerProfile->experience ?? []);
            if (empty($expIds)) {
                return [];
            }

            // ONLY active records (status = 1 or '1')
            $records = Experience::whereIn('_id', $expIds)
                ->whereIn('status', [1, '1'])
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
                    'status' => (string) ($exp->status ?? '1'),
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
     * - Verifies authorization
     * - Deduplicates and records designation and organization usage
     * - Creates or updates the Experience model
     * - Atomically pushes the new ID to ReviewerProfile->experience
     *
     * @param  array<string, mixed>  $payload
     * @return void
     */
    public function saveExperience(array $payload): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            $this->dispatch('toast', message: 'Unauthorized.', type: 'danger', position: 'bottom');

            return false;
        }

        $designationTitle = trim((string) ($payload['designation'] ?? ''));
        $orgName = trim((string) ($payload['organization'] ?? ''));
        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        $isCurrent = (bool) ($payload['is_current'] ?? false);
        $description = trim((string) ($payload['description'] ?? ''));
        $expId = ! empty($payload['id']) ? trim((string) $payload['id']) : null;

        if ($designationTitle === '' || $orgName === '' || $startDate === '') {
            $this->dispatch('toast', message: 'Please fill all required fields.', type: 'danger', position: 'bottom');

            return false;
        }

        if (! $isCurrent && $endDate === '') {
            $this->dispatch('toast', message: 'End date is required for past roles.', type: 'danger', position: 'bottom');

            return false;
        }

        $now = new \DateTime('first day of this month 00:00:00');
        if ($startDate !== '') {
            $parsedStart = \DateTime::createFromFormat('F, Y', $startDate) ?: \DateTime::createFromFormat('F Y', $startDate);
            if ($parsedStart && $parsedStart->setTime(0, 0) > $now) {
                $this->dispatch('alert-modal', title: 'Invalid Start Date', message: 'Start date cannot be in the future.', type: 'warning');

                return false;
            }
        }

        if (! $isCurrent && $endDate !== '') {
            $parsedEnd = \DateTime::createFromFormat('F, Y', $endDate) ?: \DateTime::createFromFormat('F Y', $endDate);
            if ($parsedEnd && $parsedEnd->setTime(0, 0) > $now) {
                $this->dispatch('alert-modal', title: 'Invalid End Date', message: 'End date cannot be in the future.', type: 'warning');

                return false;
            }
        }

        if ($description !== '' && (mb_strlen($description) < 50 || mb_strlen($description) > 1500)) {
            $this->dispatch('toast', message: 'Description must be between 50 and 1,500 characters.', type: 'danger', position: 'bottom');

            return false;
        }

        // Enforce: User can only have one active experience marked as currently working
        if ($isCurrent) {
            $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            $expIds = (array) ($reviewerProfile->experience ?? []);

            $existingCurrentQuery = Experience::where(function ($q) use ($expIds) {
                $q->where('user_id', (string) $this->user->_id);
                if (! empty($expIds)) {
                    $q->orWhereIn('_id', $expIds);
                }
            })->whereIn('status', [1, '1', true])
              ->where(function ($q) {
                  $q->whereIn('current_organization', [1, '1', true])
                    ->orWhere('end_date', 'regex', new \MongoDB\BSON\Regex('^present$', 'i'));
              });

            if ($expId) {
                $existingCurrentQuery->where('_id', '!=', $expId);
            }

            if ($existingCurrentQuery->exists()) {
                $this->dispatch('alert-modal', title: 'Currently Working Role Conflict', message: 'You can only have one experience marked as currently working. Please update your existing current role first.', type: 'warning');

                return false;
            }
        }

        $startMonthVal = Experience::parseMonthYearToValue($startDate);
        $endMonthVal = $isCurrent ? PHP_INT_MAX : Experience::parseMonthYearToValue($endDate);

        if (! $isCurrent && $startMonthVal && $endMonthVal && $startMonthVal > $endMonthVal) {
            $this->dispatch('alert-modal', title: 'Invalid Date Range', message: 'Start date cannot be after end date.', type: 'warning');

            return false;
        }

        // Date overlap validation (active experiences only, allowing same-month transitions)
        if ($startMonthVal && $endMonthVal) {
            $overlappingExp = Experience::findOverlappingActiveExperience($this->user, $startMonthVal, $endMonthVal, $expId);
            if ($overlappingExp) {
                $conflictOrg = $overlappingExp->organization?->organization_name ?? 'another organization';
                $conflictDesig = $overlappingExp->designationRelation?->designation ?? 'Role';
                $conflictRange = ($overlappingExp->join_date ?? '') . ' - ' . ($overlappingExp->isCurrent() ? 'Present' : ($overlappingExp->end_date ?? ''));
                $this->dispatch('alert-modal', title: 'Work Experience Date Overlap', message: "This role dates overlap with your existing role: '{$conflictDesig}' at {$conflictOrg} ({$conflictRange}). A user cannot work at two places simultaneously.", type: 'warning');

                return false;
            }
        }

        $designationModel = Designation::recordUsage($designationTitle);
        $orgModel = Organization::recordOrFind($orgName);

        if ($expId) {
            $exp = Experience::findOrFail($expId);
        } else {
            $exp = new Experience();
            $exp->user_id = (string) $this->user->_id;
            $exp->status = 1;
        }

        $exp->designation = (string) $designationModel->_id;
        $exp->organization_id = (string) $orgModel->_id;
        $exp->join_date = Experience::formatMonthYear($startDate);
        $exp->end_date = $isCurrent ? null : Experience::formatMonthYear($endDate);
        $exp->current_organization = $isCurrent ? 1 : 0;
        $exp->description = $description;
        $exp->save();

        $strId = (string) $exp->_id;
        $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        if (! $profile) {
            $profile = ReviewerProfile::create([
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

        return true;
    }

    /**
     * Delete an experience record and atomically pull its ID from ReviewerProfile.
     *
     * @param  string  $expId
     * @return void
     */
    public function deleteExperience(string $expId): void
    {
        if (! $this->isOwnProfile || ! $this->user) {
            $this->dispatch('toast', message: 'Unauthorized.', type: 'danger', position: 'bottom');

            return;
        }

        $exp = Experience::find($expId);
        if ($exp) {
            $exp->delete();
        }

        ReviewerProfile::where('user_id', (string) $this->user->_id)->pull('experience', $expId);

        $this->dispatch('toast', message: 'Experience deleted successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-experiences');
    }
}; ?>

<div>
    @if($hasError)
    {{-- GRACEFUL ERROR FALLBACK CARD --}}
    <div id="experience" class="scroll-mt-20 mt-6 bg-white dark:bg-[#111c26] border border-amber-200 dark:border-amber-800/60 rounded-2xl p-6 text-center shadow-xs">
        <div class="size-12 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-500 mx-auto flex items-center justify-center text-xl mb-3">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Unable to load Experience details</h3>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-md mx-auto">
            Something went wrong while loading this section. Please refresh the page or <a href="#" class="text-brand font-semibold hover:underline">contact our support team</a> if the problem continues.
        </p>
    </div>
@else
    @php
        $userExperiences = $this->experiences;
    @endphp

    <div 
        x-data="{
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
                    this.isSearchingDesig = true;
                    this.desigPage = 1;
                    this.desigSearchQuery = query;

                    if (this.desigAbortController) {
                        this.desigAbortController.abort();
                    }
                    this.desigAbortController = new AbortController();
                    const currentSeq = ++this.desigSearchSeq;

                    fetch(`/api/designations?q=${encodeURIComponent(cleanQuery)}&page=1&limit=20`, {
                        signal: this.desigAbortController.signal
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (currentSeq !== this.desigSearchSeq) return;
                            const items = Array.isArray(data) ? data : (data.data || []);
                            this.designationOptions = items.map(d => d.name);
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

                    fetch(`/api/designations?q=${encodeURIComponent((this.desigSearchQuery || '').trim())}&page=${page}&limit=20`)
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
                    this.isSearchingOrg = true;
                    this.orgPage = 1;
                    this.orgSearchQuery = query;

                    if (this.orgAbortController) {
                        this.orgAbortController.abort();
                    }
                    this.orgAbortController = new AbortController();
                    const currentSeq = ++this.orgSearchSeq;

                    fetch(`/api/organizations?q=${encodeURIComponent(cleanQuery)}&page=1&limit=20`, {
                        signal: this.orgAbortController.signal
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (currentSeq !== this.orgSearchSeq) return;
                            const items = Array.isArray(data) ? data : (data.data || []);
                            this.organizationOptions = items.map(o => o.name);
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

                    fetch(`/api/organizations?q=${encodeURIComponent((this.orgSearchQuery || '').trim())}&page=${page}&limit=20`)
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
                    const el = document.getElementById('experience-add-form');
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
                this.form = {
                    id: item.id,
                    designation: item.designation || '',
                    organization: item.organization || '',
                    start_date: item.start_date || '',
                    end_date: item.is_current ? '' : (item.end_date || ''),
                    is_current: !!item.is_current,
                    description: item.description || '',
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
                        this.triggerAlertModal('Invalid Start Date', 'Start date cannot be in the future.');
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
                            this.triggerAlertModal('Invalid End Date', 'End date cannot be in the future.');
                            hasError = true;
                        } else {
                            const startDateObj = parseMonthYear(this.form.start_date);
                            if (startDateObj && endDateObj && endDateObj < startDateObj) {
                                this.errors.end_date = true;
                                this.triggerAlertModal('Invalid Date Range', 'End date cannot be before start date.');
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

                if (this.form.is_current) {
                    const existingList = @js($userExperiences);
                    const hasOtherCurrent = existingList.some(e => String(e.id) !== String(this.form.id) && Boolean(e.is_current) && (e.status == 1 || e.status === '1' || e.status === true));
                    if (hasOtherCurrent) {
                        this.triggerAlertModal('Currently Working Role Conflict', 'You can only have one experience marked as currently working. Please update your existing current role first.');
                        return false;
                    }
                }

                // Date overlap validation (active experiences only, allowing same-month transitions)
                const monthVal = (val) => {
                    if (!val) return null;
                    const parts = val.replace(',', '').trim().split(/\s+/);
                    if (parts.length >= 2) {
                        const mIdx = ['january','february','march','april','may','june','july','august','september','october','november','december'].indexOf(parts[0].toLowerCase());
                        const y = parseInt(parts[1], 10);
                        if (mIdx !== -1 && !isNaN(y)) return (y * 12) + (mIdx + 1);
                    }
                    return null;
                };

                const startMonth = monthVal(this.form.start_date);
                const endMonth = this.form.is_current ? 999999 : monthVal(this.form.end_date);

                if (startMonth && endMonth) {
                    const existingList = @js($userExperiences);
                    for (const exp of existingList) {
                        if (String(exp.id) === String(this.form.id)) continue;
                        if (exp.status != 1 && exp.status !== '1' && exp.status !== true) continue;

                        const expStart = monthVal(exp.start_date);
                        const expEnd = exp.is_current ? 999999 : monthVal(exp.end_date);
                        if (!expStart || !expEnd) continue;

                        const startOverlap = Math.max(startMonth, expStart);
                        const endOverlap = Math.min(endMonth, expEnd);

                        if (startOverlap <= endOverlap) {
                            if (startOverlap < endOverlap) {
                                const conflictOrg = exp.organization || 'another organization';
                                const conflictDesig = exp.designation || 'Role';
                                const conflictRange = (exp.start_date || '') + ' - ' + (exp.is_current ? 'Present' : (exp.end_date || ''));
                                this.triggerAlertModal('Work Experience Date Overlap', `This role dates overlap with your existing role: '${conflictDesig}' at ${conflictOrg} (${conflictRange}). A user cannot work in two places simultaneously.`);
                                return false;
                            } else {
                                const m = startOverlap;
                                const isValidTransition = (endMonth === m && expStart === m) || (expEnd === m && startMonth === m);
                                if (!isValidTransition) {
                                    const conflictOrg = exp.organization || 'another organization';
                                    const conflictDesig = exp.designation || 'Role';
                                    const conflictRange = (exp.start_date || '') + ' - ' + (exp.is_current ? 'Present' : (exp.end_date || ''));
                                    this.triggerAlertModal('Work Experience Date Overlap', `This role dates overlap with your existing role: '${conflictDesig}' at ${conflictOrg} (${conflictRange}). A user cannot work in two places simultaneously.`);
                                    return false;
                                }
                            }
                        }
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

                $wire.saveExperience(this.form).then((res) => {
                    if (res !== false) {
                        this.closeForm();
                    }
                });
            },

            deleteItem(id) {
                $wire.deleteExperience(id).then(() => {
                    if (this.editingId === id) {
                        this.closeForm();
                    }
                });
            },

            triggerAlertModal(title, msg, type = 'warning') {
                window.dispatchEvent(new CustomEvent('alert-modal', { 
                    detail: { 
                        title: title, 
                        message: msg, 
                        type: type,
                        confirmText: 'OK',
                        cancelText: 'Cancel'
                    } 
                }));
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
        class="scroll-mt-20 mt-6"
        id="experience"
    >
        <section class="bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 rounded-2xl p-5 shadow-xs hover:shadow-md transition-all duration-300">
            
            <!-- CARD HEADER -->
            <div class="flex items-center justify-between pb-3 mb-5 border-b-2 border-[#f9fafb] dark:border-zinc-800">
                <h2 class="text-base font-semibold font-space text-[#111827] dark:text-white flex items-center gap-2.5 m-0 leading-snug">
                    <i class="fa-solid fa-briefcase text-base text-[#793c4e] dark:text-[#a855f7]"></i>
                    <span>Experience</span>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                        {{ count($userExperiences) }}
                    </span>
                </h2>
            </div>

            <!-- CARD BODY -->
            <div>
                <div class="relative">
                
                {{-- Continuous Left Vertical Line --}}
                @if(count($userExperiences) > 0)
                    <div 
                        class="absolute left-[7.5px] top-2 bottom-2 w-[3px] rounded-[2px] pointer-events-none"
                        style="background: linear-gradient(to bottom, var(--color-brand), var(--color-brand-50));"
                    ></div>
                @endif

                <div class="space-y-7">
                    @forelse($userExperiences as $exp)
                        <div class="relative pl-8" wire:key="exp-user-{{ $exp['id'] }}">
                            
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
                                    <div class="min-w-0 flex-1 space-y-0.5 pt-0.5">
                                        <h3 class="text-[15px] font-bold text-[#111827] dark:text-white leading-snug truncate">{{ $exp['designation'] }}</h3>
                                        
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

                                {{-- Edit Icon Button --}}
                                @if($isOwnProfile)
                                    <button 
                                        type="button" 
                                        x-show="editingId !== '{{ $exp['id'] }}'"
                                        @click="toggleEdit(@js($exp))"
                                        class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        title="Edit Experience"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                    </button>
                                @endif

                            </div>

                            {{-- Inline Edit Form --}}
                            @if($isOwnProfile)
                                <div 
                                    x-show="editingId === '{{ $exp['id'] }}'" 
                                    x-cloak
                                    x-transition:enter="ease-out duration-200" 
                                    x-transition:enter-start="opacity-0 -translate-y-2" 
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    <x-profile.experience-form />
                                </div>
                            @endif

                        </div>
                    @empty
                        <p class="text-xs text-zinc-700 dark:text-zinc-300 py-1 font-normal">
                            No experience found.
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Add Experience Form --}}
            @if($isOwnProfile)
                <div 
                    id="experience-add-form"
                    x-show="isAdding" 
                    x-cloak
                    x-transition:enter="ease-out duration-200" 
                    x-transition:enter-start="opacity-0 -translate-y-2" 
                    x-transition:enter-end="opacity-100 translate-y-0"
                >
                    <x-profile.experience-form />
                </div>
            @endif

            {{-- Bottom + Add Experience Button --}}
            @if($isOwnProfile)
                <div class="mt-6 pt-2" x-show="!isAdding">
                    <button 
                        type="button" 
                        @click="openAddForm()" 
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline transition-colors cursor-pointer select-none"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Experience</span>
                    </button>
                </div>
            @endif

        </section>

        {{-- Unsaved Changes Confirmation Modal --}}
        @if($isOwnProfile)
            <x-profile.unsaved-modal />
        @endif
    </div>
@endif
</div>