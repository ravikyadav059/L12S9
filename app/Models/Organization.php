<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'organization';

    protected $table = 'organization';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_name',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'affiliated_university',
        'website_url',
        'organization_type',
        'status',
        'logo',
        'serial_number',
        'slug',
        'description',
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
            'serial_number' => 'integer',
            'submission_count' => 'integer',
        ];
    }

    /**
     * Record or find an organization with case-insensitive deduplication and user submission tracking:
     * - If exists: increment submission_count. If status is 0 and submissions >= 3, promote to 1.
     * - If not exists: create with status 0 and submission_count 1.
     */
    public static function recordOrFind(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Organization name cannot be empty.');
        }

        // Case-insensitive exact match
        $existing = self::where('organization_name', 'regex', new Regex('^'.preg_quote($name).'$', 'i'))->first();

        if ($existing) {
            $newCount = ((int) ($existing->submission_count ?? 1)) + 1;
            $existing->submission_count = $newCount;

            $rawStatus = (string) ($existing->status ?? '0');
            if (($rawStatus === '0' || $rawStatus === '') && $newCount >= 3) {
                $existing->status = 1;
            }

            $existing->save();
            Cache::forget('organizations:default:list');
            Cache::forget('organizations:default:list:p:1:l:20');

            return $existing;
        }

        $created = self::create([
            'organization_name' => $name,
            'status' => 0,
            'submission_count' => 1,
        ]);

        Cache::forget('organizations:default:list');
        Cache::forget('organizations:default:list:p:1:l:20');

        return $created;
    }

    /**
     * High-scale search for active organizations with Redis caching and pagination.
     *
     * @return array{data: list<array{id: string, name: string, city: ?string, country: ?string}>, has_more: bool, page: int}
     */
    public static function searchActive(string $query = '', int $limit = 20, int $page = 1): array
    {
        $query = trim($query);
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));

        $cacheKey = ($query === '' ? 'organizations:default:list' : 'organizations:search:'.md5(strtolower($query)))
            .':p:'.$page.':l:'.$limit;
        $cacheTtl = $query === '' ? 3600 : 300;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($query, $limit, $page) {
            $builder = self::query()
                ->select(['_id', 'organization_name', 'city', 'country', 'submission_count', 'status'])
                ->whereNotNull('organization_name')
                ->where('organization_name', '!=', '');

            if ($query !== '') {
                $builder->where('organization_name', 'regex', new Regex(preg_quote($query), 'i'));
            } else {
                $builder->whereIn('status', [1, '1', true]);
            }

            // Fetch extra buffer to account for duplicate names across documents
            $buffer = $limit + 10;
            $records = $builder->orderBy('status', 'desc')
                ->orderBy('organization_name', 'asc')
                ->skip(($page - 1) * $limit)
                ->take($buffer)
                ->get();

            $results = [];
            $seen = [];
            $hasMore = false;
            foreach ($records as $record) {
                $name = trim((string) $record->organization_name);
                $key = strtolower($name);
                if ($name !== '' && ! isset($seen[$key])) {
                    $seen[$key] = true;
                    if (count($results) < $limit) {
                        $results[] = [
                            'id' => (string) $record->_id,
                            'name' => $name,
                            'city' => (string) ($record->city ?? ''),
                            'country' => (string) ($record->country ?? ''),
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
        });
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            // Auto-generate unique serial_number if not set
            if (empty($organization->serial_number)) {
                $maxSerial = (int) static::max('serial_number');
                if ($maxSerial <= 0) {
                    $lastRecord = static::whereNotNull('serial_number')
                        ->orderBy('serial_number', 'desc')
                        ->first();
                    $maxSerial = $lastRecord && is_numeric($lastRecord->serial_number)
                        ? (int) $lastRecord->serial_number
                        : 0;
                }
                $organization->serial_number = $maxSerial + 1;
            }

            // Auto-generate slug based on organization_name and serial_number
            if (empty($organization->slug)) {
                $baseSlug = Str::slug($organization->organization_name ?? '');
                $organization->slug = $baseSlug !== ''
                    ? "{$baseSlug}-{$organization->serial_number}"
                    : (string) $organization->serial_number;
            }

            // Default status to 1 if not explicitly provided
            if (! isset($organization->status)) {
                $organization->status = 1;
            }
        });
    }

    /**
     * Scope a query to only include active organizations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [1, '1', true]);
    }
}
