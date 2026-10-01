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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     * Automatically standardizes join_date and end_date to "Month Year" format (e.g. "January 2026", "August 2015").
     */
    protected static function booted(): void
    {
        static::saving(function (Experience $experience): void {
            if (! empty($experience->join_date)) {
                $experience->join_date = static::formatMonthYear($experience->join_date);
            }

            if (! empty($experience->end_date)) {
                $experience->end_date = static::formatMonthYear($experience->end_date, true);
            }
        });
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
}
