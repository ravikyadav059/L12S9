<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LinkPreviewService
{
    /**
     * Fetch metadata (OpenGraph & Meta tags) from a given URL.
     *
     * @return array{success: bool, url: string, title?: string, description?: string, image?: string, domain?: string, error?: string}
     */
    public function fetch(string $url): array
    {
        $cleanUrl = trim($url);

        if (! filter_var($cleanUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'url' => $cleanUrl,
                'error' => 'Invalid URL format',
            ];
        }

        // Cache preview data for 24 hours to avoid redundant requests
        $cacheKey = 'link_preview_'.md5($cleanUrl);

        return Cache::remember($cacheKey, 86400, function () use ($cleanUrl) {
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->get($cleanUrl);

                if (! $response->successful()) {
                    return [
                        'success' => false,
                        'url' => $cleanUrl,
                        'error' => 'Unable to fetch URL (HTTP '.$response->status().')',
                    ];
                }

                $html = $response->body();
                if (empty($html)) {
                    return [
                        'success' => false,
                        'url' => $cleanUrl,
                        'error' => 'Empty response from URL',
                    ];
                }

                return $this->parseHtml($html, $cleanUrl);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'url' => $cleanUrl,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Parse HTML string to extract metadata.
     *
     * @return array{success: bool, url: string, title: string, description: string, image: string, domain: string}
     */
    protected function parseHtml(string $html, string $url): array
    {
        $domain = parse_url($url, PHP_URL_HOST) ?? 'website';
        $domain = preg_replace('/^www\./i', '', $domain);

        libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($doc);

        // 1. Extract Title
        $title = $this->extractXpathValue($xpath, [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//meta[@name="title"]/@content',
            '//title',
        ]);

        if (empty($title)) {
            $title = ucfirst($domain);
        }

        // 2. Extract Description
        $description = $this->extractXpathValue($xpath, [
            '//meta[@property="og:description"]/@content',
            '//meta[@name="twitter:description"]/@content',
            '//meta[@name="description"]/@content',
        ]);

        if (empty($description)) {
            $description = 'Visit '.$domain.' to learn more.';
        }

        // 3. Extract Image
        $image = $this->extractXpathValue($xpath, [
            '//meta[@property="og:image"]/@content',
            '//meta[@property="og:image:url"]/@content',
            '//meta[@name="twitter:image"]/@content',
            '//link[@rel="image_src"]/@href',
            '//link[@rel="apple-touch-icon"]/@href',
            '//link[@rel="icon"]/@href',
            '//link[@rel="shortcut icon"]/@href',
        ]);

        if (! empty($image)) {
            $image = $this->resolveUrl($image, $url);
        } else {
            // Default placeholder avatar based on domain
            $image = 'https://www.google.com/s2/favicons?domain='.$domain.'&sz=128';
        }

        libxml_clear_errors();

        return [
            'success' => true,
            'url' => $url,
            'title' => Str::limit(trim($title), 100),
            'description' => Str::limit(trim(preg_replace('/\s+/', ' ', $description)), 160),
            'image' => $image,
            'domain' => strtolower($domain),
        ];
    }

    /**
     * Query XPath for the first matching node text or attribute value.
     *
     * @param  list<string>  $expressions
     */
    protected function extractXpathValue(DOMXPath $xpath, array $expressions): ?string
    {
        foreach ($expressions as $expr) {
            $nodes = $xpath->query($expr);
            if ($nodes && $nodes->length > 0) {
                $val = trim($nodes->item(0)->nodeValue ?? '');
                if (! empty($val)) {
                    return $val;
                }
            }
        }

        return null;
    }

    /**
     * Resolve relative image URLs to absolute URLs.
     */
    protected function resolveUrl(string $path, string $baseUrl): string
    {
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return Str::startsWith($path, '//') ? 'https:'.$path : $path;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if (Str::startsWith($path, '/')) {
            return $scheme.'://'.$host.$path;
        }

        $basePath = isset($parts['path']) ? dirname($parts['path']) : '';
        $basePath = rtrim($basePath, '/');

        return $scheme.'://'.$host.($basePath ? $basePath.'/' : '/').$path;
    }
}
