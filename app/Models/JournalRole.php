<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class JournalRole extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'journal_role';

    protected $table = 'journal_role';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get all role in research journals records associated with this role.
     */
    public function rolesInResearchJournals(): HasMany
    {
        return $this->hasMany(RoleInResearchJournals::class, 'role', '_id');
    }
}
