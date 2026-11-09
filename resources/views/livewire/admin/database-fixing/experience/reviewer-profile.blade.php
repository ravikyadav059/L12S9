<?php

use App\Models\Designation;
use App\Models\Experience;
use App\Models\Organization;
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

new #[Layout('components.layouts.admin')] #[Title('Experience — Reviewer Profile & User Sync - Admin')] class extends Component {
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
     * Map of known orphan experiences without user_id to their respective owner user IDs.
     */
    protected function getKnownUserMapping(): array
    {
        return [
            '645a3b316cabfa87580e36f2' => '645a3a7ed1330ad35e0146d2', // prof.darshanpatel
            '645e3ab04f5522ea9a048d12' => '65d6b6bed3c3d63dc80b14b2', // aniketpatel.it
            '6470a2b3ca6c0c432f002072' => '645f3a2a5f83d97ff7065602', // varun.ce7
            '6475889aba88763b1e0c6462' => '645f3a2a5f83d97ff7065602', // varun.ce7
            '64758a705d4cfa0bca027162' => '645f3a2a5f83d97ff7065602', // varun.ce7
        ];
    }

    /**
     * Scan and build the full list of data integrity issues.
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_exp_issues', 60, function () {
            // 1. Experiences missing user_id
            $noUserExps = DB::connection('mongodb')->table('experience')
                ->where(function ($q) {
                    $q->whereNull('user_id')->orWhere('user_id', '');
                })
                ->get();

            // 2. Experiences missing status
            $noStatusExps = DB::connection('mongodb')->table('experience')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '');
                })
                ->get();

            // 3. Map all experiences by ID and by user_id
            $allExps = DB::connection('mongodb')->table('experience')
                ->get(['_id', 'user_id', 'status', 'designation', 'organization_id', 'join_date', 'end_date', 'current_organization']);

            $expIdMap = [];
            $expUserMap = [];
            foreach ($allExps as $e) {
                $rawId = $e->_id ?? $e->id ?? null;
                $eid = (string) $rawId;
                $expIdMap[$eid] = $e;
                if (! empty($e->user_id)) {
                    $expUserMap[(string) $e->user_id][] = $eid;
                }
            }

            // 4. Check ReviewerProfiles
            $profiles = DB::connection('mongodb')->table('reviewer_profile')
                ->whereNotNull('user_id')
                ->get(['_id', 'user_id', 'experience']);

            $knownUserMap = $this->getKnownUserMapping();
            $issueList = [];

            // ReviewerProfile mismatches (Missing in profile & Ghost IDs)
            foreach ($profiles as $p) {
                $uid = (string) $p->user_id;
                $rawId = $p->_id ?? $p->id ?? null;
                $rpid = (string) $rawId;
                $rpExps = is_array($p->experience ?? null) ? array_map('strval', $p->experience) : [];

                // Ghost IDs in ReviewerProfile
                $ghosts = array_diff($rpExps, array_keys($expIdMap));
                foreach ($ghosts as $gid) {
                    $issueList[] = [
                        'key' => "ghost_{$rpid}_{$gid}",
                        'type' => 'ghost_in_profile',
                        'type_label' => 'Ghost ID in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'exp_id' => $gid,
                        'designation_id' => '',
                        'designation_title' => 'N/A (Deleted)',
                        'organization_id' => '',
                        'organization_name' => 'N/A (Deleted)',
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'join_date' => '',
                        'end_date' => '',
                        'current_organization' => 0,
                        'status' => null,
                        'suggested_user_id' => $uid,
                        'summary' => "Profile contains deleted experience ID [{$gid}] that does not exist in the database.",
                        'fix_label' => 'Remove Ghost',
                    ];
                }

                // Experiences belonging to user but missing in ReviewerProfile
                $userExps = $expUserMap[$uid] ?? [];
                $missing = array_diff($userExps, $rpExps);
                foreach ($missing as $mid) {
                    $expDoc = $expIdMap[$mid] ?? null;
                    $issueList[] = [
                        'key' => "missing_{$rpid}_{$mid}",
                        'type' => 'missing_in_profile',
                        'type_label' => 'Missing in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'exp_id' => $mid,
                        'designation_id' => (string) ($expDoc->designation ?? ''),
                        'designation_title' => '',
                        'organization_id' => (string) ($expDoc->organization_id ?? ''),
                        'organization_name' => '',
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'join_date' => (string) ($expDoc->join_date ?? ''),
                        'end_date' => (string) ($expDoc->end_date ?? ''),
                        'current_organization' => (int) ($expDoc->current_organization ?? 0),
                        'status' => $expDoc->status ?? null,
                        'suggested_user_id' => $uid,
                        'summary' => "Experience exists for this user, but its ID is missing from their reviewer profile.",
                        'fix_label' => 'Add to Profile',
                    ];
                }
            }

            // Experiences with missing user_id
            foreach ($noUserExps as $e) {
                $eid = (string) ($e->_id ?? $e->id ?? '');
                $suggestedUid = $knownUserMap[$eid] ?? null;

                $statusVal = $e->status ?? null;
                if (empty($suggestedUid) && ($statusVal === 0 || $statusVal === '0')) {
                    continue;
                }

                $issueList[] = [
                    'key' => "nouser_{$eid}",
                    'type' => 'no_user_id',
                    'type_label' => 'No User ID',
                    'rp_id' => '',
                    'user_id' => '',
                    'exp_id' => $eid,
                    'designation_id' => (string) ($e->designation ?? ''),
                    'designation_title' => '',
                    'organization_id' => (string) ($e->organization_id ?? ''),
                    'organization_name' => '',
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'join_date' => (string) ($e->join_date ?? ''),
                    'end_date' => (string) ($e->end_date ?? ''),
                    'current_organization' => (int) ($e->current_organization ?? 0),
                    'status' => $e->status ?? null,
                    'suggested_user_id' => $suggestedUid,
                    'summary' => $suggestedUid ? "Experience has no user ID. Detected owner: {$suggestedUid}." : "Experience has no user ID assigned.",
                    'fix_label' => 'Assign User',
                ];
            }

            // Experiences with missing status
            foreach ($noStatusExps as $e) {
                $eid = (string) ($e->_id ?? $e->id ?? '');
                $uid = (string) ($e->user_id ?? '');

                $issueList[] = [
                    'key' => "nostatus_{$eid}",
                    'type' => 'missing_status',
                    'type_label' => 'Missing Status',
                    'rp_id' => '',
                    'user_id' => $uid,
                    'exp_id' => $eid,
                    'designation_id' => (string) ($e->designation ?? ''),
                    'designation_title' => '',
                    'organization_id' => (string) ($e->organization_id ?? ''),
                    'organization_name' => '',
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'join_date' => (string) ($e->join_date ?? ''),
                    'end_date' => (string) ($e->end_date ?? ''),
                    'current_organization' => (int) ($e->current_organization ?? 0),
                    'status' => null,
                    'suggested_user_id' => $uid,
                    'summary' => "Experience document has no status field. Needs status: 0 (Inactive).",
                    'fix_label' => 'Set Status 0',
                ];
            }

            return $issueList;
        });
    }

    /**
     * Decorate raw issues with User, Designation, and Organization names.
     */
    protected function getDecoratedIssues(): array
    {
        $allIssues = $this->scanIssues();

        $userIds = [];
        $desigIds = [];
        $orgIds = [];
        foreach ($allIssues as $i) {
            if (! empty($i['user_id'])) {
                $userIds[] = $i['user_id'];
            }
            if (! empty($i['suggested_user_id'])) {
                $userIds[] = $i['suggested_user_id'];
            }
            if (! empty($i['designation_id'])) {
                $desigIds[] = $i['designation_id'];
            }
            if (! empty($i['organization_id'])) {
                $orgIds[] = $i['organization_id'];
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        $desigIds = array_values(array_unique(array_filter($desigIds)));
        $orgIds = array_values(array_unique(array_filter($orgIds)));

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
            ? User::whereIn('_id', $userSearchKeys)->get()->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        $desigMap = ! empty($desigIds)
            ? Designation::whereIn('_id', array_map(fn ($id) => $this->toMongoId($id), $desigIds))->get()->keyBy(fn ($d) => (string) $d->_id)
            : collect();

        $orgMap = ! empty($orgIds)
            ? Organization::whereIn('_id', array_map(fn ($id) => $this->toMongoId($id), $orgIds))->get()->keyBy(fn ($o) => (string) $o->_id)
            : collect();

        foreach ($allIssues as &$item) {
            $effectiveUid = ! empty($item['user_id']) ? $item['user_id'] : ($item['suggested_user_id'] ?? '');
            $u = $usersMap->get($effectiveUid);
            $d = $desigMap->get($item['designation_id'] ?? '');
            $o = $orgMap->get($item['organization_id'] ?? '');

            $item['user_name'] = $u ? ($u->name ?? 'User') : null;
            $item['user_email'] = $u ? ($u->email ?? '') : null;
            $item['user_unique_id'] = $u ? ($u->unique_id ?? null) : null;
            $item['designation_title'] = $d ? ($d->designation ?? $d->name ?? '') : ($item['designation_id'] ?: 'N/A');
            $item['organization_name'] = $o ? ($o->organization_name ?? $o->name ?? '') : ($item['organization_id'] ?: 'N/A');
        }
        unset($item);

        return $allIssues;
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $issues = $this->getFilteredIssues();
            $pageItems = array_slice($issues, ($this->getPage() - 1) * $this->perPage, $this->perPage);
            $this->selectedRows = array_column($pageItems, 'key');
        } else {
            $this->selectedRows = [];
        }
    }

    protected function getFilteredIssues(): array
    {
        $allIssues = $this->getDecoratedIssues();

        // 1. Filter by Tab
        if ($this->tab !== 'all') {
            $allIssues = array_values(array_filter($allIssues, fn ($item) => $item['type'] === $this->tab));
        }

        // 2. Filter by Search Query
        if (trim($this->search) !== '') {
            $term = strtolower(trim($this->search));
            $allIssues = array_values(array_filter($allIssues, function ($item) use ($term) {
                return str_contains(strtolower($item['exp_id'] ?? ''), $term)
                    || str_contains(strtolower($item['rp_id'] ?? ''), $term)
                    || str_contains(strtolower($item['user_id'] ?? ''), $term)
                    || str_contains(strtolower($item['suggested_user_id'] ?? ''), $term)
                    || str_contains(strtolower($item['user_name'] ?? ''), $term)
                    || str_contains(strtolower($item['user_email'] ?? ''), $term)
                    || str_contains(strtolower($item['designation_title'] ?? ''), $term)
                    || str_contains(strtolower($item['organization_name'] ?? ''), $term);
            }));
        }

        return $allIssues;
    }

    public function with(): array
    {
        $allDecorated = $this->getDecoratedIssues();

        // Count totals per category
        $counts = [
            'all' => count($allDecorated),
            'missing_in_profile' => 0,
            'no_user_id' => 0,
            'ghost_in_profile' => 0,
            'missing_status' => 0,
        ];
        foreach ($allDecorated as $i) {
            if (isset($counts[$i['type']])) {
                $counts[$i['type']]++;
            }
        }

        // Filter for active Tab & Search
        $filtered = $this->getFilteredIssues();
        $totalFiltered = count($filtered);

        // Paginate in-memory collection
        $currentPage = $this->getPage();
        $sliced = array_slice($filtered, ($currentPage - 1) * $this->perPage, $this->perPage);

        $paginator = new LengthAwarePaginator(
            $sliced,
            $totalFiltered,
            $this->perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page']
        );

        return [
            'issues' => $sliced,
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
        Cache::forget('db_fixing_exp_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully fixed issue: {$issue['type_label']} (Experience #{$issue['exp_id']})!";
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

        Cache::forget('db_fixing_exp_issues');
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

        Cache::forget('db_fixing_exp_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully resolved all {$total} issue(s) across experiences and reviewer profiles.";
    }

    /**
     * Internal resolver for an issue item.
     */
    protected function resolveIssue(array $issue): void
    {
        $type = $issue['type'];
        $expId = (string) $issue['exp_id'];
        $mongoExpId = $this->toMongoId($expId);
        $rpId = (string) ($issue['rp_id'] ?? '');
        $userId = (string) (! empty($issue['user_id']) ? $issue['user_id'] : ($issue['suggested_user_id'] ?? ''));

        switch ($type) {
            case 'missing_in_profile':
                // 1. Add experience ID to ReviewerProfile->experience array
                if ($userId !== '') {
                    $profile = ReviewerProfile::where(function ($q) use ($rpId, $userId) {
                        if ($rpId !== '') {
                            $q->where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId);
                        } else {
                            $q->where('user_id', $this->toMongoId($userId))->orWhere('user_id', $userId);
                        }
                    })->first();

                    if ($profile) {
                        $currentExp = is_array($profile->experience ?? null) ? array_map('strval', $profile->experience) : [];
                        if (! in_array($expId, $currentExp, true)) {
                            $currentExp[] = $expId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'experience' => array_values(array_unique($currentExp)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }

                // 2. Check and ensure status = 0 if missing
                $expDoc = DB::connection('mongodb')->table('experience')
                    ->where(function ($q) use ($expId, $mongoExpId) {
                        $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                    })->first();

                if ($expDoc && ($expDoc->status === null || $expDoc->status === '')) {
                    DB::connection('mongodb')->table('experience')
                        ->where(function ($q) use ($expId, $mongoExpId) {
                            $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                        })->update(['status' => 0]);
                }
                                        // Ensure experience has correct user_id if missing
                        if ($userId !== '' && $expDoc && empty($expDoc->user_id)) {
                            DB::connection('mongodb')
                                ->table('experience')
                                ->where(function ($q) use ($expId, $mongoExpId) {
                                    $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                                })
                                ->update(['user_id' => $userId]);
                        }
                        break;

            case 'no_user_id':
                // 1. Assign user_id to Experience record and set status = 0 if missing
// Determine the correct user_id if it's missing. Look up a reviewer_profile that already references this experience.
if ($userId === '' && $expId !== '') {
    $ownerProfile = DB::connection('mongodb')
        ->table('reviewer_profile')
        ->where('experience', $expId) // matches an array element
        ->first();
    if ($ownerProfile && ! empty($ownerProfile->user_id)) {
        $userId = (string) $ownerProfile->user_id;
    }
}
                if ($userId !== '') {
                    $updatePayload = ['user_id' => $userId];

                    $expDoc = DB::connection('mongodb')->table('experience')
                        ->where(function ($q) use ($expId, $mongoExpId) {
                            $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                        })->first();

                    if ($expDoc && ($expDoc->status === null || $expDoc->status === '')) {
                        $updatePayload['status'] = 0;
                    }

                    DB::connection('mongodb')->table('experience')
                        ->where(function ($q) use ($expId, $mongoExpId) {
                            $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                        })->update($updatePayload);

                    // 2. Add experience ID to that user's ReviewerProfile
                    $profile = ReviewerProfile::where('user_id', $userId)
                        ->orWhere('user_id', $this->toMongoId($userId))
                        ->first();

                    if ($profile) {
                        $currentExp = is_array($profile->experience ?? null) ? array_map('strval', $profile->experience) : [];
                        if (! in_array($expId, $currentExp, true)) {
                            $currentExp[] = $expId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'experience' => array_values(array_unique($currentExp)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }
                                        // Ensure experience has correct user_id if missing
                        if ($userId !== '' && $expDoc && empty($expDoc->user_id)) {
                            DB::connection('mongodb')
                                ->table('experience')
                                ->where(function ($q) use ($expId, $mongoExpId) {
                                    $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                                })
                                ->update(['user_id' => $userId]);
                        }
                        break;

            case 'ghost_in_profile':
                // Remove ghost ID from ReviewerProfile->experience array
                if ($rpId !== '') {
                    $profile = ReviewerProfile::where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId)->first();
                    if ($profile) {
                        $currentExp = is_array($profile->experience ?? null) ? array_map('strval', $profile->experience) : [];
                        $cleaned = array_values(array_diff($currentExp, [$expId]));
                        DB::connection('mongodb')->table('reviewer_profile')
                            ->where('_id', $profile->_id)
                            ->update([
                                'experience' => $cleaned,
                                'updated_at' => now(),
                            ]);
                    }
                }
                                        // Ensure experience has correct user_id if missing
                        if ($userId !== '' && $expDoc && empty($expDoc->user_id)) {
                            DB::connection('mongodb')
                                ->table('experience')
                                ->where(function ($q) use ($expId, $mongoExpId) {
                                    $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                                })
                                ->update(['user_id' => $userId]);
                        }
                        break;

            case 'missing_status':
                // Set status: 0 (Inactive) on experience document
                DB::connection('mongodb')->table('experience')
                    ->where(function ($q) use ($expId, $mongoExpId) {
                        $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                    })->update(['status' => 0]);
                                        // Ensure experience has correct user_id if missing
                        if ($userId !== '' && $expDoc && empty($expDoc->user_id)) {
                            DB::connection('mongodb')
                                ->table('experience')
                                ->where(function ($q) use ($expId, $mongoExpId) {
                                    $q->where('_id', $mongoExpId)->orWhere('_id', $expId);
                                })
                                ->update(['user_id' => $userId]);
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
            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-700/60">
                Reviewer Profile & User Sync
            </span>
            <a 
                href="{{ route('admin.database-fixing.experience.status') }}" 
                wire:navigate
                class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
            >
                status Fixer
            </a>
            <a 
                href="{{ route('admin.database-fixing.experience.index') }}" 
                wire:navigate
                class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
            >
                join_date & end_date Standardizer &rarr;
            </a>
        </div>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight flex items-center gap-2">
            <span>Experience &mdash; Reviewer Profile & User ID Sync</span>
            <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                4-in-1 Data Sync & Integrity
            </span>
        </h2>
        
        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-rose-600 transition cursor-pointer select-none"
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
                    This tool connects your <strong>Experience</strong> records with the user's <strong>Reviewer Profile</strong> and fixes 4 common problems automatically:
                </p>
                <ul class="list-disc list-inside space-y-1 text-xs text-zinc-700 dark:text-zinc-200">
                    <li>
                        <strong>Missing in Profile:</strong> When a user created an experience, but its ID was never added to their reviewer profile array. Clicking fix adds this experience ID directly to their reviewer profile.
                    </li>
                    <li>
                        <strong>Deleted / Ghost IDs:</strong> When a reviewer profile has an experience ID that was deleted or doesn't exist anymore. Clicking fix cleanly removes that ghost ID from their profile.
                    </li>
                    <li>
                        <strong>No User ID:</strong> When an experience record is missing its owner's user ID. Clicking fix attaches the correct user ID and links the experience to their profile.
                    </li>
                    <li>
                        <strong>Missing Status:</strong> When an experience has no status field at all. Clicking fix creates the status field and sets its value to <code class="font-mono text-rose-600 dark:text-rose-400 font-bold">0</code> (Inactive).
                    </li>
                </ul>
                <div class="pt-1 text-[11px] text-zinc-500 border-t border-zinc-200 dark:border-zinc-700/60">
                    Total Issues Detected in Database: <strong class="text-rose-600 font-bold">{{ $totalIssues }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- ROSE HERO BANNER CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-700 via-pink-600 to-rose-500 p-6 sm:p-7 text-white shadow-lg">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="space-y-2 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                        Reviewer Profile & Experience Synchronizer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-rose-100 leading-relaxed">
                    Fixes missing links between Experience records and Reviewer Profiles. It adds unlinked experiences to user profiles, cleans ghost IDs, attaches user IDs to orphan experiences, and creates missing statuses as <code class="bg-rose-800/60 px-1.5 py-0.5 rounded font-mono text-white">0</code>.
                </p>
            </div>

            <!-- Right Counters Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
                <div class="px-3.5 py-2.5 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center">
                    <span class="block text-xl font-black text-white">{{ $counts['missing_in_profile'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-rose-100">Missing in Profile</span>
                </div>
                <div class="px-3.5 py-2.5 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center">
                    <span class="block text-xl font-black text-white">{{ $counts['no_user_id'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-rose-100">No User ID</span>
                </div>
                <div class="px-3.5 py-2.5 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center">
                    <span class="block text-xl font-black text-white">{{ $counts['ghost_in_profile'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-rose-100">Ghost IDs</span>
                </div>
                <div class="px-3.5 py-2.5 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center">
                    <span class="block text-xl font-black text-white">{{ $counts['missing_status'] }}</span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-rose-100">No Status</span>
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
                    placeholder="Search by User Name, Email, or ID..." 
                    class="w-full pl-9 pr-4 py-1.5 rounded-xl text-xs bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-rose-500"
                />
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                @if(count($selectedRows) > 0)
                    <button 
                        type="button" 
                        wire:click="fixSelected"
                        wire:loading.attr="disabled"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>Fix Selected ({{ count($selectedRows) }})</span>
                    </button>
                @endif

                @if($totalIssues > 0)
                    <button 
                        type="button" 
                        wire:click="fixAll"
                        wire:confirm="Are you sure you want to automatically fix and synchronize all {{ $totalIssues }} issue(s) across experiences and reviewer profiles?"
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
                                class="rounded border-zinc-300 dark:border-zinc-700 text-rose-600 focus:ring-rose-500 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3">User Account</th>
                        <th class="px-4 py-3">Experience / Job Details</th>
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
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-rose-600 focus:ring-rose-500 cursor-pointer"
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

                            <!-- Experience Details -->
                            <td class="px-4 py-3 space-y-0.5">
                                @if($row['type'] === 'ghost_in_profile')
                                    <span class="text-zinc-400 italic">Deleted from database</span>
                                @else
                                    <div class="font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ !empty($row['designation_title']) ? $row['designation_title'] : 'Designation N/A' }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                        {{ !empty($row['organization_name']) ? $row['organization_name'] : 'Organization N/A' }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400">
                                        {{ !empty($row['join_date']) ? $row['join_date'] : 'No start' }} &mdash; 
                                        @if(($row['current_organization'] ?? 0) == 1)
                                            <span class="text-emerald-600 font-bold">Present</span>
                                        @else
                                            {{ !empty($row['end_date']) ? $row['end_date'] : 'No end' }}
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- IDs -->
                            <td class="px-4 py-3 font-mono text-[11px] space-y-0.5">
                                <div>
                                    <span class="text-[10px] text-zinc-400 font-sans">Exp:</span>
                                    <span class="text-zinc-700 dark:text-zinc-300 select-all">{{ $row['exp_id'] ?: 'N/A' }}</span>
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
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 text-rose-600 border border-rose-300 dark:border-rose-700 hover:bg-rose-600 hover:text-white hover:border-rose-600 transition shadow-2xs cursor-pointer"
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
                                        All Experiences & Reviewer Profiles Are Synchronized!
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        There are 0 unlinked or broken records in this view. Every experience is correctly linked to its owner's reviewer profile, user IDs are attached, and statuses are valid.
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

