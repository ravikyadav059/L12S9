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
        ->assertSee('Journal Already Registered in Your Database')
        ->assertSee('View in Journals List')
        ->assertSee('Edit Existing Journal')
        ->assertDontSee('Go with this change');

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

test('verifyJournalOnline uses preferredIssn passed from DOM if component property was empty', function () {
    $this->actingAs($this->admin);

    Http::fake([
        'https://api.crossref.org/journals/2041-1723' => Http::response([
            'status' => 'ok',
            'message' => [
                'title' => 'Nature Communications',
                'publisher' => 'Springer Nature',
                'ISSN' => ['2041-1723'],
            ],
        ], 200),
    ]);

    Volt::test('admin.journals.create')
        ->set('e_issn', '')
        ->set('p_issn', '')
        ->set('verificationSource', 'crossref')
        ->call('verifyJournalOnline', '2041-1723')
        ->assertSet('e_issn', '2041-1723')
        ->assertSet('isJournalVerifiedOnline', true)
        ->assertSet('verifiedSourceLabel', 'Crossref');
});

test('searchRegistryByTitle finds journal candidates and selecting one populates form and triggers verification', function () {
    $this->actingAs($this->admin);

    Http::fake([
        'https://api.crossref.org/journals?query=Nature*' => Http::response([
            'status' => 'ok',
            'message' => [
                'items' => [
                    [
                        'title' => 'Nature',
                        'publisher' => 'Nature Publishing Group',
                        'ISSN' => ['1476-4687', '0028-0836'],
                        'issn-type' => [
                            ['value' => '1476-4687', 'type' => 'electronic'],
                            ['value' => '0028-0836', 'type' => 'print'],
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://api.crossref.org/journals/1476-4687' => Http::response([
            'status' => 'ok',
            'message' => [
                'title' => 'Nature',
                'publisher' => 'Nature Publishing Group',
                'ISSN' => ['1476-4687', '0028-0836'],
            ],
        ], 200),
        'https://api.openalex.org/sources*' => Http::response(['meta' => ['count' => 0], 'results' => []], 200),
    ]);

    Volt::test('admin.journals.create')
        ->call('openRegistryTitleSearchModal', 'Nature')
        ->assertSet('showRegistrySearchModal', true)
        ->assertSet('searchRegistryTitle', 'Nature')
        ->assertCount('registryTitleCandidates', 1)
        ->call('selectRegistryCandidate', 0)
        ->assertSet('showRegistrySearchModal', false)
        ->assertSet('e_issn', '1476-4687')
        ->assertSet('p_issn', '0028-0836')
        ->assertSet('isJournalVerifiedOnline', true)
        ->assertSet('showAutoFillModal', true);
});

test('confirmAutoFill populates contact_name, contact_email, and mobile on form', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.journals.create')
        ->set('verifiedJournalData', [
            'is_valid' => true,
            'title' => 'Test Journal',
            'editor_name' => 'Dr. Amit Nandan Mishra',
            'editor_email' => 'editor@orthopaper.com',
            'editor_mobile' => '+919919604383',
            'address' => 'C-11/169, Sector-3, Rohini, Delhi',
            'zipcode' => '110085',
        ])
        ->call('confirmAutoFill')
        ->assertSet('contact_name', 'Dr. Amit Nandan Mishra')
        ->assertSet('contact_email', 'editor@orthopaper.com')
        ->assertSet('mobile', '9919604383')
        ->assertSet('country_code', '+91')
        ->assertSet('address', 'C-11/169, Sector-3, Rohini, Delhi')
        ->assertSet('zipcode', '110085')
        ->assertSet('showAutoFillModal', false);
});

test('selectRegistryCandidate deduplicates identical e-ISSN and p-ISSN ensuring only e-ISSN is populated', function () {
    $this->actingAs($this->admin);

    Http::fake([
        'https://api.crossref.org/journals?query=Orthopaedics*' => Http::response([
            'status' => 'ok',
            'message' => [
                'items' => [
                    [
                        'title' => 'International Journal of Orthopaedics Sciences',
                        'publisher' => 'AkiNik Publications',
                        'ISSN' => ['2395-1958', '2395-1958'],
                        'issn-type' => [
                            ['value' => '2395-1958', 'type' => 'electronic'],
                            ['value' => '2395-1958', 'type' => 'print'],
                        ],
                    ],
                ],
            ],
        ], 200),
        'https://api.crossref.org/journals/2395-1958' => Http::response([
            'status' => 'ok',
            'message' => [
                'title' => 'International Journal of Orthopaedics Sciences',
                'publisher' => 'AkiNik Publications',
                'ISSN' => ['2395-1958', '2395-1958'],
                'issn-type' => [
                    ['value' => '2395-1958', 'type' => 'electronic'],
                    ['value' => '2395-1958', 'type' => 'print'],
                ],
            ],
        ], 200),
        'https://api.openalex.org/sources*' => Http::response(['meta' => ['count' => 0], 'results' => []], 200),
    ]);

    Volt::test('admin.journals.create')
        ->call('openRegistryTitleSearchModal', 'Orthopaedics')
        ->assertCount('registryTitleCandidates', 1)
        ->assertSet('registryTitleCandidates.0.e_issn', '2395-1958')
        ->assertSet('registryTitleCandidates.0.p_issn', null)
        ->call('selectRegistryCandidate', 0)
        ->assertSet('e_issn', '2395-1958')
        ->assertSet('p_issn', '');
});
