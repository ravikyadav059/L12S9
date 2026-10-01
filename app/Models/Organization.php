<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'organization';

    protected $table = 'organization';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_name',
        'address',
        'city',
        'state',
        'country',
        'zipcode',
        'affiliated_university',
        'website_url',
        'organization_type',
        'status',
        'logo',
        'serial_number',
        'slug',
        'description',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            // Auto-generate unique serial_number if not set
            if (empty($organization->serial_number)) {
                $maxSerial = (int) static::max('serial_number');
                if ($maxSerial <= 0) {
                    $lastRecord = static::whereNotNull('serial_number')
                        ->orderBy('serial_number', 'desc')
                        ->first();
                    $maxSerial = $lastRecord && is_numeric($lastRecord->serial_number)
                        ? (int) $lastRecord->serial_number
                        : 0;
                }
                $organization->serial_number = $maxSerial + 1;
            }

            // Auto-generate slug based on organization_name and serial_number
            if (empty($organization->slug)) {
                $baseSlug = Str::slug($organization->organization_name ?? '');
                $organization->slug = $baseSlug !== ''
                    ? "{$baseSlug}-{$organization->serial_number}"
                    : (string) $organization->serial_number;
            }

            // Default status to 1 if not explicitly provided
            if (! isset($organization->status)) {
                $organization->status = 1;
            }
        });
    }

    /**
     * Scope a query to only include active organizations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [1, '1', true]);
    }
}
