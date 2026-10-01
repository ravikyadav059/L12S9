<?php

use App\Models\Seminar;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Seminar — Month Year Date Standardizer - Admin')] class extends Component
{
    use WithPagination;

    public string $statusMessage = 'Ready';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    // Batch-processing state (poll-driven Fix All)
    public bool $isProcessing = false;

    public int $batchProcessed = 0;

    public int $batchSkipped = 0;

    /** @var array<int, mixed> */
    public array $batchUnparseable = [];

    protected function getProblematicQuery()
    {
        $validPattern = '^(January|February|March|April|May|June|July|August|September|October|November|December) [0-9]{4}$';

        return DB::connection('mongodb')->table('seminar')
            ->whereNotNull('month_year')
            ->where('month_year', '!=', '')
            ->whereRaw(['month_year' => ['$not' => ['$regex' => $validPattern]]]);
    }

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

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $totalCount = $query->count();
        $paginator = $query->latest('_id')->paginate($this->perPage);

        $formatted = [];
        foreach ($paginator as $item) {
            $rawVal = is_array($item) ? ($item['month_year'] ?? null) : ($item->month_year ?? null);
            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $id = $rawId ? (string) $rawId : '';
            $val = is_string($rawVal) ? $rawVal : (string) $rawVal;

            $standardized = Seminar::formatMonthYear($rawVal);

            $reason = 'Unstandardized Date Format';
            if (preg_match('/^\d{4}-\d{2}/', $val)) {
                $reason = 'ISO Date String (YYYY-MM-DD)';
            } elseif (preg_match('/^\d{1,2}[\/\-]\d{4}/', $val)) {
                $reason = 'Slash/Dash Separated (MM/YYYY)';
            } elseif (preg_match('/^[a-zA-Z]{3,4}\s+\d{4}/', $val)) {
                $reason = 'Short Month Abbreviation';
            } elseif (is_numeric($val)) {
                $reason = 'Numeric Timestamp';
            }

            $date = '-';
            $createdAt = is_array($item) ? ($item['created_at'] ?? null) : ($item->created_at ?? null);
            if ($createdAt) {
                $date = is_string($createdAt) ? substr($createdAt, 0, 10) : ($createdAt instanceof DateTimeInterface ? $createdAt->format('Y-m-d') : (string) $createdAt);
            }

            $title = is_array($item) ? ($item['title'] ?? null) : ($item->title ?? null);
            $hostBy = is_array($item) ? ($item['host_by'] ?? null) : ($item->host_by ?? null);

            $formatted[] = [
                'id'          => $id,
                'title'       => (string) ($title ?: '-'),
                'host_by'     => (string) ($hostBy ?: 'N/A'),
                'broken_value' => $val,
                'standardized' => $standardized,
                'reason'      => $reason,
                'date'        => $date,
            ];
        }

        return [
            'problematicCount'   => $totalCount,
            'problematicRecords' => $formatted,
            'paginator'          => $paginator,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);
        $raw = DB::connection('mongodb')->table('seminar')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        if ($raw) {
            $rawVal = is_array($raw) ? ($raw['month_year'] ?? '') : ($raw->month_year ?? '');
            $cleaned = Seminar::formatMonthYear($rawVal);

            if ($cleaned === null) {
                $this->statusMessage = "Could not parse '{$rawVal}' — original value left unchanged.";

                return;
            }

            DB::connection('mongodb')->table('seminar')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update(['month_year' => $cleaned]);

            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
            $this->statusMessage = "Fixed record #{$id} — month_year updated to '{$cleaned}'.";
        }
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            return;
        }

        $count = 0;
        $skipped = 0;

        foreach ($this->selectedRows as $id) {
            $mongoId = $this->toMongoId($id);
            $raw = DB::connection('mongodb')->table('seminar')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->first();

            if ($raw) {
                $rawVal = is_array($raw) ? ($raw['month_year'] ?? '') : ($raw->month_year ?? '');
                $cleaned = Seminar::formatMonthYear($rawVal);

                if ($cleaned === null) {
                    $skipped++;

                    continue;
                }

                DB::connection('mongodb')->table('seminar')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update(['month_year' => $cleaned]);

                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $msg = "Standardized {$count} selected record(s) for `month_year`.";
        if ($skipped > 0) {
            $msg .= " {$skipped} record(s) had unrecognisable formats and were left unchanged.";
        }
        $this->statusMessage = $msg;
    }

    /** Start poll-driven fix for all month_year records. */
    public function startFixAll(): void
    {
        $this->isProcessing = true;
        $this->batchProcessed = 0;
        $this->batchSkipped = 0;
        $this->batchUnparseable = [];
        $this->statusMessage = 'Processing…';
    }

    /**
     * Called by wire:poll while $isProcessing — processes one batch of 200.
     * Each poll is a fresh HTTP request so no single request takes too long.
     */
    public function processBatch(): void
    {
        if (! $this->isProcessing) {
            return;
        }

        $batchSize = 200;
        $query = $this->getProblematicQuery();

        if (! empty($this->batchUnparseable)) {
            $query = $query->whereNotIn('_id', $this->batchUnparseable);
        }

        $records = $query->take($batchSize)->get();

        if ($records->isEmpty()) {
            $this->isProcessing = false;
            $msg = "Done! Standardized {$this->batchProcessed} record(s) for `month_year`";
            if ($this->batchSkipped > 0) {
                $msg .= "; {$this->batchSkipped} had unrecognisable formats and were left unchanged";
            }
            $this->statusMessage = $msg.'.';

            return;
        }

        $fixable = [];
        $newUnparseable = []; // stored as plain strings — Livewire cannot serialize ObjectId

        foreach ($records as $item) {
            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            if (! $rawId) {
                continue;
            }

            $mongoId = $this->toMongoId($rawId);
            $strId = (string) $rawId;
            $rawVal = is_array($item) ? ($item['month_year'] ?? '') : ($item->month_year ?? '');
            $cleaned = Seminar::formatMonthYear($rawVal);

            if ($cleaned === null) {
                $newUnparseable[] = $strId;
                $this->batchSkipped++;
            } else {
                $fixable[$cleaned][] = $mongoId;
            }
        }

        foreach ($fixable as $cleanedVal => $mongoIds) {
            DB::connection('mongodb')->table('seminar')
                ->whereIn('_id', $mongoIds)
                ->update(['month_year' => $cleanedVal]);
            $this->batchProcessed += count($mongoIds);
        }

        if (! empty($newUnparseable)) {
            $this->batchUnparseable = array_merge($this->batchUnparseable, $newUnparseable);
        }

        if (empty($fixable) && empty($newUnparseable)) {
            $this->isProcessing = false;
            $this->statusMessage = "Done! Standardized {$this->batchProcessed} record(s). {$this->batchSkipped} left unchanged.";
        } else {
            $this->statusMessage = "Processing… {$this->batchProcessed} fixed so far.";
        }

        unset($records, $fixable, $newUnparseable);
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

<div class="space-y-5"
    @if($isProcessing)
        wire:poll.800ms="processBatch"
    @endif
>
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">Seminar</strong> &bull; Field: <code class="font-mono text-indigo-600 dark:text-indigo-400">month_year</code>
        </span>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
            Seminar &mdash; month_year Date Standardizer
        </h2>

        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-indigo-600 transition cursor-pointer select-none"
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
                <p class="font-medium">This tool scans all Seminar records for inconsistent date strings in <code>month_year</code> (e.g. <code>2015-08-01</code>, <code>08/2015</code>, <code>Aug 2015</code>) and standardizes them into the strict <strong>"Month Year"</strong> format (e.g. <code>"January 2026"</code>, <code>"August 2015"</code>).</p>
                <p class="text-zinc-500">Unstandardized rows: <strong class="text-zinc-800 dark:text-zinc-200">{{ $problematicCount }}</strong></p>
            </div>
        </div>
    </div>

    <!-- INDIGO / VIOLET HERO BANNER CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-700 via-violet-600 to-indigo-500 p-6 sm:p-7 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="space-y-2 max-w-3xl">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                        Seminar &mdash; month_year Date Standardizer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-indigo-100 leading-relaxed">
                    Standardizes all <code class="bg-indigo-900/60 px-1.5 py-0.5 rounded font-mono text-white">month_year</code> date values in Seminar documents into strict <strong class="underline font-bold">"Month Year"</strong> format (e.g. <code class="bg-indigo-900/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"January 2026"</code>, <code class="bg-indigo-900/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"August 2015"</code>) for consistent event timeline rendering.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Suggestion* <span class="font-medium text-indigo-100">Fix All Records runs in memory-safe chunks (200 per batch) with zero downtime.</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badge -->
            <div class="shrink-0">
                <div class="px-6 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[200px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-indigo-100 mt-0.5">
                        UNSTANDARDIZED MONTH_YEAR
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
                    <strong class="text-amber-700 dark:text-amber-400">Inconsistent Date Formats:</strong> 
                    Dates stored as <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">2015-08-01</code>, <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">08/2015</code>, or <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">Aug 2015</code> cause broken chronology and irregular profile displays.
                </li>
                <li>
                    <strong class="text-red-600 dark:text-red-400">Sorting & Filtering Failures:</strong> 
                    Mixed date formats prevent accurate seminar timeline ordering and chronological CV rendering.
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
                    <strong class="text-emerald-700 dark:text-emerald-400">Standardizes to "Month Year":</strong> 
                    Parses any date format and formats it strictly as <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">"August 2015"</code>, <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">"January 2026"</code>.
                </li>
                <li>
                    <strong class="text-emerald-700 dark:text-emerald-400">Non-destructive:</strong> 
                    Records with truly unparseable values are <em>left completely untouched</em>.
                </li>
            </ul>
        </div>
    </div>

    <!-- ACTIONS TOOLBAR -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-3 bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-xs font-bold text-zinc-500 mr-1">Actions:</span>
            
            <!-- Fix All -->
            <button 
                type="button" 
                wire:click="startFixAll"
                wire:loading.attr="disabled"
                @disabled($problematicCount === 0 || $isProcessing)
                class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 shadow-sm cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
                @if($isProcessing)
                    <svg class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Processing… {{ $batchProcessed }} fixed</span>
                @else
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Standardize All month_year Records ({{ $problematicCount }})</span>
                @endif
            </button>

            <!-- Fix Selected -->
            <button 
                type="button" 
                wire:click="fixSelected"
                wire:loading.attr="disabled"
                @disabled(empty($selectedRows))
                class="px-4 py-2 rounded-xl text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg class="size-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Fix Selected ({{ count($selectedRows) }})</span>
            </button>

            <!-- Refresh Button -->
            <button 
                type="button" 
                wire:click="$refresh"
                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-zinc-800 hover:bg-zinc-900 text-white transition flex items-center gap-1.5 cursor-pointer shadow-xs"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Refresh</span>
            </button>
        </div>

        <!-- Per Page Selector -->
        <div class="flex items-center gap-2 text-xs text-zinc-500">
            <span>Per Page:</span>
            <select 
                wire:model.live="perPage"
                class="px-2.5 py-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 text-xs font-semibold focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <!-- STATUS NOTIFICATION -->
    @if($statusMessage !== 'Ready')
        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="size-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $statusMessage }}</span>
            </div>
            <button type="button" wire:click="$set('statusMessage', 'Ready')" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline">Dismiss</button>
        </div>
    @endif

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
                                @checked($selectAll) 
                                class="rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 focus:ring-indigo-500"
                            />
                        </th>
                        <th class="px-4 py-3 w-44">Record ID / Title</th>
                        <th class="px-4 py-3">Raw month_year Stored</th>
                        <th class="px-4 py-3">Standardized "Month Year" Preview</th>
                        <th class="px-4 py-3 w-48">Detected Format</th>
                        <th class="px-4 py-3 w-28 text-center">Action</th>
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
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 focus:ring-indigo-500"
                                />
                            </td>

                            <!-- ID & Title -->
                            <td class="px-4 py-3 font-mono text-[11px]">
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $row['id'] }}</div>
                                <div class="text-zinc-400 text-[10px]">{{ $row['title'] }}</div>
                            </td>

                            <!-- Broken Value -->
                            <td class="px-4 py-3">
                                <div class="max-w-xs break-all font-mono text-[11px] bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 px-2.5 py-1.5 rounded-lg border border-red-200 dark:border-red-900/50">
                                    "{{ $row['broken_value'] }}"
                                </div>
                            </td>

                            <!-- Standardized Preview -->
                            <td class="px-4 py-3">
                                @if($row['standardized'])
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        "{{ $row['standardized'] }}"
                                    </span>
                                @else
                                    <span class="text-zinc-400 font-mono text-[11px] italic">null</span>
                                @endif
                            </td>

                            <!-- Reason -->
                            <td class="px-4 py-3 text-[11px] text-zinc-600 dark:text-zinc-400">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                    {{ $row['reason'] }}
                                </span>
                            </td>

                            <!-- Fix Single Button -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button
                                    type="button"
                                    wire:click="fixSingle('{{ $row['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition shadow-xs cursor-pointer"
                                >
                                    Fix Now
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-zinc-400 dark:text-zinc-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="size-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="font-bold text-zinc-700 dark:text-zinc-300">All month_year records are standardized!</p>
                                    <p class="text-xs text-zinc-500">All dates match strict "Month Year" format (e.g. "January 2026", "August 2015").</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginator->hasPages())
            <div class="px-5 py-4 border-t border-zinc-200 dark:border-zinc-800">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
