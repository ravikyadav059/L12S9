<?php

use App\Models\Category;
use App\Models\Country;
use App\Models\Patent;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * User instance being edited in admin.
     */
    public User $user;

    /**
     * Filter patent records by status: 'all', '1' (Active), '0' (Inactive).
     */
    public string $statusFilter = 'all';

    /**
     * ID of the patent record currently being edited inline.
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
    public function mount(User $user): void
    {
        $this->user = $user;
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
     * Provide cached countries and categories for form controls.
     * Categories are cached for 1 week.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'countries' => Country::getCachedAll(),
            'categories' => Category::getCachedAll(),
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
     * Get patent records for the user being edited.
     * Admin view shows active, inactive, or all records depending on filter.
     *
     * @return list<array<string, mixed>>
     */
    public function getPatentsProperty(): array
    {
        try {
            if ($this->statusFilter === 'all') {
                return Patent::getCachedForUser((string) $this->user->_id, false);
            }

            if ($this->statusFilter === '1') {
                return Patent::getCachedForUser((string) $this->user->_id, true);
            }

            // Inactive filter (status = 0)
            $reviewerProfile = $this->user->reviewerProfile ?? ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
            $patentIds = (array) ($reviewerProfile->patent ?? []);

            $query = Patent::query();
            if (! empty($patentIds)) {
                $query->where(function ($q) use ($patentIds) {
                    $q->whereIn('_id', $patentIds)
                        ->orWhere('user_id', (string) $this->user->_id);
                });
            } else {
                $query->where('user_id', (string) $this->user->_id);
            }

            $records = $query->whereIn('status', [0, '0', false, null])
                ->with('category')
                ->orderBy('_id', 'desc')
                ->get();

            $countriesMap = collect(Country::getCachedAll())->keyBy('id');
            $categoriesMap = collect(Category::getCachedAll())->keyBy('id');

            $formatted = [];
            foreach ($records as $p) {
                $cVal = (string) ($p->country ?? '');
                $countryName = '';
                if ($cVal !== '') {
                    if ($countriesMap->has($cVal)) {
                        $countryName = (string) ($countriesMap[$cVal]['name'] ?? '');
                    } elseif (! is_numeric($cVal)) {
                        $countryName = $cVal;
                    }
                }

                $catVal = (string) ($p->category_id ?? '');
                $categoryName = '';
                if ($catVal !== '') {
                    if ($categoriesMap->has($catVal)) {
                        $categoryName = (string) ($categoriesMap[$catVal]['name'] ?? '');
                    } elseif ($p->category?->category_title) {
                        $categoryName = (string) $p->category->category_title;
                    } else {
                        $matchedCat = $categoriesMap->first(function ($c) use ($catVal) {
                            return strcasecmp((string) ($c['name'] ?? ''), $catVal) === 0;
                        });
                        $categoryName = $matchedCat ? (string) $matchedCat['name'] : $catVal;
                    }
                }

                $formatted[] = [
                    'id' => (string) $p->_id,
                    'title' => (string) ($p->title ?? ''),
                    'patent_no' => (string) ($p->patent_no ?? ''),
                    'inventor' => (string) ($p->inventor ?? ''),
                    'assignee' => (string) ($p->assignee ?? ''),
                    'application_no' => (string) ($p->application_no ?? ''),
                    'country' => $cVal,
                    'country_name' => $countryName,
                    'category_id' => $catVal,
                    'category_name' => $categoryName,
                    'filing_date' => (string) ($p->filing_date ?? ''),
                    'publication_date' => (string) ($p->publication_date ?? ''),
                    'description' => (string) ($p->description ?? ''),
                    'status' => 0,
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
     * Toggle patent record status between 1 (Active) and 0 (Inactive).
     */
    public function togglePatentStatus(string $id): void
    {
        try {
            $patent = Patent::findOrFail($id);
            $newStatus = ((int) $patent->status === 1) ? 0 : 1;
            $patent->status = $newStatus;
            $patent->save();

            Patent::forgetUserCache((string) $this->user->_id);

            $label = $newStatus === 1 ? 'Active' : 'Inactive';
            $this->dispatch('toast', message: "Patent record set to {$label}.", type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to update patent record status.', type: 'danger');
        }
    }

    /**
     * Save or update a patent record from admin.
     */
    public function savePatent(array $payload): bool
    {
        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $title = trim((string) ($payload['title'] ?? ''));
        $patentNo = trim((string) ($payload['patent_no'] ?? ''));
        $inventor = trim((string) ($payload['inventor'] ?? ''));
        $assignee = trim((string) ($payload['assignee'] ?? ''));
        $applicationNo = trim((string) ($payload['application_no'] ?? ''));
        $country = trim((string) ($payload['country'] ?? ''));
        $categoryId = trim((string) ($payload['category_id'] ?? ''));
        $filingDate = trim((string) ($payload['filing_date'] ?? ''));
        $publicationDate = trim((string) ($payload['publication_date'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $statusVal = isset($payload['status']) ? (int) $payload['status'] : 1;

        if ($title === '' || $patentNo === '' || $inventor === '' || $country === '' || $filingDate === '' || $description === '') {
            $this->dispatch('toast', message: 'Please fill in all required fields.', type: 'danger');

            return false;
        }

        try {
            if ($id) {
                $patent = Patent::find($id);
                if (! $patent) {
                    $this->dispatch('toast', message: 'Patent record not found.', type: 'danger');

                    return false;
                }
            } else {
                $patent = new Patent;
                $patent->user_id = (string) $this->user->_id;
            }

            $patent->title = $title;
            $patent->patent_no = $patentNo;
            $patent->inventor = $inventor;
            $patent->assignee = $assignee;
            $patent->application_no = $applicationNo;
            $patent->country = $country;
            $patent->category_id = $categoryId ?: null;
            $patent->filing_date = $filingDate;
            $patent->publication_date = $publicationDate;
            $patent->description = $description;
            $patent->status = $statusVal;
            $patent->save();

            // Atomic ReviewerProfile synchronization on create
            if (! $id) {
                $reviewerProfile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
                if ($reviewerProfile) {
                    ReviewerProfile::where('user_id', (string) $this->user->_id)
                        ->push('patent', (string) $patent->_id, true);
                } else {
                    ReviewerProfile::create([
                        'user_id' => (string) $this->user->_id,
                        'patent' => [(string) $patent->_id],
                    ]);
                }
            }

            Patent::forgetUserCache((string) $this->user->_id);

            $this->closeForm();
            $this->dispatch('toast', message: $id ? 'Patent record updated successfully.' : 'Patent record created successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to save patent record.', type: 'danger');

            return false;
        }
    }

    /**
     * Delete a patent record from admin.
     */
    public function deletePatent(string $id): bool
    {
        try {
            $patent = Patent::findOrFail($id);
            $patent->delete();

            ReviewerProfile::where('user_id', (string) $this->user->_id)
                ->pull('patent', $id);

            Patent::forgetUserCache((string) $this->user->_id);

            $this->closeForm();
            $this->dispatch('toast', message: 'Patent record deleted successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to delete patent record.', type: 'danger');

            return false;
        }
    }
}; ?>

<div>
    {{-- Error Boundary Fallback Alert --}}
    @if($hasError)
        <div class="p-4 mb-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-200 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            <p class="text-xs">An error occurred while rendering the Patents management section.</p>
        </div>
    @endif

    {{-- Admin Section Container --}}
    <div 
        id="admin-user-patents"
        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xs overflow-hidden"
        x-data="{
            showAddForm: @entangle('showAddForm'),
            editingId: @entangle('editingId'),
            isSaving: false,
            showUnsavedModal: false,
            pendingAction: null,
            originalSnapshot: '',

            form: {
                id: null,
                title: '',
                patent_no: '',
                inventor: '',
                assignee: '',
                application_no: '',
                country: '',
                category_id: '',
                filing_date: '',
                publication_date: '',
                description: '',
                status: 1
            },

            errors: {
                title: false,
                patent_no: false,
                inventor: false,
                country: false,
                filing_date: false,
                description: false
            },

            resetErrors() {
                this.errors = {
                    title: false,
                    patent_no: false,
                    inventor: false,
                    country: false,
                    filing_date: false,
                    description: false
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

                this.form = {
                    id: null,
                    title: '',
                    patent_no: '',
                    inventor: '',
                    assignee: '',
                    application_no: '',
                    country: '',
                    category_id: '',
                    filing_date: '',
                    publication_date: '',
                    description: '',
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
                    this.pendingAction = { type: 'edit', item };
                    this.showUnsavedModal = true;
                    return;
                }
                this.resetErrors();

                this.form = {
                    id: item.id,
                    title: item.title || '',
                    patent_no: item.patent_no || '',
                    inventor: item.inventor || '',
                    assignee: item.assignee || '',
                    application_no: item.application_no || '',
                    country: item.country || '',
                    category_id: item.category_id || '',
                    filing_date: item.filing_date || '',
                    publication_date: item.publication_date || '',
                    description: item.description || '',
                    status: (item.status === 1 || item.status === '1' || item.status === true) ? 1 : 0
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

            validate() {
                this.resetErrors();
                let valid = true;

                if (!this.form.title || this.form.title.trim().length < 2) {
                    this.errors.title = true;
                    valid = false;
                }

                if (!this.form.patent_no || this.form.patent_no.trim() === '') {
                    this.errors.patent_no = true;
                    valid = false;
                }

                if (!this.form.inventor || this.form.inventor.trim() === '') {
                    this.errors.inventor = true;
                    valid = false;
                }

                const currentCountry = (this.form.country || '').trim();
                if (!currentCountry) {
                    this.errors.country = true;
                    valid = false;
                }

                if (!this.form.filing_date || this.form.filing_date.trim() === '') {
                    this.errors.filing_date = true;
                    valid = false;
                }

                if (!this.form.description || this.form.description.trim().length < 5) {
                    this.errors.description = true;
                    valid = false;
                }

                return valid;
            },

            saveItem() {
                if (!this.validate()) return;
                this.isSaving = true;

                this.$wire.savePatent(this.form).then((res) => {
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
                if (confirm('Are you sure you want to delete this patent record?')) {
                    this.$wire.deletePatent(id).then((res) => {
                        if (res) {
                            this.originalSnapshot = '';
                            this.closeForm(true);
                        }
                    });
                }
            }
        }"
    >
        {{-- Admin Section Header (Status Filters, NO Add Button here; only bottom button) --}}
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 dark:text-amber-400 flex items-center justify-center text-lg shadow-2xs">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div>
                    <h2 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                        <span>Patents</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60">
                            {{ count($this->patents) }}
                        </span>
                    </h2>
                    <p class="text-xs text-zinc-400 mt-0.5">Manage granted patents, disclosures, and intellectual property</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                {{-- Status Filter Pill Controls --}}
                <div class="inline-flex items-center p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl text-xs">
                    <button 
                        type="button" 
                        wire:click="$set('statusFilter', 'all')"
                        :class="'{{ $statusFilter }}' === 'all' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                    >
                        All
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('statusFilter', '1')"
                        :class="'{{ $statusFilter }}' === '1' ? 'bg-white dark:bg-zinc-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                    >
                        Active
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('statusFilter', '0')"
                        :class="'{{ $statusFilter }}' === '0' ? 'bg-white dark:bg-zinc-900 text-red-600 dark:text-red-400 shadow-xs font-bold' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                    >
                        Inactive
                    </button>
                </div>
            </div>
        </div>

        {{-- Admin Section Body / List --}}
        <div class="p-5 sm:p-6 sm:pt-4">
            @php $patentList = $this->patents; @endphp

            @if(count($patentList) === 0 && !$showAddForm)
                {{-- Empty state --}}
                <div class="text-center py-10 px-4">
                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800/80 text-zinc-400 dark:text-zinc-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-lightbulb text-lg"></i>
                    </div>
                    <h4 class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">No patent records found</h4>
                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1 max-w-sm mx-auto">
                        @if($statusFilter !== 'all')
                            No patents match the current {{ $statusFilter === '1' ? 'Active' : 'Inactive' }} filter.
                        @else
                            This user does not have any patents listed yet. Click "Add Patent" below to add one.
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($patentList as $item)
                        <div 
                            wire:key="admin-patent-item-{{ $item['id'] }}" 
                            class="p-4 sm:py-3.5 sm:px-5 rounded-2xl border border-zinc-200 dark:border-zinc-800 hover:border-amber-400 dark:hover:border-amber-500 hover:border-l-4 hover:border-l-amber-500 hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                        >
                            {{-- Read-Only Display Mode (Always Visible in List) --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                    {{-- Lightbulb Icon Badge --}}
                                    <div class="size-10 sm:size-11 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60 flex items-center justify-center shrink-0 shadow-2xs select-none">
                                        <i class="fa-solid fa-lightbulb text-base sm:text-lg"></i>
                                    </div>

                                    <div class="space-y-1 flex-1 min-w-0">
                                        {{-- Title & 1-Click Status Badge --}}
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="font-bold text-sm text-zinc-900 dark:text-white leading-snug break-words">
                                                {{ $item['title'] }}
                                            </h4>

                                            {{-- 1-Click Status Toggle Pill Badge --}}
                                            <button 
                                                type="button" 
                                                wire:click="togglePatentStatus('{{ $item['id'] }}')" 
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold cursor-pointer transition-all hover:scale-105 select-none {{ ((int) $item['status'] === 1) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700' }}"
                                                title="Click to toggle status (Active / Inactive)"
                                            >
                                                <span class="size-1.5 rounded-full {{ ((int) $item['status'] === 1) ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-400' }}"></span>
                                                <span>{{ ((int) $item['status'] === 1) ? 'Active' : 'Inactive' }}</span>
                                            </button>
                                        </div>

                                        {{-- Row 1 (Above Country & Filed): Patent No, Category, Inventors, Assignee, App No, ID --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{-- Patent Number with Label --}}
                                            @if(!empty($item['patent_no']))
                                                <span class="inline-flex items-center gap-1.5 text-zinc-800 dark:text-zinc-200">
                                                    <i class="fa-solid fa-certificate text-[11px] text-amber-500"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Patent No:</strong> <span class="font-mono font-medium">{{ $item['patent_no'] }}</span></span>
                                                </span>
                                            @endif

                                            {{-- Category with Label --}}
                                            @if(!empty($item['category_name']))
                                                @if(!empty($item['patent_no']))
                                                    <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                @endif
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-layer-group text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Category:</strong> {{ $item['category_name'] }}</span>
                                                </span>
                                            @endif

                                            {{-- Inventors --}}
                                            @if(!empty($item['patent_no']) || !empty($item['category_name']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                            @endif
                                            <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                <i class="fa-solid fa-user-group text-[11px] text-zinc-400"></i>
                                                <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Inventor(s):</strong> {{ $item['inventor'] ?: 'Not Specified' }}</span>
                                            </span>

                                            {{-- Assignee --}}
                                            @if(!empty($item['assignee']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-building text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Assignee:</strong> {{ $item['assignee'] }}</span>
                                                </span>
                                            @endif

                                            {{-- Application Number --}}
                                            @if(!empty($item['application_no']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-hashtag text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">App No:</strong> <span class="font-mono">{{ $item['application_no'] }}</span></span>
                                                </span>
                                            @endif

                                            {{-- Patent Record MongoDB ID --}}
                                            <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                            <span class="text-[10.5px] font-mono text-zinc-400 select-all" title="Patent MongoDB Object ID">
                                                ID: {{ $item['id'] }}
                                            </span>
                                        </div>

                                        {{-- Row 2: Country, Filing Date, Publication Date (No duplicate category) --}}
                                        <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            @if(!empty($item['country_name']) || !empty($item['country']))
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-earth-americas text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Country:</strong> {{ $item['country_name'] ?: $item['country'] }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($item['filing_date']))
                                                @if(!empty($item['country_name']) || !empty($item['country']))
                                                    <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                @endif
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-regular fa-calendar text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Filed:</strong> {{ $item['filing_date'] }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($item['publication_date']))
                                                <span class="text-zinc-300 dark:text-zinc-600 select-none">·</span>
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-regular fa-calendar-check text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Published:</strong> {{ $item['publication_date'] }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Row 3: Description with Label and tight spacing --}}
                                        @if(!empty($item['description']))
                                            <div 
                                                x-data="{ 
                                                    expanded: false, 
                                                    isClamped: {{ mb_strlen(trim($item['description'])) > 140 ? 'true' : 'false' }},
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
                                                class="pt-0.5"
                                            >
                                                <div 
                                                    x-ref="descContainer" 
                                                    :class="{ 'line-clamp-2': !expanded, 'whitespace-pre-line': expanded }" 
                                                    class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed break-words"
                                                ><strong class="font-semibold text-zinc-700 dark:text-zinc-300">Description:</strong> {{ trim($item['description']) }}</div>
                                                <button 
                                                    type="button" 
                                                    x-show="isClamped || expanded" 
                                                    x-cloak 
                                                    @click="expanded = !expanded; if (!expanded) $nextTick(() => checkClamped())" 
                                                    class="text-[11px] font-semibold text-brand hover:underline mt-0.5 inline-flex items-center gap-1 cursor-pointer transition-colors"
                                                >
                                                    <span x-text="expanded ? 'View Less' : 'View More'"></span>
                                                    <i class="fa-solid text-[9px]" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Edit Icon Button (Top Right of Card, NO delete button here) --}}
                                <div class="flex items-center gap-1">
                                    <button 
                                        type="button" 
                                        @click="openEditForm({{ json_encode($item) }})"
                                        class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                        title="Edit Patent"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Inline Edit Form Container --}}
                            <div x-show="editingId === '{{ $item['id'] }}'" x-cloak class="mt-4 pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                                <x-profile.patent-form 
                                    :show-status="true" 
                                    :item-id="$item['id']" 
                                    :countries="$countries" 
                                    :categories="$categories" 
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Inline Add Form at Bottom --}}
            <div x-show="showAddForm" x-cloak class="mt-4">
                <x-profile.patent-form 
                    :show-status="true" 
                    :countries="$countries" 
                    :categories="$categories" 
                />
            </div>

            {{-- ADD PATENT BUTTON (Always At Bottom) --}}
            <div class="mt-6 pt-2" x-show="!showAddForm">
                <button 
                    type="button" 
                    @click="openAddForm()" 
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                >
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Patent</span>
                </button>
            </div>
        </div>

        {{-- UNSAVED CHANGES CONFIRMATION MODAL --}}
        <div 
            x-show="showUnsavedModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/60 backdrop-blur-xs"
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
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">You have unsaved changes in this patent record. Are you sure you want to discard them?</p>
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

