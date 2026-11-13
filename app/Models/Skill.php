<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Skill extends Eloquent
{
    public const AVAILABLE_CACHE_KEY = 'qa_available_skills';

    public const AVAILABLE_CACHE_TTL = 1800;

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
    ];

    /**
     * Retrieve the active skills used by the public question pages.
     *
     * @return list<string>
     */
    public static function getCachedAvailable(): array
    {
        return Cache::remember(self::AVAILABLE_CACHE_KEY, self::AVAILABLE_CACHE_TTL, function (): array {
            $skills = self::query()
                ->whereIn('skills_status', [1, '1'])
                ->limit(50)
                ->get()
                ->map(fn (self $skill): string => trim((string) ($skill->skills_title ?? $skill->name ?? $skill->title ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            return $skills !== [] ? $skills : [
                'React', 'Laravel', 'MongoDB', 'Python', 'TypeScript', 'Docker',
                'Data Analysis (STATA)', 'Visual Studio', 'PHP', 'JavaScript',
            ];
        });
    }
}
