<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Get cached states for a given country ID.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function getCachedByCountry(string|int|null $countryId): array
    {
        if (empty($countryId)) {
            return [];
        }

        $cId = (string) $countryId;

        return Cache::remember("states_lookup_{$cId}", 86400, function () use ($cId) {
            $docs = static::raw(fn ($col) => $col->find(
                ['country_id' => ['$in' => [(int) $cId, (string) $cId]]],
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
