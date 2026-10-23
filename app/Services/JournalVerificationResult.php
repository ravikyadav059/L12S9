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
        public ?string $message = null,
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
        ?string $message = null,
        array $raw = []
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
            message: $message ?? "Journal verified successfully via {$sourceLabel}.",
            raw: $raw
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
            'message' => $this->message,
            'raw' => $this->raw,
        ];
    }
}
