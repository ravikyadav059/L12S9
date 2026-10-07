<?php

use App\Livewire\Concerns\HandlesQuestionInteractions;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Skill;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use HandlesQuestionInteractions, WithFileUploads;

    public int $perPage = 10;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'newest')]
    public string $sortBy = 'newest';

    #[Url(except: '')]
    public string $selectedTag = '';

    public bool $filterSaved = false;

    public bool $filterFollowing = false;

    public string $tagFilterType = '';

    public string $filterSkillSearch = '';

    public array $userSkills = [];

    public string $newSkillInput = '';

    // Ask Question Form
    #[Validate('required|min:5|max:255')]
    public string $newTitle = '';

    #[Validate('required|min:10')]
    public string $newDescription = '';

    public string $newTags = '';

    #[Validate('nullable|image|max:5120')]
    public $newThumbnail;

    // Edit Question Form
    public ?string $editingQuestionId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editTags = '';

    #[Validate('nullable|image|max:5120')]
    public $editThumbnail;

    public ?string $existingThumbnail = null;

    // Modals state
    public bool $showAskModal = false;

    public bool $showEditModal = false;

    public bool $showFilterModal = false;

    public bool $showSkillsModal = false;

    public function mount(): void
    {
        if (session()->has('questions_per_page')) {
            $this->perPage = max(10, (int) session('questions_per_page'));
        }

        $this->userSkills = session('qa_user_skills', ['React', 'Laravel', 'MongoDB']);
        $this->initializeQuestionInteractions();
    }

    public function updatingSearch(): void
    {
        $this->perPage = 10;
        session(['questions_per_page' => 10]);
    }

    public function updatingSortBy(): void
    {
        $this->perPage = 10;
        session(['questions_per_page' => 10]);
    }

    public function updatingSelectedTag(): void
    {
        $this->perPage = 10;
        session(['questions_per_page' => 10]);
    }

    public function setSort(string $sort): void
    {
        $this->sortBy = $sort;
        $this->perPage = 10;
        session(['questions_per_page' => 10]);
    }

    public function filterByTag(string $tag): void
    {
        $this->selectedTag = ($this->selectedTag === $tag) ? '' : $tag;
        $this->perPage = 10;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->selectedTag = '';
        $this->perPage = 10;
        $this->dispatch('toast', message: 'Search cleared', type: 'info');
    }

    public function searchSuggestion(string $term): void
    {
        $this->search = $term;
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
        session(['questions_per_page' => $this->perPage]);
    }

    public function openAskModal(): void
    {
        $this->resetValidation();
        $this->newTitle = '';
        $this->newDescription = '';
        $this->newTags = '';
        $this->newThumbnail = null;
        $this->showAskModal = true;
    }

    public function checkTitleAvailable(string $title, ?string $excludeId = null): bool
    {
        return Question::isTitleUnique($title, $excludeId);
    }

    public function submitQuestion(): void
    {
        if (! Auth::check()) {
            $this->showAskModal = false;
            $this->dispatch('open-auth-modal', 'Ask a Question');

            return;
        }

        $this->validate();

        $cleanTitle = trim($this->newTitle);
        if (! Question::isTitleUnique($cleanTitle)) {
            $this->addError('newTitle', 'A question with this title already exists. Please choose a unique title.');

            return;
        }

        $tagList = array_values(array_filter(array_map('trim', explode(',', $this->newTags))));
        if (empty($tagList)) {
            $tagList = ['General'];
        }

        $thumbnailPath = null;
        if ($this->newThumbnail) {
            $thumbnailPath = $this->newThumbnail->store('thumbnails', 'public');
        }

        $user = Auth::user();

        $question = Question::create([
            'user_id' => $user->_id ?? (string) ($user->id ?? 'anonymous'),
            'title' => $cleanTitle,
            'description' => $this->newDescription,
            'tags' => $tagList,
            'category' => $tagList,
            'thumbnail' => $thumbnailPath,
            'views_count' => 0,
            'votes_count' => 0,
            'shared_count' => 0,
            'answer_count' => 0,
            'likes_count' => 0,
            'is_closed' => false,
            'status' => 1,
            'follow' => [],
            'save' => [],
            'is_reported' => false,
        ]);

        $this->showAskModal = false;
        $this->newTitle = '';
        $this->newDescription = '';
        $this->newTags = '';
        $this->newThumbnail = null;

        $this->dispatch('toast', message: 'Question posted successfully! 🎉', type: 'success');
    }

    public function openEditModal(string $questionId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Edit a Question');

            return;
        }

        $q = Question::find($questionId);
        if (! $q) {
            return;
        }

        $currentUserId = (string) (Auth::id() ?? '');
        $questionAuthorId = (string) ($q->user_id ?? ($q->user->_id ?? $q->user->id ?? ''));
        if ($currentUserId === '' || $currentUserId !== $questionAuthorId) {
            $this->dispatch('toast', message: 'You are not authorized to edit this question.', type: 'error');

            return;
        }

        $this->editingQuestionId = $questionId;
        $this->editTitle = $q->title ?? '';
        $this->editDescription = $q->description ?? '';
        $tags = $q->tags ?? $q->category ?? [];
        $this->editTags = is_array($tags) ? implode(', ', $tags) : (string) $tags;
        $this->existingThumbnail = $q->thumbnail ?? null;
        $this->editThumbnail = null;

        // Dispatch fetched database data to edit modal
        $this->dispatch('populate-edit-modal', [
            'id' => (string) $q->_id,
            'title' => $this->editTitle,
            'description' => $this->editDescription,
            'tags' => $this->editTags,
            'thumbnail' => $this->existingThumbnail,
        ]);

        $this->showEditModal = true;
    }

    public function saveEdit(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Edit a Question');

            return;
        }

        if (! $this->editingQuestionId) {
            return;
        }

        $cleanTitle = trim($this->editTitle);
        if (empty($cleanTitle)) {
            $this->addError('editTitle', 'The question title is required.');

            return;
        }

        if (! Question::isTitleUnique($cleanTitle, $this->editingQuestionId)) {
            $this->addError('editTitle', 'A question with this title already exists. Please choose a unique title.');

            return;
        }

        $q = Question::find($this->editingQuestionId);
        if ($q) {
            $currentUserId = (string) (Auth::id() ?? '');
            $questionAuthorId = (string) ($q->user_id ?? ($q->user->_id ?? $q->user->id ?? ''));
            if ($currentUserId === '' || $currentUserId !== $questionAuthorId) {
                $this->dispatch('toast', message: 'You are not authorized to edit this question.', type: 'error');

                return;
            }

            $tagList = array_values(array_filter(array_map('trim', explode(',', $this->editTags))));
            if (empty($tagList)) {
                $tagList = ['General'];
            }

            $thumbnailPath = $this->existingThumbnail;
            if ($this->editThumbnail) {
                $thumbnailPath = $this->editThumbnail->store('thumbnails', 'public');
            }

            $q->update([
                'title' => $cleanTitle,
                'description' => $this->editDescription,
                'tags' => $tagList,
                'category' => $tagList,
                'thumbnail' => $thumbnailPath,
            ]);
        }

        $this->showEditModal = false;
        $this->editingQuestionId = null;
        $this->editTitle = '';
        $this->editDescription = '';
        $this->editTags = '';
        $this->editThumbnail = null;
        $this->existingThumbnail = null;
        $this->dispatch('toast', message: 'Question updated successfully! ✨', type: 'success');
    }

    public function openSkillsModal(): void
    {
        $this->newSkillInput = '';
        $this->showSkillsModal = true;
    }

    public function addSkill(string $skill = ''): void
    {
        $skillToAdd = trim($skill !== '' ? $skill : $this->newSkillInput);
        if ($skillToAdd === '') {
            return;
        }

        if (! in_array($skillToAdd, $this->userSkills, true)) {
            $this->userSkills[] = $skillToAdd;
            session(['qa_user_skills' => $this->userSkills]);
            $this->dispatch('toast', message: "'{$skillToAdd}' added to your skills!", type: 'success');
        } else {
            $this->dispatch('toast', message: 'Skill already added', type: 'warning');
        }

        $this->newSkillInput = '';
    }

    public function removeSkill(string $skill): void
    {
        $this->userSkills = array_values(array_diff($this->userSkills, [$skill]));
        session(['qa_user_skills' => $this->userSkills]);
        $this->dispatch('toast', message: "Removed '{$skill}'", type: 'info');
    }

    public function saveSkills(): void
    {
        session(['qa_user_skills' => $this->userSkills]);
        $this->showSkillsModal = false;
        $this->dispatch('toast', message: 'Skills updated successfully! 🎯', type: 'success');
    }

    public function resetFilters(): void
    {
        $this->filterSaved = false;
        $this->filterFollowing = false;
        $this->tagFilterType = '';
        $this->filterSkillSearch = '';
        $this->selectedTag = '';
        $this->showFilterModal = false;
        $this->dispatch('toast', message: 'Filters reset', type: 'info');
    }

    public function searchSkills(string $query = ''): array
    {
        $query = trim($query);
        if ($query === '') {
            return Skill::query()
                ->select(['skills_title'])
                ->whereIn('skills_status', [1, '1'])
                ->limit(20)
                ->pluck('skills_title')
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $escaped = preg_quote($query, '/');

        // 1. First search: Prefix matches using B-Tree index (fastest, hits skills_title index)
        $prefixMatches = Skill::query()
            ->select(['skills_title'])
            ->whereIn('skills_status', [1, '1'])
            ->where('skills_title', 'regex', new \MongoDB\BSON\Regex('^'.$escaped, 'i'))
            ->limit(15)
            ->pluck('skills_title')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // 2. If fewer than 10 prefix matches, supplement with substring matches
        if (count($prefixMatches) < 10) {
            $containsMatches = Skill::query()
                ->select(['skills_title'])
                ->whereIn('skills_status', [1, '1'])
                ->where('skills_title', 'regex', new \MongoDB\BSON\Regex($escaped, 'i'))
                ->limit(15)
                ->pluck('skills_title')
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();

            return array_values(array_unique(array_merge($prefixMatches, $containsMatches)));
        }

        return $prefixMatches;
    }

    public function with(): array
    {
        $query = Question::query()
            ->with('user')
            ->whereNotIn('status', [0, '0', false]);

        // Search Filter
        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('tags', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            });
        }

        // Skill Search from Filter Modal
        $skillSearch = trim($this->filterSkillSearch);
        if ($skillSearch !== '') {
            $query->where(function ($q) use ($skillSearch) {
                $q->where('tags', 'like', '%'.$skillSearch.'%')
                    ->orWhere('category', 'like', '%'.$skillSearch.'%')
                    ->orWhere('title', 'like', '%'.$skillSearch.'%');
            });
        }

        // Tag Filter from sidebar/pills
        if ($this->selectedTag !== '') {
            $query->where(function ($q) {
                $q->where('tags', 'like', '%'.$this->selectedTag.'%')
                    ->orWhere('category', 'like', '%'.$this->selectedTag.'%');
            });
        }

        // Tag Filter by matching skills
        if ($this->tagFilterType === 'matching' && ! empty($this->userSkills)) {
            $query->where(function ($q) {
                foreach ($this->userSkills as $skill) {
                    $q->orWhere('tags', 'like', '%'.$skill.'%')
                        ->orWhere('category', 'like', '%'.$skill.'%');
                }
            });
        }

        // Saved Filter
        if ($this->filterSaved) {
            $savedIds = array_values(array_filter(array_map('strval', $this->savedQuestions)));
            if (! empty($savedIds)) {
                $query->where(function ($q) use ($savedIds) {
                    $q->whereIn('_id', $savedIds);
                    if (Auth::check()) {
                        $q->orWhere('save', (string) (Auth::user()->_id ?? Auth::id()));
                    }
                });
            } else {
                $query->where('_id', '000000000000000000000000');
            }
        }

        // Following Filter
        if ($this->filterFollowing) {
            $followingUserIds = array_values(array_filter(array_map('strval', $this->followingUsers)));
            $followingQuestionIds = array_values(array_filter(array_map('strval', $this->followingQuestions)));

            $query->where(function ($q) use ($followingUserIds, $followingQuestionIds) {
                if (! empty($followingUserIds)) {
                    $q->whereIn('user_id', $followingUserIds);
                }
                if (! empty($followingQuestionIds)) {
                    $q->orWhereIn('_id', $followingQuestionIds);
                }
                if (empty($followingUserIds) && empty($followingQuestionIds)) {
                    $q->where('_id', '000000000000000000000000');
                }
            });
        }

        // Sort handling
        match ($this->sortBy) {
            'unanswered' => $query->where(function ($q) {
                $q->where('answer_count', 0)->orWhereNull('answer_count');
            })->latest(),
            'active' => $query->orderByDesc('updated_at'),
            'most_voted' => $query->orderByDesc('votes_count'),
            default => $query->latest(),
        };

        $stats = Cache::remember('qa_community_stats', 900, function () {
            return [
                'questions' => Question::whereNotIn('status', [0, '0', false])->count(),
                'answers' => Answer::whereNotIn('status', [0, '0', false])->count(),
                'users' => User::count(),
            ];
        });

        $questions = $query->paginate($this->perPage);

        // Sidebar widgets cached with 15-min TTL for rapid responsiveness
        $popularQuestions = Cache::remember('qa_popular_questions', 900, function () {
            return Question::query()
                ->whereNotIn('status', [0, '0', false])
                ->select(['_id', 'title', 'slug', 'answer_count', 'is_closed'])
                ->orderByDesc('views_count')
                ->limit(4)
                ->get();
        });

        $activeContributors = Cache::remember('qa_active_contributors', 900, function () {
            return User::query()
                ->select(['_id', 'fullname', 'first_name', 'last_name', 'photo', 'slug', 'user_role'])
                ->limit(4)
                ->get();
        });

        $commonTags = [
            ['name' => 'React', 'count' => '2.1k'],
            ['name' => 'TypeScript', 'count' => '1.8k'],
            ['name' => 'Laravel', 'count' => '1.6k'],
            ['name' => 'Python', 'count' => '1.5k'],
            ['name' => 'MongoDB', 'count' => '1.2k'],
            ['name' => 'Docker', 'count' => '890'],
            ['name' => 'CSS', 'count' => '750'],
            ['name' => 'Node.js', 'count' => '620'],
        ];

        $availableSkills = Cache::remember('qa_available_skills', 1800, function () {
            $skills = Skill::query()
                ->whereIn('skills_status', [1, '1'])
                ->limit(50)
                ->pluck('skills_title')
                ->filter()
                ->values()
                ->all();

            return ! empty($skills) ? $skills : [
                'React', 'Laravel', 'MongoDB', 'Python', 'TypeScript', 'Docker',
                'Data Analysis (STATA)', 'Visual Studio', 'PHP', 'JavaScript',
            ];
        });

        return [
            'questions' => $questions,
            'totalCount' => $questions->total(),
            'totalQuestionsCount' => $stats['questions'] ?? 0,
            'totalAnswersCount' => $stats['answers'] ?? 0,
            'totalUsersCount' => $stats['users'] ?? 0,
            'popularQuestions' => $popularQuestions,
            'activeContributors' => $activeContributors,
            'commonTags' => $commonTags,
            'availableSkills' => $availableSkills,
        ];
    }
}; ?>

<div class="min-h-screen bg-[#f9fafb] dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 font-['DM_Sans',sans-serif]">
    
    <!-- ================= TOP HEADER HERO BANNER ================= -->
    <div class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200/90 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center gap-4">
            <!-- Left Icon Badge -->
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-[#198BEA] text-white flex items-center justify-center shrink-0 shadow-md shadow-[#198BEA]/25">
                <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
            </div>

            <!-- Header Content -->
            <div class="space-y-0.5">
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">
                    Question &amp; Answer
                </h1>
                <p class="text-xs sm:text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Join Scholar9's Q&amp;A community
                </p>
                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed pt-0.5">
                    Ask questions, engage in discussions, and explore expert insights across various research fields. Connect with scholars, share knowledge, and contribute to the global research community. 
                    <span class="font-bold text-[#198BEA] dark:text-sky-400">Get Answers. Share Expertise. Advance Research.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            
            <!-- LEFT MAIN FEED COLUMN -->
            <div class="flex-1 min-w-0 w-full">
                
                <!-- 1. SEARCH & ACTION BAR -->
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 mb-5">
                    <div class="relative flex-1 min-w-[240px] w-full">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input 
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search questions by keyword, topic, or tag..." 
                            class="w-full pl-10 pr-10 py-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl text-sm placeholder-zinc-400 focus:outline-hidden focus:border-[#198BEA] focus:ring-3 focus:ring-[#198BEA]/15 transition-all shadow-xs"
                        />
                        @if($search !== '')
                            <button 
                                wire:click="clearSearch"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                title="Clear search"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        @endif
                    </div>

                    <!-- Ask Question Button (Opens Ask Question Form Modal) -->
                    <button 
                        @click="$dispatch('open-ask-modal')"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all shrink-0 cursor-pointer active:scale-98"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span>Ask Question</span>
                    </button>

                    <!-- Filter Button -->
                    <button 
                        @click="$dispatch('open-filter-modal')"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:border-[#198BEA] hover:bg-sky-50/50 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-sm font-medium rounded-xl transition-all shrink-0 cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        <span class="hidden sm:inline">Filter</span>
                        @if($filterSaved || $filterFollowing || $selectedTag !== '')
                            <span class="w-2 h-2 rounded-full bg-[#198BEA]"></span>
                        @endif
                    </button>
                </div>

                <!-- 3. SORT ROW & ACTIVE TAG FILTER -->
                <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mr-1">
                            Sort by
                        </span>
                        <button 
                            wire:click="setSort('newest')"
                            type="button"
                            class="px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition-all cursor-pointer {{ $sortBy === 'newest' ? 'bg-[#eaf5ff] text-[#198BEA] font-semibold dark:bg-sky-950/60 dark:text-sky-300' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                        >
                            Newest
                        </button>
                        <button 
                            wire:click="setSort('unanswered')"
                            type="button"
                            class="px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition-all cursor-pointer {{ $sortBy === 'unanswered' ? 'bg-[#eaf5ff] text-[#198BEA] font-semibold dark:bg-sky-950/60 dark:text-sky-300' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                        >
                            Unanswered
                        </button>
                        <button 
                            wire:click="setSort('active')"
                            type="button"
                            class="px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition-all cursor-pointer {{ $sortBy === 'active' ? 'bg-[#eaf5ff] text-[#198BEA] font-semibold dark:bg-sky-950/60 dark:text-sky-300' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                        >
                            Active
                        </button>
                        <button 
                            wire:click="setSort('most_voted')"
                            type="button"
                            class="px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition-all cursor-pointer {{ $sortBy === 'most_voted' ? 'bg-[#eaf5ff] text-[#198BEA] font-semibold dark:bg-sky-950/60 dark:text-sky-300' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                        >
                            Most Voted
                        </button>
                    </div>

                    @if($selectedTag !== '')
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-sky-100 dark:bg-sky-950 text-[#198BEA] dark:text-sky-300 rounded-lg text-xs font-semibold">
                            <span>Tag: {{ $selectedTag }}</span>
                            <button wire:click="$set('selectedTag', '')" class="hover:text-sky-700 dark:hover:text-sky-100">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endif
                </div>

                <!-- 4. QUESTIONS FEED LIST -->
                <div class="space-y-4">
                    @forelse($questions as $question)
                        @php
                            $author = $question->user;
                            $authorId = (string) ($question->user_id ?? ($author?->_id ?? $author?->id ?? ''));
                            $authorName = $author?->name ?? 'User';
                            $authorAvatar = $author?->avatar;
                            $tags = $question->tags ?? $question->category ?? [];
                            if (!is_array($tags)) {
                                $tags = is_string($tags) ? explode(',', $tags) : [];
                            }
                            $isVotedUp = in_array($votedQuestions[$question->id] ?? ($votedQuestions[(string)$question->_id] ?? ''), ['up', 'upvote'], true);
                            $isVotedDown = in_array($votedQuestions[$question->id] ?? ($votedQuestions[(string)$question->_id] ?? ''), ['down', 'downvote'], true);
                            $isSaved = in_array((string)$question->id, array_map('strval', $savedQuestions), true) || in_array((string)$question->_id, array_map('strval', $savedQuestions), true);
                            $isFollowingAuthor = $authorId !== '' && in_array($authorId, $followingUsers, true);
                            $hasAccepted = (bool)($question->is_closed ?? false);
                        @endphp

                        <article 
                            wire:key="q-{{ $question->id }}"
                            class="group bg-white dark:bg-zinc-900 border border-zinc-200/90 dark:border-zinc-800/80 rounded-2xl p-5 sm:p-6 shadow-xs hover:shadow-lg hover:shadow-sky-500/5 hover:-translate-y-0.5 transition-all duration-300"
                        >
                            <!-- Card Header: User, Role Badge, Time, Follow/Edit Actions -->
                            <div class="flex items-center justify-between flex-wrap gap-2.5 mb-3.5">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img 
                                        src="{{ $authorAvatar }}" 
                                        alt="{{ $authorName }}" 
                                        class="w-10 h-10 rounded-full object-cover ring-2 ring-[#198BEA]/20 shrink-0" 
                                    />
                                    <div class="flex items-center flex-wrap gap-x-2 gap-y-1 min-w-0">
                                        <span class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                                            {{ $authorName }}
                                        </span>

                                        <!-- Badge -->
                                        @if(($question->votes_count ?? 0) > 10)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                                Expert
                                            </span>
                                        @elseif($author?->user_role === 'reviewer')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                Mentor
                                            </span>
                                        @endif

                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                            • {{ $question->created_at ? $question->created_at->diffForHumans() : 'Recently' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if($hasAccepted)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            Solved
                                        </span>
                                    @endif

                                    @auth
                                        @php
                                            $currentUserId = (string) (Auth::user()->_id ?? Auth::id());
                                            $isOwner = $currentUserId !== '' && $currentUserId === $authorId;
                                        @endphp

                                        @if($authorId !== '' && !$isOwner)
                                            <button 
                                                wire:click="toggleFollowUser('{{ $authorId }}', '{{ addslashes($authorName) }}')"
                                                type="button"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isFollowingAuthor ? 'bg-[#ecfdf5] text-[#059669] border border-[#10b981] dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-600' : 'text-[#198BEA] border border-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40' }}"
                                            >
                                                @if($isFollowingAuthor)
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                        <circle cx="9" cy="7" r="4"/>
                                                        <polyline points="16 11 18 13 22 9"/>
                                                    </svg>
                                                    <span>Following</span>
                                                @else
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                        <circle cx="9" cy="7" r="4"/>
                                                        <line x1="19" x2="19" y1="8" y2="14"/>
                                                        <line x1="22" x2="16" y1="11" y2="11"/>
                                                    </svg>
                                                    <span>Follow</span>
                                                @endif
                                            </button>
                                        @endif

                                        @if($isOwner)
                                            <!-- Edit Button (Fetches latest data from backend on click) -->
                                            <button 
                                                wire:click="openEditModal('{{ $question->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="openEditModal('{{ $question->id }}')"
                                                type="button"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium text-zinc-600 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:text-[#198BEA] hover:border-[#198BEA] hover:bg-sky-50 dark:hover:bg-zinc-800 transition-all cursor-pointer disabled:opacity-60"
                                                title="Edit question"
                                            >
                                                <svg wire:loading wire:target="openEditModal('{{ $question->id }}')" class="animate-spin w-3.5 h-3.5 text-[#198BEA]" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <svg wire:loading.remove wire:target="openEditModal('{{ $question->id }}')" class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21h-7" />
                                                </svg>
                                                <span>Edit</span>
                                            </button>
                                        @endif
                                    @else
                                        @if($authorId !== '')
                                            <button 
                                                wire:click="toggleFollowUser('{{ $authorId }}', '{{ addslashes($authorName) }}')"
                                                type="button"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer text-[#198BEA] border border-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40"
                                            >
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                    <circle cx="9" cy="7" r="4"/>
                                                    <line x1="19" x2="19" y1="8" y2="14"/>
                                                    <line x1="22" x2="16" y1="11" y2="11"/>
                                                </svg>
                                                <span>Follow</span>
                                            </button>
                                        @endif
                                    @endauth
                                </div>
                            </div>

                            <!-- Title (Opens Question Detail Page) -->
                            <a 
                                href="{{ route('questions.show', $question->slug ?: (string)$question->_id) }}" 
                                wire:navigate
                                class="block group/title"
                            >
                                <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white group-hover/title:text-[#198BEA] dark:group-hover/title:text-sky-400 transition-colors leading-snug mb-2 cursor-pointer">
                                    {{ $question->title }}
                                </h2>
                            </a>

                            <!-- Body Preview (Plain text excerpt for listing feed: strictly 2 lines) -->
                            <p class="text-sm text-text-secondary dark:text-zinc-300 line-clamp-2 leading-relaxed mb-3.5">
                                {{ strip_tags($question->description) }}
                            </p>

                            <!-- Optional Media / Thumbnail (Only shown when image loads successfully) -->
                            @if(!empty($question->thumbnail))
                                @php
                                    $thumbUrl = Str::startsWith($question->thumbnail, ['http://', 'https://']) 
                                        ? $question->thumbnail 
                                        : (Str::startsWith($question->thumbnail, ['storage/', '/storage/'])
                                            ? asset($question->thumbnail)
                                            : asset('storage/' . $question->thumbnail));
                                @endphp
                                <div class="mb-4 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 max-w-lg bg-zinc-100 dark:bg-zinc-800">
                                    <img 
                                        src="{{ $thumbUrl }}" 
                                        alt="{{ $question->title }}" 
                                        class="w-full max-h-56 object-cover hover:scale-102 transition-transform duration-500"
                                        loading="lazy"
                                        onerror="this.parentElement.style.display='none'"
                                    />
                                </div>
                            @endif

                            <!-- Tags Pills (Static Display Badges) -->
                            @if(!empty($tags))
                                <div class="flex flex-wrap gap-2 mb-3.5">
                                    @foreach($tags as $index => $tag)
                                        @php
                                            $tagTrim = trim($tag);
                                        @endphp
                                        @if($tagTrim !== '')
                                            <span 
                                                class="px-2.5 py-1 rounded-md text-xs font-semibold bg-[#eaf5ff] text-[#198BEA] dark:bg-sky-950/60 dark:text-sky-300 select-none"
                                            >
                                                {{ $tagTrim }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <!-- Stats Bar (Matching Design Template: 💬 18 Answers  👁 2.4k Views  ↑ 247 Votes  🕒 2h ago) -->
                            <div class="flex items-center gap-4 sm:gap-6 py-2.5 my-3.5 border-t border-b border-zinc-200/80 dark:border-zinc-800 text-[13px] text-zinc-600 dark:text-zinc-300 font-medium flex-wrap">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    <span><strong class="font-bold text-zinc-900 dark:text-white">{{ $question->answer_count ?? ($question->answers ? $question->answers->count() : 0) }}</strong> Answers</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span><strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($question->views_count ?? 0) }}</strong> Views</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-flex items-center gap-0.5">
                                        <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                        </svg>
                                        <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($question->upvotes_count ?? $question->votes_count ?? 0) }}</strong>
                                    </span>
                                    <span class="inline-flex items-center gap-0.5">
                                        <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                        </svg>
                                        <strong class="font-bold text-zinc-900 dark:text-white">{{ number_format($question->downvotes_count ?? 0) }}</strong>
                                    </span>
                                    <span>Votes</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>{{ $question->created_at ? $question->created_at->diffForHumans() : 'Recent' }}</span>
                                </span>
                            </div>

                            <!-- Bottom Actions: Upvote, Downvote, Follow, Save, Share, Answer -->
                            <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                                <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                                    @auth
                                        <!-- Upvote -->
                                        <button 
                                            wire:click="toggleVote('{{ $question->id }}', 'upvote')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isVotedUp ? 'border border-[#198BEA] text-[#198BEA] bg-[#f0f7ff] hover:bg-[#e0f0fe] dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-600' : 'text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800' }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                                            </svg>
                                            <span>Upvote</span>
                                        </button>

                                        <!-- Downvote -->
                                        <button 
                                            wire:click="toggleVote('{{ $question->id }}', 'downvote')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isVotedDown ? 'border border-rose-400 text-rose-600 bg-rose-50 hover:bg-rose-100/70 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-500' : 'text-zinc-700 dark:text-zinc-200 hover:text-rose-600 hover:bg-rose-50/60 dark:hover:bg-zinc-800' }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76 1.04m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/>
                                            </svg>
                                            <span>Downvote</span>
                                        </button>

                                        <!-- Follow Question -->
                                        @php
                                            $isFollowingQuestion = in_array($question->id, $followingQuestions, true);
                                            $followCount = is_array($question->follow) ? count($question->follow) : (int)($question->follow ?? 0);
                                            if ($isFollowingQuestion && $followCount === 0) {
                                                $followCount = 1;
                                            }
                                        @endphp
                                        <button 
                                            wire:click="toggleFollowQuestion('{{ $question->id }}')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isFollowingQuestion ? 'bg-sky-50 text-[#198BEA] border border-sky-300 dark:bg-sky-950/50 dark:text-sky-300' : 'text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800' }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                            </svg>
                                            <span>{{ $followCount }} {{ $isFollowingQuestion ? 'Following' : 'Follow' }}</span>
                                        </button>

                                        <!-- Save -->
                                        @php
                                            $isSaved = in_array((string) $question->id, $savedQuestions, true) || in_array((string) ($question->_id ?? ''), $savedQuestions, true);
                                        @endphp
                                        <button 
                                            wire:click="toggleSave('{{ $question->id }}')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isSaved ? 'bg-amber-50 text-amber-600 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300' : 'text-zinc-700 dark:text-zinc-200 hover:text-amber-600 hover:bg-amber-50/60 dark:hover:bg-zinc-800' }}"
                                            title="Save question"
                                        >
                                            <svg class="w-4 h-4 {{ $isSaved ? 'fill-amber-500 text-amber-500' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                            </svg>
                                            <span>{{ $isSaved ? 'Saved' : 'Save' }}</span>
                                        </button>
                                    @else
                                        <!-- Guest Upvote -->
                                        <button 
                                            @click="$dispatch('open-auth-modal', 'Vote on Questions')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                                            </svg>
                                            <span>Upvote</span>
                                        </button>

                                        <!-- Guest Downvote -->
                                        <button 
                                            @click="$dispatch('open-auth-modal', 'Vote on Questions')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:text-rose-600 hover:bg-rose-50/60 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76 1.04m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/>
                                            </svg>
                                            <span>Downvote</span>
                                        </button>

                                        <!-- Guest Follow Question -->
                                        <button 
                                            @click="$dispatch('open-auth-modal', 'Follow Questions')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                            </svg>
                                            <span>{{ is_array($question->follow) ? count($question->follow) : (int)($question->follow ?? 0) }} Follow</span>
                                        </button>

                                        <!-- Guest Save -->
                                        <button 
                                            @click="$dispatch('open-auth-modal', 'Save Questions')"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:text-amber-600 hover:bg-amber-50/60 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                                            title="Save question"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                            </svg>
                                            <span>Save</span>
                                        </button>
                                    @endauth

                                    <!-- Share (Dispatches Global Modal Component) -->
                                    @php
                                        $shareCount = (int)($question->shared_count ?? 0);
                                        $questionShareUrl = route('questions.show', $question->slug ?: (string)$question->_id);
                                    @endphp
                                    <button 
                                        type="button"
                                        @click="$dispatch('open-share-modal', { url: '{{ $questionShareUrl }}', title: '{{ addslashes($question->title) }}', type: 'question', header: 'Share Question', subtitle: 'Share this question across academic and social networks' })"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                        </svg>
                                        <span>{{ $shareCount }} Share</span>
                                    </button>
                                </div>

                                <!-- Answer Action Button (Direct Link to Question Detail Page with write-answer anchor) -->
                                <a 
                                    href="{{ route('questions.show', $question->slug ?: (string) $question->_id) }}#write-answer"
                                    class="inline-flex items-center gap-2 px-5 py-2 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0f67b0] text-white text-xs sm:text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all cursor-pointer active:scale-98"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                    </svg>
                                    <span>Answer</span>
                                </a>
                            </div>
                        </article>
                    @empty
                        <!-- EMPTY STATE (Matching Design Template) -->
                        <div id="qaEmpty" class="qa-empty visible bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 sm:p-14 text-center mb-4">
                            <div class="qa-empty__illo">
                                <div class="qa-empty__circle"></div>
                                <div class="qa-empty__ring"></div>
                                <div class="qa-empty__handle"></div>
                                <div class="qa-empty__qmark">?</div>
                                <div class="qa-empty__dot qa-empty__dot--1"></div>
                                <div class="qa-empty__dot qa-empty__dot--2"></div>
                                <div class="qa-empty__dot qa-empty__dot--3"></div>
                                <div class="qa-empty__dot qa-empty__dot--4"></div>
                                <div class="qa-empty__dot qa-empty__dot--5"></div>
                                <div class="qa-empty__icons">
                                    <div class="qa-empty__icon-box qa-empty__icon-box--1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>
                                    </div>
                                    <div class="qa-empty__icon-box qa-empty__icon-box--2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                                    </div>
                                    <div class="qa-empty__icon-box qa-empty__icon-box--3">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zm0 0a8.949 8.949 0 004.951-1.488A3.987 3.987 0 0013 16h-2a3.987 3.987 0 00-3.951 3.512A8.949 8.949 0 0012 21z"/></svg>
                                    </div>
                                </div>
                            </div>
                            <h2 class="qa-empty__title text-xl font-bold text-zinc-900 dark:text-white mb-2">
                                @if($search !== '')
                                    No results for "<span class="text-[#198BEA]">{{ $search }}</span>"
                                @else
                                    No questions found
                                @endif
                            </h2>
                            <p class="qa-empty__desc text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto mb-6 leading-relaxed">
                                We couldn't find any questions matching your search. Try different keywords or be the first to ask!
                            </p>
                            <div class="qa-empty__btns flex flex-col sm:flex-row items-center justify-center gap-3 w-full max-w-sm mx-auto">
                                <button 
                                    @click="$dispatch('open-ask-modal')"
                                    type="button"
                                    class="qa-empty__ask w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] text-white text-sm font-semibold rounded-xl shadow-md transition-all cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    <span>Ask This Question</span>
                                </button>
                                <button 
                                    wire:click="clearSearch"
                                    type="button"
                                    class="qa-empty__clear w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:border-[#198BEA] text-zinc-700 dark:text-zinc-300 text-sm font-medium rounded-xl transition-all cursor-pointer shadow-xs"
                                >
                                    Clear Search
                                </button>
                            </div>
                        </div>
                    @endforelse
                </div>

                <!-- 5. LOAD MORE BUTTON -->
                @if($questions->hasMorePages())
                    <div class="mt-8 text-center">
                        <button 
                            wire:click="loadMore"
                            wire:loading.attr="disabled"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:border-[#198BEA] text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] text-sm font-semibold rounded-xl shadow-xs transition-all cursor-pointer disabled:opacity-60"
                        >
                            <svg wire:loading wire:target="loadMore" class="animate-spin h-4 w-4 text-[#198BEA] shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span wire:loading.remove wire:target="loadMore">Load More Questions</span>
                            <span wire:loading wire:target="loadMore">Loading...</span>
                        </button>
                    </div>
                @endif
            </div>

            <!-- RIGHT SIDEBAR COLUMN -->
            <x-question.sidebar 
                :total-questions-count="$totalQuestionsCount"
                :total-answers-count="$totalAnswersCount"
                :total-users-count="$totalUsersCount"
                :user-skills="$userSkills"
                :common-tags="$commonTags"
                :active-contributors="$activeContributors"
                :popular-questions="$popularQuestions"
                :following-users="$followingUsers"
                :selected-tag="$selectedTag"
            />
        </div>
    </div>

    <!-- MODAL 1: ASK QUESTION (DEDICATED COMPONENT) -->
    <x-question.ask-question :available-skills="$availableSkills" />

    <!-- MODAL 2: EDIT QUESTION (DEDICATED COMPONENT) -->
    <x-question.edit-question :available-skills="$availableSkills" />

    <!-- MODAL 5: FILTER (COMPONENT) -->
    <x-question.question-filter />

    <!-- MODAL 6: SKILLS & EXPERTISE (ALPINE.JS DRIVEN) -->
    <div 
        x-data="{ show: @entangle('showSkillsModal') }" 
        x-show="show" 
        x-cloak 
        @open-skills-modal.window="show = true"
        @keydown.escape.window="show = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        style="display: none;"
    >
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-md shadow-2xl p-5 space-y-4" @click.stop>
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Edit Skills &amp; Expertise
                </h3>
                <button @click="show = false" type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-zinc-600 dark:text-zinc-300 mb-1">Add a Skill</label>
                    <div class="flex gap-2">
                        <input 
                            type="text" 
                            wire:model="newSkillInput" 
                            wire:keydown.enter.prevent="addSkill()"
                            placeholder="e.g., Python, Docker, AI" 
                            class="flex-1 px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm"
                        />
                        <button wire:click="addSkill()" class="px-4 py-2 bg-[#198BEA] text-white text-xs font-bold rounded-xl shrink-0 cursor-pointer">Add</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-zinc-600 dark:text-zinc-300 mb-1.5">Your Skills</label>
                    <div class="flex flex-wrap gap-1.5 min-h-[44px] p-2.5 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        @if(!empty($userSkills))
                            @foreach($userSkills as $s)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#eaf5ff] dark:bg-sky-950/60 text-[#198BEA] dark:text-sky-300 rounded-lg text-xs font-semibold">
                                    {{ $s }}
                                    <button wire:click="removeSkill('{{ $s }}')" class="hover:text-rose-500 cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </span>
                            @endforeach
                        @else
                            <span class="text-xs text-zinc-400">No skills added yet</span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-zinc-400 mb-1.5">Popular Suggestions</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['React', 'TypeScript', 'Node.js', 'Python', 'AWS', 'Docker', 'CSS', 'GraphQL', 'Laravel', 'MongoDB'] as $sug)
                            <button 
                                wire:click="addSkill('{{ $sug }}')" 
                                class="px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-[#eaf5ff] hover:text-[#198BEA] rounded-lg text-xs font-medium transition cursor-pointer"
                            >
                                + {{ $sug }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <button @click="show = false" type="button" class="px-5 py-2.5 text-sm font-semibold text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 hover:text-zinc-900 dark:hover:text-white hover:border-zinc-300 dark:hover:border-zinc-600 rounded-xl transition-all cursor-pointer shadow-xs">Cancel</button>
                <button 
                    wire:click="saveSkills" 
                    wire:loading.attr="disabled"
                    type="button" 
                    class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98 disabled:opacity-60"
                >
                    <svg wire:loading wire:target="saveSkills" class="animate-spin h-4 w-4 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span wire:loading.remove wire:target="saveSkills">Save Skills</span>
                    <span wire:loading wire:target="saveSkills">Saving...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Auth Prompt Modal -->
    <x-modals.auth />

    <style>
        /* ===== NO RESULTS EMPTY STATE ANIMATIONS & STYLING ===== */
        .qa-empty {display: flex;flex-direction: column;align-items: center;text-align: center;animation: emptyFadeIn 0.45s cubic-bezier(0.16, 1, 0.3, 1);
        } @keyframes emptyFadeIn { from { opacity: 0; transform: translateY(14px); }
         to { opacity: 1; transform: translateY(0); }
         }
        .qa-empty__illo { position: relative;width: 200px;height: 170px;margin: 0 auto 24px;flex-shrink: 0;
        }
        .qa-empty__circle {position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);width: 140px;height: 140px;border-radius: 50%;background: linear-gradient(135deg, #eaf5ff 0%, rgba(124, 58, 237, 0.06) 100%);animation: emptyCircleFloat 4.5s ease-in-out infinite;
        }
        .dark .qa-empty__circle {background: linear-gradient(135deg, rgba(25, 139, 234, 0.15) 0%, rgba(124, 58, 237, 0.1) 100%);
        }
        @keyframes emptyCircleFloat {0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -53%) scale(1.05); }
        }
        .qa-empty__ring { position: absolute;top: 50%;left: 50%;transform: translate(-50%, -50%);width: 72px;height: 72px;border: 4.5px solid #198BEA;border-radius: 50%;opacity: 0.65;animation: emptyRingPulse 3.5s ease-in-out infinite;
        }
        @keyframes emptyRingPulse {0%, 100% { transform: translate(-50%, -50%) scale(1) rotate(0deg); }
            50% { transform: translate(-50%, -54%) scale(1.06) rotate(-10deg); }
        }
        .qa-empty__handle { position: absolute;top: 50%;left: 50%;width: 5px;height: 28px;background: #198BEA;border-radius: 3px;opacity: 0.65;transform: translate(22px, 14px) rotate(45deg);transform-origin: top center;
        }
        .qa-empty__qmark { position: absolute;top: 50%;left: 50%;transform: translate(-50%, -62%);font-size: 1.75rem;font-weight: 800;color: #198BEA;opacity: 0.55;animation: emptyQ 2.8s ease-in-out infinite;user-select: none;
        }
        @keyframes emptyQ {0%, 100% { transform: translate(-50%, -62%) scale(1); }35% { transform: translate(-50%, -70%) scale(1.12); }65% { transform: translate(-50%, -62%) scale(1); }
        }
        .qa-empty__dot { position: absolute;border-radius: 50%;opacity: 0.35;
        }
        .qa-empty__dot--1 { width: 7px; height: 7px;background: #198BEA;top: 16%; left: 10%;animation: emptyDot 3.2s ease-in-out infinite 0s;
        }
        .qa-empty__dot--2 { width: 5px; height: 5px;background: #7c3aed;top: 10%; right: 16%;animation: emptyDot 3.8s ease-in-out infinite .5s;
        }
        .qa-empty__dot--3 { width: 9px; height: 9px;background: #d97706;bottom: 22%; left: 6%;animation: emptyDot 4s ease-in-out infinite 1s;
        }
        .qa-empty__dot--4 {width: 5px; height: 5px;background: #10b981;bottom: 14%; right: 10%;animation: emptyDot 3.4s ease-in-out infinite .8s;
        }
        .qa-empty__dot--5 {width: 6px; height: 6px;background: #198BEA;top: 38%; right: 4%;animation: emptyDot 3.6s ease-in-out infinite 1.3s;
        }
        @keyframes emptyDot {0%, 100% { transform: translateY(0) scale(1); opacity: .35; }50% { transform: translateY(-7px) scale(1.35); opacity: .65; }}
        .qa-empty__icons {position: absolute;bottom: 6px;left: 50%;transform: translateX(-50%);display: flex;gap: 10px;
        }
        .qa-empty__icon-box {width: 32px;height: 32px;border-radius: 8px;display: flex;align-items: center;justify-content: center;animation: emptyIconBob 3s ease-in-out infinite;
        }
        .qa-empty__icon-box--1 { background: #eaf5ff; color: #198BEA; animation-delay: 0s; }
        .dark .qa-empty__icon-box--1 { background: rgba(25, 139, 234, 0.2); }
        .qa-empty__icon-box--2 { background: #f5f3ff; color: #7c3aed; animation-delay: .55s; }
        .dark .qa-empty__icon-box--2 { background: rgba(124, 58, 237, 0.2); }
        .qa-empty__icon-box--3 { background: #ecfdf5; color: #059669; animation-delay: 1.1s; }
        .dark .qa-empty__icon-box--3 { background: rgba(5, 150, 105, 0.2); }
        @keyframes emptyIconBob {0%, 100% { transform: translateY(0); }50% { transform: translateY(-5px); }}
    </style>
</div>
