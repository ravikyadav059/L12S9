<?php

namespace App\Livewire\Concerns;

use App\Helpers\IssnHelper;
use App\Models\JournalRole;
use App\Models\ReviewerJournal;
use App\Models\ReviewerProfile;
use App\Models\RoleInResearchJournals;
use App\Models\User;
use App\Rules\ValidIssn;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MongoDB\BSON\Regex;

trait HandlesJournalRoleSection
{
    use HandlesAddJournalModal;
    use HandlesJournalVerification;

    /**
     * Form-aligned properties used by HandlesJournalVerification diff & auto-fill.
     */
    public string $journal_title = '';

    public string $journal_short_name = '';

    public string $e_issn = '';

    public string $p_issn = '';

    public string $organization_name = '';

    public string $journal_webiste_url = '';

    public ?string $selected_journal_id = null;

    public ?string $autoFillSuccessMessage = null;

    /**
     * Reset online verification status whenever an ISSN field changes.
     */
    public function syncFormSnapshotForVerification(array $formData = []): void
    {
        $this->selected_journal_id = ! empty($formData['journal_id']) ? (string) $formData['journal_id'] : null;
        $this->journal_title = trim((string) ($formData['journal_title'] ?? ''));
        $this->journal_short_name = trim((string) ($formData['journal_short_name'] ?? ''));
        $this->e_issn = trim((string) ($formData['e_issn'] ?? ''));
        $this->p_issn = trim((string) ($formData['p_issn'] ?? ''));
        $this->organization_name = trim((string) ($formData['organization_name'] ?? ''));
        $this->journal_webiste_url = trim((string) ($formData['journal_webiste_url'] ?? ''));
    }

    /**
     * Verify ISSN against external registries using current form snapshot.
     *
     * @return array<string, mixed>
     */
    public function verifyJournalFromForm(array $formData = []): array
    {
        $this->syncFormSnapshotForVerification($formData);
        $this->resetErrorBag(['e_issn', 'p_issn']);

        $preferred = ! empty($this->e_issn) ? $this->e_issn : $this->p_issn;
        $this->verifyJournalOnline($preferred);

        return [
            'isVerified' => $this->isJournalVerifiedOnline,
            'verifiedSourceLabel' => $this->verifiedSourceLabel,
            'verificationMessage' => $this->verificationMessage,
            'verifiedJournalData' => $this->verifiedJournalData,
            'errors' => $this->getErrorBag()->toArray(),
        ];
    }

    /**
     * Open Auto-Fill Diff Confirmation modal synced with current Alpine form values.
     */
    public function openAutoFillModalWithForm(array $formData = []): void
    {
        $this->syncFormSnapshotForVerification($formData);
        $this->openAutoFillModal();

        if (! empty($this->verifiedJournalData)) {
            $this->checkExistingJournalInDatabase($this->verifiedJournalData);
        }
    }

    /**
     * Select a journal candidate from official registry search results and prepare diff modal.
     */
    public function selectRegistryCandidateForRole(int $index, array $formData = []): void
    {
        $this->syncFormSnapshotForVerification($formData);
        $this->selectRegistryCandidate($index);

        if (! empty($this->verifiedJournalData)) {
            $this->checkExistingJournalInDatabase($this->verifiedJournalData);
        }
    }

    /**
     * Apply verified registry values and notify Alpine form fields.
     */
    public function confirmAutoFillForRole(): void
    {
        $this->confirmAutoFill();

        // Check if matching existing journal exists in DB to link journal_id
        $eClean = IssnHelper::format($this->e_issn);
        $pClean = IssnHelper::format($this->p_issn);
        $existingId = null;

        if ($eClean !== '' || $pClean !== '') {
            $found = ReviewerJournal::where(function ($q) use ($eClean, $pClean) {
                if ($eClean !== '') {
                    $q->orWhere('e_issn', $eClean)->orWhere('p_issn', $eClean);
                }
                if ($pClean !== '') {
                    $q->orWhere('e_issn', $pClean)->orWhere('p_issn', $pClean);
                }
            })
                ->where('status', '!=', 3)
                ->where('status', '!=', '3')
                ->first(['_id']);

            if ($found) {
                $existingId = (string) $found->_id;
            }
        }

        $this->selected_journal_id = $existingId;

        $this->dispatch('journal-role-autofilled', [
            'journal_id' => $existingId,
            'journal_title' => $this->journal_title,
            'journal_short_name' => $this->journal_short_name,
            'e_issn' => $this->e_issn,
            'p_issn' => $this->p_issn,
            'organization_name' => $this->organization_name,
            'journal_webiste_url' => $this->journal_webiste_url,
            'is_verified' => true,
            'verified_source' => $this->verifiedSourceLabel,
        ]);
    }

    /**
     * Use the existing database journal detected inside the Auto-Fill Diff Modal.
     */
    public function useExistingDbJournalFromModal(): void
    {
        if (empty($this->existingDbJournalInfo['id'])) {
            $this->closeAutoFillModal();

            return;
        }

        $found = ReviewerJournal::find($this->existingDbJournalInfo['id']);
        if ($found) {
            $this->dispatch('journal-role-autofilled', [
                'journal_id' => (string) $found->_id,
                'journal_title' => (string) ($found->journal_title ?? ''),
                'journal_short_name' => (string) ($found->journal_short_name ?? ''),
                'e_issn' => (string) ($found->e_issn ?? ''),
                'p_issn' => (string) ($found->p_issn ?? ''),
                'organization_name' => (string) ($found->organization_name ?? ''),
                'journal_webiste_url' => (string) ($found->journal_webiste_url ?? ''),
                'is_verified' => (bool) ($found->is_verified ?? false),
                'verified_source' => (string) ($found->verification_source ?? 'Database'),
            ]);
        }

        $this->closeAutoFillModal();
    }

    /**
     * Hook invoked automatically by HandlesAddJournalModal after creating a new journal.
     */
    public function afterJournalSaved(ReviewerJournal $journal): void
    {
        $this->selected_journal_id = (string) $journal->_id;
        $this->journal_title = (string) ($journal->journal_title ?? '');
        $this->e_issn = (string) ($journal->e_issn ?? '');
        $this->p_issn = (string) ($journal->p_issn ?? '');
        $this->isJournalVerifiedOnline = true;
        $this->verifiedSourceLabel = ucfirst((string) ($journal->verification_source ?? 'Official Registry'));
        $this->verificationMessage = 'Journal verified and added successfully.';
        $this->autoFillSuccessMessage = null;

        $this->dispatch('journal-role-autofilled', [
            'journal_id' => (string) $journal->_id,
            'journal_title' => (string) ($journal->journal_title ?? ''),
            'journal_short_name' => (string) ($journal->journal_short_name ?? ''),
            'e_issn' => (string) ($journal->e_issn ?? ''),
            'p_issn' => (string) ($journal->p_issn ?? ''),
            'organization_name' => (string) ($journal->organization_name ?? ''),
            'journal_webiste_url' => (string) ($journal->journal_webiste_url ?? ''),
            'is_verified' => true,
            'verified_source' => $this->verifiedSourceLabel,
        ]);
    }

    /**
     * Check if an ISSN is already registered in ReviewerJournal database.
     *
     * @return array<string, mixed>|null
     */
    public function checkIssn(string $issn): ?array
    {
        $formatted = ReviewerJournal::formatIssn($issn);
        if (! ReviewerJournal::isValidIssn($formatted)) {
            return null;
        }

        $found = ReviewerJournal::where(function ($q) use ($formatted) {
            $q->where('e_issn', $formatted)
                ->orWhere('p_issn', $formatted);
        })
            ->where('status', '!=', 3)
            ->where('status', '!=', '3')
            ->first();

        if (! $found) {
            return null;
        }

        $statusInt = (int) ($found->status ?? 1);

        return [
            'id' => (string) $found->_id,
            'journal_title' => (string) ($found->journal_title ?? ''),
            'journal_short_name' => (string) ($found->journal_short_name ?? ''),
            'organization_name' => (string) ($found->organization_name ?? ''),
            'journal_webiste_url' => (string) ($found->journal_webiste_url ?? ''),
            'e_issn' => (string) ($found->e_issn ?? ''),
            'p_issn' => (string) ($found->p_issn ?? ''),
            'is_verified' => (bool) ($found->is_verified ?? false),
            'status' => $statusInt,
            'status_label' => $statusInt === 1 ? 'Active' : 'Inactive',
        ];
    }

    /**
     * Validate, verify, and persist a Role in Research Journals record.
     * Enforces the same field validation & registry checks as Admin Journal Create.
     *
     * @return array{ok: bool, errors?: array<string, list<string>>}
     */
    protected function persistJournalRole(User $targetUser, array $payload, bool $isAdmin = false): array
    {
        $this->resetErrorBag();

        $id = ! empty($payload['id']) ? (string) $payload['id'] : null;
        $roleVal = trim((string) ($payload['role'] ?? ''));
        $journalIdInput = ! empty($payload['journal_id']) ? trim((string) $payload['journal_id']) : null;
        $journalTitle = trim((string) ($payload['journal_title'] ?? ''));
        $journalShortName = trim((string) ($payload['journal_short_name'] ?? ''));
        $eIssnRaw = trim((string) ($payload['e_issn'] ?? ''));
        $pIssnRaw = trim((string) ($payload['p_issn'] ?? ''));
        $eIssn = $eIssnRaw !== '' ? IssnHelper::format($eIssnRaw) : '';
        $pIssn = $pIssnRaw !== '' ? IssnHelper::format($pIssnRaw) : '';
        $publisherName = trim((string) ($payload['organization_name'] ?? ''));
        $journalUrl = trim((string) ($payload['journal_webiste_url'] ?? ''));

        $this->syncFormSnapshotForVerification([
            'journal_id' => $journalIdInput,
            'journal_title' => $journalTitle,
            'journal_short_name' => $journalShortName,
            'e_issn' => $eIssn,
            'p_issn' => $pIssn,
            'organization_name' => $publisherName,
            'journal_webiste_url' => $journalUrl,
        ]);

        // 1. Standard field-level validation (aligned with admin/journals/create)
        $validator = Validator::make([
            'role' => $roleVal,
            'journal_title' => $journalTitle,
            'journal_short_name' => $journalShortName,
            'e_issn' => $eIssn !== '' ? $eIssn : null,
            'p_issn' => $pIssn !== '' ? $pIssn : null,
            'organization_name' => $publisherName !== '' ? $publisherName : null,
            'journal_webiste_url' => $journalUrl !== '' ? $journalUrl : null,
        ], [
            'role' => ['required', 'string', 'max:255'],
            'journal_title' => ['required', 'string', 'min:2', 'max:500'],
            'journal_short_name' => ['nullable', 'string', 'max:255'],
            'e_issn' => ['nullable', 'string', 'max:50', new ValidIssn],
            'p_issn' => ['nullable', 'string', 'max:50', new ValidIssn],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'journal_webiste_url' => ['nullable', 'url', 'max:500'],
        ], [
            'role.required' => 'Role is required.',
            'journal_title.required' => 'Journal full name is required.',
            'journal_title.min' => 'Journal name must be at least 2 characters.',
            'journal_webiste_url.url' => 'Please enter a valid website URL.',
        ]);

        // 2. Cross-field validation (same as HandlesJournalForm::validateJournalCrossFields)
        $validator->after(function ($v) use ($eIssn, $pIssn, $journalTitle, $journalIdInput): void {
            $hasE = $eIssn !== '';
            $hasP = $pIssn !== '';

            if (! $hasE && ! $hasP) {
                $v->errors()->add('e_issn', 'At least one of e-ISSN or p-ISSN is required.');

                return;
            }

            if ($hasE && $hasP && $eIssn === $pIssn) {
                $v->errors()->add('p_issn', 'p-ISSN cannot be identical to e-ISSN.');

                return;
            }

            // Skip redundant external HTTP call if format/checksum has already failed
            if ($v->errors()->has('e_issn') || $v->errors()->has('p_issn')) {
                return;
            }

            // If user selected a verified DB journal with matching ISSNs, allow without extra network check
            if ($journalIdInput) {
                $existingJournal = ReviewerJournal::find($journalIdInput);
                if ($existingJournal && ! empty($existingJournal->is_verified)) {
                    $dbE = IssnHelper::format($existingJournal->e_issn);
                    $dbP = IssnHelper::format($existingJournal->p_issn);
                    if (($eIssn === $dbE || $eIssn === '') && ($pIssn === $dbP || $pIssn === '')) {
                        return;
                    }
                }
            }

            try {
                $this->performStrictVerificationOnSave($eIssn, $pIssn, $journalTitle);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $msg) {
                        $v->errors()->add($field, $msg);
                    }
                }
            }
        });

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return [
                'ok' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        try {
            // 3. Resolve Role (prefer JournalRole ID)
            $resolvedRoleId = $this->resolveJournalRoleId($roleVal);

            // 4. Resolve or create ReviewerJournal record
            $journalModel = $this->resolveOrCreateJournal(
                journalId: $journalIdInput,
                title: $journalTitle,
                shortName: $journalShortName,
                eIssn: $eIssn,
                pIssn: $pIssn,
                publisherName: $publisherName,
                websiteUrl: $journalUrl
            );

            // 5. Save or Update RoleInResearchJournals record
            if ($id) {
                $record = RoleInResearchJournals::find($id);
                if (! $record) {
                    $this->dispatch('toast', message: 'Journal role record not found.', type: 'danger');

                    return ['ok' => false, 'errors' => ['general' => ['Journal role record not found.']]];
                }
                if (! $isAdmin && ! empty($record->user_id) && (string) $record->user_id !== (string) $targetUser->_id) {
                    abort(403, 'Unauthorized action.');
                }
            } else {
                $record = new RoleInResearchJournals;
                $record->user_id = (string) $targetUser->_id;
                $record->status = $isAdmin ? (isset($payload['status']) ? (int) $payload['status'] : 1) : 1;
            }

            $record->role = $resolvedRoleId;
            $record->journal_title = (string) $journalModel->_id;
            $record->journal_short_name = $journalShortName !== '' ? $journalShortName : null;
            $record->e_issn = $eIssn !== '' ? $eIssn : null;
            $record->p_issn = $pIssn !== '' ? $pIssn : null;
            $record->organization_name = $publisherName !== '' ? $publisherName : null;
            $record->journal_webiste_url = $journalUrl !== '' ? $journalUrl : null;
            $record->user_id = (string) $targetUser->_id;

            if ($isAdmin && isset($payload['status'])) {
                $record->status = (int) $payload['status'] === 1 ? 1 : 0;
            }

            $record->save();

            // 6. Atomic ReviewerProfile synchronization on create
            if (! $id) {
                $reviewerProfile = ReviewerProfile::where('user_id', (string) $targetUser->_id)->first();
                if ($reviewerProfile) {
                    ReviewerProfile::where('user_id', (string) $targetUser->_id)
                        ->push('RoleInResearchJournal', (string) $record->_id, true);
                } else {
                    ReviewerProfile::create([
                        'user_id' => (string) $targetUser->_id,
                        'RoleInResearchJournal' => [(string) $record->_id],
                    ]);
                }
            }

            RoleInResearchJournals::forgetUserCache((string) $targetUser->_id);
            $this->resetOnlineVerification();

            $this->dispatch('toast', message: $id ? 'Research journal role updated successfully.' : 'Research journal role added successfully.', type: 'success');

            return ['ok' => true];
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'An error occurred while saving the research journal role.', type: 'danger');

            return ['ok' => false, 'errors' => ['general' => ['An unexpected error occurred.']]];
        }
    }

    /**
     * Resolve role input to JournalRole ObjectId string when matched.
     */
    protected function resolveJournalRoleId(string $roleVal): string
    {
        $roles = JournalRole::getCachedActive();
        foreach ($roles as $r) {
            if ((string) $r['id'] === $roleVal || strcasecmp((string) $r['name'], $roleVal) === 0) {
                return (string) $r['id'];
            }
        }

        return $roleVal;
    }

    /**
     * Find an existing ReviewerJournal by ID, ISSN, or exact title; or create a verified entry.
     */
    protected function resolveOrCreateJournal(
        ?string $journalId,
        string $title,
        string $shortName,
        string $eIssn,
        string $pIssn,
        string $publisherName,
        string $websiteUrl
    ): ReviewerJournal {
        if ($journalId) {
            $byId = ReviewerJournal::find($journalId);
            if ($byId && strcasecmp(trim((string) $byId->journal_title), $title) === 0) {
                return $byId;
            }
        }

        // Lookup by ISSN
        if ($eIssn !== '' || $pIssn !== '') {
            $byIssn = ReviewerJournal::where(function ($q) use ($eIssn, $pIssn) {
                if ($eIssn !== '') {
                    $q->orWhere('e_issn', $eIssn)->orWhere('p_issn', $eIssn);
                }
                if ($pIssn !== '') {
                    $q->orWhere('e_issn', $pIssn)->orWhere('p_issn', $pIssn);
                }
            })
                ->where('status', '!=', 3)
                ->where('status', '!=', '3')
                ->first();

            if ($byIssn) {
                return $byIssn;
            }
        }

        // Lookup by exact title (case-insensitive)
        $byTitle = ReviewerJournal::where('journal_title', 'regex', new Regex('^'.preg_quote($title, '/').'$', 'i'))
            ->where('status', '!=', 3)
            ->where('status', '!=', '3')
            ->first();

        if ($byTitle) {
            return $byTitle;
        }

        // Create a new verified ReviewerJournal entry
        $journal = new ReviewerJournal;
        $journal->journal_title = $title;
        $journal->journal_short_name = $shortName !== '' ? $shortName : null;
        $journal->e_issn = $eIssn !== '' ? $eIssn : null;
        $journal->p_issn = $pIssn !== '' ? $pIssn : null;
        $journal->issn = $eIssn !== '' ? $eIssn : ($pIssn !== '' ? $pIssn : null);
        $journal->organization_name = $publisherName !== '' ? $publisherName : null;
        $journal->journal_webiste_url = $websiteUrl !== '' ? $websiteUrl : null;
        $journal->is_verified = true;
        $journal->verification_status = 'verified';
        $journal->verification_source = $this->verifiedJournalData['source'] ?? 'auto';
        $journal->verified_at = now();
        $journal->verification_details = $this->verifiedJournalData ?? [];
        $journal->status = 1;
        $journal->save();

        return $journal;
    }

    /**
     * Delete a research journal role record and sync ReviewerProfile.
     */
    protected function removeJournalRole(User $targetUser, string $id, bool $isAdmin = false): bool
    {
        try {
            $record = RoleInResearchJournals::find($id);
            if ($record) {
                if (! $isAdmin && ! empty($record->user_id) && (string) $record->user_id !== (string) $targetUser->_id) {
                    abort(403, 'Unauthorized action.');
                }
                $record->delete();
            }

            ReviewerProfile::where('user_id', (string) $targetUser->_id)
                ->pull('RoleInResearchJournal', $id);

            RoleInResearchJournals::forgetUserCache((string) $targetUser->_id);

            $this->dispatch('toast', message: 'Research journal role deleted successfully.', type: 'success');

            return true;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Failed to delete research journal role record.', type: 'danger');

            return false;
        }
    }
}
