<?php

use App\Models\Membership;
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

new #[Layout('components.layouts.admin')] #[Title('Membership — organization_id Fixer - Admin')] class extends Component {
    use WithPagination;

    public string $tab = 'all'; // 'all', 'ghost_organization_id', 'missing_organization_id'

    public string $search = '';

    public string $statusMessage = 'Ready';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    // Organization Assignment Modal State
    public bool $showAssignModal = false;

    public string $assignMembershipId = '';

    public string $assignMembershipType = '';

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
     * Scan and build the list of organization_id issues for Membership records:
     * 1. Ghost Organization IDs: organization_id is set but does not exist in organization collection
     * 2. No Organization ID: organization_id is null or empty string
     */
    protected function scanIssues(): array
    {
        return Cache::remember('db_fixing_membership_org_issues', 60, function () {
            // 1. Fetch all memberships from MongoDB with light projections
            $allMemberships = DB::connection('mongodb')->table('membership')
                ->get([
                    '_id',
                    'organization_id',
                    'membership_type',
                    'user_id',
                    'status',
                    'join_date',
                    'end_date',
                    'continue_membership',
                    'website_url',
                    'webiste_url',
                ]);

            // 2. Fetch all valid Organization IDs from MongoDB
            $validOrgIds = DB::connection('mongodb')->table('organization')
                ->pluck('_id')
                ->map(fn ($id) => (string) $id)
                ->flip()
                ->all();

            $issueList = [];

            foreach ($allMemberships as $m) {
                $rawMid = $m->_id ?? $m->id ?? null;
                $mid = (string) $rawMid;
                $rawOid = $m->organization_id ?? null;
                $oidStr = trim((string) $rawOid);
                $uid = (string) ($m->user_id ?? '');

                // Case 1: Missing organization_id (null or empty string)
                if ($rawOid === null || $oidStr === '') {
                    $issueList[] = [
                        'key' => "missing_{$mid}",
                        'type' => 'missing_organization_id',
                        'type_label' => 'No Organization ID',
                        'membership_id' => $mid,
                        'user_id' => $uid,
                        'membership_type' => (string) ($m->membership_type ?? ''),
                        'join_date' => (string) ($m->join_date ?? ''),
                        'end_date' => (string) ($m->end_date ?? ''),
                        'continue_membership' => (int) ($m->continue_membership ?? 0),
                        'website_url' => (string) ($m->website_url ?? $m->webiste_url ?? ''),
                        'organization_id' => $rawOid === '' ? '"" (Empty String)' : 'NULL',
                        'raw_organization_id' => $rawOid,
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => $m->status ?? null,
                        'summary' => $rawOid === ''
                            ? 'Record contains an empty string organization_id (""). Standardizing sets it to pure NULL.'
                            : 'Membership has no organization_id assigned. You can assign a valid organization or keep as NULL.',
                        'fix_label' => $rawOid === '' ? 'Standardize to NULL' : 'Clean to NULL',
                    ];
                }
                // Case 2: Ghost organization_id (ID does not exist in organization collection)
                elseif (! isset($validOrgIds[$oidStr])) {
                    $issueList[] = [
                        'key' => "ghost_{$mid}",
                        'type' => 'ghost_organization_id',
                        'type_label' => 'Ghost Organization ID',
                        'membership_id' => $mid,
                        'user_id' => $uid,
                        'membership_type' => (string) ($m->membership_type ?? ''),
                        'join_date' => (string) ($m->join_date ?? ''),
                        'end_date' => (string) ($m->end_date ?? ''),
                        'continue_membership' => (int) ($m->continue_membership ?? 0),
                        'website_url' => (string) ($m->website_url ?? $m->webiste_url ?? ''),
                        'organization_id' => $oidStr,
                        'raw_organization_id' => $rawOid,
                        'user_name' => null,
                        'user_email' => null,
                        'user_unique_id' => null,
                        'status' => $m->status ?? null,
                        'summary' => "Referenced organization_id [{$oidStr}] does not exist in the database (deleted or orphan reference).",
                        'fix_label' => 'Clean to NULL',
                    ];
                }
            }

            return $issueList;
        });
    }

    /**
     * Decorate ONLY the current page's sliced items with User records.
     * This avoids fetching thousands of User models on every request.
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
                $uid = (string) ($item['user_id'] ?? '');

                return isset($matchedUserIds[$uid])
                    || str_contains(strtolower($item['membership_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['organization_id'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['membership_type'] ?? ''), $lowerTerm)
                    || str_contains(strtolower($item['website_url'] ?? ''), $lowerTerm);
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
            'ghost_organization_id' => 0,
            'missing_organization_id' => 0,
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
     * Clean single issue by setting organization_id to NULL.
     */
    public function cleanSingleToNull(string $key): void
    {
        $allIssues = $this->scanIssues();
        $issue = collect($allIssues)->firstWhere('key', $key);

        if (! $issue) {
            $this->statusMessage = "Issue [{$key}] was not found or already resolved.";

            return;
        }

        $mid = (string) $issue['membership_id'];
        $mongoMid = $this->toMongoId($mid);

        DB::connection('mongodb')->table('membership')
            ->where(function ($q) use ($mid, $mongoMid) {
                $q->where('_id', $mongoMid)->orWhere('_id', $mid);
            })->update([
                'organization_id' => null,
                'updated_at' => now(),
            ]);

        Cache::forget('db_fixing_membership_org_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, [$key]));
        $this->statusMessage = "Successfully cleaned organization_id to NULL for Membership #{$mid}!";
    }

    /**
     * Clean all selected rows to NULL.
     */
    public function cleanSelected(): void
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
                $issue = $issuesMap->get($key);
                $mid = (string) $issue['membership_id'];
                $mongoMid = $this->toMongoId($mid);

                DB::connection('mongodb')->table('membership')
                    ->where(function ($q) use ($mid, $mongoMid) {
                        $q->where('_id', $mongoMid)->orWhere('_id', $mid);
                    })->update([
                        'organization_id' => null,
                        'updated_at' => now(),
                    ]);
                $count++;
            }
        }

        Cache::forget('db_fixing_membership_org_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully cleaned {$count} selected membership organization_id(s) to NULL!";
    }

    /**
     * Clean all Ghost Organization IDs across the database to NULL.
     */
    public function cleanAllGhosts(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $allIssues = $this->scanIssues();
        $ghostIssues = array_values(array_filter($allIssues, fn ($i) => $i['type'] === 'ghost_organization_id'));
        $total = count($ghostIssues);

        if ($total === 0) {
            $this->statusMessage = 'No ghost organization IDs found! All memberships have valid or clean organizations.';

            return;
        }

        foreach ($ghostIssues as $issue) {
            $mid = (string) $issue['membership_id'];
            $mongoMid = $this->toMongoId($mid);

            DB::connection('mongodb')->table('membership')
                ->where(function ($q) use ($mid, $mongoMid) {
                    $q->where('_id', $mongoMid)->orWhere('_id', $mid);
                })->update([
                    'organization_id' => null,
                    'updated_at' => now(),
                ]);
        }

        Cache::forget('db_fixing_membership_org_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully cleaned all {$total} ghost organization ID(s) to NULL.";
    }

    /**
     * Standardize all empty string organization_ids ("") to pure NULL.
     */
    public function standardizeAllEmpty(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $updated = DB::connection('mongodb')->table('membership')
            ->where('organization_id', '')
            ->update([
                'organization_id' => null,
                'updated_at' => now(),
            ]);

        Cache::forget('db_fixing_membership_org_issues');
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Completed! Successfully standardized {$updated} empty string organization_id(s) to pure NULL.";
    }

    /**
     * Open Organization Assignment Modal.
     */
    public function openAssignModal(string $membershipId, string $membershipType = ''): void
    {
        $this->assignMembershipId = $membershipId;
        $this->assignMembershipType = $membershipType;
        $this->orgSearch = '';
        $this->orgSearchResults = [];
        $this->showAssignModal = true;

        // Preload default popular/active organizations
        $res = Organization::searchActive('', 10);
        $this->orgSearchResults = $res['data'] ?? [];
    }

    /**
     * Close Organization Assignment Modal.
     */
    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignMembershipId = '';
        $this->assignMembershipType = '';
        $this->orgSearch = '';
        $this->orgSearchResults = [];
    }

    /**
     * Search organizations in real-time as admin types.
     */
    public function updatedOrgSearch(string $val): void
    {
        $query = trim($val);
        $res = Organization::searchActive($query, 15);
        $this->orgSearchResults = $res['data'] ?? [];
    }

    /**
     * Assign a chosen Organization to the Membership record.
     */
    public function assignOrganization(string $orgId, string $orgName = ''): void
    {
        $mid = $this->assignMembershipId;
        if (empty($mid)) {
            $this->statusMessage = 'No membership was selected for assignment.';
            $this->closeAssignModal();

            return;
        }

        $mongoMid = $this->toMongoId($mid);

        DB::connection('mongodb')->table('membership')
            ->where(function ($q) use ($mid, $mongoMid) {
                $q->where('_id', $mongoMid)->orWhere('_id', $mid);
            })->update([
                'organization_id' => $orgId,
                'updated_at' => now(),
            ]);

        Cache::forget('db_fixing_membership_org_issues');
        $this->selectedRows = array_values(array_diff($this->selectedRows, ["ghost_{$mid}", "missing_{$mid}"]));
        $this->statusMessage = "Successfully assigned organization [".($orgName ?: $orgId)."] to Membership #{$mid}!";
        $this->closeAssignModal();
    }

    /**
     * Clear issue cache manually and refresh view.
     */
    public function refreshScan(): void
    {
        Cache::forget('db_fixing_membership_org_issues');
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = 'Refreshed! Fresh scan completed against database.';
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
            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Membership</span>
        </div>

        <!-- Tool Switcher Pills -->
        <div class="inline-flex rounded-lg bg-zinc-100 dark:bg-zinc-800 p-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md bg-white dark:bg-zinc-900 text-lime-700 dark:text-lime-300 shadow-xs">
                <flux:icon.building-office-2 class="size-3.5" />
                <span>Organization ID Fixer</span>
            </span>
            <a href="{{ route('admin.database-fixing.membership.reviewer-profile') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-md text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200">
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
                <flux:icon.information-circle class="size-4 text-teal-500" />
                <span>Before You DO Action please read this what is do. (Click me)</span>
            </span>
            <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }" />
        </button>

        <div x-show="open" x-cloak x-collapse class="p-5 border-t border-zinc-200 dark:border-zinc-700 text-sm text-zinc-600 dark:text-zinc-300 space-y-4 bg-white dark:bg-zinc-800">
            <div>
                <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-base mb-1">What does the Membership organization_id Fixer do?</h4>
                <p>
                    This tool scans all <strong>Membership</strong> records in MongoDB and specifically checks the integrity of the <code>organization_id</code> field. It detects non-existent (ghost) organization IDs and missing or empty values.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="p-3.5 rounded-lg border border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20">
                    <span class="font-bold text-rose-800 dark:text-rose-300 block mb-1">1. Ghost Organization IDs</span>
                    The <code>organization_id</code> field stores an ID that does NOT exist in the <code>organization</code> collection (due to deleted organizations or legacy test imports). Clicking <strong>Clean to NULL</strong> safely removes the invalid ghost reference, or click <strong>Assign Org</strong> to re-link to a valid organization.
                </div>

                <div class="p-3.5 rounded-lg border border-amber-200 bg-amber-50/50 dark:border-amber-900/40 dark:bg-amber-950/20">
                    <span class="font-bold text-amber-800 dark:text-amber-300 block mb-1">2. No Organization ID (Missing / Empty)</span>
                    The <code>organization_id</code> field is either <code>null</code> or an empty string <code>""</code>. You can click <strong>Assign Org</strong> to attach an organization, or click <strong>Standardize to NULL</strong> to ensure clean schema compliance.
                </div>
            </div>
        </div>
    </div>

    {{-- Hero Summary Banner (High-contrast Deep Teal Theme) --}}
    <div 
        class="rounded-2xl p-6 sm:p-8 text-white relative overflow-hidden shadow-xl"
        style="background: linear-gradient(135deg, #042f2e 0%, #115e59 40%, #0f766e 100%); color: #ffffff;"
    >
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-3 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-white/15 backdrop-blur-md border border-white/20">
                        <flux:icon.building-office-2 class="size-7 text-amber-300" />
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-white drop-shadow-sm">Membership Organization ID Fixer</h2>
                        <p class="text-xs sm:text-sm text-teal-100 font-medium">Ghost organization cleaner, empty ID standardizer & organization relinker</p>
                    </div>
                </div>
                <p class="text-xs sm:text-sm text-teal-100/90 leading-relaxed">
                    Scans all Membership records in MongoDB for <code>organization_id</code> issues. Finds ghost IDs that reference non-existent organizations and records with missing/empty organization IDs, allowing single-click purging to <strong>NULL</strong> or instant re-linking to valid organizations.
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
                    @if (($counts['ghost_organization_id'] ?? 0) > 0)
                        <button 
                            type="button" 
                            wire:click="cleanAllGhosts" 
                            wire:confirm="Are you sure you want to clean all {{ $counts['ghost_organization_id'] }} ghost organization IDs to NULL?"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-bold shadow-md cursor-pointer transition-all hover:brightness-110 active:scale-95"
                            style="background-color: #f43f5e; color: #ffffff; border: 1px solid #e11d48;"
                        >
                            <flux:icon.trash class="size-3.5 text-white" />
                            Clean All Ghosts ({{ $counts['ghost_organization_id'] }})
                        </button>
                    @endif
                </div>
            </div>

            {{-- Stat counter cards with high contrast --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-3 w-full lg:w-auto">
                <div 
                    class="rounded-xl p-3.5 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['all']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-teal-200 mt-0.5">Total Issues</div>
                </div>
                <div 
                    class="rounded-xl p-3.5 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['ghost_organization_id']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-rose-300 mt-0.5">Ghost Org IDs</div>
                </div>
                <div 
                    class="rounded-xl p-3.5 text-center border transition-all"
                    style="background-color: rgba(15, 23, 42, 0.4); border-color: rgba(255, 255, 255, 0.2);"
                >
                    <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">{{ number_format($counts['missing_organization_id']) }}</div>
                    <div class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-amber-200 mt-0.5">No Org ID</div>
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
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'all' ? 'bg-teal-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    All Issues
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'all' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['all'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('ghost_organization_id')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'ghost_organization_id' ? 'bg-teal-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    Ghost Org IDs
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'ghost_organization_id' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['ghost_organization_id'] }}</span>
                </button>
                <button 
                    type="button" 
                    wire:click="setTab('missing_organization_id')" 
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all flex items-center gap-1.5 {{ $tab === 'missing_organization_id' ? 'bg-teal-600 text-white shadow-xs' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-200' }}"
                >
                    No Org ID
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'missing_organization_id' ? 'bg-white/20 text-white' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">{{ $counts['missing_organization_id'] }}</span>
                </button>
            </div>

            {{-- Search Input --}}
            <div class="relative w-full sm:w-64">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search type, org ID, user..." 
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
                        wire:click="cleanSelected" 
                        wire:loading.attr="disabled"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-teal-600 hover:bg-teal-700 text-white shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                    >
                        <flux:icon.trash class="size-3.5" />
                        Clean Selected to NULL ({{ count($selectedRows) }})
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if ($tab === 'ghost_organization_id' && $counts['ghost_organization_id'] > 0)
                    <button 
                        type="button" 
                        wire:click="cleanAllGhosts" 
                        wire:confirm="Are you sure you want to clean all {{ $counts['ghost_organization_id'] }} ghost organization IDs to NULL?"
                        wire:loading.attr="disabled"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                    >
                        <flux:icon.trash class="size-3.5" />
                        Clean All Ghosts ({{ $counts['ghost_organization_id'] }})
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
                                class="rounded border-zinc-300 text-teal-600 focus:ring-teal-500 size-4 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3">Membership Type & Details</th>
                        <th class="px-4 py-3">Current organization_id</th>
                        <th class="px-4 py-3">Owner User</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Issue Detected</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-700 dark:text-zinc-200">
                    @forelse ($issues as $item)
                        <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-700/30 transition-colors {{ in_array($item['key'], $selectedRows, true) ? 'bg-teal-50/40 dark:bg-teal-950/20' : '' }}">
                            {{-- Checkbox --}}
                            <td class="px-4 py-3 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedRows" 
                                    value="{{ $item['key'] }}" 
                                    class="rounded border-zinc-300 text-teal-600 focus:ring-teal-500 size-4 cursor-pointer"
                                />
                            </td>

                            {{-- Membership Details --}}
                            <td class="px-4 py-3 max-w-xs">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm">
                                    {{ !empty($item['membership_type']) ? $item['membership_type'] : 'General Membership' }}
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-2 mt-0.5">
                                    @if (!empty($item['join_date']) || !empty($item['end_date']))
                                        <span>
                                            {{ $item['join_date'] ?: 'N/A' }} — {{ $item['continue_membership'] ? 'Present (Ongoing)' : ($item['end_date'] ?: 'N/A') }}
                                        </span>
                                    @endif
                                </div>
                                @if (!empty($item['website_url']))
                                    <div class="text-[10px] text-teal-600 dark:text-teal-400 truncate max-w-xs mt-0.5" title="{{ $item['website_url'] }}">
                                        {{ $item['website_url'] }}
                                    </div>
                                @endif
                                <div class="text-[10px] text-zinc-400 font-mono mt-0.5">
                                    ID: {{ $item['membership_id'] }}
                                </div>
                            </td>

                            {{-- Current organization_id --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($item['type'] === 'ghost_organization_id')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-800/80">
                                        <flux:icon.exclamation-triangle class="size-3.5 text-rose-500 shrink-0" />
                                        <span>{{ $item['organization_id'] }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ $item['organization_id'] }}
                                    </span>
                                @endif
                            </td>

                            {{-- Owner User --}}
                            <td class="px-4 py-3 max-w-[200px]">
                                @if (!empty($item['user_name']))
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ $item['user_name'] }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">
                                        {{ $item['user_email'] }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-mono">
                                        User: {{ $item['user_id'] }}
                                    </div>
                                @elseif (!empty($item['user_id']))
                                    <span class="font-mono text-zinc-600 dark:text-zinc-400 text-[11px] block truncate">
                                        User: {{ $item['user_id'] }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                        No User
                                    </span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (in_array($item['status'], [1, '1'], true))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                                        1 (Active)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">
                                        0 (Inactive)
                                    </span>
                                @endif
                            </td>

                            {{-- Issue Detected --}}
                            <td class="px-4 py-3 max-w-xs">
                                <div class="mb-1">
                                    @if ($item['type'] === 'ghost_organization_id')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            {{ $item['type_label'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-zinc-600 dark:text-zinc-400 leading-tight">
                                    {{ $item['summary'] }}
                                </p>
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button 
                                        type="button" 
                                        wire:click="openAssignModal('{{ $item['membership_id'] }}', '{{ addslashes($item['membership_type']) }}')" 
                                        class="px-2.5 py-1 rounded-md text-xs font-semibold cursor-pointer transition-all bg-teal-600 hover:bg-teal-700 text-white shadow-2xs hover:brightness-105 active:scale-95"
                                    >
                                        Assign Org
                                    </button>

                                    <button 
                                        type="button" 
                                        wire:click="cleanSingleToNull('{{ $item['key'] }}')" 
                                        wire:loading.attr="disabled"
                                        class="px-2.5 py-1 rounded-md text-xs font-semibold cursor-pointer transition-all shadow-2xs hover:brightness-105 active:scale-95 {{ $item['type'] === 'ghost_organization_id' ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-zinc-600 hover:bg-zinc-700 text-white' }}"
                                    >
                                        {{ $item['fix_label'] }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <flux:icon.check-badge class="size-10 text-emerald-500 mx-auto" />
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">No Issues Found</div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        All membership organization IDs are clean, valid, and consistent.
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

    {{-- Assign Organization Modal (Alpine.js Powered per Boost Rules) --}}
    @if ($showAssignModal)
        <div 
            x-data 
            @keydown.escape.window="$wire.closeAssignModal()" 
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title" 
            role="dialog" 
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Backdrop --}}
                <div 
                    wire:click="closeAssignModal" 
                    class="fixed inset-0 bg-zinc-900/60 backdrop-blur-xs transition-opacity" 
                    aria-hidden="true"
                ></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal Panel --}}
                <div 
                    class="inline-block align-bottom bg-white dark:bg-zinc-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-zinc-200 dark:border-zinc-700"
                >
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-xl bg-teal-50 dark:bg-teal-950/50 border border-teal-200 dark:border-teal-800 text-teal-600 dark:text-teal-400">
                                    <flux:icon.building-office-2 class="size-5" />
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100" id="modal-title">
                                        Assign Organization
                                    </h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        Membership: <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $assignMembershipType ?: 'General Membership' }}</span> (ID: <code class="text-[10px] font-mono">{{ $assignMembershipId }}</code>)
                                    </p>
                                </div>
                            </div>
                            <button 
                                type="button" 
                                wire:click="closeAssignModal" 
                                class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors cursor-pointer"
                            >
                                <flux:icon.x-mark class="size-5" />
                            </button>
                        </div>

                        {{-- Search Input --}}
                        <div class="relative">
                            <flux:input 
                                wire:model.live.debounce.300ms="orgSearch" 
                                placeholder="Search by organization name, city, country..." 
                                icon="magnifying-glass"
                                clearable
                                autofocus
                            />
                        </div>

                        {{-- Results List --}}
                        <div class="max-h-72 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-700/60 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            @forelse ($orgSearchResults as $org)
                                <div class="p-3 hover:bg-teal-50/50 dark:hover:bg-zinc-700/50 transition-colors flex items-center justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 truncate">
                                            {{ $org['name'] }}
                                        </div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center gap-2 mt-0.5">
                                            @if (!empty($org['city']) || !empty($org['country']))
                                                <span>{{ implode(', ', array_filter([$org['city'], $org['country']])) }}</span>
                                            @endif
                                            <span class="font-mono text-[10px] text-zinc-400">ID: {{ $org['id'] }}</span>
                                        </div>
                                    </div>
                                    <button 
                                        type="button" 
                                        wire:click="assignOrganization('{{ $org['id'] }}', '{{ addslashes($org['name']) }}')" 
                                        class="px-3 py-1 rounded-md text-xs font-bold bg-teal-600 hover:bg-teal-700 text-white shadow-2xs transition-all active:scale-95 shrink-0 cursor-pointer"
                                    >
                                        Select
                                    </button>
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
                                    No matching organizations found in the database.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="px-6 py-3 bg-zinc-50 dark:bg-zinc-900/50 border-t border-zinc-200 dark:border-zinc-700 flex justify-end">
                        <button 
                            type="button" 
                            wire:click="closeAssignModal" 
                            class="px-4 py-2 rounded-lg text-xs font-semibold border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 transition-colors cursor-pointer"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

