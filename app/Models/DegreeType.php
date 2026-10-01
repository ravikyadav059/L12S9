<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class DegreeType extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'degree_type';

    protected $table = 'degree_type';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'degree_type',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the education records associated with this degree type.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class, 'degree_type', '_id');
    }
}
