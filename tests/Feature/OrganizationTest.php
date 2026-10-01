<?php

use App\Models\Organization;

test('organization auto-assigns sequential serial_number and slug on creation', function () {
    $org1 = Organization::create([
        'organization_name' => 'Tata Consultancy Services (TCS)',
        'address' => 'TCS House, Raveline Street, Fort',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'country' => 'India',
        'zipcode' => '400001',
        'website_url' => 'https://www.tcs.com',
        'organization_type' => 'Corporate',
    ]);

    expect($org1->serial_number)->toBeGreaterThan(0);
    expect($org1->slug)->toBe('tata-consultancy-services-tcs-'.$org1->serial_number);
    expect($org1->status)->toBe(1);

    $org2 = Organization::create([
        'organization_name' => 'Infosys Limited',
        'city' => 'Bengaluru',
    ]);

    expect($org2->serial_number)->toBe($org1->serial_number + 1);
    expect($org2->slug)->toBe('infosys-limited-'.$org2->serial_number);

    // Clean up created test records
    $org1->delete();
    $org2->delete();
});

test('organization active scope returns only active organizations', function () {
    $activeOrg = Organization::create([
        'organization_name' => 'Active Tech Org',
        'status' => 1,
    ]);

    $inactiveOrg = Organization::create([
        'organization_name' => 'Inactive Tech Org',
        'status' => 0,
    ]);

    $activeList = Organization::active()->get();

    expect($activeList->contains('_id', $activeOrg->_id))->toBeTrue();
    expect($activeList->contains('_id', $inactiveOrg->_id))->toBeFalse();

    $activeOrg->delete();
    $inactiveOrg->delete();
});
