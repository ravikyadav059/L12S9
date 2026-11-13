@props([
    'show' => 'showAddJournalModal',
    'title' => 'newJournalTitle',
    'eIssn' => 'newJournalEIssn',
    'pIssn' => 'newJournalPIssn',
    'closeAction' => 'closeAddJournalModal',
    'saveAction' => 'saveNewJournal',
    'scope' => 'default',
])

<!-- ================= REUSABLE ADD NEW JOURNAL POPUP MODAL (100% JS Open/Close + Official Registry API Verification) ================= -->
<div 
    x-data="{
        show: false,
        clientErrors: {},

        init() {
            this.$watch('$wire.{{ $show }}', (val) => {
                if (val && !this.show) {
                    this.show = true;
                } else if (!val && this.show) {
                    this.show = false;
                }
            });
        },

        openModal(detail = {}) {
            const target = (detail && typeof detail.target === 'string') ? detail.target : 'default';
            if (target !== '{{ $scope }}') {
                return;
            }
            const prefillTitle = (detail && typeof detail.title === 'string')
                ? detail.title.trim()
                : ((detail && typeof detail.journalName === 'string') ? detail.journalName.trim() : '');
            this.clientErrors = {};
            this.$wire.set('{{ $title }}', prefillTitle, false);
            this.$wire.set('{{ $eIssn }}', '', false);
            this.$wire.set('{{ $pIssn }}', '', false);
            this.show = true;
            this.$nextTick(() => {
                if (this.$refs.journalTitleInput) {
                    this.$refs.journalTitleInput.focus();
                }
            });
        },

        closeModal() {
            this.show = false;
            this.clientErrors = {};
            this.$wire.set('{{ $title }}', '', false);
            this.$wire.set('{{ $eIssn }}', '', false);
            this.$wire.set('{{ $pIssn }}', '', false);
            if (this.$wire.{{ $show }}) {
                this.$wire.set('{{ $show }}', false, false);
            }
        },

        isValidIssn(val) {
            const clean = (val || '').trim().toUpperCase();
            if (!/^\d{4}-\d{3}[0-9X]$/.test(clean)) {
                return { valid: false, message: 'Must match valid ISSN format (e.g., 1234-5679 or 1757-790X).' };
            }
            const digits = clean.replace('-', '');
            let sum = 0;
            for (let i = 0; i < 7; i++) {
                sum += parseInt(digits[i], 10) * (8 - i);
            }
            const remainder = sum % 11;
            const check = 11 - remainder;
            const expected = check === 11 ? '0' : (check === 10 ? 'X' : String(check));
            if (digits[7] !== expected) {
                return { valid: false, message: 'Invalid ISSN checksum digit (expected ' + expected + ', got ' + digits[7] + ').' };
            }
            return { valid: true, message: '' };
        },

        submitModal() {
            this.clientErrors = {};
            const t = (this.$wire.get('{{ $title }}') || '').trim();
            const e = (this.$wire.get('{{ $eIssn }}') || '').trim().toUpperCase();
            const p = (this.$wire.get('{{ $pIssn }}') || '').trim().toUpperCase();

            let hasErr = false;
            if (t.length < 2) {
                this.clientErrors.title = 'Journal Name is required (at least 2 characters).';
                hasErr = true;
            }
            if (e === '' && p === '') {
                this.clientErrors.issnRequired = 'At least one ISSN (e-ISSN or p-ISSN) is required.';
                hasErr = true;
            } else {
                if (e !== '') {
                    const resE = this.isValidIssn(e);
                    if (!resE.valid) {
                        this.clientErrors.eIssn = resE.message;
                        hasErr = true;
                    }
                }
                if (p !== '') {
                    const resP = this.isValidIssn(p);
                    if (!resP.valid) {
                        this.clientErrors.pIssn = resP.message;
                        hasErr = true;
                    }
                }
                if (e !== '' && p !== '' && e === p) {
                    this.clientErrors.pIssn = 'P-ISSN must be different from E-ISSN.';
                    hasErr = true;
                }
            }

            if (hasErr) {
                return;
            }

            this.$wire.{{ $saveAction }}();
        }
    }"
    @open-add-journal-modal.window="openModal($event.detail)"
    @close-add-journal-modal.window="closeModal()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="if (show) closeModal()"
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
        @click="closeModal()"
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
                <div>
                    <h3 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
                        Add New Journal
                    </h3>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Journal name &amp; ISSN will be verified against official registries before adding.
                    </p>
                </div>
                <button 
                    type="button" 
                    @click="closeModal()"
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
                        x-ref="journalTitleInput"
                        wire:model="{{ $title }}" 
                        x-on:input="delete clientErrors.title"
                        placeholder="Enter exact official Journal Name" 
                    />
                    <p x-show="clientErrors.title" x-cloak x-text="clientErrors.title" class="text-xs text-red-500 font-medium"></p>
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
                        x-on:input="formatIssn($event, '{{ $eIssn }}'); delete clientErrors.eIssn; delete clientErrors.issnRequired;" 
                        maxlength="9" 
                        placeholder="e.g., 1234-5678" 
                    />
                    <p x-show="clientErrors.eIssn" x-cloak x-text="clientErrors.eIssn" class="text-xs text-red-500 font-medium"></p>
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
                        x-on:input="formatIssn($event, '{{ $pIssn }}'); delete clientErrors.pIssn; delete clientErrors.issnRequired;" 
                        maxlength="9" 
                        placeholder="e.g., 1234-5678" 
                    />
                    <p x-show="clientErrors.pIssn" x-cloak x-text="clientErrors.pIssn" class="text-xs text-red-500 font-medium"></p>
                    @error($pIssn) 
                        <p class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                    @enderror
                </div>

                <!-- Note: At least one ISSN required -->
                <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium pt-1">
                    <span class="text-red-500 font-bold">*</span> At least one ISSN (e-ISSN or p-ISSN) is required
                </div>

                <!-- Client Error Alert if neither ISSN entered -->
                <div x-show="clientErrors.issnRequired" x-cloak class="p-3 bg-zinc-900 text-zinc-100 rounded-xl flex items-center gap-2.5 text-xs font-semibold shadow-lg">
                    <svg class="w-4 h-4 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span x-text="clientErrors.issnRequired"></span>
                </div>

                <!-- Server Error Toast Alert if neither ISSN entered -->
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
                    @click="closeModal()" 
                    class="px-5 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 text-zinc-700 dark:text-zinc-200 text-sm font-bold transition cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    @click="submitModal()" 
                    wire:loading.attr="disabled"
                    wire:target="{{ $saveAction }}"
                    class="px-6 py-2.5 rounded-xl bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-bold shadow-md shadow-[#198BEA]/20 transition flex items-center gap-2 cursor-pointer disabled:opacity-60"
                >
                    <svg wire:loading wire:target="{{ $saveAction }}" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="{{ $saveAction }}">Add Journal</span>
                    <span wire:loading wire:target="{{ $saveAction }}">Verifying &amp; Adding...</span>
                </button>
            </div>
        </div>
    </div>
</div>
