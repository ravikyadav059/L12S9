<?php

namespace App\Models;

use MongoDB\BSON\Regex;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Research extends Eloquent
{
    protected $connection = 'mongodb';

    protected $collection = 'research';

    protected $table = 'research';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'research_title',
        'research_status',
        'submission_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'research_status' => 'integer',
        'submission_count' => 'integer',
    ];

    /**
     * Record usage for a research interest:
     * - If exists: increment submission_count. If status is 0 and submissions >= 3, promote status to 1.
     * - If not exists: create with status 0 and submission_count 1.
     */
    public static function recordUsage(string $title): self
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Research title cannot be empty.');
        }

        // Case-insensitive exact match
        $existing = self::where('research_title', 'regex', new Regex('^'.preg_quote($title).'$', 'i'))->first();

        if ($existing) {
            $newCount = ((int) ($existing->submission_count ?? 1)) + 1;
            $existing->submission_count = $newCount;

            // If status is 0 and submitted 3 or more times, auto-promote to 1
            if ((int) ($existing->research_status ?? 0) === 0 && $newCount >= 3) {
                $existing->research_status = 1;
            }

            $existing->save();

            return $existing;
        }

        return self::create([
            'research_title' => $title,
            'research_status' => 0,
            'submission_count' => 1,
        ]);
    }

    /**
     * High-scale optimized search for active research interests.
     *
     * @param  list<string>  $exclude
     * @return list<array{id: string, name: string}>
     */
    public static function searchActive(string $query = '', array $exclude = [], int $limit = 15): array
    {
        $builder = self::query()
            ->where('research_status', 1)
            ->select(['_id', 'research_title', 'submission_count']);

        if ($query !== '') {
            $builder->where('research_title', 'regex', new Regex(preg_quote($query), 'i'));
        }

        if (! empty($exclude)) {
            // Case-insensitive exclusion check
            $builder->whereNotIn('research_title', $exclude);
        }

        $records = $builder->orderBy('submission_count', 'desc')
            ->orderBy('research_title', 'asc')
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($records as $record) {
            $title = (string) $record->research_title;
            if ($title !== '') {
                $results[] = [
                    'id' => (string) $record->_id,
                    'name' => $title,
                ];
            }
        }

        return $results;
    }
}
