# Christube

Christube is a PHP + MySQL video-sharing platform prototype with user accounts, uploads, watch pages, comments, reactions, profiles, XP progression, and promoted video slots.

## Stack
- PHP (server-rendered pages)
- MySQL (PDO)
- Session-based auth
- Plain CSS (embedded in pages)

## Current Features
- Registration/login/logout
- Video uploads (public/private)
- Video watch pages + comments + reactions
- Profiles, follows, liked videos
- XP/level progression
- Promote videos using XP points
- XMR-to-XP request flow with admin verification
- Comment timeline and per-creator comment inbox
- Admin delete (Zesty) and user self-delete

## Setup
1. Copy `.env.example` to `.env` and set DB credentials.
2. Ensure PHP has PDO MySQL enabled.
3. Point webroot to this repository.
4. Open `register.php` and create an account.

Schema creation is automatic at runtime via `config.php`.

## Environment Variables
- `APP_ENV` (`development`|`production`)
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

## Scripts / Checks
No package scripts are used (plain PHP project). Run syntax checks:

```bash
for f in *.php uploads/*.php; do php -l "$f"; done
```

## Roadmap (Next Phases)
1. Extract shared templates/components and reduce duplicated page CSS.
2. Move business logic into services with clearer request/response contracts.
3. Add queue-backed media processing/transcoding pipeline.
4. Add dedicated moderation/reporting workflows.
5. Add API layer and typed frontend modules.
6. Add automated tests (integration + security checks).

## Known Issues
- Mostly server-rendered monolith with route files at root.
- Limited centralized validation and no CSRF implementation yet.
- Manual XMR verification (no blockchain API integration yet).
- Embedded CSS duplication across pages.
