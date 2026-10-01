<?php

use App\Models\IndexingAgency;
use App\Models\ReviewerJournal;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public int $perPage = 10;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'title_asc')]
    public string $sortBy = 'title_asc';

    #[Url(except: [])]
    public array $selectedDisciplines = [];

    #[Url(except: [])]
    public array $selectedIndexers = [];

    #[Url(except: true)]
    public bool $isOpenAccess = true;

    #[Url(except: true)]
    public bool $isNoApc = true;

    public function mount(): void
    {
        if (session()->has('journals_per_page')) {
            $this->perPage = max(10, (int) session('journals_per_page'));
        }
    }

    public function updatingSearch(): void
    {
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function updatingSortBy(): void
    {
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function updatingSelectedDisciplines(): void
    {
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function updatingSelectedIndexers(): void
    {
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function toggleOpenAccess(): void
    {
        $this->isOpenAccess = !$this->isOpenAccess;
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function toggleNoApc(): void
    {
        $this->isNoApc = !$this->isNoApc;
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function clearDisciplines(): void
    {
        $this->selectedDisciplines = [];
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function clearIndexers(): void
    {
        $this->selectedIndexers = [];
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->sortBy = 'title_asc';
        $this->selectedDisciplines = [];
        $this->selectedIndexers = [];
        $this->isOpenAccess = true;
        $this->isNoApc = true;
        $this->perPage = 10;
        session(['journals_per_page' => 10]);
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
        session(['journals_per_page' => $this->perPage]);
    }

    public function with(): array
    {
        // 1. Cached Disciplines List from Active (Status 1) Journals (24 hours cache)
        $allDisciplines = Cache::remember('journal_disciplines_list', 86400, function () {
            $raw = ReviewerJournal::raw(function ($collection) {
                return $collection->distinct('journal_subjects', ['status' => ['$in' => [1, '1', true]]]);
            });
            $list = [];
            foreach ($raw as $s) {
                $trimmed = trim((string) $s);
                if ($trimmed !== '') {
                    $list[$trimmed] = true;
                }
            }
            $clean = array_keys($list);
            natcasesort($clean);
            return array_values($clean);
        });

        // 2. Cached Indexers List from Active (Status 1) Indexing Agencies with Logos & Initials
        $allIndexers = Cache::remember('journal_indexers_list', 86400, function () {
            $fillable = (new ReviewerJournal)->getFillable();
            $checkboxes = array_filter($fillable, fn ($f) => str_ends_with($f, '_checkbox'));

            $cbMap = [];
            foreach ($checkboxes as $cb) {
                $raw = str_replace('_checkbox', '', $cb);
                $normalized = strtolower(str_replace([' ', '_', '-', '.', '/', '(', ')', '\''], '', $raw));
                $cbMap[$normalized] = $cb;
            }

            $activeAgencies = IndexingAgency::whereIn('status', [1, '1', true])
                ->select(['agency_name', 'image', 'status', 'serial_number'])
                ->get();

            $indexers = [];
            foreach ($activeAgencies as $agency) {
                $name = trim((string) $agency->agency_name);
                if ($name === '') {
                    continue;
                }

                $normalized = strtolower(str_replace([' ', '_', '-', '.', '/', '(', ')', '\''], '', $name));
                $key = $cbMap[$normalized] ?? (str_replace(' ', '_', $name) . '_checkbox');

                $img = trim((string) ($agency->image ?? ''));
                $img = preg_replace('#^https?://[^/]+/#', '', $img);
                $imageUrl = $img !== '' ? asset($img) : null;

                $initial = mb_substr($name, 0, 1);

                $indexers[] = [
                    'key' => $key,
                    'label' => $name,
                    'image' => $imageUrl,
                    'initial' => $initial,
                ];
            }

            usort($indexers, fn ($a, $b) => strcasecmp($a['label'], $b['label']));
            return $indexers;
        });

        // 3. Query Construction with Status 1 Filter, Strict Field Projection & Bounded Execution
        $query = ReviewerJournal::query()
            ->select([
                '_id',
                'journal_title',
                'title',
                'slug',
                'journal_short_name',
                'journal_photo',
                'organization_name',
                'journal_impact_factor',
                'e_issn',
                'p_issn',
                'journal_webiste_url',
                'JournalOpenAccess',
                'feesAssociatedWithPublishing',
                'journal_subjects',
                'first_publication_year',
                'country',
                'status',
                'Scopus_checkbox',
                'DOAJ_checkbox',
                'Google_Scholar_checkbox',
                'UGC_CARE_checkbox',
                'WoS_checkbox',
                'Crossref_checkbox',
                'PubMed_checkbox',
                'CAS_checkbox',
                'EBSCO_checkbox',
                'Embase_checkbox',
                'created_at',
            ])
            ->whereIn('status', [1, '1', true])
            ->whereNotNull('journal_title')
            ->where('journal_title', '!=', '');

        // Search Filter (Minimum 3 characters threshold)
        $search = trim((string) ($this->search ?? ''));
        if ($search !== '' && mb_strlen($search) >= 3) {
            $query->where(function ($q) use ($search) {
                $q->where('journal_title', 'like', '%' . $search . '%')
                    ->orWhere('organization_name', 'like', '%' . $search . '%')
                    ->orWhere('e_issn', 'like', '%' . $search . '%')
                    ->orWhere('p_issn', 'like', '%' . $search . '%');
            });
        }

        // Disciplines / Subjects Multi-filter
        if (!empty($this->selectedDisciplines)) {
            $query->whereIn('journal_subjects', array_values($this->selectedDisciplines));
        }

        // Indexers Multi-filter
        if (!empty($this->selectedIndexers)) {
            $query->where(function ($q) {
                foreach ($this->selectedIndexers as $idx) {
                    $q->orWhereIn($idx, [1, '1', true]);
                }
            });
        }

        // Open Access Filter
        if ($this->isOpenAccess) {
            $query->whereIn('JournalOpenAccess', [1, '1', true]);
        }

        // No APC (Free of charge) Filter
        if ($this->isNoApc) {
            $query->whereIn('feesAssociatedWithPublishing', [0, '0', false]);
        }

        // Sorting
        switch ($this->sortBy) {
            case 'title_desc':
                $query->orderBy('journal_title', 'desc');
                break;
            case 'impact_desc':
                $query->orderByDesc('journal_impact_factor');
                break;
            case 'year_desc':
                $query->orderByDesc('first_publication_year');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'oldest':
                $query->oldest();
                break;
            case 'title_asc':
            default:
                $query->orderBy('journal_title', 'asc');
                break;
        }

        // Single-pass check: fetch perPage + 1 to determine if more pages exist with zero count() queries
        $results = $query->limit($this->perPage + 1)->get();
        $hasMore = $results->count() > $this->perPage;
        $journals = $hasMore ? $results->slice(0, $this->perPage) : $results;

        // Base total count (Status 1 only)
        $baseTotalJournals = Cache::remember('total_active_journals_count', 3600, function () {
            return ReviewerJournal::whereIn('status', [1, '1', true])
                ->whereNotNull('journal_title')
                ->where('journal_title', '!=', '')
                ->count();
        });

        $hasActiveFilters = !empty($search) || !empty($this->selectedDisciplines) || !empty($this->selectedIndexers) || !$this->isOpenAccess || !$this->isNoApc || $this->sortBy !== 'title_asc';

        return [
            'journals' => $journals,
            'hasMore' => $hasMore,
            'allDisciplines' => $allDisciplines,
            'allIndexers' => $allIndexers,
            'baseTotalJournals' => $baseTotalJournals,
            'hasActiveFilters' => $hasActiveFilters,
        ];
    }
}; ?>

<div
    x-data="{
        disciplinesOpen: false,
        indexersOpen: false,
        disciplineSearch: '',
        indexerSearch: '',
        disciplines: @js($allDisciplines),
        indexers: @js($allIndexers),
        get filteredDisciplines() {
            if (!this.disciplineSearch || this.disciplineSearch.trim() === '') {
                return this.disciplines;
            }
            const q = this.disciplineSearch.toLowerCase().trim();
            return this.disciplines.filter(d => d.toLowerCase().includes(q));
        },
        get filteredIndexers() {
            if (!this.indexerSearch || this.indexerSearch.trim() === '') {
                return this.indexers;
            }
            const q = this.indexerSearch.toLowerCase().trim();
            return this.indexers.filter(idx => idx.label.toLowerCase().includes(q));
        }
    }"
    class="min-h-screen pb-12"
>
    <!-- ================= TOP HEADER HERO BANNER ================= -->
    <div class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200/90 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center gap-4">
            <!-- Left Icon Badge -->
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-[#198BEA] text-white flex items-center justify-center shrink-0 shadow-md shadow-[#198BEA]/25">
                <svg class="w-6 h-6 sm:w-7 sm:h-7" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.5 3.75A2.25 2.25 0 0 0 2.25 6v12.75a2.25 2.25 0 0 0 2.25 2.25h14.25a.75.75 0 0 0 .75-.75V6a2.25 2.25 0 0 0-2.25-2.25H4.5ZM6 7.5a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 7.5Zm0 3.75a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75Zm0 3.75a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                </svg>
            </div>

            <!-- Header Content -->
            <div class="space-y-0.5">
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">
                    Academic & Research Journals
                </h1>
                <p class="text-xs sm:text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Explore peer-reviewed journals, open access publications, and indexed venues
                </p>
                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed pt-0.5">
                    Browse indexed publications, check APC policies, and find the right venue for your research. 
                    <span class="font-bold text-[#198BEA] dark:text-sky-400">Discover. Publish. Connect.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================= TWO COLUMN MAIN CONTENT ================= -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- LEFT CONTAINER: JOURNALS LIST & FILTERS -->
            <div class="flex-1 min-w-0 w-full space-y-6">
                
                <!-- 1. Search & Sort Bar -->
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
                            placeholder="Search journals by title, ISSN, publisher... (Enter at least 3 characters)" 
                            class="w-full h-11 pl-11 pr-4 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-600 rounded-full text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 dark:focus:ring-brand/30 shadow-xs transition"
                        />
                    </div>

                    <!-- Sort By Dropdown -->
                    <div class="sm:w-72">
                        <select 
                            wire:model.live="sortBy"
                            class="w-full h-11 px-4 bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-600 rounded-full text-sm text-zinc-800 dark:text-zinc-200 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 dark:focus:ring-brand/30 shadow-xs cursor-pointer transition appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236b7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-[length:9px_9px] bg-[right_16px_center] bg-no-repeat pr-10"
                        >
                            <option value="title_asc">Title (A-Z)</option>
                            <option value="title_desc">Title (Z-A)</option>
                            <option value="impact_desc">Impact Factor (High to Low)</option>
                            <option value="year_desc">Year Established (Newest first)</option>
                            <option value="newest">Recently Added</option>
                            <option value="oldest">Oldest Added</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Interactive Filter Controls Row -->
                <div class="flex flex-wrap items-center gap-3">
                    
                    <!-- A. Disciplines Dropdown (With Search, Checkbox Options & Selected Count) -->
                    <div class="relative" @click.outside="disciplinesOpen = false">
                        <button 
                            type="button" 
                            @click="disciplinesOpen = !disciplinesOpen; indexersOpen = false"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-zinc-900 border rounded-xl text-xs font-semibold shadow-xs hover:border-[#198BEA] transition cursor-pointer {{ !empty($selectedDisciplines) ? 'border-[#198BEA] text-[#198BEA] bg-sky-50/50 dark:bg-sky-950/30' : 'border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300' }}"
                        >
                            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <span>All Disciplines</span>
                            @if(!empty($selectedDisciplines))
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#198BEA] text-white">
                                    {{ count($selectedDisciplines) }}
                                </span>
                            @endif
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform" :class="disciplinesOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Disciplines Dropdown Menu -->
                        <div 
                            x-show="disciplinesOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                            class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl shadow-xl z-50 p-3 space-y-2.5"
                            style="display: none;"
                        >
                            <!-- Header: Title, Selected Count, and Clear Button -->
                            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-700">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-zinc-900 dark:text-white">Disciplines</span>
                                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-medium">(Selected: {{ count($selectedDisciplines) }})</span>
                                </div>
                                @if(!empty($selectedDisciplines))
                                    <button 
                                        type="button" 
                                        wire:click="clearDisciplines"
                                        class="text-[11px] font-semibold text-red-500 hover:text-red-600 transition cursor-pointer"
                                    >
                                        Clear
                                    </button>
                                @endif
                            </div>

                            <!-- Search Inside Disciplines Dropdown -->
                            <div class="relative">
                                <svg class="w-3.5 h-3.5 absolute left-3 top-2.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input 
                                    type="text" 
                                    x-model="disciplineSearch" 
                                    placeholder="Search discipline..." 
                                    class="w-full h-8 pl-8 pr-3 text-xs bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:border-[#198BEA] focus:ring-1 focus:ring-[#198BEA]/20"
                                />
                            </div>

                            <!-- Scrollable Checkbox List -->
                            <div class="max-h-56 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
                                <template x-for="d in filteredDisciplines" :key="d">
                                    <label class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 cursor-pointer text-xs text-zinc-700 dark:text-zinc-300">
                                        <input 
                                            type="checkbox" 
                                            :value="d"
                                            wire:model.live="selectedDisciplines"
                                            class="rounded text-[#198BEA] focus:ring-[#198BEA]/20 dark:bg-zinc-900 dark:border-zinc-700"
                                        />
                                        <span class="truncate" x-text="d"></span>
                                    </label>
                                </template>
                                <template x-if="filteredDisciplines.length === 0">
                                    <div class="py-4 text-center text-xs text-zinc-400">
                                        No matching disciplines found
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- B. Indexers Dropdown (With Search, Checkbox Options & Selected Count) -->
                    <div class="relative" @click.outside="indexersOpen = false">
                        <button 
                            type="button" 
                            @click="indexersOpen = !indexersOpen; disciplinesOpen = false"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-zinc-900 border rounded-xl text-xs font-semibold shadow-xs hover:border-[#198BEA] transition cursor-pointer {{ !empty($selectedIndexers) ? 'border-[#198BEA] text-[#198BEA] bg-sky-50/50 dark:bg-sky-950/30' : 'border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300' }}"
                        >
                            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span>Indexers</span>
                            @if(!empty($selectedIndexers))
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#198BEA] text-white">
                                    {{ count($selectedIndexers) }}
                                </span>
                            @endif
                            <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform" :class="indexersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Indexers Dropdown Menu -->
                        <div 
                            x-show="indexersOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                            class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl shadow-xl z-50 p-3 space-y-2.5"
                            style="display: none;"
                        >
                            <!-- Header: Title, Selected Count, and Clear Button -->
                            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-700">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-zinc-900 dark:text-white">Indexers</span>
                                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-medium">(Selected: {{ count($selectedIndexers) }})</span>
                                </div>
                                @if(!empty($selectedIndexers))
                                    <button 
                                        type="button" 
                                        wire:click="clearIndexers"
                                        class="text-[11px] font-semibold text-red-500 hover:text-red-600 transition cursor-pointer"
                                    >
                                        Clear
                                    </button>
                                @endif
                            </div>

                            <!-- Search Inside Indexers Dropdown -->
                            <div class="relative">
                                <svg class="w-3.5 h-3.5 absolute left-3 top-2.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input 
                                    type="text" 
                                    x-model="indexerSearch" 
                                    placeholder="Search indexers (e.g. Scopus, DOAJ)..." 
                                    class="w-full h-8 pl-8 pr-3 text-xs bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-none focus:border-[#198BEA] focus:ring-1 focus:ring-[#198BEA]/20"
                                />
                            </div>

                            <!-- Scrollable Checkbox List with Logos & Initials -->
                            <div class="max-h-56 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
                                <template x-for="idx in filteredIndexers" :key="idx.key">
                                    <label class="flex items-center gap-3 px-2 py-1.5 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 cursor-pointer select-none group">
                                        <input 
                                            type="checkbox" 
                                            :value="idx.key"
                                            wire:model.live="selectedIndexers"
                                            class="w-4 h-4 rounded text-[#198BEA] border-zinc-300 dark:border-zinc-600 focus:ring-[#198BEA]/20 dark:bg-zinc-900 shrink-0 cursor-pointer"
                                        />
                                        <!-- Logo Image or Fallback Initial Box -->
                                        <div class="w-6 h-6 rounded bg-[#E2E2E2] dark:bg-zinc-700 flex items-center justify-center shrink-0 overflow-hidden">
                                            <template x-if="idx.image">
                                                <img 
                                                    :src="idx.image" 
                                                    :alt="idx.label" 
                                                    class="w-full h-full object-contain p-0.5" 
                                                    loading="lazy"
                                                    x-on:error="$el.style.display='none'; if ($el.nextElementSibling) $el.nextElementSibling.style.display='flex'"
                                                />
                                            </template>
                                            <span 
                                                class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 select-none"
                                                :style="idx.image ? 'display: none;' : 'display: flex;'"
                                                x-text="idx.initial"
                                            ></span>
                                        </div>
                                        <span 
                                            class="truncate text-xs font-bold text-zinc-900 dark:text-zinc-100" 
                                            :class="selectedIndexers.includes(idx.key) ? 'text-[#198BEA] dark:text-[#198BEA]' : ''" 
                                            x-text="idx.label"
                                        ></span>
                                    </label>
                                </template>
                                <template x-if="filteredIndexers.length === 0">
                                    <div class="py-4 text-center text-xs text-zinc-400">
                                        No matching indexers found
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- C. Open Access Toggle Button -->
                    <button 
                        type="button" 
                        wire:click="toggleOpenAccess"
                        class="inline-flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all cursor-pointer {{ $isOpenAccess ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-500 text-emerald-700 dark:text-emerald-300 shadow-xs' : 'bg-white dark:bg-zinc-900 border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:border-zinc-400' }}"
                    >
                        <div class="w-8 h-4 rounded-full transition-colors relative flex items-center {{ $isOpenAccess ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600' }}">
                            <div class="w-3 h-3 rounded-full bg-white shadow-xs transition-transform transform {{ $isOpenAccess ? 'translate-x-4' : 'translate-x-0.5' }}"></div>
                        </div>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 0 0-7.5 0v3h7.5Z" clip-rule="evenodd"/>
                            </svg>
                            Open Access Journal
                        </span>
                    </button>

                    <!-- D. No APC (Free of charge) Toggle Button -->
                    <button 
                        type="button" 
                        wire:click="toggleNoApc"
                        class="inline-flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all cursor-pointer {{ $isNoApc ? 'bg-sky-50 dark:bg-sky-950/40 border-[#198BEA] text-[#198BEA] dark:text-sky-300 shadow-xs' : 'bg-white dark:bg-zinc-900 border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:border-zinc-400' }}"
                    >
                        <div class="w-8 h-4 rounded-full transition-colors relative flex items-center {{ $isNoApc ? 'bg-[#198BEA]' : 'bg-zinc-300 dark:bg-zinc-600' }}">
                            <div class="w-3 h-3 rounded-full bg-white shadow-xs transition-transform transform {{ $isNoApc ? 'translate-x-4' : 'translate-x-0.5' }}"></div>
                        </div>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            No APC (Free of charge)
                        </span>
                    </button>

                    <!-- E. Reset All Filters Button -->
                    @if($hasActiveFilters)
                        <button 
                            type="button" 
                            wire:click="resetFilters"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-zinc-500 hover:text-red-500 dark:text-zinc-400 dark:hover:text-red-400 transition cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Reset Filters</span>
                        </button>
                    @endif

                </div>

                <!-- 3. Total Count Summary Bar (Above the List) -->
                <div class="flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-800 rounded-xl text-xs sm:text-sm text-zinc-700 dark:text-zinc-300">
                    <div class="flex items-center gap-2 font-medium">
                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <span>
                            Total Journals: 
                            <span class="font-bold text-[#198BEA] dark:text-sky-400">
                                {{ number_format($baseTotalJournals) }}
                            </span>
                        </span>
                        @if($hasActiveFilters)
                            <span class="text-zinc-400 font-normal">| Filtered results active</span>
                        @endif
                    </div>

                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        Showing up to <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $journals->count() }}</span> journals
                    </div>
                </div>

                <!-- 4. Loading State Indicator -->
                <div 
                    wire:loading.flex 
                    wire:target="search, sortBy, selectedDisciplines, selectedIndexers, isOpenAccess, isNoApc, resetFilters" 
                    class="items-center justify-center gap-2.5 py-2.5 px-4 bg-[#198BEA]/10 dark:bg-[#198BEA]/15 border border-[#198BEA]/20 dark:border-[#198BEA]/30 rounded-xl text-xs font-semibold text-[#198BEA] dark:text-sky-400 transition"
                >
                    <svg class="animate-spin w-4 h-4 text-[#198BEA]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Loading journals matching your criteria...</span>
                </div>

                <!-- 5. Journals Grid / List Container -->
                <div 
                    wire:loading.class="opacity-60 pointer-events-none" 
                    wire:target="search, sortBy, selectedDisciplines, selectedIndexers, isOpenAccess, isNoApc, resetFilters" 
                    class="space-y-4 transition-opacity duration-200"
                >
                    @forelse ($journals as $journal)
                        @php
                            $title = $journal->journal_title ?: $journal->title;
                            $hasPhoto = !empty($journal->journal_photo);
                            $isOpen = !empty($journal->JournalOpenAccess) && in_array($journal->JournalOpenAccess, [1, '1', true]);
                            $isFreeApc = in_array($journal->feesAssociatedWithPublishing, [0, '0', false], true) || ($journal->feesAssociatedWithPublishing === null && $isOpen);
                            $impactFactor = trim((string) ($journal->journal_impact_factor ?? ''));
                            $pIssn = trim((string) ($journal->p_issn ?? ''));
                            $eIssn = trim((string) ($journal->e_issn ?? ''));
                            $org = trim((string) ($journal->organization_name ?? ''));
                            $subjects = is_array($journal->journal_subjects) ? array_values(array_filter($journal->journal_subjects)) : [];
                            $visibleSubjects = array_slice($subjects, 0, 4);
                            $remainingSubjectsCount = count($subjects) - count($visibleSubjects);

                            // Detect active indexers on this journal
                            $detectedIndexers = [];
                            $indexerMap = [
                                'Scopus_checkbox' => 'Scopus',
                                'DOAJ_checkbox' => 'DOAJ',
                                'Google_Scholar_checkbox' => 'Google Scholar',
                                'UGC_CARE_checkbox' => 'UGC CARE',
                                'WoS_checkbox' => 'Web of Science',
                                'Crossref_checkbox' => 'Crossref',
                                'PubMed_checkbox' => 'PubMed',
                                'CAS_checkbox' => 'CAS',
                                'EBSCO_checkbox' => 'EBSCO',
                                'Embase_checkbox' => 'Embase',
                            ];
                            foreach ($indexerMap as $field => $label) {
                                if (!empty($journal->{$field}) && in_array($journal->{$field}, [1, '1', true])) {
                                    $detectedIndexers[] = $label;
                                }
                            }
                        @endphp

                        <div 
                            wire:key="journal-{{ $journal->_id }}" 
                            class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-theme-md hover:shadow-theme-dark transition-all duration-300 mb-2.5"
                        >
                            <!-- 1. Journal Header: Image (Left) + Info (Right) -->
                            <div class="flex flex-col sm:flex-row gap-4 p-5 sm:p-6">
                                
                                <!-- Journal Image / Placeholder -->
                                @if ($hasPhoto)
                                    <img 
                                        src="{{ asset($journal->journal_photo) }}" 
                                        alt="Journal Photo for {{ $title }}" 
                                        class="w-[90px] h-[110px] rounded-xl object-contain shrink-0 shadow-xs border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800"
                                        loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                    />
                                    <div class="hidden w-[90px] h-[110px] rounded-xl shrink-0 flex-col items-center justify-center font-bold text-sm text-white shadow-xs text-center p-2" style="background: #006EC9;">
                                        {{ substr(strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', explode(' ', trim($title))))), 0, 5) }}
                                    </div>
                                @else
                                    <div class="w-[90px] h-[110px] rounded-xl shrink-0 flex items-center justify-center font-bold text-sm text-white shadow-xs text-center p-2" style="background: #006EC9;">
                                        {{ substr(strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', explode(' ', trim($title))))), 0, 5) }}
                                    </div>
                                @endif

                                <!-- Journal Info -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-base sm:text-[17px] font-bold text-zinc-900 dark:text-white mb-1 leading-snug hover:text-[#198BEA] transition-colors">
                                        @if(!empty($journal->slug))
                                            <a href="{{ url('journal/' . $journal->slug) }}" wire:navigate>
                                                {{ $title }}
                                                @if(!empty($journal->journal_short_name))
                                                    <span class="text-[#198BEA] dark:text-sky-400 font-semibold text-sm sm:text-base ml-1">({{ $journal->journal_short_name }})</span>
                                                @endif
                                            </a>
                                        @elseif(!empty($journal->journal_webiste_url))
                                            <a href="{{ $journal->journal_webiste_url }}" target="_blank" rel="noopener noreferrer">
                                                {{ $title }}
                                                @if(!empty($journal->journal_short_name))
                                                    <span class="text-[#198BEA] dark:text-sky-400 font-semibold text-sm sm:text-base ml-1">({{ $journal->journal_short_name }})</span>
                                                @endif
                                            </a>
                                        @else
                                            <span>{{ $title }}</span>
                                            @if(!empty($journal->journal_short_name))
                                                <span class="text-[#198BEA] dark:text-sky-400 font-semibold text-sm sm:text-base ml-1">({{ $journal->journal_short_name }})</span>
                                            @endif
                                        @endif
                                    </h3>

                                    <div class="text-[13px] text-zinc-500 dark:text-zinc-400 mb-3">
                                        Publisher : <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ !empty($journal->organization_name) ? $journal->organization_name : 'N/A' }}</span>
                                    </div>

                                    <!-- Meta Row: Est. • e-ISSN • p-ISSN • Impact Factor -->
                                    <div class="flex flex-wrap gap-4 sm:gap-6">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-[11px] text-zinc-400 font-semibold uppercase tracking-wider">Est.</span>
                                            <span class="text-[13px] font-medium text-zinc-700 dark:text-zinc-300">{{ !empty($journal->first_publication_year) ? $journal->first_publication_year : 'N/A' }}</span>
                                        </div>
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-[11px] text-zinc-400 font-semibold uppercase tracking-wider">e-ISSN</span>
                                            <span class="text-[13px] font-medium text-zinc-700 dark:text-zinc-300">{{ !empty($journal->e_issn) ? $journal->e_issn : 'N/A' }}</span>
                                        </div>
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-[11px] text-zinc-400 font-semibold uppercase tracking-wider">p-ISSN</span>
                                            <span class="text-[13px] font-medium text-zinc-700 dark:text-zinc-300">{{ !empty($journal->p_issn) ? $journal->p_issn : 'N/A' }}</span>
                                        </div>
                                        @if($impactFactor !== '')
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-[11px] text-zinc-400 font-semibold uppercase tracking-wider">Impact Factor</span>
                                                <span class="text-[13px] font-medium text-amber-600 dark:text-amber-400">{{ $impactFactor }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Journal Footer: Tags + Action Buttons -->
                            <div class="px-5 sm:px-6 pb-5 sm:pb-6 space-y-3.5">
                                
                                <!-- Tags Row -->
                                <div class="flex flex-wrap items-center gap-2">
                                    @if(!empty($subjects))
                                        <div x-data="{ expanded: false }" class="flex flex-wrap items-center gap-2">
                                            <template x-if="!expanded">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    @foreach ($visibleSubjects as $subj)
                                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-[#eaf5ff] dark:bg-sky-950/50 text-[#198BEA] dark:text-sky-400 border border-sky-100 dark:border-sky-900/50">
                                                            {{ $subj }}
                                                        </span>
                                                    @endforeach
                                                    @if($remainingSubjectsCount > 0)
                                                        <button 
                                                            type="button" 
                                                            @click="expanded = true"
                                                            class="text-xs font-semibold text-[#198BEA] dark:text-sky-400 hover:underline cursor-pointer ml-1"
                                                        >
                                                            +{{ $remainingSubjectsCount }} View More
                                                        </button>
                                                    @endif
                                                </div>
                                            </template>
                                            
                                            <template x-if="expanded">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    @foreach ($subjects as $subj)
                                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-[#eaf5ff] dark:bg-sky-950/50 text-[#198BEA] dark:text-sky-400 border border-sky-100 dark:border-sky-900/50">
                                                            {{ $subj }}
                                                        </span>
                                                    @endforeach
                                                    <button 
                                                        type="button" 
                                                        @click="expanded = false"
                                                        class="text-xs font-semibold text-zinc-500 hover:text-[#198BEA] dark:hover:text-sky-400 cursor-pointer ml-1"
                                                    >
                                                        Show less
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    @endif

                                    <!-- Open Access / No APC tags matching design -->
                                    @if($isOpen)
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                            Open Access
                                        </span>
                                    @endif

                                    @if($isFreeApc)
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            No APC
                                        </span>
                                    @endif
                                </div>

                                <!-- Indexing badges if detected -->
                                @if(!empty($detectedIndexers))
                                    <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                        <span class="text-[11px] font-bold text-zinc-400">Indexed in:</span>
                                        @foreach($detectedIndexers as $idxName)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                                {{ $idxName }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Bottom Action Row: View Details (Left) + Share (Right) -->
                                <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-zinc-100 dark:border-zinc-800/80">
                                    <div>
                                        @if(!empty($journal->slug))
                                            <a 
                                                href="{{ url('journal/' . $journal->slug) }}" 
                                                wire:navigate
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs font-semibold rounded-lg shadow-xs hover:shadow transition-all cursor-pointer"
                                            >
                                                <span>View Details</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                </svg>
                                            </a>
                                        @elseif(!empty($journal->journal_webiste_url))
                                            <a 
                                                href="{{ $journal->journal_webiste_url }}" 
                                                target="_blank" 
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-xs font-semibold rounded-lg shadow-xs hover:shadow transition-all cursor-pointer"
                                            >
                                                <span>View Details</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                </svg>
                                            </a>
                                        @else
                                            <button 
                                                type="button" 
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-lg shadow-xs transition cursor-pointer opacity-70"
                                            >
                                                <span>View Details</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>

                                    @php
                                        $shareUrl = !empty($journal->slug) 
                                            ? url('journal/' . $journal->slug) 
                                            : (!empty($journal->journal_webiste_url) ? $journal->journal_webiste_url : url('/journals'));
                                    @endphp
                                    <div class="flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                                        <button 
                                            type="button" 
                                            @click="$dispatch('open-share-modal', { url: @js($shareUrl), title: @js($title), type: 'journal', header: 'Share Journal', subtitle: 'Share this journal across networks' })"
                                            class="inline-flex items-center gap-1.5 hover:text-[#198BEA] dark:hover:text-sky-400 transition-colors cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                            </svg>
                                            <span class="font-medium">Share Journal</span>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    @empty
                        <!-- Empty State -->
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-10 text-center shadow-theme-md space-y-3">
                            <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto">
                                <svg class="w-6 h-6 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                                No Journals Found
                            </h3>
                            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto">
                                We couldn't find any journals matching your search or filter criteria. Try clearing selected disciplines, indexers, or search terms.
                            </p>
                            @if($hasActiveFilters)
                                <div class="pt-2">
                                    <button 
                                        type="button" 
                                        wire:click="resetFilters"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-lg shadow-sm transition cursor-pointer"
                                    >
                                        <span>Reset all filters</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforelse
                </div>

                <!-- 6. Load More Button -->
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
                            <span wire:loading.remove wire:target="loadMore">Load More Journals</span>
                            <span wire:loading wire:target="loadMore">Loading more journals...</span>
                        </button>
                    </div>
                @endif

            </div>

            <!-- RIGHT CONTAINER: REUSABLE SIDEBAR -->
            <x-article.sidebar />

        </div>
    </div>
</div>
