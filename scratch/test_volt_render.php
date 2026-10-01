<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Livewire\Volt\Volt;

try {
    Volt::test('journal.index');
    echo "Volt test passed!\n";
} catch (Throwable $e) {
    echo 'Volt test Error: '.$e->getMessage()."\n";
    echo 'In '.$e->getFile().':'.$e->getLine()."\n";
}
