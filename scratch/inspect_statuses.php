<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\IndexingAgency;
use App\Models\ReviewerJournal;
use Illuminate\Contracts\Console\Kernel;

$agencyStatuses = IndexingAgency::raw(fn ($c) => $c->distinct('status'));
echo "IndexingAgency distinct statuses:\n";
print_r($agencyStatuses);

$agencyTotal = IndexingAgency::count();
$agencyActive = IndexingAgency::whereIn('status', [1, '1', true])->count();
echo "IndexingAgency total: $agencyTotal, active: $agencyActive\n";

$journalStatuses = ReviewerJournal::raw(fn ($c) => $c->distinct('status'));
echo "ReviewerJournal distinct statuses:\n";
print_r($journalStatuses);

$journalTotal = ReviewerJournal::count();
$journalActive = ReviewerJournal::whereIn('status', [1, '1', true])->count();
echo "ReviewerJournal total: $journalTotal, active: $journalActive\n";
