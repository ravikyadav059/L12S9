<?php

use App\Http\Controllers\LinkPreviewController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Link Preview API Route
Route::post('api/link-preview', [LinkPreviewController::class, 'preview'])->name('api.link-preview');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // From below my code new route get started for scholar normal user main
});

// For open pages :- for scholar normal user
Volt::route('questions', 'question.index')->name('questions.index');
Volt::route('question/{slug}', 'question.show')->name('questions.show');
Volt::route('articles', 'article.index')->name('articles.index');
Volt::route('journals', 'journal.index')->name('journals.index');
Volt::route('institutions', 'institution.index')->name('institutions.index');
Volt::route('institution/{slug}', 'institution.show')->name('institution.show');
Volt::route('journal/{slug}', 'journal.show')->name('journal.show');
Volt::route('journal-certificate/{slug}', 'journal.peerReviewCertificate')->name('journal_certificate');
Volt::route('deposit-article', 'article.deposit')->name('articles.deposit');
Volt::route('profile/{slug}', 'userprofile')->name('userscholar');
Volt::route('publication-detail/{slug}', 'article.detail')->name('publication.detail');

// Admin Panel Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/', 'admin.dashboard')->name('dashboard');
    Volt::route('publications', 'admin.publications.index')->name('publications.index');
    Volt::route('publications/{publication}/edit', 'admin.publications.edit')->name('publications.edit');
    Volt::route('users', 'admin.users.index')->name('users.index');
    Volt::route('questions', 'admin.questions.index')->name('questions.index');
    Volt::route('questions/{question}/edit', 'admin.questions.edit')->name('questions.edit');

    // For below the database fixing
    Volt::route('database-fixing', 'admin.database-fixing.index')->name('database-fixing.index');
    Volt::route('database-fixing/publication/status', 'admin.database-fixing.publication.status')->name('database-fixing.publication.status');
    Volt::route('database-fixing/publication/registered-co-author', 'admin.database-fixing.publication.registered-co-author')->name('database-fixing.publication.registered-co-author');
    Volt::route('database-fixing/publication/unregistered-co-author', 'admin.database-fixing.publication.unregistered-co-author')->name('database-fixing.publication.unregistered-co-author');
    Volt::route('database-fixing/publication/orphan-journal', 'admin.database-fixing.publication.orphan-journal')->name('database-fixing.publication.orphan-journal');
    Volt::route('database-fixing/role-in-research-journals/role', 'admin.database-fixing.role-in-research-journals.role')->name('database-fixing.role-in-research-journals.role');

    // RequestReviewPaper Database Fixers
    Volt::route('database-fixing/request-review-paper/registered-co-author', 'admin.database-fixing.request-review-paper.registered-co-author')->name('database-fixing.request-review-paper.registered-co-author');
    Volt::route('database-fixing/request-review-paper/unregistered-co-author', 'admin.database-fixing.request-review-paper.unregistered-co-author')->name('database-fixing.request-review-paper.unregistered-co-author');
    Volt::route('database-fixing/request-review-paper/authors-name-only', 'admin.database-fixing.request-review-paper.authors-name-only')->name('database-fixing.request-review-paper.authors-name-only');
    Volt::route('database-fixing/request-review-paper/paper-keywords', 'admin.database-fixing.request-review-paper.paper-keywords')->name('database-fixing.request-review-paper.paper-keywords');
    Volt::route('database-fixing/request-review-paper/review-parameters', 'admin.database-fixing.request-review-paper.review-parameters')->name('database-fixing.request-review-paper.review-parameters');
    Volt::route('database-fixing/request-review-paper/scholar-profiles', 'admin.database-fixing.request-review-paper.scholar-profiles')->name('database-fixing.request-review-paper.scholar-profiles');

    // ReviewerProfile Orphan Cleaners
    Volt::route('database-fixing/reviewer-profile/orphan-cleaner/publication', 'admin.database-fixing.reviewer-profile.orphan-cleaner.publication')->name('database-fixing.reviewer-profile.orphan-cleaner.publication');
    Volt::route('database-fixing/reviewer-profile/orphan-cleaner/experience', 'admin.database-fixing.reviewer-profile.orphan-cleaner.experience')->name('database-fixing.reviewer-profile.orphan-cleaner.experience');
    Volt::route('database-fixing/reviewer-profile/orphan-cleaner/seminar', 'admin.database-fixing.reviewer-profile.orphan-cleaner.seminar')->name('database-fixing.reviewer-profile.orphan-cleaner.seminar');
    Volt::route('database-fixing/reviewer-profile/orphan-cleaner/role-in-research-journal', 'admin.database-fixing.reviewer-profile.orphan-cleaner.role-in-research-journal')->name('database-fixing.reviewer-profile.orphan-cleaner.role-in-research-journal');
    Volt::route('database-fixing/reviewer-profile-publications', 'admin.database-fixing.reviewer-profile.orphan-cleaner.publication')->name('database-fixing.reviewer-profile.publication');
    Volt::route('database-fixing/reviewer-profile', 'admin.database-fixing.reviewer-profile.index')->name('database-fixing.reviewer-profile.index');
    Volt::route('database-fixing/reviewer-profile/{field}', 'admin.database-fixing.reviewer-profile.index')->name('database-fixing.reviewer-profile.field');

    // Experience Database Fixers
    Volt::route('database-fixing/experience', 'admin.database-fixing.experience.index')->name('database-fixing.experience.index');
    Volt::route('database-fixing/experience/{field}', 'admin.database-fixing.experience.index')->name('database-fixing.experience.field');

    // Seminar Database Fixers
    Volt::route('database-fixing/seminar', 'admin.database-fixing.seminar.index')->name('database-fixing.seminar.index');

    // Question Database Fixers
    Volt::route('database-fixing/question', 'admin.database-fixing.question.counts')->name('database-fixing.question.index');
    Volt::route('database-fixing/question/counts', 'admin.database-fixing.question.counts')->name('database-fixing.question.counts');
    Volt::route('database-fixing/question/slug', 'admin.database-fixing.question.slug')->name('database-fixing.question.slug');
});

require __DIR__.'/auth.php';
