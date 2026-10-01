<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class RoleInResearchJournals extends Model
{
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
}
