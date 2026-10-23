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

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden bg-zinc-50/50 dark:bg-zinc-900/40 max-h-64 overflow-y-auto">
                    @forelse($diffs as $diff)
                        <div class="p-3 text-xs space-y-1 hover:bg-zinc-100/60 dark:hover:bg-zinc-800/40 transition">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $diff['label'] }}</span>
                                @if($diff['is_overwrite'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800">
                                        Will overwrite existing
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800">
                                        Fill new field
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center gap-1.5 font-mono text-[11px] pt-0.5">
                                @if($diff['is_overwrite'])
                                    <div class="text-zinc-400 line-through truncate max-w-xs">
                                        {{ $diff['current'] }}
                                    </div>
                                    <flux:icon name="arrow-right" class="size-3 text-zinc-400 shrink-0 hidden sm:inline-block" />
                                @endif
                                <div class="font-semibold text-emerald-700 dark:text-emerald-400 truncate">
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
                <button 
                    type="button" 
                    wire:click="confirmAutoFill"
                    wire:loading.attr="disabled"
                    wire:target="confirmAutoFill"
                    class="px-4.5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="confirmAutoFill" class="flex items-center gap-1.5">
                        <flux:icon name="check" class="size-3.5" />
                        Go with this change
                    </span>
                    <span wire:loading wire:target="confirmAutoFill" class="flex items-center gap-1.5">
                        <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Applying Changes...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
