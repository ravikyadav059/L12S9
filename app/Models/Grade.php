<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Grade extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'grade';

    protected $table = 'grade';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'grade',
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
     * Get the education records associated with this grade.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class, 'grade', '_id');
    }
}
