<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class RoleInResearchJournals extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'role_in_research_journals';

    protected $table = 'role_in_research_journals';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'start_month_year',
        'end_month_year',
        'not_ended_yet',
        'journal_title',
        'journal_short_name',
        'e_issn',
        'p_issn',
        'organization_name',
        'journal_webiste_url',
        'description',
        'user_id',
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
            'not_ended_yet' => 'integer',
        ];
    }

    /**
     * Scope to retrieve active role in research journal records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1', true]);
    }

    /**
     * Accessor to seamlessly support website_url alongside database-native journal_webiste_url typo.
     */
    public function getWebsiteUrlAttribute(): ?string
    {
        return $this->attributes['journal_webiste_url'] ?? $this->attributes['website_url'] ?? null;
    }

    /**
     * Mutator to keep journal_webiste_url synchronized.
     */
    public function setWebsiteUrlAttribute(mixed $value): void
    {
        $this->attributes['journal_webiste_url'] = $value;
    }

    /**
     * Get the reviewer journal associated with this role.
     * Note: 'journal_title' field stores the MongoDB _id of the journal.
     */
    public function reviewerJournal(): BelongsTo
    {
        return $this->belongsTo(ReviewerJournal::class, 'journal_title', '_id');
    }

    /**
     * Get the user associated with this role.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /**
     * Get the JournalRole associated with this role.
     * Note: 'role' field stores the MongoDB _id of JournalRole (or legacy role name string).
     */
    public function journalRole(): BelongsTo
    {
        return $this->belongsTo(JournalRole::class, 'role', '_id');
    }

    /**
     * Get the resolved Journal title with fallback.
     */
    public function getJournalNameAttribute(): string
    {
        return (string) ($this->reviewerJournal?->journal_title ?? $this->journal_title ?? '—');
    }

    /**
     * Get the resolved User display name with fallback.
     */
    public function getUserDisplayNameAttribute(): string
    {
        return (string) ($this->user?->name ?? '—');
    }

    /**
     * Get the resolved Role display name with fallback.
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return (string) ($this->journalRole?->name ?? $this->role ?? '—');
    }

    /**
     * Bootstrap model events to manage Redis cache invalidation.
     */
    protected static function booted(): void
    {
        static::saved(function (RoleInResearchJournals $record): void {
            if (! empty($record->user_id)) {
                static::forgetUserCache((string) $record->user_id);
            }
        });

        static::deleted(function (RoleInResearchJournals $record): void {
            if (! empty($record->user_id)) {
                static::forgetUserCache((string) $record->user_id);
            }
        });
    }

    /**
     * Invalidate Redis cache for a user's research journal role records.
     */
    public static function forgetUserCache(string|int|null $userId): void
    {
        if (! $userId) {
            return;
        }

        Cache::forget("role_in_research_journals:user:{$userId}:active");
        Cache::forget("role_in_research_journals:user:{$userId}:all");
    }

    /**
     * Retrieve cached research journal role records for a user using Redis.
     *
     * @return list<array<string, mixed>>
     */
    public static function getCachedForUser(string $userId, bool $activeOnly = true): array
    {
        $cacheKey = 'role_in_research_journals:user:'.$userId.':'.($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 86400, function () use ($userId, $activeOnly) {
            $reviewerProfile = ReviewerProfile::where('user_id', $userId)->first();
            $roleRecordIds = array_values(array_unique(array_filter(array_map('strval', (array) ($reviewerProfile->RoleInResearchJournal ?? [])))));

            if (empty($roleRecordIds)) {
                return [];
            }

            $query = static::query()->whereIn('_id', $roleRecordIds);

            if ($activeOnly) {
                $query->whereIn('status', [1, '1', true]);
            }

            $records = $query->with(['reviewerJournal', 'journalRole'])
                ->orderBy('_id', 'desc')
                ->get();

            $rolesMap = collect(JournalRole::getCachedActive())->keyBy('id');

            $formatted = [];
            foreach ($records as $r) {
                $statusVal = (int) ($r->status ?? 0);
                $rawRole = (string) ($r->role ?? '');
                $roleName = (string) ($r->journalRole?->name ?? '');

                if ($roleName === '' && $rawRole !== '') {
                    if ($rolesMap->has($rawRole)) {
                        $roleName = (string) ($rolesMap[$rawRole]['name'] ?? '');
                    } else {
                        $roleName = $rawRole;
                    }
                }

                $rawJournal = (string) ($r->journal_title ?? '');
                $isJournalObjId = strlen($rawJournal) === 24 && ctype_xdigit($rawJournal);
                $journal = $r->reviewerJournal;

                $journalId = $journal ? (string) $journal->_id : ($isJournalObjId ? $rawJournal : '');
                $journalName = (string) ($journal?->journal_title ?? (! $isJournalObjId ? $rawJournal : 'Unknown Journal'));

                $shortName = trim((string) ($r->journal_short_name ?: ($journal?->journal_short_name ?? '')));
                $eIssn = trim((string) ($r->e_issn ?: ($journal?->e_issn ?? '')));
                $pIssn = trim((string) ($r->p_issn ?: ($journal?->p_issn ?? '')));
                $publisherName = trim((string) ($r->organization_name ?: ($journal?->organization_name ?? '')));
                $journalUrl = trim((string) ($r->journal_webiste_url ?: ($journal?->journal_webiste_url ?? '')));

                $formatted[] = [
                    'id' => (string) $r->_id,
                    'role' => $rawRole,
                    'role_name' => $roleName ?: 'Not Specified',
                    'journal_id' => $journalId,
                    'journal_title' => $journalName,
                    'journal_short_name' => $shortName,
                    'e_issn' => $eIssn,
                    'p_issn' => $pIssn,
                    'organization_name' => $publisherName,
                    'journal_webiste_url' => $journalUrl,
                    'status' => $statusVal === 1 ? 1 : 0,
                ];
            }

            return $formatted;
        });
    }
}
