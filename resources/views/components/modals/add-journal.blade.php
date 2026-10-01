@props([
    'show' => 'showAddJournalModal',
    'title' => 'newJournalTitle',
    'eIssn' => 'newJournalEIssn',
    'pIssn' => 'newJournalPIssn',
    'closeAction' => 'closeAddJournalModal',
    'saveAction' => 'saveNewJournal',
])

<!-- ================= REUSABLE ADD NEW JOURNAL POPUP MODAL ================= -->
<div 
    x-data="{
        show: @entangle($show),
        formatIssn(e, field) {
            let raw = (e.target.value || '').toUpperCase();
            let clean = '';
            for (let i = 0; i < raw.length && clean.length < 8; i++) {
                let ch = raw[i];
                if (clean.length < 7) {
                    if (/[0-9]/.test(ch)) clean += ch;
                } else {
                    if (/[0-9X]/.test(ch)) clean += ch;
                }
            }
            if (clean.length > 4) {
                clean = clean.substring(0, 4) + '-' + clean.substring(4);
            }
            e.target.value = clean;
            $wire.set(field, clean);
        }
    }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="show = false; $wire.{{ $closeAction }}();"
>
    <!-- Backdrop -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs"
        @click="$wire.{{ $closeAction }}()"
    ></div>

    <!-- Modal Card Container -->
    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
            class="inline-block w-full max-w-md p-6 my-8 text-left align-middle bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 space-y-5"
            @click.stop
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
                    Add New Journal
                </h3>
                <button 
                    type="button" 
                    wire:click="{{ $closeAction }}"
                    class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition p-1.5 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                    aria-label="Close modal"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body Form -->
            <div class="space-y-4">
                <!-- Journal Name Input -->
                <div class="space-y-1.5">
                    <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        Journal Name <span class="text-red-500">*</span>
                    </label>
                    <flux:input 
                        wire:model="{{ $title }}" 
                        placeholder="Enter Journal Name" 
                    />
                    @error($title) 
                        <p class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                    @enderror
                </div>

                <!-- e-ISSN Input -->
                <div class="space-y-1.5">
                    <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        e-ISSN
                    </label>
                    <flux:input 
                        wire:model="{{ $eIssn }}" 
                        x-on:input="formatIssn($event, '{{ $eIssn }}')" 
                        maxlength="9" 
                        placeholder="e.g., 1234-5678" 
                    />
                    @error($eIssn) 
                        <p class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                    @enderror
                </div>

                <!-- p-ISSN Input -->
                <div class="space-y-1.5">
                    <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        p-ISSN
                    </label>
                    <flux:input 
                        wire:model="{{ $pIssn }}" 
                        x-on:input="formatIssn($event, '{{ $pIssn }}')" 
                        maxlength="9" 
                        placeholder="e.g., 1234-5678" 
                    />
                    @error($pIssn) 
                        <p class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                    @enderror
                </div>

                <!-- Note: At least one ISSN required -->
                <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium pt-1">
                    <span class="text-red-500 font-bold">*</span> At least one ISSN (e-ISSN or p-ISSN) is required
                </div>

                <!-- Error Toast Alert if neither ISSN entered -->
                @error('newJournalIssnRequired')
                    <div class="p-3 bg-zinc-900 text-zinc-100 rounded-xl flex items-center gap-2.5 text-xs font-semibold shadow-lg">
                        <svg class="w-4 h-4 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <!-- Modal Footer Buttons -->
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <button 
                    type="button" 
                    wire:click="{{ $closeAction }}" 
                    class="px-5 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 text-zinc-700 dark:text-zinc-200 text-sm font-bold transition cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    wire:click="{{ $saveAction }}" 
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 rounded-xl bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-bold shadow-md shadow-[#198BEA]/20 transition flex items-center gap-2 cursor-pointer"
                >
                    <svg wire:loading wire:target="{{ $saveAction }}" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Add Journal</span>
                </button>
            </div>
        </div>
    </div>
</div>
