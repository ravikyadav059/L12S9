{{-- resources/views/livewire/admin/users/partials/experience.blade.php --}}
<!-- WORK EXPERIENCE SECTION (HYBRID ARCHITECTURE: BLADE TIMELINE + ALPINE MODAL/FORM) -->
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
        showDesignationDropdown: false,
        showOrgDropdown: false,
        desigDebounceTimer: null,
        orgDebounceTimer: null,

        init() {
            this.fetchDesignations('');
            this.fetchOrganizations('');
        },

        fetchDesignations(query = '') {
            clearTimeout(this.desigDebounceTimer);
            this.desigDebounceTimer = setTimeout(() => {
                this.isSearchingDesig = true;
                fetch('/api/designations?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        this.designationOptions = data.map(item => item.name);
                        this.isSearchingDesig = false;
                    })
                    .catch(() => {
                        this.isSearchingDesig = false;
                    });
            }, 250);
        },

        fetchOrganizations(query = '') {
            clearTimeout(this.orgDebounceTimer);
            this.orgDebounceTimer = setTimeout(() => {
                this.isSearchingOrg = true;
                fetch('/api/organizations?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        this.organizationOptions = data.map(item => item.name);
                        this.isSearchingOrg = false;
                    })
                    .catch(() => {
                        this.isSearchingOrg = false;
                    });
            }, 250);
        },

        filteredOrganizations() {
            return this.organizationOptions;
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

            // Resolve status for 0, '0', 1, '1'
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

            if (!this.form.start_date.trim()) {
                this.errors.start_date = true;
                hasError = true;
            }

            if (!this.form.is_current && !this.form.end_date.trim()) {
                this.errors.end_date = true;
                hasError = true;
            }

            const descLen = this.form.description.trim().length;
            if (descLen < 50) {
                this.errors.description = true;
                this.errors.descriptionMessage = 'Please write at least 50 characters';
                hasError = true;
            } else if (descLen > 1500) {
                this.errors.description = true;
                this.errors.descriptionMessage = 'Description must not exceed 1500 characters';
                hasError = true;
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
        
        {{-- ======================================================== --}}
        {{-- TIMELINE LIST VIEW (Rendered cleanly via Blade)          --}}
        {{-- ======================================================== --}}
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

                                    <p class="text-[13px] text-zinc-500 dark:text-zinc-400 leading-snug pt-0.5">{{ $exp['description'] }}</p>
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

                        {{-- ======================================================== --}}
                        {{-- INLINE EDIT FORM (Uses Shared Reusable Component)         --}}
                        {{-- ======================================================== --}}
                        <div 
                            x-show="editingId === '{{ $exp['id'] }}'" 
                            x-cloak
                            x-transition:enter="ease-out duration-200" 
                            x-transition:enter-start="opacity-0 -translate-y-2" 
                            x-transition:enter-end="opacity-100 translate-y-0"
                        >
                            <x-profile.experience-form :show-status="true" />
                        </div>

                    </div>
                @empty
                    <p class="text-xs text-zinc-700 dark:text-zinc-300 py-1 font-normal">
                        No experience found.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- ADD EXPERIENCE FORM (Uses Same Shared Component)         --}}
        {{-- ======================================================== --}}
        <div 
            id="admin-experience-add-form"
            x-show="isAdding" 
            x-cloak
            x-transition:enter="ease-out duration-200" 
            x-transition:enter-start="opacity-0 -translate-y-2" 
            x-transition:enter-end="opacity-100 translate-y-0"
        >
            <x-profile.experience-form :show-status="true" />
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

    {{-- ======================================================== --}}
    {{-- UNSAVED CHANGES MODAL                                    --}}
    {{-- ======================================================== --}}
    <div 
        x-show="showUnsavedModal" 
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
        @keydown.escape.window="keepEditing()"
    >
        <div 
            @click.outside="keepEditing()"
            x-show="showUnsavedModal"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="bg-white dark:bg-[#111c26] rounded-3xl p-8 max-w-[420px] w-full text-center shadow-2xl border border-zinc-100 dark:border-zinc-800"
        >
            <div class="size-16 rounded-full border-2 border-amber-300 bg-amber-50 text-amber-500 flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fa-solid fa-exclamation"></i>
            </div>

            <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2">
                Unsaved Changes
            </h3>

            <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed mb-6">
                You have made changes which are not saved yet. Do you want to discard these changes?
            </p>

            <div class="flex items-center justify-center gap-3">
                <button 
                    type="button" 
                    @click="confirmDiscard()" 
                    class="px-6 py-2.5 rounded-full bg-[#ef4444] hover:bg-red-600 text-white text-xs font-bold shadow-md transition-all cursor-pointer active:scale-95"
                >
                    Yes, Discard
                </button>

                <button 
                    type="button" 
                    @click="keepEditing()" 
                    class="px-6 py-2.5 rounded-full bg-[#505c6e] hover:bg-[#3d4756] text-white text-xs font-bold shadow-md transition-all cursor-pointer active:scale-95"
                >
                    Keep Editing
                </button>
            </div>
        </div>
    </div>
</div>
