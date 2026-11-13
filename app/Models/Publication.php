<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Publication extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'publications';

    protected $fillable = [
        'title',
        'slug',
        'publication_keywords',
        'status',
        'serial_number',
        'journal_title',
        'publication_type',
        'submit_from_page',
        'user_id',
        'published_date',
        'type',
        'article_type',
        'registered_co_author',
        'unregistered_co_author',
        'authors',
        'url',
        'doi',
        'citations',
        'volume',
        'issn',
        'e_issn',
        'p_issn',
        'issue',
        'page_no',
        'description',
        'image',
        'view_counts',
        'last_spam_check_result',
        'last_spam_check_text',
        'post_id',
        'is_reported',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'serial_number' => 'integer',
        ];
    }

    /**
     * Scope to retrieve active publication records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1', true]);
    }

    public function reviewerJournal(): BelongsTo
    {
        return $this->belongsTo(ReviewerJournal::class, 'journal_title', '_id');
    }

    public function articleTypeRelation(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'type', '_id');
    }

    /**
     * Bootstrap model events to manage Redis cache invalidation.
     */
    protected static function booted(): void
    {
        $invalidate = static function (Publication $record): void {
            if (! empty($record->user_id)) {
                static::forgetUserCache((string) $record->user_id);
            }

            if (is_array($record->registered_co_author)) {
                foreach ($record->registered_co_author as $coAuthorId) {
                    if (! empty($coAuthorId)) {
                        static::forgetUserCache((string) $coAuthorId);
                    }
                }
            }
        };

        static::saved($invalidate);
        static::deleted($invalidate);
    }

    /**
     * Invalidate Redis cache for a user's publication records.
     */
    public static function forgetUserCache(string|int|null $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forget("publication:user:{$userId}:active");
        Cache::forget("publication:user:{$userId}:all");
    }

    /**
     * Retrieve cached publication records for a user.
     *
     * @return list<array<string, mixed>>
     */
    public static function getCachedForUser(string $userId, bool $activeOnly = true): array
    {
        $cacheKey = 'publication:user:'.$userId.':'.($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 7200, function () use ($userId, $activeOnly): array {
            $reviewerProfile = ReviewerProfile::where('user_id', $userId)->first();
            $pubRecordIds = array_values(array_unique(array_filter(array_map('strval', (array) ($reviewerProfile->publication ?? [])))));

            if (empty($pubRecordIds)) {
                return [];
            }

            $query = static::query()->whereIn('_id', $pubRecordIds);

            if ($activeOnly) {
                $query->whereIn('status', [1, '1', true]);
            }

            $records = $query->with(['reviewerJournal', 'articleTypeRelation'])
                ->orderByDesc('created_at')
                ->orderByDesc('_id')
                ->get();

            // Preload fresh author details from User & Experience models for all registered_co_author IDs
            $allCoAuthorIds = [];
            foreach ($records as $rec) {
                if (is_array($rec->registered_co_author)) {
                    foreach ($rec->registered_co_author as $cid) {
                        $cidStr = trim((string) $cid);
                        if ($cidStr !== '') {
                            $allCoAuthorIds[] = $cidStr;
                        }
                    }
                }
            }
            $allCoAuthorIds = array_values(array_unique($allCoAuthorIds));

            $usersById = [];
            $expMapByUserId = [];
            if (! empty($allCoAuthorIds)) {
                $usersById = User::whereIn('_id', $allCoAuthorIds)->get()->keyBy(fn (User $u): string => (string) $u->_id);
                $experiences = Experience::whereIn('user_id', $allCoAuthorIds)
                    ->orderByDesc('current_organization')
                    ->orderByDesc('end_date')
                    ->get();

                $orgIds = [];
                $userPrimaryExp = [];
                foreach ($experiences as $exp) {
                    $uId = (string) $exp->user_id;
                    if (! isset($userPrimaryExp[$uId])) {
                        $userPrimaryExp[$uId] = $exp;
                        $orgId = trim((string) ($exp->organization_id ?? ''));
                        if ($orgId !== '') {
                            $orgIds[] = $orgId;
                        }
                    }
                }
                $orgIds = array_values(array_unique($orgIds));
                $orgMap = [];
                if (! empty($orgIds)) {
                    $orgs = Organization::whereIn('_id', $orgIds)->get();
                    foreach ($orgs as $org) {
                        if (! empty($org->organization_name)) {
                            $orgMap[(string) $org->_id] = (string) $org->organization_name;
                        }
                    }
                }

                foreach ($userPrimaryExp as $uId => $exp) {
                    $orgId = trim((string) ($exp->organization_id ?? ''));
                    if ($orgId !== '' && isset($orgMap[$orgId])) {
                        $expMapByUserId[$uId] = $orgMap[$orgId];
                    }
                }
            }

            return $records->map(function (Publication $record) use ($usersById, $expMapByUserId): array {
                $journal = $record->reviewerJournal;
                $articleType = $record->articleTypeRelation;

                $eIssn = trim((string) ($record->e_issn ?: $journal?->e_issn ?: ''));
                $pIssn = trim((string) ($record->p_issn ?: $journal?->p_issn ?: ''));
                $legacyIssn = trim((string) ($record->issn ?? ''));

                if ($eIssn === '' && $pIssn === '' && $legacyIssn !== '') {
                    $eIssn = $legacyIssn;
                }

                // Resolve authors strictly from registered_co_author user records so fresh details (name, affiliation, email) are always reflected
                $authors = [];
                if (is_array($record->registered_co_author) && ! empty($record->registered_co_author)) {
                    foreach ($record->registered_co_author as $cid) {
                        $cidStr = trim((string) $cid);
                        $u = $usersById[$cidStr] ?? null;
                        if ($u) {
                            $fullName = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                            if ($fullName === '' && ! empty($u->fullname)) {
                                $fullName = trim((string) $u->fullname);
                            }
                            if ($fullName === '' && ! empty($u->name)) {
                                $fullName = trim((string) $u->name);
                            }
                            $affiliation = (string) ($expMapByUserId[$cidStr] ?? ($u->affiliation ?? ''));
                            if ($fullName !== '') {
                                $authors[] = [
                                    'id' => $cidStr,
                                    'name' => $fullName,
                                    'affiliation' => $affiliation,
                                    'email' => (string) ($u->email ?? ''),
                                ];
                            }
                        }
                    }
                }

                // Fallback for any record that may have legacy embedded authors but no registered_co_author
                if (empty($authors) && is_array($record->authors) && ! empty($record->authors)) {
                    $authors = array_values(array_filter(array_map(function ($author): array {
                        $a = is_array($author) ? $author : (array) $author;

                        return [
                            'id' => isset($a['id']) ? (string) $a['id'] : (isset($a['_id']) ? (string) $a['_id'] : null),
                            'name' => trim((string) ($a['name'] ?? $a['fullname'] ?? '')),
                            'affiliation' => trim((string) ($a['affiliation'] ?? '')),
                            'email' => trim((string) ($a['email'] ?? '')),
                        ];
                    }, $record->authors), fn (array $a): bool => $a['name'] !== ''));
                }

                $keywords = is_array($record->publication_keywords)
                    ? array_values(array_filter(array_map('strval', $record->publication_keywords)))
                    : [];

                return [
                    'id' => (string) $record->_id,
                    'title' => (string) ($record->title ?? ''),
                    'slug' => (string) ($record->slug ?? ''),
                    'doi' => (string) ($record->doi ?? ''),
                    'url' => (string) ($record->url ?? ''),
                    'type' => (string) ($record->type ?? ''),
                    'article_type_name' => (string) ($articleType?->name ?? ''),
                    'journal_title' => (string) ($record->journal_title ?? ''),
                    'journal_name' => (string) ($journal?->journal_title ?? $record->journal_name ?? ''),
                    'journal_short_name' => (string) ($journal?->journal_short_name ?? ''),
                    'issn' => $legacyIssn,
                    'e_issn' => $eIssn,
                    'p_issn' => $pIssn,
                    'published_date' => (string) ($record->published_date ?? ''),
                    'citations' => $record->citations !== null && $record->citations !== '' ? (string) $record->citations : '',
                    'volume' => $record->volume !== null && $record->volume !== '' ? (string) $record->volume : '',
                    'issue' => (string) ($record->issue ?? ''),
                    'page_no' => (string) ($record->page_no ?? ''),
                    'image' => (string) ($record->image ?? ''),
                    'publication_keywords' => $keywords,
                    'authors' => $authors,
                    'registered_co_author' => is_array($record->registered_co_author) ? array_values(array_map('strval', $record->registered_co_author)) : [],
                    'description' => (string) ($record->description ?? ''),
                    'status' => (int) ($record->status ?? 1),
                    'user_id' => (string) ($record->user_id ?? ''),
                ];
            })->values()->all();
        });
    }
}
