<?php

use App\Models\DegreeType;
use App\Models\Education;
use App\Models\FieldOfStudy;
use App\Models\Grade;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Profile Education Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's educational timeline, inline add/edit modal,
 * and education deletion logic into an independent child component.
 */
new class extends Component {
    /**
     * User Eloquent model instance being viewed or edited.
     */
    public ?User $user = null;

    /**
     * Flag indicating whether current authenticated user owns this profile.
     */
    public bool $isOwnProfile = false;

    /**
     * Error boundary flag.
     */
    public bool $hasError = false;

    /**
     * Catch and stop propagation of unhandled exceptions in the education section.
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
     * Get active educations for this user profile.
     * Public profile displays only active (status: 1) records.
     *
     * @return list<array<string, mixed>>
     */
    public function getEducationsProperty(): array
    {
        try {
            $reviewerProfile = $this->user?->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user?->_id)->first();
            $eduIds = (array) ($reviewerProfile->education ?? []);
            if (empty($eduIds)) {
                return [];
            }

            $records = Education::whereIn('_id', $eduIds)
                ->whereIn('status', [1, '1', true])
                ->orderBy('_id', 'desc')
                ->with(['degreeType', 'fieldOfStudyRelation', 'gradeRelation', 'institution'])
                ->get();

            $formatted = [];
            foreach ($records as $edu) {
                // Institution Name
                $institutionName = $edu->institution?->organization_name ?? (string) ($edu->institution_name ?? 'Institution');
                $instId = (string) ($edu->institution?->_id ?? $edu->institution_id ?? '');

                // Degree Type
                $degreeName = $edu->degreeType?->degree_type ?? (string) ($edu->degree_type ?? 'Degree');
                $degreeId = (string) ($edu->degreeType?->_id ?? $edu->degree_type ?? '');

                // Field of Study
                $fieldName = $edu->fieldOfStudyRelation?->field_of_study ?? (string) ($edu->field_of_study ?? '');
                $fieldId = (string) ($edu->fieldOfStudyRelation?->_id ?? $edu->field_of_study ?? '');

                // Grade
                $gradeName = $edu->gradeRelation?->grade ?? (string) ($edu->grade ?? '');
                $gradeId = (string) ($edu->gradeRelation?->_id ?? $edu->grade ?? '');

                // Passout Year & Pursuing (supports pursuing, current_institute = 1 or "1")
                $isPursuing = $edu->isPursuing();
                $passoutYear = (string) ($edu->passout_year ?? '');

                // Acronym for fallback logo
                $words = preg_split('/[\s,\-\(\)]+/', $institutionName, -1, PREG_SPLIT_NO_EMPTY);
                $acronym = '';
                foreach ($words as $w) {
                    if (ctype_alnum($w[0]) && ! in_array(strtolower($w), ['of', 'and', 'the', 'for', 'in', 'at'], true)) {
                        $acronym .= strtoupper($w[0]);
                    }
                }
                if (strlen($acronym) < 2) {
                    $acronym = strtoupper(substr($institutionName, 0, 3));
                }

                $formatted[] = [
                    'id' => (string) $edu->_id,
                    'institution' => $institutionName,
                    'institution_id' => $instId,
                    'degree' => $degreeName,
                    'degree_id' => $degreeId,
                    'field_of_study' => $fieldName,
                    'field_of_study_id' => $fieldId,
                    'grade' => $gradeName,
                    'grade_id' => $gradeId,
                    'passout_year' => $passoutYear,
                    'pursuing' => $isPursuing,
                    'current_institute' => $isPursuing ? 1 : 0,
                    'description' => (string) ($edu->description ?? ''),
                    'status' => 1,
                    'logo_title' => strtoupper($institutionName),
                    'logo_sub' => substr($acronym, 0, 4),
                    'logo_img' => $edu->institution?->logo ? asset($edu->institution->logo) : null,
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
     * Save an education record:
     * - Creates or updates the Education model
     * - Deduplicates / creates Organization, DegreeType, FieldOfStudy
     * - Atomically pushes the new ID to ReviewerProfile->education
     *
     * @param  array<string, mixed>  $payload
     * @return void
     */
    public function saveEducation(array $payload): void
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        $instName = trim((string) ($payload['institution'] ?? ''));
        $degreeTitle = trim((string) ($payload['degree'] ?? ''));
        $fieldOfStudy = trim((string) ($payload['field_of_study'] ?? ''));
        $gradeVal = trim((string) ($payload['grade'] ?? ''));
        $isPursuing = ! empty($payload['pursuing']);
        $passoutYear = trim((string) ($payload['passout_year'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $eduId = ! empty($payload['id']) ? trim((string) $payload['id']) : null;
        $statusVal = 1;

        if ($instName === '') {
            $this->dispatch('toast', message: 'Institution is required.', type: 'danger', position: 'bottom');

            return;
        }

        if ($degreeTitle === '') {
            $this->dispatch('toast', message: 'Degree is required.', type: 'danger', position: 'bottom');

            return;
        }

        $currentYear = (int) date('Y');
        if (! $isPursuing) {
            if ($passoutYear === '') {
                $this->dispatch('toast', message: 'Passout year is required unless currently pursuing.', type: 'danger', position: 'bottom');

                return;
            }

            if ((int) $passoutYear > $currentYear) {
                $this->dispatch('toast', message: 'Passout year cannot be in the future. Please check Pursuing instead.', type: 'danger', position: 'bottom');

                return;
            }
        }

        if ($description !== '' && (mb_strlen($description) < 50 || mb_strlen($description) > 1500)) {
            $this->dispatch('toast', message: 'Description must be between 50 and 1,500 characters.', type: 'danger', position: 'bottom');

            return;
        }

        // Enforce: User can only have one active education marked as currently pursuing
        if ($isPursuing) {
            $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            $eduIds = (array) ($reviewerProfile->education ?? []);

            $existingPursuingQuery = Education::where(function ($q) use ($eduIds) {
                $q->where('user_id', (string) $this->user->_id);
                if (! empty($eduIds)) {
                    $q->orWhereIn('_id', $eduIds);
                }
            })->whereIn('status', [1, '1', true])
              ->where(function ($q) {
                  $q->whereIn('current_institute', [1, '1', true])
                    ->orWhereIn('pursuing', [1, '1', true]);
              });

            if ($eduId) {
                $existingPursuingQuery->where('_id', '!=', $eduId);
            }

            if ($existingPursuingQuery->exists()) {
                $this->dispatch('toast', message: 'You can only have one education marked as currently pursuing. Please update your existing pursuing education first.', type: 'danger', position: 'bottom');

                return;
            }
        }

        // Deduplicate or record usage for models
        $orgModel = Organization::recordOrFind($instName);
        $degreeModel = DegreeType::recordUsage($degreeTitle);
        $fieldModel = $fieldOfStudy !== '' ? FieldOfStudy::recordUsage($fieldOfStudy) : null;

        // Resolve grade relation if matching
        $gradeModel = null;
        if ($gradeVal !== '') {
            $gradeModel = Grade::where('grade', $gradeVal)->orWhere('_id', $gradeVal)->first();
        }

        if ($eduId) {
            $edu = Education::findOrFail($eduId);
        } else {
            $edu = new Education();
            $edu->user_id = (string) $this->user->_id;
        }

        $edu->institution_id = (string) $orgModel->_id;
        $edu->degree_type = (string) $degreeModel->_id;
        $edu->field_of_study = $fieldModel ? (string) $fieldModel->_id : $fieldOfStudy;
        $edu->grade = $gradeModel ? (string) $gradeModel->_id : $gradeVal;
        $edu->pursuing = $isPursuing ? 1 : 0;
        $edu->current_institute = $isPursuing ? 1 : 0;
        $edu->passout_year = $isPursuing ? null : ($passoutYear !== '' ? Education::formatYear($passoutYear) : null);
        $edu->description = $description;
        $edu->status = $statusVal;
        $edu->save();

        $strId = (string) $edu->_id;
        $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        if (! $profile) {
            ReviewerProfile::create([
                'user_id' => (string) $this->user->_id,
                'unique_id' => (string) ($this->user->unique_id ?? ''),
                'status' => (string) $this->user->getStatusCode(),
                'education' => [$strId],
            ]);
        } else {
            $currentIds = (array) ($profile->education ?? []);
            if (! in_array($strId, $currentIds, true)) {
                ReviewerProfile::where('user_id', (string) $this->user->_id)->push('education', $strId, true);
            }
        }

        $this->dispatch('toast', message: 'Education saved successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-educations');
    }

    /**
     * Delete an education record and atomically pull its ID from ReviewerProfile.
     *
     * @param  string  $eduId
     * @return void
     */
    public function deleteEducation(string $eduId): void
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        $edu = Education::find($eduId);
        if ($edu) {
            $edu->delete();
        }

        ReviewerProfile::where('user_id', (string) $this->user->_id)->pull('education', $eduId);

        $this->dispatch('toast', message: 'Education deleted successfully!', type: 'success', position: 'bottom');
        $this->dispatch('refresh-educations');
    }
}; ?>

<div>
    @if($hasError)
        {{-- GRACEFUL ERROR FALLBACK CARD --}}
        <div id="education" class="scroll-mt-20 mt-6 bg-white dark:bg-[#111c26] border border-amber-200 dark:border-amber-800/60 rounded-2xl p-6 text-center shadow-xs">
            <div class="size-12 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-500 mx-auto flex items-center justify-center text-xl mb-3">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Unable to load Education details</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-md mx-auto">
                Something went wrong while loading this section. Please refresh the page or <a href="#" class="text-brand font-semibold hover:underline">contact our support team</a> if the problem continues.
            </p>
        </div>
    @else
        @php
            $userEducations = $this->educations;
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
                    institution: '',
                    degree: '',
                    field_of_study: '',
                    grade: '',
                    passout_year: '',
                    pursuing: false,
                    description: '',
                    status: 1,
                },

                // Errors
                errors: {
                    institution: false,
                    degree: false,
                    passout_year: false,
                    description: false,
                    descriptionMessage: '',
                },

                // Dropdown states & options
                organizationOptions: [],
                degreeOptions: [],
                fieldOptions: [],
                allGrades: [
                    'Distinction', 'First Class', 'Second Class', 'Third Class',
                    'A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'E+', 'E',
                    'A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D1', 'D2', 'E1', 'E2',
                    'O', 'F', 'RA', 'U', 'P', 'Not in the list'
                ],
                allYears: [],

                // Dropdown visibility
                showOrgDropdown: false,
                showDegreeDropdown: false,
                showFieldDropdown: false,
                showGradeDropdown: false,
                showYearDropdown: false,

                // Search queries for dropdowns
                orgSearchQuery: '',
                degreeSearchQuery: '',
                fieldSearchQuery: '',
                gradeSearchQuery: '',
                yearSearchQuery: '',

                // Loading indicators
                isSearchingOrg: false,
                isSearchingDegree: false,
                isSearchingField: false,
                isLoadingMoreOrg: false,
                isLoadingMoreDegree: false,
                isLoadingMoreField: false,

                // Pagination
                orgPage: 1,
                degreePage: 1,
                fieldPage: 1,
                orgHasMore: true,
                degreeHasMore: true,
                fieldHasMore: true,

                // Debounce timers
                orgDebounceTimer: null,
                degreeDebounceTimer: null,
                fieldDebounceTimer: null,

                // Sequence counters & Abort controllers (prevent previous results from overwriting new searches)
                orgSearchSeq: 0,
                degreeSearchSeq: 0,
                fieldSearchSeq: 0,
                orgAbortController: null,
                degreeAbortController: null,
                fieldAbortController: null,

                init() {
                    // Populate years from currentYear down to 1950 (no future years)
                    const currentYear = new Date().getFullYear();
                    const startYear = currentYear;
                    const endYear = 1950;
                    this.allYears = [];
                    for (let y = startYear; y >= endYear; y--) {
                        this.allYears.push(String(y));
                    }

                    // Initial fetch for top options
                    this.fetchOrganizations('');
                    this.fetchDegrees('');
                    this.fetchFields('');
                },

                // Filtered years based on internal search
                get filteredYears() {
                    const q = (this.yearSearchQuery || '').trim();
                    if (!q) return this.allYears;
                    return this.allYears.filter(y => y.includes(q));
                },

                // Filtered grades based on internal search
                get filteredGrades() {
                    const q = (this.gradeSearchQuery || '').trim().toLowerCase();
                    if (!q) return this.allGrades;
                    return this.allGrades.filter(g => g.toLowerCase().includes(q));
                },

                // Autocomplete: Organizations
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

                        fetch('/api/organizations?q=' + encodeURIComponent((this.orgSearchQuery || '').trim()) + '&page=' + page + '&limit=20')
                            .then(res => res.json())
                            .then(data => {
                                if (currentSeq !== this.orgSearchSeq) return;
                                const items = Array.isArray(data) ? data : (data.data || []);
                                if (items.length > 0) {
                                    for (const o of items) {
                                        if (!this.organizationOptions.includes(o.name)) {
                                            this.organizationOptions.push(o.name);
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

                onOrgScroll(e) {
                    const el = e.target;
                    if (el.scrollHeight - el.scrollTop <= el.clientHeight + 40) {
                        if (this.orgHasMore && !this.isLoadingMoreOrg && !this.isSearchingOrg) {
                            this.fetchOrganizations(this.orgSearchQuery, this.orgPage + 1);
                        }
                    }
                },

                openOrgDropdown() {
                    this.showOrgDropdown = !this.showOrgDropdown;
                    this.showDegreeDropdown = false;
                    this.showFieldDropdown = false;
                    this.showGradeDropdown = false;
                    this.showYearDropdown = false;
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

                selectOrganization(name) {
                    const trimmed = (name || '').trim();
                    if (trimmed) {
                        this.form.institution = trimmed;
                        this.errors.institution = false;
                    }
                    this.showOrgDropdown = false;
                    this.orgSearchQuery = '';
                    this.fetchOrganizations('');
                },

                clearOrganization() {
                    this.form.institution = '';
                    this.orgSearchQuery = '';
                    this.errors.institution = false;
                    this.fetchOrganizations('');
                },

                hasExactOrgMatch() {
                    const q = (this.orgSearchQuery || '').trim().toLowerCase();
                    if (!q) return true;
                    return this.organizationOptions.some(o => (o || '').toLowerCase() === q);
                },

                // Autocomplete: Degrees
                fetchDegrees(query = '', page = 1) {
                    const cleanQuery = (query || '').trim();
                    if (page === 1) {
                        clearTimeout(this.degreeDebounceTimer);
                        this.degreePage = 1;
                        this.degreeSearchQuery = query;
                        this.isSearchingDegree = true;

                        if (this.degreeAbortController) {
                            this.degreeAbortController.abort();
                        }
                        this.degreeAbortController = new AbortController();
                        const currentSeq = ++this.degreeSearchSeq;

                        fetch('/api/degree-types?q=' + encodeURIComponent(cleanQuery) + '&page=1&limit=20', {
                            signal: this.degreeAbortController.signal
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (currentSeq !== this.degreeSearchSeq) return;
                                const items = Array.isArray(data) ? data : (data.data || []);
                                this.degreeOptions = items.map(d => d.name);
                                this.degreeHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                                this.isSearchingDegree = false;
                            })
                            .catch(err => {
                                if (err && err.name === 'AbortError') return;
                                if (currentSeq === this.degreeSearchSeq) {
                                    this.isSearchingDegree = false;
                                }
                            });
                    } else {
                        if (this.isLoadingMoreDegree || !this.degreeHasMore) return;
                        this.isLoadingMoreDegree = true;
                        const currentSeq = this.degreeSearchSeq;

                        fetch('/api/degree-types?q=' + encodeURIComponent((this.degreeSearchQuery || '').trim()) + '&page=' + page + '&limit=20')
                            .then(res => res.json())
                            .then(data => {
                                if (currentSeq !== this.degreeSearchSeq) return;
                                const items = Array.isArray(data) ? data : (data.data || []);
                                if (items.length > 0) {
                                    for (const d of items) {
                                        if (!this.degreeOptions.includes(d.name)) {
                                            this.degreeOptions.push(d.name);
                                        }
                                    }
                                    this.degreeHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                                    this.degreePage = page;
                                } else {
                                    this.degreeHasMore = false;
                                }
                                this.isLoadingMoreDegree = false;
                            })
                            .catch(err => {
                                if (err && err.name === 'AbortError') return;
                                if (currentSeq === this.degreeSearchSeq) {
                                    this.isLoadingMoreDegree = false;
                                }
                            });
                    }
                },

                onDegreeScroll(e) {
                    const el = e.target;
                    if (el.scrollHeight - el.scrollTop <= el.clientHeight + 40) {
                        if (this.degreeHasMore && !this.isLoadingMoreDegree && !this.isSearchingDegree) {
                            this.fetchDegrees(this.degreeSearchQuery, this.degreePage + 1);
                        }
                    }
                },

                openDegreeDropdown() {
                    this.showDegreeDropdown = !this.showDegreeDropdown;
                    this.showOrgDropdown = false;
                    this.showFieldDropdown = false;
                    this.showGradeDropdown = false;
                    this.showYearDropdown = false;
                    if (this.showDegreeDropdown) {
                        this.degreeSearchQuery = '';
                        if (!this.degreeOptions.length) {
                            this.fetchDegrees('');
                        }
                        this.$nextTick(() => {
                            this.$refs.degreeSearchInput?.focus();
                        });
                    }
                },

                searchDegrees(query) {
                    this.degreeSearchQuery = query;
                    clearTimeout(this.degreeDebounceTimer);
                    this.degreeDebounceTimer = setTimeout(() => {
                        this.fetchDegrees(query);
                    }, 500);
                },

                selectDegree(name) {
                    const trimmed = (name || '').trim();
                    if (trimmed) {
                        this.form.degree = trimmed;
                        this.errors.degree = false;
                    }
                    this.showDegreeDropdown = false;
                    this.degreeSearchQuery = '';
                    this.fetchDegrees('');
                },

                clearDegree() {
                    this.form.degree = '';
                    this.degreeSearchQuery = '';
                    this.errors.degree = false;
                    this.fetchDegrees('');
                },

                hasExactDegreeMatch() {
                    const q = (this.degreeSearchQuery || '').trim().toLowerCase();
                    if (!q) return true;
                    return this.degreeOptions.some(d => (d || '').toLowerCase() === q);
                },

                // Autocomplete: Fields of Study
                fetchFields(query = '', page = 1) {
                    const cleanQuery = (query || '').trim();
                    if (page === 1) {
                        clearTimeout(this.fieldDebounceTimer);
                        this.fieldPage = 1;
                        this.fieldSearchQuery = query;
                        this.isSearchingField = true;

                        if (this.fieldAbortController) {
                            this.fieldAbortController.abort();
                        }
                        this.fieldAbortController = new AbortController();
                        const currentSeq = ++this.fieldSearchSeq;

                        fetch('/api/fields-of-study?q=' + encodeURIComponent(cleanQuery) + '&page=1&limit=20', {
                            signal: this.fieldAbortController.signal
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (currentSeq !== this.fieldSearchSeq) return;
                                const items = Array.isArray(data) ? data : (data.data || []);
                                this.fieldOptions = items.map(f => f.name);
                                this.fieldHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                                this.isSearchingField = false;
                            })
                            .catch(err => {
                                if (err && err.name === 'AbortError') return;
                                if (currentSeq === this.fieldSearchSeq) {
                                    this.isSearchingField = false;
                                }
                            });
                    } else {
                        if (this.isLoadingMoreField || !this.fieldHasMore) return;
                        this.isLoadingMoreField = true;
                        const currentSeq = this.fieldSearchSeq;

                        fetch('/api/fields-of-study?q=' + encodeURIComponent((this.fieldSearchQuery || '').trim()) + '&page=' + page + '&limit=20')
                            .then(res => res.json())
                            .then(data => {
                                if (currentSeq !== this.fieldSearchSeq) return;
                                const items = Array.isArray(data) ? data : (data.data || []);
                                if (items.length > 0) {
                                    for (const f of items) {
                                        if (!this.fieldOptions.includes(f.name)) {
                                            this.fieldOptions.push(f.name);
                                        }
                                    }
                                    this.fieldHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                                    this.fieldPage = page;
                                } else {
                                    this.fieldHasMore = false;
                                }
                                this.isLoadingMoreField = false;
                            })
                            .catch(err => {
                                if (err && err.name === 'AbortError') return;
                                if (currentSeq === this.fieldSearchSeq) {
                                    this.isLoadingMoreField = false;
                                }
                            });
                    }
                },

                onFieldScroll(e) {
                    const el = e.target;
                    if (el.scrollHeight - el.scrollTop <= el.clientHeight + 40) {
                        if (this.fieldHasMore && !this.isLoadingMoreField && !this.isSearchingField) {
                            this.fetchFields(this.fieldSearchQuery, this.fieldPage + 1);
                        }
                    }
                },

                openFieldDropdown() {
                    this.showFieldDropdown = !this.showFieldDropdown;
                    this.showOrgDropdown = false;
                    this.showDegreeDropdown = false;
                    this.showGradeDropdown = false;
                    this.showYearDropdown = false;
                    if (this.showFieldDropdown) {
                        this.fieldSearchQuery = '';
                        if (!this.fieldOptions.length) {
                            this.fetchFields('');
                        }
                        this.$nextTick(() => {
                            this.$refs.fieldSearchInput?.focus();
                        });
                    }
                },

                searchFields(query) {
                    this.fieldSearchQuery = query;
                    clearTimeout(this.fieldDebounceTimer);
                    this.fieldDebounceTimer = setTimeout(() => {
                        this.fetchFields(query);
                    }, 500);
                },

                selectField(name) {
                    const trimmed = (name || '').trim();
                    if (trimmed) {
                        this.form.field_of_study = trimmed;
                    }
                    this.showFieldDropdown = false;
                    this.fieldSearchQuery = '';
                    this.fetchFields('');
                },

                clearField() {
                    this.form.field_of_study = '';
                    this.fieldSearchQuery = '';
                    this.fetchFields('');
                },

                hasExactFieldMatch() {
                    const q = (this.fieldSearchQuery || '').trim().toLowerCase();
                    if (!q) return true;
                    return this.fieldOptions.some(f => (f || '').toLowerCase() === q);
                },

                // Grade Select
                selectGrade(grade) {
                    this.form.grade = grade;
                    this.showGradeDropdown = false;
                },

                clearGrade() {
                    this.form.grade = '';
                    this.gradeSearchQuery = '';
                },

                // Passout Year Select
                selectYear(yr) {
                    this.form.passout_year = yr;
                    this.showYearDropdown = false;
                    this.errors.passout_year = false;
                },

                clearYear() {
                    this.form.passout_year = '';
                    this.yearSearchQuery = '';
                },

                togglePursuing() {
                    if (this.form.pursuing) {
                        this.errors.passout_year = false;
                        this.showYearDropdown = false;
                    }
                },

                // Snapshot & Dirty Checking
                takeSnapshot() {
                    return JSON.stringify(this.form);
                },

                isDirty() {
                    return this.takeSnapshot() !== this.originalSnapshot;
                },

                openAddForm() {
                    if (this.isAdding || this.editingId) {
                        if (this.isDirty()) {
                            this.showUnsavedModal = true;
                            return;
                        }
                    }

                    this.editingId = null;
                    this.resetForm();
                    this.isAdding = true;
                    this.originalSnapshot = this.takeSnapshot();

                    this.$nextTick(() => {
                        const el = document.getElementById('education-add-form');
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    });
                },

                toggleAdd() {
                    this.openAddForm();
                },

                // Toggle Edit
                toggleEdit(item) {
                    if (this.editingId === item.id) {
                        if (this.isDirty()) {
                            this.showUnsavedModal = true;
                        } else {
                            this.editingId = null;
                        }
                        return;
                    }

                    if ((this.isAdding || this.editingId) && this.isDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }

                    this.isAdding = false;
                    this.editingId = item.id;
                    const isPursuing = Boolean(item.pursuing || item.current_institute == 1 || item.current_institute === '1');
                    this.form = {
                        id: item.id,
                        institution: item.institution || '',
                        degree: item.degree || '',
                        field_of_study: item.field_of_study || '',
                        grade: item.grade || '',
                        passout_year: isPursuing ? '' : (item.passout_year ? String(item.passout_year) : ''),
                        pursuing: isPursuing,
                        description: item.description || '',
                        status: item.status ?? 1,
                    };
                    this.originalSnapshot = this.takeSnapshot();
                },

                // Cancel handling
                handleCancel() {
                    if (this.isDirty()) {
                        this.showUnsavedModal = true;
                    } else {
                        this.isAdding = false;
                        this.editingId = null;
                    }
                },

                confirmDiscard() {
                    this.showUnsavedModal = false;
                    this.isAdding = false;
                    this.editingId = null;
                    this.resetForm();
                },

                keepEditing() {
                    this.showUnsavedModal = false;
                },

                resetForm() {
                    this.form = {
                        id: null,
                        institution: '',
                        degree: '',
                        field_of_study: '',
                        grade: '',
                        passout_year: '',
                        pursuing: false,
                        description: '',
                        status: 1,
                    };
                    this.errors = {
                        institution: false,
                        degree: false,
                        passout_year: false,
                        description: false,
                        descriptionMessage: '',
                    };
                    this.yearSearchQuery = '';
                    this.gradeSearchQuery = '';
                },

                // Form validation & submission
                submitForm() {
                    this.errors = {
                        institution: false,
                        degree: false,
                        passout_year: false,
                        description: false,
                        descriptionMessage: '',
                    };

                    let hasErr = false;

                    if (!this.form.institution || !this.form.institution.trim()) {
                        this.errors.institution = true;
                        hasErr = true;
                    }

                    if (!this.form.degree || !this.form.degree.trim()) {
                        this.errors.degree = true;
                        hasErr = true;
                    }

                    if (!this.form.pursuing) {
                        const yr = parseInt(this.form.passout_year, 10);
                        const curYear = new Date().getFullYear();
                        if (!yr || isNaN(yr)) {
                            this.errors.passout_year = true;
                            hasErr = true;
                        } else if (yr > curYear) {
                            this.errors.passout_year = true;
                            hasErr = true;
                        }
                    }

                    const desc = (this.form.description || '').trim();
                    if (desc.length > 0 && desc.length < 50) {
                        this.errors.description = true;
                        this.errors.descriptionMessage = 'Please write at least 50 characters';
                        hasErr = true;
                    } else if (desc.length > 1500) {
                        this.errors.description = true;
                        this.errors.descriptionMessage = 'Description must not exceed 1500 characters';
                        hasErr = true;
                    }

                    if (hasErr) return;

                    if (this.form.pursuing) {
                        const existingList = @js($userEducations);
                        const hasOtherPursuing = existingList.some(e => String(e.id) !== String(this.form.id) && Boolean(e.pursuing));
                        if (hasOtherPursuing) {
                            $dispatch('toast', {
                                message: 'You can only have one education marked as currently pursuing. Please update your existing pursuing education first.',
                                type: 'danger',
                                position: 'bottom'
                            });
                            return;
                        }
                    }

                    $wire.saveEducation(this.form).then(() => {
                        this.isAdding = false;
                        this.editingId = null;
                        this.resetForm();
                    });
                },

                deleteItem(id) {
                    if (confirm('Are you sure you want to delete this education record?')) {
                        $wire.deleteEducation(id).then(() => {
                            this.editingId = null;
                            this.resetForm();
                        });
                    }
                }
            }"
            class="scroll-mt-20 mt-6"
            id="education"
        >
            <section class="bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 rounded-2xl p-5 shadow-xs hover:shadow-md transition-all duration-300">
                
                <!-- CARD HEADER -->
                <div class="flex items-center justify-between pb-3 mb-5 border-b-2 border-[#f9fafb] dark:border-zinc-800">
                    <h2 class="text-base font-semibold font-space text-[#111827] dark:text-white flex items-center gap-2.5 m-0 leading-snug">
                        <i class="fa-solid fa-graduation-cap text-base text-[#793c4e] dark:text-[#a855f7]"></i>
                        <span>Education</span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                            {{ count($userEducations) }}
                        </span>
                    </h2>
                </div>

                <!-- TIMELINE LIST -->
                <div class="relative">
                    @if(count($userEducations) > 0)
                        {{-- Continuous Left Line --}}
                        <div 
                            class="absolute left-[7.5px] top-2 bottom-2 w-[3px] rounded-[2px] pointer-events-none"
                            style="background: linear-gradient(to bottom, var(--color-brand), var(--color-brand-50));"
                        ></div>
                    @endif

                    <div class="space-y-7">
                        @forelse($userEducations as $edu)
                            <div class="relative pl-8" wire:key="edu-user-{{ $edu['id'] }}">
                                
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
                                            @if(!empty($edu['logo_img']))
                                                <img src="{{ $edu['logo_img'] }}" alt="{{ $edu['institution'] }}" class="max-h-full max-w-full object-contain" onerror="this.remove()">
                                            @else
                                                @if(!empty($edu['logo_title']))
                                                    <span class="text-[8.5px] font-extrabold uppercase leading-tight text-center text-zinc-700 dark:text-zinc-200 line-clamp-2">{{ $edu['logo_title'] }}</span>
                                                @endif
                                                <span class="text-[17px] font-black tracking-tight text-[#111827] dark:text-white uppercase">{{ $edu['logo_sub'] }}</span>
                                            @endif
                                        </div>

                                        {{-- Details --}}
                                        <div class="min-w-0 flex-1 space-y-0.5 pt-0.5">
                                            <h3 class="text-[15px] font-bold text-[#111827] dark:text-white leading-snug">
                                                {{ $edu['institution'] }}
                                            </h3>
                                            
                                            <p class="text-[13px] text-zinc-600 dark:text-zinc-400 font-normal leading-snug">
                                                <span class="font-semibold text-brand">{{ $edu['degree'] }}</span>
                                                @if(!empty($edu['field_of_study']))
                                                    <span>in {{ $edu['field_of_study'] }}</span>
                                                @endif
                                                @if($edu['pursuing'] || !empty($edu['passout_year']))
                                                    <span class="mx-1">·</span>
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                                        {{ $edu['pursuing'] ? 'Pursuing' : $edu['passout_year'] }}
                                                    </span>
                                                @endif
                                                @if(!empty($edu['grade']))
                                                    <span class="mx-1">·</span>
                                                    <span class="text-zinc-500">Grade: {{ $edu['grade'] }}</span>
                                                @endif
                                            </p>

                                            @if(!empty($edu['description']))
                                                <div 
                                                    x-data="{ 
                                                        expanded: false, 
                                                        isClamped: {{ mb_strlen(trim($edu['description'])) > 140 ? 'true' : 'false' }},
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
                                                    ><strong class="font-semibold text-zinc-800 dark:text-zinc-200">Description:</strong> {{ trim($edu['description']) }}</div>
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
                                            x-show="editingId !== '{{ $edu['id'] }}'"
                                            @click="toggleEdit(@js($edu))"
                                            class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            title="Edit Education"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                        </button>
                                    @endif

                                </div>

                                {{-- Inline Edit Form --}}
                                @if($isOwnProfile)
                                    <div 
                                        x-show="editingId === '{{ $edu['id'] }}'" 
                                        x-cloak
                                        x-transition:enter="ease-out duration-200" 
                                        x-transition:enter-start="opacity-0 -translate-y-2" 
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                    >
                                        <x-profile.education-form :item-id="$edu['id']" />
                                    </div>
                                @endif

                            </div>
                        @empty
                            <p class="text-xs text-zinc-700 dark:text-zinc-300 py-1 font-normal">
                                No education details found.
                            </p>
                        @endforelse
                    </div>
                </div>

                {{-- Add Education Form --}}
                @if($isOwnProfile)
                    <div 
                        id="education-add-form"
                        x-show="isAdding" 
                        x-cloak
                        x-transition:enter="ease-out duration-200" 
                        x-transition:enter-start="opacity-0 -translate-y-2" 
                        x-transition:enter-end="opacity-100 translate-y-0"
                    >
                        <x-profile.education-form item-id="new" />
                    </div>
                @endif

                {{-- Bottom + Add Education Button --}}
                @if($isOwnProfile)
                    <div class="mt-6 pt-2" x-show="!isAdding">
                        <button 
                            type="button" 
                            @click="openAddForm()" 
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline transition-colors cursor-pointer select-none"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Add Education</span>
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
