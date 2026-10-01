<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class ReviewerProfile extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'reviewer_profile';

    protected $table = 'reviewer_profile';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'unique_id',
        'user_id',
        'status',
        'research_field',
        'designation',
        'skills',
        'skills_tags',
        'research_tags',
        'research',
        'about_us',
        'aboutus',
        'photo',
        'mobile',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'badge_type',
        'cv_file_path',
        'cv_onboarding_completed',
        'public_profile',
        'education_cat',
        'experience',
        'education',
        'publication',
        'projects',
        'seminar',
        'certificate',
        'phd',
        'award',
        'RoleInResearchJournal',
        'patent',
        'invited_position',
        'membership',
        'last_degree_certificate',
        'academia_id',
        'academia_url',
        'employee_id',
        'employee_url',
        'google_scholar_id',
        'google_scholar_url',
        'mendeley_id',
        'orcid_id',
        'orcid_url',
        'publon_id',
        'research_gate_id',
        'scopus_id',
        'ssrn_id',
        'web_science_research_id',
        'youtube_link',
        'github_url',
        'linkedin_url',
        'publication_count',
        'publication_active_count',
        'publication_inactive_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publication_count' => 'integer',
            'publication_active_count' => 'integer',
            'publication_inactive_count' => 'integer',
            'cv_onboarding_completed' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     * Automatically sync status from the related User before every save (create or update)
     * and enforce that user_id is unique across reviewer profiles.
     */
    protected static function booted(): void
    {
        static::saving(function (ReviewerProfile $reviewerProfile): void {
            if (empty($reviewerProfile->user_id)) {
                return; // Nothing to sync without a user_id
            }

            // Ensure user_id is unique - prevent duplicate profiles for the same user
            if ($reviewerProfile->isDirty('user_id')) {
                $query = static::where('user_id', $reviewerProfile->user_id);

                if ($reviewerProfile->exists) {
                    $query->where('_id', '!=', $reviewerProfile->_id);
                }

                if ($query->exists()) {
                    throw new \RuntimeException("A ReviewerProfile for user_id [{$reviewerProfile->user_id}] already exists.");
                }
            }

            // Fetch the raw user document to read the un-cast status value
            $user = User::find($reviewerProfile->user_id);

            if (! $user) {
                return;
            }

            // getRawOriginal() bypasses any attribute accessors or casts on User
            // to retrieve the raw stored value (e.g. 0, 1, "0", "1")
            $rawStatus = $user->getRawOriginal('status');

            // Normalise to string "0" or "1"; ignore anything else to avoid corruption
            if (in_array((string) $rawStatus, ['0', '1'], true)) {
                $reviewerProfile->status = (string) $rawStatus;
            }
        });
    }

    /**
     * Get the user associated with this reviewer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /**
     * Get the country associated with this reviewer profile.
     */
    public function countryRelation(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country', '_id');
    }

    /**
     * Get the state associated with this reviewer profile.
     */
    public function stateRelation(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state', '_id');
    }

    /**
     * Get the city associated with this reviewer profile.
     */
    public function cityRelation(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city', '_id');
    }
}
