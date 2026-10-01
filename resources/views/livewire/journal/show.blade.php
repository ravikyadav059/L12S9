<?php

use App\Models\Country;
use App\Models\IndexingAgency;
use App\Models\Publication;
use App\Models\RequestReviewPaper;
use App\Models\ReviewerJournal;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';

    public int $publicationsLimit = 10;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function loadMorePublications(): void
    {
        $this->publicationsLimit += 10;
    }

    public function with(): array
    {
        // 1. Fetch Journal by slug or fallback to _id
        $journal = ReviewerJournal::query()
            ->where('slug', $this->slug)
            ->whereIn('status', [1, '1', true])
            ->first();

        if (! $journal) {
            $journal = ReviewerJournal::query()
                ->where('_id', $this->slug)
                ->whereIn('status', [1, '1', true])
                ->firstOrFail();
        }

        // 2. Fetch Active Indexing Agencies with Images
        $indexingAgencies = Cache::remember('indexing_agencies_active_all', 86400, function () {
            return IndexingAgency::whereIn('status', [1, '1', true])
                ->orderBy('serial_number')
                ->select(['agency_name', 'image', 'status', 'serial_number'])
                ->get();
        });

        // 3. Process Indexed Agencies for this Journal
        $matchedIndexers = [];
        $allowedTopAgencies = ['Scopus', 'WoS', 'Web of Science', 'UGC CARE', 'PubMed', 'DOAJ', 'Google Scholar', 'Crossref'];
        $indexedInTop = [];
        $notIndexedInTop = [];

        foreach ($indexingAgencies as $agency) {
            $fieldName = str_replace(' ', '_', $agency->agency_name);
            $checkboxName = $fieldName . '_checkbox';

            $isChecked = ! empty($journal->{$checkboxName}) && in_array($journal->{$checkboxName}, [1, '1', true]);
            $rawVal = $journal->{$fieldName} ?? null;
            $hasVal = ! empty($rawVal);

            if ($isChecked || $hasVal) {
                $extUrl = $hasVal ? (str_starts_with((string) $rawVal, 'http') ? (string) $rawVal : 'https://' . ltrim((string) $rawVal, '/')) : '#';
                $img = trim((string) ($agency->image ?? ''));
                $img = preg_replace('#^https?://[^/]+/#', '', $img);
                $imgUrl = $img !== '' ? asset($img) : null;

                $matchedIndexers[] = [
                    'name' => $agency->agency_name,
                    'image' => $imgUrl,
                    'initial' => mb_substr($agency->agency_name, 0, 1),
                    'url' => $extUrl,
                    'has_url' => $hasVal && $extUrl !== '#',
                ];

                if (in_array($agency->agency_name, $allowedTopAgencies)) {
                    $indexedInTop[] = $agency->agency_name;
                }
            } else {
                if (in_array($agency->agency_name, $allowedTopAgencies)) {
                    $notIndexedInTop[] = $agency->agency_name;
                }
            }
        }

        // 4. Fetch Publications belonging to this journal (Bounded query)
        $publications = Publication::query()
            ->where(function ($q) use ($journal) {
                $q->where('journal_title', (string) $journal->_id);
                    // ->orWhere('journal_title', $journal->journal_title)
                    // ->orWhere('journal_name', $journal->journal_title);
            })
            ->whereIn('status', [1, '1', true])
            ->select([
                '_id',
                'title',
                'slug',
                'description',
                'published_date',
                'publication_month_year',
                'doi',
                'citations',
                'volume',
                'issue',
                'page_no',
                'authors',
                'registered_co_author',
                'unregistered_co_author',
                'journal_name',
                'journal_title',
                'created_at',
            ])
            ->latest()
            ->take($this->publicationsLimit + 1)
            ->get();

        $hasMorePublications = $publications->count() > $this->publicationsLimit;
        $publications = $hasMorePublications ? $publications->slice(0, $this->publicationsLimit) : $publications;

        // Batch fetch registered co-authors (Zero N+1)
        $coAuthorIds = $publications->pluck('registered_co_author')
            ->flatten()
            ->filter()
            ->map(fn ($id) => is_string($id) ? trim($id) : (is_object($id) ? (string) $id : ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $registeredUsers = ! empty($coAuthorIds)
            ? User::query()
                ->whereIn('_id', $coAuthorIds)
                ->select(['_id', 'first_name', 'last_name', 'slug', 'fullname'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        // 5. Fetch Roles in this Research Journal (Editorial Board & Reviewers)
        $rolesInJournal = RoleInResearchJournals::query()
            ->where(function ($q) use ($journal) {
                $q->where('journal_title', (string) $journal->_id)
                    ->orWhere('journal_title', $journal->journal_title);
            })
            ->whereIn('status', [1, '1', true])
            ->with([
                'user' => function ($q) {
                    $q->select(['_id', 'first_name', 'last_name', 'slug', 'fullname', 'photo']);
                },
                'journalRole' => function ($q) {
                    $q->select(['_id', 'name', 'status']);
                },
            ])
            ->get();

        // 6. Fetch Transparent Peer Review Papers for this Journal
        $reviewPapers = RequestReviewPaper::query()
            ->where('journal_id', (string) $journal->_id)
            ->where('status', '1')
            ->where('openlinkstatus', '1')
            ->select([
                '_id',
                'paper_title',
                'paper_keywords',
                'authors_name_only',
                'registered_co_author',
                'unregistered_co_author',
                'abstract',
                'deadline',
                'paper_number',
                'research_category',
                'research_type',
                'is_accepted',
                'created_at',
            ])
            ->latest()
            ->take(10)
            ->get();

        // 7. Resolve Country Name from Country Model
        $countryName = $journal->countryName();

        return [
            'journal' => $journal,
            'matchedIndexers' => $matchedIndexers,
            'indexedInTop' => $indexedInTop,
            'notIndexedInTop' => $notIndexedInTop,
            'publications' => $publications,
            'registeredUsers' => $registeredUsers,
            'rolesInJournal' => $rolesInJournal,
            'hasMorePublications' => $hasMorePublications,
            'reviewPapers' => $reviewPapers,
            'countryName' => $countryName,
        ];
    }
}; ?>

@php
    $title = $journal->journal_title ?: $journal->title;
    $shortName = $journal->journal_short_name ?? '';
    $hasPhoto = !empty($journal->journal_photo);
    $subjects = is_array($journal->journal_subjects) ? $journal->journal_subjects : (!empty($journal->journal_subjects) ? [$journal->journal_subjects] : []);
    $isPeerReviewed = !empty($journal->followpeer) && in_array($journal->followpeer, [1, '1', true]);
    $isOpenAccess = !empty($journal->JournalOpenAccess) && in_array($journal->JournalOpenAccess, [1, '1', true]);
    $hasDoi = !empty($journal->DOI) && in_array($journal->DOI, [1, '1', true]);
    $hasApc = !empty($journal->feesAssociatedWithPublishing) && in_array($journal->feesAssociatedWithPublishing, [1, '1', true]);
    $websiteUrl = !empty($journal->journal_webiste_url) ? (str_starts_with($journal->journal_webiste_url, 'http') ? $journal->journal_webiste_url : 'https://' . $journal->journal_webiste_url) : null;
    $words = explode(' ', trim($title));
    $monogram = mb_substr(strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', $words))), 0, 5);
@endphp

<div 
    x-data="{
        disclaimerOpen: false,
        tagsExpanded: false,
        indexersExpanded: false,
        rolesExpanded: false,
        claimModalOpen: false
    }"
    class="min-h-screen bg-zinc-50/70 dark:bg-zinc-950 font-sans pb-8 sm:pb-12"
>
    <!-- Structured JSON-LD Data for SEO -->
    @php
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Periodical',
            'name' => $title,
            'alternateName' => $shortName ?: null,
            'issn' => array_values(array_filter([$journal->e_issn ?? null, $journal->p_issn ?? null])),
            'publisher' => !empty($journal->organization_name) ? [
                '@type' => 'Organization',
                'name' => $journal->organization_name,
            ] : null,
            'url' => url()->current(),
            'description' => Str::limit(strip_tags($journal->journal_description ?? ''), 250),
        ];
    @endphp
    <script type="application/ld+json">
    {!! json_encode(array_filter($jsonLd), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <!-- ================= TOP STICKY BAR: BACK BUTTON, TITLE ON SCROLL & SHARE BUTTON ================= -->
    <div 
        x-data="{ 
            showStickyTitle: false,
            checkScroll() {
                const titleEl = document.getElementById('hero-journal-title');
                if (titleEl) {
                    const rect = titleEl.getBoundingClientRect();
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 sm:py-3 flex items-center justify-between gap-3 text-xs sm:text-sm">
            <!-- Left: Back Button -->
            <a 
                href="{{ route('journals.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white rounded-lg transition-all shadow-2xs shrink-0 cursor-pointer group"
            >
                <flux:icon name="arrow-left" class="size-3.5 transition-transform group-hover:-translate-x-0.5" />
                <span class="hidden sm:inline">Back to Journals</span>
                <span class="sm:hidden">Back</span>
            </a>

            <!-- Center: Sticky Journal Title (Revealed when hero title scrolls out of view) -->
            <div 
                x-show="showStickyTitle"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-2"
                class="flex items-center gap-2 min-w-0 flex-1 border-l border-zinc-200 dark:border-zinc-800 pl-2.5 sm:pl-4"
                style="display: none;"
            >
                <div class="w-5 h-5 rounded-md text-white flex items-center justify-center text-[9px] font-bold shrink-0 shadow-2xs" style="background: #006EC9;">
                    {{ mb_substr($monogram, 0, 2) }}
                </div>
                <span class="text-zinc-900 dark:text-zinc-100 font-semibold text-xs sm:text-sm truncate">
                    {{ $title }}
                    @if ($shortName)
                        <span class="text-zinc-500 dark:text-zinc-400 font-normal">({{ $shortName }})</span>
                    @endif
                </span>
            </div>

            <!-- Right: Share Action Button (Always Accessible) -->
            <button 
                type="button" 
                @click="$dispatch('open-share-modal', { url: @js(url('journal/' . $journal->slug)), title: @js($title), type: 'journal', header: 'Share Journal', subtitle: 'Share this journal across networks' })"
                class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-[#198BEA] hover:text-white dark:hover:bg-[#198BEA] dark:hover:text-white text-zinc-700 dark:text-zinc-300 text-xs font-semibold transition shadow-2xs shrink-0 cursor-pointer ml-auto"
                title="Share this Journal"
            >
                <flux:icon name="share" class="size-3.5" />
                <span class="hidden sm:inline">Share</span>
            </button>
        </div>
    </div>

    <!-- Breadcrumb Navigation Bar -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-1">
        <nav class="flex items-center gap-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
            <a href="{{ url('/') }}" class="hover:text-[#198BEA] dark:hover:text-sky-400 transition" wire:navigate>Home</a>
            <span class="text-zinc-400">/</span>
            <a href="{{ route('journals.index') }}" class="hover:text-[#198BEA] dark:hover:text-sky-400 transition" wire:navigate>Journals</a>
            <span class="text-zinc-400">/</span>
            <span class="text-zinc-800 dark:text-zinc-200 font-medium truncate max-w-[200px] sm:max-w-md">{{ $title }}</span>
        </nav>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- 1. Journal Header Card -->
        <section class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-theme-md">
            <div class="flex flex-col md:flex-row items-start gap-6 lg:gap-8">
                
                <!-- Left: Journal Cover Image / Initials Monogram -->
                <div class="shrink-0 mx-auto md:mx-0">
                    @if ($hasPhoto)
                        <img 
                            src="{{ asset($journal->journal_photo) }}" 
                            alt="Cover Photo for {{ $title }}" 
                            class="w-28 sm:w-32 h-36 sm:h-44 rounded-xl object-contain bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-xs"
                            loading="lazy"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                        />
                        <div class="hidden w-28 sm:w-32 h-36 sm:h-44 rounded-xl shrink-0 flex-col items-center justify-center font-bold text-lg text-white shadow-xs text-center p-3" style="background: #006EC9;">
                            {{ $monogram }}
                        </div>
                    @else
                        <div class="w-28 sm:w-32 h-36 sm:h-44 rounded-xl shrink-0 flex items-center justify-center font-bold text-lg text-white shadow-xs text-center p-3" style="background: #006EC9;">
                            {{ $monogram }}
                        </div>
                    @endif
                </div>

                <!-- Right: Header Details -->
                <div class="flex-1 min-w-0 space-y-4">
                    
                    <!-- Badges -->
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isPeerReviewed)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800">
                                <flux:icon name="check" class="size-3.5 text-emerald-500" />
                                <span>Peer reviewed only</span>
                            </span>
                        @endif

                        @if ($isOpenAccess)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-[#198BEA] dark:bg-sky-950/50 dark:text-sky-300 border border-sky-200/60 dark:border-sky-800">
                                <flux:icon name="lock-open" class="size-3.5 text-[#198BEA]" />
                                <span>Open Access</span>
                            </span>
                        @endif
                    </div>

                    <!-- Journal Title & Short Name -->
                    <div>
                        <h1 id="hero-journal-title" class="text-lg sm:text-xl lg:text-2xl font-bold text-zinc-900 dark:text-white leading-tight">
                            {{ $title }}
                            @if ($shortName)
                                <span class="text-zinc-500 dark:text-zinc-400 font-medium">({{ $shortName }})</span>
                            @endif
                        </h1>

                        <!-- Publisher Name -->
                        @if (!empty($journal->organization_name))
                            <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                <span class="font-medium text-zinc-500 dark:text-zinc-400">Publisher:</span>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100 ml-1">{{ $journal->organization_name }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Disciplines / Subjects Pills with Alpine Expand -->
                    @if (!empty($subjects))
                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            @foreach ($subjects as $idx => $subject)
                                <span 
                                    x-show="tagsExpanded || {{ $idx < 4 ? 'true' : 'false' }}"
                                    class="inline-block px-2.5 py-1 text-xs font-medium bg-[#198BEA]/10 text-[#198BEA] dark:bg-[#198BEA]/20 dark:text-sky-300 rounded-full"
                                >
                                    {{ $subject }}
                                </span>
                            @endforeach

                            @if (count($subjects) > 4)
                                <button 
                                    type="button" 
                                    @click="tagsExpanded = !tagsExpanded" 
                                    class="text-xs font-semibold text-[#198BEA] hover:underline cursor-pointer ml-1"
                                >
                                    <span x-show="!tagsExpanded">+{{ count($subjects) - 4 }} More</span>
                                    <span x-show="tagsExpanded" style="display:none;">Show Less</span>
                                </button>
                            @endif
                        </div>
                    @endif

                    <!-- Action Buttons Bar -->
                    <div class="flex flex-wrap items-center gap-3 pt-3">
                        @if (!empty($journal->user_id))
                            <a 
                                href="{{ Route::has('journals_submit_paper') ? route('journals_submit_paper', ['slug' => $journal->slug]) : '#' }}" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold shadow-sm transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer"
                            >
                                <flux:icon name="plus" class="size-4" />
                                <span>Submit Paper</span>
                            </a>
                        @else
                            <button 
                                type="button" 
                                @click="claimModalOpen = true" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold shadow-sm transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer"
                            >
                                <flux:icon name="check-circle" class="size-4" />
                                <span>Claim Your Journal</span>
                            </button>
                        @endif

                        @if (!empty($journal->user_id))
                            <a 
                                href="{{ Route::has('journal_certificate') ? route('journal_certificate', ['slug' => $journal->slug]) : '#' }}" 
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 hover:border-[#198BEA] text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] text-xs font-semibold transition cursor-pointer"
                            >
                                <flux:icon name="check-badge" class="size-4" />
                                <span>View PRC</span>
                            </a>
                        @else
                            <a 
                                href="{{ Route::has('publisher_register') ? route('publisher_register') : '#' }}" 
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 hover:border-[#198BEA] text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] text-xs font-semibold transition cursor-pointer"
                            >
                                <flux:icon name="plus-circle" class="size-4" />
                                <span>Get PRC</span>
                            </a>
                        @endif

                        @if ($websiteUrl)
                            <a 
                                href="{{ $websiteUrl }}" 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-zinc-300 dark:border-zinc-700 hover:border-[#198BEA] text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] text-xs font-semibold transition cursor-pointer"
                            >
                                <span>Visit Website</span>
                                <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                            </a>
                        @endif
                    </div>

                </div>
            </div>
        </section>

        <!-- 2. Two-Column Main Layout (Matching Article List Sidebar Gap & Alignment) -->
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- Left Main Column -->
            <div class="flex-1 min-w-0 w-full space-y-6">

                <!-- A. Metadata Attributes Grid -->
                <section class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 shadow-theme-md">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-5">
                        
                        <!-- e-ISSN -->
                        @if (!empty($journal->e_issn))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">e-ISSN</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $journal->e_issn }}</div>
                            </div>
                        @endif

                        <!-- p-ISSN -->
                        @if (!empty($journal->p_issn))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">p-ISSN</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $journal->p_issn }}</div>
                            </div>
                        @endif

                        <!-- Issue Frequency -->
                        @if (!empty($journal->issue_frequency))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Frequency</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $journal->issue_frequency }}</div>
                            </div>
                        @endif

                        <!-- Impact Factor -->
                        @if (!empty($journal->journal_impact_factor))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Impact Factor</span>
                                <div class="text-xs sm:text-sm font-semibold text-[#198BEA] dark:text-sky-400">{{ $journal->journal_impact_factor }}</div>
                            </div>
                        @endif

                        <!-- Est. Year -->
                        @if (!empty($journal->first_publication_year))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Est. Year</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $journal->first_publication_year }}</div>
                            </div>
                        @endif

                        <!-- DOI -->
                        <div class="space-y-0.5">
                            <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Crossref DOI</span>
                            <div class="text-xs sm:text-sm font-semibold {{ $hasDoi ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500' }}">
                                {{ $hasDoi ? 'YES' : 'NO' }}
                            </div>
                        </div>

                        <!-- Country -->
                        @if (!empty($countryName))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Country</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $countryName }}</div>
                            </div>
                        @endif

                        <!-- Language -->
                        @if (!empty($journal->languages))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Language</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ is_array($journal->languages) ? implode(', ', $journal->languages) : $journal->languages }}
                                </div>
                            </div>
                        @endif

                        <!-- APC -->
                        <div class="space-y-0.5">
                            <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">APC Fees</span>
                            <div class="text-xs sm:text-sm font-semibold {{ $hasApc ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $hasApc ? 'YES (Charges Apply)' : 'Free (No APC)' }}
                            </div>
                        </div>

                        <!-- Impact Factor Agency -->
                        @if (!empty($journal->impact_factor_assignee_agency))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">IF Agency</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $journal->impact_factor_assignee_agency }}</div>
                            </div>
                        @endif

                        <!-- Contact Email -->
                        @if (!empty($journal->contact_email))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Contact Email</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $journal->contact_email }}</div>
                            </div>
                        @endif

                        <!-- Mobile -->
                        @if (!empty($journal->mobile))
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-semibold tracking-wider text-zinc-400 uppercase">Phone</span>
                                <div class="text-xs sm:text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $journal->country_code }} {{ $journal->mobile }}</div>
                            </div>
                        @endif

                    </div>

                    <!-- Disclaimer Button Inside Grid Card -->
                    <div class="mt-5 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                        <button 
                            type="button" 
                            @click="disclaimerOpen = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-zinc-500 hover:text-[#198BEA] dark:text-zinc-400 dark:hover:text-[#198BEA] transition cursor-pointer"
                        >
                            <flux:icon name="information-circle" class="size-4 text-zinc-400" />
                            <span>Read Journal Disclaimer</span>
                        </button>
                    </div>
                </section>

                <!-- B. Journal Description Section -->
                <section class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-theme-md space-y-6">
                    <div class="border-b border-zinc-100 dark:border-zinc-800 pb-3">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#198BEA]"></span>
                            Journal Descriptions
                        </h2>
                    </div>

                    <div class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed space-y-4">
                        @if (!empty($journal->journal_description))
                            <p class="whitespace-pre-line">{{ $journal->journal_description }}</p>
                        @endif

                        <div class="pt-2">
                            <h3 class="font-bold text-zinc-900 dark:text-zinc-100 mb-3 text-sm sm:text-base">
                                {{ $title }} {{ $shortName ? "($shortName)" : '' }} is:
                            </h3>

                            <ul class="space-y-2.5 text-xs sm:text-sm pl-4 list-disc marker:text-[#198BEA]">
                                <li>
                                    <span>International, Peer-Reviewed, Open Access, Refereed,</span>
                                    <span class="font-semibold">{{ is_array($subjects) ? implode(', ', $subjects) : $subjects }}</span>
                                    <span>Journal</span>
                                    @if(!empty($journal->e_issn)) (Online) @endif
                                    @if(!empty($journal->p_issn)) (Print) @endif
                                    @if(!empty($journal->issue_frequency)), {{ $journal->issue_frequency }} @endif.
                                </li>

                                <li>
                                    <span>ISSN Approved:</span>
                                    @if(!empty($journal->p_issn)) <span class="font-semibold">P-ISSN: {{ $journal->p_issn }}</span> @endif
                                    @if(!empty($journal->e_issn)) <span class="font-semibold">E-ISSN: {{ $journal->e_issn }}</span> @endif
                                    @if(!empty($journal->first_publication_year)), Established in {{ $journal->first_publication_year }} @endif
                                    @if(!empty($journal->journal_impact_factor)), Impact Factor: <span class="font-bold text-[#198BEA]">{{ $journal->journal_impact_factor }}</span> @endif.
                                </li>

                                <li>
                                    {{ $hasDoi ? 'Provides Crossref DOI for published articles.' : 'Does not provide Crossref DOI.' }}
                                </li>

                                @if (!empty($indexedInTop))
                                    <li>
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">Indexed in:</span>
                                        <span>{{ implode(', ', $indexedInTop) }}.</span>
                                    </li>
                                @endif

                                @if (!empty($notIndexedInTop))
                                    <li class="text-zinc-500 dark:text-zinc-400">
                                        <span>Not indexed in: {{ implode(', ', $notIndexedInTop) }}.</span>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- C. Indexing Agencies Grid Section -->
                @if (!empty($matchedIndexers))
                    <section class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-theme-md space-y-6">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                            <h2 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#198BEA]"></span>
                                Indexing Agencies
                            </h2>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">({{ count($matchedIndexers) }})</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach ($matchedIndexers as $idx => $indexer)
                                <div 
                                    x-show="indexersExpanded || {{ $idx < 6 ? 'true' : 'false' }}"
                                    class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/70 dark:border-zinc-700/60 rounded-xl hover:border-[#198BEA] transition group"
                                >
                                    <!-- Agency Logo / Initial Square Box -->
                                    <div class="w-8 h-8 rounded-lg bg-white dark:bg-zinc-700 flex items-center justify-center shrink-0 overflow-hidden border border-zinc-200 dark:border-zinc-600 shadow-2xs">
                                        @if ($indexer['image'])
                                            <img 
                                                src="{{ $indexer['image'] }}" 
                                                alt="{{ $indexer['name'] }} Logo" 
                                                class="w-full h-full object-contain p-0.5" 
                                                loading="lazy"
                                                onerror="this.style.display='none'; if (this.nextElementSibling) this.nextElementSibling.style.display='flex'"
                                            />
                                        @endif
                                        <span 
                                            class="text-xs font-bold text-zinc-500 dark:text-zinc-300"
                                            :style="{{ $indexer['image'] ? "'display:none;'" : "''" }}"
                                        >
                                            {{ $indexer['initial'] }}
                                        </span>
                                    </div>

                                    <!-- Agency Name & Link -->
                                    <div class="flex-1 min-w-0">
                                        @if ($indexer['has_url'])
                                            <a 
                                                href="{{ $indexer['url'] }}" 
                                                target="_blank" 
                                                rel="noopener noreferrer" 
                                                class="text-xs font-bold text-zinc-900 dark:text-zinc-100 hover:text-[#198BEA] dark:hover:text-sky-400 truncate block transition flex items-center gap-1"
                                            >
                                                <span class="truncate">{{ $indexer['name'] }}</span>
                                                <flux:icon name="arrow-top-right-on-square" class="size-3 shrink-0 text-zinc-400 group-hover:text-[#198BEA]" />
                                            </a>
                                        @else
                                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate block">
                                                {{ $indexer['name'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (count($matchedIndexers) > 6)
                            <div class="pt-2 text-center">
                                <button 
                                    type="button" 
                                    @click="indexersExpanded = !indexersExpanded"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-[#198BEA] hover:bg-sky-50 dark:hover:bg-sky-950/40 rounded-lg transition cursor-pointer"
                                >
                                    <span x-show="!indexersExpanded">+{{ count($matchedIndexers) - 6 }} More</span>
                                    <span x-show="indexersExpanded" style="display:none;">Show Less</span>
                                    <flux:icon name="chevron-down" class="size-3.5 transition-transform" ::class="indexersExpanded ? 'rotate-180' : ''" />
                                </button>
                            </div>
                        @endif
                    </section>
                @endif

                <!-- Role in Research Journal Section -->
                @if ($rolesInJournal->isNotEmpty())
                    <section class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-theme-md space-y-6">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                            <h2 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#198BEA]"></span>
                                Role in Research Journal
                            </h2>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">({{ $rolesInJournal->count() }} Members)</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5">
                            @foreach ($rolesInJournal as $idx => $roleItem)
                                @php
                                    $roleUser = $roleItem->user;
                                    $userName = $roleUser ? (trim(($roleUser->first_name ?? '') . ' ' . ($roleUser->last_name ?? '')) ?: ($roleUser->fullname ?? 'Scholar Member')) : 'Scholar Member';
                                    $userSlug = $roleUser->slug ?? null;
                                    $userPhoto = !empty($roleUser->photo) ? asset($roleUser->photo) : null;
                                    $userInitial = mb_substr($userName, 0, 1);
                                    $roleName = $roleItem->journalRole->name ?? ($roleItem->role ?: 'Editorial Member');
                                    $userUrl = !empty($userSlug) ? route('userscholar', ['slug' => $userSlug]) : '#';
                                    
                                    $start = $roleItem->start_month_year;
                                    $end = !empty($roleItem->not_ended_yet) ? 'Present' : $roleItem->end_month_year;
                                    $period = array_filter([$start, $end]);
                                @endphp

                                <div 
                                    x-show="rolesExpanded || {{ $idx < 6 ? 'true' : 'false' }}"
                                    class="flex items-center gap-3.5 p-3.5 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/70 dark:border-zinc-700/60 rounded-xl hover:border-[#198BEA] dark:hover:border-[#198BEA] transition group"
                                >
                                    <!-- User Avatar / Initial -->
                                    <div class="w-10 h-10 rounded-full bg-white dark:bg-zinc-700 flex items-center justify-center shrink-0 overflow-hidden border border-zinc-200 dark:border-zinc-600 shadow-2xs">
                                        @if ($userPhoto)
                                            <img 
                                                src="{{ $userPhoto }}" 
                                                alt="{{ $userName }} Avatar" 
                                                class="w-full h-full object-cover" 
                                                loading="lazy"
                                                onerror="this.style.display='none'; if (this.nextElementSibling) this.nextElementSibling.style.display='flex'"
                                            />
                                        @endif
                                        <span 
                                            class="text-xs font-bold text-zinc-500 dark:text-zinc-300"
                                            :style="{{ $userPhoto ? "'display:none;'" : "''" }}"
                                        >
                                            {{ $userInitial }}
                                        </span>
                                    </div>

                                    <!-- User Info & Role -->
                                    <div class="flex-1 min-w-0">
                                        @if ($userSlug)
                                            <a 
                                                href="{{ $userUrl }}" 
                                                wire:navigate
                                                class="text-xs font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-[#198BEA] dark:group-hover:text-sky-400 truncate block transition"
                                            >
                                                {{ $userName }}
                                            </a>
                                        @else
                                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate block">
                                                {{ $userName }}
                                            </span>
                                        @endif

                                        <p class="text-[11px] font-medium text-purple-600 dark:text-purple-400 truncate mt-0.5">
                                            {{ $roleName }}
                                        </p>

                                        @if (!empty($period))
                                            <p class="text-[10px] text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                                                {{ implode(' – ', $period) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($rolesInJournal->count() > 6)
                            <div class="pt-2 text-center">
                                <button 
                                    type="button" 
                                    @click="rolesExpanded = !rolesExpanded"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-[#198BEA] hover:bg-sky-50 dark:hover:bg-sky-950/40 rounded-lg transition cursor-pointer"
                                >
                                    <span x-show="!rolesExpanded">+{{ $rolesInJournal->count() - 6 }} More Members</span>
                                    <span x-show="rolesExpanded" style="display:none;">Show Less Members</span>
                                    <flux:icon name="chevron-down" class="size-3.5 transition-transform" ::class="rolesExpanded ? 'rotate-180' : ''" />
                                </button>
                            </div>
                        @endif
                    </section>
                @endif

                <!-- D. Publications of Journal Section -->
                @if ($publications->isNotEmpty())
                    <section class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-theme-md space-y-6">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                            <h2 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#198BEA]"></span>
                                Publications of {{ $shortName ?: $title }}
                            </h2>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">({{ $publications->count() }} Articles)</span>
                        </div>

                        <div class="space-y-4">
                            @foreach ($publications as $pub)
                                @php
                                    $pubTitle = $pub->title ?? 'Untitled Article';
                                    $pubUrl = !empty($pub->slug) ? route('publication.detail', ['slug' => $pub->slug]) : '#';
                                    
                                    // Author Resolution (Matching Article Detail / Index)
                                    $authorList = [];
                                    if (!empty($pub->registered_co_author) && is_array($pub->registered_co_author)) {
                                        foreach ($pub->registered_co_author as $regId) {
                                            $u = $registeredUsers[(string) $regId] ?? null;
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
                                    if (!empty($pub->unregistered_co_author) && is_array($pub->unregistered_co_author)) {
                                        foreach ($pub->unregistered_co_author as $unreg) {
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
                                    if (empty($authorList) && !empty($pub->authors) && is_array($pub->authors)) {
                                        foreach ($pub->authors as $auth) {
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

                                    // Date Resolution (Matching Article Detail / Index)
                                    $pubDate = !empty($pub->publication_month_year) 
                                        ? $pub->publication_month_year 
                                        : (!empty($pub->published_date) ? (string) $pub->published_date : ($pub->created_at ? $pub->created_at->format('F Y') : null));
                                @endphp

                                <article class="p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/40 hover:border-[#198BEA] dark:hover:border-[#198BEA] transition group">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="space-y-2 flex-1 min-w-0">
                                            <a 
                                                href="{{ $pubUrl }}" 
                                                class="text-sm sm:text-base font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-[#198BEA] dark:group-hover:text-sky-400 leading-snug block transition"
                                                wire:navigate
                                            >
                                                {{ $pubTitle }}
                                            </a>

                                            <!-- Authors, Date & DOI Metadata Row -->
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                                                <!-- Authors with User Scholar links -->
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <flux:icon name="user" class="size-3.5 text-purple-600 dark:text-purple-400 shrink-0" />
                                                    <span class="font-bold text-zinc-800 dark:text-zinc-200">Authors:</span>
                                                    @if(!empty($authorList))
                                                        <span class="text-zinc-600 dark:text-zinc-300 font-normal inline-flex flex-wrap items-center gap-x-1">
                                                            @foreach($authorList as $idx => $auth)
                                                                @if(!empty($auth['slug']))
                                                                    <a 
                                                                        href="{{ route('userscholar', ['slug' => $auth['slug']]) }}" 
                                                                        wire:navigate 
                                                                        class="text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] dark:hover:text-sky-400 font-medium transition-colors"
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
                                                        <span class="text-zinc-500 dark:text-zinc-400 font-normal">Author</span>
                                                    @endif
                                                </div>

                                                <!-- Date -->
                                                @if(!empty($pubDate))
                                                    <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                                    <span class="inline-flex items-center gap-1 text-zinc-600 dark:text-zinc-400">
                                                        <flux:icon name="calendar" class="size-3.5 text-zinc-400 shrink-0" />
                                                        <span>{{ $pubDate }}</span>
                                                    </span>
                                                @endif

                                                <!-- DOI -->
                                                @if (!empty($pub->doi))
                                                    <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                                    <span class="text-[#198BEA] font-medium">DOI: {{ $pub->doi }}</span>
                                                @endif
                                            </div>

                                            @if (!empty($pub->description))
                                                <p class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2 pt-0.5 leading-relaxed">
                                                    {{ strip_tags($pub->description) }}
                                                </p>
                                            @endif
                                        </div>

                                        <a 
                                            href="{{ $pubUrl }}" 
                                            wire:navigate
                                            class="shrink-0 p-2 sm:p-2.5 rounded-xl bg-white dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-600 text-zinc-500 group-hover:text-[#198BEA] group-hover:border-[#198BEA] shadow-2xs transition"
                                            title="View Publication"
                                        >
                                            <flux:icon name="arrow-right" class="size-4" />
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if ($hasMorePublications)
                            <div class="pt-4 text-center">
                                <button 
                                    type="button" 
                                    wire:click="loadMorePublications" 
                                    class="inline-flex items-center gap-2 px-6 py-2 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:border-[#198BEA] text-[#198BEA] dark:text-sky-400 text-xs font-semibold shadow-2xs hover:shadow-xs transition cursor-pointer"
                                >
                                    <flux:icon name="arrow-path" wire:loading wire:target="loadMorePublications" class="size-3.5 animate-spin text-[#198BEA]" />
                                    <span>Load More Publications</span>
                                </button>
                            </div>
                        @endif
                    </section>
                @endif

                {{-- E. Explore Transparent Peer Reviews Section --}}
                @if ($reviewPapers->isNotEmpty())
                    <section class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-theme-md space-y-6">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                            <h2 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Explore Transparent Peer Reviews of {{ $shortName ?: $title }}
                            </h2>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">({{ $reviewPapers->count() }} Papers)</span>
                        </div>

                        <div class="space-y-4">
                            @foreach ($reviewPapers as $rp)
                                @php
                                    $rpTitle    = $rp->paper_title ?? 'Untitled Paper';
                                    $rpAbstract = $rp->abstract ?? null;
                                @endphp

                                <article class="p-4 sm:p-5 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/40 hover:border-emerald-500 dark:hover:border-emerald-500 transition group">
                                    <div class="space-y-1.5">
                                        {{-- Paper Title --}}
                                        <p class="text-sm sm:text-base font-bold text-zinc-900 dark:text-zinc-100 leading-snug">
                                            {{ $rpTitle }}
                                        </p>

                                        {{-- Description / Abstract --}}
                                        @if ($rpAbstract)
                                            <p class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2 leading-relaxed">
                                                {{ strip_tags($rpAbstract) }}
                                            </p>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

            </div>

            <!-- Right Sidebar Column (w-full lg:w-80 shrink-0 space-y-6) -->
            <aside class="w-full lg:w-80 shrink-0 space-y-6">
                @include('components.article.sidebar')
            </aside>
        </div>
    </div>

    <!-- 3. Modal: Journal Disclaimer -->
    <div 
        x-show="disclaimerOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        style="display: none;"
    >
        <div 
            @click.outside="disclaimerOpen = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="w-full max-w-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-2xl p-6 sm:p-7 space-y-5 max-h-[90vh] overflow-y-auto"
        >
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 flex items-center justify-center">
                        <flux:icon name="exclamation-triangle" class="size-4" />
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        Disclaimer for Journal Page
                    </h3>
                </div>
                <button 
                    type="button" 
                    @click="disclaimerOpen = false" 
                    class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1.5 rounded-lg transition cursor-pointer"
                >
                    <flux:icon name="x-mark" class="size-5" />
                </button>
            </div>

            <div class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300 space-y-4 leading-relaxed">
                <div>
                    <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">Platform Role & Journal Ownership:</h4>
                    <p>Scholar9.com is a peer-review platform that indexes journals from across the globe. Please note that we do not own or publish any of the journals hosted on the platform unless explicitly stated.</p>
                </div>
                <div>
                    <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">Services for Journal Owners:</h4>
                    <p>Our platform enables journal owners to send articles for peer review to registered reviewers and provides submission tracking for journals claimed and actively managed by verified owners.</p>
                </div>
                <div>
                    <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">Indexing and Contact Details:</h4>
                    <p>For official information about journal indexing status (such as Scopus, WoS, UGC CARE) and primary contact details, users must refer to the respective official journal website.</p>
                </div>
                <div>
                    <h4 class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">Limitations of Responsibility:</h4>
                    <p>Scholar9 is not responsible for indexing claims, manuscript acceptance/rejection, APC fee refunds, or final editorial decisions. Users are advised to verify details independently.</p>
                </div>
            </div>

            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                <button 
                    type="button" 
                    @click="disclaimerOpen = false" 
                    class="px-5 py-2 rounded-xl bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold shadow-xs transition cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Modal: Claim Your Journal Confirmation -->
    <div 
        x-show="claimModalOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        style="display: none;"
    >
        <div 
            @click.outside="claimModalOpen = false" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="w-full max-w-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-2xl p-6 sm:p-7 space-y-4"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/40 text-[#198BEA] flex items-center justify-center shrink-0">
                    <flux:icon name="check-circle" class="size-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        Claim This Journal
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Are you the official owner or editor of this journal?
                    </p>
                </div>
            </div>

            <p class="text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed">
                By claiming this journal, you will gain access to Scholar9's editorial management tools, transparent peer review certificate dispatching, and manuscript submission routing.
            </p>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-zinc-100 dark:border-zinc-800">
                <button 
                    type="button" 
                    @click="claimModalOpen = false" 
                    class="px-4 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition cursor-pointer"
                >
                    Cancel
                </button>   
                <a 
                    href="{{ Route::has('publisher_register') ? route('publisher_register') : url('/register') }}" 
                    class="px-5 py-2 rounded-xl bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold shadow-xs transition cursor-pointer"
                >
                    Yes, Proceed to Claim
                </a>
            </div>
        </div>
    </div>

</div>