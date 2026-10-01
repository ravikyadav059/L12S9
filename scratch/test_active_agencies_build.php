<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\IndexingAgency;
use App\Models\ReviewerJournal;
use Illuminate\Contracts\Console\Kernel;

$fillable = (new ReviewerJournal)->getFillable();
$checkboxes = array_filter($fillable, fn ($f) => str_ends_with($f, '_checkbox'));

// Build lookup map of normalized name => checkbox key
$cbMap = [];
foreach ($checkboxes as $cb) {
    $raw = str_replace('_checkbox', '', $cb);
    $normalized = strtolower(str_replace([' ', '_', '-', '.', '/', '(', ')', '\''], '', $raw));
    $cbMap[$normalized] = $cb;
}

$activeAgencies = IndexingAgency::whereIn('status', [1, '1', true])
    ->select(['agency_name', 'status', 'serial_number'])
    ->get();

$indexers = [];
foreach ($activeAgencies as $agency) {
    $name = trim($agency->agency_name);
    if ($name === '') {
        continue;
    }

    $normalized = strtolower(str_replace([' ', '_', '-', '.', '/', '(', ')', '\''], '', $name));
    $key = $cbMap[$normalized] ?? (str_replace(' ', '_', $name).'_checkbox');

    $indexers[] = [
        'key' => $key,
        'label' => $name,
    ];
}

usort($indexers, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

echo 'Total active indexing agencies in dropdown: '.count($indexers)."\n";
echo "First 5: \n";
print_r(array_slice($indexers, 0, 5));
