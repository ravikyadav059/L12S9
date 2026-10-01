<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\IndexingAgency;
use Illuminate\Contracts\Console\Kernel;

$agencies = IndexingAgency::whereIn('status', [1, '1', true])->get(['agency_name', 'image', 'status']);
echo 'Total active agencies: '.$agencies->count()."\n";
$withImage = 0;
$withoutImage = 0;
foreach ($agencies as $a) {
    if (! empty($a->image)) {
        $withImage++;
        if ($withImage <= 10) {
            echo "Agency: {$a->agency_name} => Image: {$a->image}\n";
        }
    } else {
        $withoutImage++;
    }
}

echo "\nWith image: $withImage, Without image: $withoutImage\n";
