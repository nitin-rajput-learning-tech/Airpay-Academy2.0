# ADR-014 — Real-time mechanism for `local_sentientia_leaderboard`

- **Status:** Accepted
- **Date:** 2026-05-24
- **Decider:** Nitin Rajput (deferred to Claude under continuous-build mandate)
- **Stream:** Tier 2 #7 — Real-time leaderboards
- **Phase:** L.0 — MVP

---

## Context

Tier 2 #7 in the Sentientia LMS roadmap is real-time leaderboards: a learner submits a
quiz attempt or earns a skill point, and every viewer's leaderboard widget updates
within seconds — no manual reload. The widget will appear on the learner dashboard
(as a `block_sentientia_leaderboard` placement) and inside `local_airpay_pages`
homepage widgets.

The fundamental design question is **how the leaderboard widget receives updates**
when a remote learner moves the rankings.

The plugin will run on the same XAMPP / AWS RDS stack as the rest of Sentientia LMS,
so the choice is constrained by what works inside Apache + PHP without adding new
infrastructure for the Airpay deployment.

ADR-004 already settled this question for `local_sentientia_live` (the Mentimeter
clone) in favour of Server-Sent Events. The infrastructure is in place:

- `local_sentientia_live_events` — append-only event journal
- `local_sentientia_live\event_journal` — read_since / write helpers
- `/local/sentientia_live/stream.php` — SSE long-lived HTTP endpoint

This ADR asks: do we reuse, fork, or replace that infrastructure for leaderboards?

---

## Options considered

### A — Reuse `local_sentientia_live`'s journal + stream verbatim

Tag every leaderboard update as a fake "session" in the live table, then read its
events.

**Pros:** Zero new code.

**Cons:** Foreign keys say sessionid → live_sessions.id; cohabitation pollutes the
live table with millions of leaderboard events; retention policies don't align
(live: 24 h after session ended; leaderboards: indefinite while the board exists).

### B — Reuse only the *pattern*, with a per-plugin events table + stream endpoint

Mirror the schema and the read_since/write API into `local_sentientia_leaderboard`:

- `local_sentientia_lb_events` (id, boardid, type, payload_json, timecreated)
- `local_sentientia_leaderboard\event_journal::{write, read_since, ...}`
- `/local/sentientia_leaderboard/stream.php` (close-of-pattern of live's stream.php)

The AMD client copies the EventSource setup with the boardid in place of sessionid.

**Pros:** Clean separation; the leaderboard plugin can ship without taking a
dependency on `local_sentientia_live` (which is gated behind `live.enabled`,
default OFF, and may stay OFF in some customer deployments). Retention policies
match the board lifecycle.

**Cons:** ~150 lines of duplication. Mitigated by the fact that both files are
heavily commented and act as canonical examples — future SSE-driven features
can copy from either.

### C — Short polling

AMD client hits `local_sentientia_leaderboard_get_board` every 5 seconds.

**Pros:** Trivially compatible with any proxy.

**Cons:** Same scaling cliff as Mentimeter — O(N) requests/second per viewer. A
dashboard with the widget on 500 active sessions = 100 hits/second on the WS
endpoint, which we already know exhausts our pool under load (see ADR-004).

### D — Web Push (reuse Stream B PWA push infra)

Send a push notification on every leaderboard movement.

**Pros:** Works even when the page is closed.

**Cons:** Push is designed for important user-facing alerts, not high-frequency
UI updates. Browsers throttle / silently drop excessive pushes; iOS doesn't
support web push at all on non-PWA contexts.

---

## Decision

**Option B — reuse the pattern, with a dedicated events table + stream endpoint.**

This decision continues the precedent set by ADR-004 (SSE wins on this stack) and
keeps the new plugin behaviourally independent of `local_sentientia_live`.
A customer that has `live.enabled` OFF can still turn `leaderboards.enabled` ON
and run leaderboards through the same SSE plumbing.

---

## Consequences

### Implementation

The plugin ships:

- `local_sentientia_lb_events` — same schema as `local_sentientia_live_events`,
  with `boardid` foreign key in place of `sessionid`
- `local_sentientia_leaderboard\event_journal` — identical API to
  `local_sentientia_live\event_journal`, just a different table
- `/local/sentientia_leaderboard/stream.php` — derived from `local_sentientia_live/
  stream.php`, swapping session ⇄ board

New event types:

- `leaderboard.score_changed` — payload `{userid: int, points_now: int}`
- `leaderboard.position_changed` — payload `{userid: int, old_rank: int, new_rank: int}`
- `leaderboard.recomputed` — payload `{boardid: int, recomputed_at: int}`

### Re-computation strategy

Per ADR-004's lesson on Apache worker exhaustion, we do NOT recompute the leaderboard
on every learner action. We:

1. Run a scheduled task every 2 minutes that scans for boards with `last_recomputed`
   older than `recompute_interval_seconds` and recomputes them.
2. Write a `leaderboard.recomputed` event on each recompute so any open SSE client
   knows fresh data is available.

This trades real-time precision for stability: a quiz submission may take up to 2
minutes to surface in the leaderboard, but no learner action triggers an expensive
recompute on the request path. For boards configured with
`recompute_interval_seconds = 60`, latency drops to ~1 minute.

### Privacy

Every leaderboard widget honours a per-user opt-out flag stored in
`local_sentientia_lb_optouts`. Opted-out users still accrue points (rankings are
still tracked internally for HR analytics under `:viewall` capability), but their
row is filtered out before SSE pushes the leaderboard payload and before any WS
function returns it.

The opt-out is a single boolean per user per customer, surfaced as a checkbox on
`/user/preferences.php` ("Hide me from public leaderboards"). It is fully
reversible.

### Tenant isolation

Boards carry `customerid + tenantid` columns (same shape as `local_sentientia_live_
sessions`). Reads filter by tenant unless the caller has
`local/sentientia_leaderboard:viewall` (manager). The SSE endpoint enforces tenant
access before opening the stream — a cross-tenant viewer gets a 403.

### Feature flag

`sentientia.leaderboards.enabled` — default OFF. Master switch.

`sentientia.leaderboards.realtime.enabled` — default ON. Kill-switch (mirrors
`live.realtime.enabled`).

`sentientia.leaderboards.type.quiz`, `.completion`, `.skill` — per-type ship gates,
all default OFF, so admins can enable types one at a time as they validate
each ranking engine against their data.

---

## Status of follow-up

- Phase L.0 (this version): MVP — 3 board types, opt-out, SSE, block, WS, PHPUnit
- Phase L.1 (later): cohort scoping, embeddable widget in `local_airpay_pages`,
  CSV export
- Phase L.2 (later): per-board badge rewards (integrate with
  `local_airpay_gamification`), top-3-podium UI variant

---

## References

- ADR-004 — Real-time mechanism for `local_sentientia_live`
- ADR-002 — Customer-level feature flags
- `local_airpay_challenge/classes/leaderboard_manager.php` — competition-ranking
  algorithm (1, 2, 2, 4) that we mirror
- `local_sentientia_live/stream.php` — SSE endpoint canonical reference
- `local_sentientia_live/classes/event_journal.php` — event journal canonical
  reference
