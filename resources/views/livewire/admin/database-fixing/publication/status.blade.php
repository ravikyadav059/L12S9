<?php

use App\Models\Publication;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Publication — status Fixer - Admin')] class extends Component {
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
                $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);

                return $rawId ? (string) $rawId : '';
            })->filter()->all();
        } else {
            $this->selectedRows = [];
        }
    }

    protected function getProblematicQuery()
    {
        return DB::connection('mongodb')->table('publications')
            ->whereRaw([
                '$expr' => [
                    '$eq' => [['$type' => '$status'], 'string'],
                ],
            ]);
    }

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $totalCount = $query->count();
        $paginator = $query->latest('_id')->paginate($this->perPage);

        $formatted = [];
        foreach ($paginator as $item) {
            $rawVal = is_array($item) ? ($item['status'] ?? null) : ($item->status ?? null);

            // Skip if already integer or null
            if (is_int($rawVal) || is_numeric($rawVal) && ! is_string($rawVal)) {
                continue;
            }

            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $id = $rawId ? (string) $rawId : '';
            $val = (string) $rawVal;

            $targetVal = $this->parseStatusToInt($rawVal);

            $reason = 'String Type Stored';
            if ($val === '1') {
                $reason = 'String "1" stored instead of integer 1';
            } elseif ($val === '0') {
                $reason = 'String "0" stored instead of integer 0';
            } elseif ($val === '') {
                $reason = 'Empty string stored (will be converted to integer 0)';
            } else {
                $reason = "Text string \"{$val}\" stored instead of integer (1/0)";
            }

            $date = '-';
            $createdAt = is_array($item) ? ($item['created_at'] ?? null) : ($item->created_at ?? null);
            $pubDate = is_array($item) ? ($item['published_date'] ?? null) : ($item->published_date ?? null);

            if ($createdAt) {
                $date = is_string($createdAt) ? substr($createdAt, 0, 10) : ($createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d') : (string) $createdAt);
            } elseif ($pubDate) {
                $date = (string) $pubDate;
            }

            $userId = is_array($item) ? ($item['user_id'] ?? 'N/A') : ($item->user_id ?? 'N/A');

            $formatted[] = [
                'id' => $id,
                'user_id' => (string) ($userId ?: 'N/A'),
                'broken_value' => $val === '' ? '"" (Empty String)' : "\"{$val}\"",
                'target_value' => $targetVal,
                'reason' => $reason,
                'date' => $date,
            ];
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
        $raw = DB::connection('mongodb')->table('publications')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        if ($raw) {
            $rawVal = is_array($raw) ? ($raw['status'] ?? '') : ($raw->status ?? '');
            $cleaned = $this->parseStatusToInt($rawVal);

            DB::connection('mongodb')->table('publications')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update(['status' => $cleaned]);

            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
            $this->statusMessage = "Fixed record #{$id} status to integer {$cleaned}!";
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
            $raw = DB::connection('mongodb')->table('publications')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->first();

            if ($raw) {
                $rawVal = is_array($raw) ? ($raw['status'] ?? '') : ($raw->status ?? '');
                $cleaned = $this->parseStatusToInt($rawVal);

                DB::connection('mongodb')->table('publications')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update(['status' => $cleaned]);

                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected record(s) to integer status!";
    }

    public function fixAll(): void
    {
        $count = 0;
        $batchSize = 250;

        // Process in lightweight batches to prevent offset-skipping and maintain near-zero server memory load
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
                    $rawVal = is_array($item) ? ($item['status'] ?? '') : ($item->status ?? '');
                    $cleaned = $this->parseStatusToInt($rawVal);

                    DB::connection('mongodb')->table('publications')
                        ->where(function ($q) use ($id, $mongoId) {
                            $q->where('_id', $mongoId)->orWhere('_id', $id);
                        })
                        ->update(['status' => $cleaned]);

                    $count++;
                }
            }

            unset($records);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully converted all {$count} publication record(s) status to integer (1 / 0)!";
    }

    protected function toMongoId(mixed $id): mixed
    {
        if ($id instanceof \MongoDB\BSON\ObjectId) {
            return $id;
        }

        if (is_string($id) && strlen(trim($id)) === 24 && ctype_xdigit(trim($id))) {
            return new \MongoDB\BSON\ObjectId(trim($id));
        }

        return $id;
    }

    protected function parseStatusToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value === 1 ? 1 : 0;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $val = strtolower(trim((string) $value));
        if (in_array($val, ['1', 'published', 'active', 'true', 'yes'], true)) {
            return 1;
        }

        return 0;
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">Publication</strong> &bull; Field: <code class="font-mono text-brand">status</code>
        </span>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
            Publication &mdash; status Fixer
        </h2>
        
        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-brand transition cursor-pointer select-none"
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
                class="mt-2 p-3.5 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-600 dark:text-zinc-300 space-y-1"
            >
                <p class="font-medium">This action will modify the publication status field in MongoDB from string format (e.g. "1", "0") into standardized integers (1 = Active, 0 = Inactive). This action directly updates the database.</p>
                <p class="text-zinc-500">Problematic rows detected: <strong class="text-zinc-800 dark:text-zinc-200">{{ $problematicCount }}</strong></p>
            </div>
        </div>
    </div>

    <!-- BLUE HERO BANNER CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-700 via-blue-600 to-[#198BEA] p-6 sm:p-7 text-white shadow-lg">
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
                        Publication &mdash; status Fixer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-blue-100 leading-relaxed">
                    This tool detects Publication records where the <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-white">status</code> field is stored as a legacy string type (e.g. <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"1"</code> or <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"0"</code>) and converts them into standardized MongoDB <strong class="underline font-bold">integer</strong> values (<code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-emerald-200">1</code> for Active, <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-emerald-200">0</code> for Inactive) for high-performance indexing and querying.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Suggestion* <span class="font-medium text-blue-100">Fix All Records runs in memory-safe chunks (500 per batch) with zero downtime.</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badge -->
            <div class="shrink-0">
                <div class="px-6 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[190px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-blue-100 mt-0.5">
                        String Status Records
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- YELLOW EXPLANATION ALERT BOX -->
    <div class="p-5 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/90 dark:border-amber-800/60 text-xs text-zinc-700 dark:text-zinc-300 space-y-3 shadow-xs">
        <!-- Section 1: Why problematic -->
        <div class="space-y-1.5">
            <div class="flex items-center gap-1.5 font-bold text-amber-900 dark:text-amber-300">
                <svg class="size-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>Why are these records "problematic"?</span>
            </div>
            <ul class="space-y-1.5 pl-5 list-disc text-zinc-600 dark:text-zinc-300">
                <li>
                    <strong class="text-amber-700 dark:text-amber-400">String Type Stored:</strong> 
                    The <code class="font-mono text-zinc-800 dark:text-zinc-200">status</code> field stores string values (e.g. <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">"1"</code> or <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">"0"</code>) instead of native integer values (<code class="font-mono text-emerald-600">1</code> / <code class="font-mono text-emerald-600">0</code>).
                </li>
                <li>
                    <strong class="text-red-600 dark:text-red-400">Query Mismatches:</strong> 
                    In MongoDB, querying <code class="font-mono">{status: 1}</code> (integer) will fail to match records where status is string <code class="font-mono">{"status": "1"}</code>, leading to inconsistent filtering and counts.
                </li>
            </ul>
        </div>

        <!-- Section 2: What does Fix do -->
        <div class="space-y-1.5 pt-2 border-t border-amber-200/70 dark:border-amber-800/50">
            <div class="flex items-center gap-1.5 font-bold text-emerald-800 dark:text-emerald-300">
                <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>What does "Fix" do?</span>
            </div>
            <ul class="space-y-1.5 pl-5 list-disc text-zinc-600 dark:text-zinc-300">
                <li>
                    <strong class="text-emerald-700 dark:text-emerald-400">Converts "1" to Integer 1 (Active):</strong> 
                    String <code class="font-mono">"1"</code>, <code class="font-mono">"published"</code>, <code class="font-mono">"active"</code> are converted to native integer <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">1</code>.
                </li>
                <li>
                    <strong class="text-emerald-700 dark:text-emerald-400">Converts "0" to Integer 0 (Inactive):</strong> 
                    String <code class="font-mono">"0"</code>, <code class="font-mono">"inactive"</code>, <code class="font-mono">""</code> are converted to native integer <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">0</code>.
                </li>
            </ul>
        </div>
    </div>

    <!-- ACTIONS TOOLBAR -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-3 bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-xs font-bold text-zinc-500 mr-1">Actions:</span>
            
            <!-- Fix All Records -->
            <button 
                type="button" 
                wire:click="fixAll"
                wire:loading.attr="disabled"
                class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition flex items-center gap-1.5 shadow-sm cursor-pointer disabled:opacity-50"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Fix All Records ({{ $problematicCount }})</span>
            </button>

            <!-- Fix Selected -->
            <button 
                type="button" 
                wire:click="fixSelected"
                wire:loading.attr="disabled"
                @disabled(empty($selectedRows))
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 {{ empty($selectedRows) ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-brand text-white hover:bg-brand-600 cursor-pointer shadow-sm' }}"
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
            <div wire:loading class="text-xs font-bold text-brand flex items-center gap-1.5">
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
                    class="px-2.5 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-bold text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-brand"
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
            x-init="setTimeout(() => show = false, 6000)"
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
                                class="rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand size-4 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3.5 w-16">No</th>
                        <th class="px-4 py-3.5 w-44">User ID</th>
                        <th class="px-4 py-3.5 w-48">MongoDB ID</th>
                        <th class="px-4 py-3.5 w-36">Current String</th>
                        <th class="px-4 py-3.5 w-36">Target Integer</th>
                        <th class="px-4 py-3.5">Problem Reason</th>
                        <th class="px-4 py-3.5 w-32">Date</th>
                        <th class="px-4 py-3.5 text-center w-24">Action</th>
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
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-brand focus:ring-brand size-4 cursor-pointer"
                                />
                            </td>

                            <!-- No -->
                            <td class="px-4 py-3 font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1 }}
                            </td>

                            <!-- User ID -->
                            <td class="px-4 py-3 font-mono text-[11px] text-zinc-700 dark:text-zinc-300 truncate max-w-[150px]">
                                {{ $row['user_id'] }}
                            </td>

                            <!-- MongoDB ID -->
                            <td class="px-4 py-3 font-mono text-[11px] text-zinc-500 dark:text-zinc-400 select-all">
                                {{ $row['id'] }}
                            </td>

                            <!-- Current String Value -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                                    {{ $row['broken_value'] }}
                                </span>
                            </td>

                            <!-- Target Integer Value -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                                    <span>{{ $row['target_value'] }}</span>
                                    <span class="text-[10px] font-sans font-medium text-emerald-600 dark:text-emerald-400">({{ $row['target_value'] === 1 ? 'Active' : 'Inactive' }})</span>
                                </span>
                            </td>

                            <!-- Problem Reason -->
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300 font-medium">
                                {{ $row['reason'] }}
                            </td>

                            <!-- Date -->
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                {{ $row['date'] }}
                            </td>

                            <!-- Action: Fix Button -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 text-brand border border-brand/30 hover:bg-brand hover:text-white hover:border-brand transition shadow-2xs cursor-pointer"
                                >
                                    Fix
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
                                        All Status Records Standardized!
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        There are no publication records with string status types remaining in the MongoDB database. All records are properly formatted as native integers (1 / 0).
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
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
