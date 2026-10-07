<?php

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\MorphTo;

class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'votes';

    protected $table = 'votes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'question_id',
        'answer_id',
        'votable_id',
        'votable_type',
        'vote_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * User who cast the vote.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the question that was voted on.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /**
     * Get the answer that was voted on.
     */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'answer_id');
    }

    /**
     * Get the parent votable model (question or answer).
     */
    public function votable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Cast, switch, or remove a vote on a question.
     *
     * @return array{action: string, vote_type: ?string, delta: int, message: string, toast_type: string}
     */
    public static function toggleQuestionVote(string $userId, string|Question $questionOrId, string $type = 'upvote'): array
    {
        $type = ($type === 'downvote') ? 'downvote' : 'upvote';

        $question = $questionOrId instanceof Question
            ? $questionOrId
            : Question::query()
                ->where('_id', $questionOrId)
                ->orWhere('id', $questionOrId)
                ->first();

        if (! $question) {
            return [
                'action' => 'not_found',
                'vote_type' => null,
                'delta' => 0,
                'message' => 'Question not found.',
                'toast_type' => 'error',
            ];
        }

        $questionId = (string) ($question->_id ?? $question->id);

        $existingVote = static::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($questionId) {
                $q->where('question_id', $questionId)
                    ->orWhere('votable_id', $questionId);
            })
            ->first();

        $currentType = $existingVote ? (string) $existingVote->vote_type : null;

        if ($currentType === $type) {
            $existingVote->delete();
            $msg = ($type === 'upvote') ? 'Upvote removed' : 'Downvote removed';
            $toastType = 'info';
            $action = 'removed';
            $newVoteType = null;
        } else {
            if ($existingVote) {
                $existingVote->update([
                    'vote_type' => $type,
                    'votable_type' => 'question',
                    'question_id' => $questionId,
                    'answer_id' => null,
                ]);
                $action = 'switched';
            } else {
                static::create([
                    'user_id' => $userId,
                    'question_id' => $questionId,
                    'answer_id' => null,
                    'votable_id' => $questionId,
                    'votable_type' => 'question',
                    'vote_type' => $type,
                ]);
                $action = 'voted';
            }

            $msg = ($type === 'upvote') ? 'Question upvoted! 👍' : 'Question downvoted';
            $toastType = ($type === 'upvote') ? 'success' : 'warning';
            $newVoteType = $type;
        }

        // Recalculate actual votes_count directly from the votes collection
        $newVotesCount = static::recalculateQuestionVotes($question);

        return [
            'action' => $action,
            'vote_type' => $newVoteType,
            'votes_count' => $newVotesCount,
            'message' => $msg,
            'toast_type' => $toastType,
        ];
    }

    /**
     * Recalculate and update the votes_count on a question directly from the votes collection.
     */
    public static function recalculateQuestionVotes(string|Question $questionOrId): int
    {
        $question = $questionOrId instanceof Question
            ? $questionOrId
            : Question::query()->where('_id', $questionOrId)->orWhere('id', $questionOrId)->first();

        if (! $question) {
            return 0;
        }

        $questionId = (string) ($question->_id ?? $question->id);

        $upvotes = static::query()
            ->where(function ($q) use ($questionId) {
                $q->where('question_id', $questionId)->orWhere('votable_id', $questionId);
            })
            ->where('vote_type', 'upvote')
            ->count();

        $downvotes = static::query()
            ->where(function ($q) use ($questionId) {
                $q->where('question_id', $questionId)->orWhere('votable_id', $questionId);
            })
            ->where('vote_type', 'downvote')
            ->count();

        $actualVotesCount = max(0, $upvotes - $downvotes);
        $question->update([
            'votes_count' => $actualVotesCount,
            'upvotes_count' => $upvotes,
            'downvotes_count' => $downvotes,
        ]);

        return $actualVotesCount;
    }

    /**
     * Cast, switch, or remove a vote on an answer.
     *
     * @return array{action: string, vote_type: ?string, delta: int, message: string, toast_type: string}
     */
    public static function toggleAnswerVote(string $userId, string|Answer $answerOrId, string $type = 'upvote'): array
    {
        $type = ($type === 'downvote') ? 'downvote' : 'upvote';

        $answer = $answerOrId instanceof Answer
            ? $answerOrId
            : Answer::query()
                ->where('_id', $answerOrId)
                ->orWhere('id', $answerOrId)
                ->first();

        if (! $answer) {
            return [
                'action' => 'not_found',
                'vote_type' => null,
                'delta' => 0,
                'message' => 'Answer not found.',
                'toast_type' => 'error',
            ];
        }

        $answerId = (string) ($answer->_id ?? $answer->id);

        $existingVote = static::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($answerId) {
                $q->where('answer_id', $answerId)
                    ->orWhere('votable_id', $answerId);
            })
            ->first();

        $currentType = $existingVote ? (string) $existingVote->vote_type : null;

        if ($currentType === $type) {
            $existingVote->delete();
            $delta = ($type === 'upvote') ? -1 : 1;
            $msg = ($type === 'upvote') ? 'Upvote removed' : 'Downvote removed';
            $toastType = 'info';
            $action = 'removed';
            $newVoteType = null;
        } else {
            $delta = 0;
            if ($currentType === 'upvote') {
                $delta -= 1;
            } elseif ($currentType === 'downvote') {
                $delta += 1;
            }
            $delta += ($type === 'upvote') ? 1 : -1;

            if ($existingVote) {
                $existingVote->update([
                    'vote_type' => $type,
                    'votable_type' => 'answer',
                    'answer_id' => $answerId,
                ]);
                $action = 'switched';
            } else {
                static::create([
                    'user_id' => $userId,
                    'question_id' => $answer->question_id ?? null,
                    'answer_id' => $answerId,
                    'votable_id' => $answerId,
                    'votable_type' => 'answer',
                    'vote_type' => $type,
                ]);
                $action = 'voted';
            }

            $msg = ($type === 'upvote') ? 'Answer upvoted! 👍' : 'Answer downvoted';
            $toastType = ($type === 'upvote') ? 'success' : 'warning';
            $newVoteType = $type;
        }

        $currentVotes = (int) ($answer->votes_count ?? 0);
        $answer->update(['votes_count' => max(0, $currentVotes + $delta)]);

        return [
            'action' => $action,
            'vote_type' => $newVoteType,
            'delta' => $delta,
            'message' => $msg,
            'toast_type' => $toastType,
        ];
    }
}
