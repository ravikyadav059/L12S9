<?php

use App\Livewire\Concerns\HandlesQuestionInteractions;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Replies;
use App\Models\Skill;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use HandlesQuestionInteractions, WithFileUploads;

    public string $slug = '';

    public string $newAnswer = '';

    public string $answerSort = 'default';

    public array $votedAnswers = [];

    public array $savedAnswers = [];

    public array $followingAnswers = [];

    public array $commentInputs = [];

    public ?string $editingAnswerId = null;

    public string $editingAnswerContent = '';

    // Edit Question Form
    public ?string $editingQuestionId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editTags = '';

    public $editThumbnail;

    public ?string $existingThumbnail = null;

    public bool $showEditModal = false;

    public array $availableSkills = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        $this->availableSkills = Cache::remember('qa_available_skills', 3600, function () {
            return Skill::query()->limit(25)->pluck('name')->toArray();
        });

        // Record a view count on the question
        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->first();

        if ($question) {
            $question->increment('views_count');
            $this->initializeQuestionInteractions((string) ($question->_id ?? $question->id));
        } else {
            $this->initializeQuestionInteractions();
        }

        // Initialize user voted and saved answers if logged in
        if (Auth::check()) {
            $user = Auth::user();
            $userId = (string) ($user->_id ?? $user->id);
            $this->savedAnswers = is_array($user->saved_answers ?? null) ? $user->saved_answers : [];
            $dbFollowedAnswers = Answer::where('follow', $userId)->pluck('_id')->toArray();
            $this->followingAnswers = array_map('strval', $dbFollowedAnswers);
        }
    }

    public function searchSkills(string $query): array
    {
        $clean = trim($query);
        if (empty($clean)) {
            return [];
        }

        return Skill::query()
            ->where('name', 'like', "%{$clean}%")
            ->limit(10)
            ->pluck('name')
            ->toArray();
    }

    public function checkTitleAvailable(string $title, ?string $excludeId = null): bool
    {
        return Question::isTitleUnique($title, $excludeId);
    }

    public function openEditModal(string $questionId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Edit a Question');

            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $q = Question::find($questionId);

        if (! $q) {
            $this->dispatch('toast', message: 'Question not found.', type: 'error');

            return;
        }

        $authorId = (string) ($q->user_id ?? ($q->user->_id ?? $q->user->id ?? ''));
        if ($userId !== $authorId) {
            $this->dispatch('toast', message: 'You are not authorized to edit this question.', type: 'error');

            return;
        }

        $this->editingQuestionId = $questionId;
        $this->editTitle = $q->title ?? '';
        $this->editDescription = $q->description ?? '';
        $tags = $q->tags ?? [];
        $this->editTags = is_array($tags) ? implode(', ', $tags) : (string) $tags;
        $this->existingThumbnail = $q->thumbnail ?? null;
        $this->editThumbnail = null;

        $this->dispatch('populate-edit-modal', [
            'id' => $questionId,
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

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $q = Question::find($this->editingQuestionId);

        if ($q) {
            $authorId = (string) ($q->user_id ?? ($q->user->_id ?? $q->user->id ?? ''));
            if ($userId !== $authorId) {
                $this->dispatch('toast', message: 'You are not authorized to edit this question.', type: 'error');

                return;
            }

            $tagList = array_values(array_filter(array_map('trim', explode(',', $this->editTags))));
            if (! empty($tagList)) {
                $q->recordTagsUsage($tagList);
            }

            $thumbnailPath = $q->thumbnail;
            if ($this->editThumbnail) {
                $thumbnailPath = $this->editThumbnail->store('thumbnails', 'public');
            }

            $q->update([
                'title' => $cleanTitle,
                'slug' => Str::slug($cleanTitle),
                'description' => $this->editDescription,
                'tags' => $tagList,
                'thumbnail' => $thumbnailPath,
            ]);

            $this->slug = $q->slug;
            $this->dispatch('toast', message: 'Question updated successfully! ✏️', type: 'success');
        }

        $this->showEditModal = false;
        $this->editingQuestionId = null;
        $this->editTitle = '';
        $this->editDescription = '';
        $this->editTags = '';
        $this->editThumbnail = null;
    }

    public function submitAnswer(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Answer a Question');

            return;
        }

        $plain = trim(strip_tags(str_replace('&nbsp;', ' ', (string) $this->newAnswer)));
        if (empty($plain)) {
            $this->addError('newAnswer', 'Please enter your answer.');

            return;
        }

        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->firstOrFail();

        $user = Auth::user();

        Answer::create([
            'question_id' => (string) $question->id,
            'user_id' => $user->_id ?? (string) ($user->id ?? 'anonymous'),
            'content' => $this->newAnswer,
            'votes_count' => 0,
            'is_accepted' => false,
            'status' => 1,
        ]);

        $question->increment('answer_count');

        $this->newAnswer = '';
        $this->dispatch('toast', message: 'Answer posted successfully! 🙌', type: 'success');
    }

    public function toggleAcceptAnswer(string $answerId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Accept Answers');

            return;
        }

        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->firstOrFail();

        $currentUserId = (string) (Auth::id() ?? '');
        $questionAuthorId = (string) ($question->user_id ?? ($question->user->_id ?? $question->user->id ?? ''));

        if ($currentUserId !== $questionAuthorId) {
            $this->dispatch('toast', message: 'Only the question author can accept answers.', type: 'error');

            return;
        }

        $ans = Answer::find($answerId);
        if (! $ans) {
            return;
        }

        $isCurrentlyAccepted = (bool) ($ans->is_accepted ?? false);

        if ($isCurrentlyAccepted) {
            $ans->update(['is_accepted' => false]);
            $question->update(['is_closed' => false]);
            $this->dispatch('toast', message: 'Answer marked as unaccepted.', type: 'info');
        } else {
            // Unaccept other answers
            Answer::where('question_id', (string) $question->id)->update(['is_accepted' => false]);
            $ans->update(['is_accepted' => true]);
            $question->update(['is_closed' => true]);
            $this->dispatch('toast', message: 'Answer marked as accepted solution! ✓', type: 'success');
        }
    }

    public function toggleAnswerVote(string $answerId, string $type = 'upvote'): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-modal', 'Vote on Answers');

            return;
        }

        $ans = Answer::find($answerId);
        if (! $ans) {
            return;
        }

        $currentVote = $this->votedAnswers[$answerId] ?? null;
        $count = (int) ($ans->votes_count ?? 0);

        if ($currentVote === $type) {
            unset($this->votedAnswers[$answerId]);
            $count = $type === 'upvote' ? max(0, $count - 1) : $count + 1;
            $ans->update(['votes_count' => $count]);
            $this->dispatch('toast', message: 'Vote removed', type: 'info');
        } else {
            if ($currentVote === 'upvote' && $type === 'downvote') {
                $count = max(0, $count - 2);
            } elseif ($currentVote === 'downvote' && $type === 'upvote') {
                $count = $count + 2;
            } elseif ($type === 'upvote') {
                $count++;
            } else {
                $count = max(0, $count - 1);
            }

            $this->votedAnswers[$answerId] = $type;
            $ans->update(['votes_count' => $count]);
            $this->dispatch('toast', message: $type === 'upvote' ? 'Upvoted answer!' : 'Downvoted answer', type: $type === 'upvote' ? 'success' : 'info');
        }
    }

    public function toggleAnswerSave(string $answerId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-modal', 'Save Answers');

            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $ans = Answer::find($answerId);

        if (in_array($answerId, $this->savedAnswers, true)) {
            $this->savedAnswers = array_values(array_filter($this->savedAnswers, fn ($id) => $id !== $answerId));
            if ($ans) {
                $saveList = is_array($ans->save ?? null) ? $ans->save : [];
                $ans->update(['save' => array_values(array_filter($saveList, fn ($id) => (string) $id !== $userId))]);
            }
            $this->dispatch('toast', message: 'Removed from bookmarks', type: 'info');
        } else {
            $this->savedAnswers[] = $answerId;
            if ($ans) {
                $saveList = is_array($ans->save ?? null) ? $ans->save : [];
                $saveList[] = $userId;
                $ans->update(['save' => array_values(array_unique($saveList))]);
            }
            $this->dispatch('toast', message: 'Answer saved to bookmarks!', type: 'success');
        }
    }

    public function toggleFollowAnswer(string $answerId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Follow Answer Updates');

            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $ans = Answer::find($answerId);
        if (! $ans) {
            return;
        }

        $followList = is_array($ans->follow ?? null) ? $ans->follow : [];
        if (in_array($userId, $followList, true) || in_array((int) $userId, $followList, true)) {
            $followList = array_values(array_filter($followList, fn ($id) => (string) $id !== $userId && (string) $id !== (string) (int) $userId));
            $this->followingAnswers = array_values(array_filter($this->followingAnswers, fn ($id) => $id !== $answerId));
            $msg = 'Unfollowed answer';
            $toastType = 'info';
        } else {
            $followList[] = $userId;
            $this->followingAnswers[] = $answerId;
            $msg = 'Following answer updates! 🔔';
            $toastType = 'success';
        }

        $ans->update(['follow' => array_values(array_unique($followList))]);
        $this->dispatch('toast', message: $msg, type: $toastType);
    }

    public function addComment(string $answerId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Comment');

            return;
        }

        $content = trim($this->commentInputs[$answerId] ?? '');
        if (empty($content)) {
            return;
        }

        $ans = Answer::find($answerId);
        if (! $ans) {
            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());

        Replies::create([
            'answer_id' => (string) ($ans->_id ?? $ans->id),
            'user_id' => $userId,
            'content' => $content,
            'status' => 1,
            'likes' => [],
            'likes_count' => 0,
        ]);

        $this->commentInputs[$answerId] = '';
        $this->dispatch('toast', message: 'Comment posted! 💬', type: 'success');
    }

    public function deleteComment(string $commentId): void
    {
        if (! Auth::check()) {
            return;
        }

        $reply = Replies::find($commentId);
        if (! $reply) {
            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $replyAuthorId = (string) ($reply->user_id ?? '');

        if ($userId !== $replyAuthorId) {
            $this->dispatch('toast', message: 'You are not authorized to delete this comment.', type: 'error');

            return;
        }

        $reply->delete();
        $this->dispatch('toast', message: 'Comment deleted.', type: 'info');
    }

    public function toggleCommentLike(string $commentId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Like Comments');

            return;
        }

        $reply = Replies::find($commentId);
        if (! $reply) {
            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $likes = is_array($reply->likes ?? null) ? $reply->likes : [];

        if (in_array($userId, $likes, true)) {
            $likes = array_values(array_diff($likes, [$userId]));
        } else {
            $likes[] = $userId;
        }

        $reply->update([
            'likes' => array_values(array_unique($likes)),
            'likes_count' => count($likes),
        ]);
    }

    public function deleteAnswer(string $answerId): void
    {
        if (! Auth::check()) {
            return;
        }

        $ans = Answer::find($answerId);
        if (! $ans) {
            return;
        }

        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->first();

        $currentUserId = (string) (Auth::id() ?? '');
        $ansAuthorId = (string) ($ans->user_id ?? '');
        $qAuthorId = (string) ($question->user_id ?? '');

        if ($currentUserId !== $ansAuthorId && $currentUserId !== $qAuthorId) {
            $this->dispatch('toast', message: 'You are not authorized to delete this answer.', type: 'error');

            return;
        }

        Replies::where('answer_id', (string) ($ans->_id ?? $ans->id))->delete();
        $ans->delete();
        if ($question) {
            $question->decrement('answer_count');
        }

        $this->dispatch('toast', message: 'Answer deleted successfully.', type: 'info');
    }

    public function startEditAnswer(string $answerId): void
    {
        if (! Auth::check()) {
            return;
        }

        $ans = Answer::find($answerId);
        if ($ans && (string) $ans->user_id === (string) Auth::id()) {
            $this->editingAnswerId = (string) ($ans->_id ?? $ans->id);
            $this->editingAnswerContent = (string) ($ans->content ?? '');
        }
    }

    public function cancelEditAnswer(): void
    {
        $this->editingAnswerId = null;
        $this->editingAnswerContent = '';
    }

    public function saveEditAnswer(): void
    {
        if (! Auth::check() || ! $this->editingAnswerId) {
            return;
        }

        $plain = trim(strip_tags(str_replace('&nbsp;', ' ', (string) $this->editingAnswerContent)));
        if (empty($plain)) {
            $this->dispatch('toast', message: 'Answer content cannot be empty.', type: 'error');

            return;
        }

        $ans = Answer::find($this->editingAnswerId);
        if ($ans && (string) $ans->user_id === (string) Auth::id()) {
            $ans->update(['content' => $this->editingAnswerContent]);
            $this->dispatch('toast', message: 'Answer updated successfully! ✏️', type: 'success');
        }

        $this->cancelEditAnswer();
    }

    public function deleteQuestion(): void
    {
        if (! Auth::check()) {
            return;
        }

        $question = Question::query()
            ->where('slug', $this->slug)
            ->orWhere('_id', $this->slug)
            ->firstOrFail();

        $currentUserId = (string) (Auth::id() ?? '');
        $qAuthorId = (string) ($question->user_id ?? '');

        if ($currentUserId !== $qAuthorId) {
            $this->dispatch('toast', message: 'You are not authorized to delete this question.', type: 'error');

            return;
        }

        $answerIds = Answer::where('question_id', (string) $question->id)->pluck('_id')->toArray();
        if (! empty($answerIds)) {
            Replies::whereIn('answer_id', array_map('strval', $answerIds))->delete();
        }
        Answer::where('question_id', (string) $question->id)->delete();
        $question->delete();

        $this->dispatch('toast', message: 'Question deleted successfully.', type: 'info');
        $this->redirect(route('questions.index'), navigate: true);
    }

    public function with(): array
    {
        $question = Question::query()
            ->with(['user', 'answers.user', 'answers.replies.user'])
            ->where(function ($q) {
                $q->where('slug', $this->slug)
                    ->orWhere('_id', $this->slug);
            })
            ->whereNotIn('status', [0, '0', false])
            ->firstOrFail();

        $answersQuery = $question->answers ?? collect([]);

        // Apply Answer Sorting
        if ($this->answerSort === 'most_vote') {
            $answers = $answersQuery->sortByDesc('votes_count');
        } elseif ($this->answerSort === 'latest') {
            $answers = $answersQuery->sortByDesc('created_at');
        } elseif ($this->answerSort === 'accepted') {
            $answers = $answersQuery->sortByDesc('is_accepted');
        } elseif ($this->answerSort === 'saved') {
            $answers = $answersQuery->filter(fn ($a) => in_array((string) ($a->_id ?? $a->id), $this->savedAnswers, true));
        } elseif ($this->answerSort === 'followed') {
            $answers = $answersQuery->filter(fn ($a) => in_array((string) ($a->user_id ?? ''), $this->followingUsers, true));
        } else {
            // Default: Accepted first, then latest
            $answers = $answersQuery->sortByDesc(fn ($a) => [(bool) ($a->is_accepted ?? false), $a->created_at ?? 0]);
        }

        $allPopular = Cache::remember('qa_popular_questions_detail', 900, function () {
            return Question::query()
                ->whereNotIn('status', [0, '0', false])
                ->select(['_id', 'title', 'slug', 'answer_count', 'is_closed', 'created_at'])
                ->orderByDesc('views_count')
                ->limit(6)
                ->get();
        });

        $popularQuestions = $allPopular->reject(fn ($q) => (string) ($q->_id ?? $q->id) === (string) ($question->_id ?? $question->id))->take(5);

        $stats = Cache::remember('qa_sidebar_community_stats', 600, function () {
            $totalQuestions = Question::count();
            $totalAnswers = Answer::count();
            $totalUsers = User::count();
            $resolvedCount = Question::where('is_closed', true)->count();
            $resolvedRate = $totalQuestions > 0 ? round(($resolvedCount / $totalQuestions) * 100) : 92;

            return [
                'questions' => $totalQuestions,
                'answers' => $totalAnswers,
                'users' => $totalUsers,
                'resolved_rate' => $resolvedRate . '%',
            ];
        });

        return [
            'question' => $question,
            'answers' => $answers,
            'popularQuestions' => $popularQuestions,
            'stats' => $stats,
        ];
    }
}; ?>

<div class="min-h-screen bg-[#f9fafb] dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 py-6 sm:py-8 font-['DM_Sans',sans-serif]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Back Navigation Button -->
        <div class="mb-6">
            <a 
                href="{{ route('questions.index') }}" 
                wire:navigate 
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-700 dark:text-zinc-300 hover:text-[#198BEA] dark:hover:text-white hover:border-[#198BEA]/50 transition-all shadow-2xs group cursor-pointer"
            >
                <svg class="w-4 h-4 text-zinc-500 group-hover:text-[#198BEA] dark:text-zinc-400 dark:group-hover:text-white transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                <span>Back</span>
            </a>
        </div>

        <!-- Pending Approval Banner (from QDeatils.html) -->
        @if($question->status === 0 || $question->status === '0')
            <div x-data="{ open: true }" x-show="open" class="flex items-center gap-4 bg-amber-50/90 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700/60 border-l-4 border-l-amber-500 rounded-2xl p-4 sm:p-5 mb-6 shadow-xs transition-all">
                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/60 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="block text-sm sm:text-base font-bold text-amber-900 dark:text-amber-200">Your Question is not Get Approved yet Sorry!!!</span>
                    <span class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400">Only You can view this question</span>
                </div>
                <button @click="open = false" type="button" class="p-2 text-amber-700 dark:text-amber-300 hover:bg-amber-100/70 dark:hover:bg-amber-900/50 rounded-lg transition shrink-0" title="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <div class="flex flex-col lg:flex-row gap-8 items-start">
            
            <!-- MAIN CONTENT AREA (Left Flex-1) -->
            <div class="flex-1 min-w-0 space-y-6">
                
                <!-- Main Question Card -->
                <article class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-xs">
                    
                    <!-- Question Header & Author Info -->
                    <div class="flex items-center justify-between flex-wrap gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800/80 mb-5">
                        <div class="flex items-center gap-3">
                            @php
                                $author = $question->user;
                                $authorId = (string) ($author?->_id ?? $author?->id ?? $question->user_id ?? '');
                                $authorName = $author?->name ?? 'User';
                                $authorAvatar = $author?->avatar;
                                $currentUserId = (string) (auth()->user()?->_id ?? auth()->id() ?? '');
                                $isFollowingAuthor = $authorId !== '' && in_array($authorId, $followingUsers, true);
                                $isOwner = $currentUserId !== '' && $currentUserId === $authorId;
                            @endphp

                            <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 ring-2 ring-[#198BEA]/20">
                                <img src="{{ $authorAvatar }}" alt="{{ $authorName }}" class="w-full h-full object-cover">
                            </div>

                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                        {{ $authorName }}
                                    </h4>
                                    @auth
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
                                    @else
                                        @if($authorId !== '')
                                            <button 
                                                @click="$dispatch('open-auth-modal', 'Follow Authors')"
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
                                <p class="text-xs text-text-secondary dark:text-zinc-400 mt-0.5">
                                    Asked {{ $question->created_at ? $question->created_at->diffForHumans() : 'recently' }}
                                </p>
                            </div>
                        </div>

                        <!-- Status Badges (Solved / Bounty) -->
                        <div class="flex items-center gap-2">
                            @if($question->is_closed)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 rounded-full text-xs font-bold border border-emerald-200 dark:border-emerald-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Solved
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Question Title -->
                    <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white leading-tight mb-4">
                        {{ $question->title }}
                    </h1>

                    <!-- Question Body (Rich HTML format) -->
                    <div class="qa-rich-content text-zinc-800 dark:text-zinc-200 mb-6">
                        {!! $question->description !!}
                    </div>

                    <!-- Thumbnail Image (if present) -->
                    @if(!empty($question->thumbnail))
                        @php
                            $thumbUrl = Str::startsWith($question->thumbnail, ['http://', 'https://']) 
                                ? $question->thumbnail 
                                : (Str::startsWith($question->thumbnail, ['storage/', '/storage/'])
                                    ? asset($question->thumbnail)
                                    : asset('storage/' . $question->thumbnail));
                        @endphp
                        <div class="mb-6 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 max-w-xl bg-zinc-100 dark:bg-zinc-800">
                            <img src="{{ $thumbUrl }}" alt="{{ $question->title }}" class="w-full max-h-80 object-cover" loading="lazy">
                        </div>
                    @endif

                    <!-- Question Tags -->
                    @php
                        $tags = $question->tags ?? $question->category ?? [];
                        if (is_string($tags)) {
                            $tags = array_map('trim', explode(',', $tags));
                        }
                    @endphp
                    @if(!empty($tags))
                        <div class="flex flex-wrap gap-2 mb-6">
                            @foreach($tags as $tag)
                                @if(trim($tag) !== '')
                                    <span class="px-3 py-1 rounded-md text-xs font-semibold bg-[#eaf5ff] text-[#198BEA] dark:bg-sky-950/60 dark:text-sky-300 select-none">
                                        {{ trim($tag) }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <!-- Stats Bar (Matching Design Template: 💬 Answers, 👁 Views, ↑ Votes, 🕒 Time) -->
                    <div class="flex items-center gap-4 sm:gap-6 py-2.5 my-3.5 border-t border-b border-zinc-200/80 dark:border-zinc-800 text-[13px] text-zinc-600 dark:text-zinc-300 font-medium flex-wrap">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <span><strong class="font-bold text-zinc-900 dark:text-white">{{ $question->answer_count ?? 0 }}</strong> Answers</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
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
                            <svg class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>{{ $question->created_at ? $question->created_at->diffForHumans() : 'Recent' }}</span>
                        </span>
                    </div>

                    <!-- Bottom Action Buttons (Upvote, Downvote, Follow, Save, Share, Edit, Delete) -->
                    <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                            @auth
                                <!-- Upvote -->
                                <button 
                                    wire:click="toggleVote('upvote')"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $userQuestionVote === 'upvote' ? 'border border-[#198BEA] text-[#198BEA] bg-[#f0f7ff] hover:bg-[#e0f0fe] dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-600' : 'text-zinc-700 dark:text-zinc-200 hover:text-[#198BEA] hover:bg-sky-50/60 dark:hover:bg-zinc-800' }}"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                                    </svg>
                                    <span>Upvote</span>
                                </button>

                                <!-- Downvote -->
                                <button 
                                    wire:click="toggleVote('downvote')"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer {{ $userQuestionVote === 'downvote' ? 'border border-rose-400 text-rose-600 bg-rose-50 hover:bg-rose-100/70 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-500' : 'text-zinc-700 dark:text-zinc-200 hover:text-rose-600 hover:bg-rose-50/60 dark:hover:bg-zinc-800' }}"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76 1.04m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/>
                                    </svg>
                                    <span>Downvote</span>
                                </button>

                                <!-- Follow Question -->
                                @php
                                    $isFollowingQuestion = in_array($question->id, $followingQuestions, true);
                                    $followCount = $question->followCount();
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
                                    $isSaved = in_array($question->id, $savedQuestions, true);
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
                                    <span>{{ $question->followCount() }} Follow</span>
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

                        <!-- Right Actions: Owner Actions (Edit & Delete Question) -->
                        @auth
                            @if($isOwner)
                                <div class="flex items-center gap-2">
                                    <!-- Edit Button (Opens Question Edit Modal) -->
                                    <button 
                                        wire:click="openEditModal('{{ $question->id }}')"
                                        wire:target="openEditModal('{{ $question->id }}')"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold text-zinc-700 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-800 hover:text-[#198BEA] hover:border-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40 transition-all cursor-pointer"
                                        title="Edit question"
                                    >
                                        <svg wire:loading wire:target="openEditModal('{{ $question->id }}')" class="animate-spin w-3.5 h-3.5 text-[#198BEA]" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg wire:loading.remove wire:target="openEditModal('{{ $question->id }}')" class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        <span>Edit</span>
                                    </button>

                                    <!-- Delete Button -->
                                    <button 
                                        wire:click="deleteQuestion"
                                        wire:confirm="Are you sure you want to delete this question? All answers will be permanently removed. This action cannot be undone."
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-all cursor-pointer"
                                        title="Delete question"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                        </svg>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            @endif
                        @endauth
                    </div>
                </article>

                <!-- Post Your Answer Card (qa-add-answer matching QDeatils.html) -->
                <div 
                    id="write-answer" 
                    x-data="{
                        init() {
                            if (window.location.hash === '#write-answer') {
                                this.$nextTick(() => {
                                    const el = document.getElementById('answer-textarea');
                                    if (el) {
                                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        el.focus();
                                    }
                                });
                            }
                        }
                    }"
                    class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 sm:p-7 shadow-xs space-y-4"
                >
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#198BEA]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        <span>Add your answer</span>
                    </h3>

                    @auth
                        <div class="flex items-center gap-2.5">
                            <img src="{{ auth()->user()->avatar }}" alt="{{ auth()->user()->name }}" class="w-8 h-8 rounded-full object-cover ring-1 ring-[#198BEA]/20">
                            <span class="text-xs font-bold text-zinc-900 dark:text-white">{{ auth()->user()->name }}</span>
                        </div>
                    @endauth
                    <div class="relative">
                        <textarea 
                            wire:model="newAnswer" 
                            rows="6"
                            placeholder="Write your answer here. Be detailed and include explanations or code examples when possible..."
                            class="w-full px-4 py-3 bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 rounded-xl text-sm placeholder-zinc-400 text-zinc-900 dark:text-zinc-100 focus:outline-hidden focus:border-[#198BEA] focus:ring-2 focus:ring-[#198BEA]/15 transition-all resize-y min-h-[160px]"
                        ></textarea>
                    </div>

                    @error('newAnswer')
                        <p class="text-xs text-rose-500 font-medium">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center justify-between flex-wrap gap-3 pt-1">
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">
                            Be respectful and constructive. Support your answer with code or references.
                        </span>

                        @guest
                            <button 
                                @click="$dispatch('open-auth-modal', 'Answer a Question')" 
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                </svg>
                                <span>Post Answer</span>
                            </button>
                        @else
                            <button 
                                wire:click="submitAnswer" 
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#198BEA] hover:bg-[#1476c9] active:bg-[#0a5f9e] text-white text-sm font-semibold rounded-xl shadow-md shadow-sky-500/20 hover:shadow-lg transition-all cursor-pointer active:scale-98"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                </svg>
                                <span>Post Answer</span>
                            </button>
                        @endguest
                    </div>
                </div>

                <!-- Answers Section (qa-answers-section matching QDeatils.html) -->
                <div class="space-y-4 pt-2">
                    
                    <!-- Answers Header with Sort Tabs (Matching QDeatils.html) -->
                    <div class="flex items-center justify-between flex-wrap gap-3 pb-1">
                        <div class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white">
                            <span class="text-[#198BEA] font-extrabold">{{ count($answers) }}</span> Total Answers
                        </div>

                        <div class="flex items-center gap-1 sm:gap-1.5 flex-wrap">
                            <span class="text-[11px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mr-1">Sort by</span>
                            @foreach(['default' => 'Default', 'most_vote' => 'Most Vote', 'latest' => 'Latest', 'accepted' => 'Accepted', 'saved' => 'Saved', 'followed' => 'Followed'] as $sKey => $sLabel)
                                <button 
                                    wire:click="$set('answerSort', '{{ $sKey }}')"
                                    type="button"
                                    class="px-3 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer {{ $answerSort === $sKey ? 'bg-[#eaf5ff] text-[#198BEA] dark:bg-sky-950/60 dark:text-sky-300' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"
                                >
                                    {{ $sLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @forelse($answers as $ans)
                        <x-question.answer-card 
                            :ans="$ans" 
                            :question="$question"
                            :voted-answers="$votedAnswers"
                            :saved-answers="$savedAnswers"
                            :following-users="$followingUsers"
                            :following-answers="$followingAnswers"
                            :editing-answer-id="$editingAnswerId"
                            :editing-answer-content="$editingAnswerContent"
                        />
                    @empty
                        <div class="bg-white dark:bg-zinc-900 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl p-10 text-center text-zinc-400 text-sm">
                            <svg class="w-12 h-12 mx-auto mb-3 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <p class="font-medium text-zinc-600 dark:text-zinc-300">No answers yet.</p>
                            <p class="text-xs text-zinc-400 mt-1">Be the first to share knowledge and answer this question!</p>
                        </div>
                    @endforelse
                </div>

            </div>

            <!-- RIGHT SIDEBAR (320px ON DESKTOP - Widgets matching QDeatils.html) -->
            <aside class="w-full lg:w-[320px] lg:max-w-[320px] shrink-0 space-y-5">
                
                <!-- Ask Question CTA Box -->
                <div class="bg-gradient-to-br from-[#198BEA] to-sky-600 text-white rounded-2xl p-6 shadow-md shadow-sky-500/20 space-y-3">
                    <h3 class="text-base font-bold">Have a similar question?</h3>
                    <p class="text-xs text-sky-100 leading-relaxed">
                        Join discussions and get answers from scholars, researchers, and developers worldwide.
                    </p>
                    <a 
                        href="{{ route('questions.index') }}" 
                        wire:navigate
                        class="inline-block w-full text-center py-2.5 bg-white text-[#198BEA] hover:bg-sky-50 font-bold text-xs rounded-xl shadow-xs transition cursor-pointer"
                    >
                        Browse All Questions
                    </a>
                </div>

                <!-- 1. Community Stats Grid Widget (from QDeatils.html) -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs space-y-3.5">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span>Community Stats</span>
                    </h4>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div class="p-3 rounded-xl bg-[#eaf5ff] dark:bg-sky-950/40 text-center">
                            <div class="text-lg font-bold text-[#198BEA] dark:text-sky-300">{{ number_format($stats['questions'] ?? 0) }}</div>
                            <div class="text-[11px] font-medium text-[#198BEA]/70 dark:text-sky-400">Questions</div>
                        </div>
                        <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-center">
                            <div class="text-lg font-bold text-purple-600 dark:text-purple-300">{{ number_format($stats['answers'] ?? 0) }}</div>
                            <div class="text-[11px] font-medium text-purple-600/70 dark:text-purple-400">Answers</div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-center">
                            <div class="text-lg font-bold text-emerald-600 dark:text-emerald-300">{{ number_format($stats['users'] ?? 0) }}</div>
                            <div class="text-[11px] font-medium text-emerald-600/70 dark:text-emerald-400">Users</div>
                        </div>
                        <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-center">
                            <div class="text-lg font-bold text-amber-600 dark:text-amber-300">{{ $stats['resolved_rate'] ?? '92%' }}</div>
                            <div class="text-[11px] font-medium text-amber-600/70 dark:text-amber-400">Resolved</div>
                        </div>
                    </div>
                </div>

                <!-- 2. Popular / Related Questions Widget (from QDeatils.html) -->
                @if($popularQuestions->isNotEmpty())
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>Related Questions</span>
                        </h4>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($popularQuestions as $pop)
                                <a 
                                    href="{{ route('questions.show', $pop->slug ?: (string) $pop->_id) }}" 
                                    wire:navigate 
                                    class="block py-3 group first:pt-0 last:pb-0"
                                >
                                    <h5 class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 group-hover:text-[#198BEA] dark:group-hover:text-sky-400 transition-colors line-clamp-2 leading-snug">
                                        {{ $pop->title }}
                                    </h5>
                                    <div class="flex items-center gap-2 text-[11px] text-zinc-400 dark:text-zinc-500 mt-1">
                                        <span>{{ $pop->answer_count ?? 0 }} {{ ($pop->answer_count ?? 0) === 1 ? 'answer' : 'answers' }}</span>
                                        <span>·</span>
                                        @if($pop->is_closed)
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">Resolved</span>
                                        @else
                                            <span class="font-semibold text-amber-500">Active</span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- 3. Related Tags Widget (from QDeatils.html) -->
                @if(!empty($tags))
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs space-y-3.5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            <span>Related Tags</span>
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($tags as $t)
                                @if(trim($t) !== '')
                                    <a 
                                        href="{{ route('questions.index', ['tag' => trim($t)]) }}"
                                        wire:navigate
                                        class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-[#eaf5ff] hover:text-[#198BEA] dark:hover:bg-sky-950/60 dark:hover:text-sky-300 transition-colors"
                                    >
                                        {{ trim($t) }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- 4. Quick Links Widget (from QDeatils.html) -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-xs space-y-2.5">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Quick Links</span>
                    </h4>
                    <a href="{{ route('questions.index') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40 transition-all">
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        <span>All Questions</span>
                    </a>
                    <a href="{{ route('questions.index', ['sort' => 'unanswered']) }}" wire:navigate class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-zinc-600 dark:text-zinc-400 hover:text-[#198BEA] hover:bg-[#eaf5ff] dark:hover:bg-sky-950/40 transition-all">
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-[#198BEA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Unanswered Questions</span>
                    </a>
                </div>

            </aside>

        </div>
    </div>

    <!-- Auth Prompt Modal -->
    <x-modals.auth />

    <!-- Edit Question Modal -->
    <x-question.edit-question :available-skills="$availableSkills" />
</div>
