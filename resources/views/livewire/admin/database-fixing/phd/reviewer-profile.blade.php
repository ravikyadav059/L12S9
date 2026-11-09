<?php

use App\Models\Organization;
use App\Models\Phd;
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

new #[Layout('components.layouts.admin')] #[Title('PhD — Reviewer Profile & User Sync - Admin')] class extends Component {
    use WithPagination;

    public string $tab = 'all'; // 'all', 'missing_in_profile', 'no_user_id', 'ghost_in_profile', 'missing_status', 'missing_phd_status', 'missing_institution'

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
     * Scan and build the full list of data integrity issues for PhD records.
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_phd_issues', 60, function () {
            // 1. Fetch all PhD records from MongoDB
            $allPhds = DB::connection('mongodb')->table('phd')
                ->get(['_id', 'user_id', 'status', 'phd_status', 'institution_id', 'phd_name', 'phd_title', 'research_area', 'thesis_type']);

            $phdIdMap = [];
            $phdUserMap = [];
            $noUserPhds = [];
            $noStatusPhds = [];
            $noPhdStatusPhds = [];
            $noInstPhds = [];

            foreach ($allPhds as $p) {
                $rawId = $p->_id ?? $p->id ?? null;
                $pid = (string) $rawId;
                $phdIdMap[$pid] = $p;

                $hasUser = ! empty($p->user_id ?? null);
                if ($hasUser) {
                    $phdUserMap[(string) $p->user_id][] = $pid;
                } else {
                    $noUserPhds[] = $p;
                }

                $statusVal = $p->status ?? null;
                if ($statusVal === null || $statusVal === '') {
                    $noStatusPhds[] = $p;
                }

                $phdStatusVal = $p->phd_status ?? null;
                if ($phdStatusVal === null || trim((string) $phdStatusVal) === '') {
                    $noPhdStatusPhds[] = $p;
                }

                $instIdVal = $p->institution_id ?? null;
                if ($instIdVal === '') {
                    // Empty string should be standardized to true null
                    $noInstPhds[] = $p;
                }
            }

            // 2. Fetch ReviewerProfiles with user_id and phd array
            $profiles = DB::connection('mongodb')->table('reviewer_profile')
                ->whereNotNull('user_id')
                ->get(['_id', 'user_id', 'phd']);

            // Map each PhD ID found in profiles to its owner user_id and profile_id
            $profilePhdMap = [];
            foreach ($profiles as $prof) {
                $uid = (string) $prof->user_id;
                $rawId = $prof->_id ?? $prof->id ?? null;
                $rpid = (string) $rawId;
                $rpPhds = is_array($prof->phd ?? null) ? array_map('strval', $prof->phd) : [];

                foreach ($rpPhds as $pid) {
                    if (! isset($profilePhdMap[$pid])) {
                        $profilePhdMap[$pid] = [
                            'user_id' => $uid,
                            'rp_id' => $rpid,
                        ];
                    }
                }
            }

            $issueList = [];

            // 3. Scan ReviewerProfiles: Ghost IDs and Missing in Profile
            foreach ($profiles as $prof) {
                $uid = (string) $prof->user_id;
                $rawId = $prof->_id ?? $prof->id ?? null;
                $rpid = (string) $rawId;
                $rpPhds = is_array($prof->phd ?? null) ? array_map('strval', $prof->phd) : [];

                // A) Ghost IDs in ReviewerProfile
                $ghosts = array_diff($rpPhds, array_keys($phdIdMap));
                foreach ($ghosts as $gid) {
                    $issueList[] = [
                        'key' => "ghost_{$rpid}_{$gid}",
                        'type' => 'ghost_in_profile',
                        'type_label' => 'Ghost ID in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'phd_id' => $gid,
                        'phd_name' => 'N/A (Deleted)',
                        'phd_title' => 'N/A (Deleted)',
                        'research_area' => '',
                        'thesis_type' => '',
                        'institution_id' => null,
                        'institution_name' => null,
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => null,
                        'phd_status' => null,
                        'suggested_user_id' => $uid,
                        'summary' => "Reviewer profile references PhD ID [{$gid}] that does not exist in the database.",
                        'fix_label' => 'Remove Ghost',
                    ];
                }

                // B) PhDs belonging to this user but missing from ReviewerProfile
                $userPhds = $phdUserMap[$uid] ?? [];
                $missing = array_diff($userPhds, $rpPhds);
                foreach ($missing as $mid) {
                    $pDoc = $phdIdMap[$mid] ?? null;
                    $issueList[] = [
                        'key' => "missing_{$rpid}_{$mid}",
                        'type' => 'missing_in_profile',
                        'type_label' => 'Missing in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'phd_id' => $mid,
                        'phd_name' => (string) ($pDoc->phd_name ?? ''),
                        'phd_title' => (string) ($pDoc->phd_title ?? ''),
                        'research_area' => (string) ($pDoc->research_area ?? ''),
                        'thesis_type' => (string) ($pDoc->thesis_type ?? ''),
                        'institution_id' => $pDoc->institution_id ?? null,
                        'institution_name' => null,
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => $pDoc->status ?? null,
                        'phd_status' => $pDoc->phd_status ?? null,
                        'suggested_user_id' => $uid,
                        'summary' => 'PhD thesis exists for this user, but its ID is missing from their reviewer profile.',
                        'fix_label' => 'Add to Profile',
                    ];
                }
            }

            // 4. PhDs with missing user_id
            foreach ($noUserPhds as $p) {
                $pid = (string) ($p->_id ?? $p->id ?? '');
                $detected = $profilePhdMap[$pid] ?? null;
                $detectedUid = $detected['user_id'] ?? null;
                $detectedRpid = $detected['rp_id'] ?? '';

                $statusVal = $p->status ?? null;
                $phdStatusVal = $p->phd_status ?? null;
                $instVal = $p->institution_id ?? null;

                // If already marked as orphan (no user in profile, status 0, phd_status Completed, institution cleaned)
                if (empty($detectedUid) && ($statusVal === 0 || $statusVal === '0') && $phdStatusVal === 'Completed' && $instVal !== '') {
                    continue;
                }

                $summary = $detectedUid
                    ? "PhD has no user ID. Detected owner from Reviewer Profile: [{$detectedUid}]. Clicking fix will attach user ID, ensure phd_status is Completed, and set status = 1."
                    : 'PhD has no user ID and is not present in any reviewer profile. Clicking fix will set status = 0 (Inactive orphan) and phd_status = Completed.';

                $fixLabel = $detectedUid ? 'Assign User (Status 1)' : 'Mark Orphan (Status 0)';

                $issueList[] = [
                    'key' => "nouser_{$pid}",
                    'type' => 'no_user_id',
                    'type_label' => 'No User ID',
                    'rp_id' => $detectedRpid,
                    'user_id' => '',
                    'phd_id' => $pid,
                    'phd_name' => (string) ($p->phd_name ?? ''),
                    'phd_title' => (string) ($p->phd_title ?? ''),
                    'research_area' => (string) ($p->research_area ?? ''),
                    'thesis_type' => (string) ($p->thesis_type ?? ''),
                    'institution_id' => $p->institution_id ?? null,
                    'institution_name' => null,
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => $p->status ?? null,
                    'phd_status' => $p->phd_status ?? null,
                    'suggested_user_id' => $detectedUid,
                    'summary' => $summary,
                    'fix_label' => $fixLabel,
                ];
            }

            // 5. PhDs with missing status
            foreach ($noStatusPhds as $p) {
                $pid = (string) ($p->_id ?? $p->id ?? '');
                $uid = (string) ($p->user_id ?? '');
                $detected = $profilePhdMap[$pid] ?? null;
                $effectiveUid = $uid ?: ($detected['user_id'] ?? null);

                $summary = ! empty($effectiveUid)
                    ? 'PhD document has no status field. Has user owner; will be set to status: 1 (Active).'
                    : 'PhD document has no status field and no user owner; will be set to status: 0 (Inactive).';

                $fixLabel = ! empty($effectiveUid) ? 'Set Status 1' : 'Set Status 0';

                $issueList[] = [
                    'key' => "nostatus_{$pid}",
                    'type' => 'missing_status',
                    'type_label' => 'Missing Status',
                    'rp_id' => $detected['rp_id'] ?? '',
                    'user_id' => $uid,
                    'phd_id' => $pid,
                    'phd_name' => (string) ($p->phd_name ?? ''),
                    'phd_title' => (string) ($p->phd_title ?? ''),
                    'research_area' => (string) ($p->research_area ?? ''),
                    'thesis_type' => (string) ($p->thesis_type ?? ''),
                    'institution_id' => $p->institution_id ?? null,
                    'institution_name' => null,
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => null,
                    'phd_status' => $p->phd_status ?? null,
                    'suggested_user_id' => $effectiveUid,
                    'summary' => $summary,
                    'fix_label' => $fixLabel,
                ];
            }

            // 6. PhDs with missing phd_status
            foreach ($noPhdStatusPhds as $p) {
                $pid = (string) ($p->_id ?? $p->id ?? '');
                $uid = (string) ($p->user_id ?? '');
                $detected = $profilePhdMap[$pid] ?? null;
                $effectiveUid = $uid ?: ($detected['user_id'] ?? null);

                $issueList[] = [
                    'key' => "nophdstatus_{$pid}",
                    'type' => 'missing_phd_status',
                    'type_label' => 'Missing PhD Status',
                    'rp_id' => $detected['rp_id'] ?? '',
                    'user_id' => $uid,
                    'phd_id' => $pid,
                    'phd_name' => (string) ($p->phd_name ?? ''),
                    'phd_title' => (string) ($p->phd_title ?? ''),
                    'research_area' => (string) ($p->research_area ?? ''),
                    'thesis_type' => (string) ($p->thesis_type ?? ''),
                    'institution_id' => $p->institution_id ?? null,
                    'institution_name' => null,
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => $p->status ?? null,
                    'phd_status' => null,
                    'suggested_user_id' => $effectiveUid,
                    'summary' => 'PhD thesis does not have a completion state (null/empty). Clicking fix will set phd_status = Completed.',
                    'fix_label' => 'Set Completed',
                ];
            }

            // 7. PhDs with non-null empty string institution_id (standardize to null)
            foreach ($noInstPhds as $p) {
                $pid = (string) ($p->_id ?? $p->id ?? '');
                $uid = (string) ($p->user_id ?? '');
                $detected = $profilePhdMap[$pid] ?? null;
                $effectiveUid = $uid ?: ($detected['user_id'] ?? null);

                $issueList[] = [
                    'key' => "noinst_{$pid}",
                    'type' => 'missing_institution',
                    'type_label' => 'Empty Institution',
                    'rp_id' => $detected['rp_id'] ?? '',
                    'user_id' => $uid,
                    'phd_id' => $pid,
                    'phd_name' => (string) ($p->phd_name ?? ''),
                    'phd_title' => (string) ($p->phd_title ?? ''),
                    'research_area' => (string) ($p->research_area ?? ''),
                    'thesis_type' => (string) ($p->thesis_type ?? ''),
                    'institution_id' => '',
                    'institution_name' => null,
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => $p->status ?? null,
                    'phd_status' => $p->phd_status ?? null,
                    'suggested_user_id' => $effectiveUid,
                    'summary' => 'PhD record contains an empty string institution_id. Clicking fix will standardize it to pure NULL.',
                    'fix_label' => 'Clean to NULL',
                ];
            }

            return $issueList;
        });
    }

    /**
     * Decorate ONLY the current page's sliced items with User and Organization records.
     * This avoids fetching and hydrating thousands of models on every request.
     */
    protected function decoratePageItems(array $pageItems): array
    {
        if (empty($pageItems)) {
            return [];
        }

        $userIds = [];
        $orgIds = [];

        foreach ($pageItems as $i) {
            $uid = ! empty($i['user_id']) ? $i['user_id'] : ($i['suggested_user_id'] ?? '');
            if ($uid !== '') {
                $userIds[] = $uid;
            }

            $instId = $i['institution_id'] ?? null;
            if (! empty($instId)) {
                $orgIds[] = (string) $instId;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        $orgIds = array_values(array_unique(array_filter($orgIds)));

        // 1. Fetch Users for page
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

        // 2. Fetch Organizations for page
        $orgSearchKeys = [];
        foreach ($orgIds as $oid) {
            $orgSearchKeys[] = $oid;
            if (strlen($oid) === 24 && ctype_xdigit($oid)) {
                try {
                    $orgSearchKeys[] = new ObjectId($oid);
                } catch (\Exception $e) {
                }
            }
        }

        $orgsMap = ! empty($orgSearchKeys)
            ? Organization::whereIn('_id', $orgSearchKeys)
                ->select(['_id', 'name', 'slug'])
                ->get()
                ->keyBy(fn ($o) => (string) $o->_id)
            : collect();

        foreach ($pageItems as &$item) {
            $effectiveUid = ! empty($item['user_id']) ? $item['user_id'] : ($item['suggested_user_id'] ?? '');
            $u = $usersMap->get($effectiveUid);

            $item['user_name'] = $u ? ($u->name ?? 'User') : null;
            $item['user_email'] = $u ? ($u->email ?? '') : null;
            $item['user_unique_id'] = $u ? ($u->unique_id ?? null) : null;

            $instId = (string) ($item['institution_id'] ?? '');
            $org = $orgsMap->get($instId);
            $item['institution_name'] = $org ? ($org->name ?? 'Organization') : null;
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
                    || str_contains(strtolower($item['phd_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['rp_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['suggested_user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['phd_name'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['phd_title'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['research_area'] ?? ''), $lowerTerm);
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
            'missing_phd_status' => 0,
            'missing_institution' => 0,
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
        Cache::forget('db_fixing_phd_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully fixed issue: {$issue['type_label']} (PhD #{$issue['phd_id']})!";
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

        Cache::forget('db_fixing_phd_issues');
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

        Cache::forget('db_fixing_phd_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully resolved all {$total} issue(s) across PhDs and reviewer profiles.";
    }

    /**
     * Fix all issues in the currently active tab.
     */
    public function fixAllTab(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $allIssues = $this->scanIssues();
        $tabIssues = $this->tab === 'all'
            ? $allIssues
            : array_values(array_filter($allIssues, fn ($i) => $i['type'] === $this->tab));

        $total = count($tabIssues);
        if ($total === 0) {
            $this->statusMessage = "No issues found in category [{$this->tab}].";

            return;
        }

        foreach ($tabIssues as $issue) {
            $this->resolveIssue($issue);
        }

        Cache::forget('db_fixing_phd_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully fixed {$total} issue(s) in category [{$this->tab}].";
    }

    /**
     * Internal resolver for an issue item.
     */
    protected function resolveIssue(array $issue): void
    {
        $type = $issue['type'];
        $phdId = (string) $issue['phd_id'];
        $mongoPhdId = $this->toMongoId($phdId);
        $rpId = (string) ($issue['rp_id'] ?? '');
        $userId = (string) (! empty($issue['user_id']) ? $issue['user_id'] : ($issue['suggested_user_id'] ?? ''));

        switch ($type) {
            case 'missing_in_profile':
                // 1. Add PhD ID to ReviewerProfile->phd array
                if ($userId !== '') {
                    $profile = ReviewerProfile::where(function ($q) use ($rpId, $userId) {
                        if ($rpId !== '') {
                            $q->where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId);
                        } else {
                            $q->where('user_id', $this->toMongoId($userId))->orWhere('user_id', $userId);
                        }
                    })->first();

                    if ($profile) {
                        $currentPhd = is_array($profile->phd ?? null) ? array_map('strval', $profile->phd) : [];
                        if (! in_array($phdId, $currentPhd, true)) {
                            $currentPhd[] = $phdId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'phd' => array_values(array_unique($currentPhd)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    // 2. Ensure PhD has status = 1 (Active), user_id set, and phd_status = Completed if missing
                    $updatePayload = ['user_id' => $userId];
                    $pDoc = DB::connection('mongodb')->table('phd')
                        ->where(function ($q) use ($phdId, $mongoPhdId) {
                            $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                        })->first();

                    $docStatus = $pDoc->status ?? null;
                    if ($pDoc && ($docStatus === null || $docStatus === '' || (int) $docStatus === 0)) {
                        $updatePayload['status'] = 1;
                    }
                    $docPhdStatus = $pDoc->phd_status ?? null;
                    if ($pDoc && (empty($docPhdStatus) || trim((string) $docPhdStatus) === '')) {
                        $updatePayload['phd_status'] = 'Completed';
                    }
                    if ($pDoc && ($pDoc->institution_id ?? null) === '') {
                        $updatePayload['institution_id'] = null;
                    }

                    DB::connection('mongodb')->table('phd')
                        ->where(function ($q) use ($phdId, $mongoPhdId) {
                            $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                        })->update($updatePayload);
                }
                break;

            case 'no_user_id':
                // Determine owner user_id: if not present in issue, look up reviewer_profile that contains this PhD ID
                if ($userId === '' && $phdId !== '') {
                    $ownerProfile = DB::connection('mongodb')->table('reviewer_profile')
                        ->where('phd', $phdId)
                        ->first();
                    if ($ownerProfile && ! empty($ownerProfile->user_id)) {
                        $userId = (string) $ownerProfile->user_id;
                    }
                }

                if ($userId !== '') {
                    // Owner exists: attach user_id and set status = 1 (Active), phd_status = Completed if empty
                    $updatePayload = [
                        'user_id' => $userId,
                        'status' => 1,
                    ];

                    $pDoc = DB::connection('mongodb')->table('phd')
                        ->where(function ($q) use ($phdId, $mongoPhdId) {
                            $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                        })->first();

                    $docPhdStatus = $pDoc->phd_status ?? null;
                    if ($pDoc && (empty($docPhdStatus) || trim((string) $docPhdStatus) === '')) {
                        $updatePayload['phd_status'] = 'Completed';
                    }
                    if ($pDoc && ($pDoc->institution_id ?? null) === '') {
                        $updatePayload['institution_id'] = null;
                    }

                    DB::connection('mongodb')->table('phd')
                        ->where(function ($q) use ($phdId, $mongoPhdId) {
                            $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                        })->update($updatePayload);

                    // Ensure the owner's reviewer profile contains this PhD ID
                    $profile = ReviewerProfile::where('user_id', $userId)
                        ->orWhere('user_id', $this->toMongoId($userId))
                        ->first();

                    if ($profile) {
                        $currentPhd = is_array($profile->phd ?? null) ? array_map('strval', $profile->phd) : [];
                        if (! in_array($phdId, $currentPhd, true)) {
                            $currentPhd[] = $phdId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'phd' => array_values(array_unique($currentPhd)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                } else {
                    // True orphan: not in any profile and no owner: set status = 0 (Inactive orphan)
                    $orphanPayload = ['status' => 0];

                    $pDoc = DB::connection('mongodb')->table('phd')
                        ->where(function ($q) use ($phdId, $mongoPhdId) {
                            $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                        })->first();

                    $docPhdStatus = $pDoc->phd_status ?? null;
                    if ($pDoc && (empty($docPhdStatus) || trim((string) $docPhdStatus) === '')) {
                        $orphanPayload['phd_status'] = 'Completed';
                    }
                    if ($pDoc && ($pDoc->institution_id ?? null) === '') {
                        $orphanPayload['institution_id'] = null;
                    }

                    DB::connection('mongodb')->table('phd')
                        ->where(function ($q) use ($phdId, $mongoPhdId) {
                            $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                        })->update($orphanPayload);
                }
                break;

            case 'ghost_in_profile':
                // Remove ghost ID from ReviewerProfile->phd array
                if ($rpId !== '') {
                    $profile = ReviewerProfile::where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId)->first();
                    if ($profile) {
                        $currentPhd = is_array($profile->phd ?? null) ? array_map('strval', $profile->phd) : [];
                        $cleaned = array_values(array_diff($currentPhd, [$phdId]));
                        DB::connection('mongodb')->table('reviewer_profile')
                            ->where('_id', $profile->_id)
                            ->update([
                                'phd' => $cleaned,
                                'updated_at' => now(),
                            ]);
                    }
                }
                break;

            case 'missing_status':
                // If has user owner (attached or from profile): status = 1. If orphan: status = 0.
                if ($userId === '' && $phdId !== '') {
                    $ownerProfile = DB::connection('mongodb')->table('reviewer_profile')
                        ->where('phd', $phdId)
                        ->first();
                    if ($ownerProfile && ! empty($ownerProfile->user_id)) {
                        $userId = (string) $ownerProfile->user_id;
                    }
                }

                $statVal = ! empty($userId) ? 1 : 0;
                $updatePayload = ['status' => $statVal];

                $pDoc = DB::connection('mongodb')->table('phd')
                    ->where(function ($q) use ($phdId, $mongoPhdId) {
                        $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                    })->first();

                $docPhdStatus = $pDoc->phd_status ?? null;
                if ($pDoc && (empty($docPhdStatus) || trim((string) $docPhdStatus) === '')) {
                    $updatePayload['phd_status'] = 'Completed';
                }
                if ($pDoc && ($pDoc->institution_id ?? null) === '') {
                    $updatePayload['institution_id'] = null;
                }

                DB::connection('mongodb')->table('phd')
                    ->where(function ($q) use ($phdId, $mongoPhdId) {
                        $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                    })->update($updatePayload);
                break;

            case 'missing_phd_status':
                // Set phd_status = Completed
                $updatePayload = ['phd_status' => 'Completed'];

                $pDoc = DB::connection('mongodb')->table('phd')
                    ->where(function ($q) use ($phdId, $mongoPhdId) {
                        $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                    })->first();

                if ($pDoc && ($pDoc->institution_id ?? null) === '') {
                    $updatePayload['institution_id'] = null;
                }

                DB::connection('mongodb')->table('phd')
                    ->where(function ($q) use ($phdId, $mongoPhdId) {
                        $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                    })->update($updatePayload);
                break;

            case 'missing_institution':
                // Standardize empty string institution_id to true null
                DB::connection('mongodb')->table('phd')
                    ->where(function ($q) use ($phdId, $mongoPhdId) {
                        $q->where('_id', $mongoPhdId)->orWhere('_id', $phdId);
                    })->update(['institution_id' => null]);
                break;
        }
    }

    /**
     * Clear issue cache manually and refresh view.
     */
    public function refreshScan(): void
    {
        Cache::forget('db_fixing_phd_issues');
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = 'Refreshed! Fresh scan completed against database.';
    }
}; ?>

<div class="space-y-6">
    {{-- Explainer Accordion --}}
    <div x-data="{ open: false }" class="bg-white dark:bg-zinc-800 rounded-xl shadow-xs border border-zinc-200 dark:border-zinc-700/60 overflow-hidden">
        <button 
            type="button" 
            @click="open = !open" 
            class="w-full px-5 py-3 text-left font-semibold text-xs tracking-wide uppercase flex items-center justify-between transition-colors bg-zinc-50 dark:bg-zinc-800/80 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 text-zinc-700 dark:text-zinc-200"
        >
            <span class="flex items-center gap-2">
                <flux:icon.information-circle class="size-4 text-violet-500" />
                <span>Before You DO Action please read this what is do. (Click me)</span>
            </span>
            <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
        </button>

        <div x-show="open" x-cloak x-collapse class="p-5 border-t border-zinc-200 dark:border-zinc-700 text-sm text-zinc-600 dark:text-zinc-300 space-y-4 bg-white dark:bg-zinc-800">
            <div>
                <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-base mb-1">What does the PhD & Reviewer Profile Synchronizer do?</h4>
                <p>
                    This tool detects and fixes all structural inconsistencies between the <code>phd</code> collection and <code>reviewer_profile.phd</code> arrays in MongoDB.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="p-3.5 rounded-lg border border-violet-200 bg-violet-50/50 dark:border-violet-900/40 dark:bg-violet-950/20">
                    <span class="font-bold text-violet-800 dark:text-violet-300 block mb-1">1. No User ID Assignment</span>
                    If a PhD is linked to a reviewer profile, its owner <code>user_id</code> is retrieved from that profile, attached to the PhD document, and set to <code>status = 1</code> (Active with User). If it is a true orphan with no profile link, it is set to <code>status = 0</code> (Inactive orphan).
                </div>

                <div class="p-3.5 rounded-lg border border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20">
                    <span class="font-bold text-amber-800 dark:text-amber-300 block mb-1">2. Missing in Reviewer Profile</span>
                    When a PhD record contains a valid <code>user_id</code>, but the PhD ID is missing from that user's <code>reviewer_profile.phd</code> array, clicking fix safely appends the ID to their profile array.
                </div>

                <div class="p-3.5 rounded-lg border border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20">
                    <span class="font-bold text-rose-800 dark:text-rose-300 block mb-1">3. Ghost IDs Removal</span>
                    When a Reviewer Profile references a PhD ID in its array that was deleted or never existed in the <code>phd</code> collection, clicking fix purges the ghost ID from the profile array.
                </div>

                <div class="p-3.5 rounded-lg border border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <span class="font-bold text-emerald-800 dark:text-emerald-300 block mb-1">4. Missing Status & PhD Status</span>
                    Standardizes PhD records missing status (integer <code>1</code> for active scholar records, <code>0</code> for orphans). For records with missing <code>phd_status</code>, it safely standardizes them to <code>Completed</code>. For missing <code>institution_id</code>, it safely standardizes them to pure <code>NULL</code>.
                </div>
            </div>
        </div>
    </div>

    {{-- Hero Summary Banner (High-contrast Indigo Theme) --}}
    <div 
        class="rounded-2xl p-6 sm:p-8 text-white relative overflow-hidden shadow-xl"
        style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%); color: #ffffff;"
    >
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-3 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-white/15 backdrop-blur-md border border-white/20">
                        <flux:icon.academic-cap class="size-7 text-amber-300" />
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-white drop-shadow-sm">Reviewer Profile & PhD Synchronizer</h2>
                        <p class="text-xs sm:text-sm text-indigo-100 font-medium">Database integrity, relationship sync, and status standardizer</p>
                    </div>
                </div>
                <p class="text-xs sm:text-sm text-indigo-100/90 leading-relaxed">
                    Fixes missing links between PhD theses and Reviewer Profiles. Restores owner user IDs from profile PhD arrays, adds unlinked PhDs to profiles, purges ghost IDs, standardizes missing <code>phd_status</code> to <strong>Completed</strong>, cleans empty institutions to <strong>NULL</strong>, and normalizes status (<span class="font-mono bg-white/20 px-1 py-0.5 rounded text-white font-bold">1</span> for Active with User, <span class="font-mono bg-white/20 px-1 py-0.5 rounded text-white font-bold">0</span> for Inactive orphan).
                </p>
                <div class="flex flex-wrap items-center gap-2 pt-1 text-xs">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-semibold" style="background-color: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3);">
                        <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Status: {{ $statusMessage }}
                    </span>
                    <button 
                        type="button" 
                        wire:click="refreshScan" 
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold cursor-pointer transition-all hover:bg-white/25 active:scale-95"
                        style="background-color: rgba(255, 255, 255, 0.15); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.25);"
                    >
                        <flux:icon.arrow-path class="size-3.5" wire:loading.class="animate-spin" />
                        Scan & Refresh
                    </button>
                    @if ($totalIssues > 0)
                        <button 
                            type="button" 
                            wire:click="fixAll" 
                            wire:confirm="Are you sure you want to resolve all {{ $totalIssues }} issues across PhDs and Reviewer Profiles?"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-bold shadow-md cursor-pointer transition-all hover:brightness-110 active:scale-95"
                            style="background-color: #10b981; color: #ffffff; border: 1px solid #059669;"
                        >
                            <flux:icon.bolt class="size-3.5 text-white" />
                            Fix All ({{ $totalIssues }}) Issues
                        </button>
                    @endif
                </div>
            </div>

            {{-- Stat counter cards with high contrast --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-3 w-full lg:w-auto">
                <div 
                    class="rounded-xl p-3 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['no_user_id']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-indigo-200 mt-0.5">No User ID</div>
                </div>
                <div 
                    class="rounded-xl p-3 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['missing_phd_status']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-indigo-200 mt-0.5">No PhD Status</div>
                </div>
                <div 
                    class="rounded-xl p-3 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['missing_in_profile']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-indigo-200 mt-0.5">Missing In Profile</div>
                </div>
                <div 
                    class="rounded-xl p-3 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['ghost_in_profile']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-indigo-200 mt-0.5">Ghost IDs</div>
                </div>
                <div 
                    class="rounded-xl p-3 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['missing_status']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-indigo-200 mt-0.5">No Status</div>
                </div>
                <div 
                    class="rounded-xl p-3 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['missing_institution']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-indigo-200 mt-0.5">Empty Inst.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Navigation & Controls --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl p-4 shadow-xs border border-zinc-200 dark:border-zinc-700/60 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            {{-- Tabs --}}
            <div class="flex flex-wrap items-center gap-1.5">
                <button 
                    type="button" 
                    wire:click="setTab('all')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    All Issues
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'all' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['all'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('no_user_id')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'no_user_id' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    No User ID
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'no_user_id' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['no_user_id'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('missing_phd_status')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'missing_phd_status' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    No PhD Status
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'missing_phd_status' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['missing_phd_status'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('missing_in_profile')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'missing_in_profile' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    Missing in Profile
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'missing_in_profile' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['missing_in_profile'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('ghost_in_profile')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'ghost_in_profile' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    Ghost IDs
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'ghost_in_profile' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['ghost_in_profile'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('missing_status')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'missing_status' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    Missing Status
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'missing_status' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['missing_status'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('missing_institution')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'missing_institution' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    Empty Inst.
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'missing_institution' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['missing_institution'] }}</span>
                </button>
            </div>

            {{-- Search Input --}}
            <div class="relative w-full sm:w-64">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search PhD, User, ID..." 
                    icon="magnifying-glass"
                    clearable
                    size="sm"
                />
            </div>
        </div>

        {{-- Bulk Actions Bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-700/60">
            <div class="flex items-center gap-2">
                <button 
                    type="button" 
                    wire:click="toggleSelectAll" 
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 transition-colors"
                >
                    {{ $selectAll ? 'Deselect Page' : 'Select Page' }}
                </button>

                @if (count($selectedRows) > 0)
                    <button 
                        type="button" 
                        wire:click="fixSelected" 
                        wire:loading.attr="disabled"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                    >
                        <flux:icon.bolt class="size-3.5" />
                        Fix Selected ({{ count($selectedRows) }})
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if (($counts[$tab] ?? 0) > 0)
                    <button 
                        type="button" 
                        wire:click="fixAllTab" 
                        wire:confirm="Are you sure you want to fix all {{ $counts[$tab] }} issues in this category ({{ $tab }})?"
                        wire:loading.attr="disabled"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                    >
                        <flux:icon.check-circle class="size-3.5" />
                        Fix All in Tab ({{ $counts[$tab] }})
                    </button>
                @endif
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
                </span>
            </div>
        </div>
    </div>

    {{-- Main Issues Table --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xs border border-zinc-200 dark:border-zinc-700/60 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-semibold">
                    <tr>
                        <th class="w-10 px-4 py-3 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll" 
                                @checked($selectAll) 
                                class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 size-4 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3">PhD / Thesis Title & Topic</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">PhD Status</th>
                        <th class="px-4 py-3">Institution</th>
                        <th class="px-4 py-3">Owner User & Profile</th>
                        <th class="px-4 py-3">Issue Detected</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-700 dark:text-zinc-200">
                    @forelse ($issues as $item)
                        <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-700/30 transition-colors {{ in_array($item['key'], $selectedRows, true) ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : '' }}">
                            {{-- Checkbox --}}
                            <td class="px-4 py-3 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedRows" 
                                    value="{{ $item['key'] }}" 
                                    class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 size-4 cursor-pointer"
                                />
                            </td>

                            {{-- PhD Info --}}
                            <td class="px-4 py-3 max-w-xs">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 truncate text-sm" title="{{ $item['phd_title'] }}">
                                    {{ !empty($item['phd_title']) ? $item['phd_title'] : 'Untitled PhD' }}
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-2 mt-0.5">
                                    @if (!empty($item['phd_name']))
                                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $item['phd_name'] }}</span>
                                    @endif
                                    @if (!empty($item['research_area']))
                                        <span class="truncate max-w-[150px]" title="{{ $item['research_area'] }}">
                                            • {{ $item['research_area'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                    ID: {{ $item['phd_id'] }}
                                </div>
                            </td>

                            {{-- Record Status --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($item['status'] === null || $item['status'] === '')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
                                        Missing
                                    </span>
                                @elseif (in_array($item['status'], [1, '1'], true))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                                        1 (Active)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">
                                        0 (Inactive)
                                    </span>
                                @endif
                            </td>

                            {{-- PhD Status --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (empty($item['phd_status']))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                        Missing
                                    </span>
                                @elseif ($item['phd_status'] === 'Completed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                                        Completed
                                    </span>
                                @elseif ($item['phd_status'] === 'Ongoing')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300">
                                        Ongoing
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ $item['phd_status'] }}
                                    </span>
                                @endif
                            </td>

                            {{-- Institution --}}
                            <td class="px-4 py-3 max-w-[180px]">
                                @if (!empty($item['institution_name']))
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 truncate" title="{{ $item['institution_name'] }}">
                                        {{ $item['institution_name'] }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-mono truncate">
                                        {{ $item['institution_id'] }}
                                    </div>
                                @elseif (!empty($item['institution_id']))
                                    <span class="text-zinc-500 font-mono text-[11px] truncate block" title="{{ $item['institution_id'] }}">
                                        ID: {{ $item['institution_id'] }}
                                    </span>
                                @else
                                    <span class="text-zinc-400 italic text-[11px]">None (NULL)</span>
                                @endif
                            </td>

                            {{-- Owner User & Profile --}}
                            <td class="px-4 py-3 max-w-[200px]">
                                @if (!empty($item['user_name']))
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ $item['user_name'] }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                        {{ $item['user_email'] }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-mono">
                                        User: {{ $item['user_id'] ?: $item['suggested_user_id'] }}
                                    </div>
                                @elseif (!empty($item['user_id']) || !empty($item['suggested_user_id']))
                                    <span class="font-mono text-zinc-600 dark:text-zinc-400 text-[11px] block truncate">
                                        User: {{ $item['user_id'] ?: $item['suggested_user_id'] }}
                                    </span>
                                    @if (!empty($item['suggested_user_id']) && empty($item['user_id']))
                                        <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold">
                                            (Detected via Profile)
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                        No Owner
                                    </span>
                                @endif

                                @if (!empty($item['rp_id']))
                                    <div class="text-[10px] text-zinc-400 font-mono mt-0.5 truncate" title="Profile ID: {{ $item['rp_id'] }}">
                                        Profile: {{ $item['rp_id'] }}
                                    </div>
                                @endif
                            </td>

                            {{-- Issue Detected --}}
                            <td class="px-4 py-3 max-w-xs">
                                <div class="mb-1">
                                    @if ($item['type'] === 'no_user_id')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @elseif ($item['type'] === 'missing_in_profile')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @elseif ($item['type'] === 'ghost_in_profile')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @elseif ($item['type'] === 'missing_phd_status')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @elseif ($item['type'] === 'missing_institution')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-zinc-600 dark:text-zinc-400 leading-tight">
                                    {{ $item['summary'] }}
                                </p>
                            </td>

                            {{-- Action --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $item['key'] }}')" 
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-md text-xs font-semibold cursor-pointer transition-all shadow-2xs hover:brightness-105 active:scale-95 {{ $item['type'] === 'ghost_in_profile' ? 'bg-rose-600 hover:bg-rose-700 text-white' : ($item['type'] === 'no_user_id' && empty($item['suggested_user_id']) ? 'bg-zinc-600 hover:bg-zinc-700 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white') }}"
                                >
                                    {{ $item['fix_label'] }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <flux:icon.check-badge class="size-10 text-emerald-500 mx-auto" />
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">No Issues Found</div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        All PhD thesis records and reviewer profiles are consistent in this category.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($paginator->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>

