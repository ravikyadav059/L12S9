<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'question';

    protected $table = 'question';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'tags',
        'thumbnail',
        'views_count',
        'votes_count',
        'shared_count',
        'answer_count',
        'likes_count',
        'is_closed',
        'follow',
        'save',
        'is_reported',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
            'votes_count' => 'integer',
            'shared_count' => 'integer',
            'answer_count' => 'integer',
            'likes_count' => 'integer',
            'is_closed' => 'boolean',
            'is_reported' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Mutator for title attribute that automatically creates a slug.
     */
    public function setTitleAttribute(mixed $value): void
    {
        $this->attributes['title'] = $value;
        if (! empty($value)) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Answers relationship.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'question_id');
    }

    /**
     * Votes relationship.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'question_id');
    }
}
