<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$k = $app->make(Illuminate\Contracts\Console\Kernel::class);
$k->bootstrap();

$user = (new App\Models\User)->resolveRouteBinding('S9-032026-1006748', 'unique_id');
echo "resolveRouteBinding with unique_id: " . ($user ? $user->unique_id : 'NULL') . "\n";

$user2 = (new App\Models\User)->resolveRouteBinding('S9-032026-1006748');
echo "resolveRouteBinding default (null field): " . ($user2 ? $user2->unique_id : 'NULL') . "\n";
