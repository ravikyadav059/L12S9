<?php

use App\Services\JournalVerificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('cleanTitle strips ISSN prefix and portal prefix correctly', function () {
    expect(JournalVerificationService::cleanTitle('The ISSN Portal | ISSN 2395-1958 - International journal of orthopaedics sciences'))
        ->toBe('International Journal Of Orthopaedics Sciences');

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

test('verifies valid ISSN using Official ISSN Portal', function () {
    Http::fake([
        'https://portal.issn.org/resource/ISSN/2049-3630' => Http::response(
            '<title>The ISSN Portal | BMJ open.</title>',
            200
        ),
    ]);

    $service = new JournalVerificationService;
    $result = $service->verify('2049-3630', 'issn_portal');

    expect($result->isValid)->toBeTrue()
        ->and($result->source)->toBe('issn_portal')
        ->and($result->title)->toBe('BMJ Open');
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
