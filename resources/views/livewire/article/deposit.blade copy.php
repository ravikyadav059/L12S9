<?php

use App\Models\Publication;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;

    // Step state
    public int $currentStep = 1;

    // Step 1: Basic Paper Info
    public string $title = '';

    public string $abstract = '';

    public array $keywordsList = [];

    public string $newKeyword = '';

    public string $option = 'preprint'; // default Pre-Print

    // Step 1: Published Fields (Shown when $option === 'published')
    public string $articleType = '';

    public ?string $doi = '';

    public string $journalName = '';

    public string $issn = '';

    public string $volume = '';

    public string $issue = '';

    public string $citation = '';

    public string $pageNo = '';

    public string $pubMonthYear = '';

    public string $pdfUrl = '';

    public string $landingPageUrl = '';

    // Step 2: File & Authors
    public mixed $file = null;

    public array $authors = [
        ['name' => '', 'email' => '', 'affiliation' => ''],
    ];

    public bool $agreeCopyright = false;

    // Keyword management
    public function addKeyword(): void
    {
        $trimmed = trim($this->newKeyword);
        if ($trimmed !== '' && ! in_array($trimmed, $this->keywordsList)) {
            $this->keywordsList[] = $trimmed;
            $this->newKeyword = '';
            $this->resetValidation('keywordsList');
        }
    }

    public function removeKeyword(int $index): void
    {
        if (isset($this->keywordsList[$index])) {
            array_splice($this->keywordsList, $index, 1);
        }
    }

    // Author management
    public function addAuthor(): void
    {
        $this->authors[] = ['name' => '', 'email' => '', 'affiliation' => ''];
    }

    public function removeAuthor(int $index): void
    {
        if (count($this->authors) > 1 && isset($this->authors[$index])) {
            array_splice($this->authors, $index, 1);
        }
    }

    // Navigation & Validation
    public function goToNextStep(): void
    {
        $rules = [
            'title' => 'required|string|min:5|max:255',
            'abstract' => 'required|string|min:10',
            'keywordsList' => 'required|array|min:1',
            'option' => 'required|in:preprint,published',
        ];

        $messages = [
            'title.required' => 'Paper title is required.',
            'title.min' => 'Paper title must be at least 5 characters.',
            'abstract.required' => 'Abstract is required.',
            'abstract.min' => 'Abstract must be at least 10 characters.',
            'keywordsList.required' => 'At least one keyword is required.',
            'keywordsList.min' => 'Please add at least one keyword.',
        ];

        if ($this->option === 'published') {
            $rules = array_merge($rules, [
                'journalName' => 'required|string|min:2',
                'volume' => 'required|string',
                'issue' => 'required|string',
                'pageNo' => 'required|string',
                'doi' => 'nullable|string',
                'articleType' => 'nullable|string',
                'issn' => 'nullable|string',
                'citation' => 'nullable|string',
                'pubMonthYear' => 'nullable|string',
                'pdfUrl' => 'nullable|url',
                'landingPageUrl' => 'nullable|url',
            ]);

            $messages = array_merge($messages, [
                'journalName.required' => 'Journal name is required for published articles.',
                'volume.required' => 'Volume is required.',
                'issue.required' => 'Issue is required.',
                'pageNo.required' => 'Page No is required.',
                'pdfUrl.url' => 'PDF URL must be a valid URL (e.g. https://...).',
                'landingPageUrl.url' => 'Landing Page URL must be a valid URL (e.g. https://...).',
            ]);
        }

        $this->validate($rules, $messages);
        $this->currentStep = 2;
    }

    public function goToPreviousStep(): void
    {
        $this->currentStep = 1;
    }

    public function clearPublicationDate(): void
    {
        $this->pubMonthYear = '';
    }

    public function retrieveDoi(): void
    {
        if ($this->doi) {
            session()->flash('doi_status', 'Publication details retrieved for DOI: '.$this->doi);
        } else {
            session()->flash('doi_status_error', 'Please enter a valid DOI before retrieving.');
        }
    }

    // Submit Deposit
    public function deposit(): void
    {
        if ($this->currentStep === 1) {
            $this->goToNextStep();

            return;
        }

        $this->validate([
            'authors' => 'required|array|min:1',
            'authors.*.name' => 'required|string|max:255',
            'authors.*.email' => 'required|email|max:255',
            'authors.*.affiliation' => 'nullable|string|max:255',
            'agreeCopyright' => 'accepted',
            'file' => 'nullable|file|mimes:pdf|max:10240',
        ], [
            'authors.*.name.required' => 'Author name is required.',
            'authors.*.email.required' => 'Author email is required.',
            'authors.*.email.email' => 'Please enter a valid author email.',
            'agreeCopyright.accepted' => 'You must confirm the copyright and licensing agreement.',
            'file.mimes' => 'Only PDF files are supported.',
            'file.max' => 'PDF file must not exceed 10MB.',
        ]);

        $filePath = null;
        if ($this->file) {
            $filePath = $this->file->store('articles', 'public');
        }

        Publication::create([
            'title' => $this->title,
            'abstract' => $this->abstract,
            'keywords' => $this->keywordsList,
            'option' => $this->option,
            'doi' => $this->doi,
            'article_type' => $this->articleType,
            'journal_name' => $this->journalName,
            'issn' => $this->issn,
            'volume' => $this->volume,
            'issue' => $this->issue,
            'citation' => $this->citation,
            'page_no' => $this->pageNo,
            'publication_month_year' => $this->pubMonthYear,
            'pdf_url' => $this->pdfUrl,
            'landing_page_url' => $this->landingPageUrl,
            'file_path' => $filePath,
            'authors' => $this->authors,
            'agree_copyright' => $this->agreeCopyright,
            'status' => 'submitted',
            'user_id' => Auth::id(),
        ]);

        session()->flash('status', 'Thank you for submitting your article! Our team will review its contents.');

        $this->reset([
            'currentStep', 'title', 'abstract', 'keywordsList', 'newKeyword', 'option',
            'articleType', 'doi', 'journalName', 'issn', 'volume', 'issue', 'citation',
            'pageNo', 'pubMonthYear', 'pdfUrl', 'landingPageUrl', 'file', 'agreeCopyright',
        ]);
        $this->authors = [['name' => '', 'email' => '', 'affiliation' => '']];
        $this->option = 'preprint';
    }
}; ?>

<div>
    <!-- ================= TOP HEADER HERO BANNER ================= -->
    <div class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200/90 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center gap-4">
            <!-- Left Icon Badge -->
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-[#198BEA] text-white flex items-center justify-center shrink-0 shadow-md shadow-[#198BEA]/25">
                <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>

            <!-- Header Content -->
            <div class="space-y-0.5">
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">
                    Deposit Published or Preprint Articles
                </h1>
                <p class="text-xs sm:text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Securely upload and preserve your research papers
                </p>
                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed pt-0.5">
                    Share your published works and preprints with DOI assignment, version control, and permanent archiving. 
                    <span class="font-bold text-[#198BEA] dark:text-sky-400">Upload. Preserve. Share.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================= TWO COLUMN MAIN CONTENT ================= -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- LEFT CONTAINER: DEPOSIT FORM -->
            <div class="flex-1 min-w-0 w-full bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-xs space-y-6">
                
                <!-- Section Header with Step indicator -->
                <div class="pb-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">
                        @if($currentStep === 1)
                            Step 1: Paper Information
                        @else
                            Step 2: Upload File and Author Details
                        @endif
                    </h2>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 rounded-full border border-zinc-200 dark:border-zinc-700">
                        Step {{ $currentStep }} of 2
                    </span>
                </div>

                <!-- Alert Feedback -->
                @if (session('status'))
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-emerald-800 dark:text-emerald-300 text-sm font-medium flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <!-- Form -->
                <form wire:submit="deposit" class="space-y-6">
                    
                    @if($currentStep === 1)
                        <!-- ================= STEP 1: PAPER INFORMATION ================= -->
                        
                        <!-- Paper Title -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                Paper title <span class="text-red-500">*</span>
                            </label>
                            <flux:input 
                                wire:model="title" 
                                placeholder="Enter paper title" 
                            />
                            @error('title') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Abstract -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                Abstract <span class="text-red-500">*</span>
                            </label>
                            <flux:textarea 
                                wire:model="abstract" 
                                placeholder="Enter abstract" 
                                rows="4" 
                            />
                            @error('abstract') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Keywords Tag Container (Matching Image 3) -->
                        <div class="space-y-1.5">
                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                Keywords <span class="text-red-500">*</span>
                            </label>
                            <div class="w-full min-h-[52px] p-2 bg-white dark:bg-zinc-800/80 border border-zinc-300 dark:border-zinc-700 rounded-xl flex flex-wrap items-center gap-2 focus-within:ring-2 focus-within:ring-[#198BEA] focus-within:border-[#198BEA] transition">
                                @foreach($keywordsList as $index => $kw)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-zinc-200/80 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200 text-xs font-semibold rounded-md">
                                        <span>{{ $kw }}</span>
                                        <button type="button" wire:click="removeKeyword({{ $index }})" class="text-red-500 hover:text-red-700 font-bold text-sm leading-none focus:outline-none ml-1">
                                            &times;
                                        </button>
                                    </span>
                                @endforeach
                                
                                <input 
                                    type="text" 
                                    wire:model="newKeyword" 
                                    wire:keydown.enter.prevent="addKeyword" 
                                    wire:keydown.comma.prevent="addKeyword"
                                    placeholder="{{ count($keywordsList) === 0 ? 'Enter keywords and press enter' : 'Add keyword...' }}" 
                                    class="flex-1 min-w-[160px] bg-transparent border-0 outline-none focus:outline-none focus:ring-0 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 px-2"
                                />

                                @if(trim($newKeyword) !== '')
                                    <button type="button" wire:click="addKeyword" class="px-3 py-1 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-md transition shadow-xs">
                                        Add Tag
                                    </button>
                                @endif
                            </div>
                            @error('keywordsList') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Option (Pre-Print / Published) -->
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                Option <span class="text-red-500">*</span>
                            </label>
                            <div class="flex items-center gap-6">
                                <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300 font-medium cursor-pointer">
                                    <input 
                                        type="radio" 
                                        wire:model.live="option" 
                                        value="preprint" 
                                        class="w-4 h-4 text-[#198BEA] border-zinc-300 focus:ring-[#198BEA]" 
                                    />
                                    <span>Pre-Print</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300 font-medium cursor-pointer">
                                    <input 
                                        type="radio" 
                                        wire:model.live="option" 
                                        value="published" 
                                        class="w-4 h-4 text-[#198BEA] border-zinc-300 focus:ring-[#198BEA]" 
                                    />
                                    <span>Published</span>
                                </label>
                            </div>
                            @error('option') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Published More Input Fields (Shown when Published is selected, matching Image 1) -->
                        @if($option === 'published')
                            <div class="space-y-6 pt-4 border-t border-zinc-200/80 dark:border-zinc-800 animate-fadeIn">
                                
                                <!-- Article Type & DOI Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Article Type -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Article Type
                                        </label>
                                        <select 
                                            wire:model="articleType" 
                                            class="w-full h-10 px-3 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-xl text-sm text-zinc-800 dark:text-zinc-200 focus:ring-2 focus:ring-[#198BEA] focus:border-[#198BEA] outline-none transition"
                                        >
                                            <option value="">-- Select Article Type --</option>
                                            <option value="research_article">Research Article</option>
                                            <option value="review_article">Review Article</option>
                                            <option value="case_study">Case Study</option>
                                            <option value="short_communication">Short Communication</option>
                                            <option value="methodology">Methodology</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <!-- DOI with RETRIEVE button -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            DOI
                                        </label>
                                        <div class="relative flex items-center">
                                            <flux:input 
                                                wire:model="doi" 
                                                placeholder="Enter doi, e.g. 10.5120/ijca2016909228" 
                                                class="w-full pr-24"
                                            />
                                            <button 
                                                type="button" 
                                                wire:click="retrieveDoi"
                                                class="absolute right-1 top-1 bottom-1 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition shrink-0"
                                            >
                                                RETRIEVE
                                            </button>
                                        </div>
                                        @if(session('doi_status'))
                                            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ session('doi_status') }}</p>
                                        @endif
                                        @if(session('doi_status_error'))
                                            <p class="text-xs text-red-500 font-semibold">{{ session('doi_status_error') }}</p>
                                        @endif
                                    </div>
                                </div>

                                <!-- Journal Name & ISSN Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Journal Name -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Journal name <span class="text-red-500">*</span>
                                        </label>
                                        <flux:input 
                                            wire:model="journalName" 
                                            placeholder="Search journal name Or Add" 
                                        />
                                        @error('journalName') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- ISSN -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            ISSN
                                        </label>
                                        <flux:input 
                                            wire:model="issn" 
                                            placeholder="Enter issn, e.g. 1234-1258" 
                                        />
                                    </div>
                                </div>

                                <!-- Volume, Issue & Citation Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Volume & Issue side-by-side -->
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="space-y-1.5">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Volume <span class="text-red-500">*</span>
                                            </label>
                                            <flux:input 
                                                wire:model="volume" 
                                                placeholder="Enter Volume, e.g. 1" 
                                            />
                                            @error('volume') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Issue <span class="text-red-500">*</span>
                                            </label>
                                            <flux:input 
                                                wire:model="issue" 
                                                placeholder="Enter Issue, e.g. 5" 
                                            />
                                            @error('issue') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    <!-- Citation -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Citation
                                        </label>
                                        <flux:input 
                                            type="number" 
                                            wire:model="citation" 
                                            placeholder="Enter Numeric Value, e.g. 487 or 1002" 
                                        />
                                    </div>
                                </div>

                                <!-- Page No & Publication Month-Year Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Page No -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Page No <span class="text-red-500">*</span>
                                        </label>
                                        <flux:input 
                                            wire:model="pageNo" 
                                            placeholder="1-10" 
                                        />
                                        @error('pageNo') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Publication Month-Year with Clear Button -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Publication Month-Year
                                        </label>
                                        <flux:input 
                                            wire:model="pubMonthYear" 
                                            placeholder="MM-YYYY" 
                                        />
                                        <button 
                                            type="button" 
                                            wire:click="clearPublicationDate"
                                            class="px-3 py-1 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-md transition shadow-xs mt-1"
                                        >
                                            Clear Publication Date
                                        </button>
                                    </div>
                                </div>

                                <!-- PDF URL & Landing Page URL Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- PDF URL -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            PDF URL
                                        </label>
                                        <flux:input 
                                            type="url" 
                                            wire:model="pdfUrl" 
                                            placeholder="Published paper PDF URL, e.g. 'https://fake.pdf'" 
                                        />
                                        @error('pdfUrl') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Landing Page URL -->
                                    <div class="space-y-1.5">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Landing Page URL
                                        </label>
                                        <flux:input 
                                            type="url" 
                                            wire:model="landingPageUrl" 
                                            placeholder="Published paper landing page URL, e.g. 'https://scholar9.com/'" 
                                        />
                                        @error('landingPageUrl') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                            </div>
                        @endif

                        <!-- Step 1 Actions: Next Button -->
                        <div class="pt-6 flex items-center justify-end border-t border-zinc-200 dark:border-zinc-800">
                            <flux:button 
                                variant="primary" 
                                type="button" 
                                wire:click="goToNextStep" 
                                class="px-8 bg-[#198BEA] hover:bg-[#1476c9] text-white"
                            >
                                Next
                            </flux:button>
                        </div>

                    @else
                        <!-- ================= STEP 2: UPLOAD FILE & AUTHOR DETAILS ================= -->

                        <!-- File Upload -->
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                    Upload File
                                </label>
                                <span class="text-xs text-zinc-500 font-normal">supports .pdf</span>
                            </div>
                            
                            <div class="border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-xl p-4 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-800/50 hover:bg-zinc-100/50 transition">
                                <div class="flex items-center gap-3">
                                    <svg class="w-8 h-8 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                            {{ $file ? $file->getClientOriginalName() : 'choose your file' }}
                                        </p>
                                        @if($file)
                                            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">File selected ({{ round($file->getSize() / 1024, 1) }} KB)</p>
                                        @endif
                                    </div>
                                </div>
                                
                                <label class="cursor-pointer px-4 py-2 bg-zinc-200 hover:bg-zinc-300 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-800 dark:text-zinc-200 text-sm font-semibold rounded-lg transition shrink-0">
                                    <span>Upload</span>
                                    <input type="file" wire:model="file" accept=".pdf" class="hidden" />
                                </label>
                            </div>
                            @error('file') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <!-- Authors list -->
                        <div class="space-y-6 pt-2">
                            @foreach($authors as $index => $author)
                                <div class="p-4 bg-zinc-50/70 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/80 rounded-xl space-y-4 relative">
                                    <div class="flex items-center justify-between pb-2 border-b border-zinc-200/60 dark:border-zinc-700/50">
                                        <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Author #{{ $index + 1 }}</span>
                                        @if(count($authors) > 1)
                                            <button 
                                                type="button" 
                                                wire:click="removeAuthor({{ $index }})" 
                                                class="p-1 text-zinc-400 hover:text-red-600 dark:hover:text-red-400 transition"
                                                title="Remove Author"
                                            >
                                                <svg class="w-5 h-5 text-sky-500 hover:text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <!-- Author Name -->
                                        <div class="space-y-1">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Author Name <span class="text-red-500">*</span>
                                            </label>
                                            <flux:input 
                                                wire:model="authors.{{ $index }}.name" 
                                                placeholder="Search author name Or Add" 
                                            />
                                            @error("authors.{$index}.name") <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                        </div>

                                        <!-- Author Affiliation -->
                                        <div class="space-y-1">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Author Affiliation
                                            </label>
                                            <flux:input 
                                                wire:model="authors.{{ $index }}.affiliation" 
                                                placeholder="Enter author affiliation" 
                                            />
                                        </div>
                                    </div>

                                    <!-- Author Email -->
                                    <div class="space-y-1">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Author Email <span class="text-red-500">*</span>
                                        </label>
                                        <flux:input 
                                            type="email" 
                                            wire:model="authors.{{ $index }}.email" 
                                            placeholder="Enter author email, e.g. hello@scholar9.com" 
                                        />
                                        @error("authors.{$index}.email") <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endforeach

                            <!-- Add Author Button -->
                            <div>
                                <button 
                                    type="button" 
                                    wire:click="addAuthor" 
                                    class="px-4 py-2 bg-zinc-200 dark:bg-zinc-700 hover:bg-zinc-300 dark:hover:bg-zinc-600 text-zinc-800 dark:text-zinc-100 text-sm font-semibold rounded-lg transition shadow-xs flex items-center gap-1.5"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                    </svg>
                                    <span>Add Author</span>
                                </button>
                            </div>

                            <!-- Copyright Checkbox -->
                            <div class="pt-2">
                                <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300 font-medium cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        wire:model="agreeCopyright" 
                                        class="w-4 h-4 text-[#198BEA] rounded border-zinc-300 focus:ring-[#198BEA]" 
                                    />
                                    <span>confirm copyright and licensing agreement.</span>
                                </label>
                                @error('agreeCopyright') <p class="text-xs text-red-500 font-medium mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Step 2 Actions: Previous & Submit Buttons -->
                        <div class="pt-6 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-800">
                            <flux:button 
                                variant="subtle" 
                                type="button" 
                                wire:click="goToPreviousStep" 
                                class="px-6 border border-zinc-300 dark:border-zinc-700"
                            >
                                Previous
                            </flux:button>
                            <flux:button 
                                variant="primary" 
                                type="submit" 
                                class="px-8 bg-[#198BEA] hover:bg-[#1476c9] text-white"
                            >
                                Submit
                            </flux:button>
                        </div>

                    @endif

                </form>
            </div>

            <!-- RIGHT CONTAINER: REUSABLE SIDEBAR BANNERS -->
            <x-article.sidebar />

        </div>
    </div>
</div>
