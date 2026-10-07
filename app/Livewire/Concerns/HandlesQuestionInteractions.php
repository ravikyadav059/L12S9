<?php

namespace App\Livewire\Concerns;

use App\Models\Question;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

/**
 * Trait HandlesQuestionInteractions
 *
 * Centralized, reusable trait providing unified question interactions:
 * - Upvoting & Downvoting questions
 * - Bookmarking / Saving questions (persisted to question.save array in MongoDB & Redis Sets)
 * - Following & Unfollowing question authors (persisted to user.following / user.followers arrays in MongoDB & Redis Sets)
 * - Following & Unfollowing question notifications (persisted to question.follow array in MongoDB & Redis Sets)
 *
 * Used in:
 * - resources/views/livewire/question/index.blade.php (Questions Feed & Listing Page)
 * - resources/views/livewire/question/show.blade.php (Question Details & Answers Page)
 */
trait HandlesQuestionInteractions
{
    /**
     * User's active votes keyed by question ID ('up' or 'down').
     * Used in: index.blade.php, show.blade.php
     */
    public array $votedQuestions = [];

    /**
     * Question IDs saved/bookmarked by the user.
     * Used in: index.blade.php, show.blade.php, question-filter.blade.php
     */
    public array $savedQuestions = [];

    /**
     * Author user IDs that the authenticated user follows.
     * Used in: index.blade.php, sidebar.blade.php, show.blade.php
     */
    public array $followingUsers = [];

    /**
     * Question IDs that the user is following for updates.
     * Used in: index.blade.php, show.blade.php
     */
    public array $followingQuestions = [];

    /**
     * Active vote state on the currently opened question ('up', 'down', or null).
     * Used in: show.blade.php
     */
    public ?string $userQuestionVote = null;

    /**
     * Initialize and synchronize all question interactions from Redis, MongoDB, and session.
     *
     * Used in:
     * - resources/views/livewire/question/index.blade.php (in mount())
     * - resources/views/livewire/question/show.blade.php (in mount())
     *
     * @param  string|null  $currentQuestionId  Optional question ID for single question detail view
     */
    public function initializeQuestionInteractions(?string $currentQuestionId = null): void
    {
        $this->followingQuestions = session('qa_following_questions', []);

        if (Auth::check()) {
            /** @var User $authUser */
            $authUser = Auth::user();
            $userId = (string) ($authUser->_id ?? Auth::id());

            // 1. Fetch user question votes with Redis Hash acceleration
            $cachedVotes = $this->getRedisUserHash("user:{$userId}:votes");
            if ($cachedVotes === null) {
                $this->votedQuestions = Vote::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('question_id')
                    ->pluck('vote_type', 'question_id')
                    ->toArray();
                $this->syncRedisUserHash("user:{$userId}:votes", $this->votedQuestions);
            } else {
                $this->votedQuestions = $cachedVotes;
            }
            session(['qa_voted_questions' => $this->votedQuestions]);

            // 2. Fetch author following array from user document (with Redis acceleration)
            $rawFollowing = $authUser->following ?? [];
            $this->followingUsers = is_array($rawFollowing) ? array_values(array_map('strval', $rawFollowing)) : [];
            session(['qa_following_users' => $this->followingUsers]);

            // 3. Fetch saved questions from MongoDB and sync with Redis
            $dbSaved = Question::query()
                ->where(function ($q) use ($userId) {
                    $q->where('save', $userId)
                        ->orWhere('save', (int) $userId);
                })
                ->get(['_id', 'id']);

            $savedIds = [];
            foreach ($dbSaved as $sq) {
                $savedIds[] = (string) ($sq->_id ?? '');
                $savedIds[] = (string) ($sq->id ?? '');
            }
            $this->savedQuestions = array_values(array_unique(array_filter($savedIds)));
            $this->syncRedisUserSet("user:{$userId}:saved_questions", $this->savedQuestions);
            session(['qa_saved_questions' => $this->savedQuestions]);

            // 4. Fetch followed questions from MongoDB and sync with Redis
            $dbFollowed = Question::query()
                ->where(function ($q) use ($userId) {
                    $q->where('follow', $userId)
                        ->orWhere('follow', (int) $userId);
                })
                ->get(['_id', 'id']);

            $followedIds = [];
            foreach ($dbFollowed as $fq) {
                $followedIds[] = (string) ($fq->_id ?? '');
                $followedIds[] = (string) ($fq->id ?? '');
            }
            $this->followingQuestions = array_values(array_unique(array_filter($followedIds)));
            $this->syncRedisUserSet("user:{$userId}:following_questions", $this->followingQuestions);
            session(['qa_following_questions' => $this->followingQuestions]);

            // 5. Resolve current question vote if question ID is supplied
            if ($currentQuestionId) {
                $vote = Vote::query()
                    ->where('user_id', $userId)
                    ->where(function ($q) use ($currentQuestionId) {
                        $q->where('question_id', $currentQuestionId)
                            ->orWhere('votable_id', $currentQuestionId);
                    })
                    ->first();
                $this->userQuestionVote = $vote ? (string) $vote->vote_type : null;
            }
        } else {
            $this->votedQuestions = session('qa_voted_questions', []);
            $this->followingUsers = session('qa_following_users', []);
            $this->savedQuestions = session('qa_saved_questions', []);
            $this->userQuestionVote = null;
        }
    }

    /**
     * Upvote or Downvote a question with atomic MongoDB counter updates.
     *
     * Supports both:
     * - 2 args (from feed card): toggleVote($questionId, 'upvote')
     * - 1 arg (from detail page): toggleVote('upvote') where question is inferred from $this->slug
     *
     * Used in:
     * - resources/views/livewire/question/index.blade.php (feed cards upvote/downvote buttons)
     * - resources/views/livewire/question/show.blade.php (question detail upvote/downvote buttons)
     *
     * @param  string  $questionIdOrType  Question ID/slug OR vote type ('up', 'upvote', 'down', 'downvote')
     * @param  string|null  $type  Vote type if question ID was passed as first argument
     */
    public function toggleVote(string $questionIdOrType = 'up', ?string $type = null): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Vote');

            return;
        }

        if ($type === null) {
            $voteType = $questionIdOrType;
            $questionId = $this->slug ?? ($this->question?->_id ?? null);
        } else {
            $questionId = $questionIdOrType;
            $voteType = $type;
        }

        $question = Question::query()
            ->where('_id', $questionId)
            ->orWhere('slug', $questionId)
            ->first();

        if (! $question) {
            $this->dispatch('toast', message: 'Question not found.', type: 'error');

            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $result = Vote::toggleQuestionVote($userId, $question, $voteType);

        $qIdStr = (string) ($question->_id ?? $question->id);
        $redisVotesKey = "user:{$userId}:votes";

        if ($result['vote_type'] === null) {
            unset($this->votedQuestions[$qIdStr], $this->votedQuestions[$questionId]);
            $this->removeFromRedisUserHash($redisVotesKey, $qIdStr);
        } else {
            $this->votedQuestions[$qIdStr] = $result['vote_type'];
            $this->votedQuestions[$questionId] = $result['vote_type'];
            $this->setRedisUserHash($redisVotesKey, $qIdStr, (string) $result['vote_type']);
        }

        $this->userQuestionVote = $result['vote_type'];
        session(['qa_voted_questions' => $this->votedQuestions]);
        $this->dispatch('toast', message: $result['message'], type: $result['toast_type']);
    }

    /**
     * Save / Bookmark a question (stores user ID in question.save array in MongoDB).
     *
     * Used in:
     * - resources/views/livewire/question/index.blade.php (feed cards save button)
     * - resources/views/livewire/question/show.blade.php (question detail save button)
     *
     * @param  string  $questionId  Question ID or slug
     */
    public function toggleSave(string $questionId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Bookmark Questions');

            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $question = Question::query()
            ->where('_id', $questionId)
            ->orWhere('slug', $questionId)
            ->first();

        if (! $question) {
            return;
        }

        $isSaved = $question->toggleSave($userId);
        $qIdStr = (string) ($question->_id ?? $question->id);
        $redisKey = "user:{$userId}:saved_questions";

        if ($isSaved) {
            $this->savedQuestions = array_values(array_unique(array_merge($this->savedQuestions, [$qIdStr, (string) $question->id, $questionId])));
            $this->addToRedisUserSet($redisKey, $qIdStr);
            $msg = 'Question saved to bookmarks! 🔖';
            $toastType = 'success';
        } else {
            $this->savedQuestions = array_values(array_diff($this->savedQuestions, [$qIdStr, (string) $question->id, $questionId]));
            $this->removeFromRedisUserSet($redisKey, $qIdStr);
            $msg = 'Removed from bookmarks';
            $toastType = 'info';
        }

        session(['qa_saved_questions' => $this->savedQuestions]);
        $this->dispatch('toast', message: $msg, type: $toastType);
    }

    /**
     * Follow or Unfollow a question author (updates user.following and target.followers in MongoDB & Redis).
     *
     * Used in:
     * - resources/views/livewire/question/index.blade.php (author card header follow button)
     * - resources/views/components/question/sidebar.blade.php (most active scholars follow button)
     * - resources/views/livewire/question/show.blade.php (author follow button)
     *
     * @param  string  $userId  Author user ID
     * @param  string  $userName  Author display name for toast message
     */
    public function toggleFollowUser(string $userId, string $userName = 'user'): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Follow Scholars');

            return;
        }

        $authId = (string) (Auth::user()->_id ?? Auth::id());
        if ($authId === $userId) {
            $this->dispatch('toast', message: 'You cannot follow yourself.', type: 'info');

            return;
        }

        /** @var User|null $authUser */
        $authUser = User::find($authId);
        /** @var User|null $targetUser */
        $targetUser = User::find($userId);

        if (! $authUser || ! $targetUser) {
            $this->dispatch('toast', message: 'User not found.', type: 'error');

            return;
        }

        $nowFollowing = $authUser->toggleFollow($targetUser);
        $redisKey = "user:{$authId}:following_authors";

        $rawFollowing = $authUser->fresh()->following ?? [];
        $this->followingUsers = is_array($rawFollowing) ? array_values(array_map('strval', $rawFollowing)) : [];
        session(['qa_following_users' => $this->followingUsers]);

        if ($nowFollowing) {
            $this->addToRedisUserSet($redisKey, $userId);
            $this->dispatch('toast', message: "Now following {$userName}! ⭐", type: 'success');
        } else {
            $this->removeFromRedisUserSet($redisKey, $userId);
            $this->dispatch('toast', message: "Unfollowed {$userName}", type: 'info');
        }
    }

    /**
     * Follow or Unfollow updates on a specific question (stores user ID in question.follow array in MongoDB & Redis).
     *
     * Used in:
     * - resources/views/livewire/question/index.blade.php (bottom action follow question button)
     * - resources/views/livewire/question/show.blade.php (action follow button)
     *
     * @param  string  $questionId  Question ID or slug
     */
    public function toggleFollowQuestion(string $questionId): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-auth-alert', title: 'Connect to Scholar9 to Follow Questions');

            return;
        }

        $userId = (string) (Auth::user()->_id ?? Auth::id());
        $question = Question::query()
            ->where('_id', $questionId)
            ->orWhere('slug', $questionId)
            ->first();

        if (! $question) {
            return;
        }

        $isFollowed = $question->toggleFollow($userId);
        $qIdStr = (string) ($question->_id ?? $question->id);
        $redisKey = "user:{$userId}:following_questions";

        if ($isFollowed) {
            $this->followingQuestions = array_values(array_unique(array_merge($this->followingQuestions, [$qIdStr, (string) $question->id, $questionId])));
            $this->addToRedisUserSet($redisKey, $qIdStr);
            $msg = 'Following question updates! 🔔';
            $toastType = 'success';
        } else {
            $this->followingQuestions = array_values(array_diff($this->followingQuestions, [$qIdStr, (string) $question->id, $questionId]));
            $this->removeFromRedisUserSet($redisKey, $qIdStr);
            $msg = 'Unfollowed question';
            $toastType = 'info';
        }

        session(['qa_following_questions' => $this->followingQuestions]);
        $this->dispatch('toast', message: $msg, type: $toastType);
    }

    /**
     * Alias for toggleFollowQuestion for compatibility.
     *
     * Used in:
     * - resources/views/livewire/question/show.blade.php
     */
    public function toggleFollow(string $questionId): void
    {
        $this->toggleFollowQuestion($questionId);
    }

    /**
     * Fetch all items in a Redis Set, or null if Redis is unavailable or key does not exist.
     *
     * @return list<string>|null
     */
    protected function getRedisUserSet(string $key): ?array
    {
        try {
            if (Redis::exists($key)) {
                $members = Redis::smembers($key);

                return is_array($members) ? array_values(array_map('strval', $members)) : [];
            }
        } catch (\Throwable) {
            // Graceful fallback to database
        }

        return null;
    }

    /**
     * Synchronize and populate a Redis Set with 7-day TTL.
     *
     * @param  list<string>  $items
     */
    protected function syncRedisUserSet(string $key, array $items): void
    {
        try {
            Redis::del($key);
            if (! empty($items)) {
                Redis::sadd($key, ...array_values(array_map('strval', $items)));
                Redis::expire($key, 86400 * 7);
            }
        } catch (\Throwable) {
            // Graceful fallback
        }
    }

    /**
     * Add an item to a Redis Set.
     */
    protected function addToRedisUserSet(string $key, string $item): void
    {
        try {
            Redis::sadd($key, $item);
            Redis::expire($key, 86400 * 7);
        } catch (\Throwable) {
            // Graceful fallback
        }
    }

    /**
     * Remove an item from a Redis Set.
     */
    protected function removeFromRedisUserSet(string $key, string $item): void
    {
        try {
            Redis::srem($key, $item);
        } catch (\Throwable) {
            // Graceful fallback
        }
    }

    /**
     * Fetch all field-value pairs in a Redis Hash, or null if Redis is unavailable or key does not exist.
     *
     * @return array<string, string>|null
     */
    protected function getRedisUserHash(string $key): ?array
    {
        try {
            if (Redis::exists($key)) {
                $hash = Redis::hgetall($key);

                return is_array($hash) ? $hash : [];
            }
        } catch (\Throwable) {
            // Graceful fallback to database
        }

        return null;
    }

    /**
     * Synchronize and populate a Redis Hash with 7-day TTL.
     *
     * @param  array<string, string>  $data
     */
    protected function syncRedisUserHash(string $key, array $data): void
    {
        try {
            Redis::del($key);
            if (! empty($data)) {
                Redis::hmset($key, $data);
                Redis::expire($key, 86400 * 7);
            }
        } catch (\Throwable) {
            // Graceful fallback
        }
    }

    /**
     * Set a single field in a Redis Hash.
     */
    protected function setRedisUserHash(string $key, string $field, string $value): void
    {
        try {
            Redis::hset($key, $field, $value);
            Redis::expire($key, 86400 * 7);
        } catch (\Throwable) {
            // Graceful fallback
        }
    }

    /**
     * Remove a field from a Redis Hash.
     */
    protected function removeFromRedisUserHash(string $key, string $field): void
    {
        try {
            Redis::hdel($key, $field);
        } catch (\Throwable) {
            // Graceful fallback
        }
    }
}
