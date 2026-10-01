<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

test('institutions route is registered and accessible', function () {
    expect(Route::has('institutions.index'))->toBeTrue();

    $response = $this->get(route('institutions.index'));
    $response->assertOk();
});

test('volt institution component renders correctly and searches organizations', function () {
    $org = Organization::create([
        'organization_name' => 'Tata Research Lab Institute',
        'address' => 'TCS House',
        'city' => '888',
        'state' => '12',
        'country' => '101',
        'organization_type' => 'research',
        'website_url' => 'https://tcs.com',
        'status' => 1,
    ]);

    Volt::test('institution.index')
        ->assertSee('Academic')
        ->assertSee('Institutions')
        ->set('search', 'Tata Research Lab')
        ->assertSee('Tata Research Lab Institute')
        ->assertSee('India')
        ->assertSee('View Details')
        ->assertDontSee('Visit Website')
        ->assertSee('Share');

    $org->delete();
});

test('volt institution show page renders correctly', function () {
    $org = Organization::create([
        'organization_name' => 'Harvard Test Medical Research Center',
        'address' => '25 Shattuck St',
        'city' => 'Boston',
        'state' => 'MA',
        'country' => 'USA',
        'organization_type' => 'research',
        'description' => 'A premier medical research center.',
        'status' => 1,
    ]);

    expect(Route::has('institution.show'))->toBeTrue();

    $response = $this->get(route('institution.show', ['slug' => $org->slug]));
    $response->assertOk();

    Volt::test('institution.show', ['slug' => $org->slug])
        ->assertSee('Harvard Test Medical Research Center')
        ->assertSee('USA')
        ->assertSee('A premier medical research center.')
        ->assertSee('Institution Information');

    $org->delete();
});

test('volt institution component can reset filters and load more', function () {
    Volt::test('institution.index')
        ->set('typeFilter', 'education')
        ->assertSet('typeFilter', 'education')
        ->call('resetFilters')
        ->assertSet('typeFilter', 'all')
        ->assertSet('search', '')
        ->call('loadMore')
        ->assertSet('perPage', 20);
});

test('volt institution component displays publication, citation, and conference metrics row format', function () {
    $org = Organization::create([
        'organization_name' => 'Oxford Academic University Test',
        'address' => 'Oxford High St',
        'country' => '101',
        'organization_type' => 'education',
        'status' => 1,
    ]);

    Volt::test('institution.index')
        ->set('search', 'Oxford Academic University Test')
        ->assertSee('PUBLICATION -')
        ->assertSee('CITATIONS -')
        ->assertSee('CONFERENCES/SEMINAR -');

    $org->delete();
});

test('volt institution show page supports tabs switching for scholars, alumnus and publications', function () {
    $org = Organization::create([
        'organization_name' => 'Cambridge Academic Laboratory',
        'address' => 'Cambridge Road',
        'country' => '101',
        'organization_type' => 'education',
        'description' => 'A historic academic laboratory.',
        'status' => 1,
    ]);

    Volt::test('institution.show', ['slug' => $org->slug])
        ->assertSee('Overview')
        ->assertSee('Scholars')
        ->assertSee('Alumnus')
        ->assertSee('Publications')
        ->assertSee('PUBLICATION -')
        ->assertSee('CITATIONS -')
        ->assertSee('CONFERENCES/SEMINAR -')
        ->set('tab', 'scholars')
        ->assertSee('Active Scholars & Faculty', false)
        ->set('tab', 'alumni')
        ->assertSee('Institution Alumnus', false)
        ->set('tab', 'publications')
        ->assertSee('Research Publications', false)
        ->assertSee('No Publications Found', false);

    $org->delete();
});
