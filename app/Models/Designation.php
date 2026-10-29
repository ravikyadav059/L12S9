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
        'usage_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    /**
     * Record usage for a designation:
     * - Case-insensitive exact match.
     * - If exists: increment usage_count. If status is 0 and usage >= 3, promote status to 1.
     * - If not exists: create with status 0 and usage_count 1.
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
            $newCount = ((int) ($existing->usage_count ?? 1)) + 1;
            $existing->usage_count = $newCount;

            $rawStatus = (string) ($existing->status ?? '0');
            if (($rawStatus === '0' || $rawStatus === '') && $newCount >= 3) {
                $existing->status = 1;
            }

            $existing->save();
            Cache::forget('designations:default:list');

            return $existing;
        }

        $created = self::create([
            'designation' => $title,
            'status' => 0,
            'usage_count' => 1,
        ]);

        Cache::forget('designations:default:list');

        return $created;
    }

    /**
     * High-scale search for active designations with Redis caching.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function searchActive(string $query = '', int $limit = 20): array
    {
        $query = trim($query);
        $cacheKey = $query === ''
            ? 'designations:default:list'
            : 'designations:search:'.md5(strtolower($query));
        $cacheTtl = $query === '' ? 3600 : 300;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($query, $limit) {
            $builder = self::query()
                ->whereIn('status', [1, '1', true])
                ->select(['_id', 'designation', 'usage_count']);

            if ($query !== '') {
                $builder->where('designation', 'regex', new Regex(preg_quote($query), 'i'));
            }

            $records = $builder->orderBy('usage_count', 'desc')
                ->orderBy('designation', 'asc')
                ->limit($limit)
                ->get();

            $results = [];
            foreach ($records as $record) {
                $title = trim((string) $record->designation);
                if ($title !== '') {
                    $results[] = [
                        'id' => (string) $record->_id,
                        'name' => $title,
                    ];
                }
            }

            return $results;
        });
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
