<?php

namespace App\Models;

use App\Traits\DetectsSpam;
use Database\Factories\PhdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Phd extends Model
{
    use DetectsSpam;

    /** @use HasFactory<PhdFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'phd';

    protected $table = 'phd';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'phd_name',
        'phd_title',
        'thesis_type',
        'research_area',
        'institution_id',
        'status',
        'phd_status',
        'user_id',
    ];

    /**
     * Get the attributes that should be cast.
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
        'phd_name',
        'phd_title',
        'research_area',
    ];

    /**
     * Scope to retrieve active phd records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    /**
     * Scope to retrieve completed phd records.
     */
    public function scopeCompleted($query)
    {
        return $query->where('phd_status', 'Completed');
    }

    /**
     * Scope to retrieve ongoing phd records.
     */
    public function scopeOngoing($query)
    {
        return $query->where('phd_status', 'Ongoing');
    }

    /**
     * Get the institution / organization associated with this PhD thesis.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'institution_id', '_id');
    }

    /**
     * Alias for institution relationship.
     */
    public function organization(): BelongsTo
    {
        return $this->institution();
    }

    /**
     * Get the user that owns the PhD thesis record.
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
        static::saved(function (Phd $phd) {
            if (! empty($phd->user_id)) {
                static::forgetUserCache((string) $phd->user_id);
            }
        });

        static::deleted(function (Phd $phd) {
            if (! empty($phd->user_id)) {
                static::forgetUserCache((string) $phd->user_id);
            }
        });
    }

    /**
     * Invalidate Redis cache for a user's PhD / thesis guided records.
     */
    public static function forgetUserCache(string|int|null $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forget("phd:user:{$userId}:active");
        Cache::forget("phd:user:{$userId}:all");
    }

    /**
     * Retrieve cached PhD / thesis guided records for a user using Redis and MongoDB compound index.
     * Index utilized: {"user_id": 1, "status": 1}
     *
     * @return list<array<string, mixed>>
     */
    public static function getCachedForUser(string $userId, bool $activeOnly = true): array
    {
        $cacheKey = 'phd:user:'.$userId.':'.($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 86400, function () use ($userId, $activeOnly) {
            $reviewerProfile = ReviewerProfile::where('user_id', $userId)->first();
            $phdIds = (array) ($reviewerProfile->phd ?? []);

            $query = static::query();
            if (! empty($phdIds)) {
                $query->where(function ($q) use ($phdIds, $userId) {
                    $q->whereIn('_id', $phdIds)
                        ->orWhere('user_id', $userId);
                });
            } else {
                $query->where('user_id', $userId);
            }

            if ($activeOnly) {
                $query->whereIn('status', [1, '1', true]);
            }

            // High-scale index optimized sort & eager-load institution
            $records = $query->with('institution')
                ->orderBy('_id', 'desc')
                ->get();

            $formatted = [];
            foreach ($records as $phd) {
                $statusVal = (int) ($phd->status ?? 1);
                $formatted[] = [
                    'id' => (string) $phd->_id,
                    'phd_name' => (string) ($phd->phd_name ?? ''),
                    'phd_title' => (string) ($phd->phd_title ?? ''),
                    'thesis_type' => (string) ($phd->thesis_type ?? ''),
                    'research_area' => (string) ($phd->research_area ?? ''),
                    'institution_id' => (string) ($phd->institution_id ?? ''),
                    'institution_name' => (string) ($phd->institution?->organization_name ?? ''),
                    'phd_status' => (string) ($phd->phd_status ?? 'Ongoing'),
                    'status' => $statusVal === 1 ? 1 : 0,
                ];
            }

            return $formatted;
        });
    }
}
