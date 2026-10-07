<?php

use App\Models\Question;
use App\Models\Skill;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Edit Question - Admin')] class extends Component {
    use WithFileUploads;

    public Question $question;

    public string $title = '';
    public string $slug = '';
    public string $description = '';
    public array $tags = [];
    public string $status = '1';
    public bool $is_closed = false;
    public mixed $thumbnailUpload = null;
    public ?string $existingThumbnail = null;

    public bool $savedSuccessfully = false;

    public function mount(Question|string $question): void
    {
        if (is_string($question)) {
            $this->question = Question::findOrFail($question);
        } else {
            $this->question = $question;
        }

        $this->title = (string) ($this->question->title ?? '');
        $this->slug = (string) ($this->question->slug ?? '');
        $this->description = (string) ($this->question->description ?? '');

        $rawTags = $this->question->tags ?? $this->question->category ?? [];
        if (is_string($rawTags)) {
            $this->tags = array_values(array_filter(array_map('trim', explode(',', $rawTags))));
        } elseif (is_array($rawTags)) {
            $this->tags = array_values(array_filter(array_map(function ($t) {
                return is_array($t) ? ($t['name'] ?? $t['label'] ?? json_encode($t)) : (string) $t;
            }, $rawTags)));
        } else {
            $this->tags = [];
        }

        $rawStatus = (string) ($this->question->status ?? '1');
        $this->status = in_array($rawStatus, ['1', 1, 'active', true], true) ? '1' : '0';
        $this->is_closed = (bool) ($this->question->is_closed ?? false);
        $this->existingThumbnail = $this->question->thumbnail ?? null;
    }

    public function searchSkills(string $query = ''): array
    {
        $query = trim($query);
        if ($query === '') {
            return Skill::query()
                ->select(['skills_title'])
                ->whereIn('skills_status', [1, '1'])
                ->limit(20)
                ->pluck('skills_title')
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $escaped = preg_quote($query, '/');

        // 1. Prefix matches using index
        $prefixMatches = Skill::query()
            ->select(['skills_title'])
            ->whereIn('skills_status', [1, '1'])
            ->where('skills_title', 'regex', new \MongoDB\BSON\Regex('^'.$escaped, 'i'))
            ->limit(15)
            ->pluck('skills_title')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // 2. If fewer than 10 prefix matches, supplement with substring matches
        if (count($prefixMatches) < 10) {
            $containsMatches = Skill::query()
                ->select(['skills_title'])
                ->whereIn('skills_status', [1, '1'])
                ->where('skills_title', 'regex', new \MongoDB\BSON\Regex($escaped, 'i'))
                ->limit(15)
                ->pluck('skills_title')
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();

            return array_values(array_unique(array_merge($prefixMatches, $containsMatches)));
        }

        return $prefixMatches;
    }

    public function generateSlug(): void
    {
        if (! empty($this->title)) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function removeThumbnailUpload(): void
    {
        $this->thumbnailUpload = null;
    }

    public function removeExistingThumbnail(): void
    {
        $this->existingThumbnail = null;
        $this->thumbnailUpload = null;
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|min:5|max:500',
            'description' => 'required|string|min:10',
            'status' => 'required|in:0,1',
            'thumbnailUpload' => 'nullable|image|max:5120',
        ]);

        $this->question->title = $this->title;

        if (! empty($this->slug)) {
            $this->question->slug = Str::slug($this->slug);
        }

        $this->question->description = $this->description;
        $this->question->tags = $this->tags;
        $this->question->status = (int) $this->status;
        $this->question->is_closed = (bool) $this->is_closed;

        // Handle thumbnail upload if provided
        if ($this->thumbnailUpload) {
            $path = $this->thumbnailUpload->store('thumbnails', 'public');
            $this->question->thumbnail = 'storage/' . $path;
            $this->existingThumbnail = 'storage/' . $path;
            $this->thumbnailUpload = null;
        } elseif ($this->existingThumbnail === null) {
            $this->question->thumbnail = null;
        }

        $this->question->save();

        $this->savedSuccessfully = true;

        session()->flash('message', 'Question updated successfully.');
    }

    public function with(): array
    {
        $availableSkills = Skill::query()
            ->select(['skills_title'])
            ->whereIn('skills_status', [1, '1'])
            ->limit(25)
            ->pluck('skills_title')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'availableSkills' => $availableSkills,
        ];
    }
}; ?>

<div class="space-y-6 max-w-5xl">
    <!-- BREADCRUMBS & TOP BAR -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('admin.questions.index') }}" wire:navigate class="font-semibold text-zinc-500 hover:text-brand transition flex items-center gap-1">
                <flux:icon name="arrow-left" class="size-3.5" />
                <span>Back to Questions</span>
            </a>
            <span class="text-zinc-300 dark:text-zinc-700">/</span>
            <span class="text-zinc-400 dark:text-zinc-500">Edit</span>
        </div>

        @if($question->slug)
            <a 
                href="{{ route('questions.show', $question->slug) }}" 
                target="_blank" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 hover:text-brand transition"
            >
                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                View on Public Site
            </a>
        @endif
    </div>

    <!-- SUCCESS MESSAGE -->
    @if(session()->has('message'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-300 flex items-center justify-between text-xs font-semibold shadow-xs">
            <div class="flex items-center gap-2.5">
                <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <a href="{{ route('admin.questions.index') }}" wire:navigate class="underline text-emerald-700 dark:text-emerald-300 font-bold hover:text-emerald-900">
                View All Questions &rarr;
            </a>
        </div>
    @endif

    <!-- MAIN EDIT CARD -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-6 sm:p-8 space-y-6">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Edit Question Details</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Modify title, description, tags, status, and attachments for this question record.</p>
        </div>

        <form wire:submit="save" class="space-y-6">
            <!-- 1. Question Title -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Question Title <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="text" 
                    wire:model="title" 
                    placeholder="Enter question title..."
                    class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                />
                @error('title') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <!-- 2. Question Slug -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        URL Slug
                    </label>
                    <input 
                        type="text" 
                        wire:model="slug" 
                        placeholder="auto-generated-slug"
                        class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-white font-mono focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
                    />
                </div>
                <div>
                    <button 
                        type="button" 
                        wire:click="generateSlug"
                        class="w-full h-10 px-3 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <flux:icon name="arrow-path" class="size-3.5" />
                        Regenerate from Title
                    </button>
                </div>
            </div>

            <!-- 3. Question Description / Details -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Question Details / Content <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    wire:model="description" 
                    rows="8" 
                    placeholder="Enter full question description..."
                    class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition resize-y"
                ></textarea>
                @error('description') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <!-- 4. Tags / Skills Section (Interactive Multi-Select with Live Skill Autocomplete) -->
            <div 
                x-data="{
                    selectedTags: @entangle('tags'),
                    search: '',
                    openDropdown: false,
                    initialSkills: @js(!empty($availableSkills) ? $availableSkills : ['HTML', 'CSS', 'JavaScript', 'React', 'Laravel', 'MongoDB', 'Python', 'TypeScript', 'PHP']),
                    remoteSkills: [],
                    isLoading: false,
                    searchTimeout: null,
                    lastQuery: '',

                    get filteredSkills() {
                        const pool = this.remoteSkills.length ? this.remoteSkills : this.initialSkills;
                        if (!this.search || !this.search.trim()) {
                            return pool.filter(s => {
                                const name = typeof s === 'object' && s !== null ? (s.name || s.label || s.skills_title || '') : String(s);
                                return name && !this.selectedTags.includes(name);
                            }).slice(0, 10);
                        }
                        const q = this.search.toLowerCase().trim();
                        return pool.filter(s => {
                            const name = typeof s === 'object' && s !== null ? (s.name || s.label || s.skills_title || '') : String(s);
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
                        const cleanTag = (typeof tag === 'object' && tag !== null ? (tag.name || tag.label || tag.skills_title || tag.title || '') : String(tag || '')).trim();
                        if (cleanTag && !this.selectedTags.includes(cleanTag)) {
                            if (this.selectedTags.length >= 10) return;
                            this.selectedTags.push(cleanTag);
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
                    }
                }"
                class="relative space-y-1.5"
                @click.outside="openDropdown = false"
                @keydown.escape.stop="openDropdown = false"
            >
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        Tags &amp; Skills
                    </label>
                    <span class="text-[11px] text-zinc-400">Search and select skills from database or add custom tags</span>
                </div>

                <!-- Tags Multi-Select Container Box -->
                <div 
                    @click="$refs.tagInput.focus()"
                    class="min-h-[48px] p-2 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl flex flex-wrap items-center gap-1.5 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20 transition-all cursor-text"
                >
                    <!-- Selected Tag Pills -->
                    <template x-for="(tag, index) in selectedTags" :key="index">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand/10 text-brand dark:bg-brand/20 border border-brand/20 rounded-lg text-xs font-semibold select-none">
                            <span x-text="typeof tag === 'object' && tag !== null ? (tag.name || tag.label || tag.skills_title || JSON.stringify(tag)) : tag"></span>
                            <button 
                                type="button" 
                                @click.stop="removeTag(index)" 
                                class="text-brand hover:text-rose-500 cursor-pointer p-0.5"
                                title="Remove tag"
                            >
                                <flux:icon name="x-mark" class="size-3.5" />
                            </button>
                        </span>
                    </template>

                    <!-- Search Input with Progress Spinner -->
                    <div class="flex-1 min-w-[160px] relative flex items-center">
                        <input 
                            x-ref="tagInput"
                            type="text" 
                            x-model="search"
                            @input="fetchRemoteSkills()"
                            @focus="openDropdown = true"
                            @click.stop="openDropdown = true"
                            @blur="setTimeout(() => { openDropdown = false }, 200)"
                            @keydown.escape.stop.prevent="openDropdown = false"
                            @keydown.tab="openDropdown = false"
                            @keydown.enter.prevent="addCustomTag()"
                            @keydown.comma.prevent="addCustomTag()"
                            @keydown.backspace="if(!search && selectedTags.length) removeTag(selectedTags.length - 1)"
                            placeholder="Type to search skills (e.g. PHP, Laravel, HTML)..." 
                            class="w-full pl-2 pr-6 py-1 bg-transparent text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none"
                        />
                        <!-- Searching Indicator Spinner -->
                        <div x-show="isLoading" class="absolute right-1 text-brand pointer-events-none" style="display: none;">
                            <svg class="animate-spin size-3.5" fill="none" viewBox="0 0 24 24">
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
                    <!-- Loading Progress in Dropdown -->
                    <div x-show="isLoading" class="px-3 py-2 text-xs font-semibold text-brand flex items-center gap-2 bg-brand/5 dark:bg-brand/10 rounded-lg mb-1 animate-pulse">
                        <svg class="animate-spin size-3.5 text-brand" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Searching skills for "<span x-text="search.trim()"></span>"...</span>
                    </div>

                    <!-- Skills Results List -->
                    <template x-for="(skill, index) in filteredSkills" :key="index">
                        <button 
                            type="button" 
                            @click.stop="addTag(typeof skill === 'object' && skill !== null ? (skill.name || skill.label || skill.skills_title || skill) : skill)"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-brand/10 hover:text-brand dark:hover:bg-brand/20 flex items-center justify-between transition-colors cursor-pointer"
                        >
                            <span x-text="typeof skill === 'object' && skill !== null ? (skill.name || skill.label || skill.skills_title || skill) : skill"></span>
                            <span class="text-[10px] text-zinc-400 font-semibold">+ Add</span>
                        </button>
                    </template>

                    <!-- No direct match notice -->
                    <div x-show="!isLoading && search && search.trim() && filteredSkills.length === 0" class="px-3 py-2 text-xs text-zinc-400 dark:text-zinc-500 italic">
                        No matching skill found in database.
                    </div>

                    <!-- Add custom tag option -->
                    <template x-if="search && search.trim() && !filteredSkills.map(s => (typeof s === 'object' && s !== null ? (s.name || s.label || s.skills_title || '') : String(s)).toLowerCase()).includes(search.toLowerCase().trim()) && !selectedTags.map(t => (typeof t === 'object' && t !== null ? (t.name || t.label || t.skills_title || '') : String(t)).toLowerCase()).includes(search.toLowerCase().trim())">
                        <button 
                            type="button" 
                            @click.stop="addCustomTag()"
                            class="w-full text-left px-3 py-2 mt-1 rounded-lg text-xs font-semibold text-brand bg-brand/5 dark:bg-brand/10 hover:bg-brand/15 flex items-center justify-between transition-colors cursor-pointer border border-dashed border-brand/30"
                        >
                            <span>Add custom tag "<span x-text="search.trim()"></span>"</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider">New Tag</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- 5. Thumbnail Image -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Thumbnail Image
                </label>

                @php
                    $previewSrc = null;
                    if ($thumbnailUpload) {
                        $previewSrc = $thumbnailUpload->temporaryUrl();
                    } elseif ($existingThumbnail) {
                        if (str_starts_with($existingThumbnail, 'http://') || str_starts_with($existingThumbnail, 'https://')) {
                            $previewSrc = $existingThumbnail;
                        } elseif (str_starts_with($existingThumbnail, 'storage/')) {
                            $previewSrc = asset($existingThumbnail);
                        } else {
                            $previewSrc = asset('storage/' . ltrim($existingThumbnail, '/'));
                        }
                    }
                @endphp

                <div class="flex flex-col sm:flex-row items-start gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                    @if($previewSrc)
                        <div class="relative size-24 rounded-xl overflow-hidden bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shrink-0 shadow-xs">
                            <img src="{{ $previewSrc }}" alt="Thumbnail Preview" class="w-full h-full object-cover" />
                        </div>
                    @else
                        <div class="size-24 rounded-xl bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 shrink-0">
                            <flux:icon name="photo" class="size-8" />
                        </div>
                    @endif

                    <div class="space-y-2 flex-1 min-w-0">
                        <input 
                            type="file" 
                            wire:model="thumbnailUpload" 
                            accept="image/*"
                            class="block w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand file:text-white hover:file:bg-brand-600 file:cursor-pointer"
                        />
                        <p class="text-[11px] text-zinc-400">Supported formats: JPG, PNG, WebP up to 5MB.</p>

                        @if($existingThumbnail || $thumbnailUpload)
                            <button 
                                type="button" 
                                wire:click="removeExistingThumbnail" 
                                class="inline-flex items-center gap-1 text-xs text-rose-500 hover:underline cursor-pointer font-medium"
                            >
                                <flux:icon name="trash" class="size-3.5" />
                                Remove image
                            </button>
                        @endif
                    </div>
                </div>
                @error('thumbnailUpload') <p class="text-rose-500 text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <!-- 6. Status & Moderation Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <!-- Status -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Question Status <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        wire:model="status" 
                        class="w-full h-10 px-3 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-semibold text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                    >
                        <option value="1">Active (1) - Published &amp; Visible</option>
                        <option value="0">Inactive (0) - Unpublished / Draft</option>
                    </select>
                </div>

                <!-- Closed Status -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Discussion State
                    </label>
                    <select 
                        wire:model="is_closed" 
                        class="w-full h-10 px-3 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs font-semibold text-zinc-900 dark:text-white focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                    >
                        <option value="0">Open for new answers &amp; replies</option>
                        <option value="1">Closed / Locked for answers</option>
                    </select>
                </div>
            </div>

            <!-- FOOTER SUBMIT ACTIONS -->
            <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <a 
                    href="{{ route('admin.questions.index') }}" 
                    wire:navigate
                    class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
                >
                    Cancel
                </a>

                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-xs font-bold bg-brand text-white hover:bg-brand-600 transition shadow-sm shadow-brand/25 cursor-pointer disabled:opacity-50"
                >
                    <flux:icon name="check" class="size-4" />
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
