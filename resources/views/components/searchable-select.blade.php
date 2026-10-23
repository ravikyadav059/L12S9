@props([
    'options' => [],
    'placeholder' => 'Select an option',
    'searchPlaceholder' => 'Search...',
    'disabled' => false,
    'disabledPlaceholder' => 'Select previous option first',
    'noResultsText' => 'No result Found',
    'clearable' => true,
    'hasError' => false,
    'searchMethod' => null,
])

@php
    $wireModel = $attributes->wire('model');
    $wireProp = $wireModel->value();
    $isLive = $wireModel->hasModifier('live');
@endphp

<div 
    x-data="{
        open: false,
        search: '',
        value: @if($wireProp) @entangle($wireProp){{ $isLive ? '.live' : '' }} @else null @endif,
        options: {{ \Illuminate\Support\Js::from($options) }},
        disabled: {{ $disabled ? 'true' : 'false' }},
        placeholder: '{{ addslashes($placeholder) }}',
        disabledPlaceholder: '{{ addslashes($disabledPlaceholder) }}',
        dropUp: false,
        searchMethod: '{{ $searchMethod }}',
        loading: false,
        searchTimer: null,
        
        init() {
            if (this.searchMethod) {
                this.$watch('search', (query) => {
                    clearTimeout(this.searchTimer);
                    this.loading = true;
                    this.searchTimer = setTimeout(async () => {
                        try {
                            const lwEl = this.$el.closest('[wire\\:id]');
                            if (lwEl && window.Livewire) {
                                const component = window.Livewire.find(lwEl.getAttribute('wire:id'));
                                if (component && typeof component[this.searchMethod] === 'function') {
                                    const res = await component[this.searchMethod](query);
                                    if (Array.isArray(res)) {
                                        this.options = res;
                                    }
                                }
                            }
                        } catch (e) {
                            console.error('Async search error:', e);
                        } finally {
                            this.loading = false;
                        }
                    }, 250);
                });
            }
        },

        get selectedItem() {
            if (!this.value) return null;
            return this.options.find(o => String(o.id) === String(this.value)) || null;
        },

        get displayLabel() {
            if (this.disabled) {
                return this.disabledPlaceholder;
            }
            if (this.selectedItem) {
                return this.selectedItem.name;
            }
            return this.placeholder;
        },

        get filtered() {
            if (this.searchMethod) {
                return this.options;
            }
            if (!this.search || !this.search.trim()) {
                return this.options;
            }
            const q = this.search.toLowerCase().trim();
            return this.options.filter(o => {
                const nameMatch = o.name && o.name.toLowerCase().includes(q);
                const emailMatch = o.email && o.email.toLowerCase().includes(q);
                const subtextMatch = o.subtext && o.subtext.toLowerCase().includes(q);
                return nameMatch || emailMatch || subtextMatch;
            });
        },

        toggle() {
            if (this.disabled) return;
            this.open = !this.open;
            if (this.open) {
                this.search = '';
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                    if (this.$el) {
                        const rect = this.$el.getBoundingClientRect();
                        const spaceBelow = window.innerHeight - rect.bottom;
                        this.dropUp = spaceBelow < 280 && rect.top > 280;
                    }
                });
            }
        },

        select(item) {
            this.value = item ? item.id : '';
            this.open = false;
            this.search = '';
        },

        clear() {
            if (this.disabled) return;
            this.value = '';
            this.search = '';
            this.open = false;
        }
    }"
    x-effect="options = {{ \Illuminate\Support\Js::from($options) }}; disabled = {{ $disabled ? 'true' : 'false' }};"
    @click.outside="open = false; search = ''"
    @keydown.escape.window="open = false; search = ''"
    class="relative w-full"
    :class="open ? 'z-50' : 'z-10'"
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <!-- TRIGGER BOX (Clean, valid div role=button to prevent nested button issues) -->
    <div 
        role="button"
        tabindex="0"
        @click="toggle()"
        @keydown.enter.prevent="toggle()"
        @keydown.space.prevent="toggle()"
        class="w-full px-3.5 py-2.5 bg-zinc-50 dark:bg-zinc-900 border rounded-xl text-sm flex items-center justify-between text-left transition select-none focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 {{ $hasError ? 'border-rose-400 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700' }}"
        :class="{
            'opacity-50 cursor-not-allowed bg-zinc-100 dark:bg-zinc-800/50': disabled,
            'cursor-pointer hover:border-zinc-300 dark:hover:border-zinc-600': !disabled,
            'ring-2 ring-brand/20 border-brand': open
        }"
    >
        <div class="flex items-center gap-2 truncate min-w-0 pr-2">
            <span 
                class="truncate text-sm"
                :class="{
                    'text-zinc-400 dark:text-zinc-500 font-normal': !selectedItem && !disabled,
                    'text-zinc-900 dark:text-white font-medium': selectedItem && !disabled,
                    'text-zinc-400 dark:text-zinc-500 italic': disabled
                }"
                x-text="displayLabel"
            ></span>
            <template x-if="selectedItem && selectedItem.email">
                <span class="text-xs text-zinc-400 dark:text-zinc-500 font-normal truncate" x-text="'(' + selectedItem.email + ')'"></span>
            </template>
        </div>

        <div class="flex items-center gap-1.5 shrink-0 text-zinc-400">
            @if($clearable)
                <span 
                    role="button"
                    tabindex="0"
                    x-show="!disabled && value" 
                    @click.stop="clear()" 
                    @keydown.enter.stop="clear()"
                    class="size-5 rounded-md hover:bg-zinc-200 dark:hover:bg-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition cursor-pointer"
                    title="Clear selection"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </span>
            @endif
            <svg class="size-4 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180 text-brand' : 'text-zinc-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
    </div>

    <!-- DROPDOWN PANEL -->
    <div 
        x-show="open" 
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
        class="absolute left-0 right-0 z-50 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl overflow-hidden"
        :class="dropUp ? 'bottom-full mb-1.5 origin-bottom' : 'top-full mt-1.5 origin-top'"
        style="display: none;"
    >
        <!-- Search Input Header -->
        <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/80">
            <div class="relative">
                <template x-if="!loading">
                    <svg class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </template>
                <template x-if="loading">
                    <svg class="size-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-brand animate-spin pointer-events-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </template>
                <input 
                    type="text" 
                    x-ref="searchInput"
                    x-model="search" 
                    placeholder="{{ $searchPlaceholder }}"
                    @keydown.enter.prevent="if (filtered.length > 0) { select(filtered[0]); }"
                    class="w-full pl-8 pr-7 py-2 bg-white dark:bg-zinc-800/90 border border-zinc-200 dark:border-zinc-700 rounded-lg text-xs text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition"
                />
                <button 
                    type="button" 
                    x-show="search.length > 0" 
                    @click="search = ''; $refs.searchInput.focus();" 
                    class="absolute right-2 top-1/2 -translate-y-1/2 size-4 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 flex items-center justify-center cursor-pointer"
                >
                    <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Options List -->
        <div class="max-h-60 overflow-y-auto divide-y divide-zinc-100/80 dark:divide-zinc-800/60 p-1">
            <!-- Clear option at top if an option is selected and not searching -->
            <template x-if="!search && value">
                <button 
                    type="button"
                    @click="clear()"
                    class="w-full text-left px-3 py-2 text-xs font-medium text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition flex items-center gap-2 cursor-pointer"
                >
                    <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span>Clear Selection</span>
                </button>
            </template>

            <!-- Options Loop -->
            <template x-for="item in filtered" :key="item.id">
                <button 
                    type="button"
                    @click="select(item)"
                    class="w-full text-left px-3.5 py-2.5 rounded-lg transition-colors flex items-center justify-between gap-2 group cursor-pointer"
                    :class="String(item.id) === String(value) ? 'bg-brand/10 text-brand font-semibold' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800/80 text-zinc-800 dark:text-zinc-200'"
                >
                    <div class="flex-1 min-w-0">
                        <div class="text-xs truncate font-medium text-zinc-900 dark:text-zinc-100" :class="String(item.id) === String(value) ? 'text-brand dark:text-brand font-semibold' : ''" x-text="item.name"></div>
                        <template x-if="item.email">
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate mt-0.5" x-text="item.email"></div>
                        </template>
                        <template x-if="item.subtext">
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate mt-0.5" x-text="item.subtext"></div>
                        </template>
                    </div>
                    <template x-if="String(item.id) === String(value)">
                        <svg class="size-4 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                </button>
            </template>

            <!-- NO RESULTS FOUND -->
            <div x-show="filtered.length === 0" class="py-7 px-4 text-center">
                <svg class="size-6 mx-auto text-zinc-400/80 dark:text-zinc-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <p class="text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ $noResultsText }}</p>
                <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-0.5">Please check spelling or try another keyword</p>
            </div>
        </div>
    </div>
</div>
