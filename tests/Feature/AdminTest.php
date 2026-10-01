<?php

use App\Models\Experience;
use App\Models\Publication;
use App\Models\ReviewerJournal;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

test('admin routes are registered', function () {
    expect(Route::has('admin.dashboard'))->toBeTrue();
    expect(Route::has('admin.publications.index'))->toBeTrue();
    expect(Route::has('admin.users.index'))->toBeTrue();
    expect(Route::has('admin.database-fixing.index'))->toBeTrue();
    expect(Route::has('admin.database-fixing.publication.registered-co-author'))->toBeTrue();
    expect(Route::has('admin.database-fixing.publication.unregistered-co-author'))->toBeTrue();
    expect(Route::has('admin.database-fixing.reviewer-profile.index'))->toBeTrue();
    expect(Route::has('admin.database-fixing.reviewer-profile.field'))->toBeTrue();
    expect(Route::has('admin.database-fixing.experience.index'))->toBeTrue();
    expect(Route::has('admin.database-fixing.experience.field'))->toBeTrue();
});

test('guests are redirected away from admin dashboard', function () {
    $response = $this->get(route('admin.dashboard'));
    $response->assertRedirect('/login');
});

test('non-admin user is forbidden with 403 on admin routes', function () {
    $user = new User([
        'name' => 'Regular Scholar',
        'email' => 'scholar@test.com',
        'role' => 'scholar',
        'is_admin' => false,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('admin.dashboard'));
    $response->assertStatus(403);
});

test('admin user can access admin dashboard and views', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.dashboard')
        ->assertSee('Scholar Administration Control')
        ->assertSee('Total Publications')
        ->assertSee('Database Fixing');
});

test('database fixing index component renders correctly', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.index')
        ->assertSee('Database Fixing')
        ->assertSee('registered_co_author')
        ->assertSee('unregistered_co_author');
});

test('publication model supports core attributes', function () {
    $pub = new Publication([
        'title' => 'Deep Learning in Genomics',
        'description' => 'Legacy description field for abstract',
        'publication_keywords' => ['Genomics', 'Bioinformatics'],
        'citations' => 45,
        'published_date' => '2026-05',
        'view_counts' => 120,
    ]);

    expect($pub->title)->toBe('Deep Learning in Genomics')
        ->and($pub->description)->toBe('Legacy description field for abstract')
        ->and($pub->publication_keywords)->toBe(['Genomics', 'Bioinformatics'])
        ->and($pub->citations)->toBe(45)
        ->and($pub->published_date)->toBe('2026-05')
        ->and($pub->view_counts)->toBe(120);
});

test('publications management component renders with renamed headers and sorting capabilities', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.publications.index')
        ->assertSee('Publications Management')
        ->assertSee('Title')
        ->assertSee('Journal Name')
        ->assertSee('Status')
        ->call('sortBy', 'title')
        ->assertSet('sortField', 'title')
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'title')
        ->assertSet('sortDirection', 'desc');
});

test('reviewer journal model supports core attributes and casts', function () {
    $journal = new ReviewerJournal([
        'journal_title' => 'International Journal of Quantum Computing',
        'slug' => 'international-journal-of-quantum-computing',
        'e_issn' => '2456-1234',
        'status' => 1,
        'is_credited' => true,
        'languages' => ['English', 'German'],
        'journal_subjects' => ['Computer Science', 'Quantum Physics'],
    ]);

    expect($journal->journal_title)->toBe('International Journal of Quantum Computing')
        ->and($journal->slug)->toBe('international-journal-of-quantum-computing')
        ->and($journal->e_issn)->toBe('2456-1234')
        ->and($journal->status)->toBe(1)
        ->and($journal->is_credited)->toBeTrue()
        ->and($journal->languages)->toBe(['English', 'German'])
        ->and($journal->journal_subjects)->toBe(['Computer Science', 'Quantum Physics']);
});

test('reviewer profile database fixing component renders correctly', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.reviewer-profile.index')
        ->assertSee('ReviewerProfile')
        ->assertSee('Fixer')
        ->assertSee('experience')
        ->assertSee('patent')
        ->assertSee('RoleInResearchJournal')
        ->call('setField', 'patent')
        ->assertSet('activeField', 'patent');
});

test('experience model standardizes date formatting to Month Year', function () {
    expect(Experience::formatMonthYear('August 2015'))->toBe('August 2015')
        ->and(Experience::formatMonthYear('2015-08-01'))->toBe('August 2015')
        ->and(Experience::formatMonthYear('08/2015'))->toBe('August 2015')
        ->and(Experience::formatMonthYear('January 2026'))->toBe('January 2026')
        ->and(Experience::formatMonthYear('2026-07-15'))->toBe('July 2026')
        ->and(Experience::formatMonthYear('Present', true))->toBe('Present')
        ->and(Experience::formatMonthYear(''))->toBeNull();
});

test('experience date standardizer database fixing component renders correctly', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.experience.index')
        ->assertSee('Experience')
        ->assertSee('Date Standardizer')
        ->assertSee('join_date')
        ->assertSee('end_date')
        ->call('setField', 'end_date')
        ->assertSet('activeField', 'end_date');
});

test('reviewer profile orphan cleaner components render correctly', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Volt::test('admin.database-fixing.reviewer-profile.orphan-cleaner.publication')
        ->assertSee('ReviewerProfile')
        ->assertSee('Ghost IDs Removed')
        ->assertSee('Active Papers Preserved')
        ->assertSee('Inactive Papers Preserved');

    Volt::test('admin.database-fixing.reviewer-profile.orphan-cleaner.experience')
        ->assertSee('ReviewerProfile')
        ->assertSee('experience Orphan Cleaner');

    Volt::test('admin.database-fixing.reviewer-profile.orphan-cleaner.seminar')
        ->assertSee('ReviewerProfile')
        ->assertSee('seminar Orphan Cleaner');

    Volt::test('admin.database-fixing.reviewer-profile.orphan-cleaner.role-in-research-journal')
        ->assertSee('ReviewerProfile')
        ->assertSee('RoleInResearchJournal Orphan Cleaner');
});

test('publication missing journal_title inspector component renders correctly', function () {
    $admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    expect(Route::has('admin.database-fixing.publication.orphan-journal'))->toBeTrue();

    $test = Volt::test('admin.database-fixing.publication.orphan-journal')
        ->assertSee('Publication — Missing / Ghost journal_title Inspector')
        ->assertSee('Orphan Journal Records')
        ->assertSee('Unique Ghost Journal IDs');

    $sample = Publication::first();
    if ($sample) {
        $test->call('inspectRecord', (string) $sample->_id)
            ->assertSee('Publication Document Inspection')
            ->call('closeInspectModal')
            ->assertDontSee('Publication Document Inspection');
    }
});
