<?php

use App\Models\Publication;
use App\Models\ReviewerJournal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Publication — Missing / Ghost journal_title Inspector - Admin')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all'; // 'all', '1', '0'

    public string $fallbackFilter = 'all'; // 'all', 'with_fallback', 'without_fallback'

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    public string $statusMessage = 'Ready';

    public ?string $inspectingId = null;

    public ?array $inspectingData = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFallbackFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    protected function toMongoId(string $id): ObjectId|string
    {
        if (strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                return new ObjectId($id);
            } catch (\Exception $e) {
                return $id;
            }
        }

        return $id;
    }

    public function refreshScan(): void
    {
        Cache::forget('orphan_journal_missing_stats');
        $this->statusMessage = 'Refreshed missing journal analysis!';
        $this->resetPage();
    }

    /**
     * Cache both missing journal IDs and the global breakdown counts for fast interaction.
     */
    protected function getMissingJournalStats(): array
    {
        return Cache::remember('orphan_journal_missing_stats', 300, function () {
            $distinctPubJournals = Publication::raw(fn ($col) => $col->distinct('journal_title'));
            $distinctJournals = ReviewerJournal::raw(fn ($col) => $col->distinct('_id'));

            $validMap = [];
            foreach ($distinctJournals as $jid) {
                $validMap[(string) $jid] = true;
            }

            $missing = [];
            foreach ($distinctPubJournals as $pjid) {
                if ($pjid !== null && $pjid !== '' && ! isset($validMap[(string) $pjid])) {
                    $missing[] = $pjid;
                }
            }

            $mObjIds = [];
            $mStrIds = [];
            foreach ($missing as $mId) {
                $strId = (string) $mId;
                $mStrIds[] = $strId;
                if (strlen($strId) === 24 && ctype_xdigit($strId)) {
                    try {
                        $mObjIds[] = new ObjectId($strId);
                    } catch (\Exception $e) {
                    }
                }
            }
            $allTargetJournalIds = array_merge($mObjIds, $mStrIds);

            $baseQuery = Publication::whereIn('journal_title', $allTargetJournalIds);
            $totalCount = (clone $baseQuery)->count();
            $activeCount = (clone $baseQuery)->whereIn('status', [1, '1'])->count();
            $inactiveCount = (clone $baseQuery)->whereIn('status', [0, '0'])->count();
            $withFallbackCount = (clone $baseQuery)->whereNotNull('journal_name')->where('journal_name', '!=', '')->count();

            return [
                'missing_ids' => $missing,
                'target_query_ids' => $allTargetJournalIds,
                'total_missing_distinct' => count($missing),
                'total_orphan_pubs' => $totalCount,
                'active_orphans' => $activeCount,
                'inactive_orphans' => $inactiveCount,
                'with_fallback' => $withFallbackCount,
            ];
        });
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $stats = $this->getMissingJournalStats();
            $query = $this->buildFilteredQuery($stats['target_query_ids'] ?? []);
            $items = $query->take($this->perPage)->get(['_id']);
            $this->selectedRows = $items->map(fn ($p) => (string) $p->_id)->filter()->all();
        } else {
            $this->selectedRows = [];
        }
    }

    protected function buildFilteredQuery(array $targetJournalIds)
    {
        $query = Publication::query()
            ->select([
                '_id',
                'title',
                'slug',
                'journal_title',
                'journal_name',
                'user_id',
                'status',
                'published_date',
                'publication_month_year',
                'created_at',
            ])
            ->whereIn('journal_title', $targetJournalIds);

        if ($this->statusFilter === '1') {
            $query->whereIn('status', [1, '1']);
        } elseif ($this->statusFilter === '0') {
            $query->whereIn('status', [0, '0']);
        }

        if ($this->fallbackFilter === 'with_fallback') {
            $query->whereNotNull('journal_name')->where('journal_name', '!=', '');
        } elseif ($this->fallbackFilter === 'without_fallback') {
            $query->where(function ($q) {
                $q->whereNull('journal_name')->orWhere('journal_name', '');
            });
        }

        $searchClean = trim($this->search);
        if ($searchClean !== '') {
            $query->where(function ($q) use ($searchClean) {
                if (strlen($searchClean) === 24 && ctype_xdigit($searchClean)) {
                    $objId = new ObjectId($searchClean);
                    $q->where('_id', $objId)
                        ->orWhere('_id', $searchClean)
                        ->orWhere('journal_title', $objId)
                        ->orWhere('journal_title', $searchClean)
                        ->orWhere('user_id', $objId)
                        ->orWhere('user_id', $searchClean);
                } else {
                    $regex = new \MongoDB\BSON\Regex(preg_quote($searchClean, '/'), 'i');
                    $q->where('title', 'regex', $regex)
                        ->orWhere('journal_name', 'regex', $regex)
                        ->orWhere('journal_title', 'regex', $regex)
                        ->orWhere('user_id', 'regex', $regex);
                }
            });
        }

        return $query;
    }

    /**
     * Recursively convert MongoDB BSON objects (ObjectId, UTCDateTime, etc.) into scalar PHP primitives for Livewire synthesis.
     */
    protected function sanitizeMongoForLivewire(mixed $data): mixed
    {
        if ($data instanceof ObjectId) {
            return (string) $data;
        }

        if ($data instanceof \MongoDB\BSON\UTCDateTime) {
            return $data->toDateTime()->format('Y-m-d H:i:s');
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format('Y-m-d H:i:s');
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $val) {
                $cleaned[$key] = $this->sanitizeMongoForLivewire($val);
            }

            return $cleaned;
        }

        return $data;
    }

    public function inspectRecord(string $id): void
    {
        $this->inspectingId = $id;
        $mongoId = $this->toMongoId($id);

        $doc = DB::connection('mongodb')->table('publications')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        $this->inspectingData = $doc ? $this->sanitizeMongoForLivewire((array) $doc) : null;
    }

    public function closeInspectModal(): void
    {
        $this->inspectingId = null;
        $this->inspectingData = null;
    }

    public function with(): array
    {
        $stats = $this->getMissingJournalStats();
        $targetJournalIds = $stats['target_query_ids'] ?? [];

        // Filtered & Paginated records with lean projection
        $filteredQuery = $this->buildFilteredQuery($targetJournalIds);
        $paginator = $filteredQuery->latest('_id')->paginate($this->perPage);

        // Preload authors/users if present
        $userIds = [];
        foreach ($paginator as $pub) {
            if (! empty($pub->user_id)) {
                $userIds[] = (string) $pub->user_id;
            }
        }
        $userIds = array_values(array_unique($userIds));
        $userMap = [];
        if (! empty($userIds)) {
            $uObjIds = [];
            $uStrIds = [];
            foreach ($userIds as $uid) {
                $uStrIds[] = $uid;
                if (strlen($uid) === 24 && ctype_xdigit($uid)) {
                    try {
                        $uObjIds[] = new ObjectId($uid);
                    } catch (\Exception $e) {
                    }
                }
            }
            $users = User::whereIn('_id', array_merge($uObjIds, $uStrIds))->get(['_id', 'name', 'first_name', 'last_name', 'email', 'slug', 'status']);
            foreach ($users as $u) {
                $userMap[(string) $u->_id] = $u;
            }
        }

        $formatted = [];
        foreach ($paginator as $pub) {
            $id = (string) $pub->_id;
            $rawJournalTitle = (string) $pub->journal_title;
            $rawJournalName = (string) ($pub->journal_name ?? '');

            $userObj = ! empty($pub->user_id) ? ($userMap[(string) $pub->user_id] ?? null) : null;
            $authorName = $userObj ? (trim($userObj->name ?: (($userObj->first_name ?? '').' '.($userObj->last_name ?? ''))) ?: ($userObj->email ?? 'User #'.$pub->user_id)) : (! empty($pub->user_id) ? 'User #'.$pub->user_id : 'Unassigned');

            $date = '-';
            if (! empty($pub->published_date)) {
                $date = is_string($pub->published_date) ? $pub->published_date : date('d M Y', strtotime((string) $pub->published_date));
            } elseif (! empty($pub->publication_month_year)) {
                $date = $pub->publication_month_year;
            } elseif (! empty($pub->created_at)) {
                $date = date('d M Y', strtotime((string) $pub->created_at));
            }

            $isHexId = strlen($rawJournalTitle) === 24 && ctype_xdigit($rawJournalTitle);
            $hasValidFallbackName = ! empty($rawJournalName) && !(strlen($rawJournalName) === 24 && ctype_xdigit($rawJournalName));

            $formatted[] = [
                'id' => $id,
                'title' => $pub->title ?? 'Untitled Publication',
                'slug' => $pub->slug ?? null,
                'status' => $pub->status,
                'journal_title_raw' => $rawJournalTitle,
                'journal_name' => $rawJournalName,
                'is_hex_id' => $isHexId,
                'has_fallback' => $hasValidFallbackName,
                'author_name' => $authorName,
                'author_slug' => $userObj?->slug ?? null,
                'author_status' => $userObj?->status ?? null,
                'user_id' => (string) ($pub->user_id ?? ''),
                'date' => $date,
            ];
        }

        return [
            'totalOrphanPubsCount' => $stats['total_orphan_pubs'] ?? 0,
            'totalMissingDistinctJournals' => $stats['total_missing_distinct'] ?? 0,
            'activeOrphansCount' => $stats['active_orphans'] ?? 0,
            'inactiveOrphansCount' => $stats['inactive_orphans'] ?? 0,
            'withFallbackCount' => $stats['with_fallback'] ?? 0,
            'problematicRecords' => $formatted,
            'paginator' => $paginator,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Breadcrumb Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                <a href="{{ route('admin.database-fixing.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors">Database Fixing</a>
                <span>/</span>
                <span class="text-zinc-700 dark:text-zinc-300 font-medium">Publication</span>
                <span>/</span>
                <span>Missing journal_title Inspector</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white flex items-center gap-3">
                <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-900/60 shadow-2xs">
                    <flux:icon name="book-open" class="size-6" />
                </div>
                Publication — Missing / Ghost journal_title Inspector
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Scans and diagnoses Publication records where <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-amber-600 dark:text-amber-400 font-mono text-xs">journal_title</code> references a non-existent or deleted <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-mono text-xs">ReviewerJournal</code> ID.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button 
                type="button" 
                wire:click="refreshScan" 
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
            >
                <flux:icon name="arrow-path" wire:loading.class="animate-spin" class="size-4 text-amber-600 dark:text-amber-400" />
                <span>Refresh Scan</span>
            </button>

            <a 
                href="{{ route('admin.database-fixing.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 text-xs font-semibold shadow-2xs transition-colors"
            >
                <flux:icon name="arrow-left" class="size-4" />
                <span>Back to Tools</span>
            </a>
        </div>
    </div>

    <!-- Info Explanation Alert -->
    <div class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-900/40 text-xs text-amber-900 dark:text-amber-300 flex items-start gap-3">
        <flux:icon name="information-circle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
        <div class="space-y-1">
            <p class="font-semibold text-amber-950 dark:text-amber-200">
                Diagnostic & Inspection Tool
            </p>
            <p class="leading-relaxed">
                In MongoDB, <code class="font-mono bg-amber-100 dark:bg-amber-900/40 px-1 py-0.5 rounded text-amber-900 dark:text-amber-200">Publication.journal_title</code> stores the ObjectId reference to <code class="font-mono bg-amber-100 dark:bg-amber-900/40 px-1 py-0.5 rounded text-amber-900 dark:text-amber-200">reviewer_journal._id</code>. The records listed below reference journal IDs that do not exist in the database (deleted or imported without relational constraints).
            </p>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Missing Records -->
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>Orphan Journal Records</span>
                <span class="p-1 rounded-md bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                    <flux:icon name="exclamation-triangle" class="size-4" />
                </span>
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">
                {{ number_format($totalOrphanPubsCount) }}
            </div>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                Publications referencing missing journals
            </p>
        </div>

        <!-- Distinct Ghost Journal IDs -->
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>Unique Ghost Journal IDs</span>
                <span class="p-1 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                    <flux:icon name="finger-print" class="size-4" />
                </span>
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">
                {{ number_format($totalMissingDistinctJournals) }}
            </div>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                Non-existent journal IDs in database
            </p>
        </div>

        <!-- Active vs Inactive -->
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>Active vs Inactive</span>
                <span class="p-1 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="check-circle" class="size-4" />
                </span>
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white flex items-baseline gap-2">
                <span class="text-emerald-600 dark:text-emerald-400">{{ $activeOrphansCount }}</span>
                <span class="text-xs font-normal text-zinc-400">/</span>
                <span class="text-zinc-500 dark:text-zinc-400 text-lg">{{ $inactiveOrphansCount }}</span>
            </div>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                Active (status=1) / Inactive (status=0)
            </p>
        </div>

        <!-- Has Fallback Name -->
        <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                <span>With Fallback Name</span>
                <span class="p-1 rounded-md bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                    <flux:icon name="document-duplicate" class="size-4" />
                </span>
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">
                {{ number_format($withFallbackCount) }}
            </div>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400">
                Has raw text in <code class="font-mono">journal_name</code>
            </p>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl p-5 sm:p-6 shadow-xs space-y-4">
        <!-- Controls Bar -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 pb-2 border-b border-zinc-100 dark:border-zinc-800">
            <!-- Search & Filters -->
            <div class="flex flex-wrap items-center gap-2.5 flex-1">
                <!-- Search Input -->
                <div class="relative w-full sm:w-72">
                    <flux:icon name="magnifying-glass" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search title, ID, ghost ID, user..." 
                        class="w-full pl-9 pr-3 py-1.5 rounded-xl text-xs bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 focus:outline-hidden focus:border-amber-500 transition-colors"
                    />
                </div>

                <!-- Status Filter -->
                <select 
                    wire:model.live="statusFilter"
                    class="px-3 py-1.5 rounded-xl text-xs bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 focus:outline-hidden focus:border-amber-500 transition-colors"
                >
                    <option value="all">All Statuses</option>
                    <option value="1">Active Only (status = 1)</option>
                    <option value="0">Inactive Only (status = 0)</option>
                </select>

                <!-- Fallback Filter -->
                <select 
                    wire:model.live="fallbackFilter"
                    class="px-3 py-1.5 rounded-xl text-xs bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 focus:outline-hidden focus:border-amber-500 transition-colors"
                >
                    <option value="all">All Fallback States</option>
                    <option value="with_fallback">With Fallback journal_name</option>
                    <option value="without_fallback">No Fallback Name</option>
                </select>

                <!-- Per Page -->
                <select 
                    wire:model.live="perPage"
                    class="px-3 py-1.5 rounded-xl text-xs bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 focus:outline-hidden focus:border-amber-500 transition-colors"
                >
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>

            <!-- Total Results Badge -->
            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                Showing <strong class="text-zinc-800 dark:text-zinc-200">{{ $paginator->total() }}</strong> matched records
            </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-zinc-200/80 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 bg-zinc-50/50 dark:bg-zinc-800/30">
                    <tr>
                        <th class="p-3 w-10">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll" 
                                @checked($selectAll) 
                                class="rounded border-zinc-300 dark:border-zinc-700 text-amber-600 focus:ring-amber-500"
                            />
                        </th>
                        <th class="p-3 font-semibold">Publication Title & ID</th>
                        <th class="p-3 font-semibold">Ghost Journal ID (<code class="font-mono text-[11px]">journal_title</code>)</th>
                        <th class="p-3 font-semibold">Fallback (<code class="font-mono text-[11px]">journal_name</code>)</th>
                        <th class="p-3 font-semibold">Author / User</th>
                        <th class="p-3 font-semibold">Status</th>
                        <th class="p-3 font-semibold">Date</th>
                        <th class="p-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    @forelse ($problematicRecords as $row)
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors group">
                            <!-- Checkbox -->
                            <td class="p-3">
                                <input 
                                    type="checkbox" 
                                    value="{{ $row['id'] }}" 
                                    wire:model.live="selectedRows" 
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-amber-600 focus:ring-amber-500"
                                />
                            </td>

                            <!-- Publication Title & ID -->
                            <td class="p-3 max-w-xs">
                                <div class="font-semibold text-zinc-900 dark:text-white line-clamp-2" title="{{ $row['title'] }}">
                                    @if(!empty($row['slug']))
                                        <a href="{{ url('publication-detail/' . $row['slug']) }}" target="_blank" class="hover:text-[#198BEA] hover:underline">
                                            {{ $row['title'] }}
                                        </a>
                                    @else
                                        {{ $row['title'] }}
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] text-zinc-400 font-mono mt-0.5">
                                    <span>ID: {{ $row['id'] }}</span>
                                </div>
                            </td>

                            <!-- Ghost Journal ID -->
                            <td class="p-3">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200/70 dark:border-red-900/50 text-red-700 dark:text-red-300 font-mono text-[11px]">
                                    <flux:icon name="exclamation-circle" class="size-3.5 text-red-500 shrink-0" />
                                    <span>{{ $row['journal_title_raw'] }}</span>
                                </div>
                                <div class="text-[10px] text-zinc-400 mt-0.5">
                                    Not found in <code class="font-mono">reviewer_journal</code>
                                </div>
                            </td>

                            <!-- Fallback journal_name -->
                            <td class="p-3 max-w-[200px]">
                                @if(!empty($row['journal_name']))
                                    @if($row['has_fallback'])
                                        <span class="inline-block font-medium text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/40 px-2 py-0.5 rounded border border-purple-200/60 dark:border-purple-800/60 truncate max-w-full" title="{{ $row['journal_name'] }}">
                                            {{ $row['journal_name'] }}
                                        </span>
                                    @else
                                        <span class="text-[11px] text-zinc-400 font-mono truncate block" title="{{ $row['journal_name'] }}">
                                            {{ $row['journal_name'] }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-zinc-400 italic text-[11px]">None (Null)</span>
                                @endif
                            </td>

                            <!-- Author / User -->
                            <td class="p-3">
                                @if(!empty($row['author_slug']))
                                    <a href="{{ url('profile/' . $row['author_slug']) }}" target="_blank" class="font-medium text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] hover:underline block truncate max-w-[160px]">
                                        {{ $row['author_name'] }}
                                    </a>
                                @else
                                    <span class="text-zinc-700 dark:text-zinc-300 block truncate max-w-[160px]">
                                        {{ $row['author_name'] }}
                                    </span>
                                @endif
                                @if(!empty($row['user_id']))
                                    <span class="text-[10px] text-zinc-400 font-mono block">UID: {{ $row['user_id'] }}</span>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td class="p-3">
                                @if($row['status'] == 1 || $row['status'] === '1')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/70 dark:border-emerald-800/70">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Active ({{ $row['status'] }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                                        Inactive ({{ $row['status'] ?? 'null' }})
                                    </span>
                                @endif
                            </td>

                            <!-- Date -->
                            <td class="p-3 text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $row['date'] }}
                            </td>

                            <!-- Actions -->
                            <td class="p-3 text-right">
                                <button 
                                    type="button" 
                                    wire:click="inspectRecord('{{ $row['id'] }}')" 
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 text-xs font-medium transition-colors cursor-pointer"
                                >
                                    <flux:icon name="eye" class="size-3.5" />
                                    <span>Inspect</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-zinc-400">
                                <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 flex items-center justify-center mx-auto mb-2">
                                    <flux:icon name="check-badge" class="size-6 text-emerald-500" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">No Orphan Journal Records Found</p>
                                <p class="text-xs text-zinc-500 mt-1">No publications matching the current filter criteria reference missing journals.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($paginator->hasPages())
            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>

    <!-- JSON Inspection Modal -->
    @if ($inspectingId && $inspectingData)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-xl max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            <flux:icon name="document-text" class="size-5 text-amber-500" />
                            Publication Document Inspection
                        </h3>
                        <p class="text-xs text-zinc-500 font-mono mt-0.5">_id: {{ $inspectingId }}</p>
                    </div>
                    <button 
                        type="button" 
                        wire:click="closeInspectModal" 
                        class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
                    >
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-3 pr-1 text-xs">
                    <div class="grid grid-cols-2 gap-3 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-xl border border-zinc-200/60 dark:border-zinc-700/60">
                        <div>
                            <span class="text-zinc-400 block text-[10px]">journal_title (Raw)</span>
                            <span class="font-mono font-semibold text-red-600 dark:text-red-400">{{ (string)($inspectingData['journal_title'] ?? 'null') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px]">journal_name (Fallback)</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ (string)($inspectingData['journal_name'] ?? 'null') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px]">status</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ var_export($inspectingData['status'] ?? null, true) }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block text-[10px]">user_id</span>
                            <span class="font-mono text-zinc-800 dark:text-zinc-200">{{ (string)($inspectingData['user_id'] ?? 'null') }}</span>
                        </div>
                    </div>

                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 font-semibold block mb-1">Full Document JSON:</span>
                        <pre class="bg-zinc-950 text-zinc-100 p-4 rounded-xl font-mono text-[11px] overflow-x-auto border border-zinc-800 leading-relaxed">{{ json_encode($inspectingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>

                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                    <button 
                        type="button" 
                        wire:click="closeInspectModal" 
                        class="px-4 py-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs transition"
                    >
                        Close Inspector
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
