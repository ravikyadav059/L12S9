{{-- resources/views/components/admin/users/password-modal.blade.php --}}
<!-- CHANGE PASSWORD MODAL (ALPINE.JS - ZERO UNWANTED NETWORK REQUESTS) -->
<div 
    x-show="showPasswordModal" 
    x-cloak 
    @keydown.escape.window="showPasswordModal = false"
    class="fixed inset-0 z-50 overflow-y-auto" 
    aria-labelledby="password-modal-title" 
    role="dialog" 
    aria-modal="true"
    style="display: none;"
>
    <div class="fixed inset-0 bg-zinc-900/60 backdrop-blur-xs transition-opacity" @click="showPasswordModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div 
            class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-[#111c26] text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-zinc-200 dark:border-zinc-800 p-6 space-y-4"
            @click.away="showPasswordModal = false"
        >
            <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-brand/10 text-brand flex items-center justify-center">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white" id="password-modal-title">
                        Change Password
                    </h3>
                </div>
                <button type="button" @click="showPasswordModal = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1 rounded-lg cursor-pointer">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        New Password <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="password" 
                        wire:model="new_password" 
                        placeholder="Enter new password"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                    />
                    @error('new_password') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Confirm Password <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="password" 
                        wire:model="new_password_confirmation" 
                        placeholder="Confirm new password"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                    />
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-2.5">
                <button 
                    type="button" 
                    @click="showPasswordModal = false"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    wire:click="updatePassword"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand hover:bg-brand-600 active:scale-[0.98] transition shadow-xs cursor-pointer"
                >
                    Update Password
                </button>
            </div>
        </div>
    </div>
</div>
