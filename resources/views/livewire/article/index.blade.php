<?php

use App\Models\Article;
use App\Models\Publication;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public int $perPage = 10;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sortBy = '';

    #[Url(except: 'all')]
    public string $filterOption = 'all';

    public function mount(): void
    {
        if (session()->has('articles_per_page')) {
            $this->perPage = max(10, (int) session('articles_per_page'));
        }
    }

    public function updatingSearch(): void
    {
        $this->perPage = 10;
        session(['articles_per_page' => 10]);
    }

    public function updatingSortBy(): void
    {
        $this->perPage = 10;
        session(['articles_per_page' => 10]);
    }

    public function updatingFilterOption(): void
    {
        $this->perPage = 10;
        session(['articles_per_page' => 10]);
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
        session(['articles_per_page' => $this->perPage]);
    }

    public function with(): array
    {
        $query = Publication::query()
            ->select([
                '_id',
                'title',
                'slug',
                'authors',
                'registered_co_author',
                'unregistered_co_author',
                'type',
                'article_type',
                'option',
                'journal_name',
                'journal_title',
                'volume',
                'issue',
                'issn',
                'doi',
                'citation',
                'publication_keywords',
                'keywords',
                'description',
                'view_counts',
                'published_date',
                'publication_month_year',
                'status',
                'created_at',
            ])
            ->with(['reviewerJournal' => fn ($q) => $q->select(['_id', 'journal_title', 'title'])])
            ->whereIn('status', [1, '1'])
            ->whereNotNull('title')
            ->where('title', '!=', '');

        $search = trim((string) ($this->search ?? ''));
        if ($search !== '' && mb_strlen($search) >= 3) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        if ($this->filterOption !== 'all') {
            $query->where('option', $this->filterOption);
        }

        switch ($this->sortBy) {
            case 'citation_desc':
                $query->orderByDesc('citation');
                break;
            case 'year_desc':
                $query->orderByDesc('published_date');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'oldest':
                $query->oldest();
                break;
            case 'title_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'views_desc':
                $query->orderByDesc('view_counts');
                break;
            default:
                $query->oldest();
                break;
        }

        // Single-pass check: fetch perPage + 1 to determine if more pages exist with zero count() queries
        $results = $query->limit($this->perPage + 1)->get();
        $hasMore = $results->count() > $this->perPage;
        $articles = $hasMore ? $results->slice(0, $this->perPage) : $results;

        // Zero N+1: Batch fetch all registered authors in one single indexed query
        $coAuthorIds = $articles->pluck('registered_co_author')
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
                ->select(['_id', 'first_name', 'last_name', 'slug', 'fullname'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        // Zero N+1: Batch fetch article types from article_type collection
        $typeIds = $articles->pluck('type')
            ->filter()
            ->map(fn ($id) => is_string($id) ? trim($id) : (is_object($id) ? (string) $id : ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $articleTypes = !empty($typeIds)
            ? Article::query()
                ->whereIn('_id', $typeIds)
                ->select(['_id', 'name'])
                ->get()
                ->keyBy(fn ($a) => (string) $a->_id)
            : collect();

        return [
            'articles' => $articles,
            'hasMore' => $hasMore,
            'registeredUsers' => $registeredUsers,
            'articleTypes' => $articleTypes,
        ];
    }
}; ?>

<div
    x-data="{
        init() {
            this.restoreSnapshot();
        },
        saveSnapshot(articleId) {
            const listEl = document.getElementById('articles-list-container');
            if (listEl) {
                sessionStorage.setItem('articles_dom_cache', listEl.innerHTML);
                sessionStorage.setItem('articles_scroll_y', window.scrollY.toString());
                sessionStorage.setItem('last_article_id', articleId);
                sessionStorage.setItem('articles_cache_time', Date.now().toString());
            }
        },
        restoreSnapshot() {
            const domCache = sessionStorage.getItem('articles_dom_cache');
            const cacheTime = sessionStorage.getItem('articles_cache_time');
            const lastId = sessionStorage.getItem('last_article_id');
            const scrollY = sessionStorage.getItem('articles_scroll_y');

            // 1. Instantly inject cached HTML before browser paint
            if (domCache && cacheTime && (Date.now() - parseInt(cacheTime, 10) < 30 * 60 * 1000)) {
                const listEl = document.getElementById('articles-list-container');
                if (listEl) {
                    listEl.innerHTML = domCache;
                }
            }

            // 2. Instantly freeze viewport at the exact scroll position (zero animation / zero movement)
            if (scrollY && parseInt(scrollY, 10) > 0) {
                const targetY = parseInt(scrollY, 10);
                window.scrollTo({ top: targetY, behavior: 'instant' });

                this.$nextTick(() => {
                    window.scrollTo({ top: targetY, behavior: 'instant' });
                    sessionStorage.removeItem('last_article_id');
                    sessionStorage.removeItem('articles_scroll_y');
                });
            } else if (lastId) {
                const el = document.getElementById('article-card-' + lastId);
                if (el) {
                    el.scrollIntoView({ behavior: 'instant', block: 'center' });
                    sessionStorage.removeItem('last_article_id');
                }
            }
        },
        clearCache() {
            sessionStorage.removeItem('articles_dom_cache');
            sessionStorage.removeItem('articles_cache_time');
            sessionStorage.removeItem('last_article_id');
            sessionStorage.removeItem('articles_scroll_y');
        }
    }"
    x-init="init()"
>
    <!-- ================= TOP HEADER HERO BANNER ================= -->
    <div class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200/90 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center gap-4">
            <!-- Left Icon Badge -->
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-[#198BEA] text-white flex items-center justify-center shrink-0 shadow-md shadow-[#198BEA]/25">
                <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>

            <!-- Header Content -->
            <div class="space-y-0.5">
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">
                    Published & Preprint Articles
                </h1>
                <p class="text-xs sm:text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Explore research papers, preprints, and open access publications
                </p>
                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed pt-0.5">
                    Discover global research output, review preprints, and cite verified publications. 
                    <span class="font-bold text-[#198BEA] dark:text-sky-400">Discover. Cite. Connect.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================= TWO COLUMN MAIN CONTENT ================= -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- LEFT CONTAINER: ARTICLES LIST -->
            <div class="flex-1 min-w-0 w-full space-y-6">
                
                <!-- Search & Sort Bar (Matching Screenshot UI) -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <!-- Search Input Box -->
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-zinc-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="search" 
                            @input="clearCache()"
                            placeholder="Search (Enter at least 3 characters...)" 
                            class="w-full h-11 pl-11 pr-4 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-600 rounded-full text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 dark:focus:ring-brand/30 shadow-xs transition"
                        />
                    </div>

                    <!-- Sort By Dropdown -->
                    <div class="sm:w-72">
                        <select 
                            wire:model.live="sortBy"
                            @change="clearCache()"
                            class="w-full h-11 px-4 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-600 rounded-full text-sm text-zinc-800 dark:text-zinc-200 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 dark:focus:ring-brand/30 shadow-xs cursor-pointer transition appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236b7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:9px_9px] bg-[right_16px_center] bg-no-repeat pr-10"
                        >
                            <option value="">Sort By</option>
                            <option value="citation_desc">Citation count (High to Low)</option>
                            <option value="year_desc">Year of publication (High to Low)</option>
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="title_asc">Title (A-Z)</option>
                            <option value="title_desc">Title (Z-A)</option>
                            <option value="views_desc">Most viewed</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Option Pills (All / Published / Preprints) -->
                <div class="flex items-center gap-2">
                    <button 
                        type="button" 
                        wire:click="$set('filterOption', 'all')"
                        @click="clearCache()"
                        class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $filterOption === 'all' ? 'bg-[#198BEA] text-white shadow-xs' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50' }}"
                    >
                        All Articles
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('filterOption', 'published')"
                        @click="clearCache()"
                        class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $filterOption === 'published' ? 'bg-[#198BEA] text-white shadow-xs' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50' }}"
                    >
                        Published
                    </button>
                    <button 
                        type="button" 
                        wire:click="$set('filterOption', 'preprint')"
                        @click="clearCache()"
                        class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $filterOption === 'preprint' ? 'bg-[#198BEA] text-white shadow-xs' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50' }}"
                    >
                        Preprints
                    </button>
                </div>

                <!-- Loading State Indicator (Positioned Above Article Cards) -->
                <div 
                    wire:loading.flex 
                    wire:target="search, sortBy, filterOption" 
                    class="items-center justify-center gap-2.5 py-2.5 px-4 bg-[#198BEA]/10 dark:bg-[#198BEA]/15 border border-[#198BEA]/20 dark:border-[#198BEA]/30 rounded-xl text-xs font-semibold text-[#198BEA] dark:text-sky-400 transition"
                >
                    <svg class="animate-spin w-4 h-4 text-[#198BEA]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Loading articles...</span>
                </div>

                <!-- Articles Grid / List Container with Livewire Loading Transition -->
                <div 
                    id="articles-list-container"
                    wire:loading.class="opacity-60 pointer-events-none" 
                    wire:target="search, sortBy, filterOption" 
                    class="space-y-4 transition-opacity duration-200"
                >
                    @forelse ($articles as $article)
                        @php
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
                                                'is_registered' => true,
                                            ];
                                        }
                                    }
                                }
                            }
                            if (!empty($article->unregistered_co_author) && is_array($article->unregistered_co_author)) {
                                foreach ($article->unregistered_co_author as $unreg) {
                                    $name = is_array($unreg) ? ($unreg['name'] ?? '') : (is_string($unreg) ? $unreg : '');
                                    if (trim($name)) {
                                        $authorList[] = [
                                            'name' => trim($name),
                                            'slug' => null,
                                            'is_registered' => false,
                                        ];
                                    }
                                }
                            }
                            if (empty($authorList) && !empty($article->authors) && is_array($article->authors)) {
                                foreach ($article->authors as $auth) {
                                    $name = is_array($auth) ? ($auth['name'] ?? '') : (is_string($auth) ? $auth : '');
                                    if (trim($name)) {
                                        $authorList[] = [
                                            'name' => trim($name),
                                            'slug' => null,
                                            'is_registered' => false,
                                        ];
                                    }
                                }
                            }
                        @endphp

                        <div wire:key="article-{{ $article->_id }}" id="article-card-{{ $article->_id }}" class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-5 sm:p-6 shadow-theme-md hover:shadow-theme-dark transition-all duration-300 space-y-3.5">
                            
                            <!-- 1. Article Title with Document Icon -->
                            <div class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-zinc-900 dark:text-zinc-100 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                @if(!empty($article->slug))
                                    <h2 class="text-base sm:text-lg font-semibold text-zinc-900 dark:text-white leading-snug tracking-tight hover:text-[#198BEA] transition-colors">
                                        <a 
                                            href="{{ route('publication.detail', ['slug' => $article->slug]) }}" 
                                            @click="saveSnapshot('{{ $article->_id }}')"
                                            wire:navigate
                                        >
                                            {{ $article->title }}
                                        </a>
                                    </h2>
                                @else
                                    <h2 class="text-base sm:text-lg font-semibold text-zinc-900 dark:text-white leading-snug tracking-tight">
                                        {{ $article->title }}
                                    </h2>
                                @endif
                            </div>

                            <!-- 2. Authors & Article Type Badge -->
                            <div class="flex flex-wrap items-center gap-2.5 text-xs sm:text-sm">
                                <div class="flex flex-wrap items-center gap-1.5 text-zinc-800 dark:text-zinc-200">
                                    <svg class="w-4 h-4 text-purple-700 dark:text-purple-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="font-bold text-zinc-800 dark:text-zinc-200">Authors:</span>
                                    @if(!empty($authorList))
                                        <span class="text-zinc-600 dark:text-zinc-300 font-normal inline-flex flex-wrap items-center gap-x-1">
                                            @foreach($authorList as $idx => $auth)
                                                @if(!empty($auth['slug']))
                                                    <a 
                                                        href="{{ route('userscholar', ['slug' => $auth['slug']]) }}" 
                                                        wire:navigate 
                                                        class="text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] dark:hover:text-sky-400 font-normal no-underline transition-colors"
                                                    >{{ $auth['name'] }}</a>
                                                @else
                                                    <span>{{ $auth['name'] }}</span>
                                                @endif
                                                @if($idx < count($authorList) - 1)
                                                    <span class="text-zinc-400 dark:text-zinc-500 mr-0.5">,</span>
                                                @endif
                                            @endforeach
                                        </span>
                                    @else
                                        <span class="text-zinc-500 dark:text-zinc-400 font-normal">No authors available</span>
                                    @endif
                                </div>

                                @php
                                    $displayType = null;
                                    if (!empty($article->article_type)) {
                                        $displayType = ucwords(str_replace(['_', '-'], ' ', (string) $article->article_type));
                                    } elseif (!empty($article->type)) {
                                        $typeModel = $articleTypes[(string) $article->type] ?? null;
                                        if ($typeModel && !empty($typeModel->name)) {
                                            $displayType = $typeModel->name;
                                        }
                                    }

                                    if (empty($displayType)) {
                                        $displayType = 'Research Article';
                                    }
                                @endphp

                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/80">
                                        {{ $displayType }}
                                    </span>
                            </div>

                            <!-- 3. Journal Name with Book Icon and Link Icon -->
                            @php
                                $journalName = $article->reviewerJournal?->journal_title;
                            @endphp

                            @if(!empty($journalName))
                                <div class="flex flex-wrap items-center gap-1.5 text-xs sm:text-sm text-zinc-800 dark:text-zinc-200">
                                    <svg class="w-4 h-4 text-sky-500 dark:text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <span class="font-bold text-zinc-800 dark:text-zinc-200">Journal Name:</span>
                                    <span class="text-zinc-700 dark:text-zinc-300 font-medium">
                                        {{ $journalName }}
                                    </span>
                                    <a href="#" class="text-zinc-700 dark:text-zinc-300 hover:text-[#198BEA] transition-colors inline-flex items-center ml-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                    </a>
                                </div>
                            @endif

                            <!-- 4. Metadata Row: Volume • Issue • ISSN • DOI • Citations -->
                            @php
                                $metaItems = [];
                                if (!empty($article->volume)) {
                                    $metaItems[] = 'Volume ' . $article->volume;
                                }
                                if (!empty($article->issue)) {
                                    $metaItems[] = 'Issue ' . $article->issue;
                                }
                                if (!empty($article->issn)) {
                                    $metaItems[] = 'ISSN ' . $article->issn;
                                }
                                if (!empty($article->doi)) {
                                    $metaItems[] = 'DOI: ' . $article->doi;
                                }
                                if (!empty($article->citation)) {
                                    $metaItems[] = 'Citations: ' . $article->citation;
                                }
                            @endphp

                            @if(!empty($metaItems))
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">
                                    @foreach($metaItems as $index => $item)
                                        @if($index > 0)
                                            <span>•</span>
                                        @endif
                                        <span>{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <!-- 5. Keywords Row -->
                            @php
                                $rawKeywords = $article->publication_keywords ?? $article->keywords ?? [];
                                if (is_string($rawKeywords)) {
                                    $rawKeywords = array_filter(array_map('trim', preg_split('/[,;]+/', $rawKeywords)));
                                }
                                $keywordsList = (!empty($rawKeywords) && is_array($rawKeywords)) 
                                    ? array_values(array_filter($rawKeywords)) 
                                    : [];
                                $visibleKeywords = array_slice($keywordsList, 0, 5);
                                $remainingCount = count($keywordsList) - count($visibleKeywords);
                            @endphp

                            @if(!empty($keywordsList))
                                <div x-data="{ expanded: false }" class="flex flex-wrap items-center gap-2 pt-0.5">
                                    <template x-if="!expanded">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @foreach ($visibleKeywords as $kw)
                                                <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-medium bg-[#eaf5ff] dark:bg-sky-950/50 text-[#198BEA] dark:text-sky-400 border border-sky-100 dark:border-sky-900/50 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white hover:border-[#198BEA] transition-colors cursor-pointer">
                                                    {{ $kw }}
                                                </span>
                                            @endforeach
                                            @if($remainingCount > 0)
                                                <button 
                                                    type="button" 
                                                    @click="expanded = true"
                                                    class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 underline underline-offset-2 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors cursor-pointer pl-1"
                                                >
                                                    +{{ $remainingCount }} more
                                                </button>
                                            @endif
                                        </div>
                                    </template>
                                    
                                    <template x-if="expanded">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @foreach ($keywordsList as $kw)
                                                <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-medium bg-[#eaf5ff] dark:bg-sky-950/50 text-[#198BEA] dark:text-sky-400 border border-sky-100 dark:border-sky-900/50 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white hover:border-[#198BEA] transition-colors cursor-pointer">
                                                    {{ $kw }}
                                                </span>
                                            @endforeach
                                            <button 
                                                type="button" 
                                                @click="expanded = false"
                                                class="text-xs font-semibold text-zinc-500 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors cursor-pointer pl-1"
                                            >
                                                Show less
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            @endif

                            <!-- 6. Abstract Preview -->
                            @php
                                $abstractText = $article->description ?: $article->description;
                            @endphp
                            @if(!empty($abstractText))
                                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 line-clamp-3 leading-relaxed">
                                    {{ $abstractText }}
                                </p>
                            @endif

                            <!-- 7. Bottom Action Row: View Full Details (Left) + Share & Views (Right) -->
                            <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                                @if(!empty($article->slug))
                                    <a 
                                        href="{{ route('publication.detail', ['slug' => $article->slug]) }}" 
                                        @click="saveSnapshot('{{ $article->_id }}')"
                                        wire:navigate
                                        class="inline-flex items-center gap-2 px-4 sm:px-5 py-2 sm:py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs sm:text-sm font-semibold rounded-lg shadow-sm hover:shadow transition-all cursor-pointer"
                                    >
                                        <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                        <span>View Full Details</span>
                                    </a>
                                @else
                                    <button 
                                        type="button" 
                                        class="inline-flex items-center gap-2 px-4 sm:px-5 py-2 sm:py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs sm:text-sm font-semibold rounded-lg shadow-sm hover:shadow transition-all cursor-pointer opacity-70"
                                    >
                                        <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                        <span>View Full Details</span>
                                    </button>
                                @endif

                                <div class="flex items-center gap-4 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400">
                                    @php
                                        $shareUrl = !empty($article->slug) 
                                            ? route('publication.detail', ['slug' => $article->slug]) 
                                            : url('/articles');
                                    @endphp
                                    <button 
                                        type="button" 
                                        @click="$dispatch('open-share-modal', { url: @js($shareUrl), title: @js($article->title) })"
                                        class="inline-flex items-center gap-1.5 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors cursor-pointer"
                                    >
                                        <svg class="w-4 h-4 text-zinc-700 dark:text-zinc-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                        </svg>
                                        <span class="font-medium">Share</span>
                                    </button>

                                    <div class="inline-flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                        <svg class="w-4 h-4 text-zinc-700 dark:text-zinc-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span class="font-medium">{{ $article->view_counts ?? $article->views ?? 0 }} Views</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    @empty
                        <!-- Empty State when no articles match search or filters -->
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-10 text-center shadow-theme-md space-y-3">
                            <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                <svg class="w-6 h-6 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                                No Articles Found
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto">
                                We couldn't find any articles matching your search or filter criteria. Try searching with different keywords or clearing your filters.
                            </p>
                            @if (!empty($search) || $filterOption !== 'all' || !empty($sortBy))
                                <div class="pt-2">
                                    <button 
                                        type="button" 
                                        wire:click="$set('search', ''); $set('filterOption', 'all'); $set('sortBy', '')"
                                        @click="clearCache()"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-lg shadow-sm transition-colors cursor-pointer"
                                    >
                                        <span>Reset search & filters</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforelse
                </div>

                <!-- Load More Articles Pill Button (Shown when more pages exist) -->
                @if ($hasMore)
                    <div class="flex justify-center pt-6 pb-4">
                        <button 
                            type="button" 
                            wire:click="loadMore"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center gap-2 px-8 py-2.5 rounded-full bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] disabled:opacity-70 text-white text-sm font-semibold shadow-md shadow-[#198BEA]/25 hover:shadow-lg hover:shadow-[#198BEA]/30 active:scale-[0.98] transition-all cursor-pointer"
                        >
                            <svg wire:loading wire:target="loadMore" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="loadMore">Load More Articles</span>
                            <span wire:loading wire:target="loadMore">Loading more articles...</span>
                        </button>
                    </div>
                @endif

            </div>

            <!-- RIGHT CONTAINER: REUSABLE SIDEBAR BANNERS -->
            <x-article.sidebar />

        </div>
    </div>
</div>
