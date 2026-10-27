<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$k = $app->make(Illuminate\Contracts\Console\Kernel::class);
$k->bootstrap();
$u = App\Models\User::find('69afdddf75677d19bb0c4e83');
echo 'Found by id: ' . ($u ? $u->unique_id : 'NO') . PHP_EOL;
