<?php

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

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

    public $newThumbnail;

    // Edit Question Form
    public ?string $editingQuestionId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editTags = '';

    // Answer Form
    public ?string $answeringQuestionId = null;

    public string $answeringQuestionTitle = '';

    public string $answerContent = '';

    // Modals state
    public bool $showAskModal = false;

    public bool $showEditModal = false;

    public bool $showAnswerModal = false;

    public bool $showShareModal = false;

    public bool $showFilterModal = false;

    public bool $showSkillsModal = false;

    public string $shareUrl = '';

    public string $shareTitle = '';

    // Track user local interactions for quick UI responsiveness
    public array $votedQuestions = [];

    public array $savedQuestions = [];

    public array $followingUsers = [];

    public array $followingQuestions = [];

    public function mount(): void
    {
        if (session()->has('questions_per_page')) {
            $this->perPage = max(10, (int) session('questions_per_page'));
        }

        $this->userSkills = session('qa_user_skills', ['React', 'Laravel', 'MongoDB']);
        $this->votedQuestions = session('qa_voted_questions', []);
        $this->savedQuestions = session('qa_saved_questions', []);
        $this->followingUsers = session('qa_following_users', []);
        $this->followingQuestions = session('qa_following_questions', []);
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

    public function toggleVote(string $questionId, string $type = 'up'): void
    {
        $current = $this->votedQuestions[$questionId] ?? null;

        if ($current === $type) {
            unset($this->votedQuestions[$questionId]);
            $delta = ($type === 'up') ? -1 : 1;
            $msg = ($type === 'up') ? 'Upvote removed' : 'Downvote removed';
            $toastType = 'info';
        } else {
            $delta = 0;
            if ($current === 'up') {
                $delta -= 1;
            } elseif ($current === 'down') {
                $delta += 1;
            }
            $delta += ($type === 'up') ? 1 : -1;
            $this->votedQuestions[$questionId] = $type;
            $msg = ($type === 'up') ? 'Question upvoted! 👍' : 'Question downvoted';
            $toastType = ($type === 'up') ? 'success' : 'warning';
        }

        session(['qa_voted_questions' => $this->votedQuestions]);

        $question = Question::find($questionId);
        if ($question) {
            $question->increment('votes_count', $delta);
        }

        $this->dispatch('toast', message: $msg, type: $toastType);
    }

    public function toggleSave(string $questionId): void
    {
        if (in_array($questionId, $this->savedQuestions, true)) {
            $this->savedQuestions = array_values(array_diff($this->savedQuestions, [$questionId]));
            $msg = 'Removed from bookmarks';
            $toastType = 'info';
        } else {
            $this->savedQuestions[] = $questionId;
            $msg = 'Question saved to bookmarks! 🔖';
            $toastType = 'success';
        }

        session(['qa_saved_questions' => $this->savedQuestions]);
        $this->dispatch('toast', message: $msg, type: $toastType);
    }

    public function toggleFollowUser(string $userId, string $userName = 'user'): void
    {
        if (in_array($userId, $this->followingUsers, true)) {
            $this->followingUsers = array_values(array_diff($this->followingUsers, [$userId]));
            $msg = "Unfollowed {$userName}";
            $toastType = 'info';
        } else {
            $this->followingUsers[] = $userId;
            $msg = "Now following {$userName}! ⭐";
            $toastType = 'success';
        }

        session(['qa_following_users' => $this->followingUsers]);
        $this->dispatch('toast', message: $msg, type: $toastType);
    }

    public function toggleFollowQuestion(string $questionId): void
    {
        if (in_array($questionId, $this->followingQuestions, true)) {
            $this->followingQuestions = array_values(array_diff($this->followingQuestions, [$questionId]));
            $msg = 'Unfollowed question';
            $toastType = 'info';
            $delta = -1;
        } else {
            $this->followingQuestions[] = $questionId;
            $msg = 'Following question updates! 🔔';
            $toastType = 'success';
            $delta = 1;
        }

        session(['qa_following_questions' => $this->followingQuestions]);

        $question = Question::find($questionId);
        if ($question) {
            $currentFollow = is_array($question->follow) ? count($question->follow) : (int) ($question->follow ?? 0);
            $newFollow = max(0, $currentFollow + $delta);
            $question->update(['follow' => $newFollow]);
        }

        $this->dispatch('toast', message: $msg, type: $toastType);
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

    public function submitQuestion(): void
    {
        $this->validate();

        $tagList = array_values(array_filter(array_map('trim', explode(',', $this->newTags))));
        if (empty($tagList)) {
            $tagList = ['General'];
        }

        $thumbnailPath = null;
        if ($this->newThumbnail) {
            $thumbnailPath = $this->newThumbnail->store('thumbnails', 'public');
        }

        $user = Auth::user() ?? User::first();

        $question = Question::create([
            'user_id' => $user?->_id ?? (string) ($user?->id ?? 'anonymous'),
            'title' => $this->newTitle,
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
        $q = Question::find($questionId);
        if (! $q) {
            return;
        }

        $this->editingQuestionId = $questionId;
        $this->editTitle = $q->title ?? '';
        $this->editDescription = $q->description ?? '';
        $tags = $q->tags ?? $q->category ?? [];
        $this->editTags = is_array($tags) ? implode(', ', $tags) : (string) $tags;
        $this->showEditModal = true;
    }

    public function saveEdit(): void
    {
        if (! $this->editingQuestionId) {
            return;
        }

        $q = Question::find($this->editingQuestionId);
        if ($q) {
            $tagList = array_values(array_filter(array_map('trim', explode(',', $this->editTags))));
            $q->update([
                'title' => $this->editTitle,
                'description' => $this->editDescription,
                'tags' => $tagList,
                'category' => $tagList,
            ]);
        }

        $this->showEditModal = false;
        $this->editingQuestionId = null;
        $this->dispatch('toast', message: 'Question updated successfully! ✨', type: 'success');
    }

    public function openAnswerModal(string $questionId): void
    {
        $q = Question::find($questionId);
        if (! $q) {
            return;
        }

        $this->answeringQuestionId = $questionId;
        $this->answeringQuestionTitle = $q->title ?? 'Question';
        $this->answerContent = '';
        $this->showAnswerModal = true;
    }

    public function submitAnswer(): void
    {
        if (empty(trim($this->answerContent))) {
            $this->addError('answerContent', 'Please enter your answer content.');

            return;
        }

        $user = Auth::user() ?? User::first();

        Answer::create([
            'question_id' => $this->answeringQuestionId,
            'user_id' => $user?->_id ?? (string) ($user?->id ?? 'anonymous'),
            'content' => $this->answerContent,
            'votes_count' => 0,
            'is_accepted' => false,
            'status' => 1,
        ]);

        $q = Question::find($this->answeringQuestionId);
        if ($q) {
            $q->increment('answer_count');
        }

        $this->showAnswerModal = false;
        $this->answeringQuestionId = null;
        $this->answerContent = '';
        $this->dispatch('toast', message: 'Answer submitted! Thank you 🙌', type: 'success');
    }

    public function openShareModal(string $questionId, string $title, string $slug = ''): void
    {
        $this->shareTitle = $title;
        $this->shareUrl = url('/questions#'.$questionId);
        $this->showShareModal = true;
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

    public function applyFilters(): void
    {
        $this->showFilterModal = false;
        $this->dispatch('toast', message: 'Filters applied!', type: 'success');
    }

    public function with(): array
    {
        $query = Question::query()->with('user');

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
        if ($this->filterSaved && ! empty($this->savedQuestions)) {
            $query->whereIn('_id', $this->savedQuestions);
        }

        // Following Filter
        if ($this->filterFollowing && ! empty($this->followingUsers)) {
            $query->whereIn('user_id', $this->followingUsers);
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

        $totalQuestionsCount = Question::count();
        $totalAnswersCount = Answer::count();
        $totalUsersCount = User::count();

        $questions = $query->paginate($this->perPage);

        // Sidebar widgets data
        $popularQuestions = Question::query()
            ->select(['_id', 'title', 'slug', 'answer_count', 'is_closed'])
            ->orderByDesc('views_count')
            ->limit(4)
            ->get();

        $activeContributors = User::query()
            ->select(['_id', 'fullname', 'first_name', 'last_name', 'photo', 'slug', 'user_role'])
            ->limit(4)
            ->get();

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

        return [
            'questions' => $questions,
            'totalCount' => $questions->total(),
            'totalQuestionsCount' => $totalQuestionsCount,
            'totalAnswersCount' => $totalAnswersCount,
            'totalUsersCount' => $totalUsersCount,
            'popularQuestions' => $popularQuestions,
            'activeContributors' => $activeContributors,
            'commonTags' => $commonTags,
        ];
    }
}; ?>

<div class="min-h-screen bg-[#f9fafb] dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 font-['DM_Sans',sans-serif]">
    
    <!-- TOAST NOTIFICATION CONTAINER -->
    <div 
        x-data="{ toasts: [] }" 
        @toast.window="
            let id = Date.now();
            toasts.push({ id: id, message: $event.detail.message, type: $event.detail.type || 'success' });
            setTimeout(() => { toasts = toasts.filter(t => t.id !== id); }, 3500);
        "
        class="fixed top-20 right-5 z-50 flex flex-col gap-2.5 max-w-sm pointer-events-none"
    >
        <template x-for="t in toasts" :key="t.id">
            <div 
                x-show="true"
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                :class="{
                    'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800': t.type === 'success',
                    'bg-sky-50 text-[#198BEA] border-sky-200 dark:bg-sky-950/80 dark:text-sky-300 dark:border-sky-800': t.type === 'info',
                    'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/80 dark:text-amber-300 dark:border-amber-800': t.type === 'warning',
                    'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/80 dark:text-rose-300 dark:border-rose-800': t.type === 'error'
                }"
                class="pointer-events-auto flex items-center gap-2.5 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium backdrop-blur-md"
            >
                <template x-if="t.type === 'success'">
                    <svg class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </template>
                <template x-if="t.type === 'info'">
                    <svg class="w-5 h-5 shrink-0 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </template>
                <template x-if="t.type === 'warning'">
                    <svg class="w-5 h-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </template>
                <span x-text="t.message"></span>
            </div>
        </template>
    </div>

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

                    <!-- Ask Question Button -->
                    <button 
                        wire:click="openAskModal"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all shrink-0 cursor-pointer active:scale-98"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span>Ask Question</span>
                    </button>

                    <!-- Filter Button -->
                    <button 
                        wire:click="$set('showFilterModal', true)"
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
                            $authorName = $author ? ($author->fullname ?? trim(($author->first_name ?? '').' '.($author->last_name ?? '')) ?: 'Anonymous Scholar') : 'Anonymous Scholar';
                            $authorAvatar = $author?->avatar ?? 'https://ui-avatars.com/api/?name='.urlencode($authorName).'&background=198BEA&color=fff';
                            $tags = $question->tags ?? $question->category ?? [];
                            if (!is_array($tags)) {
                                $tags = is_string($tags) ? explode(',', $tags) : [];
                            }
                            $isVotedUp = ($votedQuestions[$question->id] ?? '') === 'up';
                            $isVotedDown = ($votedQuestions[$question->id] ?? '') === 'down';
                            $isSaved = in_array($question->id, $savedQuestions, true);
                            $isFollowingAuthor = $author ? in_array((string)$author->id, $followingUsers, true) : false;
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
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                                Expert
                                            </span>
                                        @elseif($author?->user_role === 'reviewer')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
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

                                    @if($author)
                                        <button 
                                            wire:click="toggleFollowUser('{{ (string)$author->id }}', '{{ $authorName }}')"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isFollowingAuthor ? 'bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800' : 'text-[#198BEA] border border-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40' }}"
                                        >
                                            @if($isFollowingAuthor)
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                <span>Following</span>
                                            @else
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                                <span>Follow</span>
                                            @endif
                                        </button>
                                    @endif

                                    <!-- Edit Button (Image 1 Style: Rounded pill with pen/underline icon + Edit text) -->
                                    <button 
                                        wire:click="openEditModal('{{ $question->id }}')"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium text-zinc-600 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:text-[#198BEA] hover:border-[#198BEA] hover:bg-sky-50 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                                        title="Edit question"
                                    >
                                        <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21h-7" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Title -->
                            <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors leading-snug mb-2 cursor-pointer">
                                {{ $question->title }}
                            </h2>

                            <!-- Body Preview (Preserves Rich HTML from Editor) -->
                            <div class="prose prose-sm dark:prose-invert max-w-none text-sm text-zinc-600 dark:text-zinc-300 line-clamp-3 leading-relaxed mb-3.5 [&>p]:mb-1.5 [&>p:last-child]:mb-0 [&>ul]:list-disc [&>ul]:pl-5 [&>ol]:list-decimal [&>ol]:pl-5 [&>a]:text-[#198BEA] [&>a]:underline [&>code]:bg-zinc-100 dark:[&>code]:bg-zinc-800 [&>code]:px-1.5 [&>code]:py-0.5 [&>code]:rounded [&>code]:text-xs [&>blockquote]:border-l-2 [&>blockquote]:border-[#198BEA] [&>blockquote]:pl-3 [&>blockquote]:italic">
                                {!! $question->description !!}
                            </div>

                            <!-- Optional Media / Thumbnail (Only shown when image loads successfully) -->
                            @if(!empty($question->thumbnail))
                                @php
                                    $thumbUrl = Str::startsWith($question->thumbnail, 'http') 
                                        ? $question->thumbnail 
                                        : asset($question->thumbnail);
                                @endphp
                                <div 
                                    x-data="{ loaded: false }" 
                                    x-show="loaded" 
                                    x-cloak
                                    style="display: none;"
                                    class="mb-4 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 max-w-lg bg-zinc-100 dark:bg-zinc-800"
                                >
                                    <img 
                                        src="{{ $thumbUrl }}" 
                                        alt="{{ $question->title }}" 
                                        class="w-full max-h-56 object-cover hover:scale-102 transition-transform duration-500"
                                        x-on:load="loaded = true"
                                        x-on:error="loaded = false"
                                        loading="lazy"
                                    />
                                </div>
                            @endif

                            <!-- Tags Pills -->
                            @if(!empty($tags))
                                <div class="flex flex-wrap gap-2 mb-3.5">
                                    @foreach($tags as $index => $tag)
                                        @php
                                            $tagTrim = trim($tag);
                                        @endphp
                                        @if($tagTrim !== '')
                                            <button 
                                                wire:click="filterByTag('{{ $tagTrim }}')"
                                                type="button"
                                                class="px-2.5 py-1 rounded-md text-xs font-semibold bg-[#eaf5ff] text-[#198BEA] dark:bg-sky-950/60 dark:text-sky-300 hover:opacity-85 transition-all cursor-pointer"
                                            >
                                                {{ $tagTrim }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <!-- Stats Bar (Image 2 Style: 💬 0 Answers  👁 5 Views  ↑ 1 Votes  🕒 3 months ago) -->
                            <div class="flex items-center gap-4 sm:gap-6 py-2.5 my-3.5 border-t border-zinc-100 dark:border-zinc-800/80 text-xs text-zinc-500 dark:text-zinc-400 flex-wrap">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $question->answer_count ?? ($question->answers ? $question->answers->count() : 0) }}</strong> Answers</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-200">{{ number_format($question->views_count ?? 0) }}</strong> Views</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                    <span><strong class="font-semibold text-zinc-700 dark:text-zinc-200">{{ number_format($question->votes_count ?? 0) }}</strong> Votes</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-zinc-400">
                                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>{{ $question->created_at ? $question->created_at->diffForHumans() : 'Recent' }}</span>
                                </span>
                            </div>

                            <!-- Bottom Actions: Upvote, Downvote, Follow, Save, Share, Answer (Image 2 Style) -->
                            <div class="flex items-center justify-between flex-wrap gap-2 pt-2.5 border-t border-zinc-100 dark:border-zinc-800/80">
                                <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                                    <!-- Upvote -->
                                    <button 
                                        wire:click="toggleVote('{{ $question->id }}', 'up')"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $isVotedUp ? 'bg-[#198BEA] text-white border border-[#198BEA] shadow-xs' : 'border border-[#198BEA] text-[#198BEA] bg-[#f0f7ff] hover:bg-[#e0f0fe] dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-600' }}"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                                        </svg>
                                        <span>Upvote</span>
                                    </button>

                                    <!-- Downvote -->
                                    <button 
                                        wire:click="toggleVote('{{ $question->id }}', 'down')"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium transition-all cursor-pointer {{ $isVotedDown ? 'bg-rose-50 text-rose-600 border border-rose-300 dark:bg-rose-950/60 dark:text-rose-300' : 'text-zinc-600 dark:text-zinc-300 hover:text-rose-600 hover:bg-rose-50/60 dark:hover:bg-zinc-800' }}"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
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
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all cursor-pointer {{ $isFollowingQuestion ? 'bg-sky-50 text-[#198BEA] border border-sky-300 dark:bg-sky-950/50 dark:text-sky-300' : 'text-zinc-600 dark:text-zinc-300 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800' }}"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                        </svg>
                                        <span>{{ $followCount }} Follow</span>
                                    </button>

                                    <!-- Save -->
                                    <button 
                                        wire:click="toggleSave('{{ $question->id }}')"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all cursor-pointer {{ $isSaved ? 'bg-amber-50 text-amber-600 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300' : 'text-zinc-600 dark:text-zinc-300 hover:text-amber-600 hover:bg-amber-50/60 dark:hover:bg-zinc-800' }}"
                                        title="Save question"
                                    >
                                        <svg class="w-4 h-4 {{ $isSaved ? 'fill-amber-500 text-amber-500' : '' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                        </svg>
                                        <span>{{ $isSaved ? 'Saved' : 'Save' }}</span>
                                    </button>

                                    <!-- Share -->
                                    @php
                                        $shareCount = (int)($question->shared_count ?? 0);
                                    @endphp
                                    <button 
                                        wire:click="openShareModal('{{ $question->id }}', '{{ addslashes($question->title) }}', '{{ $question->slug }}')"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800 transition-all cursor-pointer"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                        </svg>
                                        <span>{{ $shareCount }} Share</span>
                                    </button>
                                </div>

                                <!-- Answer Action Button (Blue Pill Button with Pencil Icon) -->
                                <button 
                                    wire:click="openAnswerModal('{{ $question->id }}')"
                                    type="button"
                                    class="inline-flex items-center gap-2 px-5 py-2 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0f67b0] text-white text-xs sm:text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all cursor-pointer active:scale-98"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                    </svg>
                                    <span>Answer</span>
                                </button>
                            </div>
                        </article>
                    @empty
                        <!-- EMPTY STATE -->
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 sm:p-12 text-center shadow-xs">
                            <div class="relative w-40 h-36 mx-auto mb-6 flex items-center justify-center">
                                <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-[#eaf5ff] to-purple-50 dark:from-sky-950/40 dark:to-purple-950/30 blur-sm animate-pulse"></div>
                                <div class="w-20 h-20 rounded-full border-4 border-[#198BEA] flex items-center justify-center relative z-10 opacity-70">
                                    <span class="text-3xl font-extrabold text-[#198BEA]">?</span>
                                </div>
                            </div>

                            <h3 class="text-xl font-bold text-zinc-900 dark:text-white mb-2">
                                @if($search !== '')
                                    No results for "<span class="text-[#198BEA]">{{ $search }}</span>"
                                @else
                                    No questions found
                                @endif
                            </h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-md mx-auto mb-6 leading-relaxed">
                                We couldn't find any questions matching your filters. Try different keywords or be the first to ask!
                            </p>

                            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 max-w-sm mx-auto mb-8">
                                <button 
                                    wire:click="openAskModal"
                                    type="button"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] text-white text-sm font-semibold rounded-xl shadow-md transition-all cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    <span>Ask This Question</span>
                                </button>
                                <button 
                                    wire:click="clearSearch"
                                    type="button"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 text-sm font-medium rounded-xl transition-all cursor-pointer"
                                >
                                    Clear Search
                                </button>
                            </div>

                            <!-- Suggestions -->
                            <div class="pt-6 border-t border-zinc-100 dark:border-zinc-800 max-w-md mx-auto">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-3">
                                    Try searching for
                                </p>
                                <div class="grid grid-cols-2 gap-2 text-left">
                                    <button wire:click="searchSuggestion('React')" class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 hover:bg-sky-50 dark:hover:bg-sky-950/40 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:text-[#198BEA] transition-colors">
                                        <span>React</span>
                                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                    <button wire:click="searchSuggestion('TypeScript')" class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 hover:bg-sky-50 dark:hover:bg-sky-950/40 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:text-[#198BEA] transition-colors">
                                        <span>TypeScript</span>
                                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                    <button wire:click="searchSuggestion('Laravel')" class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 hover:bg-sky-50 dark:hover:bg-sky-950/40 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:text-[#198BEA] transition-colors">
                                        <span>Laravel</span>
                                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                    <button wire:click="searchSuggestion('Docker')" class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 hover:bg-sky-50 dark:hover:bg-sky-950/40 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:text-[#198BEA] transition-colors">
                                        <span>Docker</span>
                                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
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
                            class="inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:border-[#198BEA] text-zinc-800 dark:text-zinc-200 hover:text-[#198BEA] text-sm font-semibold rounded-xl shadow-xs transition-all cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="loadMore">Load More Questions</span>
                            <span wire:loading wire:target="loadMore" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-[#198BEA]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Loading...
                            </span>
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

    <!-- MODAL 1: ASK QUESTION (COMPONENT) -->
    <x-question.ask-question />

    <!-- MODAL 2: EDIT QUESTION -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-xl shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Edit Question
                    </h3>
                    <button wire:click="$set('showEditModal', false)" class="text-zinc-400 hover:text-zinc-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-zinc-600 dark:text-zinc-300 mb-1">Title</label>
                        <input type="text" wire:model="editTitle" class="w-full px-4 py-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm focus:border-[#198BEA] outline-hidden" />
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold uppercase text-zinc-600 dark:text-zinc-300">Details (Rich HTML)</label>
                            <span class="text-[11px] text-zinc-400">Maintains HTML markup</span>
                        </div>
                        <div 
                            x-data="{
                                wrapSelection(openTag, closeTag) {
                                    const textarea = this.$refs.editEditor;
                                    if (!textarea) return;
                                    const start = textarea.selectionStart;
                                    const end = textarea.selectionEnd;
                                    const text = textarea.value;
                                    const selectedText = text.substring(start, end);
                                    const replacement = openTag + (selectedText || 'text') + closeTag;
                                    textarea.value = text.substring(0, start) + replacement + text.substring(end);
                                    textarea.selectionStart = start + openTag.length;
                                    textarea.selectionEnd = start + replacement.length - closeTag.length;
                                    textarea.focus();
                                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                                },
                                insertList(type) {
                                    const textarea = this.$refs.editEditor;
                                    if (!textarea) return;
                                    const start = textarea.selectionStart;
                                    const end = textarea.selectionEnd;
                                    const text = textarea.value;
                                    const selected = text.substring(start, end) || 'Item';
                                    const tag = type === 'ol' ? '<ol>\n  <li>' + selected + '</li>\n</ol>' : '<ul>\n  <li>' + selected + '</li>\n</ul>';
                                    textarea.value = text.substring(0, start) + tag + text.substring(end);
                                    textarea.focus();
                                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }"
                            class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden focus-within:border-[#198BEA] focus-within:ring-2 focus-within:ring-[#198BEA]/15 bg-zinc-50 dark:bg-zinc-800 transition-all"
                        >
                            <div class="flex items-center flex-wrap gap-1 px-3 py-1.5 bg-zinc-100/90 dark:bg-zinc-800 border-b border-zinc-200/80 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 text-xs">
                                <button type="button" @click="wrapSelection('<strong>', '</strong>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-bold cursor-pointer" title="Bold">B</button>
                                <button type="button" @click="wrapSelection('<em>', '</em>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] italic font-serif cursor-pointer" title="Italic">I</button>
                                <button type="button" @click="wrapSelection('<u>', '</u>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] underline cursor-pointer" title="Underline">U</button>
                                <button type="button" @click="wrapSelection('<h3>', '</h3>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-semibold text-[11px] cursor-pointer" title="H3">H3</button>
                                <div class="h-3.5 w-px bg-zinc-300 dark:bg-zinc-600 mx-1"></div>
                                <button type="button" @click="insertList('ul')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] cursor-pointer" title="Bullet List">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                </button>
                                <button type="button" @click="insertList('ol')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] cursor-pointer" title="Numbered List">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 6h14M7 12h14M7 18h14M3 6h1v4M3 14h2v2H3v2h3"/></svg>
                                </button>
                                <button type="button" @click="wrapSelection('<a href=&quot;https://&quot; target=&quot;_blank&quot;>', '</a>')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] cursor-pointer" title="Insert Link">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                </button>
                                <button type="button" @click="wrapSelection('<code>', '</code>')" class="px-1.5 py-0.5 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-mono text-[11px] cursor-pointer" title="Code">&lt;/&gt;</button>
                            </div>
                            <textarea 
                                x-ref="editEditor"
                                rows="5" 
                                wire:model="editDescription" 
                                class="w-full px-4 py-2.5 bg-transparent text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-hidden resize-y"
                            ></textarea>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-zinc-600 dark:text-zinc-300 mb-1">Tags (comma separated)</label>
                        <input type="text" wire:model="editTags" class="w-full px-4 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm focus:border-[#198BEA] outline-hidden" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 p-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button wire:click="$set('showEditModal', false)" class="px-4 py-2 text-sm text-zinc-500">Cancel</button>
                    <button wire:click="saveEdit" class="px-5 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white text-sm font-bold rounded-xl shadow">Save Changes</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: WRITE ANSWER -->
    @if($showAnswerModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-xl shadow-2xl">
                <div class="flex items-center justify-between p-5 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Write Your Answer
                    </h3>
                    <button wire:click="$set('showAnswerModal', false)" class="text-zinc-400 hover:text-zinc-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-xl bg-[#eaf5ff] dark:bg-sky-950/40 text-xs text-sky-800 dark:text-sky-200">
                        <span class="font-bold">Answering:</span> {{ $answeringQuestionTitle }}
                    </div>

                    <div 
                        x-data="{
                            wrapSelection(openTag, closeTag) {
                                const textarea = this.$refs.answerEditor;
                                if (!textarea) return;
                                const start = textarea.selectionStart;
                                const end = textarea.selectionEnd;
                                const text = textarea.value;
                                const selectedText = text.substring(start, end);
                                const replacement = openTag + (selectedText || 'text') + closeTag;
                                textarea.value = text.substring(0, start) + replacement + text.substring(end);
                                textarea.selectionStart = start + openTag.length;
                                textarea.selectionEnd = start + replacement.length - closeTag.length;
                                textarea.focus();
                                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            },
                            insertList(type) {
                                const textarea = this.$refs.answerEditor;
                                if (!textarea) return;
                                const start = textarea.selectionStart;
                                const end = textarea.selectionEnd;
                                const text = textarea.value;
                                const selected = text.substring(start, end) || 'Item';
                                const tag = type === 'ol' ? '<ol>\n  <li>' + selected + '</li>\n</ol>' : '<ul>\n  <li>' + selected + '</li>\n</ul>';
                                textarea.value = text.substring(0, start) + tag + text.substring(end);
                                textarea.focus();
                                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }"
                        class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden focus-within:border-[#198BEA] focus-within:ring-2 focus-within:ring-[#198BEA]/15 bg-zinc-50 dark:bg-zinc-800 transition-all"
                    >
                        <div class="flex items-center flex-wrap gap-1 px-3 py-1.5 bg-zinc-100/90 dark:bg-zinc-800 border-b border-zinc-200/80 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 text-xs">
                            <button type="button" @click="wrapSelection('<strong>', '</strong>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-bold cursor-pointer" title="Bold">B</button>
                            <button type="button" @click="wrapSelection('<em>', '</em>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] italic font-serif cursor-pointer" title="Italic">I</button>
                            <button type="button" @click="wrapSelection('<u>', '</u>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] underline cursor-pointer" title="Underline">U</button>
                            <button type="button" @click="wrapSelection('<h3>', '</h3>')" class="px-2 py-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-semibold text-[11px] cursor-pointer" title="H3">H3</button>
                            <div class="h-3.5 w-px bg-zinc-300 dark:bg-zinc-600 mx-1"></div>
                            <button type="button" @click="insertList('ul')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] cursor-pointer" title="Bullet List">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                            </button>
                            <button type="button" @click="insertList('ol')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] cursor-pointer" title="Numbered List">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 6h14M7 12h14M7 18h14M3 6h1v4M3 14h2v2H3v2h3"/></svg>
                            </button>
                            <button type="button" @click="wrapSelection('<a href=&quot;https://&quot; target=&quot;_blank&quot;>', '</a>')" class="p-1 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] cursor-pointer" title="Insert Link">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4.5 4.5 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                            </button>
                            <button type="button" @click="wrapSelection('<pre><code>', '</code></pre>')" class="px-1.5 py-0.5 hover:bg-white dark:hover:bg-zinc-700 rounded hover:text-[#198BEA] font-mono text-[11px] cursor-pointer" title="Code Block">Code</button>
                        </div>
                        <textarea 
                            x-ref="answerEditor"
                            rows="6"
                            wire:model="answerContent"
                            placeholder="Provide your detailed answer, solutions, rich formatting, and code examples..."
                            class="w-full px-4 py-3 bg-transparent text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-hidden resize-y"
                        ></textarea>
                    </div>
                    @error('answerContent') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-end gap-3 p-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button wire:click="$set('showAnswerModal', false)" class="px-4 py-2 text-sm text-zinc-500">Cancel</button>
                    <button wire:click="submitAnswer" class="px-5 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white text-sm font-bold rounded-xl shadow">Post Answer</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 4: SHARE -->
    @if($showShareModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-md shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">Share Question</h3>
                    <button wire:click="$set('showShareModal', false)" class="text-zinc-400 hover:text-zinc-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3" x-data="{ copied: false }">
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $shareUrl }}" class="flex-1 px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs" />
                        <button 
                            @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="px-4 py-2 bg-[#198BEA] text-white text-xs font-bold rounded-xl shrink-0"
                        >
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied">Copied!</span>
                        </button>
                    </div>

                    <div class="flex items-center justify-center gap-3 pt-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank" class="w-10 h-10 rounded-full bg-[#1877F2] text-white flex items-center justify-center hover:opacity-85 shadow">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="https://twitter.com/intent/tweet?text={{ urlencode($shareTitle) }}&url={{ urlencode($shareUrl) }}" target="_blank" class="w-10 h-10 rounded-full bg-[#1DA1F2] text-white flex items-center justify-center hover:opacity-85 shadow">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.936 9.936 0 0024 4.59z"/></svg>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}" target="_blank" class="w-10 h-10 rounded-full bg-[#0A66C2] text-white flex items-center justify-center hover:opacity-85 shadow">
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 5: FILTER (COMPONENT) -->
    <x-question.question-filter />

    <!-- MODAL 6: SKILLS & EXPERTISE -->
    @if($showSkillsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-md shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Edit Skills &amp; Expertise
                    </h3>
                    <button wire:click="$set('showSkillsModal', false)" class="text-zinc-400 hover:text-zinc-600">
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
                            <button wire:click="addSkill()" class="px-4 py-2 bg-[#198BEA] text-white text-xs font-bold rounded-xl shrink-0">Add</button>
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
                                    class="px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-[#eaf5ff] hover:text-[#198BEA] rounded-lg text-xs font-medium transition"
                                >
                                    + {{ $sug }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <button wire:click="$set('showSkillsModal', false)" class="px-4 py-2 text-xs text-zinc-500">Cancel</button>
                    <button wire:click="saveSkills" class="px-5 py-2 bg-[#198BEA] text-white text-xs font-bold rounded-xl shadow">Save Skills</button>
                </div>
            </div>
        </div>
    @endif

</div>
