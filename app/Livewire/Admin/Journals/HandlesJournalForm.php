<?php

namespace App\Livewire\Admin\Journals;

use App\Livewire\Concerns\HandlesJournalVerification;
use App\Models\City;
use App\Models\Experience;
use App\Models\IndexingAgency;
use App\Models\JournalRole;
use App\Models\Organization;
use App\Models\ReviewerJournal;
use App\Models\ReviewerProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use MongoDB\BSON\ObjectId;

trait HandlesJournalForm
{
    use HandlesJournalVerification;

    // Editorial Board state
    public array $editorial_members = [];

    public string $memberSearchQuery = '';

    public array $memberSearchResults = [];

    public bool $showMemberDropdown = false;

    public string $selectedRole = '';

    public function updatedCountry(): void
    {
        $this->state = '';
        $this->city = '';
        $this->resetErrorBag(['country', 'state', 'city']);
    }

    public function updatedState(): void
    {
        $this->city = '';
        $this->resetErrorBag(['state', 'city']);
    }

    public function updatedCity(): void
    {
        $this->resetErrorBag(['city']);
    }

    public function updatedEIssn(): void
    {
        $this->resetOnlineVerification();
        if (! empty(trim($this->e_issn))) {
            $this->resetErrorBag(['e_issn']);
        }
    }

    public function updatedPIssn(): void
    {
        $this->resetOnlineVerification();
        if (! empty(trim($this->p_issn))) {
            $this->resetErrorBag(['p_issn', 'e_issn']);
        }
    }

    public function updatedMemberSearchQuery(): void
    {
        $query = trim($this->memberSearchQuery);
        if (mb_strlen($query) < 2) {
            $this->memberSearchResults = [];
            $this->showMemberDropdown = false;

            return;
        }

        $this->searchEditorialMembers($query);
    }

    public function searchEditorialMembers(string $query): void
    {
        $queryParts = array_values(array_filter(explode(' ', trim($query))));
        if (empty($queryParts)) {
            $this->memberSearchResults = [];
            $this->showMemberDropdown = false;

            return;
        }

        // Exclude users already added
        $alreadyAddedIds = array_values(array_filter(array_map(fn ($m) => (string) ($m['user_id'] ?? ''), $this->editorial_members)));

        $usersQuery = User::where(function ($q) {
            $q->where('status', '!=', 3)
                ->where('status', '!=', '3');
        });

        if (! empty($alreadyAddedIds)) {
            $usersQuery->whereNotIn('_id', $alreadyAddedIds);
        }

        $usersQuery->where(function ($q) use ($queryParts) {
            foreach ($queryParts as $part) {
                $clean = preg_quote($part, '/');
                $q->where(function ($sub) use ($clean) {
                    $sub->where('first_name', 'like', "%{$clean}%")
                        ->orWhere('last_name', 'like', "%{$clean}%")
                        ->orWhere('fullname', 'like', "%{$clean}%")
                        ->orWhere('email', 'like', "%{$clean}%");
                });
            }
        });

        $matchedUsers = $usersQuery
            ->select(['_id', 'first_name', 'last_name', 'fullname', 'name', 'email', 'photo', 'profile_image', 'affiliation'])
            ->limit(15)
            ->get();

        if ($matchedUsers->isEmpty()) {
            $this->memberSearchResults = [];
            $this->showMemberDropdown = false;

            return;
        }

        // Rank by active reviewer profiles
        $userIds = $matchedUsers->pluck('_id')->map(fn ($id) => (string) $id)->toArray();
        $userLookupIds = [];
        foreach ($userIds as $uId) {
            $userLookupIds[] = (string) $uId;
            try {
                $userLookupIds[] = new ObjectId((string) $uId);
            } catch (\Throwable) {
            }
        }

        $activeProfiles = ReviewerProfile::whereIn('user_id', $userLookupIds)
            ->where(function ($q) {
                $q->where('status', '!=', 3)->where('status', '!=', '3');
            })
            ->select(['user_id', 'experience', 'experience_id', 'status'])
            ->get();

        $profileUserMap = [];
        $profileExpMap = [];
        $expIds = [];
        foreach ($activeProfiles as $p) {
            $pUid = (string) $p->user_id;
            $profileUserMap[$pUid] = true;
            $exp = $p->experience ?? null;
            if (is_string($exp)) {
                $decoded = json_decode($exp, true);
                if (is_array($decoded) && ! empty($decoded)) {
                    $firstExp = is_array($decoded[0]) ? ($decoded[0]['_id'] ?? $decoded[0]['organization_id'] ?? null) : $decoded[0];
                    if ($firstExp) {
                        $expIds[] = (string) $firstExp;
                        $profileExpMap[$pUid] = (string) $firstExp;
                    }
                }
            } elseif (is_array($exp) && ! empty($exp)) {
                $firstExp = is_array($exp[0]) ? ($exp[0]['_id'] ?? $exp[0]['organization_id'] ?? null) : $exp[0];
                if ($firstExp) {
                    $expIds[] = (string) $firstExp;
                    $profileExpMap[$pUid] = (string) $firstExp;
                }
            } elseif (! empty($p->experience_id)) {
                $expIds[] = (string) $p->experience_id;
                $profileExpMap[$pUid] = (string) $p->experience_id;
            }
        }

        $sortedUsers = $matchedUsers->sortByDesc(function ($u) use ($profileUserMap) {
            return isset($profileUserMap[(string) $u->_id]) ? 1 : 0;
        })->values();

        $experiences = ! empty($expIds)
            ? Experience::whereIn('_id', array_values(array_unique($expIds)))
                ->select(['_id', 'organization_id'])
                ->get()
                ->keyBy(fn ($e) => (string) $e->_id)
            : collect();

        $orgIds = [];
        foreach ($experiences as $exp) {
            if (! empty($exp->organization_id)) {
                $orgIds[] = (string) $exp->organization_id;
            }
        }
        $organizations = ! empty($orgIds)
            ? Organization::whereIn('_id', array_values(array_unique($orgIds)))
                ->select(['_id', 'organization_name', 'name'])
                ->get()
                ->keyBy(fn ($o) => (string) $o->_id)
            : collect();

        $results = [];
        foreach ($sortedUsers as $user) {
            $uId = (string) $user->_id;
            $affiliationName = null;
            if (isset($profileExpMap[$uId]) && isset($experiences[$profileExpMap[$uId]])) {
                $exp = $experiences[$profileExpMap[$uId]];
                if (! empty($exp->organization_id) && isset($organizations[(string) $exp->organization_id])) {
                    $affiliationName = $organizations[(string) $exp->organization_id]->organization_name ?? null;
                }
            }
            if (! $affiliationName && ! empty($user->affiliation)) {
                $affiliationName = $user->affiliation;
            }

            $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            if ($fullName === '') {
                $fullName = $user->name ?? $user->fullname ?? 'Member';
            }

            $photo = ! empty($user->photo) ? $user->photo : (! empty($user->profile_image) ? $user->profile_image : 'assets/images/vector.png');

            $results[] = [
                'id' => $uId,
                'name' => $fullName,
                'photo' => $photo,
                'affiliation' => $affiliationName ?? '',
            ];
        }

        $this->memberSearchResults = $results;
        $this->showMemberDropdown = true;
    }

    public function selectEditorialMember(string $userId, string $name, string $photo): void
    {
        foreach ($this->editorial_members as $m) {
            if (! empty($m['user_id']) && (string) $m['user_id'] === $userId) {
                $this->memberSearchQuery = '';
                $this->memberSearchResults = [];
                $this->showMemberDropdown = false;

                return;
            }
        }

        $roleToAssign = ! empty($this->selectedRole) ? $this->selectedRole : 'Editor-in-Chief';
        $roles = JournalRole::getCachedActive();
        if (empty($this->selectedRole) && ! empty($roles) && isset($roles[0]['id'])) {
            $roleToAssign = $roles[0]['id'];
        }

        $this->editorial_members[] = [
            'user_id' => $userId,
            'name' => $name,
            'role_id' => $roleToAssign,
            'photo' => $photo ?: 'assets/images/vector.png',
        ];

        $this->memberSearchQuery = '';
        $this->memberSearchResults = [];
        $this->showMemberDropdown = false;
    }

    public function addEditorialMember(?string $name = null): void
    {
        $nameToAdd = trim($name ?? $this->memberSearchQuery);
        if ($nameToAdd !== '') {
            $roleToAssign = ! empty($this->selectedRole) ? $this->selectedRole : 'Editor-in-Chief';
            $roles = JournalRole::getCachedActive();
            if (empty($this->selectedRole) && ! empty($roles) && isset($roles[0]['id'])) {
                $roleToAssign = $roles[0]['id'];
            }

            $this->editorial_members[] = [
                'user_id' => null,
                'name' => $nameToAdd,
                'role_id' => $roleToAssign,
                'photo' => 'assets/images/vector.png',
            ];

            $this->memberSearchQuery = '';
            $this->memberSearchResults = [];
            $this->showMemberDropdown = false;
        }
    }

    public function removeEditorialMember(int $index): void
    {
        unset($this->editorial_members[$index]);
        $this->editorial_members = array_values($this->editorial_members);
    }

    /**
     * Format editorial members for database saving.
     * Only stores user_id and role_id (name and photo are omitted).
     */
    public function formatRegisteredCoAuthors(): array
    {
        $validMembers = [];
        foreach ($this->editorial_members as $m) {
            $uid = ! empty($m['user_id']) ? (string) $m['user_id'] : null;
            if ($uid) {
                $validMembers[] = [
                    'user_id' => $uid,
                    'role_id' => (string) ($m['role_id'] ?? $m['role'] ?? 'Editor-in-Chief'),
                ];
            } elseif (! empty(trim($m['name'] ?? ''))) {
                $validMembers[] = [
                    'name' => trim($m['name']),
                    'role_id' => (string) ($m['role_id'] ?? $m['role'] ?? 'Editor-in-Chief'),
                ];
            }
        }

        return $validMembers;
    }

    /**
     * Resolve raw stored registered_co_author data by fetching fresh name and photo by user_id from User model.
     */
    public function resolveEditorialMembersWithUserData(mixed $rawMembers): array
    {
        if (empty($rawMembers)) {
            return [];
        }

        $formatted = [];
        $rawList = is_array($rawMembers) ? $rawMembers : [$rawMembers];

        foreach ($rawList as $m) {
            if (is_array($m)) {
                $uid = (string) ($m['user_id'] ?? $m['id'] ?? '');
                $formatted[] = [
                    'user_id' => ! empty($uid) ? $uid : null,
                    'name' => (string) ($m['name'] ?? ''),
                    'role_id' => (string) ($m['role_id'] ?? $m['role'] ?? 'Editor-in-Chief'),
                    'photo' => (string) ($m['photo'] ?? 'assets/images/vector.png'),
                ];
            } elseif (is_string($m)) {
                $decoded = json_decode($m, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $sub) {
                        $uid = (string) ($sub['user_id'] ?? $sub['id'] ?? '');
                        $formatted[] = [
                            'user_id' => ! empty($uid) ? $uid : null,
                            'name' => (string) ($sub['name'] ?? ''),
                            'role_id' => (string) ($sub['role_id'] ?? $sub['role'] ?? 'Editor-in-Chief'),
                            'photo' => (string) ($sub['photo'] ?? 'assets/images/vector.png'),
                        ];
                    }
                } elseif (trim($m) !== '') {
                    $formatted[] = [
                        'user_id' => trim($m),
                        'name' => '',
                        'role_id' => 'Editor-in-Chief',
                        'photo' => 'assets/images/vector.png',
                    ];
                }
            }
        }

        // Dynamically resolve latest user name and photo from User model
        $uIds = array_values(array_filter(array_map(fn ($f) => $f['user_id'] ?? null, $formatted)));
        if (! empty($uIds)) {
            $userLookupIds = [];
            foreach ($uIds as $id) {
                $userLookupIds[] = (string) $id;
                try {
                    $userLookupIds[] = new ObjectId((string) $id);
                } catch (\Throwable) {
                }
            }

            $users = User::whereIn('_id', $userLookupIds)
                ->select(['_id', 'first_name', 'last_name', 'fullname', 'name', 'photo', 'profile_image'])
                ->get()
                ->keyBy(fn ($u) => (string) $u->_id);
            foreach ($formatted as &$item) {
                $uid = (string) ($item['user_id'] ?? '');
                if (! empty($uid) && isset($users[$uid])) {
                    $user = $users[$uid];
                    $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                    if ($fullName === '') {
                        $fullName = $user->name ?? $user->fullname ?? 'Member';
                    }
                    $item['name'] = $fullName;
                    $item['photo'] = ! empty($user->photo) ? $user->photo : (! empty($user->profile_image) ? $user->profile_image : 'assets/images/vector.png');
                }
            }
            unset($item);
        }

        return $formatted;
    }

    public function getStatesList(): array
    {
        return State::getCachedByCountry($this->country);
    }

    public function getCitiesList(): array
    {
        return City::getCachedByState($this->state);
    }

    public function availableAgencies(): array
    {
        return IndexingAgency::getCachedActive();
    }

    public function searchPublishers(string $query = ''): array
    {
        return User::searchPublishers($query, 20);
    }

    public function searchJournalTitles(string $query = ''): array
    {
        return ReviewerJournal::searchTitles($query, 15);
    }

    /**
     * Check if an ISSN is already registered by another journal.
     * Excludes the current journal if in edit mode.
     */
    public function checkIssn(string $issn): ?array
    {
        $formatted = ReviewerJournal::formatIssn($issn);
        if (! ReviewerJournal::isValidIssn($formatted)) {
            return null;
        }

        $currentId = (string) (
            $this->journal_id ?? (
                isset($this->journal) && is_object($this->journal)
                    ? ($this->journal->_id ?? (method_exists($this->journal, 'getKey') ? $this->journal->getKey() : ''))
                    : ''
            )
        );

        $query = ReviewerJournal::where(function ($q) use ($formatted) {
            $q->where('e_issn', $formatted)
                ->orWhere('p_issn', $formatted);
        })
            ->where(function ($q) {
                $q->where('status', '!=', 3)->where('status', '!=', '3');
            });

        if ($currentId !== '') {
            try {
                $oid = new ObjectId($currentId);
                $query->where('_id', '!=', $oid)
                    ->where('_id', '!=', $currentId);
            } catch (\Throwable) {
                $query->where('_id', '!=', $currentId);
            }
        }

        $found = $query->first();

        // Safety fallback if driver did not filter out current journal
        if ($found && $currentId !== '' && (string) $found->_id === $currentId) {
            $found = $query->get()->first(fn ($j) => (string) $j->_id !== $currentId);
        }

        if (! $found) {
            return null;
        }

        $statusInt = (int) ($found->status ?? 1);

        return [
            'id' => (string) $found->_id,
            'journal_title' => (string) ($found->journal_title ?? ''),
            'journal_short_name' => (string) ($found->journal_short_name ?? ''),
            'user_id' => (string) ($found->user_id ?? ''),
            'organization_name' => (string) ($found->organization_name ?? ''),
            'journal_webiste_url' => (string) ($found->journal_webiste_url ?? ''),
            'first_publication_year' => $found->first_publication_year ? (int) $found->first_publication_year : null,
            'e_issn' => (string) ($found->e_issn ?? ''),
            'p_issn' => (string) ($found->p_issn ?? ''),
            'issn_assignee_country' => (string) ($found->issn_assignee_country ?? ''),
            'journal_impact_factor' => (string) ($found->journal_impact_factor ?? ''),
            'impact_factor_assignee_agency' => (string) ($found->impact_factor_assignee_agency ?? ''),
            'issue_frequency' => (string) ($found->issue_frequency ?? ''),
            'languages' => is_array($found->languages) ? array_values(array_filter($found->languages)) : (! empty($found->languages) ? [(string) $found->languages] : []),
            'journal_subjects' => is_array($found->journal_subjects) ? array_values(array_filter($found->journal_subjects)) : (! empty($found->journal_subjects) ? [(string) $found->journal_subjects] : []),
            'journal_description' => (string) ($found->journal_description ?? ''),
            'status' => $statusInt,
            'status_label' => $statusInt === 1 ? 'Active' : 'Inactive',
        ];
    }

    /**
     * Populate form fields from selected existing journal data.
     */
    public function selectExistingJournal(array $journalData): void
    {
        if (! empty($journalData['journal_title'])) {
            $this->journal_title = (string) $journalData['journal_title'];
        }
        if (! empty($journalData['journal_short_name'])) {
            $this->journal_short_name = (string) $journalData['journal_short_name'];
        }
        if (! empty($journalData['user_id']) && empty($this->user_id)) {
            $this->user_id = (string) $journalData['user_id'];
        }
        if (! empty($journalData['organization_name']) && empty($this->organization_name)) {
            $this->organization_name = (string) $journalData['organization_name'];
        }
        if (! empty($journalData['e_issn'])) {
            $this->e_issn = (string) $journalData['e_issn'];
        }
        if (! empty($journalData['p_issn'])) {
            $this->p_issn = (string) $journalData['p_issn'];
        }
        if (! empty($journalData['journal_webiste_url']) && empty($this->journal_webiste_url)) {
            $this->journal_webiste_url = (string) $journalData['journal_webiste_url'];
        }
        if (! empty($journalData['first_publication_year']) && empty($this->first_publication_year)) {
            $this->first_publication_year = (int) $journalData['first_publication_year'];
        }
        if (! empty($journalData['issn_assignee_country']) && empty($this->issn_assignee_country)) {
            $this->issn_assignee_country = (string) $journalData['issn_assignee_country'];
        }
        if (! empty($journalData['journal_impact_factor']) && empty($this->journal_impact_factor)) {
            $this->journal_impact_factor = (string) $journalData['journal_impact_factor'];
        }
        if (! empty($journalData['impact_factor_assignee_agency']) && empty($this->impact_factor_assignee_agency)) {
            $this->impact_factor_assignee_agency = (string) $journalData['impact_factor_assignee_agency'];
        }
        if (! empty($journalData['issue_frequency']) && empty($this->issue_frequency)) {
            $this->issue_frequency = (string) $journalData['issue_frequency'];
        }
        if (! empty($journalData['languages'])) {
            $this->languages = (array) $journalData['languages'];
        }
        if (! empty($journalData['journal_subjects'])) {
            $this->journal_subjects = (array) $journalData['journal_subjects'];
        }
        if (! empty($journalData['journal_description']) && empty($this->journal_description)) {
            $this->journal_description = (string) $journalData['journal_description'];
        }
        if (isset($journalData['followpeer'])) {
            $this->followpeer = (string) $journalData['followpeer'];
        }
        $this->EditorialReview = ! empty($journalData['EditorialReview']);
        $this->PeerReview = ! empty($journalData['PeerReview']);
        $this->AnonymousPeerReview = ! empty($journalData['AnonymousPeerReview']);
        $this->DoubleAnonymousPeerReview = ! empty($journalData['DoubleAnonymousPeerReview']);
        $this->PostPublicationPeerReview = ! empty($journalData['PostPublicationPeerReview']);
        $this->OpenPeerReview = ! empty($journalData['OpenPeerReview']);
        $this->other = ! empty($journalData['other']);
        $this->otherPeer = (string) ($journalData['otherPeer'] ?? '');
        if (isset($journalData['feesAssociatedWithPublishing'])) {
            $this->feesAssociatedWithPublishing = (string) $journalData['feesAssociatedWithPublishing'];
        }
    }

    /**
     * Shared custom validation error messages.
     */
    public function journalValidationMessages(): array
    {
        return [
            'organization_name.required' => 'Publisher name is required.',
            'journal_title.required' => 'Journal full name is required.',
            'journal_webiste_url.required' => 'The journal website url field is required.',
            'journal_webiste_url.url' => 'Please enter a valid website URL.',
            'first_publication_year.required' => 'Year of first publication is required.',
            'journal_description.required' => 'About Journal description is required.',
            'contact_name.required' => 'Editor in Chief or Contact Person Name is required.',
            'contact_email.required' => 'Editor in Chief Email id (Journal) is required.',
            'contact_email.email' => 'Please enter a valid email address.',
            'mobile.required' => 'Mobile No (Chief Editor / Manager) is required.',
            'address.required' => 'Address is required.',
            'country.required' => 'Country is required.',
            'zipcode.required' => 'Postal/Zip Code is required.',
        ];
    }

    /**
     * Shared cross-field validation logic (ISSN requirement, P-ISSN != E-ISSN, conditional state/city).
     */
    public function validateJournalCrossFields(Validator $validator): void
    {
        $hasE = ! empty(trim($this->e_issn));
        $hasP = ! empty(trim($this->p_issn));

        if (! $hasE && ! $hasP) {
            $validator->errors()->add('e_issn', 'At least one of e-ISSN or p-ISSN is required.');
        }

        if ($hasE && $hasP) {
            $normE = ReviewerJournal::formatIssn($this->e_issn);
            $normP = ReviewerJournal::formatIssn($this->p_issn);
            if ($normE !== '' && $normE === $normP) {
                $validator->errors()->add('p_issn', 'p-ISSN cannot be identical to e-ISSN.');
            }
        }

        // Strict registry verification if an ISSN is provided
        if ($hasE || $hasP) {
            try {
                $this->performStrictVerificationOnSave($this->e_issn, $this->p_issn, $this->journal_title ?? null);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $msg) {
                        $validator->errors()->add($field, $msg);
                    }
                }
            }
        }

        if (! empty($this->country)) {
            $availableStates = $this->getStatesList();
            if (! empty($availableStates) && empty($this->state)) {
                $validator->errors()->add('state', 'State is required for the selected country.');
            }

            if (! empty($this->state)) {
                $availableCities = $this->getCitiesList();
                if (! empty($availableCities) && empty($this->city)) {
                    $validator->errors()->add('city', 'City is required for the selected state.');
                }
            }
        }
    }
}
