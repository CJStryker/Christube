# BUILD_AUDIT

## What Was Found
- Root-level monolithic PHP route files with duplicated styling and logic.
- Hardcoded DB credentials risk and weak environment separation.
- Inconsistent helper usage for flash/errors/validation.
- Limited baseline security hooks (no shared rate-limit/mode helpers).
- Feature growth (XP, ads, XMR, profiles) without clear architecture docs.

## What Was Fixed
- Centralized environment loading and DB config into env-driven `config.php` bootstrap.
- Added reusable helper functions for:
  - env access
  - flash messaging
  - post method guard
  - visibility/tx hash validation
  - XP + level utilities
  - ad retrieval
- Added baseline security placeholders for rate limiting and moderation checks.
- Added `.env.example` and removed dependency on hardcoded DB password defaults.
- Added self-delete action on uploads page + ownership-checked endpoint.
- Added XMR purchase request flow pages and admin verification path:
  - `buy_points.php`
  - `my_point_requests.php`
  - `admin_verify_points.php`
- Added architecture and setup docs (`README.md`, `ARCHITECTURE.md`).
- Added scaffold directories for next-phase modular migration.

## What Still Needs Work
- Move repeated page layouts/styles into shared templates/components.
- Implement CSRF protection on all mutating POST endpoints.
- Replace session-only rate limiting with distributed limiter (Redis or gateway).
- Add automated test suite (unit + integration + security checks).
- Split schema migration from request-time boot into migration scripts.
- Add structured logging/observability and error monitoring.

## Risks / Limitations
- Schema auto-run in request path can be risky at scale.
- Current XMR verification is manual (no chain API proof automation).
- Root-level route sprawl still exists; scaffolding is ready but migration is phased.

## Recommended Next Phase
- Introduce a lightweight internal framework layer:
  - router + middleware
  - service layer classes
  - template/component system
- Add CSRF middleware, auth middleware, and centralized validator.
- Implement migration tooling and begin moving root pages into feature modules.
