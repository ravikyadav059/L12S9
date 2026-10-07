<?php

use App\Services\LinkPreviewService;
use Illuminate\Support\Facades\Http;

test('link preview api validates required url', function () {
    $response = $this->postJson(route('api.link-preview'), []);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['url']);
});

test('link preview api returns parsed opengraph metadata for a valid url', function () {
    Http::fake([
        'https://example.com*' => Http::response(
            '<html><head><title>Example Domain</title><meta property="og:title" content="Example Domain Title"><meta property="og:description" content="This is an example domain description."><meta property="og:image" content="https://example.com/og-image.png"></head><body><h1>Hello</h1></body></html>',
            200
        ),
    ]);

    $response = $this->postJson(route('api.link-preview'), [
        'url' => 'https://example.com/article',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'url' => 'https://example.com/article',
        'title' => 'Example Domain Title',
        'description' => 'This is an example domain description.',
        'image' => 'https://example.com/og-image.png',
        'domain' => 'example.com',
    ]);
});

test('link preview service falls back gracefully when page has minimal meta tags', function () {
    Http::fake([
        'https://simple.org*' => Http::response(
            '<html><head><title>Simple Website</title></head><body><p>Content</p></body></html>',
            200
        ),
    ]);

    $service = new LinkPreviewService;
    $result = $service->fetch('https://simple.org');

    expect($result['success'])->toBeTrue()
        ->and($result['title'])->toBe('Simple Website')
        ->and($result['domain'])->toBe('simple.org');
});
