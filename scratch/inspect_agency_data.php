<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\IndexingAgency;
use Illuminate\Contracts\Console\Kernel;

$agencies = IndexingAgency::whereIn('status', [1, '1', true])->take(10)->get();
foreach ($agencies as $a) {
    echo "ID: {$a->_id} | Name: {$a->agency_name} | Status: {$a->status} | Image: {$a->image} | Serial: {$a->serial_number}\n";
}
