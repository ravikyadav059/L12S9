<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class DegreeType extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'degree_type';

    protected $table = 'degree_type';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'degree_type',
        'status',
        'submission_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_count' => 'integer',
        ];
    }

    /**
     * Cache key for all active degree types.
     */
    public const CACHE_KEY_ACTIVE = 'degree_types:all_active_v1';

    /**
     * Clear the cached active degree types list.
     */
    public static function clearActiveCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
    }

    /**
     * Booted model events.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            self::clearActiveCache();
        });

        static::deleted(function () {
            self::clearActiveCache();
        });
    }

    /**
     * Get all active degree types from cache.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function getAllActiveCached(): array
    {
        return Cache::remember(self::CACHE_KEY_ACTIVE, 172800, function () {
            $records = self::query()
                ->select(['_id', 'degree_type', 'status'])
                ->whereIn('status', [1, '1', true])
                ->whereNotNull('degree_type')
                ->where('degree_type', '!=', '')
                ->orderBy('degree_type', 'asc')
                ->get();

            $list = [];
            $seen = [];
            foreach ($records as $record) {
                $name = trim((string) $record->degree_type);
                $key = strtolower($name);
                if ($name !== '' && ! isset($seen[$key])) {
                    $seen[$key] = true;
                    $list[] = [
                        'id' => (string) $record->_id,
                        'name' => $name,
                    ];
                }
            }

            return $list;
        });
    }

    /**
     * Search active degree types with in-memory cache and database fallback.
     *
     * @return array{data: list<array{id: string, name: string}>, has_more: bool, page: int}
     */
    public static function searchActive(string $query = '', int $limit = 20, int $page = 1): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));

        $all = self::getAllActiveCached();

        if ($query !== '') {
            $cleanQuery = strtolower($query);
            $startsWith = [];
            $contains = [];

            foreach ($all as $item) {
                $lower = strtolower($item['name']);
                if (str_starts_with($lower, $cleanQuery)) {
                    $startsWith[] = $item;
                } elseif (str_contains($lower, $cleanQuery)) {
                    $contains[] = $item;
                }
            }

            $filtered = array_merge($startsWith, $contains);

            if (empty($filtered)) {
                return self::searchFromDatabase($query, $limit, $page);
            }
        } else {
            $filtered = $all;
        }

        $total = count($filtered);
        $offset = ($page - 1) * $limit;
        $results = array_slice($filtered, $offset, $limit);
        $hasMore = ($offset + $limit) < $total;

        return [
            'data' => array_values($results),
            'has_more' => $hasMore,
            'page' => $page,
        ];
    }

    /**
     * Search directly from database as fallback.
     *
     * @return array{data: list<array{id: string, name: string}>, has_more: bool, page: int}
     */
    public static function searchFromDatabase(string $query, int $limit = 20, int $page = 1): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));

        $builder = self::query()
            ->select(['_id', 'degree_type', 'status'])
            ->whereNotNull('degree_type')
            ->where('degree_type', '!=', '');

        if ($query !== '') {
            $builder->where('degree_type', 'regex', new Regex(preg_quote($query), 'i'));
        } else {
            $builder->whereIn('status', [1, '1', true]);
        }

        $records = $builder->orderBy('degree_type', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit + 1)
            ->get();

        $results = [];
        $hasMore = $records->count() > $limit;
        foreach ($records->take($limit) as $rec) {
            $results[] = [
                'id' => (string) $rec->_id,
                'name' => trim((string) $rec->degree_type),
            ];
        }

        return [
            'data' => $results,
            'has_more' => $hasMore,
            'page' => $page,
        ];
    }

    /**
     * Record usage for a degree type:
     * - Case-insensitive exact match.
     * - If exists: increment submission_count. If status is 0 and submissions >= 3, promote status to 1.
     * - If not exists: create with status 0 and submission_count 1.
     */
    public static function recordUsage(string $title): self
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Degree title cannot be empty.');
        }

        $existing = self::where('degree_type', 'regex', new Regex('^'.preg_quote($title).'$', 'i'))->first();

        if ($existing) {
            $newCount = ((int) ($existing->submission_count ?? 1)) + 1;
            $existing->submission_count = $newCount;

            $rawStatus = (string) ($existing->status ?? '0');
            if (($rawStatus === '0' || $rawStatus === '') && $newCount >= 3) {
                $existing->status = 1;
            }

            $existing->save();
            self::clearActiveCache();

            return $existing;
        }

        $created = self::create([
            'degree_type' => $title,
            'status' => 0,
            'submission_count' => 1,
        ]);

        self::clearActiveCache();

        return $created;
    }

    /**
     * Get the education records associated with this degree type.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class, 'degree_type', '_id');
    }
}
