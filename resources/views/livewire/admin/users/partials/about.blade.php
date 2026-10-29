{{-- resources/views/livewire/admin/users/partials/about.blade.php --}}
<!-- ABOUT, SKILLS & RESEARCH PROFILE (INDEPENDENT CARD & SEPARATE SAVING) -->
<form 
    id="about-profile-card" 
    wire:submit="saveAboutProfile" 
    x-data="{
        skills: @entangle('skills_input').live,
        research: @entangle('research_interests_input').live,
        newSkill: '',
        newResearch: '',
        skillsArray: [],
        researchArray: [],
        suggestedInterests: [],
        showResearchDropdown: false,
        searchTimer: null,
        initialSnapshot: '',
        initialData: null,
        isDirty: false,

        init() {
            this.skillsArray = this.skills ? this.skills.split(',').map(s => s.trim()).filter(s => s) : [];
            this.researchArray = this.research ? this.research.split(',').map(r => r.trim()).filter(r => r) : [];
            this.$nextTick(() => {
                this.saveInitialState();
                this.markClean();
            });
        },

        saveInitialState() {
            this.initialData = {
                skills: [...this.skillsArray],
                research: [...this.researchArray],
                about_us: this.$wire.get('about_us') || '',
                github_url: this.$wire.get('github_url') || '',
                google_scholar_url: this.$wire.get('google_scholar_url') || '',
                orcid_url: this.$wire.get('orcid_url') || '',
                linkedin_url: this.$wire.get('linkedin_url') || '',
                public_profile: this.$wire.get('public_profile') || '',
                youtube_link: this.$wire.get('youtube_link') || '',
                employee_url: this.$wire.get('employee_url') || '',
            };
        },

        cancelChanges() {
            if (!this.initialData) return;
            this.skillsArray = [...this.initialData.skills];
            this.syncSkills();
            this.researchArray = [...this.initialData.research];
            this.syncResearch();

            this.$wire.set('about_us', this.initialData.about_us);
            this.$wire.set('github_url', this.initialData.github_url);
            this.$wire.set('google_scholar_url', this.initialData.google_scholar_url);
            this.$wire.set('orcid_url', this.initialData.orcid_url);
            this.$wire.set('linkedin_url', this.initialData.linkedin_url);
            this.$wire.set('public_profile', this.initialData.public_profile);
            this.$wire.set('youtube_link', this.initialData.youtube_link);
            this.$wire.set('employee_url', this.initialData.employee_url);

            this.newSkill = '';
            this.newResearch = '';
            this.showResearchDropdown = false;
            this.$nextTick(() => {
                this.markClean();
            });
        },

        getSnapshot() {
            const form = document.getElementById('about-profile-card');
            if (!form) return '';
            const fieldValues = Array.from(form.querySelectorAll('[wire\\:model]'))
                .filter(el => !['skills_input', 'research_interests_input'].includes(el.getAttribute('wire:model')))
                .map(el => (el.getAttribute('wire:model') || '') + '=' + (el.value || '').trim())
                .join('&');
            return fieldValues + '|skills=' + [...this.skillsArray].sort().join(',') + '|research=' + [...this.researchArray].sort().join(',');
        },

        markClean() {
            this.initialSnapshot = this.getSnapshot();
            this.isDirty = false;
        },

        checkDirty() {
            this.isDirty = (this.getSnapshot() !== this.initialSnapshot);
        },

        addSkill() {
            const val = this.newSkill.trim().replace(/^,|,$/g, '');
            if (val) {
                const items = val.split(',').map(s => s.trim()).filter(Boolean);
                items.forEach(item => {
                    if (!this.skillsArray.some(s => s.toLowerCase() === item.toLowerCase())) {
                        this.skillsArray.push(item);
                    }
                });
                this.syncSkills();
                this.checkDirty();
            }
            this.newSkill = '';
        },

        removeSkill(index) {
            this.skillsArray.splice(index, 1);
            this.syncSkills();
            this.checkDirty();
        },

        syncSkills() {
            this.skills = this.skillsArray.join(', ');
        },

        fetchSuggestedInterests() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                const query = this.newResearch ? this.newResearch.trim() : '';
                const params = new URLSearchParams();
                if (query) params.append('q', query);
                this.researchArray.forEach(item => params.append('exclude[]', item));

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

        addResearch(val = null) {
            const raw = val !== null ? val : this.newResearch;
            const clean = raw.trim().replace(/^,|,$/g, '');
            if (clean) {
                const items = clean.split(',').map(s => s.trim()).filter(Boolean);
                items.forEach(item => {
                    if (!this.researchArray.some(r => r.toLowerCase() === item.toLowerCase())) {
                        this.researchArray.push(item);
                    }
                });
                this.syncResearch();
                this.checkDirty();
            }
            this.newResearch = '';
            this.showResearchDropdown = false;
        },

        removeResearch(index) {
            this.researchArray.splice(index, 1);
            this.syncResearch();
            this.checkDirty();
        },

        syncResearch() {
            this.research = this.researchArray.join(', ');
        },

        isExactMatch(name) {
            const term = (name || '').trim().toLowerCase();
            return this.suggestedInterests.some(item => (item.name || '').toLowerCase() === term);
        }
    }"
    @input="checkDirty()"
    @change="checkDirty()"
    @scroll-to-about.window="$nextTick(() => { saveInitialState(); markClean(); })"
    class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden mt-6 scroll-mt-24"
>
    <!-- CARD HEADER -->
    <div class="p-6 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="size-10 rounded-xl bg-brand/10 text-brand flex items-center justify-center font-bold">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-zinc-900 dark:text-white">
                    About, Skills &amp; Research Profile
                </h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Independent section for managing user's biography, technical skills, and research tags.
                </p>
            </div>
        </div>

        @if(session('about_message'))
            <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold border border-emerald-200 dark:border-emerald-800 animate-fadeIn">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('about_message') }}</span>
            </div>
        @endif
    </div>

    <!-- CARD BODY -->
    <div class="p-6 space-y-6">
        <!-- About Me / Bio -->
        <div data-field-wrapper>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                    About Me / Bio
                </label>
                <span class="text-[11px] text-zinc-400">Between 50 and 1500 characters</span>
            </div>
            <textarea 
                wire:model="about_us" 
                rows="5" 
                placeholder="Write a brief introduction about the user, their background, and professional journey..."
                class="w-full p-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition leading-relaxed @error('about_us') border-rose-400 @enderror"
            ></textarea>
            @error('about_us') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Skills & Expertise (Interactive Tags matching Image 2) -->
        <div data-field-wrapper>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-[14px] font-bold text-zinc-900 dark:text-zinc-200 tracking-[-0.2px]">
                    Skills &amp; Expertise
                </label>
            </div>
            <div 
                class="min-h-[50px] p-2.5 border border-[#d1d5db] dark:border-zinc-700 rounded-lg bg-white dark:bg-zinc-900 flex flex-wrap items-center gap-2 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/10 transition-all cursor-text" 
                @click="$refs.adminSkillInput.focus()"
            >
                <template x-for="(skill, index) in skillsArray" :key="index">
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
                    x-ref="adminSkillInput"
                    type="text" 
                    x-model="newSkill" 
                    @keydown.enter.prevent="addSkill()" 
                    @keydown.comma.prevent="addSkill()" 
                    placeholder="Type and press Enter, or paste comma-separated skills..." 
                    class="flex-1 min-w-[150px] border-none outline-none bg-transparent text-[13px] text-zinc-900 dark:text-white placeholder-zinc-400 px-1 py-1"
                />
            </div>
            <p class="text-xs text-[#6b7280] dark:text-zinc-400 mt-1">Type and press Enter, or paste comma-separated skills</p>
        </div>

        <!-- Research Interests (Interactive Tags with [ ✕ | tag ] matching Image 2) -->
        <div data-field-wrapper class="relative" @click.outside="showResearchDropdown = false">
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-[14px] font-bold text-zinc-900 dark:text-zinc-200 tracking-[-0.2px]">
                    Research Interests
                </label>
            </div>
            <div 
                class="min-h-[50px] max-h-[110px] overflow-y-auto p-2 border border-[#60a5fa] dark:border-blue-500/40 rounded-lg bg-white dark:bg-zinc-900 flex flex-wrap items-center gap-2 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/10 transition-all cursor-text scrollbar-thin" 
                @click="$refs.adminResearchInput.focus()"
            >
                <template x-for="(interest, index) in researchArray" :key="index">
                    <span class="inline-flex items-stretch rounded-[5px] border border-[#38bdf8] bg-white dark:bg-zinc-800 text-[13px] overflow-hidden shadow-2xs hover:border-[#0284c7] transition-colors">
                        <button 
                            type="button" 
                            @click.stop="removeResearch(index)" 
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
                    x-ref="adminResearchInput"
                    type="text" 
                    x-model="newResearch" 
                    @focus="showResearchDropdown = true; fetchSuggestedInterests()"
                    @input="showResearchDropdown = true; fetchSuggestedInterests()"
                    @keydown.enter.prevent="addResearch()" 
                    @keydown.comma.prevent="addResearch()" 
                    placeholder="Search and select research interests..." 
                    class="flex-1 min-w-[150px] border-none outline-none bg-transparent text-[13px] text-zinc-900 dark:text-white placeholder-zinc-400 px-1 py-1"
                />
            </div>

            {{-- Autocomplete dropdown --}}
            <div 
                x-show="showResearchDropdown && (suggestedInterests.length > 0 || (newResearch.trim() !== '' && !researchArray.includes(newResearch.trim())))" 
                x-cloak
                class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-lg z-50 overflow-hidden max-h-56 overflow-y-auto"
            >
                <template x-for="item in suggestedInterests" :key="item.id">
                    <div 
                        @click="addResearch(item.name)" 
                        class="px-3.5 py-2 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-800/80 hover:text-brand cursor-pointer flex items-center justify-between transition-colors border-b border-zinc-100 dark:border-zinc-800/50 last:border-b-0"
                    >
                        <span x-text="item.name"></span>
                        <span class="text-[10px] text-zinc-400">Select</span>
                    </div>
                </template>

                <template x-if="newResearch.trim() !== '' && !researchArray.includes(newResearch.trim()) && !isExactMatch(newResearch)">
                    <div 
                        @click="addResearch(newResearch)" 
                        class="px-3.5 py-2.5 text-xs font-semibold text-brand hover:bg-blue-50/70 dark:hover:bg-blue-900/30 cursor-pointer flex items-center gap-2 border-t border-zinc-100 dark:border-zinc-800 transition-colors"
                    >
                        <svg class="size-4 shrink-0 text-brand" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                        </svg>
                        <span>+ Add "<span class="font-bold" x-text="newResearch.trim()"></span>" as New Interest</span>
                    </div>
                </template>
            </div>

            <p class="text-xs text-[#6b7280] dark:text-zinc-400 mt-1">Search and select research interests</p>
        </div>

        <!-- Social Links (Optional) -->
        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">
                    Social &amp; Profile Links (Optional)
                </h3>
                <span class="text-[11px] text-zinc-400">Stored directly in reviewer_profile table</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- 1. GitHub URL --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center gap-1.5">
                        <span>🔗</span>
                        <span>GitHub URL</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="github_url" 
                        placeholder="https://github.com/username"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('github_url') border-rose-400 @enderror"
                    />
                    @error('github_url') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- 2. Google Scholar URL --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center gap-1.5">
                        <span>🎓</span>
                        <span>Google Scholar URL</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="google_scholar_url" 
                        placeholder="https://scholar.google.com/citations?user=..."
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('google_scholar_url') border-rose-400 @enderror"
                    />
                    @error('google_scholar_url') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- 3. ORCID URL --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center gap-1.5">
                        <span>🔬</span>
                        <span>ORCID URL</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="orcid_url" 
                        placeholder="https://orcid.org/0000-0000-0000-0000"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('orcid_url') border-rose-400 @enderror"
                    />
                    @error('orcid_url') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- 4. LinkedIn URL --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center gap-1.5">
                        <span>💼</span>
                        <span>LinkedIn URL</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="linkedin_url" 
                        placeholder="https://linkedin.com/in/username"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('linkedin_url') border-rose-400 @enderror"
                    />
                    @error('linkedin_url') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- 5. Public Profile URL --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center gap-1.5">
                        <span>🌐</span>
                        <span>Public Profile URL</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="public_profile" 
                        placeholder="https://example.com/yourprofile"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('public_profile') border-rose-400 @enderror"
                    />
                    @error('public_profile') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- 6. YouTube URL --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center gap-1.5">
                        <span>📺</span>
                        <span>YouTube URL</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="youtube_link" 
                        placeholder="https://youtube.com/@username"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('youtube_link') border-rose-400 @enderror"
                    />
                    @error('youtube_link') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- 7. Employee URL (Job Profile) --}}
                <div>
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center gap-1.5">
                        <span>🏢</span>
                        <span>Employee URL (Job Profile)</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model="employee_url" 
                        placeholder="https://company.com/employee/username"
                        class="w-full h-10 px-3.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition @error('employee_url') border-rose-400 @enderror"
                    />
                    @error('employee_url') <p class="text-[11px] text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- CARD FOOTER WITH INDEPENDENT SAVE & CANCEL BUTTONS -->
    <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-900/40 border-t border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <span class="text-xs text-zinc-400">
            Saves independently to the user's reviewer &amp; scholar profile.
        </span>

        <div class="flex items-center gap-3">
            <button 
                type="button" 
                @click="cancelChanges()"
                :disabled="!isDirty"
                :class="!isDirty ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer active:scale-[0.98]'"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 transition shadow-xs"
            >
                <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Cancel</span>
            </button>

            <button 
                type="submit" 
                :disabled="!isDirty"
                :class="!isDirty ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'hover:bg-brand-600 active:scale-[0.98] shadow-md shadow-brand/20 cursor-pointer'"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-brand transition"
            >
                <svg wire:loading.remove wire:target="saveAboutProfile" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="saveAboutProfile" class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="saveAboutProfile">Save About, Skills &amp; Links</span>
                <span wire:loading wire:target="saveAboutProfile">Saving...</span>
            </button>
        </div>
    </div>
</form>
