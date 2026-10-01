<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\ReviewerJournal;
use Illuminate\Contracts\Console\Kernel;

$allRaw = ReviewerJournal::raw(function ($collection) {
    return $collection->distinct('journal_subjects');
});

$activeRaw = ReviewerJournal::raw(function ($collection) {
    return $collection->distinct('journal_subjects', ['status' => ['$in' => [1, '1', true]]]);
});

echo 'All distinct subjects: '.count($allRaw)."\n";
echo 'Active (status 1) distinct subjects: '.count($activeRaw)."\n";
