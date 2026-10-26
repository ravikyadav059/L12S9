<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\Regex;
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

    /**
     * Cache key for active indexing agencies.
     */
    public const CACHE_KEY = 'indexing_agencies:active';

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
     * Clear cached list of indexing agencies.
     */
    public static function forgetCached(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get cached list of all active indexing agencies with formatted keys.
     *
     * @return array<int, array{name: string, key: string, image: ?string}>
     */
    public static function getCachedActive(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        $items = static::whereIn('status', [1, '1'])
            ->select('agency_name', 'image', 'serial_number')
            ->orderBy('serial_number')
            ->get()
            ->map(function ($item) {
                $key = str_replace(' ', '_', trim((string) $item->agency_name));

                return [
                    'name' => (string) $item->agency_name,
                    'key' => $key,
                    'image' => $item->image ? asset($item->image) : null,
                ];
            })
            ->values()
            ->toArray();

        if (! empty($items)) {
            Cache::put(self::CACHE_KEY, $items, 86400);
        }

        return $items;
    }

    /**
     * Search active indexing agencies by query string.
     *
     * @return array<int, array{name: string, key: string, image: ?string}>
     */
    public static function searchAgencies(string $query = '', int $limit = 50): array
    {
        $all = static::getCachedActive();
        $q = trim(strtolower($query));
        if ($q === '') {
            return array_slice($all, 0, $limit);
        }

        $matches = array_values(array_filter($all, function ($agency) use ($q) {
            return str_contains(strtolower($agency['name']), $q)
                || str_contains(strtolower($agency['key']), $q);
        }));

        return array_slice($matches, 0, $limit);
    }

    /**
     * Get the resolved URL for the agency image icon.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return asset($this->image);
    }

    /**
     * Get the next serial number.
     */
    public static function getNextSerialNumber(): int
    {
        $max = static::all()->max(fn ($agency) => (int) $agency->serial_number) ?? 0;

        return max((int) $max + 1, 1);
    }

    /**
     * Check if a serial number is already assigned.
     */
    public static function isSerialNumberTaken(int $serialNumber, ?string $excludeId = null): bool
    {
        $query = static::where(function ($q) use ($serialNumber) {
            $q->where('serial_number', $serialNumber)
                ->orWhere('serial_number', (string) $serialNumber);
        });

        if ($excludeId) {
            $query->where('_id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Generate guaranteed next unique serial number.
     */
    public static function generateUniqueSerialNumber(?string $excludeId = null): int
    {
        $next = static::getNextSerialNumber();
        while (static::isSerialNumberTaken($next, $excludeId)) {
            $next++;
        }

        return $next;
    }

    /**
     * Check if an agency name already exists (case-insensitive).
     */
    public static function isNameTaken(string $name, ?string $excludeId = null): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return false;
        }

        $query = static::where('agency_name', 'regex', new Regex('^'.preg_quote($trimmed, '/').'$', 'i'));

        if ($excludeId) {
            $query->where('_id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
