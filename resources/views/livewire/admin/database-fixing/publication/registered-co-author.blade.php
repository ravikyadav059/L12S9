<?php

use App\Models\Publication;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Publication — registered_co_author Fixer - Admin')] class extends Component {
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
                    '$eq' => [['$type' => '$registered_co_author'], 'string'],
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
            $rawVal = is_array($item) ? ($item['registered_co_author'] ?? null) : ($item->registered_co_author ?? null);

            // Skip if already an array or null
            if (is_array($rawVal) || $rawVal === null) {
                continue;
            }

            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $id = $rawId ? (string) $rawId : '';
            $val = is_string($rawVal) ? $rawVal : (string) $rawVal;

            $trimmed = trim($val);
            $displayVal = $trimmed === '' ? '"" (Empty String)' : $val;

            $reason = 'Plain Text String Stored';
            if ($trimmed === '' || $trimmed === ',') {
                $reason = 'Empty string stored (will be converted to null)';
            } elseif (str_starts_with($val, ',')) {
                $reason = 'Leading Comma & Comma-Separated String';
            } elseif (str_contains($val, ',')) {
                $reason = 'Comma-Separated String Stored';
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
                'broken_value' => $displayVal,
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
            $rawVal = is_array($raw) ? ($raw['registered_co_author'] ?? '') : ($raw->registered_co_author ?? '');
            $cleaned = $this->parseToArrayOrNull($rawVal);

            DB::connection('mongodb')->table('publications')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update(['registered_co_author' => $cleaned]);

            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
            $msg = $cleaned ? "Fixed record #{$id} into native MongoDB array!" : "Cleaned record #{$id} empty string to null!";
            $this->statusMessage = $msg;
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
                $rawVal = is_array($raw) ? ($raw['registered_co_author'] ?? '') : ($raw->registered_co_author ?? '');
                $cleaned = $this->parseToArrayOrNull($rawVal);

                DB::connection('mongodb')->table('publications')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update(['registered_co_author' => $cleaned]);

                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected record(s)!";
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
                    $rawVal = is_array($item) ? ($item['registered_co_author'] ?? '') : ($item->registered_co_author ?? '');
                    $cleaned = $this->parseToArrayOrNull($rawVal);

                    DB::connection('mongodb')->table('publications')
                        ->where(function ($q) use ($id, $mongoId) {
                            $q->where('_id', $mongoId)->orWhere('_id', $id);
                        })
                        ->update(['registered_co_author' => $cleaned]);

                    $count++;
                }
            }

            unset($records);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed all {$count} record(s) with zero server load!";
    }

    protected function toMongoId(mixed $id): mixed
    {
        if ($id instanceof \MongoDB\BSON\ObjectId) {
            return $id;
        }

        if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
            return new \MongoDB\BSON\ObjectId($id);
        }

        return $id;
    }

    protected function parseToArrayOrNull(mixed $value): ?array
    {
        if (is_array($value)) {
            $cleaned = array_values(array_filter(array_map('trim', $value), fn ($item) => $item !== ''));
            return ! empty($cleaned) ? $cleaned : null;
        }

        if (is_string($value)) {
            $parts = preg_split('/[,;|]+/', trim($value));
            if (is_array($parts)) {
                $cleaned = array_values(array_filter(array_map('trim', $parts), fn ($item) => $item !== ''));
                return ! empty($cleaned) ? $cleaned : null;
            }
        }

        return null;
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">Publication</strong> &bull; Field: <code class="font-mono text-brand">registered_co_author</code>
        </span>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
            Publication &mdash; registered_co_author Fixer
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
                <p class="font-medium">This action will modify the database immediately and cannot be undone automatically. So please be sure 100%.</p>
                <p class="text-zinc-500">Problematic rows detected: <strong class="text-zinc-800 dark:text-zinc-200">{{ $problematicCount }}</strong></p>
            </div>
        </div>
    </div>

    <!-- BLUE HERO BANNER CARD (MATCHING IMAGE 2) -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-700 via-blue-600 to-[#198BEA] p-6 sm:p-7 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="space-y-2 max-w-3xl">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                        Publication &mdash; registered_co_author Fixer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-blue-100 leading-relaxed">
                    This tool detects Publication records where the <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-white">registered_co_author</code> field is stored as a legacy string with leading/extra commas (e.g. <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-amber-200">",65d3200...,64523..."</code>) and converts them into a standardized MongoDB <strong class="underline font-bold">array</strong> (<code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-emerald-200">["65d3200...", "64523..."]</code>) for lightning-fast retrieval and author querying.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Suggestion* <span class="font-medium text-blue-100">Always DO Fixed One by One By reading it "Problem Reason"</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badge (Matching Image 2) -->
            <div class="shrink-0">
                <div class="px-6 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[190px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-blue-100 mt-0.5">
                        Broken Records (Problematic Rows)
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- YELLOW EXPLANATION ALERT BOX (MATCHING IMAGE 2) -->
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
                    <strong class="text-amber-700 dark:text-amber-400">Amber text (Leading Comma & Comma-Separated String):</strong> 
                    The <code class="font-mono text-zinc-800 dark:text-zinc-200">registered_co_author</code> field stores a string like <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">",65d3200d8b3a5ca96a019252,64523ea859b78ef7a90c64e2"</code>. Because it is plain text with a leading comma, MongoDB cannot index each author ID separately, breaking array filtering (<code class="font-mono text-xs">$in</code>) and co-author lookups.
                </li>
                <li>
                    <strong class="text-red-600 dark:text-red-400">Red text (Broken / Invalid ID):</strong> 
                    The string contains invalid hex ObjectIds or deleted user IDs that no longer exist in the Users collection.
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
                    <strong class="text-emerald-700 dark:text-emerald-400">Standardize to Native Array:</strong> 
                    Strips leading and trailing commas, splits by comma delimiter, filters out empty string entries, and saves clean array of User IDs (<code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">["65d3200d...", "64523ea..."]</code>).
                </li>
                <li>
                    <strong class="text-emerald-700 dark:text-emerald-400">Fast & Efficient Retrieval:</strong> 
                    Allows MongoDB to natively index the array, making author lookups and co-author profile joins instantaneous without requiring string regex parsing.
                </li>
            </ul>
        </div>
    </div>

    <!-- ACTIONS TOOLBAR (MATCHING IMAGE 2) -->
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
                <span>Fix All Records</span>
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

        <!-- Right Status Indicator -->
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 text-xs font-bold rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                <span wire:loading.remove>{{ $statusMessage }}</span>
                <span wire:loading class="text-brand flex items-center gap-1">
                    <svg class="animate-spin size-3.5" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Processing...
                </span>
            </span>
        </div>
    </div>

    <!-- PROBLEMATIC RECORDS TABLE / ALL CLEAN STATE (MATCHING IMAGE 2) -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                <!-- Dark Navy Header (Matching Image 2) -->
                <thead class="bg-[#172A39] text-white text-[11px] font-extrabold uppercase tracking-wider border-b border-zinc-800">
                    <tr>
                        <th class="px-4 py-3.5 w-12 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll"
                                class="size-4 text-brand rounded border-zinc-600 bg-zinc-800 focus:ring-brand cursor-pointer" 
                            />
                        </th>
                        <th class="px-4 py-3.5 w-12">#</th>
                        <th class="px-4 py-3.5 w-44">PUBLICATION _ID</th>
                        <th class="px-4 py-3.5 w-48">USER ID</th>
                        <th class="px-4 py-3.5">REGISTERED_CO_AUTHOR (CURRENT BROKEN VALUE)</th>
                        <th class="px-4 py-3.5">PROBLEM REASON (WHY FLAGGED)</th>
                        <th class="px-4 py-3.5 w-28">JOIN DATE</th>
                        <th class="px-4 py-3.5 w-24">STATUS</th>
                        <th class="px-4 py-3.5 text-center w-24">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @if(empty($problematicRecords))
                        <!-- Empty / Clean State (Matching Image 2 Party Popper Design) -->
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
                                <div class="space-y-3 max-w-sm mx-auto">
                                    <!-- Party Icon -->
                                    <div class="text-4xl animate-bounce">
                                        🎉
                                    </div>
                                    <h4 class="text-base font-extrabold text-zinc-900 dark:text-white">
                                        All Clean!
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        No problematic Publication records were found. The database is in good shape.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @else
                        @foreach($problematicRecords as $index => $row)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition border-b border-zinc-100 dark:border-zinc-800/60">
                                <td class="px-4 py-3 text-center">
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="selectedRows" 
                                        value="{{ $row['id'] }}"
                                        class="size-4 text-brand rounded border-zinc-400 focus:ring-brand cursor-pointer"
                                    />
                                </td>
                                <td class="px-4 py-3 font-semibold text-zinc-500">{{ ($paginator->currentPage() - 1) * $perPage + $index + 1 }}</td>
                                <td class="px-4 py-3 font-mono text-zinc-700 dark:text-zinc-300 text-[11px] font-semibold">{{ $row['id'] }}</td>
                                <td class="px-4 py-3 font-mono text-zinc-500 text-[11px]">{{ $row['user_id'] }}</td>
                                <td class="px-4 py-3 font-mono text-amber-600 dark:text-amber-400 font-bold max-w-xs truncate" title="{{ $row['broken_value'] }}">
                                    {{ $row['broken_value'] }}
                                </td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $row['reason'] }}</td>
                                <td class="px-4 py-3 text-zinc-500 whitespace-nowrap">{{ $row['date'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">
                                        Broken
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button 
                                        type="button" 
                                        wire:click="fixSingle('{{ $row['id'] }}')"
                                        wire:loading.attr="disabled"
                                        class="px-2.5 py-1 text-xs font-bold rounded-lg bg-brand text-white hover:bg-brand-600 transition cursor-pointer shadow-2xs"
                                    >
                                        Fix
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        @if($paginator->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
