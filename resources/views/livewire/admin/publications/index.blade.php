<?php

use App\Models\Publication;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Manage Publications - Admin')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 15;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public bool $hasCustomSort = false;

    public function updatingSearch(): void
    {
        $this->hasCustomSort = false;
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $this->hasCustomSort = true;
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function toggleStatus(string $id): void
    {
        $publication = Publication::find($id);
        if ($publication) {
            $isPublished = (int) $publication->status === 1
                || in_array($publication->option, ['1', 1, 'published', true], true);
            $newStatus = $isPublished ? 0 : 1;
            $publication->status = $newStatus;
            $publication->option = $newStatus === 1 ? 'published' : 'preprint';
            $publication->save();
        }
    }

    public function toggleSelectedStatus(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        $publications = Publication::whereIn('_id', $ids)->get();
        foreach ($publications as $publication) {
            $isPublished = (int) $publication->status === 1
                || in_array($publication->option, ['1', 1, 'published', true], true);
            $newStatus = $isPublished ? 0 : 1;
            $publication->status = $newStatus;
            $publication->option = $newStatus === 1 ? 'published' : 'preprint';
            $publication->save();
        }
    }

    public function deletePublication(string $id): void
    {
        $publication = Publication::find($id);
        if ($publication) {
            $publication->delete();
        }
    }

    public function deleteSelected(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        Publication::whereIn('_id', $ids)->delete();
    }

    public function with(): array
    {
        $search = trim($this->search);

        if (! empty($search)) {
            $escaped = preg_quote($search, '/');
            $regex = new \MongoDB\BSON\Regex($escaped, 'i');
            $exactRegex = new \MongoDB\BSON\Regex('^'.$escaped.'$', 'i');
            $startRegex = new \MongoDB\BSON\Regex('^'.$escaped, 'i');

            $match = [];

            if ($this->statusFilter !== 'all') {
                $match['status'] = in_array($this->statusFilter, ['1', 1, 'active', true], true) ? 1 : 0;
            }

            $match['$or'] = [
                ['title' => ['$regex' => $regex]],
                ['slug' => ['$regex' => $regex]],
                ['serial_number' => is_numeric($search) ? (int) $search : ['$regex' => $regex]],
                ['journal_title' => ['$regex' => $regex]],
                ['journal_name' => ['$regex' => $regex]],
                ['doi' => ['$regex' => $regex]],
                ['publication_keywords' => ['$regex' => $regex]],
                ['description' => ['$regex' => $regex]],
                ['abstract' => ['$regex' => $regex]],
                ['authors.name' => ['$regex' => $regex]],
            ];

            $page = LengthAwarePaginator::resolveCurrentPage() ?: 1;
            $sort = $this->hasCustomSort
                ? [$this->sortField => $this->sortDirection === 'asc' ? 1 : -1]
                : ['relevanceScore' => -1, '_id' => -1];

            $pipeline = [
                ['$match' => $match],
                [
                    '$addFields' => [
                        'relevanceScore' => [
                            '$add' => [
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$title', '']], 'regex' => $exactRegex]], 100, 0]],
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$slug', '']], 'regex' => $exactRegex]], 90, 0]],
                                ['$cond' => [['$eq' => ['$serial_number', is_numeric($search) ? (int) $search : $search]], 80, 0]],
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$title', '']], 'regex' => $startRegex]], 50, 0]],
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$slug', '']], 'regex' => $startRegex]], 40, 0]],
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$title', '']], 'regex' => $regex]], 30, 0]],
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$slug', '']], 'regex' => $regex]], 20, 0]],
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$journal_title', '']], 'regex' => $regex]], 10, 0]],
                                ['$cond' => [['$regexMatch' => ['input' => ['$ifNull' => ['$doi', '']], 'regex' => $regex]], 10, 0]],
                            ],
                        ],
                    ],
                ],
                [
                    '$facet' => [
                        'metadata' => [['$count' => 'total']],
                        'data' => [
                            ['$sort' => $sort],
                            ['$skip' => ($page - 1) * $this->perPage],
                            ['$limit' => $this->perPage],
                        ],
                    ],
                ],
            ];

            $rawResult = Publication::raw(function ($collection) use ($pipeline) {
                return $collection->aggregate($pipeline);
            });

            $result = iterator_to_array($rawResult)[0] ?? null;
            $total = $result['metadata'][0]['total'] ?? 0;
            $rawDocs = iterator_to_array($result['data'] ?? []);
            $items = Publication::hydrate($rawDocs);

            $publications = new LengthAwarePaginator($items, $total, $this->perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);

            return ['publications' => $publications];
        }

        $query = Publication::query();

        if ($this->statusFilter !== 'all') {
            $intVal = in_array($this->statusFilter, ['1', 1, 'active', true], true) ? 1 : 0;
            $query->where('status', $intVal);
        }

        return [
            'publications' => $query->orderBy($this->sortField, $this->sortDirection)->paginate($this->perPage),
        ];
    }
}; ?>

<div 
    x-data="{
        selectedRows: [],
        selectAll: false,
        allIds: {{ json_encode(collect($publications->items())->pluck('_id')->map(fn ($id) => (string) $id)->all()) }},
        toggleAll() {
            if (this.selectAll) {
                this.selectedRows = [...this.allIds];
            } else {
                this.selectedRows = [];
            }
        },
        updateSelectAll() {
            this.selectAll = this.allIds.length > 0 && this.allIds.every(id => this.selectedRows.includes(id));
        }
    }"
    x-effect="allIds = {{ json_encode(collect($publications->items())->pluck('_id')->map(fn ($id) => (string) $id)->all()) }}; updateSelectAll();"
    class="space-y-6"
>
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-white">Publications Management</h2>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">Review, approve, and manage all research publications and preprints.</p>
        </div>
        <a 
            href="{{ route('articles.deposit') }}" 
            target="_blank"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-brand text-white hover:bg-brand-600 transition shadow-sm shadow-brand/25 shrink-0"
        >
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Deposit New Article
        </a>
    </div>

    <!-- BULK ACTIONS FLOATING/ACTIVE BAR (Client-side Alpine toggle, zero server roundtrips) -->
    <div 
        x-show="selectedRows.length > 0" 
        x-cloak 
        x-transition
        class="flex flex-wrap items-center justify-between gap-3 bg-dark-navy text-white px-4 py-3 rounded-2xl shadow-lg border border-zinc-700/50"
    >
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center px-2.5 py-1 text-xs font-bold bg-brand text-white rounded-lg">
                <span x-text="selectedRows.length"></span> Selected
            </span>
            <button 
                type="button" 
                @click="selectedRows = []; selectAll = false" 
                class="text-xs text-zinc-300 hover:text-white underline cursor-pointer"
            >
                Deselect all
            </button>
        </div>

        <div class="flex items-center gap-2">
            <!-- Toggle Publication Status Button -->
            <button 
                type="button" 
                @click="$wire.toggleSelectedStatus(selectedRows); selectedRows = []; selectAll = false" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-800 text-zinc-100 hover:bg-brand hover:text-white border border-zinc-700 transition cursor-pointer"
            >
                <svg class="size-3.5 text-brand group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                Toggle Publication Status
            </button>

            <!-- Delete Selected Button -->
            <button 
                type="button" 
                @click="if(confirm('Are you sure you want to delete the selected publication(s)?')) { $wire.deleteSelected(selectedRows); selectedRows = []; selectAll = false }"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-red-500/20 text-red-300 hover:bg-red-600 hover:text-white border border-red-500/40 transition cursor-pointer"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Delete Selected
            </button>
        </div>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <!-- Search Input -->
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search publications by title, author, journal, or DOI..." 
                class="w-full h-10 pl-10 pr-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
            />
        </div>

        <!-- Filter Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
            <button 
                type="button" 
                wire:click="$set('statusFilter', 'all')"
                class="px-3 py-2 text-xs font-semibold rounded-xl transition {{ $statusFilter === 'all' ? 'bg-brand text-white shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                All
            </button>
            <button 
                type="button" 
                wire:click="$set('statusFilter', '1')"
                class="px-3 py-2 text-xs font-semibold rounded-xl transition {{ $statusFilter === '1' ? 'bg-brand text-white shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                Active (1)
            </button>
            <button 
                type="button" 
                wire:click="$set('statusFilter', '0')"
                class="px-3 py-2 text-xs font-semibold rounded-xl transition {{ $statusFilter === '0' ? 'bg-brand text-white shadow-xs' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                Inactive (0)
            </button>

            <!-- Per Page Selector -->
            <select 
                wire:model.live="perPage" 
                class="h-9 px-2.5 bg-zinc-100 dark:bg-zinc-800 border-none rounded-xl text-xs font-semibold text-zinc-700 dark:text-zinc-300 focus:ring-2 focus:ring-brand/20 cursor-pointer"
            >
                <option value="10">10 / page</option>
                <option value="15">15 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>
        </div>
    </div>

    <!-- PUBLICATIONS TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-100 dark:bg-zinc-900/90 text-[11px] font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-200 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <!-- Checkbox Master Column -->
                        <th class="px-4 py-3.5 w-12 text-center">
                            <input 
                                type="checkbox" 
                                x-model="selectAll"
                                @change="toggleAll()"
                                class="size-4 rounded text-brand focus:ring-brand/20 border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 cursor-pointer"
                            />
                        </th>

                        <!-- No (Static) -->
                        <th class="px-4 py-3.5 w-14 text-center font-bold text-[11px] uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            No
                        </th>

                        <!-- Title -->
                        <th class="px-5 py-3.5">
                            <button 
                                type="button" 
                                wire:click="sortBy('title')" 
                                class="group inline-flex items-center gap-1.5 font-bold text-[11px] uppercase tracking-wider {{ $sortField === 'title' ? 'text-zinc-900 dark:text-white font-extrabold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }} focus:outline-none focus:ring-0 transition cursor-pointer select-none"
                            >
                                <span>Title</span>
                                <span class="inline-flex items-center gap-0.5 ml-1">
                                    <!-- Up Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'title' && $sortDirection === 'asc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="19" x2="12" y2="5"></line>
                                        <polyline points="5 12 12 5 19 12"></polyline>
                                    </svg>
                                    <!-- Down Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'title' && $sortDirection === 'desc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <polyline points="19 12 12 19 5 12"></polyline>
                                    </svg>
                                </span>
                            </button>
                        </th>

                        <!-- Journal Name -->
                        <th class="px-5 py-3.5">
                            <button 
                                type="button" 
                                wire:click="sortBy('journal_name')" 
                                class="group inline-flex items-center gap-1.5 font-bold text-[11px] uppercase tracking-wider {{ $sortField === 'journal_name' ? 'text-zinc-900 dark:text-white font-extrabold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }} focus:outline-none focus:ring-0 transition cursor-pointer select-none"
                            >
                                <span>Journal Name</span>
                                <span class="inline-flex items-center gap-0.5 ml-1">
                                    <!-- Up Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'journal_name' && $sortDirection === 'asc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="19" x2="12" y2="5"></line>
                                        <polyline points="5 12 12 5 19 12"></polyline>
                                    </svg>
                                    <!-- Down Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'journal_name' && $sortDirection === 'desc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <polyline points="19 12 12 19 5 12"></polyline>
                                    </svg>
                                </span>
                            </button>
                        </th>

                        <!-- Status -->
                        <th class="px-5 py-3.5">
                            <button 
                                type="button" 
                                wire:click="sortBy('status')" 
                                class="group inline-flex items-center gap-1.5 font-bold text-[11px] uppercase tracking-wider {{ $sortField === 'status' ? 'text-zinc-900 dark:text-white font-extrabold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }} focus:outline-none focus:ring-0 transition cursor-pointer select-none"
                            >
                                <span>Status</span>
                                <span class="inline-flex items-center gap-0.5 ml-1">
                                    <!-- Up Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'status' && $sortDirection === 'asc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="19" x2="12" y2="5"></line>
                                        <polyline points="5 12 12 5 19 12"></polyline>
                                    </svg>
                                    <!-- Down Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'status' && $sortDirection === 'desc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <polyline points="19 12 12 19 5 12"></polyline>
                                    </svg>
                                </span>
                            </button>
                        </th>

                        <!-- Date -->
                        <th class="px-5 py-3.5">
                            <button 
                                type="button" 
                                wire:click="sortBy('published_date')" 
                                class="group inline-flex items-center gap-1.5 font-bold text-[11px] uppercase tracking-wider {{ $sortField === 'published_date' ? 'text-zinc-900 dark:text-white font-extrabold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }} focus:outline-none focus:ring-0 transition cursor-pointer select-none"
                            >
                                <span>Date</span>
                                <span class="inline-flex items-center gap-0.5 ml-1">
                                    <!-- Up Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'published_date' && $sortDirection === 'asc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="19" x2="12" y2="5"></line>
                                        <polyline points="5 12 12 5 19 12"></polyline>
                                    </svg>
                                    <!-- Down Arrow -->
                                    <svg class="size-3.5 {{ $sortField === 'published_date' && $sortDirection === 'desc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <polyline points="19 12 12 19 5 12"></polyline>
                                    </svg>
                                </span>
                            </button>
                        </th>

                        <!-- Actions -->
                        <th class="px-5 py-3.5 text-right font-bold text-[11px] uppercase tracking-wider text-zinc-700 dark:text-zinc-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($publications as $index => $publication)
                        @php
                            $rawStatus = (string) ($publication->status ?? $publication->option ?? '0');
                            $isPub = in_array($publication->status, ['published', 1, '1', true], true) || in_array($publication->option, ['published', 1, '1', true], true);
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition">
                            <!-- Checkbox Row Column -->
                            <td class="px-4 py-4 text-center">
                                <input 
                                    type="checkbox" 
                                    value="{{ (string) $publication->_id }}"
                                    x-model="selectedRows"
                                    @change="updateSelectAll()"
                                    class="size-4 rounded text-brand focus:ring-brand/20 border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 cursor-pointer"
                                />
                            </td>

                            <!-- No -->
                            <td class="px-4 py-4 text-center font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ ($publications->currentPage() - 1) * $publications->perPage() + $index + 1 }}
                            </td>

                            <!-- Title -->
                            <td class="px-5 py-4 min-w-[240px]">
                                <p class="font-bold text-zinc-900 dark:text-white leading-snug">
                                    {{ $publication->title ?: '—' }}
                                </p>
                            </td>

                            <!-- Journal Name -->
                            <td class="px-5 py-4 min-w-[180px]">
                                <p class="font-medium text-zinc-800 dark:text-zinc-200 truncate max-w-xs">
                                    {{ $publication->journal_title ?: ($publication->journal_name ?: '—') }}
                                </p>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md {{ $isPub ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }}">
                                    {{ $rawStatus }}
                                </span>
                            </td>

                            <!-- Date -->
                            <td class="px-5 py-4 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                {{ $publication->published_date ?: '—' }}
                            </td>

                            <!-- Action Buttons with Flux UI Tooltips and Flux Icons -->
                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <!-- Edit Icon Button -->
                                    <!-- Edit Icon Link -->
                                    <flux:tooltip content="Edit Publication">
                                        <a 
                                            href="{{ route('admin.publications.edit', (string) $publication->_id) }}" 
                                            wire:navigate
                                            class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-brand hover:text-white dark:hover:bg-brand transition-colors cursor-pointer inline-flex items-center justify-center"
                                        >
                                            <flux:icon name="pencil-square" class="size-4" />
                                        </a>
                                    </flux:tooltip>
                                    
                                    <!-- Toggle Status Icon Button -->
                                    <flux:tooltip content="Toggle Status (Currently: {{ $rawStatus }})">
                                        <button 
                                            type="button" 
                                            wire:click="toggleStatus('{{ $publication->_id }}')" 
                                            class="p-2 rounded-lg {{ $isPub ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 hover:bg-amber-600 hover:text-white' }} transition-colors cursor-pointer"
                                        >
                                            <flux:icon name="arrow-path" class="size-4" />
                                        </button>
                                    </flux:tooltip>

                                    <!-- Delete Icon Button -->
                                    <flux:tooltip content="Delete Publication">
                                        <button 
                                            type="button" 
                                            wire:click="deletePublication('{{ $publication->_id }}')" 
                                            wire:confirm="Are you sure you want to delete this publication record?"
                                            class="p-2 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white transition-colors cursor-pointer"
                                        >
                                            <flux:icon name="trash" class="size-4" />
                                        </button>
                                    </flux:tooltip>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-zinc-400">
                                No publications found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION BAR -->
        @if($publications->hasPages())
            <div class="px-5 py-4 border-t border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $publications->links() }}
            </div>
        @endif
    </div>
</div>
