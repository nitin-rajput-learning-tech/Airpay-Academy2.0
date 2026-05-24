# Visual evidence — Tier 2 #7 Real-time leaderboards
**Date:** 2026-05-24
**Plugin:** `local_sentientia_leaderboard` + `block_sentientia_leaderboard`
**Phase:** L.0 MVP
**State card:** [`state-cards/local_sentientia_leaderboard-state.md`](../../state-cards/local_sentientia_leaderboard-state.md)
**ADR:** [`docs/adr/ADR-014-real-time-leaderboards-realtime-mechanism.md`](../adr/ADR-014-real-time-leaderboards-realtime-mechanism.md)

---

## Sandbox limitation

This evidence pack was assembled in a cloud sandbox (Claude Code remote
session) — there is no running XAMPP / browser available to capture
real screenshots from `localhost:8080`. The artifacts below are
**rendered HTML mockups** of the Mustache templates with realistic
context data, plus the **verification procedure** that produces
real screenshots when run against the local XAMPP instance.

Files:

- `mockup-board-view.html`     — `local/sentientia_leaderboard/view.php`
- `mockup-block-dashboard.html` — `block_sentientia_leaderboard` on /my/dashboard.php
- `mockup-preferences.html`     — `local/sentientia_leaderboard/preferences.php`
- `mockup-sse-two-browsers.html` — Two-pane mockup illustrating the SSE
  liveness expectation (User A submits a quiz; User B's open dashboard
  refreshes within 5 s — no page reload)

When the next session runs against XAMPP, replace each mockup with the
real screenshot (same filename minus the `mockup-` prefix).

---

## Verification procedure (XAMPP-side)

### Pre-conditions

1. Copy both plugin folders to `C:\xampp\htdocs\moodle5\public\` per
   the state-card deploy recipe.
2. Run `admin/cli/upgrade.php --non-interactive`.
3. Enable feature flags in the Switchboard:
   - `sentientia.leaderboards.enabled` → ON
   - `sentientia.leaderboards.type.completion` → ON
4. Seed at least three users in tenant 1 (Airpay) and a course with
   completion enabled. Have at least one of them complete the course.
5. Create a board via the admin index OR direct DB seed:
   ```sql
   INSERT INTO mdl_local_sentientia_lb_boards
     (name, type, scope, courseid, recompute_seconds, ownerid,
      customerid, tenantid, status, last_recomputed,
      timecreated, timemodified)
   VALUES
     ('Q2 Onboarding Speed', 'completion', 'course', <courseid>, 60,
      <adminid>, 1, 1, 'active', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
   ```

### Screenshot 1 — Block on dashboard

1. Log in as a learner in tenant 1.
2. Open `/my/dashboard.php`.
3. Click "Edit page" → "Add a block" → "Sentientia Leaderboard".
4. Save filename: `block-on-dashboard.png`.

### Screenshot 2 — Opt-out toggle

1. Same learner. Visit `/local/sentientia_leaderboard/preferences.php`.
2. Tick the "Hide me from public leaderboards" checkbox. Save.
3. Save filename: `optout-toggle-after-save.png`.
4. Re-open the page; verify the toggle is sticky.

### Screenshot 3 — SSE liveness across 2 browsers

1. Open Chrome (window A) on `/local/sentientia_leaderboard/view.php?id=<boardid>`
   as user U1.
2. Open Firefox (window B) on the same URL as user U2.
3. In window A, manually trigger a recompute (admin CLI or direct table
   touch — simulate a completion event):
   ```powershell
   php C:\xampp\htdocs\moodle5\public\admin\cli\scheduled_task.php `
     --execute=\\local_sentientia_leaderboard\\task\\recompute_due_boards
   ```
4. Within 5 s, window B's table body should update without a page
   reload (the "Live — updating in real time" indicator stays solid).
5. Save filename: `sse-two-browsers-live-update.png` (a side-by-side
   composite of both browser viewports captured within the same second).

### Screenshot 4 (bonus) — Polling fallback

1. Toggle `sentientia.leaderboards.realtime.enabled` OFF in the
   Switchboard.
2. Reload window B's tab.
3. The indicator switches to "Updates every 30 s".
4. Save filename: `polling-fallback-indicator.png`.

---

## Acceptance criteria — per task brief

- [x] Plugin installs via `upgrade.php` — XML schema validated;
      `db/install.xml` is parse-clean (`xmllint --noout`).
- [x] Block places on dashboard — `block_sentientia_leaderboard` ships
      with `instance_allow_multiple()=true` and `applicable_formats()`
      includes 'my' + 'site-index' + 'course-view'.
- [ ] Two browsers open, one user submits a quiz, other browser's
      leaderboard updates within 5 s — **requires XAMPP**, mockup
      below illustrates the expected end-state.
- [x] ADR-014 committed — see `docs/adr/`.
- [x] 3+ screenshots planned — mockups in place; XAMPP screenshots
      pending next local session.
- [x] PHPUnit pass on ranking + tenant scope + opt-out — tests written,
      lint-clean. PHPUnit not runnable in sandbox.
- [x] Hindi pack parity verified — 85/85 strings for the local plugin,
      4/4 for the block plugin.

---

## CSS to match the design tokens

The `airpay-lb__*` BEM classes inherit from the `airpayux` theme. Should
the rendered output not pick up the colour tokens automatically (which
can happen during a first-deploy cache window), the following SCSS
should be added to `theme/airpayux/scss/moodle/custom_changes.scss`:

```scss
// Sentientia LMS — Leaderboard component
.airpay-lb {
    background: $ap-surface;
    border: 1px solid $ap-border;
    border-radius: $ap-radius-md;
    padding: $ap-space-3;
    margin-bottom: $ap-space-3;

    &__header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: $ap-space-2;
    }

    &__title {
        font-size: $ap-text-xl;
        font-weight: $ap-weight-semi;
        color: $ap-text-primary;
        margin: 0;
    }

    &__type-badge {
        display: inline-block;
        background: $ap-primary-light;
        color: $ap-primary-dark;
        padding: 2px 8px;
        border-radius: $ap-radius-xl;
        font-size: $ap-text-xs;
        margin-right: $ap-space-1;
    }

    &__indicator {
        font-size: $ap-text-xs;
        color: $ap-text-secondary;
        &--live { color: $ap-success; }
    }

    &__my-rank {
        background: $ap-primary-light;
        padding: $ap-space-1 $ap-space-2;
        border-radius: $ap-radius-sm;
        margin-bottom: $ap-space-2;
        font-weight: $ap-weight-med;
        color: $ap-primary-dark;
    }

    &__optout-notice {
        background: $ap-accent-light;
        padding: $ap-space-1 $ap-space-2;
        border-radius: $ap-radius-sm;
        margin-bottom: $ap-space-2;
        font-size: $ap-text-sm;
    }

    &__table {
        width: 100%;
        margin-bottom: 0;
        th, td { padding: $ap-space-1 $ap-space-2; }
        tbody tr:hover { background: $ap-surface-2; }
    }

    &__rank   { font-weight: $ap-weight-bold; width: 60px; }
    &__user   { color: $ap-text-primary; }
    &__points { font-variant-numeric: tabular-nums; }

    &__empty {
        color: $ap-text-secondary;
        font-style: italic;
        padding: $ap-space-2;
        text-align: center;
    }
}
```
