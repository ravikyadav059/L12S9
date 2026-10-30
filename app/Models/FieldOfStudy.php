<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class FieldOfStudy extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'field_of_study';

    protected $table = 'field_of_study';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'field_of_study',
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
     * Cache key for all active fields of study.
     */
    public const CACHE_KEY_ACTIVE = 'fields_of_study:all_active_v1';

    /**
     * Clear the cached active fields of study list.
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
     * Get all active fields of study from cache.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function getAllActiveCached(): array
    {
        return Cache::remember(self::CACHE_KEY_ACTIVE, 172800, function () {
            $records = self::query()
                ->select(['_id', 'field_of_study', 'status'])
                ->whereIn('status', [1, '1', true])
                ->whereNotNull('field_of_study')
                ->where('field_of_study', '!=', '')
                ->orderBy('field_of_study', 'asc')
                ->get();

            $list = [];
            $seen = [];
            foreach ($records as $record) {
                $name = trim((string) $record->field_of_study);
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
     * Search active fields of study with in-memory cache and database fallback.
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
            ->select(['_id', 'field_of_study', 'status'])
            ->whereNotNull('field_of_study')
            ->where('field_of_study', '!=', '');

        if ($query !== '') {
            $builder->where('field_of_study', 'regex', new Regex(preg_quote($query), 'i'));
        } else {
            $builder->whereIn('status', [1, '1', true]);
        }

        $records = $builder->orderBy('field_of_study', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit + 1)
            ->get();

        $results = [];
        $hasMore = $records->count() > $limit;
        foreach ($records->take($limit) as $rec) {
            $results[] = [
                'id' => (string) $rec->_id,
                'name' => trim((string) $rec->field_of_study),
            ];
        }

        return [
            'data' => $results,
            'has_more' => $hasMore,
            'page' => $page,
        ];
    }

    /**
     * Record usage for a field of study:
     * - Case-insensitive exact match.
     * - If exists: increment submission_count. If status is 0 and submissions >= 3, promote status to 1.
     * - If not exists: create with status 0 and submission_count 1.
     */
    public static function recordUsage(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Field of study cannot be empty.');
        }

        $existing = self::where('field_of_study', 'regex', new Regex('^'.preg_quote($name).'$', 'i'))->first();

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
            'field_of_study' => $name,
            'status' => 0,
            'submission_count' => 1,
        ]);

        self::clearActiveCache();

        return $created;
    }

    /**
     * Get the education records associated with this field of study.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class, 'field_of_study', '_id');
    }
}
