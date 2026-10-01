<?php

use App\Models\RequestReviewPaper;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('RequestReviewPaper — scholar_profiles Fixer - Admin')] class extends Component {
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
        return DB::connection('mongodb')->table('request_review_paper')
            ->whereRaw([
                '$expr' => [
                    '$eq' => [['$type' => '$scholar_profiles'], 'string'],
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
            $rawVal = is_array($item) ? ($item['scholar_profiles'] ?? null) : ($item->scholar_profiles ?? null);

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
            } elseif (str_contains($val, ',') || str_starts_with($val, '[')) {
                $reason = 'Delimited / Raw Profiles String Stored';
            }

            $date = '-';
            $createdAt = is_array($item) ? ($item['created_at'] ?? null) : ($item->created_at ?? null);
            if ($createdAt) {
                $date = is_string($createdAt) ? substr($createdAt, 0, 10) : ($createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d') : (string) $createdAt);
            }

            $userId = is_array($item) ? ($item['user_id'] ?? 'N/A') : ($item->user_id ?? 'N/A');
            $paperTitle = is_array($item) ? ($item['paper_title'] ?? 'N/A') : ($item->paper_title ?? 'N/A');

            $formatted[] = [
                'id' => $id,
                'user_id' => (string) ($userId ?: 'N/A'),
                'paper_title' => (string) $paperTitle,
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
        $raw = DB::connection('mongodb')->table('request_review_paper')
            ->where(function ($q) use ($id, $mongoId) {
                $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
            })
            ->first();

        if ($raw) {
            $rawVal = is_array($raw) ? ($raw['scholar_profiles'] ?? '') : ($raw->scholar_profiles ?? '');
            $cleaned = $this->parseToArrayOrNull($rawVal);

            DB::connection('mongodb')->table('request_review_paper')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->update(['scholar_profiles' => $cleaned]);

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
            $raw = DB::connection('mongodb')->table('request_review_paper')
                ->where(function ($q) use ($id, $mongoId) {
                    $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                })
                ->first();

            if ($raw) {
                $rawVal = is_array($raw) ? ($raw['scholar_profiles'] ?? '') : ($raw->scholar_profiles ?? '');
                $cleaned = $this->parseToArrayOrNull($rawVal);

                DB::connection('mongodb')->table('request_review_paper')
                    ->where(function ($q) use ($id, $mongoId) {
                        $q->where('_id', $mongoId)->orWhere('_id', (string) $id);
                    })
                    ->update(['scholar_profiles' => $cleaned]);

                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed {$count} selected record(s) to native array / null!";
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
                    $rawVal = is_array($item) ? ($item['scholar_profiles'] ?? '') : ($item->scholar_profiles ?? '');
                    $cleaned = $this->parseToArrayOrNull($rawVal);

                    DB::connection('mongodb')->table('request_review_paper')
                        ->where(function ($q) use ($id, $mongoId) {
                            $q->where('_id', $mongoId)->orWhere('_id', $id);
                        })
                        ->update(['scholar_profiles' => $cleaned]);

                    $count++;
                }
            }

            unset($records);
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully converted all {$count} record(s) scholar_profiles to native array / null!";
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
        if ($str === '' || $str === ',' || $str === 'null' || $str === '[]') {
            return null;
        }

        if (str_starts_with($str, '[') && str_ends_with($str, ']')) {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) {
                return ! empty($decoded) ? array_values($decoded) : null;
            }
        }

        $items = explode(',', $str);
        $cleaned = [];
        foreach ($items as $item) {
            $trimmed = trim($item);
            if ($trimmed !== '' && $trimmed !== ',') {
                $cleaned[] = $trimmed;
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">RequestReviewPaper</strong> &bull; Field: <code class="font-mono text-brand">scholar_profiles</code>
        </span>
    </div>

    <!-- STATS / ACTION CARD -->
    <div class="p-6 rounded-2xl bg-white dark:bg-[#111c26] border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    RequestReviewPaper — <code class="text-brand">scholar_profiles</code> Array Formatter
                </h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $problematicCount > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300' }}">
                    {{ number_format($problematicCount) }} Unformatted Records
                </span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-2xl">
                Scans <code class="font-mono text-xs">request_review_paper</code> for strings, comma-separated links/names, or raw text in <code class="font-mono text-xs">scholar_profiles</code> and converts them into standardized MongoDB arrays or null.
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
                    wire:confirm="Are you sure you want to clean and convert all {{ number_format($problematicCount) }} records to array format in batches?"
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
                        <th class="px-4 py-3 w-36">User ID</th>
                        <th class="px-4 py-3 w-64">Paper Title</th>
                        <th class="px-4 py-3 w-64">Current Raw String</th>
                        <th class="px-4 py-3">Status / Reason</th>
                        <th class="px-4 py-3 w-28">Created Date</th>
                        <th class="px-4 py-3 text-center w-20">Action</th>
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

                            <!-- User ID -->
                            <td class="px-4 py-3 font-mono text-[11px] text-zinc-600 dark:text-zinc-400">
                                {{ $row['user_id'] }}
                            </td>

                            <!-- Paper Title -->
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100 truncate max-w-xs" title="{{ $row['paper_title'] }}">
                                {{ $row['paper_title'] }}
                            </td>

                            <!-- Broken Value -->
                            <td class="px-4 py-3 font-mono text-amber-600 dark:text-amber-400 font-bold truncate max-w-xs" title="{{ $row['broken_value'] }}">
                                {{ $row['broken_value'] }}
                            </td>

                            <!-- Reason -->
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                {{ $row['reason'] }}
                            </td>

                            <!-- Date -->
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 font-mono text-[11px]">
                                {{ $row['date'] }}
                            </td>

                            <!-- Action -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['id'] }}')" 
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1 rounded-lg text-xs font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white transition shadow-2xs cursor-pointer"
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
                                    Every record in <code class="font-mono">request_review_paper</code> has a standardized array or null format for <code class="font-mono">scholar_profiles</code>.
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
