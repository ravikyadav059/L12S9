<!-- ================= UNIVERSAL AUTHENTICATION MODAL (ALPINE.JS & TAILWIND CSS) ================= -->
<!-- Location: resources/views/components/modals/auth.blade.php -->
<div 
    x-data="{
        isOpen: false,
        title: 'Connect to Scholar9 to Ask a Question',
        message: 'Please proceed to connect your Scholar identity. This helps us personalize your experience and ensures you get the best possible assistance.',
        icon: 'error',
        confirmText: 'Proceed',
        cancelText: 'Cancel',

        openModal(detail) {
            if (typeof detail === 'string') {
                const action = detail.trim();
                this.title = action.toLowerCase().startsWith('connect') ? action : `Connect to Scholar9 to ${action}`;
                this.message = 'Please proceed to connect your Scholar identity. This helps us personalize your experience and ensures you get the best possible assistance.';
                this.icon = 'error';
                this.confirmText = 'Proceed';
                this.cancelText = 'Cancel';
            } else if (detail && typeof detail === 'object') {
                this.title = detail.title || (detail.action ? `Connect to Scholar9 to ${detail.action}` : 'Connect to Scholar9');
                this.message = detail.message || detail.text || 'Please proceed to connect your Scholar identity. This helps us personalize your experience and ensures you get the best possible assistance.';
                this.icon = detail.icon || 'error';
                this.confirmText = detail.confirmText || 'Proceed';
                this.cancelText = detail.cancelText || 'Cancel';
            }
            this.isOpen = true;
        },

        proceed() {
            this.isOpen = false;
            window.location.href = '{{ route('login') }}';
        },

        cancel() {
            this.isOpen = false;
        }
    }"
    @open-auth-modal.window="openModal($event.detail)"
    @open-auth-alert.window="openModal($event.detail)"
    @close-auth-modal.window="isOpen = false"
    x-show="isOpen" 
    style="display: none;"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog" 
    aria-modal="true"
    @keydown.escape.window="isOpen = false"
>
    <!-- Backdrop with Blur -->
    <div 
        x-show="isOpen"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
        @click="isOpen = false"
    ></div>

    <!-- Modal Box Container -->
    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div 
            x-show="isOpen"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-center shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md p-7 sm:p-8"
            @click.stop
        >
            <!-- Close Button (Top Right) -->
            <button 
                type="button" 
                @click="isOpen = false" 
                class="absolute top-4 right-4 p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                title="Close"
                aria-label="Close"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"/>
                    <path d="m6 6 12 12"/>
                </svg>
            </button>

            <!-- Centered Status Icon -->
            <div class="flex items-center justify-center mb-4">
                <!-- Reject / Error Icon (Single Clean Border with Lucide X) -->
                <template x-if="icon === 'error'">
                    <div class="w-14 h-14 rounded-full border-2 border-rose-300 dark:border-rose-800/80 bg-rose-50/50 dark:bg-rose-950/30 flex items-center justify-center text-rose-500 dark:text-rose-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/>
                            <path d="m6 6 12 12"/>
                        </svg>
                    </div>
                </template>

                <!-- Warning Icon -->
                <template x-if="icon === 'warning'">
                    <div class="w-14 h-14 rounded-full border-2 border-amber-300 dark:border-amber-700 bg-amber-50/50 dark:bg-amber-950/30 flex items-center justify-center text-amber-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" x2="12" y1="8" y2="13"/>
                            <line x1="12" x2="12.01" y1="17" y2="17"/>
                        </svg>
                    </div>
                </template>

                <!-- Success Icon -->
                <template x-if="icon === 'success'">
                    <div class="w-14 h-14 rounded-full border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-950/30 flex items-center justify-center text-emerald-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12 5 5L20 7"/>
                        </svg>
                    </div>
                </template>

                <!-- Info Icon -->
                <template x-if="icon === 'info'">
                    <div class="w-14 h-14 rounded-full border-2 border-sky-300 dark:border-sky-700 bg-sky-50/50 dark:bg-sky-950/30 flex items-center justify-center text-[#198BEA] dark:text-sky-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 16v-4"/>
                            <path d="M12 8h.01"/>
                        </svg>
                    </div>
                </template>
            </div>

            <!-- Title -->
            <h3 class="text-lg sm:text-xl font-bold text-[#595959] dark:text-zinc-100 leading-snug mb-3" x-text="title"></h3>

            <!-- Description / Subtitle -->
            <p class="text-xs sm:text-sm text-[#353535] dark:text-[#e1e1e1] leading-relaxed max-w-sm mx-auto mb-6" x-text="message"></p>

            <!-- Action Buttons -->
            <div class="flex items-center justify-center gap-3">
                <button 
                    type="button" 
                    @click="proceed()" 
                    class="px-8 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-full shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-95"
                    x-text="confirmText"
                ></button>

                <button 
                    type="button" 
                    @click="isOpen = false" 
                    class="px-8 py-2.5 bg-[#52525b] hover:bg-zinc-700 active:bg-zinc-800 text-white text-sm font-semibold rounded-full shadow-xs transition-all cursor-pointer active:scale-95"
                    x-text="cancelText"
                ></button>
            </div>

        </div>
    </div>
</div>
