{{-- resources/views/components/profile/thesis-form.blade.php --}}
@props([
    'showStatus' => false,
    'itemId' => null,
])

<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">

        @if($showStatus)
            <template x-if="form.id">
                <div class="flex items-center gap-2 pb-1 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-500">Thesis Record ID:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Click to select">
                        <span class="text-zinc-400 select-none">ID:</span>
                        <span x-text="form.id"></span>
                    </span>
                </div>
            </template>
        @endif

        {{-- Row 1: Candidate Name * & Thesis Title * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 1. Candidate Name * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.phd_name" 
                    @input="errors.phd_name = false"
                    placeholder="e.g. John Doe, Rahul Sharma"
                    :class="errors.phd_name ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.phd_name" x-cloak class="text-[11px] text-red-500 mt-1">Please enter candidate / student name</p>
            </div>

            {{-- 2. Thesis Title * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Thesis Title <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.phd_title" 
                    @input="errors.phd_title = false"
                    placeholder="e.g. Deep Learning Approaches to Real-Time Object Recognition"
                    :class="errors.phd_title ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.phd_title" x-cloak class="text-[11px] text-red-500 mt-1">Please enter thesis title</p>
            </div>
        </div>

        {{-- Row 2: Institution Name * & Thesis Type * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 3. Institution Name * (Full Autocomplete matching Experience & Education) --}}
            <div class="relative" @click.outside="showOrgDropdown = false">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Institution Name <span class="text-red-500">*</span>
                </label>

                {{-- Custom Select Trigger --}}
                <div 
                    @click="openOrgDropdown()"
                    :class="errors.institution ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : (showOrgDropdown ? 'border-brand ring-2 ring-brand/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900')"
                    class="w-full h-10 px-3.5 text-xs font-normal border rounded-xl flex items-center justify-between cursor-pointer select-none transition-all"
                >
                    <span 
                        x-text="form.institution || 'Select Institution...'"
                        :class="form.institution ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400'"
                        class="truncate pr-2"
                    ></span>

                    <div class="flex items-center gap-1.5 text-zinc-400 shrink-0">
                        <button 
                            type="button" 
                            x-show="form.institution" 
                            @click.stop="clearOrganization()"
                            class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1 cursor-pointer"
                            title="Clear"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                        <span class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200">
                            <i class="fa-solid fa-chevron-down text-[11px] transition-transform duration-200" :class="showOrgDropdown ? 'rotate-180' : ''"></i>
                        </span>
                    </div>
                </div>

                {{-- Autocomplete Dropdown Popup --}}
                <div 
                    x-show="showOrgDropdown" 
                    x-cloak
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border-2 border-blue-500 dark:border-blue-500 rounded-xl shadow-xl z-50 overflow-hidden"
                >
                    {{-- Search Input inside Dropdown --}}
                    <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        <div class="relative flex items-center">
                            <input 
                                type="text"
                                x-ref="orgSearchInput"
                                x-model="orgSearchQuery"
                                @input="searchOrganizations(orgSearchQuery)"
                                placeholder="Search institution..."
                                class="w-full h-9 px-3 text-xs font-normal text-zinc-900 dark:text-white border-2 border-blue-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 bg-white dark:bg-zinc-800 placeholder:text-zinc-400"
                            />
                            <button 
                                type="button" 
                                x-show="orgSearchQuery" 
                                @click="orgSearchQuery = ''; fetchOrganizations('')"
                                class="absolute right-2.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 cursor-pointer"
                            >
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div 
                        @scroll.passive="onOrgScroll($event)"
                        class="max-h-52 overflow-y-auto"
                    >
                        {{-- Add New Institution (Dashed button) --}}
                        <div 
                            x-show="orgSearchQuery && orgSearchQuery.trim() !== '' && !hasExactOrgMatch()"
                            @click="selectOrganization(orgSearchQuery)"
                            class="p-2 border-b border-zinc-100 dark:border-zinc-800/80 bg-zinc-50/40 dark:bg-zinc-900/40"
                        >
                            <div class="border-[1.5px] border-dashed border-[#198BEA] rounded-xl px-3.5 py-2 flex items-center gap-2.5 cursor-pointer bg-blue-50/50 hover:bg-blue-100/70 dark:bg-blue-950/30 dark:hover:bg-blue-950/50 transition select-none">
                                <span class="size-5 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                                    <i class="fa-solid fa-plus"></i>
                                </span>
                                <span class="text-xs font-medium text-zinc-800 dark:text-zinc-100">
                                    Add New Institution
                                </span>
                            </div>
                        </div>

                        {{-- Searching state --}}
                        <div x-show="isSearchingOrg" class="px-3.5 py-3 text-xs text-zinc-400 text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <span>Searching institutions...</span>
                        </div>

                        {{-- Matching options list --}}
                        <template x-for="org in organizationOptions" :key="org">
                            <div 
                                @click="selectOrganization(org)" 
                                :class="form.institution === org ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-medium' : 'text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand'"
                                class="px-3.5 py-2.5 text-xs cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 transition-colors"
                            >
                                <span x-text="org"></span>
                                <span x-show="form.institution === org" class="text-brand">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </span>
                            </div>
                        </template>

                        {{-- Empty state --}}
                        <div 
                            x-show="!isSearchingOrg && organizationOptions.length === 0 && (!orgSearchQuery || hasExactOrgMatch())" 
                            class="px-3.5 py-3 text-xs text-zinc-400 text-center"
                        >
                            No institutions found
                        </div>

                        {{-- Infinite scroll loading indicator --}}
                        <div x-show="isLoadingMoreOrg" class="px-3.5 py-2 text-[11px] text-zinc-400 text-center flex items-center justify-center gap-2 border-t border-zinc-100 dark:border-zinc-800">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <span>Loading more...</span>
                        </div>
                    </div>
                </div>

                <p x-show="errors.institution" x-cloak class="text-[11px] text-red-500 mt-1">Please select or enter an institution</p>
            </div>

            {{-- 4. Thesis Type * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Thesis Type <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.thesis_type" 
                    @input="errors.thesis_type = false"
                    list="thesis-type-suggestions-{{ $itemId ?? 'new' }}"
                    placeholder="e.g. Ph.D. Thesis, M.Tech, BE Project, Dissertation"
                    :class="errors.thesis_type ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <datalist id="thesis-type-suggestions-{{ $itemId ?? 'new' }}">
                    <option value="Ph.D. Thesis">
                    <option value="Doctor of Philosophy (PhD)">
                    <option value="Doctoral Dissertation">
                    <option value="M.Tech Thesis">
                    <option value="M.Phil Dissertation">
                    <option value="Master Thesis">
                    <option value="BE Project">
                    <option value="B.Tech Project">
                    <option value="Case Study">
                </datalist>
                <p x-show="errors.thesis_type" x-cloak class="text-[11px] text-red-500 mt-1">Please enter thesis type</p>
            </div>
        </div>

        {{-- Row 3: Major Area of Research * & Status * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 5. Major Area of Research * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Major Area of Research <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.research_area" 
                    @input="errors.research_area = false"
                    placeholder="e.g. Artificial Intelligence, Cloud Computing, Nanotechnology"
                    :class="errors.research_area ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.research_area" x-cloak class="text-[11px] text-red-500 mt-1">Please enter major area of research</p>
            </div>

            {{-- 6. Status * (Dropdown: Select status..., Ongoing, Completed) --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Status <span class="text-red-500">*</span>
                </label>
                <select 
                    x-model="form.phd_status" 
                    @change="errors.phd_status = false"
                    :class="errors.phd_status ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all cursor-pointer"
                >
                    <option value="">Select status...</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                </select>
                <p x-show="errors.phd_status" x-cloak class="text-[11px] text-red-500 mt-1">Please select thesis status (Ongoing or Completed)</p>
            </div>
        </div>

        {{-- Form Actions --}}
        <div wire:ignore.self class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2.5 flex-wrap">
                <button 
                    type="button" 
                    @click="saveItem()"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-60"
                >
                    <i x-show="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <i x-show="!isSaving" class="fa-solid fa-check text-xs"></i>
                    <span x-text="form.id ? 'Update Thesis' : 'Save Thesis'"></span>
                </button>
                <button 
                    type="button" 
                    @click="closeForm()"
                    class="px-4 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-xl transition cursor-pointer"
                >
                    Cancel
                </button>

                @if($showStatus)
                    <div class="flex items-center gap-3 sm:ml-2 sm:pl-3 sm:border-l sm:border-zinc-200 dark:sm:border-zinc-700">
                        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">Status:</span>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 1">
                            <input 
                                type="radio" 
                                name="form_status_thesis_{{ $itemId ?? 'new' }}" 
                                value="1" 
                                :checked="form.status == 1 || form.status === '1'"
                                @change="form.status = 1"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        </label>

                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 0">
                            <input 
                                type="radio" 
                                name="form_status_thesis_{{ $itemId ?? 'new' }}" 
                                value="0" 
                                :checked="form.status == 0 || form.status === '0'"
                                @change="form.status = 0"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                                <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                Inactive
                            </span>
                        </label>
                    </div>
                @endif
            </div>

            <template x-if="form.id">
                <button 
                    type="button" 
                    @click="deleteItem(form.id)" 
                    class="px-3.5 py-2 rounded-xl bg-[#ef4444] hover:bg-red-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs transition-all active:scale-95 cursor-pointer"
                >
                    <i class="fa-regular fa-trash-can text-sm"></i>
                    <span>Delete</span>
                </button>
            </template>
        </div>

    </div>
</div>

