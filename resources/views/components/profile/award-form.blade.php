{{-- resources/views/components/profile/award-form.blade.php --}}
@props([
    'showStatus' => false,
    'itemId' => null,
])

<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">

        @if($showStatus)
            <template x-if="form.id">
                <div class="flex items-center gap-2 pb-1 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-500">Award Record ID:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Click to select">
                        <span class="text-zinc-400 select-none">ID:</span>
                        <span x-text="form.id"></span>
                    </span>
                </div>
            </template>
        @endif

        {{-- Row 1: Award Name * & Awarded By * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 1. Award Name * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Award Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.award_name" 
                    @input="errors.award_name = false"
                    placeholder="e.g. Best Researcher Award, Outstanding Contribution to Science"
                    :class="errors.award_name ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.award_name" x-cloak class="text-[11px] text-red-500 mt-1">Please enter award name (minimum 2 characters)</p>
            </div>

            {{-- 2. Awarded By * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Awarded By <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.award_by" 
                    @input="errors.award_by = false"
                    placeholder="e.g. IEEE, Stanford University, National Science Foundation"
                    :class="errors.award_by ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.award_by" x-cloak class="text-[11px] text-red-500 mt-1">Please enter the awarding organization</p>
            </div>
        </div>

        {{-- Row 2: Year * & Status (Admin Only) --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 3. Year * --}}
            <div class="relative" @click.outside="showYearDropdown = false">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Year <span class="text-red-500">*</span>
                </label>
                <div 
                    @click="showYearDropdown = !showYearDropdown"
                    :class="errors.award_year ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border flex items-center justify-between text-zinc-900 dark:text-white cursor-pointer transition-all select-none"
                >
                    <span x-text="form.award_year || 'Select Year'" :class="!form.award_year ? 'text-zinc-400' : 'font-medium'"></span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-zinc-400 transition-transform duration-200" :class="showYearDropdown ? 'rotate-180' : ''"></i>
                </div>

                {{-- Year Dropdown Panel --}}
                <div 
                    x-show="showYearDropdown" 
                    x-cloak
                    class="absolute z-50 left-0 right-0 mt-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl overflow-hidden max-h-60 flex flex-col"
                >
                    <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/80">
                        <input 
                            type="text" 
                            x-model="yearSearchQuery" 
                            placeholder="Search year..." 
                            @click.stop
                            class="w-full h-8 px-2.5 text-xs rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-brand"
                        />
                    </div>
                    <div class="overflow-y-auto max-h-48 py-1 divide-y divide-zinc-50 dark:divide-zinc-800/40">
                        <template x-for="yr in filteredYears()" :key="yr">
                            <button 
                                type="button" 
                                @click="selectYear(yr)" 
                                class="w-full px-3.5 py-2 text-left text-xs hover:bg-brand/10 hover:text-brand dark:hover:text-brand transition cursor-pointer flex items-center justify-between"
                                :class="form.award_year === yr ? 'bg-brand/10 text-brand font-bold' : 'text-zinc-700 dark:text-zinc-300'"
                            >
                                <span x-text="yr"></span>
                                <i x-show="form.award_year === yr" class="fa-solid fa-check text-xs text-brand"></i>
                            </button>
                        </template>
                    </div>
                </div>
                <p x-show="errors.award_year" x-cloak class="text-[11px] text-red-500 mt-1">Please select the award year</p>
            </div>

            @if($showStatus)
                {{-- 4. Status * (Admin Only) --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select 
                        x-model.number="form.status" 
                        class="w-full h-10 px-3.5 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all cursor-pointer"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <p class="text-[11px] text-zinc-400 mt-1">Set visibility on user's public profile</p>
                </div>
            @endif
        </div>

        {{-- Row 3: Description --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Description
                </label>
                <span class="text-[11px] text-zinc-400" x-text="((form.description || '').length) + ' / 1,500'"></span>
            </div>
            <textarea 
                x-model="form.description" 
                rows="4" 
                maxlength="1500"
                placeholder="Describe the significance, criteria, or background of the award..."
                class="w-full p-3 text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all leading-relaxed resize-y"
            ></textarea>
            <p x-show="errors.description" x-cloak class="text-[11px] text-red-500 mt-1">Description cannot exceed 1,500 characters</p>
        </div>

        {{-- Row 4: Form Actions & Status (Admin Only) --}}
        <div wire:ignore.self class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2.5 flex-wrap">
                <button 
                    type="button" 
                    @click="saveItem()"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-60"
                >
                    <i x-show="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <span x-text="form.id ? 'Update Award' : 'Save Award'"></span>
                </button>

                <button 
                    type="button" 
                    @click="closeForm()"
                    class="px-4 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-xl hover:bg-zinc-50 dark:hover:bg-zinc-700/60 transition cursor-pointer shadow-2xs"
                >
                    Cancel
                </button>

                @if($showStatus)
                    <div class="flex items-center gap-3 sm:ml-2 sm:pl-3 sm:border-l sm:border-zinc-200 dark:sm:border-zinc-700">
                        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">Status:</span>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 1">
                            <input 
                                type="radio" 
                                name="form_status_award_{{ $itemId ?? 'new' }}" 
                                value="1" 
                                :checked="form.status == 1 || form.status === '1'"
                                @change="form.status = 1"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        </label>

                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 0">
                            <input 
                                type="radio" 
                                name="form_status_award_{{ $itemId ?? 'new' }}" 
                                value="0" 
                                :checked="form.status == 0 || form.status === '0'"
                                @change="form.status = 0"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                                <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                Inactive
                            </span>
                        </label>
                    </div>
                @endif
            </div>

            <template x-if="form.id">
                <button 
                    type="button" 
                    @click="deleteItem ? deleteItem(form.id) : confirmDelete(form.id)" 
                    class="px-3.5 py-2 rounded-xl bg-[#ef4444] hover:bg-red-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs transition-all active:scale-95 cursor-pointer"
                >
                    <i class="fa-regular fa-trash-can text-sm"></i>
                    <span>Delete</span>
                </button>
            </template>
        </div>

    </div>
</div>

