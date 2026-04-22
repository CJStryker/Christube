# Christube Architecture (Phase 6)

## Creator Dashboard Architecture
Creator Studio is routed under `/creator/*` and guarded by `requireLogin()`.

Key surfaces:
- dashboard overview
- video management
- channel settings entrypoint
- analytics (channel + per-video)
- comments inbox
- promotions
- notifications

Navigation is centralized via `creatorNav()` in `includes/creator.php`.

## Analytics Aggregation Model
### Live Aggregates
`getCreatorAnalytics()` and `getCreatorOverviewStats()` aggregate from:
- `video_views`
- `product_events`
- `video_reactions`
- `video_comments`
- `user_follows`
- `watch_history`

### Materialized Aggregates
`creator_rollup.php` materializes daily rows into `creator_daily_stats` (and prepares `video_daily_stats` schema) for scaling future analytics workloads.

## Notification Generation Model
- Storage: `notifications`
- Preferences: `notification_preferences`
- Delivery now: in-app
- Future extension path: email/push channels via same event model

Generation hooks are called from core mutation and processing flows using `notifyUser()` with optional dedupe keys.

## Aggregate/Materialization Strategy
- keep critical UX metrics available live
- use daily rollups for longitudinal reporting and dashboard performance
- avoid overengineering into warehouse-style complexity

## Creator/Admin Permission Boundaries
- Creator routes only expose authenticated user’s own channel/video data.
- Per-video edit/analytics enforce owner checks.
- Notifications are strictly user-scoped.
- Admin surfaces require explicit admin guard (`requireAdminUser()`).
- Public product surfaces still enforce video visibility/readiness gating.

## Event Taxonomy Separation
- `product_events`: engagement and product behavior analytics.
- `audit_logs`: security/operational action trail.

They are intentionally separate systems with separate purposes.
