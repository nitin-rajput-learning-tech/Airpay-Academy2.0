# local_sentientia_gamification

Points + badges + streaks foundation. Consumed by `sentientia_challenge`
(workflow) and by every plugin that emits points-earning events.

| Field | Value |
|---|---|
| Component | `local_sentientia_gamification` |
| Version | beta 1.0.0 |
| Depends on | `local_sentientia_org` |

## What it does

- Point ledger per user (event → points → running total).
- Badge definitions and per-user badge awards.
- Streak tracking (consecutive-day login count).
- Hooks (event observers) on course-completion / quiz-attempt /
  classroom-attendance events to award points automatically.

## Tables (4)

- `local_sentientia_badges` — badge definitions.
- `local_sentientia_user_badges` — user-to-badge award rows.
- `local_sentientia_streaks` — per-user streak state.
- `local_sentientia_points_log` — append-only points-earning audit.

## Event observers

Subscribes to Moodle core events `course_completed`, `quiz_attempt_submitted`,
plus Airpay-specific events from `sentientia_classroom` and `sentientia_challenge`.

## Tier-1 work

Built in commit `67a695cd8` (9 April 2026) as the Tier-1 gamification
engine. Documented in `moodle-enhancement/state-cards/sentientia_challenge-state.md`.

## Privacy / GDPR

Provider exists. DSR export bundles the user's points log and badge
awards. DSR delete redacts user-id references but preserves aggregate
points totals for any leaderboard integrity that depends on historic
rankings.

## Open backlog

- Leaderboard widget integration with `sentientia_challenge`.
- Badge issuance via Moodle core badges system (currently the badges
  are airpay-internal; Moodle's native `badge` subsystem is not yet
  wired up).
