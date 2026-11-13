<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

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

        static::saved(function (Organization $organization): void {
            static::clearActiveCache();
            if (! empty($organization->id)) {
                Cache::forget('organizations:metrics:'.(string) $organization->id);
            }
        });

        static::deleted(function (Organization $organization): void {
            static::clearActiveCache();
            if (! empty($organization->id)) {
                Cache::forget('organizations:metrics:'.(string) $organization->id);
            }
        });
    }

    /**
     * Clear all cached default organization lists and counts.
     */
    public static function clearActiveCache(): void
    {
        Cache::forget('organizations:default:list');
        for ($p = 1; $p <= 10; $p++) {
            Cache::forget("organizations:default:list:p:{$p}:l:20");
            Cache::forget("organizations:default:list:p:{$p}:l:10");
        }
        Cache::forget('organizations:active:count');
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
            static::clearActiveCache();

            return $existing;
        }

        $created = self::create([
            'organization_name' => $name,
            'status' => 0,
            'submission_count' => 1,
        ]);

        static::clearActiveCache();

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
     * Calculate aggregated academic and research metrics for an organization across MongoDB collections.
     *
     * @return array{scholars: int, alumni: int, publications: int, citations: int, seminars: int, scholar_uids: list<string>, alumni_uids: list<string>, pub_ids: list<string>}
     */
    public static function calculateMetrics(string $orgId): array
    {
        $experiences = Experience::raw(fn ($col) => $col->find(
            [
                'organization_id' => $orgId,
                'user_id' => ['$exists' => true, '$ne' => '', '$nin' => [null, '']],
                'status' => ['$in' => [1, '1']],
            ],
            [
                'projection' => [
                    'user_id' => 1,
                    'current_organization' => 1,
                ],
            ]
        ));

        $currentUsers = [];
        $alumniUsers = [];
        $allCandidateUserIds = [];

        foreach ($experiences as $exp) {
            $uid = (string) ($exp['user_id'] ?? '');
            $curr = $exp['current_organization'] ?? null;

            if ($uid === '') {
                continue;
            }

            $allCandidateUserIds[$uid] = true;
            if (in_array($curr, [1, '1', true], true)) {
                $currentUsers[$uid] = true;
            } else {
                $alumniUsers[$uid] = true;
            }
        }

        // Verify active user status (status = 1 or "1")
        $activeUserMap = [];
        $candidateUids = array_keys($allCandidateUserIds);
        if (! empty($candidateUids)) {
            $cObjIds = [];
            $cStrIds = [];
            foreach ($candidateUids as $cuid) {
                $cuidStr = (string) $cuid;
                $cStrIds[] = $cuidStr;
                if (strlen($cuidStr) === 24 && ctype_xdigit($cuidStr)) {
                    $cObjIds[] = new ObjectId($cuidStr);
                }
            }

            $activeUserDocs = User::raw(fn ($col) => $col->find(
                [
                    '_id' => ['$in' => array_merge($cObjIds, $cStrIds)],
                    'status' => ['$in' => [1, '1']],
                ],
                [
                    'projection' => ['_id' => 1],
                ]
            ));

            foreach ($activeUserDocs as $au) {
                $activeUserMap[(string) $au['_id']] = true;
            }
        }

        $currentUids = [];
        foreach (array_keys($currentUsers) as $uId) {
            if (isset($activeUserMap[$uId])) {
                $currentUids[] = $uId;
            }
        }

        $alumniUids = [];
        foreach (array_keys($alumniUsers) as $uId) {
            if (isset($activeUserMap[$uId]) && ! in_array($uId, $currentUids, true)) {
                $alumniUids[] = $uId;
            }
        }

        $orgPubIds = [];
        $orgSeminarIds = [];

        if (! empty($currentUids)) {
            $profiles = ReviewerProfile::raw(fn ($col) => $col->find(
                [
                    'user_id' => ['$in' => $currentUids],
                    'status' => ['$in' => [1, '1']],
                ],
                ['projection' => ['publication' => 1, 'seminar' => 1]]
            ));

            foreach ($profiles as $prof) {
                $pubs = is_array($prof['publication'] ?? null) ? $prof['publication'] : [];
                $sems = is_array($prof['seminar'] ?? null) ? $prof['seminar'] : [];

                foreach ($pubs as $p) {
                    if (! empty($p)) {
                        $orgPubIds[(string) $p] = true;
                    }
                }

                foreach ($sems as $s) {
                    if (! empty($s)) {
                        $orgSeminarIds[(string) $s] = true;
                    }
                }
            }
        }

        $totalCitations = 0;
        $distinctPubIds = array_keys($orgPubIds);
        $validActivePubIds = [];

        if (! empty($distinctPubIds)) {
            $validObjectIds = [];
            $validStrIds = [];
            foreach ($distinctPubIds as $pid) {
                $pidStr = (string) $pid;
                if ($pidStr !== '') {
                    $validStrIds[] = $pidStr;
                    if (strlen($pidStr) === 24 && ctype_xdigit($pidStr)) {
                        $validObjectIds[] = new ObjectId($pidStr);
                    }
                }
            }

            if (! empty($validObjectIds) || ! empty($validStrIds)) {
                $pubDocs = Publication::raw(fn ($col) => $col->find(
                    [
                        '_id' => ['$in' => array_merge($validObjectIds, $validStrIds)],
                        'status' => ['$in' => [1, '1']],
                    ],
                    ['projection' => ['_id' => 1, 'citations' => 1]]
                ));

                foreach ($pubDocs as $pd) {
                    $validActivePubIds[] = (string) $pd['_id'];
                    $totalCitations += (int) ($pd['citations'] ?? 0);
                }
            }
        }

        return [
            'scholars' => count($currentUids),
            'alumni' => count($alumniUids),
            'publications' => count($validActivePubIds),
            'citations' => $totalCitations,
            'seminars' => count($orgSeminarIds),
            'scholar_uids' => $currentUids,
            'alumni_uids' => $alumniUids,
            'pub_ids' => $validActivePubIds,
        ];
    }

    /**
     * Retrieve aggregated organization metrics with Redis cache and atomic stampede protection.
     *
     * @return array{scholars: int, alumni: int, publications: int, citations: int, seminars: int, scholar_uids: list<string>, alumni_uids: list<string>, pub_ids: list<string>}
     */
    public static function getMetrics(string $orgId, int $ttl = 1800): array
    {
        return static::getMetricsWithLock($orgId, function () use ($orgId): array {
            return static::calculateMetrics($orgId);
        }, $ttl);
    }

    /**
     * Retrieve aggregated organization metrics with stampede protection (atomic mutex locking).
     *
     * @param  \Closure(): array<string, mixed>  $calculator
     * @return array<string, mixed>
     */
    public static function getMetricsWithLock(string $orgId, \Closure $calculator, int $ttl = 1800): array
    {
        $cacheKey = 'organizations:metrics:'.$orgId;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            return Cache::lock('lock:'.$cacheKey, 15)->block(4, function () use ($cacheKey, $calculator, $ttl) {
                return Cache::remember($cacheKey, $ttl, $calculator);
            });
        } catch (\Throwable) {
            // Fallback directly to calculator if lock fails or driver does not support locks
            return Cache::remember($cacheKey, $ttl, $calculator);
        }
    }

    /**
     * Scope a query to only include active organizations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [1, '1', true]);
    }

    /**
     * Get the PhD / Thesis records affiliated with this organization.
     */
    public function phds(): HasMany
    {
        return $this->hasMany(Phd::class, 'institution_id', '_id');
    }

    /**
     * Get the professional membership records affiliated with this organization.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'organization_id', '_id');
    }

    /**
     * Get the invited position records affiliated with this organization.
     */
    public function invitedPositions(): HasMany
    {
        return $this->hasMany(InvitedPosition::class, 'organization_id', '_id');
    }
}
