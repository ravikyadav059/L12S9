<?php

use App\Models\Publication;
use App\Models\ReviewerJournal;
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

new #[Layout('components.layouts.admin')] #[Title('Publication — Reviewer Profile & User/Co-Author Sync - Admin')] class extends Component {
    use WithPagination;

    public string $tab = 'all'; // 'all', 'missing_in_profile', 'ghost_in_profile', 'no_user_id', 'missing_status'

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
     * Extract clean string User IDs from registered_co_author (array or legacy comma string).
     *
     * @return list<string>
     */
    protected function extractCoAuthorIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), $raw))));
        }

        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
        }

        return [];
    }

    /**
     * Scan and build the full list of reviewer_profile.publication sync issues:
     * 1. Missing in Profile: publication links to user via user_id or registered_co_author,
     *    but publication ID is missing from that user's reviewer_profile.publication
     * 2. Ghost in Profile: reviewer_profile.publication contains ID that does not exist in publications
     *
     * Note: Does NOT modify old publications documents (user_id, publication_type, status remain untouched).
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_publication_profile_issues', 60, function () {
            $allPubs = DB::connection('mongodb')->table('publications')
                ->get([
                    '_id',
                    'title',
                    'doi',
                    'published_date',
                    'journal_title',
                    'user_id',
                    'registered_co_author',
                    'status',
                ]);

            $validUserIds = [];
            foreach (DB::connection('mongodb')->table('users')->get(['_id']) as $u) {
                $validUserIds[(string) ($u->_id ?? $u->id)] = true;
            }

            $pubIdMap = [];
            $expectedUserPubs = [];

            foreach ($allPubs as $p) {
                $rawId = $p->_id ?? $p->id ?? null;
                $pid = (string) $rawId;
                $pubIdMap[$pid] = $p;

                $uid = ! empty($p->user_id) ? trim((string) $p->user_id) : '';
                $coAuthors = $this->extractCoAuthorIds($p->registered_co_author ?? null);

                if ($uid !== '') {
                    $expectedUserPubs[$uid][$pid] = 'owner';
                }

                foreach ($coAuthors as $coUid) {
                    if (! isset($expectedUserPubs[$coUid][$pid])) {
                        $expectedUserPubs[$coUid][$pid] = 'co_author';
                    }
                }
            }

            $profiles = DB::connection('mongodb')->table('reviewer_profile')
                ->whereNotNull('user_id')
                ->get(['_id', 'user_id', 'publication']);

            $profilePubMap = [];
            $profilesByUserId = [];
            foreach ($profiles as $rp) {
                $uid = (string) $rp->user_id;
                $rawId = $rp->_id ?? $rp->id ?? null;
                $rpid = (string) $rawId;
                $rpPubs = is_array($rp->publication ?? null) ? array_values(array_filter(array_map('strval', $rp->publication))) : [];

                $profilesByUserId[$uid] = [
                    'rp_id' => $rpid,
                    'pubs' => $rpPubs,
                ];

                foreach ($rpPubs as $pid) {
                    if (! isset($profilePubMap[$pid])) {
                        $profilePubMap[$pid] = [
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
            foreach ($profiles as $rp) {
                $uid = (string) $rp->user_id;
                $rawId = $rp->_id ?? $rp->id ?? null;
                $rpid = (string) $rawId;
                $rpPubs = is_array($rp->publication ?? null) ? array_values(array_filter(array_map('strval', $rp->publication))) : [];

                // A) Ghost IDs in ReviewerProfile
                $ghosts = array_diff($rpPubs, array_keys($pubIdMap));
                foreach ($ghosts as $gid) {
                    $issueList[] = [
                        'key' => "ghost_{$rpid}_{$gid}",
                        'type' => 'ghost_in_profile',
                        'type_label' => 'Ghost ID in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'pub_id' => $gid,
                        'title' => 'N/A (Deleted Publication)',
                        'doi' => '',
                        'published_date' => '',
                        'journal_title' => '',
                        'journal_name' => null,
                        'user_name' => null,
                        'user_email' => null,
                        'status' => null,
                        'suggested_user_id' => $uid,
                        'summary' => "Reviewer profile references publication ID [{$gid}] that does not exist in publications.",
                        'fix_label' => 'Remove Ghost',
                    ];
                }

                // B) Publications linked via user_id or registered_co_author, but missing from existing ReviewerProfile
                $userExpected = $expectedUserPubs[$uid] ?? [];
                $missing = array_diff(array_keys($userExpected), $rpPubs);
                foreach ($missing as $mid) {
                    $pubDoc = $pubIdMap[$mid] ?? null;
                    $linkSource = $userExpected[$mid] === 'co_author' ? 'registered_co_author' : 'user_id';
                    $issueList[] = [
                        'key' => "missing_{$rpid}_{$mid}",
                        'type' => 'missing_in_profile',
                        'type_label' => 'Missing in Profile',
                        'rp_id' => $rpid,
                        'user_id' => $uid,
                        'pub_id' => $mid,
                        'title' => $stringify($pubDoc->title ?? null, 'Untitled Publication'),
                        'doi' => $stringify($pubDoc->doi ?? null, ''),
                        'published_date' => $stringify($pubDoc->published_date ?? null, ''),
                        'journal_title' => $stringify($pubDoc->journal_title ?? null, ''),
                        'journal_name' => null,
                        'user_name' => null,
                        'user_email' => null,
                        'status' => $pubDoc->status ?? null,
                        'suggested_user_id' => $uid,
                        'summary' => "Publication links to user via {$linkSource}, but its ID is missing from reviewer_profile.publication.",
                        'fix_label' => 'Add to Profile',
                    ];
                }
            }

            // 3B. Valid users linked via user_id or registered_co_author who do not have a ReviewerProfile document yet
            foreach ($expectedUserPubs as $uid => $pubLinks) {
                if (isset($profilesByUserId[$uid]) || ! isset($validUserIds[$uid])) {
                    continue;
                }

                foreach ($pubLinks as $mid => $sourceType) {
                    $pubDoc = $pubIdMap[$mid] ?? null;
                    $linkSource = $sourceType === 'co_author' ? 'registered_co_author' : 'user_id';
                    $issueList[] = [
                        'key' => "missing_user_{$uid}_{$mid}",
                        'type' => 'missing_in_profile',
                        'type_label' => 'Missing in Profile',
                        'rp_id' => '',
                        'user_id' => $uid,
                        'pub_id' => $mid,
                        'title' => $stringify($pubDoc->title ?? null, 'Untitled Publication'),
                        'doi' => $stringify($pubDoc->doi ?? null, ''),
                        'published_date' => $stringify($pubDoc->published_date ?? null, ''),
                        'journal_title' => $stringify($pubDoc->journal_title ?? null, ''),
                        'journal_name' => null,
                        'user_name' => null,
                        'user_email' => null,
                        'status' => $pubDoc->status ?? null,
                        'suggested_user_id' => $uid,
                        'summary' => "User linked via {$linkSource} has no reviewer_profile yet. Clicking fix creates their profile and adds this publication.",
                        'fix_label' => 'Create Profile & Add',
                    ];
                }
            }

            return $issueList;
        });
    }

    /**
     * Decorate current page items (only visible 25 rows) with User and ReviewerJournal details.
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

            $jid = (string) ($i['journal_title'] ?? '');
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
                ->select(['_id', 'name', 'email'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        $journalsMap = ! empty($journalSearchKeys)
            ? ReviewerJournal::whereIn('_id', $journalSearchKeys)
                ->select(['_id', 'journal_title'])
                ->get()
                ->keyBy(fn ($j) => (string) $j->_id)
            : collect();

        foreach ($pageItems as &$item) {
            $uid = (string) (! empty($item['user_id']) ? $item['user_id'] : ($item['suggested_user_id'] ?? ''));
            $u = $usersMap->get($uid);

            $item['user_name'] = $u ? ($u->name ?? 'User') : null;
            $item['user_email'] = $u ? ($u->email ?? '') : null;

            $jRaw = (string) ($item['journal_title'] ?? '');
            $jDoc = $journalsMap->get($jRaw);
            $item['journal_name'] = $jDoc ? (string) ($jDoc->journal_title ?? null) : ($jRaw !== '' && strlen($jRaw) !== 24 ? $jRaw : null);
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
                    || str_contains(strtolower($item['pub_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['rp_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['suggested_user_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['title'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['doi'] ?? ''), $lowerTerm);
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
        Cache::forget('db_fixing_publication_profile_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully fixed issue: {$issue['type_label']} (Publication #{$issue['pub_id']})!";
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

        Cache::forget('db_fixing_publication_profile_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully resolved {$count} selected issue(s)!";
    }

    /**
     * High-performance batch resolver for all 27k+ publications:
     * Groups profile additions/ghost removals per ReviewerProfile (1 update per profile)
     * and chunks orphan status updates.
     */
    public function fixAll(): void
    {
        $allIssues = $this->scanIssues();
        $total = count($allIssues);

        if ($total === 0) {
            $this->statusMessage = 'All publications are already in perfect sync!';

            return;
        }

        // Preload publication statuses for accurate active/inactive counts
        $pubStatusMap = [];
        foreach (DB::connection('mongodb')->table('publications')->get(['_id', 'status']) as $p) {
            $pubStatusMap[(string) ($p->_id ?? $p->id)] = (int) ($p->status ?? 0);
        }

        $profileAdds = [];
        $profileRemovals = [];
        $newProfileUserPubs = [];

        foreach ($allIssues as $issue) {
            $type = $issue['type'];
            $rpId = (string) ($issue['rp_id'] ?? '');
            $pubId = (string) $issue['pub_id'];
            $uid = (string) ($issue['user_id'] ?? '');

            if ($type === 'missing_in_profile' && $rpId !== '') {
                $profileAdds[$rpId][] = $pubId;
            } elseif ($type === 'missing_in_profile' && $rpId === '' && $uid !== '') {
                $newProfileUserPubs[$uid][] = $pubId;
            } elseif ($type === 'ghost_in_profile' && $rpId !== '') {
                $profileRemovals[$rpId][] = $pubId;
            }
        }

        // 1. Batch update affected ReviewerProfiles (1 write per profile)
        $affectedRpIds = array_values(array_unique(array_merge(array_keys($profileAdds), array_keys($profileRemovals))));
        foreach (array_chunk($affectedRpIds, 200) as $chunkIds) {
            $mongoIds = array_map(fn ($id) => $this->toMongoId($id), $chunkIds);
            $profiles = DB::connection('mongodb')->table('reviewer_profile')
                ->whereIn('_id', array_merge($chunkIds, $mongoIds))
                ->get(['_id', 'user_id', 'publication']);

            foreach ($profiles as $rp) {
                $rpStrId = (string) ($rp->_id ?? $rp->id);
                $current = is_array($rp->publication ?? null) ? array_values(array_filter(array_map('strval', $rp->publication))) : [];

                if (! empty($profileRemovals[$rpStrId])) {
                    $current = array_diff($current, $profileRemovals[$rpStrId]);
                }
                if (! empty($profileAdds[$rpStrId])) {
                    $current = array_merge($current, $profileAdds[$rpStrId]);
                }

                $finalIds = array_values(array_unique($current));

                $activeCount = 0;
                $inactiveCount = 0;
                foreach ($finalIds as $pid) {
                    $st = $pubStatusMap[$pid] ?? 0;
                    if ($st === 1) {
                        $activeCount++;
                    } else {
                        $inactiveCount++;
                    }
                }

                DB::connection('mongodb')->table('reviewer_profile')
                    ->where('_id', $rp->_id ?? $rp->id)
                    ->update([
                        'publication' => $finalIds,
                        'publication_active_count' => $activeCount,
                        'publication_inactive_count' => $inactiveCount,
                        'publication_count' => count($finalIds),
                        'updated_at' => now(),
                    ]);

                if (! empty($rp->user_id)) {
                    Publication::forgetUserCache((string) $rp->user_id);
                }
            }
        }

        // 2. Create ReviewerProfiles for users without a profile yet
        foreach ($newProfileUserPubs as $uid => $pubIds) {
            $finalIds = array_values(array_unique($pubIds));
            $activeCount = 0;
            $inactiveCount = 0;
            foreach ($finalIds as $pid) {
                if (($pubStatusMap[$pid] ?? 0) === 1) {
                    $activeCount++;
                } else {
                    $inactiveCount++;
                }
            }

            $existing = ReviewerProfile::where('user_id', $uid)->first();
            if ($existing) {
                $merged = array_values(array_unique(array_merge(
                    is_array($existing->publication ?? null) ? array_map('strval', $existing->publication) : [],
                    $finalIds
                )));
                DB::connection('mongodb')->table('reviewer_profile')
                    ->where('_id', $existing->_id)
                    ->update([
                        'publication' => $merged,
                        'publication_count' => count($merged),
                        'updated_at' => now(),
                    ]);
            } else {
                ReviewerProfile::create([
                    'user_id' => $uid,
                    'publication' => $finalIds,
                    'publication_active_count' => $activeCount,
                    'publication_inactive_count' => $inactiveCount,
                    'publication_count' => count($finalIds),
                ]);
            }

            Publication::forgetUserCache($uid);
        }

        Cache::forget('db_fixing_publication_profile_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully resolved all {$total} issue(s) in reviewer_profile.publication.";
    }

    protected function resolveIssue(array $issue): void
    {
        $type = $issue['type'];
        $pubId = (string) $issue['pub_id'];
        $mongoPubId = $this->toMongoId($pubId);
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
                        $current = is_array($profile->publication ?? null) ? array_values(array_filter(array_map('strval', $profile->publication))) : [];
                        if (! in_array($pubId, $current, true)) {
                            $current[] = $pubId;
                            $finalIds = array_values(array_unique($current));

                            DB::connection('mongodb')->table('reviewer_profile')
                                ->where('_id', $profile->_id)
                                ->update([
                                    'publication' => $finalIds,
                                    'publication_count' => count($finalIds),
                                    'updated_at' => now(),
                                ]);
                        }
                    } else {
                        ReviewerProfile::create([
                            'user_id' => $userId,
                            'publication' => [$pubId],
                            'publication_count' => 1,
                        ]);
                    }

                    Publication::forgetUserCache($userId);
                }
                break;

            case 'ghost_in_profile':
                if ($rpId !== '') {
                    $profile = ReviewerProfile::where('_id', $this->toMongoId($rpId))->orWhere('_id', $rpId)->first();
                    if ($profile) {
                        $current = is_array($profile->publication ?? null) ? array_values(array_filter(array_map('strval', $profile->publication))) : [];
                        $cleaned = array_values(array_diff($current, [$pubId]));

                        DB::connection('mongodb')->table('reviewer_profile')
                            ->where('_id', $profile->_id)
                            ->update([
                                'publication' => $cleaned,
                                'publication_count' => count($cleaned),
                                'updated_at' => now(),
                            ]);

                        if (! empty($profile->user_id)) {
                            Publication::forgetUserCache((string) $profile->user_id);
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
            'missing_in_profile' => 0,
            'ghost_in_profile' => 0,
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
            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Publication</span>
        </div>

        <!-- Tool Switcher Pills -->
        <div class="inline-flex flex-wrap rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1 gap-1">
            <a href="{{ route('admin.database-fixing.publication.status') }}" wire:navigate class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
                <span>status</span>
            </a>
            <a href="{{ route('admin.database-fixing.publication.registered-co-author') }}" wire:navigate class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
                <span>registered_co_author</span>
            </a>
            <a href="{{ route('admin.database-fixing.publication.orphan-journal') }}" wire:navigate class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
                <span>orphan-journal</span>
            </a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md bg-white dark:bg-zinc-900 text-blue-700 dark:text-blue-300 shadow-xs">
                <flux:icon.user-group class="size-3.5" />
                <span>Reviewer Profile & Co-Author Sync</span>
            </span>
        </div>
    </div>

    <!-- Page Header & Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center size-8 rounded-lg bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                    <flux:icon.user-group class="size-4" />
                </span>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                    Publication — Reviewer Profile & Co-Author Sync
                </h1>
            </div>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Synchronizes <code>reviewer_profile.publication</code> arrays (for <code>user_id</code> and all <code>registered_co_author</code> users), purges ghost IDs, and updates profile publication counts without modifying existing <code>publications</code> records.
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
    <div x-data="{ open: false }" class="rounded-xl border border-blue-200/70 dark:border-blue-900/40 bg-blue-50/40 dark:bg-blue-950/20 p-4">
        <button @click="open = !open" type="button" class="w-full flex items-center justify-between text-left">
            <div class="flex items-center gap-2">
                <flux:icon.information-circle class="size-4 text-blue-600 dark:text-blue-400" />
                <span class="text-sm font-semibold text-blue-950 dark:text-blue-200">
                    What does this synchronization tool do? (Plain English Summary)
                </span>
            </div>
            <flux:icon.chevron-down class="size-4 text-blue-600 dark:text-blue-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
        </button>

        <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-blue-200/50 dark:border-blue-900/30 text-xs text-zinc-600 dark:text-zinc-300 space-y-2">
            <p>
                Across 27,056 publications, documents link authors via <code>registered_co_author</code> and/or <code>user_id</code>. Old <code>publications</code> documents are left completely untouched; this tool syncs only <code>reviewer_profile.publication</code>:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Sync Missing in Profile:</strong> Adds each publication ID to every linked author's &amp; co-author's <code>reviewer_profile.publication</code> array (auto-creating a profile if a valid user does not have one yet) and updates <code>publication_active_count</code> / <code>publication_inactive_count</code>.</li>
                <li><strong>Remove Ghost IDs:</strong> Purges deleted publication IDs from <code>reviewer_profile.publication</code>.</li>
            </ul>
        </div>
    </div>

    <!-- Diagnostic Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Issues</span>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($totalIssues) }}</span>
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
    </div>

    <!-- Filter Tabs, Search & Action Bar -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
        <!-- Filter Tabs -->
        <div class="flex items-center gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-zinc-800 overflow-x-auto text-xs font-medium">
            <button wire:click="setTab('all')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'all' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                All Issues ({{ number_format($counts['all']) }})
            </button>
            <button wire:click="setTab('missing_in_profile')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'missing_in_profile' ? 'bg-white dark:bg-zinc-900 text-blue-700 dark:text-blue-300 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Missing in Profile ({{ number_format($counts['missing_in_profile']) }})
            </button>
            <button wire:click="setTab('ghost_in_profile')" type="button" class="px-3 py-1.5 rounded-lg transition-all {{ $tab === 'ghost_in_profile' ? 'bg-white dark:bg-zinc-900 text-red-700 dark:text-red-300 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900' }}">
                Ghost IDs ({{ number_format($counts['ghost_in_profile']) }})
            </button>
        </div>

        <!-- Search Bar & Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[220px]">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search title, DOI, pub ID, user..." size="sm" icon="magnifying-glass" clearable />
            </div>

            @if ($totalIssues > 0)
                <button wire:click="fixAll" wire:confirm="Are you sure you want to resolve all {{ number_format($totalIssues) }} Publication synchronization issues across the database?" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition-colors">
                    <flux:icon.arrow-path class="size-3.5" wire:loading.class="animate-spin" wire:target="fixAll" />
                    <span>Fix All ({{ number_format($totalIssues) }})</span>
                </button>
            @endif

            @if (!empty($selectedRows))
                <button wire:click="fixSelected" wire:loading.attr="disabled" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-colors">
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
                            <input type="checkbox" wire:click="toggleSelectAll" @checked($selectAll) class="rounded border-zinc-300 dark:border-zinc-700 text-blue-600 focus:ring-blue-500 size-3.5" />
                        </th>
                        <th class="px-3 py-2.5 font-semibold">Issue Type</th>
                        <th class="px-3 py-2.5 font-semibold">Publication Information</th>
                        <th class="px-3 py-2.5 font-semibold">Attached User / Co-Author</th>
                        <th class="px-3 py-2.5 font-semibold">Problem Diagnostics</th>
                        <th class="px-3 py-2.5 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:border-zinc-800">
                    @forelse ($issues as $item)
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors {{ in_array($item['key'], $selectedRows) ? 'bg-blue-50/30 dark:bg-blue-950/20' : '' }}">
                            <td class="px-3 py-3">
                                <input type="checkbox" wire:model.live="selectedRows" value="{{ $item['key'] }}" class="rounded border-zinc-300 dark:border-zinc-700 text-blue-600 focus:ring-blue-500 size-3.5" />
                            </td>

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

                            <td class="px-3 py-3 max-w-[340px]">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100 line-clamp-1" title="{{ $item['title'] }}">
                                    {{ $item['title'] }}
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                                    @if ($item['journal_name'])
                                        <span class="text-blue-600 dark:text-blue-400 font-medium truncate max-w-[200px]">{{ $item['journal_name'] }}</span>
                                    @endif
                                    @if ($item['doi'])
                                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                                        <span class="font-mono">DOI: {{ $item['doi'] }}</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                    Pub ID: {{ $item['pub_id'] }}
                                </div>
                            </td>

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
                                        Detected User / Profile
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

                            <td class="px-3 py-3 max-w-[340px]">
                                <p class="text-[11px] text-zinc-600 dark:text-zinc-300">
                                    {{ $item['summary'] }}
                                </p>
                            </td>

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
                                        All Publications In Perfect Sync
                                    </h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">
                                        Every publication is linked to its authors' reviewer profiles, ghost IDs are purged, and orphan statuses are standardized.
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

