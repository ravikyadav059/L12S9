@props([
    'options' => [],
    'placeholder' => 'Type or select...',
    'allowCustom' => true,
    'hasError' => false,
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
        selected: @if($wireProp) @entangle($wireProp){{ $isLive ? '.live' : '' }} @else [] @endif,
        defaultOptions: {{ \Illuminate\Support\Js::from($options) }},
        highlightIndex: 0,

        init() {
            if (!Array.isArray(this.selected)) {
                this.selected = this.selected ? [this.selected] : [];
            }
        },

        get availableOptions() {
            const list = Array.isArray(this.defaultOptions) ? [...this.defaultOptions] : [];
            if (Array.isArray(this.selected)) {
                this.selected.forEach(s => {
                    if (s && !list.includes(s)) {
                        list.push(s);
                    }
                });
            }
            return list;
        },

        get filtered() {
            const q = (this.search || '').toLowerCase().trim();
            if (!q) {
                return this.availableOptions;
            }
            return this.availableOptions.filter(o => o && o.toLowerCase().includes(q));
        },

        get canAddCustom() {
            if (!{{ $allowCustom ? 'true' : 'false' }}) return false;
            const q = (this.search || '').trim();
            if (!q) return false;
            return !this.availableOptions.some(o => o.toLowerCase() === q.toLowerCase());
        },

        isSelected(val) {
            return Array.isArray(this.selected) && this.selected.includes(val);
        },

        toggle(val) {
            if (!Array.isArray(this.selected)) {
                this.selected = [];
            }
            const idx = this.selected.indexOf(val);
            if (idx > -1) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push(val);
            }
            this.search = '';
            this.$nextTick(() => {
                if (this.$refs.tagInput) {
                    this.$refs.tagInput.focus();
                }
            });
        },

        addCustom() {
            const q = (this.search || '').trim();
            if (!q) return;
            if (!Array.isArray(this.selected)) {
                this.selected = [];
            }
            if (!this.selected.includes(q)) {
                this.selected.push(q);
                if (!this.defaultOptions.includes(q)) {
                    this.defaultOptions.push(q);
                }
            }
            this.search = '';
            this.open = true;
            this.$nextTick(() => {
                if (this.$refs.tagInput) {
                    this.$refs.tagInput.focus();
                }
            });
        },

        removeTag(val) {
            if (Array.isArray(this.selected)) {
                this.selected = this.selected.filter(item => item !== val);
            }
        },

        handleBackspace() {
            if (this.search === '' && Array.isArray(this.selected) && this.selected.length > 0) {
                this.selected.pop();
            }
        },

        handleEnter() {
            if (this.canAddCustom && (this.highlightIndex === 0 || this.filtered.length === 0)) {
                this.addCustom();
            } else if (this.filtered.length > 0) {
                const target = this.filtered[this.highlightIndex] || this.filtered[0];
                if (target) {
                    this.toggle(target);
                }
            }
        }
    }"
    @click.outside="open = false"
    class="relative w-full"
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <!-- Multi-Select Container Box -->
    <div 
        @click="open = true; $refs.tagInput.focus()"
        class="min-h-[46px] w-full p-1.5 bg-zinc-50 dark:bg-zinc-900 border rounded-xl flex flex-wrap items-center gap-1.5 cursor-text transition focus-within:outline-none focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20 {{ $hasError ? 'border-rose-400 dark:border-rose-500' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600' }}"
    >
        <!-- Selected Badges / Chips -->
        <template x-for="tag in (Array.isArray(selected) ? selected : [])" :key="tag">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-brand/10 text-brand border border-brand/20 transition-all select-none">
                <span x-text="tag"></span>
                <button 
                    type="button" 
                    @click.stop="removeTag(tag)" 
                    class="size-3.5 rounded-full hover:bg-brand/20 flex items-center justify-center text-brand/80 hover:text-brand cursor-pointer transition"
                    title="Remove"
                >
                    <svg class="size-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </template>

        <!-- Inline Typing Input -->
        <div class="flex-1 min-w-[120px] flex items-center">
            <input 
                x-ref="tagInput"
                type="text" 
                x-model="search"
                @focus="open = true"
                @keydown.enter.prevent="handleEnter()"
                @keydown.backspace="handleBackspace()"
                @keydown.escape.prevent="open = false"
                @keydown.arrow-down.prevent="highlightIndex = Math.min(highlightIndex + 1, filtered.length - 1)"
                @keydown.arrow-up.prevent="highlightIndex = Math.max(highlightIndex - 1, 0)"
                :placeholder="(!selected || selected.length === 0) ? '{{ addslashes($placeholder) }}' : 'Add more...'"
                class="w-full py-1 px-2 text-sm bg-transparent border-0 focus:outline-none focus:ring-0 text-zinc-900 dark:text-white placeholder-zinc-400 dark:placeholder-zinc-500"
            />
        </div>

        <!-- Chevron / Action Indicator -->
        <div class="shrink-0 pr-1 flex items-center gap-1 text-zinc-400 pointer-events-none">
            <svg class="size-4 transition-transform duration-150" :class="open ? 'rotate-180 text-brand' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
    </div>

    <!-- Dropdown Panel -->
    <div 
        x-show="open" 
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
        class="absolute left-0 right-0 z-50 mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl overflow-hidden text-xs"
        style="display: none;"
    >
        <!-- Add Custom Option Banner -->
        <template x-if="canAddCustom">
            <div 
                @click="addCustom()"
                class="p-2.5 bg-brand/5 dark:bg-brand/10 border-b border-brand/20 text-brand font-medium flex items-center justify-between cursor-pointer hover:bg-brand/10 dark:hover:bg-brand/20 transition"
            >
                <div class="flex items-center gap-2">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add custom: <strong x-text="search.trim()"></strong></span>
                </div>
                <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-brand/15 text-brand">Press Enter</span>
            </div>
        </template>

        <!-- Options List -->
        <div class="max-h-56 overflow-y-auto divide-y divide-zinc-100/70 dark:divide-zinc-800/60 p-1">
            <template x-for="(opt, idx) in filtered" :key="opt">
                <button 
                    type="button" 
                    @click="toggle(opt)"
                    class="w-full text-left px-3 py-2 rounded-lg transition flex items-center justify-between gap-2 cursor-pointer"
                    :class="{
                        'bg-brand/10 text-brand font-semibold': isSelected(opt),
                        'hover:bg-zinc-100 dark:hover:bg-zinc-800/70 text-zinc-800 dark:text-zinc-200': !isSelected(opt),
                        'ring-1 ring-brand/30': idx === highlightIndex
                    }"
                >
                    <span class="truncate font-medium text-xs" x-text="opt"></span>
                    <template x-if="isSelected(opt)">
                        <span class="flex items-center gap-1 text-[11px] text-brand shrink-0">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span>Selected</span>
                        </span>
                    </template>
                </button>
            </template>

            <!-- No options match & cannot add custom -->
            <div x-show="filtered.length === 0 && !canAddCustom" class="py-6 px-4 text-center text-zinc-400 dark:text-zinc-500">
                <p class="text-xs font-semibold">No options found</p>
                <p class="text-[11px] mt-0.5">Type to add a new custom entry</p>
            </div>
        </div>

        <!-- Footer helper -->
        <div class="px-3 py-1.5 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/60 flex items-center justify-between text-[11px] text-zinc-400">
            <span>Click to select multiple</span>
            <span x-text="(Array.isArray(selected) ? selected.length : 0) + ' selected'"></span>
        </div>
    </div>
</div>
