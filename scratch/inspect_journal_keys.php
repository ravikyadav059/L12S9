<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\ReviewerJournal;
use Illuminate\Contracts\Console\Kernel;

$j = ReviewerJournal::whereIn('status', [1, '1', true])->whereNotNull('slug')->first();
if ($j) {
    echo "Journal: {$j->journal_title} (slug: {$j->slug})\n";
    $keys = array_keys($j->toArray());
    echo 'Keys in document: '.implode(', ', $keys)."\n";
}
