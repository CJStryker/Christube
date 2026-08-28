# Christube

Phase 7 introduces the platform economy/intelligence backbone: robust EXP ledger/rules, progression tiers, personalization/recommendation abstractions, advanced search intelligence, sponsor marketplace scaffolding, and payout-readiness workflows.

## EXP System Rules & Safety Model
Core EXP logic is centralized in `includes/economy.php`.

### Ledger and Idempotency
- `exp_ledger` stores every EXP grant/deduction with:
  - event code
  - reason
  - actor/target
  - post-balance
  - idempotency key
  - metadata
- Duplicate awards are blocked through unique idempotency keys.

### Progression State
- `user_progression` stores viewer/creator EXP, levels, ranks, lifetime/available EXP, and streak scaffolding.
- `progressionLevels()` derives level/rank + next-level threshold.

### Anti-abuse Guards
- daily EXP caps per track (viewer/creator)
- idempotency keys for all rule events
- deterministic event codes and reasons
- no direct EXP-to-cash conversion

### Rule Examples
- signup bonus
- follow creator
- reaction given
- meaningful comment
- watch start/completion
- return-visit reward

## Recommendation & Personalization Architecture
Implemented in `includes/recommendation.php`:
- candidate generation (`recCandidateLatest`)
- user signal extraction (`recUserSignals`)
- weighted ranking (`recScoreRows`)
- personalized homepage rail (`personalizedHomepage`)
- personalized related videos (`personalizedRelated`)
- creator suggestions (`creatorSuggestions`)

Ranking signals include recency, likes, views, creator affinity, follows, creator tier, and basic anti-risk suppression.

## Search Architecture and Ranking
`search.php` now supports:
- multi-entity search (videos/channels/playlists)
- filters for type/sort/duration
- saved queries (`search_saved_queries`) and trending queries (`search_trending_queries`)
- search analytics hooks (`search_performed`)
- visibility-safe query surfaces (public/unlisted-safe where appropriate)

## Monetization / Marketplace / Payout Readiness Models
Economy schema in `includes/economy.php` adds:
- `monetization_profiles`
- `sponsor_campaigns`
- `creator_sponsor_responses`
- `payout_reviews`

Creator flows:
- `creator/sponsors.php` for campaign requests/opportunities + response state
- `creator/monetization.php` for readiness, setup placeholders, and payout-review requests

Admin flow:
- `admin/economy.php` for EXP ledger inspection/adjustments, campaign review, payout review operations

> Note: payout execution is intentionally not implemented yet (state/workflow scaffolding only).

## Sponsor Tools & Eligibility Logic
`creatorEligibility()` combines creator EXP, followers, and views to determine:
- sponsor marketplace access
- payout review eligibility

EXP is one signal among others (not a cash balance and not sole monetization criterion).

## Retention/Lifecycle Hooks
- homepage progression panel + personalized rails
- return-visit EXP reward
- watch-completion EXP and milestone check
- milestone notifications for progression events
- Creator Studio “next best actions” panel

## Config/Tuning Controls
`economyConfig()` centralizes default knobs for:
- EXP rule values and caps
- ranking weights
- eligibility thresholds

`recommendationWeights()` supports optional runtime overrides from `system_tuning` when available.

## Run checks
```bash
for f in *.php includes/*.php creator/*.php admin/*.php uploads/*.php tests/*.php; do php -l "$f"; done
php tests/integration_flows.php
```
