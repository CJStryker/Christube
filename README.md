# Christube

Phase 5 turns Christube into a core video product experience with a complete watch page, discovery feeds, subscriptions, playlists, history, search, and analytics hooks on top of the Phase 4 media pipeline.

## Product Surfaces
- **Homepage (`index.php`)**: Latest, Trending, Subscriptions rail, Continue Watching rail.
- **Watch page (`view.php`)**: Player, metadata, creator block, reactions, follow, comments, save-to-playlist, report, related videos, playlist context.
- **Discovery**: `search.php`, `trending.php`, `subscriptions.php`.
- **Collections**: `playlists.php`, `playlist.php`, `playlist_save.php`.
- **History**: `history.php`, `history_update.php`.

## Watch Page Architecture
- Load video by slug with visibility/readiness gating.
- Count deduped views (`video_views`) with short-window fingerprint suppression.
- Render ready playback asset only (`media_asset.php`).
- Persist watch progress (`watch_history`) for authenticated users.
- Emit product analytics hooks (`product_events`) for watch start/progress and user actions.

## Discovery/Trending Logic
Implemented in `includes/product.php`:
- `getLatestVideos()` – newest ready/public videos.
- `getTrendingVideos()` – deterministic score using views + likes + recency tie-break.
- `getRelatedVideos()` – creator affinity + recency + engagement score.
- `searchVideosAndChannels()` – title/description/creator match with sort options (relevance/newest/popular).

No fake ML is used in this phase.

## Playlists / History Behavior
- User playlists support `public/private/unlisted` visibility.
- Playlist add/remove is CSRF-protected mutation (`playlist_save.php`).
- Watch Later is system playlist created lazily per user.
- History records last position + duration and powers Continue Watching.
- History route supports deleting entries.

## Analytics Hooks Added
`product_events` receives events such as:
- homepage impression
- trending impression
- search performed
- watch start / watch progress
- follow / unfollow
- reaction saved / reaction removed
- playlist created / playlist add
- comment posted
- video reported

## Running Tests
```bash
for f in *.php includes/*.php uploads/*.php tests/*.php; do php -l "$f"; done
php tests/integration_flows.php
```

## Key Limitations
- Trending/recommendations are deterministic heuristics (no personalization model yet).
- No full browser E2E harness yet; coverage is integration-wiring level.
- Playlist reorder drag/drop and chapters are scaffold-ready but not fully implemented.
