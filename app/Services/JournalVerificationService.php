<?php

namespace App\Services;

use App\Helpers\IssnHelper;
use App\Models\City;
use App\Models\Country;
use App\Models\IndexingAgency;
use App\Models\State;
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
            $minorWords = ['of', 'and', 'the', 'in', 'on', 'at', 'for', 'to', 'a', 'an', 'by', 'with', 'from'];
            $formattedWords = [];
            foreach ($words as $idx => $w) {
                $lower = strtolower($w);
                if (strlen($w) > 1 && $w === strtoupper($w) && ctype_alpha($w)) {
                    $formattedWords[] = $w;
                } elseif ($idx > 0 && in_array($lower, $minorWords, true)) {
                    $formattedWords[] = $lower;
                } else {
                    $formattedWords[] = ucfirst($lower);
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
     * Map language ISO codes or names to accepted system language names (e.g. English, Hindi).
     *
     * @param  array<string>|string|null  $rawLangs
     * @return array<int, string>
     */
    public static function mapLanguages(array|string|null $rawLangs): array
    {
        if (empty($rawLangs)) {
            return ['English'];
        }

        $items = is_array($rawLangs) ? $rawLangs : [$rawLangs];
        $map = [
            'en' => 'English',
            'eng' => 'English',
            'english' => 'English',
            'hi' => 'Hindi',
            'hin' => 'Hindi',
            'hindi' => 'Hindi',
            'fr' => 'French',
            'fra' => 'French',
            'fre' => 'French',
            'de' => 'German',
            'deu' => 'German',
            'es' => 'Spanish',
            'spa' => 'Spanish',
            'pt' => 'Portuguese',
            'por' => 'Portuguese',
            'it' => 'Italian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ar' => 'Arabic',
            'ru' => 'Russian',
        ];

        $resolved = [];
        foreach ($items as $item) {
            $key = strtolower(trim((string) $item));
            if (isset($map[$key])) {
                $resolved[] = $map[$key];
            } elseif (! empty($item) && is_string($item)) {
                $resolved[] = ucfirst(strtolower(trim($item)));
            }
        }

        $unique = array_values(array_unique(array_filter($resolved)));

        return ! empty($unique) ? $unique : ['English'];
    }

    /**
     * Clean and validate external journal website URLs.
     * Prevents internal portal URLs (e.g. portal.issn.org) from being saved as journal website.
     */
    public static function cleanJournalWebsiteUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);

        // Discard portal/registry internal pages
        if (preg_match('/(portal\.issn\.org|data\.issn\.org|crossref\.org|openalex\.org|doaj\.org)/i', $url)) {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://'.ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Normalize publication issue frequency strings into standard choices.
     */
    public static function normalizeFrequency(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $clean = trim($raw);
        $map = [
            'monthly' => 'Monthly',
            'semi-monthly' => 'Semi-monthly',
            'semimonthly' => 'Semi-monthly',
            'bi-monthly' => 'Bi-Monthly',
            'bimonthly' => 'Bi-Monthly',
            'weekly' => 'weekly',
            'bi-weekly' => 'Bi-Weekly',
            'biweekly' => 'Bi-Weekly',
            'quarterly' => 'Quarterly',
            'half-yearly' => 'Half-Yearly',
            'half yearly' => 'Half-Yearly',
            'yearly' => 'Yearly',
            'annual' => 'Yearly',
            'annually' => 'Yearly',
            'bi-annual' => 'Bi-Annual',
            'biannual' => 'Bi-Annual',
            'semiannual' => 'Semiannual',
            'triennially' => 'Triennially',
            'continuously' => 'Continuously',
            'irregular' => 'Irregular',
        ];

        $lower = strtolower($clean);

        return $map[$lower] ?? ucfirst($lower);
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
            $mergedSubjects = array_values(array_unique(array_filter(array_merge($crossrefResult->subjects, $openalexResult->subjects))));
            $mergedResult = JournalVerificationResult::success(
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
                languages: ! empty($openalexResult->languages) ? $openalexResult->languages : ['English'],
                issnAssigneeCountry: $openalexResult->countryId,
                description: $openalexResult->description,
                message: "Verified: {$crossrefResult->title} ({$crossrefResult->publisher})",
                raw: array_merge($crossrefResult->raw, ['openalex' => $openalexResult->raw]),
                journalShortName: $openalexResult->journalShortName ?: $crossrefResult->journalShortName,
                issueFrequency: $openalexResult->issueFrequency ?: $crossrefResult->issueFrequency,
                subjects: $mergedSubjects,
                editorName: $openalexResult->editorName ?: $crossrefResult->editorName,
                editorEmail: $openalexResult->editorEmail ?: $crossrefResult->editorEmail,
                editorMobile: $openalexResult->editorMobile ?: $crossrefResult->editorMobile,
                address: $openalexResult->address ?: $crossrefResult->address,
                zipcode: $openalexResult->zipcode ?: $crossrefResult->zipcode,
                city: $openalexResult->city ?: $crossrefResult->city,
                state: $openalexResult->state ?: $crossrefResult->state,
                stateId: $openalexResult->stateId ?: $crossrefResult->stateId,
                cityId: $openalexResult->cityId ?: $crossrefResult->cityId,
                indexingAgencies: ! empty($openalexResult->indexingAgencies) ? $openalexResult->indexingAgencies : $crossrefResult->indexingAgencies
            );

            return $this->enrichResultWithWebsite($mergedResult);
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

                if ($eIssn && ! ValidIssn::isValid($eIssn)) {
                    $eIssn = null;
                }
                if ($pIssn && ! ValidIssn::isValid($pIssn)) {
                    $pIssn = null;
                }
                if ($eIssn && $pIssn && $eIssn === $pIssn) {
                    $pIssn = null;
                }
                if (! $eIssn && $pIssn) {
                    $eIssn = $pIssn;
                    $pIssn = null;
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

                    if ($eIssn && ! ValidIssn::isValid($eIssn)) {
                        $eIssn = null;
                    }
                    if ($pIssn && ! ValidIssn::isValid($pIssn)) {
                        $pIssn = null;
                    }
                    if ($eIssn && $pIssn && $eIssn === $pIssn) {
                        $pIssn = null;
                    }
                    if (! $eIssn && $pIssn) {
                        $eIssn = $pIssn;
                        $pIssn = null;
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

                    $concepts = $first['concepts'] ?? [];
                    $subjects = [];
                    if (is_array($concepts)) {
                        foreach (array_slice($concepts, 0, 4) as $c) {
                            if (! empty($c['display_name'])) {
                                $subjects[] = $c['display_name'];
                            }
                        }
                    }

                    $result = JournalVerificationResult::success(
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
                        languages: self::mapLanguages($first['language'] ?? 'en'),
                        issnAssigneeCountry: $countryInfo['id'],
                        message: "Verified in OpenAlex: {$title} ({$publisher})",
                        raw: [
                            'id' => $first['id'] ?? null,
                            'works_count' => $first['works_count'] ?? 0,
                            'cited_by_count' => $first['cited_by_count'] ?? 0,
                            'is_oa' => $first['is_oa'] ?? false,
                        ],
                        subjects: $subjects
                    );

                    return $this->enrichResultWithWebsite($result);
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

                    $subjects = [];
                    if (! empty($bib['subject']) && is_array($bib['subject'])) {
                        foreach ($bib['subject'] as $s) {
                            if (! empty($s['term'])) {
                                $subjects[] = $s['term'];
                            }
                        }
                    }

                    $result = JournalVerificationResult::success(
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
                        languages: self::mapLanguages($bib['language'] ?? 'en'),
                        issnAssigneeCountry: $countryInfo['id'],
                        message: "Verified in DOAJ: {$title} ({$publisher})",
                        raw: [
                            'oa_start' => $bib['oa_start'] ?? null,
                            'apc' => $bib['apc'] ?? null,
                        ],
                        subjects: $subjects
                    );

                    return $this->enrichResultWithWebsite($result);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("DOAJ verification failed for ISSN {$cleanIssn}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Verify with Official ISSN Portal (combining structured JSON-LD and semantic HTML data-keys).
     */
    public function verifyWithIssnPortal(string $cleanIssn): ?JournalVerificationResult
    {
        try {
            $client = $this->httpClient();

            $title = null;
            $publisher = null;
            $websiteUrl = null;
            $firstYear = null;
            $countryCode = null;
            $countryName = null;
            $eIssn = null;
            $pIssn = null;
            $shortName = null;
            $frequency = null;
            $languages = ['English'];
            $subjects = [];

            // 1. Attempt structured JSON-LD (official linked data API)
            try {
                $jsonLdResp = $client->withHeaders([
                    'Accept' => 'application/ld+json',
                ])->get("https://portal.issn.org/resource/ISSN/{$cleanIssn}");

                if ($jsonLdResp->successful() && is_array($jsonLdResp->json())) {
                    $json = $jsonLdResp->json();

                    $rawTitle = $json['mainTitle'] ?? (is_array($json['name'] ?? null) ? ($json['name'][0] ?? null) : ($json['name'] ?? null));
                    if (! empty($rawTitle)) {
                        $title = self::cleanTitle($rawTitle);
                    }

                    // Publisher
                    $pubName = $json['publication'][0]['publishedBy']['name'] ?? null;
                    if (! empty($pubName)) {
                        $publisher = trim((string) $pubName);
                    }

                    // Real Journal Website URL
                    $rawUrls = $json['url'] ?? [];
                    if (is_string($rawUrls)) {
                        $rawUrls = [$rawUrls];
                    }
                    if (is_array($rawUrls)) {
                        foreach ($rawUrls as $candidateUrl) {
                            $cleanWeb = self::cleanJournalWebsiteUrl($candidateUrl);
                            if ($cleanWeb) {
                                $websiteUrl = $cleanWeb;
                                break;
                            }
                        }
                    }

                    // Created date / first publication year
                    $createdDate = $json['mainEntityOf']['generatedBy']['created'] ?? null;
                    if ($createdDate && preg_match('/\b(19\d\d|20\d\d)\b/', $createdDate, $ym)) {
                        $firstYear = (int) $ym[1];
                    }

                    // Spatial ISO 3166
                    $spatials = $json['spatial'] ?? [];
                    if (is_array($spatials)) {
                        foreach ($spatials as $sp) {
                            if (preg_match('/iso:code:3166:([A-Z]{2})/i', (string) $sp, $sm)) {
                                $countryCode = strtoupper($sm[1]);
                                break;
                            }
                        }
                    }
                    if (empty($countryCode)) {
                        $locLabel = $json['publication'][0]['location'][0]['label'] ?? null;
                        if (! empty($locLabel)) {
                            $countryName = trim((string) $locLabel);
                        }
                    }

                    // Format (Online vs Print)
                    $format = (string) ($json['format'] ?? '');
                    if (stripos($format, 'Online') !== false) {
                        $eIssn = $cleanIssn;
                    } elseif (stripos($format, 'Print') !== false) {
                        $pIssn = $cleanIssn;
                    } else {
                        $eIssn = $cleanIssn;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("JSON-LD fetch for ISSN {$cleanIssn} failed: ".$e->getMessage());
            }

            // 2. Fetch page HTML (?format=json or standard GET) to extract remaining semantic data-keys
            try {
                $htmlResp = $client->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])->get("https://portal.issn.org/resource/ISSN/{$cleanIssn}?format=json");

                if ($htmlResp->successful()) {
                    $body = $htmlResp->body();

                    // Parse semantic data-key attributes
                    if (preg_match_all('/data-key="([^"]+)"[^>]*>(.*?)<\/(?:dd|div|span|p|a)>/is', $body, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $k = strtolower(trim($m[1]));
                            $v = trim(strip_tags($m[2]));

                            if (empty($title) && ($k === 'title-proper' || $k === 'key-title')) {
                                $title = self::cleanTitle($v);
                            }

                            if ($k === 'abbreviated-key-title' && ! empty($v)) {
                                $shortName = $v;
                            }

                            if (empty($publisher) && $k === 'earliest-publisher' && ! empty($v)) {
                                // e.g. "Delhi: Medical Journals" -> "Medical Journals"
                                $publisher = trim(preg_replace('/^[^:]+:\s*/', '', $v));
                            }

                            if ($k === 'frequency' && ! empty($v)) {
                                $frequency = self::normalizeFrequency($v);
                            }

                            if (empty($countryCode) && empty($countryName) && $k === 'country' && ! empty($v)) {
                                $countryName = $v;
                            }

                            if ($k === 'language' && ! empty($v)) {
                                $languages = self::mapLanguages($v);
                            }

                            if ($k === 'medium') {
                                if (stripos($v, 'Online') !== false) {
                                    $eIssn = $cleanIssn;
                                } elseif (stripos($v, 'Print') !== false) {
                                    $pIssn = $cleanIssn;
                                }
                            }

                            if ($k === 'created-date' && empty($firstYear)) {
                                if (preg_match('/\b(19\d\d|20\d\d)\b/', $v, $ym)) {
                                    $firstYear = (int) $ym[1];
                                }
                            }
                        }
                    }

                    // Extract external resource link from <a ... data-key="resource-link">
                    if (empty($websiteUrl) && preg_match('/data-key="resource-link"[\s\S]*?<a\s+[^>]*href=["\']([^"\']+)["\']/is', $body, $urlMatch)) {
                        $cleanWeb = self::cleanJournalWebsiteUrl($urlMatch[1]);
                        if ($cleanWeb) {
                            $websiteUrl = $cleanWeb;
                        }
                    }

                    // Dates of publication fallback
                    if (empty($firstYear) && preg_match('/Dates of publication:\s*<\/dt>\s*<dd[^>]*>\s*([12]\d{3})/is', $body, $dateMatch)) {
                        $firstYear = (int) $dateMatch[1];
                    }

                    // Title fallback from <title> tag
                    if (empty($title) && preg_match('/<title>(.*?)<\/title>/is', $body, $titleMatch)) {
                        $title = self::cleanTitle($titleMatch[1]);
                    }

                    // UDC Summary (Subjects)
                    if (preg_match('/UDC Summary:\s*<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is', $body, $udcMatch)) {
                        $udcRaw = preg_replace('/\(\d+\)/', '', strip_tags($udcMatch[1]));
                        $parts = preg_split('/[\.\,;]+/', $udcRaw);
                        if (is_array($parts)) {
                            foreach ($parts as $p) {
                                $cleanP = trim($p);
                                if (strlen($cleanP) >= 3 && ! in_array($cleanP, $subjects, true)) {
                                    $subjects[] = $cleanP;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("HTML parsing for ISSN {$cleanIssn} failed: ".$e->getMessage());
            }

            if (empty($title) && empty($eIssn) && empty($pIssn)) {
                return null;
            }

            // Cross-check publisher with Crossref if publisher is missing or obsolete early publisher
            if (empty($publisher) || strcasecmp($publisher, 'Medical Journals') === 0) {
                try {
                    $crResp = $client->get("https://api.crossref.org/journals/{$cleanIssn}");
                    if ($crResp->successful()) {
                        $crPub = $crResp->json('message.publisher');
                        if (! empty($crPub)) {
                            $publisher = trim((string) $crPub);
                        }
                    }
                } catch (\Throwable) {
                }
            }

            $countryInfo = self::resolveCountry($countryCode ?: $countryName);

            $result = JournalVerificationResult::success(
                source: 'issn_portal',
                sourceLabel: 'Official ISSN Portal',
                title: $title ?: "ISSN {$cleanIssn}",
                publisher: $publisher,
                eIssn: $eIssn ?: $cleanIssn,
                pIssn: $pIssn,
                websiteUrl: $websiteUrl,
                firstPublicationYear: $firstYear,
                countryCode: $countryInfo['code'] ?? $countryCode,
                countryId: $countryInfo['id'] ?? null,
                countryName: $countryInfo['name'] ?? $countryName,
                isOa: null,
                languages: $languages,
                issnAssigneeCountry: $countryInfo['id'] ?? null,
                message: 'Verified in Official ISSN Portal'.($title ? ": {$title}" : ''),
                raw: [
                    'portal_url' => "https://portal.issn.org/resource/ISSN/{$cleanIssn}",
                ],
                journalShortName: $shortName,
                issueFrequency: $frequency,
                subjects: $subjects
            );

            return $this->enrichResultWithWebsite($result);
        } catch (\Throwable $e) {
            Log::warning("ISSN Portal verification failed for ISSN {$cleanIssn}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Search official registries (Crossref & OpenAlex) by Journal Title/Name.
     * Returns matching candidate journals with official titles, publishers, ISSNs, and metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchByTitle(string $title, int $limit = 6): array
    {
        $cleanTitle = trim($title);
        if (mb_strlen($cleanTitle) < 3) {
            return [];
        }

        $candidates = [];
        $seenIssns = [];

        // 1. Query Crossref journals API
        try {
            $resp = $this->httpClient()->get('https://api.crossref.org/journals', [
                'query' => $cleanTitle,
                'rows' => $limit,
            ]);

            if ($resp->successful()) {
                $items = $resp->json('message.items') ?? [];
                foreach ($items as $item) {
                    $rawTitle = is_array($item['title'] ?? null) ? ($item['title'][0] ?? '') : ($item['title'] ?? '');
                    $itemTitle = self::cleanTitle($rawTitle);
                    $publisher = $item['publisher'] ?? null;
                    $eIssn = null;
                    $pIssn = null;

                    $issnTypes = $item['issn-type'] ?? [];
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

                    $issnList = $item['ISSN'] ?? [];
                    if (is_array($issnList)) {
                        foreach ($issnList as $val) {
                            $f = IssnHelper::format($val);
                            if (! $eIssn && $f !== $pIssn) {
                                $eIssn = $f;
                            } elseif (! $pIssn && $f !== $eIssn) {
                                $pIssn = $f;
                            }
                        }
                    }

                    if ($eIssn && ! ValidIssn::isValid($eIssn)) {
                        $eIssn = null;
                    }
                    if ($pIssn && ! ValidIssn::isValid($pIssn)) {
                        $pIssn = null;
                    }
                    if ($eIssn && $pIssn && $eIssn === $pIssn) {
                        $pIssn = null;
                    }
                    if (! $eIssn && $pIssn) {
                        $eIssn = $pIssn;
                        $pIssn = null;
                    }

                    $primaryIssn = $eIssn ?: $pIssn;
                    if ($primaryIssn && ! isset($seenIssns[$primaryIssn])) {
                        $seenIssns[$primaryIssn] = true;
                        $candidates[] = [
                            'title' => $itemTitle,
                            'publisher' => $publisher,
                            'e_issn' => $eIssn,
                            'p_issn' => $pIssn,
                            'primary_issn' => $primaryIssn,
                            'country_code' => null,
                            'country_name' => null,
                            'country_id' => null,
                            'website_url' => null,
                            'is_oa' => false,
                            'source' => 'Crossref',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Crossref title search failed: '.$e->getMessage());
        }

        // 2. Query OpenAlex sources API
        try {
            $resp = $this->httpClient()->get('https://api.openalex.org/sources', [
                'search' => $cleanTitle,
                'per-page' => $limit,
            ]);

            if ($resp->successful()) {
                $results = $resp->json('results') ?? [];
                foreach ($results as $item) {
                    $itemTitle = self::cleanTitle($item['display_name'] ?? '');
                    $publisher = $item['host_organization_name'] ?? null;
                    $homepage = $item['homepage_url'] ?? null;
                    $countryCode = $item['country_code'] ?? null;
                    $countryInfo = self::resolveCountry($countryCode);
                    $issns = $item['issn'] ?? [];
                    $eIssn = null;
                    $pIssn = null;

                    if (is_array($issns)) {
                        $eIssn = isset($issns[0]) ? IssnHelper::format($issns[0]) : null;
                        $pIssn = isset($issns[1]) ? IssnHelper::format($issns[1]) : null;
                    }

                    if ($eIssn && ! ValidIssn::isValid($eIssn)) {
                        $eIssn = null;
                    }
                    if ($pIssn && ! ValidIssn::isValid($pIssn)) {
                        $pIssn = null;
                    }
                    if ($eIssn && $pIssn && $eIssn === $pIssn) {
                        $pIssn = null;
                    }
                    if (! $eIssn && $pIssn) {
                        $eIssn = $pIssn;
                        $pIssn = null;
                    }

                    $primaryIssn = $eIssn ?: $pIssn;
                    if ($primaryIssn) {
                        if (isset($seenIssns[$primaryIssn])) {
                            foreach ($candidates as &$cand) {
                                if (($cand['primary_issn'] ?? '') === $primaryIssn) {
                                    $cand['country_code'] = $cand['country_code'] ?: $countryCode;
                                    $cand['country_name'] = $cand['country_name'] ?: $countryInfo['name'];
                                    $cand['country_id'] = $cand['country_id'] ?: $countryInfo['id'];
                                    $cand['website_url'] = $cand['website_url'] ?: $homepage;
                                    $cand['is_oa'] = $cand['is_oa'] ?: (! empty($item['is_oa']));
                                }
                            }
                            unset($cand);
                        } else {
                            $seenIssns[$primaryIssn] = true;
                            $candidates[] = [
                                'title' => $itemTitle,
                                'publisher' => $publisher,
                                'e_issn' => $eIssn,
                                'p_issn' => $pIssn,
                                'primary_issn' => $primaryIssn,
                                'country_code' => $countryCode,
                                'country_name' => $countryInfo['name'],
                                'country_id' => $countryInfo['id'],
                                'website_url' => $homepage,
                                'is_oa' => ! empty($item['is_oa']),
                                'source' => 'OpenAlex',
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OpenAlex title search failed: '.$e->getMessage());
        }

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Decode Cloudflare-obfuscated email address from data-cfemail hex attribute.
     */
    public static function decodeCloudflareEmail(string $hex): string
    {
        if (strlen($hex) < 4) {
            return '';
        }
        $k = hexdec(substr($hex, 0, 2));
        $str = '';
        for ($i = 2; $i < strlen($hex); $i += 2) {
            $str .= chr(hexdec(substr($hex, $i, 2)) ^ $k);
        }

        return filter_var($str, FILTER_VALIDATE_EMAIL) ? $str : '';
    }

    /**
     * Deep scrape journal website for contact info, editorial team, location/address, indexing agencies, and subjects.
     *
     * @return array<string, mixed>
     */
    public function scrapeJournalWebsite(?string $websiteUrl, ?string $journalTitle = null): array
    {
        if (empty($websiteUrl) || ! filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
            return [];
        }

        try {
            $client = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->timeout(5);

            $baseUrl = rtrim($websiteUrl, '/');
            $homeResp = $client->get($websiteUrl);
            if (! $homeResp->successful()) {
                return [];
            }

            $homeHtml = $homeResp->body();

            // Discover contact, indexing, and about links
            $contactUrl = null;
            $indexingUrl = null;
            $editorialUrl = null;
            $aboutUrl = null;

            if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $homeHtml, $links)) {
                foreach ($links[1] as $idx => $href) {
                    $text = strtolower(strip_tags($links[2][$idx] ?? ''));
                    $hrefLower = strtolower($href);
                    if (! $contactUrl && (str_contains($text, 'contact') || str_contains($hrefLower, 'contact'))) {
                        $contactUrl = str_starts_with($href, 'http') ? $href : $baseUrl.'/'.ltrim($href, '/');
                    }
                    if (! $indexingUrl && (str_contains($text, 'indexing') || str_contains($hrefLower, 'indexing') || str_contains($text, 'abstracting'))) {
                        $indexingUrl = str_starts_with($href, 'http') ? $href : $baseUrl.'/'.ltrim($href, '/');
                    }
                    if (! $editorialUrl && (str_contains($text, 'editorial board') || str_contains($text, 'editorial team') || str_contains($hrefLower, 'board') || str_contains($hrefLower, 'editorial-board') || str_contains($hrefLower, 'editorial_board') || str_contains($hrefLower, 'editors'))) {
                        $editorialUrl = str_starts_with($href, 'http') ? $href : $baseUrl.'/'.ltrim($href, '/');
                    }
                    if (! $aboutUrl && (str_contains($text, 'about') || str_contains($hrefLower, 'about'))) {
                        $aboutUrl = str_starts_with($href, 'http') ? $href : $baseUrl.'/'.ltrim($href, '/');
                    }
                }
            }

            // Fallback for common editorial board URL paths
            if (! $editorialUrl) {
                $editorialUrl = $baseUrl.'/board';
            }

            $contactHtml = '';
            if ($contactUrl) {
                try {
                    $cResp = $client->get($contactUrl);
                    if ($cResp->successful()) {
                        $contactHtml = $cResp->body();
                    }
                } catch (\Throwable) {
                }
            }

            $editorialHtml = '';
            if ($editorialUrl) {
                try {
                    $eResp = $client->get($editorialUrl);
                    if ($eResp->successful()) {
                        $editorialHtml = $eResp->body();
                    }
                } catch (\Throwable) {
                }
            }

            $indexingHtml = '';
            if ($indexingUrl) {
                try {
                    $iResp = $client->get($indexingUrl);
                    if ($iResp->successful()) {
                        $indexingHtml = $iResp->body();
                    }
                } catch (\Throwable) {
                }
            }

            $editorName = null;
            $editorEmail = null;
            $editorMobile = null;

            // 1. Priority: Isolate Editor-in-Chief section for 100% verified accuracy
            $eicSection = '';
            $combinedEditorial = ($editorialHtml ?: '').' '.($homeHtml ?: '').' '.($contactHtml ?: '');
            if (preg_match('/(?:Editor-in-Chief|Chief\s+Editor|Editor\s+in\s+Chief)(.*?)(?:Managing\s+Editor|Associate\s+Editor|Executive\s+Editor|Editorial\s+Board\s+Members|Editorial\s+Board|<\/table>)/is', $combinedEditorial, $eicMatch)) {
                $eicSection = $eicMatch[1];
            }

            if (! empty($eicSection)) {
                // Name
                if (preg_match('/<b[^>]*>([A-Za-z\.\s]{3,50})<\/b>/i', $eicSection, $nm)) {
                    $editorName = trim(strip_tags($nm[1]));
                } elseif (preg_match('/alt=["\']([A-Za-z\.\s]{3,50})["\']/i', $eicSection, $nm)) {
                    $editorName = trim($nm[1]);
                } elseif (preg_match('/(?:<strong>|<b>)?(Dr\.?\s+[A-Za-z\.\s]{2,40}|Prof(?:essor)?\.?\s+[A-Za-z\.\s]{2,40})/i', $eicSection, $nm)) {
                    $editorName = trim(strip_tags($nm[1]));
                }

                // Email (Cloudflare protected or regular)
                if (preg_match('/data-cfemail=["\']([a-fA-F0-9]+)["\']/', $eicSection, $cf)) {
                    $dec = self::decodeCloudflareEmail($cf[1]);
                    if ($dec) {
                        $editorEmail = strtolower($dec);
                    }
                } elseif (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $eicSection, $em)) {
                    $editorEmail = strtolower($em[0]);
                }

                // Phone / Mobile
                if (preg_match('/(?:Phone|Mobile|Tel)[:\s\.\-]*<[^>]*>[:\s\.\-]*(\+?[0-9]{1,4}[\s\-]?[0-9]{5,12})/i', $eicSection, $pm)) {
                    $editorMobile = trim($pm[1]);
                } elseif (preg_match('/(?:Phone|Mobile|Tel)[:\s\.\-]*(\+?[0-9]{1,4}[\s\-]?[0-9]{5,12})/i', $eicSection, $pm)) {
                    $editorMobile = trim($pm[1]);
                }
            }

            // Fallback for email, phone, editor name from contact & general pages
            $searchHtml = ($editorialHtml ?: '').' '.($contactHtml ?: '').' '.($homeHtml ?: '').' '.($indexingHtml ?: '');

            if (empty($editorEmail)) {
                $emails = [];
                if (preg_match_all('/data-cfemail=["\']([a-fA-F0-9]+)["\']/', $searchHtml, $cfMatches)) {
                    foreach ($cfMatches[1] as $cf) {
                        $dec = self::decodeCloudflareEmail($cf);
                        if ($dec) {
                            $emails[] = strtolower($dec);
                        }
                    }
                }
                if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $searchHtml, $stdMatches)) {
                    foreach ($stdMatches[0] as $em) {
                        $emLower = strtolower($em);
                        if (! str_contains($emLower, 'example.com') && ! str_contains($emLower, 'sentry') && ! str_contains($emLower, 'domain.com') && ! str_contains($emLower, 'w3.org')) {
                            $emails[] = $emLower;
                        }
                    }
                }
                $editorEmail = array_values(array_unique($emails))[0] ?? null;
            }

            if (empty($editorMobile)) {
                if (preg_match('/(?:Tel|Phone|Mobile|Helpline)[:\s\.\-]*(\+?[0-9]{1,4}[\s\-]?[0-9]{5,12})/i', $searchHtml, $pMatch)) {
                    $editorMobile = trim($pMatch[1]);
                }
            }

            if (empty($editorName)) {
                if (preg_match('/(?:<div><strong>|<strong>)?(Dr\.?\s+[A-Z][a-zA-Z\.\s]{2,30}|Prof(?:essor)?\.?\s+[A-Z][a-zA-Z\.\s]{2,30})\s*(?:\(|<|\n|,|Managing|Editor)/i', $contactHtml ?: $homeHtml, $m)) {
                    $editorName = trim(strip_tags($m[1]));
                }
                if (! $editorName && preg_match('/(?:Editor-in-Chief|Managing Editor|Chief Editor|Contact Person)[:\s\.\-]*([A-Z][a-zA-Z\.\s]{3,35})/i', $contactHtml ?: $homeHtml, $m)) {
                    $editorName = trim(strip_tags($m[1]));
                }
            }

            // 4. Publisher
            $publisher = null;
            if (preg_match('/Publisher[:\s\.\-]*<a[^>]*>(.*?)<\/a>/i', $contactHtml ?: $homeHtml, $m)) {
                $publisher = trim(strip_tags($m[1]));
            } elseif (preg_match('/Publisher[:\s\.\-]*([A-Z][a-zA-Z0-9\.\s&]{3,45})/i', $contactHtml ?: $homeHtml, $m)) {
                $publisher = trim(strip_tags($m[1]));
            }
            if ($publisher) {
                $publisher = trim(preg_replace('/[®™]/u', '', $publisher));
            }

            // 5. Address, City, State, Zipcode
            $address = null;
            $zipcode = null;
            $city = null;
            $state = null;

            if (preg_match('/(?:H\.?Q\.?|Headquarters|Office Address|Address)[:\s\.\-]*<\/strong>\s*([^<\n\r]{10,120})/i', $contactHtml ?: $homeHtml, $m)) {
                $address = rtrim(trim(strip_tags($m[1])), '.');
            } elseif (preg_match('/(?:H\.?Q\.?|Headquarters|Office Address|Postal Address|Office|Address)[:\s\.\-]*([^<\n\r]{10,120})/i', $contactHtml ?: $homeHtml, $m)) {
                $address = rtrim(trim(strip_tags($m[1])), '.');
            }

            if ($address) {
                if (preg_match('/\b([1-9][0-9]{5})\b/', $address, $zm)) {
                    $zipcode = $zm[1];
                }
                if (preg_match('/(?:Delhi|New Delhi|Mumbai|Kolkata|Chennai|Bengaluru|Bangalore|Hyderabad|Ahmedabad|Pune|Chandigarh|Jaipur|Lucknow)/i', $address, $cm)) {
                    $city = ucfirst(strtolower($cm[0]));
                    if (strcasecmp($city, 'Delhi') === 0 || strcasecmp($city, 'New Delhi') === 0) {
                        $state = 'Delhi';
                    }
                }
            }

            // 6. Indexing Agencies Detection
            $detectedAgencies = [];
            $activeAgencies = IndexingAgency::getCachedActive();
            $rawCombined = ($indexingHtml ?: '').' '.($homeHtml ?: '');
            $spacedHtml = preg_replace('/<[^>]+>/', ' ', $rawCombined);
            $indexingText = preg_replace('/\s+/', ' ', strip_tags($spacedHtml));

            foreach ($activeAgencies as $agency) {
                $agName = $agency['name'];
                $agKey = $agency['key'];
                if (preg_match('/\b'.preg_quote($agName, '/').'\b/i', $indexingText)) {
                    $urlMatch = '';
                    if (! empty($indexingHtml) && preg_match('/'.preg_quote($agName, '/').'[^<]*?<a[^>]+href=["\']([^"\']+)["\']/is', $indexingHtml, $uM)) {
                        $urlMatch = $uM[1];
                    }
                    $detectedAgencies[$agKey] = [
                        'name' => $agName,
                        'key' => $agKey,
                        'url' => $urlMatch,
                        'checked' => true,
                    ];
                }
            }

            // 7. Subjects extraction from journal title or website keywords
            $subjects = [];
            if ($journalTitle) {
                $knownSubjects = [
                    'Orthopaedics' => ['orthop', 'bone', 'joint', 'fracture'],
                    'Surgery' => ['surg'],
                    'Traumatology' => ['trauma'],
                    'Medicine' => ['medic', 'clinical'],
                    'Pharmacology' => ['pharmac', 'therap', 'toxicolog'],
                    'Pediatrics' => ['pediatr', 'paediatr'],
                    'Oncology' => ['oncol', 'cancer'],
                    'Cardiology' => ['cardio', 'heart'],
                    'Neurology' => ['neuro', 'brain'],
                    'Computer Science' => ['comput', 'software', 'artificial intelligence', 'information technology'],
                    'Engineering' => ['engineer'],
                ];
                foreach ($knownSubjects as $subj => $keywords) {
                    foreach ($keywords as $kw) {
                        if (stripos($journalTitle, $kw) !== false) {
                            $subjects[] = $subj;
                            break;
                        }
                    }
                }
            }

            return [
                'editor_name' => $editorName,
                'editor_email' => $editorEmail,
                'editor_mobile' => $editorMobile,
                'publisher' => $publisher,
                'address' => $address,
                'zipcode' => $zipcode,
                'city' => $city,
                'state' => $state,
                'indexing_agencies' => $detectedAgencies,
                'subjects' => array_values(array_unique($subjects)),
            ];
        } catch (\Throwable $e) {
            Log::debug('Journal website scraping failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Enrich a verification result with deep website metadata (contact, address, indexing, subjects).
     */
    protected function enrichResultWithWebsite(JournalVerificationResult $result): JournalVerificationResult
    {
        if (empty($result->websiteUrl)) {
            return $result;
        }

        $site = $this->scrapeJournalWebsite($result->websiteUrl, $result->title);
        if (empty($site)) {
            return $result;
        }

        $publisher = $result->publisher;
        if (! empty($site['publisher']) && (empty($publisher) || strcasecmp($publisher, 'Medical Journals') === 0)) {
            $publisher = $site['publisher'];
        }

        $subjects = $result->subjects;
        if (! empty($site['subjects'])) {
            foreach ($site['subjects'] as $s) {
                if (! in_array($s, $subjects, true)) {
                    $subjects[] = $s;
                }
            }
        }

        $countryId = $result->countryId;
        $stateName = $site['state'] ?? $result->state;
        $cityName = $site['city'] ?? $result->city;
        $stateId = $result->stateId;
        $cityId = $result->cityId;

        if (! empty($countryId) && ! empty($stateName) && empty($stateId)) {
            $states = State::getCachedByCountry($countryId);
            foreach ($states as $s) {
                if (strcasecmp($s['name'], $stateName) === 0) {
                    $stateId = (string) $s['id'];
                    break;
                }
            }
        }

        if (! empty($stateId) && ! empty($cityName) && empty($cityId)) {
            $cities = City::getCachedByState($stateId);
            foreach ($cities as $c) {
                if (strcasecmp($c['name'], $cityName) === 0) {
                    $cityId = (string) $c['id'];
                    break;
                }
            }
        }

        return JournalVerificationResult::success(
            source: $result->source ?? 'auto',
            sourceLabel: $result->sourceLabel ?? 'Official Registry',
            title: $result->title,
            publisher: $publisher,
            eIssn: $result->eIssn,
            pIssn: $result->pIssn,
            websiteUrl: $result->websiteUrl,
            firstPublicationYear: $result->firstPublicationYear,
            countryCode: $result->countryCode,
            countryId: $countryId,
            countryName: $result->countryName,
            isOa: $result->isOa,
            hasDoi: $result->hasDoi,
            languages: $result->languages,
            issnAssigneeCountry: $result->issnAssigneeCountry,
            description: $result->description,
            message: $result->message,
            raw: $result->raw,
            journalShortName: $result->journalShortName,
            issueFrequency: $result->issueFrequency,
            subjects: $subjects,
            editorName: $site['editor_name'] ?? $result->editorName,
            editorEmail: $site['editor_email'] ?? $result->editorEmail,
            editorMobile: $site['editor_mobile'] ?? $result->editorMobile,
            address: $site['address'] ?? $result->address,
            zipcode: $site['zipcode'] ?? $result->zipcode,
            city: $cityName,
            state: $stateName,
            stateId: $stateId,
            cityId: $cityId,
            indexingAgencies: ! empty($site['indexing_agencies']) ? $site['indexing_agencies'] : $result->indexingAgencies
        );
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
