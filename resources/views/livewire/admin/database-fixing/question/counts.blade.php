<?php

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

new #[Layout('components.layouts.admin')] #[Title('Question — answer_count & votes_count Fixer - Admin')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $statusMessage = 'Ready';

    public array $selectedRows = [];

    public bool $selectAll = false;

    public int $perPage = 25;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function toMongoId(mixed $id): mixed
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        $idStr = trim((string) $id);
        if (strlen($idStr) === 24 && ctype_xdigit($idStr)) {
            try {
                return new ObjectId($idStr);
            } catch (\Exception $e) {
                return $id;
            }
        }

        return $id;
    }

    /**
     * Inspect a single question document against answers and votes collections.
     *
     * @return array{actual_answers: int, actual_upvotes: int, actual_downvotes: int, actual_votes: int, has_issue: bool, reasons: array<string>}
     */
    protected function analyzeQuestion(mixed $question): array
    {
        $qId = is_array($question) ? ($question['_id'] ?? $question['id'] ?? null) : ($question->_id ?? $question->id ?? null);
        $idStr = (string) $qId;
        $mongoId = $this->toMongoId($idStr);

        // Actual answers count
        $actualAnswers = Answer::query()
            ->where(function ($query) use ($idStr, $mongoId) {
                $query->where('question_id', $idStr);
                if ($mongoId instanceof ObjectId) {
                    $query->orWhere('question_id', $mongoId);
                }
            })
            ->count();

        // Actual votes count
        $actualUpvotes = Vote::query()
            ->where(function ($query) use ($idStr, $mongoId) {
                $query->where('question_id', $idStr)->orWhere('votable_id', $idStr);
                if ($mongoId instanceof ObjectId) {
                    $query->orWhere('question_id', $mongoId)->orWhere('votable_id', $mongoId);
                }
            })
            ->where('vote_type', 'upvote')
            ->count();

        $actualDownvotes = Vote::query()
            ->where(function ($query) use ($idStr, $mongoId) {
                $query->where('question_id', $idStr)->orWhere('votable_id', $idStr);
                if ($mongoId instanceof ObjectId) {
                    $query->orWhere('question_id', $mongoId)->orWhere('votable_id', $mongoId);
                }
            })
            ->where('vote_type', 'downvote')
            ->count();

        $actualVotes = max(0, $actualUpvotes - $actualDownvotes);

        $storedAns = is_array($question) ? ($question['answer_count'] ?? null) : ($question->answer_count ?? null);
        $storedVotes = is_array($question) ? ($question['votes_count'] ?? null) : ($question->votes_count ?? null);
        $storedViews = is_array($question) ? ($question['views_count'] ?? null) : ($question->views_count ?? null);
        $storedStatus = is_array($question) ? ($question['status'] ?? null) : ($question->status ?? null);

        $reasons = [];

        if ($storedAns === null) {
            $reasons[] = 'Missing answer_count (null)';
        } elseif (! is_int($storedAns) && ! is_numeric($storedAns)) {
            $reasons[] = 'Invalid answer_count type';
        } elseif ((int) $storedAns !== $actualAnswers) {
            $reasons[] = "answer_count mismatch (stored {$storedAns}, actual {$actualAnswers})";
        }

        if ($storedVotes === null) {
            $reasons[] = 'Missing votes_count (null)';
        } elseif (! is_int($storedVotes) && ! is_numeric($storedVotes)) {
            $reasons[] = 'Invalid votes_count type';
        } elseif ((int) $storedVotes !== $actualVotes) {
            $reasons[] = "votes_count mismatch (stored {$storedVotes}, actual {$actualVotes})";
        }

        if ($storedViews === null || ! is_numeric($storedViews)) {
            $reasons[] = 'views_count is null or non-numeric';
        }

        if ($storedStatus === null || ! is_numeric($storedStatus)) {
            $reasons[] = 'status is not an integer';
        }

        return [
            'actual_answers' => $actualAnswers,
            'actual_upvotes' => $actualUpvotes,
            'actual_downvotes' => $actualDownvotes,
            'actual_votes' => $actualVotes,
            'has_issue' => count($reasons) > 0,
            'reasons' => $reasons,
        ];
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;
        if ($this->selectAll) {
            $allProblematic = $this->getProblematicRecordsList();
            $paginator = $this->paginateCollection($allProblematic);
            $this->selectedRows = collect($paginator->items())->pluck('id')->all();
        } else {
            $this->selectedRows = [];
        }
    }

    /**
     * Scan MongoDB questions and return only records with count/type issues.
     */
    protected function getProblematicRecordsList(): array
    {
        $query = DB::connection('mongodb')->table('question');

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $regex = new Regex($term, 'i');
            $query->where(function ($q) use ($regex, $term) {
                $q->where('title', 'regex', $regex)
                    ->orWhere('slug', 'regex', $regex)
                    ->orWhere('_id', $term);
            });
        }

        $allQuestions = $query->latest('_id')->get();

        // Preload users
        $userIds = collect($allQuestions)->pluck('user_id')->filter()->unique()->all();
        $userSearchKeys = [];
        foreach ($userIds as $uid) {
            $uidStr = (string) $uid;
            if ($uidStr !== '') {
                $userSearchKeys[] = $uidStr;
                if (strlen($uidStr) === 24 && ctype_xdigit($uidStr)) {
                    try {
                        $userSearchKeys[] = new ObjectId($uidStr);
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        $userMap = [];
        if (! empty($userSearchKeys)) {
            $loadedUsers = User::whereIn('_id', $userSearchKeys)->get();
            foreach ($loadedUsers as $u) {
                $userMap[(string) $u->_id] = $u;
            }
        }

        $problematic = [];

        foreach ($allQuestions as $item) {
            $analysis = $this->analyzeQuestion($item);

            // ONLY collect records that have problems/mismatches
            if (! $analysis['has_issue']) {
                continue;
            }

            $rawId = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
            $idStr = (string) $rawId;
            $userIdStr = (string) (is_array($item) ? ($item['user_id'] ?? '') : ($item->user_id ?? ''));

            $user = $userMap[$userIdStr] ?? null;
            $userName = 'Unknown User';
            $userEmail = 'N/A';
            if ($user) {
                $fullName = trim($user->name ?: (($user->first_name ?? '').' '.($user->last_name ?? '')));
                $userName = $fullName !== '' ? $fullName : ($user->email ?? 'User #'.$userIdStr);
                $userEmail = $user->email ?? 'N/A';
            } elseif ($userIdStr !== '') {
                $userName = 'User #'.substr($userIdStr, 0, 8);
            }

            $createdAt = is_array($item) ? ($item['created_at'] ?? null) : ($item->created_at ?? null);
            $dateStr = '-';
            if ($createdAt) {
                $dateStr = is_string($createdAt) ? substr($createdAt, 0, 10) : ($createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d') : (string) $createdAt);
            }

            $problematic[] = [
                'id' => $idStr,
                'title' => (string) (is_array($item) ? ($item['title'] ?? 'Untitled') : ($item->title ?? 'Untitled')),
                'slug' => (string) (is_array($item) ? ($item['slug'] ?? '') : ($item->slug ?? '')),
                'user_id' => $userIdStr ?: 'N/A',
                'user_name' => $userName,
                'user_email' => $userEmail,
                'stored_answers' => is_array($item) ? ($item['answer_count'] ?? null) : ($item->answer_count ?? null),
                'actual_answers' => $analysis['actual_answers'],
                'stored_votes' => is_array($item) ? ($item['votes_count'] ?? null) : ($item->votes_count ?? null),
                'actual_votes' => $analysis['actual_votes'],
                'actual_upvotes' => $analysis['actual_upvotes'],
                'actual_downvotes' => $analysis['actual_downvotes'],
                'reasons' => $analysis['reasons'],
                'date' => $dateStr,
            ];
        }

        return $problematic;
    }

    protected function paginateCollection(array $items): LengthAwarePaginator
    {
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $collection = collect($items);
        $currentPageItems = $collection->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();

        return new LengthAwarePaginator(
            $currentPageItems,
            $collection->count(),
            $this->perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    public function with(): array
    {
        $problematicList = $this->getProblematicRecordsList();
        $totalProblematicCount = count($problematicList);
        $paginator = $this->paginateCollection($problematicList);

        $totalQuestions = DB::connection('mongodb')->table('question')->count();
        $totalAnswers = DB::connection('mongodb')->table('answers')->count();
        $totalVotes = DB::connection('mongodb')->table('votes')->count();

        return [
            'problematicRecords' => $paginator->items(),
            'problematicCount' => $totalProblematicCount,
            'paginator' => $paginator,
            'totalQuestions' => $totalQuestions,
            'totalAnswers' => $totalAnswers,
            'totalVotes' => $totalVotes,
        ];
    }

    /**
     * Recalculate and synchronize all counts for a single question.
     */
    public function recalculateAllCounts(mixed $item): array
    {
        $id = is_array($item) ? ($item['_id'] ?? $item['id'] ?? null) : ($item->_id ?? $item->id ?? null);
        $idStr = (string) $id;
        $mongoId = $this->toMongoId($idStr);

        // Calculate actual answers count from answers collection
        $ansCount = Answer::query()
            ->where(function ($q) use ($idStr, $mongoId) {
                $q->where('question_id', $idStr);
                if ($mongoId instanceof ObjectId) {
                    $q->orWhere('question_id', $mongoId);
                }
            })
            ->count();

        // Calculate actual upvotes from votes collection
        $upvotes = Vote::query()
            ->where(function ($q) use ($idStr, $mongoId) {
                $q->where('question_id', $idStr)->orWhere('votable_id', $idStr);
                if ($mongoId instanceof ObjectId) {
                    $q->orWhere('question_id', $mongoId)->orWhere('votable_id', $mongoId);
                }
            })
            ->where('vote_type', 'upvote')
            ->count();

        // Calculate actual downvotes from votes collection
        $downvotes = Vote::query()
            ->where(function ($q) use ($idStr, $mongoId) {
                $q->where('question_id', $idStr)->orWhere('votable_id', $idStr);
                if ($mongoId instanceof ObjectId) {
                    $q->orWhere('question_id', $mongoId)->orWhere('votable_id', $mongoId);
                }
            })
            ->where('vote_type', 'downvote')
            ->count();

        $netVotes = max(0, $upvotes - $downvotes);

        $rawViews = is_array($item) ? ($item['views_count'] ?? 0) : ($item->views_count ?? 0);
        $viewsCount = is_numeric($rawViews) ? max(0, (int) $rawViews) : 0;

        $rawStatus = is_array($item) ? ($item['status'] ?? 1) : ($item->status ?? 1);
        $status = is_numeric($rawStatus) ? (int) $rawStatus : 1;

        // Perform direct atomic MongoDB update to the question collection
        DB::connection('mongodb')->table('question')
            ->where(function ($q) use ($idStr, $mongoId) {
                if ($mongoId instanceof ObjectId) {
                    $q->where('_id', $mongoId);
                } else {
                    $q->where('_id', $idStr);
                }
                $q->orWhere('_id', $idStr);
            })
            ->update([
                'answer_count' => (int) $ansCount,
                'votes_count' => (int) $netVotes,
                'upvotes_count' => (int) $upvotes,
                'downvotes_count' => (int) $downvotes,
                'views_count' => (int) $viewsCount,
                'status' => (int) $status,
                'updated_at' => now(),
            ]);

        return [
            'answer_count' => (int) $ansCount,
            'votes_count' => (int) $netVotes,
            'upvotes_count' => (int) $upvotes,
            'downvotes_count' => (int) $downvotes,
            'views_count' => (int) $viewsCount,
            'status' => (int) $status,
        ];
    }

    public function fixSingle(string $id): void
    {
        $mongoId = $this->toMongoId($id);
        $raw = DB::connection('mongodb')->table('question')
            ->where(function ($q) use ($id, $mongoId) {
                if ($mongoId instanceof ObjectId) {
                    $q->where('_id', $mongoId);
                } else {
                    $q->where('_id', $id);
                }
                $q->orWhere('_id', (string) $id);
            })
            ->first();

        if (! $raw) {
            $this->statusMessage = "Question record [{$id}] not found.";

            return;
        }

        $title = is_array($raw) ? ($raw['title'] ?? 'Question') : ($raw->title ?? 'Question');
        $results = $this->recalculateAllCounts($raw);

        $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
        $this->statusMessage = "Successfully fixed & synchronized Question \"".Str::limit((string) $title, 35)."\" (Answers: {$results['answer_count']}, Net Votes: {$results['votes_count']})!";
    }

    public function fixSelected(): void
    {
        if (empty($this->selectedRows)) {
            $this->statusMessage = 'No questions selected.';

            return;
        }

        $count = 0;
        foreach ($this->selectedRows as $id) {
            $mongoId = $this->toMongoId($id);
            $raw = DB::connection('mongodb')->table('question')
                ->where(function ($q) use ($id, $mongoId) {
                    if ($mongoId instanceof ObjectId) {
                        $q->where('_id', $mongoId);
                    } else {
                        $q->where('_id', $id);
                    }
                    $q->orWhere('_id', (string) $id);
                })
                ->first();

            if ($raw) {
                $this->recalculateAllCounts($raw);
                $count++;
            }
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed and synchronized {$count} selected question(s)!";
    }

    public function fixAll(): void
    {
        $problematic = $this->getProblematicRecordsList();
        $count = 0;

        foreach ($problematic as $row) {
            $this->fixSingle($row['id']);
            $count++;
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->statusMessage = "Successfully fixed and synchronized all {$count} problematic question record(s)!";
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
            Table: <strong class="text-zinc-700 dark:text-zinc-200">Question</strong> &bull; Target Fields: <code class="font-mono text-cyan-600 dark:text-cyan-400">answer_count, votes_count</code>
        </span>
    </div>

    <!-- TITLE HEADER -->
    <div>
        <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">
            Question &mdash; Answer & Vote Count Fixer
        </h2>
        
        <!-- CLIENT-SIDE ACCORDION COLLAPSIBLE GUIDE (Click me) -->
        <div x-data="{ open: false }" class="mt-2 text-xs">
            <button 
                type="button" 
                @click="open = !open"
                class="inline-flex items-center gap-1.5 font-bold text-zinc-700 dark:text-zinc-300 hover:text-cyan-600 dark:hover:text-cyan-400 transition cursor-pointer select-none"
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
                <p class="font-medium">
                    This tool isolates and lists only Question records where stored counters (<code class="font-mono text-xs bg-zinc-200 dark:bg-zinc-700 px-1 py-0.5 rounded">answer_count</code>, <code class="font-mono text-xs bg-zinc-200 dark:bg-zinc-700 px-1 py-0.5 rounded">votes_count</code>) do not match actual records in the <code class="font-mono text-xs bg-zinc-200 dark:bg-zinc-700 px-1 py-0.5 rounded">answers</code> or <code class="font-mono text-xs bg-zinc-200 dark:bg-zinc-700 px-1 py-0.5 rounded">votes</code> collections.
                </p>
                <p class="text-zinc-500 dark:text-zinc-400">
                    Executing a fix directly updates the document in MongoDB with native integer counts, removing the record from the problematic list upon synchronization.
                </p>
            </div>
        </div>
    </div>

    <!-- HERO BANNER STATS CARD -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-teal-700 via-cyan-700 to-sky-600 p-6 sm:p-7 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="space-y-2 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-white/20 text-white flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-extrabold tracking-tight">
                            Question Counts & Data Sync
                        </h3>
                        <p class="text-xs text-cyan-100">
                            Problematic records scanner & count validator
                        </p>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-cyan-100/90 leading-relaxed pt-1">
                    Detects questions with broken or desynchronized counters against real answer and vote documents in MongoDB.
                </p>

                <!-- Suggestion Note -->
                <div class="pt-1">
                    <p class="text-xs font-bold text-amber-200">
                        Total Records in DB: <span class="font-medium text-white">{{ $totalQuestions }} Questions</span> &bull; <span class="font-medium text-white">{{ $totalAnswers }} Answers</span> &bull; <span class="font-medium text-white">{{ $totalVotes }} Votes</span>
                    </p>
                </div>
            </div>

            <!-- Right Counter Badges -->
            <div class="shrink-0">
                <div class="px-6 py-4 rounded-xl bg-white/15 border border-white/25 backdrop-blur-xs text-center min-w-[200px]">
                    <span class="block text-3xl font-black text-white">
                        {{ $problematicCount }}
                    </span>
                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-cyan-200 mt-0.5">
                        Problematic Records
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- EXPLANATION ALERT BOX -->
    <div class="p-5 rounded-2xl bg-cyan-50 dark:bg-cyan-950/30 border border-cyan-200/90 dark:border-cyan-800/60 text-xs text-zinc-700 dark:text-zinc-300 space-y-3 shadow-xs">
        <div class="space-y-1.5">
            <div class="flex items-center gap-1.5 font-bold text-cyan-900 dark:text-cyan-300">
                <svg class="size-4 text-cyan-600 dark:text-cyan-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <span>What issues are displayed in this list?</span>
            </div>
            <ul class="space-y-1.5 pl-5 list-disc text-zinc-600 dark:text-zinc-300">
                <li>
                    <strong class="text-cyan-800 dark:text-cyan-300">Answer Count Mismatch:</strong> 
                    When <code class="font-mono text-zinc-800 dark:text-zinc-200">answer_count</code> in the question document does not match the actual number of documents in the <code class="font-mono text-zinc-800 dark:text-zinc-200">answers</code> collection.
                </li>
                <li>
                    <strong class="text-cyan-800 dark:text-cyan-300">Vote Count Mismatch:</strong> 
                    When <code class="font-mono text-zinc-800 dark:text-zinc-200">votes_count</code> does not equal the calculated net votes (upvotes minus downvotes) from the <code class="font-mono text-zinc-800 dark:text-zinc-200">votes</code> collection.
                </li>
                <li>
                    <strong class="text-cyan-800 dark:text-cyan-300">Missing or Null Counters:</strong> 
                    When any counter field is <code class="font-mono text-zinc-800 dark:text-zinc-200">null</code> or stored as a non-integer data type.
                </li>
            </ul>
        </div>
    </div>

    <!-- ACTIONS & CONTROLS TOOLBAR -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-3.5 bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-xs font-bold text-zinc-500 mr-1">Actions:</span>
            
            <!-- Fix All Records -->
            <button 
                type="button" 
                wire:click="fixAll"
                wire:loading.attr="disabled"
                @disabled($problematicCount === 0)
                class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm {{ $problematicCount === 0 ? 'bg-zinc-200 dark:bg-zinc-800 text-zinc-400 cursor-not-allowed' : 'bg-rose-600 hover:bg-rose-700 text-white cursor-pointer' }}"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Fix All Problematic Records ({{ $problematicCount }})</span>
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
                class="px-3.5 py-2 rounded-xl text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition flex items-center gap-1.5 shadow-xs cursor-pointer"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Refresh</span>
            </button>
        </div>

        <!-- Right Side: Search & Per Page -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative min-w-[200px] sm:min-w-[240px]">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search problematic questions..." 
                    class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                />
                <svg class="size-4 text-zinc-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <!-- Per Page -->
            <div class="flex items-center gap-1.5 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                <span>Per Page:</span>
                <select 
                    wire:model.live="perPage" 
                    class="px-2 py-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-bold text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                >
                    <option value="15">15</option>
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

    <!-- DATA TABLE -->
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
                                class="rounded border-zinc-300 dark:border-zinc-700 text-cyan-600 focus:ring-cyan-500 size-4 cursor-pointer"
                            />
                        </th>
                        <th class="px-4 py-3.5 w-14">No</th>
                        <th class="px-4 py-3.5 min-w-[260px]">Question Title & Details</th>
                        <th class="px-4 py-3.5 w-44">Author</th>
                        <th class="px-4 py-3.5 w-40 text-center">Answer Count</th>
                        <th class="px-4 py-3.5 w-40 text-center">Vote Count</th>
                        <th class="px-4 py-3.5 min-w-[220px]">Problem Reason</th>
                        <th class="px-4 py-3.5 text-center w-28">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($problematicRecords as $index => $row)
                        <tr wire:key="q-problem-row-{{ $row['id'] }}" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition bg-rose-50/20 dark:bg-rose-950/10">
                            <!-- Checkbox -->
                            <td class="px-4 py-3 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:model="selectedRows" 
                                    value="{{ $row['id'] }}"
                                    class="rounded border-zinc-300 dark:border-zinc-700 text-cyan-600 focus:ring-cyan-500 size-4 cursor-pointer"
                                />
                            </td>

                            <!-- No -->
                            <td class="px-4 py-3 font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1 }}
                            </td>

                            <!-- Title & Info -->
                            <td class="px-4 py-3">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-zinc-900 dark:text-white line-clamp-1">
                                        {{ $row['title'] }}
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-zinc-400">
                                        <span class="font-mono text-zinc-500 dark:text-zinc-400 select-all">ID: {{ $row['id'] }}</span>
                                        @if($row['slug'])
                                            <span>&bull;</span>
                                            <span class="font-mono text-zinc-400 truncate max-w-[150px]">{{ $row['slug'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Author -->
                            <td class="px-4 py-3">
                                <div class="font-semibold text-zinc-800 dark:text-zinc-200 truncate max-w-[160px]">
                                    {{ $row['user_name'] }}
                                </div>
                                <div class="text-[11px] text-zinc-400 truncate max-w-[160px]">
                                    {{ $row['user_email'] }}
                                </div>
                            </td>

                            <!-- Answer Count (Stored vs Actual) -->
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-mono font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">
                                    <span>Stored: {{ $row['stored_answers'] ?? 'null' }}</span>
                                    <span>&rarr;</span>
                                    <span>Actual: {{ $row['actual_answers'] }}</span>
                                </span>
                            </td>

                            <!-- Vote Count (Stored vs Actual) -->
                            <td class="px-4 py-3 text-center">
                                <div class="inline-flex flex-col items-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-mono font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                                        <span>Stored: {{ $row['stored_votes'] ?? 'null' }}</span>
                                        <span>&rarr;</span>
                                        <span>Net: {{ $row['actual_votes'] }}</span>
                                    </span>
                                    <span class="text-[10px] text-zinc-400 mt-0.5">
                                        ({{ $row['actual_upvotes'] }} up / {{ $row['actual_downvotes'] }} down)
                                    </span>
                                </div>
                            </td>

                            <!-- Problem Reasons -->
                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    @foreach($row['reasons'] as $reason)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/70">
                                            <span class="size-1 rounded-full bg-rose-500"></span>
                                            {{ $reason }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <!-- Action: Fix Button -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button 
                                    type="button" 
                                    wire:click="fixSingle('{{ $row['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="px-3.5 py-1 rounded-lg text-xs font-bold bg-cyan-600 hover:bg-cyan-700 text-white transition shadow-xs cursor-pointer"
                                >
                                    Fix
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="max-w-md mx-auto space-y-3">
                                    <div class="size-12 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                        All Question Records Standardized & Synchronized!
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        There are no question records with count mismatches or invalid types remaining in the MongoDB database. All records are 100% accurate.
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
