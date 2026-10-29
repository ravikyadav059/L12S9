@props([
    'user' => null,
    'isOwnProfile' => true,
])

@php
    $realExperiences = [];
    if (isset($user) && $user) {
        $realExperiences = \App\Models\Experience::where('user_id', (string) $user->_id)
            ->with(['organization', 'designationModel'])
            ->get()
            ->map(function ($exp) {
                return [
                    'id' => (string) $exp->_id,
                    'designation' => $exp->designation_name,
                    'designation_id' => (string) ($exp->designationModel?->_id ?? (string) $exp->designation),
                    'organization' => $exp->organization_name,
                    'organization_id' => (string) ($exp->organization?->_id ?? (string) $exp->organization_id),
                    'start_date' => (string) $exp->join_date,
                    'end_date' => $exp->current_organization ? '' : (string) $exp->end_date,
                    'is_current' => (bool) $exp->current_organization,
                    'description' => (string) $exp->description,
                    'logo_title' => $exp->organization?->organization_name ?? '',
                    'logo_sub' => $exp->organization?->slug ?? '',
                ];
            })
            ->toArray();
    }
@endphp

<div 
    class="scroll-mt-28"
    id="experience"
    x-data="{
        experiences: @js($realExperiences),
        isAdding: false,
        editingId: null,
        showUnsavedModal: false,
        originalSnapshot: '',
        isSaving: false,
        canEdit: @js($isOwnProfile),

        // Form fields
        form: {
            id: null,
            designation: '',
            designation_id: null,
            organization: '',
            organization_id: null,
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

        // Dynamic Autocomplete
        designationSuggestions: [],
        isLoadingDesignations: false,
        designationTimer: null,

        orgSuggestions: [],
        isLoadingOrgs: false,
        orgTimer: null,

        showDesignationDropdown: false,
        showOrgDropdown: false,

        fetchDesignations(query = '') {
            this.isLoadingDesignations = true;
            fetch(`/api/designations?q=${encodeURIComponent(query)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                this.designationSuggestions = Array.isArray(data) ? data : [];
            })
            .catch(() => { this.designationSuggestions = []; })
            .finally(() => { this.isLoadingDesignations = false; });
        },

        selectDesignation(item) {
            if (typeof item === 'string') {
                this.form.designation = item;
                this.form.designation_id = null;
            } else {
                this.form.designation = item.name;
                this.form.designation_id = item.id;
            }
            this.errors.designation = false;
            this.showDesignationDropdown = false;
        },

        onDesignationInput() {
            this.showDesignationDropdown = true;
            this.errors.designation = false;
            this.form.designation_id = null;
            clearTimeout(this.designationTimer);
            this.designationTimer = setTimeout(() => {
                this.fetchDesignations(this.form.designation);
            }, 250);
        },

        fetchOrganizations(query = '') {
            this.isLoadingOrgs = true;
            fetch(`/api/organizations?q=${encodeURIComponent(query)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                this.orgSuggestions = Array.isArray(data) ? data : [];
            })
            .catch(() => { this.orgSuggestions = []; })
            .finally(() => { this.isLoadingOrgs = false; });
        },

        selectOrg(item) {
            if (typeof item === 'string') {
                this.form.organization = item;
                this.form.organization_id = null;
            } else {
                this.form.organization = item.name;
                this.form.organization_id = item.id;
            }
            this.errors.organization = false;
            this.showOrgDropdown = false;
        },

        onOrgInput() {
            this.showOrgDropdown = true;
            this.errors.organization = false;
            this.form.organization_id = null;
            clearTimeout(this.orgTimer);
            this.orgTimer = setTimeout(() => {
                this.fetchOrganizations(this.form.organization);
            }, 250);
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
                designation_id: null,
                organization: '',
                organization_id: null,
                start_date: '',
                end_date: '',
                is_current: false,
                description: '',
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
                designation_id: item.designation_id || null,
                organization: item.organization || '',
                organization_id: item.organization_id || null,
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
            if (!this.validateForm() || this.isSaving) {
                return;
            }

            this.isSaving = true;

            const payload = {
                id: this.editingId || null,
                designation: this.form.designation.trim(),
                designation_id: this.form.designation_id || null,
                organization: this.form.organization.trim(),
                organization_id: this.form.organization_id || null,
                start_date: this.form.start_date.trim(),
                end_date: this.form.is_current ? '' : this.form.end_date.trim(),
                is_current: !!this.form.is_current,
                description: this.form.description.trim(),
            };

            this.$wire.saveExperience(payload);
        },

        handleExperienceSaved(payload) {
            this.isSaving = false;
            if (!payload || !payload.success) {
                this.triggerToast(payload?.message || 'Failed to save experience.', 'error');
                return;
            }

            const exp = payload.experience;
            const idx = this.experiences.findIndex(e => e.id === exp.id);
            if (idx !== -1) {
                this.experiences[idx] = exp;
            } else {
                this.experiences.unshift(exp);
            }

            this.closeForm();
            this.triggerToast(payload.message || 'Experience saved successfully!', 'success');
        },

        handleExperienceDeleted(payload) {
            const id = payload.id || payload;
            this.experiences = this.experiences.filter(e => e.id !== id);
        },

        deleteItem(id) {
            if (!this.canEdit) return;
            if (confirm('Are you sure you want to delete this work experience?')) {
                this.$wire.deleteExperience(id);
                this.experiences = this.experiences.filter(e => e.id !== id);
                if (this.editingId === id) {
                    this.closeForm();
                }
            }
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
    @experience-saved.window="handleExperienceSaved($event.detail)"
    @experience-deleted.window="handleExperienceDeleted($event.detail)"
>
    {{-- Card Shell --}}
    <section class="bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 rounded-2xl p-5 shadow-xs hover:shadow-md transition-all duration-300">
        
        {{-- Section Header with Font Awesome Briefcase --}}
        <div class="flex items-center justify-between pb-3.5 mb-5 border-b border-zinc-100 dark:border-zinc-800">
            <h2 class="text-[17px] font-bold font-space text-[#111827] dark:text-white flex items-center gap-2.5 m-0 leading-snug tracking-[-0.3px]">
                <i class="fa-solid fa-briefcase text-[18px] text-[#78350f] dark:text-[#d97706]"></i>
                <span>Experience (<span x-text="experiences.length"></span>)</span>
            </h2>
        </div>

        {{-- ======================================================== --}}
        {{-- TIMELINE LIST VIEW (Exact match with Image 1 & 3)        --}}
        {{-- ======================================================== --}}
        <div class="relative">
            
            {{-- Continuous Left Vertical Line (Starts at first dot height, ends at bottom fading out) --}}
            <template x-if="experiences.length > 0">
                <div 
                    class="absolute left-[7.5px] top-2 bottom-2 w-[3px] rounded-[2px] pointer-events-none"
                    style="background: linear-gradient(to bottom, var(--brand), var(--brand-050));"
                ></div>
            </template>

            <div class="space-y-7">
                <template x-for="(exp, index) in experiences" :key="exp.id">
                    <div class="relative pl-8">
                        
                        {{-- Timeline Dot (Enlarged 18px, aligned with top of logo box) --}}
                        <div 
                            class="absolute left-0 top-0 size-[18px] rounded-full bg-brand border-[3.5px] border-white dark:border-[#111c26] z-10 shrink-0"
                            style="box-shadow: 0 0 0 2.5px var(--color-brand-50), 0 2px 8px color-mix(in srgb, var(--color-brand) 35%, transparent);"
                        ></div>

                        {{-- Item Row --}}
                        <div class="flex items-start justify-between gap-4">
                            
                            {{-- Logo + Info Group --}}
                            <div class="flex items-start gap-4 min-w-0 flex-1">
                                
                                {{-- Logo Box (Enlarged size 74px with blue outline border) --}}
                                <div class="size-[74px] rounded-2xl border-[1.5px] border-blue-400 dark:border-blue-500/50 bg-white dark:bg-zinc-800 flex flex-col items-center justify-center p-2 shrink-0 shadow-2xs overflow-hidden select-none">
                                    <template x-if="exp.logo_title">
                                        <span class="text-[8.5px] font-extrabold uppercase leading-tight text-center text-zinc-700 dark:text-zinc-200" x-text="exp.logo_title"></span>
                                    </template>
                                    <span class="text-[17px] font-black tracking-tight text-[#111827] dark:text-white uppercase" x-text="exp.logo_sub"></span>
                                </div>

                                {{-- Details --}}
                                <div class="min-w-0 flex-1 space-y-0.5 pt-0.5">
                                    <h3 class="text-[15px] font-bold text-[#111827] dark:text-white leading-snug truncate" x-text="exp.designation"></h3>
                                    
                                    <p class="text-[13px] text-zinc-600 dark:text-zinc-400 font-normal leading-snug">
                                        <span class="font-semibold text-brand" x-text="exp.organization"></span>
                                        <span class="mx-1">·</span>
                                        <span x-text="exp.start_date + ' - ' + (exp.is_current ? 'Present' : exp.end_date)"></span>
                                    </p>

                                    <p class="text-[13px] text-zinc-500 dark:text-zinc-400 leading-snug pt-0.5" x-text="exp.description"></p>
                                </div>
                            </div>

                            {{-- Edit Icon Button (Font Awesome fa-solid fa-pen-to-square) --}}
                            @if($isOwnProfile)
                                <button 
                                    type="button" 
                                    @click="toggleEdit(exp)"
                                    class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors cursor-pointer shrink-0 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                    title="Edit Experience"
                                >
                                    <i class="fa-solid fa-pen-to-square text-[16px]"></i>
                                </button>
                            @endif

                        </div>

                        {{-- ======================================================== --}}
                        {{-- INLINE EDIT FORM (Exactly matches Image 3)               --}}
                        {{-- ======================================================== --}}
                        <div 
                            x-show="editingId === exp.id" 
                            x-cloak
                            x-transition:enter="ease-out duration-200" 
                            x-transition:enter-start="opacity-0 -translate-y-2" 
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="mt-3.5 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs"
                        >
                            <div class="space-y-4">
                                
                                {{-- 1. Job Title / Designation * --}}
                                <div class="relative" @click.outside="showDesignationDropdown = false">
                                    <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                                        Job Title / Designation <span class="text-red-500">*</span>
                                    </label>

                                    <div class="relative flex items-center">
                                        <input 
                                            type="text"
                                            x-model="form.designation"
                                            @focus="showDesignationDropdown = true; if (!designationSuggestions.length) fetchDesignations(form.designation)"
                                            @input="onDesignationInput()"
                                            placeholder="Select or enter designation..."
                                            :class="errors.designation ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 border rounded-xl focus:outline-none focus:border-[#198BEA] focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 pr-14"
                                        />

                                        <div class="absolute right-2.5 flex items-center gap-1.5 text-zinc-400">
                                            <button 
                                                type="button" 
                                                x-show="form.designation" 
                                                @click="form.designation = ''; errors.designation = false"
                                                class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1"
                                                title="Clear"
                                            >
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                            <button 
                                                type="button" 
                                                @click="showDesignationDropdown = !showDesignationDropdown"
                                                class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200"
                                            >
                                                <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showDesignationDropdown ? 'rotate-180' : ''"></i>
                                            </button>
                                        </div>

                                        {{-- Dropdown --}}
                                        <div 
                                            x-show="showDesignationDropdown" 
                                            x-cloak
                                            class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 max-h-52 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800"
                                        >
                                            <template x-for="item in designationSuggestions" :key="item.id || item.name">
                                                <div 
                                                    @click="selectDesignation(item)" 
                                                    class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand cursor-pointer flex items-center justify-between transition"
                                                >
                                                    <span x-text="item.name"></span>
                                                    <span x-show="form.designation && form.designation.toLowerCase() === item.name.toLowerCase()" class="text-brand">
                                                        <i class="fa-solid fa-check text-xs"></i>
                                                    </span>
                                                </div>
                                            </template>

                                            <div 
                                                x-show="!isLoadingDesignations && form.designation.trim() && !designationSuggestions.some(s => s.name.toLowerCase() === form.designation.trim().toLowerCase())"
                                                @click="selectDesignation(form.designation.trim())"
                                                class="px-3.5 py-2 text-xs font-bold text-brand bg-brand-50/50 dark:bg-brand-950/20 hover:bg-brand-100/50 dark:hover:bg-brand-900/40 cursor-pointer flex items-center gap-1.5"
                                            >
                                                <i class="fa-solid fa-plus text-xs"></i>
                                                <span>Add new: "<span x-text="form.designation.trim()"></span>"</span>
                                            </div>

                                            <div x-show="!isLoadingDesignations && designationSuggestions.length === 0 && !form.designation.trim()" class="px-3.5 py-3 text-xs text-zinc-400 text-center">
                                                Type to search designations...
                                            </div>
                                        </div>
                                    </div>

                                    <p x-show="errors.designation" x-cloak class="text-xs text-red-500 mt-1">Please select a designation</p>
                                </div>

                                {{-- 2. Organization * --}}
                                <div class="relative" @click.outside="showOrgDropdown = false">
                                    <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                                        Organization <span class="text-red-500">*</span>
                                    </label>

                                    <div class="relative flex items-center">
                                        <input 
                                            type="text"
                                            x-model="form.organization"
                                            @focus="showOrgDropdown = true; if (!orgSuggestions.length) fetchOrganizations(form.organization)"
                                            @input="onOrgInput()"
                                            placeholder="Search or enter organization..."
                                            :class="errors.organization ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 border rounded-xl focus:outline-none focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/10 transition-all placeholder:text-zinc-400 pr-14"
                                        />

                                        <div class="absolute right-2.5 flex items-center gap-1.5 text-zinc-400">
                                            <button 
                                                type="button" 
                                                x-show="form.organization" 
                                                @click="form.organization = ''; errors.organization = false"
                                                class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1"
                                                title="Clear"
                                            >
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                            <button 
                                                type="button" 
                                                @click="showOrgDropdown = !showOrgDropdown"
                                                class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200"
                                            >
                                                <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showOrgDropdown ? 'rotate-180' : ''"></i>
                                            </button>
                                        </div>

                                        {{-- Dropdown --}}
                                        <div 
                                            x-show="showOrgDropdown" 
                                            x-cloak
                                            class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 max-h-52 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800"
                                        >
                                            <template x-for="item in orgSuggestions" :key="item.id || item.name">
                                                <div 
                                                    @click="selectOrg(item)" 
                                                    class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand cursor-pointer flex items-center justify-between transition"
                                                >
                                                    <span x-text="item.name"></span>
                                                    <span x-show="form.organization && form.organization.toLowerCase() === item.name.toLowerCase()" class="text-brand">
                                                        <i class="fa-solid fa-check text-xs"></i>
                                                    </span>
                                                </div>
                                            </template>

                                            <div 
                                                x-show="!isLoadingOrgs && form.organization.trim() && !orgSuggestions.some(s => s.name.toLowerCase() === form.organization.trim().toLowerCase())"
                                                @click="selectOrg(form.organization.trim())"
                                                class="px-3.5 py-2 text-xs font-bold text-brand bg-brand-50/50 dark:bg-brand-950/20 hover:bg-brand-100/50 dark:hover:bg-brand-900/40 cursor-pointer flex items-center gap-1.5"
                                            >
                                                <i class="fa-solid fa-plus text-xs"></i>
                                                <span>Add new: "<span x-text="form.organization.trim()"></span>"</span>
                                            </div>

                                            <div x-show="!isLoadingOrgs && orgSuggestions.length === 0 && !form.organization.trim()" class="px-3.5 py-3 text-xs text-zinc-400 text-center">
                                                Type to search organizations...
                                            </div>
                                        </div>
                                    </div>

                                    <p x-show="errors.organization" x-cloak class="text-xs text-red-500 mt-1">Please select an organization</p>
                                </div>

                                {{-- 3. Two-Column Date Row --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {{-- Start Date * --}}
                                    <div>
                                        <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                                            Start Date <span class="text-red-500">*</span>
                                        </label>
                                        <x-profile.month-picker 
                                            model="form.start_date" 
                                            error="errors.start_date" 
                                            placeholder="December, 2025" 
                                        />
                                    </div>

                                    {{-- End Date + Current Checkbox --}}
                                    <div>
                                        <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                                            <span>End Date</span>
                                            <span x-show="!form.is_current" class="text-red-500">*</span>
                                        </label>
                                        
                                        <div class="flex items-center gap-3">
                                            <div class="relative flex-1">
                                                <x-profile.month-picker 
                                                    model="form.end_date" 
                                                    error="errors.end_date" 
                                                    placeholder="December, 2025" 
                                                    disabled="form.is_current"
                                                    disabled-placeholder="Present"
                                                />
                                            </div>

                                            <label class="inline-flex items-center gap-1.5 text-[13px] font-bold text-[#111827] dark:text-zinc-200 cursor-pointer select-none shrink-0">
                                                <input 
                                                    type="checkbox" 
                                                    x-model="form.is_current" 
                                                    @change="if(form.is_current) { form.end_date = ''; errors.end_date = false; }"
                                                    class="size-4 rounded border-zinc-300 text-brand focus:ring-brand cursor-pointer"
                                                />
                                                <span>Current</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {{-- 4. Description --}}
                                <div>
                                    <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                                        Description
                                    </label>

                                    <textarea 
                                        x-model="form.description" 
                                        @input="errors.description = false"
                                        rows="3" 
                                        placeholder="Describe your responsibilities and achievements..."
                                        :class="errors.description ? 'border-red-500 bg-red-50/10 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                                        class="w-full p-3.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 border rounded-xl focus:border-[#198BEA] focus:outline-none focus:ring-2 focus:ring-[#198BEA]/10 transition-all leading-relaxed placeholder:text-zinc-400"
                                    ></textarea>

                                    <p x-show="errors.description" x-cloak class="text-xs text-red-500 mt-1" x-text="errors.descriptionMessage"></p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                        Description (between 50 and 1500 characters)
                                    </p>
                                </div>

                                {{-- 5. Button Actions: Update + Cancel (Left), Delete (Right) --}}
                                <div class="flex items-center justify-between pt-2">
                                    <div class="flex items-center gap-2.5">
                                        <button 
                                            type="button" 
                                            @click="submitForm()" 
                                            class="px-6 py-2 rounded-lg bg-brand hover:bg-brand-600 text-white text-[14px] font-semibold shadow-xs transition-all active:scale-95 cursor-pointer"
                                        >
                                            Update
                                        </button>

                                        <button 
                                            type="button" 
                                            @click="handleCancel()" 
                                            class="px-6 py-2 rounded-lg bg-white dark:bg-zinc-800 text-[#111827] dark:text-zinc-200 text-[14px] font-semibold border border-[#d1d5db] dark:border-zinc-700 hover:bg-[#f9fafb] dark:hover:bg-zinc-700 transition-all cursor-pointer shadow-2xs"
                                        >
                                            Cancel
                                        </button>
                                    </div>

                                    <button 
                                        type="button" 
                                        @click="deleteItem(exp.id)" 
                                        class="px-4 py-2 rounded-lg bg-[#ef4444] hover:bg-red-600 text-white text-[14px] font-semibold flex items-center gap-2 shadow-xs transition-all active:scale-95 cursor-pointer"
                                    >
                                        <i class="fa-regular fa-trash-can text-sm"></i>
                                        <span>Delete</span>
                                    </button>
                                </div>

                            </div>
                        </div>

                    </div>
                </template>
            </div>

            {{-- Empty State (Matches Image 2 reference) --}}
            <template x-if="experiences.length === 0 && !isAdding">
                <p class="text-[14px] text-[#111827] dark:text-zinc-300 py-1 font-normal">
                    No experience found.
                </p>
            </template>
        </div>

        {{-- ======================================================== --}}
        {{-- ADD EXPERIENCE FORM (When clicking + Add Experience)     --}}
        {{-- ======================================================== --}}
        <div 
            id="experience-add-form"
            x-show="isAdding" 
            x-cloak
            x-transition:enter="ease-out duration-200" 
            x-transition:enter-start="opacity-0 -translate-y-2" 
            x-transition:enter-end="opacity-100 translate-y-0"
            class="mt-5 border-[1.5px] border-dashed border-[#198BEA] rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs"
        >
            <div class="space-y-4">
                
                {{-- Designation --}}
                <div class="relative" @click.outside="showDesignationDropdown = false">
                    <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                        Job Title / Designation <span class="text-red-500">*</span>
                    </label>

                    <div class="relative flex items-center">
                        <input 
                            type="text"
                            x-model="form.designation"
                            @focus="showDesignationDropdown = true"
                            @input="showDesignationDropdown = true; errors.designation = false"
                            placeholder="Select Designation..."
                            :class="errors.designation ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 border rounded-xl focus:outline-none focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/10 transition-all pr-14"
                        />

                        <div class="absolute right-2.5 flex items-center gap-1.5 text-zinc-400">
                            <button 
                                type="button" 
                                x-show="form.designation" 
                                @click="form.designation = ''; errors.designation = false"
                                class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1"
                                title="Clear"
                            >
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                            <button 
                                type="button" 
                                @click="showDesignationDropdown = !showDesignationDropdown"
                                class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showDesignationDropdown ? 'rotate-180' : ''"></i>
                            </button>
                        </div>

                        <div 
                            x-show="showDesignationDropdown" 
                            x-cloak
                            class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 max-h-52 overflow-y-auto"
                        >
                            <template x-for="opt in designationOptions" :key="opt">
                                <div 
                                    @click="form.designation = opt; errors.designation = false; showDesignationDropdown = false" 
                                    class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0"
                                >
                                    <span x-text="opt"></span>
                                    <span x-show="form.designation === opt" class="text-brand">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <p x-show="errors.designation" x-cloak class="text-xs text-red-500 mt-1">Please select a designation</p>
                </div>

                {{-- Organization --}}
                <div class="relative" @click.outside="showOrgDropdown = false">
                    <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                        Organization <span class="text-red-500">*</span>
                    </label>

                    <div class="relative flex items-center">
                        <input 
                            type="text"
                            x-model="form.organization"
                            @focus="showOrgDropdown = true"
                            @input="showOrgDropdown = true; errors.organization = false"
                            placeholder="Search Organization..."
                            :class="errors.organization ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 border rounded-xl focus:outline-none focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/10 transition-all pr-14"
                        />

                        <div class="absolute right-2.5 flex items-center gap-1.5 text-zinc-400">
                            <button 
                                type="button" 
                                x-show="form.organization" 
                                @click="form.organization = ''; errors.organization = false"
                                class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1"
                                title="Clear"
                            >
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                            <button 
                                type="button" 
                                @click="showOrgDropdown = !showOrgDropdown"
                                class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200"
                            >
                                <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showOrgDropdown ? 'rotate-180' : ''"></i>
                            </button>
                        </div>

                        <div 
                            x-show="showOrgDropdown" 
                            x-cloak
                            class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 max-h-52 overflow-y-auto"
                        >
                            <template x-for="org in filteredOrganizations()" :key="org">
                                <div 
                                    @click="form.organization = org; errors.organization = false; showOrgDropdown = false" 
                                    class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0"
                                >
                                    <span x-text="org"></span>
                                    <span x-show="form.organization === org" class="text-brand">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <p x-show="errors.organization" x-cloak class="text-xs text-red-500 mt-1">Please select an organization</p>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                            Start Date <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text"
                                x-model="form.start_date"
                                @input="errors.start_date = false"
                                placeholder="December, 2025"
                                :class="errors.start_date ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                                class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 border rounded-xl focus:outline-none focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/10 transition-all pr-10"
                            />
                            <div class="absolute right-3.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                                <i class="fa-regular fa-calendar-days text-sm"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                            <span>End Date</span>
                            <span x-show="!form.is_current" class="text-red-500">*</span>
                        </label>
                        
                        <div class="flex items-center gap-3">
                            <div class="relative flex-1">
                                <input 
                                    type="text"
                                    x-model="form.end_date"
                                    :disabled="form.is_current"
                                    @input="errors.end_date = false"
                                    :placeholder="form.is_current ? 'Present' : 'December, 2025'"
                                    :class="[
                                        form.is_current ? 'bg-zinc-100 dark:bg-zinc-800/60 text-zinc-400 border-zinc-200 dark:border-zinc-700/50 cursor-not-allowed' : 'bg-white dark:bg-zinc-900 text-[#111827] dark:text-zinc-100',
                                        errors.end_date ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700'
                                    ]"
                                    class="w-full px-3.5 py-2.5 text-[13px] font-normal border rounded-xl focus:outline-none focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/10 transition-all pr-10"
                                />
                                <div class="absolute right-3.5 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none">
                                    <i class="fa-regular fa-calendar-days text-sm"></i>
                                </div>
                            </div>

                            <label class="inline-flex items-center gap-1.5 text-[13px] font-bold text-[#111827] dark:text-zinc-200 cursor-pointer select-none shrink-0">
                                <input 
                                    type="checkbox" 
                                    x-model="form.is_current" 
                                    @change="if(form.is_current) { form.end_date = ''; errors.end_date = false; }"
                                    class="size-4 rounded border-zinc-300 text-[#198BEA] focus:ring-[#198BEA] cursor-pointer"
                                />
                                <span>Current</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-[13px] font-bold text-[#111827] dark:text-zinc-200 mb-1.5">
                        Description
                    </label>

                    <textarea 
                        x-model="form.description" 
                        @input="errors.description = false"
                        rows="3" 
                        placeholder="Describe your responsibilities and achievements..."
                        :class="errors.description ? 'border-red-500 bg-red-50/10 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="w-full p-3.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 border rounded-xl focus:border-[#198BEA] focus:outline-none focus:ring-2 focus:ring-[#198BEA]/10 transition-all leading-relaxed placeholder:text-zinc-400"
                    ></textarea>

                    <p x-show="errors.description" x-cloak class="text-xs text-red-500 mt-1" x-text="errors.descriptionMessage"></p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        Description (between 50 and 1500 characters)
                    </p>
                </div>

                {{-- Action Buttons (Save & Cancel) --}}
                <div class="flex items-center gap-2.5 pt-2">
                    <button 
                        type="button" 
                        @click="submitForm()" 
                        class="px-6 py-2 rounded-lg bg-[#198BEA] hover:bg-[#1476c9] text-white text-[14px] font-semibold shadow-xs transition-all active:scale-95 cursor-pointer"
                    >
                        Save
                    </button>

                    <button 
                        type="button" 
                        @click="handleCancel()" 
                        class="px-6 py-2 rounded-lg bg-white dark:bg-zinc-800 text-[#111827] dark:text-zinc-200 text-[14px] font-semibold border border-[#d1d5db] dark:border-zinc-700 hover:bg-[#f9fafb] dark:hover:bg-zinc-700 transition-all cursor-pointer shadow-2xs"
                    >
                        Cancel
                    </button>
                </div>

            </div>
        </div>

        {{-- Add Experience Button at Bottom (Matches Image 1 reference) --}}
        @if($isOwnProfile)
            <div x-show="!isAdding" class="pt-4">
                <button 
                    type="button" 
                    @click="openAddForm()" 
                    class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-brand hover:text-brand-600 hover:underline cursor-pointer transition-colors"
                >
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Experience</span>
                </button>
            </div>
        @endif

    </section>

    {{-- ======================================================== --}}
    {{-- UNSAVED CHANGES MODAL (Matches Image 2 reference)        --}}
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

            <h3 class="text-xl font-bold text-zinc-900 dark:text-white mb-2">
                Unsaved Changes
            </h3>

            <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed mb-6">
                You have made changes which are not saved yet. Do you want to discard these changes?
            </p>

            <div class="flex items-center justify-center gap-3">
                <button 
                    type="button" 
                    @click="confirmDiscard()" 
                    class="px-6 py-2.5 rounded-full bg-[#ef4444] hover:bg-red-600 text-white text-sm font-semibold shadow-md transition-all cursor-pointer active:scale-95"
                >
                    Yes, Discard
                </button>

                <button 
                    type="button" 
                    @click="keepEditing()" 
                    class="px-6 py-2.5 rounded-full bg-[#505c6e] hover:bg-[#3d4756] text-white text-sm font-semibold shadow-md transition-all cursor-pointer active:scale-95"
                >
                    Keep Editing
                </button>
            </div>
        </div>
    </div>
</div>
