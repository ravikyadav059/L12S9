<?php

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

new #[Layout('components.layouts.admin')] #[Title('Project — registered_contributors Fixer - Admin')] class extends Component {
    use WithPagination;

    public array $selectedRows = [];
    public bool $selectAll = false;
    public string $statusMessage = 'Ready';
    public int $perPage = 25;
    public string $search = '';

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
        $query = DB::connection('mongodb')->table('projects')
            ->whereRaw([
                '$expr' => [
                    '$eq' => [['$type' => '$registered_contributors'], 'string'],
                ],
            ]);

        if (! empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('project_name', 'regex', new Regex(preg_quote($s), 'i'))
                  ->orWhere('registered_contributors', 'regex', new Regex(preg_quote($s), 'i'));
            });
        }

        return $query;
    }

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $problematicCount = $query->count();
        $totalProjects = DB::connection('mongodb')->table('projects')->count();
        $standardizedCount = max(0, $totalProjects - $problematicCount);

        $paginator = $query->latest('_id')->paginate($this->perPage);

        $formatted = [];
        foreach ($paginator as $item) {
            $rawVal = is_array($item) ? ($item['registered_contributors'] ?? null) : ($item->registered_contributors ?? null);

            if (is_array($rawVal) || $rawVal === null) {
                continue;
            }

            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $id = $rawId ? (string) $rawId : '';
            $projectName = is_array($item) ? ($item['project_name'] ?? 'Untitled') : ($item->project_name ?? 'Untitled');
            $userId = is_array($item) ? ($item['user_id'] ?? 'N/A') : ($item->user_id ?? 'N/A');
            $val = is_string($rawVal) ? $rawVal : (string) $rawVal;

            $trimmed = trim($val);
            $displayVal = $trimmed === '' ? '"" (Empty String)' : $val;

            $cleaned = $this->parseToArrayOrNull($val);

            $reason = 'Plain Text String Stored';
            if ($trimmed === '' || $trimmed === ',') {
                $reason = 'Empty string stored (will convert to null)';
            } elseif (str_starts_with($val, ',')) {
                $reason = 'Leading Comma & Comma-Separated String';
            } elseif (str_contains($val, ',')) {
                $reason = 'Comma-Separated String Stored';
            } else {
                $reason = 'Single User ID Stored as Plain String';
            }

            $formatted[] = [
                'id' => $id,
                'project_name' => (string) $projectName,
                'user_id' => (string) ($userId ?: 'N/A'),
                'broken_value' => $displayVal,
                'cleaned_preview' => $cleaned ? json_encode($cleaned) : 'null (unset)',
                'cleaned_count' => $cleaned ? count($cleaned) : 0,
                'reason' => $reason,
            ];
        }

        return [
            'problematicCount' => $problematicCount,
            'totalProjects' => $totalProjects,
            'standardizedCount' => $standardizedCount,
            'records' => $formatted,
            'paginator' => $paginator,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);
        $raw = DB::connection('mongodb')->table('projects')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        if ($raw) {
            $rawVal = is_array($raw) ? ($raw['registered_contributors'] ?? '') : ($raw->registered_contributors ?? '');
            $cleaned = $this->parseToArrayOrNull($rawVal);

            DB::connection('mongodb')->table('projects')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update([
                    'registered_contributors' => $cleaned,
                    'updated_at' => now(),
                ]);

            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
            $msg = $cleaned ? "Fixed project #{$id} into native MongoDB array (" . count($cleaned) . " ID(s))!" : "Cleaned project #{$id} empty string to null!";
            $this->statusMessage = $msg;
            $this->dispatch('toast', message: $msg, type: 'success');
        }
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            $this->dispatch('toast', message: 'No rows selected.', type: 'warning');
            return;
        }

        $count = 0;
        foreach ($this->selectedRows as $id) {
            $mongoId = $this->toMongoId($id);
            $raw = DB::connection('mongodb')->table('projects')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->first();

            if ($raw) {
                $rawVal = is_array($raw) ? ($raw['registered_contributors'] ?? '') : ($raw->registered_contributors ?? '');
                $cleaned = $this->parseToArrayOrNull($rawVal);

                DB::connection('mongodb')->table('projects')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update([
                        'registered_contributors' => $cleaned,
                        'updated_at' => now(),
                    ]);

                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected project(s)!";
        $this->dispatch('toast', message: "Fixed {$count} project(s) into native MongoDB array.", type: 'success');
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
                    $rawVal = is_array($item) ? ($item['registered_contributors'] ?? '') : ($item->registered_contributors ?? '');
                    $cleaned = $this->parseToArrayOrNull($rawVal);

                    DB::connection('mongodb')->table('projects')
                        ->where(function ($q) use ($id, $mongoId) {
                            $q->where('_id', $mongoId)->orWhere('_id', $id);
                        })
                        ->update([
                            'registered_contributors' => $cleaned,
                            'updated_at' => now(),
                        ]);

                    $count++;
                }
            }

            unset($records);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed all {$count} project(s) into native MongoDB array!";
        $this->dispatch('toast', message: "Fixed all {$count} project(s) into native MongoDB array.", type: 'success');
    }

    protected function toMongoId(mixed $id): mixed
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
            return new ObjectId($id);
        }

        return $id;
    }

    protected function parseToArrayOrNull(mixed $value): ?array
    {
        if (is_array($value)) {
            $cleaned = array_values(array_unique(array_filter(array_map('trim', $value), fn ($item) => $item !== '')));
            return ! empty($cleaned) ? $cleaned : null;
        }

        if (is_string($value)) {
            $parts = preg_split('/[,;|]+/', trim($value));
            if (is_array($parts)) {
                $cleaned = array_values(array_unique(array_filter(array_map('trim', $parts), fn ($item) => $item !== '')));
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">Project</strong> &bull; Field: <code class="font-mono text-brand">registered_contributors</code>
        </span>
    </div>

    <!-- TITLE & SWITCHER PILLS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
                Project &mdash; registered_contributors Fixer
            </h2>
            <p class="text-xs text-zinc-500 mt-0.5">
                Standardize legacy string-stored contributor user IDs into native MongoDB BSON arrays for high-performance querying and contributor retrieval.
            </p>
        </div>

        <!-- Project Status Pill -->
        <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/80 rounded-xl text-xs font-semibold shrink-0">
            <span class="px-3 py-1.5 rounded-lg bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-xs flex items-center gap-1.5">
                <span>Registered Contributors</span>
                <span class="size-2 rounded-full {{ $problematicCount > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                <span class="text-[10px] font-mono opacity-80">{{ $problematicCount }} pending</span>
            </span>
        </div>
    </div>

    <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
    <div x-data="{ open: false }" class="text-xs">
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
            <p class="font-medium">This action will modify the MongoDB database immediately and cannot be undone automatically. So please be sure 100%.</p>
            <p class="text-zinc-500">Problematic rows detected: <strong class="text-zinc-800 dark:text-zinc-200">{{ $problematicCount }}</strong> out of {{ $totalProjects }} total projects.</p>
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
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                        Project &mdash; registered_contributors Fixer
                    </h3>
                </div>

                <p class="text-xs sm:text-sm text-blue-100 leading-relaxed">
                    This tool detects Project records where the <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-white">registered_contributors</code> field is stored as a comma-delimited string (e.g. <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-amber-200">"6490183ff28...,645a3a7ed..."</code>) or plain text, and standardizes them into clean native MongoDB <strong class="underline font-bold">arrays</strong> (<code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-emerald-200">["6490183ff28...", "645a3a7ed..."]</code>) or <code class="bg-blue-800/60 px-1.5 py-0.5 rounded font-mono text-zinc-300">null</code>.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Suggestion* <span class="font-medium text-blue-100">Review the converted preview in the table before clicking "Fix All Records".</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badges -->
            <div class="flex items-center gap-3 shrink-0">
                <div class="px-5 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[150px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-blue-100 mt-0.5">
                        Needs Conversion
                    </span>
                </div>
                <div class="px-5 py-4 rounded-xl bg-white/10 border border-white/20 backdrop-blur-xs text-center min-w-[150px] hidden sm:block">
                    <span class="block text-3xl font-black text-emerald-300">
                        {{ $standardizedCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-blue-100 mt-0.5">
                        Clean / Standardized
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- YELLOW EXPLANATION ALERT BOX -->
    <div class="p-5 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/90 dark:border-amber-800/60 text-xs text-zinc-700 dark:text-zinc-300 space-y-3 shadow-xs">
        <div class="space-y-1.5">
            <div class="flex items-center gap-1.5 font-bold text-amber-900 dark:text-amber-300">
                <svg class="size-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>Why is storing as a string problematic?</span>
            </div>
            <ul class="space-y-1.5 pl-5 list-disc text-zinc-600 dark:text-zinc-300">
                <li>
                    <strong class="text-amber-700 dark:text-amber-400">Inefficient MongoDB Queries:</strong> 
                    When stored as comma-separated string, MongoDB cannot index each contributor ID separately. Finding all projects where a user is a contributor requires slow regex searches instead of fast indexed array matching (<code class="font-mono text-zinc-800 dark:text-zinc-200">$in</code> queries).
                </li>
                <li>
                    <strong class="text-emerald-700 dark:text-emerald-400">What the Fixer Does:</strong> 
                    Splits comma-delimited strings, trims whitespace, removes duplicate IDs and empty values, and stores a clean native MongoDB BSON array (<code class="font-mono bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-1 py-0.5 rounded">["id1", "id2"]</code>) or <code class="font-mono text-zinc-800 dark:text-zinc-200">null</code> if empty.
                </li>
            </ul>
        </div>
    </div>

    <!-- ACTIONS TOOLBAR -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-3 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Fix All Records with Alpine confirmation -->
            <div x-data="{ confirming: false }">
                <button 
                    type="button" 
                    x-show="!confirming" 
                    @click="confirming = true"
                    wire:loading.attr="disabled"
                    @disabled($problematicCount === 0)
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition flex items-center gap-1.5 shadow-sm cursor-pointer disabled:opacity-50"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Fix All Records ({{ $problematicCount }})</span>
                </button>

                <div x-show="confirming" x-cloak class="flex items-center gap-2">
                    <span class="text-xs font-bold text-rose-600 dark:text-rose-400">Fix all {{ $problematicCount }} projects?</span>
                    <button 
                        type="button" 
                        @click="confirming = false; $wire.fixAll()" 
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 shadow-xs cursor-pointer"
                    >
                        Yes, Fix All
                    </button>
                    <button 
                        type="button" 
                        @click="confirming = false" 
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-300 cursor-pointer"
                    >
                        Cancel
                    </button>
                </div>
            </div>

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

            <!-- Search input -->
            <div class="relative">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search project or ID..." 
                    class="h-9 pl-8 pr-3 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-brand w-56"
                />
                <svg class="size-3.5 text-zinc-400 absolute left-2.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <!-- Per Page Dropdown -->
            <select 
                wire:model.live="perPage" 
                class="h-9 px-3 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-none"
            >
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
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

    <!-- PROBLEMATIC RECORDS TABLE -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700 dark:text-zinc-300">
                <thead class="bg-zinc-50/75 dark:bg-zinc-800/50 text-zinc-500 font-bold uppercase tracking-wider text-[10px] border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <th class="p-3.5 w-12 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll"
                                @checked($selectAll)
                                class="size-4 text-brand rounded border-zinc-300 dark:border-zinc-700 focus:ring-brand cursor-pointer" 
                            />
                        </th>
                        <th class="p-3.5 w-44">PROJECT _ID</th>
                        <th class="p-3.5 min-w-[200px]">PROJECT NAME</th>
                        <th class="p-3.5 min-w-[220px]">CURRENT BROKEN VALUE (STRING)</th>
                        <th class="p-3.5 min-w-[220px]">CONVERTED PREVIEW (ARRAY)</th>
                        <th class="p-3.5">PROBLEM REASON</th>
                        <th class="p-3.5 text-right w-28">ACTION</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60 font-normal">
                    @forelse($records as $row)
                        <tr wire:key="proj-row-{{ $row['id'] }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition">
                            <td class="p-3.5 text-center">
                                <input 
                                    wire:key="proj-check-{{ $row['id'] }}"
                                    type="checkbox" 
                                    wire:model.live="selectedRows"
                                    value="{{ $row['id'] }}"
                                    class="size-4 text-brand rounded border-zinc-300 dark:border-zinc-700 focus:ring-brand cursor-pointer"
                                />
                            </td>
                            <td class="p-3.5 font-mono text-[11px] text-zinc-600 dark:text-zinc-400 select-all">
                                {{ $row['id'] }}
                            </td>
                            <td class="p-3.5 font-semibold text-zinc-900 dark:text-white">
                                {{ $row['project_name'] }}
                            </td>
                            <td class="p-3.5">
                                <span class="inline-block max-w-[320px] font-mono text-[11px] text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-1 rounded-lg border border-amber-200 dark:border-amber-800 break-all">
                                    {{ $row['broken_value'] }}
                                </span>
                            </td>
                            <td class="p-3.5">
                                <span class="inline-block max-w-[320px] font-mono text-[11px] text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-1 rounded-lg border border-emerald-200 dark:border-emerald-800 break-all">
                                    {{ $row['cleaned_preview'] }}
                                </span>
                                @if($row['cleaned_count'] > 0)
                                    <span class="text-[10px] text-zinc-400 ml-1">({{ $row['cleaned_count'] }} item{{ $row['cleaned_count'] > 1 ? 's' : '' }})</span>
                                @endif
                            </td>
                            <td class="p-3.5">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    {{ $row['reason'] }}
                                </span>
                            </td>
                            <td class="p-3.5 text-right">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-brand hover:bg-brand-600 text-white transition shadow-2xs cursor-pointer active:scale-95 whitespace-nowrap"
                                >
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Fix to Array</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-zinc-400">
                                <div class="size-12 rounded-full bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 flex items-center justify-center mx-auto mb-2 text-xl">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div class="font-bold text-zinc-800 dark:text-zinc-200">All Project records have standardized registered_contributors!</div>
                                <p class="text-xs text-zinc-500 mt-1">Zero legacy strings found. All projects store clean native MongoDB BSON arrays or null.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paginator->hasPages())
            <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
