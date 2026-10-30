<?php

use App\Models\DegreeType;
use App\Models\Education;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

new #[Layout('components.layouts.admin')] #[Title('Education — degree_type Fixer - Admin')] class extends Component {
    use WithPagination;

    public array $selectedRows = [];
    public array $assignedDegreeTypes = [];
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
                return $this->extractIdString($item);
            })->filter()->all();
        } else {
            $this->selectedRows = [];
        }
    }

    protected function extractIdString(mixed $item): string
    {
        if (is_array($item)) {
            $val = $item['_id'] ?? $item['id'] ?? null;
        } elseif (is_object($item)) {
            $val = $item->_id ?? $item->id ?? null;
        } else {
            $val = $item;
        }

        if ($val instanceof ObjectId) {
            return (string) $val;
        }

        return is_scalar($val) ? (string) $val : '';
    }

    protected function toMongoId(string $id): ObjectId|string
    {
        try {
            return preg_match('/^[0-9a-fA-F]{24}$/', $id) ? new ObjectId($id) : $id;
        } catch (\Throwable) {
            return $id;
        }
    }

    protected function getProblematicQuery()
    {
        $query = DB::connection('mongodb')->table('education')
            ->whereNotNull('degree_type')
            ->where('degree_type', '!=', '')
            ->whereRaw(['degree_type' => ['$not' => new Regex('^[0-9a-fA-F]{24}$')]]) ;

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $query->where('degree_type', 'regex', new Regex(preg_quote($term), 'i'));
        }

        return $query;
    }

    public function findMatchingDegreeType(string $rawText): ?DegreeType
    {
        $text = trim($rawText);
        if ($text === '') {
            return null;
        }

        // 1. Exact case-insensitive match
        $exact = DegreeType::where('degree_type', 'regex', new Regex('^'.preg_quote($text).'$', 'i'))->first();
        if ($exact) {
            return $exact;
        }

        // 2. Normalized punctuation / dots removal (e.g. "ph.d." == "phd", "m.tech" == "mtech", "b.e." == "be")
        $noDots = str_replace(['.', '-', ' '], '', $text);
        if ($noDots !== '') {
            $candidates = DegreeType::where('degree_type', 'regex', new Regex('^'.preg_quote(substr($text, 0, 4)), 'i'))->limit(50)->get();
            foreach ($candidates as $cand) {
                $candNoDots = str_replace(['.', '-', ' '], '', (string) $cand->degree_type);
                if (strcasecmp($candNoDots, $noDots) === 0) {
                    return $cand;
                }
            }
        }

        return null;
    }

    public function with(): array
    {
        $query = $this->getProblematicQuery();
        $totalProblematic = DB::connection('mongodb')->table('education')
            ->whereNotNull('degree_type')
            ->where('degree_type', '!=', '')
            ->whereRaw(['degree_type' => ['$not' => new Regex('^[0-9a-fA-F]{24}$')]])
            ->count();

        $totalEducation = DB::connection('mongodb')->table('education')->count();
        $totalStandardized = max(0, $totalEducation - $totalProblematic);

        $gradeCount = DB::connection('mongodb')->table('education')
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->whereRaw(['grade' => ['$not' => new Regex('^[0-9a-fA-F]{24}$')]])
            ->count();

        $fieldOfStudyCount = DB::connection('mongodb')->table('education')
            ->whereNotNull('field_of_study')
            ->where('field_of_study', '!=', '')
            ->whereRaw(['field_of_study' => ['$not' => new Regex('^[0-9a-fA-F]{24}$')]])
            ->count();

        $instCount = DB::connection('mongodb')->table('education')
            ->whereNotNull('institution_id')
            ->where('institution_id', '!=', '')
            ->whereRaw(['institution_id' => ['$not' => new Regex('^[0-9a-fA-F]{24}$')]])
            ->count();

        $paginator = $query->latest('_id')->paginate($this->perPage);

        $formatted = [];
        $autoMatchableCount = 0;

        foreach ($paginator as $item) {
            $id = (string) $this->extractIdString($item);
            $rawDegree = is_array($item) ? ($item['degree_type'] ?? '') : ($item->degree_type ?? '');
            $fieldOfStudy = is_array($item) ? ($item['field_of_study'] ?? '-') : ($item->field_of_study ?? '-');
            $passoutYear = is_array($item) ? ($item['passout_year'] ?? '-') : ($item->passout_year ?? '-');

            $matched = $this->findMatchingDegreeType((string) $rawDegree);
            $matchedId = $matched ? (string) $matched->_id : '';
            $matchedName = $matched ? $matched->degree_type : '';

            if ($matched) {
                $autoMatchableCount++;
            }

            if (! isset($this->assignedDegreeTypes[$id]) && $matchedId !== '') {
                $this->assignedDegreeTypes[$id] = $matchedId;
            }

            $currentAssignedId = $this->assignedDegreeTypes[$id] ?? $matchedId;
            $assignedName = $currentAssignedId === $matchedId ? $matchedName : '';

            $matchType = 'No Match';
            if ($matched) {
                if (strtolower(trim((string) $rawDegree)) === strtolower(trim($matched->degree_type))) {
                    $matchType = 'Exact Match';
                } else {
                    $matchType = 'Suggested Match';
                }
            }

            $formatted[] = [
                'id' => $id,
                'field_of_study' => (string) $fieldOfStudy,
                'passout_year' => (string) $passoutYear,
                'raw_degree' => (string) $rawDegree,
                'matched_id' => $matchedId,
                'matched_name' => $matchedName,
                'assigned_id' => $currentAssignedId,
                'assigned_name' => $assignedName,
                'match_type' => $matchType,
            ];
        }

        return [
            'totalProblematic' => $totalProblematic,
            'totalEducation' => $totalEducation,
            'totalStandardized' => $totalStandardized,
            'gradeProblematicCount' => $gradeCount,
            'fieldOfStudyProblematicCount' => $fieldOfStudyCount,
            'instProblematicCount' => $instCount,
            'records' => $formatted,
            'paginator' => $paginator,
            'autoMatchableCount' => $autoMatchableCount,
        ];
    }

    public function createAndFix(string $id, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            $this->dispatch('toast', message: 'Degree type title cannot be empty.', type: 'danger');

            return;
        }

        // Case-insensitive check if it already exists
        $existing = DegreeType::where('degree_type', 'regex', new Regex('^'.preg_quote($name).'$', 'i'))->first();
        if ($existing) {
            $targetId = (string) $existing->_id;
        } else {
            $created = DegreeType::create([
                'degree_type' => $name,
                'status' => 1,
            ]);
            $targetId = (string) $created->_id;
        }

        DB::connection('mongodb')->table('education')
            ->where('_id', $this->toMongoId($id))
            ->update([
                'degree_type' => (string) $targetId,
                'updated_at' => now(),
            ]);

        $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
        unset($this->assignedDegreeTypes[(string) $id]);

        $this->statusMessage = "Created/Mapped DegreeType '{$name}' and fixed record #{$id}!";
        $this->dispatch('toast', message: "Created & Fixed to DegreeType: {$name}", type: 'success');
    }

    public function fixSingle(string $id): void
    {
        $targetId = $this->assignedDegreeTypes[$id] ?? null;

        if (! $targetId || $targetId === '__create__') {
            $item = DB::connection('mongodb')->table('education')->where('_id', $this->toMongoId($id))->first();
            if ($item) {
                $raw = is_array($item) ? ($item['degree_type'] ?? '') : ($item->degree_type ?? '');
                if ($targetId === '__create__') {
                    $this->createAndFix($id, (string) $raw);

                    return;
                }
                $matched = $this->findMatchingDegreeType((string) $raw);
                $targetId = $matched ? (string) $matched->_id : null;
            }
        }

        if (! $targetId) {
            $this->statusMessage = "Error: Please select or create a DegreeType for record #{$id} before fixing.";
            $this->dispatch('toast', message: 'Please select a DegreeType or click "+ Create & Fix".', type: 'danger');

            return;
        }

        $degreeObj = DegreeType::find($targetId);
        $degreeTitle = $degreeObj ? $degreeObj->degree_type : $targetId;

        DB::connection('mongodb')->table('education')
            ->where('_id', $this->toMongoId($id))
            ->update([
                'degree_type' => (string) $targetId,
                'updated_at' => now(),
            ]);

        $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
        unset($this->assignedDegreeTypes[$id]);

        $this->statusMessage = "Fixed record #{$id}: degree_type updated to '{$degreeTitle}' ({$targetId}).";
        $this->dispatch('toast', message: "Fixed to DegreeType: {$degreeTitle}", type: 'success');
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            $this->dispatch('toast', message: 'No rows selected.', type: 'warning');

            return;
        }

        $updated = 0;

        foreach ($this->selectedRows as $id) {
            $targetId = $this->assignedDegreeTypes[$id] ?? null;

            if (! $targetId || $targetId === '__create__') {
                $item = DB::connection('mongodb')->table('education')->where('_id', $this->toMongoId($id))->first();
                if ($item) {
                    $raw = is_array($item) ? ($item['degree_type'] ?? '') : ($item->degree_type ?? '');
                    if ($targetId === '__create__') {
                        $this->createAndFix($id, (string) $raw);
                        $updated++;
                        continue;
                    }
                    $matched = $this->findMatchingDegreeType((string) $raw);
                    $targetId = $matched ? (string) $matched->_id : null;
                }
            }

            if ($targetId && $targetId !== '__create__') {
                DB::connection('mongodb')->table('education')
                    ->where('_id', $this->toMongoId($id))
                    ->update([
                        'degree_type' => (string) $targetId,
                        'updated_at' => now(),
                    ]);
                $updated++;
                unset($this->assignedDegreeTypes[$id]);
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$updated} selected education records.";
        $this->dispatch('toast', message: "Fixed {$updated} records successfully.", type: 'success');
    }

    public function fixAllAutoMatched(): void
    {
        $records = DB::connection('mongodb')->table('education')
            ->whereNotNull('degree_type')
            ->where('degree_type', '!=', '')
            ->whereRaw(['degree_type' => ['$not' => new Regex('^[0-9a-fA-F]{24}$')]])
            ->get();

        $updated = 0;

        foreach ($records as $item) {
            $id = $this->extractIdString($item);
            $raw = is_array($item) ? ($item['degree_type'] ?? '') : ($item->degree_type ?? '');

            // Pre-assigned in UI or auto-match
            $targetId = $this->assignedDegreeTypes[$id] ?? null;
            if (! $targetId || $targetId === '__create__') {
                $matched = $this->findMatchingDegreeType((string) $raw);
                $targetId = $matched ? (string) $matched->_id : null;
            }

            if ($targetId && $targetId !== '__create__') {
                DB::connection('mongodb')->table('education')
                    ->where('_id', $this->toMongoId($id))
                    ->update([
                        'degree_type' => (string) $targetId,
                        'updated_at' => now(),
                    ]);
                $updated++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->assignedDegreeTypes = [];

        $this->statusMessage = "Auto-fix complete: Updated {$updated} education records with standardized DegreeType IDs.";
        $this->dispatch('toast', message: "Auto-fixed {$updated} records to DegreeType IDs.", type: 'success');
    }
}; ?>

<div class="space-y-6">
    <!-- TOP BREADCRUMB & SWITCHER -->
    <div class="flex items-center justify-between flex-wrap gap-3">
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
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-brand text-white shadow-xs">
                <i class="fa-solid fa-graduation-cap text-xs"></i>
                <span>degree_type Fixer</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20 text-white font-extrabold">{{ $totalProblematic }}</span>
            </span>
            <a 
                href="{{ route('admin.database-fixing.education.field-of-study') }}" 
                wire:navigate
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
            >
                <i class="fa-solid fa-book-open text-xs"></i>
                <span>field_of_study Fixer</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">{{ $fieldOfStudyProblematicCount }}</span>
            </a>
            <a 
                href="{{ route('admin.database-fixing.education.grade') }}" 
                wire:navigate
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
            >
                <i class="fa-solid fa-award text-xs"></i>
                <span>grade Fixer</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">{{ $gradeProblematicCount }}</span>
            </a>
            <a 
                href="{{ route('admin.database-fixing.education.institution') }}" 
                wire:navigate
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition"
            >
                <i class="fa-solid fa-building-columns text-xs"></i>
                <span>institution_id Fixer</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">{{ $instProblematicCount }}</span>
            </a>
        </div>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight flex items-center gap-2">
            <span>Education &mdash; degree_type Text-to-ID Standardizer</span>
            <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                Legacy String &rarr; DegreeType ObjectId
            </span>
        </h2>
        
        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-brand transition cursor-pointer select-none"
            >
                <span x-text="open ? '▴' : '▾'">▾</span>
                <span>Before <strong class="underline">You DO Action</strong> please read what this tool does. <span class="text-zinc-400 font-normal">(Click me)</span></span>
            </button>
            
            <div 
                x-show="open" 
                x-cloak 
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-2 p-4 bg-zinc-100/90 dark:bg-zinc-800/90 border border-zinc-200 dark:border-zinc-700 rounded-2xl text-zinc-600 dark:text-zinc-300 space-y-2"
            >
                <p class="font-medium text-zinc-800 dark:text-zinc-200">
                    This tool scans all documents in the <code class="font-mono text-brand font-bold">education</code> collection to find records where <code class="font-mono text-amber-600 dark:text-amber-400">degree_type</code> stores a legacy plain text string (e.g. <em>"Mastars"</em>, <em>"Diploma"</em>, <em>"BE"</em>, <em>"Ph.D."</em>) instead of a normalized 24-character MongoDB ObjectId.
                </p>
                <p>
                    It matches the raw text against the <code class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">degree_type</code> collection in a <strong>case-insensitive</strong> manner and assigns the corresponding <code class="font-mono text-emerald-600 font-bold">_id</code>. If no match exists, you can click "+ Create & Fix" to add it to the collection with status 1.
                </p>
                <p class="text-[11px] text-zinc-500">
                    Records missing a <code class="font-mono">user_id</code> are safely displayed as <span class="font-semibold text-zinc-600 dark:text-zinc-400">N/A (No user_id)</span> without any errors.
                </p>
            </div>
        </div>
    </div>

    <!-- HERO STATS BANNER -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-700 via-indigo-600 to-brand p-6 sm:p-7 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 shadow-inner">
                        <i class="fa-solid fa-graduation-cap text-lg"></i>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                        Education Degree Type Migration Engine
                    </h3>
                </div>
                <p class="text-xs sm:text-sm text-blue-100 leading-relaxed">
                    Convert legacy un-indexed raw text strings in the <code class="bg-blue-900/60 px-1.5 py-0.5 rounded font-mono text-white">degree_type</code> field into standardized 24-character MongoDB ObjectIds referencing the <code class="bg-blue-900/60 px-1.5 py-0.5 rounded font-mono text-emerald-200">degree_type</code> catalog.
                </p>
            </div>

            <!-- Stats Counters -->
            <div class="flex items-center gap-3 shrink-0 flex-wrap">
                <div class="px-4 py-3 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[130px]">
                    <span class="block text-2xl font-black text-white">{{ number_format($totalEducation) }}</span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-blue-100 mt-0.5">Total Education</span>
                </div>
                <div class="px-4 py-3 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[130px]">
                    <span class="block text-2xl font-black text-amber-200">{{ number_format($totalProblematic) }}</span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-blue-100 mt-0.5">Plain Text Rows</span>
                </div>
                <div class="px-4 py-3 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[130px]">
                    <span class="block text-2xl font-black text-emerald-200">{{ number_format($totalStandardized) }}</span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-blue-100 mt-0.5">Standardized IDs</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ACTION BUTTONS & SEARCH TOOLBAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <!-- Left Buttons -->
        <div class="flex items-center gap-2.5 flex-wrap">
            <button 
                type="button" 
                wire:click="fixAllAutoMatched"
                wire:loading.attr="disabled"
                wire:confirm="Are you sure you want to fix all auto-matched records? This will update MongoDB documents setting degree_type to their matched ObjectId."
                @disabled($totalProblematic === 0)
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $totalProblematic === 0 ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs cursor-pointer' }}"
            >
                <i class="fa-solid fa-bolt text-xs"></i>
                <span>Fix All Auto-Matched ({{ $totalProblematic }})</span>
            </button>

            <button 
                type="button" 
                wire:click="fixSelected"
                wire:loading.attr="disabled"
                @disabled(empty($selectedRows))
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ empty($selectedRows) ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-brand text-white hover:bg-brand-600 cursor-pointer shadow-sm' }}"
            >
                <i class="fa-solid fa-check-double text-xs"></i>
                <span>Fix Selected ({{ count($selectedRows) }})</span>
            </button>

            <!-- Status Indicator -->
            <span class="text-xs text-zinc-500 font-medium ml-2">
                {{ $statusMessage }}
            </span>
        </div>

        <!-- Right Search & PerPage -->
        <div class="flex items-center gap-3">
            <div class="relative">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search raw text..."
                    class="h-9 pl-9 pr-3 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 placeholder:text-zinc-400 focus:outline-none focus:border-brand"
                />
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-zinc-400 text-xs"></i>
            </div>

            <select 
                wire:model.live="perPage"
                class="h-9 px-3 text-xs rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 focus:outline-none"
            >
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/75 dark:bg-zinc-800/50 text-zinc-500 font-bold uppercase tracking-wider text-[10px]">
                        <th class="p-3.5 w-10 text-center">
                            <input 
                                type="checkbox" 
                                wire:click="toggleSelectAll"
                                @checked($selectAll)
                                class="rounded border-zinc-300 text-brand focus:ring-brand size-3.5 cursor-pointer"
                            />
                        </th>
                        <th class="p-3.5">Education ID</th>
                        <th class="p-3.5">Field of Study & Year</th>
                        <th class="p-3.5">Stored Raw Text</th>
                        <th class="p-3.5">Assign Target DegreeType</th>
                        <th class="p-3.5 text-center">Match Status</th>
                        <th class="p-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60 font-normal text-zinc-700 dark:text-zinc-300">
                    @forelse($records as $row)
                        <tr wire:key="edu-row-{{ $row['id'] }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition">
                            <td class="p-3.5 text-center">
                                <input 
                                    wire:key="edu-check-{{ $row['id'] }}"
                                    type="checkbox" 
                                    wire:model.live="selectedRows"
                                    value="{{ $row['id'] }}"
                                    class="rounded border-zinc-300 text-brand focus:ring-brand size-3.5 cursor-pointer"
                                />
                            </td>
                            <td class="p-3.5 font-mono text-[11px] text-zinc-600 dark:text-zinc-400 select-all">
                                {{ $row['id'] }}
                            </td>
                            <td class="p-3.5">
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $row['field_of_study'] }}</div>
                                <div class="text-[11px] text-zinc-400 font-mono">Year: {{ $row['passout_year'] }}</div>
                            </td>
                            <td class="p-3.5">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                    <i class="fa-solid fa-quote-left text-[10px] opacity-70"></i>
                                    <span>{{ $row['raw_degree'] }}</span>
                                    <i class="fa-solid fa-quote-right text-[10px] opacity-70"></i>
                                </span>
                            </td>
                            <td class="p-3.5">
                                <select 
                                    wire:key="edu-select-{{ $row['id'] }}"
                                    wire:model.live="assignedDegreeTypes.{{ $row['id'] }}"
                                    class="w-full min-w-[200px] max-w-[280px] h-8 px-2.5 text-xs rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium focus:outline-none focus:border-brand cursor-pointer"
                                >
                                    @if($row['matched_id'])
                                        <option value="{{ $row['matched_id'] }}">
                                            ✓ {{ $row['matched_name'] }} (Matched)
                                        </option>
                                    @else
                                        <option value="">-- No Match Found --</option>
                                    @endif
                                    @if($row['raw_degree'])
                                        <option value="__create__" class="font-bold text-emerald-600 dark:text-emerald-400">
                                            + Create &quot;{{ $row['raw_degree'] }}&quot; (New)
                                        </option>
                                    @endif
                                </select>
                            </td>
                            <td class="p-3.5 text-center">
                                @if($row['match_type'] === 'Exact Match')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <i class="fa-solid fa-check text-[9px]"></i>
                                        <span>Exact</span>
                                    </span>
                                @elseif($row['match_type'] === 'Suggested Match')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                        <i class="fa-solid fa-wand-magic-sparkles text-[9px]"></i>
                                        <span>Suggested</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800" title="No existing degree type matched this text">
                                        <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                                        <span>No Match</span>
                                    </span>
                                @endif
                            </td>
                            <td class="p-3.5 text-right">
                                @if($row['match_type'] === 'No Match')
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                        <button 
                                            type="button" 
                                            wire:click="createAndFix('{{ $row['id'] }}', '{{ addslashes($row['raw_degree']) }}')"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-2xs cursor-pointer active:scale-95 whitespace-nowrap"
                                            title="Create '{{ $row['raw_degree'] }}' as a new DegreeType in database and fix this record"
                                        >
                                            <i class="fa-solid fa-plus text-[10px]"></i>
                                            <span>Create & Fix</span>
                                        </button>
                                        <button 
                                            type="button" 
                                            wire:click="fixSingle('{{ $row['id'] }}')"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-zinc-700 hover:bg-zinc-800 text-white transition shadow-2xs cursor-pointer active:scale-95 whitespace-nowrap"
                                            title="Fix using selected DegreeType from dropdown"
                                        >
                                            <i class="fa-solid fa-wrench text-[10px]"></i>
                                            <span>Fix</span>
                                        </button>
                                    </div>
                                @else
                                    <button 
                                        type="button" 
                                        wire:click="fixSingle('{{ $row['id'] }}')"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-brand hover:bg-brand-600 text-white transition shadow-2xs cursor-pointer active:scale-95 whitespace-nowrap"
                                    >
                                        <i class="fa-solid fa-wrench text-[10px]"></i>
                                        <span>Fix to ID</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-zinc-400">
                                <div class="size-12 rounded-full bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 flex items-center justify-center mx-auto mb-2 text-xl">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <div class="font-bold text-zinc-800 dark:text-zinc-200">No problematic degree_type records found!</div>
                                <p class="text-xs text-zinc-500 mt-1">All education records have standardized 24-character MongoDB ObjectIds.</p>
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
