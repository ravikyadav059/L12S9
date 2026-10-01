<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\IndexingAgency;
use App\Models\ReviewerJournal;
use Illuminate\Contracts\Console\Kernel;

$agencies = IndexingAgency::whereIn('status', [1, '1', true])->orderBy('serial_number')->get();
$journalFillable = (new ReviewerJournal)->getFillable();
$checkboxes = array_filter($journalFillable, fn ($f) => str_ends_with($f, '_checkbox'));

echo 'Active Indexing Agencies count: '.$agencies->count()."\n";
echo 'ReviewerJournal checkbox count: '.count($checkboxes)."\n\n";

$matched = 0;
$unmatched = [];

foreach ($agencies as $a) {
    $rawName = trim($a->agency_name);
    // test various field keys
    $candidate1 = str_replace(' ', '_', $rawName).'_checkbox';
    $candidate2 = $rawName.'_checkbox';
    $found = null;
    foreach ($checkboxes as $cb) {
        if (strcasecmp($cb, $candidate1) === 0 || strcasecmp($cb, $candidate2) === 0 || strcasecmp(str_replace('_checkbox', '', $cb), str_replace([' ', '_', '-'], '', $rawName)) === 0) {
            $found = $cb;
            break;
        }
    }
    if ($found) {
        $matched++;
    } else {
        $unmatched[] = $rawName;
    }
}

echo "Matched agencies to ReviewerJournal checkboxes: $matched / ".$agencies->count()."\n";
if (! empty($unmatched)) {
    echo 'Unmatched samples: '.implode(', ', array_slice($unmatched, 0, 10))."\n";
}
