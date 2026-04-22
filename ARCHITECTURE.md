# Christube Architecture (Phase 5)

## Watch Page Data Flow
1. Resolve video slug and enforce access (`public+ready` or owner).
2. Resolve media URLs via asset IDs (no raw internal path exposure).
3. Record deduped view and watch-start event.
4. Render watch UI with related videos, comments, follow/react/report controls.
5. Persist watch progress asynchronously (`history_update.php`).

## Discovery / Ranking Services
`includes/product.php` centralizes ranking/query logic:
- latest feed
- trending scoring
- related scoring
- search ranking/sorting

All discovery queries enforce ready/public gating to prevent leakage.

## Playlist Model
- `playlists`: owner, slug, metadata, visibility (`public/private/unlisted`), system Watch Later flag.
- `playlist_videos`: join table with order index and uniqueness.
- Ownership checks are enforced for modifications.

## History Model
- `watch_history` stores per-user per-video last position and duration.
- `video_views` tracks dedup-able views via session fingerprint and time window.
- Continue Watching rail uses history recency.

## Search Architecture
- `search.php` calls `searchVideosAndChannels()` service.
- Input is sanitized/parameterized.
- Sorting: relevance/newest/popular.
- Results reuse shared video card rendering.

## Reusable UI Components
`includes/components.php` provides:
- section header
- video card
- related row
- comment item (creator badge support)

## Product Analytics Hooks
`product_events` provides event-capture scaffolding for:
watching, discovery, reactions, follows, comments, playlist actions, reports.
