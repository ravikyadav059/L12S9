<?php

namespace App\Models;

use App\Traits\DetectsSpam;
use Database\Factories\InvitedPositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class InvitedPosition extends Model
{
    use DetectsSpam;

    /** @use HasFactory<InvitedPositionFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'invited_position';

    protected $table = 'invited_position';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role',
        'join_date',
        'end_date',
        'continue_invited_position',
        'webiste_url',
        'organization_id',
        'country',
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
            'continue_invited_position' => 'integer',
        ];
    }

    /**
     * The model attributes to inspect during spam detection.
     *
     * @var array<int, string>
     */
    protected array $spamCheckAttributes = [
        'role',
    ];

    /**
     * Scope to retrieve active invited position records.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    /**
     * Check if the invited position is continuing / ongoing.
     */
    public function isContinuing(): bool
    {
        return in_array($this->continue_invited_position, [1, '1', true], true)
            || empty($this->end_date);
    }

    /**
     * Accessor to seamlessly support both website_url and database-native webiste_url typo.
     */
    public function getWebsiteUrlAttribute(): ?string
    {
        return $this->attributes['website_url'] ?? $this->attributes['webiste_url'] ?? null;
    }

    /**
     * Mutator to keep both attributes synchronized.
     */
    public function setWebsiteUrlAttribute(mixed $value): void
    {
        $this->attributes['website_url'] = $value;
        $this->attributes['webiste_url'] = $value;
    }

    /**
     * Get the organization associated with this invited position.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', '_id');
    }

    /**
     * Get the user that owns the invited position record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }
}
