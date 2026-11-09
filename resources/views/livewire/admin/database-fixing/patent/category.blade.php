<?php

use App\Models\Category;
use App\Models\Patent;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Patent — category_id Fixer - Admin')] class extends Component {
    use WithPagination;

    public string $tab = 'all'; // 'all', 'non_object_id', 'ghost_category_id', 'empty_category_id'

    public string $search = '';

    public string $statusMessage = 'Ready';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    // Category Assignment Modal State
    public bool $showAssignModal = false;

    public string $assignPatentId = '';

    public string $assignPatentTitle = '';

    public string $categorySearch = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    protected function toMongoId(mixed $id): ObjectId|string
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        if (is_string($id) && strlen(trim($id)) === 24 && ctype_xdigit(trim($id))) {
            try {
                return new ObjectId(trim($id));
            } catch (\Exception $e) {
                return $id;
            }
        }

        return (string) $id;
    }

    /**
     * Scan and build the list of category_id issues for Patent records:
     * 1. Non-ObjectId String: category_id is a text string (e.g. category title) rather than a 24-character hex ObjectId.
     *    -> If it matches a category_title in the category collection, auto-match the Category ObjectId!
     * 2. Ghost Category ID: category_id is a 24-char hex ObjectId, but does not exist in the category collection.
     * 3. Missing Category ID: category_id is null, empty string, or missing.
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_patent_category_issues', 60, function () {
            // 1. Fetch all patents from MongoDB
            $allPatents = DB::connection('mongodb')->table('patent')
                ->get([
                    '_id',
                    'title',
                    'inventor',
                    'assignee',
                    'application_no',
                    'patent_no',
                    'country',
                    'category_id',
                    'filing_date',
                    'publication_date',
                    'status',
                    'user_id',
                ]);

            // 2. Fetch all valid Categories from MongoDB
            $allCategories = DB::connection('mongodb')->table('category')
                ->get(['_id', 'category_title', 'serial_number', 'category_status']);

            $validCategoryIds = [];
            $categoriesByTitle = []; // lowercase title => ['id' => string, 'title' => string]

            foreach ($allCategories as $cat) {
                $cIdStr = (string) ($cat->_id ?? $cat->id ?? '');
                if ($cIdStr === '') {
                    continue;
                }
                $validCategoryIds[$cIdStr] = true;

                $cTitle = trim((string) ($cat->category_title ?? ''));
                if ($cTitle !== '') {
                    $categoriesByTitle[strtolower($cTitle)] = [
                        'id' => $cIdStr,
                        'title' => $cTitle,
                    ];
                }
            }

            $issueList = [];

            foreach ($allPatents as $p) {
                $pidStr = (string) ($p->_id ?? $p->id ?? '');
                $rawCatId = $p->category_id ?? null;
                $catIdStr = is_scalar($rawCatId) ? trim((string) $rawCatId) : '';

                // Issue 1: Empty / Null category_id
                if ($rawCatId === null || $catIdStr === '') {
                    $issueList[] = [
                        'key' => $pidStr,
                        'id' => $pidStr,
                        'type' => 'empty_category_id',
                        'title' => $p->title ?? 'Untitled Patent',
                        'inventor' => $p->inventor ?? null,
                        'assignee' => $p->assignee ?? null,
                        'application_no' => $p->application_no ?? null,
                        'patent_no' => $p->patent_no ?? null,
                        'country' => $p->country ?? null,
                        'user_id' => $p->user_id ?? null,
                        'category_id' => null,
                        'raw_category_id' => null,
                        'status' => $p->status ?? null,
                        'match_found' => false,
                        'suggested_id' => null,
                        'suggested_title' => null,
                        'summary' => 'Patent has no category assigned (null or empty).',
                        'fix_label' => 'Assign Category',
                    ];
                    continue;
                }

                // Issue 2: Non-ObjectId String (e.g. text title or legacy string)
                $is24Hex = strlen($catIdStr) === 24 && ctype_xdigit($catIdStr);
                if (! $is24Hex) {
                    $lowerStr = strtolower($catIdStr);
                    $matched = $categoriesByTitle[$lowerStr] ?? null;

                    $issueList[] = [
                        'key' => $pidStr,
                        'id' => $pidStr,
                        'type' => 'non_object_id',
                        'title' => $p->title ?? 'Untitled Patent',
                        'inventor' => $p->inventor ?? null,
                        'assignee' => $p->assignee ?? null,
                        'application_no' => $p->application_no ?? null,
                        'patent_no' => $p->patent_no ?? null,
                        'country' => $p->country ?? null,
                        'user_id' => $p->user_id ?? null,
                        'category_id' => $catIdStr,
                        'raw_category_id' => $rawCatId,
                        'status' => $p->status ?? null,
                        'match_found' => (bool) $matched,
                        'suggested_id' => $matched['id'] ?? null,
                        'suggested_title' => $matched['title'] ?? null,
                        'summary' => $matched
                            ? "Legacy string [{$catIdStr}] matches Category '{$matched['title']}'."
                            : "Legacy string [{$catIdStr}] is not a valid 24-character MongoDB ObjectId.",
                        'fix_label' => $matched ? "Map to '{$matched['title']}'" : 'Assign Category',
                    ];
                    continue;
                }

                // Issue 3: Ghost Category ID (24-hex string but not found in category collection)
                if (! isset($validCategoryIds[$catIdStr])) {
                    $issueList[] = [
                        'key' => $pidStr,
                        'id' => $pidStr,
                        'type' => 'ghost_category_id',
                        'title' => $p->title ?? 'Untitled Patent',
                        'inventor' => $p->inventor ?? null,
                        'assignee' => $p->assignee ?? null,
                        'application_no' => $p->application_no ?? null,
                        'patent_no' => $p->patent_no ?? null,
                        'country' => $p->country ?? null,
                        'user_id' => $p->user_id ?? null,
                        'category_id' => $catIdStr,
                        'raw_category_id' => $rawCatId,
                        'status' => $p->status ?? null,
                        'match_found' => false,
                        'suggested_id' => null,
                        'suggested_title' => null,
                        'summary' => "Referenced category_id [{$catIdStr}] does not exist in the category collection.",
                        'fix_label' => 'Clean or Reassign',
                    ];
                }
            }

            return $issueList;
        });
    }

    /**
     * Decorate current page items with Owner User details for clear context.
     */
    protected function decoratePageItems(array $pageItems): array
    {
        if (empty($pageItems)) {
            return [];
        }

        $userIds = [];
        foreach ($pageItems as $i) {
            $uid = (string) ($i['user_id'] ?? '');
            if ($uid !== '') {
                $userIds[] = $uid;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));

        $userSearchKeys = [];
        foreach ($userIds as $uid) {
            $userSearchKeys[] = $uid;
            if (strlen($uid) === 24 && ctype_xdigit($uid)) {
                try {
                    $userSearchKeys[] = new ObjectId($uid);
                } catch (\Exception $e) {
                }
            }
        }

        $usersMap = ! empty($userSearchKeys)
            ? User::whereIn('_id', $userSearchKeys)
                ->select(['_id', 'name', 'email', 'unique_id'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        foreach ($pageItems as &$item) {
            $uid = (string) ($item['user_id'] ?? '');
            $u = $usersMap->get($uid);

            $item['user_name'] = $u ? ($u->name ?? 'User') : null;
            $item['user_email'] = $u ? ($u->email ?? '') : null;
            $item['user_unique_id'] = $u ? ($u->unique_id ?? null) : null;
        }
        unset($item);

        return $pageItems;
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $filtered = $this->getFilteredIssues();
            $pageItems = array_slice($filtered, ($this->getPage() - 1) * $this->perPage, $this->perPage);
            $this->selectedRows = array_column($pageItems, 'key');
        } else {
            $this->selectedRows = [];
        }
    }

    protected function getFilteredIssues(): array
    {
        $allIssues = $this->scanIssues();

        // 1. Filter by Tab
        if ($this->tab !== 'all') {
            $allIssues = array_values(array_filter($allIssues, fn ($item) => $item['type'] === $this->tab));
        }

        // 2. Filter by Search Query
        $term = trim($this->search);
        if ($term !== '') {
            $lowerTerm = strtolower($term);

            $allIssues = array_values(array_filter($allIssues, function ($item) use ($lowerTerm) {
                if (str_contains(strtolower($item['title'] ?? ''), $lowerTerm)) {
                    return true;
                }
                if (str_contains(strtolower($item['application_no'] ?? ''), $lowerTerm)) {
                    return true;
                }
                if (str_contains(strtolower($item['patent_no'] ?? ''), $lowerTerm)) {
                    return true;
                }
                if (str_contains(strtolower($item['inventor'] ?? ''), $lowerTerm)) {
                    return true;
                }
                if (str_contains(strtolower($item['assignee'] ?? ''), $lowerTerm)) {
                    return true;
                }
                if (str_contains(strtolower($item['category_id'] ?? ''), $lowerTerm)) {
                    return true;
                }
                if (str_contains(strtolower($item['suggested_title'] ?? ''), $lowerTerm)) {
                    return true;
                }
                if (str_contains(strtolower($item['id'] ?? ''), $lowerTerm)) {
                    return true;
                }

                return false;
            }));
        }

        return $allIssues;
    }

    /**
     * Fix a single issue by key.
     */
    public function fixSingle(string $key): void
    {
        $issues = $this->scanIssues();
        $target = collect($issues)->firstWhere('key', $key);

        if (! $target) {
            $this->statusMessage = "Error: Patent record [{$key}] was not found.";

            return;
        }

        $patentId = $target['id'];
        $mongoId = $this->toMongoId($patentId);

        // Case 1: Has a suggested matching category
        if (! empty($target['suggested_id'])) {
            $catMongoId = $this->toMongoId($target['suggested_id']);

            DB::connection('mongodb')->table('patent')
                ->where('_id', $mongoId)
                ->update([
                    'category_id' => $catMongoId,
                    'updated_at' => now(),
                ]);

            Cache::forget('db_fixing_patent_category_issues');
            $this->statusMessage = "Successfully updated Patent [{$target['title']}] to Category '{$target['suggested_title']}' ({$target['suggested_id']}).";

            return;
        }

        // Case 2: Ghost category -> Clean to null
        if ($target['type'] === 'ghost_category_id') {
            DB::connection('mongodb')->table('patent')
                ->where('_id', $mongoId)
                ->update([
                    'category_id' => null,
                    'updated_at' => now(),
                ]);

            Cache::forget('db_fixing_patent_category_issues');
            $this->statusMessage = "Cleaned ghost category_id to NULL on Patent [{$target['title']}].";

            return;
        }

        // Case 3: Empty category -> Open assignment modal
        $this->openAssignModal($patentId, $target['title']);
    }

    /**
     * Auto-match and update all text strings that match a category_title in one click.
     */
    public function autoMatchAll(): void
    {
        $issues = $this->scanIssues();
        $matched = array_filter($issues, fn ($i) => ! empty($i['suggested_id']));

        if (empty($matched)) {
            $this->statusMessage = 'No auto-matchable string categories found to update.';

            return;
        }

        $count = 0;
        foreach ($matched as $item) {
            $mongoId = $this->toMongoId($item['id']);
            $catMongoId = $this->toMongoId($item['suggested_id']);

            DB::connection('mongodb')->table('patent')
                ->where('_id', $mongoId)
                ->update([
                    'category_id' => $catMongoId,
                    'updated_at' => now(),
                ]);
            $count++;
        }

        Cache::forget('db_fixing_patent_category_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully auto-matched and updated {$count} Patent category records!";
    }

    /**
     * Open Modal to Assign Category.
     */
    public function openAssignModal(string $patentId = '', string $patentTitle = ''): void
    {
        $this->assignPatentId = $patentId;
        $this->assignPatentTitle = $patentTitle;
        $this->categorySearch = '';
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignPatentId = '';
        $this->assignPatentTitle = '';
        $this->categorySearch = '';
    }

    /**
     * Assign selected category to either the single active patent or all selected rows.
     */
    public function assignCategory(string $categoryId, string $categoryTitle): void
    {
        $catMongoId = $this->toMongoId($categoryId);

        // Case A: Single patent modal
        if (! empty($this->assignPatentId)) {
            $mongoId = $this->toMongoId($this->assignPatentId);

            DB::connection('mongodb')->table('patent')
                ->where('_id', $mongoId)
                ->update([
                    'category_id' => $catMongoId,
                    'updated_at' => now(),
                ]);

            $this->statusMessage = "Assigned Category '{$categoryTitle}' to Patent [{$this->assignPatentTitle}].";
        }
        // Case B: Batch assign to selected rows
        elseif (! empty($this->selectedRows)) {
            $count = 0;
            foreach ($this->selectedRows as $pid) {
                $mongoId = $this->toMongoId($pid);
                DB::connection('mongodb')->table('patent')
                    ->where('_id', $mongoId)
                    ->update([
                        'category_id' => $catMongoId,
                        'updated_at' => now(),
                    ]);
                $count++;
            }

            $this->selectedRows = [];
            $this->selectAll = false;
            $this->statusMessage = "Assigned Category '{$categoryTitle}' to {$count} selected Patent records.";
        }

        Cache::forget('db_fixing_patent_category_issues');
        $this->closeAssignModal();
    }

    /**
     * Get list of Categories for the assignment modal.
     */
    public function getCategoriesProperty()
    {
        $term = trim($this->categorySearch);

        $query = Category::query();
        if ($term !== '') {
            $query->where('category_title', 'like', "%{$term}%");
        }

        return $query->orderBy('serial_number', 'asc')->limit(50)->get();
    }

    public function with(): array
    {
        $allIssues = $this->scanIssues();

        // Count per issue type
        $stats = [
            'total' => count($allIssues),
            'non_object_id' => count(array_filter($allIssues, fn ($i) => $i['type'] === 'non_object_id')),
            'ghost_category_id' => count(array_filter($allIssues, fn ($i) => $i['type'] === 'ghost_category_id')),
            'empty_category_id' => count(array_filter($allIssues, fn ($i) => $i['type'] === 'empty_category_id')),
            'auto_matchable' => count(array_filter($allIssues, fn ($i) => ! empty($i['suggested_id']))),
        ];

        $filtered = $this->getFilteredIssues();
        $totalFiltered = count($filtered);

        $currentPage = $this->getPage();
        $offset = ($currentPage - 1) * $this->perPage;
        $sliced = array_slice($filtered, $offset, $this->perPage);

        // Decorate current page items
        $pageItems = $this->decoratePageItems($sliced);

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $totalFiltered,
            $this->perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'stats' => $stats,
            'issues' => $paginator,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Top Breadcrumbs / Switcher Nav -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-4">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.database-fixing.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200">
                <flux:icon.arrow-left class="size-3.5" />
                <span>Back to Database Fixing Hub</span>
            </a>
            <span class="text-zinc-300 dark:text-zinc-700">/</span>
            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Patent</span>
        </div>

        <!-- Tool Switcher Pills -->
        <div class="inline-flex rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md bg-white dark:bg-zinc-900 text-fuchsia-700 dark:text-fuchsia-300 shadow-xs">
                <flux:icon.tag class="size-3.5" />
                <span>Category Fixer</span>
            </span>
            <a href="{{ \Illuminate\Support\Facades\Route::has('admin.database-fixing.patent.reviewer-profile') ? route('admin.database-fixing.patent.reviewer-profile') : '#' }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
                <flux:icon.user-group class="size-3.5" />
                <span>Reviewer Profile & User Sync</span>
            </a>
        </div>
    </div>

    <!-- Page Header & Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center size-8 rounded-lg bg-fuchsia-100 dark:bg-fuchsia-950/60 text-fuchsia-600 dark:text-fuchsia-400">
                    <flux:icon.tag class="size-4" />
                </span>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                    Patent — category_id (Category Fixer & Standardizer)
                </h1>
            </div>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Scans all Patent documents to validate <code>category_id</code> ObjectIds, auto-match legacy title strings against Category titles, detect ghost references, and assign missing categories.
            </p>
        </div>

        @if ($statusMessage !== 'Ready')
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-medium animate-fade-in">
                <flux:icon.check-circle class="size-4 shrink-0 text-emerald-500" />
                <span>{{ $statusMessage }}</span>
            </div>
        @endif
    </div>

    <!-- Collapsible Transformation Summary -->
    <div x-data="{ open: false }" class="rounded-xl border border-fuchsia-200/70 dark:border-fuchsia-900/40 bg-fuchsia-50/40 dark:bg-fuchsia-950/20 p-4">
        <button @click="open = !open" type="button" class="w-full flex items-center justify-between text-left">
            <div class="flex items-center gap-2">
                <flux:icon.information-circle class="size-4 text-fuchsia-600 dark:text-fuchsia-400" />
                <span class="text-sm font-semibold text-fuchsia-950 dark:text-fuchsia-200">
                    What does this tool do? (Plain English Transformation Summary)
                </span>
            </div>
            <flux:icon.chevron-down class="size-4 text-fuchsia-600 dark:text-fuchsia-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
        </button>

        <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-fuchsia-200/50 dark:border-fuchsia-900/30 text-xs text-zinc-600 dark:text-zinc-300 space-y-2">
            <p>
                In the <strong><code>patent</code></strong> collection, each intellectual property document has a <strong><code>category_id</code></strong> referencing a research category (such as <em>Art & Humanities</em>, <em>Computer Science</em>, etc.).
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
                <div class="p-2.5 rounded-lg bg-white/70 dark:bg-zinc-900/60 border border-fuchsia-100 dark:border-fuchsia-900/30">
                    <span class="font-semibold text-fuchsia-900 dark:text-fuchsia-200">1. Text Title / Non-ObjectId:</span>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">If a record stores a raw text string title instead of a 24-character hex ObjectId, this tool checks if that string matches any <code>category.category_title</code> (case-insensitively) and auto-maps it to the matching MongoDB ObjectId.</p>
                </div>
                <div class="p-2.5 rounded-lg bg-white/70 dark:bg-zinc-900/60 border border-fuchsia-100 dark:border-fuchsia-900/30">
                    <span class="font-semibold text-fuchsia-900 dark:text-fuchsia-200">2. Missing / Empty Category:</span>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">Patents with <code>null</code> or empty category are highlighted, allowing administrators to assign a standard Category individually or in bulk.</p>
                </div>
                <div class="p-2.5 rounded-lg bg-white/70 dark:bg-zinc-900/60 border border-fuchsia-100 dark:border-fuchsia-900/30">
                    <span class="font-semibold text-fuchsia-900 dark:text-fuchsia-200">3. Ghost Category IDs:</span>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">If a patent references a category ID that no longer exists in the <code>category</code> collection, it is detected and can be cleared to <code>NULL</code> or reassigned safely.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Badges / Quick Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Category Issues</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total']) }}</span>
                @if ($stats['total'] === 0)
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Clean</span>
                @endif
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/40 bg-rose-50/40 dark:bg-rose-950/20">
            <span class="text-xs font-medium text-rose-700 dark:text-rose-400">Non-ObjectId / Text String</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-rose-900 dark:text-rose-100">{{ number_format($stats['non_object_id']) }}</span>
                @if ($stats['auto_matchable'] > 0)
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">({{ $stats['auto_matchable'] }} Matchable)</span>
                @endif
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/40 dark:bg-amber-950/20">
            <span class="text-xs font-medium text-amber-800 dark:text-amber-400">Missing / Empty Category</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-amber-950 dark:text-amber-100">{{ number_format($stats['empty_category_id']) }}</span>
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Ghost Category IDs</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['ghost_category_id']) }}</span>
            </div>
        </div>
    </div>

    <!-- Filter Tabs, Search & Action Bar -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
        <!-- Filter Tabs -->
        <div class="flex items-center gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-zinc-800 overflow-x-auto text-xs font-medium">
            <button wire:click="setTab('all')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'all' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                All Issues ({{ $stats['total'] }})
            </button>
            <button wire:click="setTab('non_object_id')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'non_object_id' ? 'bg-white dark:bg-zinc-900 text-rose-700 dark:text-rose-300 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Text Title / Non-ObjectId ({{ $stats['non_object_id'] }})
            </button>
            <button wire:click="setTab('empty_category_id')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'empty_category_id' ? 'bg-white dark:bg-zinc-900 text-amber-800 dark:text-amber-300 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Missing Category ({{ $stats['empty_category_id'] }})
            </button>
            <button wire:click="setTab('ghost_category_id')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'ghost_category_id' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Ghost Category IDs ({{ $stats['ghost_category_id'] }})
            </button>
        </div>

        <!-- Search Bar & Batch Actions -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[220px]">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search title, patent no, inventor..." size="sm" icon="magnifying-glass" clearable />
            </div>

            @if ($stats['auto_matchable'] > 0)
                <button wire:click="autoMatchAll" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <flux:icon.arrow-path class="size-3.5" wire:loading.class="animate-spin" wire:target="autoMatchAll" />
                    <span>Auto-Match All ({{ $stats['auto_matchable'] }})</span>
                </button>
            @endif

            @if (!empty($selectedRows))
                <button wire:click="openAssignModal" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-fuchsia-600 hover:bg-fuchsia-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <flux:icon.tag class="size-3.5" />
                    <span>Assign Category to Selected ({{ count($selectedRows) }})</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Issues Table -->
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/75 dark:bg-zinc-800/40 text-zinc-600 dark:text-zinc-400">
                    <tr>
                        <th class="w-8 px-3 py-2.5">
                            <input type="checkbox" wire:click="toggleSelectAll" @checked($selectAll) class="rounded border-zinc-300 dark:border-zinc-700 text-fuchsia-600 focus:ring-fuchsia-500 size-3.5" />
                        </th>
                        <th class="px-3 py-2.5 font-semibold">Patent Details</th>
                        <th class="px-3 py-2.5 font-semibold">Current category_id</th>
                        <th class="px-3 py-2.5 font-semibold">Detected Issue</th>
                        <th class="px-3 py-2.5 font-semibold">Suggested / Target Fix</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($issues as $item)
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors {{ in_array($item['key'], $selectedRows) ? 'bg-fuchsia-50/30 dark:bg-fuchsia-950/20' : '' }}">
                            <!-- Checkbox -->
                            <td class="px-3 py-3">
                                <input type="checkbox" wire:model.live="selectedRows" value="{{ $item['key'] }}" class="rounded border-zinc-300 dark:border-zinc-700 text-fuchsia-600 focus:ring-fuchsia-500 size-3.5" />
                            </td>

                            <!-- Patent Details -->
                            <td class="px-3 py-3 max-w-[340px]">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100 line-clamp-1" title="{{ $item['title'] }}">
                                    {{ $item['title'] }}
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                                    @if ($item['application_no'])
                                        <span class="inline-flex items-center gap-0.5">
                                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">App:</span> {{ $item['application_no'] }}
                                        </span>
                                    @endif
                                    @if ($item['patent_no'])
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                        <span class="inline-flex items-center gap-0.5">
                                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">Pat:</span> {{ $item['patent_no'] }}
                                        </span>
                                    @endif
                                    @if ($item['inventor'])
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                        <span class="truncate max-w-[150px]" title="{{ $item['inventor'] }}">
                                            {{ $item['inventor'] }}
                                        </span>
                                    @endif
                                </div>
                                @if (!empty($item['user_name']))
                                    <div class="mt-0.5 text-[10px] text-zinc-400 dark:text-zinc-500">
                                        Owner: <span class="text-zinc-600 dark:text-zinc-300">{{ $item['user_name'] }}</span>
                                        @if ($item['user_unique_id'])
                                            <span>({{ $item['user_unique_id'] }})</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- Current category_id -->
                            <td class="px-3 py-3">
                                @if ($item['category_id'])
                                    <span class="inline-block font-mono text-[11px] px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                        {{ $item['category_id'] }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800">
                                        <flux:icon.exclamation-triangle class="size-3" />
                                        NULL / Empty
                                    </span>
                                @endif
                            </td>

                            <!-- Detected Issue -->
                            <td class="px-3 py-3">
                                @if ($item['type'] === 'non_object_id')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        Text Title / Non-ObjectId
                                    </span>
                                @elseif ($item['type'] === 'empty_category_id')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        Missing Category
                                    </span>
                                @elseif ($item['type'] === 'ghost_category_id')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300 border border-red-200 dark:border-red-800">
                                        Ghost Category ID
                                    </span>
                                @endif
                                <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400 max-w-[260px]">
                                    {{ $item['summary'] }}
                                </p>
                            </td>

                            <!-- Suggested / Target Fix -->
                            <td class="px-3 py-3">
                                @if ($item['suggested_id'])
                                    <div class="inline-flex flex-col gap-0.5">
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                            <flux:icon.check class="size-3" />
                                            {{ $item['suggested_title'] }}
                                        </span>
                                        <span class="font-mono text-[10px] text-zinc-400">
                                            {{ $item['suggested_id'] }}
                                        </span>
                                    </div>
                                @elseif ($item['type'] === 'ghost_category_id')
                                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 italic">
                                        Clean to NULL or Reassign
                                    </span>
                                @else
                                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 italic">
                                        Assign via Modal
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-3 text-right">
                                @if ($item['suggested_id'])
                                    <button wire:click="fixSingle('{{ $item['key'] }}')" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-xs transition-colors">
                                        <flux:icon.check class="size-3" />
                                        <span>Auto-Fix</span>
                                    </button>
                                @elseif ($item['type'] === 'ghost_category_id')
                                    <div class="inline-flex items-center gap-1">
                                        <button wire:click="fixSingle('{{ $item['key'] }}')" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-zinc-600 hover:bg-zinc-700 text-white font-medium text-[11px] shadow-xs transition-colors">
                                            <span>Set NULL</span>
                                        </button>
                                        <button wire:click="openAssignModal('{{ $item['id'] }}', '{{ addslashes($item['title']) }}')" type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-fuchsia-600 hover:bg-fuchsia-700 text-white font-medium text-[11px] shadow-xs transition-colors">
                                            <span>Assign</span>
                                        </button>
                                    </div>
                                @else
                                    <button wire:click="openAssignModal('{{ $item['id'] }}', '{{ addslashes($item['title']) }}')" type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-fuchsia-600 hover:bg-fuchsia-700 text-white font-medium text-xs shadow-xs transition-colors">
                                        <flux:icon.tag class="size-3" />
                                        <span>Assign</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="size-10 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                        <flux:icon.check class="size-5" />
                                    </div>
                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        No Category Issues Found
                                    </h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">
                                        All Patent records in the filtered view have valid 24-character MongoDB ObjectIds pointing to existing Category documents.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginator Links -->
        @if ($issues->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800">
                {{ $issues->links() }}
            </div>
        @endif
    </div>

    <!-- Category Assignment Modal -->
    @if ($showAssignModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-zinc-950/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                            <flux:icon.tag class="size-4 text-fuchsia-600" />
                            <span>Select Category to Assign</span>
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            @if (!empty($assignPatentTitle))
                                Assigning to: <span class="font-semibold text-zinc-700 dark:text-zinc-300 truncate inline-block max-w-[280px] align-bottom">{{ $assignPatentTitle }}</span>
                            @else
                                Assigning to {{ count($selectedRows) }} selected Patent records
                            @endif
                        </p>
                    </div>
                    <button wire:click="closeAssignModal" type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4">
                    <!-- Search Input -->
                    <div>
                        <flux:input wire:model.live.debounce.250ms="categorySearch" placeholder="Search category by title..." size="sm" icon="magnifying-glass" autofocus clearable />
                    </div>

                    <!-- Category List -->
                    <div class="max-h-72 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        @forelse ($this->categories as $cat)
                            <div class="p-3 flex items-center justify-between hover:bg-fuchsia-50/40 dark:hover:bg-fuchsia-950/20 transition-colors">
                                <div class="min-w-0 pr-3">
                                    <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $cat->category_title }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                        ID: {{ (string) $cat->_id }}
                                        @if ($cat->serial_number)
                                            <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                            <span>#{{ $cat->serial_number }}</span>
                                        @endif
                                    </div>
                                </div>
                                <button wire:click="assignCategory('{{ (string) $cat->_id }}', '{{ addslashes($cat->category_title) }}')" type="button" class="shrink-0 px-2.5 py-1 rounded-md bg-fuchsia-600 hover:bg-fuchsia-700 text-white text-xs font-semibold shadow-xs transition-colors">
                                    Select
                                </button>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-zinc-500">
                                No categories matching your search.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40 flex justify-end">
                    <button wire:click="closeAssignModal" type="button" class="px-3 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

