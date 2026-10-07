<?php

use App\Models\Question;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.admin')] #[Title('Manage Questions - Admin')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 15;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public bool $hasCustomSort = false;

    public function updatingSearch(): void
    {
        $this->hasCustomSort = false;
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $this->hasCustomSort = true;
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function toggleStatus(string $id): void
    {
        $question = Question::find($id);
        if ($question) {
            $isActive = in_array($question->status, [1, '1', true], true);
            $question->status = $isActive ? 0 : 1;
            $question->save();
        }
    }

    public function toggleSelectedStatus(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        $questions = Question::whereIn('_id', $ids)->get();
        foreach ($questions as $question) {
            $isActive = in_array($question->status, [1, '1', true], true);
            $question->status = $isActive ? 0 : 1;
            $question->save();
        }
    }

    public function deleteQuestion(string $id): void
    {
        $question = Question::find($id);
        if ($question) {
            $question->delete();
        }
    }

    public function deleteSelected(array $ids = []): void
    {
        if (empty($ids)) {
            return;
        }

        Question::whereIn('_id', $ids)->delete();
    }

    public function with(): array
    {
        $totalCount = Question::count();
        $activeCount = Question::whereIn('status', [1, '1', true])->count();
        $inactiveCount = Question::whereIn('status', [0, '0', false, null])->count();

        $query = Question::with('user');

        if ($this->statusFilter === '1') {
            $query->whereIn('status', [1, '1', true]);
        } elseif ($this->statusFilter === '0') {
            $query->whereIn('status', [0, '0', false, null]);
        }

        $search = trim($this->search);

        if (! empty($search)) {
            $escaped = preg_quote($search, '/');
            $fullRegex = new \MongoDB\BSON\Regex($escaped, 'i');

            // Tokenize words for multi-word title/slug matching
            $tokens = array_values(array_filter(
                preg_split('/\s+/', $search),
                fn ($w) => mb_strlen(trim($w)) >= 2
            ));

            $query->where(function ($q) use ($search, $fullRegex, $tokens) {
                // Match exact title or slug
                $q->where('title', 'regex', $fullRegex)
                  ->orWhere('slug', 'regex', $fullRegex);

                // Match _id (exact ObjectId or string match)
                if (preg_match('/^[a-f\d]{24}$/i', $search)) {
                    try {
                        $objectId = new \MongoDB\BSON\ObjectId($search);
                        $q->orWhere('_id', $objectId)->orWhere('_id', $search);
                    } catch (\Throwable) {
                        $q->orWhere('_id', $search);
                    }
                } else {
                    $q->orWhere('_id', $search);
                }

                // Match tokens in title and slug only
                foreach ($tokens as $token) {
                    $tokenRegex = new \MongoDB\BSON\Regex(preg_quote($token, '/'), 'i');
                    $q->orWhere('title', 'regex', $tokenRegex)
                      ->orWhere('slug', 'regex', $tokenRegex);
                }
            });
        }

        return [
            'questions' => $query->orderBy($this->sortField, $this->sortDirection)->paginate($this->perPage),
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ];
    }
}; ?>

<div 
    x-data="{
        selectedRows: [],
        selectAll: false,
        allIds: {{ json_encode(collect($questions->items())->pluck('_id')->map(fn ($id) => (string) $id)->all()) }},
        toggleAll() {
            if (this.selectAll) {
                this.selectedRows = [...this.allIds];
            } else {
                this.selectedRows = [];
            }
        },
        updateSelectAll() {
            this.selectAll = this.allIds.length > 0 && this.allIds.every(id => this.selectedRows.includes(id));
        }
    }"
    x-effect="allIds = {{ json_encode(collect($questions->items())->pluck('_id')->map(fn ($id) => (string) $id)->all()) }}; updateSelectAll();"
    class="space-y-6"
>
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg sm:text-xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                <span>Manage Questions</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-brand/10 text-brand dark:bg-brand/20">
                    {{ $questions->total() }} Questions
                </span>
            </h2>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">Review, update status, edit, and moderate community questions.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a 
                href="{{ route('questions.index') }}" 
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition shrink-0"
            >
                <flux:icon name="arrow-top-right-on-square" class="size-4" />
                Public Questions
            </a>
        </div>
    </div>

    <!-- BULK ACTIONS FLOATING/ACTIVE BAR -->
    <div 
        x-show="selectedRows.length > 0" 
        x-cloak 
        x-transition
        class="flex flex-wrap items-center justify-between gap-3 bg-dark-navy text-white px-4 py-3 rounded-2xl shadow-lg border border-zinc-700/50"
    >
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center px-2.5 py-1 text-xs font-bold bg-brand text-white rounded-lg">
                <span x-text="selectedRows.length"></span> Selected
            </span>
            <button 
                type="button" 
                @click="selectedRows = []; selectAll = false" 
                class="text-xs text-zinc-300 hover:text-white underline cursor-pointer"
            >
                Deselect all
            </button>
        </div>

        <div class="flex items-center gap-2">
            <!-- Toggle Question Status Button -->
            <button 
                type="button" 
                @click="$wire.toggleSelectedStatus(selectedRows); selectedRows = []; selectAll = false" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-zinc-800 text-zinc-100 hover:bg-brand hover:text-white border border-zinc-700 transition cursor-pointer"
            >
                <flux:icon name="arrow-path" class="size-3.5 text-brand" />
                Toggle Status
            </button>

            <!-- Delete Selected Button -->
            <button 
                type="button" 
                @click="if(confirm('Are you sure you want to delete the selected question(s)?')) { $wire.deleteSelected(selectedRows); selectedRows = []; selectAll = false }"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-500/20 text-rose-300 hover:bg-rose-600 hover:text-white border border-rose-500/40 transition cursor-pointer"
            >
                <flux:icon name="trash" class="size-3.5" />
                Delete Selected
            </button>
        </div>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <!-- Search Input -->
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                <flux:icon name="magnifying-glass" class="size-4" />
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search questions by title, slug, or ID..." 
                class="w-full h-10 pl-10 pr-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition"
            />
        </div>

        <!-- Status Filter Selection Option & Per Page Selector -->
        <div class="flex items-center gap-2.5 overflow-x-auto pb-1 sm:pb-0">
            <!-- Status Dropdown Select -->
            <div class="relative flex items-center">
                <select 
                    wire:model.live="statusFilter" 
                    class="h-10 pl-3 pr-8 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 rounded-xl text-xs font-semibold text-zinc-700 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer appearance-none"
                >
                    <option value="all">All Status ({{ $totalCount }})</option>
                    <option value="1">Active ({{ $activeCount }})</option>
                    <option value="0">Inactive ({{ $inactiveCount }})</option>
                </select>
                <div class="absolute right-2.5 pointer-events-none text-zinc-400">
                    <flux:icon name="chevron-down" class="size-3.5" />
                </div>
            </div>

            <!-- Per Page Selector -->
            <div class="relative flex items-center">
                <select 
                    wire:model.live="perPage" 
                    class="h-10 pl-3 pr-8 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 rounded-xl text-xs font-semibold text-zinc-700 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer appearance-none"
                >
                    <option value="10">10 / page</option>
                    <option value="15">15 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
                <div class="absolute right-2.5 pointer-events-none text-zinc-400">
                    <flux:icon name="chevron-down" class="size-3.5" />
                </div>
            </div>
        </div>
    </div>

    <!-- QUESTIONS TABLE -->
    <div class="bg-white dark:bg-[#111c26] rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-100 dark:bg-zinc-900/90 text-[11px] font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-200 border-b border-zinc-200 dark:border-zinc-800">
                    <tr>
                        <!-- Checkbox Master Column -->
                        <th class="px-4 py-3.5 w-12 text-center">
                            <input 
                                type="checkbox" 
                                x-model="selectAll"
                                @change="toggleAll()"
                                class="size-4 rounded text-brand focus:ring-brand/20 border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 cursor-pointer"
                            />
                        </th>

                        <!-- No (Static) -->
                        <th class="px-4 py-3.5 w-14 text-center font-bold text-[11px] uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            No
                        </th>

                        <!-- Thumbnail Column -->
                        <th class="px-4 py-3.5 w-20 text-center font-bold text-[11px] uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                            Thumbnail
                        </th>

                        <!-- Question Title -->
                        <th class="px-5 py-3.5">
                            <button 
                                type="button" 
                                wire:click="sortBy('title')" 
                                class="group inline-flex items-center gap-1.5 font-bold text-[11px] uppercase tracking-wider {{ $sortField === 'title' ? 'text-zinc-900 dark:text-white font-extrabold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }} focus:outline-none focus:ring-0 transition cursor-pointer select-none"
                            >
                                <span>Question Title</span>
                                <span class="inline-flex items-center gap-0.5 ml-1">
                                    <flux:icon name="chevron-up" class="size-3.5 {{ $sortField === 'title' && $sortDirection === 'asc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" />
                                    <flux:icon name="chevron-down" class="size-3.5 {{ $sortField === 'title' && $sortDirection === 'desc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" />
                                </span>
                            </button>
                        </th>

                        <!-- Status -->
                        <th class="px-5 py-3.5 w-28">
                            <button 
                                type="button" 
                                wire:click="sortBy('status')" 
                                class="group inline-flex items-center gap-1.5 font-bold text-[11px] uppercase tracking-wider {{ $sortField === 'status' ? 'text-zinc-900 dark:text-white font-extrabold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }} focus:outline-none focus:ring-0 transition cursor-pointer select-none"
                            >
                                <span>Status</span>
                                <span class="inline-flex items-center gap-0.5 ml-1">
                                    <flux:icon name="chevron-up" class="size-3.5 {{ $sortField === 'status' && $sortDirection === 'asc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" />
                                    <flux:icon name="chevron-down" class="size-3.5 {{ $sortField === 'status' && $sortDirection === 'desc' ? 'text-zinc-900 dark:text-white stroke-[2.5]' : 'text-zinc-300 dark:text-zinc-600 group-hover:text-zinc-400' }}" />
                                </span>
                            </button>
                        </th>

                        <!-- Action -->
                        <th class="px-5 py-3.5 text-right font-bold text-[11px] uppercase tracking-wider text-zinc-700 dark:text-zinc-300 w-36">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800">
                    @forelse($questions as $index => $question)
                        @php
                            $isActive = in_array($question->status, [1, '1', true], true);

                            // Resolve thumbnail URL
                            $thumb = $question->thumbnail ?? null;
                            $thumbUrl = null;
                            if (!empty($thumb)) {
                                if (str_starts_with($thumb, 'http://') || str_starts_with($thumb, 'https://')) {
                                    $thumbUrl = $thumb;
                                } elseif (str_starts_with($thumb, 'storage/')) {
                                    $thumbUrl = asset($thumb);
                                } elseif (str_starts_with($thumb, '/storage/')) {
                                    $thumbUrl = asset(ltrim($thumb, '/'));
                                } else {
                                    $thumbUrl = asset('storage/' . ltrim($thumb, '/'));
                                }
                            }

                            // Extract tags array
                            $tagsList = [];
                            if (is_array($question->tags)) {
                                $tagsList = $question->tags;
                            } elseif (is_string($question->tags) && trim($question->tags) !== '') {
                                $tagsList = array_map('trim', explode(',', $question->tags));
                            } elseif (is_array($question->category)) {
                                $tagsList = $question->category;
                            }
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40 transition">
                            <!-- Checkbox Row Column -->
                            <td class="px-4 py-4 text-center">
                                <input 
                                    type="checkbox" 
                                    value="{{ (string) $question->_id }}"
                                    x-model="selectedRows"
                                    @change="updateSelectAll()"
                                    class="size-4 rounded text-brand focus:ring-brand/20 border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 cursor-pointer"
                                />
                            </td>

                            <!-- No -->
                            <td class="px-4 py-4 text-center font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ ($questions->currentPage() - 1) * $questions->perPage() + $index + 1 }}
                            </td>

                            <!-- Thumbnail -->
                            <td class="px-4 py-4 text-center">
                                <div class="flex items-center justify-center">
                                    @if($thumbUrl)
                                        <div class="size-12 rounded-xl overflow-hidden bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shrink-0 shadow-xs">
                                            <img 
                                                src="{{ $thumbUrl }}" 
                                                alt="{{ $question->title ?? 'Question Thumbnail' }}" 
                                                class="w-full h-full object-cover"
                                                loading="lazy"
                                                onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-zinc-100 dark:bg-zinc-800 text-zinc-400\'><svg class=\'size-5\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg></div>';"
                                            />
                                        </div>
                                    @else
                                        <div class="size-12 rounded-xl bg-gradient-to-br from-brand/10 to-brand/5 dark:from-brand/20 dark:to-brand/10 border border-brand/20 flex items-center justify-center text-brand shrink-0">
                                            <flux:icon name="question-mark-circle" class="size-6 text-brand" />
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Question Title -->
                            <td class="px-5 py-4 min-w-[280px]">
                                <div class="space-y-1.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <a 
                                            href="{{ route('questions.show', $question->slug ?: (string) $question->_id) }}" 
                                            target="_blank"
                                            class="font-bold text-zinc-900 dark:text-white hover:text-brand dark:hover:text-brand transition leading-snug line-clamp-2"
                                            title="{{ $question->title }}"
                                        >
                                            {{ $question->title ?: '—' }}
                                        </a>
                                    </div>

                                    <!-- Tags and Category Pills -->
                                    @if(!empty($tagsList))
                                        <div class="flex flex-wrap items-center gap-1">
                                            @foreach(array_slice($tagsList, 0, 4) as $tag)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border border-zinc-200/60 dark:border-zinc-700/60">
                                                    {{ is_array($tag) ? ($tag['name'] ?? json_encode($tag)) : $tag }}
                                                </span>
                                            @endforeach
                                            @if(count($tagsList) > 4)
                                                <span class="text-[10px] text-zinc-400 font-medium">+{{ count($tagsList) - 4 }} more</span>
                                            @endif
                                        </div>
                                    @endif

                                    <!-- Author & Stats Meta Info -->
                                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-zinc-500 dark:text-zinc-400">
                                        @if($question->user)
                                            <span class="inline-flex items-center gap-1 font-medium text-zinc-700 dark:text-zinc-300">
                                                <flux:icon name="user" class="size-3 text-zinc-400" />
                                                {{ $question->user->name ?? $question->user->fullname ?? 'Author' }}
                                            </span>
                                        @endif
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="chat-bubble-left-right" class="size-3 text-zinc-400" />
                                            {{ (int) ($question->answer_count ?? 0) }} answers
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="eye" class="size-3 text-zinc-400" />
                                            {{ (int) ($question->views_count ?? 0) }} views
                                        </span>
                                        @if($question->created_at)
                                            <span class="text-zinc-400">
                                                {{ $question->created_at->format('M d, Y') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-[11px] font-bold uppercase rounded-md inline-flex items-center gap-1.5 {{ $isActive ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800' }}">
                                    <span class="size-1.5 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                    <span>{{ $isActive ? 'Active (1)' : 'Inactive (0)' }}</span>
                                </span>
                            </td>

                            <!-- Action Buttons: Edit, Toggle Status, Delete -->
                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <!-- Edit Icon Link -->
                                    <flux:tooltip content="Edit Question">
                                        <a 
                                            href="{{ route('admin.questions.edit', (string) $question->_id) }}" 
                                            wire:navigate
                                            class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-brand hover:text-white dark:hover:bg-brand transition-colors cursor-pointer inline-flex items-center justify-center"
                                        >
                                            <flux:icon name="pencil-square" class="size-4" />
                                        </a>
                                    </flux:tooltip>
                                    
                                    <!-- Toggle Status Icon Button -->
                                    <flux:tooltip content="Toggle Status (Currently: {{ $isActive ? 'Active' : 'Inactive' }})">
                                        <button 
                                            type="button" 
                                            wire:click="toggleStatus('{{ $question->_id }}')" 
                                            class="p-2 rounded-lg {{ $isActive ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 hover:bg-amber-600 hover:text-white' }} transition-colors cursor-pointer"
                                        >
                                            <flux:icon name="arrow-path" class="size-4" />
                                        </button>
                                    </flux:tooltip>

                                    <!-- Delete Icon Button -->
                                    <flux:tooltip content="Delete Question">
                                        <button 
                                            type="button" 
                                            wire:click="deleteQuestion('{{ $question->_id }}')" 
                                            wire:confirm="Are you sure you want to delete this question record?"
                                            class="p-2 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white transition-colors cursor-pointer"
                                        >
                                            <flux:icon name="trash" class="size-4" />
                                        </button>
                                    </flux:tooltip>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-zinc-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="size-12 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 mb-3">
                                        <flux:icon name="question-mark-circle" class="size-6" />
                                    </div>
                                    <p class="font-semibold text-zinc-700 dark:text-zinc-300">No questions found</p>
                                    <p class="text-xs text-zinc-400 mt-0.5">Try adjusting your search query or status filter.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION BAR -->
        @if($questions->hasPages())
            <div class="px-5 py-4 border-t border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/30">
                {{ $questions->links() }}
            </div>
        @endif
    </div>
</div>
