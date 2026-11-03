{{-- resources/views/components/profile/project-form.blade.php --}}
@props([
    'showStatus' => false,
    'itemId' => null,
])
<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">

        @if($showStatus)
            <template x-if="form.id">
                <div class="flex items-center gap-2 pb-1 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-500">Project Record ID:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Click to select">
                        <span class="text-zinc-400 select-none">ID:</span>
                        <span x-text="form.id"></span>
                    </span>
                </div>
            </template>
        @endif

        {{-- 1. Project Name * --}}
        <div>
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Project Name <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                x-model="form.project_name"
                @input="errors.project_name = false"
                placeholder="Enter project name"
                :class="errors.project_name ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
            />
            <p x-show="errors.project_name" x-cloak class="text-[11px] text-red-500 mt-1">Project name is required</p>
        </div>

        {{-- 2. Other Contributors (Autocomplete Search matching deposit-article) --}}
        <div class="relative" @click.outside="showContributorDropdown = false">
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Other Contributors
            </label>

            {{-- Selected Contributors Badges / Chips --}}
            <div x-show="form.contributors && form.contributors.length > 0" x-cloak class="flex flex-wrap gap-2 mb-2">
                <template x-for="(contributor, cIndex) in form.contributors" :key="contributor.id || contributor.name + cIndex">
                    <span class="inline-flex items-center gap-1.5 pl-1.5 pr-2.5 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-full shadow-2xs text-xs">
                        <template x-if="contributor.photo">
                            <img :src="contributor.photo" :alt="contributor.name" class="size-5 rounded-full object-cover border border-zinc-200 dark:border-zinc-700 shrink-0" onerror="this.onerror=null; this.src='{{ asset('assets/images/vector.png') }}';" />
                        </template>
                        <template x-if="!contributor.photo">
                            <span class="size-5 rounded-full bg-blue-100 dark:bg-blue-900/60 text-brand flex items-center justify-center font-bold text-[10px] shrink-0">
                                <span x-text="(contributor.name || 'U').charAt(0).toUpperCase()"></span>
                            </span>
                        </template>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200 text-xs truncate max-w-[160px]" x-text="contributor.name"></span>
                        <template x-if="contributor.id">
                            <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1 py-0.2 rounded" title="Registered User">✓</span>
                        </template>
                        <button 
                            type="button" 
                            @click="removeContributor(cIndex)" 
                            class="text-zinc-400 hover:text-red-500 transition-colors ml-0.5 cursor-pointer"
                            title="Remove"
                        >
                            <i class="fa-solid fa-xmark text-[11px]"></i>
                        </button>
                    </span>
                </template>
            </div>

            {{-- Search Input with Loading Indicator --}}
            <div class="relative">
                <div class="relative flex items-center">
                    <input 
                        type="text"
                        x-ref="contributorSearchInput"
                        x-model="contributorSearchQuery"
                        @focus="if(contributorSearchQuery && contributorSearchQuery.trim().length >= 2) { showContributorDropdown = true; if(contributorResults.length === 0 && !isSearchingContributor) { onContributorInput(contributorSearchQuery); } }"
                        @input="onContributorInput($event.target.value)"
                        @keydown.enter.prevent="if(contributorResults.length > 0) { selectContributor(contributorResults[0]); } else if(!isSearchingContributor && contributorSearchQuery && contributorSearchQuery.trim().length >= 2) { openAddAuthorModal(contributorSearchQuery); }"
                        placeholder="Search author by name..."
                        class="w-full h-10 pl-3.5 pr-9 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />

                    {{-- Search / Spinner Icon --}}
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-400">
                        <template x-if="isSearchingContributor">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                        </template>
                        <template x-if="!isSearchingContributor">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </template>
                    </div>
                </div>

                {{-- Autocomplete Dropdown Popup --}}
                <div 
                    x-show="showContributorDropdown" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800"
                >
                    {{-- Dropdown Header --}}
                    <div class="px-3.5 py-2 bg-zinc-50 dark:bg-zinc-800/80 flex items-center justify-between text-xs">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-zinc-500 dark:text-zinc-400">
                            Registered Authors
                        </span>
                        <template x-if="!isSearchingContributor && contributorResults.length > 0">
                            <span x-text="contributorResults.length + ' found'" class="text-[10px] font-semibold text-brand bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded-full"></span>
                        </template>
                    </div>

                    {{-- Results List / Searching State / Empty State --}}
                    <div class="max-h-60 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/60">
                        {{-- Searching State (Shown first while searching) --}}
                        <div x-show="isSearchingContributor" class="px-3.5 py-4 text-xs text-zinc-400 text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                            <span>Searching authors...</span>
                        </div>

                        {{-- Results List (Shown once result comes) --}}
                        <div x-show="!isSearchingContributor">
                            <template x-for="author in contributorResults" :key="author.id">
                                <div 
                                    @click="selectContributor(author)"
                                    class="w-full text-left px-3.5 py-2.5 hover:bg-sky-50/70 dark:hover:bg-zinc-800/80 transition-colors flex items-center gap-3 group cursor-pointer"
                                >
                                    <img 
                                        :src="author.photo || '{{ asset('assets/images/vector.png') }}'" 
                                        :alt="author.name" 
                                        class="size-8 rounded-full object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 bg-zinc-100"
                                        onerror="this.onerror=null; this.src='{{ asset('assets/images/vector.png') }}';"
                                    />

                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100 group-hover:text-brand transition leading-snug truncate" x-text="author.name"></div>
                                        <template x-if="author.affiliation">
                                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate mt-0.5" x-text="author.affiliation"></div>
                                        </template>
                                    </div>

                                    <div class="text-xs font-semibold text-brand opacity-0 group-hover:opacity-100 transition shrink-0">
                                        + Add
                                    </div>
                                </div>
                            </template>

                            {{-- Empty Search State with Add Author Modal Trigger --}}
                            {{-- ONLY shown when search has finished, no match found, and user typed at least 2 chars --}}
                            <div x-show="contributorResults.length === 0 && contributorSearchQuery && contributorSearchQuery.trim().length >= 2" class="p-4 text-center space-y-3">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    No registered author found for "<span class="font-semibold text-zinc-700 dark:text-zinc-200" x-text="contributorSearchQuery"></span>"
                                </p>

                                <button 
                                    type="button"
                                    @click="openAddAuthorModal(contributorSearchQuery)"
                                    class="w-full border-[1.5px] border-dashed border-[#198BEA] rounded-xl px-3.5 py-2.5 flex items-center gap-2.5 cursor-pointer bg-blue-50/50 hover:bg-blue-100/70 dark:bg-blue-950/30 dark:hover:bg-blue-950/50 transition select-none text-left"
                                >
                                    <span class="size-6 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-plus"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100">
                                            Add New Author "<span class="text-brand font-bold" x-text="contributorSearchQuery.trim()"></span>"
                                        </div>
                                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Click to register author via modal</div>
                                    </div>
                                    <span class="text-xs font-bold text-[#198BEA] shrink-0">Add &rarr;</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">Search registered authors by name, or add a new author.</p>
        </div>

        {{-- 3. Two-Column Date Row: Start Date & End Date (with Not Ended Yet) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Start Date --}}
            <div>
                <x-profile.month-picker 
                    label="Start Date" 
                    :required="true" 
                    model="form.start_month_year" 
                    hasErrorExpr="errors.start_month_year" 
                    placeholder="Select Start Month, Year" 
                />
                <p x-show="errors.start_month_year" x-cloak class="text-[11px] text-red-500 mt-1">Please select start date</p>
            </div>

            {{-- End Date + Not Ended Yet --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        <span>End Date</span>
                        <span x-show="!form.not_ended_yet" class="text-red-500">*</span>
                    </label>

                    <label class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-700 dark:text-zinc-300 cursor-pointer select-none">
                        <input 
                            type="checkbox" 
                            x-model="form.not_ended_yet" 
                            @change="if(form.not_ended_yet) { form.end_month_year = ''; errors.end_month_year = false; }"
                            class="size-4 rounded border-zinc-300 text-brand focus:ring-brand cursor-pointer"
                        />
                        <span>Not Ended Yet</span>
                    </label>
                </div>

                <x-profile.month-picker 
                    label="" 
                    :required="false" 
                    model="form.end_month_year" 
                    disabledExpr="form.not_ended_yet" 
                    hasErrorExpr="errors.end_month_year" 
                    placeholder="Select End Month, Year" 
                />
                <p x-show="errors.end_month_year" x-cloak class="text-[11px] text-red-500 mt-1">Please select end date or check Not Ended Yet</p>
            </div>
        </div>

        {{-- 4. Funded Project & Funded Amount Row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Funded Project * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Funded Project <span class="text-red-500">*</span>
                </label>
                <select 
                    x-model="form.funded_project"
                    @change="onFundedProjectChange()"
                    :class="errors.funded_project ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all cursor-pointer"
                >
                    <option value="">Select option</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
                <p x-show="errors.funded_project" x-cloak class="text-[11px] text-red-500 mt-1">Please select funded project option</p>
            </div>

            {{-- Funded Amount --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    <span>Funded Amount</span>
                    <span x-show="isFundedYes()" class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.funded_amount"
                    :disabled="!isFundedYes()"
                    @input="errors.funded_amount = false"
                    placeholder="Enter amount eg. 5000"
                    :class="errors.funded_amount ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : (!isFundedYes() ? 'opacity-50 cursor-not-allowed bg-zinc-100 dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-800' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white')"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.funded_amount" x-cloak class="text-[11px] text-red-500 mt-1">Funded amount is required when project is funded</p>
            </div>
        </div>

        {{-- 5. Project URL & Agency Name Row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Project URL --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Project URL
                </label>
                <input 
                    type="url" 
                    x-model="form.project_url"
                    @input="errors.project_url = false"
                    placeholder="https://example.com/project"
                    :class="errors.project_url ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.project_url" x-cloak class="text-[11px] text-red-500 mt-1">Please enter a valid URL (e.g. https://...)</p>
            </div>

            {{-- Agency Name --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Agency Name
                </label>
                <input 
                    type="text" 
                    x-model="form.agency_name"
                    placeholder="Funding Agency Name (e.g. DST, UGC, AICTE, SSIP)"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
            </div>
        </div>

        {{-- 6. Description (between 50 and 1500 characters) --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Description
                </label>
                <span class="text-[11px] text-zinc-400">
                    <span x-text="(form.description || '').length"></span> / 1500
                </span>
            </div>
            <textarea 
                rows="3" 
                x-model="form.description"
                @input="errors.description = false"
                placeholder="Describe project objectives, scope, methodology, technologies, or achievements..."
                :class="errors.description ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                class="w-full p-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all resize-y"
            ></textarea>
            <div class="flex items-center justify-between mt-1">
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Description (between 50 and 1500 characters)</p>
                <p x-show="errors.description" x-cloak class="text-[11px] text-red-500" x-text="errors.descriptionMessage || 'Description must be between 50 and 1500 characters'"></p>
            </div>
        </div>

        {{-- 7. Action Buttons & Status (Admin Only) --}}
        <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800 flex-wrap gap-3">
            <div class="flex items-center gap-2 flex-wrap">
                <button 
                    type="button" 
                    @click="submitForm()"
                    class="px-5 py-2.5 rounded-xl bg-brand hover:bg-brand-600 text-white text-xs font-bold shadow-xs hover:shadow-md transition-all cursor-pointer active:scale-95 flex items-center gap-2"
                >
                    <i class="fa-solid fa-check text-xs"></i>
                    <span x-text="form.id ? 'Update' : 'Save Project'"></span>
                </button>

                <button 
                    type="button" 
                    @click="closeForm()"
                    class="px-4 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-xs font-medium transition cursor-pointer active:scale-95"
                >
                    Cancel
                </button>

                @if($showStatus)
                    <div class="flex items-center gap-3 sm:ml-2 sm:pl-3 sm:border-l sm:border-zinc-200 dark:sm:border-zinc-700">
                        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">Status:</span>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 1">
                            <input 
                                type="radio" 
                                name="form_status_proj_{{ $itemId ?? 'new' }}" 
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
                                name="form_status_proj_{{ $itemId ?? 'new' }}" 
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
                    class="px-4 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-xs font-bold shadow-xs hover:shadow-md transition cursor-pointer flex items-center gap-1.5 active:scale-95"
                    title="Delete Project"
                >
                    <i class="fa-solid fa-trash-can text-xs"></i>
                    <span>Delete</span>
                </button>
            </template>
        </div>
    </div>
</div>
