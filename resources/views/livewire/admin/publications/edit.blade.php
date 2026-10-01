<?php

use App\Models\Publication;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.admin')] #[Title('Edit Publication - Admin')] class extends Component {
    use WithFileUploads;

    public Publication $publication;

    public string $title = '';
    public string $slug = '';
    public array $publication_keywords = [];
    public string $newKeyword = '';
    public string $status = '1';
    public string $serial_number = '';
    public string $journal_title = '';
    public string $journal_name = '';
    public string $doi = '';
    public string $published_date = '';
    public string $article_type = '';
    public string $volume = '';
    public string $issue = '';
    public string $page_no = '';
    public string $issn = '';
    public string $citations = '';
    public string $url = '';
    public string $description = '';
    public mixed $fileUpload = null;
    public ?string $existingImage = null;

    public bool $savedSuccessfully = false;

    public function mount(Publication|string $publication): void
    {
        if (is_string($publication)) {
            $this->publication = Publication::findOrFail($publication);
        } else {
            $this->publication = $publication;
        }

        $this->title = (string) ($this->publication->title ?? '');
        $this->slug = (string) ($this->publication->slug ?? '');

        $keywords = $this->publication->publication_keywords ?? $this->publication->keywords ?? [];
        if (is_string($keywords)) {
            $this->publication_keywords = array_filter(array_map('trim', explode(',', $keywords)));
        } elseif (is_array($keywords)) {
            $this->publication_keywords = array_values(array_filter($keywords));
        } else {
            $this->publication_keywords = [];
        }

        $rawStatus = (string) ($this->publication->status ?? '1');
        $this->status = in_array($rawStatus, ['1', 1, 'published', 'active', true], true) ? '1' : '0';
        $this->serial_number = (string) ($this->publication->serial_number ?? '');
        $this->journal_title = (string) ($this->publication->journal_title ?? $this->publication->journal_name ?? '');
        $this->journal_name = (string) ($this->publication->journal_name ?? $this->publication->journal_title ?? '');
        $this->doi = (string) ($this->publication->doi ?? '');
        $this->published_date = (string) ($this->publication->published_date ?? $this->publication->publication_month_year ?? '');
        $this->article_type = (string) ($this->publication->article_type ?? '');
        $this->volume = (string) ($this->publication->volume ?? '');
        $this->issue = (string) ($this->publication->issue ?? '');
        $this->page_no = (string) ($this->publication->page_no ?? '');
        $this->issn = (string) ($this->publication->issn ?? '');
        $this->citations = (string) ($this->publication->citations ?? '');
        $this->url = (string) ($this->publication->url ?? '');
        $this->description = (string) ($this->publication->description ?? $this->publication->abstract ?? '');
        $this->existingImage = $this->publication->image ?? $this->publication->file_path ?? null;
    }

    public function addKeyword(): void
    {
        $trimmed = trim($this->newKeyword);
        if ($trimmed !== '' && ! in_array($trimmed, $this->publication_keywords)) {
            $this->publication_keywords[] = $trimmed;
            $this->newKeyword = '';
        }
    }

    public function removeKeyword(int $index): void
    {
        if (isset($this->publication_keywords[$index])) {
            array_splice($this->publication_keywords, $index, 1);
        }
    }

    public function removeFileUpload(): void
    {
        $this->fileUpload = null;
    }

    public function removeExistingFile(): void
    {
        $this->existingImage = null;
        $this->fileUpload = null;
        $this->publication->image = null;
        $this->publication->file_path = null;
        $this->publication->save();
        $this->savedSuccessfully = true;
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:1000',
            'slug' => 'nullable|string|max:500',
            'publication_keywords' => 'nullable|array',
            'status' => 'required|in:1,0',
            'serial_number' => 'nullable|string|max:100',
            'journal_title' => 'nullable|string|max:500',
            'journal_name' => 'nullable|string|max:500',
            'doi' => 'nullable|string|max:255',
            'published_date' => 'nullable|string|max:100',
            'article_type' => 'nullable|in:published,pre-print',
            'volume' => 'nullable|string|max:100',
            'issue' => 'nullable|string|max:100',
            'page_no' => 'nullable|string|max:100',
            'issn' => 'nullable|string|max:100',
            'citations' => 'nullable|numeric',
            'url' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'fileUpload' => 'nullable|file|mimes:pdf,doc,docx|max:20480',
        ]);

        if ($this->fileUpload) {
            $uploadedPath = $this->fileUpload->store('publications', 'public');
            $this->publication->image = $uploadedPath;
            $this->publication->file_path = $uploadedPath;
            $this->existingImage = $uploadedPath;
        }

        $this->publication->title = $this->title;
        $this->publication->slug = $this->slug ?: Str::slug($this->title);
        $this->publication->publication_keywords = array_values($this->publication_keywords);
        $this->publication->status = (int) $this->status;
        $this->publication->serial_number = $this->serial_number;
        $this->publication->journal_title = $this->journal_title;
        $this->publication->journal_name = $this->journal_name ?: $this->journal_title;
        $this->publication->doi = $this->doi;
        $this->publication->published_date = $this->published_date;
        $this->publication->article_type = $this->article_type;
        $this->publication->volume = $this->volume;
        $this->publication->issue = $this->issue;
        $this->publication->page_no = $this->page_no;
        $this->publication->issn = $this->issn;
        $this->publication->citations = $this->citations !== '' ? (int) $this->citations : null;
        $this->publication->url = $this->url;
        $this->publication->description = $this->description;
        $this->publication->abstract = $this->description;
        $this->publication->save();

        $this->savedSuccessfully = true;
    }
}; ?>

<div class="max-w-5xl mx-auto space-y-6 pb-16">
    
    <!-- Header Section -->
    <div class="flex items-center justify-between">
        <div>
            <a 
                href="{{ route('admin.publications.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-brand dark:text-zinc-400 dark:hover:text-brand transition mb-1"
            >
                <flux:icon name="arrow-left" class="size-3.5" />
                Back to Publications
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight">
                    Edit Publication
                </h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $status === '1' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }}">
                    {{ $status === '1' ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Success Feedback Alert -->
    @if($savedSuccessfully)
        <div 
            x-data="{ show: true }" 
            x-show="show" 
            x-init="setTimeout(() => show = false, 5000)"
            class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-emerald-800 dark:text-emerald-300 text-sm font-medium flex items-center justify-between"
        >
            <div class="flex items-center gap-2.5">
                <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                <span>Publication record updated successfully!</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-700 dark:text-emerald-300 hover:opacity-75 cursor-pointer">
                <flux:icon name="x-mark" class="size-4" />
            </button>
        </div>
    @endif

    <!-- Single Unified Edit Form Card -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-8">
        <form id="edit-publication-form" wire:submit.prevent="save" class="space-y-8">
            
            <!-- SECTION 1: Paper Title, Slug & Abstract -->
            <div class="space-y-5">
                <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                        1. Paper Information
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Core publication title, slug, description, and keyword tags.
                    </p>
                </div>

                <!-- Paper Title -->
                <div class="space-y-1.5">
                    <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        Paper Title <span class="text-red-500">*</span>
                    </label>
                    <flux:input 
                        wire:model="title" 
                        placeholder="Enter paper title" 
                        required 
                    />
                    @error('title') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Slug & Serial Number -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Slug -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Slug
                        </label>
                        <flux:input 
                            wire:model="slug" 
                            placeholder="publication-slug-title" 
                        />
                        @error('slug') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Serial Number -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Serial Number
                        </label>
                        <flux:input 
                            wire:model="serial_number" 
                            placeholder="e.g. SN-2024-001" 
                        />
                        @error('serial_number') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Publication Keywords Tag Container -->
                <div 
                    x-data="{ 
                        keywords: @entangle('publication_keywords'), 
                        newKeyword: '',
                        addTag(text = null) {
                            let val = text !== null ? text : this.newKeyword;
                            if (!val) return;
                            let items = val.split(',').map(k => k.trim()).filter(k => k.length > 0);
                            items.forEach(item => {
                                if (!this.keywords.some(k => k.toLowerCase() === item.toLowerCase())) {
                                    this.keywords.push(item);
                                }
                            });
                            if (text === null) this.newKeyword = '';
                        },
                        removeTag(index) {
                            this.keywords.splice(index, 1);
                        },
                        handleKeyDown(e) {
                            if (e.key === 'Backspace' && this.newKeyword === '' && this.keywords.length > 0) {
                                this.removeTag(this.keywords.length - 1);
                            } else if (e.key === 'Enter' || e.key === 'Tab' || e.key === ',') {
                                e.preventDefault();
                                this.addTag();
                            }
                        }
                    }" 
                    class="space-y-1.5"
                >
                    <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        Publication Keywords
                    </label>
                    <div 
                        @click="$refs.kwInput.focus()"
                        class="w-full min-h-[42px] px-3 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-xl flex flex-wrap items-center gap-2 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/20 cursor-text transition-all"
                    >
                        <template x-for="(kw, index) in keywords" :key="index">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-brand-50 dark:bg-brand/10 text-brand text-xs font-semibold rounded-lg border border-brand/20">
                                <span x-text="kw"></span>
                                <button 
                                    type="button" 
                                    @click.stop="removeTag(index)" 
                                    class="text-brand hover:text-red-500 font-bold text-sm leading-none focus:outline-none"
                                >
                                    &times;
                                </button>
                            </span>
                        </template>
                        <input 
                            x-ref="kwInput"
                            type="text" 
                            x-model="newKeyword" 
                            @keydown="handleKeyDown($event)"
                            :placeholder="keywords.length === 0 ? 'Type keywords (press Enter or comma)...' : 'Add keyword...'" 
                            class="flex-1 min-w-[180px] bg-transparent border-0 outline-none focus:outline-none focus:ring-0 text-xs text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 px-1 shadow-none"
                        />
                        <button 
                            type="button" 
                            x-show="newKeyword.trim() !== ''" 
                            @click.stop="addTag()" 
                            class="px-2.5 py-1 bg-brand hover:bg-brand-600 text-white text-xs font-semibold rounded-lg transition"
                        >
                            Add
                        </button>
                    </div>
                    @error('publication_keywords') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Abstract / Description -->
                <div class="space-y-1.5">
                    <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        Abstract / Description
                    </label>
                    <flux:textarea 
                        wire:model="description" 
                        rows="5" 
                        placeholder="Enter abstract or description..." 
                    />
                    @error('description') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- SECTION 2: Journal & Publication Details -->
            <div class="space-y-5">
                <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                        2. Journal & Publishing Details
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Journal source, DOI identifiers, month/year picker, and status.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Journal Title -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Journal Title
                        </label>
                        <flux:input 
                            wire:model="journal_title" 
                            placeholder="e.g. Nature Biotechnology" 
                        />
                        @error('journal_title') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Journal Name -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Journal Name
                        </label>
                        <flux:input 
                            wire:model="journal_name" 
                            placeholder="e.g. Nature Biotechnology" 
                        />
                        @error('journal_name') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- DOI -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            DOI (Digital Object Identifier)
                        </label>
                        <flux:input 
                            wire:model="doi" 
                            placeholder="10.1000/182" 
                            icon="link" 
                        />
                        @error('doi') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Published Date (Flatpickr Month and Year Picker) -->
                    <div 
                        wire:ignore
                        class="space-y-1.5"
                        x-data="{
                            value: @entangle('published_date'),
                            instance: null,
                            init() {
                                this.$nextTick(() => {
                                    if (typeof flatpickr !== 'undefined') {
                                        this.instance = flatpickr(this.$refs.picker, {
                                            plugins: typeof monthSelectPlugin !== 'undefined' ? [
                                                new monthSelectPlugin({
                                                    shorthand: true,
                                                    dateFormat: 'F Y',
                                                    altFormat: 'F Y',
                                                    theme: 'light'
                                                })
                                            ] : [],
                                            altInput: true,
                                            altInputClass: 'w-full h-10 px-3.5 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-xl text-xs text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand cursor-pointer shadow-xs transition',
                                            defaultDate: this.value || null,
                                            onChange: (selectedDates, dateStr) => {
                                                this.value = dateStr;
                                            }
                                        });

                                        this.$watch('value', (val) => {
                                            if (this.instance && val !== this.instance.input.value) {
                                                this.instance.setDate(val, false);
                                            }
                                        });
                                    }
                                });
                            }
                        }"
                    >
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Published Date (Month & Year)
                        </label>
                        <input 
                            x-ref="picker" 
                            type="text" 
                            class="hidden" 
                            placeholder="Select Month and Year..." 
                        />
                        @error('published_date') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Status (Active / Inactive) -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <flux:select wire:model="status">
                            <option value="1">Active (1)</option>
                            <option value="0">Inactive (0)</option>
                        </flux:select>
                        <p class="text-[11px] text-zinc-400">1 = Active, 0 = Inactive</p>
                        @error('status') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Metadata, Volume, Citations & PDF File Upload -->
            <div class="space-y-5">
                <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                        3. Volume, Citations & Document File
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Volume numbering, citations count, and uploaded publication PDF/file.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <!-- Volume -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Volume
                        </label>
                        <flux:input wire:model="volume" placeholder="e.g. 12" />
                        @error('volume') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Issue -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Issue
                        </label>
                        <flux:input wire:model="issue" placeholder="e.g. 4" />
                        @error('issue') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Page No -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Page No
                        </label>
                        <flux:input wire:model="page_no" placeholder="e.g. 102-115" />
                        @error('page_no') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- ISSN -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            ISSN
                        </label>
                        <flux:input wire:model="issn" placeholder="e.g. 2049-3630" />
                        @error('issn') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <!-- Citations -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Citations
                        </label>
                        <flux:input 
                            type="number"
                            wire:model="citations" 
                            placeholder="Enter citation count, e.g. 15" 
                        />
                        @error('citations') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Article Type -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Article Type
                        </label>
                        <flux:select wire:model="article_type">
                            <option value="" disabled {{ empty($article_type) ? 'selected' : '' }}>Select Article Type</option>
                            <option value="published">Published</option>
                            <option value="pre-print">Pre-print</option>
                        </flux:select>
                        @error('article_type') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- URL -->
                    <div class="space-y-1.5">
                        <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                            Article URL / Landing Page
                        </label>
                        <flux:input 
                            wire:model="url" 
                            placeholder="https://..." 
                            icon="globe-alt" 
                        />
                        @error('url') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Publication Document (PDF / DOC) Upload Card -->
                <div x-data="{ isDropping: false }" class="space-y-2 pt-2">
                    <label class="block text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        Publication Document (PDF / DOC only)
                    </label>

                    <!-- Hidden Actual File Input -->
                    <input 
                        x-ref="fileInput"
                        type="file" 
                        wire:model="fileUpload" 
                        accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        class="hidden"
                    />

                    <!-- STATE: Actively Uploading File (Shown directly in the box) -->
                    <div 
                        wire:loading.flex 
                        wire:target="fileUpload" 
                        class="w-full border-2 border-dashed border-brand/50 bg-brand-50/20 dark:bg-brand/10 rounded-2xl p-6 text-center transition-all duration-150 items-center justify-center"
                    >
                        <div class="flex flex-col items-center justify-center space-y-2">
                            <div class="size-11 rounded-2xl bg-brand-50 dark:bg-brand/20 text-brand flex items-center justify-center shadow-xs">
                                <flux:icon name="arrow-path" class="size-5 animate-spin" />
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-bold text-brand">
                                    Uploading document...
                                </p>
                                <p class="text-[11px] text-zinc-400">
                                    Please wait while your file is being uploaded
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Idle States (Hidden during active file upload) -->
                    <div wire:loading.remove wire:target="fileUpload" class="w-full">
                        <!-- STATE 1: Newly Selected File (Unsaved Upload) -->
                        @if($fileUpload)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800/80 gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 shadow-xs">
                                        <flux:icon name="document-arrow-up" class="size-5" />
                                    </div>
                                    <div class="truncate">
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-xs text-zinc-900 dark:text-white truncate">
                                                {{ $fileUpload->getClientOriginalName() }}
                                            </p>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/70 dark:text-emerald-300">
                                                New File Ready
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                            {{ round($fileUpload->getSize() / 1024, 1) }} KB &bull; Click Save Changes to store
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                    <button 
                                        type="button" 
                                        @click="$refs.fileInput.click()"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition cursor-pointer shadow-2xs"
                                    >
                                        Change File
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="removeFileUpload"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/60 hover:bg-red-600 hover:text-white dark:hover:bg-red-600 transition cursor-pointer shadow-2xs"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                        <!-- STATE 2: Existing Document in Database -->
                        @elseif($existingImage)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-10 rounded-xl bg-brand-50 dark:bg-brand/20 text-brand flex items-center justify-center shrink-0 shadow-xs">
                                        @if(str_ends_with(strtolower($existingImage), '.pdf'))
                                            <flux:icon name="document-text" class="size-5" />
                                        @else
                                            <flux:icon name="document" class="size-5" />
                                        @endif
                                    </div>
                                    <div class="truncate">
                                        <p class="font-bold text-xs text-zinc-900 dark:text-white truncate">
                                            {{ basename($existingImage) }}
                                        </p>
                                        <p class="text-[11px] text-zinc-400 font-mono truncate">
                                            {{ $existingImage }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                    <a 
                                        href="{{ asset('storage/' . $existingImage) }}" 
                                        target="_blank" 
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-zinc-700 text-brand border border-zinc-300 dark:border-zinc-600 hover:bg-brand hover:text-white hover:border-brand transition cursor-pointer shadow-2xs inline-flex items-center gap-1.5"
                                    >
                                        <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                        <span>View File</span>
                                    </a>
                                    <button 
                                        type="button" 
                                        @click="$refs.fileInput.click()"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-600 transition cursor-pointer shadow-2xs"
                                    >
                                        Change File
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="removeExistingFile"
                                        wire:confirm="Are you sure you want to remove and delete this document from the publication?"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/60 hover:bg-red-600 hover:text-white dark:hover:bg-red-600 transition cursor-pointer shadow-2xs"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                        <!-- STATE 3: No File Uploaded (Drag & Drop / Upload Trigger) -->
                        @else
                            <div 
                                @click="$refs.fileInput.click()"
                                @dragover.prevent="isDropping = true"
                                @dragleave.prevent="isDropping = false"
                                @drop.prevent="isDropping = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))"
                                :class="isDropping ? 'border-brand bg-brand-50/40 dark:bg-brand/10' : 'border-zinc-300 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/40 hover:bg-zinc-100/70 dark:hover:bg-zinc-800/80'"
                                class="border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all duration-150 group"
                            >
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="size-11 rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-400 group-hover:text-brand group-hover:scale-105 group-hover:bg-brand-50 dark:group-hover:bg-brand/20 transition-all flex items-center justify-center shadow-xs">
                                        <flux:icon name="arrow-up-tray" class="size-5" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-xs font-bold text-zinc-700 dark:text-zinc-200">
                                            <span class="text-brand group-hover:underline">Click to upload</span> or drag and drop document
                                        </p>
                                        <p class="text-[11px] text-zinc-400">
                                            Supports PDF (.pdf) and Word documents (.doc, .docx) up to 20MB
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @error('fileUpload') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- SECTION 4: Record Metadata Card -->
            <div class="space-y-4 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white">
                            4. Record Metadata
                        </h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Database timestamps, view counts, and system identifiers.
                        </p>
                    </div>
                    <flux:icon name="circle-stack" class="size-5 text-zinc-400" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/60">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 block">MongoDB Object ID</span>
                        <span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-200 select-all break-all">{{ (string) $publication->_id }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 block">Views</span>
                        <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $publication->view_counts ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 block">Created At</span>
                        <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $publication->created_at ? $publication->created_at->format('M d, Y') : 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 block">Updated At</span>
                        <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $publication->updated_at ? $publication->updated_at->format('M d, Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Form Actions Bottom Bar -->
            <div class="pt-6 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <flux:button variant="filled" href="{{ route('admin.publications.index') }}" wire:navigate>
                    Cancel
                </flux:button>
                
                <div class="flex items-center gap-3">
                    <flux:button variant="primary" type="submit" icon="check" wire:target="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Save Changes</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    </div>
</div>
