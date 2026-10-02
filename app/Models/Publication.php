<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Publication extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'publications';

    protected $fillable = [
        'title',
        'slug',
        'publication_keywords',
        'status',
        'serial_number',
        'journal_name',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function reviewerJournal(): BelongsTo
    {
        return $this->belongsTo(ReviewerJournal::class, 'journal_title', '_id');
    }

    public function articleTypeRelation(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'type', '_id');
    }
}
