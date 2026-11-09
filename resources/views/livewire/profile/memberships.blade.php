<?php

use App\Models\Country;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Profile Professional Memberships Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's professional memberships and scholarly societies,
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
     * ID of the membership record currently being edited inline.
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
     * Get active membership records for this user profile.
     * Public visitor view displays active records only.
     *
     * @return list<array<string, mixed>>
     */
    public function getMembershipsProperty(): array
    {
        try {
            if (! $this->user) {
                return [];
            }

            return Membership::getCachedForUser((string) $this->user->_id, true);
        } catch (\Throwable $e) {
            report($e);
            $this->hasError = true;

            return [];
        }
    }

    /**
     * Save or update a professional membership record.
     */
    public function saveMembership(array $payload): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $orgName = trim((string) ($payload['organization'] ?? $payload['organization_name'] ?? ''));
        $membershipType = trim((string) ($payload['membership_type'] ?? ''));
        $countryVal = trim((string) ($payload['country'] ?? ''));
        $joinDate = trim((string) ($payload['join_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        $websiteUrl = trim((string) ($payload['website_url'] ?? ''));

        if ($orgName === '' || $membershipType === '' || $countryVal === '' || $joinDate === '') {
            $this->dispatch('toast', message: 'Please fill in all required fields.', type: 'danger');

            return false;
        }

        $isContinuing = empty($endDate)
            || in_array(strtolower($endDate), ['present', 'ongoing', 'present / ongoing'], true)
            || ! empty($payload['continue_membership']);

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
                $membership = Membership::find($id);
                if (! $membership) {
                    $this->dispatch('toast', message: 'Membership record not found.', type: 'danger');

                    return false;
                }
            } else {
                $membership = new Membership;
                $membership->user_id = (string) $this->user->_id;
                $membership->status = 1;
            }

            $membership->organization_id = (string) $orgModel->_id;
            $membership->membership_type = $membershipType;
            $membership->country = $countryVal;
            $membership->join_date = $joinDate;
            $membership->end_date = $isContinuing ? null : $endDate;
            $membership->continue_membership = $isContinuing ? 1 : 0;
            $membership->website_url = $websiteUrl;
            $membership->save();

            // Atomic ReviewerProfile synchronization on create
            if (! $id) {
                $reviewerProfile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
                if ($reviewerProfile) {
                    ReviewerProfile::where('user_id', (string) $this->user->_id)
                        ->push('membership', (string) $membership->_id, true);
                } else {
                    ReviewerProfile::create([
                        'user_id' => (string) $this->user->_id,
                        'membership' => [(string) $membership->_id],
                    ]);
                }
            }

            Membership::forgetUserCache((string) $this->user->_id);

            $this->closeForm();
            $this->dispatch('toast', message: $id ? 'Professional membership updated successfully.' : 'Professional membership added successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'An error occurred while saving the membership record.', type: 'danger');

            return false;
        }
    }

    /**
     * Delete a professional membership record owned by this user.
     */
    public function deleteMembership(string $id): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $membership = Membership::find($id);
            if ($membership) {
                $membership->delete();
            }

            ReviewerProfile::where('user_id', (string) $this->user->_id)
                ->pull('membership', (string) $id);

            Membership::forgetUserCache((string) $this->user->_id);

            $this->closeForm();
            $this->dispatch('toast', message: 'Professional membership deleted successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to delete membership record.', type: 'danger');

            return false;
        }
    }
}; ?>

<div>
    {{-- Error boundary notice --}}
    @if($hasError)
        <div class="p-4 mb-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-200 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            <p class="text-xs">An error occurred while rendering the Professional Memberships section. Other sections remain functional.</p>
        </div>
    @endif

    {{-- Section Container --}}
    <div 
        id="memberships" 
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
                membership_type: '',
                country: '',
                join_date: '',
                end_date: 'Present / Ongoing',
                continue_membership: 1,
                website_url: ''
            },

            errors: {
                organization: false,
                membership_type: false,
                country: false,
                join_date: false,
                end_date: false,
                website_url: false
            },

            resetErrors() {
                this.errors = {
                    organization: false,
                    membership_type: false,
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
                    membership_type: '',
                    country: '',
                    join_date: '',
                    end_date: 'Present / Ongoing',
                    continue_membership: 1,
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
                    this.pendingAction = { type: 'edit', item };
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();
                this.showOrgDropdown = false;
                this.orgSearchQuery = '';

                this.form = {
                    id: item.id,
                    organization: item.organization_name || '',
                    membership_type: item.membership_type || '',
                    country: item.country || '',
                    join_date: item.join_date || '',
                    end_date: item.is_continuing ? 'Present / Ongoing' : (item.end_date || 'Present / Ongoing'),
                    continue_membership: item.is_continuing ? 1 : 0,
                    website_url: item.website_url || ''
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

            validateDates() {
                const startYear = parseInt(this.form.join_date);
                const endDate = (this.form.end_date || '').trim();

                if (endDate && endDate !== 'Present / Ongoing') {
                    const endYear = parseInt(endDate);
                    if (!isNaN(startYear) && !isNaN(endYear) && endYear < startYear) {
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

                if (!this.form.organization || this.form.organization.trim().length < 2) {
                    this.errors.organization = true;
                    valid = false;
                }

                if (!this.form.membership_type || this.form.membership_type.trim().length < 2) {
                    this.errors.membership_type = true;
                    valid = false;
                }

                const currentCountry = (this.form.country || '').trim();
                if (!currentCountry) {
                    this.errors.country = true;
                    valid = false;
                }

                if (!this.form.join_date || this.form.join_date.trim() === '') {
                    this.errors.join_date = true;
                    valid = false;
                }

                if (!this.validateDates()) {
                    valid = false;
                }

                return valid;
            },

            saveItem() {
                if (!this.validate()) return;
                this.isSaving = true;

                this.$wire.saveMembership(this.form).then((res) => {
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
                if (confirm('Are you sure you want to delete this professional membership record?')) {
                    this.$wire.deleteMembership(id).then((res) => {
                        if (res) {
                            this.originalSnapshot = '';
                            this.closeForm(true);
                        }
                    });
                }
            }
        }"
    >
        {{-- Section Header --}}
        <div class="px-6 py-5 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-sm">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <span>Professional Memberships</span>
                        <span class="inline-flex items-center justify-center size-5 text-xs font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 rounded-full">
                            {{ count($this->memberships) }}
                        </span>
                    </h2>
                    <p class="text-[11px] text-zinc-400 mt-0.5">Professional associations, scholarly societies, and memberships</p>
                </div>
            </div>
        </div>

        {{-- Section Content / List --}}
        <div class="p-6">
            @php $membershipList = $this->memberships; @endphp

            @if(count($membershipList) === 0 && !$showAddForm)
                {{-- Empty state --}}
                <div class="text-center py-10 px-4">
                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800/80 text-zinc-400 dark:text-zinc-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-id-card text-lg"></i>
                    </div>
                    <h4 class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">No Professional Memberships listed</h4>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1 max-w-sm mx-auto">
                        @if($isOwnProfile)
                            Click "Add Membership" below to showcase your academic societies and professional associations.
                        @else
                            This scholar has not listed any professional memberships yet.
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($membershipList as $item)
                        <div 
                            wire:key="profile-membership-item-{{ $item['id'] }}" 
                            class="p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-800/80 hover:border-brand dark:hover:border-brand hover:border-l-4 hover:border-l-brand hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                        >
                            {{-- Read-Only Display Mode (Always Visible in List) --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                    {{-- Membership ID Card Icon Badge --}}
                                    <div class="size-11 sm:size-12 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60 flex items-center justify-center shrink-0 shadow-2xs select-none">
                                        <i class="fa-solid fa-id-card text-lg sm:text-xl"></i>
                                    </div>

                                    <div class="space-y-1.5 flex-1 min-w-0">
                                        {{-- Membership Type & Continuing Badge --}}
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-white leading-snug break-words">
                                                {{ $item['membership_type'] }}
                                            </h4>
                                            @if($item['is_continuing'])
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/60">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>Present / Ongoing</span>
                                                </span>
                                            @elseif(!empty($item['end_date']))
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-medium bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                                    <i class="fa-regular fa-clock text-[10px] text-zinc-400"></i>
                                                    <span>{{ $item['join_date'] }} – {{ $item['end_date'] }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Row 1: Organization Name & Country --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{-- Organization Name --}}
                                            <span class="inline-flex items-center gap-1.5 text-zinc-800 dark:text-zinc-200">
                                                <i class="fa-solid fa-building-columns text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Organization:</strong> {{ $item['organization_name'] ?: 'Not Specified' }}</span>
                                            </span>

                                            {{-- Country --}}
                                            @if(!empty($item['country_name']) || !empty($item['country']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-earth-americas text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Country:</strong> {{ $item['country_name'] ?: $item['country'] }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Row 2: Period & Website URL --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400 pt-0.5">
                                            {{-- Period --}}
                                            <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                <i class="fa-regular fa-calendar text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Period:</strong> {{ $item['join_date'] }} – {{ $item['is_continuing'] ? 'Present' : $item['end_date'] }}</span>
                                            </span>

                                            {{-- Website URL Link --}}
                                            @if(!empty($item['website_url']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
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
                                            title="Edit Membership"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            {{-- Inline Edit Form Container --}}
                            @if($isOwnProfile)
                                <div x-show="editingId === '{{ $item['id'] }}'" x-cloak class="mt-4 pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                                    <x-profile.membership-form :show-status="false" :item-id="$item['id']" :countries="$countries" />
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Inline Add Form at Bottom --}}
            <div x-show="showAddForm" x-cloak class="mt-4">
                <x-profile.membership-form :show-status="false" :countries="$countries" />
            </div>

            {{-- BOTTOM ADD MEMBERSHIP BUTTON (Always At Bottom) --}}
            @if($isOwnProfile)
                <div class="mt-6 pt-2" x-show="!showAddForm">
                    <button 
                        type="button" 
                        @click="openAddForm()" 
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Membership</span>
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
                            You have unsaved changes in your membership form. Discard changes or keep editing?
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
