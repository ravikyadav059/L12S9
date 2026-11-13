# Search and External-Service Caches

| Key pattern | TTL | Where used | Benefit | Invalidation |
| --- | --- | --- | --- | --- |
| `authors:search:{md5(lowercase query)}` | 5 minutes | Publication author autocomplete and API helper | Reuses prefix/fallback MongoDB searches for identical text | TTL only |
| `link_preview_{md5(url)}` | 24 hours | `LinkPreviewService` | Prevents repeat outbound HTTP, HTML download, and OpenGraph parsing | TTL only, including errors |

Hashes make key components compact; neither key is user-specific. Warm values prevent repeat work. Cold concurrent `remember` calls may repeat the author search or third-party URL fetch, so link previews should receive lock/rate-limit/negative-cache design before high-volume reliance.
