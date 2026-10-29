<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'city';

    protected $table = 'city';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'state_id',
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
        ];
    }

    /**
     * Get cached cities for a given state ID.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function getCachedByState(string|int|null $stateId): array
    {
        if (empty($stateId)) {
            return [];
        }

        $sId = (string) $stateId;

        return Cache::remember("cities_lookup_{$sId}", 86400, function () use ($sId) {
            $docs = static::raw(fn ($col) => $col->find(
                ['state_id' => ['$in' => [(int) $sId, (string) $sId]]],
                ['projection' => ['id' => 1, 'name' => 1], 'sort' => ['name' => 1]]
            ));

            $list = [];
            foreach ($docs as $doc) {
                if (isset($doc['id']) && isset($doc['name'])) {
                    $list[] = [
                        'id' => (string) $doc['id'],
                        'name' => (string) $doc['name'],
                    ];
                }
            }
            usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            return $list;
        });
    }
}
