<?php

namespace App\Helpers;

use App\Models\Country;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TimezoneHelper
{
    /**
     * Common legacy/deprecated IANA timezone aliases that PHP's timezone_location_get returns "??" for.
     *
     * @var array<string, string>
     */
    protected static array $legacyAliases = [
        'Asia/Calcutta' => 'IN',
        'Asia/Katmandu' => 'NP',
        'Asia/Saigon' => 'VN',
        'Asia/Rangoon' => 'MM',
        'Asia/Dacca' => 'BD',
        'Asia/Thimbu' => 'BT',
        'Asia/Ulan_Bator' => 'MN',
        'Asia/Macao' => 'MO',
        'America/Buenos_Aires' => 'AR',
        'America/Cordoba' => 'AR',
        'America/Rosario' => 'AR',
        'America/Indianapolis' => 'US',
        'America/Louisville' => 'US',
        'Europe/Kiev' => 'UA',
    ];

    /**
     * Detect current timezone from authenticated user, cookie, or application default.
     */
    public static function getTimezone(): string
    {
        if (Auth::check() && ! empty(Auth::user()->timezone)) {
            return (string) Auth::user()->timezone;
        }

        $cookieTz = request()->cookie('app_timezone');
        if (! empty($cookieTz)) {
            return (string) $cookieTz;
        }

        return config('app.timezone', 'UTC');
    }

    /**
     * Get the 2-letter ISO country code from an IANA timezone string.
     *
     * @param  string|null  $timezone  e.g. "Asia/Kolkata", "America/New_York", "Asia/Calcutta"
     * @return string|null 2-letter uppercase ISO code e.g. "IN", "US", or null
     */
    public static function getCountryCodeByTimezone(?string $timezone = null): ?string
    {
        $tz = ! empty($timezone) ? trim($timezone) : static::getTimezone();

        if (empty($tz) || in_array(strtoupper($tz), ['UTC', 'GMT'])) {
            return null;
        }

        if (isset(static::$legacyAliases[$tz])) {
            return static::$legacyAliases[$tz];
        }

        return Cache::remember("tz_country_iso_{$tz}", 86400 * 30, function () use ($tz) {
            try {
                $dtz = new DateTimeZone($tz);
                $location = @timezone_location_get($dtz);

                if (! empty($location['country_code']) && $location['country_code'] !== '??') {
                    return strtoupper(trim((string) $location['country_code']));
                }
            } catch (\Throwable $e) {
                // Invalid or unsupported timezone string
            }

            return null;
        });
    }

    /**
     * Get the matching Country model for a given timezone.
     */
    public static function getCountryByTimezone(?string $timezone = null): ?Country
    {
        $iso2 = static::getCountryCodeByTimezone($timezone);

        if (empty($iso2)) {
            return null;
        }

        return Cache::remember("tz_country_model_{$iso2}", 86400 * 30, function () use ($iso2) {
            return Country::where('sortname', $iso2)->first();
        });
    }

    /**
     * Detect default country ID for the current request.
     */
    public static function detectDefaultCountryId(?string $timezone = null): ?string
    {
        // 1. If explicit timezone is passed, resolve it
        if (! empty($timezone)) {
            $country = static::getCountryByTimezone($timezone);
            if ($country && ! empty($country->id)) {
                return (string) $country->id;
            }
        }

        // 2. If authenticated user has country or timezone set, use it
        if (Auth::check()) {
            $user = Auth::user();
            if (! empty($user->country)) {
                return (string) $user->country;
            }
            if (! empty($user->timezone)) {
                $country = static::getCountryByTimezone($user->timezone);
                if ($country && ! empty($country->id)) {
                    return (string) $country->id;
                }
            }
        }

        // 3. Resolve country from cookie / default timezone
        $country = static::getCountryByTimezone();

        if ($country && ! empty($country->id)) {
            return (string) $country->id;
        }

        return null;
    }

    /**
     * Update user timezone and country upon login or auto-detection.
     */
    public static function updateUserLocation(User $user, ?string $timezone = null): void
    {
        $tz = ! empty($timezone) ? trim($timezone) : request()->cookie('app_timezone');

        if (empty($tz)) {
            return;
        }

        $country = static::getCountryByTimezone($tz);
        $updates = [];

        if (($user->timezone ?? '') !== $tz) {
            $updates['timezone'] = $tz;
        }

        if ($country) {
            if (($user->countryiso2 ?? '') !== $country->sortname) {
                $updates['countryiso2'] = (string) $country->sortname;
            }

            if (empty($user->country)) {
                $updates['country'] = (string) $country->id;
            }
        }

        $clientIp = request()->ip();
        if (! empty($clientIp) && ($user->last_login_with ?? '') !== $clientIp) {
            $updates['last_login_with'] = $clientIp;
        }

        if (! empty($updates)) {
            $user->update($updates);
        }
    }
}
