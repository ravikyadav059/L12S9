<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'country';

    protected $table = 'country';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'sortname',
        'name',
        'phoneCode',
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
     * Get the states belonging to this country.
     */
    public function states(): HasMany
    {
        return $this->hasMany(State::class, 'country_id', '_id');
    }
}
