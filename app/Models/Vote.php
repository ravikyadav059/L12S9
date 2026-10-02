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
}
