<?php

use App\Models\Question;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

new #[Layout('components.layouts.admin')] #[Title('Question — Duplicate Slug & Title Resolver - Admin')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = 'all'; // 'all', 'both', 'same_title', 'same_slug'

    public string $statusMessage = 'Ready';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    protected function toMongoId(mixed $id): mixed
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        $idStr = trim((string) $id);
        if (strlen($idStr) === 24 && ctype_xdigit($idStr)) {
            try {
                return new ObjectId($idStr);
            } catch (\Exception $e) {
                return $id;
            }
        }

        return $id;
    }

    /**
     * Scan all questions from MongoDB and find duplicates grouped into the 3 categories.
     *
     * @return array{items: array, counts: array{total: int, both: int, same_title: int, same_slug: int}}
     */
    protected function analyzeAllSlugsAndTitles(): array
    {
        $allQuestions = DB::connection('mongodb')->table('question')->get();

        $slugMap = [];
        $titleMap = [];
        $existingSlugs = [];

        foreach ($allQuestions as $item) {
            $id = (string) (is_array($item) ? ($item['_id'] ?? $item['id'] ?? '') : ($item->_id ?? $item->id ?? ''));
            $slug = trim((string) (is_array($item) ? ($item['slug'] ?? '') : ($item->slug ?? '')));
            $title = trim((string) (is_array($item) ? ($item['title'] ?? '') : ($item->title ?? '')));
            $titleLower = strtolower($title);

            if ($slug !== '') {
                $slugMap[$slug][] = $id;
                $existingSlugs[$slug] = true;
            }
            if ($titleLower !== '') {
                $titleMap[$titleLower][] = $id;
            }
        }

        // Preload users
        $userIds = collect($allQuestions)->pluck('user_id')->filter()->unique()->all();
        $userSearchKeys = [];
        foreach ($userIds as $uid) {
            $uidStr = (string) $uid;
            if ($uidStr !== '') {
                $userSearchKeys[] = $uidStr;
                if (strlen($uidStr) === 24 && ctype_xdigit($uidStr)) {
                    try {
                        $userSearchKeys[] = new ObjectId($uidStr);
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        $userMap = [];
        if (! empty($userSearchKeys)) {
            $loadedUsers = User::whereIn('_id', $userSearchKeys)->get();
            foreach ($loadedUsers as $u) {
                $userMap[(string) $u->_id] = $u;
            }
        }

        $duplicates = [];
        $counts = [
            'total' => 0,
            'both' => 0,
            'same_title' => 0,
            'same_slug' => 0,
        ];

        // Track allocated target slugs in this pass
        $allocatedSlugs = [];

        // Group collisions by slug or title
        $processedGroupIds = [];

        foreach ($allQuestions as $item) {
            $id = (string) (is_array($item) ? ($item['_id'] ?? $item['id'] ?? '') : ($item->_id ?? $item->id ?? ''));
            $slug = trim((string) (is_array($item) ? ($item['slug'] ?? '') : ($item->slug ?? '')));
            $title = trim((string) (is_array($item) ? ($item['title'] ?? '') : ($item->title ?? '')));
            $titleLower = strtolower($title);

            $slugCollisions = $slug !== '' ? ($slugMap[$slug] ?? []) : [];
            $titleCollisions = $titleLower !== '' ? ($titleMap[$titleLower] ?? []) : [];

            $hasSlugCollision = count($slugCollisions) > 1;
            $hasTitleCollision = count($titleCollisions) > 1;
            $isMissingSlug = $slug === '';

            // If no collision and has a slug, skip
            if (! $hasSlugCollision && ! $hasTitleCollision && ! $isMissingSlug) {
                continue;
            }

            // Determine category
            $category = 'same_slug';
            $categoryLabel = 'Same Slug';

            if ($hasSlugCollision && $hasTitleCollision) {
                $category = 'both';
                $categoryLabel = 'Same Title & Same Slug';
            } elseif ($hasTitleCollision && ! $hasSlugCollision) {
                $category = 'same_title';
                $categoryLabel = 'Same Title';
            } elseif ($isMissingSlug) {
                $category = 'same_slug';
                $categoryLabel = 'Missing Slug';
            }

            $userIdStr = (string) (is_array($item) ? ($item['user_id'] ?? '') : ($item->user_id ?? ''));
            $user = $userMap[$userIdStr] ?? null;
            $userName = 'Unknown User';
            $userEmail = 'N/A';
            if ($user) {
                $fullName = trim($user->name ?: (($user->first_name ?? '').' '.($user->last_name ?? '')));
                $userName = $fullName !== '' ? $fullName : ($user->email ?? 'User #'.$userIdStr);
                $userEmail = $user->email ?? 'N/A';
            } elseif ($userIdStr !== '') {
                $userName = 'User #'.substr($userIdStr, 0, 8);
            }

            $createdAt = is_array($item) ? ($item['created_at'] ?? null) : ($item->created_at ?? null);
            $dateStr = '-';
            if ($createdAt) {
                $dateStr = is_string($createdAt) ? substr($createdAt, 0, 10) : ($createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d') : (string) $createdAt);
            }

            // Determine position in the collision group
            // Oldest/First record keeps original clean slug; subsequent records get slug-1, slug-2, etc.
            $groupList = $hasSlugCollision ? $slugCollisions : $titleCollisions;
            $indexInGroup = array_search($id, $groupList, true);
            $isOriginal = ($indexInGroup === 0 && ! $isMissingSlug && ! $hasTitleCollision && ! $hasSlugCollision);

            // Compute target unique slug
            $baseSlug = $slug !== '' ? preg_replace('/-\d+$/', '', $slug) : Str::slug($title);
            if (empty($baseSlug)) {
                $baseSlug = 'question-'.substr($id, -6);
            }

            // Find next available slug
            $targetSlug = $baseSlug;
            if ($indexInGroup > 0 || $hasSlugCollision || $isMissingSlug) {
                $suffix = max(1, (int) $indexInGroup);
                $candidate = "{$baseSlug}-{$suffix}";

                // Check against existing database slugs (excluding this record) and allocated in this batch
                $counter = $suffix;
                while (
                    (isset($existingSlugs[$candidate]) && ($existingSlugs[$candidate] !== $id))
                    || isset($allocatedSlugs[$candidate])
                ) {
                    $candidate = "{$baseSlug}-{$counter}";
                    $counter++;
                }
                $targetSlug = $candidate;
            }

            $allocatedSlugs[$targetSlug] = $id;

            $needsFix = ($slug !== $targetSlug) || $isMissingSlug || $hasSlugCollision;

            // Increment stats counts
            $counts['total']++;
            if ($category === 'both') {
                $counts['both']++;
            } elseif ($category === 'same_title') {
                $counts['same_title']++;
            } elseif ($category === 'same_slug') {
                $counts['same_slug']++;
            }

            $duplicates[] = [
                'id' => $id,
                'title' => $title ?: 'Untitled',
                'current_slug' => $slug ?: '(empty)',
                'target_slug' => $targetSlug,
                'category' => $category,
                'category_label' => $categoryLabel,
                'collision_count' => max(count($slugCollisions), count($titleCollisions)),
                'index_in_group' => $indexInGroup !== false ? ((int) $indexInGroup + 1) : 1,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'date' => $dateStr,
                'needs_fix' => $needsFix,
            ];
        }

        return [
            'items' => $duplicates,
            'counts' => $counts,
        ];
    }

    protected function paginateCollection(array $items): LengthAwarePaginator
    {
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $collection = collect($items);
        $currentPageItems = $collection->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $collection->count(),
            $this->perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $analysis = $this->analyzeAllSlugsAndTitles();
            $filtered = $this->filterRecords($analysis['items']);
            $paginator = $this->paginateCollection($filtered);
            $this->selectedRows = collect($paginator->items())->pluck('id')->all();
        } else {
            $this->selectedRows = [];
        }
    }

    protected function filterRecords(array $items): array
    {
        return collect($items)
            ->filter(function ($row) {
                // Category Filter
                if ($this->categoryFilter === 'both' && $row['category'] !== 'both') {
                    return false;
                }
                if ($this->categoryFilter === 'same_title' && $row['category'] !== 'same_title') {
                    return false;
                }
                if ($this->categoryFilter === 'same_slug' && $row['category'] !== 'same_slug') {
                    return false;
                }

                // Search query
                if (trim($this->search) !== '') {
                    $term = strtolower(trim($this->search));
                    $inTitle = str_contains(strtolower($row['title']), $term);
                    $inCurrentSlug = str_contains(strtolower($row['current_slug']), $term);
                    $inTargetSlug = str_contains(strtolower($row['target_slug']), $term);
                    $inId = str_contains(strtolower($row['id']), $term);
                    $inAuthor = str_contains(strtolower($row['user_name']), $term);

                    return $inTitle || $inCurrentSlug || $inTargetSlug || $inId || $inAuthor;
                }

                return true;
            })
            ->values()
            ->all();
    }

    public function with(): array
    {
        $analysis = $this->analyzeAllSlugsAndTitles();
        $filtered = $this->filterRecords($analysis['items']);
        $paginator = $this->paginateCollection($filtered);

        $totalQuestions = DB::connection('mongodb')->table('question')->count();

        return [
            'records' => $paginator->items(),
            'paginator' => $paginator,
            'totalDuplicates' => $analysis['counts']['total'],
            'bothCount' => $analysis['counts']['both'],
            'sameTitleCount' => $analysis['counts']['same_title'],
            'sameSlugCount' => $analysis['counts']['same_slug'],
            'totalQuestions' => $totalQuestions,
        ];
    }

    /**
     * Fix a single question's slug.
     */
    public function fixSingle(string $id, ?string $explicitTargetSlug = null): void
    {
        $mongoId = $this->toMongoId($id);
        $raw = DB::connection('mongodb')->table('question')
            ->where(function ($q) use ($id, $mongoId) {
                if ($mongoId instanceof ObjectId) {
                    $q->where('_id', $mongoId);
                } else {
                    $q->where('_id', $id);
                }
                $q->orWhere('_id', (string) $id);
            })
            ->first();

        if (! $raw) {
            $this->statusMessage = "Question record [{$id}] not found.";

            return;
        }

        $title = (string) (is_array($raw) ? ($raw['title'] ?? 'Untitled') : ($raw->title ?? 'Untitled'));
        $currentSlug = (string) (is_array($raw) ? ($raw['slug'] ?? '') : ($raw->slug ?? ''));

        // If target slug is provided, use it, else compute fresh unique slug
        if ($explicitTargetSlug && $explicitTargetSlug !== '(empty)') {
            $targetSlug = $explicitTargetSlug;
        } else {
            $baseSlug = $currentSlug !== '' ? preg_replace('/-\d+$/', '', $currentSlug) : Str::slug($title);
            if (empty($baseSlug)) {
                $baseSlug = 'question-'.substr($id, -6);
            }

            $targetSlug = $baseSlug;
            $count = 1;
            while (
                DB::connection('mongodb')->table('question')
                    ->where('slug', $targetSlug)
                    ->where(function ($q) use ($id, $mongoId) {
                        if ($mongoId instanceof ObjectId) {
                            $q->where('_id', '!=', $mongoId);
                        } else {
                            $q->where('_id', '!=', $id);
                        }
                    })
                    ->exists()
            ) {
                $targetSlug = "{$baseSlug}-{$count}";
                $count++;
            }
        }

        DB::connection('mongodb')->table('question')
            ->where(function ($q) use ($id, $mongoId) {
                if ($mongoId instanceof ObjectId) {
                    $q->where('_id', $mongoId);
                } else {
                    $q->where('_id', $id);
                }
                $q->orWhere('_id', (string) $id);
            })
            ->update([
                'slug' => $targetSlug,
                'updated_at' => now(),
            ]);

        $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
        $this->statusMessage = "Successfully updated Question slug to \"{$targetSlug}\"!";
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            $this->statusMessage = 'No questions selected.';

            return;
        }

        $analysis = $this->analyzeAllSlugsAndTitles();
        $lookup = collect($analysis['items'])->keyBy('id');

        $count = 0;
        foreach ($this->selectedRows as $id) {
            $targetSlug = $lookup[$id]['target_slug'] ?? null;
            $this->fixSingle($id, $targetSlug);
            $count++;
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully resolved slugs for {$count} selected question(s)!";
    }

    public function fixAll(): void
    {
        $analysis = $this->analyzeAllSlugsAndTitles();
        $count = 0;

        foreach ($analysis['items'] as $item) {
            $this->fixSingle($item['id'], $item['target_slug']);
            $count++;
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully resolved and updated all {$count} duplicate slug record(s)!";
    }
}; ?>

<div class="space-y-5">
    <!-- TOP BREADCRUMB & BACK BUTTON -->
    <div class="flex items-center justify-between">
        <a 
            href="{{ route('admin.database-fixing.index') }}" 
            wire:navigate
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
        >
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Back to Database Fixing List</span>
        </a>

        <span class="text-xs font-semibold text-zinc-400">
            Table: <strong class="text-zinc-700 dark:text-zinc-200">Question</strong> &bull; Target Fields: <code class="font-mono text-cyan-600 dark:text-cyan-400">slug, title</code>
        </span>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
            Question &mdash; Duplicate Slug & Title Resolver
        </h2>
        
        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-cyan-600 dark:hover:text-cyan-400 transition cursor-pointer select-none"
            >
                <span x-text="open ? '▴' : '▾'">▾</span>
                <span>Before <strong class="underline">You DO Action</strong> please read this what is do. <span class="text-zinc-400 font-normal">(Click me)</span></span>
            </button>
            
            <div 
                x-show="open" 
                x-cloak 
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-2 p-3.5 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-600 dark:text-zinc-300 space-y-1.5"
            >
                <p class="font-medium">
                    This tool detects all Question records in MongoDB that share identical slugs or identical titles across documents.
                </p>
                <p class="text-zinc-500 dark:text-zinc-400">
                    Fixing conflicts automatically keeps the original record's slug intact and appends a clean numerical suffix (<code class="font-mono text-xs bg-zinc-200 dark:bg-zinc-700 px-1 py-0.5 rounded">slug-1</code>, <code class="font-mono text-xs bg-zinc-200 dark:bg-zinc-700 px-1 py-0.5 rounded">slug-2</code>) to duplicate records, guaranteeing unique SEO-friendly URLs.
                </p>
            </div>
        </div>
    </div>

    <!-- HERO BANNER STATS CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-teal-700 via-cyan-700 to-sky-600 p-6 sm:p-7 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="space-y-2 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                            Duplicate Slug & Title Detector
                        </h3>
                        <p class="text-xs text-cyan-100">
                            Automated collision resolver with <code class="font-mono bg-cyan-800/60 px-1 rounded text-white">slug-1</code> suffixes
                        </p>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-cyan-100/90 leading-relaxed pt-1">
                    Scans <strong class="underline font-bold text-white">{{ $totalQuestions }}</strong> questions for duplicate URL slugs or title collisions and automatically computes unique sequential suffixes.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Categorized into: <span class="font-medium text-white">Same Title & Slug ({{ $bothCount }})</span> &bull; <span class="font-medium text-white">Same Title ({{ $sameTitleCount }})</span> &bull; <span class="font-medium text-white">Same Slug ({{ $sameSlugCount }})</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badges -->
            <div class="grid grid-cols-2 gap-3 shrink-0">
                <div class="px-5 py-3.5 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[130px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $totalDuplicates }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-cyan-200 mt-0.5">
                        Duplicate Records
                    </span>
                </div>

                <div class="px-5 py-3.5 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[130px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $bothCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-cyan-200 mt-0.5">
                        Title & Slug Collisions
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3 CATEGORY TABS & ACTIONS TOOLBAR -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-3.5 bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <!-- Left: Action Buttons -->
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-xs font-bold text-zinc-500 mr-1">Actions:</span>
            
            <!-- Fix All Duplicate Slugs -->
            <button 
                type="button" 
                wire:click="fixAll"
                wire:loading.attr="disabled"
                @disabled($totalDuplicates === 0)
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm {{ $totalDuplicates === 0 ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-rose-600 hover:bg-rose-700 text-white cursor-pointer' }}"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Fix All Duplicate Slugs ({{ $totalDuplicates }})</span>
            </button>

            <!-- Fix Selected -->
            <button 
                type="button" 
                wire:click="fixSelected"
                wire:loading.attr="disabled"
                @disabled(empty($selectedRows))
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 {{ empty($selectedRows) ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-brand text-white hover:bg-brand-600 cursor-pointer shadow-sm' }}"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Fix Selected ({{ count($selectedRows) }})</span>
            </button>

            <!-- Refresh -->
            <button 
                type="button" 
                wire:click="$refresh"
                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition flex items-center gap-1.5 shadow-xs cursor-pointer"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Refresh</span>
            </button>
        </div>

        <!-- Right: 3 Category Filter Tabs & Search -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- 3 Category Tabs -->
            <div class="flex items-center rounded-xl bg-zinc-100 dark:bg-zinc-800 p-0.5 border border-zinc-200 dark:border-zinc-700">
                <!-- All Duplicates -->
                <button 
                    type="button" 
                    wire:click="$set('categoryFilter', 'all')"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $categoryFilter === 'all' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-xs' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
                >
                    All Duplicates ({{ $totalDuplicates }})
                </button>

                <!-- Category 1: Same Title & Same Slug -->
                <button 
                    type="button" 
                    wire:click="$set('categoryFilter', 'both')"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $categoryFilter === 'both' ? 'bg-white dark:bg-zinc-900 text-rose-600 dark:text-rose-400 shadow-xs' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
                >
                    Same Title & Slug ({{ $bothCount }})
                </button>

                <!-- Category 2: Same Title -->
                <button 
                    type="button" 
                    wire:click="$set('categoryFilter', 'same_title')"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $categoryFilter === 'same_title' ? 'bg-white dark:bg-zinc-900 text-purple-600 dark:text-purple-400 shadow-xs' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
                >
                    Same Title ({{ $sameTitleCount }})
                </button>

                <!-- Category 3: Same Slug -->
                <button 
                    type="button" 
                    wire:click="$set('categoryFilter', 'same_slug')"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $categoryFilter === 'same_slug' ? 'bg-white dark:bg-zinc-900 text-cyan-600 dark:text-cyan-400 shadow-xs' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
                >
                    Same Slug ({{ $sameSlugCount }})
                </button>
            </div>

            <!-- Search -->
            <div class="relative min-w-[180px] sm:min-w-[220px]">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search titles, slugs..." 
                    class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                />
                <svg class="size-4 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <!-- Per Page -->
            <div class="flex items-center gap-1.5 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                <span>Per Page:</span>
                <select 
                    wire:model.live="perPage" 
                    class="px-2 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-bold text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                >
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION ALERT -->
    @if($statusMessage !== 'Ready')
        <div 
            x-data="{ show: true }" 
            x-show="show" 
            x-init="setTimeout(() => show = false, 6000)"
            class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center justify-between shadow-xs"
        >
            <div class="flex items-center gap-2">
                <svg class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ $statusMessage }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-700 dark:text-emerald-300 hover:opacity-75 cursor-pointer">
                &times;
            </button>
        </div>
    @endif

    <!-- DATA TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-900/80 text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-4 py-3.5 w-12 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll"
                                @checked($selectAll)
                                class="rounded border-zinc-300 dark:border-zinc-700 text-cyan-600 focus:ring-cyan-500 size-4 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3.5 w-14">No</th>
                        <th class="px-4 py-3.5 min-w-[240px]">Question Title & ID</th>
                        <th class="px-4 py-3.5 w-40">Author</th>
                        <th class="px-4 py-3.5 min-w-[200px]">Current Slug</th>
                        <th class="px-4 py-3.5 min-w-[200px]">Target Unique Slug</th>
                        <th class="px-4 py-3.5 w-44">Collision Category</th>
                        <th class="px-4 py-3.5 text-center w-28">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($records as $index => $row)
                        <tr wire:key="q-slug-row-{{ $row['id'] }}" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition bg-rose-50/20 dark:bg-rose-950/10">
                            <!-- Checkbox -->
                            <td class="px-4 py-3 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:model="selectedRows" 
                                    value="{{ $row['id'] }}"
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-cyan-600 focus:ring-cyan-500 size-4 cursor-pointer"
                                />
                            </td>

                            <!-- No -->
                            <td class="px-4 py-3 font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1 }}
                            </td>

                            <!-- Title & Info -->
                            <td class="px-4 py-3">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-zinc-900 dark:text-white line-clamp-1">
                                        {{ $row['title'] }}
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-zinc-400">
                                        <span class="font-mono text-zinc-500 dark:text-zinc-400 select-all">ID: {{ $row['id'] }}</span>
                                        <span>&bull;</span>
                                        <span class="font-semibold text-zinc-500">Record #{{ $row['index_in_group'] }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Author -->
                            <td class="px-4 py-3">
                                <div class="font-semibold text-zinc-800 dark:text-zinc-200 truncate max-w-[150px]">
                                    {{ $row['user_name'] }}
                                </div>
                                <div class="text-[11px] text-zinc-400 truncate max-w-[150px]">
                                    {{ $row['user_email'] }}
                                </div>
                            </td>

                            <!-- Current Slug -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 max-w-[220px] truncate" title="{{ $row['current_slug'] }}">
                                    {{ $row['current_slug'] }}
                                </span>
                            </td>

                            <!-- Target Unique Slug -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 max-w-[220px] truncate" title="{{ $row['target_slug'] }}">
                                    {{ $row['target_slug'] }}
                                </span>
                            </td>

                            <!-- Category Badge -->
                            <td class="px-4 py-3">
                                @if($row['category'] === 'both')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/70">
                                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                                        Same Title & Slug
                                    </span>
                                @elseif($row['category'] === 'same_title')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/70">
                                        <span class="size-1.5 rounded-full bg-purple-500"></span>
                                        Same Title
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-cyan-50 dark:bg-cyan-950/50 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/70">
                                        <span class="size-1.5 rounded-full bg-cyan-500"></span>
                                        Same Slug
                                    </span>
                                @endif
                            </td>

                            <!-- Action: Fix Button -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['id'] }}', '{{ $row['target_slug'] }}')"
                                    wire:loading.attr="disabled"
                                    class="px-3.5 py-1 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-700 text-white transition shadow-xs cursor-pointer"
                                >
                                    Fix Slug
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="max-w-md mx-auto space-y-3">
                                    <div class="size-12 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                        All Question Slugs Are Unique & Clean!
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        There are no duplicate slugs or duplicate title collisions remaining in the MongoDB database.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION FOOTER -->
        @if($paginator->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
