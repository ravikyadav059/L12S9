<?php

use App\Models\Article;
use App\Models\Publication;
use App\Models\ReviewerJournal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // Atomically increment views count on page visit
        Publication::where('slug', $slug)->increment('view_counts');
    }

    public function with(): array
    {
        $article = Publication::query()
            ->where('slug', $this->slug)
            ->with(['reviewerJournal', 'articleTypeRelation'])
            ->firstOrFail();

        // 1. Resolve registered co-authors with Zero N+1
        $coAuthorIds = collect($article->registered_co_author ?? [])
            ->filter()
            ->flatten()
            ->map(fn ($id) => is_string($id) ? trim($id) : (is_object($id) ? (string) $id : ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $registeredUsers = !empty($coAuthorIds)
            ? User::query()
                ->whereIn('_id', $coAuthorIds)
                ->select(['_id', 'first_name', 'last_name', 'slug', 'fullname', 'email', 'photo', 'affiliation'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        // 2. Build complete author list with priority fallback
        $authorList = [];
        if (!empty($article->registered_co_author) && is_array($article->registered_co_author)) {
            foreach ($article->registered_co_author as $uid) {
                $u = $registeredUsers[(string) $uid] ?? null;
                if ($u) {
                    $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->fullname ?? '');
                    if ($name) {
                        $authorList[] = [
                            'name' => $name,
                            'slug' => $u->slug ?? null,
                            'email' => $u->email ?? null,
                            'photo' => $u->avatar ?? null,
                            'initials' => $u->initials(),
                            'affiliation' => $u->affiliation ?? null,
                            'is_registered' => true,
                        ];
                    }
                }
            }
        }

        if (!empty($article->unregistered_co_author) && is_array($article->unregistered_co_author)) {
            foreach ($article->unregistered_co_author as $unreg) {
                $name = is_array($unreg) ? ($unreg['name'] ?? '') : (is_string($unreg) ? $unreg : '');
                $email = is_array($unreg) ? ($unreg['email'] ?? null) : null;
                $affiliation = is_array($unreg) ? ($unreg['affiliation'] ?? null) : null;
                if (trim($name)) {
                    $authorList[] = [
                        'name' => trim($name),
                        'slug' => null,
                        'email' => $email,
                        'photo' => null,
                        'initials' => mb_strtoupper(mb_substr(trim($name), 0, 2)),
                        'affiliation' => $affiliation,
                        'is_registered' => false,
                    ];
                }
            }
        }

        if (empty($authorList) && !empty($article->authors) && is_array($article->authors)) {
            foreach ($article->authors as $auth) {
                $name = is_array($auth) ? ($auth['name'] ?? '') : (is_string($auth) ? $auth : '');
                $email = is_array($auth) ? ($auth['email'] ?? null) : null;
                $affiliation = is_array($auth) ? ($auth['affiliation'] ?? null) : null;
                if (trim($name)) {
                    $authorList[] = [
                        'name' => trim($name),
                        'slug' => null,
                        'email' => $email,
                        'photo' => null,
                        'initials' => mb_strtoupper(mb_substr(trim($name), 0, 2)),
                        'affiliation' => $affiliation,
                        'is_registered' => false,
                    ];
                }
            }
        }

        // 3. Resolve Priority Article Type (Only if present in database)
        $displayType = null;
        if (!empty($article->article_type)) {
            $displayType = ucwords(str_replace(['_', '-'], ' ', (string) $article->article_type));
        } elseif (!empty($article->type)) {
            $typeModel = $article->articleTypeRelation ?? Article::find($article->type);
            if ($typeModel && !empty($typeModel->name)) {
                $displayType = $typeModel->name;
            }
        }

        // 4. Resolve Article PDF URL (stored in the 'image' field)
        $pdfUrl = null;
        if (!empty($article->image)) {
            $imagePath = trim((string) $article->image);
            if (\Illuminate\Support\Str::startsWith($imagePath, ['http://', 'https://'])) {
                $pdfUrl = $imagePath;
            } elseif (Storage::disk('public')->exists($imagePath)) {
                $pdfUrl = Storage::disk('public')->url($imagePath);
            } else {
                $pdfUrl = asset('storage/' . ltrim($imagePath, '/'));
            }
        }

        // 5. Build Keywords List (stored in 'publication_keywords')
        $keywords = [];
        $rawKeywords = $article->publication_keywords ?? [];
        if (is_array($rawKeywords)) {
            $keywords = array_values(array_filter(array_map('trim', $rawKeywords)));
        } elseif (is_string($rawKeywords) && trim($rawKeywords) !== '') {
            $keywords = array_values(array_filter(array_map('trim', explode(',', $rawKeywords))));
        }

        // 6. Build Citation Formats
        $authorNames = !empty($authorList) ? implode(', ', array_column($authorList, 'name')) : 'Anonymous';
        $year = !empty($article->publication_month_year) 
            ? (preg_match('/\b(19|20)\d{2}\b/', $article->publication_month_year, $matches) ? $matches[0] : date('Y'))
            : (!empty($article->created_at) ? $article->created_at->format('Y') : date('Y'));
        $journal = $article->reviewerJournal?->journal_title ?? $article->journal_name ?? 'Research Journal';
        $volume = $article->volume ?? '';
        $issue = $article->issue ?? '';
        $pages = $article->page_no ?? '';
        $doi = $article->doi ?? '';

        $apaCitation = "{$authorNames} ({$year}). {$article->title}. {$journal}" 
            . ($volume ? ", {$volume}" : '') 
            . ($issue ? "({$issue})" : '') 
            . ($pages ? ", {$pages}" : '') 
            . ($doi ? ". https://doi.org/{$doi}" : '.');

        $mlaCitation = "{$authorNames}. \"{$article->title}.\" {$journal}" 
            . ($volume ? ", vol. {$volume}" : '') 
            . ($issue ? ", no. {$issue}" : '') 
            . ", {$year}" 
            . ($pages ? ", pp. {$pages}" : '') 
            . ($doi ? ". DOI: {$doi}" : '.');

        $chicagoCitation = "{$authorNames}. \"{$article->title}.\" {$journal} " 
            . ($volume ? "{$volume}" : '') 
            . ($issue ? ", no. {$issue}" : '') 
            . " ({$year})" 
            . ($pages ? ": {$pages}" : '') 
            . ($doi ? ". https://doi.org/{$doi}" : '.');

        $bibtexCitation = "@article{article_{$article->_id},\n"
            . "  title = {{" . addslashes($article->title) . "}},\n"
            . "  author = {" . addslashes($authorNames) . "},\n"
            . "  journal = {" . addslashes($journal) . "},\n"
            . "  year = {" . $year . "},\n"
            . ($volume ? "  volume = {" . addslashes($volume) . "},\n" : "")
            . ($issue ? "  number = {" . addslashes($issue) . "},\n" : "")
            . ($doi ? "  doi = {" . addslashes($doi) . "},\n" : "")
            . "  url = {" . url()->current() . "}\n"
            . "}";

        $rawDoi = $article->doi ?? '';
        $cleanDoi = null;
        if (!empty($rawDoi)) {
            $cleanDoi = preg_replace('#^(https?://(dx\.)?doi\.org/|doi:)#i', '', trim((string) $rawDoi));
            $cleanDoi = trim($cleanDoi, " -/");
        }

        return [
            'article' => $article,
            'authorList' => $authorList,
            'displayType' => $displayType,
            'pdfUrl' => $pdfUrl,
            'keywords' => $keywords,
            'journal' => $journal,
            'year' => $year,
            'cleanDoi' => $cleanDoi,
            'citations' => [
                'apa' => $apaCitation,
                'mla' => $mlaCitation,
                'chicago' => $chicagoCitation,
                'bibtex' => $bibtexCitation,
            ],
        ];
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950 font-sans antialiased text-zinc-900 dark:text-zinc-100">

    <!-- ================= BACK NAVIGATION (STICKY BAR) ================= -->
    <div 
        x-data="{ 
            showStickyTitle: false,
            checkScroll() {
                const titleEl = document.getElementById('hero-article-title');
                if (titleEl) {
                    const rect = titleEl.getBoundingClientRect();
                    // When the main hero title scrolls past the top sticky navigation bar
                    this.showStickyTitle = rect.bottom <= 70;
                } else {
                    this.showStickyTitle = window.scrollY > 150;
                }
            }
        }" 
        @scroll.window="checkScroll()"
        x-init="checkScroll()"
        class="sticky top-14 sm:top-16 z-40 w-full bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-b border-zinc-200/80 dark:border-zinc-800 shadow-xs transition-all"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 sm:py-3 flex items-center gap-2.5 sm:gap-4 text-xs sm:text-sm">
            <!-- Back to Articles Button -->
            <a 
                href="{{ route('articles.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-zinc-700 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white rounded-lg transition-all shadow-xs shrink-0 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span class="hidden sm:inline">Back to Articles</span>
                <span class="sm:hidden">Back</span>
            </a>

            <!-- Sticky Article Title (Only appears when hero title is scrolled out of view) -->
            <div 
                x-show="showStickyTitle"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-2"
                class="flex items-center min-w-0 flex-1 border-l border-zinc-200 dark:border-zinc-800 pl-2.5 sm:pl-4"
                style="display: none;"
            >
                <span class="text-zinc-800 dark:text-zinc-100 font-semibold text-xs sm:text-sm truncate">
                    {{ $article->title }}
                </span>
            </div>
        </div>
    </div>

    <!-- ================= HERO PUBLICATION HEADER ================= -->
    <div class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200/90 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-3 sm:space-y-4">
            
            <!-- Badges Row -->
            @php
                $pubDate = !empty($article->publication_month_year) 
                    ? $article->publication_month_year 
                    : (!empty($article->published_date) ? (string) $article->published_date : ($article->created_at ? $article->created_at->format('F Y') : null));
            @endphp
            @if(!empty($displayType) || !empty($article->option) || !empty($pubDate))
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                    <!-- Article Type Badge (Only when present in database) -->
                    @if(!empty($displayType))
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/80 shadow-2xs">
                            {{ $displayType }}
                        </span>
                    @endif

                    <!-- Option Status Badge (Only when option field is present in database) -->
                    @if(!empty($article->option))
                        @if($article->option === 'published')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-sky-100 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/80 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                Published
                            </span>
                        @elseif($article->option === 'preprint' || $article->option === 'pre-print')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/80 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Pre-print
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                                {{ ucwords(str_replace(['_', '-'], ' ', $article->option)) }}
                            </span>
                        @endif
                    @endif

                    <!-- Published Date Badge / Text next to option badge -->
                    @if(!empty($pubDate))
                        <span class="inline-flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                            <svg class="w-3.5 h-3.5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Published: <strong class="text-zinc-800 dark:text-zinc-200 font-semibold">{{ $pubDate }}</strong></span>
                        </span>
                    @endif
                </div>
            @endif

            <!-- Article Title -->
            <h1 id="hero-article-title" class="text-base sm:text-xl lg:text-2xl font-bold text-zinc-900 dark:text-white leading-snug tracking-tight">
                {{ $article->title }}
            </h1>

            <!-- Authors Row with Avatars -->
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs sm:text-sm md:text-base text-zinc-800 dark:text-zinc-200 pt-0.5">
                <div class="flex items-center gap-1.5 shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600 dark:text-purple-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-semibold text-zinc-900 dark:text-white">Authors:</span>
                </div>

                @if(!empty($authorList))
                    <div class="inline-flex flex-wrap items-center gap-x-1.5 sm:gap-x-2 gap-y-1.5">
                        @foreach($authorList as $idx => $auth)
                            <div class="inline-flex items-center">
                                @if(!empty($auth['slug']))
                                    <a 
                                        href="{{ route('userscholar', ['slug' => $auth['slug']]) }}" 
                                        wire:navigate 
                                        class="group inline-flex items-center gap-1.5 text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] dark:hover:text-sky-400 font-medium no-underline transition-colors"
                                    >
                                        @if(!empty($auth['photo']))
                                             <img 
                                                src="{{ $auth['photo'] }}" 
                                                alt="{{ $auth['name'] }}" 
                                                class="w-6 h-6 sm:w-7 sm:h-7 rounded-full object-cover shrink-0 border border-zinc-200 dark:border-zinc-700 shadow-2xs group-hover:ring-2 group-hover:ring-[#198BEA]/40 transition" 
                                            />
                                        @else
                                            <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-[10px] sm:text-xs font-bold shrink-0 shadow-2xs group-hover:brightness-110 transition">
                                                {{ $auth['initials'] ?? 'AU' }}
                                            </span>
                                        @endif
                                        <span>{{ $auth['name'] }}</span>
                                    </a>
                                @else
                                    <div class="inline-flex items-center gap-1.5">
                                        @if(!empty($auth['photo']))
                                            <img 
                                                src="{{ $auth['photo'] }}" 
                                                alt="{{ $auth['name'] }}" 
                                                class="w-6 h-6 sm:w-7 sm:h-7 rounded-full object-cover shrink-0 border border-zinc-200 dark:border-zinc-700 shadow-2xs" 
                                            />
                                        @else
                                            <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-[#198BEA] text-white flex items-center justify-center text-[10px] sm:text-xs font-bold shrink-0 shadow-2xs">
                                                {{ $auth['initials'] ?? 'AU' }}
                                            </span>
                                        @endif
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $auth['name'] }}</span>
                                    </div>
                                @endif

                                @if($idx < count($authorList) - 1)
                                    <span class="text-zinc-400 dark:text-zinc-500 font-normal ml-0.5">,</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <span class="text-zinc-500 dark:text-zinc-400 font-normal">No authors listed</span>
                @endif
            </div>

            <!-- Quick Metadata Bar -->
            <div class="flex flex-wrap items-center gap-y-2 gap-x-3 sm:gap-x-5 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <!-- Journal Name -->
                @if(!empty($journal))
                    <div class="flex items-center gap-1.5 basis-full sm:basis-auto">
                        <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200 break-words">{{ $journal }}</span>
                    </div>
                @endif

                <!-- DOI -->
                @if(!empty($article->doi))
                    <div class="flex items-center gap-1.5 min-w-0 max-w-full sm:max-w-xs">
                        <svg class="w-4 h-4 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        <span class="truncate">DOI: 
                            <a 
                                href="https://doi.org/{{ $article->doi }}" 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                class="text-[#198BEA] dark:text-sky-400 hover:underline font-medium"
                            >
                                {{ $article->doi }}
                            </a>
                        </span>
                    </div>
                @endif

                <!-- Total Views -->
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span><strong class="text-zinc-800 dark:text-zinc-200 font-semibold">{{ number_format((int) ($article->view_counts ?? 0)) }}</strong> Views</span>
                </div>
            </div>

        </div>
    </div>

    <!-- ================= TWO COLUMN MAIN CONTENT ================= -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8">
        <div class="flex flex-col lg:flex-row gap-5 lg:gap-6 items-start">
            
            <!-- ================= LEFT COLUMN: MAIN ARTICLE BODY (65%) ================= -->
            <div class="flex-1 min-w-0 w-full space-y-5">
                
                <!-- 1. ABSTRACT CARD -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-6 shadow-theme-md space-y-3.5">
                    <div class="flex items-center gap-2.5 border-b border-zinc-100 dark:border-zinc-800 pb-3">
                        <svg class="w-5 h-5 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
                            Abstract
                        </h2>
                    </div>

                    @php
                        $abstractContent = !empty($article->abstract) ? $article->abstract : (!empty($article->description) ? $article->description : null);
                    @endphp

                    @if(!empty($abstractContent))
                        <div class="prose dark:prose-invert max-w-none text-sm sm:text-base text-zinc-700 dark:text-zinc-300 leading-relaxed space-y-3">
                            {!! nl2br(e($abstractContent)) !!}
                        </div>
                    @else
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 italic">
                            No abstract is available for this publication.
                        </p>
                    @endif
                </div>

                <!-- 2. KEYWORDS SECTION -->
                @if(!empty($keywords))
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-6 shadow-theme-md space-y-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <h3 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white tracking-tight">
                                Keywords
                            </h3>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 pt-0.5">
                            @foreach($keywords as $kw)
                                <a 
                                    href="{{ route('articles.index') }}?search={{ urlencode($kw) }}" 
                                    wire:navigate
                                    class="inline-flex items-center px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-[#198BEA]/10 hover:text-[#198BEA] dark:hover:bg-sky-950/50 dark:hover:text-sky-300 border border-zinc-200/60 dark:border-zinc-700 transition"
                                >
                                    #{{ $kw }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- 3. EMBEDDED PDF READER / VIEWER SECTION -->
                <div id="pdf-reader-section" class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-6 shadow-theme-md space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <h2 class="text-base sm:text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
                                Full Article PDF Reader
                            </h2>
                        </div>

                        @if(!empty($pdfUrl))
                            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                                <a 
                                    href="{{ $pdfUrl }}" 
                                    target="_blank" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 rounded-lg transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                                    </svg>
                                    <span>Fullscreen</span>
                                </a>

                                <a 
                                    href="{{ $pdfUrl }}" 
                                    download 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-[#198BEA] hover:bg-[#1476c9] text-white rounded-lg transition shadow-2xs"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <span>Download</span>
                                </a>
                            </div>
                        @endif
                    </div>

                    @if(!empty($pdfUrl))
                        <div class="w-full rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-950">
                            <iframe 
                                src="{{ $pdfUrl }}#toolbar=1&navpanes=0" 
                                class="w-full h-[450px] sm:h-[650px] lg:h-[800px] border-0"
                                loading="lazy"
                            >
                                <div class="p-8 text-center space-y-3">
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Your browser does not support embedded PDF viewing.</p>
                                    <a href="{{ $pdfUrl }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-[#198BEA] text-white rounded-lg font-medium text-xs">
                                        Download PDF Directly
                                    </a>
                                </div>
                            </iframe>
                        </div>
                    @else
                        <div class="py-10 px-4 sm:py-12 sm:px-6 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-800 text-center space-y-3 bg-zinc-50 dark:bg-zinc-950/50">
                            <svg class="w-10 h-10 sm:w-12 sm:h-12 text-zinc-400 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h4 class="text-sm sm:text-base font-semibold text-zinc-800 dark:text-zinc-200">No PDF Uploaded for this Article</h4>
                            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto">
                                The full manuscript file has not been directly uploaded to the repository, but you can read the abstract and access the original publisher page below.
                            </p>
                            @if(!empty($article->doi) || !empty($article->url) || !empty($article->landing_page_url))
                                <a 
                                    href="{{ !empty($article->doi) ? 'https://doi.org/' . $article->doi : ($article->url ?? $article->landing_page_url) }}" 
                                    target="_blank" 
                                    rel="noopener noreferrer" 
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white rounded-xl text-xs font-semibold transition"
                                >
                                    <span>Visit Original Source / Publisher</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

            </div>

            <!-- ================= RIGHT COLUMN: SIDEBAR (35%) ================= -->
            <div class="w-full lg:w-96 space-y-5 lg:space-y-6 shrink-0">
                
                <!-- 0. ARTICLE ACTIONS CARD -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-5 shadow-theme-md space-y-3">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white tracking-tight border-b border-zinc-100 dark:border-zinc-800 pb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span>Article Actions</span>
                    </h3>

                    <div class="space-y-2">
                        <!-- 1. Download Full PDF -->
                        @if(!empty($pdfUrl))
                            <a 
                                href="{{ $pdfUrl }}" 
                                download 
                                target="_blank"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] text-white font-semibold text-xs sm:text-sm rounded-xl shadow-md shadow-[#198BEA]/25 transition active:scale-98"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span>Download Full PDF</span>
                            </a>
                        @endif

                        <div class="grid grid-cols-2 gap-2">
                            <!-- 2. Read Online -->
                            @if(!empty($pdfUrl))
                                <a 
                                    href="#pdf-reader-section" 
                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 transition shadow-2xs"
                                >
                                    <svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span>Read Online</span>
                                </a>
                            @endif

                            <!-- 3. Cite Paper -->
                            <a 
                                href="#citation-section" 
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 transition shadow-2xs {{ empty($pdfUrl) ? 'col-span-2' : '' }}"
                            >
                                <svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                </svg>
                                <span>Cite Paper</span>
                            </a>

                            <!-- 4. Share -->
                            <button 
                                type="button" 
                                @click="$dispatch('open-share-modal', { url: window.location.href, title: @js($article->title) })"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 transition shadow-2xs"
                            >
                                <svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                                <span>Share</span>
                            </button>

                            <!-- 5. Official Publication -->
                            @if(!empty($article->doi) || !empty($article->url) || !empty($article->landing_page_url))
                                @php
                                    $sourceUrl = !empty($article->doi) ? 'https://doi.org/' . $article->doi : ($article->url ?? $article->landing_page_url);
                                @endphp
                                <a 
                                    href="{{ $sourceUrl }}" 
                                    target="_blank" 
                                    rel="noopener noreferrer" 
                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-zinc-50 dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 transition shadow-2xs"
                                >
                                    <span class="truncate">Publication</span>
                                    <svg class="w-3.5 h-3.5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 1. IMPACT METRICS CARD (Rendered when DOI is present) -->
                @if(!empty($cleanDoi))
                    <div 
                        x-data="{
                            doi: @js($cleanDoi),
                            initBadges() {
                                if (window.__dimensions_embed) {
                                    try { window.__dimensions_embed.init(); } catch (e) {}
                                }
                                if (window._altmetric) {
                                    try { window._altmetric.init(); } catch (e) {}
                                }
                                if (window.__scite && typeof window.__scite.insertBadges === 'function') {
                                    try { window.__scite.insertBadges(); } catch (e) {}
                                } else if (window.__SCITE && typeof window.__SCITE.insertBadges === 'function') {
                                    try { window.__SCITE.insertBadges(); } catch (e) {}
                                } else if (window.scite && typeof window.scite.insertBadges === 'function') {
                                    try { window.scite.insertBadges(); } catch (e) {}
                                }
                            }
                        }"
                        x-init="
                            $nextTick(() => {
                                initBadges();
                                setTimeout(() => initBadges(), 300);
                                setTimeout(() => initBadges(), 800);
                                setTimeout(() => initBadges(), 2000);
                            });
                        "
                        class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-6 shadow-theme-md space-y-4"
                    >
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white tracking-tight border-b border-zinc-100 dark:border-zinc-800 pb-3 flex items-center gap-2">
                            <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>Impact Metrics</span>
                        </h3>
                        
                        <!-- Top Badges: Altmetric Donut & Dimensions AI Circle -->
                        <div class="flex items-center justify-center gap-4 sm:gap-6 py-1 flex-wrap min-h-[50px]">
                            <!-- Altmetric Badge -->
                            <div 
                                class="altmetric-embed inline-block" 
                                data-badge-type="donut" 
                                data-doi="{{ $cleanDoi }}"
                                data-badge-popover="bottom"
                                data-hide-no-mentions="false"
                            ></div>

                            <!-- Dimensions AI Citation Badge (badge.dimensions.ai) -->
                            <span 
                                class="__dimensions_badge_embed__ inline-block" 
                                data-doi="{{ $cleanDoi }}"
                                data-style="small_circle" 
                                data-legend="hover-bottom"
                                data-hide-zero-citations="false"
                            ></span>
                        </div>

                        <!-- Bottom Badge: scite_ Smart Citations -->
                        <div class="flex items-center justify-center pt-0.5 overflow-x-auto min-h-[36px]">
                            <div 
                                class="scite-badge" 
                                data-doi="{{ $cleanDoi }}" 
                                data-layout="horizontal" 
                                data-show-labels="false" 
                                data-tooltip-placement="top"
                                data-section-tally-show="false"
                                data-show-zero="true"
                            ></div>
                        </div>
                    </div>
                @endif

                <!-- 2. PUBLICATION & JOURNAL METADATA CARD -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-6 shadow-theme-md space-y-4">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white tracking-tight border-b border-zinc-100 dark:border-zinc-800 pb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span>Publication Metadata</span>
                    </h3>

                    <dl class="space-y-3 text-xs sm:text-sm divide-y divide-zinc-100 dark:divide-zinc-800">
                        <!-- Journal -->
                        @if(!empty($journal))
                            <div class="pt-2.5 flex justify-between gap-2">
                                <dt class="text-zinc-500 dark:text-zinc-400">Journal</dt>
                                <dd class="text-right font-medium text-zinc-900 dark:text-white">{{ $journal }}</dd>
                            </div>
                        @endif

                        <!-- ISSN -->
                        @php
                            $issnVal = $article->issn ?? $article->reviewerJournal?->e_issn ?? $article->reviewerJournal?->p_issn ?? null;
                        @endphp
                        @if(!empty($issnVal))
                            <div class="pt-2.5 flex justify-between gap-2">
                                <dt class="text-zinc-500 dark:text-zinc-400">ISSN</dt>
                                <dd class="text-right font-medium text-zinc-900 dark:text-white font-mono">{{ $issnVal }}</dd>
                            </div>
                        @endif

                        <!-- Volume & Issue -->
                        @if(!empty($article->volume) || !empty($article->issue))
                            <div class="pt-2.5 flex justify-between gap-2">
                                <dt class="text-zinc-500 dark:text-zinc-400">Volume / Issue</dt>
                                <dd class="text-right font-medium text-zinc-900 dark:text-white">
                                    {{ $article->volume ? 'Vol. ' . $article->volume : '' }}
                                    {{ $article->issue ? '(Issue ' . $article->issue . ')' : '' }}
                                </dd>
                            </div>
                        @endif

                        <!-- Page No -->
                        @if(!empty($article->page_no))
                            <div class="pt-2.5 flex justify-between gap-2">
                                <dt class="text-zinc-500 dark:text-zinc-400">Pages</dt>
                                <dd class="text-right font-medium text-zinc-900 dark:text-white font-mono">{{ $article->page_no }}</dd>
                            </div>
                        @endif

                        <!-- Publication Date -->
                        @if(!empty($pubDate))
                            <div class="pt-2.5 flex justify-between gap-2">
                                <dt class="text-zinc-500 dark:text-zinc-400">Date</dt>
                                <dd class="text-right font-medium text-zinc-900 dark:text-white">{{ $pubDate }}</dd>
                            </div>
                        @endif

                        <!-- Serial Number -->
                        @if(!empty($article->serial_number))
                            <div class="pt-2.5 flex justify-between gap-2">
                                <dt class="text-zinc-500 dark:text-zinc-400">Article ID</dt>
                                <dd class="text-right font-mono font-medium text-zinc-900 dark:text-white">{{ $article->serial_number }}</dd>
                            </div>
                        @endif

                        <!-- DOI -->
                        @if(!empty($article->doi))
                            <div class="pt-2.5 flex justify-between gap-2 items-start">
                                <dt class="text-zinc-500 dark:text-zinc-400 shrink-0">DOI</dt>
                                <dd class="text-right font-medium text-zinc-900 dark:text-white font-mono break-all text-xs">
                                    <a 
                                        href="https://doi.org/{{ $article->doi }}" 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        class="text-[#198BEA] dark:text-sky-400 hover:underline"
                                    >
                                        {{ $article->doi }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <!-- 3. HOW TO CITE THIS PUBLICATION CARD -->
                <div 
                    id="citation-section" 
                    x-data="{ 
                        activeTab: 'apa', 
                        copied: false,
                        citations: @js($citations),
                        copyToClipboard() {
                            const text = this.citations[this.activeTab];
                            navigator.clipboard.writeText(text);
                            this.copied = true;
                            setTimeout(() => { this.copied = false }, 2000);
                        }
                    }"
                    class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-6 shadow-theme-md space-y-4"
                >
                    <div class="flex items-center justify-between flex-wrap gap-2 border-b border-zinc-100 dark:border-zinc-800 pb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                            <h3 class="text-base font-bold text-zinc-900 dark:text-white tracking-tight">
                                How to Cite
                            </h3>
                        </div>

                        <!-- 1-Click Copy Button -->
                        <button 
                            type="button" 
                            @click="copyToClipboard()" 
                            class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 transition shadow-2xs"
                        >
                            <svg x-show="!copied" class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied" class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display: none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>

                    <!-- Citation Format Tabs -->
                    <div class="grid grid-cols-4 gap-1 sm:gap-1.5 border-b border-zinc-100 dark:border-zinc-800 pb-2">
                        <button 
                            type="button" 
                            @click="activeTab = 'apa'"
                            :class="activeTab === 'apa' ? 'bg-[#198BEA] text-white shadow-2xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                            class="py-1 text-center text-xs font-semibold rounded-lg transition"
                        >
                            APA
                        </button>
                        <button 
                            type="button" 
                            @click="activeTab = 'mla'"
                            :class="activeTab === 'mla' ? 'bg-[#198BEA] text-white shadow-2xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                            class="py-1 text-center text-xs font-semibold rounded-lg transition"
                        >
                            MLA
                        </button>
                        <button 
                            type="button" 
                            @click="activeTab = 'chicago'"
                            :class="activeTab === 'chicago' ? 'bg-[#198BEA] text-white shadow-2xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                            class="py-1 text-center text-xs font-semibold rounded-lg transition"
                        >
                            Chicago
                        </button>
                        <button 
                            type="button" 
                            @click="activeTab = 'bibtex'"
                            :class="activeTab === 'bibtex' ? 'bg-[#198BEA] text-white shadow-2xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                            class="py-1 text-center text-xs font-semibold rounded-lg transition"
                        >
                            BibTeX
                        </button>
                    </div>

                    <!-- Citation Text Container -->
                    <div class="p-3 sm:p-3.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200/80 dark:border-zinc-800 rounded-xl font-mono text-xs text-zinc-800 dark:text-zinc-200 leading-relaxed whitespace-pre-wrap select-all break-words max-h-56 overflow-y-auto">
                        <span x-text="citations[activeTab]"></span>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Dimensions AI, Altmetric & scite_ External Embed Scripts -->
    <script async src="https://badge.dimensions.ai/badge.js" charset="utf-8"></script>
    <script async type="text/javascript" src="https://d1bxh8uas1mnw7.cloudfront.net/assets/embed.js"></script>
    <script async type="application/javascript" src="https://cdn.scite.ai/badge/scite-badge-latest.min.js"></script>
</div>