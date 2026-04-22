# Christube

Christube is a PHP + MySQL video platform prototype. Phase 4 adds a production-style media ingestion foundation with resumable uploads, storage abstraction, background processing, metadata extraction, transcoding, thumbnail generation, and safe media asset delivery.

## Stack
- PHP (server-rendered routes + JSON mutation endpoints)
- MySQL (PDO)
- Session auth + CSRF-protected POST mutations
- FFmpeg/ffprobe (optional but recommended for processing)

## Setup
1. Copy `.env.example` to `.env`.
2. Set DB credentials.
3. Ensure writable media root (`storage/media` by default).
4. Install FFmpeg/ffprobe for full processing quality.
5. Serve repository with PHP-enabled web server.

Schema is bootstrapped in `config.php` via `ensureSchema()` + `ensureMediaSchema()`.

## Environment Variables
- `APP_ENV`
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `MEDIA_ROOT` (default: `storage/media`)
- `MAX_VIDEO_UPLOAD_BYTES` (default 150MB)
- `UPLOAD_CHUNK_BYTES` (default 5MB)
- `UPLOAD_SESSION_TTL_HOURS` (default 24)

## Phase 4 Upload & Processing Flow
1. **Start upload session** (`upload_start.php`) with title/description/visibility/file size.
2. **Chunk upload** (`upload_chunk.php`) pushes ordered chunks tied to session owner.
3. **Finalize upload** (`upload_finalize.php`) assembles source asset, validates MIME, creates video in `processing` state, and enqueues `process_video` job.
4. **Worker processing** (`worker_media.php`) handles probing/transcode/thumbnail generation and marks video `ready` or `failed`.
5. **Asset delivery** (`media_asset.php?id=...`) enforces visibility and readiness before streaming file bytes.

Legacy form upload (`upload.php`) still works and internally uses the same pipeline.

## Storage Layout
Under `MEDIA_ROOT`:
- `tmp_chunks/` upload-session chunk staging
- `originals/` finalized source files
- `playback/` normalized MP4 outputs
- `thumbnails/` generated JPG images
- `derivatives/` placeholder for future captions/moderation outputs

Frontend only receives stable route URLs (`media_asset.php?id=...`), not raw internal paths.

## Job Flow (Dev/Test/Prod)
- Run worker manually:
  ```bash
  php worker_media.php
  ```
- Cleanup expired upload sessions/chunks:
  ```bash
  php cleanup_uploads.php
  ```
- In production, schedule both via cron/supervisor:
  - worker every minute (or continuously)
  - cleanup hourly

## Testing
Run integration checks:
```bash
php tests/integration_flows.php
```

Run syntax lint:
```bash
for f in *.php includes/*.php uploads/*.php tests/*.php; do php -l "$f"; done
```

## Known Limitations
- Queue backend is DB-polled worker, not distributed queue yet.
- FFmpeg absence falls back to source copy as playback output.
- Adaptive streaming manifests (HLS/DASH) are scaffold-ready but not implemented.
- Chunk integrity uses ordered assembly but does not yet checksum each part.
