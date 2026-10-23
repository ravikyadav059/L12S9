<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
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
     * Cache key for active journal roles.
     */
    public const CACHE_KEY = 'journal_roles:active';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            static::forgetCached();
        });

        static::deleted(function (): void {
            static::forgetCached();
        });
    }

    /**
     * Clear cached list of journal roles.
     */
    public static function forgetCached(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all role in research journals records associated with this role.
     */
    public function rolesInResearchJournals(): HasMany
    {
        return $this->hasMany(RoleInResearchJournals::class, 'role', '_id');
    }

    /**
     * Get cached list of active journal roles.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function getCachedActive(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        $roles = static::whereIn('status', [1, '1'])
            ->get()
            ->map(fn ($r) => [
                'id' => (string) ($r->_id ?? $r->id),
                'name' => (string) $r->name,
            ])
            ->values()
            ->toArray();

        if (! empty($roles)) {
            Cache::put(self::CACHE_KEY, $roles, 86400);
        }

        return $roles;
    }
}
