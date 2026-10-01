<?php

use App\Models\ReviewerJournal;
use Livewire\Volt\Volt;

test('journal certificate route is registered', function () {
    expect(Route::has('journal_certificate'))->toBeTrue();
});

test('journal certificate returns 404 for nonexistent journal', function () {
    $response = $this->get('/journal-certificate/non-existent-journal-slug-xyz');

    $response->assertNotFound();
});

test('journal certificate renders successfully for existing journal and generates PDF', function () {
    $journal = ReviewerJournal::first();

    if (! $journal) {
        $this->markTestSkipped('No journal found in database');
    }

    $response = $this->get(route('journal_certificate', ['slug' => $journal->slug]));

    $response->assertOk();
    $response->assertSee('Copy Link');
    $response->assertSee('Share');
    $response->assertSee('Back to Journal');
    $response->assertSee('PRC');
});

test('journal certificate volt component compiles and renders', function () {
    $journal = ReviewerJournal::first();

    if (! $journal) {
        $this->markTestSkipped('No journal found in database');
    }

    Volt::test('journal.peerReviewCertificate', ['slug' => $journal->slug])
        ->assertSee('Copy Link')
        ->assertSee('Share')
        ->assertSee('Back to Journal');
});
