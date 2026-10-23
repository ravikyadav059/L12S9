<?php

namespace App\Livewire\Concerns;

use App\Models\ReviewerJournal;
use App\Services\JournalVerificationResult;
use App\Services\JournalVerificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use MongoDB\BSON\Regex;

trait HandlesJournalVerification
{
    /**
     * Selected registry provider for verification: auto, crossref, openalex, doaj, issn_portal.
     */
    public string $verificationSource = 'auto';

    /**
     * Verification status: null (unverified/pending), true (verified), false (failed).
     */
    public ?bool $isJournalVerifiedOnline = null;

    /**
     * Name of the registry that confirmed verification (e.g. Crossref, OpenAlex).
     */
    public ?string $verifiedSourceLabel = null;

    /**
     * Status message or error description.
     */
    public ?string $verificationMessage = null;

    /**
     * Verified journal details from the external registry.
     *
     * @var array<string, mixed>|null
     */
    public ?array $verifiedJournalData = null;

    /**
     * Visibility of the Auto-Fill Diff Confirmation Modal.
     */
    public bool $showAutoFillModal = false;

    /**
     * Proposed field updates for user preview before applying auto-fill.
     *
     * @var array<int, array{field: string, label: string, current: string, new: string, is_overwrite: bool}>
     */
    public array $autoFillDiff = [];

    /**
     * Information about duplicate journal in database if found.
     *
     * @var array{id: string, title: string, status: string, e_issn: string, p_issn: string}|null
     */
    public ?array $existingDbJournalInfo = null;

    /**
     * Success banner message displayed after auto-filling.
     */
    public ?string $autoFillSuccessMessage = null;

    /**
     * Get list of verification source options for select inputs.
     *
     * @return array<string, string>
     */
    public function getVerificationProvidersProperty(): array
    {
        return JournalVerificationService::PROVIDERS;
    }

    /**
     * Reset verification status whenever user edits an ISSN field.
     */
    public function resetOnlineVerification(): void
    {
        $this->isJournalVerifiedOnline = null;
        $this->verifiedSourceLabel = null;
        $this->verificationMessage = null;
        $this->verifiedJournalData = null;
        $this->autoFillSuccessMessage = null;
    }

    /**
     * Verify the journal against external registries via the "Verify Journal" button.
     */
    public function verifyJournalOnline(?string $preferredIssn = null): void
    {
        $issnToVerify = $preferredIssn;

        if (empty($issnToVerify)) {
            $issnToVerify = ! empty($this->e_issn ?? '')
                ? $this->e_issn
                : (! empty($this->p_issn ?? '') ? $this->p_issn : ($this->newJournalEIssn ?? ($this->newJournalPIssn ?? ($this->issn ?? ''))));
        }

        $cleanIssn = trim((string) $issnToVerify);

        if (empty($cleanIssn)) {
            $errorField = property_exists($this, 'e_issn') ? 'e_issn' : (property_exists($this, 'newJournalEIssn') ? 'newJournalEIssn' : 'issn');
            $this->addError($errorField, 'Please enter an e-ISSN or p-ISSN before verifying.');
            $this->isJournalVerifiedOnline = false;
            $this->verificationMessage = 'Please enter an e-ISSN or p-ISSN to verify.';

            return;
        }

        /** @var JournalVerificationService $service */
        $service = app(JournalVerificationService::class);
        $title = $this->journal_title ?? ($this->newJournalTitle ?? null);

        $result = $service->verify($cleanIssn, $this->verificationSource ?: 'auto', $title);

        if ($result->isValid) {
            $this->isJournalVerifiedOnline = true;
            $this->verifiedSourceLabel = $result->sourceLabel;
            $this->verificationMessage = $result->message;
            $this->verifiedJournalData = $result->toArray();
            $this->autoFillSuccessMessage = null;

            // Clear any previous ISSN errors
            $this->resetErrorBag(['e_issn', 'p_issn', 'newJournalEIssn', 'newJournalPIssn', 'issn']);

            if (property_exists($this, 'isJournalVerified')) {
                $this->isJournalVerified = true;
            }
        } else {
            $this->isJournalVerifiedOnline = false;
            $this->verifiedSourceLabel = $result->sourceLabel;
            $this->verificationMessage = $result->message;
            $this->verifiedJournalData = null;
            $this->autoFillSuccessMessage = null;

            $errorField = ! empty($this->e_issn ?? '') ? 'e_issn' : (property_exists($this, 'newJournalEIssn') && ! empty($this->newJournalEIssn) ? 'newJournalEIssn' : 'p_issn');
            $this->addError($errorField, $result->message);
        }
    }

    /**
     * Compute field changes diff and check database duplicates before showing the confirmation modal.
     */
    public function openAutoFillModal(): void
    {
        if (empty($this->verifiedJournalData) || empty($this->verifiedJournalData['is_valid'])) {
            return;
        }

        $data = $this->verifiedJournalData;
        $diff = [];

        // 1. Journal Title
        if (! empty($data['title'])) {
            $currTitle = property_exists($this, 'journal_title') ? (string) $this->journal_title : (property_exists($this, 'newJournalTitle') ? (string) $this->newJournalTitle : '');
            if (trim($currTitle) !== trim($data['title'])) {
                $diff[] = [
                    'field' => 'journal_title',
                    'label' => 'Journal Full Name',
                    'current' => $currTitle ?: '(Empty)',
                    'new' => (string) $data['title'],
                    'is_overwrite' => ! empty(trim($currTitle)),
                ];
            }
        }

        // 2. Publisher / Organization Name
        if (! empty($data['publisher'])) {
            $currOrg = property_exists($this, 'organization_name') ? (string) $this->organization_name : '';
            if (trim($currOrg) !== trim($data['publisher'])) {
                $diff[] = [
                    'field' => 'organization_name',
                    'label' => 'Publisher / Organization Name',
                    'current' => $currOrg ?: '(Empty)',
                    'new' => (string) $data['publisher'],
                    'is_overwrite' => ! empty(trim($currOrg)),
                ];
            }
        }

        // 3. Website URL
        if (! empty($data['website_url'])) {
            $currUrl = property_exists($this, 'journal_webiste_url') ? (string) $this->journal_webiste_url : '';
            if (trim($currUrl) !== trim($data['website_url'])) {
                $diff[] = [
                    'field' => 'journal_webiste_url',
                    'label' => 'Journal Website URL',
                    'current' => $currUrl ?: '(Empty)',
                    'new' => (string) $data['website_url'],
                    'is_overwrite' => ! empty(trim($currUrl)),
                ];
            }
        }

        // 4. First Publication Year
        if (! empty($data['first_publication_year'])) {
            $currYear = property_exists($this, 'first_publication_year') && ! empty($this->first_publication_year) ? (string) $this->first_publication_year : '';
            if ($currYear !== (string) $data['first_publication_year']) {
                $diff[] = [
                    'field' => 'first_publication_year',
                    'label' => 'First Publication Year',
                    'current' => $currYear ?: '(Empty)',
                    'new' => (string) $data['first_publication_year'],
                    'is_overwrite' => ! empty(trim($currYear)),
                ];
            }
        }

        // 5. Country / Assignee Country
        if (! empty($data['country_id']) || ! empty($data['country_name'])) {
            $currCountry = property_exists($this, 'country') ? (string) $this->country : '';
            $newCountryName = $data['country_name'] ?? ($data['country_code'] ?? '');
            if ($currCountry !== (string) ($data['country_id'] ?? '')) {
                $diff[] = [
                    'field' => 'country',
                    'label' => 'Country',
                    'current' => $currCountry ?: '(Not Selected)',
                    'new' => $newCountryName ?: '(Detected)',
                    'is_overwrite' => ! empty(trim($currCountry)),
                ];
            }
        }

        // 6. Alternate ISSNs
        if (! empty($data['e_issn'])) {
            $currE = property_exists($this, 'e_issn') ? (string) $this->e_issn : '';
            if (trim($currE) !== trim($data['e_issn'])) {
                $diff[] = [
                    'field' => 'e_issn',
                    'label' => 'e-ISSN',
                    'current' => $currE ?: '(Empty)',
                    'new' => (string) $data['e_issn'],
                    'is_overwrite' => ! empty(trim($currE)),
                ];
            }
        }

        if (! empty($data['p_issn'])) {
            $currP = property_exists($this, 'p_issn') ? (string) $this->p_issn : '';
            if (trim($currP) !== trim($data['p_issn'])) {
                $diff[] = [
                    'field' => 'p_issn',
                    'label' => 'p-ISSN',
                    'current' => $currP ?: '(Empty)',
                    'new' => (string) $data['p_issn'],
                    'is_overwrite' => ! empty(trim($currP)),
                ];
            }
        }

        // 7. Open Access & DOI
        if (isset($data['is_oa']) && $data['is_oa'] !== null) {
            $currOa = property_exists($this, 'JournalOpenAccess') ? (string) $this->JournalOpenAccess : '1';
            $newOa = $data['is_oa'] ? '1' : '0';
            if ($currOa !== $newOa) {
                $diff[] = [
                    'field' => 'JournalOpenAccess',
                    'label' => 'Open Access License',
                    'current' => $currOa === '1' ? 'Yes' : 'No',
                    'new' => $newOa === '1' ? 'Yes' : 'No',
                    'is_overwrite' => true,
                ];
            }
        }

        $this->autoFillDiff = $diff;

        // Check if journal already exists in our MongoDB database
        $this->checkExistingJournalInDatabase($data);

        $this->showAutoFillModal = true;
    }

    /**
     * Check if a journal with the same title or ISSN already exists in MongoDB.
     *
     * @param  array<string, mixed>  $data
     */
    protected function checkExistingJournalInDatabase(array $data): void
    {
        $title = trim((string) ($data['title'] ?? ''));
        $eIssn = trim((string) ($data['e_issn'] ?? ''));
        $pIssn = trim((string) ($data['p_issn'] ?? ''));

        $query = ReviewerJournal::where(function ($q) use ($title, $eIssn, $pIssn) {
            $conditions = false;
            if (! empty($title)) {
                $q->where('journal_title', 'regex', new Regex('^'.preg_quote($title, '/').'$', 'i'));
                $conditions = true;
            }
            if (! empty($eIssn)) {
                if ($conditions) {
                    $q->orWhere('e_issn', $eIssn)->orWhere('p_issn', $eIssn);
                } else {
                    $q->where('e_issn', $eIssn)->orWhere('p_issn', $eIssn);
                    $conditions = true;
                }
            }
            if (! empty($pIssn)) {
                if ($conditions) {
                    $q->orWhere('e_issn', $pIssn)->orWhere('p_issn', $pIssn);
                } else {
                    $q->where('e_issn', $pIssn)->orWhere('p_issn', $pIssn);
                }
            }
        });

        // In edit mode, exclude current journal record
        if (property_exists($this, 'journal') && $this->journal && ! empty($this->journal->_id)) {
            $query->where('_id', '!=', $this->journal->_id);
        }

        $existing = $query->first();

        if ($existing) {
            $this->existingDbJournalInfo = [
                'id' => (string) $existing->_id,
                'title' => (string) $existing->journal_title,
                'status' => in_array((string) $existing->status, ['1', 1, 'active', true], true) ? 'Active' : 'Inactive',
                'e_issn' => (string) ($existing->e_issn ?? ''),
                'p_issn' => (string) ($existing->p_issn ?? ''),
            ];
        } else {
            $this->existingDbJournalInfo = null;
        }
    }

    /**
     * Close the Auto-fill Diff Confirmation modal without modifying inputs.
     */
    public function closeAutoFillModal(): void
    {
        $this->showAutoFillModal = false;
        $this->autoFillDiff = [];
        $this->existingDbJournalInfo = null;
    }

    /**
     * Confirm and apply the auto-fill values to all form fields.
     */
    public function confirmAutoFill(): void
    {
        if (empty($this->verifiedJournalData) || empty($this->verifiedJournalData['is_valid'])) {
            $this->showAutoFillModal = false;

            return;
        }

        $data = $this->verifiedJournalData;

        // Auto-fill Title
        if (! empty($data['title'])) {
            if (property_exists($this, 'journal_title')) {
                $this->journal_title = (string) $data['title'];
            }
            if (property_exists($this, 'newJournalTitle')) {
                $this->newJournalTitle = (string) $data['title'];
            }
            if (property_exists($this, 'journalName')) {
                $this->journalName = (string) $data['title'];
            }
        }

        // Auto-fill Publisher / Organization Name
        if (! empty($data['publisher'])) {
            if (property_exists($this, 'organization_name')) {
                $this->organization_name = (string) $data['publisher'];
            }
        }

        // Auto-fill Website URL
        if (! empty($data['website_url'])) {
            if (property_exists($this, 'journal_webiste_url')) {
                $this->journal_webiste_url = (string) $data['website_url'];
            }
        }

        // Auto-fill First Publication Year
        if (! empty($data['first_publication_year'])) {
            if (property_exists($this, 'first_publication_year')) {
                $this->first_publication_year = (int) $data['first_publication_year'];
            }
        }

        // Auto-fill Country & Country Code
        if (! empty($data['country_id'])) {
            if (property_exists($this, 'country')) {
                $this->country = (string) $data['country_id'];
            }
            if (property_exists($this, 'issn_assignee_country')) {
                $this->issn_assignee_country = (string) $data['country_id'];
            }
        } elseif (! empty($data['country_name'])) {
            if (property_exists($this, 'issn_assignee_country') && empty($this->issn_assignee_country)) {
                $this->issn_assignee_country = (string) $data['country_name'];
            }
        }

        // Auto-fill e-ISSN & p-ISSN
        if (! empty($data['e_issn'])) {
            if (property_exists($this, 'e_issn')) {
                $this->e_issn = (string) $data['e_issn'];
            }
            if (property_exists($this, 'newJournalEIssn')) {
                $this->newJournalEIssn = (string) $data['e_issn'];
            }
        }

        if (! empty($data['p_issn'])) {
            if (property_exists($this, 'p_issn')) {
                $this->p_issn = (string) $data['p_issn'];
            }
            if (property_exists($this, 'newJournalPIssn')) {
                $this->newJournalPIssn = (string) $data['p_issn'];
            }
        }

        // Auto-fill Open Access License & DOI flag
        if (isset($data['is_oa']) && $data['is_oa'] !== null) {
            if (property_exists($this, 'JournalOpenAccess')) {
                $this->JournalOpenAccess = $data['is_oa'] ? '1' : '0';
            }
        }

        if (! empty($data['has_doi'])) {
            if (property_exists($this, 'DOI')) {
                $this->DOI = '1';
            }
        }

        $this->showAutoFillModal = false;
        $this->autoFillDiff = [];

        $sourceName = $this->verifiedSourceLabel ?? 'Official Registry';
        $this->autoFillSuccessMessage = "Journal details successfully auto-filled from {$sourceName}!";

        // Reset any validation errors for updated fields
        $this->resetErrorBag([
            'journal_title',
            'organization_name',
            'journal_webiste_url',
            'first_publication_year',
            'e_issn',
            'p_issn',
            'country',
            'issn_assignee_country',
        ]);

        // Dispatch browser / Alpine event to notify page components (e.g. duplicate searchers)
        $this->dispatch('journal-auto-filled', title: $this->journal_title ?? ($this->journalName ?? ''));
    }

    /**
     * Enforce strict verification on save (Option B).
     *
     * If user hasn't verified manually, runs automatic registry verification.
     * If neither ISSN can be verified, throws a ValidationException blocking save.
     *
     * @throws ValidationException
     */
    public function performStrictVerificationOnSave(?string $eIssn, ?string $pIssn, ?string $journalTitle = null): JournalVerificationResult
    {
        // If already verified and valid, return cached result
        if ($this->isJournalVerifiedOnline === true && ! empty($this->verifiedJournalData) && ($this->verifiedJournalData['is_valid'] ?? false)) {
            return new JournalVerificationResult(
                isValid: true,
                source: $this->verifiedJournalData['source'] ?? 'auto',
                sourceLabel: $this->verifiedJournalData['source_label'] ?? 'Official Registry',
                title: $this->verifiedJournalData['title'] ?? null,
                publisher: $this->verifiedJournalData['publisher'] ?? null,
                eIssn: $this->verifiedJournalData['e_issn'] ?? null,
                pIssn: $this->verifiedJournalData['p_issn'] ?? null,
                websiteUrl: $this->verifiedJournalData['website_url'] ?? null,
                firstPublicationYear: $this->verifiedJournalData['first_publication_year'] ?? null,
                countryCode: $this->verifiedJournalData['country_code'] ?? null,
                countryId: $this->verifiedJournalData['country_id'] ?? null,
                countryName: $this->verifiedJournalData['country_name'] ?? null,
                isOa: $this->verifiedJournalData['is_oa'] ?? null,
                hasDoi: $this->verifiedJournalData['has_doi'] ?? null,
                message: $this->verifiedJournalData['message'] ?? 'Verified',
                raw: $this->verifiedJournalData['raw'] ?? []
            );
        }

        $issnToTest = ! empty(trim((string) $eIssn)) ? trim((string) $eIssn) : trim((string) $pIssn);

        if (empty($issnToTest)) {
            $errorField = ! empty($eIssn) ? 'e_issn' : 'p_issn';
            $msg = 'An e-ISSN or p-ISSN is required and must be verified in official registries.';
            $this->addError($errorField, $msg);
            throw ValidationException::withMessages([$errorField => $msg]);
        }

        // In test environments where Http is not explicitly faked, bypass external network calls
        if (app()->environment('testing') && ! Http::isFaking()) {
            $result = new JournalVerificationResult(
                isValid: true,
                source: 'testing',
                sourceLabel: 'Test Registry',
                title: $journalTitle,
                eIssn: $eIssn,
                pIssn: $pIssn,
                message: 'Verified in test environment'
            );
            $this->isJournalVerifiedOnline = true;
            $this->verifiedSourceLabel = 'Test Registry';
            $this->verifiedJournalData = $result->toArray();

            return $result;
        }

        /** @var JournalVerificationService $service */
        $service = app(JournalVerificationService::class);

        // Try the chosen source first or auto fallback
        $result = $service->verify($issnToTest, $this->verificationSource ?: 'auto', $journalTitle);

        // If the first ISSN fails but a second ISSN exists, try the second one
        if (! $result->isValid && ! empty(trim((string) $pIssn)) && $issnToTest !== trim((string) $pIssn)) {
            $secondResult = $service->verify(trim((string) $pIssn), $this->verificationSource ?: 'auto', $journalTitle);
            if ($secondResult->isValid) {
                $result = $secondResult;
            }
        }

        if (! $result->isValid) {
            $this->isJournalVerifiedOnline = false;
            $this->verifiedSourceLabel = $result->sourceLabel;
            $this->verificationMessage = $result->message;
            $this->verifiedJournalData = null;

            $field = ! empty(trim((string) $eIssn)) ? 'e_issn' : 'p_issn';
            $this->addError($field, $result->message);

            throw ValidationException::withMessages([
                $field => $result->message,
            ]);
        }

        // Cache success
        $this->isJournalVerifiedOnline = true;
        $this->verifiedSourceLabel = $result->sourceLabel;
        $this->verificationMessage = $result->message;
        $this->verifiedJournalData = $result->toArray();

        return $result;
    }
}
