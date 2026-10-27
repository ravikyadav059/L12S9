<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$k = $app->make(Illuminate\Contracts\Console\Kernel::class);
$k->bootstrap();
$u = App\Models\User::where('unique_id', 'S9-032026-1006748')->first();
echo "ATTRIBUTES:\n";
print_r($u->getAttributes());
