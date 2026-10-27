<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('unique_id', 'S9-032026-1006748')->first();
if (!$user) {
    echo "USER NOT FOUND\n";
    exit;
}
echo "USER FOUND: {$user->unique_id}, first_name: '{$user->first_name}', fullname: '{$user->fullname}', email: '{$user->email}'\n";

$test = Livewire\Livewire::test('admin.users.edit', ['user' => $user]);
echo "Component values:\n";
echo "first_name: " . json_encode($test->get('first_name')) . "\n";
echo "last_name: " . json_encode($test->get('last_name')) . "\n";
echo "fullname: " . json_encode($test->get('fullname')) . "\n";
echo "email: " . json_encode($test->get('email')) . "\n";
echo "prefix: " . json_encode($test->get('prefix')) . "\n";

// Let's also check the rendered HTML for input first_name
$html = $test->html();
preg_match('/<input[^>]*wire:model="first_name"[^>]*>/i', $html, $matches);
echo "HTML for first_name: " . ($matches[0] ?? 'NOT FOUND') . "\n";
preg_match('/<input[^>]*wire:model="email"[^>]*>/i', $html, $matchesEmail);
echo "HTML for email: " . ($matchesEmail[0] ?? 'NOT FOUND') . "\n";
