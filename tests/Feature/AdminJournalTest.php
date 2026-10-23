<?php

use App\Models\Country;
use App\Models\IndexingAgency;
use App\Models\ReviewerJournal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = new User([
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'is_admin' => true,
    ]);

    Cache::flush();

    if (IndexingAgency::count() === 0) {
        $agencies = [
            'Google Scholar', 'Scopus', 'WoS', 'DOAJ', 'PubMed', 'Crossref',
            'ISI', 'Publon', 'EBSCO', 'IJIFACTOR', 'ABCD', 'Elsevier',
            'Science Direct', 'Research Gate', 'Scholastica', 'J-Gate',
            'SJR', 'Mendeley', 'CAS', 'ProQuest',
        ];
        foreach ($agencies as $idx => $name) {
            IndexingAgency::create([
                'agency_name' => $name,
                'status' => 1,
                'serial_number' => $idx + 1,
            ]);
        }
    }

    if (Country::where('sortname', 'IN')->count() === 0) {
        Country::create([
            'id' => 101,
            'sortname' => 'IN',
            'name' => 'India',
            'phoneCode' => 91,
        ]);
    }
});

test('admin journals routes are registered', function () {
    expect(Route::has('admin.journals.index'))->toBeTrue();
    expect(Route::has('admin.journals.create'))->toBeTrue();
    expect(Route::has('admin.journals.edit'))->toBeTrue();
});

test('admin user can view add journal page with all required fields and sections', function () {
    $this->actingAs($this->admin);

    Volt::test('admin.journals.create')
        ->assertSee('Select Publisher')
        ->assertSee('Conference Charges')
        ->assertSee('Upload Your Journal Photo')
        ->assertSee('Journal Full Name')
        ->assertSee('Journal Short Name')
        ->assertSee('e-ISSN')
        ->assertSee('p-ISSN')
        ->assertSee('Journal Website URL')
        ->assertSee('Year of First Publication')
        ->assertSee('ISSN Assignee Country')
        ->assertSee('Journal Impact Factor')
        ->assertSee('Impact Factor Assignee')
        ->assertSee('Languages in which manuscripts are accepted')
        ->assertSee('Issue Frequency')
        ->assertSee('Publisher Name')
        ->assertSee('Journal Discipline')
        ->assertSee('Editor in Chief or Contact Person Name')
        ->assertSee('Editor in Chief Email id')
        ->assertSee('Mobile No')
        ->assertSee('Add Editorial Board Members')
        ->assertSee('About Journal')
        ->assertSee('Address')
        ->assertSee('Country')
        ->assertSee('State')
        ->assertSee('City')
        ->assertSee('Postal/Zip Code')
        ->assertSee('Does your Journal use an open-access license')
        ->assertSee('Does your journal follow COPE Guidelines')
        ->assertSee('Do authors retain copyright and full publishing rights')
        ->assertSee('Does your journal follow peer review')
        ->assertSee('Does the journal regularly conduct plagiarism checks')
        ->assertSee('What is the publication platform utilized by your journal')
        ->assertSee('Are there any fees associated with publishing an article')
        ->assertSee('Does the journal have a preservation service and archiving policy')
        ->assertSee('Does your journal have DOI')
        ->assertSee('Google Scholar')
        ->assertSee('Scopus')
        ->assertSee('WoS')
        ->assertSee('DOAJ')
        ->assertSee('PubMed')
        ->assertSee('Crossref')
        ->assertSee('ISI')
        ->assertSee('Publon')
        ->assertSee('EBSCO')
        ->assertSee('IJIFACTOR')
        ->assertSee('ABCD')
        ->assertSee('Elsevier')
        ->assertSee('Science Direct')
        ->assertSee('Research Gate')
        ->assertSee('Scholastica')
        ->assertSee('J-Gate')
        ->assertSee('SJR')
        ->assertSee('Mendeley')
        ->assertSee('CAS')
        ->assertSee('ProQuest');
});

test('admin can create a journal with complete dataset', function () {
    $this->actingAs($this->admin);

    $title = 'International Journal of Quantum Computing '.uniqid();

    $authorUser = User::create([
        'first_name' => 'Bob',
        'last_name' => 'Smith',
        'email' => 'bob.'.uniqid().'@test.org',
        'password' => bcrypt('password'),
        'photo' => 'uploads/photos/bob.png',
    ]);

    Volt::test('admin.journals.create')
        ->set('journal_title', $title)
        ->set('journal_short_name', 'IJQC')
        ->set('e_issn', '2581-'.rand(1000, 9999))
        ->set('p_issn', '2582-'.rand(1000, 9999))
        ->set('journal_webiste_url', 'https://ijqc-journal.org')
        ->set('first_publication_year', 2020)
        ->set('issn_assignee_country', 'United States')
        ->set('journal_impact_factor', '4.85')
        ->set('impact_factor_assignee_agency', 'Clarivate')
        ->set('languages', ['English'])
        ->set('issue_frequency', 'Monthly')
        ->set('organization_name', 'Global Science Press')
        ->set('journal_subjects', ['Computer Science'])
        ->set('contact_name', 'Dr. Alice Quantum')
        ->set('contact_email', 'alice@ijqc-journal.org')
        ->set('country_code', '+91')
        ->set('mobile', '1234567890')
        ->set('conference_charges', '60')
        ->set('editorial_members', [
            ['user_id' => (string) $authorUser->_id, 'name' => 'Bob Smith', 'role_id' => 'Editor-in-Chief', 'photo' => 'uploads/photos/bob.png'],
        ])
        ->set('journal_description', 'A premier open access peer reviewed journal focused on quantum systems.')
        ->set('address', '100 Science Parkway')
        ->set('country', '231')
        ->set('zipcode', '94016')
        ->set('JournalOpenAccess', '1')
        ->set('COPEguidelines', '1')
        ->set('restrictions', '1')
        ->set('followpeer', '1')
        ->set('plagiarism', '1')
        ->set('publicationPlatform', 'Own Customized Platform')
        ->set('feesAssociatedWithPublishing', '0')
        ->set('preservation', '1')
        ->set('DOI', '1')
        ->set('doi', '10.1234/ijqc.2026')
        ->set('indexing.Google_Scholar.checked', true)
        ->set('indexing.Google_Scholar.url', 'https://scholar.google.com/citations?user=sample')
        ->set('indexing.Scopus.checked', true)
        ->set('indexing.Scopus.url', 'https://www.scopus.com/sourceid/sample')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.journals.index'));

    expect(session('message'))->toContain('has been successfully created!');

    $created = ReviewerJournal::where('journal_title', $title)->first();
    expect($created)->not->toBeNull();
    expect($created->journal_short_name)->toBe('IJQC');
    expect($created->conference_charges)->toBe('60');
    expect($created->conference_allowed)->toBe('1');
    expect($created->country_code)->toBe('+91');
    expect($created->mobile)->toBe('1234567890');
    expect($created->publicationPlatform)->toBe('Own Customized Platform');
    expect($created->DOI)->toBe('1');
    expect($created->doi)->toBe('10.1234/ijqc.2026');
    expect($created->slug)->not->toBeNull();
    expect($created->serial_number)->not->toBeNull();
    expect($created->Google_Scholar_checkbox)->toBeTrue();
    expect($created->Google_Scholar)->toBe('https://scholar.google.com/citations?user=sample');
    expect($created->Scopus_checkbox)->toBeTrue();
    expect($created->Scopus)->toBe('https://www.scopus.com/sourceid/sample');
    expect($created->registered_co_author)->toHaveCount(1);
    expect($created->registered_co_author[0]['user_id'])->toBe((string) $authorUser->_id);
    expect($created->registered_co_author[0]['role_id'])->toBe('Editor-in-Chief');
    expect(array_key_exists('name', $created->registered_co_author[0]))->toBeFalse();
    expect(array_key_exists('photo', $created->registered_co_author[0]))->toBeFalse();

    // Verify edit component resolves fresh name and photo dynamically from User model by user_id
    $editComp = Volt::test('admin.journals.edit', ['journal' => $created]);
    $loadedMembers = $editComp->get('editorial_members');
    expect($loadedMembers)->toHaveCount(1);
    expect($loadedMembers[0]['name'])->toBe('Bob Smith');
    expect($loadedMembers[0]['photo'])->toBe('uploads/photos/bob.png');

    // Clean up
    $authorUser->delete();
    $created->delete();
});

test('admin can view journals index page and filter by search query', function () {
    $this->actingAs($this->admin);

    $testJournal = ReviewerJournal::create([
        'journal_title' => 'Sample Test Journal '.uniqid(),
        'journal_short_name' => 'STJ',
        'status' => '1',
        'e_issn' => '9999-1111',
    ]);

    Volt::test('admin.journals.index')
        ->assertSee('Manage Journals')
        ->assertSee('Add Journal')
        ->set('search', 'STJ')
        ->assertSee($testJournal->journal_title);

    $testJournal->delete();
});

test('admin can update a journal via edit component', function () {
    $this->actingAs($this->admin);

    $journal = ReviewerJournal::create([
        'journal_title' => 'Original Journal Title '.uniqid(),
        'journal_short_name' => 'OJT',
        'organization_name' => 'Original Org',
        'journal_webiste_url' => 'https://original-journal.org',
        'first_publication_year' => 2021,
        'e_issn' => '1111-2222',
        'contact_name' => 'Original Contact',
        'contact_email' => 'contact@test.com',
        'mobile' => '9876543210',
        'journal_description' => 'Original description test',
        'address' => '123 Test Street',
        'country' => '231',
        'zipcode' => '12345',
        'status' => '1',
    ]);

    $updatedTitle = 'Updated Journal Title '.uniqid();

    Volt::test('admin.journals.edit', ['journal' => $journal])
        ->set('journal_title', $updatedTitle)
        ->set('journal_short_name', 'UJT')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.journals.index'));

    expect(session('message'))->toContain('has been successfully updated!');

    $fresh = ReviewerJournal::find($journal->_id);
    expect($fresh->journal_title)->toBe($updatedTitle);
    expect($fresh->journal_short_name)->toBe('UJT');

    $fresh->delete();
});

test('admin journal edit enforces same cross field validation as create', function () {
    $this->actingAs($this->admin);

    ReviewerJournal::where('e_issn', '3333-4444')->orWhere('p_issn', '3333-4444')->delete();

    $journal = ReviewerJournal::create([
        'journal_title' => 'Validation Test Journal '.uniqid(),
        'journal_short_name' => 'VTJ',
        'organization_name' => 'Org Name',
        'journal_webiste_url' => 'https://vtj.org',
        'first_publication_year' => 2022,
        'e_issn' => '3333-4444',
        'contact_name' => 'Contact Person',
        'contact_email' => 'contact@vtj.org',
        'mobile' => '9876543210',
        'journal_description' => 'Description test',
        'address' => '456 Test Ave',
        'country' => '231',
        'zipcode' => '67890',
        'status' => '1',
    ]);

    // Clearing both ISSNs fails validation
    Volt::test('admin.journals.edit', ['journal' => $journal])
        ->set('e_issn', '')
        ->set('p_issn', '')
        ->call('save')
        ->assertHasErrors(['e_issn']);

    // Setting same P-ISSN as E-ISSN fails validation
    Volt::test('admin.journals.edit', ['journal' => $journal])
        ->set('e_issn', '5555-6666')
        ->set('p_issn', '5555-6666')
        ->call('save')
        ->assertHasErrors(['p_issn']);

    // checkIssn method excludes current journal being edited
    $component = Volt::test('admin.journals.edit', ['journal' => $journal]);
    expect($component->instance()->checkIssn('3333-4444'))->toBeNull();

    $journal->delete();
});

test('admin journal create auto detects country from client timezone', function () {
    $this->actingAs($this->admin);

    $component = Volt::test('admin.journals.create')
        ->call('autoDetectCountry', 'Asia/Kolkata');

    expect($component->get('country'))->toBe('101');
    expect($component->get('issn_assignee_country'))->toBe('101');
});

test('admin journal create has default publication platform as first option', function () {
    $this->actingAs($this->admin);

    $component = Volt::test('admin.journals.create');
    expect($component->get('publicationPlatform'))->toBe('Open Journal Systems (OJS). The Public Knowledge Project');
});
