# local_sentientia_leaderboard — State Card
**Plugin status:** Phase L.0 MVP shipped (2026-05-24)
**Tier:** 2 #7 (Sentientia LMS roadmap)
**Maturity:** `MATURITY_ALPHA`
**Version:** 2026052400 / 0.1.0-alpha
**Last updated:** 2026-05-28 (C17 seed CLI + F-053..F-056 rename)

## 2026-05-28 updates

- **C17 second-wave seed CLI** shipped: `cli/seed_demo_boards.php`.
  Creates 2 boards (completion-type/course-scope + skill-type/customer-
  scope) via `board_manager::create()`, then optionally calls
  `ranking_engine::recompute()` to fill `lb_entries` from REAL local
  course-completion data. On the local 2,871-user DB the
  completion-type board yields 108 entries against the "Introduction
  to airpay" course in ~0.5s.
- Also seeds one customer-wide opt-out (per-customer, not per-board)
  so the consent gate (B3/F-002) has something visible on local.
- `--purge` removes `[DEMO]` boards (and their entries via FK delete)
  but intentionally leaves the customer-wide opt-out alone — those
  belong to the user, not the seed.
- State-card renamed from `local_sentientia_leaderboard-state.md` →
  `sentientia_leaderboard-state.md` via F-053..F-056 cleanup.


**Component:** `local_sentientia_leaderboard` + `block_sentientia_leaderboard`
**ADR:** [ADR-014 — Real-time mechanism for `local_sentientia_leaderboard`](../docs/adr/ADR-014-real-time-leaderboards-realtime-mechanism.md)

---

## Mission

Real-time leaderboards for Sentientia LMS. Builds on the SSE infrastructure
that `local_sentientia_live` proved out under ADR-004 — per ADR-014 we reuse
the *pattern* (event journal table, stream endpoint, AMD client) rather than
the table, so leaderboards can run independently of whether the Mentimeter
clone is enabled in a tenant.

---

## Architecture summary

```
                    ┌──────────────┐
                    │  Source       │   mod_quiz.attempts /
                    │  aggregators  │   course_completions /
                    │              │   local_airpay_user_skill_hist
                    └──────┬───────┘
                           │ recompute()
                           ▼
                ┌─────────────────────┐
                │ local_sentientia_   │
                │   lb_entries        │  ← snapshot ranking table
                └──────┬──────────────┘
                       │ write
                       ▼
                ┌─────────────────────┐         /local/sentientia_leaderboard/
                │ local_sentientia_   │ ──────► stream.php (SSE long-lived HTTP)
                │   lb_events         │         │
                └─────────────────────┘         │ SSE: leaderboard.recomputed
                                                ▼
                                  ┌─────────────────────────────┐
                                  │ block_sentientia_leaderboard │
                                  │ + leaderboard_client.js      │
                                  │ (re-fetches top-N on event)  │
                                  └──────────────────────────────┘
```

Three independent board types — each toggle-able:

| Type | Aggregates from | Tiebreak |
|------|----------------|----------|
| `quiz` | `mod_quiz.quiz_attempts` (state=finished, preview=0) | shorter attempt time |
| `completion` | `course_completions` (timecompleted - timeenrolled) | completion percent |
| `skill` | `local_airpay_user_skill_hist` (new_level - previous_level) | distinct skills levelled |

Tenant scope is mandatory: every aggregator joins to `user.open_path` and
filters `'/N' OR '/N/%'` where N is the board's `tenantid`. Boards
created with `tenantid=0` are customer-wide and require `:promoteboard`
capability to create.

---

## Feature flags (all in `db/feature_flags.php`)

| Flag | Default | Purpose |
|------|---------|---------|
| `sentientia.leaderboards.enabled` | OFF | Master switch. Every entry point bails when OFF. |
| `sentientia.leaderboards.realtime.enabled` | ON | SSE kill-switch. OFF = 30 s polling. |
| `sentientia.leaderboards.type.quiz` | OFF | Per-type ship gate (quiz). |
| `sentientia.leaderboards.type.completion` | OFF | Per-type ship gate (completion). |
| `sentientia.leaderboards.type.skill` | OFF | Per-type ship gate (skill). |
| `sentientia.leaderboards.optout.enabled` | ON | Surface the opt-out UI on /user/preferences.php. |

Default behaviour with no admin action: **plugin installs but renders nothing**.
That's the additive-shipping commitment from CLAUDE.md Day 0.

---

## DB schema (4 tables)

| Table | Purpose | Key indexes |
|-------|---------|-------------|
| `local_sentientia_lb_boards` | Board definitions (one per leaderboard) | `(status, tenantid)`, `(type)`, `(customerid, tenantid)`, `(last_recomputed)` |
| `local_sentientia_lb_entries` | Snapshot ranking (one per board×user) | `(boardid, userrank)`, `(costcenterid)`, UK `(boardid, userid)` |
| `local_sentientia_lb_optouts` | Per-user privacy opt-outs | UK `(userid, customerid)`, `(customerid)` |
| `local_sentientia_lb_events` | Append-only SSE event journal | `(boardid, id)`, `(timecreated)` |

Foreign keys: every user-referencing column has a real FK to `user.id`.

---

## Capabilities (`db/access.php`)

| Capability | Risk | Archetypes |
|-----------|------|------------|
| `local/sentientia_leaderboard:view` | none | user, student, teacher, editingteacher, manager |
| `local/sentientia_leaderboard:manageboard` | CONFIG | editingteacher, manager |
| `local/sentientia_leaderboard:promoteboard` | CONFIG + PERSONAL | manager |
| `local/sentientia_leaderboard:viewall` | PERSONAL | manager |

`:viewall` is the only capability that bypasses the opt-out filter. It's
designed for HR analytics workflows. The block widget always reads with
the caller's actual `:viewall` state — never the system admin's.

---

## REST API (3 functions, all in `db/services.php`)

| Function | Type | Capability | Purpose |
|---------|------|-----------|---------|
| `local_sentientia_leaderboard_get_board` | read | `:view` | Top-N for a board, tenant-scoped, opt-outs filtered |
| `local_sentientia_leaderboard_list_boards` | read | `:view` | Active boards visible to caller |
| `local_sentientia_leaderboard_set_optout` | write | (login-only) | Toggle the caller's opt-out |

---

## SSE endpoint

`/local/sentientia_leaderboard/stream.php?boardid=N` — derived from
`/local/sentientia_live/stream.php`. Headers, polling loop, and reconnect
logic mirror the live plugin exactly. Differences:

- Boards instead of sessions
- Tenant gate at the head (instead of per-session participant lookup)
- No anonymous token bearer auth (logged-in users only)

Event types:

- `leaderboard.recomputed` — fired by `ranking_engine::recompute()`
  after a successful commit. Client refetches the top-N.
- `leaderboard.score_changed`, `leaderboard.position_changed` — **registered**
  but not yet emitted; reserved for Phase L.1+ incremental updates.

---

## Scheduled tasks (`db/tasks.php`)

| Class | Schedule | Purpose |
|-------|----------|---------|
| `recompute_due_boards` | every 2 minutes | Recompute boards whose `last_recomputed` is older than their `recompute_seconds` |
| `purge_old_events` | daily at 03:00 | Delete events older than 7 days |

---

## Frontend surfaces

1. **Block** `block_sentientia_leaderboard` — placeable on Dashboard,
   site index, any course view. Config selects which board to render
   (default = first visible board).
2. **Full page** `/local/sentientia_leaderboard/view.php?id=N` — same
   template, top 25 instead of top 5.
3. **Admin index** `/local/sentientia_leaderboard/index.php` — list of
   visible boards. Click-through to view.
4. **Preferences** `/local/sentientia_leaderboard/preferences.php` — single
   "Hide me from public leaderboards" toggle, accessible from the
   user-settings navigation node added by `lib.php`.

---

## Hindi parity

```
EN strings: 85
HI strings: 85 ✅
```

Block plugin: 4 EN strings, 4 HI strings ✅

---

## Privacy

Privacy provider (`classes/privacy/provider.php`) declares both tables that
carry user data:
- `local_sentientia_lb_entries`
- `local_sentientia_lb_optouts`

Export: includes the user's rankings + their opt-out timestamps.
Deletion: removes both tables' rows for the requested user.

The opt-out itself is fully reversible (delete-row on opt-in, not a flag
flip) so a stale opted-out flag can never linger.

---

## Test coverage (PHPUnit)

| Test class | Focus |
|------------|-------|
| `ranking_engine_test` | Competition ranking, tie-handling, tenant scope, opt-out honoring, idempotency, type validation, due-board detection |
| `board_manager_test` | Tenant pinning, list_visible filter, cascade delete, tenant root parsing |
| `optout_manager_test` | Reversibility, idempotency, per-customer isolation, bulk fetch |
| `event_journal_test` | Write/read round-trip, last_event_id filter, board filter, retention purge, latest_event_id |

Total: 4 test classes, 31 test methods (ranking_engine 12, optout_manager 7,
event_journal 6, board_manager 6) covering:
- ✅ Ranking correctness (1-2-2-4 competition format)
- ✅ Tenant scope on every board type
- ✅ Opt-out filter on learner read; bypass on `:viewall`
- ✅ Idempotent recompute (no duplicate rows on re-run)
- ✅ Event emission on recompute
- ✅ Required-field validation per board type

**NOTE:** PHPUnit cannot execute inside this cloud sandbox (no `vendor/`
on the ephemeral container). All test classes are syntax-clean
(`php -l` passes). To run locally:

```powershell
cd C:\xampp\htdocs\moodle5
vendor\bin\phpunit --filter ranking_engine_test
vendor\bin\phpunit --filter board_manager_test
vendor\bin\phpunit --filter optout_manager_test
vendor\bin\phpunit --filter event_journal_test
```

---

## Visual evidence

See `docs/visual-evidence/2026-05-24/` for screenshots and the README
describing the verification procedure (block on dashboard, opt-out
toggle, two-browser SSE liveness test).

---

## Open items for Phase L.1+

- Incremental events (`score_changed`, `position_changed`) — currently
  registered but unused. Client logic to apply them would let us drop
  the full re-fetch on every recompute.
- Embeddable widget in `local_airpay_pages` (use the `[lb:N]` shortcode
  pattern from compliance reports).
- CSV export of the snapshot (manager-only).
- Cohort scoping (`cohort_members` join on aggregators).
- Badge reward integration with `local_airpay_gamification`.
- Trainer UI for board creation (today: WS-only or direct SQL — admin
  reach only).

---

## Deploy recipe (local XAMPP)

```powershell
# Copy plugin folders to the XAMPP install.
Copy-Item -Recurse "D:\Claude Local\airpay-ld-os\moodle-enhancement\local\sentientia_leaderboard" `
                   "C:\xampp\htdocs\moodle5\public\local\sentientia_leaderboard" -Force
Copy-Item -Recurse "D:\Claude Local\airpay-ld-os\moodle-enhancement\blocks\sentientia_leaderboard" `
                   "C:\xampp\htdocs\moodle5\public\blocks\sentientia_leaderboard" -Force

# Upgrade DB.
php C:\xampp\htdocs\moodle5\public\admin\cli\upgrade.php --non-interactive

# Enable feature flags via the Switchboard or directly:
# Admin → Site administration → Plugins → Local plugins → Airpay Core → Switchboard
#   sentientia.leaderboards.enabled → ON
#   sentientia.leaderboards.type.completion → ON (try this first; quiz/skill need their source data)

# Purge caches + hard refresh.
php C:\xampp\htdocs\moodle5\public\admin\cli\purge_caches.php
# Browser: Ctrl+Shift+R on Dashboard, add block, choose a board.
```

---

## State card refresh — 2026-05-24

P1 state-card pass: confirmed plugin still at `2026052400` /
`0.1.0-alpha` after the merge wave. Re-counted PHPUnit methods:
ranking_engine 12 + optout_manager 7 + event_journal 6 +
board_manager 6 = 31 total (previously listed as "~30"). Confirmed
4 DB tables (`lb_boards`, `lb_entries`, `lb_optouts`, `lb_events`),
4 capabilities (`:view`, `:manageboard`, `:promoteboard`, `:viewall`),
6 feature flags (master + realtime + 3 per-type + opt-out UI), 3 REST
WS functions, 2 scheduled tasks, 4 frontend surfaces (block, view,
admin index, preferences). Companion block plugin
`block_sentientia_leaderboard` shipped same wave — see its own state
card. Master flag `sentientia.leaderboards.enabled` still default OFF;
plugin renders nothing on a vanilla install.
