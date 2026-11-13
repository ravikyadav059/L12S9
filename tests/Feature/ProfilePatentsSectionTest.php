<?php

use App\Models\Category;
use App\Models\Patent;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

test('category model caches active categories for 1 week and invalidates on save or delete', function () {
    // Clear cache initially
    Category::forgetCached();
    expect(Cache::has(Category::CACHE_KEY))->toBeFalse();

    $category = Category::create([
        'category_title' => 'Quantum Computing & Algorithms',
        'category_status' => 1,
    ]);

    // Initial retrieval caches the list
    $cached = Category::getCachedAll();
    expect(Cache::has(Category::CACHE_KEY))->toBeTrue();
    expect(collect($cached)->firstWhere('name', 'Quantum Computing & Algorithms'))->not->toBeNull();

    // Updating category invalidates the cache
    $category->category_title = 'Quantum Computing & Information Theory';
    $category->save();

    expect(Cache::has(Category::CACHE_KEY))->toBeFalse();

    // Re-fetch populates updated cache
    $reloaded = Category::getCachedAll();
    expect(collect($reloaded)->firstWhere('name', 'Quantum Computing & Information Theory'))->not->toBeNull();

    // Deleting category invalidates the cache
    $category->delete();
    expect(Cache::has(Category::CACHE_KEY))->toBeFalse();
});

test('profile patents component renders patent records', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'category_title' => 'Artificial Intelligence & Neural Nets',
        'category_status' => 1,
    ]);

    $patent = Patent::create([
        'user_id' => (string) $user->_id,
        'title' => 'Self-Supervised Neural Code Synthesis Architecture',
        'patent_no' => 'US 11,456,789 B2',
        'inventor' => 'Dr. Alan Turing, Ada Lovelace',
        'assignee' => 'DeepMind Technologies',
        'application_no' => '17/456,123',
        'country' => 'United States',
        'category_id' => (string) $category->_id,
        'filing_date' => 'October 2021',
        'publication_date' => 'April 2023',
        'description' => 'A system and method for generating executable AST nodes from latent space representations.',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'patent' => [(string) $patent->_id],
    ]);

    $component = Volt::test('profile.patents', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    $component->assertStatus(200);
    $component->assertSee('Patents');
    $component->assertSee('Self-Supervised Neural Code Synthesis Architecture');
    $component->assertSee('US 11,456,789 B2');
    $component->assertSee('Dr. Alan Turing, Ada Lovelace');
    $component->assertSee('DeepMind Technologies');
    $component->assertSee('17/456,123');
    $component->assertSee('United States');
    $component->assertSee('October 2021');
    $component->assertSee('April 2023');
    $component->assertSee('Artificial Intelligence &amp; Neural Nets', false);
    $component->assertSee('Description:');
});

test('profile patents component allows creating, editing and deleting patent record', function () {
    $user = User::factory()->create();

    $category = Category::create([
        'category_title' => 'Biotechnology & Genetics',
        'category_status' => 1,
    ]);

    $component = Volt::test('profile.patents', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    // Save new patent
    $component->call('savePatent', [
        'title' => 'Targeted Epigenetic Editing Delivery Platform',
        'patent_no' => 'US 10,876,543 B2',
        'inventor' => 'Dr. Jennifer Doudna',
        'assignee' => 'Innovative Genomics Institute',
        'application_no' => '20190123456',
        'country' => 'United States',
        'category_id' => (string) $category->_id,
        'filing_date' => 'January 2020',
        'publication_date' => 'July 2021',
        'description' => 'Novel lipid nanoparticles for precise cellular epigenetic alteration without double-strand breaks.',
    ]);

    $patent = Patent::where('user_id', (string) $user->_id)->first();
    expect($patent)->not->toBeNull()
        ->and($patent->title)->toBe('Targeted Epigenetic Editing Delivery Platform')
        ->and($patent->patent_no)->toBe('US 10,876,543 B2')
        ->and($patent->inventor)->toBe('Dr. Jennifer Doudna')
        ->and($patent->assignee)->toBe('Innovative Genomics Institute')
        ->and($patent->application_no)->toBe('20190123456')
        ->and($patent->country)->toBe('United States')
        ->and((string) $patent->category_id)->toBe((string) $category->_id)
        ->and($patent->filing_date)->toBe('January 2020')
        ->and($patent->publication_date)->toBe('July 2021')
        ->and((int) $patent->status)->toBe(1);

    // Verify ReviewerProfile atomic synchronization
    $reviewerProfile = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect($reviewerProfile)->not->toBeNull()
        ->and((array) $reviewerProfile->patent)->toContain((string) $patent->_id);

    // Update patent
    $component->call('savePatent', [
        'id' => (string) $patent->_id,
        'title' => 'Targeted Epigenetic Editing Delivery Platform - Granted',
        'patent_no' => 'US 10,876,543 B2',
        'inventor' => 'Dr. Jennifer Doudna, Dr. Emmanuelle Charpentier',
        'assignee' => 'Innovative Genomics Institute',
        'application_no' => '20190123456',
        'country' => 'United States',
        'category_id' => (string) $category->_id,
        'filing_date' => 'January 2020',
        'publication_date' => 'August 2021',
        'description' => 'Updated formulation with enhanced tissue tropism and biodistribution.',
    ]);

    $updatedPatent = $patent->fresh();
    expect($updatedPatent->title)->toBe('Targeted Epigenetic Editing Delivery Platform - Granted')
        ->and($updatedPatent->inventor)->toBe('Dr. Jennifer Doudna, Dr. Emmanuelle Charpentier')
        ->and($updatedPatent->publication_date)->toBe('August 2021');

    // Delete patent
    $component->call('deletePatent', (string) $patent->_id);

    expect(Patent::find((string) $patent->_id))->toBeNull();

    $reviewerProfile->refresh();
    expect((array) ($reviewerProfile->patent ?? []))->not->toContain((string) $patent->_id);
});

test('admin patents partial renders and allows toggling status', function () {
    $user = User::factory()->create();

    $patent = Patent::create([
        'user_id' => (string) $user->_id,
        'title' => 'Solid State Battery Electrolyte Composition',
        'patent_no' => 'EP 3 456 789 A1',
        'inventor' => 'Dr. John Goodenough',
        'assignee' => 'University of Texas',
        'country' => 'United Kingdom',
        'filing_date' => 'March 2019',
        'publication_date' => 'September 2020',
        'description' => 'Non-flammable glass-ceramic electrolyte for ultra-high energy density cells.',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'patent' => [(string) $patent->_id],
    ]);

    $adminComponent = Volt::test('admin.users.partials.patents', [
        'user' => $user,
    ]);

    $adminComponent->assertStatus(200);
    $adminComponent->assertSee('Patents');
    $adminComponent->assertSee('Solid State Battery Electrolyte Composition');
    $adminComponent->assertSee('EP 3 456 789 A1');
    $adminComponent->assertSee('Active');

    // Toggle status to Inactive (0)
    $adminComponent->call('togglePatentStatus', (string) $patent->_id);

    expect((int) $patent->fresh()->status)->toBe(0);

    // Toggle back to Active (1)
    $adminComponent->call('togglePatentStatus', (string) $patent->_id);

    expect((int) $patent->fresh()->status)->toBe(1);
});

test('redis caching correctly caches and invalidates patent records', function () {
    $user = User::factory()->create();

    $patent = Patent::create([
        'user_id' => (string) $user->_id,
        'title' => 'Optical Phase Array Radar Sensor',
        'patent_no' => 'US 12,345,678 B1',
        'inventor' => 'Alice Wong',
        'country' => 'United States',
        'filing_date' => 'May 2022',
        'description' => 'Silicon photonics beam steering device for autonomous vehicle navigation.',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'patent' => [(string) $patent->_id],
    ]);

    // Initial cached retrieval
    $cached = Patent::getCachedForUser((string) $user->_id, true);
    expect($cached)->toHaveCount(1)
        ->and($cached[0]['title'])->toBe('Optical Phase Array Radar Sensor');

    // Verify cache exists in Redis
    expect(Cache::has("patent:user:{$user->_id}:active"))->toBeTrue();

    // Updating model should invalidate cache
    $patent->title = 'Optical Phase Array Radar Sensor - Rev 2';
    $patent->save();

    expect(Cache::has("patent:user:{$user->_id}:active"))->toBeFalse();

    // Re-fetch should repopulate cache with updated data
    $cachedUpdated = Patent::getCachedForUser((string) $user->_id, true);
    expect($cachedUpdated[0]['title'])->toBe('Optical Phase Array Radar Sensor - Rev 2');

    // Deleting should invalidate cache
    $patent->delete();
    expect(Cache::has("patent:user:{$user->_id}:active"))->toBeFalse();
});
