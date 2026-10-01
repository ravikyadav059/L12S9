<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Education extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'education';

    protected $table = 'education';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'degree_type',
        'field_of_study',
        'passout_year',
        'pursuing',
        'description',
        'grade',
        'institution_id',
        'current_institute',
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
            'current_institute' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     * Automatically standardizes passout_year to a 4-digit year format (e.g. "2024").
     */
    protected static function booted(): void
    {
        static::saving(function (Education $education): void {
            if (! empty($education->passout_year)) {
                $education->passout_year = static::formatYear($education->passout_year);
            }
        });
    }

    /**
     * Standardize any year input into strict 4-digit year format (e.g. "2024").
     */
    public static function formatYear(mixed $value, bool $allowPursuing = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y');
        }

        if ($value instanceof UTCDateTime) {
            return $value->toDateTime()->format('Y');
        }

        $str = trim((string) $value);
        if ($str === '' || strtolower($str) === 'null' || $str === '0000' || $str === '0' || $str === '-----') {
            return null;
        }

        // Allow "Present" / "Current" / "Ongoing" / "Pursuing" / "Appearing" when explicitly permitted
        if ($allowPursuing && in_array(strtolower($str), ['pursuing', 'ongoing', 'present', 'current', 'appearing', 'till date', 'now'], true)) {
            return 'Pursuing';
        }

        // Remove internal spaces e.g. "19 96" -> "1996"
        $clean = str_replace(' ', '', $str);

        // Exact 4-digit year e.g. 1969 to 2099
        if (preg_match('/^(19\d{2}|20\d{2})$/', $clean)) {
            return $clean;
        }

        // Match 5-digit typo like 19996 -> take the first 4 if starts with 19 or 20
        if (preg_match('/^(19\d{2}|20\d{2})\d+$/', $clean, $matches)) {
            return $matches[1];
        }

        // Match year in date formats like MM/YYYY, DD/MM/YYYY, YYYY-MM-DD
        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $str, $matches)) {
            return $matches[1];
        }

        // Fallback: try PHP strtotime
        $timestamp = strtotime($str);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y', $timestamp);
        }

        return null;
    }

    /**
     * Get the institution / organization associated with this education record.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'institution_id', '_id');
    }

    /**
     * Get the degree type associated with this education record.
     */
    public function degreeType(): BelongsTo
    {
        return $this->belongsTo(DegreeType::class, 'degree_type', '_id');
    }

    /**
     * Get the grade associated with this education record.
     */
    public function gradeRelation(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade', '_id');
    }
}
