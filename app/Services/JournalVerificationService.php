<?php

namespace App\Services;

use App\Helpers\IssnHelper;
use App\Models\Country;
use App\Rules\ValidIssn;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JournalVerificationService
{
    /**
     * Map of supported registry providers.
     */
    public const PROVIDERS = [
        'auto' => 'Auto (Any Official Registry)',
        'crossref' => 'Crossref',
        'openalex' => 'OpenAlex',
        'doaj' => 'DOAJ',
        'issn_portal' => 'Official ISSN Portal',
    ];

    /**
     * Clean and sanitize journal titles from official registry pages.
     * Strips "The ISSN Portal | ", "ISSN XXXX-XXXX - ", and normalizes casing.
     */
    public static function cleanTitle(?string $title): string
    {
        if (empty($title)) {
            return '';
        }

        $clean = trim((string) $title);

        // Remove "The ISSN Portal | "
        if (str_contains($clean, '|')) {
            $parts = explode('|', $clean, 2);
            $clean = trim($parts[1] ?? $clean);
        }

        // Remove "ISSN xxxx-xxxx - " or "ISSN: " or "ISSN xxxx-xxxx : "
        $clean = (string) preg_replace('/^ISSN(?:\s+\d{4}-\d{3}[0-9X])?\s*[-–:]\s*/i', '', $clean);
        $clean = (string) preg_replace('/^ISSN\s+/i', '', $clean);

        // Strip trailing punctuation
        $clean = trim($clean, " .\t\n\r\0\x0B-–:");

        // Detect sentence-case (only first letter capitalized, like "International journal of ...") or all-caps/all-lower
        $words = array_values(array_filter(explode(' ', $clean)));
        $uppercaseWords = 0;
        foreach ($words as $w) {
            if (! empty($w) && mb_strtoupper(mb_substr($w, 0, 1)) === mb_substr($w, 0, 1) && ctype_alpha(mb_substr($w, 0, 1))) {
                $uppercaseWords++;
            }
        }

        if (count($words) > 1 && ($uppercaseWords <= 1 || $clean === strtoupper($clean) || $clean === strtolower($clean))) {
            $formattedWords = [];
            foreach ($words as $w) {
                if (strlen($w) > 1 && $w === strtoupper($w) && ctype_alpha($w)) {
                    $formattedWords[] = $w;
                } else {
                    $formattedWords[] = ucfirst(strtolower($w));
                }
            }
            $clean = implode(' ', $formattedWords);
        }

        return $clean;
    }

    /**
     * Resolve country information from country ISO-2 code using the database Country model.
     *
     * @return array{id: ?string, name: ?string, phoneCode: ?string}
     */
    public static function resolveCountry(?string $countryCode): array
    {
        if (empty($countryCode)) {
            return ['id' => null, 'name' => null, 'phoneCode' => null];
        }

        $code = strtoupper(trim($countryCode));
        try {
            $country = Country::where('sortname', $code)->first();
            if ($country) {
                return [
                    'id' => (string) ($country->id ?? $country->_id),
                    'name' => (string) ($country->name ?? ''),
                    'phoneCode' => ! empty($country->phoneCode) ? (string) $country->phoneCode : null,
                ];
            }
        } catch (\Throwable) {
        }

        return ['id' => null, 'name' => null, 'phoneCode' => null];
    }

    /**
     * Verify a journal by its ISSN against a specified or auto-selected registry.
     */
    public function verify(?string $issn, string $source = 'auto', ?string $journalTitle = null): JournalVerificationResult
    {
        $cleanIssn = IssnHelper::format($issn);

        if (empty($cleanIssn)) {
            return JournalVerificationResult::failure('Please provide an ISSN (e-ISSN or p-ISSN) to verify.');
        }

        // 1. Instant ISO 3297 Modulus 11 Checksum validation
        if (! ValidIssn::isValid($cleanIssn)) {
            return JournalVerificationResult::failure(
                "The ISSN '{$cleanIssn}' has an invalid format or checksum digit. Please verify the digits.",
                'checksum',
                'ISSN Checksum'
            );
        }

        $source = strtolower(trim($source));
        if (! array_key_exists($source, self::PROVIDERS)) {
            $source = 'auto';
        }

        if ($source === 'auto') {
            return $this->verifyAuto($cleanIssn);
        }

        return match ($source) {
            'crossref' => $this->verifyWithCrossref($cleanIssn) ?? JournalVerificationResult::failure(
                "ISSN '{$cleanIssn}' could not be found in the Crossref registry.",
                'crossref',
                'Crossref'
            ),
            'openalex' => $this->verifyWithOpenAlex($cleanIssn) ?? JournalVerificationResult::failure(
                "ISSN '{$cleanIssn}' could not be found in the OpenAlex database.",
                'openalex',
                'OpenAlex'
            ),
            'doaj' => $this->verifyWithDoaj($cleanIssn) ?? JournalVerificationResult::failure(
                "ISSN '{$cleanIssn}' is not registered in the Directory of Open Access Journals (DOAJ).",
                'doaj',
                'DOAJ'
            ),
            'issn_portal' => $this->verifyWithIssnPortal($cleanIssn) ?? JournalVerificationResult::failure(
                "ISSN '{$cleanIssn}' could not be verified in the Official ISSN Portal.",
                'issn_portal',
                'Official ISSN Portal'
            ),
            default => $this->verifyAuto($cleanIssn),
        };
    }

    /**
     * Run sequential fallback across all official registries, enriching metadata where possible.
     */
    public function verifyAuto(string $cleanIssn): JournalVerificationResult
    {
        // 1. Crossref (primary for DOIs & official publishers)
        $crossrefResult = $this->verifyWithCrossref($cleanIssn);

        // 2. OpenAlex (provides country, website, open access)
        $openalexResult = $this->verifyWithOpenAlex($cleanIssn);

        if ($crossrefResult && $openalexResult) {
            // Merge complementary metadata: Crossref title & publisher + OpenAlex website & country
            return JournalVerificationResult::success(
                source: 'crossref',
                sourceLabel: 'Crossref & OpenAlex',
                title: $crossrefResult->title ?: $openalexResult->title,
                publisher: $crossrefResult->publisher ?: $openalexResult->publisher,
                eIssn: $crossrefResult->eIssn ?: $openalexResult->eIssn,
                pIssn: $crossrefResult->pIssn ?: $openalexResult->pIssn,
                websiteUrl: $openalexResult->websiteUrl ?: $crossrefResult->websiteUrl,
                firstPublicationYear: $crossrefResult->firstPublicationYear ?: $openalexResult->firstPublicationYear,
                countryCode: $openalexResult->countryCode,
                countryId: $openalexResult->countryId,
                countryName: $openalexResult->countryName,
                isOa: $openalexResult->isOa,
                hasDoi: true,
                message: "Verified: {$crossrefResult->title} ({$crossrefResult->publisher})",
                raw: array_merge($crossrefResult->raw, ['openalex' => $openalexResult->raw])
            );
        }

        if ($crossrefResult) {
            return $crossrefResult;
        }

        if ($openalexResult) {
            return $openalexResult;
        }

        // 3. DOAJ
        if ($res = $this->verifyWithDoaj($cleanIssn)) {
            return $res;
        }

        // 4. Official ISSN Portal
        if ($res = $this->verifyWithIssnPortal($cleanIssn)) {
            return $res;
        }

        return JournalVerificationResult::failure(
            "ISSN '{$cleanIssn}' could not be found in any official registries (Crossref, OpenAlex, DOAJ, or ISSN Portal). Please verify the entered data.",
            'auto',
            'Official Registries'
        );
    }

    /**
     * Verify with Crossref API.
     */
    public function verifyWithCrossref(string $cleanIssn): ?JournalVerificationResult
    {
        try {
            $response = $this->httpClient()
                ->get("https://api.crossref.org/journals/{$cleanIssn}");

            if ($response->successful()) {
                $data = $response->json('message') ?? [];
                $rawTitle = is_array($data['title'] ?? null)
                    ? ($data['title'][0] ?? null)
                    : ($data['title'] ?? null);
                $title = self::cleanTitle($rawTitle);
                $publisher = $data['publisher'] ?? null;

                $eIssn = null;
                $pIssn = null;

                $issnTypes = $data['issn-type'] ?? [];
                if (is_array($issnTypes)) {
                    foreach ($issnTypes as $type) {
                        $val = IssnHelper::format($type['value'] ?? '');
                        if (($type['type'] ?? '') === 'electronic') {
                            $eIssn = $val;
                        } elseif (($type['type'] ?? '') === 'print') {
                            $pIssn = $val;
                        }
                    }
                }

                $issnList = $data['ISSN'] ?? [];
                if (is_array($issnList)) {
                    foreach ($issnList as $issnVal) {
                        $formatted = IssnHelper::format($issnVal);
                        if (! $eIssn && $formatted !== $pIssn) {
                            $eIssn = $formatted;
                        } elseif (! $pIssn && $formatted !== $eIssn) {
                            $pIssn = $formatted;
                        }
                    }
                }

                // Calculate first publication year from issued DOIs
                $firstYear = null;
                $issuedYears = $data['breakdowns']['dois-by-issued-year'] ?? [];
                if (is_array($issuedYears) && ! empty($issuedYears)) {
                    $years = [];
                    foreach ($issuedYears as $item) {
                        if (isset($item[0]) && is_numeric($item[0])) {
                            $years[] = (int) $item[0];
                        }
                    }
                    if (! empty($years)) {
                        $firstYear = min($years);
                    }
                }

                return JournalVerificationResult::success(
                    source: 'crossref',
                    sourceLabel: 'Crossref',
                    title: $title,
                    publisher: $publisher,
                    eIssn: $eIssn,
                    pIssn: $pIssn,
                    firstPublicationYear: $firstYear,
                    hasDoi: true,
                    message: "Verified in Crossref: {$title} ({$publisher})",
                    raw: [
                        'counts' => $data['counts'] ?? [],
                        'flags' => $data['flags'] ?? [],
                        'subjects' => $data['subjects'] ?? [],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("Crossref verification failed for ISSN {$cleanIssn}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Verify with OpenAlex API.
     */
    public function verifyWithOpenAlex(string $cleanIssn): ?JournalVerificationResult
    {
        try {
            $response = $this->httpClient()
                ->get('https://api.openalex.org/sources', [
                    'filter' => "issn:{$cleanIssn}",
                ]);

            if ($response->successful()) {
                $count = (int) ($response->json('meta.count') ?? 0);
                $first = $response->json('results.0');

                if ($count > 0 && is_array($first)) {
                    $title = self::cleanTitle($first['display_name'] ?? null);
                    $publisher = $first['host_organization_name'] ?? null;
                    $homepage = $first['homepage_url'] ?? null;
                    $issns = $first['issn'] ?? [];
                    $countryCode = $first['country_code'] ?? null;
                    $countryInfo = self::resolveCountry($countryCode);

                    $eIssn = null;
                    $pIssn = null;
                    if (is_array($issns)) {
                        $eIssn = isset($issns[0]) ? IssnHelper::format($issns[0]) : null;
                        $pIssn = isset($issns[1]) ? IssnHelper::format($issns[1]) : null;
                    }

                    // Calculate first publication year from counts_by_year
                    $firstYear = null;
                    $countsByYear = $first['counts_by_year'] ?? [];
                    if (is_array($countsByYear) && ! empty($countsByYear)) {
                        $years = [];
                        foreach ($countsByYear as $item) {
                            if (isset($item['year']) && is_numeric($item['year'])) {
                                $years[] = (int) $item['year'];
                            }
                        }
                        if (! empty($years)) {
                            $firstYear = min($years);
                        }
                    }

                    return JournalVerificationResult::success(
                        source: 'openalex',
                        sourceLabel: 'OpenAlex',
                        title: $title,
                        publisher: $publisher,
                        eIssn: $eIssn,
                        pIssn: $pIssn,
                        websiteUrl: $homepage,
                        firstPublicationYear: $firstYear,
                        countryCode: $countryCode,
                        countryId: $countryInfo['id'],
                        countryName: $countryInfo['name'],
                        isOa: ! empty($first['is_oa']),
                        message: "Verified in OpenAlex: {$title} ({$publisher})",
                        raw: [
                            'id' => $first['id'] ?? null,
                            'works_count' => $first['works_count'] ?? 0,
                            'cited_by_count' => $first['cited_by_count'] ?? 0,
                            'is_oa' => $first['is_oa'] ?? false,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning("OpenAlex verification failed for ISSN {$cleanIssn}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Verify with DOAJ API.
     */
    public function verifyWithDoaj(string $cleanIssn): ?JournalVerificationResult
    {
        try {
            $response = $this->httpClient()
                ->get("https://doaj.org/api/v2/search/journals/{$cleanIssn}");

            if ($response->successful()) {
                $total = (int) ($response->json('total') ?? 0);
                $bib = $response->json('results.0.bibjson');

                if ($total > 0 && is_array($bib)) {
                    $title = self::cleanTitle($bib['title'] ?? null);
                    $publisher = $bib['publisher']['name'] ?? null;
                    $eIssn = isset($bib['eissn']) ? IssnHelper::format($bib['eissn']) : null;
                    $pIssn = isset($bib['pissn']) ? IssnHelper::format($bib['pissn']) : null;
                    $countryCode = $bib['publisher']['country'] ?? null;
                    $countryInfo = self::resolveCountry($countryCode);
                    $firstYear = isset($bib['oa_start']) && is_numeric($bib['oa_start']) ? (int) $bib['oa_start'] : null;

                    $homepage = null;
                    if (! empty($bib['link']) && is_array($bib['link'])) {
                        foreach ($bib['link'] as $link) {
                            if (($link['type'] ?? '') === 'homepage') {
                                $homepage = $link['url'] ?? null;
                                break;
                            }
                        }
                    }

                    return JournalVerificationResult::success(
                        source: 'doaj',
                        sourceLabel: 'DOAJ',
                        title: $title,
                        publisher: $publisher,
                        eIssn: $eIssn,
                        pIssn: $pIssn,
                        websiteUrl: $homepage,
                        firstPublicationYear: $firstYear,
                        countryCode: $countryCode,
                        countryId: $countryInfo['id'],
                        countryName: $countryInfo['name'],
                        isOa: true,
                        message: "Verified in DOAJ: {$title} ({$publisher})",
                        raw: [
                            'oa_start' => $bib['oa_start'] ?? null,
                            'apc' => $bib['apc'] ?? null,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning("DOAJ verification failed for ISSN {$cleanIssn}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Verify with Official ISSN Portal.
     */
    public function verifyWithIssnPortal(string $cleanIssn): ?JournalVerificationResult
    {
        try {
            $response = $this->httpClient()
                ->get("https://portal.issn.org/resource/ISSN/{$cleanIssn}");

            if ($response->successful()) {
                $body = $response->body();
                $title = null;

                if (preg_match('/<title>(.*?)<\/title>/is', $body, $matches)) {
                    $rawTitle = trim(html_entity_decode($matches[1] ?? ''));
                    $title = self::cleanTitle($rawTitle);
                }

                return JournalVerificationResult::success(
                    source: 'issn_portal',
                    sourceLabel: 'Official ISSN Portal',
                    title: $title ?: "ISSN {$cleanIssn}",
                    publisher: null,
                    eIssn: $cleanIssn,
                    websiteUrl: "https://portal.issn.org/resource/ISSN/{$cleanIssn}",
                    message: 'Verified in Official ISSN Portal'.($title ? ": {$title}" : ''),
                    raw: ['portal_url' => "https://portal.issn.org/resource/ISSN/{$cleanIssn}"]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("ISSN Portal verification failed for ISSN {$cleanIssn}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Create an HTTP client configured with academic etiquette headers and timeout.
     */
    protected function httpClient(): PendingRequest
    {
        $mailto = config('services.journal_verification.mailto', env('JOURNAL_VERIFICATION_MAILTO', 'admin@scholar9.com'));
        $timeout = (int) config('services.journal_verification.timeout', 5);
        $userAgent = "Scholar9/1.0 (mailto:{$mailto})";

        return Http::timeout($timeout)
            ->withUserAgent($userAgent)
            ->acceptJson();
    }
}
