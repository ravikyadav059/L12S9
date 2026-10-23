<?php

use App\Models\Country;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$items = Country::getCachedAll();
foreach ($items as $item) {
    if ($item['name'] === 'India') {
        echo 'India item: '.json_encode($item)."\n";
        break;
    }
}
