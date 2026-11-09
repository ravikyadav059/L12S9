<?php

use App\Models\Organization;
use App\Models\ReviewerProfile;
use App\Models\Seminar;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Seminar — Reviewer Profile & User Sync - Admin')] class extends Component {
    use WithPagination;

    public string $tab = 'all'; // 'all', 'no_user_id', 'missing_in_profile', 'ghost_in_profile', 'missing_status'

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
     * Scan and build the full list of data integrity issues for Seminars:
     * 1. No User ID: seminar has no user_id (recoverable from profile or true orphan)
     * 2. Missing in Profile: seminar has user_id, but seminar ID is missing from user's reviewer_profile.seminar
     * 3. Ghost in Profile: reviewer_profile.seminar contains ID that does not exist in seminar collection
     * 4. Missing Status: seminar has null or empty status
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_seminar_issues', 60, function () {
            // 1. Fetch all seminars from MongoDB with lean projection
            $allSeminars = DB::connection('mongodb')->table('seminar')
                ->get([
                    '_id',
                    'title',
                    'host_by',
                    'month_year',
                    'type_event',
                    'country',
                    'user_id',
                    'status',
                ]);

            $seminarIdMap = [];
            $seminarUserMap = [];
            $noUserSeminars = [];
            $noStatusSeminars = [];

            foreach ($allSeminars as $s) {
                $rawId = $s->_id ?? $s->id ?? null;
                $sid = (string) $rawId;
                $seminarIdMap[$sid] = $s;

                $hasUser = ! empty($s->user_id);
                if ($hasUser) {
                    $seminarUserMap[(string) $s->user_id][] = $sid;
                } else {
                    $noUserSeminars[] = $s;
                }

                if ($s->status === null || $s->status === '') {
                    $noStatusSeminars[] = $s;
                }
            }

            // 2. Fetch ReviewerProfiles with user_id and seminar array
            $profiles = DB::connection('mongodb')->table('reviewer_profile')
                ->whereNotNull('user_id')
                ->get(['_id', 'user_id', 'seminar']);

            // Map each seminar ID found in profiles to its owner user_id and profile_id
            $profileSeminarMap = [];
            foreach ($profiles as $p) {
                $uid = (string) $p->user_id;
                $rawId = $p->_id ?? $p->id ?? null;
                $rpid = (string) $rawId;
                $rpSeminars = is_array($p->seminar ?? null) ? array_map('strval', $p->seminar) : [];

                foreach ($rpSeminars as $sid) {
                    if (! isset($profileSeminarMap[$sid])) {
                        $profileSeminarMap[$sid] = [
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
                $rpSeminars = is_array($p->seminar ?? null) ? array_map('strval', $p->seminar) : [];

                // A) Ghost IDs in ReviewerProfile
                $ghosts = array_diff($rpSeminars, array_keys($seminarIdMap));
                foreach ($ghosts as $gid) {
                    $issueList[] = [
                        'key' => "ghost_{$rpid}_{$gid}",
                        'type' => 'ghost_in_profile',
                        'type_label' => 'Ghost ID in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'seminar_id' => $gid,
                        'title' => 'N/A (Deleted Seminar)',
                        'host_by' => '',
                        'host_name' => '',
                        'month_year' => '',
                        'type_event' => '',
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => null,
                        'suggested_user_id' => $uid,
                        'summary' => "Reviewer profile references seminar ID [{$gid}] that does not exist in the database.",
                        'fix_label' => 'Remove Ghost',
                    ];
                }

                // B) Seminars belonging to this user but missing from ReviewerProfile
                $userSeminars = $seminarUserMap[$uid] ?? [];
                $missing = array_diff($userSeminars, $rpSeminars);
                foreach ($missing as $mid) {
                    $sDoc = $seminarIdMap[$mid] ?? null;
                    $issueList[] = [
                        'key' => "missing_{$rpid}_{$mid}",
                        'type' => 'missing_in_profile',
                        'type_label' => 'Missing in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'seminar_id' => $mid,
                        'title' => (string) ($sDoc->title ?? 'Untitled Seminar'),
                        'host_by' => (string) ($sDoc->host_by ?? ''),
                        'host_name' => null,
                        'month_year' => (string) ($sDoc->month_year ?? ''),
                        'type_event' => (string) ($sDoc->type_event ?? ''),
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => $sDoc->status ?? null,
                        'suggested_user_id' => $uid,
                        'summary' => 'Seminar exists with user_id, but its ID is missing from their reviewer profile array.',
                        'fix_label' => 'Add to Profile',
                    ];
                }
            }

            // 4. Seminars with missing user_id
            foreach ($noUserSeminars as $s) {
                $sid = (string) ($s->_id ?? $s->id ?? '');
                $detected = $profileSeminarMap[$sid] ?? null;
                $detectedUid = $detected['user_id'] ?? null;
                $detectedRpid = $detected['rp_id'] ?? '';

                $statusVal = $s->status ?? null;
                // If this is a true orphan (not present in any reviewer profile)
                // and its status is ALREADY set to 0 (Inactive orphan),
                // it is fully processed and should not be flagged as an active issue.
                if (empty($detectedUid) && ($statusVal === 0 || $statusVal === '0')) {
                    continue;
                }

                $summary = $detectedUid
                    ? "Seminar has no user ID. Detected owner from Reviewer Profile: [{$detectedUid}]. Clicking fix will attach user ID and set status = 1."
                    : 'Seminar has no user ID and is not present in any reviewer profile. Clicking fix will set status = 0 (Inactive orphan).';

                $fixLabel = $detectedUid ? 'Assign User (Status 1)' : 'Mark Orphan (Status 0)';

                $issueList[] = [
                    'key' => "nouser_{$sid}",
                    'type' => 'no_user_id',
                    'type_label' => 'No User ID',
                    'rp_id' => $detectedRpid,
                    'user_id' => '',
                    'seminar_id' => $sid,
                    'title' => (string) ($s->title ?? 'Untitled Seminar'),
                    'host_by' => (string) ($s->host_by ?? ''),
                    'host_name' => null,
                    'month_year' => (string) ($s->month_year ?? ''),
                    'type_event' => (string) ($s->type_event ?? ''),
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => $s->status ?? null,
                    'suggested_user_id' => $detectedUid,
                    'summary' => $summary,
                    'fix_label' => $fixLabel,
                ];
            }

            // 5. Seminars with missing status
            foreach ($noStatusSeminars as $s) {
                $sid = (string) ($s->_id ?? $s->id ?? '');
                $uid = (string) ($s->user_id ?? '');
                $detected = $profileSeminarMap[$sid] ?? null;
                $effectiveUid = $uid ?: ($detected['user_id'] ?? null);

                $summary = ! empty($effectiveUid)
                    ? 'Seminar document has no status field. Has owner; will be set to status: 1 (Active).'
                    : 'Seminar document has no status field and no owner; will be set to status: 0 (Inactive).';

                $issueList[] = [
                    'key' => "nostatus_{$sid}",
                    'type' => 'missing_status',
                    'type_label' => 'Missing Status',
                    'rp_id' => $detected['rp_id'] ?? '',
                    'user_id' => $uid,
                    'seminar_id' => $sid,
                    'title' => (string) ($s->title ?? 'Untitled Seminar'),
                    'host_by' => (string) ($s->host_by ?? ''),
                    'host_name' => null,
                    'month_year' => (string) ($s->month_year ?? ''),
                    'type_event' => (string) ($s->type_event ?? ''),
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => null,
                    'suggested_user_id' => $effectiveUid,
                    'summary' => $summary,
                    'fix_label' => ! empty($effectiveUid) ? 'Set Status 1 (Active)' : 'Set Status 0 (Inactive)',
                ];
            }

            return $issueList;
        });
    }

    /**
     * Decorate current page items with User and Organization models.
     */
    protected function decoratePageItems(array $pageItems): array
    {
        if (empty($pageItems)) {
            return [];
        }

        $userIds = [];
        $orgIds = [];

        foreach ($pageItems as $i) {
            $uid = (string) (! empty($i['user_id']) ? $i['user_id'] : ($i['suggested_user_id'] ?? ''));
            if ($uid !== '') {
                $userIds[] = $uid;
            }

            $hid = (string) ($i['host_by'] ?? '');
            if ($hid !== '' && strlen($hid) === 24 && ctype_xdigit($hid)) {
                $orgIds[] = $hid;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
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

        $orgSearchKeys = [];
        foreach ($orgIds as $oid) {
            $orgSearchKeys[] = $oid;
            try {
                $orgSearchKeys[] = new ObjectId($oid);
            } catch (\Exception $e) {
            }
        }

        $usersMap = ! empty($userSearchKeys)
            ? User::whereIn('_id', $userSearchKeys)
                ->select(['_id', 'name', 'email', 'unique_id'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        $orgsMap = ! empty($orgSearchKeys)
            ? Organization::whereIn('_id', $orgSearchKeys)
                ->select(['_id', 'name', 'institution_name'])
                ->get()
                ->keyBy(fn ($o) => (string) $o->_id)
            : collect();

        foreach ($pageItems as &$item) {
            $uid = (string) (! empty($item['user_id']) ? $item['user_id'] : ($item['suggested_user_id'] ?? ''));
            $u = $usersMap->get($uid);

            $item['user_name'] = $u ? ($u->name ?? 'User') : null;
            $item['user_email'] = $u ? ($u->email ?? '') : null;
            $item['user_unique_id'] = $u ? ($u->unique_id ?? null) : null;

            $hid = (string) ($item['host_by'] ?? '');
            $o = $orgsMap->get($hid);
            $item['host_name'] = $o ? ($o->name ?? $o->institution_name ?? $hid) : $hid;
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
                    || str_contains(strtolower($item['seminar_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['rp_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['suggested_user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['title'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['host_by'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['month_year'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['type_event'] ?? ''), $lowerTerm);
            }));
        }

        return $allIssues;
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
        Cache::forget('db_fixing_seminar_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully fixed issue: {$issue['type_label']} (Seminar #{$issue['seminar_id']})!";
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

        Cache::forget('db_fixing_seminar_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected issue(s)!";
    }

    /**
     * Fix all issues across the entire database in one click.
     * Uses optimized bulk groupings for maximum speed over 23,000+ records.
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

        // Fast batch user recovery via reviewer profiles
        $profiles = DB::connection('mongodb')->table('reviewer_profile')
            ->whereNotNull('user_id')
            ->whereNotNull('seminar')
            ->get(['_id', 'user_id', 'seminar']);

        foreach ($profiles as $p) {
            $uid = (string) $p->user_id;
            $sList = is_array($p->seminar ?? null) ? array_map('strval', $p->seminar) : [];
            if (empty($sList) || empty($uid)) {
                continue;
            }

            $mongoIds = [];
            foreach ($sList as $sid) {
                $mongoIds[] = $this->toMongoId($sid);
                $mongoIds[] = $sid;
            }

            DB::connection('mongodb')->table('seminar')
                ->whereIn('_id', $mongoIds)
                ->where(function ($q) {
                    $q->whereNull('user_id')->orWhere('user_id', '');
                })
                ->update([
                    'user_id' => $uid,
                    'status' => 1,
                    'updated_at' => now(),
                ]);
        }

        // True orphans: mark status = 0
        DB::connection('mongodb')->table('seminar')
            ->where(function ($q) {
                $q->whereNull('user_id')->orWhere('user_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 0);
            })
            ->update([
                'status' => 0,
                'updated_at' => now(),
            ]);

        // Resolve any remaining miscellaneous issues (ghost IDs, missing in profile)
        foreach ($allIssues as $issue) {
            if ($issue['type'] === 'ghost_in_profile' || $issue['type'] === 'missing_in_profile') {
                $this->resolveIssue($issue);
            }
        }

        Cache::forget('db_fixing_seminar_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully resolved all {$total} issue(s) across seminars and reviewer profiles.";
    }

    /**
     * Internal resolver for an issue item.
     */
    protected function resolveIssue(array $issue): void
    {
        $type = $issue['type'];
        $seminarId = (string) $issue['seminar_id'];
        $mongoSeminarId = $this->toMongoId($seminarId);
        $rpId = (string) ($issue['rp_id'] ?? '');
        $userId = (string) (! empty($issue['user_id']) ? $issue['user_id'] : ($issue['suggested_user_id'] ?? ''));

        switch ($type) {
            case 'missing_in_profile':
                // 1. Add seminar ID to ReviewerProfile->seminar array
                if ($userId !== '') {
                    $profile = ReviewerProfile::where(function ($q) use ($rpId, $userId) {
                        if ($rpId !== '') {
                            $q->where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId);
                        } else {
                            $q->where('user_id', $this->toMongoId($userId))->orWhere('user_id', $userId);
                        }
                    })->first();

                    if ($profile) {
                        $currentS = is_array($profile->seminar ?? null) ? array_map('strval', $profile->seminar) : [];
                        if (! in_array($seminarId, $currentS, true)) {
                            $currentS[] = $seminarId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'seminar' => array_values(array_unique($currentS)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    // 2. Ensure seminar has status = 1 (Active) and user_id set
                    $updatePayload = ['user_id' => $userId];
                    $sDoc = DB::connection('mongodb')->table('seminar')
                        ->where(function ($q) use ($seminarId, $mongoSeminarId) {
                            $q->where('_id', $mongoSeminarId)->orWhere('_id', $seminarId);
                        })->first();

                    if ($sDoc && ($sDoc->status === null || $sDoc->status === '' || (int) $sDoc->status === 0)) {
                        $updatePayload['status'] = 1;
                    }

                    DB::connection('mongodb')->table('seminar')
                        ->where(function ($q) use ($seminarId, $mongoSeminarId) {
                            $q->where('_id', $mongoSeminarId)->orWhere('_id', $seminarId);
                        })->update($updatePayload);
                }
                break;

            case 'no_user_id':
                // Determine owner user_id: if not present in issue, look up reviewer_profile that contains this seminar ID
                if ($userId === '' && $seminarId !== '') {
                    $ownerProfile = DB::connection('mongodb')->table('reviewer_profile')
                        ->where('seminar', $seminarId)
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

                    DB::connection('mongodb')->table('seminar')
                        ->where(function ($q) use ($seminarId, $mongoSeminarId) {
                            $q->where('_id', $mongoSeminarId)->orWhere('_id', $seminarId);
                        })->update($updatePayload);

                    // Ensure the owner's reviewer profile contains this seminar ID
                    $profile = ReviewerProfile::where('user_id', $userId)
                        ->orWhere('user_id', $this->toMongoId($userId))
                        ->first();

                    if ($profile) {
                        $currentS = is_array($profile->seminar ?? null) ? array_map('strval', $profile->seminar) : [];
                        if (! in_array($seminarId, $currentS, true)) {
                            $currentS[] = $seminarId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'seminar' => array_values(array_unique($currentS)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                } else {
                    // True orphan: not in any profile and no owner: set status = 0 (Inactive)
                    DB::connection('mongodb')->table('seminar')
                        ->where(function ($q) use ($seminarId, $mongoSeminarId) {
                            $q->where('_id', $mongoSeminarId)->orWhere('_id', $seminarId);
                        })->update(['status' => 0]);
                }
                break;

            case 'ghost_in_profile':
                // Remove ghost ID from ReviewerProfile->seminar array
                if ($rpId !== '') {
                    $profile = ReviewerProfile::where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId)->first();
                    if ($profile) {
                        $currentS = is_array($profile->seminar ?? null) ? array_map('strval', $profile->seminar) : [];
                        $cleaned = array_values(array_diff($currentS, [$seminarId]));
                        DB::connection('mongodb')->table('reviewer_profile')
                            ->where('_id', $profile->_id)
                            ->update([
                                'seminar' => $cleaned,
                                'updated_at' => now(),
                            ]);
                    }
                }
                break;

            case 'missing_status':
                $statusToSet = ! empty($userId) ? 1 : 0;
                $updateData = ['status' => $statusToSet];
                if (! empty($userId)) {
                    $updateData['user_id'] = $userId;
                }

                DB::connection('mongodb')->table('seminar')
                    ->where(function ($q) use ($seminarId, $mongoSeminarId) {
                        $q->where('_id', $mongoSeminarId)->orWhere('_id', $seminarId);
                    })->update($updateData);

                if (! empty($userId)) {
                    $profile = ReviewerProfile::where('user_id', $userId)
                        ->orWhere('user_id', $this->toMongoId($userId))
                        ->first();

                    if ($profile) {
                        $currentS = is_array($profile->seminar ?? null) ? array_map('strval', $profile->seminar) : [];
                        if (! in_array($seminarId, $currentS, true)) {
                            $currentS[] = $seminarId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'seminar' => array_values(array_unique($currentS)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }
                break;
        }
    }

    public function with(): array
    {
        $allIssues = $this->scanIssues();

        $counts = [
            'all' => count($allIssues),
            'no_user_id' => 0,
            'missing_in_profile' => 0,
            'ghost_in_profile' => 0,
            'missing_status' => 0,
        ];
        foreach ($allIssues as $i) {
            if (isset($counts[$i['type']])) {
                $counts[$i['type']]++;
            }
        }

        $filtered = $this->getFilteredIssues();
        $totalFiltered = count($filtered);

        $currentPage = $this->getPage();
        $slicedRaw = array_slice($filtered, ($currentPage - 1) * $this->perPage, $this->perPage);
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
            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Seminar</span>
        </div>

        <!-- Tool Switcher Pills -->
        <div class="inline-flex rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1">
            <a href="{{ route('admin.database-fixing.seminar.index') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
                <flux:icon.calendar class="size-3.5" />
                <span>Date Standardizer</span>
            </a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-300 shadow-xs">
                <flux:icon.user-group class="size-3.5" />
                <span>Reviewer Profile & User Sync</span>
            </span>
        </div>
    </div>

    <!-- Page Header & Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center size-8 rounded-lg bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400">
                    <flux:icon.user-group class="size-4" />
                </span>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                    Seminar — Reviewer Profile & User ID Sync
                </h1>
            </div>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Synchronizes seminar records with reviewer profiles, recovers missing user IDs from profiles (23,611 recoverable), cleans deleted ghost IDs, and standardizes orphan statuses.
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
    <div x-data="{ open: false }" class="rounded-xl border border-indigo-200/70 dark:border-indigo-900/40 bg-indigo-50/40 dark:bg-indigo-950/20 p-4">
        <button @click="open = !open" type="button" class="w-full flex items-center justify-between text-left">
            <div class="flex items-center gap-2">
                <flux:icon.information-circle class="size-4 text-indigo-600 dark:text-indigo-400" />
                <span class="text-sm font-semibold text-indigo-950 dark:text-indigo-200">
                    What does this synchronization tool do? (Plain English Summary)
                </span>
            </div>
            <flux:icon.chevron-down class="size-4 text-indigo-600 dark:text-indigo-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
        </button>

        <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-indigo-200/50 dark:border-indigo-900/30 text-xs text-zinc-600 dark:text-zinc-300 space-y-2">
            <p>
                In the scholar database, seminar records are linked bidirectionally: each <strong><code>seminar</code></strong> has a <code>user_id</code>, and each <strong><code>reviewer_profile</code></strong> contains an array of seminar IDs (<code>reviewer_profile.seminar</code>).
            </p>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-1">
                <div class="p-2.5 rounded-lg bg-white/70 dark:bg-zinc-900/60 border border-indigo-100 dark:border-indigo-900/30">
                    <span class="font-semibold text-indigo-900 dark:text-indigo-200">1. Recover User IDs (23,611 records):</span>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">Seminars with missing user IDs that appear in a reviewer profile will have their user ID restored and status set to 1 (Active).</p>
                </div>
                <div class="p-2.5 rounded-lg bg-white/70 dark:bg-zinc-900/60 border border-indigo-100 dark:border-indigo-900/30">
                    <span class="font-semibold text-indigo-900 dark:text-indigo-200">2. True Orphans (8 records):</span>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">Seminars with no user ID and not found in any reviewer profile will have their status set to 0 (Inactive orphan).</p>
                </div>
                <div class="p-2.5 rounded-lg bg-white/70 dark:bg-zinc-900/60 border border-indigo-100 dark:border-indigo-900/30">
                    <span class="font-semibold text-indigo-900 dark:text-indigo-200">3. Add Missing to Profile:</span>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">If a seminar has a user ID but is missing from their reviewer profile, it will be added to ensure it displays properly.</p>
                </div>
                <div class="p-2.5 rounded-lg bg-white/70 dark:bg-zinc-900/60 border border-indigo-100 dark:border-indigo-900/30">
                    <span class="font-semibold text-indigo-900 dark:text-indigo-200">4. Remove Ghost IDs:</span>
                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">If a profile references seminar IDs that have been deleted from the database, they will be purged safely.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Badges / Quick Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Sync Issues</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($counts['all']) }}</span>
                @if ($counts['all'] === 0)
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">In Sync</span>
                @endif
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/40 dark:bg-amber-950/20">
            <span class="text-xs font-medium text-amber-800 dark:text-amber-400">No User ID</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-amber-950 dark:text-amber-100">{{ number_format($counts['no_user_id']) }}</span>
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-blue-200 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-950/20">
            <span class="text-xs font-medium text-blue-700 dark:text-blue-400">Missing in Profile</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ number_format($counts['missing_in_profile']) }}</span>
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50/40 dark:bg-red-950/20">
            <span class="text-xs font-medium text-red-700 dark:text-red-400">Ghost Profile IDs</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-red-900 dark:text-red-100">{{ number_format($counts['ghost_in_profile']) }}</span>
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Missing Status</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($counts['missing_status']) }}</span>
            </div>
        </div>
    </div>

    <!-- Filter Tabs, Search & Action Bar -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
        <!-- Filter Tabs -->
        <div class="flex items-center gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-zinc-800 overflow-x-auto text-xs font-medium">
            <button wire:click="setTab('all')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'all' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                All Issues ({{ $counts['all'] }})
            </button>
            <button wire:click="setTab('no_user_id')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'no_user_id' ? 'bg-white dark:bg-zinc-900 text-amber-800 dark:text-amber-300 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                No User ID ({{ $counts['no_user_id'] }})
            </button>
            <button wire:click="setTab('missing_in_profile')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'missing_in_profile' ? 'bg-white dark:bg-zinc-900 text-blue-700 dark:text-blue-300 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Missing in Profile ({{ $counts['missing_in_profile'] }})
            </button>
            <button wire:click="setTab('ghost_in_profile')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'ghost_in_profile' ? 'bg-white dark:bg-zinc-900 text-red-700 dark:text-red-300 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Ghost IDs ({{ $counts['ghost_in_profile'] }})
            </button>
            <button wire:click="setTab('missing_status')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'missing_status' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Missing Status ({{ $counts['missing_status'] }})
            </button>
        </div>

        <!-- Search Bar & Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[220px]">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search title, host, user..." size="sm" icon="magnifying-glass" clearable />
            </div>

            @if ($totalIssues > 0)
                <button wire:click="fixAll" wire:confirm="Are you sure you want to resolve all {{ $totalIssues }} Seminar synchronization issues across the database?" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <flux:icon.arrow-path class="size-3.5" wire:loading.class="animate-spin" wire:target="fixAll" />
                    <span>Fix All ({{ $totalIssues }})</span>
                </button>
            @endif

            @if (!empty($selectedRows))
                <button wire:click="fixSelected" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <flux:icon.check class="size-3.5" wire:loading.class="animate-spin" wire:target="fixSelected" />
                    <span>Fix Selected ({{ count($selectedRows) }})</span>
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
                            <input type="checkbox" wire:click="toggleSelectAll" @checked($selectAll) class="rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 focus:ring-indigo-500 size-3.5" />
                        </th>
                        <th class="px-3 py-2.5 font-semibold">Issue Type</th>
                        <th class="px-3 py-2.5 font-semibold">Seminar Details</th>
                        <th class="px-3 py-2.5 font-semibold">Attached User / Owner</th>
                        <th class="px-3 py-2.5 font-semibold">Problem Diagnostics</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:border-zinc-800">
                    @forelse ($issues as $item)
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors {{ in_array($item['key'], $selectedRows) ? 'bg-indigo-50/30 dark:bg-indigo-950/20' : '' }}">
                            <!-- Checkbox -->
                            <td class="px-3 py-3">
                                <input type="checkbox" wire:model.live="selectedRows" value="{{ $item['key'] }}" class="rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 focus:ring-indigo-500 size-3.5" />
                            </td>

                            <!-- Issue Type Badge -->
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if ($item['type'] === 'no_user_id')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        No User ID
                                    </span>
                                @elseif ($item['type'] === 'missing_in_profile')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                        Missing in Profile
                                    </span>
                                @elseif ($item['type'] === 'ghost_in_profile')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300 border border-red-200 dark:border-red-800">
                                        Ghost ID
                                    </span>
                                @elseif ($item['type'] === 'missing_status')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                        Missing Status
                                    </span>
                                @endif
                            </td>

                            <!-- Seminar Details -->
                            <td class="px-3 py-3 max-w-[320px]">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100 line-clamp-1" title="{{ $item['title'] }}">
                                    {{ $item['title'] }}
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                                    @if (!empty($item['host_name']))
                                        <span class="text-indigo-700 dark:text-indigo-400 font-medium truncate max-w-[180px]" title="{{ $item['host_name'] }}">
                                            {{ $item['host_name'] }}
                                        </span>
                                    @endif
                                    @if ($item['month_year'])
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                        <span>{{ $item['month_year'] }}</span>
                                    @endif
                                    @if ($item['type_event'])
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                        <span class="capitalize text-zinc-600 dark:text-zinc-400">{{ $item['type_event'] }}</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                    Seminar ID: {{ $item['seminar_id'] }}
                                </div>
                            </td>

                            <!-- Attached User / Owner -->
                            <td class="px-3 py-3 max-w-[220px]">
                                @if (!empty($item['user_name']))
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 truncate" title="{{ $item['user_name'] }}">
                                        {{ $item['user_name'] }}
                                    </div>
                                    @if (!empty($item['user_email']))
                                        <div class="text-[11px] text-zinc-500 truncate" title="{{ $item['user_email'] }}">
                                            {{ $item['user_email'] }}
                                        </div>
                                    @endif
                                    <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                        UID: {{ $item['user_id'] ?: ($item['suggested_user_id'] ?? 'N/A') }}
                                    </div>
                                @elseif (!empty($item['suggested_user_id']))
                                    <div class="text-xs font-semibold text-amber-700 dark:text-amber-400">
                                        Detected in Reviewer Profile
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                        UID: {{ $item['suggested_user_id'] }}
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-zinc-400 dark:text-zinc-500">
                                        <flux:icon.user-minus class="size-3" />
                                        No User Attached
                                    </span>
                                @endif
                            </td>

                            <!-- Problem Diagnostics -->
                            <td class="px-3 py-3 max-w-[340px]">
                                <p class="text-[11px] text-zinc-600 dark:text-zinc-300">
                                    {{ $item['summary'] }}
                                </p>
                            </td>

                            <!-- Action -->
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <button wire:click="fixSingle('{{ $item['key'] }}')" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold shadow-xs transition-colors {{ $item['type'] === 'ghost_in_profile' ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}">
                                    <flux:icon.check class="size-3" wire:loading.class="hidden" wire:target="fixSingle('{{ $item['key'] }}')" />
                                    <flux:icon.arrow-path class="size-3 animate-spin hidden" wire:loading.class.remove="hidden" wire:target="fixSingle('{{ $item['key'] }}')" />
                                    <span>{{ $item['fix_label'] }}</span>
                                </button>
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
                                        All Seminars In Perfect Sync
                                    </h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">
                                        All Seminar documents are properly linked to their owner user profiles, have valid statuses, and no ghost references exist.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginator Links -->
        @if ($paginator->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>

