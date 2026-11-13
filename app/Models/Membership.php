<?php

namespace App\Models;

use App\Traits\DetectsSpam;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Membership extends Model
{
    use DetectsSpam;

    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'membership';

    protected $table = 'membership';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'membership_type',
        'join_date',
        'end_date',
        'continue_membership',
        'webiste_url',
        'organization_id',
        'user_id',
        'country',
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
            'continue_membership' => 'integer',
        ];
    }

    /**
     * The model attributes to inspect during spam detection.
     *
     * @var array<int, string>
     */
    protected array $spamCheckAttributes = [
        'membership_type',
    ];

    /**
     * Scope to retrieve active membership records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    /**
     * Check if membership is ongoing / continuing.
     */
    public function isContinuing(): bool
    {
        return ! empty($this->continue_membership);
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
     * Get the organization associated with this membership.
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
     * Get the user that owns the membership record.
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
        static::saved(function (Membership $membership) {
            if (! empty($membership->user_id)) {
                static::forgetUserCache((string) $membership->user_id);
            }
        });

        static::deleted(function (Membership $membership) {
            if (! empty($membership->user_id)) {
                static::forgetUserCache((string) $membership->user_id);
            }
        });
    }

    /**
     * Invalidate Redis cache for a user's membership records.
     */
    public static function forgetUserCache(string|int|null $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forget("membership:user:{$userId}:active");
        Cache::forget("membership:user:{$userId}:all");
    }

    /**
     * Retrieve cached membership records for a user using Redis and MongoDB compound index.
     * Index utilized: {"user_id": 1, "status": 1}
     *
     * @return list<array<string, mixed>>
     */
    public static function getCachedForUser(string $userId, bool $activeOnly = true): array
    {
        $cacheKey = 'membership:user:'.$userId.':'.($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 86400, function () use ($userId, $activeOnly) {
            $reviewerProfile = ReviewerProfile::where('user_id', $userId)->first();
            $membershipIds = array_values(array_unique(array_filter(array_map('strval', (array) ($reviewerProfile->membership ?? [])))));

            if (empty($membershipIds)) {
                return [];
            }

            $query = static::query()->whereIn('_id', $membershipIds);

            if ($activeOnly) {
                $query->whereIn('status', [1, '1', true]);
            }

            // High-scale index optimized sort & eager-load organization
            $records = $query->with('organization')
                ->orderBy('_id', 'desc')
                ->get();

            $countriesMap = collect(Country::getCachedAll())->keyBy('id');

            $formatted = [];
            foreach ($records as $m) {
                $statusVal = (int) ($m->status ?? 1);
                $isContinuing = $m->isContinuing();
                $cVal = (string) ($m->country ?? '');
                $countryName = '';
                if ($cVal !== '') {
                    if ($countriesMap->has($cVal)) {
                        $countryName = (string) ($countriesMap[$cVal]['name'] ?? '');
                    } elseif (! is_numeric($cVal)) {
                        $countryName = $cVal;
                    }
                }

                $formatted[] = [
                    'id' => (string) $m->_id,
                    'membership_type' => (string) ($m->membership_type ?? ''),
                    'organization_id' => (string) ($m->organization_id ?? ''),
                    'organization_name' => (string) ($m->organization?->organization_name ?? ''),
                    'join_date' => (string) ($m->join_date ?? ''),
                    'end_date' => (string) ($m->end_date ?? ''),
                    'continue_membership' => $isContinuing ? 1 : 0,
                    'is_continuing' => $isContinuing,
                    'country' => $cVal,
                    'country_name' => $countryName,
                    'webiste_url' => (string) ($m->webiste_url ?? $m->website_url ?? ''),
                    'website_url' => (string) ($m->website_url ?? $m->webiste_url ?? ''),
                    'status' => $statusVal === 1 ? 1 : 0,
                ];
            }

            return $formatted;
        });
    }
}
