<?php

use App\Helpers\TimezoneHelper;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::first();
Auth::login($user);

echo 'Logged in user: '.$user->email."\n";
echo 'User timezone: '.var_export($user->timezone, true)."\n";
echo 'User country: '.var_export($user->country, true)."\n";

$detectedTzCountry = TimezoneHelper::detectDefaultCountryId('Asia/Kolkata');
echo "Direct helper call ('Asia/Kolkata'): ".var_export($detectedTzCountry, true)."\n";

$component = Volt::test('admin.journals.create');
echo 'Component country before: '.var_export($component->get('country'), true)."\n";

$component->call('autoDetectCountry', 'Asia/Kolkata');
echo 'Component country after: '.var_export($component->get('country'), true)."\n";
