# Christube

Christube is a PHP + MySQL video platform prototype with uploads, watch pages, profiles/channels, comments, reactions, follows, XP economy, promotions, and XMR-to-XP request flow.

## Stack
- PHP (server-rendered routes)
- MySQL (PDO)
- Session-based auth
- Shared CSS (`public/styles.css`)

## Setup
1. Copy `.env.example` to `.env`.
2. Set DB credentials.
3. Serve repository with PHP-enabled web server.
4. Register account at `register.php`.

Schema is bootstrapped in `config.php` (`ensureSchema`).

## Environment Variables
- `APP_ENV` (`development` / `production`)
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

## Auth & Session
- Registration (`register.php`) with validation + password hashing.
- Login (`login.php`) with password verification.
- Logout (`logout.php`).
- Session persistence with hardened cookie settings.
- Protected routes use `requireLogin()`.

## CSRF + Mutation Middleware
- CSRF token helpers in `config.php`:
  - `csrfToken()`
  - `csrfInput()`
  - `verifyCsrfToken()`
- Centralized mutation pipeline via `handleMutation([...], fn)`:
  - method enforcement (`POST`)
  - rate-limit hook
  - CSRF verification
  - auth guard (configurable)
  - normalized error flashing + redirect

All major mutation flows now use this wrapper.

## User / Profile / Channel Foundation
- User model supports:
  - username, display name, email, password hash
  - bio, avatar URL, banner URL
  - role, account status, profile visibility
  - XP and level progression
- Public profile page (`profile.php`) with channel semantics.
- Channel compatibility route (`channel.php`) redirects to profile.
- Profile editing (`edit_profile.php`) for display/bio/avatar/banner/visibility.

## Features
- Upload videos, manage privacy, delete own videos.
- Watch videos, comment, react, follow creators.
- Timeline pages for comments.
- Promote videos with XP.
- XMR point requests + admin verification flow.

## Integration Test Checks
Run:

```bash
php tests/integration_flows.php
```

Also run syntax lint:

```bash
for f in *.php uploads/*.php; do php -l "$f"; done
```

## Known Limitations
- No true browser E2E tests yet (current integration script validates critical wiring).
- CSRF is form/session based; no SPA token header flow yet.
- Schema migrations still run at request boot.
- Modular service extraction is partial.

## Next Phase Direction
- Add real HTTP integration/E2E suite.
- Add CSRF token rotation strategy and strict logout POST-only UI everywhere.
- Move route logic into service classes and shared controllers.
- Introduce moderation/report queue + audit logging.
