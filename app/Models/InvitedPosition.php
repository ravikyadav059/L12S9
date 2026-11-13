<?php

namespace App\Models;

use App\Traits\DetectsSpam;
use Database\Factories\InvitedPositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class InvitedPosition extends Model
{
    use DetectsSpam;

    /** @use HasFactory<InvitedPositionFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'invited_position';

    protected $table = 'invited_position';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role',
        'join_date',
        'end_date',
        'continue_invited_position',
        'webiste_url',
        'organization_id',
        'country',
        'user_id',
        'status',
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
            'continue_invited_position' => 'integer',
        ];
    }

    /**
     * The model attributes to inspect during spam detection.
     *
     * @var array<int, string>
     */
    protected array $spamCheckAttributes = [
        'role',
    ];

    /**
     * Scope to retrieve active invited position records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    /**
     * Check if the invited position is continuing / ongoing.
     */
    public function isContinuing(): bool
    {
        return in_array($this->continue_invited_position, [1, '1', true], true)
            || empty($this->end_date);
    }

    /**
     * Accessor to seamlessly support both website_url and database-native webiste_url typo.
     */
    public function getWebsiteUrlAttribute(): ?string
    {
        return $this->attributes['website_url'] ?? $this->attributes['webiste_url'] ?? null;
    }

    /**
     * Mutator to keep both attributes synchronized.
     */
    public function setWebsiteUrlAttribute(mixed $value): void
    {
        $this->attributes['website_url'] = $value;
        $this->attributes['webiste_url'] = $value;
    }

    /**
     * Get the organization associated with this invited position.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', '_id');
    }

    /**
     * Alias for organization relationship.
     */
    public function institution(): BelongsTo
    {
        return $this->organization();
    }

    /**
     * Get the user that owns the invited position record.
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
        static::saved(function (InvitedPosition $position) {
            if (! empty($position->user_id)) {
                static::forgetUserCache((string) $position->user_id);
            }
        });

        static::deleted(function (InvitedPosition $position) {
            if (! empty($position->user_id)) {
                static::forgetUserCache((string) $position->user_id);
            }
        });
    }

    /**
     * Invalidate Redis cache for a user's invited position records.
     */
    public static function forgetUserCache(string|int|null $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forget("invited_position:user:{$userId}:active");
        Cache::forget("invited_position:user:{$userId}:all");
    }

    /**
     * Retrieve cached invited position records for a user using Redis and MongoDB compound index.
     * Index utilized: {"user_id": 1, "status": 1}
     *
     * @return list<array<string, mixed>>
     */
    public static function getCachedForUser(string $userId, bool $activeOnly = true): array
    {
        $cacheKey = 'invited_position:user:'.$userId.':'.($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 86400, function () use ($userId, $activeOnly) {
            $reviewerProfile = ReviewerProfile::where('user_id', $userId)->first();
            $positionIds = array_values(array_unique(array_filter(array_map('strval', (array) ($reviewerProfile->invited_position ?? [])))));

            if (empty($positionIds)) {
                return [];
            }

            $query = static::query()->whereIn('_id', $positionIds);

            if ($activeOnly) {
                $query->whereIn('status', [1, '1', true]);
            }

            // High-scale index optimized sort & eager-load organization
            $records = $query->with('organization')
                ->orderBy('_id', 'desc')
                ->get();

            $countriesMap = collect(Country::getCachedAll())->keyBy('id');

            $formatted = [];
            foreach ($records as $p) {
                $statusVal = (int) ($p->status ?? 1);
                $isContinuing = $p->isContinuing();
                $cVal = (string) ($p->country ?? '');
                $countryName = '';
                if ($cVal !== '') {
                    if ($countriesMap->has($cVal)) {
                        $countryName = (string) ($countriesMap[$cVal]['name'] ?? '');
                    } elseif (! is_numeric($cVal)) {
                        $countryName = $cVal;
                    }
                }

                $formatted[] = [
                    'id' => (string) $p->_id,
                    'role' => (string) ($p->role ?? ''),
                    'organization_id' => (string) ($p->organization_id ?? ''),
                    'organization_name' => (string) ($p->organization?->organization_name ?? ''),
                    'join_date' => (string) ($p->join_date ?? ''),
                    'end_date' => (string) ($p->end_date ?? ''),
                    'continue_invited_position' => $isContinuing ? 1 : 0,
                    'is_continuing' => $isContinuing,
                    'country' => $cVal,
                    'country_name' => $countryName,
                    'webiste_url' => (string) ($p->webiste_url ?? $p->website_url ?? ''),
                    'website_url' => (string) ($p->website_url ?? $p->webiste_url ?? ''),
                    'status' => $statusVal === 1 ? 1 : 0,
                ];
            }

            return $formatted;
        });
    }
}
