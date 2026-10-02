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
            'is_accepted' => 'boolean',
            'status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
}
