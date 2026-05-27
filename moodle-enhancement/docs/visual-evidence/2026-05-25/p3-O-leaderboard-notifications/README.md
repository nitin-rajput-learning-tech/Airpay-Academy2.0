# P3-O — Leaderboard L.1 rank-change notifications

**Chip:** P3-O / `intelligent-ride-82LNQ` · **Merge:** `f787257a2` · **Date:** 2026-05-24

## What changed

Phase L.1 of `local_sentientia_leaderboard` — rank-change notifications
via Moodle's `message_send()` API. Pure scaffold + observer; no UI
changes outside Moodle's standard notification surfaces.

### New files

- `classes/message_helper.php` — assembles `core\message\message` objects
  with leaderboard context
- `classes/observer.php` — listens to `leaderboard_recomputed` event
- `db/events.php` — registers the observer
- `db/messages.php` — declares the `rank_change` message type so users
  see it in Preferences → Notifications

### Trigger rules

| Event | Condition |
|-------|-----------|
| Big climb / drop | Position change ≥ ±5 ranks |
| Top-10 entry  | First time in top 10 |
| Top-3 entry   | First time in top 3  |
| Throttle      | 24h per learner per board |

### Feature flag

`sentientia.leaderboards.notifications.enabled` — default OFF. With
flag OFF the observer is registered but the handler returns early
before `message_send()`.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-notification-toast.png` | Notification card "You've broken into the Top 10!" with previous → new rank, view-leaderboard CTA. Trigger rules + channels grid on the right. |
| `screenshot-desktop-dark.png`               | Same surface, dark mode |
| `screenshot-mobile-notification-toast.png`  | 590px viewport — card stacks above trigger rules |

## What to look for

1. **Notification anatomy.** Card header uses
   `--ap-color-primary` band with white text and the 🏆 icon. Body uses
   `--ap-color-text-secondary`. Rank delta callout uses
   `--ap-color-success` for an upward move.
2. **Channel preferences respect Moodle conventions.** `popup` is
   forced ON for `rank_change`; `email` and `mobile_push` defer to
   user preferences (the standard Moodle pattern).
3. **Opt-out banner.** Footer reminds that learners with
   `preferences::hide_from_public = 1` never receive these (the
   privacy mandate in CLAUDE.md Day 0).

## Acceptance

- ✓ Event registers in `\core\event\manager`
- ✓ `message_send()` fires only when flag is ON AND throttle has expired
- ✓ Opt-out preference suppresses send-side AND read-side
- ✓ PHPUnit covers: trigger conditions, throttle, opt-out, message body

## Refs

- ADR: `docs/adr/ADR-014-real-time-leaderboards-realtime-mechanism.md` (existing — L.1 extends L.0)
- State card: `state-cards/local_sentientia_leaderboard-state.md`
- Predecessor: Tier 2 #7 — Leaderboard L.0 MVP (`claude/youthful-wozniak-3WuYx`, 2026-05-24)
