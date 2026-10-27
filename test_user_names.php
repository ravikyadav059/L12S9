<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$k = $app->make(Illuminate\Contracts\Console\Kernel::class);
$k->bootstrap();

$users = App\Models\User::take(10)->get();
foreach ($users as $u) {
    echo "ID: {$u->unique_id} | name: " . json_encode($u->name) . " | first_name: " . json_encode($u->first_name) . " | last_name: " . json_encode($u->last_name) . " | fullname: " . json_encode($u->fullname) . " | prefix: " . json_encode($u->prefix) . "\n";
}
