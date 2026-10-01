<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;

$content = file_get_contents(__DIR__.'/../resources/views/livewire/journal/index.blade.php');
// Remove PHP block
$bladePart = preg_replace('/^<\?php.*?\?>/s', '', $content);

try {
    $compiled = Blade::compileString($bladePart);
    echo "Blade compiled successfully!\n";
    // Now evaluate syntax of compiled PHP
    $tokens = token_get_all('<?php '.$compiled);
    echo "Tokens parsed successfully!\n";
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n";
    echo $e->getTraceAsString()."\n";
}
