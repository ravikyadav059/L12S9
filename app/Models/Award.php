<?php

namespace App\Models;

use App\Traits\DetectsSpam;
use Database\Factories\AwardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Award extends Model
{
    use DetectsSpam;

    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'award';

    protected $table = 'award';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'award_year',
        'award_name',
        'award_by',
        'status',
        'user_id',
        'description',
        'post_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    /**
     * The model attributes to inspect during spam detection.
     *
     * @var array<int, string>
     */
    protected array $spamCheckAttributes = [
        'award_name',
        'award_by',
        'description',
    ];

    /**
     * Scope to retrieve active award records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    /**
     * Get the user that owns the award.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /**
     * Bootstrap model events to manage Redis cache invalidation.
     */
    protected static function booted(): void
    {
        static::saved(function (Award $award) {
            if (! empty($award->user_id)) {
                static::forgetUserCache((string) $award->user_id);
            }
        });

        static::deleted(function (Award $award) {
            if (! empty($award->user_id)) {
                static::forgetUserCache((string) $award->user_id);
            }
        });
    }

    /**
     * Invalidate Redis cache for a user's awards.
     */
    public static function forgetUserCache(string|int|null $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forget("awards:user:{$userId}:active");
        Cache::forget("awards:user:{$userId}:all");
    }

    /**
     * Retrieve cached awards for a user using Redis and MongoDB compound index.
     * Index utilized: {"user_id": 1, "status": 1, "award_year": -1}
     *
     * @return list<array<string, mixed>>
     */
    public static function getCachedForUser(string $userId, bool $activeOnly = true): array
    {
        $cacheKey = 'awards:user:'.$userId.':'.($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 86400, function () use ($userId, $activeOnly) {
            $reviewerProfile = ReviewerProfile::where('user_id', $userId)->first();
            $awardIds = (array) ($reviewerProfile->award ?? []);

            $query = static::query();
            if (! empty($awardIds)) {
                $query->where(function ($q) use ($awardIds, $userId) {
                    $q->whereIn('_id', $awardIds)
                        ->orWhere('user_id', $userId);
                });
            } else {
                $query->where('user_id', $userId);
            }

            if ($activeOnly) {
                $query->whereIn('status', [1, '1', true]);
            }

            // High-scale index optimized sort & projection:
            // Matches compound index: {"user_id": 1, "status": 1, "award_year": -1}
            $records = $query->orderBy('award_year', 'desc')
                ->orderBy('_id', 'desc')
                ->select(['_id', 'user_id', 'award_name', 'award_by', 'award_year', 'description', 'status'])
                ->get();

            $formatted = [];
            foreach ($records as $award) {
                $statusVal = (int) ($award->status ?? 1);
                $formatted[] = [
                    'id' => (string) $award->_id,
                    'award_name' => (string) ($award->award_name ?? ''),
                    'award_by' => (string) ($award->award_by ?? ''),
                    'award_year' => (string) ($award->award_year ?? ''),
                    'description' => (string) ($award->description ?? ''),
                    'status' => $statusVal === 1 ? 1 : 0,
                ];
            }

            return $formatted;
        });
    }
}
