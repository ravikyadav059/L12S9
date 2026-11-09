<?php

use App\Models\Award;
use App\Models\ReviewerProfile;
use App\Models\User;
use Livewire\Volt\Component;

/**
 * Profile Awards & Achievements Section Component (Livewire Volt Single-File Component)
 *
 * Encapsulates the user's awards and achievements list, inline add/edit form,
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
     * ID of the award currently being edited inline.
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
     * Get active awards for this user profile.
     * Scholar/user view ONLY displays records with status 1.
     *
     * @return list<array<string, mixed>>
     */
    public function getAwardsProperty(): array
    {
        try {
            if (! $this->user) {
                return [];
            }

            return Award::getCachedForUser((string) $this->user->_id, true);
        } catch (\Throwable $e) {
            report($e);
            $this->hasError = true;

            return [];
        }
    }

    /**
     * Save or update an award record.
     */
    public function saveAward(array $payload): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $awardName = trim((string) ($payload['award_name'] ?? ''));
        $awardBy = trim((string) ($payload['award_by'] ?? ''));
        $awardYear = trim((string) ($payload['award_year'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        if ($awardName === '' || $awardBy === '' || $awardYear === '') {
            $this->dispatch('toast', message: 'Please fill in all required fields.', type: 'danger');

            return false;
        }

        if (mb_strlen($description) > 1500) {
            $this->dispatch('toast', message: 'Description cannot exceed 1,500 characters.', type: 'danger');

            return false;
        }

        try {
            if ($id) {
                $award = Award::find($id);
                if (! $award) {
                    $this->dispatch('toast', message: 'Award record not found.', type: 'danger');

                    return false;
                }
            } else {
                $award = new Award;
                $award->user_id = (string) $this->user->_id;
                $award->status = 1;
            }

            $award->award_name = $awardName;
            $award->award_by = $awardBy;
            $award->award_year = $awardYear;
            $award->description = $description !== '' ? $description : null;
            $award->save();

            // Atomic ReviewerProfile synchronization on create
            if (! $id) {
                $reviewerProfile = ReviewerProfile::where('user_id', (string) $this->user->_id)->first();
                if ($reviewerProfile) {
                    ReviewerProfile::where('user_id', (string) $this->user->_id)
                        ->push('award', (string) $award->_id, true);
                } else {
                    ReviewerProfile::create([
                        'user_id' => (string) $this->user->_id,
                        'award' => [(string) $award->_id],
                    ]);
                }
            }

            $this->closeForm();
            $this->dispatch('toast', message: $id ? 'Award updated successfully.' : 'Award added successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to save award. Please try again.', type: 'danger');

            return false;
        }
    }

    /**
     * Delete an award record.
     */
    public function deleteAward(string $id): bool
    {
        if (! $this->isOwnProfile || ! $this->user) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $award = Award::find($id);
            if ($award) {
                $award->delete();
            }

            ReviewerProfile::where('user_id', (string) $this->user->_id)
                ->pull('award', (string) $id);

            $this->closeForm();
            $this->dispatch('toast', message: 'Award deleted successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to delete award.', type: 'danger');

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
                <h3 class="text-sm font-semibold">Awards Section Notice</h3>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    An error occurred while loading this section. Other profile sections remain unaffected.
                </p>
            </div>
        </div>
    @endif

    {{-- AWARDS & ACHIEVEMENTS CARD CONTAINER --}}
    <div 
        id="achievements" 
        class="scroll-mt-28 bg-white dark:bg-[#111c26] border border-zinc-200/80 dark:border-zinc-800 rounded-2xl shadow-xs overflow-hidden"
        x-data="{
            showAddForm: @entangle('showAddForm'),
            editingId: @entangle('editingId'),
            isSaving: false,
            showUnsavedModal: false,
            pendingAction: null,
            originalSnapshot: '',

            allYears: [],
            showYearDropdown: false,
            yearSearchQuery: '',

            init() {
                const currentYear = new Date().getFullYear();
                this.allYears = [];
                for (let y = currentYear; y >= 1950; y--) {
                    this.allYears.push(String(y));
                }
            },

            filteredYears() {
                const q = (this.yearSearchQuery || '').trim();
                if (!q) return this.allYears;
                return this.allYears.filter(y => y.includes(q));
            },

            selectYear(yr) {
                this.form.award_year = yr;
                this.showYearDropdown = false;
                this.errors.award_year = false;
            },

            form: {
                id: null,
                award_name: '',
                award_by: '',
                award_year: '',
                description: '',
                status: 1
            },

            errors: {
                award_name: false,
                award_by: false,
                award_year: false,
                description: false
            },

            resetErrors() {
                this.errors = {
                    award_name: false,
                    award_by: false,
                    award_year: false,
                    description: false
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
                this.showYearDropdown = false;
                this.yearSearchQuery = '';

                this.form = {
                    id: null,
                    award_name: '',
                    award_by: '',
                    award_year: '',
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
                this.showYearDropdown = false;
                this.yearSearchQuery = '';

                this.form = {
                    id: item.id,
                    award_name: item.award_name || '',
                    award_by: item.award_by || '',
                    award_year: item.award_year || '',
                    description: item.description || '',
                    status: 1
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

                if (!this.form.award_name || this.form.award_name.trim().length < 2) {
                    this.errors.award_name = true;
                    valid = false;
                }

                if (!this.form.award_by || this.form.award_by.trim().length < 2) {
                    this.errors.award_by = true;
                    valid = false;
                }

                if (!this.form.award_year || this.form.award_year.trim() === '') {
                    this.errors.award_year = true;
                    valid = false;
                }

                const descLen = (this.form.description || '').trim().length;
                if (descLen > 1500) {
                    this.errors.description = true;
                    valid = false;
                }

                return valid;
            },

            saveItem() {
                if (!this.validate()) return;
                this.isSaving = true;

                this.$wire.saveAward(this.form).then((res) => {
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
                if (confirm('Are you sure you want to delete this award?')) {
                    this.$wire.deleteAward(id).then((res) => {
                        if (res && this.editingId === id) {
                            this.closeForm(true);
                        }
                    });
                }
            }
        }"
    >
        {{-- SECTION HEADER --}}
        <div class="p-6 border-b border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800/80 flex items-center justify-center text-amber-600 dark:text-amber-400">
                        <i class="fa-solid fa-trophy text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            <span>Awards &amp; Achievements</span>
                            <span class="inline-flex items-center justify-center size-5 text-xs font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 rounded-full">
                                {{ count($this->awards) }}
                            </span>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">
            {{-- AWARDS LIST --}}
            <div class="space-y-4">
                @forelse($this->awards as $award)
                    <div 
                        wire:key="profile-award-card-{{ $award['id'] }}" 
                        class="p-4 rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 hover:border-brand dark:hover:border-brand hover:border-l-4 hover:border-l-brand hover:pl-[13px] bg-zinc-50/40 dark:bg-zinc-900/40 hover:bg-white dark:hover:bg-zinc-800/50 hover:shadow-xs transition-all duration-200"
                    >
                        {{-- DISPLAY MODE --}}
                        <div>
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-start gap-4 min-w-0 flex-1">
                                    {{-- Trophy Icon Medallion --}}
                                    <div class="size-[64px] rounded-2xl border-[2px] border-amber-300 dark:border-amber-500/50 bg-amber-50/70 dark:bg-amber-950/30 flex items-center justify-center p-2 text-amber-500 dark:text-amber-400 shrink-0 shadow-2xs select-none">
                                        <i class="fa-solid fa-trophy text-2xl"></i>
                                    </div>

                                    <div class="space-y-1.5 flex-1 min-w-0">
                                        {{-- Award Name --}}
                                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white leading-snug">
                                            {{ $award['award_name'] }}
                                        </h4>

                                        {{-- Meta Row: Awarded By & Year --}}
                                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 flex-wrap">
                                            @if(!empty($award['award_by']))
                                                <span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                                    <i class="fa-solid fa-building-columns text-[11px] text-zinc-400"></i>
                                                    <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200">Awarded by:</strong> {{ $award['award_by'] }}</span>
                                                </span>
                                            @endif

                                            @if(!empty($award['award_year']))
                                                @if(!empty($award['award_by']))
                                                    <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                @endif
                                                <span class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                    <i class="fa-regular fa-calendar text-[11px] text-zinc-400"></i>
                                                    <span>{{ $award['award_year'] }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Description: 2 lines with View More / View Less --}}
                                        @if(!empty($award['description']))
                                            <div 
                                                x-data="{ 
                                                    expanded: false, 
                                                    isClamped: {{ mb_strlen(trim($award['description'])) > 140 ? 'true' : 'false' }},
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
                                                ><strong class="font-semibold text-zinc-800 dark:text-zinc-200">Description:</strong> {{ trim($award['description']) }}</div>
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
                                </div>

                                {{-- Action Buttons (Owner only: Edit only; Delete is inside form) --}}
                                @if($isOwnProfile)
                                    <div class="flex items-center gap-1 shrink-0 ml-2">
                                        <button 
                                            type="button" 
                                            x-show="editingId !== '{{ $award['id'] }}'"
                                            @click="openEditForm({{ json_encode($award) }})"
                                            class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            title="Edit Award"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- INLINE EDIT FORM CONTAINER --}}
                        <div x-show="editingId === '{{ $award['id'] }}'" x-cloak class="mt-4 pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                            <x-profile.award-form :item-id="$award['id']" />
                        </div>
                    </div>
                @empty
                    @if(!$showAddForm)
                        <div class="text-center py-8 text-zinc-400 dark:text-zinc-500">
                            <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-3 text-zinc-400">
                                <i class="fa-solid fa-trophy text-lg"></i>
                            </div>
                            <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">No awards &amp; achievements added yet</p>
                            @if($isOwnProfile)
                                <p class="text-[11px] text-zinc-400 mt-0.5">Click "Add Award" below to highlight honors and recognitions on your profile.</p>
                            @endif
                        </div>
                    @endif
                @endforelse
            </div>

            {{-- BOTTOM ADD FORM CONTAINER --}}
            <div x-show="showAddForm" x-cloak class="mt-4">
                <x-profile.award-form />
            </div>

            {{-- BOTTOM ADD BUTTON (Own Profile Only) --}}
            @if($isOwnProfile)
                <div class="mt-6 pt-2" x-show="!showAddForm">
                    <button 
                        type="button" 
                        @click="openAddForm()" 
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Award</span>
                    </button>
                </div>
            @endif
        </div>

        {{-- UNSAVED CHANGES CONFIRMATION MODAL --}}
        @if($isOwnProfile)
            <x-profile.unsaved-modal />
        @endif
    </div>
</div>

