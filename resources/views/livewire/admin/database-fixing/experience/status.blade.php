<?php

use App\Models\Designation;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Experience — Missing status Fixer - Admin')] class extends Component {
    use WithPagination;

    public array $selectedRows = [];
    public bool $selectAll = false;
    public string $statusMessage = 'Ready';
    public int $perPage = 25;

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $data = $this->getProblematicQuery()->take($this->perPage)->get();
            $this->selectedRows = $data->map(function ($item) {
                return $this->extractIdString($item);
            })->filter()->all();
        } else {
            $this->selectedRows = [];
        }
    }

    protected function getProblematicQuery()
    {
        // Target: Only records where the status field is missing, null, or empty string
        // Existing string "1" / "0" and integer 1 / 0 are allowed and preserved
        return DB::connection('mongodb')->table('experience')
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '');
            });
    }

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $totalCount = $query->count();
        $paginator = $query->latest('_id')->paginate($this->perPage);

        $rawItems = [];
        $desigIds = [];
        $orgIds = [];
        $userIds = [];

        foreach ($paginator as $item) {
            $id = $this->extractIdString($item);
            $rawStatus = is_array($item) ? ($item['status'] ?? null) : ($item->status ?? null);
            $userId = is_array($item) ? ($item['user_id'] ?? null) : ($item->user_id ?? null);
            $desigId = is_array($item) ? ($item['designation'] ?? null) : ($item->designation ?? null);
            $orgId = is_array($item) ? ($item['organization_id'] ?? null) : ($item->organization_id ?? null);
            $joinDate = is_array($item) ? ($item['join_date'] ?? null) : ($item->join_date ?? null);
            $endDate = is_array($item) ? ($item['end_date'] ?? null) : ($item->end_date ?? null);
            $currentOrg = is_array($item) ? ($item['current_organization'] ?? null) : ($item->current_organization ?? null);

            $brokenValue = $rawStatus === null ? '<missing / null>' : '"" (Empty String)';
            $reason = 'Document has NO "status" field in MongoDB (Unset / Null)';

            if ($userId) {
                $userIds[] = (string) $userId;
            }
            if ($desigId) {
                $desigIds[] = (string) $desigId;
            }
            if ($orgId) {
                $orgIds[] = (string) $orgId;
            }

            $rawItems[] = [
                'id' => $id,
                'user_id' => (string) ($userId ?: 'N/A'),
                'designation_id' => (string) ($desigId ?: ''),
                'organization_id' => (string) ($orgId ?: ''),
                'broken_value' => $brokenValue,
                'target_value' => 1,
                'reason' => $reason,
                'join_date' => $joinDate,
                'end_date' => $endDate,
                'current_organization' => $currentOrg,
            ];
        }

        // Efficient bulk preloading for user, designation, organization names
        $usersMap = ! empty($userIds) ? User::whereIn('_id', array_unique($userIds))->get()->keyBy(fn ($u) => (string) $u->_id) : collect();
        $desigMap = ! empty($desigIds) ? Designation::whereIn('_id', array_unique($desigIds))->get()->keyBy(fn ($d) => (string) $d->_id) : collect();
        $orgMap = ! empty($orgIds) ? Organization::whereIn('_id', array_unique($orgIds))->get()->keyBy(fn ($o) => (string) $o->_id) : collect();

        $formatted = [];
        foreach ($rawItems as $row) {
            $u = $usersMap->get($row['user_id']);
            $d = $desigMap->get($row['designation_id']);
            $o = $orgMap->get($row['organization_id']);

            $row['user_name'] = $u ? $u->name : null;
            $row['user_email'] = $u ? $u->email : null;
            $row['user_unique_id'] = $u ? ($u->unique_id ?? null) : null;

            $row['designation_title'] = $d ? ($d->designation ?? $d->name ?? $row['designation_id']) : ($row['designation_id'] ?: 'N/A');
            $row['organization_name'] = $o ? ($o->organization_name ?? $o->name ?? $row['organization_id']) : ($row['organization_id'] ?: 'N/A');

            $formatted[] = $row;
        }

        return [
            'problematicCount' => $totalCount,
            'problematicRecords' => $formatted,
            'paginator' => $paginator,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);

        DB::connection('mongodb')->table('experience')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->update(['status' => 1]);

        $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
        $this->statusMessage = "Fixed Experience record #{$id} status to integer 1 (Active)!";
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            return;
        }

        $mongoIds = [];
        foreach ($this->selectedRows as $id) {
            $mongoIds[] = $this->toMongoId($id);
        }

        $count = DB::connection('mongodb')->table('experience')
            ->whereIn('_id', $mongoIds)
            ->update(['status' => 1]);

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected Experience record(s) to integer status 1!";
    }

    public function fixAll(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $count = 0;
        $batchSize = 250;

        while (true) {
            $records = $this->getProblematicQuery()->take($batchSize)->get();

            if ($records->isEmpty()) {
                break;
            }

            $mongoIds = [];
            foreach ($records as $item) {
                $idStr = $this->extractIdString($item);
                if ($idStr) {
                    $mongoIds[] = $this->toMongoId($idStr);
                }
            }

            if (! empty($mongoIds)) {
                DB::connection('mongodb')->table('experience')
                    ->whereIn('_id', $mongoIds)
                    ->update(['status' => 1]);
                $count += count($mongoIds);
            } else {
                break;
            }

            unset($records, $mongoIds);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully assigned native integer status 1 to all {$count} Experience record(s)!";
    }

    protected function extractIdString(mixed $item): string
    {
        $rawId = is_array($item)
            ? ($item['_id'] ?? $item['id'] ?? null)
            : ($item->_id ?? $item->id ?? null);

        if ($rawId instanceof ObjectId) {
            return (string) $rawId;
        }

        if (is_array($rawId) && isset($rawId['$oid'])) {
            return (string) $rawId['$oid'];
        }

        if (is_object($rawId) && isset($rawId->{'$oid'})) {
            return (string) $rawId->{'$oid'};
        }

        return (string) ($rawId ?? '');
    }

    protected function toMongoId(mixed $id): mixed
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        if (is_array($id) && isset($id['$oid'])) {
            return new ObjectId($id['$oid']);
        }

        if (is_object($id) && isset($id->{'$oid'})) {
            return new ObjectId($id->{'$oid'});
        }

        if (is_string($id) && strlen(trim($id)) === 24 && ctype_xdigit(trim($id))) {
            return new ObjectId(trim($id));
        }

        return $id;
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
        <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-700/60">
                status (Missing Status Fixer)
            </span>
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
            <span>Experience &mdash; Missing status Fixer</span>
            <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                Target: status = 1 (Active)
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
                class="mt-2 p-3.5 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-600 dark:text-zinc-300 space-y-1.5"
            >
                <p class="font-medium text-zinc-800 dark:text-zinc-200">
                    This tool scans all MongoDB documents in the <code class="font-mono text-rose-600 dark:text-rose-400">experience</code> collection to locate records that have <strong>no status field at all</strong> (missing / unset / null).
                </p>
                <p class="text-zinc-600 dark:text-zinc-300">
                    Existing records that already store string <code class="font-mono text-zinc-700 dark:text-zinc-300">"1"</code> or integer <code class="font-mono text-emerald-600">1</code> are considered valid and are preserved. When you click <strong>Fix to 1</strong>, <strong>Fix Selected</strong>, or <strong>Fix All</strong>, it directly updates only the records with no status in MongoDB, setting <code class="font-mono text-emerald-600 font-bold">status: 1</code> (Active).
                </p>
                <p class="text-zinc-500 text-[11px] pt-1">
                    Missing Status Records Detected: <strong class="text-rose-600 font-bold">{{ $problematicCount }}</strong>
                </p>
            </div>
        </div>
    </div>

    <!-- ROSE HERO BANNER CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-700 via-pink-600 to-rose-500 p-6 sm:p-7 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="space-y-2 max-w-3xl">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                        Experience &mdash; Missing status Fixer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-rose-100 leading-relaxed">
                    This tool finds Experience records where the <code class="bg-rose-800/60 px-1.5 py-0.5 rounded font-mono text-white">status</code> field is completely <strong class="underline">missing</strong> or null in MongoDB, and assigns them the standardized integer <code class="bg-rose-800/60 px-1.5 py-0.5 rounded font-mono text-emerald-200">1</code> (Active).
                </p>

                <!-- Note -->
                <div class="pt-1">
                    <p class="text-xs text-rose-100">
                        Existing records with string <code class="font-mono text-white bg-white/10 px-1 rounded">"1"</code> or integer <code class="font-mono text-white bg-white/10 px-1 rounded">1</code> are preserved and supported.
                    </p>
                </div>
            </div>

            <!-- Right Counter Badge -->
            <div class="shrink-0">
                <div class="px-6 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[180px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-rose-100 mt-0.5">
                        No Status (Missing / Unset)
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ACTION BUTTONS TOOLBAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <!-- Left Buttons -->
        <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Fix All -->
            <button 
                type="button" 
                wire:click="fixAll"
                wire:loading.attr="disabled"
                wire:confirm="Are you sure you want to set status = 1 on all {{ $problematicCount }} missing records? This directly updates MongoDB."
                @disabled($problematicCount === 0)
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 {{ $problematicCount === 0 ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs cursor-pointer' }}"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Fix All ({{ $problematicCount }}) &rarr; Set status = 1</span>
            </button>

            <!-- Fix Selected -->
            <button 
                type="button" 
                wire:click="fixSelected"
                wire:loading.attr="disabled"
                @disabled(empty($selectedRows))
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 {{ empty($selectedRows) ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-rose-600 text-white hover:bg-rose-700 cursor-pointer shadow-sm' }}"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Fix Selected ({{ count($selectedRows) }})</span>
            </button>

            <!-- Refresh -->
            <button 
                type="button" 
                wire:click="$refresh"
                class="px-4 py-2 rounded-xl text-xs font-bold bg-zinc-600 hover:bg-zinc-700 text-white transition flex items-center gap-1.5 shadow-sm cursor-pointer"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Refresh</span>
            </button>
        </div>

        <!-- Per Page Dropdown & Status Indicator -->
        <div class="flex items-center gap-4">
            <div wire:loading class="text-xs font-bold text-rose-600 flex items-center gap-1.5">
                <svg class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>Processing database...</span>
            </div>

            <div class="flex items-center gap-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                <span>Per Page:</span>
                <select 
                    wire:model.live="perPage" 
                    class="px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-bold text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-rose-500"
                >
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION ALERT -->
    @if($statusMessage !== 'Ready')
        <div 
            x-data="{ show: true }" 
            x-show="show" 
            x-init="setTimeout(() => show = false, 7000)"
            class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center justify-between shadow-xs"
        >
            <div class="flex items-center gap-2">
                <svg class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ $statusMessage }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-700 dark:text-emerald-300 hover:opacity-75 cursor-pointer">
                &times;
            </button>
        </div>
    @endif

    <!-- PROBLEMATIC RECORDS TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-900/80 text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="px-4 py-3.5 w-12 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll"
                                @checked($selectAll)
                                class="rounded border-zinc-300 dark:border-zinc-700 text-rose-600 focus:ring-rose-500 size-4 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3.5 w-14">No</th>
                        <th class="px-4 py-3.5 w-56">User</th>
                        <th class="px-4 py-3.5 w-64">Role & Organization</th>
                        <th class="px-4 py-3.5 w-44">Experience ID</th>
                        <th class="px-4 py-3.5 w-36">Current Status</th>
                        <th class="px-4 py-3.5 w-36">Target Integer</th>
                        <th class="px-4 py-3.5">Diagnostic Reason</th>
                        <th class="px-4 py-3.5 text-center w-28">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($problematicRecords as $index => $row)
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition">
                            <!-- Checkbox -->
                            <td class="px-4 py-3 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:model="selectedRows" 
                                    value="{{ $row['id'] }}"
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-rose-600 focus:ring-rose-500 size-4 cursor-pointer"
                                />
                            </td>

                            <!-- No -->
                            <td class="px-4 py-3 font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1 }}
                            </td>

                            <!-- User info -->
                            <td class="px-4 py-3">
                                @if($row['user_name'])
                                    <div class="font-bold text-zinc-900 dark:text-white truncate max-w-[200px]">
                                        @if($row['user_unique_id'])
                                            <a href="{{ route('admin.users.edit', $row['user_unique_id']) }}" target="_blank" class="hover:text-rose-600 hover:underline">
                                                {{ $row['user_name'] }}
                                            </a>
                                        @else
                                            {{ $row['user_name'] }}
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate max-w-[200px]">
                                        {{ $row['user_email'] }}
                                    </div>
                                @else
                                    <span class="font-mono text-[11px] text-zinc-500">ID: {{ $row['user_id'] }}</span>
                                @endif
                            </td>

                            <!-- Role & Organization -->
                            <td class="px-4 py-3 space-y-0.5">
                                <div class="font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $row['designation_title'] }}
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                    {{ $row['organization_name'] }}
                                </div>
                                <div class="text-[10px] text-zinc-400 dark:text-zinc-500">
                                    {{ $row['join_date'] ?: 'No Start' }} &mdash; 
                                    @if($row['current_organization'] == 1)
                                        <span class="text-emerald-600 font-bold">Present</span>
                                    @else
                                        {{ $row['end_date'] ?: 'No End' }}
                                    @endif
                                </div>
                            </td>

                            <!-- Experience MongoDB ID -->
                            <td class="px-4 py-3 font-mono text-[11px] text-zinc-500 dark:text-zinc-400 select-all">
                                {{ $row['id'] }}
                            </td>

                            <!-- Current Status Value -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">
                                    {{ $row['broken_value'] }}
                                </span>
                            </td>

                            <!-- Target Integer Value -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                                    <span>{{ $row['target_value'] }}</span>
                                    <span class="text-[10px] font-sans font-medium text-emerald-600 dark:text-emerald-400">(Active)</span>
                                </span>
                            </td>

                            <!-- Problem Reason -->
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300 font-medium">
                                {{ $row['reason'] }}
                            </td>

                            <!-- Action: Fix Button -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 text-rose-600 border border-rose-300 dark:border-rose-700 hover:bg-rose-600 hover:text-white hover:border-rose-600 transition shadow-2xs cursor-pointer"
                                >
                                    Fix to 1
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="max-w-md mx-auto space-y-3">
                                    <div class="size-12 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                        All Experience Records Have a Status Field!
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        There are 0 records with missing status remaining in the MongoDB database. All experience records have a status field assigned.
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
