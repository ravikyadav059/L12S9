<?php

use App\Models\ReviewerProfile;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('ReviewerProfile — Array Fields Fixer - Admin')] class extends Component {
    use WithPagination;

    #[Url(as: 'field')]
    public string $activeField = 'experience';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public string $statusMessage = 'Ready';

    public int $perPage = 25;

    public array $availableFields = [
        'experience' => 'experience',
        'education' => 'education',
        'publication' => 'publication',
        'projects' => 'projects',
        'seminar' => 'seminar',
        'certificate' => 'certificate',
        'phd' => 'phd',
        'award' => 'award',
        'RoleInResearchJournal' => 'RoleInResearchJournal',
        'invited_position' => 'invited_position',
        'membership' => 'membership',
        'patent' => 'patent',
    ];

    public function mount(?string $field = null): void
    {
        if ($field && array_key_exists($field, $this->availableFields)) {
            $this->activeField = $field;
        } elseif ($field) {
            foreach ($this->availableFields as $key => $name) {
                if (strtolower(str_replace(['_', '-'], '', $key)) === strtolower(str_replace(['_', '-'], '', $field))) {
                    $this->activeField = $key;
                    break;
                }
            }
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

        return DB::connection('mongodb')->table('reviewer_profile')
            ->whereRaw([
                '$expr' => [
                    '$eq' => [['$type' => '$'.$targetField], 'string'],
                ],
            ]);
    }

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $totalCount = $query->count();
        $paginator = $query->latest('_id')->paginate($this->perPage);

        $fieldCounts = [];
        foreach (array_keys($this->availableFields) as $f) {
            $fieldCounts[$f] = $this->getProblematicQuery($f)->count();
        }
        $totalProblematicAllFields = array_sum($fieldCounts);

        $formatted = [];
        foreach ($paginator as $item) {
            $rawVal = is_array($item) ? ($item[$this->activeField] ?? null) : ($item->{$this->activeField} ?? null);

            if (is_array($rawVal) || $rawVal === null) {
                continue;
            }

            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $id = $rawId ? (string) $rawId : '';
            $val = is_string($rawVal) ? $rawVal : (string) $rawVal;

            $trimmed = trim($val);
            $displayVal = $trimmed === '' ? '"" (Empty String)' : $val;

            $reason = 'Plain Text String Stored';
            if ($trimmed === '' || $trimmed === ',' || $trimmed === 'null' || $trimmed === '[]') {
                $reason = 'Empty / null string (will be converted to null)';
            } elseif (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $reason = 'JSON-Encoded Array String';
            } elseif (str_contains($val, ',') || str_contains($val, ';') || str_contains($val, '|')) {
                $reason = 'Delimiter-Separated String';
            } else {
                $reason = 'Single ID String';
            }

            $previewArray = $this->parseToArrayOrNull($rawVal);

            $date = '-';
            $createdAt = is_array($item) ? ($item['created_at'] ?? null) : ($item->created_at ?? null);
            if ($createdAt) {
                $date = is_string($createdAt) ? substr($createdAt, 0, 10) : ($createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d') : (string) $createdAt);
            }

            $userId = is_array($item) ? ($item['user_id'] ?? 'N/A') : ($item->user_id ?? 'N/A');
            $uniqueId = is_array($item) ? ($item['unique_id'] ?? null) : ($item->unique_id ?? null);

            $formatted[] = [
                'id' => $id,
                'user_id' => (string) ($userId ?: 'N/A'),
                'unique_id' => $uniqueId ? (string) $uniqueId : null,
                'broken_value' => $displayVal,
                'preview_array' => $previewArray,
                'reason' => $reason,
                'date' => $date,
            ];
        }

        return [
            'problematicCount' => $totalCount,
            'fieldCounts' => $fieldCounts,
            'totalProblematicAllFields' => $totalProblematicAllFields,
            'problematicRecords' => $formatted,
            'paginator' => $paginator,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);
        $raw = DB::connection('mongodb')->table('reviewer_profile')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        if ($raw) {
            $rawVal = is_array($raw) ? ($raw[$this->activeField] ?? '') : ($raw->{$this->activeField} ?? '');
            $cleaned = $this->parseToArrayOrNull($rawVal);

            DB::connection('mongodb')->table('reviewer_profile')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update([$this->activeField => $cleaned]);

            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
            $msg = $cleaned ? "Fixed record #{$id} `{$this->activeField}` into native MongoDB array!" : "Cleaned record #{$id} `{$this->activeField}` empty string to null!";
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
            $raw = DB::connection('mongodb')->table('reviewer_profile')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->first();

            if ($raw) {
                $rawVal = is_array($raw) ? ($raw[$this->activeField] ?? '') : ($raw->{$this->activeField} ?? '');
                $cleaned = $this->parseToArrayOrNull($rawVal);

                DB::connection('mongodb')->table('reviewer_profile')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update([$this->activeField => $cleaned]);

                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected record(s) for `{$this->activeField}`!";
    }

    public function fixActiveFieldAll(): void
    {
        $count = 0;
        $batchSize = 250;

        while (true) {
            $records = $this->getProblematicQuery($this->activeField)->take($batchSize)->get();

            if ($records->isEmpty()) {
                break;
            }

            foreach ($records as $item) {
                $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
                if ($rawId) {
                    $id = (string) $rawId;
                    $mongoId = $this->toMongoId($rawId);
                    $rawVal = is_array($item) ? ($item[$this->activeField] ?? '') : ($item->{$this->activeField} ?? '');
                    $cleaned = $this->parseToArrayOrNull($rawVal);

                    DB::connection('mongodb')->table('reviewer_profile')
                        ->where(function ($q) use ($id, $mongoId) {
                            $q->where('_id', $mongoId)->orWhere('_id', $id);
                        })
                        ->update([$this->activeField => $cleaned]);

                    $count++;
                }
            }

            unset($records);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully converted all {$count} record(s) for `{$this->activeField}` to native array / null!";
    }

    public function fixAllFieldsMaster(): void
    {
        $fields = array_keys($this->availableFields);
        $count = 0;
        $batchSize = 200;

        $orConditions = array_map(function ($f) {
            return ['$expr' => ['$eq' => [['$type' => '$'.$f], 'string']]];
        }, $fields);

        while (true) {
            $records = DB::connection('mongodb')->table('reviewer_profile')
                ->whereRaw(['$or' => $orConditions])
                ->take($batchSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            foreach ($records as $item) {
                $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
                if (! $rawId) {
                    continue;
                }

                $id = (string) $rawId;
                $mongoId = $this->toMongoId($rawId);
                $updates = [];

                foreach ($fields as $f) {
                    $rawVal = is_array($item) ? ($item[$f] ?? null) : ($item->{$f} ?? null);
                    if (is_string($rawVal)) {
                        $updates[$f] = $this->parseToArrayOrNull($rawVal);
                    }
                }

                if (! empty($updates)) {
                    DB::connection('mongodb')->table('reviewer_profile')
                        ->where(function ($q) use ($id, $mongoId) {
                            $q->where('_id', $mongoId)->orWhere('_id', $id);
                        })
                        ->update($updates);

                    $count++;
                }
            }

            unset($records);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Master Ingestion Complete! Successfully fixed all 12 array fields across {$count} ReviewerProfile record(s)!";
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

    protected function parseToArrayOrNull(mixed $value): ?array
    {
        if (is_array($value)) {
            $cleaned = array_values(array_filter(array_map('trim', $value)));

            return ! empty($cleaned) ? $cleaned : null;
        }

        $str = trim((string) $value);
        if ($str === '' || $str === ',' || strtolower($str) === 'null' || $str === '[]') {
            return null;
        }

        // Handle JSON array strings e.g. ["69a6..."] or ["id1","id2"]
        if (str_starts_with($str, '[') && str_ends_with($str, ']')) {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) {
                $cleaned = array_values(array_filter(array_map(function ($item) {
                    return is_string($item) ? trim($item, " \t\n\r\0\x0B\"'[]") : $item;
                }, $decoded), fn ($item) => $item !== '' && $item !== null));

                return ! empty($cleaned) ? array_values(array_unique($cleaned)) : null;
            }
        }

        // Split on comma, semicolon, newline, pipe
        $items = preg_split('/[,;\n\r|]+/', $str);
        $cleaned = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                $trimmed = trim((string) $item, " \t\n\r\0\x0B\"'[]");
                if ($trimmed !== '' && strtolower($trimmed) !== 'null') {
                    $cleaned[] = $trimmed;
                }
            }
        }

        return ! empty($cleaned) ? array_values(array_unique($cleaned)) : null;
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">ReviewerProfile</strong> &bull; Field: <code class="font-mono text-emerald-600 dark:text-emerald-400">{{ $activeField }}</code>
        </span>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
            ReviewerProfile &mdash; {{ $activeField }} Fixer
        </h2>
        
        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-emerald-600 transition cursor-pointer select-none"
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
                <p class="font-medium">This action transforms legacy serialized strings, JSON array strings (e.g. <code>["69a6..."]</code>), or comma-delimited ID strings across 12 ReviewerProfile fields into standardized MongoDB BSON arrays. This directly updates the database in lightweight batches.</p>
                <p class="text-zinc-500">Active Field unformatted rows: <strong class="text-zinc-800 dark:text-zinc-200">{{ $problematicCount }}</strong> &bull; Total across all 12 fields: <strong class="text-emerald-600">{{ $totalProblematicAllFields }}</strong></p>
            </div>
        </div>
    </div>

    <!-- EMERALD / TEAL HERO BANNER CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-800 via-teal-700 to-emerald-600 p-6 sm:p-7 text-white shadow-lg">
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
                        ReviewerProfile &mdash; {{ $activeField }} Fixer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-emerald-100 leading-relaxed">
                    This tool detects ReviewerProfile records where <code class="bg-emerald-900/60 px-1.5 py-0.5 rounded font-mono text-white">{{ $activeField }}</code> is stored as a legacy string (e.g. <code class="bg-emerald-900/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"[\"69a6...\"]"</code> or <code class="bg-emerald-900/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"id1,id2"</code>) and converts them into standardized MongoDB <strong class="underline font-bold">native arrays</strong> (<code class="bg-emerald-900/60 px-1.5 py-0.5 rounded font-mono text-white">["id1", "id2"]</code>) for high-performance indexing and querying.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Suggestion* <span class="font-medium text-emerald-100">Fix All Records runs in memory-safe chunks (250 per batch) with zero downtime.</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badge -->
            <div class="shrink-0">
                <div class="px-6 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[200px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-emerald-100 mt-0.5">
                        STRING {{ strtoupper($activeField) }} RECORDS
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
                    <strong class="text-amber-700 dark:text-amber-400">String / JSON String Type Stored:</strong> 
                    The <code class="font-mono text-zinc-800 dark:text-zinc-200">{{ $activeField }}</code> field stores string values (e.g. <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">"[\"69a67...\"]"</code> or <code class="font-mono bg-zinc-200/70 dark:bg-zinc-800 px-1 py-0.5 rounded text-amber-600 dark:text-amber-400">"id1,id2"</code>) instead of native MongoDB BSON array values (<code class="font-mono text-emerald-600">["id1", "id2"]</code>).
                </li>
                <li>
                    <strong class="text-red-600 dark:text-red-400">Query & Relationship Mismatches:</strong> 
                    In MongoDB, querying array operators like <code class="font-mono">$in</code>, <code class="font-mono">$all</code>, <code class="font-mono">$size</code>, or relationships will fail to match records where the field is a string, preventing author profiles and publications from linking properly.
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
                    <strong class="text-emerald-700 dark:text-emerald-400">Decodes JSON & Delimiters into Native Arrays:</strong> 
                    JSON arrays, comma/semicolon/pipe-separated IDs are parsed, trimmed, and converted to native MongoDB arrays <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">["69a6...", "690b..."]</code>.
                </li>
                <li>
                    <strong class="text-emerald-700 dark:text-emerald-400">Cleans Empty Strings to NULL:</strong> 
                    Empty strings <code class="font-mono">""</code>, <code class="font-mono">"null"</code>, and <code class="font-mono">"[]"</code> are standardized to <code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">null</code> to keep the database index clean.
                </li>
            </ul>
        </div>
    </div>

    <!-- FIELD SELECTION TABS -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 p-3 shadow-xs">
        <div class="flex items-center justify-between px-2 mb-2">
            <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                ReviewerProfile Array Fields (12 Total):
            </span>
            @if($totalProblematicAllFields > 0)
                <button
                    type="button"
                    wire:click="fixAllFieldsMaster"
                    wire:loading.attr="disabled"
                    wire:confirm="Are you sure you want to fix all 12 array fields across the entire database in one batch?"
                    class="text-[11px] font-bold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 hover:underline cursor-pointer flex items-center gap-1"
                >
                    <span>⚡ Fix All 12 Fields Across Entire Database ({{ $totalProblematicAllFields }})</span>
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
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition cursor-pointer border {{ $isActive ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-zinc-50 dark:bg-zinc-800/60 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}"
                >
                    <span class="font-mono">{{ $fieldKey }}</span>
                    @if($count > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $isActive ? 'bg-white/20 text-white' : 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300' }}">
                            {{ $count }}
                        </span>
                    @else
                        <span class="text-[10px] {{ $isActive ? 'text-white/80' : 'text-emerald-500' }}">✓</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <!-- ACTIONS TOOLBAR (MATCHING SCREENSHOT) -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-3 bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-xs font-bold text-zinc-500 mr-1">Actions:</span>
            
            <!-- Fix All for Active Field -->
            <button 
                type="button" 
                wire:click="fixActiveFieldAll"
                wire:loading.attr="disabled"
                @disabled($problematicCount === 0)
                wire:confirm="Are you sure you want to fix all {{ $problematicCount }} records for '{{ $activeField }}'?"
                class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition flex items-center gap-1.5 shadow-sm cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg wire:loading.remove wire:target="fixActiveFieldAll" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <svg wire:loading wire:target="fixActiveFieldAll" class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Fix All {{ $activeField }} Records ({{ $problematicCount }})</span>
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
                class="px-2.5 py-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 text-xs font-semibold focus:ring-emerald-500 focus:border-emerald-500"
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
                                class="rounded border-zinc-300 dark:border-zinc-700 text-emerald-600 focus:ring-emerald-500"
                            />
                        </th>
                        <th class="px-4 py-3 w-44">Record ID / User ID</th>
                        <th class="px-4 py-3">Raw String Stored</th>
                        <th class="px-4 py-3">Parsed Array Preview</th>
                        <th class="px-4 py-3 w-48">Detected Issue</th>
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
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-emerald-600 focus:ring-emerald-500"
                                />
                            </td>

                            <!-- ID & User ID -->
                            <td class="px-4 py-3 font-mono text-[11px]">
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $row['id'] }}</div>
                                <div class="text-zinc-400 text-[10px]">User: {{ $row['user_id'] }}</div>
                                @if($row['unique_id'])
                                    <div class="text-zinc-400 text-[10px]">Unique: {{ $row['unique_id'] }}</div>
                                @endif
                            </td>

                            <!-- Broken Value -->
                            <td class="px-4 py-3">
                                <div class="max-w-xs break-all font-mono text-[11px] bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 px-2.5 py-1.5 rounded-lg border border-red-200 dark:border-red-900/50">
                                    {{ $row['broken_value'] }}
                                </div>
                            </td>

                            <!-- Parsed Array Preview -->
                            <td class="px-4 py-3">
                                @if($row['preview_array'] && count($row['preview_array']) > 0)
                                    <div class="flex flex-wrap gap-1 max-w-sm">
                                        @foreach($row['preview_array'] as $item)
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                {{ $item }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-zinc-400 font-mono text-[11px] italic">null (will clean empty string)</span>
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
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-white dark:bg-zinc-800 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 transition shadow-xs cursor-pointer"
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
                                    <p class="font-bold text-zinc-700 dark:text-zinc-300">All {{ $activeField }} records are clean!</p>
                                    <p class="text-xs text-zinc-500">No unformatted string values detected for this field.</p>
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
