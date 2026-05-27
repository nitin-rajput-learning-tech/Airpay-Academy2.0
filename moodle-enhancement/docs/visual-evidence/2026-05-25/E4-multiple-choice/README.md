# Phase E.4 — Multiple Choice Question Type (Visual Evidence)

**Date:** 2026-05-25
**Plugin:** `local_sentientia_live` v0.1.3-alpha (2026052501)
**Branch:** `claude/trusting-hypatia-ohwH8`
**Workstream:** E — Live engagement (Mentimeter clone)

---

## Why this folder is empty

The Phase E.4 chip is implemented in a cloud Linux container (the
remote Claude Code execution environment); it has no XAMPP, no Apache,
no Chrome, and no access to `http://localhost:8080`. Screenshots
cannot be captured here. Nitin's local environment is the only place
that can produce the visual evidence — this README documents the
exact capture procedure and the acceptance bar each screenshot must
meet.

When Nitin's local run completes, drop the PNGs alongside this README
(filenames listed below). The PROJECT-STATE H2 entry treats this
folder as the evidence anchor.

---

## Plugin install + version bump

```powershell
# From repo root in Windows:
Copy-Item moodle-enhancement\local\sentientia_live\* `
          C:\xampp\htdocs\moodle5\public\local\sentientia_live\ `
          -Recurse -Force
Set-Location C:\xampp\htdocs\moodle5\public
php admin\cli\upgrade.php --non-interactive
php admin\cli\purge_caches.php
```

Expected upgrade output: `local_sentientia_live  2026052402 → 2026052501  OK`.

## Required feature flag flips (Site administration → Switchboard)

| Flag | Default | Set to | Why |
|---|---|---|---|
| `live.enabled` | OFF | **ON** | Master gate for the entire stream |
| `live.realtime.enabled` | ON | leave ON | SSE updates the bar chart live |
| `live.questiontype.multichoice` | OFF | **ON** | This chip's gate |

## Test session setup (do once)

1. Sign in as a trainer with `local/sentientia_live:create`
   (e.g. `nitin.rajput@airpay.co.in`).
2. Navigate to `/local/sentientia_live/trainer/index.php`.
3. Click "Create session", title "E.4 MC demo".
4. Click "Add slide", pick **Multiple choice**.
5. Question text: `What's your favourite payment rail?`.
6. Options: `UPI`, `IMPS`, `Card`, `Wallet` (4 options to exercise both
   the 2-option lower bound and the 6-option upper bound visually).
7. Save the slide. Click "Show now" on the slide row.
8. Click "Start session" — session enters `live` state, code shown.

## Screenshots to capture

Save each one in this folder with the exact filename listed. Keep the
device-toolbar viewport at the dimensions noted. PNG, no compression
loss.

### `01-audience-radio-desktop.png` (desktop, 1440×900)

- Open a SECOND Chrome window in Incognito (anonymous participant).
- Visit `/local/sentientia_live/audience/join.php`.
- Enter the 6-digit code, display name `Anon-A`.
- The audience play page renders the question + 4 radio options.
- **Acceptance:**
  - Question heading reads `What's your favourite payment rail?`
  - 4 radio cards stacked vertically (UPI / IMPS / Card / Wallet).
  - Submit button uses airpay-primary blue (`#0066A7`).
  - No JS console errors.

### `02-audience-buttons-mobile.png` (mobile, 390×844 — iPhone 12)

- Edit the slide → set `render_style` to `buttons` (via the form's
  Display style dropdown).
- Reload the audience tab at 390×844 viewport.
- **Acceptance:**
  - 4 large tap-target buttons (one option each).
  - Each button visibly ≥48px tall (mobile WCAG target size).
  - Submit button still primary blue, full width.

### `03-trainer-empty-bar-chart.png` (desktop, 1440×900)

- Trainer tab on `/local/sentientia_live/trainer/run.php?id={sessid}`.
- BEFORE any vote arrives — "Waiting for the first response…" empty
  state visible.
- **Acceptance:** Empty-state copy in English. Audience-count counter
  shows ≥1 (the anon participant joined).

### `04-trainer-live-bar-chart.png` (desktop, 1440×900)

- Cast 2 votes from the audience tab for `UPI`, 1 vote for `IMPS`
  (open 3 incognito windows or reuse the join URL with different
  names).
- Capture the trainer's bar chart with bars at 2:1 ratio.
- **Acceptance:**
  - 4 bars in option order (UPI, IMPS, Card, Wallet).
  - Bar widths reflect counts (UPI longest at 100%, IMPS at 50%,
    others at 0%).
  - Total responses badge reads `3 responses`.
  - **No page reload happened during voting** — the SSE event drove
    the chart updates. If a page-reload spinner flashed, the SSE wire
    is broken.

### `05-sse-chart-update-mid-vote.png` (side-by-side, 2880×900)

- Two browser windows side-by-side (Chrome built-in tile view).
  Left = trainer run page. Right = audience play page.
- Audience submits a vote. Within ≤300ms, the trainer's bar chart
  width grows for the chosen option.
- Capture the trainer half mid-animation (bar partly grown). This is
  the screenshot that proves SSE works end-to-end.
- **Acceptance:** The trainer's `<span class="sentientia-bar-count">`
  for the voted option increments by 1 in the screenshot vs the
  previous frame (compare 04 vs 05).

### `06-correct-answer-reveal.png` (desktop, 1440×900)

- Edit slide → set Correct option to `UPI` (option 1).
- Trainer run page reloads → option 1 row gains `border-success`
  highlight + green "Correct" badge.
- **Acceptance:** Only the correct row carries the green badge.

## Acceptance gate (all must be true)

- [ ] All 6 PNGs present in this folder.
- [ ] No JS console errors in any screenshot's devtools panel.
- [ ] Trainer's `data-slideid` attribute matches the URL `id` param.
- [ ] `local_sentientia_live` version reads `2026052501` in Site
  administration → Plugins overview.

## Console / SSE verification commands

```javascript
// Run in the audience tab's devtools console after joining:
document.querySelector('.sentientia-mc-audience').dataset.slideid
// Should return a positive integer matching the trainer's slide id.

// Run in the trainer tab's devtools console:
new EventSource(M.cfg.wwwroot + '/local/sentientia_live/stream.php?sessionid=' + SESSIONID).onmessage = (e) => console.log(e.data)
// Should log a stream of `{type: 'response_added', ...}` lines as votes arrive.
```

---

## Files in this chip (no screenshots required for these)

- `moodle-enhancement/local/sentientia_live/classes/question_types/multiple_choice.php`
- `moodle-enhancement/local/sentientia_live/templates/qt_multiple_choice_audience.mustache`
- `moodle-enhancement/local/sentientia_live/templates/qt_multiple_choice_result.mustache`
- `moodle-enhancement/local/sentientia_live/tests/multiple_choice_test.php`
- `moodle-enhancement/local/sentientia_live/lang/{en,hi}/local_sentientia_live.php` (+10 keys each)
- `moodle-enhancement/local/sentientia_live/version.php` (bumped to 2026052501)
- `moodle-enhancement/PROJECT-STATE.md` (H2 appended)
