<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('unique_id', 'S9-032026-1006748')->first();
$response = Livewire\Livewire::test('admin.users.edit', ['user' => $user]);

$html = $response->html();
file_put_contents('scratch_route_response.html', $html);

if (preg_match('/wire:snapshot="([^"]+)"/', $html, $m)) {
    $snapshotJson = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    $snapshot = json_decode($snapshotJson, true);
    echo "Snapshot data:\n";
    print_r($snapshot['data'] ?? 'No data in snapshot');
} else {
    echo "No wire:snapshot found in response\n";
}
