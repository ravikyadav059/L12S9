<?php

use App\Livewire\Concerns\HandlesAddJournalModal;
use App\Models\JournalRole;
use App\Models\ReviewerJournal;
use App\Models\ReviewerProfile;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::flush();
    HandlesAddJournalModal::$verifyInTesting = false;
});

afterEach(function () {
    HandlesAddJournalModal::$verifyInTesting = false;
});

test('profile journal roles component renders active records and verification toolbar', function () {
    $user = User::factory()->create();

    $journalRole = JournalRole::create([
        'name' => 'Editor-in-Chief',
        'status' => '1',
    ]);

    $journal = ReviewerJournal::create([
        'journal_title' => 'Nature Communications',
        'journal_short_name' => 'Nat Commun',
        'e_issn' => '2041-1723',
        'organization_name' => 'Springer Nature',
        'journal_webiste_url' => 'https://www.nature.com/ncomms',
        'is_verified' => true,
        'status' => 1,
    ]);

    $activeRole = RoleInResearchJournals::create([
        'user_id' => (string) $user->_id,
        'role' => (string) $journalRole->_id,
        'journal_title' => (string) $journal->_id,
        'journal_short_name' => 'Nat Commun',
        'e_issn' => '2041-1723',
        'organization_name' => 'Springer Nature',
        'journal_webiste_url' => 'https://www.nature.com/ncomms',
        'status' => 1,
    ]);

    // Inactive record should NOT appear on public/profile view
    $inactiveRole = RoleInResearchJournals::create([
        'user_id' => (string) $user->_id,
        'role' => (string) $journalRole->_id,
        'journal_title' => 'Hidden Inactive Journal',
        'e_issn' => '1234-5679',
        'status' => 0,
    ]);

    // Unlinked record with matching user_id should ALSO NOT appear because it is not in ReviewerProfile.RoleInResearchJournal
    RoleInResearchJournals::create([
        'user_id' => (string) $user->_id,
        'role' => (string) $journalRole->_id,
        'journal_title' => 'Unlinked Journal Role Entry',
        'e_issn' => '2041-1723',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'RoleInResearchJournal' => [(string) $activeRole->_id, (string) $inactiveRole->_id],
    ]);

    $component = Volt::test('profile.journal-roles', [
        'user' => $user,
        'isOwnProfile' => true,
    ]);

    $component->assertStatus(200)
        ->assertSee('Role in Research Journals')
        ->assertSee('Editor-in-Chief')
        ->assertSee('Nature Communications')
        ->assertSee('Nat Commun')
        ->assertSee('2041-1723')
        ->assertSee('Springer Nature')
        ->assertSee('https://www.nature.com/ncomms')
        ->assertSee('Official Journal Verification')
        ->assertSee('Verify Journal')
        ->assertDontSee('Hidden Inactive Journal')
        ->assertDontSee('Unlinked Journal Role Entry');
});

test('profile journal roles validates all fields same as admin journal create', function () {
    $user = User::factory()->create();

    $journalRole = JournalRole::create([
        'name' => 'Reviewer',
        'status' => '1',
    ]);

    $component = Volt::actingAs($user)
        ->test('profile.journal-roles', [
            'user' => $user,
            'isOwnProfile' => true,
        ]);

    // 1. Missing required fields (role, journal_title, e_issn/p_issn)
    $component->call('saveJournalRole', [
        'role' => '',
        'journal_title' => '',
        'e_issn' => '',
        'p_issn' => '',
    ])->assertHasErrors(['role', 'journal_title', 'e_issn']);

    // 2. Invalid ISSN format / checksum & invalid URL
    $component->call('saveJournalRole', [
        'role' => (string) $journalRole->_id,
        'journal_title' => 'Test Journal Name',
        'e_issn' => '1234-5678', // invalid checksum (expected 9)
        'journal_webiste_url' => 'not-a-valid-url',
    ])->assertHasErrors(['e_issn', 'journal_webiste_url']);

    // 3. Identical e-ISSN and p-ISSN
    $component->call('saveJournalRole', [
        'role' => (string) $journalRole->_id,
        'journal_title' => 'Test Journal Name',
        'e_issn' => '2041-1723',
        'p_issn' => '2041-1723',
    ])->assertHasErrors(['p_issn']);

    // 4. Strict verification failure when Http::fake returns 404 from all registries
    Http::fake([
        'https://api.crossref.org/journals/*' => Http::response([], 404),
        'https://api.openalex.org/sources*' => Http::response(['meta' => ['count' => 0], 'results' => []], 200),
        'https://doaj.org/api/v2/search/journals/*' => Http::response(['total' => 0, 'results' => []], 200),
        'https://portal.issn.org/resource/ISSN/*' => Http::response('', 404),
    ]);

    $component->call('saveJournalRole', [
        'role' => (string) $journalRole->_id,
        'journal_title' => 'Unverified Fake Journal',
        'e_issn' => '1234-5679', // valid checksum, not in registries
    ])->assertHasErrors(['e_issn']);
});

test('profile journal roles allows verifying, auto-filling, creating, editing and deleting record', function () {
    $user = User::factory()->create();

    $roleEditor = JournalRole::create(['name' => 'Associate Editor', 'status' => '1']);
    $roleChief = JournalRole::create(['name' => 'Editor-in-Chief', 'status' => '1']);

    Http::fake([
        'https://api.crossref.org/journals/2041-1723' => Http::response([
            'status' => 'ok',
            'message-type' => 'journal',
            'message' => [
                'title' => 'Nature Communications',
                'publisher' => 'Springer Nature',
                'ISSN' => ['2041-1723'],
                'issn-type' => [
                    ['value' => '2041-1723', 'type' => 'electronic'],
                ],
            ],
        ], 200),
        'https://api.openalex.org/sources*' => Http::response([
            'meta' => ['count' => 0],
            'results' => [],
        ], 200),
    ]);

    $component = Volt::actingAs($user)
        ->test('profile.journal-roles', [
            'user' => $user,
            'isOwnProfile' => true,
        ]);

    // Verify ISSN online & confirm Auto-fill modal
    $component->set('verificationSource', 'crossref')
        ->call('verifyJournalFromForm', [
            'e_issn' => '2041-1723',
            'journal_title' => 'Nature',
        ])
        ->assertSet('isJournalVerifiedOnline', true)
        ->assertSet('verifiedSourceLabel', 'Crossref')
        ->call('openAutoFillModalWithForm', [
            'e_issn' => '2041-1723',
            'journal_title' => 'Nature',
        ])
        ->assertSet('showAutoFillModal', true)
        ->call('confirmAutoFillForRole')
        ->assertSet('showAutoFillModal', false)
        ->assertSet('journal_title', 'Nature Communications')
        ->assertSet('organization_name', 'Springer Nature')
        ->assertDispatched('journal-role-autofilled');

    // Save record
    $component->call('saveJournalRole', [
        'role' => (string) $roleEditor->_id,
        'journal_title' => 'Nature Communications',
        'journal_short_name' => 'Nat Commun',
        'e_issn' => '2041-1723',
        'p_issn' => '',
        'organization_name' => 'Springer Nature',
        'journal_webiste_url' => 'https://www.nature.com/ncomms',
    ])->assertHasNoErrors();

    $record = RoleInResearchJournals::where('user_id', (string) $user->_id)->first();
    expect($record)->not->toBeNull()
        ->and((string) $record->role)->toBe((string) $roleEditor->_id)
        ->and($record->journal_short_name)->toBe('Nat Commun')
        ->and($record->e_issn)->toBe('2041-1723')
        ->and($record->organization_name)->toBe('Springer Nature')
        ->and($record->journal_webiste_url)->toBe('https://www.nature.com/ncomms')
        ->and((int) $record->status)->toBe(1);

    // Linked ReviewerJournal created cleanly
    $journal = ReviewerJournal::find($record->journal_title);
    expect($journal)->not->toBeNull()
        ->and($journal->journal_title)->toBe('Nature Communications')
        ->and($journal->e_issn)->toBe('2041-1723');

    // Verify ReviewerProfile atomic synchronization
    $profile = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect($profile)->not->toBeNull()
        ->and((array) $profile->RoleInResearchJournal)->toContain((string) $record->_id);

    // Check ISSN DB lookup helper finds the created journal
    $foundByIssn = $component->instance()->checkIssn('2041-1723');
    expect($foundByIssn)->not->toBeNull()
        ->and($foundByIssn['journal_title'])->toBe('Nature Communications');

    // Update record
    $component->call('saveJournalRole', [
        'id' => (string) $record->_id,
        'role' => (string) $roleChief->_id,
        'journal_id' => (string) $journal->_id,
        'journal_title' => 'Nature Communications',
        'journal_short_name' => 'NC',
        'e_issn' => '2041-1723',
        'p_issn' => '',
        'organization_name' => 'Springer Nature Group',
        'journal_webiste_url' => 'https://www.nature.com/ncomms',
    ])->assertHasNoErrors();

    $updated = $record->fresh();
    expect((string) $updated->role)->toBe((string) $roleChief->_id)
        ->and($updated->journal_short_name)->toBe('NC')
        ->and($updated->organization_name)->toBe('Springer Nature Group');

    // Delete record
    $component->call('deleteJournalRole', (string) $record->_id);

    expect(RoleInResearchJournals::find((string) $record->_id))->toBeNull();
    $profileAfter = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect((array) ($profileAfter->RoleInResearchJournal ?? []))->not->toContain((string) $record->_id);
});

test('admin journal roles partial renders, toggles status, and filters correctly', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $role = JournalRole::create(['name' => 'Editorial Board Member', 'status' => '1']);
    $journal = ReviewerJournal::create([
        'journal_title' => 'IEEE Access',
        'journal_short_name' => 'IEEE Access',
        'e_issn' => '2169-3536',
        'is_verified' => true,
        'status' => 1,
    ]);

    $record = RoleInResearchJournals::create([
        'user_id' => (string) $user->_id,
        'role' => (string) $role->_id,
        'journal_title' => (string) $journal->_id,
        'journal_short_name' => 'IEEE Access',
        'e_issn' => '2169-3536',
        'organization_name' => 'IEEE',
        'status' => 1,
    ]);

    // Unlinked record should NOT appear in admin view either
    RoleInResearchJournals::create([
        'user_id' => (string) $user->_id,
        'role' => (string) $role->_id,
        'journal_title' => 'Unlinked Admin Hidden Journal',
        'e_issn' => '2169-3536',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'RoleInResearchJournal' => [(string) $record->_id],
    ]);

    $component = Volt::actingAs($admin)
        ->test('admin.users.partials.journal-roles', [
            'user' => $user,
        ]);

    $component->assertStatus(200)
        ->assertSee('Role in Research Journals')
        ->assertSee('Editorial Board Member')
        ->assertSee('IEEE Access')
        ->assertSee('2169-3536')
        ->assertDontSee('Unlinked Admin Hidden Journal');

    // Toggle status to Inactive
    $component->call('toggleRoleStatus', (string) $record->_id);
    expect((int) $record->fresh()->status)->toBe(0);

    // Filter Inactive only
    $component->set('statusFilter', '0')->assertSee('IEEE Access');
    // Filter Active only
    $component->set('statusFilter', '1')->assertDontSee('IEEE Access');

    // Toggle back to Active
    $component->call('toggleRoleStatus', (string) $record->_id);
    expect((int) $record->fresh()->status)->toBe(1);
});

test('role in research journals caching caches and invalidates on save and delete', function () {
    $user = User::factory()->create();
    $role = JournalRole::create(['name' => 'Guest Editor', 'status' => '1']);

    $record = RoleInResearchJournals::create([
        'user_id' => (string) $user->_id,
        'role' => (string) $role->_id,
        'journal_title' => 'Cached Journal',
        'e_issn' => '2041-1723',
        'status' => 1,
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'RoleInResearchJournal' => [(string) $record->_id],
    ]);

    $cached = RoleInResearchJournals::getCachedForUser((string) $user->_id, true);
    expect($cached)->toHaveCount(1)
        ->and($cached[0]['journal_title'])->toBe('Cached Journal');

    expect(Cache::has("role_in_research_journals:user:{$user->_id}:active"))->toBeTrue();

    // Update invalidates cache
    $record->journal_short_name = 'CJ';
    $record->save();

    expect(Cache::has("role_in_research_journals:user:{$user->_id}:active"))->toBeFalse();

    $reCached = RoleInResearchJournals::getCachedForUser((string) $user->_id, true);
    expect($reCached[0]['journal_short_name'])->toBe('CJ');

    // Delete invalidates cache
    $record->delete();
    expect(Cache::has("role_in_research_journals:user:{$user->_id}:active"))->toBeFalse();
});

test('add new journal popup modal matches deposit-article and auto-populates role form', function () {
    ReviewerJournal::where('e_issn', '2049-3630')->orWhere('journal_title', 'saaas')->delete();
    $user = User::factory()->create();

    $component = Volt::actingAs($user)
        ->test('profile.journal-roles', [
            'user' => $user,
            'isOwnProfile' => true,
        ]);

    // Renders reusable deposit-article Add New Journal modal
    $component->assertSee('Add New Journal');

    // Open Add New Journal modal pre-filled with typed query
    $component->call('openAddJournalModal', 'saaas')
        ->assertSet('showAddJournalModal', true)
        ->assertSet('newJournalTitle', 'saaas');

    // Validation inside Add New Journal modal requires at least one ISSN
    $component->call('saveNewJournal')
        ->assertHasErrors(['newJournalIssnRequired']);

    // Save valid journal from modal and verify auto-fill dispatch
    $component->set('newJournalEIssn', '2049-3630')
        ->call('saveNewJournal')
        ->assertHasNoErrors()
        ->assertSet('showAddJournalModal', false)
        ->assertSet('journal_title', 'saaas')
        ->assertSet('e_issn', '2049-3630')
        ->assertDispatched('journal-created')
        ->assertDispatched('journal-role-autofilled');

    $createdJournal = ReviewerJournal::where('journal_title', 'saaas')->first();
    expect($createdJournal)->not->toBeNull()
        ->and($createdJournal->e_issn)->toBe('2049-3630')
        ->and((int) $createdJournal->status)->toBe(1);
});

test('add new journal modal verifies journal name, e-issn, and p-issn via official api before allowing add', function () {
    HandlesAddJournalModal::$verifyInTesting = true;
    ReviewerJournal::whereIn('e_issn', ['2049-3630', '1234-5679'])
        ->orWhereIn('journal_title', ['ssswqwqqw', 'Verified Journal API Test'])
        ->delete();

    $user = User::factory()->create();

    Http::fake([
        'https://api.crossref.org/journals/2049-3630' => Http::response([
            'status' => 'ok',
            'message' => [
                'title' => 'Verified Journal API Test',
                'publisher' => 'Academic Press',
                'ISSN' => ['2049-3630'],
                'issn-type' => [
                    ['value' => '2049-3630', 'type' => 'electronic'],
                ],
            ],
        ], 200),
        'https://api.crossref.org/journals/1234-5679' => Http::response([], 404),
        'https://api.crossref.org/journals/2169-3536' => Http::response([
            'status' => 'ok',
            'message' => [
                'title' => 'IEEE Access',
                'publisher' => 'IEEE',
                'ISSN' => ['2169-3536'],
            ],
        ], 200),
        'https://api.openalex.org/sources*' => Http::response(['meta' => ['count' => 0], 'results' => []], 200),
        'https://doaj.org/api/v2/search/journals/*' => Http::response(['total' => 0, 'results' => []], 200),
        'https://portal.issn.org/resource/ISSN/*' => Http::response('', 404),
    ]);

    $component = Volt::actingAs($user)
        ->test('profile.journal-roles', [
            'user' => $user,
            'isOwnProfile' => true,
        ]);

    // 1. Fake/unverified e-ISSN is blocked
    $component->set('newJournalTitle', 'Verified Journal API Test')
        ->set('newJournalEIssn', '1234-5679')
        ->set('newJournalPIssn', '')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalEIssn']);

    expect(ReviewerJournal::where('e_issn', '1234-5679')->exists())->toBeFalse();

    // 2. Valid ISSN but wrong journal name ('ssswqwqqw') is blocked
    $component->set('newJournalTitle', 'ssswqwqqw')
        ->set('newJournalEIssn', '2049-3630')
        ->set('newJournalPIssn', '')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalTitle']);

    expect(ReviewerJournal::where('journal_title', 'ssswqwqqw')->exists())->toBeFalse();

    // 3. Mismatched p-ISSN belonging to another journal is blocked
    $component->set('newJournalTitle', 'Verified Journal API Test')
        ->set('newJournalEIssn', '2049-3630')
        ->set('newJournalPIssn', '2169-3536')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalPIssn']);

    // 4. Matching journal name + verified e-ISSN succeeds
    $component->set('newJournalTitle', 'Verified Journal API Test')
        ->set('newJournalEIssn', '2049-3630')
        ->set('newJournalPIssn', '')
        ->call('saveNewJournal')
        ->assertHasNoErrors()
        ->assertDispatched('close-add-journal-modal')
        ->assertDispatched('journal-created');

    $saved = ReviewerJournal::where('journal_title', 'Verified Journal API Test')->first();
    expect($saved)->not->toBeNull()
        ->and($saved->e_issn)->toBe('2049-3630')
        ->and($saved->organization_name)->toBe('Academic Press');

    $saved->delete();
});
