<?php

namespace App\Models;

use App\Traits\DetectsSpam;
use Database\Factories\PatentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Patent extends Model
{
    use DetectsSpam;

    /** @use HasFactory<PatentFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'patent';

    protected $table = 'patent';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'inventor',
        'assignee',
        'application_no',
        'patent_no',
        'country',
        'category_id',
        'filing_date',
        'publication_date',
        'status',
        'description',
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
        'title',
        'inventor',
        'assignee',
        'description',
    ];

    /**
     * Bootstrap model events to manage Redis cache invalidation.
     */
    protected static function booted(): void
    {
        static::saved(function (Patent $patent) {
            if (! empty($patent->user_id)) {
                static::forgetUserCache((string) $patent->user_id);
            }
        });

        static::deleted(function (Patent $patent) {
            if (! empty($patent->user_id)) {
                static::forgetUserCache((string) $patent->user_id);
            }
        });
    }

    /**
     * Invalidate Redis cache for a user's patent records.
     */
    public static function forgetUserCache(string|int|null $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forget("patent:user:{$userId}:active");
        Cache::forget("patent:user:{$userId}:all");
    }

    /**
     * Retrieve cached patent records for a user using Redis and MongoDB compound index.
     * Index utilized: {"user_id": 1, "status": 1}
     *
     * @return list<array<string, mixed>>
     */
    public static function getCachedForUser(string $userId, bool $activeOnly = true): array
    {
        $cacheKey = 'patent:user:'.$userId.':'.($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 86400, function () use ($userId, $activeOnly) {
            $reviewerProfile = ReviewerProfile::where('user_id', $userId)->first();
            $patentIds = array_values(array_unique(array_filter(array_map('strval', (array) ($reviewerProfile->patent ?? [])))));

            if (empty($patentIds)) {
                return [];
            }

            $query = static::query()->whereIn('_id', $patentIds);

            if ($activeOnly) {
                $query->whereIn('status', [1, '1', true]);
            }

            // High-scale index optimized sort & eager-load category
            $records = $query->with('category')
                ->orderBy('_id', 'desc')
                ->get();

            $countriesMap = collect(Country::getCachedAll())->keyBy('id');
            $categoriesMap = collect(Category::getCachedAll())->keyBy('id');

            $formatted = [];
            foreach ($records as $p) {
                $statusVal = (int) ($p->status ?? 1);
                $cVal = (string) ($p->country ?? '');
                $countryName = '';
                if ($cVal !== '') {
                    if ($countriesMap->has($cVal)) {
                        $countryName = (string) ($countriesMap[$cVal]['name'] ?? '');
                    } elseif (! is_numeric($cVal)) {
                        $countryName = $cVal;
                    }
                }

                $catVal = (string) ($p->category_id ?? '');
                $categoryName = '';
                if ($catVal !== '') {
                    if ($categoriesMap->has($catVal)) {
                        $categoryName = (string) ($categoriesMap[$catVal]['name'] ?? '');
                    } elseif ($p->category?->category_title) {
                        $categoryName = (string) $p->category->category_title;
                    } else {
                        $matchedCat = $categoriesMap->first(function ($c) use ($catVal) {
                            return strcasecmp((string) ($c['name'] ?? ''), $catVal) === 0;
                        });
                        $categoryName = $matchedCat ? (string) $matchedCat['name'] : $catVal;
                    }
                }

                $formatted[] = [
                    'id' => (string) $p->_id,
                    'title' => (string) ($p->title ?? ''),
                    'patent_no' => (string) ($p->patent_no ?? ''),
                    'inventor' => (string) ($p->inventor ?? ''),
                    'assignee' => (string) ($p->assignee ?? ''),
                    'application_no' => (string) ($p->application_no ?? ''),
                    'country' => $cVal,
                    'country_name' => $countryName,
                    'category_id' => $catVal,
                    'category_name' => $categoryName,
                    'filing_date' => (string) ($p->filing_date ?? ''),
                    'publication_date' => (string) ($p->publication_date ?? ''),
                    'description' => (string) ($p->description ?? ''),
                    'status' => $statusVal === 1 ? 1 : 0,
                ];
            }

            return $formatted;
        });
    }

    /**
     * Scope to retrieve active patent records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    /**
     * Get the category associated with this patent.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', '_id');
    }

    /**
     * Get the user that owns the patent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }
}
