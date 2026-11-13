<?php

use App\Models\InvitedPosition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Invited Position — organization_id Fixer - Admin')] class extends Component {
    use WithPagination;

    public string $tab = 'all'; // 'all', 'ghost_organization_id', 'missing_organization_id', 'has_suggestion'

    public string $search = '';

    public string $statusMessage = 'Ready';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    // Organization Assignment Modal State
    public bool $showAssignModal = false;

    public string $assignInvitedPositionId = '';

    public string $assignRole = '';

    public string $orgSearch = '';

    public array $orgSearchResults = [];

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
     * Scan and build the full list of organization_id issues for Invited Position records:
     * 1. Ghost Organization IDs: organization_id is set but does not exist in organization collection
     * 2. Missing Organization ID: organization_id is null or empty
     * 3. Website Auto-Match: searches organization collection for matching website domain
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_invited_position_org_issues', 60, function () {
            // 1. Fetch all invited positions
            $allPositions = DB::connection('mongodb')->table('invited_position')
                ->get([
                    '_id',
                    'organization_id',
                    'role',
                    'invited_position_role',
                    'user_id',
                    'status',
                    'join_date',
                    'end_date',
                    'continue_invited_position',
                    'webiste_url',
                    'website_url',
                    'country',
                ]);

            // 2. Fetch all valid Organization IDs and prepare domain lookup cache
            $validOrgIds = DB::connection('mongodb')->table('organization')
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->flip()
                ->all();

            // Preload organizations with website URLs for domain matching
            $orgsWithWebsites = DB::connection('mongodb')->table('organization')
                ->whereNotNull('website_url')
                ->where('website_url', '!=', '')
                ->get(['_id', 'organization_name', 'website_url']);

            $domainToOrg = [];
            foreach ($orgsWithWebsites as $o) {
                $rawUrl = (string) $o->website_url;
                $cleanHost = strtolower(preg_replace('#^https?://(www\.)?#i', '', rtrim($rawUrl, '/')));
                if (! empty($cleanHost) && ! isset($domainToOrg[$cleanHost])) {
                    $domainToOrg[$cleanHost] = [
                        'id' => (string) ($o->_id ?? $o->id),
                        'name' => (string) ($o->organization_name ?? 'Organization'),
                    ];
                }
            }

            $stringify = function ($val, string $fallback = ''): string {
                if (is_array($val)) {
                    return implode(', ', array_filter(array_map('strval', $val)));
                }

                return (string) ($val ?? $fallback);
            };

            $issueList = [];

            foreach ($allPositions as $p) {
                $rawId = $p->_id ?? $p->id ?? null;
                $pid = (string) $rawId;
                $rawOrgId = $p->organization_id ?? null;
                $orgIdStr = is_scalar($rawOrgId) ? trim((string) $rawOrgId) : '';
                $webUrl = (string) ($p->website_url ?? $p->webiste_url ?? '');
                $role = $stringify($p->role ?? $p->invited_position_role ?? null, 'Invited Position');

                // Check for website domain match
                $suggestedOrgId = null;
                $suggestedOrgName = null;
                if (! empty($webUrl)) {
                    $posHost = strtolower(preg_replace('#^https?://(www\.)?#i', '', rtrim($webUrl, '/')));
                    // Check direct domain or host match
                    if (isset($domainToOrg[$posHost])) {
                        $suggestedOrgId = $domainToOrg[$posHost]['id'];
                        $suggestedOrgName = $domainToOrg[$posHost]['name'];
                    } else {
                        // Check if host contains or is contained in org website
                        foreach ($domainToOrg as $dHost => $dData) {
                            if (strlen($posHost) >= 4 && (str_contains($dHost, $posHost) || str_contains($posHost, $dHost))) {
                                $suggestedOrgId = $dData['id'];
                                $suggestedOrgName = $dData['name'];
                                break;
                            }
                        }
                    }
                }

                if ($orgIdStr !== '') {
                    // Check if ghost
                    if (! isset($validOrgIds[$orgIdStr])) {
                        $issueList[] = [
                            'key' => "ghost_{$pid}",
                            'type' => 'ghost_organization_id',
                            'type_label' => 'Ghost Organization ID',
                            'invited_position_id' => $pid,
                            'role' => $role,
                            'organization_id' => $orgIdStr,
                            'user_id' => (string) ($p->user_id ?? ''),
                            'status' => $p->status ?? null,
                            'website_url' => $webUrl,
                            'join_date' => (string) ($p->join_date ?? ''),
                            'end_date' => (string) ($p->end_date ?? ''),
                            'country' => (string) ($p->country ?? ''),
                            'suggested_org_id' => $suggestedOrgId,
                            'suggested_org_name' => $suggestedOrgName,
                            'summary' => "Invited position references organization_id [{$orgIdStr}] which does not exist in the database.",
                        ];
                    }
                } else {
                    // Missing organization ID
                    $summary = $suggestedOrgName
                        ? "organization_id is null/empty. Auto-matched organization from website URL: '{$suggestedOrgName}'."
                        : 'organization_id is null/empty. No organization linked to this invited position.';

                    $issueList[] = [
                        'key' => "missing_{$pid}",
                        'type' => 'missing_organization_id',
                        'type_label' => 'No Organization ID',
                        'invited_position_id' => $pid,
                        'role' => $role,
                        'organization_id' => null,
                        'user_id' => (string) ($p->user_id ?? ''),
                        'status' => $p->status ?? null,
                        'website_url' => $webUrl,
                        'join_date' => (string) ($p->join_date ?? ''),
                        'end_date' => (string) ($p->end_date ?? ''),
                        'country' => (string) ($p->country ?? ''),
                        'suggested_org_id' => $suggestedOrgId,
                        'suggested_org_name' => $suggestedOrgName,
                        'summary' => $summary,
                    ];
                }
            }

            return $issueList;
        });
    }

    /**
     * Decorate page items with User owner and existing Organization details.
     */
    protected function decoratePageItems(array $pageItems): array
    {
        if (empty($pageItems)) {
            return [];
        }

        $userIds = [];
        $orgIds = [];

        foreach ($pageItems as $i) {
            $uid = (string) ($i['user_id'] ?? '');
            if ($uid !== '') {
                $userIds[] = $uid;
            }

            $oid = (string) ($i['organization_id'] ?? '');
            if ($oid !== '') {
                $orgIds[] = $oid;
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
        if ($this->tab === 'has_suggestion') {
            $allIssues = array_values(array_filter($allIssues, fn ($item) => ! empty($item['suggested_org_id'])));
        } elseif ($this->tab !== 'all') {
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
                $uid = $item['user_id'] ?? '';

                return isset($matchedUserIds[$uid])
                    || str_contains(strtolower($item['invited_position_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['role'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['organization_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['website_url'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['suggested_org_name'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['summary'] ?? ''), $lowerTerm);
            }));
        }

        return $allIssues;
    }

    /**
     * Apply suggested organization match to a single record.
     */
    public function applySuggestion(string $key): void
    {
        $allIssues = $this->scanIssues();
        $issue = collect($allIssues)->firstWhere('key', $key);

        if (! $issue || empty($issue['suggested_org_id'])) {
            $this->statusMessage = 'No suggested match found for this item.';

            return;
        }

        $pid = $issue['invited_position_id'];
        $mongoPid = $this->toMongoId($pid);
        $orgId = $issue['suggested_org_id'];
        $orgName = $issue['suggested_org_name'] ?? $orgId;

        DB::connection('mongodb')->table('invited_position')
            ->where(function ($q) use ($pid, $mongoPid) {
                $q->where('_id', $mongoPid)->orWhere('_id', $pid);
            })->update([
                'organization_id' => $orgId,
                'updated_at' => now(),
            ]);

        Cache::forget('db_fixing_invited_position_org_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully attached suggested organization [{$orgName}] to Invited Position #{$pid}!";
    }

    /**
     * Batch apply all suggested organization matches.
     */
    public function applyAllSuggestions(): void
    {
        $allIssues = $this->scanIssues();
        $withSuggestions = array_filter($allIssues, fn ($i) => ! empty($i['suggested_org_id']));

        if (empty($withSuggestions)) {
            $this->statusMessage = 'No suggested organization matches available to apply.';

            return;
        }

        $count = 0;
        foreach ($withSuggestions as $issue) {
            $pid = $issue['invited_position_id'];
            $mongoPid = $this->toMongoId($pid);

            DB::connection('mongodb')->table('invited_position')
                ->where(function ($q) use ($pid, $mongoPid) {
                    $q->where('_id', $mongoPid)->orWhere('_id', $pid);
                })->update([
                    'organization_id' => $issue['suggested_org_id'],
                    'updated_at' => now(),
                ]);
            $count++;
        }

        Cache::forget('db_fixing_invited_position_org_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully applied {$count} suggested organization match(es)!";
    }

    /**
     * Clean ghost organization_id to NULL.
     */
    public function cleanGhost(string $key): void
    {
        $allIssues = $this->scanIssues();
        $issue = collect($allIssues)->firstWhere('key', $key);

        if (! $issue) {
            $this->statusMessage = "Issue [{$key}] not found.";

            return;
        }

        $pid = $issue['invited_position_id'];
        $mongoPid = $this->toMongoId($pid);

        DB::connection('mongodb')->table('invited_position')
            ->where(function ($q) use ($pid, $mongoPid) {
                $q->where('_id', $mongoPid)->orWhere('_id', $pid);
            })->update([
                'organization_id' => null,
                'updated_at' => now(),
            ]);

        Cache::forget('db_fixing_invited_position_org_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully cleaned ghost organization_id to NULL on Invited Position #{$pid}!";
    }

    /**
     * Batch clean all ghost organization IDs to NULL.
     */
    public function cleanAllGhosts(): void
    {
        $allIssues = $this->scanIssues();
        $ghosts = array_filter($allIssues, fn ($i) => $i['type'] === 'ghost_organization_id');

        if (empty($ghosts)) {
            $this->statusMessage = 'No ghost organization IDs to clean.';

            return;
        }

        $count = 0;
        foreach ($ghosts as $issue) {
            $pid = $issue['invited_position_id'];
            $mongoPid = $this->toMongoId($pid);

            DB::connection('mongodb')->table('invited_position')
                ->where(function ($q) use ($pid, $mongoPid) {
                    $q->where('_id', $mongoPid)->orWhere('_id', $pid);
                })->update([
                    'organization_id' => null,
                    'updated_at' => now(),
                ]);
            $count++;
        }

        Cache::forget('db_fixing_invited_position_org_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully cleaned all {$count} ghost organization ID(s) to NULL!";
    }

    /**
     * Open Organization Assignment Modal.
     */
    public function openAssignModal(string $invitedPositionId, string $role = ''): void
    {
        $this->assignInvitedPositionId = $invitedPositionId;
        $this->assignRole = $role;
        $this->orgSearch = '';
        $this->showAssignModal = true;

        // Preload default popular organizations
        $res = Organization::searchActive('', 10);
        $this->orgSearchResults = $res['data'] ?? [];
    }

    /**
     * Close Organization Assignment Modal.
     */
    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignInvitedPositionId = '';
        $this->assignRole = '';
        $this->orgSearch = '';
        $this->orgSearchResults = [];
    }

    /**
     * Search organizations in real-time.
     */
    public function updatedOrgSearch(string $val): void
    {
        $query = trim($val);
        $res = Organization::searchActive($query, 15);
        $this->orgSearchResults = $res['data'] ?? [];
    }

    /**
     * Assign a chosen Organization to the Invited Position record.
     */
    public function assignOrganization(string $orgId, string $orgName = ''): void
    {
        $pid = $this->assignInvitedPositionId;
        if (empty($pid)) {
            $this->statusMessage = 'No invited position was selected for assignment.';
            $this->closeAssignModal();

            return;
        }

        $mongoPid = $this->toMongoId($pid);

        DB::connection('mongodb')->table('invited_position')
            ->where(function ($q) use ($pid, $mongoPid) {
                $q->where('_id', $mongoPid)->orWhere('_id', $pid);
            })->update([
                'organization_id' => $orgId,
                'updated_at' => now(),
            ]);

        Cache::forget('db_fixing_invited_position_org_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, ["ghost_{$pid}", "missing_{$pid}"]));
        $this->statusMessage = 'Successfully assigned organization ['.($orgName ?: $orgId)."] to Invited Position #{$pid}!";
        $this->closeAssignModal();
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
            $issue = $issuesMap->get($key);
            if (! $issue) {
                continue;
            }

            $pid = $issue['invited_position_id'];
            $mongoPid = $this->toMongoId($pid);

            if (! empty($issue['suggested_org_id'])) {
                // Apply suggested match
                DB::connection('mongodb')->table('invited_position')
                    ->where(function ($q) use ($pid, $mongoPid) {
                        $q->where('_id', $mongoPid)->orWhere('_id', $pid);
                    })->update([
                        'organization_id' => $issue['suggested_org_id'],
                        'updated_at' => now(),
                    ]);
                $count++;
            } elseif ($issue['type'] === 'ghost_organization_id') {
                // Clean ghost to null
                DB::connection('mongodb')->table('invited_position')
                    ->where(function ($q) use ($pid, $mongoPid) {
                        $q->where('_id', $mongoPid)->orWhere('_id', $pid);
                    })->update([
                        'organization_id' => null,
                        'updated_at' => now(),
                    ]);
                $count++;
            }
        }

        Cache::forget('db_fixing_invited_position_org_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully processed {$count} selected item(s)!";
    }

    /**
     * Clear issue cache manually and refresh view.
     */
    public function refreshScan(): void
    {
        Cache::forget('db_fixing_invited_position_org_issues');
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = 'Refreshed! Fresh scan completed against database.';
    }

    public function with(): array
    {
        $allIssues = $this->scanIssues();

        $counts = [
            'all' => count($allIssues),
            'ghost_organization_id' => 0,
            'missing_organization_id' => 0,
            'has_suggestion' => 0,
        ];

        foreach ($allIssues as $i) {
            if (isset($counts[$i['type']])) {
                $counts[$i['type']]++;
            }
            if (! empty($i['suggested_org_id'])) {
                $counts['has_suggestion']++;
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
            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Invited Position</span>
        </div>

        <!-- Tool Switcher Pills -->
        <div class="inline-flex rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md bg-white dark:bg-zinc-900 text-yellow-800 dark:text-yellow-300 shadow-xs">
                <flux:icon.building-office-2 class="size-3.5" />
                <span>Organization ID Fixer</span>
            </span>
            <a href="{{ route('admin.database-fixing.invited-position.reviewer-profile') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
                <flux:icon.user-group class="size-3.5" />
                <span>Reviewer Profile & User Sync</span>
            </a>
        </div>
    </div>

    {{-- Explainer Accordion --}}
    <div x-data="{ open: false }" class="bg-white dark:bg-zinc-800 rounded-xl shadow-xs border border-zinc-200 dark:border-zinc-700/60 overflow-hidden">
        <button 
            type="button" 
            @click="open = !open" 
            class="w-full px-5 py-3 text-left font-semibold text-xs tracking-wide uppercase flex items-center justify-between transition-colors bg-zinc-50 dark:bg-zinc-800/80 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 text-zinc-700 dark:text-zinc-200"
        >
            <span class="flex items-center gap-2">
                <flux:icon.information-circle class="size-4 text-yellow-600 dark:text-yellow-400" />
                <span>What does this tool fix? (Plain-English Summary)</span>
            </span>
            <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
        </button>
        <div x-show="open" x-cloak class="p-5 text-xs text-zinc-600 dark:text-zinc-300 border-t border-zinc-200 dark:border-zinc-700/60 space-y-2 bg-white dark:bg-zinc-800">
            <p>
                Each <strong><code>invited_position</code></strong> record links to an organization via <code>organization_id</code>. This tool detects two main issues:
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Ghost Organization IDs:</strong> <code>organization_id</code> is set, but the ID does not exist in the <code>organization</code> collection (deleted/broken references). You can safely clean them to <code>null</code> or assign a valid organization.</li>
                <li><strong>Missing Organization IDs:</strong> <code>organization_id</code> is null or empty. The tool inspects <code>website_url</code> and automatically suggests matches from the organization collection. You can also search from all 37,000+ organizations in real-time.</li>
            </ul>
        </div>
    </div>

    <!-- Header & Live Status Message -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <span>Invited Position — organization_id Fixer</span>
            </h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Inspect broken organization references, auto-match from website URLs, and attach valid organizations to invited positions.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="refreshScan"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 text-xs font-medium transition cursor-pointer"
            >
                <flux:icon.arrow-path class="size-3.5" wire:loading.class="animate-spin" wire:target="refreshScan" />
                <span>Refresh Diagnostics</span>
            </button>
        </div>
    </div>

    <!-- Live Flash Message -->
    @if ($statusMessage !== 'Ready')
        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200 font-medium flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="size-4 text-emerald-600 dark:text-emerald-400" />
                <span>{{ $statusMessage }}</span>
            </div>
            <button type="button" wire:click="$set('statusMessage', 'Ready')" class="text-emerald-600 hover:text-emerald-800 text-[11px] font-bold">Dismiss</button>
        </div>
    @endif

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-zinc-800/80 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-2xs">
            <div class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Total Issues</div>
            <div class="text-2xl font-black text-zinc-900 dark:text-white mt-1">{{ number_format($counts['all']) }}</div>
            <div class="text-[10px] text-zinc-400 mt-0.5">Ghost or missing organization IDs</div>
        </div>

        <div class="bg-white dark:bg-zinc-800/80 p-4 rounded-xl border border-red-200 dark:border-red-800/60 shadow-2xs">
            <div class="text-[11px] font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider">Ghost Org IDs</div>
            <div class="text-2xl font-black text-red-600 dark:text-red-400 mt-1">{{ number_format($counts['ghost_organization_id']) }}</div>
            <div class="text-[10px] text-red-500/80 mt-0.5">Referenced org does not exist</div>
        </div>

        <div class="bg-white dark:bg-zinc-800/80 p-4 rounded-xl border border-amber-200 dark:border-amber-800/60 shadow-2xs">
            <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Missing Org IDs</div>
            <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ number_format($counts['missing_organization_id']) }}</div>
            <div class="text-[10px] text-amber-500/80 mt-0.5">Empty or null organization_id</div>
        </div>

        <div class="bg-white dark:bg-zinc-800/80 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800/60 shadow-2xs">
            <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Suggested Matches</div>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($counts['has_suggestion']) }}</div>
            <div class="text-[10px] text-emerald-500/80 mt-0.5">Matched from website domain</div>
        </div>
    </div>

    <!-- Filter Tabs & Search Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Tabs -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <button
                type="button"
                wire:click="setTab('all')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $tab === 'all' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-2xs' : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50' }}"
            >
                All ({{ $counts['all'] }})
            </button>
            <button
                type="button"
                wire:click="setTab('ghost_organization_id')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $tab === 'ghost_organization_id' ? 'bg-red-600 text-white shadow-2xs' : 'bg-white dark:bg-zinc-800 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50' }}"
            >
                Ghost IDs ({{ $counts['ghost_organization_id'] }})
            </button>
            <button
                type="button"
                wire:click="setTab('missing_organization_id')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $tab === 'missing_organization_id' ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white dark:bg-zinc-800 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 hover:bg-amber-50' }}"
            >
                Missing Org IDs ({{ $counts['missing_organization_id'] }})
            </button>
            @if ($counts['has_suggestion'] > 0)
                <button
                    type="button"
                    wire:click="setTab('has_suggestion')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $tab === 'has_suggestion' ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white dark:bg-zinc-800 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50' }}"
                >
                    Has Suggestion ({{ $counts['has_suggestion'] }})
                </button>
            @endif
        </div>

        <!-- Search & Batch Actions -->
        <div class="flex items-center gap-2 flex-wrap">
            <div class="w-full sm:w-64">
                <flux:input
                    wire:model.live.debounce.250ms="search"
                    placeholder="Search role, website, user..."
                    size="sm"
                    icon="magnifying-glass"
                />
            </div>

            @if ($counts['has_suggestion'] > 0)
                <button
                    type="button"
                    wire:click="applyAllSuggestions"
                    wire:confirm="Are you sure you want to apply all {{ $counts['has_suggestion'] }} auto-matched organizations?"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition-all cursor-pointer flex items-center gap-1.5"
                >
                    <flux:icon.check class="size-3.5" />
                    <span>Apply All Matches ({{ $counts['has_suggestion'] }})</span>
                </button>
            @endif

            @if ($counts['ghost_organization_id'] > 0)
                <button
                    type="button"
                    wire:click="cleanAllGhosts"
                    wire:confirm="Are you sure you want to clean all {{ $counts['ghost_organization_id'] }} ghost organization IDs to NULL?"
                    class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-bold shadow-2xs transition-all cursor-pointer flex items-center gap-1.5"
                >
                    <flux:icon.trash class="size-3.5" />
                    <span>Clean All Ghosts ({{ $counts['ghost_organization_id'] }})</span>
                </button>
            @endif

            @if (! empty($selectedRows))
                <button
                    type="button"
                    wire:click="fixSelected"
                    class="px-3 py-1.5 rounded-lg bg-yellow-600 hover:bg-yellow-700 text-white text-xs font-bold shadow-2xs transition-all cursor-pointer flex items-center gap-1.5"
                >
                    <flux:icon.sparkles class="size-3.5" />
                    <span>Fix Selected ({{ count($selectedRows) }})</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Issues Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-900/60 text-[10px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 w-10 text-center">
                            <input
                                type="checkbox"
                                wire:click="toggleSelectAll"
                                @checked($selectAll)
                                class="rounded border-zinc-300 text-yellow-600 focus:ring-yellow-500"
                            />
                        </th>
                        <th class="px-4 py-3 w-40">Issue Type</th>
                        <th class="px-4 py-3">Invited Position Details</th>
                        <th class="px-4 py-3 w-52">Owner User</th>
                        <th class="px-4 py-3 w-56">Current Org / Match Status</th>
                        <th class="px-4 py-3 text-right w-44">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/60 dark:divide-zinc-700/60">
                    @forelse($issues as $item)
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-700/30 transition">
                            <!-- Checkbox -->
                            <td class="px-4 py-3.5 text-center">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedRows"
                                    value="{{ $item['key'] }}"
                                    class="rounded border-zinc-300 text-yellow-600 focus:ring-yellow-500"
                                />
                            </td>

                            <!-- Issue Type -->
                            <td class="px-4 py-3.5">
                                @if ($item['type'] === 'ghost_organization_id')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800">
                                        <span class="size-1.5 rounded-full bg-red-500"></span>
                                        <span>Ghost Org ID</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800">
                                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        <span>No Org ID</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Position Details -->
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-zinc-900 dark:text-white text-sm">
                                    {{ $item['role'] }}
                                </div>
                                @if (! empty($item['website_url']))
                                    <div class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-1">
                                        <flux:icon.globe-alt class="size-3 text-zinc-400" />
                                        <a href="{{ $item['website_url'] }}" target="_blank" class="hover:underline text-brand truncate max-w-xs">{{ $item['website_url'] }}</a>
                                    </div>
                                @endif
                                <div class="mt-1 flex items-center gap-2 text-[10px] text-zinc-400">
                                    <span>ID: <code class="font-mono text-zinc-500 dark:text-zinc-400">{{ $item['invited_position_id'] }}</code></span>
                                    @if ($item['join_date'] || $item['end_date'])
                                        <span>•</span>
                                        <span>{{ $item['join_date'] ?: 'N/A' }} – {{ $item['end_date'] ?: 'Present' }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- User Owner -->
                            <td class="px-4 py-3.5">
                                @if (! empty($item['user_name']))
                                    <div class="font-bold text-zinc-900 dark:text-white truncate max-w-[190px]">
                                        {{ $item['user_name'] }}
                                    </div>
                                    @if ($item['user_email'])
                                        <div class="text-[11px] text-zinc-400 truncate max-w-[190px]">{{ $item['user_email'] }}</div>
                                    @endif
                                    <div class="text-[10px] text-zinc-400 font-mono mt-0.5">UID: {{ $item['user_id'] }}</div>
                                @elseif (! empty($item['user_id']))
                                    <span class="font-mono text-[11px] text-zinc-500">{{ $item['user_id'] }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] text-zinc-400 italic">
                                        <flux:icon.user-minus class="size-3" />
                                        <span>No user linked</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Current Org & Match Status -->
                            <td class="px-4 py-3.5">
                                @if ($item['type'] === 'ghost_organization_id')
                                    <div class="font-mono text-[11px] text-red-600 dark:text-red-400 break-all font-semibold">
                                        {{ $item['organization_id'] }}
                                    </div>
                                    <div class="text-[10px] text-red-500 mt-0.5">Broken reference</div>
                                @else
                                    <div class="text-[11px] text-zinc-400 italic font-medium">NULL / Empty</div>
                                @endif

                                @if (! empty($item['suggested_org_name']))
                                    <div class="mt-1.5 p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-[11px]">
                                        <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-1">
                                            <flux:icon.sparkles class="size-3 text-emerald-500" />
                                            <span>Suggested Match:</span>
                                        </div>
                                        <div class="font-bold text-emerald-900 dark:text-emerald-100 truncate mt-0.5" title="{{ $item['suggested_org_name'] }}">
                                            {{ $item['suggested_org_name'] }}
                                        </div>
                                    </div>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if (! empty($item['suggested_org_id']))
                                        <button
                                            type="button"
                                            wire:click="applySuggestion('{{ $item['key'] }}')"
                                            title="Attach suggested organization"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition cursor-pointer"
                                        >
                                            <flux:icon.check class="size-3" />
                                            <span>Apply</span>
                                        </button>
                                    @endif

                                    <button
                                        type="button"
                                        wire:click="openAssignModal('{{ $item['invited_position_id'] }}', '{{ addslashes($item['role']) }}')"
                                        title="Search and assign an organization"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 shadow-2xs transition cursor-pointer"
                                    >
                                        <flux:icon.magnifying-glass class="size-3" />
                                        <span>Assign</span>
                                    </button>

                                    @if ($item['type'] === 'ghost_organization_id')
                                        <button
                                            type="button"
                                            wire:click="cleanGhost('{{ $item['key'] }}')"
                                            title="Clean ghost ID to NULL"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 border border-red-200 dark:border-red-800 transition cursor-pointer"
                                        >
                                            <flux:icon.trash class="size-3" />
                                            <span>NULL</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="size-10 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                        <flux:icon.check class="size-5" />
                                    </div>
                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">
                                        No organization_id Issues Found!
                                    </h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">
                                        All invited position records reference valid organizations or are cleanly accounted for.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($paginator->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>

    <!-- ORGANIZATION ASSIGNMENT MODAL -->
    <div
        x-data="{ show: @entangle('showAssignModal') }"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div
                x-show="show"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="$wire.closeAssignModal()"
                class="fixed inset-0 transition-opacity bg-zinc-900/60 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal Panel -->
            <div
                x-show="show"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-zinc-800 shadow-xl rounded-2xl border border-zinc-200 dark:border-zinc-700"
            >
                <div class="flex items-center justify-between pb-4 border-b border-zinc-200 dark:border-zinc-700">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                            <flux:icon.building-office-2 class="size-5 text-yellow-600 dark:text-yellow-400" />
                            <span>Assign Organization</span>
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Linking to Invited Position: <strong class="text-zinc-700 dark:text-zinc-200">{{ $assignRole ?: '#' . $assignInvitedPositionId }}</strong>
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeAssignModal"
                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition"
                    >
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <!-- Search Input -->
                    <div>
                        <flux:input
                            wire:model.live.debounce.250ms="orgSearch"
                            placeholder="Type organization name, university, or city..."
                            size="sm"
                            icon="magnifying-glass"
                            clearable
                            autofocus
                        />
                    </div>

                    <!-- Search Results List -->
                    <div class="max-h-72 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-700/60 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/40">
                        @forelse($orgSearchResults as $org)
                            @php
                                $orgId = (string) ($org['_id'] ?? $org['id'] ?? '');
                            @endphp
                            <div class="p-3 flex items-center justify-between gap-3 hover:bg-zinc-100/70 dark:hover:bg-zinc-700/40 transition">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-zinc-900 dark:text-white truncate">
                                        {{ $org['organization_name'] ?? 'Untitled Organization' }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5 mt-0.5 truncate">
                                        @if (! empty($org['city']) || ! empty($org['state']))
                                            <span>{{ implode(', ', array_filter([$org['city'] ?? '', $org['state'] ?? ''])) }}</span>
                                        @endif
                                        @if (! empty($org['website_url']))
                                            <span>•</span>
                                            <span class="truncate">{{ $org['website_url'] }}</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-mono mt-0.5">ID: {{ $orgId }}</div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="assignOrganization('{{ $orgId }}', '{{ addslashes($org['organization_name'] ?? '') }}')"
                                    class="shrink-0 px-3 py-1.5 rounded-lg bg-yellow-600 hover:bg-yellow-700 text-white text-xs font-bold shadow-2xs transition cursor-pointer flex items-center gap-1"
                                >
                                    <flux:icon.check class="size-3.5" />
                                    <span>Select</span>
                                </button>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
                                No organizations found matching "{{ $orgSearch }}".
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-zinc-200 dark:border-zinc-700 flex justify-end">
                    <button
                        type="button"
                        wire:click="closeAssignModal"
                        class="px-4 py-2 rounded-lg text-xs font-bold border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 transition cursor-pointer"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

