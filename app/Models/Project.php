<?php

namespace App\Models;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Project extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'projects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'project_name',
        'category_id',
        'project_url',
        'start_month_year',
        'end_month_year',
        'not_ended_yet',
        'funded_project',
        'registered_contributors',
        'unregistered_contributors',
        'agency_name',
        'funded_amount',
        'description',
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
            'not_ended_yet' => 'integer',
            'status' => 'integer',
        ];
    }

    /**
     * The "booted" method of the model.
     * Ensures status is numeric and normalizes start and end month years.
     */
    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            if ($project->status === null || $project->status === '') {
                $project->status = 1;
            } else {
                $project->status = (int) $project->status;
            }

            if (! empty($project->start_month_year)) {
                $project->start_month_year = static::formatMonthYear($project->start_month_year);
            }

            if (! empty($project->end_month_year)) {
                $project->end_month_year = static::formatMonthYear($project->end_month_year, true);
            }
        });
    }

    /**
     * Scope to retrieve active project records (supports integer 1 and string '1').
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    /**
     * Owner user relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /**
     * Check if project is ongoing / not ended yet.
     */
    public function isNotEnded(): bool
    {
        return in_array($this->not_ended_yet, [1, '1', true], true)
            || strtolower(trim((string) ($this->end_month_year ?? ''))) === 'present';
    }

    /**
     * Check if project is funded.
     */
    public function isFunded(): bool
    {
        $val = strtolower(trim((string) ($this->funded_project ?? '')));

        return in_array($val, ['yes', '1', 'true'], true);
    }

    /**
     * Get array of registered contributor user IDs.
     *
     * @return list<string>
     */
    public function getRegisteredContributorIds(): array
    {
        $val = $this->registered_contributors;
        if (is_array($val)) {
            return array_values(array_filter(array_map('strval', $val)));
        }
        if (is_string($val) && trim($val) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $val))));
        }

        return [];
    }

    /**
     * Get array of unregistered / external contributor names.
     *
     * @return list<string>
     */
    public function getUnregisteredContributorNames(): array
    {
        $val = $this->unregistered_contributors;
        if (is_array($val)) {
            return array_values(array_filter(array_map('strval', $val)));
        }
        if (is_string($val) && trim($val) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $val))));
        }

        return [];
    }

    /**
     * Standardize any date input into strict "Month Year" format (e.g. "January 2026", "August 2015").
     */
    public static function formatMonthYear(mixed $value, bool $allowPresent = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('F Y');
        }

        if ($value instanceof UTCDateTime) {
            return $value->toDateTime()->format('F Y');
        }

        $str = trim((string) $value);
        if ($str === '' || strtolower($str) === 'null' || $str === '0000-00-00' || $str === '0') {
            return null;
        }

        // Allow "Present" / "Ongoing" for ongoing projects
        if ($allowPresent && in_array(strtolower($str), ['present', 'current', 'ongoing', 'till date', 'now'], true)) {
            return 'Present';
        }

        // Match "January 2026", "August 2015"
        if (preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})$/i', $str, $matches)) {
            $month = ucfirst(strtolower($matches[1]));

            return "{$month} {$matches[2]}";
        }

        // Match "Aug 2015", "Aug, 2015", "August, 2015"
        if (preg_match('/^([a-zA-Z]+)[,\s]+(\d{4})$/', $str, $matches)) {
            $timestamp = strtotime("1 {$matches[1]} {$matches[2]}");
            if ($timestamp !== false) {
                return date('F Y', $timestamp);
            }
        }

        // Match MM/YYYY or MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $str, $matches)) {
            $timestamp = strtotime("{$matches[2]}-{$matches[1]}-01");
            if ($timestamp !== false) {
                return date('F Y', $timestamp);
            }
        }

        // Match YYYY-MM or YYYY/MM
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/', $str, $matches)) {
            $timestamp = strtotime("{$matches[1]}-{$matches[2]}-01");
            if ($timestamp !== false) {
                return date('F Y', $timestamp);
            }
        }

        $parsed = strtotime($str);
        if ($parsed !== false) {
            return date('F Y', $parsed);
        }

        return $str;
    }

    /**
     * Parse date string into a Carbon instance anchored at the first day of that month.
     */
    public static function parseCarbonDate(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        $clean = trim(str_replace(',', '', $value));
        if ($clean === '' || in_array(strtolower($clean), ['present', 'current', 'ongoing', 'null'], true)) {
            return null;
        }

        try {
            return Carbon::parse($clean)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}
