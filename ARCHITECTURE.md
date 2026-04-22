# Christube Architecture (Phase 4)

## Media Lifecycle
`draft/uploading -> processing -> ready|failed`

- `upload_sessions`: resumable upload state, ownership, expiry, progress bytes.
- `upload_chunks`: chunk records keyed by `(session, chunk_index)`.
- `videos`: canonical content record with processing and readiness fields.
- `media_assets`: source/playback/thumbnail references and technical metadata.
- `media_jobs`: queue-friendly background processing tasks.

## Storage Abstraction
Implemented in `includes/media.php`:
- `mediaConfig()` env-driven root/chunk limits
- `mediaPaths()` typed buckets (tmp/original/playback/thumb/derivatives)
- `mediaResolveAbsolutePath()` central local disk resolver
- `mediaAssetUrl()` centralized frontend URL generation

Design supports future additional disks (e.g., S3-compatible) by extending storage resolution logic.

## Upload Pipeline
1. `upload_start.php` validates auth+CSRF+constraints and opens `upload_sessions` record.
2. `upload_chunk.php` verifies owner + session and stores chunk file + DB entry.
3. `upload_finalize.php` assembles file with stream copy, validates MIME, creates source asset, video record, and queue job.

Idempotency support:
- finalize returns existing video if session already moved to processing/ready.
- chunk rows are upserted per chunk index.

## Background Processing
`worker_media.php` polls queued `process_video` jobs and performs:
- metadata probe (ffprobe)
- normalized playback generation (ffmpeg -> MP4)
- thumbnail extraction
- asset record creation and video status transition
- retry-ready failure handling (`queued` until max attempts, then `failed`)

## Asset Delivery Rules
`media_asset.php` resolves asset by id and enforces:
- public delivery only when `visibility=public` and `processing_status=ready`
- owner access to private/not-ready assets
- internal path never leaked to frontend

## Creator UX
- `index.php` now starts resumable chunk uploads from browser JS.
- Upload progress is surfaced per chunk and finalization status is shown.
- `uploads/index.php` shows per-video processing status/errors and media preview thumbnails.

## Moderation/Audit Hooks
- `audit_logs` table records upload session creation, finalize, and processing completion events.
- Video states gate visibility to prevent draft/failed/processing content from appearing public.
