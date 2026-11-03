<?php

use App\Livewire\Concerns\HandlesAddAuthorModal;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ReviewerProfile;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Volt\Component;
use MongoDB\BSON\Regex;

/**
 * Admin User Projects Component (Livewire Volt Child Component)
 *
 * Provides isolated project management for the admin user edit screen.
 * Displays all records (active and inactive), allows real-time status toggling,
 * editing, adding, and deletion with error boundary protection.
 */
new class extends Component {
    use HandlesAddAuthorModal;

    /**
     * User Eloquent model instance being edited.
     */
    public User $user;

    /**
     * Status filter for displaying projects ('all', '1', '0').
     */
    public string $statusFilter = 'all';

    /**
     * Error boundary flag.
     */
    public bool $hasError = false;

    /**
     * Catch and stop propagation of unhandled exceptions in the projects section.
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        report($e);
        $stopPropagation();
        $this->hasError = true;
    }

    /**
     * Get projects for the user being edited from ReviewerProfile.
     * Admin view shows both active and inactive records.
     *
     * @return list<array<string, mixed>>
     */
    public function getProjectsProperty(): array
    {
        try {
            $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            $projectIds = (array) ($reviewerProfile->projects ?? []);

            $query = Project::query();
            if (! empty($projectIds)) {
                $query->where(function ($q) use ($projectIds) {
                    $q->where('user_id', (string) $this->user->_id)
                      ->orWhereIn('_id', $projectIds);
                });
            } else {
                $query->where('user_id', (string) $this->user->_id);
            }

            if ($this->statusFilter === '1') {
                $query->whereIn('status', [1, '1', true]);
            } elseif ($this->statusFilter === '0') {
                $query->whereIn('status', [0, '0', false, null]);
            }

            // Load all projects for this user, ordered latest first
            $records = $query->orderBy('_id', 'desc')->get();

            // Zero N+1: Batch fetch all registered contributor users in 1 single database call
            $allContributorIds = [];
            foreach ($records as $p) {
                foreach ($p->getRegisteredContributorIds() as $cId) {
                    $allContributorIds[] = $cId;
                }
            }
            $allContributorIds = array_values(array_unique(array_filter($allContributorIds)));

            $usersMap = ! empty($allContributorIds)
                ? User::whereIn('_id', $allContributorIds)
                    ->select(['_id', 'first_name', 'last_name', 'fullname', 'email', 'photo', 'affiliation', 'slug'])
                    ->get()
                    ->keyBy(fn ($u) => (string) $u->_id)
                : collect();

            $formatted = [];
            foreach ($records as $p) {
                $regContributors = [];
                foreach ($p->getRegisteredContributorIds() as $cId) {
                    if (isset($usersMap[$cId])) {
                        $u = $usersMap[$cId];
                        $fullName = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                        if ($fullName === '' && ! empty($u->fullname)) {
                            $fullName = (string) $u->fullname;
                        }
                        $photo = ! empty($u->photo) ? $u->photo : 'assets/images/vector.png';
                        $photoSrc = (! empty($photo) && ! str_starts_with($photo, 'http')) ? asset($photo) : ($photo ?: asset('assets/images/vector.png'));

                        $regContributors[] = [
                            'id' => (string) $u->_id,
                            'name' => $fullName !== '' ? $fullName : 'Author',
                            'email' => (string) ($u->email ?? ''),
                            'photo' => $photoSrc,
                            'affiliation' => (string) ($u->affiliation ?? ''),
                            'slug' => (string) ($u->slug ?? ''),
                            'is_registered' => true,
                        ];
                    }
                }

                $allContributors = $regContributors;

                $startDate = (string) ($p->start_month_year ?? '');
                $endDate = (string) ($p->end_month_year ?? '');
                $notEnded = $p->isNotEnded();
                $funded = $p->isFunded();
                $fundedAmount = (string) ($p->funded_amount ?? '');
                $agencyName = (string) ($p->agency_name ?? '');
                $projectUrl = (string) ($p->project_url ?? '');
                $description = (string) ($p->description ?? '');
                $statusVal = (int) ($p->status ?? 1);

                $formatted[] = [
                    'id' => (string) $p->_id,
                    'project_name' => (string) ($p->project_name ?? 'Untitled Project'),
                    'start_month_year' => $startDate,
                    'end_month_year' => $endDate,
                    'not_ended_yet' => $notEnded,
                    'date_range' => $startDate.($notEnded ? ' - Present' : ($endDate ? ' - '.$endDate : '')),
                    'funded_project' => $funded ? 'Yes' : 'No',
                    'is_funded' => $funded,
                    'funded_amount' => $fundedAmount,
                    'agency_name' => $agencyName,
                    'project_url' => $projectUrl,
                    'description' => $description,
                    'contributors' => $allContributors,
                    'status' => $statusVal === 1 ? 1 : 0,
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
     * Search registered users for Other Contributors autocomplete.
     *
     * @return list<array<string, mixed>>
     */
    public function searchContributors(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $queryParts = array_values(array_filter(explode(' ', $query)));

        $fetchUsers = function (array $parts) {
            $usersQuery = User::where(function ($q) {
                $q->where('type', 0)
                    ->orWhere('type', '0')
                    ->orWhereNull('type');
            })
                ->where('status', '!=', 3)
                ->where('status', '!=', '3');

            $usersQuery->where(function ($q) use ($parts) {
                foreach ($parts as $part) {
                    $escaped = preg_quote($part, '/');
                    $partRegex = new Regex($escaped, 'i');
                    $q->where(function ($sub) use ($partRegex) {
                        $sub->where('first_name', 'regex', $partRegex)
                            ->orWhere('last_name', 'regex', $partRegex)
                            ->orWhere('fullname', 'regex', $partRegex)
                            ->orWhere('email', 'regex', $partRegex);
                    });
                }
            });

            return $usersQuery->limit(25)->get();
        };

        $users = $fetchUsers($queryParts);

        // If no results on exact words for multi-word queries, try prefix fallback (e.g. darshna -> darsh)
        if ($users->isEmpty() && count($queryParts) > 1) {
            $prefixParts = array_map(function ($part) {
                return mb_strlen($part) > 4 ? mb_substr($part, 0, 4) : $part;
            }, $queryParts);
            $users = $fetchUsers($prefixParts);
        }

        if ($users->isEmpty()) {
            return [];
        }

        // Sort users: exact match (100) > starts with (80) > exact first/last (60) > partial (40) > contains all (20)
        $sortedUsers = $users->sortByDesc(function ($user) use ($query, $queryParts) {
            $f = strtolower(trim($user->first_name ?? ''));
            $l = strtolower(trim($user->last_name ?? ''));
            $fullName1 = trim("{$f} {$l}");
            $fullName2 = trim("{$l} {$f}");
            $searchQuery = strtolower(trim($query));

            if ($fullName1 === $searchQuery || $fullName2 === $searchQuery) {
                return 100;
            }

            if (str_starts_with($fullName1, $searchQuery) || str_starts_with($fullName2, $searchQuery)) {
                return 80;
            }

            if ($f === $searchQuery || $l === $searchQuery) {
                return 60;
            }

            if (str_starts_with($f, $searchQuery) || str_starts_with($l, $searchQuery)) {
                return 40;
            }

            $hasAll = true;
            foreach ($queryParts as $part) {
                $p = strtolower($part);
                if (! str_contains($fullName1, $p) && ! str_contains($fullName2, $p)) {
                    $hasAll = false;
                    break;
                }
            }

            return $hasAll ? 20 : 1;
        })->values()->take(10);

        // Batch resolve affiliations matching deposit-article workflow
        $userIds = $sortedUsers->pluck('_id')->map(fn ($id) => (string) $id)->toArray();
        $profiles = ReviewerProfile::whereIn('user_id', $userIds)->get()->keyBy(fn ($p) => (string) $p->user_id);

        $expIds = [];
        $profileExpMap = [];
        foreach ($profiles as $uId => $profile) {
            $exp = $profile->experience ?? null;
            if (is_string($exp)) {
                $decoded = json_decode($exp, true);
                if (is_array($decoded) && ! empty($decoded)) {
                    $firstExp = is_array($decoded[0]) ? ($decoded[0]['_id'] ?? $decoded[0]['organization_id'] ?? null) : $decoded[0];
                    if ($firstExp) {
                        $expIds[] = (string) $firstExp;
                        $profileExpMap[$uId] = (string) $firstExp;
                    }
                }
            } elseif (is_array($exp) && ! empty($exp)) {
                $firstExp = is_array($exp[0]) ? ($exp[0]['_id'] ?? $exp[0]['organization_id'] ?? null) : $exp[0];
                if ($firstExp) {
                    $expIds[] = (string) $firstExp;
                    $profileExpMap[$uId] = (string) $firstExp;
                }
            }
        }

        $experiences = ! empty($expIds) ? Experience::whereIn('_id', array_values(array_unique($expIds)))->get()->keyBy(fn ($e) => (string) $e->_id) : collect();

        $orgIds = [];
        foreach ($experiences as $e) {
            if (! empty($e->organization_id)) {
                $orgIds[] = (string) $e->organization_id;
            }
        }
        $organizations = ! empty($orgIds) ? Organization::whereIn('_id', array_values(array_unique($orgIds)))->get()->keyBy(fn ($o) => (string) $o->_id) : collect();

        $results = [];
        foreach ($sortedUsers as $user) {
            $uId = (string) $user->_id;
            $affiliationName = null;

            if (isset($profileExpMap[$uId]) && isset($experiences[$profileExpMap[$uId]])) {
                $exp = $experiences[$profileExpMap[$uId]];
                if (! empty($exp->organization_id) && isset($organizations[(string) $exp->organization_id])) {
                    $affiliationName = $organizations[(string) $exp->organization_id]->organization_name ?? null;
                }
            }

            if (! $affiliationName && ! empty($user->affiliation)) {
                $affiliationName = is_object($user->affiliation) ? ($user->affiliation->name ?? null) : (string) $user->affiliation;
            }

            $authorName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            if ($authorName === '') {
                $authorName = trim((string) (($user->first_name ?? '') ?: ($user->last_name ?? '')));
            }
            if ($authorName === '' && ! empty($user->fullname)) {
                $authorName = trim((string) $user->fullname);
            }

            $photo = ! empty($user->photo) ? $user->photo : 'assets/images/vector.png';
            $photoSrc = (! empty($photo) && ! str_starts_with($photo, 'http')) ? asset($photo) : ($photo ?: asset('assets/images/vector.png'));

            $results[] = [
                'id' => $uId,
                'name' => $authorName !== '' ? $authorName : 'Author',
                'photo' => $photoSrc,
                'affiliation' => (string) ($affiliationName ?? ''),
            ];
        }

        return $results;
    }

    /**
     * Toggle project status between 1 (Active) and 0 (Inactive).
     */
    public function toggleProjectStatus(string $projectId): void
    {
        $project = Project::findOrFail($projectId);
        $newStatus = ((int) $project->status === 1) ? 0 : 1;
        $project->status = $newStatus;
        $project->save();

        $label = $newStatus === 1 ? 'Active' : 'Inactive';
        $this->dispatch('toast', message: "Project set to {$label}.", type: 'success', position: 'bottom');
    }

    /**
     * Save a project record (Admin).
     *
     * @param  array<string, mixed>  $payload
     */
    public function saveProject(array $payload): bool
    {
        $projectName = trim((string) ($payload['project_name'] ?? ''));
        $startMonthYear = trim((string) ($payload['start_month_year'] ?? ''));
        $endMonthYear = trim((string) ($payload['end_month_year'] ?? ''));
        $notEndedYet = ! empty($payload['not_ended_yet']);
        $fundedProject = trim((string) ($payload['funded_project'] ?? ''));
        $fundedAmount = trim((string) ($payload['funded_amount'] ?? ''));
        $projectUrl = trim((string) ($payload['project_url'] ?? ''));
        $agencyName = trim((string) ($payload['agency_name'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $contributors = (array) ($payload['contributors'] ?? []);
        $projectId = ! empty($payload['id']) ? trim((string) $payload['id']) : null;
        $statusVal = isset($payload['status']) ? ((int) $payload['status'] === 1 ? 1 : 0) : 1;

        // 1. Required: Project Name
        if ($projectName === '') {
            $this->dispatch('toast', message: 'Project Name is required.', type: 'danger', position: 'bottom');

            return false;
        }

        // 2. Required: Start Date
        if ($startMonthYear === '') {
            $this->dispatch('toast', message: 'Start Date is required.', type: 'danger', position: 'bottom');

            return false;
        }

        // 3. Date Validations
        $currentMonth = Carbon::now()->startOfMonth();
        $startDateObj = Project::parseCarbonDate($startMonthYear);

        if ($startDateObj && $startDateObj->greaterThan($currentMonth)) {
            $this->dispatch('alert-modal', title: 'Invalid Start Date', message: 'Start date cannot be in the future.', type: 'warning');

            return false;
        }

        if (! $notEndedYet) {
            if ($endMonthYear === '') {
                $this->dispatch('toast', message: 'End Date is required unless Not Ended Yet is checked.', type: 'danger', position: 'bottom');

                return false;
            }

            $endDateObj = Project::parseCarbonDate($endMonthYear);
            if ($endDateObj && $endDateObj->greaterThan($currentMonth)) {
                $this->dispatch('alert-modal', title: 'Invalid End Date', message: 'End date cannot be in the future.', type: 'warning');

                return false;
            }

            if ($startDateObj && $endDateObj && $startDateObj->greaterThan($endDateObj)) {
                $this->dispatch('alert-modal', title: 'Invalid Date Range', message: 'Start date cannot be after end date.', type: 'warning');

                return false;
            }
        }

        // 4. Funded Project validation
        $isFundedYes = in_array(strtolower($fundedProject), ['yes', '1', 'true'], true);
        if ($fundedProject === '') {
            $this->dispatch('toast', message: 'Please select whether this is a Funded Project (Yes or No).', type: 'danger', position: 'bottom');

            return false;
        }

        if ($isFundedYes && $fundedAmount === '') {
            $this->dispatch('toast', message: 'Funded Amount is required when project is funded.', type: 'danger', position: 'bottom');

            return false;
        }

        // 5. Description validation
        if ($description !== '') {
            $descLen = mb_strlen($description);
            if ($descLen < 50 || $descLen > 1500) {
                $this->dispatch('toast', message: 'Description must be between 50 and 1500 characters.', type: 'danger', position: 'bottom');

                return false;
            }
        }

        // 6. Process registered contributors into native string array
        $regIds = [];
        foreach ($contributors as $c) {
            if (! empty($c['id'])) {
                $regIds[] = (string) $c['id'];
            }
        }
        $regContributors = ! empty($regIds) ? array_values(array_unique($regIds)) : null;

        // 7. Persist Project Model
        if ($projectId) {
            $project = Project::find($projectId);
            if (! $project) {
                $this->dispatch('toast', message: 'Project record not found.', type: 'danger', position: 'bottom');

                return false;
            }
        } else {
            $project = new Project;
            $project->user_id = (string) $this->user->_id;
        }

        $project->project_name = $projectName;
        $project->start_month_year = Project::formatMonthYear($startMonthYear);
        $project->end_month_year = $notEndedYet ? null : Project::formatMonthYear($endMonthYear, true);
        $project->not_ended_yet = $notEndedYet ? 1 : 0;
        $project->funded_project = $isFundedYes ? '1' : '0';
        $project->funded_amount = $isFundedYes ? $fundedAmount : null;
        $project->project_url = $projectUrl !== '' ? $projectUrl : null;
        $project->agency_name = $agencyName !== '' ? $agencyName : null;
        $project->registered_contributors = $regContributors;
        $project->description = $description !== '' ? $description : null;
        $project->status = $statusVal;
        $project->save();

        // 8. Atomic ReviewerProfile synchronization on create
        if (! $projectId) {
            ReviewerProfile::where('user_id', (string) $this->user->_id)
                ->push('projects', (string) $project->_id, true);
        }

        $this->dispatch('toast', message: $projectId ? 'Project updated successfully.' : 'Project added successfully.', type: 'success', position: 'bottom');

        return true;
    }

    /**
     * Delete a project record and atomically remove its ID from ReviewerProfile.
     */
    public function deleteProject(string $id): bool
    {
        $project = Project::find($id);
        if ($project) {
            $project->delete();
        }

        ReviewerProfile::where('user_id', (string) $this->user->_id)
            ->pull('projects', (string) $id);

        $this->dispatch('toast', message: 'Project deleted successfully.', type: 'success', position: 'bottom');

        return true;
    }
}; ?>

<div class="mt-6">
    @if ($hasError)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-start gap-3 shadow-xs">
            <svg class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-semibold">Projects Section Notice</h3>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    We encountered an unexpected error loading the projects section. Other sections remain functional.
                </p>
            </div>
        </div>
    @else
        <div 
            @author-added.window="addAuthorFromModal($event.detail.author)"
            x-data="{
                showUnsavedModal: false,
                originalSnapshot: '',
                statusFilter: 'all',
                isAdding: false,
                editingId: null,
                form: {
                    id: null,
                    project_name: '',
                    start_month_year: '',
                    end_month_year: '',
                    not_ended_yet: false,
                    funded_project: '',
                    funded_amount: '',
                    project_url: '',
                    agency_name: '',
                    description: '',
                    status: 1,
                    contributors: []
                },
                errors: {
                    project_name: false,
                    start_month_year: false,
                    end_month_year: false,
                    funded_project: false,
                    funded_amount: false,
                    project_url: false,
                    description: false,
                    descriptionMessage: ''
                },
                contributorSearchQuery: '',
                contributorResults: [],
                isSearchingContributor: false,
                showContributorDropdown: false,
                searchTimeout: null,
                contributorSearchSeq: 0,

                resetForm() {
                    this.form = {
                        id: null,
                        project_name: '',
                        start_month_year: '',
                        end_month_year: '',
                        not_ended_yet: false,
                        funded_project: '',
                        funded_amount: '',
                        project_url: '',
                        agency_name: '',
                        description: '',
                        status: 1,
                        contributors: []
                    };
                    this.errors = {
                        project_name: false,
                        start_month_year: false,
                        end_month_year: false,
                        funded_project: false,
                        funded_amount: false,
                        project_url: false,
                        description: false,
                        descriptionMessage: ''
                    };
                    this.contributorSearchQuery = '';
                    this.contributorResults = [];
                    this.isSearchingContributor = false;
                    this.showContributorDropdown = false;
                },

                takeSnapshot() {
                    return JSON.stringify(this.form);
                },

                isDirty() {
                    return this.originalSnapshot !== '' && this.takeSnapshot() !== this.originalSnapshot;
                },

                openAddForm() {
                    if ((this.isAdding || this.editingId) && this.isDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                    this.resetForm();
                    this.editingId = null;
                    this.isAdding = true;
                    this.originalSnapshot = this.takeSnapshot();
                    this.$nextTick(() => {
                        const el = document.getElementById('admin-project-add-form');
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    });
                },

                openEditForm(item) {
                    if ((this.isAdding || this.editingId) && this.isDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                    this.resetForm();
                    this.isAdding = false;
                    this.editingId = item.id;
                    this.form = {
                        id: item.id,
                        project_name: item.project_name || '',
                        start_month_year: item.start_month_year || '',
                        end_month_year: item.end_month_year || '',
                        not_ended_yet: Boolean(item.not_ended_yet),
                        funded_project: item.funded_project || (item.is_funded ? 'Yes' : 'No'),
                        funded_amount: item.funded_amount || '',
                        project_url: item.project_url || '',
                        agency_name: item.agency_name || '',
                        description: item.description || '',
                        status: item.status !== undefined ? item.status : 1,
                        contributors: item.contributors ? JSON.parse(JSON.stringify(item.contributors)) : []
                    };
                    this.originalSnapshot = this.takeSnapshot();
                },

                closeForm(force = false) {
                    if (!force && this.isDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                    this.showUnsavedModal = false;
                    this.isAdding = false;
                    this.editingId = null;
                    this.resetForm();
                    this.originalSnapshot = '';
                },

                handleCancel() {
                    this.closeForm();
                },

                confirmDiscard() {
                    this.closeForm(true);
                },

                keepEditing() {
                    this.showUnsavedModal = false;
                },

                isFundedYes() {
                    return this.form.funded_project === 'Yes' || this.form.funded_project === '1';
                },

                onFundedProjectChange() {
                    this.errors.funded_project = false;
                    if (!this.isFundedYes()) {
                        this.form.funded_amount = '';
                        this.errors.funded_amount = false;
                    }
                },

                onContributorInput(val) {
                    clearTimeout(this.searchTimeout);
                    const query = (val || '').trim();
                    if (query.length < 2) {
                        this.contributorResults = [];
                        this.isSearchingContributor = false;
                        this.showContributorDropdown = false;
                        return;
                    }

                    this.contributorResults = [];
                    this.isSearchingContributor = true;
                    this.showContributorDropdown = true;
                    this.contributorSearchSeq = (this.contributorSearchSeq || 0) + 1;
                    const seq = this.contributorSearchSeq;

                    this.searchTimeout = setTimeout(() => {
                        this.$wire.searchContributors(query).then((results) => {
                            if (seq !== this.contributorSearchSeq) return;
                            this.contributorResults = results || [];
                            this.isSearchingContributor = false;
                        }).catch(() => {
                            if (seq === this.contributorSearchSeq) {
                                this.isSearchingContributor = false;
                            }
                        });
                    }, 250);
                },

                hasExactContributorMatch() {
                    const q = (this.contributorSearchQuery || '').trim().toLowerCase();
                    if (!q) return true;
                    return (this.contributorResults || []).some(a => (a.name || '').toLowerCase() === q || (a.email || '').toLowerCase() === q);
                },

                openAddAuthorModal(name) {
                    this.showContributorDropdown = false;
                    this.$wire.openAddAuthorModal(null, name || this.contributorSearchQuery);
                },

                addAuthorFromModal(author) {
                    if (!author || !author.name) return;
                    if (!this.form.contributors) this.form.contributors = [];
                    const exists = this.form.contributors.some(c => c.id && String(c.id) === String(author.id));
                    if (!exists) {
                        this.form.contributors.push({
                            id: author.id,
                            name: author.name,
                            email: author.email,
                            photo: author.photo,
                            affiliation: author.affiliation,
                            is_registered: true
                        });
                    }
                    this.contributorSearchQuery = '';
                    this.contributorResults = [];
                    this.showContributorDropdown = false;
                },

                selectContributor(author) {
                    if (!this.form.contributors) this.form.contributors = [];
                    const exists = this.form.contributors.some(c => c.id && String(c.id) === String(author.id));
                    if (!exists) {
                        this.form.contributors.push({
                            id: author.id,
                            name: author.name,
                            email: author.email,
                            photo: author.photo,
                            affiliation: author.affiliation,
                            is_registered: true
                        });
                    }
                    this.contributorSearchQuery = '';
                    this.contributorResults = [];
                    this.showContributorDropdown = false;
                },

                removeContributor(idx) {
                    this.form.contributors.splice(idx, 1);
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

                parseDateToTimestamp(str) {
                    if (!str) return 0;
                    const clean = str.replace(',', '').trim();
                    const d = new Date(clean);
                    return isNaN(d.getTime()) ? 0 : d.getTime();
                },

                submitForm() {
                    this.errors = {
                        project_name: false,
                        start_month_year: false,
                        end_month_year: false,
                        funded_project: false,
                        funded_amount: false,
                        project_url: false,
                        description: false,
                        descriptionMessage: ''
                    };

                    let hasErr = false;

                    if (!this.form.project_name || !this.form.project_name.trim()) {
                        this.errors.project_name = true;
                        hasErr = true;
                    }

                    if (!this.form.start_month_year || !this.form.start_month_year.trim()) {
                        this.errors.start_month_year = true;
                        hasErr = true;
                    }

                    if (!this.form.not_ended_yet) {
                        if (!this.form.end_month_year || !this.form.end_month_year.trim()) {
                            this.errors.end_month_year = true;
                            hasErr = true;
                        }
                    }

                    if (!this.form.funded_project) {
                        this.errors.funded_project = true;
                        hasErr = true;
                    } else if (this.isFundedYes()) {
                        if (!this.form.funded_amount || !this.form.funded_amount.trim()) {
                            this.errors.funded_amount = true;
                            hasErr = true;
                        }
                    }

                    const desc = (this.form.description || '').trim();
                    if (desc.length > 0 && desc.length < 50) {
                        this.errors.description = true;
                        this.errors.descriptionMessage = 'Description must be at least 50 characters';
                        hasErr = true;
                    } else if (desc.length > 1500) {
                        this.errors.description = true;
                        this.errors.descriptionMessage = 'Description must not exceed 1500 characters';
                        hasErr = true;
                    }

                    if (this.form.project_url && this.form.project_url.trim()) {
                        try {
                            new URL(this.form.project_url.trim());
                        } catch (_) {
                            this.errors.project_url = true;
                            hasErr = true;
                        }
                    }

                    if (hasErr) return;

                    const now = new Date();
                    const currentMonthTimestamp = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
                    const startTimestamp = this.parseDateToTimestamp(this.form.start_month_year);

                    if (startTimestamp > currentMonthTimestamp) {
                        this.errors.start_month_year = true;
                        this.triggerAlertModal('Invalid Start Date', 'Start date cannot be in the future.');
                        return;
                    }

                    if (!this.form.not_ended_yet && this.form.end_month_year) {
                        const endTimestamp = this.parseDateToTimestamp(this.form.end_month_year);
                        if (endTimestamp > currentMonthTimestamp) {
                            this.errors.end_month_year = true;
                            this.triggerAlertModal('Invalid End Date', 'End date cannot be in the future.');
                            return;
                        }

                        if (startTimestamp > endTimestamp) {
                            this.errors.start_month_year = true;
                            this.errors.end_month_year = true;
                            this.triggerAlertModal('Invalid Date Range', 'Start date cannot be after end date.');
                            return;
                        }
                    }

                    this.$wire.saveProject(this.form).then((res) => {
                        if (res !== false) {
                            this.originalSnapshot = '';
                            this.closeForm(true);
                        }
                    });
                },

                deleteItem(id) {
                    if (confirm('Are you sure you want to delete this project?')) {
                        this.$wire.deleteProject(id).then((res) => {
                            if (res !== false) {
                                this.originalSnapshot = '';
                                this.closeForm(true);
                            }
                        });
                    }
                }
            }"
            class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden"
        >
            {{-- CARD HEADER --}}
            <div class="px-6 py-5 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-brand flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <span>Projects Details</span>
                            <span class="inline-flex items-center justify-center size-5 text-xs font-bold bg-[#eff6ff] dark:bg-blue-950/60 text-brand rounded-full">
                                {{ count($this->projects) }}
                            </span>
                        </h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Manage user's research, industrial, and development projects.</p>
                    </div>
                </div>

                {{-- Status Filter Pill Controls --}}
                <div class="flex items-center gap-1 p-1 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl text-xs font-semibold">
                    <button 
                        type="button" 
                        @click="statusFilter = 'all'; $wire.set('statusFilter', 'all')"
                        :class="statusFilter === 'all' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-2xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg transition cursor-pointer"
                    >
                        All
                    </button>
                    <button 
                        type="button" 
                        @click="statusFilter = '1'; $wire.set('statusFilter', '1')"
                        :class="statusFilter === '1' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-2xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg transition cursor-pointer"
                    >
                        Active
                    </button>
                    <button 
                        type="button" 
                        @click="statusFilter = '0'; $wire.set('statusFilter', '0')"
                        :class="statusFilter === '0' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-2xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                        class="px-3 py-1 rounded-lg transition cursor-pointer"
                    >
                        Inactive
                    </button>
                </div>
            </div>

            <div class="p-6">
                {{-- PROJECTS LIST / EMPTY STATE --}}
                <div class="space-y-4">
                    @forelse($this->projects as $project)
                        <div 
                            wire:key="admin-project-card-{{ $project['id'] }}" 
                            class="p-4 rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 hover:border-brand dark:hover:border-brand hover:border-l-4 hover:border-l-brand hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                        >
                                {{-- DISPLAY MODE (Always visible in list) --}}
                                <div>
                                    <div class="flex items-start justify-between gap-4 flex-wrap sm:flex-nowrap">
                                        <div class="space-y-1.5 flex-1 min-w-0">
                                            {{-- Title, ID & Status Pill --}}
                                            <div class="flex items-center gap-2.5 flex-wrap">
                                                <h4 class="text-sm font-bold text-zinc-900 dark:text-white leading-snug">
                                                    {{ $project['project_name'] }}
                                                </h4>

                                                {{-- Database ID with select-all --}}
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Database ID (Click to select)">
                                                    <span class="text-zinc-400 select-none">ID:</span>
                                                    <span>{{ $project['id'] }}</span>
                                                </span>

                                                {{-- 1-Click Status Pill Toggle (Active / Inactive) --}}
                                                <button 
                                                    type="button" 
                                                    wire:click="toggleProjectStatus('{{ $project['id'] }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold cursor-pointer transition select-none active:scale-95 {{ $project['status'] === 1 ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200' }}"
                                                    title="Click to toggle status"
                                                >
                                                    <span class="size-1.5 rounded-full {{ $project['status'] === 1 ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                                                    <span>{{ $project['status'] === 1 ? 'Active' : 'Inactive' }}</span>
                                                </button>
                                            </div>

                                            {{-- Meta Row: Agency, Dates, Funded Status & Amount --}}
                                            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 flex-wrap">
                                                {{-- Agency Name --}}
                                                @if(!empty($project['agency_name']))
                                                    <span>Agency: <strong class="text-zinc-700 dark:text-zinc-300 font-semibold">{{ $project['agency_name'] }}</strong></span>
                                                    <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                @endif

                                                {{-- Date Range --}}
                                                <span class="inline-flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-regular fa-calendar text-[11px] text-zinc-400"></i>
                                                    <span>{{ $project['date_range'] }}</span>
                                                </span>

                                                {{-- Funded Status & Amount --}}
                                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                <span class="inline-flex items-center gap-1">
                                                    <span>Funded:</span>
                                                    @if($project['is_funded'])
                                                        <span class="font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.2 rounded text-[11px]">Yes</span>
                                                        @if(!empty($project['funded_amount']))
                                                            <span class="ml-1 text-zinc-500 dark:text-zinc-400">Amount: <strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $project['funded_amount'] }}</strong></span>
                                                        @endif
                                                    @else
                                                        <span class="font-bold text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.2 rounded text-[11px]">No</span>
                                                    @endif
                                                </span>
                                            </div>

                                            {{-- Contributors Row --}}
                                            @if(!empty($project['contributors']))
                                                <div class="flex items-center gap-1.5 flex-wrap pt-1">
                                                    <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Contributors:</span>
                                                    @foreach($project['contributors'] as $c)
                                                        @if($c['is_registered'] && !empty($c['slug']))
                                                            <a 
                                                                href="{{ url('/profile/' . $c['slug']) }}" 
                                                                target="_blank"
                                                                class="inline-flex items-center gap-1 pl-1 pr-2 py-0.5 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-700 dark:text-zinc-300 hover:text-brand hover:border-brand/40 transition shadow-2xs"
                                                            >
                                                                @if(!empty($c['photo']))
                                                                    <img src="{{ $c['photo'] }}" alt="{{ $c['name'] }}" class="size-4 rounded-full object-cover" onerror="this.onerror=null; this.src='{{ asset('assets/images/vector.png') }}';" />
                                                                @endif
                                                                <span class="font-medium text-xs">{{ $c['name'] }}</span>
                                                            </a>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs">
                                                                <i class="fa-regular fa-user text-[10px] text-zinc-400"></i>
                                                                <span class="font-medium text-xs">{{ $c['name'] }}</span>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Project URL (Above Description) --}}
                                            @if(!empty($project['project_url']))
                                                <div class="flex items-center gap-1.5 text-xs pt-1">
                                                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">Project URL :-</span>
                                                    <a 
                                                        href="{{ $project['project_url'] }}" 
                                                        target="_blank" 
                                                        rel="noopener noreferrer" 
                                                        class="text-xs font-semibold text-brand hover:underline inline-flex items-center gap-1 break-all"
                                                        title="{{ $project['project_url'] }}"
                                                    >
                                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                                        <span class="truncate max-w-[350px]">{{ $project['project_url'] }}</span>
                                                    </a>
                                                </div>
                                            @endif

                                            {{-- Description: 2 lines with View More / View Less --}}
                                            @if(!empty($project['description']))
                                                <div 
                                                    x-data="{ 
                                                        expanded: false, 
                                                        isClamped: {{ mb_strlen(trim($project['description'])) > 140 ? 'true' : 'false' }},
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
                                                    class="pt-1"
                                                >
                                                    <div 
                                                        x-ref="descContainer" 
                                                        :class="{ 'line-clamp-2': !expanded, 'whitespace-pre-line': expanded }" 
                                                        class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed break-words"
                                                    ><strong class="font-semibold text-zinc-800 dark:text-zinc-200">Description:</strong> {{ trim($project['description']) }}</div>
                                                    <button 
                                                        type="button" 
                                                        x-show="isClamped || expanded" 
                                                        x-cloak 
                                                        @click="expanded = !expanded; if (!expanded) $nextTick(() => checkClamped())" 
                                                        class="text-xs font-semibold text-brand hover:underline mt-0.5 inline-flex items-center gap-1 cursor-pointer transition-colors"
                                                    >
                                                        <span x-text="expanded ? 'View Less' : 'View More'"></span>
                                                        <i class="fa-solid text-[9px]" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Action Buttons: Edit & Delete --}}
                                        <div class="flex items-center gap-1.5 shrink-0 self-start">
                                            <button 
                                                type="button" 
                                                x-show="editingId !== '{{ $project['id'] }}'"
                                                @click="openEditForm(@js($project))"
                                                class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                                title="Edit Project"
                                            >
                                                <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                            </button>

                                            <button 
                                                type="button" 
                                                @click="deleteItem('{{ $project['id'] }}')"
                                                class="p-1.5 text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400 transition-colors cursor-pointer shrink-0 rounded hover:bg-red-50 dark:hover:bg-red-950/40"
                                                title="Delete Project"
                                            >
                                                <i class="fa-solid fa-trash-can text-[15px]"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- INLINE EDIT FORM (When editing this item, displayed directly below item info) --}}
                                <div 
                                    x-show="editingId === '{{ $project['id'] }}'" 
                                    x-cloak
                                    x-transition:enter="ease-out duration-200" 
                                    x-transition:enter-start="opacity-0 -translate-y-2" 
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                >
                                    <template x-if="editingId === '{{ $project['id'] }}'">
                                        <div>
                                            <x-profile.project-form :show-status="true" :item-id="$project['id']" />
                                        </div>
                                    </template>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-zinc-700 dark:text-zinc-300 py-1 font-normal">
                                No projects found.
                            </p>
                        @endforelse
                    </div>

                    {{-- Add Project Form --}}
                    <div 
                        id="admin-project-add-form"
                        x-show="isAdding" 
                        x-cloak
                        x-transition:enter="ease-out duration-200" 
                        x-transition:enter-start="opacity-0 -translate-y-2" 
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="mt-4"
                    >
                        <x-profile.project-form :show-status="true" item-id="new" />
                    </div>

                    {{-- Add Project Button --}}
                    <div class="mt-6 pt-2" x-show="!isAdding">
                        <button 
                            type="button" 
                            @click="openAddForm()" 
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Add Project</span>
                        </button>
                    </div>

                </div>
            </div>

            {{-- Add Author Modal --}}
            <x-modals.add-author />

            {{-- Unsaved Changes Confirmation Modal --}}
            <x-profile.unsaved-modal />
        @endif
    </div>



