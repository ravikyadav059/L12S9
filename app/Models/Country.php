<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'country';

    protected $table = 'country';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'sortname',
        'name',
        'phoneCode',
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
        ];
    }

    /**
     * Get the states belonging to this country.
     */
    /**
     * Cache key for all countries list.
     */
    public const CACHE_KEY = 'countries:all';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            static::forgetCached();
        });

        static::deleted(function (): void {
            static::forgetCached();
        });
    }

    /**
     * Clear cached list of countries.
     */
    public static function forgetCached(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get cached list of all countries sorted alphabetically.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function getCachedAll(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        $docs = static::raw(fn ($col) => $col->find(
            [],
            ['projection' => ['id' => 1, 'name' => 1], 'sort' => ['name' => 1]]
        ));

        $list = [];
        foreach ($docs as $doc) {
            if (isset($doc['id']) && isset($doc['name'])) {
                $list[] = [
                    'id' => (string) $doc['id'],
                    'name' => (string) $doc['name'],
                ];
            }
        }
        usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        if (! empty($list)) {
            Cache::put(self::CACHE_KEY, $list, 86400);
        }

        return $list;
    }
}
