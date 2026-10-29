@props([
    'user' => null,
    'isOwnProfile' => true,
])

@php
    $reviewerProfile = $user?->reviewerProfile;
    $bio = (string) ($reviewerProfile?->about_us ?: $reviewerProfile?->aboutus ?: '');
    
    $dbSkills = $reviewerProfile?->skills_tags ?: $reviewerProfile?->skills ?: [];
    if (is_string($dbSkills)) {
        $dbSkills = array_values(array_filter(array_map('trim', explode(',', $dbSkills))));
    }
    $skills = is_array($dbSkills) ? array_values(array_filter($dbSkills)) : [];

    $dbInterests = $reviewerProfile?->research_tags ?: $reviewerProfile?->research ?: [];
    if (is_string($dbInterests)) {
        $dbInterests = array_values(array_filter(array_map('trim', explode(',', $dbInterests))));
    }
    $interests = is_array($dbInterests) ? array_values(array_filter($dbInterests)) : [];

    $socialLinks = [
        'github_url' => (string) ($reviewerProfile?->github_url ?: ''),
        'google_scholar_url' => (string) ($reviewerProfile?->google_scholar_url ?: ''),
        'orcid_url' => (string) ($reviewerProfile?->orcid_url ?: ''),
        'linkedin_url' => (string) ($reviewerProfile?->linkedin_url ?: ''),
        'public_profile' => (string) ($reviewerProfile?->public_profile ?: ''),
        'youtube_url' => (string) ($reviewerProfile?->youtube_link ?: ($reviewerProfile?->youtube_url ?: '')),
        'employee_url' => (string) ($reviewerProfile?->employee_url ?: ''),
    ];
@endphp

<div 
    x-data="{
        isEditing: false,
        expandedBio: false,
        showAllSkills: false,
        showAllInterests: false,
        
        bioText: @js($bio),
        tempBioText: @js($bio),
        
        skillsList: @js($skills),
        tempSkillsList: [...@js($skills)],
        newSkillInput: '',
        
        interestsList: @js($interests),
        tempInterestsList: [...@js($interests)],
        newInterestInput: '',
        suggestedInterests: [],
        showInterestDropdown: false,
        searchDebounceTimer: null,

        socialLinks: @js($socialLinks),
        tempSocialLinks: { ...@js($socialLinks) },

        startEditing() {
            this.tempBioText = this.bioText;
            this.tempSkillsList = [...this.skillsList];
            this.tempInterestsList = [...this.interestsList];
            this.tempSocialLinks = { ...this.socialLinks };
            this.newSkillInput = '';
            this.newInterestInput = '';
            this.suggestedInterests = [];
            this.showInterestDropdown = false;
            this.isEditing = true;
        },

        cancelEditing() {
            this.isEditing = false;
            this.showInterestDropdown = false;
        },

        saveChanges() {
            this.bioText = this.tempBioText;
            this.skillsList = [...this.tempSkillsList];
            this.interestsList = [...this.tempInterestsList];
            this.socialLinks = { ...this.tempSocialLinks };
            this.isEditing = false;
            this.showInterestDropdown = false;
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'About section updated successfully!', type: 'success', position: 'bottom' } }));
        },

        addSkill() {
            const val = this.newSkillInput.trim().replace(/^,|,$/g, '');
            if (val) {
                const items = val.split(',').map(s => s.trim()).filter(Boolean);
                items.forEach(item => {
                    if (!this.tempSkillsList.some(s => s.toLowerCase() === item.toLowerCase())) {
                        this.tempSkillsList.push(item);
                    }
                });
            }
            this.newSkillInput = '';
        },

        removeSkill(index) {
            this.tempSkillsList.splice(index, 1);
        },

        fetchSuggestedInterests() {
            clearTimeout(this.searchDebounceTimer);
            this.searchDebounceTimer = setTimeout(() => {
                const query = this.newInterestInput ? this.newInterestInput.trim() : '';
                const params = new URLSearchParams();
                if (query) params.append('q', query);
                this.tempInterestsList.forEach(item => params.append('exclude[]', item));

                fetch(`/api/research-tags?${params.toString()}`)
                    .then(res => res.json())
                    .then(data => {
                        this.suggestedInterests = Array.isArray(data) ? data : [];
                    })
                    .catch(() => {
                        this.suggestedInterests = [];
                    });
            }, 200);
        },

        addInterest() {
            const val = this.newInterestInput.trim().replace(/^,|,$/g, '');
            if (val) {
                const items = val.split(',').map(s => s.trim()).filter(Boolean);
                items.forEach(item => {
                    if (!this.tempInterestsList.some(i => i.toLowerCase() === item.toLowerCase())) {
                        this.tempInterestsList.push(item);
                    }
                });
            }
            this.newInterestInput = '';
            this.showInterestDropdown = false;
        },

        addSpecificInterest(name) {
            const val = name.trim();
            if (val && !this.tempInterestsList.some(i => i.toLowerCase() === val.toLowerCase())) {
                this.tempInterestsList.push(val);
            }
            this.newInterestInput = '';
            this.showInterestDropdown = false;
        },

        isExactMatch(name) {
            const term = (name || '').trim().toLowerCase();
            return this.suggestedInterests.some(item => (item.name || '').toLowerCase() === term);
        },

        removeInterest(index) {
            this.tempInterestsList.splice(index, 1);
        }
    }" 
    class="scroll-mt-20"
    id="about"
>
    {{-- .NewProfilesection-card: border 1px solid #e5e7eb, border-radius 16px, padding 20px --}}
    <section class="bg-white dark:bg-[#111c26] border border-[#e5e7eb] dark:border-zinc-800 rounded-2xl p-5 shadow-xs hover:shadow-md transition-all duration-300">
        
        {{-- .NewProfilesection-NewProfileheader --}}
        <div class="flex items-center justify-between pb-3 mb-4 border-b-2 border-[#f9fafb] dark:border-zinc-800">
            <h2 class="text-base font-semibold font-space text-[#111827] dark:text-white flex items-center gap-2.5 m-0 leading-snug">
                <i class="fa-solid fa-user text-base text-[#4f2a7a] dark:text-[#a855f7]"></i>
                <span>About</span>
            </h2>

            @if($isOwnProfile)
                <div>
                    {{-- Edit button (toggles inline form instead of modal) --}}
                    <button 
                        type="button" 
                        x-show="!isEditing"
                        @click="startEditing()" 
                        class="p-1.5 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors cursor-pointer"
                        title="Edit About"
                    >
                        <i class="fa-solid fa-pen-to-square text-[15px]"></i>
                    </button>
                    <button 
                        type="button" 
                        x-show="isEditing" 
                        x-cloak
                        @click="cancelEditing()" 
                        class="p-1.5 text-zinc-400 hover:text-red-500 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors cursor-pointer"
                        title="Close Edit"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            @endif
        </div>

        {{-- ========================================== --}}
        {{-- VIEW MODE (#about-display-mode)            --}}
        {{-- ========================================== --}}
        <div x-show="!isEditing" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            {{-- Bio Description with View More (15px, line-height: 1.6, color: #111827) --}}
            <div class="space-y-2">
                <template x-if="bioText && bioText.trim() !== ''">
                    <div>
                        <div 
                            :class="expandedBio ? '' : 'line-clamp-3'" 
                            class="text-[15px] leading-[1.6] text-[#111827] dark:text-zinc-300 transition-all whitespace-pre-line"
                            x-text="bioText"
                        ></div>

                        <button 
                            type="button" 
                            x-show="bioText.length > 250"
                            @click="expandedBio = !expandedBio" 
                            class="inline-flex items-center gap-1 text-[13px] font-semibold text-brand hover:underline cursor-pointer pt-0.5"
                        >
                            <span x-text="expandedBio ? 'View Less' : 'View More'">View More</span>
                            <svg 
                                :class="expandedBio ? 'rotate-180' : ''" 
                                class="size-3 transition-transform" 
                                fill="none" 
                                viewBox="0 0 24 24" 
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </template>
                <template x-if="!bioText || bioText.trim() === ''">
                    <p class="text-[14px] text-zinc-400 dark:text-zinc-500 italic py-1">No biography added yet.</p>
                </template>
            </div>

            {{-- Skills & Expertise (margin: 20px 0 10px, 16px, font-weight: 600, color: #111827) --}}
            <div class="mt-5">
                <h3 class="text-[16px] font-semibold font-space text-[#111827] dark:text-zinc-100 mb-2.5 tracking-[-0.3px] leading-[1.4] flex items-center gap-2.5">
                    Skills &amp; Expertise
                </h3>

                <div class="flex flex-wrap gap-2 items-center">
                    <template x-for="(skill, index) in skillsList" :key="index">
                        <span 
                            x-show="showAllSkills || index < 10"
                            class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-brand-50 dark:bg-brand/10 text-brand border border-brand/15 hover:bg-brand hover:text-white transition-all duration-200 cursor-default"
                            x-text="skill"
                        ></span>
                    </template>

                    <button 
                        type="button" 
                        x-show="skillsList.length > 10"
                        @click="showAllSkills = !showAllSkills" 
                        class="text-[13px] font-semibold text-brand hover:underline cursor-pointer ml-1 self-center"
                    >
                        <span x-text="showAllSkills ? 'Show less' : '+' + (skillsList.length - 10) + ' View More'"></span>
                    </button>

                    <template x-if="skillsList.length === 0">
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 italic py-0.5">No skills added yet.</p>
                    </template>
                </div>
            </div>

            {{-- Research Interests (margin: 20px 0 10px, 16px, font-weight: 600, color: #111827) --}}
            <div class="mt-5">
                <h3 class="text-[16px] font-semibold font-space text-[#111827] dark:text-zinc-100 mb-2.5 tracking-[-0.3px] leading-[1.4] flex items-center gap-2.5">
                    Research Interests
                </h3>

                <div class="flex flex-wrap gap-2 items-center">
                    <template x-for="(interest, index) in interestsList" :key="index">
                        <span 
                            x-show="showAllInterests || index < 10"
                            class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-brand-50 dark:bg-brand/10 text-brand border border-brand/15 hover:bg-brand hover:text-white transition-all duration-200 cursor-default"
                            x-text="interest"
                        ></span>
                    </template>

                    <button 
                        type="button" 
                        x-show="interestsList.length > 10"
                        @click="showAllInterests = !showAllInterests" 
                        class="text-[13px] font-semibold text-brand hover:underline cursor-pointer ml-1 self-center"
                    >
                        <span x-text="showAllInterests ? 'Show less' : '+' + (interestsList.length - 10) + ' View More'"></span>
                    </button>

                    <template x-if="interestsList.length === 0">
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 italic py-0.5">No research interests added yet.</p>
                    </template>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- EDIT MODE (Inline Form - Matches Screenshot) --}}
        {{-- ========================================== --}}
        <form 
            x-show="isEditing" 
            x-cloak
            x-transition:enter="ease-out duration-200" 
            x-transition:enter-start="opacity-0 translate-y-1" 
            x-transition:enter-end="opacity-100 translate-y-0"
            @submit.prevent="saveChanges()" 
            class="space-y-4 pt-1"
        >
            {{-- 1. About Me / Bio --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <label class="text-[14px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px]">
                        About Me / Bio <span class="text-red-500">*</span>
                    </label>

                    {{-- Generate with AI Pill Button (Static button per requirement) --}}
                    <button 
                        type="button" 
                        class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold text-[#111827] dark:text-zinc-200 bg-white dark:bg-zinc-800 border border-zinc-400 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-all cursor-pointer shadow-2xs active:scale-95"
                        title="Generate with AI"
                    >
                        <span class="text-xs">✏️</span>
                        <span>Generate with AI</span>
                    </button>
                </div>

                {{-- Textarea with rounded borders and light gray border --}}
                <textarea 
                    x-model="tempBioText" 
                    rows="6" 
                    class="w-full p-3.5 text-[14px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all leading-relaxed placeholder-zinc-400"
                    placeholder="Tell us about yourself, your background, and your professional journey..."
                    required
                ></textarea>

                <p class="text-xs text-[#6b7280] dark:text-zinc-400 mt-1">
                    Write a brief introduction about yourself (between 50 and 1500 characters)
                </p>
            </div>

            {{-- 2. Skills & Expertise --}}
            <div>
                <label class="block text-[14px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] mb-1.5">
                    Skills &amp; Expertise
                </label>

                {{-- Interactive Tags Container (Solid Blue Chips with crisp white SVG '✖' on right) --}}
                <div 
                    @click="$refs.skillInputRef.focus()"
                    class="min-h-[50px] p-2.5 border border-[#d1d5db] dark:border-zinc-700 rounded-lg bg-white dark:bg-zinc-900 flex flex-wrap items-center gap-2 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/10 transition-all cursor-text"
                >
                    <template x-for="(skill, index) in tempSkillsList" :key="index">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[5px] bg-brand text-white text-[13px] font-semibold shadow-2xs">
                            <span x-text="skill"></span>
                            <button 
                                type="button" 
                                @click.stop="removeSkill(index)" 
                                class="flex items-center justify-center p-0.5 text-white/90 hover:text-white transition-opacity cursor-pointer rounded leading-none"
                                title="Remove skill"
                            >
                                <svg class="size-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </template>

                    <input 
                        x-ref="skillInputRef"
                        type="text" 
                        x-model="newSkillInput" 
                        @keydown.enter.prevent="addSkill()" 
                        @keydown.comma.prevent="addSkill()" 
                        placeholder="Type and press Enter, or paste comma-separated skills..." 
                        class="flex-1 min-w-[150px] border-none outline-none bg-transparent text-[13px] text-[#111827] dark:text-zinc-100 placeholder-[#9ca3af] px-1 py-1"
                    />
                </div>

                <p class="text-xs text-[#6b7280] dark:text-zinc-400 mt-1">
                    Type and press Enter, or paste comma-separated skills
                </p>
            </div>

            {{-- 3. Research Interests --}}
            <div class="relative" @click.outside="showInterestDropdown = false">
                <label class="block text-[14px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] mb-1.5">
                    Research Interests
                </label>

                {{-- Interactive Multi-Select Container (White Chips + Blue Border + crisp blue SVG '✕' on left with divider) --}}
                <div 
                    @click="$refs.interestInputRef.focus()"
                    class="min-h-[50px] max-h-[110px] overflow-y-auto p-2 border border-[#60a5fa] dark:border-blue-500/40 rounded-lg bg-white dark:bg-zinc-900 flex flex-wrap items-center gap-2 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/10 transition-all cursor-text scrollbar-thin"
                >
                    <template x-for="(interest, index) in tempInterestsList" :key="index">
                        <span class="inline-flex items-stretch rounded-[5px] border border-[#38bdf8] bg-white dark:bg-zinc-800 text-[13px] overflow-hidden shadow-2xs hover:border-[#0284c7] transition-colors">
                            <button 
                                type="button" 
                                @click.stop="removeInterest(index)" 
                                class="px-2 py-1 flex items-center justify-center border-r border-[#38bdf8] text-[#0284c7] hover:bg-rose-50 hover:text-rose-600 transition-colors cursor-pointer"
                                title="Remove interest"
                            >
                                <svg class="size-3 text-[#0284c7]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <span class="px-2.5 py-1 text-[#0284c7] dark:text-sky-400 font-medium text-[13px] flex items-center whitespace-nowrap" x-text="interest"></span>
                        </span>
                    </template>

                    <input 
                        x-ref="interestInputRef"
                        type="text" 
                        x-model="newInterestInput" 
                        @focus="showInterestDropdown = true; fetchSuggestedInterests()"
                        @input="showInterestDropdown = true; fetchSuggestedInterests()"
                        @keydown.enter.prevent="addInterest()" 
                        @keydown.comma.prevent="addInterest()" 
                        placeholder="Search and select research interests..." 
                        class="flex-1 min-w-[150px] border-none outline-none bg-transparent text-[13px] text-[#111827] dark:text-zinc-100 placeholder-[#9ca3af] px-1 py-1"
                    />
                </div>

                {{-- Research Interests Dropdown & Auto-Add Option matching screenshot --}}
                <div 
                    x-show="showInterestDropdown && (suggestedInterests.length > 0 || (newInterestInput.trim() !== '' && !tempInterestsList.includes(newInterestInput.trim())))" 
                    x-cloak
                    class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 overflow-hidden max-h-56 overflow-y-auto"
                >
                    {{-- Suggested active interests --}}
                    <template x-for="item in suggestedInterests" :key="item.id">
                        <div 
                            @click="addSpecificInterest(item.name)" 
                            class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-800/80 hover:text-brand cursor-pointer flex items-center justify-between transition-colors border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0"
                        >
                            <span x-text="item.name"></span>
                            <span class="text-[10px] text-zinc-400">Select</span>
                        </div>
                    </template>

                    {{-- Add new unique interest option matching user screenshot --}}
                    <template x-if="newInterestInput.trim() !== '' && !tempInterestsList.includes(newInterestInput.trim()) && !isExactMatch(newInterestInput)">
                        <div 
                            @click="addSpecificInterest(newInterestInput)" 
                            class="px-3.5 py-2.5 text-xs font-semibold text-brand hover:bg-blue-50/70 dark:hover:bg-blue-900/30 cursor-pointer flex items-center gap-2 border-t border-zinc-100 dark:border-zinc-800 transition-colors"
                        >
                            <svg class="size-4 shrink-0 text-brand" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                            </svg>
                            <span>+ Add "<span class="font-bold" x-text="newInterestInput.trim()"></span>" as New Interest</span>
                        </div>
                    </template>
                </div>

                <p class="text-xs text-[#6b7280] dark:text-zinc-400 mt-1">
                    Search and select research interests
                </p>
            </div>

            {{-- 4. Social Links (Optional) --}}
            <div class="pt-2">
                <h3 class="text-[15px] font-bold text-[#111827] dark:text-zinc-100 mb-3 tracking-[-0.2px]">
                    Social Links (Optional)
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- 1. GitHub URL --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[13px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] flex items-center gap-1.5">
                            <span>🔗</span>
                            <span>GitHub URL</span>
                        </label>
                        <input 
                            type="url" 
                            x-model="tempSocialLinks.github_url" 
                            placeholder="https://github.com/username"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 shadow-2xs"
                        />
                    </div>

                    {{-- 2. Google Scholar URL --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[13px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] flex items-center gap-1.5">
                            <span>🎓</span>
                            <span>Google Scholar URL</span>
                        </label>
                        <input 
                            type="url" 
                            x-model="tempSocialLinks.google_scholar_url" 
                            placeholder="https://scholar.google.com/citations?user=..."
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 shadow-2xs"
                        />
                    </div>

                    {{-- 3. ORCID URL --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[13px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] flex items-center gap-1.5">
                            <span>🔬</span>
                            <span>ORCID URL</span>
                        </label>
                        <input 
                            type="url" 
                            x-model="tempSocialLinks.orcid_url" 
                            placeholder="https://orcid.org/0000-0000-0000-0000"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 shadow-2xs"
                        />
                    </div>

                    {{-- 4. LinkedIn URL --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[13px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] flex items-center gap-1.5">
                            <span>💼</span>
                            <span>LinkedIn URL</span>
                        </label>
                        <input 
                            type="url" 
                            x-model="tempSocialLinks.linkedin_url" 
                            placeholder="https://linkedin.com/in/username"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 shadow-2xs"
                        />
                    </div>

                    {{-- 5. Public Profile URL --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[13px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] flex items-center gap-1.5">
                            <span>🌐</span>
                            <span>Public Profile URL</span>
                        </label>
                        <input 
                            type="url" 
                            x-model="tempSocialLinks.public_profile" 
                            placeholder="https://example.com/yourprofile"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 shadow-2xs"
                        />
                    </div>

                    {{-- 6. YouTube URL --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[13px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] flex items-center gap-1.5">
                            <span>📺</span>
                            <span>YouTube URL</span>
                        </label>
                        <input 
                            type="url" 
                            x-model="tempSocialLinks.youtube_url" 
                            placeholder="https://youtube.com/@username"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 shadow-2xs"
                        />
                    </div>

                    {{-- 7. Employee URL --}}
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[13px] font-bold text-[#111827] dark:text-zinc-200 tracking-[-0.2px] flex items-center gap-1.5">
                            <span>🏢</span>
                            <span>Employee URL</span>
                        </label>
                        <input 
                            type="url" 
                            x-model="tempSocialLinks.employee_url" 
                            placeholder="https://company.com/employee/username"
                            class="w-full px-3.5 py-2.5 text-[13px] font-normal text-[#111827] dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 rounded-xl focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/10 transition-all placeholder:text-zinc-400 shadow-2xs"
                        />
                    </div>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center gap-2.5 pt-3">
                <button 
                    type="submit" 
                    class="px-5 py-2 rounded-lg bg-brand hover:bg-brand-600 text-white text-[14px] font-semibold shadow-xs transition-all active:scale-95 cursor-pointer"
                >
                    Save About
                </button>
                <button 
                    type="button" 
                    @click="cancelEditing()" 
                    class="px-5 py-2 rounded-lg bg-white dark:bg-zinc-800 text-[#111827] dark:text-zinc-200 text-[14px] font-semibold border border-[#d1d5db] dark:border-zinc-700 hover:bg-[#f9fafb] dark:hover:bg-zinc-700 transition-all cursor-pointer shadow-2xs"
                >
                    Cancel
                </button>
            </div>
        </form>

    </section>
</div>
