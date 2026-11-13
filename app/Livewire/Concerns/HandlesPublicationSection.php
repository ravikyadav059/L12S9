<?php

namespace App\Livewire\Concerns;

use App\Helpers\IssnHelper;
use App\Models\Article;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\Publication;
use App\Models\ReviewerJournal;
use App\Models\ReviewerProfile;
use App\Models\User;
use App\Rules\ValidIssn;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MongoDB\BSON\Regex;

trait HandlesPublicationSection
{
    use HandlesAddAuthorModal;
    use HandlesAddJournalModal;
    use HandlesDuplicateChecks;

    /**
     * Temporary uploaded PDF file.
     *
     * @var mixed
     */
    public $pdfFile = null;

    /**
     * Journal properties used by HandlesAddJournalModal hook.
     */
    public string $journal_title = '';

    public string $journal_name = '';

    public string $journal_short_name = '';

    public string $e_issn = '';

    public string $p_issn = '';

    /**
     * Hook called by HandlesAddJournalModal after a new journal is saved.
     */
    protected function afterJournalSaved(ReviewerJournal $journal): void
    {
        $this->journal_title = (string) $journal->_id;
        $this->journal_name = (string) ($journal->journal_title ?? '');
        $this->journal_short_name = (string) ($journal->journal_short_name ?? '');
        $this->e_issn = (string) ($journal->e_issn ?? '');
        $this->p_issn = (string) ($journal->p_issn ?? '');
    }

    /**
     * Retrieve publication metadata by DOI (Livewire wrapper).
     *
     * @return array<string, mixed>
     */
    public function retrieveFromCrossref(string $doi): array
    {
        return static::retrieveDoiData($doi);
    }

    /**
     * Retrieve publication metadata by DOI via external registry and match local journal.
     *
     * @return array<string, mixed>
     */
    public static function retrieveDoiData(string $doi): array
    {
        $cleanDoi = trim($doi);
        $cleanDoi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $cleanDoi) ?? $cleanDoi;
        $cleanDoi = preg_replace('#^doi:\s*#i', '', $cleanDoi) ?? $cleanDoi;
        $cleanDoi = trim($cleanDoi);

        if ($cleanDoi === '') {
            return [
                'found' => false,
                'message' => 'Please enter a valid DOI before clicking Retrieve.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Scholar9/1.0 (mailto:support@scholar9.com)',
                    'Accept' => 'application/json',
                ])
                ->get('https://api.crossref.org/works/'.rawurlencode($cleanDoi));

            if (! $response->successful()) {
                return [
                    'found' => false,
                    'message' => 'No publication found for DOI: '.$cleanDoi,
                ];
            }

            $payload = $response->json();
            $message = $payload['message'] ?? null;

            if (! is_array($message)) {
                return [
                    'found' => false,
                    'message' => 'Unexpected response format while retrieving DOI metadata.',
                ];
            }

            $title = '';
            if (! empty($message['title']) && is_array($message['title'])) {
                $title = trim(html_entity_decode(strip_tags((string) $message['title'][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $url = trim((string) ($message['URL'] ?? ''));
            if ($url === '' && $cleanDoi !== '') {
                $url = 'https://doi.org/'.$cleanDoi;
            }

            $journalName = '';
            if (! empty($message['container-title']) && is_array($message['container-title'])) {
                $journalName = trim((string) $message['container-title'][0]);
            } elseif (! empty($message['short-container-title']) && is_array($message['short-container-title'])) {
                $journalName = trim((string) $message['short-container-title'][0]);
            }

            $eIssn = '';
            $pIssn = '';
            if (! empty($message['issn-type']) && is_array($message['issn-type'])) {
                foreach ($message['issn-type'] as $issnEntry) {
                    if (! is_array($issnEntry)) {
                        continue;
                    }
                    $val = IssnHelper::format((string) ($issnEntry['value'] ?? ''));
                    $type = strtolower((string) ($issnEntry['type'] ?? ''));
                    if ($val === '') {
                        continue;
                    }
                    if ($type === 'electronic' && $eIssn === '') {
                        $eIssn = $val;
                    } elseif ($type === 'print' && $pIssn === '') {
                        $pIssn = $val;
                    }
                }
            }

            if ($eIssn === '' && $pIssn === '' && ! empty($message['ISSN']) && is_array($message['ISSN'])) {
                $eIssn = IssnHelper::format((string) ($message['ISSN'][0] ?? ''));
                if (isset($message['ISSN'][1])) {
                    $pIssn = IssnHelper::format((string) $message['ISSN'][1]);
                }
            }

            // Try to match an existing ReviewerJournal by ISSN or exact title
            $matchedJournal = null;
            $issnCandidates = array_values(array_filter([$eIssn, $pIssn]));
            if (! empty($issnCandidates)) {
                $matchedJournal = ReviewerJournal::query()
                    ->where(function ($q) use ($issnCandidates): void {
                        $q->whereIn('e_issn', $issnCandidates)
                            ->orWhereIn('p_issn', $issnCandidates);
                    })
                    ->first();
            }

            if (! $matchedJournal && $journalName !== '') {
                $matchedJournal = ReviewerJournal::query()
                    ->where('journal_title', 'regex', new Regex('^'.preg_quote($journalName, '/').'$', 'i'))
                    ->first();
            }

            $journalId = $matchedJournal ? (string) $matchedJournal->_id : '';
            if ($matchedJournal) {
                $journalName = (string) ($matchedJournal->journal_title ?? $journalName);
                if ($eIssn === '' && ! empty($matchedJournal->e_issn)) {
                    $eIssn = IssnHelper::format((string) $matchedJournal->e_issn);
                }
                if ($pIssn === '' && ! empty($matchedJournal->p_issn)) {
                    $pIssn = IssnHelper::format((string) $matchedJournal->p_issn);
                }
            }

            $publishedDate = static::extractCrossrefPublishedDate($message);

            $authors = [];
            if (! empty($message['author']) && is_array($message['author'])) {
                foreach ($message['author'] as $authorData) {
                    if (! is_array($authorData)) {
                        continue;
                    }
                    $fullName = trim(((string) ($authorData['given'] ?? '')).' '.((string) ($authorData['family'] ?? '')));
                    if ($fullName === '' && ! empty($authorData['name'])) {
                        $fullName = trim((string) $authorData['name']);
                    }
                    if ($fullName === '') {
                        continue;
                    }
                    $affiliation = '';
                    if (! empty($authorData['affiliation']) && is_array($authorData['affiliation'])) {
                        $firstAff = $authorData['affiliation'][0] ?? null;
                        if (is_array($firstAff) && ! empty($firstAff['name'])) {
                            $affiliation = trim((string) $firstAff['name']);
                        }
                    }
                    $authors[] = [
                        'name' => $fullName,
                        'affiliation' => $affiliation,
                        'email' => '',
                    ];
                }
            }

            $abstract = '';
            if (! empty($message['abstract'])) {
                $decoded = html_entity_decode((string) $message['abstract'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $stripped = strip_tags($decoded);
                $abstract = trim((string) preg_replace('/\s+/', ' ', $stripped));
            }

            $keywords = [];
            if (! empty($message['subject']) && is_array($message['subject'])) {
                $keywords = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $message['subject'])))));
            }

            $matchedTypeId = static::matchCrossrefArticleType((string) ($message['type'] ?? ''));

            $issnType = 'both';
            if ($eIssn !== '' && $pIssn === '') {
                $issnType = 'e_issn';
            } elseif ($pIssn !== '' && $eIssn === '') {
                $issnType = 'p_issn';
            }

            return [
                'found' => true,
                'data' => [
                    'doi' => $cleanDoi,
                    'title' => $title,
                    'url' => $url,
                    'type' => $matchedTypeId,
                    'authors' => $authors,
                    'journal_title' => $journalId,
                    'journal_name' => $journalName,
                    'issn_type' => $issnType,
                    'e_issn' => $eIssn,
                    'p_issn' => $pIssn,
                    'published_date' => $publishedDate,
                    'citations' => isset($message['is-referenced-by-count']) ? (string) ((int) $message['is-referenced-by-count']) : '',
                    'volume' => isset($message['volume']) ? trim((string) $message['volume']) : '',
                    'issue' => isset($message['issue']) ? trim((string) $message['issue']) : '',
                    'page_no' => isset($message['page']) ? trim((string) $message['page']) : '',
                    'publication_keywords' => $keywords,
                    'description' => $abstract,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'found' => false,
                'message' => 'Could not retrieve DOI metadata right now. Please try again.',
            ];
        }
    }

    /**
     * Check if a publication title already exists (excluding the record being edited).
     */
    public function checkTitleDuplicate(string $title, ?string $excludeId = null): bool
    {
        return $this->checkPublicationTitleDuplicate($title, $excludeId);
    }

    /**
     * Search registered journals using the indexed & Redis-cached ReviewerJournal::searchTitles().
     *
     * @return list<array<string, mixed>>
     */
    public function searchJournals(string $query): array
    {
        return ReviewerJournal::searchTitles($query, 15);
    }

    /**
     * Search registered researchers for the Authors autocomplete dropdown (Redis-cached & prefix-first).
     *
     * @return list<array<string, string>>
     */
    public function searchAuthors(string $query): array
    {
        return static::searchAuthorsQuery($query);
    }

    /**
     * Shared Redis-cached & prefix-first author search used by both Livewire and /api/authors.
     *
     * @return list<array<string, string>>
     */
    public static function searchAuthorsQuery(string $query): array
    {
        $trimmed = trim($query);
        if (mb_strlen($trimmed) < 2) {
            return [];
        }

        $cacheKey = 'authors:search:v1:'.md5(strtolower($trimmed));

        return Cache::remember($cacheKey, 300, function () use ($trimmed): array {
            $queryParts = array_values(array_filter(explode(' ', $trimmed)));

            $runSearch = static function (bool $prefixOnly) use ($queryParts) {
                $usersQuery = User::where(function ($q): void {
                    $q->where('type', 0)
                        ->orWhere('type', '0')
                        ->orWhereNull('type');
                })
                    ->where('status', '!=', 3)
                    ->where('status', '!=', '3');

                $usersQuery->where(function ($q) use ($queryParts, $prefixOnly): void {
                    foreach ($queryParts as $part) {
                        $escaped = preg_quote($part, '/');
                        $pattern = $prefixOnly ? '^'.$escaped : $escaped;
                        $partRegex = new Regex($pattern, 'i');
                        $q->where(function ($sub) use ($partRegex): void {
                            $sub->where('first_name', 'regex', $partRegex)
                                ->orWhere('last_name', 'regex', $partRegex)
                                ->orWhere('email', 'regex', $partRegex);
                        });
                    }
                });

                return $usersQuery
                    ->select(['_id', 'first_name', 'last_name', 'fullname', 'email', 'photo'])
                    ->limit(10)
                    ->get();
            };

            $users = $runSearch(true);
            if ($users->isEmpty()) {
                $users = $runSearch(false);
            }

            if ($users->isEmpty()) {
                return [];
            }

            $userIds = $users->pluck('_id')->map(fn ($id) => (string) $id)->toArray();
            $profiles = ReviewerProfile::whereIn('user_id', $userIds)->select(['user_id', 'experience'])->get()->keyBy(fn ($p) => (string) $p->user_id);

            $expIds = [];
            $profileExpMap = [];
            foreach ($profiles as $uId => $profile) {
                $exp = $profile->experience ?? null;
                if (is_array($exp) && ! empty($exp)) {
                    $firstExp = is_array($exp[0]) ? ($exp[0]['_id'] ?? $exp[0]['organization_id'] ?? null) : $exp[0];
                    if ($firstExp) {
                        $expIds[] = (string) $firstExp;
                        $profileExpMap[$uId] = (string) $firstExp;
                    }
                }
            }

            $experiences = ! empty($expIds)
                ? Experience::whereIn('_id', array_values(array_unique($expIds)))->select(['_id', 'organization_id'])->get()->keyBy(fn ($e) => (string) $e->_id)
                : collect();

            $orgIds = [];
            foreach ($experiences as $e) {
                if (! empty($e->organization_id)) {
                    $orgIds[] = (string) $e->organization_id;
                }
            }

            $organizations = ! empty($orgIds)
                ? Organization::whereIn('_id', array_values(array_unique($orgIds)))->select(['_id', 'organization_name'])->get()->keyBy(fn ($o) => (string) $o->_id)
                : collect();

            return $users->map(function (User $user) use ($profileExpMap, $experiences, $organizations): array {
                $uId = (string) $user->_id;
                $affiliation = '';
                if (isset($profileExpMap[$uId]) && isset($experiences[$profileExpMap[$uId]])) {
                    $exp = $experiences[$profileExpMap[$uId]];
                    if (! empty($exp->organization_id) && isset($organizations[(string) $exp->organization_id])) {
                        $affiliation = (string) ($organizations[(string) $exp->organization_id]->organization_name ?? '');
                    }
                }

                $name = trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));
                if ($name === '') {
                    $name = (string) ($user->fullname ?? $user->email ?? '');
                }

                $rawPhoto = ! empty($user->photo) ? (string) $user->photo : 'assets/images/vector.png';
                $photoSrc = (! empty($rawPhoto) && ! str_starts_with($rawPhoto, 'http'))
                    ? asset($rawPhoto)
                    : ($rawPhoto ?: asset('assets/images/vector.png'));

                return [
                    'id' => $uId,
                    'name' => $name,
                    'email' => (string) ($user->email ?? ''),
                    'affiliation' => $affiliation,
                    'photo' => $photoSrc,
                ];
            })->values()->all();
        });
    }

    /**
     * Validate and persist a publication record, syncing ReviewerProfile and Redis cache.
     *
     * @param  array<string, mixed>  $formData
     *
     * @throws ValidationException
     */
    protected function persistPublication(array $formData, string $ownerUserId, bool $allowStatusChange = false): Publication
    {
        $recordId = ! empty($formData['id']) ? (string) $formData['id'] : null;

        $issnType = (string) ($formData['issn_type'] ?? 'both');
        if (! in_array($issnType, ['both', 'e_issn', 'p_issn'], true)) {
            $issnType = 'both';
        }

        $eIssn = IssnHelper::format((string) ($formData['e_issn'] ?? ''));
        $pIssn = IssnHelper::format((string) ($formData['p_issn'] ?? ''));

        $authors = array_values(array_filter(array_map(function ($author): array {
            $a = is_array($author) ? $author : [];

            return [
                'name' => trim((string) ($a['name'] ?? '')),
                'affiliation' => trim((string) ($a['affiliation'] ?? '')),
                'email' => trim((string) ($a['email'] ?? '')),
            ];
        }, (array) ($formData['authors'] ?? [])), fn (array $a) => $a['name'] !== '' || $a['email'] !== '' || $a['affiliation'] !== ''));

        if (empty($authors)) {
            $authors = [['name' => '', 'affiliation' => '', 'email' => '']];
        }

        $keywords = array_values(array_unique(array_filter(array_map(
            fn ($k) => trim((string) $k),
            (array) ($formData['publication_keywords'] ?? [])
        ))));

        $dataToValidate = [
            'doi' => trim((string) ($formData['doi'] ?? '')),
            'title' => trim((string) ($formData['title'] ?? '')),
            'url' => trim((string) ($formData['url'] ?? '')),
            'type' => trim((string) ($formData['type'] ?? '')),
            'journal_title' => trim((string) ($formData['journal_title'] ?? '')),
            'journal_name' => trim((string) ($formData['journal_name'] ?? '')),
            'e_issn' => $eIssn,
            'p_issn' => $pIssn,
            'published_date' => trim((string) ($formData['published_date'] ?? '')),
            'citations' => isset($formData['citations']) && trim((string) $formData['citations']) !== '' ? trim((string) $formData['citations']) : null,
            'volume' => isset($formData['volume']) && trim((string) $formData['volume']) !== '' ? trim((string) $formData['volume']) : null,
            'issue' => trim((string) ($formData['issue'] ?? '')),
            'page_no' => trim((string) ($formData['page_no'] ?? '')),
            'description' => trim((string) ($formData['description'] ?? '')),
            'authors' => $authors,
            'pdfFile' => $this->pdfFile,
        ];

        $rules = [
            'doi' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'url', 'max:1000'],
            'type' => ['required', 'string'],
            'journal_title' => ['required', 'string'],
            'e_issn' => [
                in_array($issnType, ['both', 'e_issn'], true) ? 'required' : 'nullable',
                'string',
                'max:20',
                new ValidIssn,
            ],
            'p_issn' => [
                in_array($issnType, ['both', 'p_issn'], true) ? 'required' : 'nullable',
                'string',
                'max:20',
                new ValidIssn,
            ],
            'published_date' => ['required', 'string', 'max:100'],
            'citations' => ['nullable', 'integer', 'min:0'],
            'volume' => ['nullable', 'integer', 'min:1'],
            'issue' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9]+([-–][a-zA-Z0-9]+)*$/'],
            'page_no' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:10000'],
            'authors' => ['required', 'array', 'min:1'],
            'authors.*.name' => ['required', 'string', 'max:255'],
            'authors.*.affiliation' => ['nullable', 'string', 'max:255'],
            'authors.*.email' => ['nullable', 'email', 'max:255'],
            'pdfFile' => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
        ];

        $messages = [
            'title.required' => 'Title is required.',
            'type.required' => 'Article Type is required.',
            'journal_title.required' => 'Please select a journal from the list or add a new journal.',
            'e_issn.required' => 'E-ISSN is required.',
            'p_issn.required' => 'P-ISSN is required.',
            'published_date.required' => 'Publication Date is required.',
            'description.required' => 'Abstract/Description is required.',
            'issue.regex' => 'Issue must be alphanumeric or a range (e.g., 1, S1, 1-2).',
            'authors.*.name.required' => 'Author name is required.',
            'authors.*.email.email' => 'Please enter a valid author email address.',
            'pdfFile.mimes' => 'Only PDF files are allowed.',
            'pdfFile.max' => 'The PDF file must not exceed 15MB.',
        ];

        $validator = Validator::make($dataToValidate, $rules, $messages);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Duplicate title check (excluding current record if editing)
        if ($this->checkPublicationTitleDuplicate($dataToValidate['title'], $recordId)) {
            throw ValidationException::withMessages([
                'title' => 'A publication with this title already exists.',
            ]);
        }

        $journal = ReviewerJournal::find($dataToValidate['journal_title']);
        $resolvedJournalName = $journal ? (string) ($journal->journal_title ?? $dataToValidate['journal_name']) : $dataToValidate['journal_name'];

        $record = $recordId ? Publication::findOrFail($recordId) : new Publication;

        if (! $recordId) {
            $maxSerial = (int) (Publication::max('serial_number') ?? 0);
            $serialNumber = $maxSerial + 1;
            $record->serial_number = $serialNumber;
            $record->slug = Str::slug($dataToValidate['title']).'-'.$serialNumber;
            $record->user_id = $ownerUserId;
            $record->article_type = 'published';
            $record->publication_type = 'journal';
            $record->submit_from_page = 'profile';
        }

        $imagePath = (string) ($record->image ?? '');
        if (! empty($formData['remove_existing_pdf']) && $imagePath !== '') {
            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = '';
        }

        if ($this->pdfFile) {
            if ($imagePath !== '' && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->pdfFile->store('publications', 'public');
        }

        $registeredCoAuthors = [$ownerUserId];
        foreach ($authors as $author) {
            if ($author['email'] !== '') {
                $matchedUser = User::where('email', 'regex', new Regex('^'.preg_quote($author['email'], '/').'$', 'i'))->first();
                if ($matchedUser) {
                    $registeredCoAuthors[] = (string) $matchedUser->_id;
                }
            }
        }
        $registeredCoAuthors = array_values(array_unique(array_filter($registeredCoAuthors)));

        $status = $allowStatusChange
            ? ((int) ($formData['status'] ?? 1))
            : ($recordId ? (int) ($record->status ?? 1) : 1);

        $record->fill([
            'doi' => $dataToValidate['doi'] !== '' ? $dataToValidate['doi'] : null,
            'title' => $dataToValidate['title'],
            'url' => $dataToValidate['url'] !== '' ? $dataToValidate['url'] : null,
            'type' => $dataToValidate['type'],
            'journal_title' => $dataToValidate['journal_title'],
            'journal_name' => $resolvedJournalName,
            'issn' => $eIssn !== '' ? $eIssn : $pIssn,
            'e_issn' => $eIssn !== '' ? $eIssn : null,
            'p_issn' => $pIssn !== '' ? $pIssn : null,
            'published_date' => $dataToValidate['published_date'],
            'citations' => $dataToValidate['citations'] !== null ? (int) $dataToValidate['citations'] : null,
            'volume' => $dataToValidate['volume'] !== null ? (int) $dataToValidate['volume'] : null,
            'issue' => $dataToValidate['issue'] !== '' ? $dataToValidate['issue'] : null,
            'page_no' => $dataToValidate['page_no'] !== '' ? $dataToValidate['page_no'] : null,
            'description' => $dataToValidate['description'],
            'authors' => $authors,
            'registered_co_author' => $registeredCoAuthors,
            'publication_keywords' => $keywords,
            'image' => $imagePath !== '' ? $imagePath : null,
            'status' => $status,
        ]);

        $record->save();

        // Sync ReviewerProfile.publication atomically
        $profile = ReviewerProfile::where('user_id', $ownerUserId)->first();
        if (! $profile) {
            $profile = ReviewerProfile::create([
                'user_id' => $ownerUserId,
                'publication' => [(string) $record->_id],
            ]);
        } else {
            $profile->push('publication', (string) $record->_id, true);
        }

        Publication::forgetUserCache($ownerUserId);
        foreach ($registeredCoAuthors as $coAuthorId) {
            Publication::forgetUserCache($coAuthorId);
        }

        $this->pdfFile = null;

        return $record;
    }

    /**
     * Delete a publication record and remove its ID from ReviewerProfile.publication.
     */
    protected function deletePublicationRecord(string $id, string $ownerUserId): void
    {
        $record = Publication::find($id);
        if (! $record) {
            return;
        }

        $coAuthors = is_array($record->registered_co_author) ? $record->registered_co_author : [];

        if (! empty($record->image) && Storage::disk('public')->exists((string) $record->image)) {
            Storage::disk('public')->delete((string) $record->image);
        }

        $record->delete();

        $profile = ReviewerProfile::where('user_id', $ownerUserId)->first();
        if ($profile) {
            $profile->pull('publication', $id);
        }

        Publication::forgetUserCache($ownerUserId);
        foreach ($coAuthors as $coAuthorId) {
            Publication::forgetUserCache((string) $coAuthorId);
        }
    }

    /**
     * Extract formatted publication month and year (e.g., "September 2024") from Crossref date parts.
     *
     * @param  array<string, mixed>  $message
     */
    private static function extractCrossrefPublishedDate(array $message): string
    {
        $candidates = [
            $message['published']['date-parts'][0] ?? null,
            $message['published-print']['date-parts'][0] ?? null,
            $message['published-online']['date-parts'][0] ?? null,
            $message['issued']['date-parts'][0] ?? null,
        ];

        foreach ($candidates as $parts) {
            if (! is_array($parts) || empty($parts[0])) {
                continue;
            }
            $year = (int) $parts[0];
            $month = isset($parts[1]) ? max(1, min(12, (int) $parts[1])) : 1;

            try {
                return Carbon::createFromDate($year, $month, 1)->format('F Y');
            } catch (\Throwable) {
                return (string) $year;
            }
        }

        return '';
    }

    /**
     * Best-effort match of Crossref work type to local Article (article_type) collection.
     */
    private static function matchCrossrefArticleType(string $crossrefType): string
    {
        if ($crossrefType === '') {
            return '';
        }

        $normalized = strtolower(str_replace('-', ' ', $crossrefType));
        $articles = Article::all();

        foreach ($articles as $article) {
            $name = strtolower(trim((string) $article->name));
            if ($name === $normalized || str_contains($name, $normalized) || str_contains($normalized, $name)) {
                return (string) $article->_id;
            }
        }

        if ($crossrefType === 'journal-article') {
            foreach ($articles as $article) {
                $name = strtolower(trim((string) $article->name));
                if (str_contains($name, 'research') || str_contains($name, 'journal') || str_contains($name, 'article')) {
                    return (string) $article->_id;
                }
            }
        }

        return '';
    }
}
