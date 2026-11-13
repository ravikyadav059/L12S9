<?php

use App\Livewire\Concerns\HandlesAddJournalModal;
use App\Models\Article;
use App\Models\Publication;
use App\Models\ReviewerJournal;
use App\Models\ReviewerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

beforeEach(function () {
    Cache::flush();
    HandlesAddJournalModal::$verifyInTesting = false;
});

afterEach(function () {
    HandlesAddJournalModal::$verifyInTesting = false;
});

test('profile publications component renders active records only and hides add button for visitors', function () {
    $user = User::factory()->create();
    $articleType = Article::create(['name' => 'Research Article']);

    $journal = ReviewerJournal::create([
        'journal_title' => 'Nature Communications',
        'journal_short_name' => 'Nat Commun',
        'e_issn' => '2041-1723',
        'status' => 1,
    ]);

    $activePub = Publication::create([
        'user_id' => (string) $user->_id,
        'title' => 'Active Quantum Research Paper',
        'type' => (string) $articleType->_id,
        'journal_title' => (string) $journal->_id,
        'journal_name' => 'Nature Communications',
        'e_issn' => '2041-1723',
        'published_date' => 'May 2025',
        'authors' => [['name' => 'Dr. Alice', 'affiliation' => 'MIT', 'email' => 'alice@mit.edu']],
        'publication_keywords' => ['Quantum', 'Physics', 'Optics', 'Computing', 'Cryptography'],
        'description' => 'Active abstract text.',
        'image' => 'publications/sample.pdf',
        'status' => 1,
        'serial_number' => 1001,
        'slug' => 'active-quantum-research-paper-1001',
    ]);

    $inactivePub = Publication::create([
        'user_id' => (string) $user->_id,
        'title' => 'Hidden Inactive Paper',
        'type' => (string) $articleType->_id,
        'journal_title' => (string) $journal->_id,
        'journal_name' => 'Nature Communications',
        'e_issn' => '2041-1723',
        'published_date' => 'June 2025',
        'authors' => [['name' => 'Dr. Alice', 'affiliation' => 'MIT', 'email' => '']],
        'description' => 'Inactive abstract.',
        'status' => 0,
        'serial_number' => 1002,
        'slug' => 'hidden-inactive-paper-1002',
    ]);

    // Unlinked record with matching user_id/registered_co_author should NOT appear if missing from ReviewerProfile.publication
    Publication::create([
        'user_id' => (string) $user->_id,
        'registered_co_author' => [(string) $user->_id],
        'title' => 'Unlinked Hidden Publication Paper',
        'type' => (string) $articleType->_id,
        'journal_title' => (string) $journal->_id,
        'status' => 1,
        'serial_number' => 1003,
        'slug' => 'unlinked-hidden-publication-paper-1003',
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'publication' => [(string) $activePub->_id, (string) $inactivePub->_id],
    ]);

    // Visitor view
    $detailUrl = route('publication.detail', ['slug' => 'active-quantum-research-paper-1001']);
    Volt::test('profile.publications', ['user' => $user, 'isOwnProfile' => false])
        ->assertSee('Active Quantum Research Paper')
        ->assertSee($detailUrl)
        ->assertSee('Journal:')
        ->assertSee('Nature Communications')
        ->assertSee('Published:')
        ->assertSee('May 2025')
        ->assertSee('Quantum')
        ->assertSee('Physics')
        ->assertSee('Optics')
        ->assertDontSee('>Computing<', false)
        ->assertSee('+2 more')
        ->assertDontSee('>Dr. Alice<', false)
        ->assertDontSee('fa-file-pdf')
        ->assertDontSee('Hidden Inactive Paper')
        ->assertDontSee('Unlinked Hidden Publication Paper')
        ->assertDontSee('Add Publication');

    // Owner view
    $this->actingAs($user);
    Volt::test('profile.publications', ['user' => $user, 'isOwnProfile' => true])
        ->assertSee('Active Quantum Research Paper')
        ->assertSee('Add Publication');
});

test('crossref doi retrieval populates publication fields and matches local journal', function () {
    $user = User::factory()->create();
    $articleType = Article::create(['name' => 'Research Article']);

    $journal = ReviewerJournal::firstOrCreate(
        ['e_issn' => '2045-2322'],
        [
            'journal_title' => 'Scientific Reports',
            'status' => 1,
        ]
    );

    Http::fake([
        'https://api.crossref.org/works/*' => Http::response([
            'status' => 'ok',
            'message' => [
                'DOI' => '10.1038/s41598-024-12345',
                'title' => ['Deep Learning for Climate Modeling'],
                'URL' => 'https://doi.org/10.1038/s41598-024-12345',
                'type' => 'journal-article',
                'container-title' => ['Scientific Reports'],
                'issn-type' => [
                    ['value' => '2045-2322', 'type' => 'electronic'],
                ],
                'published' => [
                    'date-parts' => [[2024, 9, 15]],
                ],
                'author' => [
                    [
                        'given' => 'Jane',
                        'family' => 'Doe',
                        'affiliation' => [['name' => 'Stanford University']],
                    ],
                ],
                'volume' => '14',
                'issue' => '3',
                'page' => '101-115',
                'is-referenced-by-count' => 27,
                'subject' => ['Climate Science', 'Machine Learning'],
                'abstract' => '<jats:p>A novel <jats:italic>deep learning</jats:italic> framework.</jats:p>',
            ],
        ], 200),
    ]);

    $this->actingAs($user);

    $component = Volt::test('profile.publications', ['user' => $user, 'isOwnProfile' => true]);
    $res = $component->instance()->retrieveFromCrossref('https://doi.org/10.1038/s41598-024-12345');

    expect($res['found'])->toBeTrue();
    expect($res['data']['doi'])->toBe('10.1038/s41598-024-12345');
    expect($res['data']['title'])->toBe('Deep Learning for Climate Modeling');
    expect($res['data']['journal_title'])->toBe((string) $journal->_id);
    expect($res['data']['e_issn'])->toBe('2045-2322');
    expect($res['data']['issn_type'])->toBe('e_issn');
    expect($res['data']['published_date'])->toBe('September 2024');
    expect($res['data']['volume'])->toBe('14');
    expect($res['data']['issue'])->toBe('3');
    expect($res['data']['page_no'])->toBe('101-115');
    expect($res['data']['citations'])->toBe('27');
    expect($res['data']['type'])->not->toBeEmpty();
    expect(Article::find($res['data']['type']))->not->toBeNull();
    expect($res['data']['authors'][0]['name'])->toBe('Jane Doe');
    expect($res['data']['authors'][0]['affiliation'])->toBe('Stanford University');
    expect($res['data']['publication_keywords'])->toBe(['Climate Science', 'Machine Learning']);
    expect($res['data']['description'])->toBe('A novel deep learning framework.');
});

test('duplicate title check detects existing publications and excludes current record when editing', function () {
    $user = User::factory()->create();
    $uniqueTitle = 'Unique Neural Architecture Study '.uniqid();

    $existing = Publication::create([
        'user_id' => (string) $user->_id,
        'title' => $uniqueTitle,
        'status' => 1,
        'serial_number' => 2001,
        'slug' => Str::slug($uniqueTitle).'-2001',
    ]);

    $this->actingAs($user);

    $component = Volt::test('profile.publications', ['user' => $user, 'isOwnProfile' => true]);

    expect($component->instance()->checkTitleDuplicate($uniqueTitle))->toBeTrue();
    expect($component->get('showDuplicateModal'))->toBeTrue();

    // Editing the same record should NOT trigger duplicate
    $component->instance()->closeDuplicateModal();
    expect($component->instance()->checkTitleDuplicate($uniqueTitle, (string) $existing->_id))->toBeFalse();
    expect($component->get('showDuplicateModal'))->toBeFalse();
});

test('profile publications validates issn tabs, checksum, issue format, and persists crud with pdf and profile sync', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $articleType = Article::create(['name' => 'Review Article']);
    $journal = ReviewerJournal::create([
        'journal_title' => 'IEEE Access',
        'e_issn' => '2169-3536',
        'status' => 1,
    ]);

    $this->actingAs($user);

    $component = Volt::test('profile.publications', ['user' => $user, 'isOwnProfile' => true]);

    // Invalid ISSN checksum & missing required fields
    $invalidRes = $component->instance()->saveRecord([
        'title' => '',
        'type' => '',
        'journal_title' => '',
        'issn_type' => 'e_issn',
        'e_issn' => '1234-5678', // invalid checksum
        'published_date' => '',
        'issue' => 'bad issue!!',
        'description' => '',
        'authors' => [['name' => '', 'affiliation' => '', 'email' => 'not-an-email']],
    ]);

    expect($invalidRes['ok'])->toBeFalse();
    expect($invalidRes['errors'])->toHaveKeys(['title', 'type', 'journal_title', 'e_issn', 'published_date', 'issue', 'description', 'authors.0.name', 'authors.0.email']);

    // Valid save with PDF upload
    $pdf = UploadedFile::fake()->create('research-paper.pdf', 512, 'application/pdf');
    $component->set('pdfFile', $pdf);

    $uniqueTitle = 'Validated Edge Computing Survey '.uniqid();
    $saveRes = $component->instance()->saveRecord([
        'doi' => '10.1109/ACCESS.2025.999999',
        'title' => $uniqueTitle,
        'url' => 'https://ieeexplore.ieee.org/document/999999',
        'type' => (string) $articleType->_id,
        'journal_title' => (string) $journal->_id,
        'journal_name' => 'IEEE Access',
        'issn_type' => 'e_issn',
        'e_issn' => '2169-3536',
        'p_issn' => '',
        'published_date' => 'October 2025',
        'citations' => '5',
        'volume' => '13',
        'issue' => 'S1',
        'page_no' => '10-25',
        'publication_keywords' => ['Edge Computing', 'IoT'],
        'description' => 'Comprehensive survey of edge computing.',
        'authors' => [
            ['name' => 'Dr. Bob', 'affiliation' => 'ETH Zurich', 'email' => $user->email],
        ],
    ]);

    expect($saveRes['errors'] ?? [])->toBeEmpty();
    expect($saveRes['ok'])->toBeTrue();
    expect($saveRes['records'])->toHaveCount(1);

    $created = Publication::where('title', $uniqueTitle)->first();
    expect($created)->not->toBeNull();
    expect($created->e_issn)->toBe('2169-3536');
    expect($created->volume)->toBe(13);
    expect($created->citations)->toBe(5);
    expect($created->image)->not->toBeNull();
    Storage::disk('public')->assertExists($created->image);

    $profile = ReviewerProfile::where('user_id', (string) $user->_id)->first();
    expect($profile->publication)->toContain((string) $created->_id);

    // Update record
    $updatedTitle = $uniqueTitle.' (Updated)';
    $updateRes = $component->instance()->saveRecord([
        'id' => (string) $created->_id,
        'title' => $updatedTitle,
        'type' => (string) $articleType->_id,
        'journal_title' => (string) $journal->_id,
        'journal_name' => 'IEEE Access',
        'issn_type' => 'e_issn',
        'e_issn' => '2169-3536',
        'published_date' => 'November 2025',
        'citations' => '10',
        'volume' => '14',
        'issue' => '2',
        'description' => 'Updated abstract.',
        'authors' => [['name' => 'Dr. Bob', 'affiliation' => 'ETH Zurich', 'email' => '']],
    ]);

    expect($updateRes['ok'])->toBeTrue();
    expect(Publication::find($created->_id)->title)->toBe($updatedTitle);

    // Delete record
    $pdfPath = $created->image;
    $remaining = $component->instance()->deleteRecord((string) $created->_id);
    expect($remaining)->toHaveCount(0);
    expect(Publication::find($created->_id))->toBeNull();
    Storage::disk('public')->assertMissing($pdfPath);
});

test('shared add new journal modal works inside publications component', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $uniqueTitle = 'Journal of Test Publications '.uniqid();
    ReviewerJournal::where('e_issn', '1932-6203')->orWhere('p_issn', '1932-6203')->delete();

    Http::fake([
        'https://api.crossref.org/journals/*' => Http::response([
            'status' => 'ok',
            'message' => [
                'title' => $uniqueTitle,
                'publisher' => 'Test Publisher',
                'ISSN' => ['1932-6203'],
            ],
        ], 200),
        '*' => Http::response([], 404),
    ]);

    Volt::test('profile.publications', ['user' => $user, 'isOwnProfile' => true])
        ->set('newJournalTitle', $uniqueTitle)
        ->set('newJournalEIssn', '1932-6203')
        ->call('saveNewJournal')
        ->assertHasNoErrors()
        ->assertDispatched('journal-created');

    expect(ReviewerJournal::where('journal_title', $uniqueTitle)->exists())->toBeTrue();
});

test('admin publications partial filters and toggles active/inactive status with red inactive badge', function () {
    $admin = User::factory()->create(['type' => 1]);
    $user = User::factory()->create();
    $articleType = Article::create(['name' => 'Original Research']);

    $pub = Publication::create([
        'user_id' => (string) $user->_id,
        'title' => 'Admin Managed Paper',
        'type' => (string) $articleType->_id,
        'journal_name' => 'Test Journal',
        'e_issn' => '2041-1723',
        'published_date' => 'January 2026',
        'authors' => [['name' => 'Author One', 'affiliation' => '', 'email' => '']],
        'description' => 'Test abstract.',
        'status' => 1,
        'serial_number' => 3001,
        'slug' => 'admin-managed-paper-3001',
    ]);

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'publication' => [(string) $pub->_id],
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.users.partials.publications', ['user' => $user])
        ->assertSee('Admin Managed Paper')
        ->call('toggleStatus', (string) $pub->_id)
        ->assertSet('statusFilter', 'all');

    expect((int) Publication::find($pub->_id)->status)->toBe(0);
    $component->assertSeeHtml('bg-red-500');
});

test('publications section shows 5 records initially and loads more on click', function () {
    $user = User::factory()->create();
    $articleType = Article::create(['name' => 'Research Article']);
    $pubIds = [];

    for ($i = 1; $i <= 7; $i++) {
        $pub = Publication::create([
            'user_id' => (string) $user->_id,
            'title' => "Paginated Publication Item {$i}",
            'type' => (string) $articleType->_id,
            'journal_name' => 'Test Journal',
            'published_date' => 'May 2025',
            'description' => "Abstract {$i}",
            'status' => 1,
            'serial_number' => 4000 + $i,
            'slug' => "paginated-publication-item-{$i}-".(4000 + $i),
            'created_at' => now()->addSeconds($i),
        ]);
        $pubIds[] = (string) $pub->_id;
    }

    ReviewerProfile::create([
        'user_id' => (string) $user->_id,
        'publication' => $pubIds,
    ]);

    $component = Volt::test('profile.publications', ['user' => $user, 'isOwnProfile' => false])
        ->assertSee('Paginated Publication Item 7')
        ->assertSee('Paginated Publication Item 3')
        ->assertDontSee('Paginated Publication Item 2')
        ->assertDontSee('Paginated Publication Item 1')
        ->assertSee('Load More (2)')
        ->call('loadMore')
        ->assertSee('Paginated Publication Item 2')
        ->assertSee('Paginated Publication Item 1')
        ->assertDontSee('Load More');

    $component->assertSet('limit', 10);
});

test('saving with e_issn only ignores leftover invalid p_issn and saves successfully', function () {
    $user = User::factory()->create();
    $articleType = Article::create(['name' => 'Original Article']);
    $journal = ReviewerJournal::create([
        'journal_title' => 'International Journal of Innovation '.uniqid(),
        'e_issn' => '2252-2824',
    ]);
    $title = 'Article With E-ISSN Only '.uniqid();

    $component = Volt::actingAs($user)
        ->test('profile.publications', ['user' => $user, 'isOwnProfile' => true]);

    $returned = $component->instance()->saveRecord([
        'title' => $title,
        'type' => (string) $articleType->_id,
        'journal_title' => (string) $journal->_id,
        'issn_type' => 'e_issn',
        'e_issn' => '2252-2824',
        'p_issn' => 'invalid-p-issn-here',
        'published_date' => 'November 2026',
        'description' => 'Test publication description',
        'authors' => [
            ['name' => 'Dr. Test Author', 'affiliation' => 'Test University', 'email' => 'author@example.com'],
        ],
    ]);

    expect($returned['ok'])->toBeTrue();

    $saved = Publication::where('title', $title)->first();
    expect($saved)->not->toBeNull()
        ->and($saved->e_issn)->toBe('2252-2824')
        ->and($saved->p_issn)->toBeNull()
        ->and($saved->issn)->toBe('2252-2824');
});

test('saving with a future published date fails validation', function () {
    $user = User::factory()->create();
    $articleType = Article::create(['name' => 'Original Article']);
    $journal = ReviewerJournal::create([
        'journal_title' => 'International Journal of Future '.uniqid(),
        'e_issn' => '2252-2824',
    ]);

    $component = Volt::actingAs($user)
        ->test('profile.publications', ['user' => $user, 'isOwnProfile' => true]);

    $returned = $component->instance()->saveRecord([
        'title' => 'Future Date Publication '.uniqid(),
        'type' => (string) $articleType->_id,
        'journal_title' => (string) $journal->_id,
        'issn_type' => 'e_issn',
        'e_issn' => '2252-2824',
        'published_date' => 'December 2099',
        'description' => 'Test publication description',
        'authors' => [
            ['name' => 'Dr. Test Author', 'affiliation' => 'Test University', 'email' => 'author@example.com'],
        ],
    ]);

    expect($returned['ok'])->toBeFalse()
        ->and($returned['errors']['published_date'][0])->toBe('Publication Date cannot be in the future.');
});
