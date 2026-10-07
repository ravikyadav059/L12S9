@props([
    'show' => 'showEditModal',
    'editingQuestionId' => 'editingQuestionId',
    'title' => 'editTitle',
    'description' => 'editDescription',
    'tags' => 'editTags',
    'thumbnail' => 'editThumbnail',
    'existingThumbnail' => 'existingThumbnail',
    'submitAction' => 'saveEdit',
    'closeAction' => '$set(\'showEditModal\', false)',
    'availableSkills' => [],
])

<!-- ================= EDIT QUESTION POPUP MODAL (ALPINE.JS DRIVEN & CLIENT PRE-VALIDATED) ================= -->
<div 
    x-data="{
        show: @entangle($show),
        editingId: '',
        formTitle: '',
        existingImage: null,
        clientErrors: {
            title: '',
            description: '',
            tags: '',
            thumbnail: ''
        },

        // Tags multi-select state
        selectedTags: [],
        search: '',
        openDropdown: false,
        initialSkills: @js(!empty($availableSkills) ? $availableSkills : ['HTML', 'CSS', 'JavaScript', 'React', 'Laravel', 'MongoDB', 'Python', 'TypeScript', 'Docker', 'PHP', 'Machine Learning', 'Data Analysis (STATA)']),
        remoteSkills: [],
        isLoading: false,
        searchTimeout: null,
        lastQuery: '',

        // Attachments state
        hasNewFile: false,
        fileName: '',
        fileSize: '',
        previewUrl: null,
        clientError: '',
        isUploading: false,
        progress: 0,

        get filteredSkills() {
            const pool = this.remoteSkills.length ? this.remoteSkills : this.initialSkills;
            if (!this.search || !this.search.trim()) {
                return pool.filter(s => {
                    const name = typeof s === 'object' && s !== null ? (s.name || s.label || '') : String(s);
                    return name && !this.selectedTags.includes(name);
                }).slice(0, 10);
            }
            const q = this.search.toLowerCase().trim();
            return pool.filter(s => {
                const name = typeof s === 'object' && s !== null ? (s.name || s.label || '') : String(s);
                return name && name.toLowerCase().includes(q) && !this.selectedTags.includes(name);
            }).slice(0, 10);
        },

        fetchRemoteSkills() {
            clearTimeout(this.searchTimeout);
            const q = (this.search || '').trim();
            if (!q) {
                this.remoteSkills = [];
                this.isLoading = false;
                return;
            }
            this.lastQuery = q;
            this.isLoading = true;
            this.openDropdown = true;

            this.searchTimeout = setTimeout(async () => {
                try {
                    if (this.$wire && typeof this.$wire.searchSkills === 'function') {
                        const res = await this.$wire.searchSkills(q);
                        if (this.lastQuery === q && Array.isArray(res)) {
                            this.remoteSkills = res;
                        }
                    }
                } catch (e) {
                    // Ignore search errors
                } finally {
                    if (this.lastQuery === q) {
                        this.isLoading = false;
                    }
                }
            }, 50);
        },

        addTag(tag) {
            const cleanTag = (typeof tag === 'object' && tag !== null ? (tag.name || tag.label || tag.title || '') : String(tag || '')).trim();
            if (cleanTag && !this.selectedTags.includes(cleanTag)) {
                if (this.selectedTags.length >= 8) return;
                this.selectedTags.push(cleanTag);
                this.clientErrors.tags = '';
                this.syncTagsToLivewire();
            }
            this.search = '';
            this.remoteSkills = [];
            this.isLoading = false;
            this.openDropdown = false;
            this.$nextTick(() => {
                if (this.$refs.tagInput) {
                    this.$refs.tagInput.focus();
                }
            });
        },

        removeTag(index) {
            this.selectedTags.splice(index, 1);
            this.syncTagsToLivewire();
            this.$nextTick(() => {
                if (this.$refs.tagInput) {
                    this.$refs.tagInput.focus();
                }
            });
        },

        addCustomTag() {
            if (this.search && this.search.trim()) {
                this.addTag(this.search.trim());
            }
        },

        syncTagsToLivewire() {
            const tagStr = this.selectedTags.join(', ');
            if (this.$wire) {
                this.$wire.set('{{ $tags }}', tagStr, false);
            }
        },

        handleFile(e) {
            this.clientError = '';
            this.clientErrors.thumbnail = '';
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            // 1. Validate file size (max 5MB)
            const maxSizeBytes = 5 * 1024 * 1024;
            if (file.size > maxSizeBytes) {
                const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
                this.clientError = 'File size (' + sizeMB + 'MB) exceeds the 5MB limit. Please choose a smaller image.';
                this.clientErrors.thumbnail = this.clientError;
                this.removeNewFile();
                return;
            }

            // 2. Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jfif', 'image/jpg'];
            const ext = file.name.split('.').pop().toLowerCase();
            const allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
            if (!allowedTypes.includes(file.type) && !allowedExts.includes(ext)) {
                this.clientError = 'Unsupported format. Please upload JPG, PNG, WebP, or JFIF.';
                this.clientErrors.thumbnail = this.clientError;
                this.removeNewFile();
                return;
            }

            // 3. Set preview and file details
            this.fileName = file.name;
            this.fileSize = file.size < 1024 * 1024 
                ? Math.round(file.size / 1024) + ' KB' 
                : (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            this.previewUrl = URL.createObjectURL(file);
            this.hasNewFile = true;
            this.isUploading = true;
            this.progress = 0;

            // 4. Upload to Livewire
            if (this.$wire && typeof this.$wire.upload === 'function') {
                this.$wire.upload('{{ $thumbnail }}', file,
                    () => {
                        this.isUploading = false;
                        this.progress = 100;
                    },
                    (err) => {
                        this.isUploading = false;
                        this.clientError = 'Upload error. Please choose a smaller image.';
                        this.clientErrors.thumbnail = this.clientError;
                    },
                    (event) => {
                        this.progress = event.detail.progress;
                    }
                );
            } else {
                this.isUploading = false;
            }
        },

        removeNewFile() {
            this.hasNewFile = false;
            this.fileName = '';
            this.fileSize = '';
            this.isUploading = false;
            this.progress = 0;
            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = null;
            }
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
            if (this.$wire) {
                this.$wire.set('{{ $thumbnail }}', null, false);
            }
        },

        removeExistingImage() {
            this.existingImage = null;
            if (this.$wire) {
                this.$wire.set('{{ $existingThumbnail }}', null, false);
            }
        },

        async checkTitleUniqueness() {
            const cleanTitle = (this.formTitle || '').trim();
            if (!cleanTitle || cleanTitle.length < 5) return true;

            try {
                if (this.$wire && typeof this.$wire.checkTitleAvailable === 'function') {
                    const isUnique = await this.$wire.checkTitleAvailable(cleanTitle, this.editingId || null);
                    if (!isUnique) {
                        this.clientErrors.title = 'A question with this title already exists. Please make your title unique.';
                        return false;
                    } else if (this.clientErrors.title === 'A question with this title already exists. Please make your title unique.') {
                        this.clientErrors.title = '';
                    }
                }
            } catch (e) {
                // Ignore search error
            }
            return true;
        },

        async validateAndSubmit() {
            this.clientErrors = { title: '', description: '', tags: '', thumbnail: '' };
            let hasError = false;

            // 1. Validate Question Title
            const cleanTitle = (this.formTitle || '').trim();
            if (!cleanTitle) {
                this.clientErrors.title = 'The question title is required.';
                hasError = true;
            } else if (cleanTitle.length < 5) {
                this.clientErrors.title = 'The question title must be at least 5 characters.';
                hasError = true;
            }

            // Client-side Title Uniqueness Pre-Check (excluding current editing question ID)
            if (!hasError && this.$wire && typeof this.$wire.checkTitleAvailable === 'function') {
                const isUnique = await this.$wire.checkTitleAvailable(cleanTitle, this.editingId || null);
                if (!isUnique) {
                    this.clientErrors.title = 'A question with this title already exists. Please make your title unique.';
                    hasError = true;
                }
            }

            // 2. Validate Details (Textarea content)
            const desc = (this.$wire && this.$wire.get('{{ $description }}')) || '';
            const plainDesc = (desc || '').trim();
            if (!plainDesc) {
                this.clientErrors.description = 'The question details are required.';
                hasError = true;
            } else if (plainDesc.length < 20) {
                this.clientErrors.description = 'Please provide more details (minimum 20 characters). Currently: ' + plainDesc.length + ' chars.';
                hasError = true;
            }

            // 3. Validate Tags
            if (!this.selectedTags || this.selectedTags.length === 0) {
                this.clientErrors.tags = 'Please select or add at least one tag.';
                hasError = true;
            }

            // 4. Validate Attachment error
            if (this.clientError) {
                this.clientErrors.thumbnail = this.clientError;
                hasError = true;
            }
            if (this.isUploading) {
                this.clientErrors.thumbnail = 'Please wait for the image upload to finish.';
                hasError = true;
            }

            // STOP immediately if validation fails — display errors in UI
            if (hasError) {
                return;
            }

            // Check authentication
            const isAuthenticated = @js(Auth::check());
            if (!isAuthenticated) {
                this.show = false;
                window.dispatchEvent(new CustomEvent('open-auth-alert', { detail: { title: 'Connect to Scholar9 to Edit a Question' } }));
                return;
            }

            // Pre-validation PASSED! Sync data non-blockingly and trigger submit action
            if (this.$wire) {
                this.$wire.set('{{ $title }}', cleanTitle, false);
                this.$wire.set('{{ $tags }}', this.selectedTags.join(', '), false);
                this.$wire.call('{{ $submitAction }}');
            }
        },

        populateFromData(data) {
            if (!data) return;
            const payload = Array.isArray(data) ? data[0] : data;
            if (!payload) return;

            this.editingId = payload.id || '';
            this.formTitle = payload.title || '';
            
            const rawTags = payload.tags || '';
            if (typeof rawTags === 'string') {
                this.selectedTags = rawTags.split(',').map(t => t.trim()).filter(Boolean);
            } else if (Array.isArray(rawTags)) {
                this.selectedTags = rawTags.map(t => typeof t === 'object' && t !== null ? (t.name || t.label || t.title || JSON.stringify(t)) : String(t).trim()).filter(Boolean);
            } else {
                this.selectedTags = [];
            }

            if (payload.description !== undefined && this.$wire) {
                this.$wire.set('{{ $description }}', payload.description, false);
            }

            this.existingImage = payload.thumbnail || null;
            this.hasNewFile = false;
            this.fileName = '';
            this.fileSize = '';
            this.clientError = '';
            this.clientErrors = { title: '', description: '', tags: '', thumbnail: '' };
            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = null;
            }
        },

        syncFromWire() {
            this.formTitle = (this.$wire && this.$wire.get('{{ $title }}')) || '';
            const initialTags = (this.$wire && this.$wire.get('{{ $tags }}')) || '';
            if (initialTags && typeof initialTags === 'string') {
                this.selectedTags = initialTags.split(',').map(t => t.trim()).filter(Boolean);
            } else if (Array.isArray(initialTags)) {
                this.selectedTags = initialTags.map(t => typeof t === 'object' && t !== null ? (t.name || t.label || t.title || JSON.stringify(t)) : String(t).trim()).filter(Boolean);
            } else {
                this.selectedTags = [];
            }
            this.editingId = (this.$wire && this.$wire.get('{{ $editingQuestionId }}')) || '';
            this.existingImage = (this.$wire && this.$wire.get('{{ $existingThumbnail }}')) || null;
            this.clientErrors = { title: '', description: '', tags: '', thumbnail: '' };
        },

        init() {
            this.syncFromWire();

            this.$watch('show', (val) => {
                if (val) {
                    this.syncFromWire();
                } else {
                    this.removeNewFile();
                }
            });
        }
    }"
    x-show="show"
    x-cloak
    @populate-edit-modal.window="populateFromData($event.detail)"
    @open-edit-modal.window="show = true"
    @close-edit-modal.window="show = false"
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="show = false"
>
    <!-- Backdrop with blur -->
    <div 
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs"
        @click="show = false"
    ></div>

    <!-- Modal Box Container -->
    <div class="min-h-screen px-4 text-center flex items-center justify-center p-4">
        <div 
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
            class="inline-block w-full max-w-2xl text-left align-middle bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 transform transition-all relative z-10 overflow-hidden my-8"
            @click.stop
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 flex items-center justify-center text-[#198BEA] shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                            Edit Question
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Update your question details and keep information accurate
                        </p>
                    </div>
                </div>
                <button 
                    type="button" 
                    @click="show = false" 
                    class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Form with Client-Side Pre-Validation -->
            <form @submit.prevent="validateAndSubmit()" class="p-6 space-y-5">
                
                <!-- 1. Title Section -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Question Title <span class="text-rose-500">*</span>
                    </label>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-2 leading-relaxed">
                        Be specific and imagine you’re asking a question to another person
                    </p>
                    <input 
                        type="text" 
                        x-model="formTitle"
                        @input="clientErrors.title = ''; if ($wire) $wire.set('{{ $title }}', formTitle, false)"
                        @blur="checkTitleUniqueness()"
                        placeholder="Add Question here" 
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all"
                    />
                    <p x-show="clientErrors.title" x-text="clientErrors.title" x-cloak style="display: none;" class="text-rose-500 text-xs mt-1 font-medium"></p>
                    <div x-show="!clientErrors.title">
                        @error($title) <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- 2. Details Section -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            Details <span class="text-rose-500">*</span>
                        </label>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-2.5 leading-relaxed">
                        Introduce the problem and expand on what you put in the title. Minimum 20 characters.
                    </p>

                    <textarea 
                        wire:model="{{ $description }}"
                        @input="clientErrors.description = ''"
                        rows="6"
                        placeholder="Describe your question in detail..." 
                        class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 text-zinc-900 dark:text-zinc-100 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all resize-y min-h-[140px]"
                    ></textarea>
                    <p x-show="clientErrors.description" x-text="clientErrors.description" x-cloak style="display: none;" class="text-rose-500 text-xs mt-1 font-medium"></p>
                    <div x-show="!clientErrors.description">
                        @error($description) <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- 3. Tags Section (Multi-select Pills with Autocomplete from Skills) -->
                <div 
                    wire:ignore
                    class="relative"
                    @click.outside="openDropdown = false"
                    @keydown.escape.stop="openDropdown = false"
                >
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Tags <span class="text-rose-500">*</span>
                    </label>

                    <!-- Tags Multi-Select Container Box -->
                    <div 
                        @click="$refs.tagInput.focus()"
                        class="min-h-[46px] p-2 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl flex flex-wrap items-center gap-1.5 focus-within:border-[#198BEA] focus-within:ring-2 focus-within:ring-[#198BEA]/15 transition-all cursor-text"
                    >
                        <!-- Selected Tag Pills -->
                        <template x-for="(tag, index) in selectedTags" :key="index">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 border border-sky-200 dark:border-sky-800/60 rounded-lg text-xs font-semibold select-none">
                                <span x-text="typeof tag === 'object' && tag !== null ? (tag.name || tag.label || JSON.stringify(tag)) : tag"></span>
                                <button 
                                    type="button" 
                                    @click.stop="removeTag(index)" 
                                    class="text-[#198BEA] hover:text-rose-500 dark:text-sky-300 dark:hover:text-rose-400 cursor-pointer p-0.5"
                                    title="Remove tag"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </template>

                        <!-- Search Input with Progress Spinner -->
                        <div class="flex-1 min-w-[140px] relative flex items-center">
                            <input 
                                x-ref="tagInput"
                                type="text" 
                                x-model="search"
                                @input="fetchRemoteSkills(); clientErrors.tags = ''"
                                @focus="openDropdown = true"
                                @click.stop="openDropdown = true"
                                @blur="setTimeout(() => { openDropdown = false }, 200)"
                                @keydown.escape.stop.prevent="openDropdown = false"
                                @keydown.tab="openDropdown = false"
                                @keydown.enter.prevent="addCustomTag()"
                                @keydown.comma.prevent="addCustomTag()"
                                @keydown.backspace="if(!search && selectedTags.length) removeTag(selectedTags.length - 1)"
                                placeholder="Search and select tags" 
                                class="w-full pl-2 pr-6 py-1 bg-transparent text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-hidden"
                            />
                            <!-- Searching Indicator Spinner in Input -->
                            <div x-show="isLoading" class="absolute right-1 text-[#198BEA] pointer-events-none" style="display: none;">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Autocomplete Dropdown List -->
                    <div 
                        x-show="openDropdown && (filteredSkills.length > 0 || isLoading || (search && search.trim().length > 0))"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        @click.outside="openDropdown = false"
                        class="absolute left-0 right-0 top-full mt-1 z-30 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl max-h-60 overflow-y-auto p-1.5"
                        style="display: none;"
                    >
                        <!-- Loading Progress Row in Dropdown -->
                        <div x-show="isLoading" class="px-3 py-2 text-xs font-semibold text-[#198BEA] flex items-center gap-2 bg-[#eaf5ff] dark:bg-sky-950/40 rounded-lg mb-1 animate-pulse">
                            <svg class="animate-spin w-3.5 h-3.5 text-[#198BEA]" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Searching skills for "<span x-text="search.trim()"></span>"...</span>
                        </div>

                        <!-- Results List -->
                        <template x-for="(skill, index) in filteredSkills" :key="index">
                            <button 
                                type="button" 
                                @click.stop="addTag(typeof skill === 'object' && skill !== null ? (skill.name || skill.label || skill) : skill)"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-[#eaf5ff] hover:text-[#198BEA] dark:hover:bg-sky-950/50 dark:hover:text-sky-300 flex items-center justify-between transition-colors cursor-pointer"
                            >
                                <span x-text="typeof skill === 'object' && skill !== null ? (skill.name || skill.label || skill) : skill"></span>
                                <span class="text-[10px] text-zinc-400 font-semibold">+ Add</span>
                            </button>
                        </template>

                        <!-- No direct match notice if list is empty and not loading -->
                        <div x-show="!isLoading && search && search.trim() && filteredSkills.length === 0" class="px-3 py-2 text-xs text-zinc-400 dark:text-zinc-500 italic">
                            No matching skill found in database.
                        </div>

                        <!-- If searching and search term is not in filtered list, allow adding custom tag directly -->
                        <template x-if="search && search.trim() && !filteredSkills.map(s => (typeof s === 'object' && s !== null ? (s.name || s.label || '') : String(s)).toLowerCase()).includes(search.toLowerCase().trim()) && !selectedTags.map(t => (typeof t === 'object' && t !== null ? (t.name || t.label || '') : String(t)).toLowerCase()).includes(search.toLowerCase().trim())">
                            <button 
                                type="button" 
                                @click.stop="addCustomTag()"
                                class="w-full text-left px-3 py-2 mt-1 rounded-lg text-xs font-semibold text-[#198BEA] bg-sky-50 dark:bg-sky-950/40 hover:bg-sky-100 dark:hover:bg-sky-900/50 flex items-center justify-between transition-colors cursor-pointer border border-dashed border-sky-300 dark:border-sky-800"
                            >
                                <span>Add "<span x-text="search.trim()"></span>"</span>
                                <span class="text-[10px] font-bold uppercase tracking-wider">New Tag</span>
                            </button>
                        </template>
                    </div>

                    <p x-show="clientErrors.tags" x-text="clientErrors.tags" x-cloak style="display: none;" class="text-rose-500 text-xs mt-1 font-medium"></p>
                    <div x-show="!clientErrors.tags">
                        @error($tags) <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- 4. Attachments Upload Box -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            Attachments
                        </label>
                        <span x-show="hasNewFile || existingImage" x-cloak style="display: none;" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span x-text="hasNewFile ? 'New Image Selected' : 'Existing Image Attached'"></span>
                        </span>
                    </div>

                    <!-- Empty / Change Upload Dropzone (shown when no new file or existing image) -->
                    <div 
                        x-show="!hasNewFile && !existingImage"
                        class="relative border-2 border-dashed border-zinc-200 dark:border-zinc-700 hover:border-[#198BEA]/60 dark:hover:border-[#198BEA]/60 rounded-2xl p-4 text-center transition-all bg-zinc-50/50 dark:bg-zinc-800/30 cursor-pointer group"
                    >
                        <input 
                            x-ref="fileInput"
                            type="file" 
                            accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp"
                            @change="handleFile($event)"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                        />
                        <div class="flex flex-col items-center justify-center pointer-events-none">
                            <div class="w-10 h-10 rounded-full bg-sky-50 dark:bg-sky-950/60 text-[#198BEA] flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                Drag &amp; drop an image, or <span class="text-[#198BEA] font-bold underline decoration-sky-300 underline-offset-2">browse</span>
                            </p>
                            <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-0.5">Supports JPG, PNG, WebP, JFIF up to 5MB</p>
                        </div>
                    </div>

                    <!-- Existing Image Card (when loaded from database and not replaced yet) -->
                    <div 
                        x-show="!hasNewFile && existingImage" 
                        x-cloak 
                        style="display: none;"
                        class="p-3 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-2xl flex items-center justify-between gap-3 shadow-xs"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <img :src="existingImage ? (existingImage.startsWith('http') ? existingImage : '/storage/' + existingImage) : ''" alt="Existing Attachment" class="w-12 h-12 rounded-xl object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 shadow-xs" />
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-zinc-900 dark:text-white truncate">Current Attachment</p>
                                <label class="text-[11px] text-[#198BEA] hover:underline cursor-pointer font-medium relative">
                                    Change image
                                    <input 
                                        type="file" 
                                        accept=".jpg,.jpeg,.png,.webp,.jfif,image/jpeg,image/png,image/webp"
                                        @change="handleFile($event)"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                    />
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button 
                                type="button" 
                                @click="removeExistingImage()" 
                                class="p-2 text-zinc-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-xl transition-colors cursor-pointer"
                                title="Remove existing image"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Newly Uploaded File Preview Card -->
                    <div 
                        x-show="hasNewFile" 
                        x-cloak 
                        style="display: none;"
                        class="p-3 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-2xl flex items-center justify-between gap-3 shadow-xs"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Image Thumbnail Preview -->
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="New Preview" class="w-12 h-12 rounded-xl object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 shadow-xs" />
                            </template>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-zinc-900 dark:text-white truncate" x-text="fileName"></p>
                                <p class="text-[11px] text-zinc-400 dark:text-zinc-500" x-text="fileSize"></p>
                            </div>
                        </div>

                        <!-- Action buttons: Loading & Remove -->
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Livewire Upload Spinner -->
                            <div x-show="isUploading" class="flex items-center gap-1.5 text-xs text-[#198BEA] font-medium pr-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>Uploading...</span>
                            </div>

                            <button 
                                type="button" 
                                @click="removeNewFile()" 
                                class="p-2 text-zinc-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-xl transition-colors cursor-pointer"
                                title="Remove new image"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Client-Side Error Notice -->
                    <div x-show="clientErrors.thumbnail" x-cloak style="display: none;">
                        <p class="text-rose-500 text-xs font-medium flex items-center gap-1.5 mt-1" x-text="clientErrors.thumbnail"></p>
                    </div>

                    <!-- Server Error Notice -->
                    <div x-show="!clientErrors.thumbnail">
                        @error($thumbnail) <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- 5. Footer Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button 
                        type="button" 
                        @click="show = false" 
                        class="px-5 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-zinc-900 dark:hover:text-white hover:border-zinc-300 dark:hover:border-zinc-600 rounded-xl transition-all cursor-pointer shadow-xs"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98 disabled:opacity-60"
                    >
                        <svg wire:loading wire:target="{{ $submitAction }}" class="animate-spin h-4 w-4 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="{{ $submitAction }}">Update Question</span>
                        <span wire:loading wire:target="{{ $submitAction }}">Updating...</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
