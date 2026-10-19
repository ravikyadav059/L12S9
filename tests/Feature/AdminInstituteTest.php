<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);
});

test('admin institutes routes are registered', function () {
    expect(Route::has('admin.institutes.index'))->toBeTrue();
    expect(Route::has('admin.institutes.create'))->toBeTrue();
    expect(Route::has('admin.institutes.edit'))->toBeTrue();
});

test('admin user can view add institute page with all fields', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.institutes.create')
        ->assertSee('Institute Quick Registration')
        ->assertSee('Institute Name')
        ->assertSee('Select Type')
        ->assertSee('Education')
        ->assertSee('Corporate')
        ->assertSee('Country')
        ->assertSee('State')
        ->assertSee('City')
        ->assertSee('Zip Code')
        ->assertSee('Affiliated University')
        ->assertSee('Website URL')
        ->assertSee('Institute Logo')
        ->assertSee('Status')
        ->assertSee('Address')
        ->assertSee('Description');
});

test('admin can create an institute with valid data matching required storage format', function () {
    $this->actingAs($this->admin);

    $testName = 'Virtueteam INC Test '.uniqid();

    Volt::test('admin.institutes.create')
        ->set('organization_name', $testName)
        ->set('organization_type', 'education')
        ->set('country', '231')
        ->set('state', '3974')
        ->set('city', '46711')
        ->set('zipcode', '22012')
        ->set('affiliated_university', 'Dell')
        ->set('website_url', 'https://www.sourcesecurity.com/companies/virtustream-inc.html')
        ->set('status', '1')
        ->set('address', '8444 Westpark Dr Ste 900')
        ->set('description', 'Virtustream was a provider of cloud computing management software, info...')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee("Institute '{$testName}' has been successfully registered!");

    $org = Organization::where('organization_name', $testName)->first();
    expect($org)->not->toBeNull();
    expect($org->organization_type)->toBe('education');
    expect((string) $org->country)->toBe('231');
    expect((string) $org->state)->toBe('3974');
    expect((string) $org->city)->toBe('46711');
    expect($org->zipcode)->toBe('22012');
    expect($org->affiliated_university)->toBe('Dell');
    expect($org->website_url)->toBe('https://www.sourcesecurity.com/companies/virtustream-inc.html');
    expect((string) $org->status)->toBe('1');
    expect($org->address)->toBe('8444 Westpark Dr Ste 900');
    expect($org->serial_number)->toBeGreaterThan(0);
    expect($org->slug)->toContain('virtueteam-inc-test');

    // Clean up
    $org->delete();
});

test('admin can edit an existing institute', function () {
    $this->actingAs($this->admin);

    $org = new Organization;
    $org->organization_name = 'Edit Target Test '.uniqid();
    $org->organization_type = 'education';
    $org->country = '231';
    $org->state = '3974';
    $org->city = '46711';
    $org->status = '1';
    $org->description = 'Initial description for edit testing';
    $org->save();

    $updatedName = 'Updated Institute Name '.uniqid();

    Volt::test('admin.institutes.edit', ['organization' => $org])
        ->assertSee($org->organization_name)
        ->set('organization_name', $updatedName)
        ->set('description', 'Updated description content after edit')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee("Institute '{$updatedName}' has been updated successfully!");

    $org->refresh();
    expect($org->organization_name)->toBe($updatedName);
    expect($org->description)->toBe('Updated description content after edit');

    $org->delete();
});

test('admin can delete an institute from index', function () {
    $this->actingAs($this->admin);

    $org = new Organization;
    $org->organization_name = 'Delete Target Test '.uniqid();
    $org->organization_type = 'education';
    $org->status = '1';
    $org->country = '231';
    $org->state = '3974';
    $org->city = '46711';
    $org->description = 'Test to delete';
    $org->save();

    $orgId = (string) $org->_id;

    Volt::test('admin.institutes.index')
        ->call('delete', $orgId)
        ->assertSee('was deleted successfully');

    expect(Organization::find($orgId))->toBeNull();
});

test('validation requires mandatory fields for institute registration', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.institutes.create')
        ->set('status', '')
        ->call('save')
        ->assertHasErrors([
            'organization_name' => 'required',
            'organization_type' => 'required',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            'status' => 'required',
            'description' => 'required',
        ]);
});

test('admin can view institutes index with Edit and Delete actions and without Location or Affiliated columns', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.institutes.index')
        ->assertSee('Manage Institutes')
        ->assertSee('Add Institute')
        ->assertSee('Edit')
        ->assertSee('Delete')
        ->assertDontSee('Location</th>', false)
        ->assertDontSee('Affiliated / Web</th>', false);
});

test('admin search filters only by name, id, and slug', function () {
    $this->actingAs($this->admin);

    $uniqueToken = 'srch'.uniqid();
    $org = new Organization;
    $org->organization_name = 'NameMatch '.$uniqueToken;
    $org->organization_type = 'education';
    $org->status = '1';
    $org->country = '231';
    $org->state = '3974';
    $org->city = '46711';
    $org->address = 'AddressSecretKeyword999';
    $org->affiliated_university = 'UniSecretKeyword888';
    $org->description = 'Search test description';
    $org->save();

    // 1. Search by name matches
    Volt::test('admin.institutes.index')
        ->set('search', $uniqueToken)
        ->assertSee('NameMatch '.$uniqueToken);

    // 2. Search by slug matches
    Volt::test('admin.institutes.index')
        ->set('search', $org->slug)
        ->assertSee('NameMatch '.$uniqueToken);

    // 3. Search by id matches
    Volt::test('admin.institutes.index')
        ->set('search', (string) $org->_id)
        ->assertSee('NameMatch '.$uniqueToken);

    // 4. Search by address does NOT match (only name, id, and slug)
    Volt::test('admin.institutes.index')
        ->set('search', 'AddressSecretKeyword999')
        ->assertDontSee('NameMatch '.$uniqueToken);

    // 5. Search by affiliated_university does NOT match
    Volt::test('admin.institutes.index')
        ->set('search', 'UniSecretKeyword888')
        ->assertDontSee('NameMatch '.$uniqueToken);

    $org->delete();
});
