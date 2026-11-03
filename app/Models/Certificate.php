<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'certificate';

    protected $table = 'certificate';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'certificate',
        'certificate_no',
        'month_year',
        'description',
        'organizer',
        'event_name',
        'address',
        'status',
        'image',
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
            'status' => 'integer',
        ];
    }

    /**
     * The "booted" method of the model.
     * Standardizes status to integer (defaults to 1) and standardizes month_year.
     */
    protected static function booted(): void
    {
        static::saving(function (Certificate $certificate): void {
            if ($certificate->status === null || $certificate->status === '') {
                $certificate->status = 1;
            } else {
                $certificate->status = (int) $certificate->status;
            }

            if (! empty($certificate->month_year)) {
                $certificate->month_year = static::formatMonthYear($certificate->month_year);
            }
        });
    }

    /**
     * Scope to retrieve active certificate records (supports integer 1 and string '1').
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
     * Check if the certificate file is a PDF document.
     */
    public function getIsPdfAttribute(): bool
    {
        if (empty($this->image)) {
            return false;
        }

        return strtolower(pathinfo((string) $this->image, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Get the absolute public URL to the certificate file or document.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        $imagePath = (string) $this->image;

        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        if (file_exists(public_path($imagePath))) {
            return asset($imagePath);
        }

        if (file_exists(public_path('storage/'.$imagePath))) {
            return asset('storage/'.$imagePath);
        }

        return asset($imagePath);
    }

    /**
     * Get the clean base file name for display.
     */
    public function getFileNameAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        return basename((string) $this->image);
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

        // Allow "Present" / "Ongoing" when explicitly permitted
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

        // Fallback: try PHP strtotime
        $timestamp = strtotime($str);
        if ($timestamp !== false && $timestamp > 0) {
            return date('F Y', $timestamp);
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
