<?php

namespace App\Services;

class JournalVerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $isValid,
        public ?string $source = null,
        public ?string $sourceLabel = null,
        public ?string $title = null,
        public ?string $publisher = null,
        public ?string $eIssn = null,
        public ?string $pIssn = null,
        public ?string $websiteUrl = null,
        public ?int $firstPublicationYear = null,
        public ?string $countryCode = null,
        public ?string $countryId = null,
        public ?string $countryName = null,
        public ?bool $isOa = null,
        public ?bool $hasDoi = null,
        public array $languages = ['English'],
        public ?string $issnAssigneeCountry = null,
        public ?string $description = null,
        public ?string $message = null,
        public ?string $journalShortName = null,
        public ?string $issueFrequency = null,
        public array $subjects = [],
        public ?string $editorName = null,
        public ?string $editorEmail = null,
        public ?string $editorMobile = null,
        public ?string $address = null,
        public ?string $zipcode = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $stateId = null,
        public ?string $cityId = null,
        public array $indexingAgencies = [],
        public array $raw = []
    ) {}

    public static function success(
        string $source,
        string $sourceLabel,
        ?string $title = null,
        ?string $publisher = null,
        ?string $eIssn = null,
        ?string $pIssn = null,
        ?string $websiteUrl = null,
        ?int $firstPublicationYear = null,
        ?string $countryCode = null,
        ?string $countryId = null,
        ?string $countryName = null,
        ?bool $isOa = null,
        ?bool $hasDoi = null,
        array $languages = ['English'],
        ?string $issnAssigneeCountry = null,
        ?string $description = null,
        ?string $message = null,
        array $raw = [],
        ?string $journalShortName = null,
        ?string $issueFrequency = null,
        array $subjects = [],
        ?string $editorName = null,
        ?string $editorEmail = null,
        ?string $editorMobile = null,
        ?string $address = null,
        ?string $zipcode = null,
        ?string $city = null,
        ?string $state = null,
        ?string $stateId = null,
        ?string $cityId = null,
        array $indexingAgencies = []
    ): self {
        return new self(
            isValid: true,
            source: $source,
            sourceLabel: $sourceLabel,
            title: $title,
            publisher: $publisher,
            eIssn: $eIssn,
            pIssn: $pIssn,
            websiteUrl: $websiteUrl,
            firstPublicationYear: $firstPublicationYear,
            countryCode: $countryCode,
            countryId: $countryId,
            countryName: $countryName,
            isOa: $isOa,
            hasDoi: $hasDoi,
            languages: ! empty($languages) ? $languages : ['English'],
            issnAssigneeCountry: $issnAssigneeCountry ?? $countryId,
            description: $description,
            message: $message ?? "Journal verified successfully via {$sourceLabel}.",
            raw: $raw,
            journalShortName: $journalShortName,
            issueFrequency: $issueFrequency,
            subjects: $subjects,
            editorName: $editorName,
            editorEmail: $editorEmail,
            editorMobile: $editorMobile,
            address: $address,
            zipcode: $zipcode,
            city: $city,
            state: $state,
            stateId: $stateId,
            cityId: $cityId,
            indexingAgencies: $indexingAgencies
        );
    }

    public static function failure(string $message, ?string $source = null, ?string $sourceLabel = null): self
    {
        return new self(
            isValid: false,
            source: $source,
            sourceLabel: $sourceLabel,
            message: $message
        );
    }

    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'source' => $this->source,
            'source_label' => $this->sourceLabel,
            'title' => $this->title,
            'publisher' => $this->publisher,
            'e_issn' => $this->eIssn,
            'p_issn' => $this->pIssn,
            'website_url' => $this->websiteUrl,
            'first_publication_year' => $this->firstPublicationYear,
            'country_code' => $this->countryCode,
            'country_id' => $this->countryId,
            'country_name' => $this->countryName,
            'is_oa' => $this->isOa,
            'has_doi' => $this->hasDoi,
            'languages' => $this->languages,
            'issn_assignee_country' => $this->issnAssigneeCountry ?? $this->countryId,
            'description' => $this->description,
            'message' => $this->message,
            'raw' => $this->raw,
            'journal_short_name' => $this->journalShortName,
            'issue_frequency' => $this->issueFrequency,
            'subjects' => $this->subjects,
            'editor_name' => $this->editorName,
            'editor_email' => $this->editorEmail,
            'editor_mobile' => $this->editorMobile,
            'address' => $this->address,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'state' => $this->state,
            'state_id' => $this->stateId,
            'city_id' => $this->cityId,
            'indexing_agencies' => $this->indexingAgencies,
        ];
    }
}
