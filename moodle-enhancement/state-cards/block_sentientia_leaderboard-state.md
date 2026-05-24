# State Card — `block_sentientia_leaderboard`

**Component:** `block_sentientia_leaderboard`
**Version:** `2026052400` / `0.1.0-alpha`
**Maturity:** `MATURITY_ALPHA`
**Status:** Phase L.0 — shipped alongside `local_sentientia_leaderboard`
**ADR:** [ADR-014 — Real-time mechanism for `local_sentientia_leaderboard`](../docs/adr/ADR-014-real-time-leaderboards-realtime-mechanism.md)
**Hard dependency:** `local_sentientia_leaderboard >= 2026052400`
**Last updated:** 2026-05-24

---

## Mission

The block-plugin half of the Sentientia LMS leaderboards feature. Wraps
the local plugin's `ranking_engine::read_top()` snapshot reader into a
placeable dashboard / site / course-page block. The local plugin owns
the schema, scheduling, SSE stream, and capabilities; this block is
purely a render surface that loads `local_sentientia_leaderboard/leaderboard_client`
on init so the table refreshes via SSE without a full page reload.

## Why a separate block plugin

A Moodle `block_*` is the canonical way to drop a widget onto pages the
admin can re-arrange via the page-customise UI. Bundling the renderer
into the `local_*` plugin would have forced the surface to live at a
fixed `/local/sentientia_leaderboard/view.php` URL only. The split lets
the same engine power both a full page (one big table) and a dashboard
block (top-5 widget) without duplicating ranking logic.

## Frontend behaviour

```
init()
  └─ title = lang string `pluginname`
applicable_formats()
  ├─ all = true       (anywhere the admin allows)
  ├─ my = true        (user dashboard)
  ├─ site-index       (front page)
  └─ course-view      (inside a course)
instance_allow_multiple() = true  (more than one board per page)
get_content()
  ├─ master flag `sentientia.leaderboards.enabled` gate
  │     → if OFF: render NOTIFY_INFO banner explaining feature disabled
  ├─ capability gate `local/sentientia_leaderboard:view`
  │     → if denied: render nothing
  ├─ board selection
  │     - explicit config $this->config->boardid
  │     - fallback: first visible board (board_manager::list_visible)
  │     - no boards visible: NOTIFY_INFO banner
  ├─ tenant gate (rejects out-of-tenant board if viewer lacks :viewall)
  ├─ ranking_engine::read_top($boardid, 5, $can_view_all)
  ├─ ranking_engine::read_my_rank($boardid, $USER->id)
  ├─ render `local_sentientia_leaderboard/board_view` template (TOP-5)
  ├─ footer: link to /local/sentientia_leaderboard/view.php?id=N (full board)
  └─ $PAGE->requires->js_call_amd
       local_sentientia_leaderboard/leaderboard_client::init
         ({ boardid: N, realtime: <flag-state> })
```

## Capabilities (`db/access.php`)

| Capability | Risk | Archetypes |
|------------|------|------------|
| `block/sentientia_leaderboard:addinstance` | RISK_SPAM \| RISK_XSS | editingteacher, manager (cloned from `moodle/site:manageblocks`) |
| `block/sentientia_leaderboard:myaddinstance` | none | user, student, teacher, editingteacher, manager (cloned from `moodle/my:manageblocks`) |

The `:myaddinstance` cap is broad on purpose — any logged-in learner
should be able to put a leaderboard on their personal dashboard.

## DB tables

None. The block reads from `local_sentientia_leaderboard_*` tables via
the parent plugin's `ranking_engine` API.

## Feature flags

Read-only consumer of the parent plugin's flags. No flags registered
here directly. Gating logic in `get_content()`:

1. `sentientia.leaderboards.enabled` (master) — when OFF, block renders
   an info banner instead of the board.
2. `sentientia.leaderboards.realtime.enabled` — passed to the AMD client
   as the `realtime` arg. When OFF, the client falls back to 30 s polling.

## Key files

```
blocks/sentientia_leaderboard/
├── version.php                                     2026052400 / 0.1.0-alpha
├── block_sentientia_leaderboard.php                Block class — init + get_content + flag gates
├── edit_form.php                                   Per-instance config: pick a boardid
├── db/access.php                                   2 caps
└── lang/
    ├── en/block_sentientia_leaderboard.php          4 strings
    └── hi/block_sentientia_leaderboard.php          4 strings (100% parity)
```

## Hindi parity

EN strings: 4 — HI strings: 4 ✅

## Tests

None on this block — coverage lives in the parent plugin's PHPUnit
classes (`ranking_engine_test`, `board_manager_test`, `optout_manager_test`,
`event_journal_test`). The block surface is exercised manually + via
visual evidence; a future Phase L.1 test pass should add a smoke test
that asserts `get_content()` returns the NOTIFY_INFO banner when the
master flag is OFF.

## Manual smoke

```
1. Master flag ON for the customer
2. Create at least one local_sentientia_lb_boards row, status=active
3. Navigate to /my/  (dashboard)
4. Click "Customise this page" → "Add a block" → "Sentientia Leaderboard"
5. Block appears with top 5 rows + "View full board" footer link
6. AMD client opens SSE; modify rankings via the parent plugin → block re-renders
```

Visual evidence: `docs/visual-evidence/2026-05-24/` (block on
dashboard, opt-out toggle, two-browser SSE liveness test).

## Open items

- [ ] PHPUnit test for `get_content()` gating (flag OFF, missing
      capability, missing board, missing tenant — Phase L.1)
- [ ] Inline "your rank" callout when the viewer is not in top 5 (P1)
- [ ] Per-block customisation: choose top-N count (currently hardcoded 5)
- [ ] Light/dark theme polish — colour ramp parity with rest of the
      Sentientia design system
- [ ] Course-context awareness (auto-pick the course-scoped board when
      placed inside a `course-view` page)

## State card created — 2026-05-24

Initial card for the block-plugin half of the leaderboards feature.
Companion to `local_sentientia_leaderboard-state.md`.
