<?php

$content = file_get_contents(__DIR__.'/../resources/views/livewire/journal/show.blade.php');
$lines = explode("\n", $content);

foreach ($lines as $num => $line) {
    if ($num < 120) {
        continue;
    } // skip PHP block
    if (preg_match('/@\w+/', $line, $m)) {
        echo 'Line '.($num + 1).': '.trim($line)."\n";
    }
}
