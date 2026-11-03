<?php

use App\Helpers\TimezoneHelper;
use App\Models\City;
use App\Models\Country;
use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\Seminar;
use App\Models\State;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Profile Conferences & Seminars Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's conferences, seminars, webinars, and events list,
 * inline add/edit form, organization autocomplete, cascading country/state/city selects (same as Add Journal),
 * and atomic synchronization with ReviewerProfile.
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
     * ID of the seminar currently being edited inline.
     */
    public ?string $editingId = null;

    /**
     * Flag indicating whether Add form is open at bottom.
     */
    public bool $showAddForm = false;

    /**
     * Livewire model properties for cascading location (same as Add Journal).
     */
    public string $country = '';

    public string $state = '';

    public string $city = '';

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
        $this->initDefaultCountry();
    }

    /**
     * Initialize country property (starts empty so required validation is enforced).
     */
    public function initDefaultCountry(): void
    {
        $this->country = '';
    }

    /**
     * Auto-detect country hook (kept for API compatibility).
     */
    public function autoDetectCountry(?string $clientTimezone = null): void
    {
        // Keep empty by default so user selects and gets validated
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
     * Cascading dropdown hooks (same as Add Journal).
     */
    public function updatedCountry(): void
    {
        $this->state = '';
        $this->city = '';
    }

    public function updatedState(): void
    {
        $this->city = '';
    }

    public function updatedCity(): void
    {
        // City selected
    }

    public function getStatesList(): array
    {
        return ! empty($this->country) ? State::getCachedByCountry($this->country) : [];
    }

    public function getCitiesList(): array
    {
        return ! empty($this->state) ? City::getCachedByState($this->state) : [];
    }

    /**
     * Reset location to default country matching Add Journal behavior.
     */
    public function resetLocation(): void
    {
        $this->country = '';
        $this->initDefaultCountry();
        $this->state = '';
        $this->city = '';
    }

    /**
     * Programmatically set location values when opening edit or add form.
     */
    public function setLocation(string $country = '', string $state = '', string $city = ''): void
    {
        $this->country = $country;
        $this->state = $state;
        $this->city = $city;
    }

    /**
     * Open the Add form.
     */
    public function openAdd(): void
    {
        $this->editingId = null;
        $this->showAddForm = true;
        $this->resetLocation();
    }

    /**
     * Open inline editing for a seminar record.
     */
    public function openEdit(string $id, string $country = '', string $state = '', string $city = ''): void
    {
        $this->showAddForm = false;
        $this->editingId = $id;
        $this->country = $country;
        $this->state = $state;
        $this->city = $city;
    }

    /**
     * Close form and reset state.
     */
    public function closeForm(): void
    {
        $this->showAddForm = false;
        $this->editingId = null;
        $this->resetLocation();
    }

    /**
     * Get active seminars/conferences for this user profile.
     * Scholar/user view ONLY displays records with status 1.
     *
     * @return list<array<string, mixed>>
     */
    public function getSeminarsProperty(): array
    {
        try {
            $reviewerProfile = $this->user?->reviewerProfile ?? ($this->user ? ReviewerProfile::where('user_id', (string) $this->user->_id)->first() : null);
            $seminarIds = (array) ($reviewerProfile->seminar ?? []);
            if (empty($seminarIds)) {
                return [];
            }

            // ONLY active records (status = 1 or '1' or true), ordered latest first
            $records = Seminar::whereIn('_id', $seminarIds)
                ->whereIn('status', [1, '1', true])
                ->orderBy('_id', 'desc')
                ->get();

            // Zero N+1: Batch fetch all host organizations in 1 single database call
            $hostOrgIds = [];
            foreach ($records as $s) {
                if (! empty($s->host_by)) {
                    $hostOrgIds[] = (string) $s->host_by;
                }
            }
            $hostOrgIds = array_values(array_unique(array_filter($hostOrgIds)));

            $orgsMap = ! empty($hostOrgIds)
                ? Organization::whereIn('_id', $hostOrgIds)
                    ->select(['_id', 'organization_name', 'logo'])
                    ->get()
                    ->keyBy(fn ($o) => (string) $o->_id)
                : collect();

            $countriesMap = collect(Country::getCachedAll())->keyBy('id');

            $formatted = [];
            foreach ($records as $s) {
                // Host organization name
                $orgName = '';
                if (! empty($s->host_by)) {
                    $hostId = (string) $s->host_by;
                    if (isset($orgsMap[$hostId])) {
                        $orgName = (string) $orgsMap[$hostId]->organization_name;
                    } else {
                        $orgName = (string) $s->host_by;
                    }
                }

                // Location resolution
                $countryName = '';
                if (! empty($s->country)) {
                    $cId = (string) $s->country;
                    if ($countriesMap->has($cId)) {
                        $countryName = (string) $countriesMap[$cId]['name'];
                    } elseif (is_string($s->country) && ! is_numeric($s->country)) {
                        $countryName = $s->country;
                    }
                }

                $stateName = '';
                if (! empty($s->country) && ! empty($s->state)) {
                    $states = State::getCachedByCountry($s->country);
                    $matchedState = collect($states)->firstWhere('id', (string) $s->state);
                    if ($matchedState && ! empty($matchedState['name'])) {
                        $stateName = (string) $matchedState['name'];
                    } elseif (is_string($s->state) && ! is_numeric($s->state)) {
                        $stateName = $s->state;
                    }
                }

                $cityName = '';
                if (! empty($s->state) && ! empty($s->city)) {
                    $cities = City::getCachedByState($s->state);
                    $matchedCity = collect($cities)->firstWhere('id', (string) $s->city);
                    if ($matchedCity && ! empty($matchedCity['name'])) {
                        $cityName = (string) $matchedCity['name'];
                    } elseif (is_string($s->city) && ! is_numeric($s->city)) {
                        $cityName = $s->city;
                    }
                }

                $locationParts = array_filter([$cityName, $stateName, $countryName]);
                $formattedLocation = implode(', ', $locationParts);

                $formatted[] = [
                    'id' => (string) $s->_id,
                    'title' => (string) ($s->title ?? 'Untitled Event'),
                    'organization' => $orgName,
                    'host_by' => (string) ($s->host_by ?? ''),
                    'type_event' => (string) ($s->type_event ?? 'Conference'),
                    'role' => (string) ($s->role ?? ''),
                    'month_year' => (string) ($s->month_year ?? ''),
                    'country' => (string) ($s->country ?? ''),
                    'state' => (string) ($s->state ?? ''),
                    'city' => (string) ($s->city ?? ''),
                    'location' => $formattedLocation,
                    'description' => (string) ($s->description ?? ''),
                    'status' => 1,
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
     * Provide cached dropdown datasets to the view (same as Add Journal).
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'countries' => Country::getCachedAll(),
            'states' => $this->getStatesList(),
            'cities' => $this->getCitiesList(),
        ];
    }

    /**
     * Save or update a seminar/conference entry.
     */
    public function saveSeminar(array $payload): bool
    {
        if (! $this->isOwnProfile) {
            $this->dispatch('toast', message: 'Unauthorized action.', type: 'danger');
            return false;
        }

        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $title = trim((string) ($payload['title'] ?? ''));
        $orgName = trim((string) ($payload['organization'] ?? ''));
        $typeEvent = trim((string) ($payload['type_event'] ?? ''));
        $role = trim((string) ($payload['role'] ?? ''));
        $monthYear = trim((string) ($payload['month_year'] ?? ''));
        $country = trim((string) ($this->country ?: ($payload['country'] ?? '')));
        $state = trim((string) ($this->state ?: ($payload['state'] ?? '')));
        $city = trim((string) ($this->city ?: ($payload['city'] ?? '')));
        $description = trim((string) ($payload['description'] ?? ''));

        if (mb_strlen($title) < 3) {
            $this->dispatch('toast', message: 'Conference / Event name is required (minimum 3 characters).', type: 'danger');
            return false;
        }

        if (empty($orgName)) {
            $this->dispatch('toast', message: 'Organization is required.', type: 'danger');
            return false;
        }

        if (empty($typeEvent)) {
            $this->dispatch('toast', message: 'Type of event is required.', type: 'danger');
            return false;
        }

        if (empty($role)) {
            $this->dispatch('toast', message: 'Role is required.', type: 'danger');
            return false;
        }

        if (empty($monthYear)) {
            $this->dispatch('toast', message: 'Date is required.', type: 'danger');
            return false;
        }

        if (empty($country)) {
            $this->dispatch('toast', message: 'Country is required.', type: 'danger');
            return false;
        }

        $availableStates = $this->getStatesList();
        if (! empty($availableStates) && empty($state)) {
            $this->dispatch('toast', message: 'State is required for the selected country.', type: 'danger');
            return false;
        }

        if (! empty($state)) {
            $availableCities = $this->getCitiesList();
            if (! empty($availableCities) && empty($city)) {
                $this->dispatch('toast', message: 'City is required for the selected state.', type: 'danger');
                return false;
            }
        }

        if (! empty($description) && (mb_strlen($description) < 50 || mb_strlen($description) > 1500)) {
            $this->dispatch('toast', message: 'Description must be between 50 and 1,500 characters.', type: 'danger');
            return false;
        }

        $formattedDate = Seminar::formatMonthYear($monthYear);
        if (! $formattedDate) {
            $this->dispatch('toast', message: 'Invalid Date format. Use "Month Year" (e.g. January 2025).', type: 'danger');
            return false;
        }

        // Record or find host organization
        $orgModel = Organization::recordOrFind($orgName);

        if ($id) {
            $seminar = Seminar::findOrFail($id);
        } else {
            $seminar = new Seminar();
            $seminar->user_id = (string) $this->user->_id;
        }

        $seminar->title = $title;
        $seminar->host_by = (string) $orgModel->_id;
        $seminar->type_event = $typeEvent;
        $seminar->role = ! empty($role) ? $role : null;
        $seminar->month_year = $formattedDate;
        $seminar->country = (string) $country;
        $seminar->state = (string) $state;
        $seminar->city = (string) $city;
        $seminar->description = $description;
        $seminar->status = 1;
        $seminar->save();

        // Atomically append _id to ReviewerProfile->seminar if not present
        $strId = (string) $seminar->_id;
        $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        if (! $profile) {
            ReviewerProfile::create([
                'user_id' => (string) $this->user->_id,
                'unique_id' => (string) ($this->user->unique_id ?? ''),
                'status' => (string) $this->user->getStatusCode(),
                'seminar' => [$strId],
            ]);
        } else {
            $currentSeminars = array_map('strval', (array) ($profile->seminar ?? []));
            if (! in_array($strId, $currentSeminars, true)) {
                $currentSeminars[] = $strId;
                $profile->seminar = array_values(array_unique($currentSeminars));
                $profile->save();
            }
        }

        $this->country = '';
        $this->state = '';
        $this->city = '';
        $this->editingId = null;
        $this->showAddForm = false;

        $this->dispatch('toast', message: $id ? 'Conference / Event updated successfully!' : 'Conference / Event added successfully!', type: 'success');

        return true;
    }

    /**
     * Delete a seminar record owned by this user.
     */
    public function deleteSeminar(string $id): bool
    {
        if (! $this->isOwnProfile) {
            $this->dispatch('toast', message: 'Unauthorized action.', type: 'danger');
            return false;
        }

        $seminar = Seminar::findOrFail($id);
        if ((string) $seminar->user_id !== (string) $this->user->_id) {
            $this->dispatch('toast', message: 'Unauthorized action.', type: 'danger');
            return false;
        }

        $seminar->delete();

        // Remove from ReviewerProfile->seminar array
        $profile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
        if ($profile && ! empty($profile->seminar)) {
            $list = array_values(array_filter(
                array_map('strval', (array) $profile->seminar),
                fn ($i) => $i !== $id
            ));
            $profile->seminar = $list;
            $profile->save();
        }

        $this->editingId = null;
        $this->showAddForm = false;

        $this->dispatch('toast', message: 'Conference / Event deleted successfully!', type: 'success');

        return true;
    }
}; ?>

<div id="conferences" class="scroll-mt-28">
    @if ($hasError)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 flex items-start gap-3 shadow-xs">
            <svg class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-xs">
                <p class="font-bold">Unable to load Conferences & Seminars</p>
                <p class="mt-0.5 text-amber-700 dark:text-amber-300">An unexpected error occurred while loading this section. Other sections remain functional.</p>
            </div>
        </div>
    @else
        <div 
            x-data="{
                showUnsavedModal: false,
                originalSnapshot: '',
                showAddForm: false,
                editingId: null,
                isSaving: false,

                // Organization Autocomplete State
                showOrgDropdown: false,
                orgSearchQuery: '',
                organizationOptions: [],
                orgPage: 1,
                orgHasMore: false,
                isSearchingOrg: false,
                isLoadingMoreOrg: false,
                orgDebounceTimer: null,
                orgAbortController: null,
                orgSearchSeq: 0,

                form: {
                    id: null,
                    title: '',
                    organization: '',
                    type_event: '',
                    role: '',
                    month_year: '',
                    country: '',
                    state: '',
                    city: '',
                    description: '',
                    status: 1
                },

                errors: {
                    title: false,
                    organization: false,
                    type_event: false,
                    role: false,
                    month_year: false,
                    country: false,
                    state: false,
                    city: false,
                    description: false
                },

                resetErrors() {
                    this.errors = {
                        title: false,
                        organization: false,
                        type_event: false,
                        role: false,
                        month_year: false,
                        country: false,
                        state: false,
                        city: false,
                        description: false
                    };
                },

                takeSnapshot() {
                    return JSON.stringify({
                        ...this.form,
                        country: this.$wire.get('country') || '',
                        state: this.$wire.get('state') || '',
                        city: this.$wire.get('city') || ''
                    });
                },

                isDirty() {
                    return this.originalSnapshot !== '' && this.takeSnapshot() !== this.originalSnapshot;
                },

                openAddForm() {
                    if ((this.showAddForm || this.editingId) && this.isDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                    this.resetErrors();
                    this.form = {
                        id: null,
                        title: '',
                        organization: '',
                        type_event: '',
                        role: '',
                        month_year: '',
                        country: '',
                        state: '',
                        city: '',
                        description: '',
                        status: 1
                    };
                    this.editingId = null;
                    this.showAddForm = true;
                    if (this.$wire.country !== '') {
                        this.$wire.country = '';
                    }
                    if (this.$wire.state !== '') {
                        this.$wire.state = '';
                    }
                    if (this.$wire.city !== '') {
                        this.$wire.city = '';
                    }
                    this.$nextTick(() => {
                        this.originalSnapshot = this.takeSnapshot();
                    });
                },

                openEditForm(item) {
                    if ((this.showAddForm || this.editingId) && this.isDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                    this.resetErrors();
                    this.form = {
                        id: item.id,
                        title: item.title || '',
                        organization: item.organization || '',
                        type_event: item.type_event || '',
                        role: item.role || '',
                        month_year: item.month_year || '',
                        country: item.country || '',
                        state: item.state || '',
                        city: item.city || '',
                        description: item.description || '',
                        status: 1
                    };
                    this.showAddForm = false;
                    this.editingId = item.id;
                    if (
                        (item.country && this.$wire.country !== item.country) ||
                        (item.state && this.$wire.state !== item.state) ||
                        (item.city && this.$wire.city !== item.city)
                    ) {
                        this.$wire.setLocation(item.country || '', item.state || '', item.city || '');
                    }
                    this.$nextTick(() => {
                        this.originalSnapshot = this.takeSnapshot();
                    });
                },

                closeForm(force = false) {
                    if (!force && this.isDirty()) {
                        this.showUnsavedModal = true;
                        return;
                    }
                    this.showUnsavedModal = false;
                    this.showAddForm = false;
                    this.editingId = null;
                    this.resetErrors();
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

                // Organization Autocomplete Logic
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

                onOrgScroll(e) {
                    const el = e.target;
                    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 50) {
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
                        this.fetchOrganizations(query, 1);
                    }, 250);
                },

                selectOrganization(org) {
                    this.form.organization = org;
                    this.showOrgDropdown = false;
                    this.orgSearchQuery = '';
                    this.errors.organization = false;
                },

                clearOrganization() {
                    this.form.organization = '';
                    this.orgSearchQuery = '';
                },

                hasExactOrgMatch() {
                    if (!this.orgSearchQuery || !this.orgSearchQuery.trim()) return false;
                    const q = this.orgSearchQuery.trim().toLowerCase();
                    return this.organizationOptions.some(item => (item || '').trim().toLowerCase() === q);
                },

                validate() {
                    this.resetErrors();
                    let valid = true;

                    if (!this.form.title || this.form.title.trim().length < 3) {
                        this.errors.title = true;
                        valid = false;
                    }

                    if (!this.form.organization || !this.form.organization.trim()) {
                        this.errors.organization = true;
                        valid = false;
                    }

                    if (!this.form.type_event || !this.form.type_event.trim()) {
                        this.errors.type_event = true;
                        valid = false;
                    }

                    if (!this.form.role || !this.form.role.trim()) {
                        this.errors.role = true;
                        valid = false;
                    }

                    if (!this.form.month_year || !this.form.month_year.trim()) {
                        this.errors.month_year = true;
                        valid = false;
                    }

                    const selectedCountry = this.$wire.get('country');
                    if (!selectedCountry) {
                        this.errors.country = true;
                        valid = false;
                    }

                    const selectedState = this.$wire.get('state');
                    if (!selectedState) {
                        this.errors.state = true;
                        valid = false;
                    }

                    const selectedCity = this.$wire.get('city');
                    if (!selectedCity) {
                        this.errors.city = true;
                        valid = false;
                    }

                    const descLen = (this.form.description || '').trim().length;
                    if (descLen > 0 && (descLen < 50 || descLen > 1500)) {
                        this.errors.description = true;
                        valid = false;
                    }

                    return valid;
                },

                saveItem() {
                    if (!this.validate()) return;
                    this.isSaving = true;

                    this.form.country = this.$wire.get('country') || '';
                    this.form.state = this.$wire.get('state') || '';
                    this.form.city = this.$wire.get('city') || '';

                    this.$wire.saveSeminar(this.form).then((res) => {
                        this.isSaving = false;
                        if (res !== false) {
                            this.originalSnapshot = '';
                            this.closeForm(true);
                        }
                    }).catch(() => {
                        this.isSaving = false;
                    });
                }
            }"
            x-init="
                $watch('$wire.country', val => { if (val) errors.country = false; });
                $watch('$wire.state', val => { if (val) errors.state = false; });
                $watch('$wire.city', val => { if (val) errors.city = false; });
            "
            class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden"
        >
            {{-- CARD HEADER --}}
            <div class="px-6 py-5 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-microphone"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <span>Conferences &amp; Seminars</span>
                            <span class="inline-flex items-center justify-center size-5 text-xs font-bold bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 rounded-full">
                                {{ count($this->seminars) }}
                            </span>
                        </h2>
                    </div>
                </div>
            </div>

            <div class="p-6">
                {{-- SEMINARS / CONFERENCES LIST --}}
                <div class="space-y-4">
                    @forelse($this->seminars as $seminar)
                        <div 
                            wire:key="seminar-card-{{ $seminar['id'] }}" 
                            class="p-4 rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 hover:border-brand dark:hover:border-brand hover:border-l-4 hover:border-l-brand hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                        >
                            {{-- DISPLAY MODE (Always visible in list) --}}
                            <div>
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1.5 flex-1 min-w-0">
                                        {{-- Title --}}
                                        <div>
                                            <h4 class="text-sm font-bold text-zinc-900 dark:text-white leading-snug">
                                                {{ $seminar['title'] }}
                                            </h4>
                                        </div>

                                        {{-- Meta Row 1: Host By, Location, Date --}}
                                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 flex-wrap">
                                            {{-- Host By --}}
                                            @if(!empty($seminar['organization']))
                                                <span>Host by: <strong class="text-zinc-700 dark:text-zinc-300 font-semibold">{{ $seminar['organization'] }}</strong></span>
                                            @endif

                                            {{-- Location --}}
                                            @if(!empty($seminar['location']))
                                                @if(!empty($seminar['organization']))
                                                    <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                @endif
                                                <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                    <i class="fa-solid fa-location-dot text-[11px] text-zinc-400"></i>
                                                    <span>{{ $seminar['location'] }}</span>
                                                </span>
                                            @endif

                                            {{-- Date (Month Year) --}}
                                            @if(!empty($seminar['month_year']))
                                                @if(!empty($seminar['organization']) || !empty($seminar['location']))
                                                    <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                @endif
                                                <span class="inline-flex items-center gap-1.5 font-medium text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-regular fa-calendar text-[11px] text-zinc-400"></i>
                                                    <span>{{ $seminar['month_year'] }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Meta Row 2: Below it: Type of event and Role --}}
                                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 flex-wrap pt-0.5">
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="text-zinc-500 dark:text-zinc-400 font-normal">Type of event:</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60">
                                                    {{ $seminar['type_event'] }}
                                                </span>
                                            </span>

                                            @if(!empty($seminar['role']))
                                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                <span class="inline-flex items-center gap-1.5">
                                                    <span class="text-zinc-500 dark:text-zinc-400 font-normal">Role:</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-brand border border-sky-200 dark:border-sky-800/50 capitalize">
                                                        {{ $seminar['role'] }}
                                                    </span>
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Description: 2 lines with View More / View Less --}}
                                        @if(!empty($seminar['description']))
                                            <div 
                                                x-data="{ 
                                                    expanded: false, 
                                                    isClamped: {{ mb_strlen(trim($seminar['description'])) > 140 ? 'true' : 'false' }},
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
                                                ><strong class="font-semibold text-zinc-800 dark:text-zinc-200">Description:</strong> {{ trim($seminar['description']) }}</div>
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

                                    {{-- Edit Button (Own Profile Only) --}}
                                    @if($isOwnProfile)
                                        <button 
                                            type="button" 
                                            x-show="editingId !== '{{ $seminar['id'] }}'"
                                            @click="openEditForm(@js($seminar))"
                                            class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            title="Edit Conference / Event"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- INLINE EDIT FORM (When editing this item, displayed directly below item info) --}}
                            <div 
                                x-show="editingId === '{{ $seminar['id'] }}'"
                                x-cloak
                                x-transition:enter="ease-out duration-200" 
                                x-transition:enter-start="opacity-0 -translate-y-2" 
                                x-transition:enter-end="opacity-100 translate-y-0"
                            >
                                <x-profile.seminar-form 
                                    :show-status="false" 
                                    :item-id="$seminar['id']" 
                                    :countries="$countries"
                                    :states="$states"
                                    :cities="$cities"
                                    :country="$country"
                                    :state="$state"
                                />
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 px-4 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800">
                            <i class="fa-solid fa-microphone text-2xl text-zinc-300 dark:text-zinc-600 mb-2 block"></i>
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">No conferences or seminars added yet.</p>
                            @if($isOwnProfile)
                                <p class="text-[11px] text-zinc-400 mt-0.5">Click "Add Conference / Event" below to showcase your participations.</p>
                            @endif
                        </div>
                    @endforelse
                </div>

                {{-- ADD FORM (At Bottom) --}}
                <div x-show="showAddForm" x-cloak class="mt-4">
                    <x-profile.seminar-form 
                        :show-status="false" 
                        :item-id="null" 
                        :countries="$countries" 
                        :states="$states" 
                        :cities="$cities" 
                        :country="$country" 
                        :state="$state" 
                    />
                </div>

                {{-- ADD CONFERENCE / EVENT BUTTON --}}
                @if($isOwnProfile)
                    <div class="mt-6 pt-2" x-show="!showAddForm">
                        <button 
                            type="button" 
                            @click="openAddForm()" 
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Add Conference / Event</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Unsaved Changes Confirmation Modal --}}
            @if($isOwnProfile)
                <x-profile.unsaved-modal />
            @endif
        </div>
    @endif
</div>
