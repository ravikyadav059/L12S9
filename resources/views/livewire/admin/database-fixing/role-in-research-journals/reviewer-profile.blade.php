<?php

use App\Models\JournalRole;
use App\Models\ReviewerJournal;
use App\Models\ReviewerProfile;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('RoleInResearchJournals — Reviewer Profile & User ID Sync - Admin')] class extends Component {
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
     * Scan and build the full list of data integrity issues for RoleInResearchJournals:
     * 1. No User ID: record has no user_id (recoverable from reviewer_profile.RoleInResearchJournal or true orphan)
     * 2. Missing in Profile: record has user_id, but ID is missing from user's reviewer_profile.RoleInResearchJournal
     * 3. Ghost in Profile: reviewer_profile.RoleInResearchJournal contains ID that does not exist in role_in_research_journals
     * 4. Missing Status: record has null or empty status
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_role_journal_profile_issues', 60, function () {
            $allRoles = DB::connection('mongodb')->table('role_in_research_journals')
                ->get([
                    '_id',
                    'role',
                    'journal_title',
                    'journal_short_name',
                    'e_issn',
                    'p_issn',
                    'organization_name',
                    'start_month_year',
                    'end_month_year',
                    'user_id',
                    'status',
                ]);

            $roleIdMap = [];
            $roleUserMap = [];
            $noUserRoles = [];
            $noStatusRoles = [];

            foreach ($allRoles as $r) {
                $rawId = $r->_id ?? $r->id ?? null;
                $rid = (string) $rawId;
                $roleIdMap[$rid] = $r;

                $hasUser = ! empty($r->user_id);
                if ($hasUser) {
                    $roleUserMap[(string) $r->user_id][] = $rid;
                } else {
                    $noUserRoles[] = $r;
                }

                if (! isset($r->status) || $r->status === null || $r->status === '') {
                    $noStatusRoles[] = $r;
                }
            }

            // Fetch ReviewerProfiles with user_id
            $profiles = DB::connection('mongodb')->table('reviewer_profile')
                ->whereNotNull('user_id')
                ->get(['_id', 'user_id', 'RoleInResearchJournal']);

            $profileRoleMap = [];
            foreach ($profiles as $p) {
                $uid = (string) $p->user_id;
                $rawId = $p->_id ?? $p->id ?? null;
                $rpid = (string) $rawId;
                $rpIds = is_array($p->RoleInResearchJournal ?? null) ? array_map('strval', $p->RoleInResearchJournal) : [];

                foreach ($rpIds as $rid) {
                    if ($rid !== '' && ! isset($profileRoleMap[$rid])) {
                        $profileRoleMap[$rid] = [
                            'user_id' => $uid,
                            'rp_id' => $rpid,
                        ];
                    }
                }
            }

            $stringify = function ($val, string $fallback = ''): string {
                if (is_array($val)) {
                    return implode(', ', array_filter(array_map('strval', $val)));
                }

                return (string) ($val ?? $fallback);
            };

            $issueList = [];

            // 3. Scan ReviewerProfiles: Ghost IDs and Missing in Profile
            foreach ($profiles as $p) {
                $uid = (string) $p->user_id;
                $rawId = $p->_id ?? $p->id ?? null;
                $rpid = (string) $rawId;
                $rpIds = array_values(array_filter(is_array($p->RoleInResearchJournal ?? null) ? array_map('strval', $p->RoleInResearchJournal) : []));

                // A) Ghost IDs in ReviewerProfile
                $ghosts = array_diff($rpIds, array_keys($roleIdMap));
                foreach ($ghosts as $gid) {
                    $issueList[] = [
                        'key' => "ghost_{$rpid}_{$gid}",
                        'type' => 'ghost_in_profile',
                        'type_label' => 'Ghost ID in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'role_record_id' => $gid,
                        'role_raw' => '',
                        'role_name' => 'N/A (Deleted Record)',
                        'journal_raw' => '',
                        'journal_name' => 'Deleted Journal Role',
                        'e_issn' => '',
                        'p_issn' => '',
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => null,
                        'suggested_user_id' => $uid,
                        'summary' => "Reviewer profile references RoleInResearchJournal ID [{$gid}] that does not exist in role_in_research_journals.",
                        'fix_label' => 'Remove Ghost',
                    ];
                }

                // B) Records belonging to this user but missing from ReviewerProfile
                $userRoles = $roleUserMap[$uid] ?? [];
                $missing = array_diff($userRoles, $rpIds);
                foreach ($missing as $mid) {
                    $doc = $roleIdMap[$mid] ?? null;
                    $issueList[] = [
                        'key' => "missing_{$rpid}_{$mid}",
                        'type' => 'missing_in_profile',
                        'type_label' => 'Missing in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'role_record_id' => $mid,
                        'role_raw' => $stringify($doc->role ?? null, ''),
                        'role_name' => null,
                        'journal_raw' => $stringify($doc->journal_title ?? null, ''),
                        'journal_name' => null,
                        'e_issn' => $stringify($doc->e_issn ?? null, ''),
                        'p_issn' => $stringify($doc->p_issn ?? null, ''),
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => $doc->status ?? null,
                        'suggested_user_id' => $uid,
                        'summary' => 'Record has user_id saved, but its ID is missing from reviewer_profile.RoleInResearchJournal.',
                        'fix_label' => 'Add to Profile',
                    ];
                }
            }

            // 4. Records with missing user_id
            foreach ($noUserRoles as $r) {
                $rid = (string) ($r->_id ?? $r->id ?? '');
                $detected = $profileRoleMap[$rid] ?? null;
                $detectedUid = $detected['user_id'] ?? null;
                $detectedRpid = $detected['rp_id'] ?? '';

                $statusVal = $r->status ?? null;
                if (empty($detectedUid) && ($statusVal === 0 || $statusVal === '0')) {
                    continue;
                }

                $summary = $detectedUid
                    ? "Record has no user_id saved. Found in reviewer_profile.RoleInResearchJournal for User [{$detectedUid}]. Clicking fix attaches user_id and sets status = 1."
                    : 'Record has no user_id and is not referenced in any reviewer profile. Clicking fix will set status = 0 (Inactive orphan).';

                $fixLabel = $detectedUid ? 'Assign User (Status 1)' : 'Mark Orphan (Status 0)';

                $issueList[] = [
                    'key' => "nouser_{$rid}",
                    'type' => 'no_user_id',
                    'type_label' => 'No User ID',
                    'rp_id' => $detectedRpid,
                    'user_id' => '',
                    'role_record_id' => $rid,
                    'role_raw' => $stringify($r->role ?? null, ''),
                    'role_name' => null,
                    'journal_raw' => $stringify($r->journal_title ?? null, ''),
                    'journal_name' => null,
                    'e_issn' => $stringify($r->e_issn ?? null, ''),
                    'p_issn' => $stringify($r->p_issn ?? null, ''),
                    'user_name' => null,
                    'user_email' => null,
                    'user_unique_id' => null,
                    'status' => $r->status ?? null,
                    'suggested_user_id' => $detectedUid,
                    'summary' => $summary,
                    'fix_label' => $fixLabel,
                ];
            }

            // 5. Records with missing status
            foreach ($noStatusRoles as $r) {
                $rid = (string) ($r->_id ?? $r->id ?? '');
                $uid = (string) ($r->user_id ?? '');
                $detected = $profileRoleMap[$rid] ?? null;
                $effectiveUid = $uid ?: ($detected['user_id'] ?? null);

                $summary = ! empty($effectiveUid)
                    ? 'Record has no status field. Has owner; will be set to status: 1 (Active).'
                    : 'Record has no status field and no owner; will be set to status: 0 (Inactive).';

                $issueList[] = [
                    'key' => "nostatus_{$rid}",
                    'type' => 'missing_status',
                    'type_label' => 'Missing Status',
                    'rp_id' => $detected['rp_id'] ?? '',
                    'user_id' => $uid,
                    'role_record_id' => $rid,
                    'role_raw' => $stringify($r->role ?? null, ''),
                    'role_name' => null,
                    'journal_raw' => $stringify($r->journal_title ?? null, ''),
                    'journal_name' => null,
                    'e_issn' => $stringify($r->e_issn ?? null, ''),
                    'p_issn' => $stringify($r->p_issn ?? null, ''),
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
     * Decorate current page items with User, JournalRole, and ReviewerJournal labels.
     */
    protected function decoratePageItems(array $pageItems): array
    {
        if (empty($pageItems)) {
            return [];
        }

        $userIds = [];
        $journalIds = [];

        foreach ($pageItems as $i) {
            $uid = (string) (! empty($i['user_id']) ? $i['user_id'] : ($i['suggested_user_id'] ?? ''));
            if ($uid !== '') {
                $userIds[] = $uid;
            }

            $jid = (string) ($i['journal_raw'] ?? '');
            if (strlen($jid) === 24 && ctype_xdigit($jid)) {
                $journalIds[] = $jid;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        $journalIds = array_values(array_unique(array_filter($journalIds)));

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

        $journalSearchKeys = [];
        foreach ($journalIds as $jid) {
            $journalSearchKeys[] = $jid;
            try {
                $journalSearchKeys[] = new ObjectId($jid);
            } catch (\Exception $e) {
            }
        }

        $usersMap = ! empty($userSearchKeys)
            ? User::whereIn('_id', $userSearchKeys)
                ->select(['_id', 'name', 'email', 'unique_id'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        $journalsMap = ! empty($journalSearchKeys)
            ? ReviewerJournal::whereIn('_id', $journalSearchKeys)
                ->select(['_id', 'journal_title'])
                ->get()
                ->keyBy(fn ($j) => (string) $j->_id)
            : collect();

        $journalRolesMap = collect(JournalRole::getCachedActive())->keyBy('id');

        foreach ($pageItems as &$item) {
            $uid = (string) (! empty($item['user_id']) ? $item['user_id'] : ($item['suggested_user_id'] ?? ''));
            $u = $usersMap->get($uid);

            $item['user_name'] = $u ? ($u->name ?? 'User') : null;
            $item['user_email'] = $u ? ($u->email ?? '') : null;
            $item['user_unique_id'] = $u ? ($u->unique_id ?? null) : null;

            if ($item['type'] !== 'ghost_in_profile') {
                $rRaw = (string) ($item['role_raw'] ?? '');
                $item['role_name'] = $journalRolesMap->has($rRaw)
                    ? (string) ($journalRolesMap[$rRaw]['name'] ?? $rRaw)
                    : ($rRaw !== '' ? $rRaw : 'No Role');

                $jRaw = (string) ($item['journal_raw'] ?? '');
                $jDoc = $journalsMap->get($jRaw);
                $item['journal_name'] = $jDoc
                    ? (string) ($jDoc->journal_title ?? 'Journal')
                    : ($jRaw !== '' ? $jRaw : 'Unknown Journal');
            }
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

        if ($this->tab !== 'all') {
            $allIssues = array_values(array_filter($allIssues, fn ($item) => $item['type'] === $this->tab));
        }

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
                    || str_contains(strtolower($item['role_record_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['rp_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['suggested_user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['role_raw'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['journal_raw'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['e_issn'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['p_issn'] ?? ''), $lowerTerm);
            }));
        }

        return $allIssues;
    }

    public function fixSingle(string $key): void
    {
        $allIssues = $this->scanIssues();
        $issue = collect($allIssues)->firstWhere('key', $key);

        if (! $issue) {
            $this->statusMessage = "Issue [{$key}] was not found or already resolved.";

            return;
        }

        $this->resolveIssue($issue);
        Cache::forget('db_fixing_role_journal_profile_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully fixed issue: {$issue['type_label']} (Record #{$issue['role_record_id']})!";
    }

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
            $issue = $issuesMap->get($key);
            if ($issue) {
                $this->resolveIssue($issue);
                $count++;
            }
        }

        Cache::forget('db_fixing_role_journal_profile_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully resolved {$count} selected issue(s)!";
    }

    public function fixAll(): void
    {
        $allIssues = $this->scanIssues();
        $total = count($allIssues);

        if ($total === 0) {
            $this->statusMessage = 'All RoleInResearchJournals records are already in perfect sync!';

            return;
        }

        foreach ($allIssues as $issue) {
            $this->resolveIssue($issue);
        }

        Cache::forget('db_fixing_role_journal_profile_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully resolved all {$total} issue(s) across role_in_research_journals and reviewer profiles.";
    }

    protected function resolveIssue(array $issue): void
    {
        $type = $issue['type'];
        $recordId = (string) $issue['role_record_id'];
        $mongoRecordId = $this->toMongoId($recordId);
        $rpId = (string) ($issue['rp_id'] ?? '');
        $userId = (string) (! empty($issue['user_id']) ? $issue['user_id'] : ($issue['suggested_user_id'] ?? ''));

        switch ($type) {
            case 'missing_in_profile':
                if ($userId !== '') {
                    $profile = ReviewerProfile::where(function ($q) use ($rpId, $userId) {
                        if ($rpId !== '') {
                            $q->where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId);
                        } else {
                            $q->where('user_id', $this->toMongoId($userId))->orWhere('user_id', $userId);
                        }
                    })->first();

                    if ($profile) {
                        $currentIds = array_values(array_filter(is_array($profile->RoleInResearchJournal ?? null) ? array_map('strval', $profile->RoleInResearchJournal) : []));
                        if (! in_array($recordId, $currentIds, true)) {
                            $currentIds[] = $recordId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'RoleInResearchJournal' => array_values(array_unique($currentIds)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    $doc = DB::connection('mongodb')->table('role_in_research_journals')
                        ->where(function ($q) use ($recordId, $mongoRecordId) {
                            $q->where('_id', $mongoRecordId)->orWhere('_id', $recordId);
                        })->first();

                    $updatePayload = ['user_id' => $userId];
                    if ($doc && (! isset($doc->status) || $doc->status === null || $doc->status === '' || (int) $doc->status === 0)) {
                        $updatePayload['status'] = 1;
                    }

                    DB::connection('mongodb')->table('role_in_research_journals')
                        ->where(function ($q) use ($recordId, $mongoRecordId) {
                            $q->where('_id', $mongoRecordId)->orWhere('_id', $recordId);
                        })->update($updatePayload);

                    RoleInResearchJournals::forgetUserCache($userId);
                }
                break;

            case 'no_user_id':
                if ($userId === '' && $recordId !== '') {
                    $ownerProfile = DB::connection('mongodb')->table('reviewer_profile')
                        ->where('RoleInResearchJournal', $recordId)
                        ->first();
                    if ($ownerProfile && ! empty($ownerProfile->user_id)) {
                        $userId = (string) $ownerProfile->user_id;
                    }
                }

                if ($userId !== '') {
                    DB::connection('mongodb')->table('role_in_research_journals')
                        ->where(function ($q) use ($recordId, $mongoRecordId) {
                            $q->where('_id', $mongoRecordId)->orWhere('_id', $recordId);
                        })->update([
                            'user_id' => $userId,
                            'status' => 1,
                        ]);

                    $profile = ReviewerProfile::where('user_id', $userId)
                        ->orWhere('user_id', $this->toMongoId($userId))
                        ->first();

                    if ($profile) {
                        $currentIds = array_values(array_filter(is_array($profile->RoleInResearchJournal ?? null) ? array_map('strval', $profile->RoleInResearchJournal) : []));
                        if (! in_array($recordId, $currentIds, true)) {
                            $currentIds[] = $recordId;
                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'RoleInResearchJournal' => array_values(array_unique($currentIds)),
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    RoleInResearchJournals::forgetUserCache($userId);
                } else {
                    DB::connection('mongodb')->table('role_in_research_journals')
                        ->where(function ($q) use ($recordId, $mongoRecordId) {
                            $q->where('_id', $mongoRecordId)->orWhere('_id', $recordId);
                        })->update(['status' => 0]);
                }
                break;

            case 'ghost_in_profile':
                if ($rpId !== '') {
                    $profile = ReviewerProfile::where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId)->first();
                    if ($profile) {
                        $currentIds = array_values(array_filter(is_array($profile->RoleInResearchJournal ?? null) ? array_map('strval', $profile->RoleInResearchJournal) : []));
                        $cleaned = array_values(array_diff($currentIds, [$recordId]));
                        DB::connection('mongodb')->table('reviewer_profile')
                            ->where('_id', $profile->_id)
                            ->update([
                                'RoleInResearchJournal' => $cleaned,
                                'updated_at' => now(),
                            ]);

                        if (! empty($profile->user_id)) {
                            RoleInResearchJournals::forgetUserCache((string) $profile->user_id);
                        }
                    }
                }
                break;

            case 'missing_status':
                $doc = DB::connection('mongodb')->table('role_in_research_journals')
                    ->where(function ($q) use ($recordId, $mongoRecordId) {
                        $q->where('_id', $mongoRecordId)->orWhere('_id', $recordId);
                    })->first();

                $ownerUid = (string) ($doc->user_id ?? $userId);
                $newStatus = ! empty($ownerUid) ? 1 : 0;

                DB::connection('mongodb')->table('role_in_research_journals')
                    ->where(function ($q) use ($recordId, $mongoRecordId) {
                        $q->where('_id', $mongoRecordId)->orWhere('_id', $recordId);
                    })->update(['status' => $newStatus]);

                if (! empty($ownerUid)) {
                    RoleInResearchJournals::forgetUserCache($ownerUid);
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
            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">RoleInResearchJournals</span>
        </div>

        <!-- Tool Switcher Pills -->
        <div class="inline-flex rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1">
            <a href="{{ route('admin.database-fixing.role-in-research-journals.role') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
                <flux:icon.tag class="size-3.5" />
                <span>Role ObjectId Fixer</span>
            </a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md bg-white dark:bg-zinc-900 text-purple-700 dark:text-purple-300 shadow-xs">
                <flux:icon.user-group class="size-3.5" />
                <span>Reviewer Profile & User Sync</span>
            </span>
        </div>
    </div>

    <!-- Page Header & Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center size-8 rounded-lg bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400">
                    <flux:icon.user-group class="size-4" />
                </span>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                    RoleInResearchJournals — Reviewer Profile & User ID Sync
                </h1>
            </div>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Synchronizes <code>role_in_research_journals</code> records with <code>reviewer_profile.RoleInResearchJournal</code> arrays, recovers missing <code>user_id</code> values, purges ghost IDs, and standardizes statuses.
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
    <div x-data="{ open: false }" class="rounded-xl border border-purple-200/70 dark:border-purple-900/40 bg-purple-50/40 dark:bg-purple-950/20 p-4">
        <button @click="open = !open" type="button" class="w-full flex items-center justify-between text-left">
            <div class="flex items-center gap-2">
                <flux:icon.information-circle class="size-4 text-purple-600 dark:text-purple-400" />
                <span class="text-sm font-semibold text-purple-950 dark:text-purple-200">
                    What does this synchronization tool do? (Plain English Summary)
                </span>
            </div>
            <flux:icon.chevron-down class="size-4 text-purple-600 dark:text-purple-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
        </button>

        <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-purple-200/50 dark:border-purple-900/30 text-xs text-zinc-600 dark:text-zinc-300 space-y-2">
            <p>
                User and Admin profiles fetch Role in Research Journals entries strictly from <strong><code>reviewer_profile.RoleInResearchJournal</code></strong>. This tool keeps that array and the <strong><code>role_in_research_journals</code></strong> collection in 100% bidirectional sync:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Recover Missing User IDs:</strong> Records missing <code>user_id</code> whose ID exists in <code>reviewer_profile.RoleInResearchJournal</code> get their owner <code>user_id</code> attached and <code>status = 1</code>.</li>
                <li><strong>Mark Inactive Orphans:</strong> True orphan records without a user owner or profile link get <code>status = 0</code>.</li>
                <li><strong>Add Missing Profile IDs:</strong> Ensures records owned by a user are saved inside their <code>reviewer_profile.RoleInResearchJournal</code> array.</li>
                <li><strong>Remove Ghost IDs:</strong> Purges deleted or non-existent record IDs from <code>reviewer_profile.RoleInResearchJournal</code>.</li>
            </ul>
        </div>
    </div>

    <!-- Diagnostic Metrics Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Issues</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($totalIssues) }}</span>
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/30 dark:bg-amber-950/20">
            <span class="text-xs font-medium text-amber-700 dark:text-amber-400">Missing User ID</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-amber-900 dark:text-amber-100">{{ number_format($counts['no_user_id']) }}</span>
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/30 dark:bg-blue-950/20">
            <span class="text-xs font-medium text-blue-700 dark:text-blue-400">Missing in Profile</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ number_format($counts['missing_in_profile']) }}</span>
            </div>
        </div>

        <div class="p-3.5 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/30 dark:bg-red-950/20">
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
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search ID, journal, ISSN, user..." size="sm" icon="magnifying-glass" clearable />
            </div>

            @if ($totalIssues > 0)
                <button wire:click="fixAll" wire:confirm="Are you sure you want to resolve all {{ $totalIssues }} RoleInResearchJournals synchronization issues across the database?" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <flux:icon.arrow-path class="size-3.5" wire:loading.class="animate-spin" wire:target="fixAll" />
                    <span>Fix All ({{ $totalIssues }})</span>
                </button>
            @endif

            @if (!empty($selectedRows))
                <button wire:click="fixSelected" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold shadow-xs transition-colors">
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
                            <input type="checkbox" wire:click="toggleSelectAll" @checked($selectAll) class="rounded border-zinc-300 dark:border-zinc-700 text-purple-600 focus:ring-purple-500 size-3.5" />
                        </th>
                        <th class="px-3 py-2.5 font-semibold">Issue Type</th>
                        <th class="px-3 py-2.5 font-semibold">Journal & Role Details</th>
                        <th class="px-3 py-2.5 font-semibold">Attached User / Owner</th>
                        <th class="px-3 py-2.5 font-semibold">Problem Diagnostics</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:border-zinc-800">
                    @forelse ($issues as $item)
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors {{ in_array($item['key'], $selectedRows) ? 'bg-purple-50/30 dark:bg-purple-950/20' : '' }}">
                            <!-- Checkbox -->
                            <td class="px-3 py-3">
                                <input type="checkbox" wire:model.live="selectedRows" value="{{ $item['key'] }}" class="rounded border-zinc-300 dark:border-zinc-700 text-purple-600 focus:ring-purple-500 size-3.5" />
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

                            <!-- Journal & Role Details -->
                            <td class="px-3 py-3 max-w-[320px]">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100 line-clamp-1" title="{{ $item['journal_name'] }}">
                                    {{ $item['journal_name'] }}
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                                    <span class="text-purple-700 dark:text-purple-300 font-semibold">{{ $item['role_name'] }}</span>
                                    @if ($item['e_issn'])
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                        <span class="font-mono">e-ISSN: {{ $item['e_issn'] }}</span>
                                    @endif
                                    @if ($item['p_issn'])
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                        <span class="font-mono">p-ISSN: {{ $item['p_issn'] }}</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                    Record ID: {{ $item['role_record_id'] }}
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
                                        All RoleInResearchJournals In Perfect Sync
                                    </h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">
                                        Every record has its owner assigned, reviewer profiles contain no ghost IDs, and all statuses are standardized.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($paginator->hasPages())
            <div class="border-t border-zinc-200 dark:border-zinc-800 px-4 py-3">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>

