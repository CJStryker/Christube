# Christube

Phase 6 introduces **Creator Studio** and operational foundations: creator dashboard, creator analytics, notifications, creator moderation inbox, matured promotions, and admin operations tooling.

## Creator Dashboard Usage
Main studio routes:
- `creator/dashboard.php` – overview widgets, quick actions, recent activity, top videos.
- `creator/videos.php` – owned video management with status/archive/search filters.
- `creator/video_edit.php` – metadata, visibility, archive state updates.
- `creator/analytics.php` + `creator/video_analytics.php` – channel and per-video analytics.
- `creator/comments.php` – creator comments inbox and moderation/removal action.
- `creator/promotions.php` – promotion creation + history/status.
- `creator/notifications.php` – notifications feed + read-state + preference scaffolding.
- `creator/settings.php` – channel management entrypoint.

## Analytics Derivation Approach
Analytics are derived from real product/activity tables:
- views from `video_views`
- watch starts from `product_events` (`watch_start`)
- likes/comments from `video_reactions` and `video_comments`
- follower growth from `user_follows`
- watch quality approximations from `watch_history` (`avg_watch_seconds`, completion ratio)

Aggregation logic is centralized in `includes/creator.php` (`getCreatorAnalytics`, `getCreatorOverviewStats`).

## Notification System
`notifications` + `notification_preferences` provide in-app notification foundations:
- unread/read state
- mark one/mark all read
- dedupe key support to reduce spam duplicates
- user preference scaffolding for comments/follows/processing/promotions

Notification generation is integrated into key flows:
- new comment on owned video
- new follower
- upload processing success/failure
- promotion activation/status updates

## Promotions Workflow
Creators can manage promotions from `creator/promotions.php`:
- validates ownership and ready video state
- blocks duplicate concurrent promotion on same video
- enforces XP spend and duration policy
- records audit + notification on activation

## Admin / Moderation Operational Surfaces
`admin/ops.php` adds:
- operational counters (pending reports, failed processing, pending point requests, new users, recent uploads)
- reports queue with review actions and quick links
- recent audit log browsing surface

## Aggregate / Materialization Strategy
- **Live compute** for immediate dashboard metrics (fast enough current scale).
- **Materialized daily rollups** via `creator_rollup.php` into `creator_daily_stats` (and schema for `video_daily_stats`) for future heavier analytics views.

Run rollup manually:
```bash
php creator_rollup.php
```

## Event Taxonomy Notes
- Product analytics events live in `product_events` (viewer/creator behavior).
- Security/operational actions remain in `audit_logs`.
- This keeps analytics and audit concerns separate by design.

## Testing
```bash
for f in *.php includes/*.php creator/*.php admin/*.php uploads/*.php tests/*.php; do php -l "$f"; done
php tests/integration_flows.php
```

## Known Limitations
- Charts are table-first (no JS chart rendering library yet).
- Rollup job is CLI/manual and not scheduler-managed by default.
- Admin workflows are practical but still lightweight compared to full moderation suites.
