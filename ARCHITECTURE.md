# Christube Architecture (Current)

## Runtime Model
- Server-rendered PHP pages in repository root.
- Shared bootstrap/config in `config.php`.
- MySQL database accessed through PDO.
- Session auth (`$_SESSION['user_id']`, `$_SESSION['username']`).

## Core Boundaries
- **UI pages**: `index.php`, `view.php`, `profile.php`, `uploads/index.php`, etc.
- **Action endpoints**: `upload.php`, `comment.php`, `react.php`, `follow.php`, `promote_video.php`, etc.
- **Shared foundation**: `config.php`.

## Data Domains
- Users, profiles, follows
- Videos, visibility, ads
- Comments, reactions
- XP events, XMR point requests

## Foundation Decisions
- Environment-based configuration via `.env` + `.env.example`.
- Schema auto-migration on boot for current prototype speed.
- Shared helpers for flash, validation, XP, and ad lookup.
- Placeholder hooks for rate limiting and moderation in `config.php`.

## Planned Structural Evolution
Scaffold directories created for next-phase modularization:
- `components/`, `features/`, `lib/`, `services/`, `hooks/`, `types/`, `database/`, `api/`, `admin/`, `tests/`

These are placeholders for phased migration from root-route monolith to cleaner layered modules.
