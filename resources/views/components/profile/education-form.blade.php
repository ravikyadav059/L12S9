{{-- resources/views/components/profile/education-form.blade.php --}}
@props([
    'showStatus' => false,
    'itemId' => null,
])
<div class="mt-4 border-[1.5px] border-dashed border-brand rounded-2xl p-5 sm:p-6 bg-form-bg shadow-2xs">
    <div class="space-y-4">

        {{-- 1. Institution * --}}
        <div class="relative" @click.outside="showOrgDropdown = false">
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Institution <span class="text-red-500">*</span>
            </label>

            {{-- Custom Select Trigger matching Image 1 --}}
            <div 
                @click="openOrgDropdown()"
                :class="errors.institution ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : (showOrgDropdown ? 'border-brand ring-2 ring-brand/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900')"
                class="w-full h-10 px-3.5 text-xs font-normal border rounded-xl flex items-center justify-between cursor-pointer select-none transition-all"
            >
                <span 
                    x-text="form.institution || 'Select Institution...'"
                    :class="form.institution ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400'"
                    class="truncate pr-2"
                ></span>

                <div class="flex items-center gap-1.5 text-zinc-400 shrink-0">
                    <button 
                        type="button" 
                        x-show="form.institution" 
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

            {{-- Autocomplete Dropdown Popup matching Image 1 & 2 --}}
            <div 
                x-show="showOrgDropdown" 
                x-cloak
                class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border-2 border-blue-500 dark:border-blue-500 rounded-xl shadow-xl z-50 overflow-hidden"
            >
                {{-- Search Input inside Dropdown matching Image 1 --}}
                <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                    <div class="relative flex items-center">
                        <input 
                            type="text"
                            x-ref="orgSearchInput"
                            x-model="orgSearchQuery"
                            @input="searchOrganizations(orgSearchQuery)"
                            placeholder="Search..."
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
                    {{-- Add New Institution (Dashed button) --}}
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
                                Add New Institution
                            </span>
                        </div>
                    </div>

                    {{-- Searching state --}}
                    <div x-show="isSearchingOrg" class="px-3.5 py-3 text-xs text-zinc-400 text-center flex items-center justify-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Searching institutions...</span>
                    </div>

                    {{-- Matching options list (Selected highlighted matching Image 2) --}}
                    <template x-for="org in organizationOptions" :key="org">
                        <div 
                            @click="selectOrganization(org)" 
                            :class="form.institution === org ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-medium' : 'text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand'"
                            class="px-3.5 py-2.5 text-xs cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 transition-colors"
                        >
                            <span x-text="org"></span>
                            <span x-show="form.institution === org" class="text-brand">
                                <i class="fa-solid fa-check text-xs"></i>
                            </span>
                        </div>
                    </template>

                    {{-- Empty state --}}
                    <div 
                        x-show="!isSearchingOrg && organizationOptions.length === 0 && (!orgSearchQuery || hasExactOrgMatch())" 
                        class="px-3.5 py-3 text-xs text-zinc-400 text-center"
                    >
                        No institutions found
                    </div>

                    {{-- Infinite scroll loading indicator --}}
                    <div x-show="isLoadingMoreOrg" class="px-3.5 py-2 text-[11px] text-zinc-400 text-center flex items-center justify-center gap-2 border-t border-zinc-100 dark:border-zinc-800">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Loading more...</span>
                    </div>
                </div>
            </div>

            <p x-show="errors.institution" x-cloak class="text-[11px] text-red-500 mt-1">Please select an institution</p>
        </div>

        {{-- 2. Degree * and Field of Study (Two Columns) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Degree * --}}
            <div class="relative" @click.outside="showDegreeDropdown = false">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Degree <span class="text-red-500">*</span>
                </label>

                {{-- Custom Select Trigger matching Image 1 --}}
                <div 
                    @click="openDegreeDropdown()"
                    :class="errors.degree ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : (showDegreeDropdown ? 'border-brand ring-2 ring-brand/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900')"
                    class="w-full h-10 px-3.5 text-xs font-normal border rounded-xl flex items-center justify-between cursor-pointer select-none transition-all"
                >
                    <span 
                        x-text="form.degree || 'Select Degree...'"
                        :class="form.degree ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400'"
                        class="truncate pr-2"
                    ></span>

                    <div class="flex items-center gap-1.5 text-zinc-400 shrink-0">
                        <button 
                            type="button" 
                            x-show="form.degree" 
                            @click.stop="clearDegree()"
                            class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1 cursor-pointer"
                            title="Clear"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                        <span class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200">
                            <i class="fa-solid fa-chevron-down text-[11px] transition-transform duration-200" :class="showDegreeDropdown ? 'rotate-180' : ''"></i>
                        </span>
                    </div>
                </div>

                {{-- Autocomplete Dropdown Popup matching Image 1 & 2 --}}
                <div 
                    x-show="showDegreeDropdown" 
                    x-cloak
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border-2 border-blue-500 dark:border-blue-500 rounded-xl shadow-xl z-50 overflow-hidden"
                >
                    {{-- Search Input inside Dropdown matching Image 1 --}}
                    <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                    <div class="relative flex items-center">
                        <input 
                            type="text"
                            x-ref="degreeSearchInput"
                            x-model="degreeSearchQuery"
                            @input="searchDegrees(degreeSearchQuery)"
                            placeholder="Search..."
                            class="w-full h-9 px-3 text-xs font-normal text-zinc-900 dark:text-white border-2 border-blue-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 bg-white dark:bg-zinc-800 placeholder:text-zinc-400"
                        />
                        <button 
                            type="button" 
                            x-show="degreeSearchQuery" 
                            @click="degreeSearchQuery = ''; fetchDegrees('')"
                            class="absolute right-2.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>

                <div 
                    @scroll.passive="onDegreeScroll($event)"
                    class="max-h-52 overflow-y-auto"
                >
                    {{-- Add New Degree (Dashed button) --}}
                    <div 
                        x-show="degreeSearchQuery && degreeSearchQuery.trim() !== '' && !hasExactDegreeMatch()"
                        @click="selectDegree(degreeSearchQuery)"
                        class="p-2 border-b border-zinc-100 dark:border-zinc-800/80 bg-zinc-50/40 dark:bg-zinc-900/40"
                    >
                        <div class="border-[1.5px] border-dashed border-[#198BEA] rounded-xl px-3.5 py-2 flex items-center gap-2.5 cursor-pointer bg-blue-50/50 hover:bg-blue-100/70 dark:bg-blue-950/30 dark:hover:bg-blue-950/50 transition select-none">
                            <span class="size-5 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                                <i class="fa-solid fa-plus"></i>
                            </span>
                            <span class="text-xs font-medium text-zinc-800 dark:text-zinc-100">
                                Add New Degree
                            </span>
                        </div>
                    </div>

                    {{-- Searching state --}}
                    <div x-show="isSearchingDegree" class="px-3.5 py-3 text-xs text-zinc-400 text-center flex items-center justify-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Searching degrees...</span>
                    </div>

                    {{-- Matching options list (Selected highlighted matching Image 2) --}}
                    <template x-for="deg in degreeOptions" :key="deg">
                        <div 
                            @click="selectDegree(deg)" 
                            :class="form.degree === deg ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-medium' : 'text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand'"
                            class="px-3.5 py-2.5 text-xs cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 transition-colors"
                        >
                            <span x-text="deg"></span>
                            <span x-show="form.degree === deg" class="text-brand">
                                <i class="fa-solid fa-check text-xs"></i>
                            </span>
                        </div>
                    </template>

                    {{-- Empty state --}}
                    <div 
                        x-show="!isSearchingDegree && degreeOptions.length === 0 && (!degreeSearchQuery || hasExactDegreeMatch())" 
                        class="px-3.5 py-3 text-xs text-zinc-400 text-center"
                    >
                        No degrees found
                    </div>

                    {{-- Infinite scroll loading indicator --}}
                    <div x-show="isLoadingMoreDegree" class="px-3.5 py-2 text-[11px] text-zinc-400 text-center flex items-center justify-center gap-2 border-t border-zinc-100 dark:border-zinc-800">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Loading more...</span>
                    </div>
                </div>
            </div>

            <p x-show="errors.degree" x-cloak class="text-[11px] text-red-500 mt-1">Please select a degree</p>
        </div>

        {{-- Field of Study --}}
        <div class="relative" @click.outside="showFieldDropdown = false">
            <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                Field of Study
            </label>

            {{-- Custom Select Trigger matching Image 1 --}}
            <div 
                @click="openFieldDropdown()"
                :class="showFieldDropdown ? 'border-brand ring-2 ring-brand/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                class="w-full h-10 px-3.5 text-xs font-normal border rounded-xl flex items-center justify-between cursor-pointer select-none transition-all"
            >
                <span 
                    x-text="form.field_of_study || 'Select Field of Study...'"
                    :class="form.field_of_study ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400'"
                    class="truncate pr-2"
                ></span>

                <div class="flex items-center gap-1.5 text-zinc-400 shrink-0">
                    <button 
                        type="button" 
                        x-show="form.field_of_study" 
                        @click.stop="clearField()"
                        class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1 cursor-pointer"
                        title="Clear"
                    >
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                    <span class="p-0.5 hover:text-zinc-700 dark:hover:text-zinc-200">
                        <i class="fa-solid fa-chevron-down text-[11px] transition-transform duration-200" :class="showFieldDropdown ? 'rotate-180' : ''"></i>
                    </span>
                </div>
            </div>

            {{-- Autocomplete Dropdown Popup matching Image 1 & 2 --}}
            <div 
                x-show="showFieldDropdown" 
                x-cloak
                class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border-2 border-blue-500 dark:border-blue-500 rounded-xl shadow-xl z-50 overflow-hidden"
            >
                {{-- Search Input inside Dropdown matching Image 1 --}}
                <div class="p-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                    <div class="relative flex items-center">
                        <input 
                            type="text"
                            x-ref="fieldSearchInput"
                            x-model="fieldSearchQuery"
                            @input="searchFields(fieldSearchQuery)"
                            placeholder="Search..."
                            class="w-full h-9 px-3 text-xs font-normal text-zinc-900 dark:text-white border-2 border-blue-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 bg-white dark:bg-zinc-800 placeholder:text-zinc-400"
                        />
                        <button 
                            type="button" 
                            x-show="fieldSearchQuery" 
                            @click="fieldSearchQuery = ''; fetchFields('')"
                            class="absolute right-2.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>

                <div 
                    @scroll.passive="onFieldScroll($event)"
                    class="max-h-52 overflow-y-auto"
                >
                    {{-- Add New Field of Study (Dashed button) --}}
                    <div 
                        x-show="fieldSearchQuery && fieldSearchQuery.trim() !== '' && !hasExactFieldMatch()"
                        @click="selectField(fieldSearchQuery)"
                        class="p-2 border-b border-zinc-100 dark:border-zinc-800/80 bg-zinc-50/40 dark:bg-zinc-900/40"
                    >
                        <div class="border-[1.5px] border-dashed border-[#198BEA] rounded-xl px-3.5 py-2 flex items-center gap-2.5 cursor-pointer bg-blue-50/50 hover:bg-blue-100/70 dark:bg-blue-950/30 dark:hover:bg-blue-950/50 transition select-none">
                            <span class="size-5 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                                <i class="fa-solid fa-plus"></i>
                            </span>
                            <span class="text-xs font-medium text-zinc-800 dark:text-zinc-100">
                                Add New Field of Study
                            </span>
                        </div>
                    </div>

                    {{-- Searching state --}}
                    <div x-show="isSearchingField" class="px-3.5 py-3 text-xs text-zinc-400 text-center flex items-center justify-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Searching fields of study...</span>
                    </div>

                    {{-- Matching options list (Selected highlighted matching Image 2) --}}
                    <template x-for="fld in fieldOptions" :key="fld">
                        <div 
                            @click="selectField(fld)" 
                            :class="form.field_of_study === fld ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-medium' : 'text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand'"
                            class="px-3.5 py-2.5 text-xs cursor-pointer flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0 transition-colors"
                        >
                            <span x-text="fld"></span>
                            <span x-show="form.field_of_study === fld" class="text-brand">
                                <i class="fa-solid fa-check text-xs"></i>
                            </span>
                        </div>
                    </template>

                    {{-- Empty state --}}
                    <div 
                        x-show="!isSearchingField && fieldOptions.length === 0 && (!fieldSearchQuery || hasExactFieldMatch())" 
                        class="px-3.5 py-3 text-xs text-zinc-400 text-center"
                    >
                        No fields found
                    </div>

                    {{-- Infinite scroll loading indicator --}}
                    <div x-show="isLoadingMoreField" class="px-3.5 py-2 text-[11px] text-zinc-400 text-center flex items-center justify-center gap-2 border-t border-zinc-100 dark:border-zinc-800">
                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Loading more...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

        {{-- 3. Grade and Passout Year (with Pursuing Checkbox) (Two Columns) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Grade --}}
            <div class="relative" @click.outside="showGradeDropdown = false">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Grade
                </label>

                <div class="relative">
                    <button 
                        type="button"
                        @click="showGradeDropdown = !showGradeDropdown; if (showGradeDropdown) $nextTick(() => $refs.gradeSearchInput?.focus())"
                        class="w-full h-10 px-3.5 text-xs font-normal text-left border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 rounded-xl focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 transition-all flex items-center justify-between cursor-pointer"
                    >
                        <span :class="form.grade ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400'" x-text="form.grade || 'Select...'"></span>
                        <div class="flex items-center gap-1.5 text-zinc-400">
                            <span 
                                x-show="form.grade" 
                                @click.stop="clearGrade()"
                                class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1 cursor-pointer"
                                title="Clear"
                            >
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </span>
                            <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showGradeDropdown ? 'rotate-180' : ''"></i>
                        </div>
                    </button>

                    {{-- Grade Dropdown with Search Input --}}
                    <div 
                        x-show="showGradeDropdown" 
                        x-cloak
                        class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 p-2"
                    >
                        <div class="mb-2">
                            <input 
                                type="text"
                                x-ref="gradeSearchInput"
                                x-model="gradeSearchQuery"
                                placeholder="Search Grade..."
                                class="w-full h-8 px-2.5 text-xs text-zinc-900 dark:text-white bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg focus:outline-none focus:border-brand"
                            />
                        </div>

                        <div class="max-h-48 overflow-y-auto space-y-0.5">
                            <template x-for="g in filteredGrades" :key="g">
                                <div 
                                    @click="selectGrade(g)" 
                                    :class="form.grade === g ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-medium' : 'text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand'"
                                    class="px-2.5 py-1.5 text-xs rounded-lg cursor-pointer flex items-center justify-between transition-colors"
                                >
                                    <span x-text="g"></span>
                                    <span x-show="form.grade === g" class="text-brand">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </span>
                                </div>
                            </template>

                            <div x-show="filteredGrades.length === 0" class="px-2 py-3 text-xs text-zinc-400 text-center">
                                No grades found
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Passout Year * with Pursuing Checkbox --}}
            <div>
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Passout Year <span class="text-red-500">*</span>
                </label>

                <div class="flex items-center gap-3">
                    {{-- Year Select Dropdown with Search Filter --}}
                    <div class="relative flex-1" @click.outside="showYearDropdown = false">
                        <button 
                            type="button"
                            :disabled="form.pursuing"
                            @click="if(!form.pursuing) { showYearDropdown = !showYearDropdown; if (showYearDropdown) $nextTick(() => $refs.yearSearchInput?.focus()); }"
                            :class="[
                                errors.passout_year ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900',
                                form.pursuing ? 'opacity-50 cursor-not-allowed bg-zinc-100 dark:bg-zinc-800' : 'cursor-pointer'
                            ]"
                            class="w-full h-10 px-3.5 text-xs font-normal text-left border rounded-xl focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 transition-all flex items-center justify-between"
                        >
                            <span 
                                :class="(form.passout_year && !form.pursuing) ? 'text-zinc-900 dark:text-white font-medium' : 'text-zinc-400'" 
                                x-text="form.pursuing ? 'Pursuing' : (form.passout_year || 'Select...')"
                            ></span>
                            <div class="flex items-center gap-1.5 text-zinc-400">
                                <span 
                                    x-show="form.passout_year && !form.pursuing" 
                                    @click.stop="clearYear()"
                                    class="hover:text-zinc-700 dark:hover:text-zinc-200 px-1 cursor-pointer"
                                    title="Clear"
                                >
                                    <i class="fa-solid fa-xmark text-xs"></i>
                                </span>
                                <i class="fa-solid fa-chevron-down text-[11px] transition-transform" :class="showYearDropdown ? 'rotate-180' : ''"></i>
                            </div>
                        </button>

                        {{-- Passout Year Searchable Dropdown Popup --}}
                        <div 
                            x-show="showYearDropdown && !form.pursuing" 
                            x-cloak
                            class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 p-2"
                        >
                            <div class="mb-2">
                                <input 
                                    type="text"
                                    x-ref="yearSearchInput"
                                    x-model="yearSearchQuery"
                                    placeholder="Search Year..."
                                    class="w-full h-8 px-2.5 text-xs text-zinc-900 dark:text-white bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg focus:outline-none focus:border-brand"
                                />
                            </div>

                            <div class="max-h-48 overflow-y-auto space-y-0.5">
                                <template x-for="yr in filteredYears" :key="yr">
                                    <div 
                                        @click="selectYear(yr)" 
                                        :class="String(form.passout_year) === String(yr) ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-medium' : 'text-zinc-700 dark:text-zinc-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-brand'"
                                        class="px-2.5 py-1.5 text-xs rounded-lg cursor-pointer flex items-center justify-between transition-colors"
                                    >
                                        <span x-text="yr"></span>
                                        <span x-show="String(form.passout_year) === String(yr)" class="text-brand">
                                            <i class="fa-solid fa-check text-xs"></i>
                                        </span>
                                    </div>
                                </template>

                                <div x-show="filteredYears.length === 0" class="px-2 py-3 text-xs text-zinc-400 text-center">
                                    No years found
                                </div>
                            </div>
                        </div>

                        <p x-show="errors.passout_year && !form.pursuing" x-cloak class="text-[11px] text-red-500 mt-1">Please select a passout year or mark as pursuing</p>
                    </div>

                    {{-- Pursuing Checkbox --}}
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-zinc-700 dark:text-zinc-200 select-none whitespace-nowrap shrink-0">
                        <input 
                            type="checkbox"
                            x-model="form.pursuing"
                            @change="togglePursuing()"
                            class="size-4 rounded text-brand focus:ring-brand border-zinc-300 dark:border-zinc-700 cursor-pointer"
                        />
                        <span>Pursuing</span>
                    </label>
                </div>
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
                placeholder="Add details about your education..."
                :class="errors.description ? 'border-red-500 bg-red-50/10 dark:bg-red-950/10' : 'border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900'"
                class="w-full p-3.5 text-xs font-normal text-zinc-900 dark:text-white border rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all leading-relaxed placeholder:text-zinc-400"
            ></textarea>

            <p x-show="errors.description" x-cloak class="text-[11px] text-red-500 mt-1" x-text="errors.descriptionMessage"></p>
            <p x-show="!errors.description" class="text-[11px] text-zinc-400 mt-1">
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
                            name="form_status_{{ $itemId ?? 'new' }}" 
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

                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-zinc-700 dark:text-zinc-200 select-none" @click="form.status = 0">
                        <input 
                            type="radio" 
                            name="form_status_{{ $itemId ?? 'new' }}" 
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
