<?php

use App\Models\Country;
use App\Models\ReviewerJournal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    Cache::flush();

    if (Country::where('sortname', 'IN')->count() === 0) {
        Country::create([
            'id' => 101,
            'sortname' => 'IN',
            'name' => 'India',
            'phoneCode' => 91,
        ]);
    }
});

test('admin journal create renders verification toolbar', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.journals.create')
        ->assertSee('Official Journal Verification')
        ->assertSee('Verify Journal')
        ->assertSee('Auto (Any Official Registry)')
        ->assertSee('Crossref API')
        ->assertSee('OpenAlex API')
        ->assertSee('DOAJ API')
        ->assertSee('Official ISSN Portal');
});

test('verifyJournalOnline with valid ISSN updates verification state and displays auto fill button', function () {
    $this->actingAs($this->admin);

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
    ]);

    Volt::test('admin.journals.create')
        ->set('e_issn', '2041-1723')
        ->set('verificationSource', 'crossref')
        ->call('verifyJournalOnline')
        ->assertSet('isJournalVerifiedOnline', true)
        ->assertSet('verifiedSourceLabel', 'Crossref')
        ->assertSee('Nature Communications')
        ->assertSee('Auto-fill Details')
        ->call('openAutoFillModal')
        ->assertSet('showAutoFillModal', true)
        ->assertSee('Confirm Auto-Fill Details')
        ->call('confirmAutoFill')
        ->assertSet('showAutoFillModal', false)
        ->assertSet('journal_title', 'Nature Communications')
        ->assertSet('organization_name', 'Springer Nature');
});

test('openAutoFillModal detects existing database journal and warns user', function () {
    $this->actingAs($this->admin);

    $existing = ReviewerJournal::create([
        'journal_title' => 'International Journal of Orthopaedics Sciences',
        'e_issn' => '2395-1958',
        'status' => 0,
    ]);

    Http::fake([
        'https://api.crossref.org/journals/2395-1958' => Http::response([
            'status' => 'ok',
            'message' => [
                'title' => 'International Journal of Orthopaedics Sciences',
                'publisher' => 'AkiNik Publications',
                'ISSN' => ['2395-1958'],
            ],
        ], 200),
        'https://api.openalex.org/sources*' => Http::response(['meta' => ['count' => 0], 'results' => []], 200),
    ]);

    Volt::test('admin.journals.create')
        ->set('e_issn', '2395-1958')
        ->set('verificationSource', 'crossref')
        ->call('verifyJournalOnline')
        ->call('openAutoFillModal')
        ->assertSet('showAutoFillModal', true)
        ->assertSet('existingDbJournalInfo.title', 'International Journal of Orthopaedics Sciences')
        ->assertSet('existingDbJournalInfo.status', 'Inactive')
        ->assertSee('Journal Already Registered in Your Database');

    $existing->delete();
});

test('strict verification on save blocks unverified or fake ISSN', function () {
    $this->actingAs($this->admin);

    Http::fake([
        'https://api.crossref.org/journals/*' => Http::response([], 404),
        'https://api.openalex.org/sources*' => Http::response(['meta' => ['count' => 0], 'results' => []], 200),
        'https://doaj.org/api/v2/search/journals/*' => Http::response(['total' => 0, 'results' => []], 200),
        'https://portal.issn.org/resource/ISSN/*' => Http::response('', 404),
    ]);

    Volt::test('admin.journals.create')
        ->set('organization_name', 'Test Publisher')
        ->set('journal_title', 'Fake Test Journal')
        ->set('journal_webiste_url', 'https://example.com')
        ->set('first_publication_year', 2020)
        ->set('e_issn', '1234-5678') // Invalid checksum
        ->set('contact_name', 'Dr. Smith')
        ->set('contact_email', 'smith@example.com')
        ->set('mobile', '9876543210')
        ->set('country', '101')
        ->set('zipcode', '110001')
        ->set('journal_description', 'Valid journal description here')
        ->set('address', '123 Test St')
        ->call('save')
        ->assertHasErrors(['e_issn']);
});
