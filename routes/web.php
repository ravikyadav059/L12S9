<?php

use App\Http\Controllers\LinkPreviewController;
use App\Models\DegreeType;
use App\Models\Designation;
use App\Models\FieldOfStudy;
use App\Models\Grade;
use App\Models\Organization;
use App\Models\Research;
use App\Models\ReviewerJournal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Public / Open APIs (Throttled)
Route::prefix('api')->middleware(['throttle:60,1'])->group(function () {
    Route::post('link-preview', [LinkPreviewController::class, 'preview'])->name('api.link-preview');

    Route::get('research-tags', function (Request $request) {
        $query = trim((string) $request->input('q', ''));
        $exclude = array_values(array_filter(array_map('trim', (array) $request->input('exclude', []))));
        $results = Research::searchActive($query, $exclude, 20);

        return response()->json($results);
    })->name('api.research-tags');
});

// Search Journals API Route
Route::middleware(['throttle:120,1'])->get('search-journals', function (Request $request) {
    $query = (string) $request->input('search', $request->input('q', ''));
    $limit = min(50, max(1, (int) $request->input('limit', 30)));
    $journals = ReviewerJournal::searchTitles($query, $limit);

    return response()->json(['journals' => $journals]);
})->name('search-journals');

// Authenticated Internal APIs (Dropdowns & Autocomplete)
Route::middleware(['auth', 'throttle:120,1'])->prefix('api')->name('api.')->group(function () {
    Route::get('designations', function (Request $request) {
        $query = trim((string) $request->input('q', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));
        $page = max(1, (int) $request->input('page', 1));

        return response()->json(Designation::searchActive($query, $limit, $page));
    })->name('designations');

    Route::get('organizations', function (Request $request) {
        $query = trim((string) $request->input('q', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));
        $page = max(1, (int) $request->input('page', 1));

        return response()->json(Organization::searchActive($query, $limit, $page));
    })->name('organizations');

    Route::get('degree-types', function (Request $request) {
        $query = trim((string) $request->input('q', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));
        $page = max(1, (int) $request->input('page', 1));

        return response()->json(DegreeType::searchActive($query, $limit, $page));
    })->name('degree-types');

    Route::get('fields-of-study', function (Request $request) {
        $query = trim((string) $request->input('q', ''));
        $limit = min(50, max(1, (int) $request->input('limit', 20)));
        $page = max(1, (int) $request->input('page', 1));

        return response()->json(FieldOfStudy::searchActive($query, $limit, $page));
    })->name('fields-of-study');

    Route::get('grades', function (Request $request) {
        $query = trim((string) $request->input('q', ''));

        return response()->json(Grade::searchActive($query));
    })->name('grades');
});

// Impersonation - Return to Admin
Route::get('impersonate/leave', function () {
    $adminId = session()->pull('impersonated_by');

    if ($adminId) {
        $adminUser = User::find($adminId);
        if ($adminUser && $adminUser->isAdmin()) {
            Auth::login($adminUser);

            return redirect()->route('admin.users.index');
        }
    }

    return redirect()->route('home');
})->name('impersonate.leave');

// Admin Panel Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/', 'admin.dashboard')->name('dashboard');
    Volt::route('publications', 'admin.publications.index')->name('publications.index');
    Volt::route('publications/{publication}/edit', 'admin.publications.edit')->name('publications.edit');
    Volt::route('users', 'admin.users.index')->name('users.index');
    Volt::route('edit_users/{user:unique_id}', 'admin.users.edit')->name('users.edit');
    Volt::route('questions', 'admin.questions.index')->name('questions.index');
    Volt::route('questions/{question}/edit', 'admin.questions.edit')->name('questions.edit');
    Volt::route('institutes', 'admin.institutes.index')->name('institutes.index');
    Volt::route('institutes/create', 'admin.institutes.create')->name('institutes.create');
    Volt::route('institutes/{organization}/edit', 'admin.institutes.edit')->name('institutes.edit');

    Volt::route('journals', 'admin.journals.index')->name('journals.index');
    Volt::route('journals/create', 'admin.journals.create')->name('journals.create');
    Volt::route('journals/{journal}/edit', 'admin.journals.edit')->name('journals.edit');

    Volt::route('indexing-agencies', 'admin.indexing-agencies.index')->name('indexing-agencies.index');
    Volt::route('indexing-agencies/create', 'admin.indexing-agencies.create')->name('indexing-agencies.create');
    Volt::route('indexing-agencies/{agency}/edit', 'admin.indexing-agencies.edit')->name('indexing-agencies.edit');

    Volt::route('journal-role-requests', 'admin.journal-role-requests.index')->name('journal-role-requests.index');

    Volt::route('journal-roles', 'admin.journal-roles.index')->name('journal-roles.index');
    Volt::route('journal-roles/create', 'admin.journal-roles.create')->name('journal-roles.create');
    Volt::route('journal-roles/{journalRole}/edit', 'admin.journal-roles.edit')->name('journal-roles.edit');

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
    Volt::route('database-fixing/experience/status', 'admin.database-fixing.experience.status')->name('database-fixing.experience.status');
    Volt::route('database-fixing/experience', 'admin.database-fixing.experience.index')->name('database-fixing.experience.index');
    Volt::route('database-fixing/experience/{field}', 'admin.database-fixing.experience.index')->name('database-fixing.experience.field');

    // Education Database Fixers
    Volt::route('database-fixing/education/degree-type', 'admin.database-fixing.education.degree-type')->name('database-fixing.education.degree-type');
    Volt::route('database-fixing/education/field-of-study', 'admin.database-fixing.education.field-of-study')->name('database-fixing.education.field-of-study');
    Volt::route('database-fixing/education/grade', 'admin.database-fixing.education.grade')->name('database-fixing.education.grade');
    Volt::route('database-fixing/education/institution', 'admin.database-fixing.education.institution')->name('database-fixing.education.institution');

    // Seminar Database Fixers
    Volt::route('database-fixing/seminar', 'admin.database-fixing.seminar.index')->name('database-fixing.seminar.index');

    // Question Database Fixers
    Volt::route('database-fixing/question', 'admin.database-fixing.question.counts')->name('database-fixing.question.index');
    Volt::route('database-fixing/question/counts', 'admin.database-fixing.question.counts')->name('database-fixing.question.counts');
    Volt::route('database-fixing/question/slug', 'admin.database-fixing.question.slug')->name('database-fixing.question.slug');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // From below my code new route get started for scholar normal user main
    Route::view('reviewer', 'Reviewer.enroll_reviewer')->name('reviewer.enroll');
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
