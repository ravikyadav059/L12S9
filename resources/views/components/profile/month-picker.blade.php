@props([
    'label' => '',
    'required' => false,
    'model' => '',
    'disabledExpr' => 'false',
    'hasErrorExpr' => 'false',
    'placeholder' => 'Select Month, Year',
    'minDateExpr' => 'null',
])

<div 
    class="space-y-1.5"
    x-data="{
        open: false,
        pickerYear: new Date().getFullYear(),
        selectedMonth: '',
        months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        fullMonths: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],

        initPicker() {
            const val = (typeof {{ $model }} !== 'undefined') ? ({{ $model }} || '') : '';
            if (val) {
                const parts = val.replace(',', '').trim().split(/\s+/);
                if (parts.length >= 2) {
                    const mIndex = this.fullMonths.findIndex(m => m.toLowerCase().startsWith(parts[0].toLowerCase()));
                    if (mIndex !== -1) {
                        this.selectedMonth = this.months[mIndex];
                    }
                    const y = parseInt(parts[1], 10);
                    if (!isNaN(y)) {
                        this.pickerYear = y;
                    }
                }
            }
        },

        selectMonth(m, index) {
            this.selectedMonth = m;
            const fullMonth = this.fullMonths[index];
            {{ $model }} = `${fullMonth}, ${this.pickerYear}`;
            this.open = false;
        },

        prevYear() {
            this.pickerYear--;
        },

        nextYear() {
            this.pickerYear++;
        },

        clear() {
            this.selectedMonth = '';
            {{ $model }} = '';
            this.open = false;
        },

        selectThisMonth() {
            const now = new Date();
            this.pickerYear = now.getFullYear();
            const mIndex = now.getMonth();
            this.selectedMonth = this.months[mIndex];
            {{ $model }} = `${this.fullMonths[mIndex]}, ${this.pickerYear}`;
            this.open = false;
        },

        isSelected(m) {
            const val = (typeof {{ $model }} !== 'undefined') ? ({{ $model }} || '') : '';
            if (!val) return false;
            return this.selectedMonth === m && val.includes(String(this.pickerYear));
        }
    }"
    x-init="initPicker(); $watch(() => (typeof {{ $model }} !== 'undefined' ? {{ $model }} : ''), () => initPicker())"
    @click.outside="open = false"
>
    @if($label)
        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
            {{ $label }}
            @if($required)
                <span class="text-rose-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        {{-- Display Input --}}
        <div 
            @click="if (!({{ $disabledExpr }})) open = !open"
            :class="{
                'opacity-50 cursor-not-allowed bg-zinc-100 dark:bg-zinc-800/60': ({{ $disabledExpr }}),
                'cursor-pointer bg-white dark:bg-zinc-900': !({{ $disabledExpr }}),
                'border-rose-500 ring-1 ring-rose-500': ({{ $hasErrorExpr }}),
                'border-zinc-300 dark:border-zinc-700 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20': !({{ $hasErrorExpr }})
            }"
            class="w-full h-10 px-3.5 rounded-xl border flex items-center justify-between transition text-xs select-none"
        >
            <span 
                x-text="{{ $model }} || '{{ $placeholder }}'"
                :class="{{ $model }} ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400 dark:text-zinc-500'"
            ></span>

            <i class="fa-regular fa-calendar text-zinc-400 text-sm"></i>
        </div>

        {{-- Month-Year Popover (Exact match with Image media_1793188779760.png) --}}
        <div 
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-95"
            class="absolute left-0 mt-1.5 w-64 p-3 bg-white dark:bg-[#182230] rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-700/80 z-50 text-xs"
            x-cloak
        >
            {{-- Year Selector Header --}}
            <div class="flex items-center justify-between bg-zinc-100 dark:bg-zinc-800/80 rounded-lg px-2.5 py-1.5 mb-3">
                <button 
                    type="button" 
                    @click.stop="prevYear()"
                    class="p-1 text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white rounded hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
                >
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>

                <div class="flex items-center gap-1 font-bold text-zinc-800 dark:text-zinc-100 text-sm">
                    <span x-text="pickerYear"></span>
                </div>

                <button 
                    type="button" 
                    @click.stop="nextYear()"
                    class="p-1 text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white rounded hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
                >
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </div>

            {{-- 3x4 Month Grid --}}
            <div class="grid grid-cols-4 gap-1.5 mb-3 text-center">
                <template x-for="(m, idx) in months" :key="m">
                    <button 
                        type="button" 
                        @click.stop="selectMonth(m, idx)"
                        :class="isSelected(m) 
                            ? 'bg-zinc-800 text-white dark:bg-white dark:text-zinc-900 font-bold shadow-xs' 
                            : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800/80'"
                        class="py-2 rounded-lg text-xs font-medium transition cursor-pointer"
                        x-text="m"
                    ></button>
                </template>
            </div>

            {{-- Footer: Clear & This Month --}}
            <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800 text-xs font-semibold">
                <button 
                    type="button" 
                    @click.stop="clear()"
                    class="text-brand hover:underline cursor-pointer"
                >
                    Clear
                </button>

                <button 
                    type="button" 
                    @click.stop="selectThisMonth()"
                    class="text-brand hover:underline cursor-pointer"
                >
                    This month
                </button>
            </div>
        </div>
    </div>
</div>
