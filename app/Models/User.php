<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
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
        'user_role',
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
        'enable_conference',
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

        static::saved(function (): void {
            static::forgetCachedPublishers();
        });

        static::deleted(function (): void {
            static::forgetCachedPublishers();
        });
    }

    /**
     * Check if the user is an administrator.
     * Users with type=1 or "1" (even if status is 0 or "0") are treated as admin.
     * Users with type=0 or "0" are treated as simple users.
     */
    public function isAdmin(): bool
    {
        $type = $this->type ?? null;

        // If type is explicitly 0 or "0", the user is a simple user, not an admin
        if ($type === 0 || $type === '0') {
            return false;
        }

        // If type is 1 or "1", they are treated as admin (even if status is 0 or "0")
        if ($type === 1 || $type === '1' || (is_numeric($type) && (int) $type === 1) || (is_string($type) && strtolower($type) === 'admin')) {
            return true;
        }

        // Explicit is_admin boolean flag set by admin management
        if (! empty($this->is_admin) && filter_var($this->is_admin, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user is a simple/standard user (type=0, "0", or not an admin).
     */
    public function isSimpleUser(): bool
    {
        return ! $this->isAdmin();
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

    /**
     * Check if this user is following another user.
     */
    public function isFollowing(string|User|null $user): bool
    {
        if (empty($user)) {
            return false;
        }

        $targetId = $user instanceof self ? (string) ($user->_id ?? $user->id) : (string) $user;
        if ($targetId === '') {
            return false;
        }

        $following = is_array($this->following) ? array_map('strval', $this->following) : [];

        return in_array($targetId, $following, true);
    }

    /**
     * Check if this user is followed by another user.
     */
    public function isFollowedBy(string|User|null $user): bool
    {
        if (empty($user)) {
            return false;
        }

        $sourceId = $user instanceof self ? (string) ($user->_id ?? $user->id) : (string) $user;
        if ($sourceId === '') {
            return false;
        }

        $followers = is_array($this->followers) ? array_map('strval', $this->followers) : [];

        return in_array($sourceId, $followers, true);
    }

    /**
     * Follow another user (stores target ID in this user's following array, and this ID in target user's followers array).
     */
    public function follow(string|User $user): bool
    {
        $targetUser = $user instanceof self ? $user : static::find($user);
        if (! $targetUser) {
            return false;
        }

        $authId = (string) ($this->_id ?? $this->id);
        $targetId = (string) ($targetUser->_id ?? $targetUser->id);

        if ($authId === '' || $targetId === '' || $authId === $targetId) {
            return false;
        }

        $myFollowing = is_array($this->following) ? array_map('strval', $this->following) : [];
        if (! in_array($targetId, $myFollowing, true)) {
            $myFollowing[] = $targetId;
            $this->following = array_values($myFollowing);
            $this->save();
        }

        $theirFollowers = is_array($targetUser->followers) ? array_map('strval', $targetUser->followers) : [];
        if (! in_array($authId, $theirFollowers, true)) {
            $theirFollowers[] = $authId;
            $targetUser->followers = array_values($theirFollowers);
            $targetUser->save();
        }

        return true;
    }

    /**
     * Unfollow another user (removes target ID from this user's following array, and this ID from target user's followers array).
     */
    public function unfollow(string|User $user): bool
    {
        $targetUser = $user instanceof self ? $user : static::find($user);
        if (! $targetUser) {
            return false;
        }

        $authId = (string) ($this->_id ?? $this->id);
        $targetId = (string) ($targetUser->_id ?? $targetUser->id);

        if ($authId === '' || $targetId === '') {
            return false;
        }

        $myFollowing = is_array($this->following) ? array_map('strval', $this->following) : [];
        $this->following = array_values(array_diff($myFollowing, [$targetId]));
        $this->save();

        $theirFollowers = is_array($targetUser->followers) ? array_map('strval', $targetUser->followers) : [];
        $targetUser->followers = array_values(array_diff($theirFollowers, [$authId]));
        $targetUser->save();

        return true;
    }

    /**
     * Toggle follow status for another user.
     * Returns true if now following, false if unfollowed.
     */
    public function toggleFollow(string|User $user): bool
    {
        if ($this->isFollowing($user)) {
            $this->unfollow($user);

            return false;
        }

        $this->follow($user);

        return true;
    }

    /**
     * Total following count.
     */
    public function followingCount(): int
    {
        return is_array($this->following) ? count($this->following) : 0;
    }

    /**
     * Total followers count.
     */
    public function followersCount(): int
    {
        return is_array($this->followers) ? count($this->followers) : 0;
    }

    /**
     * Cache key for active publishers.
     */
    public const CACHE_KEY_PUBLISHERS = 'publishers:initial';

    /**
     * Get initial list of active publishers (limited for scale and performance).
     *
     * @return array<int, array{id: string, name: string, email: string}>
     */
    public static function getInitialPublishers(int $limit = 20, ?string $selectedId = null): array
    {
        $cached = Cache::get(self::CACHE_KEY_PUBLISHERS);

        if (is_array($cached) && ! empty($cached)) {
            $list = $cached;
        } else {
            $list = static::whereIn('status', [1, '1'])
                ->whereIn('publisher_status', [1, '1'])
                ->whereNotNull('slug')
                ->select('_id', 'first_name', 'last_name', 'fullname', 'email')
                ->limit(50)
                ->get()
                ->map(function ($u) {
                    $fullName = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                    if ($fullName === '' && ! empty($u->fullname)) {
                        $fullName = trim((string) $u->fullname);
                    }

                    return [
                        'id' => (string) $u->_id,
                        'name' => $fullName !== '' ? $fullName : 'Publisher',
                        'email' => (string) ($u->email ?? ''),
                    ];
                })
                ->values()
                ->toArray();

            if (! empty($list)) {
                Cache::put(self::CACHE_KEY_PUBLISHERS, $list, 3600);
            }
        }

        $result = array_slice($list, 0, $limit);

        if (! empty($selectedId)) {
            $alreadyPresent = false;
            foreach ($result as $item) {
                if ($item['id'] === (string) $selectedId) {
                    $alreadyPresent = true;
                    break;
                }
            }

            if (! $alreadyPresent) {
                foreach ($list as $item) {
                    if ($item['id'] === (string) $selectedId) {
                        array_unshift($result, $item);
                        $alreadyPresent = true;
                        break;
                    }
                }

                if (! $alreadyPresent) {
                    $selectedUser = static::find($selectedId);
                    if ($selectedUser) {
                        $fullName = trim(($selectedUser->first_name ?? '').' '.($selectedUser->last_name ?? ''));
                        if ($fullName === '' && ! empty($selectedUser->fullname)) {
                            $fullName = trim((string) $selectedUser->fullname);
                        }
                        array_unshift($result, [
                            'id' => (string) $selectedUser->_id,
                            'name' => $fullName !== '' ? $fullName : 'Publisher',
                            'email' => (string) ($selectedUser->email ?? ''),
                        ]);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Search active publishers dynamically on-demand with debounced queries.
     *
     * @return array<int, array{id: string, name: string, email: string}>
     */
    public static function searchPublishers(string $query = '', int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return static::getInitialPublishers($limit);
        }

        return static::whereIn('status', [1, '1'])
            ->whereIn('publisher_status', [1, '1'])
            ->whereNotNull('slug')
            ->where(function ($sub) use ($query) {
                $sub->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('fullname', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->select('_id', 'first_name', 'last_name', 'fullname', 'email')
            ->limit($limit)
            ->get()
            ->map(function ($u) {
                $fullName = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                if ($fullName === '' && ! empty($u->fullname)) {
                    $fullName = trim((string) $u->fullname);
                }

                return [
                    'id' => (string) $u->_id,
                    'name' => $fullName !== '' ? $fullName : 'Publisher',
                    'email' => (string) ($u->email ?? ''),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get cached list of active publishers.
     *
     * @return array<int, array{id: string, name: string, email: string}>
     */
    public static function getCachedPublishers(): array
    {
        return static::getInitialPublishers(50);
    }

    /**
     * Clear cached list of publishers from cache/redis.
     */
    public static function forgetCachedPublishers(): void
    {
        Cache::forget(self::CACHE_KEY_PUBLISHERS);
    }

    /**
     * Get unique scholar profile slug for routing (/profile/{slug}).
     */
    public function profileSlug(): string
    {
        if (! empty($this->slug)) {
            return (string) $this->slug;
        }

        if (! empty($this->unique_id)) {
            $nameForSlug = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
            if (empty($nameForSlug)) {
                $nameForSlug = trim((string) ($this->fullname ?? ($this->name ?? 'author')));
            }
            $base = Str::slug($nameForSlug) ?: 'author';

            return $base.'-'.$this->unique_id;
        }

        return (string) $this->_id;
    }

    /**
     * Get status integer code: 0=Pending, 1=Active, 2=Re-Document, 3=Reject.
     */
    public function getStatusCode(): int
    {
        $raw = strtolower(trim((string) ($this->status ?? '0')));

        return match ($raw) {
            '1', 'active' => 1,
            '2', 'redocument', 're-document' => 2,
            '3', 'reject', 'rejected' => 3,
            default => 0,
        };
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabel(): string
    {
        return match ($this->getStatusCode()) {
            1 => 'Active',
            2 => 'Re-Document',
            3 => 'Reject',
            default => 'Pending',
        };
    }
}
