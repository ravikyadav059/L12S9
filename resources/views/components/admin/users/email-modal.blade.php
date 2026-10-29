@props([
    'user' => null,
])
<!-- CHANGE EMAIL CONFIRMATION MODAL (ALPINE.JS - ZERO UNWANTED NETWORK REQUESTS) -->
<div 
    x-show="showEmailModal" 
    x-cloak 
    @keydown.escape.window="showEmailModal = false"
    class="fixed inset-0 z-50 overflow-y-auto" 
    aria-labelledby="email-modal-title" 
    role="dialog" 
    aria-modal="true"
    style="display: none;"
>
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-xs transition-opacity" @click="showEmailModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div 
            class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-[#111c26] text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-zinc-200 dark:border-zinc-800 p-6 space-y-4"
            @click.away="showEmailModal = false"
        >
            <!-- Header -->
            <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white" id="email-modal-title">
                        Change User Email Address
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Please proceed with caution.
                    </p>
                </div>
                <button type="button" @click="showEmailModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1 rounded-lg cursor-pointer">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body Warning Note -->
            <div class="p-4 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 space-y-2">
                <p class="font-semibold">
                    Are you sure you want to unlock and change this user's email?
                </p>
                <p class="text-[11px] text-amber-800/90 dark:text-amber-300/80 leading-relaxed">
                    Changing the email address directly affects the user's login username. All account recovery, verification links, and notifications will be sent to the new email once saved.
                </p>
                <div class="pt-1 flex items-center gap-1.5 text-[11px] font-mono text-zinc-600 dark:text-zinc-300">
                    <span class="font-sans font-semibold text-zinc-500">Current:</span>
                    <span class="px-2 py-0.5 rounded bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-bold">
                        {{ $user->email }}
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-2.5">
                <button 
                    type="button" 
                    @click="showEmailModal = false"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    @click="confirmUnlockEmail()"
                    class="inline-flex items-center gap-1.5 px-4.5 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 active:scale-[0.98] transition shadow-xs cursor-pointer"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                    <span>Unlock Email Field</span>
                </button>
            </div>
        </div>
    </div>
</div>
