<?php

use App\Models\IndexingAgency;
use App\Services\JournalVerificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('cleanTitle strips ISSN prefix and portal prefix correctly', function () {
    expect(JournalVerificationService::cleanTitle('The ISSN Portal | ISSN 2395-1958 - International journal of orthopaedics sciences'))
        ->toBe('International Journal of Orthopaedics Sciences');

    expect(JournalVerificationService::cleanTitle('ISSN 2049-3630 - BMJ Open.'))
        ->toBe('BMJ Open');

    expect(JournalVerificationService::cleanTitle('The ISSN Portal | Nature communications.'))
        ->toBe('Nature Communications');
});

test('rejects invalid checksum without making any HTTP request', function () {
    Http::fake();

    $service = new JournalVerificationService;
    $result = $service->verify('1234-5678', 'auto'); // Checksum is invalid (expected 9)

    expect($result->isValid)->toBeFalse()
        ->and($result->source)->toBe('checksum')
        ->and($result->message)->toContain('invalid format or checksum digit');

    Http::assertNothingSent();
});

test('verifies valid ISSN using Crossref API', function () {
    Http::fake([
        'https://api.crossref.org/journals/2041-1723' => Http::response([
            'status' => 'ok',
            'message-type' => 'journal',
            'message' => [
                'title' => 'Nature Communications',
                'publisher' => 'Springer Nature',
                'ISSN' => ['2041-1723'],
                'issn-type' => [
                    ['value' => '2041-1723', 'type' => 'electronic'],
                ],
            ],
        ], 200),
    ]);

    $service = new JournalVerificationService;
    $result = $service->verify('2041-1723', 'crossref');

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('crossref')
        ->and($result->title)->toBe('Nature Communications')
        ->and($result->publisher)->toBe('Springer Nature')
        ->and($result->eIssn)->toBe('2041-1723');
});

test('verifies valid ISSN using OpenAlex API', function () {
    Http::fake([
        'https://api.openalex.org/sources*' => Http::response([
            'meta' => ['count' => 1],
            'results' => [
                [
                    'display_name' => 'Journal of Machine Learning Research',
                    'host_organization_name' => 'Microtome Publishing',
                    'issn' => ['1532-4435', '1533-7928'],
                    'homepage_url' => 'http://jmlr.org/',
                ],
            ],
        ], 200),
    ]);

    $service = new JournalVerificationService;
    $result = $service->verify('1532-4435', 'openalex');

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('openalex')
        ->and($result->title)->toBe('Journal of Machine Learning Research')
        ->and($result->publisher)->toBe('Microtome Publishing')
        ->and($result->websiteUrl)->toBe('http://jmlr.org/');
});

test('verifies valid ISSN using DOAJ API', function () {
    Http::fake([
        'https://doaj.org/api/v2/search/journals/*' => Http::response([
            'total' => 1,
            'results' => [
                [
                    'bibjson' => [
                        'title' => 'Open Access Journal of Science',
                        'publisher' => ['name' => 'MedCrave'],
                        'eissn' => '2575-8772',
                        'pissn' => null,
                        'link' => [
                            ['type' => 'homepage', 'url' => 'https://medcraveonline.com/OAJS/'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new JournalVerificationService;
    $result = $service->verify('2575-8772', 'doaj');

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('doaj')
        ->and($result->title)->toBe('Open Access Journal of Science')
        ->and($result->publisher)->toBe('MedCrave')
        ->and($result->websiteUrl)->toBe('https://medcraveonline.com/OAJS/');
});

test('verifies valid ISSN using Official ISSN Portal with rich metadata and real journal website', function () {
    Http::fake([
        'https://portal.issn.org/resource/ISSN/2395-1958?format=json' => Http::response(
            '<title>The ISSN Portal | International journal of orthopaedics sciences.</title>'.
            '<dd data-key="abbreviated-key-title">Int. j. orthop. sci.</dd>'.
            '<dd data-key="earliest-publisher">Delhi: Medical Journals</dd>'.
            '<dd data-key="frequency">Quarterly</dd>'.
            '<dd data-key="country">INDIA</dd>'.
            '<dd data-key="language">English</dd>'.
            '<dd data-key="resource-link"><a href="http://www.orthopaper.com">www.orthopaper.com</a></dd>',
            200
        ),
        'https://portal.issn.org/resource/ISSN/2395-1958*' => function ($request) {
            $accept = is_array($request->header('Accept')) ? implode(', ', $request->header('Accept')) : (string) $request->header('Accept');
            if (str_contains($accept, 'ld+json')) {
                return Http::response([
                    'mainTitle' => 'International journal of orthopaedics sciences.',
                    'url' => ['http://www.orthopaper.com'],
                    'publication' => [
                        [
                            'location' => [['label' => 'India']],
                            'publishedBy' => ['name' => 'Medical Journals'],
                        ],
                    ],
                    'spatial' => ['https://www.iso.org/obp/ui/#iso:code:3166:IN'],
                    'mainEntityOf' => [
                        'generatedBy' => ['created' => '2015-05-01'],
                    ],
                    'format' => 'medium:Online',
                ], 200);
            }

            return Http::response(
                '<title>The ISSN Portal | International journal of orthopaedics sciences.</title>'.
                '<dd data-key="abbreviated-key-title">Int. j. orthop. sci.</dd>'.
                '<dd data-key="earliest-publisher">Delhi: Medical Journals</dd>'.
                '<dd data-key="frequency">Quarterly</dd>'.
                '<dd data-key="country">INDIA</dd>'.
                '<dd data-key="language">English</dd>'.
                '<dd data-key="resource-link"><a href="http://www.orthopaper.com">www.orthopaper.com</a></dd>',
                200
            );
        },
    ]);

    $service = new JournalVerificationService;
    $result = $service->verify('2395-1958', 'issn_portal');

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('issn_portal')
        ->and($result->title)->toBe('International Journal of Orthopaedics Sciences')
        ->and($result->publisher)->toBe('AkiNik Publications')
        ->and($result->websiteUrl)->toBe('http://www.orthopaper.com')
        ->and($result->websiteUrl)->not->toContain('portal.issn.org')
        ->and($result->firstPublicationYear)->toBe(2015)
        ->and($result->journalShortName)->toBe('Int. j. orthop. sci.')
        ->and($result->issueFrequency)->toBe('Quarterly')
        ->and($result->languages)->toContain('English');
});

test('auto mode falls back to secondary registry when primary fails', function () {
    Http::fake([
        'https://api.crossref.org/journals/*' => Http::response(['message' => null], 404),
        'https://api.openalex.org/sources*' => Http::response([
            'meta' => ['count' => 1],
            'results' => [
                [
                    'display_name' => 'Fallback Journal from OpenAlex',
                    'host_organization_name' => 'Academic Press',
                    'issn' => ['2049-3630'],
                ],
            ],
        ], 200),
    ]);

    $service = new JournalVerificationService;
    $result = $service->verify('2049-3630', 'auto');

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('openalex')
        ->and($result->title)->toBe('Fallback Journal from OpenAlex');
});

test('auto mode fails gracefully when no registry has the ISSN', function () {
    Http::fake([
        'https://api.crossref.org/journals/*' => Http::response(['message' => null], 404),
        'https://api.openalex.org/sources*' => Http::response(['meta' => ['count' => 0], 'results' => []], 200),
        'https://doaj.org/api/v2/search/journals/*' => Http::response(['total' => 0, 'results' => []], 200),
        'https://portal.issn.org/resource/ISSN/*' => Http::response('', 404),
    ]);

    $service = new JournalVerificationService;
    // 2049-3630 has a valid checksum digit
    $result = $service->verify('2049-3630', 'auto');

    expect($result->isValid)->toBeFalse()
        ->and($result->source)->toBe('auto')
        ->and($result->message)->toContain('could not be found in any official registries');
});

test('scrapeJournalWebsite decodes cloudflare emails, extracts editor, publisher, address, and indexing agencies', function () {
    // Cloudflare email for "ortho.manuscript@gmail.com":
    // 'ortho.manuscript@gmail.com' XOR key 0x2f
    $cfHex = '2f405d5b474001424e415a5c4c5d465f5b6f48424e4643014c4042';

    Http::fake([
        'http://www.orthopaper.com' => Http::response(
            '<html><body>'.
            '<h1>International Journal of Orthopaedics Sciences</h1>'.
            '<a href="/contact">Contact Us</a>'.
            '<a href="/board">Editorial Board</a>'.
            '<a href="/indexing">Indexing</a>'.
            '</body></html>',
            200
        ),
        'http://www.orthopaper.com/board' => Http::response(
            '<table><tr><th>Editor-in-Chief</th></tr>'.
            '<tr><td><img alt="Dr. Amit Nandan Mishra"></td>'.
            '<td><b>Dr. Amit Nandan Mishra</b> (M.S. (Orthopaedics))<br>'.
            'Email: <a class="__cf_email__" data-cfemail="5336373a273c21133c21273b3c23322336217d303c3e">[email&#160;protected]</a><br>'.
            'Phone: +919919604383</td></tr>'.
            '<tr><th>Managing Editor</th></tr>'.
            '<tr><td><b>Dr. Shishir Kumar</b></td></tr></table>',
            200
        ),
        'http://www.orthopaper.com/contact' => Http::response(
            '<html><body>'.
            '<div><strong>Dr Shishir Kumar (</strong>Managing Editor<strong>)</strong></div>'.
            '<div>Publisher: <a href="https://www.akinik.com/">AkiNik Publications ®</a></div>'.
            '<strong>H.Q.:</strong> C-11/169, Sector-3, Rohini, Delhi-110085, India.'.
            '<div>Tel: +91-9711224068</div>'.
            '<div>Email: <a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="'.$cfHex.'">[email&#160;protected]</a></div>'.
            '</body></html>',
            200
        ),
        'http://www.orthopaper.com/indexing' => Http::response(
            '<html><body>'.
            '<h2>Indexing Links:</h2>'.
            '<p>Crossref: <a href="https://crossref.org">Click here</a></p>'.
            '<p>Embase: <a href="https://embase.com">Click here</a></p>'.
            '<p>Google Scholar: <a href="https://scholar.google.com">Click here</a></p>'.
            '</body></html>',
            200
        ),
    ]);

    Cache::put(IndexingAgency::CACHE_KEY, [
        ['name' => 'Crossref', 'key' => 'Crossref', 'image' => null],
        ['name' => 'Embase', 'key' => 'Embase', 'image' => null],
    ], 3600);

    $service = new JournalVerificationService;
    $scraped = $service->scrapeJournalWebsite('http://www.orthopaper.com', 'International Journal of Orthopaedics Sciences');

    expect($scraped['editor_name'])->toBe('Dr. Amit Nandan Mishra')
        ->and($scraped['publisher'])->toBe('AkiNik Publications')
        ->and($scraped['editor_email'])->toBe('editor@orthopaper.com')
        ->and($scraped['editor_mobile'])->toBe('+919919604383')
        ->and($scraped['address'])->toBe('C-11/169, Sector-3, Rohini, Delhi-110085, India')
        ->and($scraped['zipcode'])->toBe('110085')
        ->and($scraped['city'])->toBe('Delhi')
        ->and($scraped['state'])->toBe('Delhi')
        ->and($scraped['indexing_agencies'])->toHaveKey('Crossref')
        ->and($scraped['indexing_agencies'])->toHaveKey('Embase')
        ->and($scraped['subjects'])->toContain('Orthopaedics');
});
