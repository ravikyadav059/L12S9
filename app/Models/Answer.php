<?php

namespace App\Models;

use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'answers';

    protected $table = 'answers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'question_id',
        'user_id',
        'files',
        'content',
        'votes_count',
        'upvotes_count',
        'downvotes_count',
        'is_accepted',
        'status',
        'follow',
        'save',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
            'upvotes_count' => 'integer',
            'downvotes_count' => 'integer',
            'is_accepted' => 'boolean',
            'status' => 'integer',
        ];
    }

    /**
     * Accessor for upvotes_count.
     */
    public function getUpvotesCountAttribute(): int
    {
        if (isset($this->attributes['upvotes_count'])) {
            return (int) $this->attributes['upvotes_count'];
        }

        return (int) $this->votes()->where('vote_type', 'upvote')->count();
    }

    /**
     * Accessor for downvotes_count.
     */
    public function getDownvotesCountAttribute(): int
    {
        if (isset($this->attributes['downvotes_count'])) {
            return (int) $this->attributes['downvotes_count'];
        }

        return (int) $this->votes()->where('vote_type', 'downvote')->count();
    }

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Question relationship.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /**
     * Votes relationship.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'answer_id');
    }

    /**
     * Replies (Comments) relationship.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Replies::class, 'answer_id');
    }
}
