<?php

use App\Models\IndexingAgency;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

foreach (IndexingAgency::take(10)->get() as $a) {
    echo $a->agency_name.' => image: '.var_export($a->image, true)."\n";
}
