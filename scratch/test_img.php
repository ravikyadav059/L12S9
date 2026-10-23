<?php

use App\Models\IndexingAgency;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$agencies = IndexingAgency::getCachedActive();
foreach (array_slice($agencies, 0, 5) as $a) {
    echo $a['name'].' => '.$a['image']."\n";
}
