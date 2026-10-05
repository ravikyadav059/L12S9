<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Skill extends Eloquent
{
    protected $connection = 'mongodb';

    protected $collection = 'skills';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'skills_title',
        'skills_status',
        'serial_number',
        'slug',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'skills_status' => 'integer',
        'serial_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
