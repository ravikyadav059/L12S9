<?php

use App\Models\ReviewerProfile;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('ReviewerProfile — RoleInResearchJournal Orphan Cleaner - Admin')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $statusMessage = 'Ready';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    // Batch-processing state
    public bool $isProcessing = false;

    public int $batchProcessed = 0;

    public int $batchFixed = 0;

    public int $batchSkipped = 0;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function toMongoId(string $id): ObjectId|string
    {
        if (strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                return new ObjectId($id);
            } catch (\Exception $e) {
                return $id;
            }
        }

        return $id;
    }

    /**
     * Inspect RoleInResearchJournal IDs against the role_in_research_journals collection.
     */
    protected function analyzeRoleIds(array $rawIds): array
    {
        $objIds = [];
        $strIds = [];
        $normalizedInput = [];

        foreach ($rawIds as $raw) {
            if (empty($raw)) {
                continue;
            }
            $str = (string) $raw;
            $normalizedInput[] = $str;
            $strIds[] = $str;
            if (strlen($str) === 24 && ctype_xdigit($str)) {
                try {
                    $objIds[] = new ObjectId($str);
                } catch (\Exception $e) {
                    // Ignore malformed ID
                }
            }
        }

        $normalizedInput = array_values(array_unique($normalizedInput));

        if (empty($normalizedInput)) {
            return [
                'raw_count' => 0,
                'active_ids' => [],
                'inactive_ids' => [],
                'orphan_ids' => [],
                'cleaned_ids' => [],
                'has_issues' => false,
            ];
        }

        // Query role_in_research_journals collection
        $matchedRoles = RoleInResearchJournals::whereIn('_id', array_merge($objIds, $strIds))
            ->get(['_id', 'status', 'role', 'journal_title']);

        $foundMap = [];
        foreach ($matchedRoles as $role) {
            $foundMap[(string) $role->_id] = $role;
        }

        $activeIds = [];
        $inactiveIds = [];
        $orphanIds = [];
        $cleanedIds = [];

        foreach ($normalizedInput as $rid) {
            if (isset($foundMap[$rid])) {
                $roleDoc = $foundMap[$rid];
                $roleStatus = (string) ($roleDoc->status ?? 0);

                if (in_array($roleStatus, ['1', 'active'], true) || $roleDoc->status === 1) {
                    $activeIds[] = $rid;
                } else {
                    $inactiveIds[] = $rid;
                }
                $cleanedIds[] = $rid;
            } else {
                $orphanIds[] = $rid;
            }
        }

        return [
            'raw_count' => count($normalizedInput),
            'active_ids' => $activeIds,
            'inactive_ids' => $inactiveIds,
            'orphan_ids' => $orphanIds,
            'cleaned_ids' => $cleanedIds,
            'has_issues' => count($orphanIds) > 0,
        ];
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $paginator = $this->getProfilesQuery()->paginate($this->perPage);
            $this->selectedRows = collect($paginator->items())->map(fn ($p) => (string) $p->_id)->all();
        } else {
            $this->selectedRows = [];
        }
    }

    protected function getProfilesQuery()
    {
        $query = ReviewerProfile::query();

        // Target profiles that have RoleInResearchJournal arrays
        $query->where(function ($q) {
            $q->whereNotNull('RoleInResearchJournal')
                ->where('RoleInResearchJournal', '!=', []);
        });

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $regex = new \MongoDB\BSON\Regex($term, 'i');

            // Search users across name, first_name, last_name, email, and slug
            $matchingUsers = User::where('name', 'regex', $regex)
                ->orWhere('first_name', 'regex', $regex)
                ->orWhere('last_name', 'regex', $regex)
                ->orWhere('email', 'regex', $regex)
                ->orWhere('slug', 'regex', $regex)
                ->get(['_id']);

            $userIdsStr = [];
            $userIdsObj = [];
            foreach ($matchingUsers as $u) {
                $uid = (string) $u->_id;
                $userIdsStr[] = $uid;
                if (strlen($uid) === 24 && ctype_xdigit($uid)) {
                    try {
                        $userIdsObj[] = new ObjectId($uid);
                    } catch (\Exception $e) {
                    }
                }
            }

            $allUserKeys = array_values(array_unique(array_merge($userIdsStr, $userIdsObj)));

            $query->where(function ($q) use ($regex, $allUserKeys) {
                if (! empty($allUserKeys)) {
                    $q->whereIn('user_id', $allUserKeys);
                } else {
                    $q->where('_id', 'regex', $regex);
                }
                $q->orWhere('_id', 'regex', $regex);
            });
        }

        return $query->latest('_id');
    }

    public function with(): array
    {
        $paginator = $this->getProfilesQuery()->paginate($this->perPage);

        // Preload users for display using both ObjectId and string representations
        $rawUserIds = collect($paginator->items())->pluck('user_id')->filter()->unique()->all();
        $userSearchKeys = [];
        foreach ($rawUserIds as $ruid) {
            $ruidStr = (string) $ruid;
            if ($ruidStr !== '') {
                $userSearchKeys[] = $ruidStr;
                if (strlen($ruidStr) === 24 && ctype_xdigit($ruidStr)) {
                    try {
                        $userSearchKeys[] = new ObjectId($ruidStr);
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

        $formatted = [];
        $totalOrphansOnPage = 0;

        foreach ($paginator->items() as $profile) {
            $rawRole = is_array($profile->RoleInResearchJournal) ? $profile->RoleInResearchJournal : (empty($profile->RoleInResearchJournal) ? [] : [(string) $profile->RoleInResearchJournal]);
            $analysis = $this->analyzeRoleIds($rawRole);

            if ($analysis['has_issues']) {
                $totalOrphansOnPage += count($analysis['orphan_ids']);
            }

            $user = isset($userMap[(string) $profile->user_id]) ? $userMap[(string) $profile->user_id] : null;

            $userName = 'Unknown User';
            $userEmail = 'N/A';
            if ($user) {
                $fullName = trim($user->name ?: (($user->first_name ?? '').' '.($user->last_name ?? '')));
                $userName = $fullName !== '' ? $fullName : ($user->email ?? 'User #'.$profile->user_id);
                $userEmail = $user->email ?? 'N/A';
            } elseif (! empty($profile->user_id)) {
                $userName = 'User #'.substr((string) $profile->user_id, 0, 8);
            }

            $formatted[] = [
                'id' => (string) $profile->_id,
                'user_id' => (string) $profile->user_id,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'raw_count' => count($rawRole),
                'active_count' => count($analysis['active_ids']),
                'inactive_count' => count($analysis['inactive_ids']),
                'orphan_count' => count($analysis['orphan_ids']),
                'orphan_ids' => $analysis['orphan_ids'],
                'cleaned_ids' => $analysis['cleaned_ids'],
                'needs_fix' => $analysis['has_issues'],
            ];
        }

        $totalProfilesCount = ReviewerProfile::whereNotNull('RoleInResearchJournal')->where('RoleInResearchJournal', '!=', [])->count();

        return [
            'profiles' => $formatted,
            'paginator' => $paginator,
            'totalProfilesCount' => $totalProfilesCount,
            'totalOrphansOnPage' => $totalOrphansOnPage,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);
        $profile = ReviewerProfile::where(function ($q) use ($id, $mongoId) {
            $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
        })->first();

        if (! $profile) {
            $this->statusMessage = "Profile [{$id}] not found.";

            return;
        }

        $rawRole = is_array($profile->RoleInResearchJournal) ? $profile->RoleInResearchJournal : (empty($profile->RoleInResearchJournal) ? [] : [(string) $profile->RoleInResearchJournal]);
        $analysis = $this->analyzeRoleIds($rawRole);
        $orphanCount = count($analysis['orphan_ids']);

        // Update MongoDB ReviewerProfile with cleaned RoleInResearchJournal array (ghosts removed, valid records preserved)
        DB::connection('mongodb')->table('reviewer_profile')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->update([
                'RoleInResearchJournal' => $analysis['cleaned_ids'],
                'updated_at' => now(),
            ]);

        $this->statusMessage = "Fixed profile [{$id}]: Removed {$orphanCount} orphan RoleInResearchJournal IDs. Valid records preserved.";
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            $this->statusMessage = 'No profiles selected.';

            return;
        }

        $count = 0;
        foreach ($this->selectedRows as $id) {
            $this->fixSingle($id);
            $count++;
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully cleaned RoleInResearchJournal arrays for {$count} selected profiles.";
    }

    public function startBatchFix(): void
    {
        $this->isProcessing = true;
        $this->batchProcessed = 0;
        $this->batchFixed = 0;
        $this->batchSkipped = 0;
        $this->statusMessage = 'Starting batch synchronization of RoleInResearchJournal arrays...';
    }

    public function processBatch(): void
    {
        if (! $this->isProcessing) {
            return;
        }

        $batchSize = 25;
        $profiles = ReviewerProfile::whereNotNull('RoleInResearchJournal')
            ->where('RoleInResearchJournal', '!=', [])
            ->skip($this->batchProcessed)
            ->take($batchSize)
            ->get();

        if ($profiles->isEmpty()) {
            $this->isProcessing = false;
            $this->statusMessage = "Batch complete! Scanned {$this->batchProcessed} profiles. Cleaned {$this->batchFixed} profiles.";

            return;
        }

        foreach ($profiles as $profile) {
            $id = (string) $profile->_id;
            $rawRole = is_array($profile->RoleInResearchJournal) ? $profile->RoleInResearchJournal : (empty($profile->RoleInResearchJournal) ? [] : [(string) $profile->RoleInResearchJournal]);
            $analysis = $this->analyzeRoleIds($rawRole);

            if ($analysis['has_issues']) {
                $mongoId = $this->toMongoId($id);
                DB::connection('mongodb')->table('reviewer_profile')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update([
                        'RoleInResearchJournal' => $analysis['cleaned_ids'],
                        'updated_at' => now(),
                    ]);
                $this->batchFixed++;
            } else {
                $this->batchSkipped++;
            }

            $this->batchProcessed++;
        }

        $this->statusMessage = "Processing... Scanned {$this->batchProcessed} profiles. Cleaned {$this->batchFixed} so far.";
    }

    public function stopBatchFix(): void
    {
        $this->isProcessing = false;
        $this->statusMessage = "Batch paused by user. Processed {$this->batchProcessed} profiles.";
    }
}; ?>

<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                <a href="{{ route('admin.database-fixing.index') }}" class="hover:text-indigo-600 transition-colors">Database Fixing</a>
                <span>/</span>
                <a href="{{ route('admin.database-fixing.reviewer-profile.index') }}" class="hover:text-indigo-600 transition-colors">ReviewerProfile</a>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200">RoleInResearchJournal (Orphan Cleaner)</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-base">
                    🏛️
                </div>
                ReviewerProfile — RoleInResearchJournal Orphan Cleaner
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                Scans <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-indigo-600 font-mono text-[11px]">reviewer_profile.RoleInResearchJournal</code> arrays, detects and purges non-existent (ghost) role IDs, and preserves both active & inactive existing role records.
            </p>
        </div>

        <!-- Global Actions -->
        <div class="flex items-center gap-2">
            @if(!$isProcessing)
                <button
                    wire:click="startBatchFix"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm shadow-indigo-500/20 transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Fix All Profiles (Auto Runner)
                </button>
            @else
                <button
                    wire:click="stopBatchFix"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm shadow-rose-500/20 transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Pause Auto Runner
                </button>
            @endif
        </div>
    </div>

    <!-- Quick Category Switcher Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200 dark:border-slate-800 text-xs">
        <span class="text-slate-400 font-semibold px-2">Cleaners:</span>
        <a href="{{ route('admin.database-fixing.reviewer-profile.orphan-cleaner.publication') }}" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 font-medium">📚 publication</a>
        <a href="{{ route('admin.database-fixing.reviewer-profile.orphan-cleaner.experience') }}" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 font-medium">📋 experience</a>
        <a href="{{ route('admin.database-fixing.reviewer-profile.orphan-cleaner.seminar') }}" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 font-medium">🎤 seminar</a>
        <a href="{{ route('admin.database-fixing.reviewer-profile.orphan-cleaner.role-in-research-journal') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold">🏛️ RoleInResearchJournal</a>
    </div>

    <!-- Live Status / Runner Banner -->
    @if($isProcessing)
        <div wire:poll.1000ms="processBatch" class="p-4 rounded-2xl bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-emerald-500/10 border border-indigo-500/20 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-5 h-5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                <div>
                    <div class="text-xs font-bold text-indigo-900 dark:text-indigo-200">Auto Runner In Progress</div>
                    <div class="text-[11px] text-slate-600 dark:text-slate-400">Scanned: <span class="font-bold">{{ $batchProcessed }}</span> | Fixed: <span class="font-bold text-emerald-600">{{ $batchFixed }}</span> | Clean/Skipped: <span class="font-bold">{{ $batchSkipped }}</span></div>
                </div>
            </div>
            <button wire:click="stopBatchFix" class="px-3 py-1 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] hover:bg-slate-300">Stop</button>
        </div>
    @elseif($statusMessage !== 'Ready')
        <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>{{ $statusMessage }}</span>
            </div>
            <button wire:click="$set('statusMessage', 'Ready')" class="text-slate-400 hover:text-slate-600 text-xs font-bold">×</button>
        </div>
    @endif

    <!-- Search & Bulk Actions Bar -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="relative w-full sm:w-72">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search researcher name, email, or ID..."
                    class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            @if($search)
                <button wire:click="$set('search', '')" class="text-xs text-slate-400 hover:text-slate-600">Clear</button>
            @endif
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            @if(!empty($selectedRows))
                <button
                    wire:click="fixSelected"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-all cursor-pointer">
                    Fix Selected ({{ count($selectedRows) }})
                </button>
            @endif
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/75 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4 w-10">
                            <input
                                type="checkbox"
                                wire:click="toggleSelectAll"
                                {{ $selectAll ? 'checked' : '' }}
                                class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="py-3 px-4">Researcher / Profile</th>
                        <th class="py-3 px-4 text-center">Raw Array Count</th>
                        <th class="py-3 px-4 text-center">Valid Active (Status 1)</th>
                        <th class="py-3 px-4 text-center">Valid Inactive (Status 0)</th>
                        <th class="py-3 px-4 text-center">Ghost / Orphan IDs</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                    @forelse($profiles as $row)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors {{ $row['needs_fix'] ? 'bg-amber-500/[0.02]' : '' }}">
                            <td class="py-3 px-4">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedRows"
                                    value="{{ $row['id'] }}"
                                    class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $row['user_name'] }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $row['user_email'] }}</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">Profile: {{ $row['id'] }}</div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[11px] font-bold">
                                    {{ $row['raw_count'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 font-mono text-[11px] font-bold border border-emerald-200 dark:border-emerald-800/40">
                                    {{ $row['active_count'] }} Active
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 font-mono text-[11px] font-bold border border-amber-200 dark:border-amber-800/40">
                                    {{ $row['inactive_count'] }} Inactive
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($row['orphan_count'] > 0)
                                    <div class="inline-flex flex-col items-center">
                                        <span class="px-2 py-0.5 rounded-full bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 font-mono text-[11px] font-bold border border-rose-200 dark:border-rose-800/40">
                                            {{ $row['orphan_count'] }} Ghost IDs
                                        </span>
                                        <div class="text-[9px] text-rose-500 font-mono mt-0.5 max-w-[140px] truncate" title="{{ implode(', ', $row['orphan_ids']) }}">
                                            {{ implode(', ', $row['orphan_ids']) }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-[11px]">0 (Clean)</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($row['needs_fix'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 font-bold text-[10px]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                        Has Ghost IDs
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 font-bold text-[10px]">
                                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Clean
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button
                                    wire:click="fixSingle('{{ $row['id'] }}')"
                                    class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-bold text-[11px] transition-colors cursor-pointer">
                                    Fix Record
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">
                                No reviewer profiles found matching the criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginator->hasPages())
            <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
