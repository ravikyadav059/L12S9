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
        'upvotes_count',
        'downvotes_count',
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
            'upvotes_count' => 'integer',
            'downvotes_count' => 'integer',
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

            if (static::getConnectionResolver() !== null) {
                try {
                    while (static::where('slug', $slug)->when($currentId, fn ($q) => $q->where('_id', '!=', $currentId))->exists()) {
                        $slug = "{$baseSlug}-{$count}";
                        $count++;
                    }
                } catch (\Throwable) {
                    // Fallback in case of disconnected environment or unit tests
                }
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

    /**
     * Check if a user has saved this question.
     */
    public function isSavedBy(string|User|null $user): bool
    {
        if (empty($user)) {
            return false;
        }

        $userId = $user instanceof User ? (string) ($user->_id ?? $user->id) : (string) $user;
        if ($userId === '') {
            return false;
        }

        $saved = is_array($this->save) ? array_map('strval', $this->save) : [];

        return in_array($userId, $saved, true);
    }

    /**
     * Toggle save status for a user.
     * Returns true if now saved, false if removed from saved.
     */
    public function toggleSave(string|User $user): bool
    {
        $userId = $user instanceof User ? (string) ($user->_id ?? $user->id) : (string) $user;
        if ($userId === '') {
            return false;
        }

        $saved = is_array($this->save) ? array_map('strval', $this->save) : [];

        if (in_array($userId, $saved, true)) {
            $this->save = array_values(array_diff($saved, [$userId]));
            $this->save();

            return false;
        }

        $saved[] = $userId;
        $this->save = array_values(array_unique($saved));
        $this->save();

        return true;
    }

    /**
     * Total saves count.
     */
    public function savedCount(): int
    {
        return is_array($this->save) ? count($this->save) : 0;
    }

    /**
     * Check if a user has followed this question.
     */
    public function isFollowedBy(string|User|null $user): bool
    {
        if (empty($user)) {
            return false;
        }

        $userId = $user instanceof User ? (string) ($user->_id ?? $user->id) : (string) $user;
        if ($userId === '') {
            return false;
        }

        $followed = is_array($this->follow) ? array_map('strval', $this->follow) : [];

        return in_array($userId, $followed, true);
    }

    /**
     * Toggle follow status for a user on this question.
     * Returns true if now following, false if unfollowed.
     */
    public function toggleFollow(string|User $user): bool
    {
        $userId = $user instanceof User ? (string) ($user->_id ?? $user->id) : (string) $user;
        if ($userId === '') {
            return false;
        }

        $followed = is_array($this->follow) ? array_map('strval', $this->follow) : [];

        if (in_array($userId, $followed, true)) {
            $this->follow = array_values(array_diff($followed, [$userId]));
            $this->save();

            return false;
        }

        $followed[] = $userId;
        $this->follow = array_values(array_unique($followed));
        $this->save();

        return true;
    }

    /**
     * Total follow count on the question.
     */
    public function followCount(): int
    {
        return is_array($this->follow) ? count($this->follow) : (int) ($this->follow ?? 0);
    }
}
