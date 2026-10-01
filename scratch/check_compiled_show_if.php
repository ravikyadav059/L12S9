
<?php

$content = file_get_contents(__DIR__.'/../storage/framework/views/61621e1ee8c91cc5021b674218c6c83a.php');
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
