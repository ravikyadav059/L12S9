<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'category';

    protected $table = 'category';

    /**
     * Cache key and 1-week TTL for active category listing.
     */
    public const CACHE_KEY = 'categories:active';

    public const CACHE_TTL = 604800; // 1 week in seconds (7 * 24 * 3600)

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_title',
        'category_status',
        'serial_number',
        'category_slug',
        'category_image',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_status' => 'integer',
            'serial_number' => 'integer',
        ];
    }

    /**
     * The "booted" method of the model.
     * Automatically assigns serial_number and category_slug if not present,
     * and invalidates Redis cache on create, update, or delete.
     */
    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (empty($category->serial_number)) {
                $maxSerial = (int) static::query()->max('serial_number');
                $category->serial_number = $maxSerial + 1;
            }

            if (empty($category->category_slug) && ! empty($category->category_title)) {
                $category->category_slug = Str::slug($category->category_title).'-'.$category->serial_number;
            }
        });

        static::saved(function (Category $category): void {
            static::forgetCached();
        });

        static::deleted(function (Category $category): void {
            static::forgetCached();
        });
    }

    /**
     * Clear cached list of categories.
     */
    public static function forgetCached(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Retrieve cached list of active categories for 1 week.
     *
     * @return array<int, array{id: string, name: string, title: string, slug: string}>
     */
    public static function getCachedAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::query()
                ->whereIn('category_status', [1, '1', true])
                ->orderBy('category_title', 'asc')
                ->get(['_id', 'id', 'category_title', 'category_slug'])
                ->map(fn ($cat) => [
                    'id' => (string) $cat->_id,
                    'name' => (string) ($cat->category_title ?? ''),
                    'title' => (string) ($cat->category_title ?? ''),
                    'slug' => (string) ($cat->category_slug ?? ''),
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * Scope to retrieve active category records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('category_status', [1, '1']);
    }

    /**
     * Get the patents associated with this category.
     */
    public function patents(): HasMany
    {
        return $this->hasMany(Patent::class, 'category_id', '_id');
    }
}
