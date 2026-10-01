<?php

use App\Models\ReviewerJournal;
use Livewire\Volt\Volt;

test('deposit article route is registered', function () {
    $this->assertTrue(Route::has('articles.deposit'));
});

test('article list route is registered', function () {
    $this->assertTrue(Route::has('articles.index'));
});

test('volt deposit component compiles and renders correctly', function () {
    Volt::test('article.deposit')
        ->assertSee('Paper title')
        ->assertSee('Step 1: Paper Information')
        ->assertSee('Pre-Print')
        ->assertSee('Published');
});

test('defaults option to preprint and allows adding keyword tags', function () {
    Volt::test('article.deposit')
        ->assertSet('option', 'preprint')
        ->set('newKeyword', 'Machine Learning')
        ->call('addKeyword')
        ->assertSet('keywordsList', ['Machine Learning'])
        ->call('removeKeyword', 0)
        ->assertSet('keywordsList', []);
});

test('shows published fields when option is set to published', function () {
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->assertSee('Article Type')
        ->assertSee('Journal name')
        ->assertSee('RETRIEVE');
});

test('can navigate to step 2 and validate step 2 fields', function () {
    Volt::test('article.deposit')
        ->set('title', 'Quantum Computing Advances in 2026')
        ->set('abstract', 'This paper explores quantum speedups and decoherence mitigation techniques.')
        ->set('keywordsList', ['Quantum', 'Physics'])
        ->set('option', 'preprint')
        ->call('goToNextStep')
        ->assertSet('currentStep', 2)
        ->assertSee('Step 2: Upload File and Author Details')
        ->assertSee('Upload File')
        ->assertSee('Add Author')
        ->assertSee('confirm copyright and licensing agreement.');
});

test('validates volume and citation as numeric values when option is published', function () {
    Volt::test('article.deposit')
        ->set('title', 'Quantum Computing Advances in 2026')
        ->set('abstract', 'This paper explores quantum speedups and decoherence mitigation techniques.')
        ->set('keywordsList', ['Quantum', 'Physics'])
        ->set('option', 'published')
        ->set('articleType', 'Research Article')
        ->set('journalName', 'International Journal of Quantum')
        ->set('issue', '1')
        ->set('pageNo', '1-10')
        ->set('pubMonthYear', '01-2025')
        ->set('volume', 'abc') // Non-numeric
        ->set('citation', 'invalid') // Non-numeric
        ->call('goToNextStep')
        ->assertHasErrors(['volume' => 'numeric', 'citation' => 'numeric']);
});

test('requires issn when option is published', function () {
    Volt::test('article.deposit')
        ->set('title', 'Quantum Computing Advances in 2026')
        ->set('abstract', 'This paper explores quantum speedups and decoherence mitigation techniques.')
        ->set('keywordsList', ['Quantum', 'Physics'])
        ->set('option', 'published')
        ->set('articleType', 'Research Article')
        ->set('journalName', 'International Journal of Quantum')
        ->set('volume', '1')
        ->set('pubMonthYear', '01-2025')
        ->set('issnType', 'both')
        ->set('eIssn', '')
        ->set('pIssn', '')
        ->set('issue', '1')
        ->set('pageNo', '1-10')
        ->call('goToNextStep')
        ->assertHasErrors(['eIssn' => 'required', 'pIssn' => 'required']);
});

test('validates issn, issue, and pageNo format when option is published', function () {
    Volt::test('article.deposit')
        ->set('title', 'Quantum Computing Advances in 2026')
        ->set('abstract', 'This paper explores quantum speedups and decoherence mitigation techniques.')
        ->set('keywordsList', ['Quantum', 'Physics'])
        ->set('option', 'published')
        ->set('articleType', 'Research Article')
        ->set('journalName', 'International Journal of Quantum')
        ->set('volume', '1')
        ->set('pubMonthYear', '01-2025')
        ->set('eIssn', 'invalid-issn')
        ->set('issue', 'bad issue format!')
        ->set('pageNo', '')
        ->call('goToNextStep')
        ->assertHasErrors(['eIssn', 'issue' => 'regex', 'pageNo' => 'required']);
});

test('rejects format-valid but checksum-invalid issn', function () {
    Volt::test('article.deposit')
        ->set('title', 'Quantum Computing Advances in 2026')
        ->set('abstract', 'This paper explores quantum speedups and decoherence mitigation techniques.')
        ->set('keywordsList', ['Quantum', 'Physics'])
        ->set('option', 'published')
        ->set('articleType', 'Research Article')
        ->set('journalName', 'International Journal of Quantum')
        ->set('volume', '1')
        ->set('pubMonthYear', '01-2025')
        ->set('eIssn', '1234-5678') // Format matches ^\d{4}-\d{4}$ but checksum is invalid (expected 9)
        ->set('issue', '1')
        ->set('pageNo', '1-10')
        ->call('goToNextStep')
        ->assertHasErrors(['eIssn']);
});

test('accepts valid issn, issue, and pageNo format', function () {
    Volt::test('article.deposit')
        ->set('title', 'Quantum Computing Advances in 2026')
        ->set('abstract', 'This paper explores quantum speedups and decoherence mitigation techniques.')
        ->set('keywordsList', ['Quantum', 'Physics'])
        ->set('option', 'published')
        ->set('articleType', 'Research Article')
        ->set('journalName', 'International Journal of Quantum')
        ->set('volume', '1')
        ->set('pubMonthYear', '01-2025')
        ->set('issnType', 'both')
        ->set('eIssn', '1234-5679')
        ->set('pIssn', '1234-1258')
        ->set('issue', '1-2')
        ->set('pageNo', '10-25')
        ->call('goToNextStep')
        ->assertHasNoErrors(['eIssn', 'pIssn', 'issn', 'issue', 'pageNo'])
        ->assertSet('currentStep', 2);
});

test('accepts valid issn ending with X check digit', function () {
    Volt::test('article.deposit')
        ->set('title', 'Quantum Computing Advances in 2026')
        ->set('abstract', 'This paper explores quantum speedups and decoherence mitigation techniques.')
        ->set('keywordsList', ['Quantum', 'Physics'])
        ->set('option', 'published')
        ->set('articleType', 'Research Article')
        ->set('journalName', 'BMJ Case Reports')
        ->set('volume', '1')
        ->set('pubMonthYear', '01-2025')
        ->set('issnType', 'e_issn')
        ->set('eIssn', '1757-790X')
        ->set('issue', '1')
        ->set('pageNo', '1-5')
        ->call('goToNextStep')
        ->assertHasNoErrors(['eIssn', 'issn'])
        ->assertSet('currentStep', 2);
});

test('can search and select journal from reviewer journals prioritizing e_issn', function () {
    $journal = ReviewerJournal::create([
        'journal_title' => 'Advanced AI & Machine Learning Journal',
        'journal_short_name' => 'AAIMLJ',
        'e_issn' => '1757-790X',
        'p_issn' => '1234-5679',
        'status' => 1,
    ]);

    Volt::test('article.deposit')
        ->set('option', 'published')
        ->set('journalName', 'Advanced AI')
        ->assertSet('showJournalDropdown', true)
        ->assertSee('Advanced AI & Machine Learning Journal')
        ->call('selectJournal', (string) $journal->_id, 'Advanced AI & Machine Learning Journal', '1757-790X', '1234-5679')
        ->assertSet('selectedJournalId', (string) $journal->_id)
        ->assertSet('journalName', 'Advanced AI & Machine Learning Journal')
        ->assertSet('issnType', 'both')
        ->assertSet('eIssn', '1757-790X')
        ->assertSet('pIssn', '1234-5679')
        ->assertSet('issn', '1757-790X')
        ->assertSet('showJournalDropdown', false);

    $journal->delete();
});

test('populates p_issn when e_issn is not available', function () {
    $journal = ReviewerJournal::create([
        'journal_title' => 'Print Only Medical Journal',
        'journal_short_name' => 'POMJ',
        'e_issn' => '',
        'p_issn' => '1234-5679',
        'status' => 1,
    ]);

    Volt::test('article.deposit')
        ->set('option', 'published')
        ->set('journalName', 'Print Only')
        ->call('selectJournal', (string) $journal->_id, 'Print Only Medical Journal', '', '1234-5679')
        ->assertSet('selectedJournalId', (string) $journal->_id)
        ->assertSet('journalName', 'Print Only Medical Journal')
        ->assertSet('isJournalVerified', true)
        ->assertSet('issnType', 'p_issn')
        ->assertSet('pIssn', '1234-5679')
        ->assertSet('issn', '1234-5679')
        ->assertSet('showJournalDropdown', false);

    $journal->delete();
});

test('verifies journal name exists in database and shows verified state', function () {
    $uniqueTitle = 'Unique Medical Journal '.uniqid();
    $journal = ReviewerJournal::create([
        'journal_title' => $uniqueTitle,
        'e_issn' => '1757-790X',
        'status' => 1,
    ]);

    Volt::test('article.deposit')
        ->set('option', 'published')
        ->set('journalName', $uniqueTitle)
        ->call('checkJournalVerification')
        ->assertSet('isJournalVerified', true)
        ->assertSet('selectedJournalId', (string) $journal->_id)
        ->assertSee('Verified');

    $journal->delete();
});

test('can open add journal modal and validates required fields', function () {
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->set('journalName', 'Custom New Scientific Journal')
        ->call('openAddJournalModal')
        ->assertSet('showAddJournalModal', true)
        ->assertSet('newJournalTitle', 'Custom New Scientific Journal')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalIssnRequired']);
});

test('can add a new journal via modal and auto-selects it', function () {
    $uniqueTitle = 'Bioinformatics NextGen Journal '.uniqid();
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', $uniqueTitle)
        ->set('newJournalEIssn', '2049-3630')
        ->set('newJournalPIssn', '0974-3154')
        ->call('saveNewJournal')
        ->assertSet('showAddJournalModal', false)
        ->assertSet('journalName', $uniqueTitle)
        ->assertSet('isJournalVerified', true)
        ->assertSet('issnType', 'both')
        ->assertSet('eIssn', '2049-3630')
        ->assertSet('pIssn', '0974-3154');

    // Clean up created journal
    ReviewerJournal::where('journal_title', $uniqueTitle)->delete();
});

test('cancelling add journal modal clears unverified journal name input', function () {
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->set('journalName', 'Non Existing Journal ABC')
        ->call('openAddJournalModal')
        ->assertSet('showAddJournalModal', true)
        ->assertSet('newJournalTitle', 'Non Existing Journal ABC')
        ->call('closeAddJournalModal')
        ->assertSet('showAddJournalModal', false)
        ->assertSet('journalName', '')
        ->assertSet('isJournalVerified', false);
});

test('rejects duplicate journal title in add journal modal', function () {
    $existing = ReviewerJournal::create([
        'journal_title' => 'Duplicate Title Test Journal',
        'e_issn' => '1757-790X',
        'status' => 1,
    ]);

    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', 'Duplicate Title Test Journal')
        ->set('newJournalEIssn', '1234-5679')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalTitle' => 'This journal name already exists in the database.']);

    $existing->delete();
});

test('rejects duplicate e_issn and p_issn in add journal modal', function () {
    $existing = ReviewerJournal::create([
        'journal_title' => 'Unique First Journal',
        'e_issn' => '1757-790X',
        'p_issn' => '1234-5679',
        'status' => 1,
    ]);

    // Duplicate e-ISSN
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', 'Brand New Journal Title')
        ->set('newJournalEIssn', '1757-790X')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalEIssn' => 'This e-ISSN is already registered to an existing journal.']);

    // Duplicate p-ISSN
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', 'Brand New Journal Title')
        ->set('newJournalPIssn', '1234-5679')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalPIssn' => 'This p-ISSN is already registered to an existing journal.']);

    // Identical e-ISSN and p-ISSN
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', 'Brand New Journal Title')
        ->set('newJournalEIssn', '2434-561X')
        ->set('newJournalPIssn', '2434-561X')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalPIssn' => 'P-ISSN must be different from E-ISSN.']);

    $existing->delete();
});

test('rejects duplicate journal title with different case (case-insensitive)', function () {
    $existing = ReviewerJournal::create([
        'journal_title' => 'International Journal of Research Trends',
        'e_issn' => '2049-3630',
        'status' => 1,
    ]);

    // Attempting to add same title with all lowercase
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', 'international journal of research trends')
        ->set('newJournalEIssn', '0974-3154')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalTitle' => 'This journal name already exists in the database.']);

    // Attempting to add same title with ALL UPPERCASE
    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', 'INTERNATIONAL JOURNAL OF RESEARCH TRENDS')
        ->set('newJournalEIssn', '0974-3154')
        ->call('saveNewJournal')
        ->assertHasErrors(['newJournalTitle' => 'This journal name already exists in the database.']);

    $existing->delete();
});

test('assigns auto-incrementing serial_number and generates slug with serial_number appended', function () {
    $title = 'Specialized Technology Journal '.uniqid();

    Volt::test('article.deposit')
        ->set('option', 'published')
        ->call('openAddJournalModal', $title)
        ->set('newJournalEIssn', '2049-3630')
        ->call('saveNewJournal')
        ->assertSet('showAddJournalModal', false);

    $created = ReviewerJournal::where('journal_title', $title)->first();
    expect($created)->not->toBeNull()
        ->and($created->serial_number)->toBeGreaterThan(0)
        ->and($created->slug)->toEndWith('-'.$created->serial_number);

    $created->delete();
});
