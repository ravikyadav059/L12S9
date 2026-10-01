<?php

use App\Models\JournalRole;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

new #[Layout('components.layouts.admin')] #[Title('RoleInResearchJournals — role Fixer - Admin')] class extends Component
{
    use WithPagination;

    public array $selectedRows = [];

    public array $assignedRoles = [];

    public bool $selectAll = false;

    public string $statusMessage = 'Ready';

    public int $perPage = 25;

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $data = $this->getProblematicQuery()->take($this->perPage)->get();
            $this->selectedRows = $data->map(function ($item) {
                $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);

                return $rawId ? (string) $rawId : '';
            })->filter()->all();
        } else {
            $this->selectedRows = [];
        }
    }

    protected function getProblematicQuery()
    {
        return DB::connection('mongodb')->table('role_in_research_journals')
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->whereRaw(['role' => ['$not' => ['$regex' => '^[0-9a-fA-F]{24}$']]]);
    }

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $totalCount = $query->count();
        $paginator = $query->latest('_id')->paginate($this->perPage);

        // Fetch all JournalRoles
        $allJournalRoles = JournalRole::orderBy('name')->get();
        $roleNameMap = [];
        foreach ($allJournalRoles as $jr) {
            $norm = strtolower(trim($jr->name));
            $roleNameMap[$norm] = $jr;
        }

        // Batch fetch users
        $userIds = [];
        foreach ($paginator as $item) {
            $uId = is_array($item) ? ($item['user_id'] ?? null) : ($item->user_id ?? null);
            if ($uId) {
                $userIds[] = (string) $uId;
            }
        }

        $users = ! empty($userIds)
            ? User::whereIn('_id', array_unique($userIds))->select(['_id', 'first_name', 'last_name', 'fullname', 'slug'])->get()->keyBy(fn ($u) => (string) $u->_id)
            : collect();

        $formatted = [];
        foreach ($paginator as $item) {
            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $id = $rawId ? (string) $rawId : '';
            $rawRole = is_array($item) ? ($item['role'] ?? '') : ($item->role ?? '');
            $strRole = trim((string) $rawRole);
            $normRole = strtolower($strRole);

            // Exact match or partial search match
            $matchedTarget = $roleNameMap[$normRole] ?? null;
            if (! $matchedTarget) {
                foreach ($allJournalRoles as $jr) {
                    if (str_contains(strtolower($jr->name), $normRole) || str_contains($normRole, strtolower($jr->name))) {
                        $matchedTarget = $jr;
                        break;
                    }
                }
            }

            $targetId = $matchedTarget ? (string) $matchedTarget->_id : '';
            $targetName = $matchedTarget ? $matchedTarget->name : '';

            // Auto-initialize assigned role if not explicitly changed
            if (! isset($this->assignedRoles[$id]) && $targetId !== '') {
                $this->assignedRoles[$id] = $targetId;
            }

            $uId = is_array($item) ? ($item['user_id'] ?? null) : ($item->user_id ?? null);
            $userObj = $uId ? ($users[(string) $uId] ?? null) : null;
            $userName = $userObj ? (trim(($userObj->first_name ?? '').' '.($userObj->last_name ?? '')) ?: ($userObj->fullname ?? 'User #'.$uId)) : ($uId ? 'User #'.$uId : 'N/A');

            $journalTitle = is_array($item) ? ($item['journal_short_name'] ?? $item['journal_title'] ?? 'Journal') : ($item->journal_short_name ?? $item->journal_title ?? 'Journal');

            $reason = 'Raw String Stored';
            if ($matchedTarget) {
                $reason = "Auto-matched to \"{$matchedTarget->name}\"";
            } else {
                $reason = "No direct match for \"{$strRole}\" (Select manually)";
            }

            $date = '-';
            $createdAt = is_array($item) ? ($item['created_at'] ?? null) : ($item->created_at ?? null);
            if ($createdAt) {
                $date = is_string($createdAt) ? substr($createdAt, 0, 10) : ($createdAt instanceof DateTimeInterface ? $createdAt->format('Y-m-d') : (string) $createdAt);
            }

            $formatted[] = [
                'id' => $id,
                'user_name' => $userName,
                'journal_title' => $journalTitle,
                'broken_value' => "\"{$strRole}\"",
                'target_id' => $targetId,
                'target_name' => $targetName,
                'reason' => $reason,
                'date' => $date,
            ];
        }

        return [
            'problematicCount' => $totalCount,
            'problematicRecords' => $formatted,
            'allJournalRoles' => $allJournalRoles,
            'paginator' => $paginator,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);
        $record = DB::connection('mongodb')->table('role_in_research_journals')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        if (! $record) {
            return;
        }

        // Get selected target role ID from dropdown or auto-match
        $targetRoleId = $this->assignedRoles[$id] ?? null;

        if (! $targetRoleId) {
            $rawRole = is_array($record) ? ($record['role'] ?? '') : ($record->role ?? '');
            $targetRole = $this->findMatchingJournalRole((string) $rawRole);
            $targetRoleId = $targetRole ? (string) $targetRole->_id : null;
        }

        if ($targetRoleId) {
            $roleObj = JournalRole::find($targetRoleId);
            $roleName = $roleObj ? $roleObj->name : $targetRoleId;

            DB::connection('mongodb')->table('role_in_research_journals')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update(['role' => (string) $targetRoleId]);

            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
            unset($this->assignedRoles[$id]);
            $this->statusMessage = "Fixed record #{$id} role to JournalRole: {$roleName} (ID: {$targetRoleId})!";
        } else {
            $this->statusMessage = "Error: Please select a target JournalRole for record #{$id} before fixing.";
        }
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            return;
        }

        $count = 0;
        foreach ($this->selectedRows as $id) {
            $mongoId = $this->toMongoId($id);
            $record = DB::connection('mongodb')->table('role_in_research_journals')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->first();

            if ($record) {
                $targetRoleId = $this->assignedRoles[$id] ?? null;
                if (! $targetRoleId) {
                    $rawRole = is_array($record) ? ($record['role'] ?? '') : ($record->role ?? '');
                    $targetRole = $this->findMatchingJournalRole((string) $rawRole);
                    $targetRoleId = $targetRole ? (string) $targetRole->_id : null;
                }

                if ($targetRoleId) {
                    DB::connection('mongodb')->table('role_in_research_journals')
                        ->where(function ($q) use ($id, $mongoId) {
                            $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                        })
                        ->update(['role' => (string) $targetRoleId]);

                    $count++;
                }
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected record(s) to JournalRole ObjectIds!";
    }

    public function fixAll(): void
    {
        $count = 0;
        $batchSize = 250;

        while (true) {
            $records = $this->getProblematicQuery()->take($batchSize)->get();

            if ($records->isEmpty()) {
                break;
            }

            foreach ($records as $item) {
                $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
                if ($rawId) {
                    $id = (string) $rawId;
                    $mongoId = $this->toMongoId($rawId);
                    $rawRole = is_array($item) ? ($item['role'] ?? '') : ($item->role ?? '');
                    $targetRole = $this->findMatchingJournalRole((string) $rawRole);

                    if ($targetRole) {
                        DB::connection('mongodb')->table('role_in_research_journals')
                            ->where(function ($q) use ($id, $mongoId) {
                                $q->where('_id', $mongoId)->orWhere('_id', $id);
                            })
                            ->update(['role' => (string) $targetRole->_id]);

                        $count++;
                    }
                }
            }

            unset($records);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully converted all {$count} record(s) role to JournalRole ObjectIds!";
    }

    protected function findMatchingJournalRole(string $rawRole): ?JournalRole
    {
        $clean = trim($rawRole);
        if ($clean === '') {
            return null;
        }

        // Exact match (case-insensitive)
        $exact = JournalRole::where('name', 'regex', new Regex('^'.preg_quote($clean, '/').'$', 'i'))->first();
        if ($exact) {
            return $exact;
        }

        // Partial match
        return JournalRole::where('name', 'regex', new Regex(preg_quote($clean, '/'), 'i'))->first();
    }

    protected function toMongoId(mixed $id): mixed
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        if (is_string($id) && strlen(trim($id)) === 24 && ctype_xdigit(trim($id))) {
            return new ObjectId(trim($id));
        }

        return $id;
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">RoleInResearchJournals</strong> &bull; Field: <code class="font-mono text-brand">role</code>
        </span>
    </div>

    <!-- STATS / ACTION CARD -->
    <div class="p-6 rounded-2xl bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    Role in Research Journals — <code class="text-brand">role</code> String to ObjectId Fixer
                </h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $problematicCount > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300' }}">
                    {{ number_format($problematicCount) }} Unfixed Records
                </span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-2xl">
                Finds records in <code class="font-mono text-xs">role_in_research_journals</code> where <code class="font-mono text-xs">role</code> is stored as a raw string (e.g. <em>"Assistant Editor"</em>, <em>"Guest Editor"</em>, <em>"Reviewer"</em>) and maps them to the corresponding <code class="font-mono text-xs">JournalRole</code> MongoDB ObjectId upon clicking <strong>Fix</strong>.
            </p>
            @if ($statusMessage !== 'Ready')
                <div class="mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ $statusMessage }}</span>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if (!empty($selectedRows))
                <button 
                    type="button" 
                    wire:click="fixSelected" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition flex items-center gap-2 cursor-pointer"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Fix Selected ({{ count($selectedRows) }})</span>
                </button>
            @endif

            @if ($problematicCount > 0)
                <button 
                    type="button" 
                    wire:click="fixAll" 
                    wire:confirm="Are you sure you want to fix all {{ number_format($problematicCount) }} records to JournalRole ObjectIds in batches?"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-brand hover:bg-sky-600 text-white shadow-xs transition flex items-center gap-2 cursor-pointer"
                >
                    <svg wire:loading.remove wire:target="fixAll" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg wire:loading wire:target="fixAll" class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Fix All ({{ number_format($problematicCount) }})</span>
                </button>
            @endif
        </div>
    </div>

    <!-- TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-900/80 text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-4 py-3 w-10 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll" 
                                {{ $selectAll ? 'checked' : '' }}
                                class="rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3 w-40">Record ID</th>
                        <th class="px-4 py-3 w-44">User / Member</th>
                        <th class="px-4 py-3 w-36">Journal</th>
                        <th class="px-4 py-3 w-44">Stored Raw String</th>
                        <th class="px-4 py-3 w-64">Target JournalRole (Name & ID)</th>
                        <th class="px-4 py-3">Match Status</th>
                        <th class="px-4 py-3 text-center w-24">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($problematicRecords as $row)
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition">
                            <!-- Checkbox -->
                            <td class="px-4 py-3 text-center">
                                <input 
                                    type="checkbox" 
                                    value="{{ $row['id'] }}" 
                                    wire:model.live="selectedRows"
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand cursor-pointer"
                                />
                            </td>

                            <!-- Record ID -->
                            <td class="px-4 py-3 font-mono text-[11px] text-zinc-600 dark:text-zinc-400">
                                {{ $row['id'] }}
                            </td>

                            <!-- User Name -->
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $row['user_name'] }}
                            </td>

                            <!-- Journal -->
                            <td class="px-4 py-3 font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $row['journal_title'] }}
                            </td>

                            <!-- Current Raw String -->
                            <td class="px-4 py-3 font-bold text-amber-600 dark:text-amber-400 font-mono">
                                {{ $row['broken_value'] }}
                            </td>

                            <!-- Target JournalRole Select / Preview -->
                            <td class="px-4 py-3">
                                <select 
                                    wire:model.live="assignedRoles.{{ $row['id'] }}"
                                    class="w-full text-xs font-semibold py-1.5 px-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-brand focus:border-brand"
                                >
                                    <option value="">-- Choose Journal Role --</option>
                                    @foreach($allJournalRoles as $jr)
                                        <option value="{{ (string)$jr->_id }}">
                                            {{ $jr->name }} (ID: {{ substr((string)$jr->_id, -6) }}...)
                                        </option>
                                    @endforeach
                                </select>
                                @if (!empty($assignedRoles[$row['id']]))
                                    <div class="mt-1 font-mono text-[10px] text-zinc-400 truncate">
                                        Selected ID: {{ $assignedRoles[$row['id']] }}
                                    </div>
                                @endif
                            </td>

                            <!-- Reason / Status -->
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 text-xs">
                                {{ $row['reason'] }}
                            </td>

                            <!-- Action: Fix Button -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['id'] }}')" 
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white transition shadow-2xs cursor-pointer"
                                    title="Click to write JournalRole ObjectId into database"
                                >
                                    Fix
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 mb-3">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">All Records Clean!</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                    Every record in <code class="font-mono">role_in_research_journals</code> is already linked to a standardized <code class="font-mono">JournalRole</code> ObjectId.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        @if ($paginator->hasPages())
            <div class="px-5 py-3 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
