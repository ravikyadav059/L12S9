<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Seminar extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'seminar';

    protected $table = 'seminar';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'host_by',
        'month_year',
        'type_event',
        'street',
        'city',
        'state',
        'country',
        'zipcode',
        'description',
        'role',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     * Automatically standardizes month_year to "Month Year" format (e.g. "January 2026", "August 2015").
     */
    protected static function booted(): void
    {
        static::saving(function (Seminar $seminar): void {
            if (! empty($seminar->month_year)) {
                $seminar->month_year = static::formatMonthYear($seminar->month_year);
            }
        });
    }

    /**
     * Standardize any date input into strict "Month Year" format (e.g. "January 2026", "August 2015").
     * Mirrors the same logic used in the Experience model.
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

        // Allow "Present" / "Current" / "Ongoing" when explicitly permitted
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
     * Get the organization that hosted this seminar.
     */
    public function hostOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'host_by', '_id');
    }
}
