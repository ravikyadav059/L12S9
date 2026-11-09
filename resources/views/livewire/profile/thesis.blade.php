<?php

use App\Models\Organization;
use App\Models\Phd;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Profile Thesis Guided Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's PhD & thesis supervision list, inline add/edit form,
 * organization search, and atomic synchronization with ReviewerProfile.
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
     * ID of the thesis currently being edited inline.
     */
    public ?string $editingId = null;

    /**
     * Flag indicating whether Add form is open at bottom.
     */
    public bool $showAddForm = false;

    /**
     * Error boundary flag.
     */
    public bool $hasError = false;

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
     * Reset form state on server.
     */
    public function closeForm(): void
    {
        $this->editingId = null;
        $this->showAddForm = false;
    }

    /**
     * Get active PhD / thesis guided records for this user profile.
     * Scholar/user view ONLY displays records with status 1.
     *
     * @return list<array<string, mixed>>
     */
    public function getThesesProperty(): array
    {
        try {
            if (! $this->user) {
                return [];
            }

            return Phd::getCachedForUser((string) $this->user->_id, true);
        } catch (\Throwable $e) {
            report($e);
            $this->hasError = true;

            return [];
        }
    }

    /**
     * Save or update a PhD / thesis record.
     */
    public function saveThesis(array $payload): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $phdName = trim((string) ($payload['phd_name'] ?? ''));
        $phdTitle = trim((string) ($payload['phd_title'] ?? ''));
        $institutionName = trim((string) ($payload['institution'] ?? ''));
        $thesisType = trim((string) ($payload['thesis_type'] ?? ''));
        $researchArea = trim((string) ($payload['research_area'] ?? ''));
        $phdStatus = trim((string) ($payload['phd_status'] ?? ''));

        if ($phdName === '' || $phdTitle === '' || $institutionName === '' || $thesisType === '' || $researchArea === '' || $phdStatus === '') {
            $this->dispatch('toast', message: 'Please fill in all required fields.', type: 'danger');

            return false;
        }

        if (! in_array($phdStatus, ['Ongoing', 'Completed'], true)) {
            $this->dispatch('toast', message: 'Please select a valid status (Ongoing or Completed).', type: 'danger');

            return false;
        }

        try {
            // Find or record institution organization
            $orgModel = Organization::recordOrFind($institutionName);

            if ($id) {
                $phd = Phd::find($id);
                if (! $phd) {
                    $this->dispatch('toast', message: 'Thesis record not found.', type: 'danger');

                    return false;
                }
            } else {
                $phd = new Phd;
                $phd->user_id = (string) $this->user->_id;
                $phd->status = 1;
            }

            $phd->phd_name = $phdName;
            $phd->phd_title = $phdTitle;
            $phd->institution_id = (string) $orgModel->_id;
            $phd->thesis_type = $thesisType;
            $phd->research_area = $researchArea;
            $phd->phd_status = $phdStatus;
            $phd->save();

            // Atomic ReviewerProfile synchronization on create
            if (! $id) {
                $reviewerProfile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
                if ($reviewerProfile) {
                    ReviewerProfile::where('user_id', (string) $this->user->_id)
                        ->push('phd', (string) $phd->_id, true);
                } else {
                    ReviewerProfile::create([
                        'user_id' => (string) $this->user->_id,
                        'phd' => [(string) $phd->_id],
                    ]);
                }
            }

            $this->closeForm();
            $this->dispatch('toast', message: $id ? 'Thesis record updated successfully.' : 'Thesis record added successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to save thesis record. Please try again.', type: 'danger');

            return false;
        }
    }

    /**
     * Delete a PhD / thesis record.
     */
    public function deleteThesis(string $id): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $phd = Phd::find($id);
            if ($phd) {
                $phd->delete();
            }

            ReviewerProfile::where('user_id', (string) $this->user->_id)
                ->pull('phd', (string) $id);

            $this->closeForm();
            $this->dispatch('toast', message: 'Thesis record deleted successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to delete thesis record.', type: 'danger');

            return false;
        }
    }
};
?>

<div>
    @if ($hasError)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-start gap-3 shadow-xs mb-4">
            <svg class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-semibold">Thesis Guided Section Notice</h3>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    An error occurred while loading this section. Other profile sections remain unaffected.
                </p>
            </div>
        </div>
    @endif

    {{-- THESIS GUIDED CARD CONTAINER --}}
    <div 
        id="thesis" 
        class="scroll-mt-28 bg-white dark:bg-[#111c26] border border-zinc-200/80 dark:border-zinc-800 rounded-2xl shadow-xs overflow-hidden"
        x-data="{
            showAddForm: @entangle('showAddForm'),
            editingId: @entangle('editingId'),
            isSaving: false,
            showUnsavedModal: false,
            pendingAction: null,
            originalSnapshot: '',

            // Autocomplete: Organizations
            organizationOptions: [],
            orgSearchQuery: '',
            orgPage: 1,
            orgHasMore: false,
            isSearchingOrg: false,
            isLoadingMoreOrg: false,
            showOrgDropdown: false,
            orgDebounceTimer: null,
            orgAbortController: null,
            orgSearchSeq: 0,

            init() {
                this.fetchOrganizations('');
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
                }, 400);
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
                return this.organizationOptions.some(org => org.toLowerCase() === q);
            },

            form: {
                id: null,
                phd_name: '',
                phd_title: '',
                institution: '',
                thesis_type: '',
                research_area: '',
                phd_status: ''
            },

            errors: {
                phd_name: false,
                phd_title: false,
                institution: false,
                thesis_type: false,
                research_area: false,
                phd_status: false
            },

            resetErrors() {
                this.errors = {
                    phd_name: false,
                    phd_title: false,
                    institution: false,
                    thesis_type: false,
                    research_area: false,
                    phd_status: false
                };
            },

            takeSnapshot() {
                return JSON.stringify(this.form);
            },

            isDirty() {
                return this.originalSnapshot !== '' && this.takeSnapshot() !== this.originalSnapshot;
            },

            openAddForm() {
                this.$dispatch('close-popovers');
                if ((this.showAddForm || this.editingId) && this.isDirty()) {
                    this.pendingAction = 'openAdd';
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();
                this.showOrgDropdown = false;
                this.orgSearchQuery = '';

                this.form = {
                    id: null,
                    phd_name: '',
                    phd_title: '',
                    institution: '',
                    thesis_type: '',
                    research_area: '',
                    phd_status: ''
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
                    this.pendingAction = { type: 'edit', item };
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();
                this.showOrgDropdown = false;
                this.orgSearchQuery = '';

                this.form = {
                    id: item.id,
                    phd_name: item.phd_name || '',
                    phd_title: item.phd_title || '',
                    institution: item.institution_name || '',
                    thesis_type: item.thesis_type || '',
                    research_area: item.research_area || '',
                    phd_status: item.phd_status || ''
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
                this.showOrgDropdown = false;
                this.resetErrors();
                this.originalSnapshot = '';
                this.$wire.closeForm();
            },

            confirmDiscard() {
                this.showUnsavedModal = false;
                const act = this.pendingAction;
                this.pendingAction = null;
                this.originalSnapshot = '';

                if (act === 'close') {
                    this.closeForm(true);
                } else if (act === 'openAdd') {
                    this.closeForm(true);
                    this.openAddForm();
                } else if (act && act.type === 'edit') {
                    this.closeForm(true);
                    this.openEditForm(act.item);
                }
            },

            cancelDiscard() {
                this.pendingAction = null;
                this.showUnsavedModal = false;
            },

            validate() {
                this.resetErrors();
                let valid = true;

                if (!this.form.phd_name || this.form.phd_name.trim().length < 2) {
                    this.errors.phd_name = true;
                    valid = false;
                }

                if (!this.form.phd_title || this.form.phd_title.trim().length < 2) {
                    this.errors.phd_title = true;
                    valid = false;
                }

                if (!this.form.institution || this.form.institution.trim().length < 2) {
                    this.errors.institution = true;
                    valid = false;
                }

                if (!this.form.thesis_type || this.form.thesis_type.trim() === '') {
                    this.errors.thesis_type = true;
                    valid = false;
                }

                if (!this.form.research_area || this.form.research_area.trim() === '') {
                    this.errors.research_area = true;
                    valid = false;
                }

                if (!this.form.phd_status || (this.form.phd_status !== 'Ongoing' && this.form.phd_status !== 'Completed')) {
                    this.errors.phd_status = true;
                    valid = false;
                }

                return valid;
            },

            saveItem() {
                if (!this.validate()) return;
                this.isSaving = true;

                this.$wire.saveThesis(this.form).then((res) => {
                    this.isSaving = false;
                    if (res) {
                        this.showAddForm = false;
                        this.editingId = null;
                        this.originalSnapshot = '';
                    }
                }).catch(() => {
                    this.isSaving = false;
                });
            },

            deleteItem(id) {
                this.confirmDelete(id);
            },

            confirmDelete(id) {
                if (confirm('Are you sure you want to delete this thesis record?')) {
                    this.$wire.deleteThesis(id).then((res) => {
                        if (res && this.editingId === id) {
                            this.closeForm(true);
                        }
                    });
                }
            }
        }"
    >
        {{-- Section Header --}}
        <div class="p-6 pb-4 flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/80 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <span class="size-9 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60 flex items-center justify-center text-sm shadow-2xs">
                    📑
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold font-space text-base text-zinc-900 dark:text-zinc-100">
                            Thesis Guided
                        </h3>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-mono">
                            {{ count($this->theses) }}
                        </span>
                    </div>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">
                        Doctoral & research thesis supervision, guidance, and academic mentorship
                    </p>
                </div>
            </div>
        </div>

        {{-- Section Content / List --}}
        <div class="p-6">
            @php $thesisList = $this->theses; @endphp

            @if(count($thesisList) === 0 && !$showAddForm)
                {{-- Empty state --}}
                <div class="text-center py-10 px-4">
                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800/80 text-zinc-400 dark:text-zinc-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-graduation-cap text-lg"></i>
                    </div>
                    <h4 class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">No Thesis Guided records listed</h4>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1 max-w-sm mx-auto">
                        @if($isOwnProfile)
                            Click "Add Thesis" below to showcase the research projects and theses you have guided.
                        @else
                            This scholar has not listed any thesis guidance records yet.
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($thesisList as $item)
                        <div 
                            wire:key="profile-thesis-item-{{ $item['id'] }}" 
                            class="p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 hover:border-brand dark:hover:border-brand hover:border-l-4 hover:border-l-brand hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                        >
                            {{-- Read-Only Display Mode (Always Visible in List) --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                    {{-- Graduation Cap Icon Badge --}}
                                    <div class="size-11 sm:size-12 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60 flex items-center justify-center shrink-0 shadow-2xs select-none">
                                        <i class="fa-solid fa-graduation-cap text-lg"></i>
                                    </div>

                                    <div class="min-w-0 flex-1 space-y-2">
                                        {{-- Thesis Title --}}
                                        <h4 class="font-bold text-sm text-zinc-900 dark:text-white leading-snug break-words">
                                            {{ $item['phd_title'] }}
                                        </h4>

                                        {{-- Row 1: Name, Type, Status --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1.5 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{-- Name --}}
                                            <span class="inline-flex items-center gap-1.5 text-zinc-800 dark:text-zinc-200">
                                                <i class="fa-regular fa-user text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Name:</strong> {{ $item['phd_name'] }}</span>
                                            </span>

                                            {{-- Type --}}
                                            @if(!empty($item['thesis_type']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <strong class="font-semibold text-zinc-700 dark:text-zinc-300">Type:</strong>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700/60">
                                                        {{ $item['thesis_type'] }}
                                                    </span>
                                                </span>
                                            @endif

                                            {{-- Status --}}
                                            <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                            <span class="inline-flex items-center gap-1.5">
                                                <strong class="font-semibold text-zinc-700 dark:text-zinc-300">Status:</strong>
                                                @if($item['phd_status'] === 'Completed')
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/60">
                                                        <i class="fa-solid fa-check text-[10px]"></i>
                                                        <span>Completed</span>
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200/80 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/60">
                                                        <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        <span>Ongoing</span>
                                                    </span>
                                                @endif
                                            </span>
                                        </div>

                                        {{-- Row 2: Institution Name below it & Area --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400 pt-0.5">
                                            {{-- Institution Name --}}
                                            <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                <i class="fa-solid fa-building-columns text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Institution Name:</strong> {{ $item['institution_name'] ?: 'Not Specified' }}</span>
                                            </span>

                                            {{-- Major Area of Research --}}
                                            @if(!empty($item['research_area']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                <span class="inline-flex items-center gap-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                                    <i class="fa-solid fa-microscope text-[10px] text-zinc-400"></i>
                                                    <span class="font-medium text-zinc-600 dark:text-zinc-300">Area:</span>
                                                    <span>{{ $item['research_area'] }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Action Buttons for Own Profile (Edit Only; Delete is inside form) --}}
                                @if($isOwnProfile)
                                    <div class="flex items-center gap-1 shrink-0 ml-2">
                                        <button 
                                            type="button" 
                                            x-show="editingId !== '{{ $item['id'] }}'"
                                            @click="openEditForm({{ json_encode($item) }})"
                                            class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            title="Edit Thesis"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            {{-- Inline Edit Form Container --}}
                            @if($isOwnProfile)
                                <div x-show="editingId === '{{ $item['id'] }}'" x-cloak class="mt-4 pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                                    <x-profile.thesis-form :show-status="false" :item-id="$item['id']" />
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Inline Add Form at Bottom --}}
            <div x-show="showAddForm" x-cloak class="mt-4">
                <x-profile.thesis-form :show-status="false" />
            </div>

            {{-- BOTTOM ADD THESIS BUTTON (Always At Bottom) --}}
            @if($isOwnProfile)
                <div class="mt-6 pt-2" x-show="!showAddForm">
                    <button 
                        type="button" 
                        @click="openAddForm()" 
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Thesis</span>
                    </button>
                </div>
            @endif
        </div>

        {{-- UNSAVED CHANGES MODAL --}}
        <div 
            x-show="showUnsavedModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/50 backdrop-blur-xs"
            @keydown.escape.window="cancelDiscard()"
        >
            <div 
                @click.outside="cancelDiscard()"
                class="bg-white dark:bg-zinc-900 rounded-2xl max-w-sm w-full p-5 border border-zinc-200 dark:border-zinc-800 shadow-2xl space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Unsaved Changes</h4>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            You have unsaved changes in your thesis form. Discard changes or keep editing?
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <button 
                        type="button" 
                        @click="cancelDiscard()"
                        class="px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-xl transition cursor-pointer"
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

    </div>
</div>

