# Christube Architecture (Phase 7)

## EXP Domain Model & Ledger
- `exp_ledger`: immutable event ledger with idempotency keys.
- `user_progression`: aggregate progression state (viewer/creator tracks).
- `achievements` + `user_achievements`: badge/milestone scaffolding.

All EXP mutations route through `awardExp()` in `includes/economy.php`.

## Ranking / Recommendation Architecture
`includes/recommendation.php` separates:
1. candidate generation
2. user signal extraction
3. weighted scoring/ranking

Targets:
- homepage personalized rail
- related videos
- creator suggestions

Safety:
- non-public/unready excluded by candidate query
- anti-repeat and creator saturation controls
- low-quality risk penalty hook

## Search Services
Search is expanded to include:
- videos/channels/playlists
- sort + type + duration filtering
- saved/trending query data stores
- analytics event logging

Ranking remains deterministic and explainable.

## Monetization Domain Boundaries
Introduced in `includes/economy.php`:
- `monetization_profiles`
- `sponsor_campaigns`
- `creator_sponsor_responses`
- `payout_reviews`

This phase provides state/workflow scaffolding only; no external payout execution.

## Sponsor/Marketplace Models
- Campaign lifecycle states: draft/submitted/review/approved/active/completed/cancelled.
- Creator response states: pending/accepted/declined.
- Admin review surface in `admin/economy.php`.

## Payout Readiness Model
- Creator-facing readiness + setup placeholders (`creator/monetization.php`).
- Eligibility based on multi-signal checks (EXP + followers + views + policy flags).
- Admin updates payout review decisions and state transitions.

## Retention/Lifecycle Design
- progression nudges (homepage + creator dashboard)
- return-visit and completion rewards
- milestone notification hooks
- next-best-action scaffolding for creator growth

## Permission & Safety Boundaries
- creator routes: owner-scoped
- admin economy routes: admin-only
- EXP adjustments: admin-controlled and audit logged
- monetization states: controlled workflow, no direct cash conversion
- recommendation/search: visibility-safe content only
