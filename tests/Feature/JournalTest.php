<?php

use App\Models\ReviewerJournal;
use Livewire\Volt\Volt;

test('journals route is registered', function () {
    $this->assertTrue(Route::has('journals.index'));
});

test('journals index page renders successfully', function () {
    $response = $this->get(route('journals.index'));
    $response->assertOk();
    $response->assertSee('Research Journals');
    $response->assertSee('All Disciplines');
    $response->assertSee('Indexers');
    $response->assertSee('Open Access Journal');
    $response->assertSee('No APC (Free of charge)');
    $response->assertSee('Total Journals:');
});

test('volt journal index component compiles and renders correctly', function () {
    Volt::test('journal.index')
        ->assertSee('Research Journals')
        ->assertSee('All Disciplines')
        ->assertSee('Indexers')
        ->assertSee('Search discipline...')
        ->assertSee('Open Access Journal')
        ->assertSee('No APC (Free of charge)')
        ->assertSet('sortBy', 'title_asc')
        ->assertSet('isOpenAccess', true)
        ->assertSet('isNoApc', true);
});

test('toggle open access and no apc filters works', function () {
    Volt::test('journal.index')
        ->call('toggleOpenAccess')
        ->assertSet('isOpenAccess', false)
        ->call('toggleNoApc')
        ->assertSet('isNoApc', false)
        ->call('resetFilters')
        ->assertSet('isOpenAccess', true)
        ->assertSet('isNoApc', true);
});

test('discipline and indexer selections work', function () {
    Volt::test('journal.index')
        ->set('selectedDisciplines', ['Engineering'])
        ->assertSet('selectedDisciplines', ['Engineering'])
        ->call('clearDisciplines')
        ->assertSet('selectedDisciplines', [])
        ->set('selectedIndexers', ['Scopus_checkbox'])
        ->assertSet('selectedIndexers', ['Scopus_checkbox'])
        ->call('clearIndexers')
        ->assertSet('selectedIndexers', []);
});

test('journal detail route is registered', function () {
    $this->assertTrue(Route::has('journal.show'));
});

test('journal detail page renders correctly for existing journal', function () {
    $journal = ReviewerJournal::whereIn('status', [1, '1', true])->whereNotNull('slug')->first();
    if (! $journal) {
        $journal = ReviewerJournal::create([
            'journal_title' => 'Test Medical Journal',
            'slug' => 'test-medical-journal',
            'status' => 1,
            'journal_description' => 'Test description for journal.',
        ]);
    }

    $response = $this->get(route('journal.show', ['slug' => $journal->slug]));
    $response->assertOk();
    $response->assertSee('Back to Journals');
    $response->assertSee('Journal Descriptions');
});
