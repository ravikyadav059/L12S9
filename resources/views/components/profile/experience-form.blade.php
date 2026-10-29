{{-- resources/views/components/profile/experience-form.blade.php --}}
@props([
    'showStatus' => false,
])
<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">
        
        {{-- 1. Job Title / Designation * --}}
        <div class="relative" @click.outside="showDesignationDropdown = false">
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Job Title / Designation <span class="text-red-500">*</span>
            </label>

            <div class="relative flex items-center">
                <input 
                    type="text"
                    x-model="form.designation"
                    @focus="showDesignationDropdown = true"
                    @input="showDesignationDropdown = true; errors.designation = false"
                    placeholder="Select Designation..."
                    :class="errors.designation ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs font-normal text-zinc-900 dark:text-white border rounded-xl focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 pr-14"
                />

                <div class="absolute right-2.5 flex items-center gap-1.5 text-zinc-400">
                    <button 
                        type="button" 
                        x-show="form.designation" 
                        @click="form.designation = ''; errors.designation = false"
                        class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1"
                        title="Clear"
                    >
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                    <button 
                        type="button" 
                        @click="showDesignationDropdown = !showDesignationDropdown"
                        class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200"
                    >
                        <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showDesignationDropdown ? 'rotate-180' : ''"></i>
                    </button>
                </div>

                {{-- Autocomplete Dropdown --}}
                <div 
                    x-show="showDesignationDropdown" 
                    x-cloak
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 max-h-52 overflow-y-auto"
                >
                    <template x-for="opt in designationOptions" :key="opt">
                        <div 
                            @click="form.designation = opt; errors.designation = false; showDesignationDropdown = false" 
                            class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0"
                        >
                            <span x-text="opt"></span>
                            <span x-show="form.designation === opt" class="text-brand">
                                <i class="fa-solid fa-check text-xs"></i>
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <p x-show="errors.designation" x-cloak class="text-[11px] text-red-500 mt-1">Please select a designation</p>
        </div>

        {{-- 2. Organization * --}}
        <div class="relative" @click.outside="showOrgDropdown = false">
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Organization <span class="text-red-500">*</span>
            </label>

            <div class="relative flex items-center">
                <input 
                    type="text"
                    x-model="form.organization"
                    @focus="showOrgDropdown = true"
                    @input="showOrgDropdown = true; errors.organization = false"
                    placeholder="Search Organization..."
                    :class="errors.organization ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs font-normal text-zinc-900 dark:text-white border rounded-xl focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 pr-14"
                />

                <div class="absolute right-2.5 flex items-center gap-1.5 text-zinc-400">
                    <button 
                        type="button" 
                        x-show="form.organization" 
                        @click="form.organization = ''; errors.organization = false"
                        class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1"
                        title="Clear"
                    >
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                    <button 
                        type="button" 
                        @click="showOrgDropdown = !showOrgDropdown"
                        class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200"
                    >
                        <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showOrgDropdown ? 'rotate-180' : ''"></i>
                    </button>
                </div>

                {{-- Autocomplete Dropdown --}}
                <div 
                    x-show="showOrgDropdown" 
                    x-cloak
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 max-h-52 overflow-y-auto"
                >
                    <template x-for="org in filteredOrganizations()" :key="org">
                        <div 
                            @click="form.organization = org; errors.organization = false; showOrgDropdown = false" 
                            class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0"
                        >
                            <span x-text="org"></span>
                            <span x-show="form.organization === org" class="text-brand">
                                <i class="fa-solid fa-check text-xs"></i>
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <p x-show="errors.organization" x-cloak class="text-[11px] text-red-500 mt-1">Please select an organization</p>
        </div>

        {{-- 3. Two-Column Date Row with Month-Year Popover Picker --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-profile.month-picker 
                    label="Start Date" 
                    :required="true" 
                    model="form.start_date" 
                    hasErrorExpr="errors.start_date" 
                    placeholder="December, 2025" 
                />
                <p x-show="errors.start_date" x-cloak class="text-[11px] text-red-500 mt-1">Please select start date</p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        <span>End Date</span>
                        <span x-show="!form.is_current" class="text-red-500">*</span>
                    </label>

                    <label class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-700 dark:text-zinc-300 cursor-pointer select-none">
                        <input 
                            type="checkbox" 
                            x-model="form.is_current" 
                            @change="if(form.is_current) { form.end_date = ''; errors.end_date = false; }"
                            class="size-4 rounded border-zinc-300 text-brand focus:ring-brand cursor-pointer"
                        />
                        <span>Current</span>
                    </label>
                </div>

                <x-profile.month-picker 
                    label="" 
                    :required="false" 
                    model="form.end_date" 
                    disabledExpr="form.is_current" 
                    hasErrorExpr="errors.end_date" 
                    placeholder="December, 2025" 
                />
                <p x-show="errors.end_date && !form.is_current" x-cloak class="text-[11px] text-red-500 mt-1">Please select end date</p>
            </div>
        </div>

        {{-- 4. Description --}}
        <div>
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Description
            </label>

            <textarea 
                x-model="form.description" 
                @input="errors.description = false"
                rows="3" 
                placeholder="Describe your responsibilities and achievements..."
                :class="errors.description ? 'border-red-500 bg-red-50/10 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                class="w-full p-3.5 text-xs font-normal text-zinc-900 dark:text-white border rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all leading-relaxed placeholder:text-zinc-400"
            ></textarea>

            <p x-show="errors.description" x-cloak class="text-[11px] text-red-500 mt-1" x-text="errors.descriptionMessage"></p>
            <p class="text-[11px] text-zinc-400 mt-1">
                Description (between 50 and 1500 characters)
            </p>
        </div>

        @if($showStatus)
            {{-- 5. Status (Admin Only) --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Status
                </label>
                <div class="flex items-center gap-4 pt-0.5">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 1">
                        <input 
                            type="radio" 
                            :name="'form_status_' + (form.id || 'new')" 
                            value="1" 
                            x-model="form.status"
                            :checked="form.status == 1"
                            class="size-4 text-brand focus:ring-brand cursor-pointer"
                        />
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            Active
                        </span>
                    </label>

                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 0">
                        <input 
                            type="radio" 
                            :name="'form_status_' + (form.id || 'new')" 
                            value="0" 
                            x-model="form.status"
                            :checked="form.status == 0"
                            class="size-4 text-brand focus:ring-brand cursor-pointer"
                        />
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                            <span class="size-1.5 rounded-full bg-zinc-400"></span>
                            Inactive
                        </span>
                    </label>
                </div>
            </div>
        @endif

        {{-- 6. Action Buttons --}}
        <div class="flex items-center justify-between pt-2">
            <div class="flex items-center gap-2.5">
                <button 
                    type="button" 
                    @click="submitForm()" 
                    class="px-5 py-2 rounded-xl bg-brand hover:bg-brand-600 text-white text-xs font-bold shadow-xs transition-all active:scale-95 cursor-pointer"
                    x-text="form.id ? 'Update' : 'Save'"
                >
                </button>

                <button 
                    type="button" 
                    @click="handleCancel()" 
                    class="px-5 py-2 rounded-xl bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 text-xs font-bold border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-all cursor-pointer shadow-2xs"
                >
                    Cancel
                </button>
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
