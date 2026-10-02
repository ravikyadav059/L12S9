<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use MongoDB\Laravel\Auth\User as Authenticatable;
use MongoDB\Laravel\Relations\HasMany;
use MongoDB\Laravel\Relations\HasOne;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'fullname',
        'prefix',
        'email',
        'password',
        'role',
        'user_role',
        'is_admin',
        'type',
        'admin_permission',
        'status',
        'profile_status',
        'profile_type',
        'badge_type',
        'slug',
        'unique_id',
        'sequence_number',
        'mobile',
        'countrycode',
        'countryiso2',
        'photo',
        'affiliation',
        'scholar_profile',
        'publisher_name',
        'publisher_address',
        'publisher_city',
        'publisher_state',
        'publisher_country',
        'publisher_website',
        'publisher_organization',
        'publisher_logo',
        'publisher_status',
        'reviewer_status',
        'reviewer_date',
        'review_request_status',
        'followers',
        'following',
        'verified',
        'verification_request_status',
        'verification_rejection_reason',
        'verification_requested_at',
        'timezone',
        'last_login_with',
        'user_profile_count',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['avatar'];

    public $sortable = [
        'first_name',
        'last_name',
        'fullname',
        'email',
        'type',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_requested_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Always format and store email in lowercase.
     */
    public function setEmailAttribute(mixed $value): void
    {
        $this->attributes['email'] = ! empty($value) ? strtolower(trim((string) $value)) : $value;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->sequence_number)) {
                $maxSeq = (int) static::max('sequence_number');
                $user->sequence_number = $maxSeq + 1;
            }

            if (empty($user->unique_id)) {
                $seqStr = str_pad((string) $user->sequence_number, 7, '0', STR_PAD_LEFT);
                $user->unique_id = 'S9-'.date('mY').'-'.$seqStr;
            }

            if (empty($user->slug)) {
                $nameForSlug = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                if (empty($nameForSlug)) {
                    $nameForSlug = trim((string) ($user->fullname ?? 'author'));
                }
                $baseSlug = Str::slug($nameForSlug);
                if (empty($baseSlug)) {
                    $baseSlug = 'author';
                }
                $user->slug = $baseSlug.'-'.$user->unique_id;
            }
        });

        static::saving(function (User $user): void {
            if (! empty($user->email)) {
                $user->email = strtolower(trim((string) $user->email));
            }
        });
    }

    /**
     * Check if the user is an administrator (type=1, role=admin, admin_permission=1).
     */
    public function isAdmin(): bool
    {
        return (int) ($this->type ?? 0) === 1
            || ($this->type ?? '') === 'admin'
            || (bool) ($this->is_admin ?? false)
            || ($this->role ?? '') === 'admin'
            || ($this->user_role ?? '') === 'admin'
            || (bool) ($this->admin_permission ?? false);
    }

    /**
     * Get the user's display name from first_name and last_name.
     */
    public function getNameAttribute(): string
    {
        $combined = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        if (! empty($combined)) {
            return $combined;
        }

        if (! empty($this->fullname)) {
            return (string) $this->fullname;
        }

        return ! empty($this->email) ? Str::before($this->email, '@') : 'User';
    }

    /**
     * Get the user's avatar URL or generated SVG data URI.
     */
    public function getAvatarAttribute(): string
    {
        if (! empty($this->photo)) {
            if (Str::startsWith($this->photo, ['http://', 'https://', 'data:image/'])) {
                return $this->photo;
            }

            if (file_exists(public_path($this->photo))) {
                return asset($this->photo);
            }
        }

        $initials = $this->initials();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100%" height="100%"><rect width="100" height="100" fill="#198BEA" rx="50"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="Inter, system-ui, sans-serif" font-size="38" font-weight="700">'.htmlspecialchars($initials, ENT_QUOTES, 'UTF-8').'</text></svg>';

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }

    /**
     * Get the user's initials directly from first_name and last_name (1-2 uppercase characters, UTF-8 safe).
     */
    public function initials(): string
    {
        $first = trim((string) ($this->first_name ?? ''));
        $last = trim((string) ($this->last_name ?? ''));

        if (! empty($first) && ! empty($last)) {
            return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
        }

        if (! empty($first)) {
            return mb_strtoupper(mb_substr($first, 0, min(2, mb_strlen($first))));
        }

        if (! empty($last)) {
            return mb_strtoupper(mb_substr($last, 0, min(2, mb_strlen($last))));
        }

        if (! empty($this->fullname)) {
            $cleaned = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', (string) $this->fullname) ?: 'U');
            $words = array_values(array_filter(preg_split('/\s+/u', $cleaned) ?: []));

            if (count($words) >= 2) {
                return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1));
            }

            if (! empty($words)) {
                return mb_strtoupper(mb_substr($words[0], 0, min(2, mb_strlen($words[0]))));
            }
        }

        if (! empty($this->email)) {
            $emailPrefix = Str::before($this->email, '@');

            return mb_strtoupper(mb_substr($emailPrefix, 0, min(2, mb_strlen($emailPrefix))));
        }

        return 'U';
    }

    /**
     * Get the reviewer profile associated with the user.
     */
    public function reviewerProfile(): HasOne
    {
        return $this->hasOne(ReviewerProfile::class, 'user_id', '_id');
    }

    /**
     * Get the experience records associated with the user.
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class, 'user_id', '_id');
    }
}
