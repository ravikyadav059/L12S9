<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Designation extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'designation';

    protected $table = 'designation';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'designation',
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
     * Record usage for a designation:
     * - Case-insensitive exact match.
     * - If exists: increment submission_count. If status is 0 and submissions >= 3, promote status to 1.
     * - If not exists: create with status 0 and submission_count 1.
     */
    public static function recordUsage(string $title): self
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Designation title cannot be empty.');
        }

        // Case-insensitive exact match
        $existing = self::where('designation', 'regex', new Regex('^'.preg_quote($title).'$', 'i'))->first();

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
            'designation' => $title,
            'status' => 0,
            'submission_count' => 1,
        ]);

        self::clearActiveCache();

        return $created;
    }

    /**
     * Cache key for all active designations.
     */
    public const CACHE_KEY_ACTIVE = 'designations:all_active_v1';

    /**
     * Boot model events to clear cache on change.
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
     * Clear all cached designation lists.
     */
    public static function clearActiveCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
        Cache::forget('designations:default:list');
        Cache::forget('designations:default:list:p:1:l:20');
    }

    /**
     * Get all active designations cached for 2 days (172,800 seconds).
     *
     * @return list<array{id: string, name: string}>
     */
    public static function getAllActiveCached(): array
    {
        return Cache::remember(self::CACHE_KEY_ACTIVE, now()->addDays(2), function (): array {
            $records = self::query()
                ->select(['_id', 'designation'])
                ->whereIn('status', [1, '1', true])
                ->whereNotNull('designation')
                ->where('designation', '!=', '')
                ->orderBy('designation', 'asc')
                ->get();

            $list = [];
            $seen = [];
            foreach ($records as $record) {
                $title = trim((string) $record->designation);
                $key = strtolower($title);
                if ($title !== '' && ! isset($seen[$key])) {
                    $seen[$key] = true;
                    $list[] = [
                        'id' => (string) $record->_id,
                        'name' => $title,
                    ];
                }
            }

            return $list;
        });
    }

    /**
     * High-scale search for active designations:
     * 1. Search directly in 2-day PHP in-memory cache (Ultra fast, 0 DB load).
     * 2. If no match found in cache, fallback to query live MongoDB database directly.
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

            // Fallback: If not found in 2-day in-memory cache, query live MongoDB database directly
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
     * Search directly from the MongoDB database as fallback.
     *
     * @return array{data: list<array{id: string, name: string}>, has_more: bool, page: int}
     */
    public static function searchFromDatabase(string $query, int $limit = 20, int $page = 1): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));

        $builder = self::query()
            ->select(['_id', 'designation', 'status'])
            ->whereNotNull('designation')
            ->where('designation', '!=', '');

        if ($query !== '') {
            $builder->where('designation', 'regex', new Regex(preg_quote($query), 'i'));
        } else {
            $builder->whereIn('status', [1, '1', true]);
        }

        $buffer = $limit + 10;
        $records = $builder->orderBy('status', 'desc')
            ->orderBy('designation', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($buffer)
            ->get();

        $results = [];
        $seen = [];
        $hasMore = false;
        foreach ($records as $record) {
            $title = trim((string) $record->designation);
            $key = strtolower($title);
            if ($title !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                if (count($results) < $limit) {
                    $results[] = [
                        'id' => (string) $record->_id,
                        'name' => $title,
                    ];
                } else {
                    $hasMore = true;
                    break;
                }
            }
        }

        return [
            'data' => $results,
            'has_more' => $hasMore,
            'page' => $page,
        ];
    }

    /**
     * Scope a query to only include active designations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [1, '1', true]);
    }

    /**
     * Get experiences associated with this designation.
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class, 'designation', '_id');
    }
}
