<?php

use App\Livewire\Concerns\HandlesAddAuthorModal;
use App\Livewire\Concerns\HandlesAddJournalModal;
use App\Livewire\Concerns\HandlesDuplicateChecks;
use App\Models\Article;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\Publication;
use App\Models\ReviewerJournal;
use App\Models\ReviewerProfile;
use App\Models\User;
use App\Rules\ValidIssn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use MongoDB\BSON\Regex;

new #[Layout('components.layouts.app')] class extends Component {
    use HandlesAddAuthorModal;
    use HandlesAddJournalModal;
    use HandlesDuplicateChecks;
    use WithFileUploads;

    // Step state
    public int $currentStep = 1;

    // Step 1: Basic Paper Info
    public string $title = '';
    public string $abstract = '';
    public array $keywordsList = [];
    public string $newKeyword = '';
    public string $option = 'preprint'; // default Pre-Print

    // Step 1: Published Fields (Shown when $option === 'published')
    public string $articleType = '';
    public ?string $doi = '';
    public string $journalName = '';
    public ?string $selectedJournalId = null;
    public array $journalSearchResults = [];
    public bool $showJournalDropdown = false;
    public bool $isJournalVerified = false;
    public string $issnType = 'both'; // 'both', 'e_issn', 'p_issn'
    public string $eIssn = '';
    public string $pIssn = '';
    public string $issn = '';
    public string $volume = '';
    public string $issue = '';
    public string $citation = '';
    public string $pageNo = '';
    public string $pubMonthYear = '';
    public string $pdfUrl = '';
    public string $landingPageUrl = '';

    // Step 2: File & Authors
    public mixed $file = null;
    public array $authors = [
        ['name' => '', 'email' => '', 'affiliation' => '']
    ];
    public array $authorSearchResults = [[]];
    public array $authorSearchNoResults = [false];
    public array $showAuthorDropdown = [false];
    public array $authorSearchSequence = [0];
    public array $isAuthorSelected = [false];
    public bool $agreeCopyright = false;

    public function with(): array
    {
        return [
            'articleTypes' => Article::select(['_id', 'name'])->orderBy('name', 'asc')->get(),
        ];
    }

    // Keyword management
    public function addKeyword(): void
    {
        $trimmed = trim($this->newKeyword);
        if ($trimmed !== '' && !in_array($trimmed, $this->keywordsList)) {
            $this->keywordsList[] = $trimmed;
            $this->newKeyword = '';
            $this->resetValidation('keywordsList');
        }
    }

    public function removeKeyword(int $index): void
    {
        if (isset($this->keywordsList[$index])) {
            array_splice($this->keywordsList, $index, 1);
        }
    }

    // Author management
    public function addAuthor(): void
    {
        $this->authors[] = ['name' => '', 'email' => '', 'affiliation' => ''];
        $this->authorSearchResults[] = [];
        $this->authorSearchNoResults[] = false;
        $this->showAuthorDropdown[] = false;
        $this->authorSearchSequence[] = 0;
        $this->isAuthorSelected[] = false;
    }

    public function removeAuthor(int $index): void
    {
        if (count($this->authors) > 1 && isset($this->authors[$index])) {
            array_splice($this->authors, $index, 1);
            unset(
                $this->authorSearchResults[$index],
                $this->authorSearchNoResults[$index],
                $this->showAuthorDropdown[$index],
                $this->authorSearchSequence[$index],
                $this->isAuthorSelected[$index]
            );
            $this->authorSearchResults = array_values($this->authorSearchResults);
            $this->authorSearchNoResults = array_values($this->authorSearchNoResults);
            $this->showAuthorDropdown = array_values($this->showAuthorDropdown);
            $this->authorSearchSequence = array_values($this->authorSearchSequence);
            $this->isAuthorSelected = array_values($this->isAuthorSelected);
        }
    }

    public function updatedAuthors(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.name')) {
            $parts = explode('.', $key);
            $index = (int) $parts[0];
            $this->searchAuthor($index, (string) $value);
        } elseif (str_ends_with($key, '.email')) {
            $parts = explode('.', $key);
            $index = (int) $parts[0];
            $this->lookupAuthorByEmail($index, (string) $value);
        }
    }

    public function resolveUserAffiliation(User|string $user): string
    {
        $userObj = is_string($user) ? User::find($user) : $user;
        if (! $userObj) {
            return '';
        }

        $uId = (string) ($userObj->_id ?? $userObj->id ?? '');

        // 1. Try resolving through ReviewerProfile -> Experience -> Organization
        $profile = ReviewerProfile::where('user_id', $uId)->first();
        if ($profile && ! empty($profile->experience)) {
            $exp = $profile->experience;
            $firstExpId = null;

            if (is_string($exp)) {
                $decoded = json_decode($exp, true);
                if (is_array($decoded) && ! empty($decoded)) {
                    $firstExpId = is_array($decoded[0]) ? ($decoded[0]['_id'] ?? $decoded[0]['organization_id'] ?? null) : $decoded[0];
                }
            } elseif (is_array($exp) && ! empty($exp)) {
                $firstExpId = is_array($exp[0]) ? ($exp[0]['_id'] ?? $exp[0]['organization_id'] ?? null) : $exp[0];
            }

            if ($firstExpId) {
                $experience = Experience::find((string) $firstExpId);
                if ($experience && ! empty($experience->organization_id)) {
                    $org = Organization::find((string) $experience->organization_id);
                    if ($org && ! empty($org->organization_name)) {
                        return (string) $org->organization_name;
                    }
                }
            }
        }

        // 2. Direct user affiliation attribute fallback
        if (! empty($userObj->affiliation)) {
            if (is_object($userObj->affiliation) && isset($userObj->affiliation->name)) {
                return (string) $userObj->affiliation->name;
            } elseif (is_string($userObj->affiliation)) {
                return (string) $userObj->affiliation;
            }
        }

        return '';
    }

    public function lookupAuthorByEmail(int $index, string $email): void
    {
        $email = strtolower(trim($email));

        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $escaped = preg_quote($email, '/');
        $user = User::where(function ($q) {
            $q->where('type', 0)
                ->orWhere('type', '0')
                ->orWhereNull('type');
        })
            ->where('status', '!=', 3)
            ->where('status', '!=', '3')
            ->where('email', 'regex', new Regex('^'.$escaped.'$', 'i'))
            ->first();

        if ($user) {
            $authorName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            if ($authorName === '') {
                $authorName = trim((string) (($user->first_name ?? '') ?: ($user->last_name ?? '')));
            }
            if ($authorName === '' && ! empty($user->fullname)) {
                $authorName = $user->fullname;
            }

            $affiliationName = $this->resolveUserAffiliation($user);

            if ($authorName !== '') {
                $this->authors[$index]['name'] = $authorName;
            }

            $this->authors[$index]['affiliation'] = $affiliationName;

            $this->showAuthorDropdown[$index] = false;
            $this->authorSearchResults[$index] = [];
            $this->authorSearchNoResults[$index] = false;

            $this->resetValidation("authors.{$index}.name");
            $this->resetValidation("authors.{$index}.email");
            $this->resetValidation("authors.{$index}.affiliation");
        }
    }

    public function searchAuthor(int $index, ?string $name = null): void
    {
        $this->isAuthorSelected[$index] = false;

        $query = trim($name ?? ($this->authors[$index]['name'] ?? ''));

        // Initialize sequence tracking to discard older async responses
        $this->authorSearchSequence[$index] = ($this->authorSearchSequence[$index] ?? 0) + 1;
        $currentSequence = $this->authorSearchSequence[$index];

        if (mb_strlen($query) < 3) {
            $this->authorSearchResults[$index] = [];
            $this->authorSearchNoResults[$index] = false;
            $this->showAuthorDropdown[$index] = false;

            return;
        }

        $queryParts = array_values(array_filter(explode(' ', $query)));

        $fetchUsers = function (array $parts) {
            $usersQuery = User::where(function ($q) {
                $q->where('type', 0)
                    ->orWhere('type', '0')
                    ->orWhereNull('type');
            })
                ->where('status', '!=', 3)
                ->where('status', '!=', '3');

            $usersQuery->where(function ($q) use ($parts) {
                foreach ($parts as $part) {
                    $escaped = preg_quote($part, '/');
                    $partRegex = new Regex($escaped, 'i');
                    $q->where(function ($sub) use ($partRegex) {
                        $sub->where('first_name', 'regex', $partRegex)
                            ->orWhere('last_name', 'regex', $partRegex)
                            ->orWhere('email', 'regex', $partRegex);
                    });
                }
            });

            return $usersQuery->limit(25)->get();
        };

        $users = $fetchUsers($queryParts);

        // If no results on exact words for multi-word queries, try prefix fallback (e.g. darshna -> darsh)
        if ($users->isEmpty() && count($queryParts) > 1) {
            $prefixParts = array_map(function ($part) {
                return mb_strlen($part) > 4 ? mb_substr($part, 0, 4) : $part;
            }, $queryParts);
            $users = $fetchUsers($prefixParts);
        }

        // Prevent race condition: if sequence changed, discard stale results
        if (($this->authorSearchSequence[$index] ?? 0) !== $currentSequence) {
            return;
        }

        if ($users->isEmpty()) {
            $this->authorSearchResults[$index] = [];
            $this->authorSearchNoResults[$index] = true;
            $this->showAuthorDropdown[$index] = true;

            return;
        }

        // Sort users: exact match (100) > starts with (80) > exact first/last (60) > partial (40)
        $sortedUsers = $users->sortByDesc(function ($user) use ($query, $queryParts) {
            $f = strtolower(trim($user->first_name ?? ''));
            $l = strtolower(trim($user->last_name ?? ''));
            $fullName1 = trim("{$f} {$l}");
            $fullName2 = trim("{$l} {$f}");
            $searchQuery = strtolower(trim($query));

            if ($fullName1 === $searchQuery || $fullName2 === $searchQuery) {
                return 100;
            }

            if (str_starts_with($fullName1, $searchQuery) || str_starts_with($fullName2, $searchQuery)) {
                return 80;
            }

            if ($f === $searchQuery || $l === $searchQuery) {
                return 60;
            }

            if (str_starts_with($f, $searchQuery) || str_starts_with($l, $searchQuery)) {
                return 40;
            }

            $hasAll = true;
            foreach ($queryParts as $part) {
                $p = strtolower($part);
                if (!str_contains($fullName1, $p) && !str_contains($fullName2, $p)) {
                    $hasAll = false;
                    break;
                }
            }

            return $hasAll ? 20 : 1;
        })->values()->take(10);

        // Batch resolve affiliations
        $userIds = $sortedUsers->pluck('_id')->map(fn ($id) => (string) $id)->toArray();
        $profiles = ReviewerProfile::whereIn('user_id', $userIds)->get()->keyBy(fn ($p) => (string) $p->user_id);

        $expIds = [];
        $profileExpMap = [];
        foreach ($profiles as $uId => $profile) {
            $exp = $profile->experience ?? null;
            if (is_string($exp)) {
                $decoded = json_decode($exp, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $firstExp = is_array($decoded[0]) ? ($decoded[0]['_id'] ?? $decoded[0]['organization_id'] ?? null) : $decoded[0];
                    if ($firstExp) {
                        $expIds[] = (string) $firstExp;
                        $profileExpMap[$uId] = (string) $firstExp;
                    }
                }
            } elseif (is_array($exp) && !empty($exp)) {
                $firstExp = is_array($exp[0]) ? ($exp[0]['_id'] ?? $exp[0]['organization_id'] ?? null) : $exp[0];
                if ($firstExp) {
                    $expIds[] = (string) $firstExp;
                    $profileExpMap[$uId] = (string) $firstExp;
                }
            }
        }

        $experiences = !empty($expIds) ? Experience::whereIn('_id', array_values(array_unique($expIds)))->get()->keyBy(fn ($e) => (string) $e->_id) : collect();

        $orgIds = [];
        foreach ($experiences as $e) {
            if (!empty($e->organization_id)) {
                $orgIds[] = (string) $e->organization_id;
            }
        }
        $organizations = !empty($orgIds) ? Organization::whereIn('_id', array_values(array_unique($orgIds)))->get()->keyBy(fn ($o) => (string) $o->_id) : collect();

        // Check race condition again before committing results to state
        if (($this->authorSearchSequence[$index] ?? 0) !== $currentSequence) {
            return;
        }

        $results = [];
        foreach ($sortedUsers as $user) {
            $uId = (string) $user->_id;
            $affiliationName = null;

            if (isset($profileExpMap[$uId]) && isset($experiences[$profileExpMap[$uId]])) {
                $exp = $experiences[$profileExpMap[$uId]];
                if (!empty($exp->organization_id) && isset($organizations[(string) $exp->organization_id])) {
                    $affiliationName = $organizations[(string) $exp->organization_id]->organization_name ?? null;
                }
            }

            if (!$affiliationName && !empty($user->affiliation)) {
                $affiliationName = $user->affiliation;
            }

            $authorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if ($authorName === '') {
                $authorName = trim((string) (($user->first_name ?? '') ?: ($user->last_name ?? '')));
            }

            $photo = !empty($user->photo) ? $user->photo : 'assets/images/vector.png';

            $results[] = [
                'id' => $uId,
                'name' => $authorName,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email ?? '',
                'photo' => $photo,
                'slug' => $user->slug ?? '',
                'affiliation' => $affiliationName ?? '',
                'status' => $user->status ?? 1,
            ];
        }

        $this->authorSearchResults[$index] = $results;
        $this->authorSearchNoResults[$index] = false;
        $this->showAuthorDropdown[$index] = !empty($results);
    }

    public function selectAuthor(int $index, string $name, string $email, ?string $affiliation = null): void
    {
        if (isset($this->authors[$index])) {
            $this->authors[$index]['name'] = $name;
            $this->authors[$index]['email'] = $email;
            $this->authors[$index]['affiliation'] = $affiliation ?? '';

            $this->resetValidation("authors.{$index}.name");
            $this->resetValidation("authors.{$index}.email");
        }

        $this->isAuthorSelected[$index] = true;
        $this->authorSearchResults[$index] = [];
        $this->authorSearchNoResults[$index] = false;
        $this->showAuthorDropdown[$index] = false;
    }

    public function hideAuthorDropdown(int $index): void
    {
        $this->showAuthorDropdown[$index] = false;
    }

    public function updatedFile(): void
    {
        $this->validate([
            'file' => 'nullable|file|mimes:pdf|max:15360',
        ], [
            'file.mimes' => 'Only PDF files are supported.',
            'file.max' => 'PDF file must not exceed 15MB.',
        ]);
    }

    public function removeFile(): void
    {
        $this->file = null;
        $this->resetValidation('file');
    }

    // Navigation & Validation
    public function goToNextStep(): void
    {
        $rules = [
            'title' => 'required|string|min:5|max:255',
            'abstract' => 'required|string|min:10',
            'keywordsList' => 'required|array|min:1',
            'option' => 'required|in:preprint,published',
        ];

        $messages = [
            'title.required' => 'Paper title is required.',
            'title.min' => 'Paper title must be at least 5 characters.',
            'abstract.required' => 'Abstract is required.',
            'abstract.min' => 'Abstract must be at least 10 characters.',
            'keywordsList.required' => 'At least one keyword is required.',
            'keywordsList.min' => 'Please add at least one keyword.',
        ];

        if ($this->option === 'published') {
            // Synchronize from legacy $issn if set and eIssn/pIssn empty
            if (empty($this->eIssn) && empty($this->pIssn) && !empty($this->issn)) {
                if ($this->issnType === 'p_issn') {
                    $this->pIssn = $this->issn;
                } else {
                    $this->eIssn = $this->issn;
                }
            }

            $publishedRules = [
                'articleType' => 'required|string',
                'journalName' => 'required|string|min:2',
                'volume' => 'required|numeric|min:1',
                'issue' => ['required', 'string', 'regex:/^[a-zA-Z0-9]+([-–][a-zA-Z0-9]+)*$/'],
                'pageNo' => 'required|string',
                'pubMonthYear' => 'required|string',
                'doi' => 'nullable|string',
                'issnType' => 'required|in:both,e_issn,p_issn',
                'citation' => 'nullable|numeric|min:0',
                'pdfUrl' => 'nullable|url',
                'landingPageUrl' => 'nullable|url',
            ];

            if ($this->issnType === 'both') {
                $publishedRules['eIssn'] = ['required', 'string', new ValidIssn];
                $publishedRules['pIssn'] = ['required', 'string', new ValidIssn];
            } elseif ($this->issnType === 'e_issn') {
                $publishedRules['eIssn'] = ['required', 'string', new ValidIssn];
            } elseif ($this->issnType === 'p_issn') {
                $publishedRules['pIssn'] = ['required', 'string', new ValidIssn];
            }

            $rules = array_merge($rules, $publishedRules);

            $messages = array_merge($messages, [
                'articleType.required' => 'Article type is required.',
                'journalName.required' => 'Journal name is required for published articles.',
                'volume.required' => 'Volume is required.',
                'volume.numeric' => 'Volume must be a numeric value.',
                'volume.min' => 'Volume must be at least 1.',
                'citation.numeric' => 'Citation must be a numeric value.',
                'citation.min' => 'Citation must be at least 0.',
                'issue.required' => 'Enter proper format (e.g., 1 or 1-2)',
                'issue.regex' => 'Enter proper format (e.g., 1 or 1-2)',
                'pageNo.required' => 'Enter proper page no',
                'pubMonthYear.required' => 'Publication Month-Year is required.',
                'eIssn.required' => 'E-ISSN is required.',
                'pIssn.required' => 'P-ISSN is required.',
                'issn.required' => 'ISSN is required.',
                'pdfUrl.url' => 'PDF URL must be a valid URL (e.g. https://...).',
                'landingPageUrl.url' => 'Landing Page URL must be a valid URL (e.g. https://...).',
            ]);
        }

        try {
            $this->validate($rules, $messages);

            // Synchronize primary $this->issn
            if (!empty($this->eIssn) || !empty($this->pIssn)) {
                $this->issn = $this->eIssn ?: $this->pIssn;
            } elseif (!empty($this->issn)) {
                if (empty($this->eIssn) && ($this->issnType === 'both' || $this->issnType === 'e_issn')) {
                    $this->eIssn = $this->issn;
                } elseif (empty($this->pIssn) && $this->issnType === 'p_issn') {
                    $this->pIssn = $this->issn;
                }
            }

            if ($this->checkPublicationTitleDuplicate()) {
                $this->showDuplicateModal = true;
                $this->addError('title', 'This Article has already been deposited.');
                $this->dispatch('scroll-to-first-error');
                return;
            }

            if ($this->option === 'published' && ! empty($this->pubMonthYear)) {
                try {
                    $pubDate = null;
                    if (str_contains($this->pubMonthYear, '-')) {
                        $parts = explode('-', $this->pubMonthYear);
                        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                            $pubDate = Carbon::createFromDate((int) $parts[1], (int) $parts[0], 1)->endOfMonth();
                        }
                    }
                    if (! $pubDate) {
                        $pubDate = Carbon::parse($this->pubMonthYear)->endOfMonth();
                    }

                    if ($pubDate && $pubDate->isFuture()) {
                        $this->addError('pubMonthYear', 'Publication date cannot be in the future.');
                        $this->dispatch('scroll-to-first-error');

                        return;
                    }
                } catch (\Throwable $e) {
                    // Fallback if parsing fails
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('scroll-to-first-error');
            throw $e;
        }

        $this->currentStep = 2;
    }

    public function goToPreviousStep(): void
    {
        $this->currentStep = 1;
    }

    public function updatedJournalName(): void
    {
        $query = trim($this->journalName);
        $this->isJournalVerified = false;
        $this->selectedJournalId = null;

        if (mb_strlen($query) < 3) {
            $this->journalSearchResults = [];
            $this->showJournalDropdown = false;

            return;
        }

        $escaped = preg_quote($query, '/');
        $regex = new Regex($escaped, 'i');

        $this->journalSearchResults = ReviewerJournal::where(function ($q) use ($regex) {
            $q->where('journal_title', 'regex', $regex)
                ->orWhere('journal_short_name', 'regex', $regex)
                ->orWhere('slug', 'regex', $regex)
                ->orWhere('e_issn', 'regex', $regex)
                ->orWhere('p_issn', 'regex', $regex);
        })
            ->where('status', '!=', 3)
            ->where('status', '!=', '3')
            ->select(['_id', 'journal_title', 'journal_short_name', 'e_issn', 'p_issn'])
            ->orderBy('journal_title', 'asc')
            ->limit(25)
            ->get()
            ->toArray();

        $this->showJournalDropdown = true;
    }

    public function checkJournalVerification(?string $title = null): bool
    {
        $name = trim($title ?? $this->journalName);
        if ($name === '') {
            $this->isJournalVerified = false;
            $this->selectedJournalId = null;
            return false;
        }

        // Check if selectedJournalId exists and is valid
        if ($this->selectedJournalId) {
            $journal = ReviewerJournal::where('_id', $this->selectedJournalId)
                ->where('status', '!=', 3)
                ->where('status', '!=', '3')
                ->first();

            if ($journal) {
                $this->isJournalVerified = true;
                return true;
            }
        }

        // Case-insensitive exact match on journal_title or slug or short_name
        $escaped = preg_quote($name, '/');
        $regex = new Regex('^' . $escaped . '$', 'i');

        $journal = ReviewerJournal::where(function ($q) use ($regex) {
            $q->where('journal_title', 'regex', $regex)
                ->orWhere('slug', 'regex', $regex)
                ->orWhere('journal_short_name', 'regex', $regex);
        })
            ->where('status', '!=', 3)
            ->where('status', '!=', '3')
            ->first();

        if ($journal) {
            $this->selectedJournalId = (string) $journal->_id;
            $this->isJournalVerified = true;

            if (empty($this->eIssn) && empty($this->pIssn)) {
                $eClean = trim($journal->e_issn ?? '');
                $pClean = trim($journal->p_issn ?? '');
                $this->eIssn = $eClean;
                $this->pIssn = $pClean;
                if ($eClean !== '' && $pClean !== '') {
                    $this->issnType = 'both';
                    $this->issn = $eClean;
                } elseif ($eClean !== '') {
                    $this->issnType = 'e_issn';
                    $this->issn = $eClean;
                } elseif ($pClean !== '') {
                    $this->issnType = 'p_issn';
                    $this->issn = $pClean;
                }
            }

            return true;
        }

        $this->isJournalVerified = false;
        return false;
    }

    public function selectJournal(string $id, string $title, ?string $eIssn = null, ?string $pIssn = null): void
    {
        $this->selectedJournalId = $id;
        $this->journalName = $title;
        $this->isJournalVerified = true;

        $eClean = trim($eIssn ?? '');
        $pClean = trim($pIssn ?? '');

        $this->eIssn = $eClean;
        $this->pIssn = $pClean;

        if ($eClean !== '' && $pClean !== '') {
            $this->issnType = 'both';
            $this->issn = $eClean;
        } elseif ($eClean !== '') {
            $this->issnType = 'e_issn';
            $this->issn = $eClean;
        } elseif ($pClean !== '') {
            $this->issnType = 'p_issn';
            $this->issn = $pClean;
        } else {
            $this->issn = '';
        }

        $this->journalSearchResults = [];
        $this->showJournalDropdown = false;
        $this->resetValidation('journalName');
        $this->resetValidation('issn');
        $this->resetValidation('eIssn');
        $this->resetValidation('pIssn');
    }

    public function hideJournalDropdown(): void
    {
        $this->showJournalDropdown = false;
    }

    public function clearPublicationDate(): void
    {
        $this->pubMonthYear = '';
    }

    public function retrieveDoi(): void
    {
        if ($this->doi) {
            session()->flash('doi_status', 'Publication details retrieved for DOI: ' . $this->doi);
            $this->checkJournalVerification();
        } else {
            session()->flash('doi_status_error', 'Please enter a valid DOI before retrieving.');
        }
    }

    // Submit Deposit
    public function deposit(): void
    {
        if ($this->currentStep === 1) {
            $this->goToNextStep();
            return;
        }

        try {
            $this->validate([
                'authors' => 'required|array|min:1',
                'authors.*.name' => 'required|string|max:255',
                'authors.*.email' => 'required|email|max:255',
                'authors.*.affiliation' => 'nullable|string|max:255',
                'agreeCopyright' => 'accepted',
                'file' => 'nullable|file|mimes:pdf|max:15360',
            ], [
                'authors.*.name.required' => 'Author name is required.',
                'authors.*.email.required' => 'Author email is required.',
                'authors.*.email.email' => 'Please enter a valid author email.',
                'agreeCopyright.accepted' => 'You must confirm the copyright and licensing agreement.',
                'file.mimes' => 'Only PDF files are supported.',
                'file.max' => 'PDF file must not exceed 15MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('scroll-to-first-error');
            throw $e;
        }

        // 1. Determine publication_type and user_id
        $isLoggedIn = Auth::check();
        $publicationType = $isLoggedIn ? 'auth_person' : 'guest';
        $userId = $isLoggedIn ? (string) Auth::id() : null;

        // 2. Resolve registered co-authors array & format authors
        $registeredCoAuthors = [];
        if ($isLoggedIn && $userId) {
            $registeredCoAuthors[] = $userId;
        }

        $formattedAuthors = [];
        foreach ($this->authors as $author) {
            $authorName = trim($author['name'] ?? '');
            $authorEmail = strtolower(trim($author['email'] ?? ''));
            $authorAffiliation = trim($author['affiliation'] ?? '');

            $formattedAuthors[] = [
                'name' => $authorName,
                'affiliation' => $authorAffiliation !== '' ? $authorAffiliation : null,
                'email' => $authorEmail,
            ];

            if (! empty($authorEmail)) {
                $escaped = preg_quote($authorEmail, '/');
                $matchedUser = User::where(function ($q) {
                    $q->where('type', 0)
                        ->orWhere('type', '0')
                        ->orWhereNull('type');
                })
                    ->where('status', '!=', 3)
                    ->where('status', '!=', '3')
                    ->where('email', 'regex', new Regex('^'.$escaped.'$', 'i'))
                    ->first();

                if ($matchedUser) {
                    $uIdStr = (string) ($matchedUser->_id ?? $matchedUser->id);
                    if (! in_array($uIdStr, $registeredCoAuthors, true)) {
                        $registeredCoAuthors[] = $uIdStr;
                    }
                }
            }
        }

        $registeredCoAuthors = array_values($registeredCoAuthors);
        $formattedAuthors = array_values($formattedAuthors);

        // 3. Generate unique serial_number & slug
        $maxSerial = (int) (Publication::max('serial_number') ?? 0);
        $serialNumber = $maxSerial > 0 ? $maxSerial + 1 : 1;

        while (Publication::where('serial_number', $serialNumber)->exists()) {
            $serialNumber++;
        }

        $slugBase = Str::slug($this->title);
        if ($slugBase === '') {
            $slugBase = 'article';
        }
        $slug = "{$slugBase}-{$serialNumber}";

        // 4. File handling (stored into publication directory)
        $imagePath = null;
        if ($this->file) {
            $origName = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext = $this->file->getClientOriginalExtension() ?: 'pdf';
            $fileName = "{$origName}_".time().".{$ext}";
            $imagePath = $this->file->storeAs('publication', $fileName, 'public');
        }

        // 5. Build Publication data conditionally based on option
        $isPreprint = ($this->option === 'preprint');

        if ($isPreprint) {
            $publicationData = [
                'publication_type' => $publicationType,
                'user_id' => $userId,
                'registered_co_author' => $registeredCoAuthors,
                'serial_number' => $serialNumber,
                'slug' => $slug,
                'title' => $this->title,
                'description' => $this->abstract,
                'publication_keywords' => $this->keywordsList,
                'article_type' => 'pre-print',
                'status' => 0,
                'image' => $imagePath,
                'authors' => $formattedAuthors,
                'agree_copyright' => $this->agreeCopyright,
            ];
        } else {
            $finalEIssn = trim($this->eIssn);
            $finalPIssn = trim($this->pIssn);
            $finalIssn = $finalEIssn ?: ($finalPIssn ?: trim($this->issn));

            $publishedDateValue = null;
            if (! empty($this->pubMonthYear)) {
                try {
                    if (str_contains($this->pubMonthYear, '-')) {
                        $parts = explode('-', $this->pubMonthYear);
                        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                            $publishedDateValue = Carbon::createFromDate((int) $parts[1], (int) $parts[0], 1)->format('F Y');
                        }
                    }
                    if (! $publishedDateValue) {
                        $publishedDateValue = Carbon::parse($this->pubMonthYear)->format('F Y');
                    }
                } catch (\Throwable $e) {
                    $publishedDateValue = trim($this->pubMonthYear);
                }
            } else {
                $publishedDateValue = date('F Y');
            }

            $publicationData = [
                'publication_type' => $publicationType,
                'user_id' => $userId,
                'registered_co_author' => $registeredCoAuthors,
                'serial_number' => $serialNumber,
                'slug' => $slug,
                'title' => $this->title,
                'description' => $this->abstract,
                'publication_keywords' => $this->keywordsList,
                'type' => $this->articleType ?: null,
                'article_type' => 'published',
                'published_date' => $publishedDateValue,
                'status' => 0,
                'journal_title' => $this->selectedJournalId ?: null,
                'journal_name' => $this->journalName ?: null,
                'issn' => $finalIssn ?: null,
                'e_issn' => $finalEIssn ?: null,
                'p_issn' => $finalPIssn ?: null,
                'volume' => $this->volume ?: null,
                'issue' => $this->issue ?: null,
                'citations' => $this->citation ?: null,
                'page_no' => $this->pageNo ?: null,
                'doi' => $this->doi ?: null,
                'published_paper_PDF' => $this->pdfUrl ?: null,
                'url' => $this->landingPageUrl ?: null,
                'image' => $imagePath,
                'authors' => $formattedAuthors,
                'agree_copyright' => $this->agreeCopyright,
            ];
        }

        Publication::create($publicationData);

        session()->flash('status', 'Thank you for submitting your article! Our team will review its contents.');

        $this->reset([
            'currentStep', 'title', 'abstract', 'keywordsList', 'newKeyword', 'option',
            'articleType', 'doi', 'journalName', 'selectedJournalId', 'journalSearchResults', 'showJournalDropdown',
            'isJournalVerified', 'showAddJournalModal', 'newJournalTitle', 'newJournalEIssn', 'newJournalPIssn',
            'issnType', 'eIssn', 'pIssn', 'issn', 'volume', 'issue', 'citation',
            'pageNo', 'pubMonthYear', 'pdfUrl', 'landingPageUrl', 'file', 'agreeCopyright',
            'authorSearchResults', 'authorSearchNoResults', 'showAuthorDropdown', 'authorSearchSequence', 'isAuthorSelected'
        ]);
        $this->issnType = 'both';
        $this->authors = [['name' => '', 'email' => '', 'affiliation' => '']];
        $this->option = 'preprint';
    }
}; ?>

<div 
    x-data="{
        scrollToFirstError() {
            this.$nextTick(() => {
                setTimeout(() => {
                    const list = Array.from(document.querySelectorAll('p.text-red-500:not([x-cloak]):not(.hidden), div.text-red-500:not([x-cloak]):not(.hidden), .border-red-500:not(span), [aria-invalid=\'true\'], [data-invalid], [data-error]'));
                    const errorEl = list.find(function(el) {
                        const txt = (el.textContent || '').trim();
                        if (txt === '*' || (el.tagName === 'SPAN' && txt === '*')) {
                            return false;
                        }
                        if (el.closest('label') && txt === '*') {
                            return false;
                        }
                        return Boolean(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
                    });

                    if (errorEl) {
                        const field = errorEl.closest('.space-y-1\\.5, .space-y-1, .space-y-2, [id^=\'field-\']') || errorEl;
                        const headerOffset = 100;
                        const elementPosition = field.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                        window.scrollTo({
                            top: Math.max(0, offsetPosition),
                            behavior: 'smooth'
                        });

                        const input = field.querySelector('input:not([type=\'hidden\']), select, textarea, .flatpickr-input');
                        if (input && typeof input.focus === 'function') {
                            setTimeout(function() {
                                input.focus({ preventScroll: true });
                            }, 350);
                        }
                    }
                }, 100);
            });
        }
    }"
    @scroll-to-first-error.window="scrollToFirstError()"
>
    <!-- ================= TOP HEADER HERO BANNER ================= -->
    <div class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200/90 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center gap-4">
            <!-- Left Icon Badge -->
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl sm:rounded-2xl bg-[#198BEA] text-white flex items-center justify-center shrink-0 shadow-md shadow-[#198BEA]/25">
                <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>

            <!-- Header Content -->
            <div class="space-y-0.5">
                <h1 class="text-xl sm:text-2xl font-bold text-zinc-900 dark:text-white tracking-tight">
                    Deposit Published or Preprint Articles
                </h1>
                <p class="text-xs sm:text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    Securely upload and preserve your research papers
                </p>
                <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed pt-0.5">
                    Share your published works and preprints with DOI assignment, version control, and permanent archiving. 
                    <span class="font-bold text-[#198BEA] dark:text-sky-400">Upload. Preserve. Share.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- ================= TWO COLUMN MAIN CONTENT ================= -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- LEFT CONTAINER: DEPOSIT FORM -->
            <div class="flex-1 min-w-0 w-full bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-theme-lg dark:shadow-none space-y-6">
                
                <!-- Section Header with Step indicator -->
                <div class="pb-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">
                        @if($currentStep === 1)
                            Step 1: Paper Information
                        @else
                            Step 2: Upload File and Author Details
                        @endif
                    </h2>
                    <span class="text-xs font-semibold px-2.5 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 rounded-full border border-zinc-200 dark:border-zinc-700">
                        Step {{ $currentStep }} of 2
                    </span>
                </div>

                <!-- Alert Feedback -->
                @if (session('status'))
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-emerald-800 dark:text-emerald-300 text-sm font-medium flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <!-- Form -->
                <form wire:submit="deposit" class="space-y-4">
                    
                    @if($currentStep === 1)
                        <!-- ================= STEP 1: PAPER INFORMATION ================= -->
                        
                        <!-- Paper Title -->
                        <div class="space-y-1.5" id="field-title">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                    Paper title <span class="text-red-500">*</span>
                                </label>
                                <span wire:loading wire:target="title, checkPublicationTitleDuplicate" class="text-xs text-[#198BEA] font-medium flex items-center gap-1.5">
                                    <svg class="animate-spin w-3.5 h-3.5 text-[#198BEA]" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    Checking title...
                                </span>
                            </div>
                            <flux:input 
                                wire:model="title" 
                                @blur="let val = ($event.target.value || '').trim(); if (val.length >= 5) { $wire.checkPublicationTitleDuplicate(val); }"
                                placeholder="Enter paper title (must be at least 5 characters)" 
                            />
                            @error('title') 
                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('title'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                            @enderror
                        </div>

                        <!-- Abstract -->
                        <div class="space-y-1.5" id="field-abstract">
                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                Abstract <span class="text-red-500">*</span>
                            </label>
                            <flux:textarea 
                                wire:model="abstract" 
                                placeholder="Enter abstract (must be at least 10 characters)" 
                                rows="4" 
                            />
                            @error('abstract') 
                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('abstract'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                            @enderror
                        </div>

                        <!-- Keywords Tag Container with Advanced Client-Side Alpine.js Features (Matching Image 3 & Legacy JS Specs) -->
                        <div 
                            id="field-keywordsList"
                            x-data="{ 
                                keywords: @entangle('keywordsList'), 
                                newKeyword: '',
                                errorMessage: '',

                                addTag(text = null) {
                                    let value = text !== null ? text : this.newKeyword;
                                    if (!value) return;

                                    let items = value.split(',').map(k => k.trim()).filter(k => k.length > 0);
                                    let hasDuplicate = false;

                                    items.forEach(item => {
                                        let exists = this.keywords.some(k => k.toLowerCase() === item.toLowerCase());
                                        if (!exists) {
                                            this.keywords.push(item);
                                        } else {
                                            hasDuplicate = true;
                                        }
                                    });

                                    if (text === null) {
                                        this.newKeyword = '';
                                    }

                                    if (hasDuplicate) {
                                        this.errorMessage = 'This keyword is already added!';
                                        setTimeout(() => { this.errorMessage = ''; }, 5000);
                                    } else {
                                        this.errorMessage = '';
                                    }
                                },

                                removeTag(index) {
                                    this.keywords.splice(index, 1);
                                },

                                handleKeyDown(e) {
                                    if (e.key === 'Backspace' && this.newKeyword === '' && this.keywords.length > 0) {
                                        this.removeTag(this.keywords.length - 1);
                                    } else if (e.key === 'Enter' || e.key === 'Tab' || e.key === ',') {
                                        e.preventDefault();
                                        this.addTag();
                                    }
                                },

                                handlePaste(e) {
                                    e.preventDefault();
                                    let pastedText = (e.clipboardData || window.clipboardData).getData('text');
                                    if (pastedText) {
                                        this.addTag(pastedText);
                                    }
                                }
                            }" 
                            class="space-y-1.5"
                        >
                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                Keywords <span class="text-red-500">*</span>
                            </label>
                            
                            <div 
                                @click="$refs.keywordInput.focus()"
                                class="w-full min-h-[42px] px-3 py-2 bg-white dark:bg-zinc-800 border @error('keywordsList') border-red-500 @else border-zinc-300 dark:border-zinc-700 @enderror rounded-xl flex flex-wrap items-center gap-2 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30 cursor-text transition-all"
                            >
                                <!-- Rendered Tag Pills -->
                                <template x-for="(kw, index) in keywords" :key="index">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-zinc-200/80 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200 text-xs font-semibold rounded-md animate-fadeIn">
                                        <span x-text="kw"></span>
                                        <button 
                                            type="button" 
                                            @click.stop="removeTag(index)" 
                                            class="text-red-500 hover:text-red-700 font-bold text-sm leading-none focus:outline-none ml-1 cursor-pointer"
                                        >
                                            &times;
                                        </button>
                                    </span>
                                </template>
                                
                                <!-- Keyword Input Field -->
                                <input 
                                    x-ref="keywordInput"
                                    type="text" 
                                    x-model="newKeyword" 
                                    @keydown="handleKeyDown($event)"
                                    @paste="handlePaste($event)"
                                    :placeholder="keywords.length === 0 ? 'Enter keywords (press Enter, Tab, comma, or paste)...' : 'Add keyword...'" 
                                    class="flex-1 min-w-[180px] bg-transparent border-0 outline-none focus:outline-none focus:ring-0 focus:border-transparent text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400 px-1 shadow-none"
                                />

                                <!-- Add Button -->
                                <button 
                                    type="button" 
                                    x-show="newKeyword.trim() !== ''" 
                                    @click.stop="addTag()" 
                                    class="px-3 py-1 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-md transition shadow-xs cursor-pointer"
                                >
                                    Add Tag
                                </button>
                            </div>

                            <!-- Duplicate Keyword Alert Warning in Red -->
                            <div x-show="errorMessage !== ''" x-cloak class="text-xs text-red-500 dark:text-red-400 font-semibold animate-fadeIn">
                                <span x-text="errorMessage"></span>
                            </div>

                            @error('keywordsList') 
                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('keywordsList'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                            @enderror
                        </div>

                        <!-- Option (Pre-Print / Published) with Client-Side Instant Alpine Toggling -->
                        <div x-data="{ option: @entangle('option') }" class="space-y-4" id="field-option">
                            <div class="space-y-2">
                                <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                    Option <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center gap-6">
                                    <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300 font-medium cursor-pointer">
                                        <input 
                                            type="radio" 
                                            wire:model="option" 
                                            x-model="option" 
                                            value="preprint" 
                                            class="w-4 h-4 text-[#198BEA] border-zinc-300 focus:ring-[#198BEA]" 
                                        />
                                        <span>Pre-Print</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300 font-medium cursor-pointer">
                                        <input 
                                            type="radio" 
                                            wire:model="option" 
                                            x-model="option" 
                                            value="published" 
                                            class="w-4 h-4 text-[#198BEA] border-zinc-300 focus:ring-[#198BEA]" 
                                        />
                                        <span>Published</span>
                                    </label>
                                </div>
                                @error('option') 
                                    <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('option'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                @enderror
                            </div>

                            <!-- Published More Input Fields (Instant Client-Side Display using x-show) -->
                            <div x-show="option === 'published'" x-cloak class="space-y-6 pt-4 border-t border-zinc-200/80 dark:border-zinc-800">
                                
                                <!-- Article Type & DOI Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Article Type -->
                                    <div class="space-y-1.5" id="field-articleType">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Article Type <span class="text-red-500">*</span>
                                        </label>
                                        <select 
                                            wire:model="articleType" 
                                            class="w-full h-10 px-3 py-2 bg-white dark:bg-zinc-800 border @error('articleType') border-red-500 focus:ring-red-200 @else border-zinc-300 dark:border-zinc-700 focus:ring-[#198BEA]/20 focus:border-[#198BEA] @enderror rounded-xl text-sm text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-2 transition"
                                        >
                                            <option value="">-- Select Article Type --</option>
                                            @foreach($articleTypes as $type)
                                                <option value="{{ $type->_id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('articleType') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('articleType'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror
                                    </div>

                                    <!-- DOI with RETRIEVE button (Matching Image 1) -->
                                    <div class="space-y-1.5" id="field-doi">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            DOI
                                        </label>
                                        <div class="flex items-center overflow-hidden rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 focus-within:border-brand focus-within:ring-2 focus-within:ring-brand/30 transition-all">
                                            <input 
                                                type="text" 
                                                wire:model="doi" 
                                                placeholder="Enter doi, e.g. 10.5120/ijca2016909228" 
                                                class="flex-1 h-10 px-3.5 bg-transparent border-0 outline-none focus:outline-none focus:ring-0 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-400"
                                            />
                                            <button 
                                                type="button" 
                                                wire:click="retrieveDoi"
                                                class="h-10 px-5 bg-[#13C20F] hover:bg-[#11ab0d] text-white text-xs font-extrabold tracking-wider uppercase transition shrink-0 flex items-center justify-center cursor-pointer"
                                            >
                                                RETRIEVE
                                            </button>
                                        </div>
                                        @if(session('doi_status'))
                                            <p x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ session('doi_status') }}</p>
                                        @endif
                                        @if(session('doi_status_error'))
                                            <p x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-semibold">{{ session('doi_status_error') }}</p>
                                        @endif
                                    </div>
                                </div>

                                <!-- Journal Name & ISSN Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Journal Name -->
                                    <div 
                                        class="relative space-y-1.5" 
                                        id="field-journalName" 
                                        x-data="{ 
                                            open: @entangle('showJournalDropdown'), 
                                            queryLength: $wire.journalName ? $wire.journalName.length : 0 
                                        }" 
                                        @click.outside="open = false"
                                    >
                                        <div class="flex items-center gap-2">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Journal name <span class="text-red-500">*</span>
                                            </label>
                                            @if($isJournalVerified)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 px-2 py-0.5 rounded-md shadow-xs" title="Journal verified in database">
                                                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Verified</span>
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <div class="relative">
                                            <flux:input 
                                                wire:model.live.debounce.500ms="journalName" 
                                                x-on:input="queryLength = $event.target.value.trim().length; if (queryLength >= 3) { open = true; }"
                                                x-on:focus="if (queryLength >= 3 && !$wire.isJournalVerified) { open = true; }"
                                                placeholder="Search journal name Or Add (Enter at least 3 characters...)" 
                                            />
                                            
                                            <!-- Loading Spinner -->
                                            <div wire:loading wire:target="journalName" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                                <svg class="animate-spin h-4 w-4 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- 1-2 char helper text -->
                                        <template x-if="queryLength > 0 && queryLength < 3">
                                            <p class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">Type at least 3 characters to search journals...</p>
                                        </template>

                                        @error('journalName') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('journalName'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror

                                        @if(session('journal_added_success'))
                                            <p x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ session('journal_added_success') }}</p>
                                        @endif

                                        <!-- Dropdown List -->
                                        @if($showJournalDropdown && !empty($journalSearchResults))
                                            <div 
                                                x-show="open" 
                                                x-transition:enter="transition ease-out duration-150"
                                                x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
                                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                x-transition:leave="transition ease-in duration-100"
                                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
                                                class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl divide-y divide-zinc-100 dark:divide-zinc-700/60"
                                            >
                                                <div class="px-3.5 py-2 bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-100 dark:border-zinc-700/60 flex items-center justify-between">
                                                    <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Existing Journals</span>
                                                    <span class="text-[11px] font-semibold text-brand bg-brand-50 dark:bg-brand/20 px-2 py-0.5 rounded-full">{{ count($journalSearchResults) }} found</span>
                                                </div>
                                                @foreach($journalSearchResults as $journal)
                                                    @php
                                                        $jId = (string) ($journal['_id'] ?? '');
                                                        $jTitle = $journal['journal_title'] ?? '';
                                                        $jShort = $journal['journal_short_name'] ?? '';
                                                        $jEIssn = $journal['e_issn'] ?? '';
                                                        $jPIssn = $journal['p_issn'] ?? '';
                                                        $jIssn = !empty(trim($jEIssn)) ? trim($jEIssn) : trim($jPIssn);
                                                        $issnLabel = !empty(trim($jEIssn)) ? 'E-ISSN' : 'P-ISSN';
                                                    @endphp
                                                    <button 
                                                        type="button"
                                                        wire:click="selectJournal('{{ $jId }}', '{{ addslashes($jTitle) }}', '{{ addslashes($jEIssn) }}', '{{ addslashes($jPIssn) }}')"
                                                        class="w-full text-left px-3.5 py-2.5 hover:bg-brand-50 dark:hover:bg-brand/10 transition-colors flex items-start justify-between gap-3 group cursor-pointer"
                                                    >
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 group-hover:text-brand transition leading-snug break-words whitespace-normal">
                                                                {{ $jTitle }}
                                                            </div>
                                                            @if(!empty($jShort))
                                                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 break-words whitespace-normal">
                                                                    Short: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $jShort }}</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div class="shrink-0 flex flex-col items-end gap-1 mt-0.5">
                                                            @if(!empty(trim($jEIssn)))
                                                                <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700/60 text-zinc-600 dark:text-zinc-300 group-hover:bg-brand/20 group-hover:text-brand transition">
                                                                    E: {{ trim($jEIssn) }}
                                                                </span>
                                                            @endif
                                                            @if(!empty(trim($jPIssn)))
                                                                <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700/60 text-zinc-600 dark:text-zinc-300 group-hover:bg-brand/20 group-hover:text-brand transition">
                                                                    P: {{ trim($jPIssn) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </button>
                                                @endforeach

                                                <!-- Add Journal Option at the Bottom of List (Only shown if no exact match exists) -->
                                                @php
                                                    $hasExactMatch = false;
                                                    $trimmedQuery = mb_strtolower(trim($journalName));
                                                    if ($trimmedQuery !== '') {
                                                        foreach ($journalSearchResults as $res) {
                                                            $t = mb_strtolower(trim($res['journal_title'] ?? ''));
                                                            $s = mb_strtolower(trim($res['journal_short_name'] ?? ''));
                                                            if ($t === $trimmedQuery || $s === $trimmedQuery) {
                                                                $hasExactMatch = true;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                @endphp

                                                @if(!$hasExactMatch)
                                                    <div class="p-2 bg-zinc-50 dark:bg-zinc-800/90 border-t border-zinc-100 dark:border-zinc-700/60 flex items-center justify-between gap-2">
                                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Can't find your journal?</span>
                                                        <button 
                                                            type="button" 
                                                            wire:click="openAddJournalModal" 
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-bold rounded-lg shadow-sm transition cursor-pointer"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                            </svg>
                                                            <span>Add Journal</span>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif(!$isJournalVerified && $showJournalDropdown && empty($journalSearchResults) && mb_strlen(trim($journalName)) >= 3)
                                            <div 
                                                x-show="open" 
                                                class="absolute left-0 right-0 z-30 mt-1 p-3.5 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl space-y-2.5"
                                            >
                                                <div class="text-xs text-zinc-600 dark:text-zinc-300">
                                                    No existing journal found with this name.
                                                </div>
                                                <button 
                                                    type="button" 
                                                    wire:click="openAddJournalModal" 
                                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-bold rounded-lg shadow-sm transition cursor-pointer"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                    </svg>
                                                    <span>Add "{{ Str::limit($journalName, 25) }}" as New Journal</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- ISSN Options & Inputs -->
                                    <div 
                                        class="space-y-1.5" 
                                        id="field-issn"
                                        x-data="{
                                            issnType: @entangle('issnType'),
                                            formatIssn(e, field) {
                                                let raw = (e.target.value || '').toUpperCase();
                                                let clean = '';
                                                for (let i = 0; i < raw.length && clean.length < 8; i++) {
                                                    let ch = raw[i];
                                                    if (clean.length < 7) {
                                                        if (/[0-9]/.test(ch)) clean += ch;
                                                    } else {
                                                        if (/[0-9X]/.test(ch)) clean += ch;
                                                    }
                                                }
                                                if (clean.length > 4) {
                                                    clean = clean.substring(0, 4) + '-' + clean.substring(4);
                                                }
                                                e.target.value = clean;
                                                $wire.set(field, clean);
                                            }
                                        }"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                ISSN <span class="text-red-500">*</span>
                                            </label>
                                            
                                            <!-- ISSN Selection Tabs -->
                                            <div class="inline-flex items-center p-0.5 bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 text-xs">
                                                <button 
                                                    type="button" 
                                                    @click="issnType = 'both'; $wire.set('issnType', 'both')" 
                                                    :class="issnType === 'both' ? 'bg-white dark:bg-zinc-700 text-[#198BEA] font-bold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 font-medium'"
                                                    class="px-2.5 py-0.5 rounded-md transition cursor-pointer text-xs"
                                                >
                                                    Both (E & P)
                                                </button>
                                                <button 
                                                    type="button" 
                                                    @click="issnType = 'e_issn'; $wire.set('issnType', 'e_issn')" 
                                                    :class="issnType === 'e_issn' ? 'bg-white dark:bg-zinc-700 text-[#198BEA] font-bold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 font-medium'"
                                                    class="px-2.5 py-0.5 rounded-md transition cursor-pointer text-xs"
                                                >
                                                    E-ISSN
                                                </button>
                                                <button 
                                                    type="button" 
                                                    @click="issnType = 'p_issn'; $wire.set('issnType', 'p_issn')" 
                                                    :class="issnType === 'p_issn' ? 'bg-white dark:bg-zinc-700 text-[#198BEA] font-bold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 font-medium'"
                                                    class="px-2.5 py-0.5 rounded-md transition cursor-pointer text-xs"
                                                >
                                                    P-ISSN
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Option 1: Both E-ISSN and P-ISSN -->
                                        <div x-show="issnType === 'both'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-0.5">
                                            <div class="space-y-1">
                                                <span class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400">E-ISSN (Electronic) <span class="text-red-500">*</span></span>
                                                <flux:input 
                                                    wire:model="eIssn" 
                                                    x-on:input="formatIssn($event, 'eIssn')" 
                                                    maxlength="9" 
                                                    placeholder="e.g. 1757-790X" 
                                                />
                                                @error('eIssn') 
                                                    <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('eIssn'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                                @enderror
                                            </div>
                                            <div class="space-y-1">
                                                <span class="block text-[11px] font-semibold text-zinc-500 dark:text-zinc-400">P-ISSN (Print) <span class="text-red-500">*</span></span>
                                                <flux:input 
                                                    wire:model="pIssn" 
                                                    x-on:input="formatIssn($event, 'pIssn')" 
                                                    maxlength="9" 
                                                    placeholder="e.g. 1234-5679" 
                                                />
                                                @error('pIssn') 
                                                    <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('pIssn'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Option 2: E-ISSN only -->
                                        <div x-show="issnType === 'e_issn'" class="space-y-1 pt-0.5">
                                            <flux:input 
                                                wire:model="eIssn" 
                                                x-on:input="formatIssn($event, 'eIssn')" 
                                                maxlength="9" 
                                                placeholder="Enter Electronic ISSN, e.g. 1757-790X" 
                                            />
                                            @error('eIssn') 
                                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('eIssn'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                            @enderror
                                        </div>

                                        <!-- Option 3: P-ISSN only -->
                                        <div x-show="issnType === 'p_issn'" class="space-y-1 pt-0.5">
                                            <flux:input 
                                                wire:model="pIssn" 
                                                x-on:input="formatIssn($event, 'pIssn')" 
                                                maxlength="9" 
                                                placeholder="Enter Print ISSN, e.g. 1234-5679" 
                                            />
                                            @error('pIssn') 
                                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('pIssn'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                            @enderror
                                        </div>

                                        @error('issn') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('issn'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror
                                    </div>
                                </div>

                                <!-- Volume, Issue & Citation Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Volume & Issue side-by-side -->
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="space-y-1.5" id="field-volume">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Volume <span class="text-red-500">*</span>
                                            </label>
                                            <flux:input 
                                                type="number"
                                                min="1"
                                                wire:model="volume" 
                                                placeholder="Enter Volume, e.g. 1" 
                                            />
                                            @error('volume') 
                                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('volume'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                            @enderror
                                        </div>
                                        <div class="space-y-1.5" id="field-issue">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Issue <span class="text-red-500">*</span>
                                            </label>
                                            <flux:input 
                                                wire:model="issue" 
                                                placeholder="Enter Issue, e.g. 5" 
                                            />
                                            @error('issue') 
                                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('issue'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Citation -->
                                    <div class="space-y-1.5" id="field-citation">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Citation
                                        </label>
                                        <flux:input 
                                            type="number" 
                                            min="0"
                                            wire:model="citation" 
                                            placeholder="Enter Numeric Value, e.g. 487 or 1002" 
                                        />
                                        @error('citation') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('citation'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror
                                    </div>
                                </div>

                                <!-- Page No & Publication Month-Year Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- Page No -->
                                    <div class="space-y-1.5" id="field-pageNo">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Page No <span class="text-red-500">*</span>
                                        </label>
                                        <flux:input 
                                            wire:model="pageNo" 
                                            placeholder="1-10" 
                                        />
                                        @error('pageNo') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('pageNo'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror
                                    </div>

                                    <!-- Publication Month-Year with Flatpickr Month/Year Picker -->
                                    <div 
                                        class="space-y-1.5"
                                        id="field-pubMonthYear"
                                    >
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Publication Month-Year <span class="text-red-500">*</span>
                                        </label>

                                        <div 
                                            wire:ignore
                                            x-data="{
                                                value: @entangle('pubMonthYear').live,
                                                instance: null,
                                                init() {
                                                    this.$nextTick(() => {
                                                        if (typeof flatpickr !== 'undefined') {
                                                            this.instance = flatpickr(this.$refs.picker, {
                                                                maxDate: new Date(),
                                                                plugins: typeof monthSelectPlugin !== 'undefined' ? [
                                                                    new monthSelectPlugin({
                                                                        shorthand: false,
                                                                        dateFormat: 'F Y',
                                                                        altFormat: 'F Y',
                                                                        theme: 'light'
                                                                    })
                                                                ] : [],
                                                                altInput: true,
                                                                altInputClass: 'w-full h-10 px-3.5 py-2 bg-white dark:bg-zinc-800 border @error('pubMonthYear') border-red-500 @else border-zinc-300 dark:border-zinc-700 @enderror rounded-xl text-sm text-zinc-800 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-[#198BEA]/20 focus:border-[#198BEA] cursor-pointer shadow-xs transition',
                                                                defaultDate: this.value || null,
                                                                onChange: (selectedDates, dateStr) => {
                                                                    this.value = dateStr;
                                                                    $wire.set('pubMonthYear', dateStr);
                                                                }
                                                            });

                                                            this.$watch('value', (val) => {
                                                                if (!val && this.instance) {
                                                                    this.instance.clear();
                                                                } else if (val && this.instance && val !== this.instance.input.value) {
                                                                    this.instance.setDate(val, false);
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                            }"
                                        >
                                            <div class="relative">
                                                <input 
                                                    x-ref="picker" 
                                                    type="text" 
                                                    placeholder="Select Month & Year" 
                                                    class="hidden" 
                                                />
                                            </div>

                                            <div class="pt-0.5">
                                                <button 
                                                    type="button" 
                                                    @click="if (instance) { instance.clear(); } value = ''; $wire.set('pubMonthYear', '');"
                                                    class="px-3 py-1 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-md transition shadow-xs cursor-pointer"
                                                >
                                                    Clear Publication Date
                                                </button>
                                            </div>
                                        </div>
                                        @error('pubMonthYear') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('pubMonthYear'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium mt-1">{{ $message }}</p> 
                                        @enderror
                                    </div>
                                </div>

                                <!-- PDF URL & Landing Page URL Row -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <!-- PDF URL -->
                                    <div class="space-y-1.5" id="field-pdfUrl">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            PDF URL
                                        </label>
                                        <flux:input 
                                            type="url" 
                                            wire:model="pdfUrl" 
                                            placeholder="Published paper PDF URL, e.g. 'https://fake.pdf'" 
                                        />
                                        @error('pdfUrl') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('pdfUrl'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror
                                    </div>

                                    <!-- Landing Page URL -->
                                    <div class="space-y-1.5" id="field-landingPageUrl">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Landing Page URL
                                        </label>
                                        <flux:input 
                                            type="url" 
                                            wire:model="landingPageUrl" 
                                            placeholder="Published paper landing page URL, e.g. 'https://scholar9.com/'" 
                                        />
                                        @error('landingPageUrl') 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('landingPageUrl'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Step 1 Actions: Next Button -->
                        <div class="pt-6 flex items-center justify-end border-t border-zinc-200 dark:border-zinc-800">
                            <flux:button 
                                variant="primary" 
                                type="button" 
                                wire:click="goToNextStep" 
                                class="px-8 bg-[#198BEA] hover:bg-[#1476c9] text-white"
                            >
                                Next
                            </flux:button>
                        </div>

                    @else
                        <!-- ================= STEP 2: UPLOAD FILE & AUTHOR DETAILS ================= -->

                        <!-- File Upload -->
                        <div class="space-y-1.5" id="field-file">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                        Upload File
                                    </span>
                                    <span class="text-xs text-zinc-500 font-normal">supports .pdf (max 15MB)</span>
                                </div>
                                @if($file && !$errors->has('file'))
                                    <button 
                                        type="button" 
                                        wire:click="removeFile" 
                                        class="text-xs font-semibold text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition"
                                    >
                                        Remove file
                                    </button>
                                @endif
                            </div>
                            
                            <label class="group border-2 border-dashed rounded-xl p-4 flex items-center justify-between transition cursor-pointer {{ $errors->has('file') ? 'border-red-400 bg-red-50/40 dark:bg-red-950/20 dark:border-red-600' : ($file ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/20 dark:border-emerald-500 hover:bg-emerald-50/80 dark:hover:bg-emerald-950/30' : 'border-zinc-300 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-600 bg-zinc-50/50 dark:bg-zinc-800/50 hover:bg-zinc-100/50 dark:hover:bg-zinc-800/80') }}">
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($file && !$errors->has('file'))
                                        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center shrink-0 text-emerald-600 dark:text-emerald-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                            </svg>
                                        </div>
                                    @else
                                        <svg class="w-8 h-8 {{ $errors->has('file') ? 'text-red-400' : 'text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300' }} shrink-0 transition" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                        </svg>
                                    @endif

                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold truncate {{ $errors->has('file') ? 'text-red-700 dark:text-red-300' : ($file ? 'text-emerald-900 dark:text-emerald-200' : 'text-zinc-700 dark:text-zinc-300') }}">
                                            {{ $file ? $file->getClientOriginalName() : 'choose your file' }}
                                        </p>
                                        @if($file && !$errors->has('file'))
                                            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                                Upload complete ({{ round($file->getSize() / 1024, 1) }} KB)
                                            </p>
                                        @endif
                                        <div wire:loading wire:target="file" class="text-xs text-sky-600 dark:text-sky-400 font-medium">
                                            Uploading file...
                                        </div>
                                    </div>
                                </div>
                                
                                @if($file && !$errors->has('file'))
                                    <div class="px-3.5 py-1.5 bg-emerald-600 group-hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-lg transition shrink-0 pointer-events-none shadow-xs">
                                        <span>Change File</span>
                                    </div>
                                @else
                                    <div class="px-4 py-2 bg-zinc-200 group-hover:bg-zinc-300 dark:bg-zinc-700 dark:group-hover:bg-zinc-600 text-zinc-800 dark:text-zinc-200 text-sm font-semibold rounded-lg transition shrink-0 pointer-events-none">
                                        <span>Upload</span>
                                    </div>
                                @endif
                                
                                <input 
                                    type="file" 
                                    wire:model="file" 
                                    accept=".pdf,application/pdf" 
                                    class="hidden" 
                                    @change="
                                        const f = $event.target.files[0];
                                        if (f && f.size > 15 * 1024 * 1024) {
                                            alert('File size exceeds the 15MB limit. Please choose a smaller PDF file.');
                                            $event.target.value = '';
                                        }
                                    "
                                />
                            </label>
                            @error('file') 
                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('file'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                            @enderror
                        </div>

                        <!-- Authors list -->
                        <div class="space-y-6 pt-2">
                            @foreach($authors as $index => $author)
                                <div class="p-4 bg-zinc-50/70 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/80 rounded-xl space-y-4 relative">
                                    <div class="flex items-center justify-between pb-2 border-b border-zinc-200/60 dark:border-zinc-700/50">
                                        <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Author #{{ $index + 1 }}</span>
                                        @if(count($authors) > 1)
                                            <button 
                                                type="button" 
                                                wire:click="removeAuthor({{ $index }})" 
                                                class="p-1 text-zinc-400 hover:text-red-600 dark:hover:text-red-400 transition"
                                                title="Remove Author"
                                            >
                                                <svg class="w-5 h-5 text-sky-500 hover:text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <!-- Author Name with Auto-complete Dropdown -->
                                        <div 
                                            class="space-y-1 relative" 
                                            id="field-authors-{{ $index }}-name"
                                            x-data="{ 
                                                open: true,
                                                queryLength: $wire.authors[{{ $index }}]?.name ? $wire.authors[{{ $index }}].name.length : 0 
                                            }" 
                                            @click.outside="open = false"
                                        >
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Author Name <span class="text-red-500">*</span>
                                            </label>
                                            
                                            <div class="relative">
                                                <flux:input 
                                                    wire:model.live.debounce.300ms="authors.{{ $index }}.name" 
                                                    x-on:input="queryLength = $event.target.value.trim().length; if (queryLength >= 3) { open = true; }"
                                                    x-on:focus="if (queryLength >= 3) { open = true; }"
                                                    placeholder="Search author name Or Add" 
                                                />
                                                
                                                <!-- Loading Spinner -->
                                                <div wire:loading wire:target="authors.{{ $index }}.name" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                                    <svg class="animate-spin h-4 w-4 text-[#198BEA]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                            </div>

                                            <!-- 1-2 char helper text -->
                                            <template x-if="queryLength > 0 && queryLength < 3">
                                                <p class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">Type at least 3 characters to search registered authors...</p>
                                            </template>

                                            <!-- Dropdown Results -->
                                            @if(!empty($showAuthorDropdown[$index]) && !empty($authorSearchResults[$index]))
                                                <div 
                                                    x-show="open" 
                                                    x-transition:enter="transition ease-out duration-150"
                                                    x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
                                                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                    x-transition:leave="transition ease-in duration-100"
                                                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                    x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
                                                    class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl divide-y divide-zinc-100 dark:divide-zinc-700/60"
                                                >
                                                    <div class="px-3.5 py-2 bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-100 dark:border-zinc-700/60 flex items-center justify-between">
                                                        <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Registered Authors</span>
                                                        <span class="text-[11px] font-semibold text-[#198BEA] bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded-full">{{ count($authorSearchResults[$index]) }} found</span>
                                                    </div>
                                                    
                                                    @foreach($authorSearchResults[$index] as $userResult)
                                                        @php
                                                            $uName = $userResult['name'] ?? '';
                                                            $uEmail = $userResult['email'] ?? '';
                                                            $uAffiliation = $userResult['affiliation'] ?? '';
                                                            $uPhoto = $userResult['photo'] ?? 'assets/images/vector.png';
                                                            $uPhotoSrc = (!empty($uPhoto) && !str_starts_with($uPhoto, 'http')) ? asset($uPhoto) : ($uPhoto ?: asset('assets/images/vector.png'));
                                                        @endphp
                                                        <button 
                                                            type="button"
                                                            @click="open = false"
                                                            wire:click="selectAuthor({{ $index }}, '{{ addslashes($uName) }}', '{{ addslashes($uEmail) }}', '{{ addslashes($uAffiliation) }}')"
                                                            class="w-full text-left px-3.5 py-2.5 hover:bg-sky-50/70 dark:hover:bg-zinc-700/60 transition-colors flex items-center gap-3 group cursor-pointer"
                                                        >
                                                            <!-- User Photo -->
                                                            <img 
                                                                src="{{ $uPhotoSrc }}" 
                                                                alt="{{ $uName }}" 
                                                                class="w-9 h-9 rounded-full object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 bg-zinc-100"
                                                                onerror="this.onerror=null; this.src='{{ asset('assets/images/vector.png') }}';"
                                                            />

                                                            <!-- User details -->
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 group-hover:text-[#198BEA] transition leading-snug truncate">
                                                                    {{ $uName }}
                                                                </div>
                                                                @if(!empty($uAffiliation))
                                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                                                                        <span>{{ $uAffiliation }}</span>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <div class="text-xs font-semibold text-[#198BEA] opacity-0 group-hover:opacity-100 transition shrink-0">
                                                                Select →
                                                            </div>
                                                        </button>
                                                    @endforeach

                                                    <!-- Dropdown Footer Add Author Action -->
                                                    <div class="p-2 bg-zinc-50 dark:bg-zinc-800/80 border-t border-zinc-100 dark:border-zinc-700/60 text-center">
                                                        <button 
                                                            type="button"
                                                            @click="open = false"
                                                            wire:click="openAddAuthorModal({{ $index }})"
                                                            class="w-full py-1.5 px-3 text-xs font-semibold text-[#198BEA] hover:bg-sky-50 dark:hover:bg-sky-950/40 rounded-lg transition flex items-center justify-center gap-1.5 cursor-pointer"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                                            </svg>
                                                            <span>Add new author instead</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            @elseif(!empty($showAuthorDropdown[$index]) && empty($authorSearchResults[$index]) && mb_strlen(trim($authors[$index]['name'] ?? '')) >= 3)
                                                <!-- No Results Found: Clean Add Author Prompt -->
                                                <div 
                                                    x-show="open" 
                                                    class="absolute left-0 right-0 z-30 mt-1 p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl text-center space-y-2"
                                                >
                                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        No registered author found for "<span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $authors[$index]['name'] ?? '' }}</span>"
                                                    </p>
                                                    <button 
                                                        type="button" 
                                                        @click="open = false"
                                                        wire:click="openAddAuthorModal({{ $index }})" 
                                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-[#198BEA] hover:bg-[#1476c9] text-white text-xs font-semibold rounded-lg transition shadow-xs cursor-pointer"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                                        </svg>
                                                        <span>Add Author</span>
                                                    </button>
                                                </div>
                                            @endif

                                            @error("authors.{$index}.name") 
                                                <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('authors.{{ $index }}.name'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                            @enderror

                                            @if(session('author_added_success'))
                                                <p x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1">{{ session('author_added_success') }}</p>
                                            @endif
                                        </div>

                                        <!-- Author Affiliation -->
                                        <div class="space-y-1">
                                            <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                                Author Affiliation
                                            </label>
                                            <flux:input 
                                                wire:model="authors.{{ $index }}.affiliation" 
                                                placeholder="Enter author affiliation" 
                                            />
                                        </div>
                                    </div>

                                    <!-- Author Email -->
                                    <div class="space-y-1" id="field-authors-{{ $index }}-email">
                                        <label class="block text-sm font-bold text-zinc-800 dark:text-zinc-200">
                                            Author Email <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <flux:input 
                                                type="email" 
                                                wire:model.live.debounce.400ms="authors.{{ $index }}.email" 
                                                placeholder="Enter author email, e.g. rahul@gmail.com" 
                                            />
                                            
                                            <!-- Email lookup loading spinner -->
                                            <div wire:loading wire:target="authors.{{ $index }}.email" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                                <svg class="animate-spin h-4 w-4 text-[#198BEA]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        @error("authors.{$index}.email") 
                                            <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('authors.{{ $index }}.email'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium">{{ $message }}</p> 
                                        @enderror
                                    </div>
                                </div>
                            @endforeach

                            <!-- Add Author Button -->
                            <div>
                                <button 
                                    type="button" 
                                    wire:click="addAuthor" 
                                    class="px-4 py-2 bg-zinc-200 dark:bg-zinc-700 hover:bg-zinc-300 dark:hover:bg-zinc-600 text-zinc-800 dark:text-zinc-100 text-sm font-semibold rounded-lg transition shadow-xs flex items-center gap-1.5"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                    </svg>
                                    <span>Add Author</span>
                                </button>
                            </div>

                            <!-- Copyright Checkbox -->
                            <div class="pt-2" id="field-agreeCopyright">
                                <label class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300 font-medium cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        wire:model="agreeCopyright" 
                                        class="w-4 h-4 text-[#198BEA] rounded border-zinc-300 focus:ring-[#198BEA]" 
                                    />
                                    <span>confirm copyright and licensing agreement.</span>
                                </label>
                                @error('agreeCopyright') 
                                    <p x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.clearFieldValidation('agreeCopyright'); }, 5000)" x-show="show" x-transition.opacity.duration.300ms class="text-xs text-red-500 font-medium mt-1">{{ $message }}</p> 
                                @enderror
                            </div>
                        </div>

                        <!-- Step 2 Actions: Previous & Submit Buttons -->
                        <div class="pt-6 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-800">
                            <flux:button 
                                variant="subtle" 
                                type="button" 
                                wire:click="goToPreviousStep" 
                                class="px-6 border border-zinc-300 dark:border-zinc-700"
                            >
                                Previous
                            </flux:button>
                            <flux:button 
                                variant="primary" 
                                type="submit" 
                                class="px-8 bg-[#198BEA] hover:bg-[#1476c9] text-white"
                            >
                                Submit
                            </flux:button>
                        </div>

                    @endif

                </form>
            </div>

            <!-- RIGHT CONTAINER: REUSABLE SIDEBAR BANNERS -->
            <x-article.sidebar />

        </div>
    </div>

    <!-- ================= REUSABLE DUPLICATE ARTICLE POPUP MODAL ================= -->
    <x-modals.duplicate-article targetInput="#field-title input" />

    <!-- ================= REUSABLE ADD NEW JOURNAL POPUP MODAL ================= -->
    <x-modals.add-journal />

    <!-- ================= REUSABLE ADD NEW AUTHOR POPUP MODAL ================= -->
    <x-modals.add-author />
</div>

