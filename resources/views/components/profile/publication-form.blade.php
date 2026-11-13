@props([
    'showStatus' => false,
    'itemId' => null,
    'articleTypes' => [],
    'existingPdfName' => null,
])

<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">
        @if($showStatus)
            <template x-if="form.id">
                <div class="flex items-center gap-2 pb-1 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-500">Publication Record ID:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Click to select">
                        <span class="text-zinc-400 select-none">ID:</span>
                        <span x-text="form.id"></span>
                    </span>
                </div>
            </template>
        @endif
        {{-- 1. DOI (Optional) + Retrieve Button  |  2. Title * --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    DOI (Optional)
                </label>
                <div class="flex items-center gap-2">
                    <input 
                        type="text" 
                        x-model="form.doi"
                        @keydown.enter.prevent="retrieveDoi()"
                        placeholder="e.g., 10.1038/s41598-024-xxxxx"
                        class="flex-1 min-w-0 h-10 px-3.5 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />
                    <button 
                        type="button" 
                        @click="retrieveDoi()"
                        :disabled="isRetrievingDoi"
                        class="inline-flex items-center gap-1.5 h-10 px-4 text-xs font-bold text-white bg-[#198BEA] hover:bg-[#1476c9] rounded-xl shadow-xs transition-all cursor-pointer disabled:opacity-60 shrink-0"
                    >
                        <i x-show="isRetrievingDoi" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <i x-show="!isRetrievingDoi" class="fa-solid fa-download text-xs"></i>
                        <span x-text="isRetrievingDoi ? 'Retrieving...' : 'Retrieve'"></span>
                    </button>
                </div>
                <p x-show="doiFeedback.message" x-cloak x-text="doiFeedback.message" :class="doiFeedback.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'" class="text-[11px] font-medium mt-1"></p>
            </div>

            <div id="field-title-{{ $itemId ?? 'new' }}">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Title <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.title"
                    @input="errors.title = false; errorMessages.title = ''"
                    @blur="checkTitleDuplicate()"
                    placeholder="Enter full publication title"
                    :class="errors.title ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.title" x-cloak x-text="errorMessages.title || 'Title is required.'" class="text-[11px] text-red-500 mt-1"></p>
            </div>
        </div>

        {{-- 3. URL  |  4. Article Type * --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- URL --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    URL
                </label>
                <input 
                    type="url" 
                    x-model="form.url"
                    @input="errors.url = false; errorMessages.url = ''"
                    placeholder="https://doi.org/..."
                    :class="errors.url ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.url" x-cloak x-text="errorMessages.url || 'Please enter a valid URL.'" class="text-[11px] text-red-500 mt-1"></p>
            </div>

            {{-- Article Type * --}}
            <div wire:ignore.self>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Article Type <span class="text-red-500">*</span>
                </label>
                <x-searchable-select 
                    alpine-model="form.type" 
                    :options="$articleTypes" 
                    :clearable="false"
                    size="sm"
                    has-error-expr="errors.type"
                    placeholder="Select Article Type" 
                    search-placeholder="Search article type..." 
                    no-results-text="No article type found" 
                    wire:key="pub-article-type-select-{{ $itemId ?: 'new' }}" 
                />
                <p x-show="errors.type" x-cloak x-text="errorMessages.type || 'Article Type is required.'" class="text-[11px] text-red-500 mt-1"></p>
            </div>
        </div>

        {{-- 5. Authors (Matches Project -> Other Contributors)  |  6. Journal * --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Authors --}}
            <div class="relative" @click.outside="authorDropdownOpen = false">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Authors <span class="text-red-500">*</span>
                </label>

                {{-- Selected Authors Pills --}}
                <div x-show="form.authors && form.authors.filter(a => a && (a.name || '').trim()).length > 0" class="flex flex-wrap gap-1.5 mb-2">
                    <template x-for="(author, aIdx) in form.authors" :key="aIdx">
                        <template x-if="author && (author.name || '').trim()">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-50 text-[#0284c7] dark:bg-sky-950/50 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                <span x-text="author.name"></span>
                                <button type="button" @click="removeAuthorChip(aIdx)" class="hover:text-red-500 cursor-pointer">
                                    <i class="fa-solid fa-xmark text-[11px]"></i>
                                </button>
                            </span>
                        </template>
                    </template>
                </div>

                {{-- Search Input with Loading Indicator --}}
                <div class="relative flex items-center">
                    <input 
                        type="text"
                        x-model="authorSearchQuery"
                        @focus="if(authorSearchQuery && authorSearchQuery.trim().length >= 2) { authorDropdownOpen = true; if(authorSearchResults.length === 0 && !isSearchingAuthor) { searchAuthorsCompact(); } }"
                        @input.debounce.250ms="searchAuthorsCompact()"
                        @keydown.enter.prevent="if(authorSearchResults.length > 0) { selectAuthorChip(authorSearchResults[0]); } else if(!isSearchingAuthor && authorSearchQuery && authorSearchQuery.trim().length >= 2) { openAddAuthorModal(authorSearchQuery); }"
                        placeholder="Search author by name..."
                        :class="errors.authors ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                        class="w-full h-10 pl-3.5 pr-9 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />
                    <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-400">
                        <template x-if="isSearchingAuthor">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                        </template>
                        <template x-if="!isSearchingAuthor">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </template>
                    </div>
                </div>
                <p x-show="errors.authors" x-cloak x-text="errorMessages.authors || 'At least one author is required.'" class="text-[11px] text-red-500 mt-1"></p>

                {{-- Autocomplete Dropdown Popup (Matches Project -> Other Contributors) --}}
                <div 
                    x-show="authorDropdownOpen" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800"
                >
                    <div class="px-3.5 py-2 bg-zinc-50 dark:bg-zinc-800/80 flex items-center justify-between text-xs">
                        <span class="font-bold uppercase tracking-wider text-[10px] text-zinc-500 dark:text-zinc-400">
                            Registered Authors
                        </span>
                        <template x-if="!isSearchingAuthor && authorSearchResults.length > 0">
                            <span x-text="authorSearchResults.length + ' found'" class="text-[10px] font-semibold text-brand bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded-full"></span>
                        </template>
                    </div>

                    <div class="max-h-60 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/60">
                        <div x-show="isSearchingAuthor" class="px-3.5 py-4 text-xs text-zinc-400 text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                            <span>Searching authors...</span>
                        </div>

                        <div x-show="!isSearchingAuthor">
                            <template x-for="author in authorSearchResults" :key="author.id">
                                <div 
                                    @click="selectAuthorChip(author)"
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

                            <div x-show="authorSearchResults.length === 0 && authorSearchQuery && authorSearchQuery.trim().length >= 2" class="p-4 text-center space-y-3">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    No registered author found for "<span class="font-semibold text-zinc-700 dark:text-zinc-200" x-text="authorSearchQuery"></span>"
                                </p>
                                <button 
                                    type="button"
                                    @click="openAddAuthorModal(authorSearchQuery)"
                                    class="w-full border-[1.5px] border-dashed border-[#198BEA] rounded-xl px-3.5 py-2.5 flex items-center gap-2.5 cursor-pointer bg-blue-50/50 hover:bg-blue-100/70 dark:bg-blue-950/30 dark:hover:bg-blue-950/50 transition select-none text-left"
                                >
                                    <span class="size-6 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-plus"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100">
                                            Add New Author "<span class="text-brand font-bold" x-text="authorSearchQuery.trim()"></span>"
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

            {{-- Journal * (with Redis/Indexed Search + Shared Add New Journal Modal) --}}
            <div class="relative" @click.outside="journalDropdownOpen = false">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Journal <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input 
                        type="text" 
                        x-model="form.journal_name"
                        @input.debounce.250ms="searchJournals()"
                        @input="form.journal_title = ''; errors.journal_title = false; errorMessages.journal_title = ''"
                        @focus="if (!form.journal_title && form.journal_name.trim().length >= 2) { if (journalResults.length === 0) { searchJournals(); } else { journalDropdownOpen = true; } }"
                        placeholder="Search journal name..."
                        :class="errors.journal_title ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : (form.journal_title ? 'border-emerald-400 dark:border-emerald-600 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white')"
                        class="w-full h-10 pl-3.5 pr-9 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />
                    <div x-show="isSearchingJournals" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                    </div>
                    <div x-show="form.journal_title && !isSearchingJournals" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" title="Journal exists in database">
                        <i class="fa-solid fa-circle-check text-emerald-500 text-sm"></i>
                    </div>
                </div>
                <p x-show="errors.journal_title" x-cloak x-text="errorMessages.journal_title || 'Please select a journal or add a new one.'" class="text-[11px] text-red-500 mt-1"></p>

                {{-- Journal Dropdown --}}
                <div 
                    x-show="journalDropdownOpen && !form.journal_title && form.journal_name.trim().length >= 2"
                    x-cloak
                    class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl divide-y divide-zinc-100 dark:divide-zinc-700/60"
                >
                    <template x-if="journalResults.length > 0">
                        <div>
                            <template x-for="j in journalResults" :key="j.id || j._id">
                                <button 
                                    type="button"
                                    @click="selectJournal(j)"
                                    class="w-full text-left px-3.5 py-2.5 hover:bg-sky-50/70 dark:hover:bg-zinc-700/60 transition flex items-center justify-between gap-3 cursor-pointer"
                                >
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100 truncate" x-text="j.journal_title"></div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-2 mt-0.5">
                                            <span x-show="j.journal_short_name" x-text="j.journal_short_name" class="font-medium"></span>
                                            <span x-show="j.e_issn" x-text="'e-ISSN: ' + j.e_issn"></span>
                                            <span x-show="j.p_issn" x-text="'p-ISSN: ' + j.p_issn"></span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-semibold text-[#198BEA] shrink-0">Select</span>
                                </button>
                            </template>
                        </div>
                    </template>

                    <template x-if="journalResults.length === 0 && !isSearchingJournals">
                        <div class="px-4 py-3 text-center space-y-2">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                No existing journal matched. Add as new: <span class="font-semibold text-zinc-700 dark:text-zinc-200" x-text="form.journal_name"></span>
                            </p>
                            <button 
                                type="button"
                                @click="openAddJournalModalJs()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-lg transition shadow-xs cursor-pointer"
                            >
                                <i class="fa-solid fa-plus text-[10px]"></i>
                                <span>Add New</span>
                            </button>
                        </div>
                    </template>

                    <template x-if="journalResults.length > 0">
                        <div class="p-2 bg-zinc-50 dark:bg-zinc-800/80 text-center">
                            <button 
                                type="button"
                                @click="openAddJournalModalJs()"
                                class="w-full py-1.5 px-3 text-xs font-semibold text-[#198BEA] hover:bg-sky-50 dark:hover:bg-sky-950/40 rounded-lg transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <i class="fa-solid fa-plus text-[10px]"></i>
                                <span>Add New Journal instead</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- 6. ISSN * (Segmented Tabs: Both (E & P) | E-ISSN | P-ISSN) --}}
        <div class="space-y-2.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    ISSN <span class="text-red-500">*</span>
                </label>

                <div class="inline-flex items-center p-0.5 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                    <button 
                        type="button" 
                        @click="setIssnType('both')"
                        :class="form.issn_type === 'both' ? 'bg-white dark:bg-zinc-900 text-[#198BEA] shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200'"
                        class="px-3 py-1 text-xs font-bold rounded-lg transition cursor-pointer"
                    >
                        Both (E &amp; P)
                    </button>
                    <button 
                        type="button" 
                        @click="setIssnType('e_issn')"
                        :class="form.issn_type === 'e_issn' ? 'bg-white dark:bg-zinc-900 text-[#198BEA] shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200'"
                        class="px-3 py-1 text-xs font-bold rounded-lg transition cursor-pointer"
                    >
                        E-ISSN
                    </button>
                    <button 
                        type="button" 
                        @click="setIssnType('p_issn')"
                        :class="form.issn_type === 'p_issn' ? 'bg-white dark:bg-zinc-900 text-[#198BEA] shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200'"
                        class="px-3 py-1 text-xs font-bold rounded-lg transition cursor-pointer"
                    >
                        P-ISSN
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- E-ISSN --}}
                <div x-show="form.issn_type === 'both' || form.issn_type === 'e_issn'">
                    <label class="block text-xs font-semibold text-zinc-600 dark:text-zinc-400 mb-1.5">
                        E-ISSN (Electronic) <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        x-model="form.e_issn"
                        @input="formatIssnField('e_issn'); errors.e_issn = false; errorMessages.e_issn = ''"
                        maxlength="9"
                        placeholder="e.g. 1757-790X"
                        :class="errors.e_issn ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                        class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />
                    <p x-show="errors.e_issn" x-cloak x-text="errorMessages.e_issn || 'Valid E-ISSN is required.'" class="text-[11px] text-red-500 mt-1"></p>
                </div>

                {{-- P-ISSN --}}
                <div x-show="form.issn_type === 'both' || form.issn_type === 'p_issn'">
                    <label class="block text-xs font-semibold text-zinc-600 dark:text-zinc-400 mb-1.5">
                        P-ISSN (Print) <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        x-model="form.p_issn"
                        @input="formatIssnField('p_issn'); errors.p_issn = false; errorMessages.p_issn = ''"
                        maxlength="9"
                        placeholder="e.g. 1234-5679"
                        :class="errors.p_issn ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                        class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />
                    <p x-show="errors.p_issn" x-cloak x-text="errorMessages.p_issn || 'Valid P-ISSN is required.'" class="text-[11px] text-red-500 mt-1"></p>
                </div>
            </div>
        </div>

        {{-- 7. Publication Date *, Citations, Volume, Issue, Page No --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- Publication Date * (Flatpickr Month & Year) --}}
            <div 
                class="lg:col-span-2"
                wire:ignore
                x-data="{
                    fp: null,
                    initPicker() {
                        if (typeof flatpickr === 'undefined') {
                            return;
                        }
                        const plugins = typeof monthSelectPlugin !== 'undefined'
                            ? [new monthSelectPlugin({ shorthand: false, dateFormat: 'F Y', altFormat: 'F Y' })]
                            : [];
                        this.fp = flatpickr(this.$refs.pubDateInput, {
                            plugins: plugins,
                            dateFormat: 'F Y',
                            maxDate: new Date(),
                            defaultDate: form.published_date || null,
                            onChange: (selectedDates, dateStr) => {
                                form.published_date = dateStr;
                                errors.published_date = false;
                                errorMessages.published_date = '';
                            }
                        });
                    }
                }"
                x-init="$nextTick(() => initPicker())"
                @pub-date-updated.window="if (fp) { $event.detail ? fp.setDate($event.detail, false) : fp.clear(); }"
            >
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Publication Date <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                    <input 
                        x-ref="pubDateInput"
                        type="text" 
                        x-model="form.published_date"
                        @input="errors.published_date = false"
                        placeholder="Select Month & Year"
                        :class="errors.published_date ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                        class="w-full h-10 pl-3.5 pr-8 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                    />
                    <button 
                        type="button" 
                        x-show="form.published_date"
                        @click="if (fp) { fp.clear(); } form.published_date = ''"
                        class="absolute right-2.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 cursor-pointer"
                        title="Clear Publication Date"
                    >
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>
                <p x-show="errors.published_date" x-cloak x-text="errorMessages.published_date || 'Publication Date is required.'" class="text-[11px] text-red-500 mt-1"></p>
            </div>

            {{-- Citations --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Citations
                </label>
                <input 
                    type="number" 
                    min="0"
                    x-model="form.citations"
                    @input="errors.citations = false"
                    placeholder="e.g. 12"
                    :class="errors.citations ? 'border-red-500 bg-red-50/20' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.citations" x-cloak x-text="errorMessages.citations" class="text-[11px] text-red-500 mt-1"></p>
            </div>

            {{-- Volume --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Volume
                </label>
                <input 
                    type="number" 
                    min="1"
                    x-model="form.volume"
                    @input="errors.volume = false"
                    placeholder="e.g. 14"
                    :class="errors.volume ? 'border-red-500 bg-red-50/20' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.volume" x-cloak x-text="errorMessages.volume" class="text-[11px] text-red-500 mt-1"></p>
            </div>

            {{-- Issue --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Issue
                </label>
                <input 
                    type="text" 
                    x-model="form.issue"
                    @input="errors.issue = false"
                    placeholder="e.g. 3 or S1"
                    :class="errors.issue ? 'border-red-500 bg-red-50/20' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.issue" x-cloak x-text="errorMessages.issue" class="text-[11px] text-red-500 mt-1"></p>
            </div>
        </div>

        {{-- 8. Page No & Upload Research Paper (PDF only) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Page No --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Page No
                </label>
                <input 
                    type="text" 
                    x-model="form.page_no"
                    @input="errors.page_no = false"
                    placeholder="e.g. 1-10 or 104-118"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
            </div>

            {{-- Upload Research Paper (PDF only) --}}
            <div class="md:col-span-2">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        Upload Research Paper (PDF only)
                    </label>
                    <button 
                        type="button"
                        x-show="form.image && !form.remove_existing_pdf"
                        @click="form.remove_existing_pdf = true; form.image = ''"
                        class="text-[11px] font-semibold text-red-500 hover:text-red-700 cursor-pointer"
                    >
                        Remove existing PDF
                    </button>
                </div>

                <label class="flex items-center justify-between gap-3 h-10 px-3.5 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 hover:border-brand transition cursor-pointer">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fa-regular fa-file-pdf text-red-500 text-sm shrink-0"></i>
                        <span class="text-xs text-zinc-600 dark:text-zinc-300 truncate" x-text="selectedPdfName || (form.image && !form.remove_existing_pdf ? form.image.split('/').pop() : 'Choose PDF file (max 15MB)')"></span>
                    </div>
                    <span class="px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-[11px] font-semibold rounded-lg shrink-0">
                        Browse
                    </span>
                    <input 
                        type="file" 
                        wire:model="pdfFile" 
                        accept=".pdf,application/pdf"
                        class="hidden"
                        @change="
                            const f = $event.target.files[0];
                            if (f && f.size > 15 * 1024 * 1024) {
                                alert('File size exceeds the 15MB limit. Please choose a smaller PDF file.');
                                $event.target.value = '';
                                selectedPdfName = '';
                                return;
                            }
                            selectedPdfName = f ? f.name : '';
                            errors.pdfFile = false;
                        "
                    />
                </label>
                <div wire:loading wire:target="pdfFile" class="text-[11px] text-sky-600 dark:text-sky-400 font-medium mt-1">
                    Uploading PDF...
                </div>
                <p x-show="errors.pdfFile" x-cloak x-text="errorMessages.pdfFile" class="text-[11px] text-red-500 mt-1"></p>
            </div>
        </div>

        {{-- 9. Keywords/Tags (Optional) --}}
        <div>
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Keywords/Tags (Optional)
            </label>
            <div class="p-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 space-y-2">
                <div x-show="form.publication_keywords.length > 0" class="flex flex-wrap gap-1.5">
                    <template x-for="(kw, kIdx) in form.publication_keywords" :key="kIdx">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-[#198BEA] dark:bg-sky-950/40 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                            <span x-text="kw"></span>
                            <button type="button" @click="removeKeyword(kIdx)" class="hover:text-red-500 cursor-pointer">
                                <i class="fa-solid fa-xmark text-[10px]"></i>
                            </button>
                        </span>
                    </template>
                </div>
                <input 
                    type="text" 
                    x-model="keywordInput"
                    @keydown.enter.prevent="commitKeywordInput()"
                    @keydown.,.prevent="commitKeywordInput()"
                    @paste.prevent="handleKeywordPaste($event)"
                    @blur="commitKeywordInput()"
                    placeholder="Type and press Enter, or paste comma-separated keywords"
                    class="w-full text-xs bg-transparent text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none"
                />
            </div>
        </div>

        {{-- 10. Abstract/Description * --}}
        <div>
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Abstract/Description <span class="text-red-500">*</span>
            </label>
            <textarea 
                rows="4"
                x-model="form.description"
                @input="errors.description = false; errorMessages.description = ''"
                placeholder="Enter publication abstract or summary..."
                :class="errors.description ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white'"
                class="w-full p-3.5 text-xs rounded-xl border placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
            ></textarea>
            <p x-show="errors.description" x-cloak x-text="errorMessages.description || 'Abstract/Description is required.'" class="text-[11px] text-red-500 mt-1"></p>
        </div>

        {{-- Action Row (Save / Cancel / Status / Delete Inside Form Only) --}}
        <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800 flex-wrap gap-3">
            <div class="flex items-center gap-2 flex-wrap">
                <button 
                    type="button" 
                    @click="saveItem()"
                    :disabled="isSaving"
                    class="px-5 py-2.5 rounded-xl bg-brand hover:bg-brand-600 text-white text-xs font-bold shadow-xs hover:shadow-md transition-all cursor-pointer active:scale-95 flex items-center gap-2 disabled:opacity-60"
                >
                    <i x-show="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <i x-show="!isSaving" class="fa-solid fa-check text-xs"></i>
                    <span x-text="form.id ? 'Update' : 'Save Publication'"></span>
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
                                name="form_status_publication_{{ $itemId ?? 'new' }}" 
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
                                name="form_status_publication_{{ $itemId ?? 'new' }}" 
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
                    title="Delete publication record"
                >
                    <i class="fa-regular fa-trash-can text-xs"></i>
                    <span>Delete</span>
                </button>
            </template>
        </div>
    </div>
</div>

