<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class RequestReviewPaper extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'request_review_paper';

    protected $table = 'request_review_paper';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'journal_id',
        'paper_title',
        'paper_keywords',
        'registered_co_author',
        'unregistered_co_author',
        'authors_name_only',
        'abstract',
        'research_category',
        'menu_script',
        'review_parameters',
        'scholar_profiles',
        'deadline',
        'status',
        'paper_id',
        'paper_number',
        'checkPlagiarism',
        'plagiarismPercentage',
        'plagiarism_report',
        'is_accepted',
        'research_type',
        'references',
        'country',
        'state',
        'city',
        'openlinkstatus',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_accepted' => 'boolean',
            'registered_co_author' => 'array',
            'unregistered_co_author' => 'array',
            'authors_name_only' => 'array',
            'paper_keywords' => 'array',
            'review_parameters' => 'array',
            'scholar_profiles' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the reviewer journal associated with this paper request.
     */
    public function reviewerJournal(): BelongsTo
    {
        return $this->belongsTo(ReviewerJournal::class, 'journal_id', '_id');
    }

    /**
     * Get the user who submitted this review paper request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /**
     * Get the country associated with this paper.
     */
    public function countryRelation(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country', '_id');
    }

    /**
     * Get the state associated with this paper.
     */
    public function stateRelation(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state', '_id');
    }

    /**
     * Get the city associated with this paper.
     */
    public function cityRelation(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city', '_id');
    }
}
