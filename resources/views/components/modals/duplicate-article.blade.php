@props([
    'targetInput' => '#field-title input',
])

<!-- ================= REUSABLE DUPLICATE ARTICLE POPUP MODAL ================= -->
<div 
    x-data="{ show: @entangle('showDuplicateModal') }"
    x-show="show" 
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto bg-black/60 backdrop-blur-xs"
    @keydown.escape.window="show = false; $wire.closeDuplicateModal();"
>
    <div 
        @click.outside="show = false; $wire.closeDuplicateModal();"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative w-full max-w-sm bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 sm:p-7 shadow-2xl text-center space-y-4"
    >
        <!-- Red Circle X Icon -->
        <div class="flex justify-center">
            <div class="w-16 h-16 rounded-full bg-[#E53E3E] text-white flex items-center justify-center shadow-md shadow-red-500/25">
                <svg class="w-8 h-8 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
        </div>

        <!-- Modal Body Text -->
        <div class="space-y-3">
            <p class="text-sm sm:text-base font-bold text-zinc-900 dark:text-white leading-snug">
                {{ $duplicateModalMessage ?? 'This Article has already been deposited.' }}
                Kindly check it at this link 
                <a href="{{ route('articles.index') }}" class="font-bold text-[#198BEA] hover:underline underline-offset-2">
                    'Article List.'
                </a>
            </p>

            <div class="space-y-0.5">
                <p class="text-xs sm:text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    In case of any questions, please reach out to us at:
                </p>
                <a href="mailto:hello@scholar9.com" class="inline-block text-xs sm:text-sm font-bold text-zinc-900 dark:text-white hover:text-[#198BEA] transition">
                    hello@scholar9.com
                </a>
            </div>
        </div>

        <!-- Action Button (Red OK) -->
        <div class="pt-1 flex justify-center">
            <button 
                type="button" 
                @click="show = false; $wire.closeDuplicateModal(); setTimeout(() => { const el = document.querySelector('{{ $targetInput }}'); if (el) el.focus(); }, 150);" 
                class="px-9 py-2 bg-[#E53E3E] hover:bg-[#c53030] text-white font-bold text-xs sm:text-sm rounded-full shadow-sm shadow-red-500/25 transition cursor-pointer"
            >
                OK
            </button>
        </div>
    </div>
</div>
