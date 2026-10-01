<?php

$localPaths = [
    'public/uploads/indexingAgency',
    'public/uploads',
    'storage/app/public/uploads/indexingAgency',
];

foreach ($localPaths as $p) {
    echo "$p exists? ".(file_exists($p) ? 'YES' : 'NO')."\n";
}
