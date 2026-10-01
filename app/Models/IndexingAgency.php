<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class IndexingAgency extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'indexing_agency';

    protected $table = 'indexing_agency';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'serial_number',
        'agency_name',
        'image',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'serial_number' => 'integer',
            'status' => 'integer',
        ];
    }
}
