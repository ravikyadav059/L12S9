<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use MongoDB\BSON\Regex;
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
        'status',
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
            'status' => 'integer',
            'is_closed' => 'boolean',
            'is_reported' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Mutator for title attribute that automatically creates a unique slug.
     */
    public function setTitleAttribute(mixed $value): void
    {
        $this->attributes['title'] = $value;
        if (! empty($value)) {
            $baseSlug = Str::slug($value);
            if (empty($baseSlug)) {
                $baseSlug = 'question-'.Str::random(6);
            }

            $slug = $baseSlug;
            $count = 1;
            $currentId = $this->attributes['_id'] ?? $this->attributes['id'] ?? null;

            while (static::where('slug', $slug)->when($currentId, fn ($q) => $q->where('_id', '!=', $currentId))->exists()) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }

            $this->attributes['slug'] = $slug;
        }
    }

    /**
     * Helper to verify if a question title is unique.
     */
    public static function isTitleUnique(?string $title, ?string $excludeId = null): bool
    {
        $cleanTitle = trim((string) $title);
        if ($cleanTitle === '') {
            return true;
        }

        $query = static::query()
            ->where('title', 'regex', new Regex('^'.preg_quote($cleanTitle, '/').'$', 'i'))
            ->whereNotIn('status', [0, '0', false]);

        if ($excludeId) {
            $query->where('_id', '!=', $excludeId);
        }

        return ! $query->exists();
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
