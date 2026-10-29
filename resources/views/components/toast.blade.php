<!-- ================= GLOBAL TOAST NOTIFICATION COMPONENT ================= -->
<div 
    x-data="{ toasts: [] }" 
    @toast.window="
        let id = Date.now() + Math.random();
        toasts.push({ 
            id: id, 
            message: $event.detail.message || (typeof $event.detail === 'string' ? $event.detail : 'Notification'), 
            type: $event.detail.type || 'success',
            position: $event.detail.position || 'top-right'
        });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id); }, 4000);
    "
    @if(session('toast_message'))
        x-init="
            $nextTick(() => {
                let id = Date.now();
                toasts.push({
                    id: id,
                    message: @js(session('toast_message')),
                    type: @js(session('toast_type', 'success')),
                    position: @js(session('toast_position', 'top-right'))
                });
                setTimeout(() => { toasts = toasts.filter(t => t.id !== id); }, 4000);
            });
        "
    @endif
>
    <!-- 1. TOP-RIGHT CONTAINER (DEFAULT FOR QUESTIONS & APP ACTIONS - PREVIOUS DESIGN) -->
    <div 
        class="fixed top-20 right-5 z-[9999] flex flex-col gap-2.5 max-w-sm pointer-events-none"
        aria-live="polite"
    >
        <template x-for="t in toasts.filter(t => t.position !== 'bottom' && t.position !== 'bottom-center')" :key="t.id">
            <div 
                x-show="true"
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                :class="{
                    'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/90 dark:text-emerald-300 dark:border-emerald-800': t.type === 'success',
                    'bg-sky-50 text-brand border-sky-200 dark:bg-sky-950/90 dark:text-sky-300 dark:border-sky-800': t.type === 'info',
                    'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/90 dark:text-amber-300 dark:border-amber-800': t.type === 'warning',
                    'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/90 dark:text-rose-300 dark:border-rose-800': t.type === 'error'
                }"
                class="pointer-events-auto flex items-center gap-2.5 px-4 py-3 rounded-xl border shadow-xl text-sm font-medium backdrop-blur-md transition-all"
            >
                <!-- Success Icon -->
                <template x-if="t.type === 'success'">
                    <svg class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </template>

                <!-- Info Icon -->
                <template x-if="t.type === 'info'">
                    <svg class="w-5 h-5 shrink-0 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </template>

                <!-- Warning Icon -->
                <template x-if="t.type === 'warning'">
                    <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </template>

                <!-- Error Icon -->
                <template x-if="t.type === 'error'">
                    <svg class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </template>

                <span x-text="t.message" class="leading-snug"></span>
            </div>
        </template>
    </div>

    <!-- 2. BOTTOM-CENTER CONTAINER (DARK PILL DESIGN MATCHING SCREENSHOT) -->
    <div 
        class="fixed bottom-8 left-1/2 -translate-x-1/2 z-[99999] flex flex-col-reverse items-center gap-2.5 pointer-events-none"
        aria-live="polite"
    >
        <template x-for="t in toasts.filter(t => t.position === 'bottom' || t.position === 'bottom-center')" :key="t.id">
            <div 
                x-show="true"
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                class="pointer-events-auto inline-flex items-center gap-3 px-5 py-2.5 rounded-full bg-[#1e2530] text-white border border-slate-700/60 shadow-2xl shadow-black/40 text-sm font-semibold backdrop-blur-md transition-all select-none whitespace-nowrap"
            >
                <!-- Success Icon: Green square with white checkmark matching user screenshot -->
                <template x-if="t.type === 'success'">
                    <span class="size-5 rounded-[4px] bg-[#22c55e] text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="size-3.5 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </template>

                <!-- Info Icon -->
                <template x-if="t.type === 'info'">
                    <span class="size-5 rounded-[4px] bg-brand text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="size-3.5 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                </template>

                <!-- Warning Icon -->
                <template x-if="t.type === 'warning'">
                    <span class="size-5 rounded-[4px] bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="size-3.5 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </span>
                </template>

                <!-- Error Icon -->
                <template x-if="t.type === 'error'">
                    <span class="size-5 rounded-[4px] bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="size-3.5 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </span>
                </template>

                <span x-text="t.message" class="text-white text-[14px] font-semibold tracking-[-0.2px]"></span>
            </div>
        </template>
    </div>
</div>
