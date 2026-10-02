@props([
    'show' => 'showAddAuthorModal',
    'firstName' => 'newAuthorFirstName',
    'lastName' => 'newAuthorLastName',
    'email' => 'newAuthorEmail',
    'closeAction' => 'closeAddAuthorModal',
    'saveAction' => 'saveNewAuthor',
    'errorMessage' => 'existingAuthorErrorMessage',
])

<!-- ================= REUSABLE ADD NEW AUTHOR POPUP MODAL ================= -->
<div 
    x-data="{
        show: @entangle($show)
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
            class="inline-block w-full max-w-lg p-6 my-8 text-left align-middle bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10"
            @click.stop
        >
            <!-- Header -->
            <div class="flex items-start justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 flex items-center justify-center text-[#198BEA] shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                            Add New Author
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Enter author name and email address to register them.
                        </p>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="{{ $closeAction }}"
                    class="p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
                    title="Close"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Existing Author Error Alert Banner -->
            @if(!empty($$errorMessage ?? null))
                <div class="mt-4 p-3.5 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                    </svg>
                    <div class="flex-1">
                        <h4 class="text-xs font-bold text-red-800 dark:text-red-300">Author Already Registered</h4>
                        <p class="text-xs text-red-700 dark:text-red-300/90 mt-0.5 leading-relaxed">
                            {{ $$errorMessage }}
                        </p>
                    </div>
                </div>
            @endif

            <!-- Form Inputs -->
            <div class="mt-4 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- First Name -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <flux:input 
                            wire:model="{{ $firstName }}" 
                            placeholder="e.g. Rahul" 
                            autofocus
                        />
                        @error($firstName) 
                            <p class="text-xs text-red-500 font-medium mt-1">{{ $message }}</p> 
                        @enderror
                    </div>

                    <!-- Last Name -->
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <flux:input 
                            wire:model="{{ $lastName }}" 
                            placeholder="e.g. Kumar" 
                        />
                        @error($lastName) 
                            <p class="text-xs text-red-500 font-medium mt-1">{{ $message }}</p> 
                        @enderror
                    </div>
                </div>

                <!-- Email Address -->
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">
                        Author Email <span class="text-red-500">*</span>
                    </label>
                    <flux:input 
                        type="email" 
                        wire:model="{{ $email }}" 
                        placeholder="e.g. rahul@gmail.com" 
                    />
                    @error($email) 
                        <p class="text-xs text-red-500 font-medium mt-1">{{ $message }}</p> 
                    @enderror
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                        We check this email to ensure it is unique and not already linked to another author profile.
                    </p>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-3">
                <flux:button 
                    variant="subtle" 
                    type="button" 
                    wire:click="{{ $closeAction }}"
                    class="px-4 text-xs font-semibold"
                >
                    Cancel
                </flux:button>

                <flux:button 
                    variant="primary" 
                    type="button" 
                    wire:click="{{ $saveAction }}"
                    class="px-5 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold"
                >
                    <span wire:loading.remove wire:target="{{ $saveAction }}">Add Author</span>
                    <span wire:loading wire:target="{{ $saveAction }}" class="flex items-center gap-1.5">
                        <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Saving...</span>
                    </span>
                </flux:button>
            </div>
        </div>
    </div>
</div>
