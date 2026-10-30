<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Grade extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'grade';

    protected $table = 'grade';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'grade',
        'status',
    ];

    /**
     * Cache key for all active grades.
     */
    public const CACHE_KEY_ALL = 'grades:all_active_v1';

    /**
     * Clear active grades cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_ALL);
    }

    /**
     * Booted model events.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * Get all active grades from cache.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function getAllCached(): array
    {
        return Cache::remember(self::CACHE_KEY_ALL, 604800, function () {
            $records = self::query()
                ->select(['_id', 'grade', 'status'])
                ->whereIn('status', [1, '1', true])
                ->whereNotNull('grade')
                ->where('grade', '!=', '')
                ->get();

            $list = [];
            foreach ($records as $record) {
                $list[] = [
                    'id' => (string) $record->_id,
                    'name' => trim((string) $record->grade),
                ];
            }

            return $list;
        });
    }

    /**
     * Search active grades.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function searchActive(string $query = ''): array
    {
        $all = self::getAllCached();
        $query = trim($query);

        if ($query === '') {
            return $all;
        }

        $lower = strtolower($query);

        return array_values(array_filter($all, function ($item) use ($lower) {
            return str_contains(strtolower($item['name']), $lower);
        }));
    }

    /**
     * Get the education records associated with this grade.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class, 'grade', '_id');
    }
}
