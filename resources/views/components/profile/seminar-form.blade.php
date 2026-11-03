{{-- resources/views/components/profile/seminar-form.blade.php --}}
@props([
    'showStatus' => false,
    'itemId' => null,
    'countries' => [],
    'states' => [],
    'cities' => [],
    'country' => '',
    'state' => '',
])

<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">

        {{-- Row 1: Conference/Event Name * & Host By * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- 1. Conference / Event Name * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Conference/Event Name <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    x-model="form.title" 
                    placeholder="e.g. 2nd International Conference on Data Science and Intelligent Applications"
                    :class="errors.title ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all"
                />
                <p x-show="errors.title" x-cloak class="text-[11px] text-red-500 mt-1">Please enter conference / event name (minimum 3 characters)</p>
            </div>

            {{-- 2. Host By * (Organization autocomplete) --}}
            <div class="relative" @click.outside="showOrgDropdown = false">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Host By <span class="text-red-500">*</span>
                </label>

                {{-- Custom Select Trigger --}}
                <div 
                    @click="openOrgDropdown()"
                    :class="errors.organization ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : (showOrgDropdown ? 'border-brand ring-2 ring-brand/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900')"
                    class="w-full h-10 px-3.5 text-xs font-normal border rounded-xl flex items-center justify-between cursor-pointer select-none transition-all"
                >
                    <span 
                        x-text="form.organization || 'Select Host By...'"
                        :class="form.organization ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400'"
                        class="truncate pr-2"
                    ></span>

                    <div class="flex items-center gap-1.5 text-zinc-400 shrink-0">
                        <button 
                            type="button" 
                            x-show="form.organization" 
                            @click.stop="clearOrganization()"
                            class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1 cursor-pointer"
                            title="Clear"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                        <span class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200">
                            <i class="fa-solid fa-chevron-down text-[11px] transition-transform duration-200" :class="showOrgDropdown ? 'rotate-180' : ''"></i>
                        </span>
                    </div>
                </div>

                {{-- Autocomplete Dropdown Popup --}}
                <div 
                    x-show="showOrgDropdown" 
                    x-cloak
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border-2 border-blue-500 dark:border-blue-500 rounded-xl shadow-xl z-50 overflow-hidden"
                >
                    {{-- Search Input inside Dropdown --}}
                    <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        <div class="relative flex items-center">
                            <input 
                                type="text" 
                                x-ref="orgSearchInput" 
                                x-model="orgSearchQuery" 
                                @input="searchOrganizations(orgSearchQuery)" 
                                placeholder="Search Host By / organization..." 
                                class="w-full h-9 px-3 text-xs font-normal text-zinc-900 dark:text-white border-2 border-blue-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 bg-white dark:bg-zinc-800 placeholder:text-zinc-400"
                            />
                            <button 
                                type="button" 
                                x-show="orgSearchQuery" 
                                @click="orgSearchQuery = ''; fetchOrganizations('')" 
                                class="absolute right-2.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                            >
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div 
                        @scroll.passive="onOrgScroll($event)"
                        class="max-h-52 overflow-y-auto"
                    >
                        {{-- Add New Organization --}}
                        <div 
                            x-show="orgSearchQuery && orgSearchQuery.trim() !== '' && !hasExactOrgMatch()"
                            @click="selectOrganization(orgSearchQuery)"
                            class="p-2 border-b border-zinc-100 dark:border-zinc-800/80 bg-zinc-50/40 dark:bg-zinc-900/40"
                        >
                            <div class="border-[1.5px] border-dashed border-[#198BEA] rounded-xl px-3.5 py-2 flex items-center gap-2.5 cursor-pointer bg-blue-50/50 hover:bg-blue-100/70 dark:bg-blue-950/30 dark:hover:bg-blue-950/50 transition select-none">
                                <span class="size-5 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                                    <i class="fa-solid fa-plus"></i>
                                </span>
                                <span class="text-xs font-medium text-zinc-800 dark:text-zinc-100">
                                    Add New Host
                                </span>
                            </div>
                        </div>

                        {{-- Searching state --}}
                        <div x-show="isSearchingOrg" class="px-3.5 py-3 text-xs text-zinc-400 text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                            <span>Searching organizations...</span>
                        </div>

                        {{-- Matching options list --}}
                        <template x-for="opt in organizationOptions" :key="opt">
                            <div 
                                @click="selectOrganization(opt)" 
                                :class="form.organization === opt ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-medium' : 'text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand'"
                                class="px-3.5 py-2.5 text-xs cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 transition-colors"
                            >
                                <span x-text="opt"></span>
                                <span x-show="form.organization === opt" class="text-brand">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </span>
                            </div>
                        </template>

                        {{-- Empty state --}}
                        <div 
                            x-show="!isSearchingOrg && organizationOptions.length === 0 && (!orgSearchQuery || hasExactOrgMatch())" 
                            class="px-3.5 py-3 text-xs text-zinc-400 text-center"
                        >
                            No organizations found
                        </div>

                        {{-- Infinite scroll loading indicator --}}
                        <div x-show="isLoadingMoreOrg" class="px-3.5 py-2 text-[11px] text-zinc-400 text-center flex items-center justify-center gap-2 border-t border-zinc-100 dark:border-zinc-800">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs text-brand"></i>
                            <span>Loading more...</span>
                        </div>
                    </div>
                </div>

                <p x-show="errors.organization" x-cloak class="text-[11px] text-red-500 mt-1">Please select or enter Host By</p>
            </div>
        </div>

        {{-- Row 2: Type of Event *, Date *, Role * --}}
        <div wire:ignore.self class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Type of Event * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Type of Event <span class="text-red-500">*</span>
                </label>
                <select 
                    x-model="form.type_event"
                    :class="errors.type_event ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all cursor-pointer"
                >
                    <option value="">Please select</option>
                    <option value="Conference">Conference</option>
                    <option value="Seminar">Seminar</option>
                    <option value="Webinar">Webinar</option>
                    <option value="Workshop">Workshop</option>
                    <option value="Symposium">Symposium</option>
                    <option value="FDP">FDP (Faculty Development Program)</option>
                    <option value="STTP">STTP (Short Term Training Program)</option>
                </select>
                <p x-show="errors.type_event" x-cloak class="text-[11px] text-red-500 mt-1">Please select type of event</p>
            </div>

            {{-- Date * (Month Year Picker) --}}
            <div>
                <x-profile.month-picker 
                    label="Date" 
                    :required="true" 
                    model="form.month_year" 
                    hasErrorExpr="errors.month_year" 
                    placeholder="Select Month, Year" 
                />
                <p x-show="errors.month_year" x-cloak class="text-[11px] text-red-500 mt-1">Please select date</p>
            </div>

            {{-- Role * --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Role <span class="text-red-500">*</span>
                </label>
                <select 
                    x-model="form.role"
                    :class="errors.role ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                    class="w-full h-10 px-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all cursor-pointer"
                >
                    <option value="">Select role...</option>
                    <option value="Speaker">Speaker</option>
                    <option value="Keynote Speaker">Keynote Speaker</option>
                    <option value="Panelist">Panelist</option>
                    <option value="Attendee">Attendee</option>
                    <option value="Organizer">Organizer</option>
                </select>
                <p x-show="errors.role" x-cloak class="text-[11px] text-red-500 mt-1">Please select role</p>
            </div>
        </div>

        {{-- Row 3: Country *, State *, City * --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Country * (Searchable Select from Add Journal) --}}
            <div data-field-wrapper>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        Country <span class="text-red-500">*</span>
                    </label>
                    <span wire:loading wire:target="country" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                </div>
                <x-searchable-select 
                    wire:model.live="country" 
                    :options="$countries" 
                    :clearable="false"
                    size="sm"
                    has-error-expr="errors.country"
                    placeholder="Select Country" 
                    search-placeholder="Search country..." 
                    no-results-text="No result Found" 
                    wire:key="seminar-country-select-{{ $itemId ?: 'new' }}" 
                />
                <p x-show="errors.country" x-cloak class="text-[11px] text-red-500 mt-1">Please select country</p>
            </div>

            {{-- State * (Searchable Select from Add Journal) --}}
            <div data-field-wrapper>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        State <span class="text-red-500">*</span>
                    </label>
                    <span wire:loading wire:target="state" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                </div>
                <x-searchable-select 
                    wire:model.live="state" 
                    :options="$states" 
                    :disabled="empty($country)" 
                    disabled-placeholder="Select State" 
                    :clearable="false"
                    size="sm"
                    has-error-expr="errors.state"
                    placeholder="Select State" 
                    search-placeholder="Search state..." 
                    no-results-text="No result Found" 
                    wire:key="seminar-state-select-{{ $country ?: 'none' }}-{{ $itemId ?: 'new' }}" 
                />
                <p x-show="errors.state" x-cloak class="text-[11px] text-red-500 mt-1">Please select state</p>
            </div>

            {{-- City * (Searchable Select from Add Journal) --}}
            <div data-field-wrapper>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        City <span class="text-red-500">*</span>
                    </label>
                    <span wire:loading wire:target="city" class="text-[11px] text-brand font-medium animate-pulse">Loading...</span>
                </div>
                <x-searchable-select 
                    wire:model="city" 
                    :options="$cities" 
                    :disabled="empty($state)" 
                    disabled-placeholder="Select City" 
                    :clearable="false"
                    size="sm"
                    has-error-expr="errors.city"
                    placeholder="Select City" 
                    search-placeholder="Search city..." 
                    no-results-text="No result Found" 
                    wire:key="seminar-city-select-{{ $state ?: 'none' }}-{{ $itemId ?: 'new' }}" 
                />
                <p x-show="errors.city" x-cloak class="text-[11px] text-red-500 mt-1">Please select city</p>
            </div>
        </div>

        {{-- Row 4: Description (between 50 and 1500 characters) --}}
        <div wire:ignore.self>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    Description
                </label>
                <span 
                    class="text-[11px] font-mono"
                    :class="(form.description.length > 0 && form.description.length < 50) || form.description.length > 1500 ? 'text-red-500 font-bold' : 'text-zinc-400'"
                >
                    <span x-text="form.description.length"></span> / 1,500
                </span>
            </div>
            <textarea 
                x-model="form.description" 
                rows="3" 
                placeholder="Describe your participation and key highlights... (between 50 and 1500 characters)"
                :class="errors.description ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                class="w-full p-3.5 text-xs rounded-xl border text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand/20 transition-all leading-relaxed"
            ></textarea>
            <p x-show="errors.description" x-cloak class="text-[11px] text-red-500 mt-1">Description must be between 50 and 1,500 characters</p>
            <p x-show="!errors.description" class="text-[11px] text-zinc-400 mt-1">Describe your participation, presentation, key highlights, or takeaways.</p>
        </div>

        {{-- 5. Form Actions & Status (Admin Only) --}}
        <div wire:ignore.self class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2.5 flex-wrap">
                <button 
                    type="button" 
                    @click="saveItem()"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-brand hover:bg-brand-600 rounded-xl shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-60"
                >
                    <i x-show="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                    <span x-text="form.id ? 'Update Conference / Event' : 'Save Conference / Event'"></span>
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
                                name="form_status_seminar_{{ $itemId ?? 'new' }}" 
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
                                name="form_status_seminar_{{ $itemId ?? 'new' }}" 
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
        </div>

    </div>
</div>
