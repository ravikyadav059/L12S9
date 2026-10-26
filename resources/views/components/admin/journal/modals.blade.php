<!-- ================= MODAL 1: JOURNAL ALREADY REGISTERED ================= -->
<div 
    x-show="showExistingModal"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="cancelExistingJournal()"
>
    <!-- Backdrop -->
    <div 
        x-show="showExistingModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs"
        @click="cancelExistingJournal()"
    ></div>

    <!-- Modal Card Container -->
    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            x-show="showExistingModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
            class="inline-block w-full max-w-md p-6 my-8 text-left align-middle bg-white dark:bg-[#111c26] rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 space-y-4"
            @click.stop
        >
            <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <flux:icon name="exclamation-triangle" class="size-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        Journal Already Registered
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        This journal is already present in the database.
                    </p>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900/60 border border-zinc-200 dark:border-zinc-800/80 space-y-2">
                <div class="text-sm font-bold text-zinc-900 dark:text-white break-words" x-text="selectedExistingJournal?.journal_title"></div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span 
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                        :class="selectedExistingJournal?.status === 1 ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30'"
                        x-text="selectedExistingJournal?.status === 1 ? 'Active' : 'Inactive'"
                    ></span>
                    <span x-show="selectedExistingJournal?.e_issn" class="font-mono text-[11px]" x-text="'e-ISSN: ' + selectedExistingJournal?.e_issn"></span>
                    <span x-show="selectedExistingJournal?.p_issn" class="font-mono text-[11px]" x-text="'p-ISSN: ' + selectedExistingJournal?.p_issn"></span>
                </div>
                <p class="text-xs text-zinc-600 dark:text-zinc-300 pt-1">
                    Do you want to view this journal in the journals list?
                </p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button 
                    type="button" 
                    @click="cancelExistingJournal()"
                    class="px-4 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition cursor-pointer"
                >
                    No / Cancel
                </button>
                <button 
                    type="button" 
                    @click="goToExistingJournal()"
                    class="px-4 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <span>Go to Journal in List</span>
                    <flux:icon name="arrow-right" class="size-3.5" />
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL 2: JOURNAL FOUND WITH THIS ISSN ================= -->
<div 
    x-show="showIssnFoundModal"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="dismissIssnPrompt()"
>
    <!-- Backdrop -->
    <div 
        x-show="showIssnFoundModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs"
        @click="dismissIssnPrompt()"
    ></div>

    <!-- Modal Card Container -->
    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            x-show="showIssnFoundModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
            class="inline-block w-full max-w-md p-6 my-8 text-left align-middle bg-white dark:bg-[#111c26] rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 space-y-4"
            @click.stop
        >
            <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                    <flux:icon name="identification" class="size-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        Journal Found with This ISSN!
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        An existing journal matches the entered ISSN.
                    </p>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900/60 border border-zinc-200 dark:border-zinc-800/80 space-y-2">
                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                    Are you referring to this journal?
                </p>
                <div class="text-base font-bold text-zinc-900 dark:text-white break-words" x-text="foundIssnJournal?.journal_title"></div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 pt-1">
                    <span 
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                        :class="foundIssnJournal?.status === 1 ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30'"
                        x-text="foundIssnJournal?.status === 1 ? 'Active' : 'Inactive'"
                    ></span>
                    <span x-show="foundIssnJournal?.e_issn" class="font-mono text-[11px]" x-text="'e-ISSN: ' + foundIssnJournal?.e_issn"></span>
                    <span x-show="foundIssnJournal?.p_issn" class="font-mono text-[11px]" x-text="'p-ISSN: ' + foundIssnJournal?.p_issn"></span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button 
                    type="button" 
                    @click="dismissIssnPrompt()"
                    class="px-4 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition cursor-pointer"
                >
                    No, Continue Registering New
                </button>
                <button 
                    type="button" 
                    @click="confirmUseIssnJournal()"
                    class="px-4 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <span>Yes, Use This Journal</span>
                    <flux:icon name="check" class="size-3.5" />
                </button>
            </div>
        </div>
    </div>
</div>

@props([
    'showAutoFillModal' => false,
    'autoFillDiff' => [],
    'existingDbJournalInfo' => null,
    'verifiedSourceLabel' => null,
    'showRegistrySearchModal' => false,
    'searchRegistryTitle' => '',
    'registryTitleCandidates' => [],
    'isSearchingRegistryTitle' => false,
])

@php
    $modalOpen = !empty($showAutoFillModal) || (!empty($this) && !empty($this->showAutoFillModal));
    $diffs = !empty($autoFillDiff) ? $autoFillDiff : ((!empty($this) && !empty($this->autoFillDiff)) ? $this->autoFillDiff : []);
    $existingJournal = !empty($existingDbJournalInfo) ? $existingDbJournalInfo : ((!empty($this) && !empty($this->existingDbJournalInfo)) ? $this->existingDbJournalInfo : null);
    $registryLabel = $verifiedSourceLabel ?? ((!empty($this) && !empty($this->verifiedSourceLabel)) ? $this->verifiedSourceLabel : 'Registry');
@endphp

<!-- ================= MODAL 3: AUTO-FILL CONFIRMATION & DIFF PREVIEW ================= -->
@if($modalOpen)
<div 
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    wire:keydown.escape="closeAutoFillModal"
>
    <!-- Backdrop -->
    <div 
        class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
        wire:click="closeAutoFillModal"
    ></div>

    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            class="inline-block w-full max-w-xl p-6 my-8 text-left align-middle bg-white dark:bg-[#111c26] rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 space-y-4"
            @click.stop
        >
            <!-- Header -->
            <div class="flex items-start justify-between gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                        <flux:icon name="arrow-path" class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                            Confirm Auto-Fill Details
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Verified official data via <span class="font-semibold text-sky-600 dark:text-sky-400">{{ $registryLabel }}</span>
                        </p>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="closeAutoFillModal"
                    class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 text-xl leading-none p-1 rounded-lg transition"
                >
                    &times;
                </button>
            </div>

            <!-- Existing Journal Warning if found in DB -->
            @if(!empty($existingJournal))
                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs space-y-1.5">
                    <div class="flex items-center gap-2 font-bold">
                        <flux:icon name="exclamation-triangle" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                        <span>Journal Already Registered in Your Database!</span>
                    </div>
                    <p class="text-[11px] leading-relaxed pl-6">
                        A journal matching this title or ISSN already exists in your database:
                        <strong class="font-semibold text-zinc-900 dark:text-white">&ldquo;{{ $existingJournal['title'] }}&rdquo;</strong>
                        (Status: <span class="font-semibold">{{ $existingJournal['status'] }}</span>).
                    </p>
                    <p class="text-[11px] text-amber-700 dark:text-amber-400/90 pl-6">
                        Please verify that you do not unintentionally create a duplicate journal record.
                    </p>
                </div>
            @endif

            <!-- Diff Content -->
            <div class="space-y-3">
                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                    The following fields will be updated from the verified registry data:
                </p>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden bg-zinc-50/50 dark:bg-zinc-900/40 max-h-80 overflow-y-auto">
                    @forelse($diffs as $diff)
                        <div class="p-3 text-xs space-y-1 hover:bg-zinc-100/60 dark:hover:bg-zinc-800/40 transition">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $diff['label'] }}</span>
                                @if($diff['is_overwrite'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800 shrink-0">
                                        Will overwrite existing
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800 shrink-0">
                                        Fill new field
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-start gap-1.5 font-mono text-[11px] pt-0.5">
                                @if($diff['is_overwrite'])
                                    <div class="text-zinc-400 line-through break-words max-w-xs">
                                        {{ $diff['current'] }}
                                    </div>
                                    <flux:icon name="arrow-right" class="size-3 text-zinc-400 shrink-0 hidden sm:inline-block mt-0.5" />
                                @endif
                                <div class="font-semibold text-emerald-700 dark:text-emerald-400 break-words flex-1">
                                    {{ $diff['new'] }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-zinc-500 dark:text-zinc-400">
                            All form fields are already up-to-date with registry data.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <button 
                    type="button" 
                    wire:click="closeAutoFillModal"
                    class="px-4 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition cursor-pointer"
                >
                    Cancel
                </button>
                @if(!empty($existingJournal))
                    <!-- If journal already exists in database: navigate to list or edit instead -->
                    <a 
                        href="{{ route('admin.journals.index', ['search' => $existingJournal['title']]) }}"
                        class="px-4 py-2 text-xs font-bold text-zinc-700 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition inline-flex items-center gap-1.5 cursor-pointer"
                    >
                        <flux:icon name="magnifying-glass" class="size-3.5" />
                        <span>View in Journals List</span>
                    </a>
                    <a 
                        href="{{ route('admin.journals.edit', $existingJournal['id']) }}"
                        class="px-4.5 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>Edit Existing Journal</span>
                        <flux:icon name="arrow-right" class="size-3.5" />
                    </a>
                @else
                    <!-- When NOT duplicate: show standard "Go with this change" button -->
                    <button 
                        type="button" 
                        wire:click="confirmAutoFill"
                        wire:loading.attr="disabled"
                        wire:target="confirmAutoFill"
                        class="px-4.5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] rounded-xl shadow-xs transition inline-flex flex-row items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50 whitespace-nowrap"
                    >
                        <span wire:loading.remove wire:target="confirmAutoFill" class="inline-flex flex-row items-center gap-1.5 whitespace-nowrap">
                            <flux:icon name="check" class="size-3.5 shrink-0" />
                            <span>Go with this change</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="confirmAutoFill" class="inline-flex flex-row items-center gap-1.5 whitespace-nowrap">
                            <svg class="animate-spin size-3.5 shrink-0 inline-block text-white" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span>Applying Changes...</span>
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- ================= MODAL 4: SEARCH OFFICIAL REGISTRIES BY TITLE (100% JS-DRIVEN) ================= -->
@php
    $candidates = !empty($registryTitleCandidates) ? $registryTitleCandidates : ((!empty($this) && !empty($this->registryTitleCandidates)) ? $this->registryTitleCandidates : []);
    $searchQuery = $searchRegistryTitle ?? ((!empty($this) && !empty($this->searchRegistryTitle)) ? $this->searchRegistryTitle : '');
@endphp

<div 
    x-show="showRegistrySearchModal && !{{ $modalOpen ? 'true' : 'false' }}"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    x-on:close-registry-search-modal.window="showRegistrySearchModal = false; if (typeof closeRegistrySearchModal === 'function') closeRegistrySearchModal();"
    @keydown.escape.window="closeRegistrySearchModal()"
>
    <!-- Backdrop -->
    <div 
        x-show="showRegistrySearchModal && !{{ $modalOpen ? 'true' : 'false' }}"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs"
        @click="closeRegistrySearchModal()"
    ></div>

    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            x-show="showRegistrySearchModal && !{{ $modalOpen ? 'true' : 'false' }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
            class="inline-block w-full max-w-2xl p-6 my-8 text-left align-middle bg-white dark:bg-[#111c26] rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 space-y-4"
            @click.stop
        >
            <!-- Loading Overlay while selectRegistryCandidate is running -->
            <div 
                wire:loading.flex 
                wire:target="selectRegistryCandidate"
                class="absolute inset-0 bg-white/95 dark:bg-[#111c26]/95 backdrop-blur-xs flex flex-col items-center justify-center z-30 rounded-2xl p-6 text-center space-y-3"
            >
                <div class="relative size-12 flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full border-3 border-brand/20"></div>
                    <div class="absolute inset-0 rounded-full border-3 border-brand border-t-transparent animate-spin"></div>
                    <flux:icon name="arrow-path" class="size-5 text-brand" />
                </div>
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Verifying Journal & Preparing Auto-Fill...</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">
                        Validating ISSN, checking editorial contacts, indexing agencies, and building auto-fill preview.
                    </p>
                </div>
            </div>

            <!-- Header -->
            <div class="flex items-start justify-between gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <flux:icon name="magnifying-glass" class="size-5 shrink-0" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                            Search Official Registry by Journal Name
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Search Crossref and OpenAlex official registries to find journal details & ISSNs
                        </p>
                    </div>
                </div>
                <button 
                    type="button" 
                    @click="closeRegistrySearchModal()"
                    class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 text-xl leading-none p-1 rounded-lg transition cursor-pointer"
                >
                    &times;
                </button>
            </div>

            <!-- Search Input Box -->
            <form @submit.prevent="triggerRegistrySearch()" class="space-y-2">
                <div class="relative flex items-center gap-2">
                    <div class="relative flex-1">
                        <input 
                            type="text" 
                            x-model="registrySearchInput"
                            placeholder="e.g. Nature, The Lancet, IEEE Transactions on Software Engineering..."
                            class="w-full h-11 px-4 pr-10 text-sm rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"
                            autofocus
                        />
                    </div>
                    <button 
                        type="submit" 
                        wire:loading.attr="disabled"
                        wire:target="searchRegistryByTitle"
                        class="h-11 px-5 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs transition inline-flex flex-row items-center justify-center gap-2 shrink-0 cursor-pointer disabled:opacity-50 whitespace-nowrap"
                    >
                        <span wire:loading.remove wire:target="searchRegistryByTitle" class="inline-flex flex-row items-center gap-1.5 whitespace-nowrap">
                            <flux:icon name="magnifying-glass" class="size-4 shrink-0" />
                            <span>Search</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="searchRegistryByTitle" class="inline-flex flex-row items-center gap-1.5 whitespace-nowrap">
                            <svg class="animate-spin size-4 shrink-0 inline-block text-white" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span>Searching...</span>
                        </span>
                    </button>
                </div>
            </form>

            <!-- Results Section -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 px-1">
                    <span>Registry Candidates ({{ count($candidates) }})</span>
                    <span class="text-[11px] text-zinc-400">Click &ldquo;Select & Fill&rdquo; to auto-populate form</span>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden bg-zinc-50/50 dark:bg-zinc-900/40 max-h-72 overflow-y-auto">
                    @forelse($candidates as $index => $candidate)
                        <div class="p-3 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-zinc-100/60 dark:hover:bg-zinc-800/40 transition">
                            <div class="space-y-1 min-w-0 flex-1">
                                <div class="font-bold text-zinc-900 dark:text-white truncate text-sm">
                                    {{ $candidate['title'] ?? 'Unknown Title' }}
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-[11px] text-zinc-500 dark:text-zinc-400">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-300 dark:border-sky-800">
                                        {{ $candidate['source'] ?? 'Registry' }}
                                    </span>
                                    @if(!empty($candidate['e_issn']))
                                        <span class="font-mono">e-ISSN: {{ $candidate['e_issn'] }}</span>
                                    @endif
                                    @if(!empty($candidate['p_issn']) && $candidate['p_issn'] !== ($candidate['e_issn'] ?? ''))
                                        <span class="font-mono">p-ISSN: {{ $candidate['p_issn'] }}</span>
                                    @endif
                                    @if(!empty($candidate['publisher']))
                                        <span class="truncate max-w-xs text-zinc-400">&bull; {{ $candidate['publisher'] }}</span>
                                    @endif
                                    @if(!empty($candidate['country']))
                                        <span class="text-zinc-400">&bull; {{ $candidate['country'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <button 
                                    type="button" 
                                    wire:click="selectRegistryCandidate({{ $index }})"
                                    wire:loading.attr="disabled"
                                    class="px-3.5 py-1.5 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-lg shadow-xs transition inline-flex flex-row items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50 whitespace-nowrap"
                                >
                                    <span wire:loading.remove wire:target="selectRegistryCandidate({{ $index }})" class="inline-flex items-center gap-1.5">
                                        <flux:icon name="arrow-down-tray" class="size-3.5 shrink-0" />
                                        <span>Select & Fill</span>
                                    </span>
                                    <span wire:loading.inline-flex wire:target="selectRegistryCandidate({{ $index }})" class="inline-flex items-center gap-1.5">
                                        <svg class="animate-spin size-3.5 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <span>Verifying...</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-zinc-500 dark:text-zinc-400 space-y-1">
                            @if(!empty($searchQuery))
                                <flux:icon name="magnifying-glass" class="size-6 text-zinc-400 mx-auto mb-2 opacity-50" />
                                <p class="font-semibold text-zinc-700 dark:text-zinc-300">No journals found matching &ldquo;{{ $searchQuery }}&rdquo;</p>
                                <p class="text-[11px]">Try checking the spelling or searching by a broader keyword.</p>
                            @else
                                <flux:icon name="information-circle" class="size-6 text-zinc-400 mx-auto mb-2 opacity-50" />
                                <p>Enter a journal title above and click Search to look up official records.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <button 
                    type="button" 
                    @click="closeRegistrySearchModal()"
                    class="px-4 py-2 text-xs font-semibold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
