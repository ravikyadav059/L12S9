<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

class State extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'state';

    protected $table = 'state';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'country_id',
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
     * Get the country that owns this state.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', '_id');
    }

    /**
     * Get the cities belonging to this state.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'state_id', '_id');
    }
}
