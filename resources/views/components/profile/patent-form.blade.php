{{-- resources/views/components/profile/patent-form.blade.php --}}
@props([
    'showStatus' => false,
    'itemId' => null,
    'countries' => [],
    'categories' => [],
])

<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">

        @if($showStatus)
            <template x-if="form.id">
                <div class="flex items-center gap-2 pb-1 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-xs font-semibold text-zinc-500">Patent Record ID:</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-mono text-[10.5px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700/80 select-all" title="Click to select">
                        <span class="text-zinc-400 select-none">ID:</span>
                        <span x-text="form.id"></span>
                    </span>
                </div>
            </template>
        @endif

        {{-- Row 1: Patent Title * & Patent Number * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 1. Patent Title * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Patent Title <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.title" 
                    @input="errors.title = false"
                    placeholder="e.g. Distributed Consensus Engine Using Quantum Entanglement"
                    :class="errors.title ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.title" x-cloak class="text-[11px] text-red-500 mt-1">Please enter patent title (minimum 2 characters)</p>
            </div>

            {{-- 2. Patent Number * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Patent Number <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.patent_no" 
                    @input="errors.patent_no = false"
                    placeholder="e.g., US 10,876,543 B2"
                    :class="errors.patent_no ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all font-mono"
                />
                <p x-show="errors.patent_no" x-cloak class="text-[11px] text-red-500 mt-1">Please enter patent number</p>
            </div>
        </div>

        {{-- Row 2: Inventor(s) * & Assignee --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 3. Inventor(s) * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Inventor(s) <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.inventor" 
                    @input="errors.inventor = false"
                    placeholder="e.g. Dr. Jane Doe, John Smith, Prof. Robert Davis"
                    :class="errors.inventor ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.inventor" x-cloak class="text-[11px] text-red-500 mt-1">Please enter inventor name(s)</p>
            </div>

            {{-- 4. Assignee --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Assignee
                </label>
                <input 
                    type="text" 
                    x-model="form.assignee" 
                    @input="errors.assignee = false"
                    placeholder="e.g. Stanford University, Google LLC, MIT"
                    :class="errors.assignee ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
            </div>
        </div>

        {{-- Row 3: Application Number & Filing Country * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 5. Application Number --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Application Number
                </label>
                <input 
                    type="text" 
                    x-model="form.application_no" 
                    @input="errors.application_no = false"
                    placeholder="e.g. 16/123,456 or 201621040827"
                    :class="errors.application_no ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all font-mono"
                />
            </div>

            {{-- 6. Filing Country * (Searchable select with zero-latency client-side Alpine binding) --}}
            <div data-field-wrapper>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        Filing Country <span class="text-red-500">*</span>
                    </label>
                </div>
                <x-searchable-select 
                    alpine-model="form.country" 
                    :options="$countries" 
                    :clearable="false"
                    size="sm"
                    has-error-expr="errors.country"
                    placeholder="Select Filing Country" 
                    search-placeholder="Search country..." 
                    no-results-text="No result Found" 
                    wire:key="patent-country-select-{{ $itemId ?: 'new' }}" 
                />
                <p x-show="errors.country" x-cloak class="text-[11px] text-red-500 mt-1">Please select filing country</p>
            </div>
        </div>

        {{-- Row 4: Category (Searchable select from Category model cached 1 week) --}}
        <div data-field-wrapper wire:ignore.self>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Category
                </label>
            </div>
            <x-searchable-select 
                alpine-model="form.category_id" 
                :options="$categories" 
                :clearable="true"
                size="sm"
                placeholder="Select Category" 
                search-placeholder="Search category..." 
                no-results-text="No category found" 
                wire:key="patent-category-select-{{ $itemId ?: 'new' }}" 
            />
        </div>

        {{-- Row 5: Filing Date * & Publication Date (Month Pickers) --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 7. Filing Date * (Month Picker, past or present) --}}
            <div>
                <x-profile.month-picker 
                    label="Filing Date" 
                    :required="true" 
                    model="form.filing_date" 
                    has-error-expr="errors.filing_date" 
                    :disable-future="true" 
                />
                <p x-show="errors.filing_date" x-cloak class="text-[11px] text-red-500 mt-1">Please select filing date</p>
            </div>

            {{-- 8. Publication Date (Month Picker, can be future) --}}
            <div>
                <x-profile.month-picker 
                    label="Publication Date" 
                    :required="false" 
                    model="form.publication_date" 
                    has-error-expr="errors.publication_date" 
                    :disable-future="false" 
                />
            </div>
        </div>

        {{-- Row 6: Description * --}}
        <div wire:ignore.self>
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Description <span class="text-red-500">*</span>
            </label>
            <textarea 
                x-model="form.description" 
                @input="errors.description = false"
                rows="4" 
                placeholder="Brief summary of the patent innovation, abstract, key claims, or commercial applications..."
                :class="errors.description ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                class="w-full p-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all resize-y"
            ></textarea>
            <p x-show="errors.description" x-cloak class="text-[11px] text-red-500 mt-1">Please enter patent description (minimum 5 characters)</p>
        </div>

        {{-- Row 7: Form Actions & Status Row --}}
        <div wire:ignore.self class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2.5 flex-wrap">
                <button 
                    type="button" 
                    @click="saveItem()"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-60"
                >
                    <i x-show="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <i x-show="!isSaving" class="fa-solid fa-check text-xs"></i>
                    <span x-text="form.id ? 'Update Patent' : 'Save Patent'"></span>
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
                                name="form_status_patent_{{ $itemId ?? 'new' }}" 
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
                                name="form_status_patent_{{ $itemId ?? 'new' }}" 
                                value="0" 
                                :checked="form.status == 0 || form.status === '0'"
                                @change="form.status = 0"
                                class="size-4 text-brand focus:ring-brand cursor-pointer"
                            />
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-400 border border-red-200 dark:border-red-800/60">
                                <span class="size-1.5 rounded-full bg-red-500"></span>
                                Inactive
                            </span>
                        </label>
                    </div>
                @endif
            </div>

            <template x-if="form.id">
                <button 
                    type="button" 
                    @click="deleteItem(form.id)" 
                    class="px-3.5 py-2 rounded-xl bg-[#ef4444] hover:bg-red-600 text-white text-xs font-bold flex items-center gap-1.5 shadow-xs transition-all active:scale-95 cursor-pointer"
                >
                    <i class="fa-regular fa-trash-can text-sm"></i>
                    <span>Delete</span>
                </button>
            </template>
        </div>

    </div>
</div>

