# Search and External-Service Caches

| Key pattern | TTL | Where used | Benefit | Invalidation |
| --- | --- | --- | --- | --- |
| `authors:search:{md5(lowercase query)}` | 5 minutes | Publication author autocomplete and API helper | Reuses prefix/fallback MongoDB searches for identical text | TTL only |
| `link_preview_{md5(url)}` | 24 hours for a successful preview; 60 seconds for a failed fetch | `LinkPreviewService` | Prevents repeat outbound HTTP, HTML download, and OpenGraph parsing while allowing a temporarily unavailable URL to recover quickly | TTL only |

Hashes make key components compact; neither key is user-specific. A successful link preview remains warm for one day. HTTP errors, empty responses, and thrown request exceptions are **negative-cached for only 60 seconds**, so a transient remote failure does not hide a recovered preview for a day.

The service now checks the cache before making its HTTP request, but it does not lock cold misses. Concurrent cold requests can therefore still make duplicate author searches or third-party URL fetches. Add a short lock or a queue/rate limit before treating link previews as safe for high-volume concurrent traffic.
