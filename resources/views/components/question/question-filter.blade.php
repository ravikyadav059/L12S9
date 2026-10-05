@props([
    'show' => 'showFilterModal',
    'filterSaved' => 'filterSaved',
    'filterFollowing' => 'filterFollowing',
    'tagFilterType' => 'tagFilterType',
    'skillSearch' => 'filterSkillSearch',
    'closeAction' => '$set(\'showFilterModal\', false)',
    'resetAction' => 'resetFilters',
    'applyAction' => 'applyFilters',
])

<!-- ================= QUESTION FILTER MODAL (ALPINE.JS DRIVEN) ================= -->
<div 
    x-data="{
        show: @entangle($show)
    }"
    x-show="show"
    x-cloak
    @open-filter-modal.window="show = true"
    @close-filter-modal.window="show = false"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="show = false"
>
    <!-- Backdrop with blur -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs"
        @click="show = false"
    ></div>

    <!-- Modal Box Container -->
    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
            class="inline-block w-full max-w-[440px] text-left align-middle bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 overflow-hidden"
            @click.stop
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800/80">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        Filter by
                    </h3>
                </div>
                <button 
                    type="button" 
                    @click="show = false" 
                    class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body Content -->
            <div class="px-6 py-5 space-y-6">
                
                <!-- Section 1: Filter by -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-zinc-800 dark:text-zinc-200">
                        Filter by
                    </h4>
                    <div class="space-y-2.5">
                        <!-- Saved Questions -->
                        <label class="flex items-center gap-3 cursor-pointer group select-none">
                            <input 
                                type="checkbox" 
                                wire:model.live="{{ $filterSaved }}"
                                class="w-4 h-4 rounded-md border-zinc-300 dark:border-zinc-700 text-[#198BEA] focus:ring-[#198BEA]/20 cursor-pointer"
                            />
                            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">
                                <svg class="w-4 h-4 text-zinc-400 group-hover:text-[#198BEA] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                </svg>
                                <span>Saved Questions</span>
                            </div>
                        </label>

                        <!-- Following Questions -->
                        <label class="flex items-center gap-3 cursor-pointer group select-none">
                            <input 
                                type="checkbox" 
                                wire:model.live="{{ $filterFollowing }}"
                                class="w-4 h-4 rounded-md border-zinc-300 dark:border-zinc-700 text-[#198BEA] focus:ring-[#198BEA]/20 cursor-pointer"
                            />
                            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">
                                <svg class="w-4 h-4 text-zinc-400 group-hover:text-[#198BEA] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span>Following Questions</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Section 2: Tagged with -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-zinc-800 dark:text-zinc-200">
                        Tagged with
                    </h4>
                    <div class="space-y-2.5">
                        <!-- My Matching Skills -->
                        <label class="flex items-center gap-3 cursor-pointer group select-none">
                            <input 
                                type="radio" 
                                name="tagOption" 
                                value="matching" 
                                wire:model.live="{{ $tagFilterType }}"
                                class="w-4 h-4 border-zinc-300 dark:border-zinc-700 text-[#198BEA] focus:ring-[#198BEA]/20 cursor-pointer"
                            />
                            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">
                                <svg class="w-4 h-4 text-zinc-400 group-hover:text-[#198BEA] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <span>My Matching Skills</span>
                            </div>
                        </label>

                        <!-- The Following Skills -->
                        <label class="flex items-center gap-3 cursor-pointer group select-none">
                            <input 
                                type="radio" 
                                name="tagOption" 
                                value="following" 
                                wire:model.live="{{ $tagFilterType }}"
                                class="w-4 h-4 border-zinc-300 dark:border-zinc-700 text-[#198BEA] focus:ring-[#198BEA]/20 cursor-pointer"
                            />
                            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">
                                <svg class="w-4 h-4 text-zinc-400 group-hover:text-[#198BEA] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                <span>The Following Skills</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Section 3: Search Skills -->
                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-zinc-800 dark:text-zinc-200">
                        Search Skills
                    </h4>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="{{ $skillSearch }}"
                            placeholder="e.g., React, Python, Docker..." 
                            class="w-full pl-10 pr-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all"
                        />
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between px-6 py-4 border-t border-zinc-100 dark:border-zinc-800/80 bg-zinc-50/50 dark:bg-zinc-900/50">
                <!-- Reset Button -->
                <button 
                    type="button" 
                    wire:click="{{ $resetAction }}"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition-colors cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </button>

                <!-- Filter Action Button -->
                <button 
                    type="button" 
                    wire:click="{{ $applyAction }}"
                    class="inline-flex items-center gap-1.5 px-6 py-2 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs font-bold rounded-full shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Filter</span>
                </button>
            </div>

        </div>
    </div>
</div>
