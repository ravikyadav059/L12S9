<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Article extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'article_type';

    protected $table = 'article_type';

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
