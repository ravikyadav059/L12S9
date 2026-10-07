<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$questions = DB::connection('mongodb')->table('question')->get();

$slugMap = [];
$titleMap = [];

foreach ($questions as $q) {
    $id = (string) ($q->_id ?? $q->id ?? (is_array($q) ? ($q['_id'] ?? $q['id'] ?? '') : ''));
    $slug = (string) ($q->slug ?? (is_array($q) ? ($q['slug'] ?? '') : ''));
    $title = trim((string) ($q->title ?? (is_array($q) ? ($q['title'] ?? '') : '')));
    $titleLower = strtolower($title);

    if ($slug !== '') {
        $slugMap[$slug][] = ['id' => $id, 'title' => $title, 'slug' => $slug];
    }
    if ($title !== '') {
        $titleMap[$titleLower][] = ['id' => $id, 'title' => $title, 'slug' => $slug];
    }
}

echo "=== DUPLICATE SLUGS ===\n";
foreach ($slugMap as $slug => $list) {
    if (count($list) > 1) {
        echo "Slug '{$slug}' appears ".count($list)." times:\n";
        foreach ($list as $item) {
            echo "   - ID: {$item['id']} | Title: {$item['title']}\n";
        }
    }
}

echo "\n=== DUPLICATE TITLES ===\n";
foreach ($titleMap as $title => $list) {
    if (count($list) > 1) {
        echo "Title '{$title}' appears ".count($list)." times:\n";
        foreach ($list as $item) {
            echo "   - ID: {$item['id']} | Slug: {$item['slug']}\n";
        }
    }
}
