# PS3 — Secure media and R2 runbook

## Trust boundary

The presigned upload is an untrusted private object. Filename, declared MIME, byte count,
checksum and any browser dimensions are claims. A worker streams the object with a hard byte
limit, recomputes SHA-256, detects magic bytes, pings and decodes one frame under ImageMagick
resource limits, applies EXIF orientation, strips metadata, and re-encodes every public byte.
No external malware scanner is claimed: the configured policy is truthfully
`decode_reencode` for image-only uploads.

Only a `ready` intent has a `MediaAsset`. Public URLs are derived after all versioned JPEG,
WebP and AVIF variants have been written. Raw uploads remain under `_private/`; ready/rejected
raw objects are deleted immediately and scheduled cleanup supplies a second deletion pass.

## Input and failure matrix

| Input or failure | State | Retry | Raw object |
|---|---|---:|---|
| Valid single-frame JPEG/PNG/WebP/AVIF | `ready` | No | Deleted after publish |
| MIME spoof or unsupported magic | `rejected` | No | Deleted |
| Truncated/corrupt decode | `rejected` | No | Deleted |
| Size/SHA-256 mismatch | `rejected` | No | Deleted |
| Pixel/dimension/frame limit exceeded | `rejected` | No | Deleted |
| Storage/decoder dependency unavailable | `failed` | Explicit, max 3 | Retained up to 24h |
| Worker timeout/termination | `failed` | Explicit, max 3 | Retained up to 24h |

Failure records contain stable reason codes only. Decoder text, storage responses, credentials,
object contents and signed URLs are never persisted as failure details.

## Configuration and workers

The PHP runtime must load Imagick with JPEG, WebP and AVIF delegates. Production images install
`libmagickwand` and the PECL Imagick extension. The worker must consume the `media` queue with a
memory limit greater than `ROSTA_MEDIA_MEMORY_LIMIT_MB`.

Relevant non-secret settings:

- `ROSTA_MEDIA_MAX_SIZE_BYTES`, `ROSTA_MEDIA_MAX_PIXELS`, `ROSTA_MEDIA_MAX_WIDTH`,
  `ROSTA_MEDIA_MAX_HEIGHT`
- `ROSTA_MEDIA_MEMORY_LIMIT_MB`, `ROSTA_MEDIA_PROCESSING_TIMEOUT_MS`
- `ROSTA_MEDIA_VARIANT_VERSION`, `ROSTA_MEDIA_VARIANT_WIDTHS`
- `ROSTA_MEDIA_MAX_PROCESSING_ATTEMPTS`, `ROSTA_MEDIA_ORPHAN_RETENTION_HOURS`

Activation is fail-closed when the storage disk, HTTPS CDN URL, decode/re-encode policy, or any
of the JPEG/WebP/AVIF delegates is missing.

## Rollout and rollback-forward

1. Back up the database and apply the additive migration. It maps `pending` to `uploading` and
   `completed` to `ready`, while preserving existing `MediaAsset` rows with a nullable variant
   version.
2. Deploy API and `media` workers together. Keep `ROSTA_MEDIA_UPLOADS_ENABLED=false` until
   readiness confirms Imagick delegates, the private prefix policy and the public CDN.
3. Run `composer audit:media-storage`, the targeted media tests, `composer check`, and
   `php artisan rosta:staging-acceptance --json`.
4. Enable uploads, watch `failed`/`rejected` counts, processing latency, queue depth and orphan
   cleanup. Never automatically retry ambiguous worker failures.
5. Roll forward by disabling new uploads, fixing the worker/runtime, and using the owner-scoped
   retry endpoint for retryable intents. The migration down path is only for an unused rollout;
   it maps new states back to their legacy equivalents and cannot restore stripped metadata.

Scheduled cleanup commands:

```bash
php artisan media:expire-upload-intents --limit=500
php artisan media:cleanup-terminal --limit=500
```

R2 lifecycle should independently expire `_private/` objects after the operational retention
window and prune obsolete `published/.../<variant-version>/` prefixes only after database and
CDN references have migrated.
