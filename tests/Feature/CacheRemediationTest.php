<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Designation;
use App\Models\IndexingAgency;
use App\Models\JournalRole;
use App\Models\Organization;
use App\Models\ReviewerJournal;
use App\Models\Skill;
use App\Models\State;
use App\Models\User;
use App\Services\LinkPreviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('link preview service does not poison cache for 24 hours on HTTP error', function () {
    Http::fake([
        'https://example.com/broken-article' => Http::sequence()
            ->push('Server Error', 500)
            ->push('<html><head><title>Fixed</title></head></html>', 200),
    ]);

    $service = new LinkPreviewService;
    $cacheKey = 'link_preview_'.md5('https://example.com/broken-article');

    Cache::forget($cacheKey);

    $result = $service->fetch('https://example.com/broken-article');

    expect($result['success'])->toBeFalse();
    expect(Cache::has($cacheKey))->toBeTrue();

    // Should return cached error within the 60s window (without consuming next fake response)
    $cachedResult = $service->fetch('https://example.com/broken-article');
    expect($cachedResult['success'])->toBeFalse();

    // After clearing cache (simulating 60s expiry), it recovers immediately with the second response
    Cache::forget($cacheKey);
    $recoveredResult = $service->fetch('https://example.com/broken-article');
    expect($recoveredResult['success'])->toBeTrue()
        ->and($recoveredResult['title'])->toBe('Fixed');
});

test('indexing agency forgetCached flushes all active list key variants', function () {
    Cache::put(IndexingAgency::CACHE_KEY, ['item1'], 3600);
    Cache::put('indexing_agencies:active:list', ['item1'], 3600);
    Cache::put('indexing_agencies_active_all', ['item1'], 3600);

    expect(Cache::has(IndexingAgency::CACHE_KEY))->toBeTrue()
        ->and(Cache::has('indexing_agencies:active:list'))->toBeTrue()
        ->and(Cache::has('indexing_agencies_active_all'))->toBeTrue();

    IndexingAgency::forgetCached();

    expect(Cache::has(IndexingAgency::CACHE_KEY))->toBeFalse()
        ->and(Cache::has('indexing_agencies:active:list'))->toBeFalse()
        ->and(Cache::has('indexing_agencies_active_all'))->toBeFalse();
});

test('country forgetCached flushes both countries:all and countries:all:list', function () {
    Cache::put(Country::CACHE_KEY, ['c1'], 3600);
    Cache::put('countries:all:list', ['c1'], 3600);

    Country::forgetCached();

    expect(Cache::has(Country::CACHE_KEY))->toBeFalse()
        ->and(Cache::has('countries:all:list'))->toBeFalse();
});

test('journal role forgetCached flushes both role key variants', function () {
    Cache::put(JournalRole::CACHE_KEY, ['r1'], 3600);
    Cache::put('journal_roles:active:list', ['r1'], 3600);

    JournalRole::forgetCached();

    expect(Cache::has(JournalRole::CACHE_KEY))->toBeFalse()
        ->and(Cache::has('journal_roles:active:list'))->toBeFalse();
});

test('user forgetCachedPublishers flushes both publisher key variants', function () {
    Cache::put(User::CACHE_KEY_PUBLISHERS, ['p1'], 3600);
    Cache::put('users:publishers:active:list', ['p1'], 3600);

    User::forgetCachedPublishers();

    expect(Cache::has(User::CACHE_KEY_PUBLISHERS))->toBeFalse()
        ->and(Cache::has('users:publishers:active:list'))->toBeFalse();
});

test('state and city forget helpers clear cascading lookup caches', function () {
    Cache::put('states_lookup_999', ['s1'], 3600);
    Cache::put('cities_lookup_888', ['c1'], 3600);

    State::forgetCachedCountry('999');
    City::forgetCachedState('888');

    expect(Cache::has('states_lookup_999'))->toBeFalse()
        ->and(Cache::has('cities_lookup_888'))->toBeFalse();
});

test('skill clearAvailableCache clears qa_available_skills', function () {
    Cache::put(Skill::AVAILABLE_CACHE_KEY, ['PHP', 'Laravel'], 3600);
    expect(Cache::has(Skill::AVAILABLE_CACHE_KEY))->toBeTrue();

    Skill::clearAvailableCache();
    expect(Cache::has(Skill::AVAILABLE_CACHE_KEY))->toBeFalse();
});

test('organization clearActiveCache clears paginated buffers and counts', function () {
    Cache::put('organizations:default:list', ['org1'], 3600);
    Cache::put('organizations:default:list:p:1:l:20', ['org1'], 3600);
    Cache::put('organizations:default:list:p:2:l:20', ['org2'], 3600);
    Cache::put('organizations:active:count', 42, 3600);

    Organization::clearActiveCache();

    expect(Cache::has('organizations:default:list'))->toBeFalse()
        ->and(Cache::has('organizations:default:list:p:1:l:20'))->toBeFalse()
        ->and(Cache::has('organizations:default:list:p:2:l:20'))->toBeFalse()
        ->and(Cache::has('organizations:active:count'))->toBeFalse();
});

test('organization getMetricsWithLock computes and caches metrics', function () {
    $orgId = 'test_org_123';
    Cache::forget('organizations:metrics:'.$orgId);

    $computed = Organization::getMetricsWithLock($orgId, function () {
        return [
            'current_scholars_count' => 10,
            'alumni_count' => 5,
        ];
    }, 60);

    expect($computed['current_scholars_count'])->toBe(10);
    expect(Cache::has('organizations:metrics:'.$orgId))->toBeTrue();

    // Verify cache return without running closure again
    $cached = Organization::getMetricsWithLock($orgId, function () {
        throw new Exception('Should not be called when cached');
    });

    expect($cached['current_scholars_count'])->toBe(10);
});

test('institution show view uses getMetricsWithLock on live page', function () {
    $org = Organization::create([
        'organization_name' => 'Stanford University Test '.uniqid(),
        'status' => 1,
    ]);

    $metrics = Organization::getMetricsWithLock((string) $org->_id, function () {
        return [
            'scholars' => 5,
            'alumni' => 2,
            'publications' => 12,
            'citations' => 45,
            'seminars' => 1,
            'scholar_uids' => [],
            'alumni_uids' => [],
            'pub_ids' => [],
        ];
    }, 1800);

    expect(Cache::has('organizations:metrics:'.(string) $org->_id))->toBeTrue();
    expect($metrics['scholars'])->toBe(5);
});

test('institution directory index uses getMetrics for each visible card', function () {
    $org = Organization::create([
        'organization_name' => 'Directory Org Test '.uniqid(),
        'status' => 1,
    ]);

    $metrics = Organization::getMetrics((string) $org->_id);

    expect(Cache::has('organizations:metrics:'.(string) $org->_id))->toBeTrue()
        ->and(array_key_exists('scholars', $metrics))->toBeTrue()
        ->and(array_key_exists('alumni', $metrics))->toBeTrue()
        ->and(array_key_exists('publications', $metrics))->toBeTrue()
        ->and(array_key_exists('citations', $metrics))->toBeTrue()
        ->and(array_key_exists('seminars', $metrics))->toBeTrue();
});

test('designation clearActiveCache clears paginated buffer pages', function () {
    Cache::put(Designation::CACHE_KEY_ACTIVE, ['d1'], 3600);
    Cache::put('designations:default:list', ['d1'], 3600);
    Cache::put('designations:default:list:p:1:l:20', ['d1'], 3600);
    Cache::put('designations:default:list:p:2:l:20', ['d2'], 3600);

    Designation::clearActiveCache();

    expect(Cache::has(Designation::CACHE_KEY_ACTIVE))->toBeFalse()
        ->and(Cache::has('designations:default:list'))->toBeFalse()
        ->and(Cache::has('designations:default:list:p:1:l:20'))->toBeFalse()
        ->and(Cache::has('designations:default:list:p:2:l:20'))->toBeFalse();
});

test('reviewer journal forgetCaches handles cache drivers gracefully', function () {
    Cache::put('journals:active:count', 10, 3600);
    ReviewerJournal::forgetCaches();

    expect(Cache::has('journals:active:count'))->toBeFalse();
});
