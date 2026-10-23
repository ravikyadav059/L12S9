<?php

$html = file_get_contents('scratch/rendered_create.html');
echo 'Has 1. Journal Core Details: '.(strpos($html, '1. Journal Core Details') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'Has 2. ISSN: '.(strpos($html, '2. ISSN') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'Has 3. Disciplines: '.(strpos($html, '3. Disciplines') !== false ? 'YES' : 'NO').PHP_EOL;

// Check where 2. ISSN is
$p1 = strpos($html, '1. Journal Core Details');
$p2 = strpos($html, '2. ISSN');
$p3 = strpos($html, '3. Disciplines');
echo "Pos 1: $p1, Pos 2: $p2, Pos 3: $p3".PHP_EOL;

// What is between Pos 1 and Pos 2?
echo 'Length between 1 and 2: '.($p2 - $p1).PHP_EOL;
echo 'Length between 2 and 3: '.($p3 - $p2).PHP_EOL;
