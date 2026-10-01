<?php

use App\Models\Experience;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;

new #[Layout('components.layouts.admin')] #[Title('Experience — Date Standardizer - Admin')] class extends Component
{
    use WithPagination;

    #[Url(as: 'field')]
    public string $activeField = 'join_date';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public string $statusMessage = 'Ready';

    public int $perPage = 25;

    // Batch-processing state (poll-driven Fix All)
    public bool $isProcessing = false;

    public string $batchField = '';

    public int $batchProcessed = 0;

    public int $batchSkipped = 0;

    /** @var array<int, mixed> */
    public array $batchUnparseable = [];

    public bool $isBothProcessing = false;

    public int $bothProcessed = 0;

    public int $bothSkipped = 0;

    /** @var array<int, mixed> */
    public array $bothUnparseable = [];

    public array $availableFields = [
        'join_date' => 'join_date',
        'end_date' => 'end_date',
    ];

    public function mount(?string $field = null): void
    {
        if ($field && array_key_exists($field, $this->availableFields)) {
            $this->activeField = $field;
        }
    }

    public function setField(string $field): void
    {
        if (array_key_exists($field, $this->availableFields)) {
            $this->activeField = $field;
            $this->selectedRows = [];
            $this->selectAll = false;
            $this->resetPage();
        }
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

    protected function getProblematicQuery(?string $field = null)
    {
        $targetField = $field ?: $this->activeField;
        $validPattern = '^(January|February|March|April|May|June|July|August|September|October|November|December) [0-9]{4}$';

        if ($targetField === 'end_date') {
            return DB::connection('mongodb')->table('experience')
                ->whereNotNull('end_date')
                ->where('end_date', '!=', '')
                ->where('end_date', '!=', 'Present')
                ->whereRaw(['end_date' => ['$not' => ['$regex' => $validPattern]]]);
        }

        return DB::connection('mongodb')->table('experience')
            ->whereNotNull('join_date')
            ->where('join_date', '!=', '')
            ->whereRaw(['join_date' => ['$not' => ['$regex' => $validPattern]]]);
    }

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $totalCount = $query->count();
        $paginator = $query->latest('_id')->paginate($this->perPage);

        // Only compute sidebar counts when actually displaying (not during bulk fix actions)
        // Use a lightweight approach — clone and count in one pass
        $fieldCounts = [
            'join_date' => $this->activeField === 'join_date' ? $totalCount : $this->getProblematicQuery('join_date')->count(),
            'end_date' => $this->activeField === 'end_date' ? $totalCount : $this->getProblematicQuery('end_date')->count(),
        ];
        $totalProblematic = array_sum($fieldCounts);

        $formatted = [];
        foreach ($paginator as $item) {
            $rawVal = is_array($item) ? ($item[$this->activeField] ?? null) : ($item->{$this->activeField} ?? null);

            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $id = $rawId ? (string) $rawId : '';
            $val = is_string($rawVal) ? $rawVal : (string) $rawVal;

            $allowPresent = ($this->activeField === 'end_date');
            $standardized = Experience::formatMonthYear($rawVal, $allowPresent);

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

            $userId = is_array($item) ? ($item['user_id'] ?? 'N/A') : ($item->user_id ?? 'N/A');
            $designation = is_array($item) ? ($item['designation'] ?? null) : ($item->designation ?? null);

            $formatted[] = [
                'id' => $id,
                'user_id' => (string) ($userId ?: 'N/A'),
                'designation' => (string) ($designation ?: '-'),
                'broken_value' => $val,
                'standardized' => $standardized,
                'reason' => $reason,
                'date' => $date,
            ];
        }

        return [
            'problematicCount' => $totalCount,
            'fieldCounts' => $fieldCounts,
            'totalProblematic' => $totalProblematic,
            'problematicRecords' => $formatted,
            'paginator' => $paginator,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);
        $raw = DB::connection('mongodb')->table('experience')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        if ($raw) {
            $rawVal = is_array($raw) ? ($raw[$this->activeField] ?? '') : ($raw->{$this->activeField} ?? '');
            $allowPresent = ($this->activeField === 'end_date');
            $cleaned = Experience::formatMonthYear($rawVal, $allowPresent);

            if ($cleaned === null) {
                // Cannot parse — leave original data untouched
                $this->statusMessage = "Could not parse '{$rawVal}' — original value left unchanged.";

                return;
            }

            DB::connection('mongodb')->table('experience')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update([$this->activeField => $cleaned]);

            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
            $this->statusMessage = "Fixed record #{$id} — {$this->activeField} updated to '{$cleaned}'.";
        }
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            return;
        }

        $count = 0;
        $skipped = 0;
        $allowPresent = ($this->activeField === 'end_date');

        foreach ($this->selectedRows as $id) {
            $mongoId = $this->toMongoId($id);
            $raw = DB::connection('mongodb')->table('experience')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->first();

            if ($raw) {
                $rawVal = is_array($raw) ? ($raw[$this->activeField] ?? '') : ($raw->{$this->activeField} ?? '');
                $cleaned = Experience::formatMonthYear($rawVal, $allowPresent);

                if ($cleaned === null) {
                    // Cannot parse — leave original data untouched
                    $skipped++;

                    continue;
                }

                DB::connection('mongodb')->table('experience')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update([$this->activeField => $cleaned]);

                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $msg = "Standardized {$count} selected record(s) for `{$this->activeField}`.";
        if ($skipped > 0) {
            $msg .= " {$skipped} record(s) had unrecognisable formats and were left unchanged.";
        }
        $this->statusMessage = $msg;
    }

    public function fixActiveFieldAll(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $count = 0;
        $skipped = 0;
        $batchSize = 200;
        $allowPresent = ($this->activeField === 'end_date');
        $field = $this->activeField;

        // Track IDs we cannot fix so we skip them in subsequent batches (avoids infinite loop)
        // Their original data is NEVER modified — we just exclude them from future fetches.
        $unparseable = [];

        while (true) {
            $query = $this->getProblematicQuery($field);

            if (! empty($unparseable)) {
                $query = $query->whereNotIn('_id', $unparseable);
            }

            $records = $query->take($batchSize)->get();

            if ($records->isEmpty()) {
                break;
            }

            $fixable = []; // [cleaned => [mongoId, ...]]
            $newUnparseable = [];

            foreach ($records as $item) {
                $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
                if (! $rawId) {
                    continue;
                }

                $mongoId = $this->toMongoId($rawId);
                $rawVal = is_array($item) ? ($item[$field] ?? '') : ($item->{$field} ?? '');
                $cleaned = Experience::formatMonthYear($rawVal, $allowPresent);

                if ($cleaned === null) {
                    // Cannot parse — leave the original data untouched, just skip this record
                    $newUnparseable[] = $mongoId;
                    $skipped++;
                } else {
                    $fixable[$cleaned][] = $mongoId;
                }
            }

            // Bulk-update parseable records (one query per unique cleaned value)
            foreach ($fixable as $cleanedVal => $mongoIds) {
                DB::connection('mongodb')->table('experience')
                    ->whereIn('_id', $mongoIds)
                    ->update([$field => $cleanedVal]);
                $count += count($mongoIds);
            }

            // Remember unparseable IDs so the next iteration skips them
            if (! empty($newUnparseable)) {
                $unparseable = array_merge($unparseable, $newUnparseable);
            }

            unset($records, $fixable, $newUnparseable);

            // If this whole batch was unparseable and nothing was fixed, stop
            if (empty($fixable) && empty($newUnparseable)) {
                break;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $msg = "Standardized {$count} record(s) for `{$field}` to 'Month Year' format.";
        if ($skipped > 0) {
            $msg .= " {$skipped} record(s) had unrecognisable date formats and were left unchanged.";
        }
        $this->statusMessage = $msg;
    }

    /** Start poll-driven fix for the active field (called by the button). */
    public function startFixAll(): void
    {
        $this->isProcessing = true;
        $this->batchField = $this->activeField;
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

        $field = $this->batchField;
        $allowPresent = ($field === 'end_date');
        $batchSize = 200;

        $query = $this->getProblematicQuery($field);

        if (! empty($this->batchUnparseable)) {
            $query = $query->whereNotIn('_id', $this->batchUnparseable);
        }

        $records = $query->take($batchSize)->get();

        if ($records->isEmpty()) {
            $this->isProcessing = false;
            $msg = "Done! Standardized {$this->batchProcessed} record(s) for `{$field}`";
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
            $rawVal = is_array($item) ? ($item[$field] ?? '') : ($item->{$field} ?? '');
            $cleaned = Experience::formatMonthYear($rawVal, $allowPresent);

            if ($cleaned === null) {
                $newUnparseable[] = $strId; // plain string, Livewire-safe
                $this->batchSkipped++;
            } else {
                $fixable[$cleaned][] = $mongoId;
            }
        }

        foreach ($fixable as $cleanedVal => $mongoIds) {
            DB::connection('mongodb')->table('experience')
                ->whereIn('_id', $mongoIds)
                ->update([$field => $cleanedVal]);
            $this->batchProcessed += count($mongoIds);
        }

        if (! empty($newUnparseable)) {
            // Convert back to ObjectId for the whereNotIn exclusion next poll
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

    /** Start poll-driven fix for both date fields. */
    public function startFixBothMaster(): void
    {
        $this->isBothProcessing = true;
        $this->bothProcessed = 0;
        $this->bothSkipped = 0;
        $this->bothUnparseable = [];
        $this->statusMessage = 'Processing both date fields…';
    }

    /** Called by wire:poll while $isBothProcessing — one batch of 200. */
    public function processBothBatch(): void
    {
        if (! $this->isBothProcessing) {
            return;
        }

        $validPattern = '^(January|February|March|April|May|June|July|August|September|October|November|December) [0-9]{4}$';
        $batchSize = 200;

        $query = DB::connection('mongodb')->table('experience')
            ->where(function ($q2) use ($validPattern) {
                $q2->where(function ($q) use ($validPattern) {
                    $q->whereNotNull('join_date')
                        ->where('join_date', '!=', '')
                        ->whereRaw(['join_date' => ['$not' => ['$regex' => $validPattern]]]);
                })->orWhere(function ($q) use ($validPattern) {
                    $q->whereNotNull('end_date')
                        ->where('end_date', '!=', '')
                        ->where('end_date', '!=', 'Present')
                        ->whereRaw(['end_date' => ['$not' => ['$regex' => $validPattern]]]);
                });
            });

        if (! empty($this->bothUnparseable)) {
            $query = $query->whereNotIn('_id', $this->bothUnparseable);
        }

        $records = $query->take($batchSize)->get();

        if ($records->isEmpty()) {
            $this->isBothProcessing = false;
            $msg = "Done! Standardized {$this->bothProcessed} record(s) across both date fields.";
            if ($this->bothSkipped > 0) {
                $msg .= " {$this->bothSkipped} left unchanged (unrecognisable format).";
            }
            $this->statusMessage = $msg;

            return;
        }

        $newUnparseable = [];
        $anyFixed = false;

        foreach ($records as $item) {
            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            if (! $rawId) {
                continue;
            }

            $mongoId = $this->toMongoId($rawId);
            $updates = [];

            $rawJoin = is_array($item) ? ($item['join_date'] ?? null) : ($item->join_date ?? null);
            if ($rawJoin !== null && $rawJoin !== '') {
                $c = Experience::formatMonthYear($rawJoin, false);
                if ($c !== null) {
                    $updates['join_date'] = $c;
                }
            }

            $rawEnd = is_array($item) ? ($item['end_date'] ?? null) : ($item->end_date ?? null);
            if ($rawEnd !== null && $rawEnd !== '') {
                $c = Experience::formatMonthYear($rawEnd, true);
                if ($c !== null) {
                    $updates['end_date'] = $c;
                }
            }

            if (! empty($updates)) {
                DB::connection('mongodb')->table('experience')
                    ->where('_id', $mongoId)
                    ->update($updates);
                $this->bothProcessed++;
                $anyFixed = true;
            } else {
                $newUnparseable[] = (string) $rawId; // plain string, Livewire-safe
                $this->bothSkipped++;
            }
        }

        if (! empty($newUnparseable)) {
            $this->bothUnparseable = array_merge($this->bothUnparseable, $newUnparseable);
        }

        if (! $anyFixed && empty($newUnparseable)) {
            $this->isBothProcessing = false;
            $this->statusMessage = "Done! {$this->bothProcessed} fixed, {$this->bothSkipped} left unchanged.";
        } else {
            $this->statusMessage = "Processing… {$this->bothProcessed} fixed so far.";
        }

        unset($records, $newUnparseable);
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
    @if($isProcessing || $isBothProcessing)
        wire:poll.800ms="{{ $isProcessing ? 'processBatch' : 'processBothBatch' }}"
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">Experience</strong> &bull; Field: <code class="font-mono text-rose-600 dark:text-rose-400">{{ $activeField }}</code>
        </span>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
            Experience &mdash; {{ $activeField }} Date Standardizer
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
                class="mt-2 p-3.5 bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-600 dark:text-zinc-300 space-y-1"
            >
                <p class="font-medium">This tool scans all Experience records for inconsistent date strings (e.g. <code>2015-08-01</code>, <code>08/2015</code>, <code>Aug 2015</code>) and standardizes them into the strict <strong>"Month Year"</strong> format (e.g. <code>"January 2026"</code>, <code>"August 2015"</code>, <code>"July 2026"</code>).</p>
                <p class="text-zinc-500">Unstandardized rows for {{ $activeField }}: <strong class="text-zinc-800 dark:text-zinc-200">{{ $problematicCount }}</strong> &bull; Total across both dates: <strong class="text-rose-600">{{ $totalProblematic }}</strong></p>
            </div>
        </div>
    </div>

    <!-- ROSE / PINK HERO BANNER CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-700 via-pink-600 to-rose-500 p-6 sm:p-7 text-white shadow-lg">
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
                        Experience &mdash; {{ $activeField }} Date Standardizer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-rose-100 leading-relaxed">
                    Standardizes all <code class="bg-rose-900/60 px-1.5 py-0.5 rounded font-mono text-white">{{ $activeField }}</code> date values in Experience documents into strict <strong class="underline font-bold">"Month Year"</strong> format (e.g. <code class="bg-rose-900/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"January 2026"</code>, <code class="bg-rose-900/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"August 2015"</code>, <code class="bg-rose-900/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"July 2026"</code>) for consistent timeline rendering and clean scholar profile formatting.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Suggestion* <span class="font-medium text-rose-100">Fix All Records runs in memory-safe chunks (250 per batch) with zero downtime.</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badge -->
            <div class="shrink-0">
                <div class="px-6 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[200px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-rose-100 mt-0.5">
                        UNSTANDARDIZED {{ strtoupper($activeField) }}
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
                    <strong class="text-red-600 dark:text-red-400">Sorting & Calculation Failures:</strong> 
                    Mixed date formats prevent accurate experience calculations (years of experience) and chronologically ordered CVs.
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
                    Parses any date format and formats it strictly as <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">"August 2015"</code>, <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">"January 2026"</code>, or <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">"July 2026"</code>.
                </li>
                <li>
                    <strong class="text-emerald-700 dark:text-emerald-400">Preserves "Present":</strong> 
                    For ongoing positions in <code class="font-mono">end_date</code>, keeps <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">"Present"</code> intact.
                </li>
            </ul>
        </div>
    </div>

    <!-- FIELD SELECTION TABS -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 p-3 shadow-xs">
        <div class="flex items-center justify-between px-2 mb-2">
            <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                Experience Date Fields:
            </span>
            @if($totalProblematic > 0)
                <button
                    type="button"
                    wire:click="startFixBothMaster"
                    wire:loading.attr="disabled"
                    @disabled($isBothProcessing || $isProcessing)
                    wire:confirm="Are you sure you want to standardize join_date and end_date across all Experience records?"
                    class="text-[11px] font-bold text-rose-600 hover:text-rose-700 dark:text-rose-400 hover:underline cursor-pointer flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    @if($isBothProcessing)
                        <svg class="size-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Processing… {{ $bothProcessed }} fixed</span>
                    @else
                        <span>⚡ Standardize Both Dates Across Entire Database ({{ $totalProblematic }})</span>
                    @endif
                </button>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($availableFields as $fieldKey => $fieldName)
                @php
                    $count = $fieldCounts[$fieldKey] ?? 0;
                    $isActive = $activeField === $fieldKey;
                @endphp
                <button
                    type="button"
                    wire:click="setField('{{ $fieldKey }}')"
                    class="px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition cursor-pointer border {{ $isActive ? 'bg-rose-600 text-white border-rose-600 shadow-xs' : 'bg-zinc-50 dark:bg-zinc-800/60 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}"
                >
                    <span class="font-mono font-bold">{{ $fieldKey }}</span>
                    @if($count > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $isActive ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300' }}">
                            {{ $count }}
                        </span>
                    @else
                        <span class="text-[10px] {{ $isActive ? 'text-white/80' : 'text-emerald-500' }}">✓ Clean</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <!-- ACTIONS TOOLBAR -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-3 bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-xs font-bold text-zinc-500 mr-1">Actions:</span>
            
            <!-- Fix All for Active Field -->
            <button 
                type="button" 
                wire:click="startFixAll"
                wire:loading.attr="disabled"
                @disabled($problematicCount === 0 || $isProcessing || $isBothProcessing)
                class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition flex items-center gap-1.5 shadow-sm cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
                @if($isProcessing && $batchField === $activeField)
                    <svg class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Processing… {{ $batchProcessed }} fixed</span>
                @else
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Standardize All {{ $activeField }} Records ({{ $problematicCount }})</span>
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
                class="px-2.5 py-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 text-xs font-semibold focus:ring-rose-500 focus:border-rose-500"
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
                                class="rounded border-zinc-300 dark:border-zinc-700 text-rose-600 focus:ring-rose-500"
                            />
                        </th>
                        <th class="px-4 py-3 w-44">Record ID / User ID</th>
                        <th class="px-4 py-3">Raw Date Stored</th>
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
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-rose-600 focus:ring-rose-500"
                                />
                            </td>

                            <!-- ID & User ID -->
                            <td class="px-4 py-3 font-mono text-[11px]">
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $row['id'] }}</div>
                                <div class="text-zinc-400 text-[10px]">User: {{ $row['user_id'] }}</div>
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
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-700 hover:bg-rose-600 hover:text-white hover:border-rose-600 transition shadow-xs cursor-pointer"
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
                                    <p class="font-bold text-zinc-700 dark:text-zinc-300">All {{ $activeField }} records are standardized!</p>
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
