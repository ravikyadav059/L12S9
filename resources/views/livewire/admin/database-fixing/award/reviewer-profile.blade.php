<?php

use App\Models\Award;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Award — Reviewer Profile & User Sync - Admin')] class extends Component {
    use WithPagination;

    public string $tab = 'all'; // 'all', 'missing_in_profile', 'no_user_id', 'ghost_in_profile', 'missing_status'
    public string $search = '';
    public string $statusMessage = 'Ready';
    public array $selectedRows = [];
    public bool $selectAll = false;
    public int $perPage = 25;

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
     * Scan and build the full list of data integrity issues for Awards.
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_award_issues', 60, function () {
            // 1. Fetch all awards from MongoDB
            $allAwards = DB::connection('mongodb')->table('award')
                ->get(['_id', 'user_id', 'status', 'award_name', 'award_by', 'award_year', 'description']);

            $awardIdMap = [];
            $awardUserMap = [];
            $noUserAwards = [];
            $noStatusAwards = [];

            foreach ($allAwards as $a) {
                $rawId = $a->_id ?? $a->id ?? null;
                $aid = (string) $rawId;
                $awardIdMap[$aid] = $a;

                $hasUser = ! empty($a->user_id);
                if ($hasUser) {
                    $awardUserMap[(string) $a->user_id][] = $aid;
                } else {
                    $noUserAwards[] = $a;
                }

                if ($a->status === null || $a->status === '') {
                    $noStatusAwards[] = $a;
                }
            }

            // 2. Fetch ReviewerProfiles with user_id and awards
            $profiles = DB::connection('mongodb')->table('reviewer_profile')
                ->whereNotNull('user_id')
                ->get(['_id', 'user_id', 'award']);

            // Map each award ID found in profiles to its owner user_id and profile_id
            $profileAwardMap = [];
            foreach ($profiles as $p) {
                $uid = (string) $p->user_id;
                $rawId = $p->_id ?? $p->id ?? null;
                $rpid = (string) $rawId;
                $rpAwards = is_array($p->award ?? null) ? array_map('strval', $p->award) : [];

                foreach ($rpAwards as $aid) {
                    if (! isset($profileAwardMap[$aid])) {
                        $profileAwardMap[$aid] = [
                            'user_id' => $uid,
                            'rp_id' => $rpid,
                        ];
                    }
                }
            }

            $issueList = [];

            // 3. Scan ReviewerProfiles: Ghost IDs and Missing in Profile
            foreach ($profiles as $p) {
                $uid = (string) $p->user_id;
                $rawId = $p->_id ?? $p->id ?? null;
                $rpid = (string) $rawId;
                $rpAwards = is_array($p->award ?? null) ? array_map('strval', $p->award) : [];

                // A) Ghost IDs in ReviewerProfile
                $ghosts = array_diff($rpAwards, array_keys($awardIdMap));
                foreach ($ghosts as $gid) {
                    $issueList[] = [
                        'key' => "ghost_{$rpid}_{$gid}",
                        'type' => 'ghost_in_profile',
                        'type_label' => 'Ghost ID in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'award_id' => $gid,
                        'award_name' => 'N/A (Deleted)',
                        'award_by' => 'N/A (Deleted)',
                        'award_year' => '',
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => null,
                        'suggested_user_id' => $uid,
                        'summary' => "Reviewer profile references award ID [{$gid}] that does not exist in the database.",
                        'fix_label' => 'Remove Ghost',
                    ];
                }

                // B) Awards belonging to this user but missing from ReviewerProfile
                $userAwards = $awardUserMap[$uid] ?? [];
                $missing = array_diff($userAwards, $rpAwards);
                foreach ($missing as $mid) {
                    $awardDoc = $awardIdMap[$mid] ?? null;
                    $issueList[] = [
                        'key' => "missing_{$rpid}_{$mid}",
                        'type' => 'missing_in_profile',
                        'type_label' => 'Missing in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'award_id' => $mid,
                        'award_name' => (string) ($awardDoc->award_name ?? ''),
                        'award_by' => (string) ($awardDoc->award_by ?? ''),
                        'award_year' => (string) ($awardDoc->award_year ?? ''),
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => $awardDoc->status ?? null,
                        'suggested_user_id' => $uid,
                        'summary' => "Award exists for this user, but its ID is missing from their reviewer profile.",
                        'fix_label' => 'Add to Profile',
                    ];
                }
            }

            // 4. Awards with missing user_id
            foreach ($noUserAwards as $a) {
                $aid = (string) ($a->_id ?? $a->id ?? '');
                $detected = $profileAwardMap[$aid] ?? null;
                $detectedUid = $detected['user_id'] ?? null;
                $detectedRpid = $detected['rp_id'] ?? '';

                $statusVal = $a->status ?? null;
                // If this is a true orphan (not present in any reviewer profile)
                // and its status is ALREADY set to 0 (Inactive orphan),
                // it is fully processed and should not be flagged as an active issue.
                if (empty($detectedUid) && ($statusVal === 0 || $statusVal === '0')) {
                    continue;
                }

                $summary = $detectedUid
                    ? "Award has no user ID. Detected owner from Reviewer Profile: [{$detectedUid}]. Clicking fix will attach user ID and set status = 1."
                    : 'Award has no user ID and is not present in any reviewer profile. Clicking fix will set status = 0 (Inactive orphan).';

                $fixLabel = $detectedUid ? 'Assign User (Status 1)' : 'Mark Orphan (Status 0)';

                $issueList[] = [
                    'key' => "nouser_{$aid}",
                    'type' => 'no_user_id',
                    'type_label' => 'No User ID',
                    'rp_id' => $detectedRpid,
                    'user_id' => '',
                    'award_id' => $aid,
                    'award_name' => (string) ($a->award_name ?? ''),
                    'award_by' => (string) ($a->award_by ?? ''),
                    'award_year' => (string) ($a->award_year ?? ''),
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => $a->status ?? null,
                    'suggested_user_id' => $detectedUid,
                    'summary' => $summary,
                    'fix_label' => $fixLabel,
                ];
            }

            // 5. Awards with missing status
            foreach ($noStatusAwards as $a) {
                $aid = (string) ($a->_id ?? $a->id ?? '');
                $uid = (string) ($a->user_id ?? '');
                $detected = $profileAwardMap[$aid] ?? null;
                $effectiveUid = $uid ?: ($detected['user_id'] ?? null);

                $summary = ! empty($effectiveUid)
                    ? 'Award document has no status field. Has user owner; will be set to status: 1 (Active).'
                    : 'Award document has no status field and no user owner; will be set to status: 0 (Inactive).';

                $fixLabel = ! empty($effectiveUid) ? 'Set Status 1' : 'Set Status 0';

                $issueList[] = [
                    'key' => "nostatus_{$aid}",
                    'type' => 'missing_status',
                    'type_label' => 'Missing Status',
                    'rp_id' => $detected['rp_id'] ?? '',
                    'user_id' => $uid,
                    'award_id' => $aid,
                    'award_name' => (string) ($a->award_name ?? ''),
                    'award_by' => (string) ($a->award_by ?? ''),
                    'award_year' => (string) ($a->award_year ?? ''),
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => null,
                    'suggested_user_id' => $effectiveUid,
                    'summary' => $summary,
                    'fix_label' => $fixLabel,
                ];
            }

            return $issueList;
        });
    }

    /**
     * Decorate ONLY the current page's sliced items with User records.
     * This avoids fetching and hydrating thousands of User models on every request.
     */
    protected function decoratePageItems(array $pageItems): array
    {
        if (empty($pageItems)) {
            return [];
        }

        $userIds = [];
        foreach ($pageItems as $i) {
            $uid = ! empty($i['user_id']) ? $i['user_id'] : ($i['suggested_user_id'] ?? '');
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
            $effectiveUid = ! empty($item['user_id']) ? $item['user_id'] : ($item['suggested_user_id'] ?? '');
            $u = $usersMap->get($effectiveUid);

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

            // If search is at least 2 characters, check if it matches any user names or emails
            $matchedUserIds = [];
            if (strlen($term) >= 2) {
                $matchedUserIds = User::where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('unique_id', 'like', "%{$term}%")
                    ->limit(100)
                    ->pluck('_id')
                    ->map(fn ($id) => (string) $id)
                    ->flip()
                    ->all();
            }

            $allIssues = array_values(array_filter($allIssues, function ($item) use ($lowerTerm, $matchedUserIds) {
                $uid = $item['user_id'] ?: ($item['suggested_user_id'] ?? '');

                return isset($matchedUserIds[$uid])
                    || str_contains(strtolower($item['award_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['rp_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['suggested_user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['award_name'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['award_by'] ?? ''), $lowerTerm);
            }));
        }

        return $allIssues;
    }

    public function with(): array
    {
        $allIssues = $this->scanIssues();

        // Count totals per category directly in-memory (< 0.2ms)
        $counts = [
            'all' => count($allIssues),
            'missing_in_profile' => 0,
            'no_user_id' => 0,
            'ghost_in_profile' => 0,
            'missing_status' => 0,
        ];
        foreach ($allIssues as $i) {
            if (isset($counts[$i['type']])) {
                $counts[$i['type']]++;
            }
        }

        // Filter for active Tab & Search
        $filtered = $this->getFilteredIssues();
        $totalFiltered = count($filtered);

        // Paginate in-memory collection
        $currentPage = $this->getPage();
        $slicedRaw = array_slice($filtered, ($currentPage - 1) * $this->perPage, $this->perPage);

        // Decorate ONLY the 25 items on the current page (< 5ms)
        $slicedDecorated = $this->decoratePageItems($slicedRaw);

        $paginator = new LengthAwarePaginator(
            $slicedDecorated,
            $totalFiltered,
            $this->perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page']
        );

        return [
            'issues' => $slicedDecorated,
            'paginator' => $paginator,
            'counts' => $counts,
            'totalIssues' => $counts['all'],
        ];
    }

    /**
     * Fix a single issue by its unique key.
     */
    public function fixSingle(string $key): void
    {
        $allIssues = $this->scanIssues();
        $issue = collect($allIssues)->firstWhere('key', $key);

        if (! $issue) {
            $this->statusMessage = "Issue [{$key}] was not found or already resolved.";

            return;
        }

        $this->resolveIssue($issue);
        Cache::forget('db_fixing_award_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully fixed issue: {$issue['type_label']} (Award #{$issue['award_id']})!";
    }

    /**
     * Fix all selected rows.
     */
    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            $this->statusMessage = 'No items were selected.';

            return;
        }

        $allIssues = $this->scanIssues();
        $issuesMap = collect($allIssues)->keyBy('key');

        $count = 0;
        foreach ($this->selectedRows as $key) {
            if ($issuesMap->has($key)) {
                $this->resolveIssue($issuesMap->get($key));
                $count++;
            }
        }

        Cache::forget('db_fixing_award_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected issue(s)!";
    }

    /**
     * Fix all issues across the entire database in one click.
     */
    public function fixAll(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $allIssues = $this->scanIssues();
        $total = count($allIssues);

        if ($total === 0) {
            $this->statusMessage = 'No issues found! Database is already fully clean and in sync.';

            return;
        }

        foreach ($allIssues as $issue) {
            $this->resolveIssue($issue);
        }

        Cache::forget('db_fixing_award_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully resolved all {$total} issue(s) across awards and reviewer profiles.";
    }

    /**
     * Internal resolver for an issue item.
     */
    protected function resolveIssue(array $issue): void
    {
        $type = $issue['type'];
        $awardId = (string) $issue['award_id'];
        $mongoAwardId = $this->toMongoId($awardId);
        $rpId = (string) ($issue['rp_id'] ?? '');
        $userId = (string) (! empty($issue['user_id']) ? $issue['user_id'] : ($issue['suggested_user_id'] ?? ''));

        switch ($type) {
            case 'missing_in_profile':
                // 1. Add award ID to ReviewerProfile->award array
                if ($userId !== '') {
                    $profile = ReviewerProfile::where(function ($q) use ($rpId, $userId) {
                        if ($rpId !== '') {
                            $q->where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId);
                        } else {
                            $q->where('user_id', $this->toMongoId($userId))->orWhere('user_id', $userId);
                        }
                    })->first();

                    if ($profile) {
                        $currentAward = is_array($profile->award ?? null) ? array_map('strval', $profile->award) : [];
                        if (! in_array($awardId, $currentAward, true)) {
                            $currentAward[] = $awardId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'award' => array_values(array_unique($currentAward)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    // 2. Ensure award has status = 1 (Active) and user_id set
                    $updatePayload = ['user_id' => $userId];
                    $awardDoc = DB::connection('mongodb')->table('award')
                        ->where(function ($q) use ($awardId, $mongoAwardId) {
                            $q->where('_id', $mongoAwardId)->orWhere('_id', $awardId);
                        })->first();

                    if ($awardDoc && ($awardDoc->status === null || $awardDoc->status === '' || (int) $awardDoc->status === 0)) {
                        $updatePayload['status'] = 1;
                    }

                    DB::connection('mongodb')->table('award')
                        ->where(function ($q) use ($awardId, $mongoAwardId) {
                            $q->where('_id', $mongoAwardId)->orWhere('_id', $awardId);
                        })->update($updatePayload);

                    Award::forgetUserCache($userId);
                }
                break;

            case 'no_user_id':
                // Determine owner user_id: if not present in issue, look up reviewer_profile that contains this award ID
                if ($userId === '' && $awardId !== '') {
                    $ownerProfile = DB::connection('mongodb')->table('reviewer_profile')
                        ->where('award', $awardId)
                        ->first();
                    if ($ownerProfile && ! empty($ownerProfile->user_id)) {
                        $userId = (string) $ownerProfile->user_id;
                    }
                }

                if ($userId !== '') {
                    // Owner exists: attach user_id and set status = 1 (Active)
                    $updatePayload = [
                        'user_id' => $userId,
                        'status' => 1,
                    ];

                    DB::connection('mongodb')->table('award')
                        ->where(function ($q) use ($awardId, $mongoAwardId) {
                            $q->where('_id', $mongoAwardId)->orWhere('_id', $awardId);
                        })->update($updatePayload);

                    // Ensure the owner's reviewer profile contains this award ID
                    $profile = ReviewerProfile::where('user_id', $userId)
                        ->orWhere('user_id', $this->toMongoId($userId))
                        ->first();

                    if ($profile) {
                        $currentAward = is_array($profile->award ?? null) ? array_map('strval', $profile->award) : [];
                        if (! in_array($awardId, $currentAward, true)) {
                            $currentAward[] = $awardId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'award' => array_values(array_unique($currentAward)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    Award::forgetUserCache($userId);
                } else {
                    // True orphan: not in any profile and no owner: set status = 0 (Inactive)
                    DB::connection('mongodb')->table('award')
                        ->where(function ($q) use ($awardId, $mongoAwardId) {
                            $q->where('_id', $mongoAwardId)->orWhere('_id', $awardId);
                        })->update(['status' => 0]);
                }
                break;

            case 'ghost_in_profile':
                // Remove ghost ID from ReviewerProfile->award array
                if ($rpId !== '') {
                    $profile = ReviewerProfile::where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId)->first();
                    if ($profile) {
                        $currentAward = is_array($profile->award ?? null) ? array_map('strval', $profile->award) : [];
                        $cleaned = array_values(array_diff($currentAward, [$awardId]));
                        DB::connection('mongodb')->table('reviewer_profile')
                            ->where('_id', $profile->_id)
                            ->update([
                                'award' => $cleaned,
                                'updated_at' => now(),
                            ]);
                    }
                }
                if ($userId !== '') {
                    Award::forgetUserCache($userId);
                }
                break;

            case 'missing_status':
                // If has user owner (attached or from profile): status = 1. If orphan: status = 0.
                if ($userId === '' && $awardId !== '') {
                    $ownerProfile = DB::connection('mongodb')->table('reviewer_profile')
                        ->where('award', $awardId)
                        ->first();
                    if ($ownerProfile && ! empty($ownerProfile->user_id)) {
                        $userId = (string) $ownerProfile->user_id;
                    }
                }

                if ($userId !== '') {
                    DB::connection('mongodb')->table('award')
                        ->where(function ($q) use ($awardId, $mongoAwardId) {
                            $q->where('_id', $mongoAwardId)->orWhere('_id', $awardId);
                        })->update([
                            'status' => 1,
                            'user_id' => $userId,
                        ]);
                    Award::forgetUserCache($userId);
                } else {
                    DB::connection('mongodb')->table('award')
                        ->where(function ($q) use ($awardId, $mongoAwardId) {
                            $q->where('_id', $mongoAwardId)->orWhere('_id', $awardId);
                        })->update(['status' => 0]);
                }
                break;
        }
    }
}; ?>

<div class="space-y-5">
    <!-- TOP BREADCRUMB & BACK BUTTON -->
    <div class="flex items-center justify-between flex-wrap gap-2">
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

        <!-- TOOL SWITCHER PILLS -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700/60">
                Award Sync
            </span>
            <a 
                href="{{ route('admin.database-fixing.experience.reviewer-profile') }}" 
                wire:navigate
                class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
            >
                Experience Sync &rarr;
            </a>
            <a 
                href="{{ route('admin.database-fixing.reviewer-profile.index') }}" 
                wire:navigate
                class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
            >
                All 12 Array Fields Master
            </a>
        </div>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight flex items-center gap-2">
            <span>Award &mdash; Reviewer Profile & User ID Sync</span>
            <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                4-in-1 Data Sync & Integrity
            </span>
        </h2>
        
        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-amber-600 transition cursor-pointer select-none"
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
                class="mt-2 p-4 bg-zinc-100/90 dark:bg-zinc-800/90 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-600 dark:text-zinc-300 space-y-2.5"
            >
                <div class="font-bold text-sm text-zinc-900 dark:text-white">
                    Transformation Summary (In Simple Words)
                </div>
                <p class="text-xs leading-relaxed">
                    This tool connects your <strong>Award</strong> records with the user's <strong>Reviewer Profile</strong> and fixes 4 common problems automatically:
                </p>
                <ul class="list-disc list-inside space-y-1 text-xs text-zinc-700 dark:text-zinc-200">
                    <li>
                        <strong>No User ID:</strong> When an award has no user ID. If the award ID is present in a reviewer profile, it detects that owner, links their user ID, and sets <code class="font-mono text-emerald-600 font-bold">status = 1</code> (Active). If not in any profile, it marks it as an orphan with <code class="font-mono text-rose-600 font-bold">status = 0</code>.
                    </li>
                    <li>
                        <strong>Missing in Profile:</strong> When a user owns an award, but its ID was never added to their reviewer profile array. Clicking fix adds this award ID directly to their reviewer profile and ensures <code class="font-mono text-emerald-600 font-bold">status = 1</code>.
                    </li>
                    <li>
                        <strong>Deleted / Ghost IDs:</strong> When a reviewer profile has an award ID that was deleted or doesn't exist anymore in the award table. Clicking fix cleanly removes that ghost ID from their profile.
                    </li>
                    <li>
                        <strong>Missing Status:</strong> When an award document has no status field. If it has a user, it sets <code class="font-mono text-emerald-600 font-bold">status = 1</code> (Active); if orphan, it sets <code class="font-mono text-rose-600 font-bold">status = 0</code> (Inactive).
                    </li>
                </ul>
                <div class="pt-1 text-[11px] text-zinc-500 border-t border-zinc-200 dark:border-zinc-700/60">
                    Total Issues Detected in Database: <strong class="text-amber-600 font-bold">{{ $totalIssues }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- AMBER HERO BANNER CARD (High-Contrast Rich Bronze Theme with Guaranteed Contrast) -->
    <div 
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-amber-950 via-amber-900 to-amber-800 p-6 sm:p-7 text-white shadow-xl border border-amber-800/60"
        style="background: linear-gradient(135deg, #451a03 0%, #78350f 50%, #92400e 100%); color: #ffffff;"
    >
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="space-y-2.5 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div 
                        class="size-10 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center shrink-0 shadow-inner"
                        style="background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.4); color: #fcd34d;"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold tracking-tight text-white drop-shadow-xs" style="color: #ffffff;">
                        Reviewer Profile & Award Synchronizer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-amber-100/90 leading-relaxed" style="color: #fef3c7;">
                    Fixes missing links between Award records and Reviewer Profiles. It assigns owner user IDs from profile award arrays, adds unlinked awards to user profiles, cleans ghost IDs, and standardizes status (<code class="bg-black/40 px-1.5 py-0.5 rounded font-mono text-amber-300 font-bold border border-amber-500/30" style="background: rgba(0,0,0,0.5); color: #fcd34d;">1</code> for Active with User, <code class="bg-black/40 px-1.5 py-0.5 rounded font-mono text-amber-300 font-bold border border-amber-500/30" style="background: rgba(0,0,0,0.5); color: #fcd34d;">0</code> for Inactive orphan).
                </p>
            </div>

            <!-- Right Counters Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
                <div class="px-3.5 py-2.5 rounded-xl bg-black/35 border border-amber-500/30 backdrop-blur-xs text-center shadow-inner" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(245, 158, 11, 0.35);">
                    <span class="block text-2xl font-black text-amber-300 tracking-tight" style="color: #fcd34d;">{{ $counts['no_user_id'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-200/80" style="color: #fef3c7;">No User ID</span>
                </div>
                <div class="px-3.5 py-2.5 rounded-xl bg-black/35 border border-amber-500/30 backdrop-blur-xs text-center shadow-inner" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(245, 158, 11, 0.35);">
                    <span class="block text-2xl font-black text-amber-300 tracking-tight" style="color: #fcd34d;">{{ $counts['missing_in_profile'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-200/80" style="color: #fef3c7;">Missing in Profile</span>
                </div>
                <div class="px-3.5 py-2.5 rounded-xl bg-black/35 border border-amber-500/30 backdrop-blur-xs text-center shadow-inner" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(245, 158, 11, 0.35);">
                    <span class="block text-2xl font-black text-amber-300 tracking-tight" style="color: #fcd34d;">{{ $counts['ghost_in_profile'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-200/80" style="color: #fef3c7;">Ghost IDs</span>
                </div>
                <div class="px-3.5 py-2.5 rounded-xl bg-black/35 border border-amber-500/30 backdrop-blur-xs text-center shadow-inner" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(245, 158, 11, 0.35);">
                    <span class="block text-2xl font-black text-amber-300 tracking-tight" style="color: #fcd34d;">{{ $counts['missing_status'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-200/80" style="color: #fef3c7;">No Status</span>
                </div>
            </div>
        </div>
    </div>

    <!-- FLASH STATUS MESSAGE -->
    @if($statusMessage && $statusMessage !== 'Ready')
        <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="size-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ $statusMessage }}</span>
            </div>
            <button type="button" wire:click="$set('statusMessage', 'Ready')" class="text-emerald-600 hover:text-emerald-800 cursor-pointer">
                &times;
            </button>
        </div>
    @endif

    <!-- FILTER TABS & SEARCH & ACTIONS BAR -->
    <div class="space-y-3">
        <!-- Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs font-semibold">
            <button 
                type="button" 
                wire:click="setTab('all')"
                class="px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 {{ $tab === 'all' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                <span>All Issues</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'all' ? 'bg-white/20 text-white dark:bg-zinc-900/20 dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">
                    {{ $counts['all'] }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="setTab('no_user_id')"
                class="px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 {{ $tab === 'no_user_id' ? 'bg-amber-600 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                <span>No User ID</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'no_user_id' ? 'bg-white/25 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">
                    {{ $counts['no_user_id'] }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="setTab('missing_in_profile')"
                class="px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 {{ $tab === 'missing_in_profile' ? 'bg-rose-600 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                <span>Missing in Profile</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'missing_in_profile' ? 'bg-white/25 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">
                    {{ $counts['missing_in_profile'] }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="setTab('ghost_in_profile')"
                class="px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 {{ $tab === 'ghost_in_profile' ? 'bg-purple-600 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                <span>Ghost IDs</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'ghost_in_profile' ? 'bg-white/25 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">
                    {{ $counts['ghost_in_profile'] }}
                </span>
            </button>

            <button 
                type="button" 
                wire:click="setTab('missing_status')"
                class="px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 {{ $tab === 'missing_status' ? 'bg-sky-600 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >
                <span>Missing Status</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'missing_status' ? 'bg-white/25 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">
                    {{ $counts['missing_status'] }}
                </span>
            </button>
        </div>

        <!-- Search & Action Buttons Bar -->
        <div class="flex items-center justify-between flex-wrap gap-3 bg-white dark:bg-zinc-900 p-3 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-2xs">
            <!-- Search Box -->
            <div class="relative flex-1 min-w-[240px]">
                <svg class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by User Name, Email, Award Name, or ID..." 
                    class="w-full pl-9 pr-4 py-1.5 rounded-xl text-xs bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-amber-500"
                />
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                @if(count($selectedRows) > 0)
                    <button 
                        type="button" 
                        wire:click="fixSelected"
                        wire:loading.attr="disabled"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white shadow-2xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>Fix Selected ({{ count($selectedRows) }})</span>
                    </button>
                @endif

                @if($totalIssues > 0)
                    <button 
                        type="button" 
                        wire:click="fixAll"
                        wire:confirm="Are you sure you want to automatically fix and synchronize all {{ $totalIssues }} issue(s) across awards and reviewer profiles?"
                        wire:loading.attr="disabled"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-zinc-100 shadow-2xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Fix All Issues ({{ $totalIssues }})</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- MAIN DATA TABLE -->
    <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 uppercase tracking-wider text-[10px] font-bold border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-4 py-3 w-8 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll" 
                                @checked($selectAll)
                                class="rounded border-zinc-300 dark:border-zinc-700 text-amber-600 focus:ring-amber-500 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3">User Account</th>
                        <th class="px-4 py-3">Award Details</th>
                        <th class="px-4 py-3">MongoDB IDs</th>
                        <th class="px-4 py-3">Problem Type</th>
                        <th class="px-4 py-3">What Will Be Fixed</th>
                        <th class="px-4 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($issues as $row)
                        <tr wire:key="row-{{ $row['key'] }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition">
                            <!-- Checkbox -->
                            <td class="px-4 py-3 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedRows" 
                                    value="{{ $row['key'] }}"
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-amber-600 focus:ring-amber-500 cursor-pointer"
                                />
                            </td>

                            <!-- User Account -->
                            <td class="px-4 py-3 space-y-0.5">
                                @if(!empty($row['user_name']))
                                    <div class="font-bold text-zinc-900 dark:text-white flex items-center gap-1.5">
                                        <span>{{ $row['user_name'] }}</span>
                                        @if(!empty($row['user_unique_id']))
                                            <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                                                #{{ $row['user_unique_id'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                        {{ $row['user_email'] }}
                                    </div>
                                @elseif(!empty($row['suggested_user_id']))
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            Detected: #{{ substr($row['suggested_user_id'], 0, 8) }}
                                        </span>
                                        <div class="font-mono text-[10px] text-zinc-500">
                                            {{ $row['suggested_user_id'] }}
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                        No User Attached
                                    </span>
                                @endif
                            </td>

                            <!-- Award Details -->
                            <td class="px-4 py-3 space-y-0.5">
                                @if($row['type'] === 'ghost_in_profile')
                                    <span class="text-zinc-400 italic">Deleted from database</span>
                                @else
                                    <div class="font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ !empty($row['award_name']) ? $row['award_name'] : 'Award N/A' }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                        {{ !empty($row['award_by']) ? $row['award_by'] : 'Award By N/A' }}
                                    </div>
                                    @if(!empty($row['award_year']))
                                        <div class="text-[10px] text-zinc-400">
                                            Year: <span class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $row['award_year'] }}</span>
                                        </div>
                                    @endif
                                @endif
                            </td>

                            <!-- IDs -->
                            <td class="px-4 py-3 font-mono text-[11px] space-y-0.5">
                                <div>
                                    <span class="text-[10px] text-zinc-400 font-sans">Award:</span>
                                    <span class="text-zinc-700 dark:text-zinc-300 select-all">{{ $row['award_id'] ?: 'N/A' }}</span>
                                </div>
                                @if(!empty($row['rp_id']))
                                    <div>
                                        <span class="text-[10px] text-zinc-400 font-sans">Profile:</span>
                                        <span class="text-zinc-500 select-all">{{ $row['rp_id'] }}</span>
                                    </div>
                                @endif
                            </td>

                            <!-- Problem Type Badge -->
                            <td class="px-4 py-3">
                                @if($row['type'] === 'missing_in_profile')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        Missing in Profile
                                    </span>
                                @elseif($row['type'] === 'no_user_id')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        No User ID
                                    </span>
                                @elseif($row['type'] === 'ghost_in_profile')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                        Ghost ID in Profile
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                        Missing Status
                                    </span>
                                @endif
                            </td>

                            <!-- What Will Be Fixed -->
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300 text-xs leading-relaxed max-w-xs">
                                {{ $row['summary'] }}
                            </td>

                            <!-- Action Button -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['key'] }}')"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 text-amber-700 border border-amber-300 dark:border-amber-700 hover:bg-amber-600 hover:text-white hover:border-amber-600 transition shadow-2xs cursor-pointer"
                                >
                                    {{ $row['fix_label'] }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="max-w-md mx-auto space-y-3">
                                    <div class="size-12 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                        All Awards & Reviewer Profiles Are Synchronized!
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        There are 0 unlinked or broken records in this view. Every award is correctly linked to its owner's reviewer profile, user IDs are attached, and statuses are valid.
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
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>

