<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Experience extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'experience';

    protected $table = 'experience';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'field_type',
        'designation',
        'join_date',
        'end_date',
        'description',
        'current_organization',
        'status',
        'post_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_organization' => 'integer',
            'status' => 'integer',
        ];
    }

    /**
     * The "booted" method of the model.
     * Automatically ensures status is saved as integer (defaulting to 1 if null)
     * and standardizes join_date and end_date to "Month Year" format.
     */
    protected static function booted(): void
    {
        static::saving(function (Experience $experience): void {
            if ($experience->status === null || $experience->status === '') {
                $experience->status = 0;
            } else {
                $experience->status = (int) $experience->status;
            }

            if (! empty($experience->join_date)) {
                $experience->join_date = static::formatMonthYear($experience->join_date);
            }

            if (! empty($experience->end_date)) {
                $experience->end_date = static::formatMonthYear($experience->end_date, true);
            }
        });
    }

    /**
     * Scope to retrieve active experience records (supports both native integer 1 and legacy string '1').
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [1, '1']);
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

        // Allow "Present" / "Current" / "Ongoing" for ongoing positions
        if ($allowPresent && in_array(strtolower($str), ['present', 'current', 'ongoing', 'till date', 'now'], true)) {
            return 'Present';
        }

        // Match already correct format like "January 2026", "August 2015"
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

        // Match DD/MM/YYYY or DD-MM-YYYY (day-first format)
        if (preg_match('/^(\d{1,2})[\/\-](\d{2})[\/\-](\d{4})$/', $str, $matches)) {
            $timestamp = mktime(0, 0, 0, (int) $matches[2], (int) $matches[1], (int) $matches[3]);
            if ($timestamp !== false && $timestamp > 0) {
                return date('F Y', $timestamp);
            }
        }

        // Match ordinal/prefixed-day formats: "18 th April 2016", "1st January 2020"
        if (preg_match('/\b(\d{1,2})(?:\s*(?:st|nd|rd|th))?\s+([a-zA-Z]+)\s+(\d{4})\b/i', $str, $matches)) {
            $timestamp = strtotime("1 {$matches[2]} {$matches[3]}");
            if ($timestamp !== false && $timestamp > 0) {
                return date('F Y', $timestamp);
            }
        }

        // Match range-like entries: take only the first recognisable month+year
        if (preg_match('/([a-zA-Z]+)[\s\-\.]+(?:[a-zA-Z]+[\s\-\.]+)?(\d{4})/', $str, $matches)) {
            $timestamp = strtotime("1 {$matches[1]} {$matches[2]}");
            if ($timestamp !== false && $timestamp > 0) {
                return date('F Y', $timestamp);
            }
        }

        // Fallback: try PHP strtotime (handles ISO strings, many English formats)
        $timestamp = strtotime($str);
        if ($timestamp !== false && $timestamp > 0) {
            return date('F Y', $timestamp);
        }

        // Truly unparseable — return null so the record is not re-queued infinitely
        return null;
    }

    /**
     * Check if this experience record represents a currently ongoing position.
     * Supports current_organization (1, "1", true) or end_date ("Present").
     */
    public function isCurrent(): bool
    {
        return in_array($this->current_organization, [1, '1', true], true)
            || strtolower(trim((string) ($this->end_date ?? ''))) === 'present';
    }

    /**
     * Get the user who owns this experience record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /**
     * Get the organization associated with this experience record.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', '_id');
    }

    /**
     * Get the designation associated with this experience record.
     */
    public function designationRelation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation', '_id');
    }

    /**
     * Alias for designation relationship.
     */
    public function designationModel(): BelongsTo
    {
        return $this->designationRelation();
    }

    /**
     * Convert any date string (e.g. "January 2025", "Jan, 2025", "2025-01") to an integer month value (year * 12 + month).
     */
    public static function parseMonthYearToValue(?string $dateStr): ?int
    {
        if (! $dateStr) {
            return null;
        }

        $trimmed = trim($dateStr);
        if ($trimmed === '' || strtolower($trimmed) === 'present') {
            return null;
        }

        $dt = \DateTime::createFromFormat('!F Y', $trimmed)
            ?: \DateTime::createFromFormat('!F, Y', $trimmed)
            ?: \DateTime::createFromFormat('!M Y', $trimmed)
            ?: \DateTime::createFromFormat('!M, Y', $trimmed)
            ?: \DateTime::createFromFormat('!Y-m', $trimmed)
            ?: \DateTime::createFromFormat('!m/Y', $trimmed);

        if (! $dt) {
            $timestamp = strtotime($trimmed);
            if ($timestamp !== false && $timestamp > 0) {
                $dt = (new \DateTime)->setTimestamp($timestamp);
            }
        }

        if ($dt) {
            $year = (int) $dt->format('Y');
            $month = (int) $dt->format('n');

            return ($year * 12) + $month;
        }

        return null;
    }

    /**
     * Check if two experience date ranges overlap.
     *
     * Rule:
     * - Overlapping by 2 or more months is an error (cannot work at two places simultaneously).
     * - Touching at exactly 1 month (e.g. job 1 ends Jan 2025, job 2 starts Jan 2025)
     *   is ALLOWED as a same-month transition (e.g., left Jan 15, joined Jan 16).
     */
    public static function checkDateOverlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        $startOverlap = max($startA, $startB);
        $endOverlap = min($endA, $endB);

        // No intersection
        if ($startOverlap > $endOverlap) {
            return false;
        }

        // Overlap of 2 or more months
        if ($startOverlap < $endOverlap) {
            return true;
        }

        // Exactly 1 month intersection ($startOverlap == $endOverlap == $m):
        // Allowed ONLY if one job ends in month $m and the other starts in month $m.
        $m = $startOverlap;
        $isValidTransition = ($endA === $m && $startB === $m) || ($endB === $m && $startA === $m);

        return ! $isValidTransition;
    }

    /**
     * Find any existing active experience record that date-overlaps with the given range.
     *
     * @param  User|string  $user
     * @param  int  $startMonthVal  (year * 12 + month)
     * @param  int  $endMonthVal  (year * 12 + month, or PHP_INT_MAX for current role)
     */
    public static function findOverlappingActiveExperience($user, int $startMonthVal, int $endMonthVal, ?string $excludeExpId = null): ?self
    {
        $userId = is_string($user) ? $user : (string) $user->_id;
        $reviewerProfile = is_string($user) ? ReviewerProfile::where('user_id', $userId)->first() : ($user->reviewerProfile ?? ReviewerProfile::where('user_id', $userId)->first());
        $expIds = (array) ($reviewerProfile->experience ?? []);

        $activeExperiences = self::where(function ($q) use ($userId, $expIds) {
            $q->where('user_id', $userId);
            if (! empty($expIds)) {
                $q->orWhereIn('_id', $expIds);
            }
        })
            ->whereIn('status', [1, '1', true])
            ->when($excludeExpId, fn ($q) => $q->where('_id', '!=', $excludeExpId))
            ->with(['designationRelation', 'organization'])
            ->get();

        foreach ($activeExperiences as $existingExp) {
            $existingStart = self::parseMonthYearToValue($existingExp->join_date);
            if (! $existingStart) {
                continue;
            }

            $existingEnd = $existingExp->isCurrent()
                ? PHP_INT_MAX
                : (self::parseMonthYearToValue($existingExp->end_date) ?? PHP_INT_MAX);

            if (self::checkDateOverlap($startMonthVal, $endMonthVal, $existingStart, $existingEnd)) {
                return $existingExp;
            }
        }

        return null;
    }
}
