<?php

$content = file_get_contents(__DIR__.'/../storage/framework/views/f14b402af58d65245c1da1af9ed0c158.php');
$lines = explode("\n", $content);

$stack = [];
foreach ($lines as $idx => $line) {
    $lineNum = $idx + 1;
    if (preg_match('/<\?php\s+if\s*\(/', $line)) {
        $stack[] = ['line' => $lineNum, 'text' => trim($line)];
    }
    if (preg_match('/<\?php\s+endif;/', $line)) {
        array_pop($stack);
    }
}

echo 'Unclosed PHP if count: '.count($stack)."\n";
foreach ($stack as $s) {
    echo "Line {$s['line']}: {$s['text']}\n";
}
