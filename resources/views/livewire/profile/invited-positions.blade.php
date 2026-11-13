<?php

use App\Models\Country;
use App\Models\InvitedPosition;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Profile Invited Positions Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's invited positions, visiting professorships, and guest roles,
 * inline add/edit form, organization autocomplete, country select,
 * and atomic synchronization with ReviewerProfile.
 */
new class extends Component
{
    /**
     * User Eloquent model instance whose profile is being viewed.
     */
    public ?User $user = null;

    /**
     * Flag indicating whether the viewing visitor is the profile owner.
     */
    public bool $isOwnProfile = false;

    /**
     * ID of the invited position record currently being edited inline.
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
     * Provide cached countries list for the searchable select.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'countries' => Country::getCachedAll(),
        ];
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
     * Get active invited position records for this user profile.
     * Public visitor view displays active records only.
     *
     * @return list<array<string, mixed>>
     */
    public function getPositionsProperty(): array
    {
        try {
            if (! $this->user) {
                return [];
            }

            return InvitedPosition::getCachedForUser((string) $this->user->_id, true);
        } catch (\Throwable $e) {
            report($e);
            $this->hasError = true;

            return [];
        }
    }

    /**
     * Save or update an invited position record.
     */
    public function savePosition(array $payload): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $orgName = trim((string) ($payload['organization'] ?? $payload['organization_name'] ?? ''));
        $role = trim((string) ($payload['role'] ?? ''));
        $countryVal = trim((string) ($payload['country'] ?? ''));
        $joinDate = trim((string) ($payload['join_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        $websiteUrl = trim((string) ($payload['website_url'] ?? ''));

        if ($orgName === '' || $role === '' || $countryVal === '' || $joinDate === '') {
            $this->dispatch('toast', message: 'Please fill in all required fields.', type: 'danger');

            return false;
        }

        $isContinuing = empty($endDate)
            || in_array(strtolower($endDate), ['present', 'ongoing', 'present / ongoing'], true)
            || ! empty($payload['continue_invited_position']);

        if (! $isContinuing && is_numeric($endDate) && is_numeric($joinDate)) {
            if ((int) $endDate < (int) $joinDate) {
                $this->dispatch('toast', message: 'End year must be equal to or greater than start year.', type: 'danger');

                return false;
            }
        }

        try {
            // Find or record organization in master collection
            $orgModel = Organization::recordOrFind($orgName);

            if ($id) {
                $position = InvitedPosition::find($id);
                if (! $position) {
                    $this->dispatch('toast', message: 'Invited position record not found.', type: 'danger');

                    return false;
                }
            } else {
                $position = new InvitedPosition;
                $position->user_id = (string) $this->user->_id;
                $position->status = 1;
            }

            $position->organization_id = (string) $orgModel->_id;
            $position->role = $role;
            $position->country = $countryVal;
            $position->join_date = $joinDate;
            $position->end_date = $isContinuing ? null : $endDate;
            $position->continue_invited_position = $isContinuing ? 1 : 0;
            $position->website_url = $websiteUrl;
            $position->save();

            // Atomic ReviewerProfile synchronization on create
            if (! $id) {
                $reviewerProfile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
                if ($reviewerProfile) {
                    ReviewerProfile::where('user_id', (string) $this->user->_id)
                        ->push('invited_position', (string) $position->_id, true);
                } else {
                    ReviewerProfile::create([
                        'user_id' => (string) $this->user->_id,
                        'invited_position' => [(string) $position->_id],
                    ]);
                }
            }

            InvitedPosition::forgetUserCache((string) $this->user->_id);

            $this->closeForm();
            $this->dispatch('toast', message: $id ? 'Invited position updated successfully.' : 'Invited position added successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'An error occurred while saving the invited position record.', type: 'danger');

            return false;
        }
    }

    /**
     * Delete an invited position record owned by this user.
     */
    public function deletePosition(string $id): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $position = InvitedPosition::find($id);
            if ($position) {
                $position->delete();
            }

            ReviewerProfile::where('user_id', (string) $this->user->_id)
                ->pull('invited_position', (string) $id);

            InvitedPosition::forgetUserCache((string) $this->user->_id);

            $this->closeForm();
            $this->dispatch('toast', message: 'Invited position deleted successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to delete invited position record.', type: 'danger');

            return false;
        }
    }
}; ?>

<div>
    {{-- Error boundary notice --}}
    @if($hasError)
        <div class="p-4 mb-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-200 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            <p class="text-xs">An error occurred while rendering the Invited Positions section. Other sections remain functional.</p>
        </div>
    @endif

    {{-- Section Container --}}
    <div 
        id="invited-positions" 
        class="scroll-mt-28 bg-white dark:bg-[#111c26] border border-zinc-200/80 dark:border-zinc-800 rounded-2xl shadow-xs overflow-hidden"
        x-data="{
            showAddForm: @entangle('showAddForm'),
            editingId: @entangle('editingId'),
            isSaving: false,
            showUnsavedModal: false,
            pendingAction: null,
            originalSnapshot: '',

            // Organization Autocomplete State
            showOrgDropdown: false,
            orgSearchQuery: '',
            organizationOptions: [],
            isSearchingOrg: false,
            isLoadingMoreOrg: false,
            orgPage: 1,
            orgHasMore: false,
            orgDebounceTimer: null,
            orgAbortController: null,
            orgSearchSeq: 0,

            init() {
            },

            fetchOrganizations(query = '', page = 1) {
                const currentSeq = ++this.orgSearchSeq;

                if (page === 1) {
                    this.isSearchingOrg = true;
                    if (this.orgAbortController) {
                        this.orgAbortController.abort();
                    }
                    this.orgAbortController = new AbortController();

                    fetch('/api/organizations?q=' + encodeURIComponent((query || '').trim()) + '&page=1&limit=20', {
                        signal: this.orgAbortController.signal
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (currentSeq !== this.orgSearchSeq) return;
                        const items = Array.isArray(data) ? data : (data.data || []);
                        this.organizationOptions = items.map(i => i.name).filter(Boolean);
                        this.orgHasMore = typeof data.has_more === 'boolean' ? data.has_more : (items.length >= 20);
                        this.orgPage = 1;
                        this.isSearchingOrg = false;
                    })
                    .catch(err => {
                        if (err && err.name === 'AbortError') return;
                        if (currentSeq === this.orgSearchSeq) {
                            this.organizationOptions = [];
                            this.orgHasMore = false;
                            this.isSearchingOrg = false;
                        }
                    });
                } else {
                    if (this.isLoadingMoreOrg || !this.orgHasMore) return;
                    this.isLoadingMoreOrg = true;

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

            searchOrganizations(query) {
                this.orgSearchQuery = query;
                clearTimeout(this.orgDebounceTimer);
                this.orgDebounceTimer = setTimeout(() => {
                    this.fetchOrganizations(query);
                }, 500);
            },

            clearOrganization() {
                this.form.organization = '';
                this.orgSearchQuery = '';
                this.errors.organization = false;
                this.fetchOrganizations('');
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

            hasExactOrgMatch() {
                const q = (this.orgSearchQuery || '').trim().toLowerCase();
                if (!q) return true;
                return this.organizationOptions.some(opt => (opt || '').toLowerCase() === q);
            },

            openOrgDropdown() {
                this.showOrgDropdown = true;
                if (!this.organizationOptions.length) {
                    this.fetchOrganizations('');
                }
                this.$nextTick(() => {
                    if (this.$refs.orgSearchInput) {
                        this.$refs.orgSearchInput.focus();
                    }
                });
            },

            onOrgScroll(e) {
                const el = e.target;
                if (el.scrollHeight - el.scrollTop - el.clientHeight < 40) {
                    if (this.orgHasMore && !this.isLoadingMoreOrg && !this.isSearchingOrg) {
                        this.fetchOrganizations(this.orgSearchQuery, this.orgPage + 1);
                    }
                }
            },

            form: {
                id: null,
                organization: '',
                role: '',
                country: '',
                join_date: '',
                end_date: 'Present / Ongoing',
                continue_invited_position: 1,
                website_url: ''
            },

            errors: {
                organization: false,
                role: false,
                country: false,
                join_date: false,
                end_date: false,
                website_url: false
            },

            resetErrors() {
                this.errors = {
                    organization: false,
                    role: false,
                    country: false,
                    join_date: false,
                    end_date: false,
                    website_url: false
                };
            },

            takeSnapshot() {
                return JSON.stringify({
                    form: this.form
                });
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
                    organization: '',
                    role: '',
                    country: '',
                    join_date: '',
                    end_date: 'Present / Ongoing',
                    continue_invited_position: 1,
                    website_url: ''
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
                this.showOrgDropdown = false;
                this.orgSearchQuery = '';

                const isContinuing = item.is_continuing || !item.end_date || item.end_date === 'Present / Ongoing';

                this.form = {
                    id: item.id,
                    organization: item.organization_name || '',
                    role: item.role || '',
                    country: String(item.country || ''),
                    join_date: String(item.join_date || ''),
                    end_date: isContinuing ? 'Present / Ongoing' : String(item.end_date || ''),
                    continue_invited_position: isContinuing ? 1 : 0,
                    website_url: item.website_url || item.webiste_url || ''
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

            validateDates() {
                if (this.form.end_date && this.form.end_date !== 'Present / Ongoing' && this.form.join_date) {
                    const startY = parseInt(this.form.join_date);
                    const endY = parseInt(this.form.end_date);
                    if (!isNaN(startY) && !isNaN(endY) && endY < startY) {
                        this.errors.end_date = true;
                        return false;
                    }
                }
                this.errors.end_date = false;
                return true;
            },

            validate() {
                this.resetErrors();
                let valid = true;

                if (!this.form.organization || this.form.organization.trim() === '') {
                    this.errors.organization = true;
                    valid = false;
                }

                if (!this.form.role || this.form.role.trim() === '') {
                    this.errors.role = true;
                    valid = false;
                }

                if (!this.form.country || String(this.form.country).trim() === '') {
                    this.errors.country = true;
                    valid = false;
                }

                if (!this.form.join_date || String(this.form.join_date).trim() === '') {
                    this.errors.join_date = true;
                    valid = false;
                }

                if (!this.validateDates()) {
                    valid = false;
                }

                if (this.form.website_url && this.form.website_url.trim() !== '') {
                    const urlVal = this.form.website_url.trim();
                    const urlPattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i;
                    if (!urlPattern.test(urlVal)) {
                        this.errors.website_url = true;
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
                    organization: this.form.organization,
                    role: this.form.role,
                    country: this.form.country,
                    join_date: this.form.join_date,
                    end_date: this.form.end_date === 'Present / Ongoing' ? null : this.form.end_date,
                    continue_invited_position: this.form.end_date === 'Present / Ongoing' ? 1 : 0,
                    website_url: this.form.website_url
                };

                this.$wire.savePosition(payload).then((success) => {
                    this.isSaving = false;
                    if (success) {
                        this.originalSnapshot = '';
                        this.closeForm(true);
                    }
                }).catch(() => {
                    this.isSaving = false;
                });
            },

            deleteItem(id) {
                if (confirm('Are you sure you want to delete this invited position record?')) {
                    this.$wire.deletePosition(id).then((res) => {
                        if (res && this.editingId === id) {
                            this.originalSnapshot = '';
                            this.closeForm(true);
                        }
                    });
                }
            }
        }"
    >
        {{-- Section Header (NO Add button here; only bottom button as requested) --}}
        <div class="px-6 py-4 border-b border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">&#x1F3DB;&#xFE0F;</span>
                <div>
                    <h2 class="text-base font-bold font-space text-zinc-900 dark:text-white">
                        Invited Positions ({{ count($this->positions) }})
                    </h2>
                    <p class="text-[11px] text-zinc-400 mt-0.5">Visiting professorships, honorary appointments, keynote speaker, and guest lectures</p>
                </div>
            </div>
        </div>

        {{-- Section Content / List --}}
        <div class="p-5 sm:p-6 sm:pt-4">
            @php $positionList = $this->positions; @endphp

            @if(count($positionList) === 0 && !$showAddForm)
                {{-- Empty state --}}
                <div class="text-center py-10 px-4">
                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800/80 text-zinc-400 dark:text-zinc-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-landmark text-lg"></i>
                    </div>
                    <h4 class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">No Invited Positions listed</h4>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1 max-w-sm mx-auto">
                        @if($isOwnProfile)
                            Click "Add Position" below to showcase your visiting professorships, guest roles, and honorary positions.
                        @else
                            This scholar has not listed any invited positions yet.
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach(array_slice($positionList, 0, $limit) as $item)
                        <div 
                            wire:key="profile-invited-position-item-{{ $item['id'] }}" 
                            class="p-4 sm:py-3.5 sm:px-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 hover:border-blue-500 dark:hover:border-blue-500 hover:border-l-4 hover:border-l-blue-500 hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                        >
                            {{-- Read-Only Display Mode (Always Visible in List) --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                    {{-- Landmark Icon Badge --}}
                                    <div class="size-10 sm:size-11 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60 flex items-center justify-center shrink-0 shadow-2xs select-none">
                                        <i class="fa-solid fa-landmark text-base sm:text-lg"></i>
                                    </div>

                                    <div class="space-y-1 flex-1 min-w-0">
                                        {{-- Role/Title --}}
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-white leading-snug break-words">
                                                {{ $item['role'] }}
                                            </h4>
                                        </div>

                                        {{-- Row 1: Organization Name --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{-- Organization Name --}}
                                            <span class="inline-flex items-center gap-1.5 text-zinc-800 dark:text-zinc-200">
                                                <i class="fa-solid fa-building-columns text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Organization:</strong> {{ $item['organization_name'] ?: 'Not Specified' }}</span>
                                            </span>
                                        </div>

                                        {{-- Row 2: Period, Country & Website URL --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{-- Period --}}
                                            <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                <i class="fa-regular fa-calendar text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Period:</strong> {{ $item['join_date'] }} â€“ {{ $item['is_continuing'] ? 'Present' : $item['end_date'] }}</span>
                                            </span>

                                            {{-- Country --}}
                                            @if(!empty($item['country_name']) || !empty($item['country']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">Â·</span>
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-earth-americas text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Country:</strong> {{ $item['country_name'] ?: $item['country'] }}</span>
                                                </span>
                                            @endif

                                            {{-- Website URL Link --}}
                                            @if(!empty($item['website_url']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">Â·</span>
                                                @php
                                                    $url = $item['website_url'];
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
                                                    <span>Website</span>
                                                </a>
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
                                            title="Edit Position"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            {{-- Inline Edit Form Container --}}
                            @if($isOwnProfile)
                                <div x-show="editingId === '{{ $item['id'] }}'" x-cloak class="mt-4 pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                                    <x-profile.invited-position-form :show-status="false" :item-id="$item['id']" :countries="$countries" />
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

            @endif

            {{-- Inline Add Form at Bottom --}}
            @if($isOwnProfile)
                <div x-show="showAddForm" x-cloak class="mt-4">
                    <x-profile.invited-position-form :show-status="false" item-id="new" :countries="$countries" />
                </div>
            @endif

            {{-- BOTTOM ADD POSITION BUTTON (Always At Bottom - Same Design as Patents) --}}
            @if($isOwnProfile || count($positionList) > $limit)
                <div class="mt-6 pt-2 relative flex items-center justify-between gap-3 flex-wrap min-h-[34px]" x-show="!showAddForm">
                    @if($isOwnProfile)
                        <button 
                        type="button" 
                        @click="openAddForm()" 
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Position</span>
                    </button>
                    @else
                        <span></span>
                    @endif
                    @if(count($positionList) > $limit)
                    <button 
                        type="button" 
                        wire:click="loadMore"
                        wire:loading.attr="disabled"
                        class="md:absolute md:left-1/2 md:-translate-x-1/2 inline-flex items-center gap-2 px-4 py-1.5 text-xs font-bold text-brand bg-brand/5 hover:bg-brand/10 border border-brand/20 rounded-xl transition cursor-pointer disabled:opacity-60"
                    >
                        <i wire:loading.remove wire:target="loadMore" class="fa-solid fa-chevron-down text-[10px]"></i>
                        <i wire:loading wire:target="loadMore" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Load More ({{ count($positionList) - $limit }})</span>
                    </button>
                @endif
                    <span class="hidden md:block"></span>
                </div>
                @endif
        </div>

        {{-- UNSAVED CHANGES CONFIRMATION MODAL (Operated strictly on frontend by Alpine) --}}
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

    </div>
</div>

