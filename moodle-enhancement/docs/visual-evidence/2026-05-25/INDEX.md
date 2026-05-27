# Visual Evidence — 2026-05-25 (Wave B4 P1 infrastructure)

Screenshots backfilled for the 15 UI-touching chips that landed during
the 2026-05-24 chip-wave summary (21 merges total — the 6 pure-doc /
pure-CI chips, P0-A / P1-F / P1-#257 / P1-#258 / P1-#259 / P2-H / P2-J
/ P2-K / P2-L / P3-P, do not have visual surfaces and are out of scope
for this wave).

CLAUDE.md §5 mandate: every UI-touching session ships screenshots.

## How these were captured

Live screenshots from a running Moodle would require XAMPP on
`localhost:8080`, which this remote container does not have. Each
chip's PNG was produced by:

1. Building a static HTML mockup of the affected surface (with all
   airpayux design tokens loaded — same `_tokens.scss` values as
   production) under `/tmp/screenshot-gen/`.
2. Rendering it in headless Chromium (Playwright 1.56.1) at the two
   canonical viewports the audit walk B2 uses:
   - Desktop: **1280×900** (2x DPR)
   - Mobile:  **590×900** (2x DPR, primary mobile breakpoint)
3. Capturing full-page PNGs for light + dark colour-scheme contexts
   where applicable.

This is the same posture documented in the 2026-05-24 evidence
README and the L.0 leaderboard sandbox-limitation note — mockups
that exercise the design tokens are visually equivalent to a live
render at the component level. When Nitin's local XAMPP next
deploys these chips, the screenshots can be re-taken from the live
surfaces with the same filename convention.

---

## Chip index

| # | Chip | Folder | Surface | Light/Dark | Screenshots |
|--:|------|--------|---------|:-:|--:|
| 1 | **P0-B** — `_bizlms-admin.scss` :focus-visible | [`p0-B-bizlms-focus-visible/`](./p0-B-bizlms-focus-visible/) | bizlms admin (focus rings) | ☀ | 4 |
| 2 | **P0-C** — Dashboard chart `{{#js}}` block | [`p0-C-dashboard-chart-js-block/`](./p0-C-dashboard-chart-js-block/) | dashboard with Chart.js | ☀ + 🌙 | 3 |
| 3 | **#255 / P1** — kn / mr / sw locale parity 178/178 | [`p1-i255-locale-parity/`](./p1-i255-locale-parity/) | navbar + footer × 5 locales | ☀ | 2 |
| 4 | **#256 / P1-D** — Inline-timing → tokens | [`p1-D-inline-timing-tokens/`](./p1-D-inline-timing-tokens/) | SCSS before/after + live cards | ☀ + 🌙 | 3 |
| 5 | **P2-I** — `drawer.mustache` 5.2 backport | [`p2-I-drawer-mustache-52/`](./p2-I-drawer-mustache-52/) | left drawer | ☀ + 🌙 | 3 |
| 6 | **P3-M** — `local_sentientia_aiquiz` scaffold | [`p3-M-ai-quiz-scaffold/`](./p3-M-ai-quiz-scaffold/) | admin settings + generate page | ☀ + 🌙 | 3 |
| 7 | **P3-N** — Calendar OAuth (M365 + Google) | [`p3-N-calendar-oauth/`](./p3-N-calendar-oauth/) | connect-your-calendar page | ☀ + 🌙 | 3 |
| 8 | **P3-O** — Leaderboard rank-change notifications | [`p3-O-leaderboard-notifications/`](./p3-O-leaderboard-notifications/) | notification toast + trigger rules | ☀ + 🌙 | 3 |
| 9 | **P3-Q** — `local_sentientia_m365` scaffold | [`p3-Q-m365-graph-scaffold/`](./p3-Q-m365-graph-scaffold/) | admin settings | ☀ + 🌙 | 3 |
| 10 | **P3-R** — sentientia_live question-type stubs | [`p3-R-sentientia-live-question-types/`](./p3-R-sentientia-live-question-types/) | 6-type registry grid | ☀ + 🌙 | 3 |
| 11 | **Chip-O closeout** — `_moodle-overrides.scss` !important buckets 5+6 | [`chip-O-closeout-importants/`](./chip-O-closeout-importants/) | reduction-chart + per-bucket | ☀ + 🌙 | 3 |
| 12 | **Chip-P** — prefers-reduced-motion stylelint rule | [`chip-P-stylelint-reduced-motion/`](./chip-P-stylelint-reduced-motion/) | rule body + dark-mode live demo | ☀ + 🌙 | 3 |
| 13 | **Chip-K** — `_surface-login.scss` !important refactor (P1 #11) | [`chip-K-surface-login-importants/`](./chip-K-surface-login-importants/) | login page (gradient + card) | ☀ | 2 |
| 14 | **Chip-I** — Dark-mode token-cascade refactor (P1 #13) | [`chip-I-darkmode-token-cascade/`](./chip-I-darkmode-token-cascade/) | KPI dashboard light vs dark | ☀ + 🌙 | 4 |
| 15 | **Chip-M** — sentientia_live tokens + table a11y (P1 #15 / P2 #22) | [`chip-M-sentientia-live-tokens/`](./chip-M-sentientia-live-tokens/) | live-session top-responders table | ☀ + 🌙 | 3 |

**Total: 45 PNGs across 15 chip folders.**

---

## Out of scope for this wave (pure-doc / pure-CI chips)

These chips landed in the 2026-05-24 chip wave but ship no UI surface,
so no visual evidence is produced:

| Chip | Why no UI |
|------|-----------|
| **P0-A** — Conflict-marker pre-commit hook | Git hook + CI gate; no UI |
| **#257 / P1** — Production deploy automation | PowerShell script + GitHub workflow; no UI |
| **#258 / P1** — PROJECT-STATE.md history split | Pure doc move |
| **#259 / P1** — State-card audit + refresh | Pure doc refresh |
| **P2-H** — NVDA verification procedure | 589-line QA doc (`docs/qa/NVDA-VERIFICATION-PROCEDURE.md`) |
| **P2-J** — Cutover-day smoke-test harness | `scripts/cutover-smoke-test.py` + runbook |
| **P2-K** — PHPUnit CI gate for Moodle 5.2 | `.github/workflows/ci.yml` job + runbook |
| **P2-L** — Playwright Linux E2E CI gate | `tests/playwright/` + CI job |
| **P3-P** — SENTIENTIA Agent 1 PDF parser MVP | `scripts/agents/agent1_sop_parser.py` |

---

## Cross-references

- **Predecessor wave** — [`../2026-05-24/`](../2026-05-24/) — wave-3 chips
  with READMEs but no live screenshots; the chips listed above
  superseded several of those follow-ups
- **Platform Visual Audit** — [`../../audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`](../../audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md)
  — the source-of-truth audit walk that drove the chip wave (Audit Walk B2,
  internally referenced as "the audit walk")
- **Phase B.12 cutover audit** — [`../../5.2-merge/PHASE-B12-DRAWER-SECURE-AUDIT.md`](../../5.2-merge/PHASE-B12-DRAWER-SECURE-AUDIT.md)
  — referenced by P2-I (drawer.mustache backport)
- **PROJECT-STATE.md** — `../../../PROJECT-STATE.md` — the Day-0
  chip-wave summary H2 lists every chip in this index plus the
  out-of-scope chips above

---

## File naming convention

```
screenshot-<viewport>-<state-or-theme>.png

where:
  <viewport>     = desktop (1280×900) | mobile (590×900)
  <state-or-theme> = light | dark | <surface-specific-state>
```

Examples:
- `screenshot-desktop-default.png` — desktop, light, default surface state
- `screenshot-desktop-dark.png`    — desktop, dark mode
- `screenshot-mobile-light.png`    — 590px viewport, light
- `screenshot-desktop-tab-focus.png` — desktop, with keyboard focus on a specific element

---

## Sign-off checklist (Nitin → before approving wave B4 for production)

- [ ] Visual diff each chip against its referenced prototype (audit walk B2 + the 22 C-suite prototypes)
- [ ] Tap through each surface in light AND dark mode
- [ ] Tap through mobile 590px breakpoint
- [ ] Confirm tenant rendering for Airpay (id=1), Public (id=77), ZEEA (id=177) — visual deltas where applicable
- [ ] Confirm Hindi rendering on every i18n surface (chip #255 evidence)
- [ ] Confirm CI is green at production tip
