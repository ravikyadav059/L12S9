{{-- resources/views/components/alert-modal.blade.php --}}
<!-- ================= GLOBAL ALERT / WARNING MODAL COMPONENT ================= -->
<div 
    x-data="{ 
        open: false, 
        title: 'Notice', 
        message: '', 
        type: 'warning', 
        confirmText: 'OK', 
        cancelText: 'Cancel',
        showAlert(detail) {
            if (typeof detail === 'string') {
                this.message = detail;
                this.title = 'Notice';
                this.type = 'warning';
                this.confirmText = 'OK';
                this.cancelText = 'Cancel';
            } else if (detail) {
                this.title = detail.title || 'Notice';
                this.message = detail.message || '';
                this.type = detail.type || 'warning';
                this.confirmText = detail.confirmText || 'OK';
                this.cancelText = detail.cancelText || 'Cancel';
            }
            this.open = true;
        },
        closeAlert() {
            this.open = false;
        }
    }" 
    @alert-modal.window="showAlert($event.detail)"
    x-show="open" 
    x-cloak
    class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
    @keydown.escape.window="closeAlert()"
>
    <div 
        @click.outside="closeAlert()"
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="bg-white dark:bg-[#111c26] rounded-3xl p-6 sm:p-8 max-w-[440px] w-full text-center shadow-2xl border border-zinc-100 dark:border-zinc-800"
    >
        <!-- Icon -->
        <div 
            :class="{
                'border-amber-300 bg-amber-50 text-amber-500 dark:border-amber-700/60 dark:bg-amber-950/40': type === 'warning',
                'border-red-300 bg-red-50 text-red-500 dark:border-red-700/60 dark:bg-red-950/40': type === 'danger' || type === 'error',
                'border-sky-300 bg-sky-50 text-brand dark:border-sky-700/60 dark:bg-sky-950/40': type === 'info'
            }"
            class="size-16 rounded-full border-2 flex items-center justify-center text-2xl mx-auto mb-4 shrink-0"
        >
            <template x-if="type === 'warning'">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </template>
            <template x-if="type === 'danger' || type === 'error'">
                <i class="fa-solid fa-circle-exclamation"></i>
            </template>
            <template x-if="type === 'info'">
                <i class="fa-solid fa-circle-info"></i>
            </template>
        </div>

        <!-- Title -->
        <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2" x-text="title"></h3>

        <!-- Message -->
        <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed mb-6 font-normal whitespace-pre-line" x-text="message"></p>

        <!-- Buttons: Cancel & OK -->
        <div class="flex items-center justify-center gap-3">
            <button 
                type="button" 
                @click="closeAlert()" 
                class="px-6 py-2.5 rounded-full bg-[#505c6e] hover:bg-[#3d4756] text-white text-xs font-bold shadow-md transition-all cursor-pointer active:scale-95"
                x-text="cancelText"
            >
                Cancel
            </button>

            <button 
                type="button" 
                @click="closeAlert()" 
                class="px-6 py-2.5 rounded-full bg-brand hover:bg-brand-600 text-white text-xs font-bold shadow-md transition-all cursor-pointer active:scale-95"
                x-text="confirmText"
            >
                OK
            </button>
        </div>
    </div>
</div>
