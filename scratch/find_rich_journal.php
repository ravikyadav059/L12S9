
<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\ReviewerJournal;
use Illuminate\Contracts\Console\Kernel;

$j = ReviewerJournal::whereIn('status', [1, '1', true])
    ->whereNotNull('journal_description')
    ->where('journal_description', '!=', '')
    ->first();

if ($j) {
    echo "Rich Journal: {$j->journal_title} (slug: {$j->slug})\n";
    echo "Organization: {$j->organization_name}\n";
    echo 'Description preview: '.substr(strip_tags($j->journal_description), 0, 100)."...\n";
    echo 'Subjects: '.json_encode($j->journal_subjects)."\n";
    echo "Scopus: {$j->Scopus_checkbox}, DOAJ: {$j->DOAJ_checkbox}\n";
}
