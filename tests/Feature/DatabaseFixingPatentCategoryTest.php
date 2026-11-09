<?php

use App\Models\Category;
use App\Models\Patent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use MongoDB\BSON\ObjectId;

beforeEach(function () {
    Cache::forget('db_fixing_patent_category_issues');
});

test('patent category database fixing routes are registered', function () {
    expect(Route::has('admin.database-fixing.patent.category'))->toBeTrue();
    expect(Route::has('admin.database-fixing.patent.index'))->toBeTrue();
});

test('admin can access patent category database fixing page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.patent.category'));
    $response->assertStatus(200);
    $response->assertSee('Patent — category_id');
    $response->assertSee('Text Title / Non-ObjectId');
    $response->assertSee('Missing Category');
});

test('patent category fixer tool is listed on the database fixing index page', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.database-fixing.index'));
    $response->assertStatus(200);
    $response->assertSee('Patent');
    $response->assertSee('category_id (Category Fixer &amp; Standardizer)', false);
});

test('patent component renders and can toggle tabs', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.patent.category')
        ->assertSee('Total Category Issues')
        ->set('tab', 'empty_category_id')
        ->assertSet('tab', 'empty_category_id')
        ->set('tab', 'non_object_id')
        ->assertSet('tab', 'non_object_id')
        ->set('tab', 'all')
        ->assertSet('tab', 'all');
});

test('patent component can auto-match legacy title strings against category collection', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    // Create a Category
    $category = Category::create([
        'category_title' => 'Test Artificial Intelligence',
        'category_status' => 1,
    ]);

    // Create a Patent with the text string title as category_id
    $patent = Patent::create([
        'title' => 'Test AI Neural Patent',
        'category_id' => 'Test Artificial Intelligence',
        'status' => 1,
    ]);

    Cache::forget('db_fixing_patent_category_issues');

    $test = Volt::test('admin.database-fixing.patent.category');
    $test->assertSee('Test AI Neural Patent');
    $test->assertSee('Auto-Fix');

    // Execute single auto-fix
    $test->call('fixSingle', (string) $patent->_id);

    // Verify in database that category_id is now the Category ObjectId
    $updated = Patent::find($patent->_id);
    expect((string) $updated->category_id)->toBe((string) $category->_id);

    // Clean up test records
    $patent->delete();
    $category->delete();
});

test('patent component can assign category via modal', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'type' => 1,
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $category = Category::create([
        'category_title' => 'Biotechnology Innovations',
        'category_status' => 1,
    ]);

    $patent = Patent::create([
        'title' => 'Test Bio Patent',
        'category_id' => null,
        'status' => 1,
    ]);

    Cache::forget('db_fixing_patent_category_issues');

    Volt::test('admin.database-fixing.patent.category')
        ->call('openAssignModal', (string) $patent->_id, 'Test Bio Patent')
        ->assertSet('showAssignModal', true)
        ->assertSet('assignPatentId', (string) $patent->_id)
        ->call('assignCategory', (string) $category->_id, 'Biotechnology Innovations')
        ->assertSet('showAssignModal', false);

    $updated = Patent::find($patent->_id);
    expect((string) $updated->category_id)->toBe((string) $category->_id);

    // Clean up
    $patent->delete();
    $category->delete();
});
