@props([
    'showStatus' => false,
    'itemId' => null,
    'journalRoles' => [],
    'isJournalVerifiedOnline' => null,
    'verifiedSourceLabel' => null,
    'verificationMessage' => null,
    'verifiedJournalData' => null,
    'autoFillSuccessMessage' => null,
])

<div class="p-5 sm:p-6 bg-zinc-50/70 dark:bg-zinc-900/40 rounded-2xl border border-zinc-200 dark:border-zinc-800 space-y-5">
    {{-- Header --}}
    <div class="flex items-center justify-between pb-3 border-b border-zinc-200/80 dark:border-zinc-800">
        <div class="flex items-center gap-2.5">
            <div class="size-8 rounded-xl bg-brand/10 text-brand flex items-center justify-center shrink-0">
                <i class="fa-solid fa-book-open text-sm"></i>
            </div>
            <div>
                <h4 class="font-bold text-sm text-zinc-900 dark:text-white" x-text="form.id ? 'Edit Research Journal Role' : 'Add Research Journal Role'"></h4>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Search registered journals or verify ISSN with official registries (Crossref, OpenAlex, DOAJ, ISSN Portal).</p>
            </div>
        </div>
        <button 
            type="button" 
            @click="closeForm()" 
            class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition p-1.5 rounded-lg hover:bg-zinc-200/60 dark:hover:bg-zinc-800 cursor-pointer"
            title="Close Form"
        >
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <div class="space-y-4">
        {{-- Row 1: Role * --}}
        <div wire:ignore.self>
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Role <span class="text-red-500">*</span>
            </label>
            <x-searchable-select 
                alpine-model="form.role" 
                :options="$journalRoles" 
                :clearable="false"
                size="sm"
                has-error-expr="errors.role"
                placeholder="Select Role" 
                search-placeholder="Search role (e.g. Editor-in-Chief, Reviewer)..." 
                no-results-text="No role found" 
                wire:key="journal-role-select-{{ $itemId ?: 'new' }}" 
            />
            <p x-show="errors.role" x-cloak x-text="errorMessages.role || 'Role is required.'" class="text-[11px] text-red-500 mt-1"></p>
        </div>

        {{-- Row 2: Journal Name * (2 cols) & Journal Short Title (1 col) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Journal Name * with Live Autocomplete + Official Registry Search --}}
            <div 
                class="md:col-span-2 relative"
                @click.outside="journalDropdownOpen = false"
            >
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        Journal Name <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <span x-show="journalLoading" x-cloak class="text-[11px] text-brand font-medium animate-pulse">Searching existing...</span>
                        <button 
                            type="button" 
                            @click="openRegistrySearchModal(form.journal_title)" 
                            class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex flex-row items-center gap-1 cursor-pointer whitespace-nowrap"
                            title="Search Crossref / OpenAlex official registries by journal name"
                        >
                            <i class="fa-solid fa-magnifying-glass text-[10px] shrink-0"></i>
                            <span>Search Registry by Name</span>
                        </button>
                    </div>
                </div>

                <div class="relative">
                    <input 
                        type="text" 
                        x-model="form.journal_title"
                        @input.debounce.350ms="onJournalTitleInput()"
                        @focus="if ((form.journal_title || '').trim().length >= 2) { searchJournals(); journalDropdownOpen = true; }"
                        @keydown.escape="journalDropdownOpen = false"
                        @keydown.enter.prevent="selectNewJournal()"
                        placeholder="Type or search journal name (e.g. International Journal of Engineering...)"
                        :class="errors.journal_title ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                        class="w-full h-10 px-3.5 pr-9 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />
                    <div x-show="journalLoading" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                    </div>
                </div>
                <p x-show="errors.journal_title" x-cloak x-text="errorMessages.journal_title || 'Journal name is required.'" class="text-[11px] text-red-500 mt-1"></p>

                {{-- Autocomplete Suggestions Dropdown --}}
                <div 
                    x-show="journalDropdownOpen && (form.journal_title || '').trim().length >= 2" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute left-0 right-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-xl bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 shadow-xl py-1 text-xs"
                >
                    <div x-show="journalLoading && journalResults.length === 0" class="px-3.5 py-3 text-center text-xs text-zinc-500 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin text-brand"></i>
                        <span>Searching registered journals...</span>
                    </div>

                    <template x-if="journalResults.length > 0">
                        <div>
                            <div class="px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-400 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                                <span>Existing registered journals (click to auto-fill)</span>
                                <span x-text="journalResults.length + ' found'"></span>
                            </div>

                            <template x-for="item in journalResults" :key="item.id">
                                <div 
                                    @click="selectExistingJournalFromDropdown(item)"
                                    class="px-3.5 py-2.5 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 cursor-pointer flex items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 transition"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 text-xs truncate" x-text="item.journal_title"></div>
                                        <div class="flex items-center flex-wrap gap-2 mt-0.5 text-zinc-500 dark:text-zinc-400">
                                            <span x-show="item.journal_short_name" class="font-mono text-[10px] text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded" x-text="item.journal_short_name"></span>
                                            <span x-show="item.e_issn" class="text-[11px] font-mono" x-text="'e-ISSN: ' + item.e_issn"></span>
                                            <span x-show="item.p_issn" class="text-[11px] font-mono" x-text="'p-ISSN: ' + item.p_issn"></span>
                                        </div>
                                    </div>

                                    <div class="shrink-0 flex items-center gap-1.5">
                                        <span 
                                            x-show="item.status === 1"
                                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                                        >
                                            Active
                                        </span>
                                        <span 
                                            x-show="item.status !== 1"
                                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                                        >
                                            Inactive
                                        </span>
                                    </div>
                                </div>
                            </template>

                            {{-- Add New Journal Footer Option (when no exact match exists, same as /deposit-article) --}}
                            <div 
                                x-show="!hasExactJournalMatch()"
                                class="p-2 bg-zinc-50 dark:bg-zinc-800/90 border-t border-zinc-100 dark:border-zinc-700/60 flex items-center justify-between gap-2"
                            >
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Can't find your journal?</span>
                                <button 
                                    type="button" 
                                    @click="openAddNewJournalModal()" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-bold rounded-lg shadow-xs transition cursor-pointer"
                                >
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add Journal</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div 
                        x-show="hasSearchedJournals && journalResults.length === 0 && !journalLoading"
                        @click="openAddNewJournalModal()"
                        class="px-3.5 py-2.5 bg-brand/5 hover:bg-brand/10 dark:bg-brand/10 dark:hover:bg-brand/20 cursor-pointer flex items-center justify-between gap-3 transition"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <i class="fa-solid fa-circle-plus text-sm shrink-0 text-brand"></i>
                            <span class="truncate text-xs text-zinc-800 dark:text-zinc-200">
                                No existing journal matched. Add as new: <strong class="text-brand font-bold underline" x-text="form.journal_title"></strong>
                            </span>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/15 text-brand uppercase tracking-wider">
                            Add New
                        </span>
                    </div>
                </div>
            </div>

            {{-- Journal Short Title --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Journal Short Title
                </label>
                <input 
                    type="text" 
                    x-model="form.journal_short_name"
                    @input="errors.journal_short_name = false"
                    placeholder="e.g. IJEDR"
                    :class="errors.journal_short_name ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.journal_short_name" x-cloak x-text="errorMessages.journal_short_name" class="text-[11px] text-red-500 mt-1"></p>
            </div>
        </div>

        {{-- Row 3: Official Journal Verification & Registry Check (Aligned with Section 2 of Admin Journal Create) --}}
        <div class="p-3.5 rounded-xl border border-sky-100 dark:border-sky-950/60 bg-gradient-to-r from-sky-50/70 via-indigo-50/40 to-transparent dark:from-sky-950/20 dark:via-indigo-950/10 dark:to-transparent flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="size-8 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-certificate text-sm"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11px] font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">Official Journal Verification</span>
                        @if($isJournalVerifiedOnline === true)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800">
                                <i class="fa-solid fa-check text-[9px]"></i> Verified ({{ $verifiedSourceLabel ?? 'Official Registry' }})
                            </span>
                        @elseif($isJournalVerifiedOnline === false)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-300 dark:border-rose-800">
                                <i class="fa-solid fa-xmark text-[9px]"></i> Unverified
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                Pending Verification
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Check official ISSN existence against Crossref, OpenAlex, DOAJ, or ISSN Portal.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button 
                    type="button"
                    @click="openRegistrySearchModal(form.journal_title)"
                    class="inline-flex flex-row items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-800 shadow-xs transition-all cursor-pointer whitespace-nowrap"
                    title="Search Crossref / OpenAlex official registries by journal name"
                >
                    <i class="fa-solid fa-magnifying-glass text-[11px] shrink-0"></i>
                    <span>Search by Name</span>
                </button>

                <select 
                    wire:model.live="verificationSource"
                    class="px-3 py-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs font-medium text-zinc-700 dark:text-zinc-300 focus:outline-none focus:ring-1 focus:ring-brand"
                    title="Select Registry Provider"
                >
                    <option value="auto">Auto (Any Official Registry)</option>
                    <option value="crossref">Crossref API</option>
                    <option value="openalex">OpenAlex API</option>
                    <option value="doaj">DOAJ API</option>
                    <option value="issn_portal">Official ISSN Portal</option>
                </select>

                <button 
                    type="button"
                    @click="verifyCurrentJournal()"
                    :disabled="isVerifyingJournal"
                    class="inline-flex flex-row items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-sky-600 hover:bg-sky-500 active:bg-sky-700 shadow-xs transition-all disabled:opacity-50 cursor-pointer whitespace-nowrap"
                >
                    <span x-show="!isVerifyingJournal" class="inline-flex flex-row items-center gap-1.5 whitespace-nowrap">
                        <i class="fa-solid fa-magnifying-glass text-[11px] shrink-0"></i>
                        <span>Verify Journal</span>
                    </span>
                    <span x-show="isVerifyingJournal" x-cloak class="inline-flex flex-row items-center gap-1.5 whitespace-nowrap">
                        <i class="fa-solid fa-circle-notch fa-spin text-[11px] shrink-0"></i>
                        <span>Verifying...</span>
                    </span>
                </button>
            </div>
        </div>

        {{-- Verification Feedback Banners --}}
        @if($isJournalVerifiedOnline === true && !empty($verifiedJournalData))
            <div class="p-3 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="space-y-0.5">
                    <div class="font-semibold text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                        <span>{{ $verifiedJournalData['message'] ?? 'Journal verified successfully.' }}</span>
                    </div>
                    @if(!empty($verifiedJournalData['title']))
                        <div class="text-[11px] text-emerald-700 dark:text-emerald-400/80 pl-5">
                            Official Title: <strong>{{ $verifiedJournalData['title'] }}</strong>
                            @if(!empty($verifiedJournalData['publisher']))
                                &bull; Publisher: <strong>{{ $verifiedJournalData['publisher'] }}</strong>
                            @endif
                        </div>
                    @endif
                </div>
                <button 
                    type="button" 
                    @click="$wire.openAutoFillModalWithForm(getVerificationSnapshot())"
                    wire:loading.attr="disabled"
                    wire:target="openAutoFillModalWithForm"
                    class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white shadow-xs transition shrink-0 cursor-pointer disabled:opacity-50"
                >
                    <i class="fa-solid fa-arrows-rotate text-[11px]"></i>
                    <span>Auto-fill Details</span>
                </button>
            </div>

            @if(!empty($autoFillSuccessMessage))
                <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200 flex items-center justify-between gap-2 transition">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-certificate text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                        <span class="font-medium">{{ $autoFillSuccessMessage }}</span>
                    </div>
                    <button type="button" wire:click="$set('autoFillSuccessMessage', null)" class="text-emerald-600 hover:text-emerald-800 dark:hover:text-emerald-100 text-sm leading-none cursor-pointer">&times;</button>
                </div>
            @endif
        @elseif($isJournalVerifiedOnline === false)
            <div class="p-3 rounded-xl bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/60 text-xs text-rose-800 dark:text-rose-300 flex items-start gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 shrink-0 mt-0.5"></i>
                <div>
                    <span class="font-semibold">Verification Alert:</span>
                    <span>{{ $verificationMessage ?? 'The entered ISSN could not be verified in official registries.' }}</span>
                </div>
            </div>
        @endif

        {{-- Row 4: e-ISSN & p-ISSN --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- e-ISSN --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    e-ISSN <span class="text-zinc-400 font-normal">(or p-ISSN required)</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.e_issn"
                    @input="onIssnInput($event, 'e_issn')"
                    maxlength="9"
                    placeholder="e.g. 2321-9939"
                    :class="errors.e_issn ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs font-mono uppercase rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.e_issn" x-cloak x-text="errorMessages.e_issn || 'Valid e-ISSN is required.'" class="text-[11px] text-red-500 mt-1"></p>
            </div>

            {{-- p-ISSN --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    p-ISSN <span class="text-zinc-400 font-normal">(or e-ISSN required)</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.p_issn"
                    @input="onIssnInput($event, 'p_issn')"
                    maxlength="9"
                    placeholder="e.g. 2321-9939"
                    :class="errors.p_issn ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs font-mono uppercase rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.p_issn" x-cloak x-text="errorMessages.p_issn || 'Invalid p-ISSN.'" class="text-[11px] text-red-500 mt-1"></p>
            </div>
        </div>

        {{-- Row 5: Publisher Name & Journal URL --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Publisher Name --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Publisher Name
                </label>
                <input 
                    type="text" 
                    x-model="form.organization_name"
                    @input="errors.organization_name = false"
                    placeholder="e.g. IJEDR Publishing or Oxford University Press"
                    :class="errors.organization_name ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.organization_name" x-cloak x-text="errorMessages.organization_name" class="text-[11px] text-red-500 mt-1"></p>
            </div>

            {{-- Journal URL --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Journal URL
                </label>
                <input 
                    type="url" 
                    x-model="form.journal_webiste_url"
                    @input="errors.journal_webiste_url = false"
                    placeholder="https://www.ijedr.org"
                    :class="errors.journal_webiste_url ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.journal_webiste_url" x-cloak x-text="errorMessages.journal_webiste_url || 'Please enter a valid website URL.'" class="text-[11px] text-red-500 mt-1"></p>
            </div>
        </div>

        {{-- Action Row (Save / Cancel / Status / Delete Inside Form Only) --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-zinc-200/80 dark:border-zinc-800">
            <div class="flex flex-wrap items-center gap-2.5">
                <button 
                    type="button" 
                    @click="saveItem()"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-60"
                >
                    <i x-show="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <i x-show="!isSaving" class="fa-solid fa-check text-xs"></i>
                    <span x-text="form.id ? 'Update Role' : 'Save Role'"></span>
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
                                name="form_status_journal_role_{{ $itemId ?? 'new' }}" 
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
                                name="form_status_journal_role_{{ $itemId ?? 'new' }}" 
                                value="0" 
                                :checked="form.status == 0 || form.status === '0'"
                                @change="form.status = 0"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-400 border border-red-200 dark:border-red-800/60">
                                <span class="size-1.5 rounded-full bg-red-500"></span>
                                Inactive
                            </span>
                        </label>
                    </div>
                @endif
            </div>

            {{-- Delete Button (Inside Form Only; Shown when editing an existing record) --}}
            <template x-if="form.id">
                <button 
                    type="button" 
                    @click="deleteItem(form.id)"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl text-white bg-red-600 hover:bg-red-700 active:scale-95 transition-all shadow-xs cursor-pointer ml-auto"
                    title="Delete research journal role record"
                >
                    <i class="fa-regular fa-trash-can text-xs"></i>
                    <span>Delete</span>
                </button>
            </template>
        </div>
    </div>
</div>

