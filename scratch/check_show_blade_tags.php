<?php

$content = file_get_contents(__DIR__.'/../resources/views/livewire/journal/show.blade.php');
$lines = explode("\n", $content);

$stack = [];
foreach ($lines as $num => $line) {
    $lineNum = $num + 1;
    if (preg_match('/@(if|foreach|forelse|unless|auth|guest|isset|empty)\b/', $line, $m)) {
        $stack[] = ['tag' => $m[1], 'line' => $lineNum, 'text' => trim($line)];
    }
    if (preg_match('/@(endif|endforeach|endforelse|endunless|endauth|endguest|endisset|endempty)\b/', $line, $m)) {
        $expected = 'end'.end($stack)['tag'];
        $last = array_pop($stack);
        if ($m[1] !== $expected) {
            echo "Mismatch at line $lineNum: found @{$m[1]}, expected @{$expected} (opened at line {$last['line']})\n";
        }
    }
}

echo 'Remaining unclosed tags: '.count($stack)."\n";
foreach ($stack as $s) {
    echo "Unclosed @{$s['tag']} from line {$s['line']}: {$s['text']}\n";
}
