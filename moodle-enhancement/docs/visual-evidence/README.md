# Visual Evidence Ledger — Sentientia LMS

Every session that touches UI ends with screenshots saved here, dated.

## Why this exists

Nitin's Day 0 directive: "we will not go to production until [...] visually
verified, UI/UX is world class". This folder is where verification happens.

Each session that ships UI changes leaves:
1. Screenshots — desktop (1920×1080) + mobile (390×844 iPhone 14 viewport)
2. Short README describing what changed
3. Before/after pairs when modifying existing UI

Nitin reviews each session's screenshots before approving merge to production.

## Folder structure

```
visual-evidence/
├── README.md (this file)
├── YYYY-MM-DD/
│   ├── README.md (what changed this session)
│   ├── before/
│   │   ├── desktop-<surface>.png
│   │   └── mobile-<surface>.png
│   └── after/
│       ├── desktop-<surface>.png
│       └── mobile-<surface>.png
```

## Capture conventions

- **Desktop:** 1920×1080, Chrome on Windows, devtools closed, theme = airpayux
- **Mobile:** 390×844 (iPhone 14), Chrome devtools device toolbar, theme = airpayux
- **Tenants:** Capture both Airpay tenant (id=1) AND Public tenant (id=77) if styling differs per-tenant
- **Roles:** Capture as Learner (not admin — admin bypasses some access checks)
- **State:** Capture both empty-state AND populated-state if the surface has both

## File naming inside a session folder

`<surface>-<viewport>-<state>.png`

Examples:
- `dashboard-desktop-populated.png`
- `dashboard-mobile-empty.png`
- `signup-desktop-error.png`
- `course-detail-mobile-default.png`

## Required per-session README content

```markdown
# Visual Evidence — YYYY-MM-DD

## Session
[Brief description of what shipped this session]

## Surfaces affected
- /path/to/surface (description)
- /path/to/another (description)

## Reviewed against prototypes
- D:\Claude Local\Moodle Backup\03-prototypes\preview\<prototype>.html (✓ match / ✗ deviation)

## Sign-off
- [ ] Nitin reviewed
- [ ] Mobile responsive verified at 590px breakpoint
- [ ] Hindi language tested (lang=hi user switch)
- [ ] Dark mode tested (if applicable)
- [ ] Both tenants verified (if tenant-specific styling)
- [ ] Browser console: zero JS errors
```

## Index

Auto-generated 2026-09-08 (`tools`-free: a one-off Python listing of every session folder that carries a README; newest last). Two roots exist — the canonical one is this folder; a handful of early sessions live under the repo-level `docs/visual-evidence/`.

| Date | Folder | Session |
|---|---|---|
| 2026-05-20 | `2026-05-20/` | Visual Evidence — 2026-05-20 (Session 2) |
| 2026-05-21 | `2026-05-21/` | Visual Evidence — 2026-05-21 |
| 2026-05-23 | `2026-05-23/` | Visual evidence — 2026-05-23 |
| 2026-05-24 | `2026-05-24/` | Visual evidence — 2026-05-24 |
| 2026-05-24 | `2026-05-24/D4-question-types/` | Wave D4 — `local_sentientia_live` 4 question types implemented |
| 2026-05-24 | `2026-05-24/p0-followup-chip-B/` | P0 Follow-up — Chip B (navbar + footer hygiene) |
| 2026-05-24 | `2026-05-24/p0-followup-chip-C/` | P0 #5 Follow-up — Dashboard Inline-Style Cleanup (2026-05-24) |
| 2026-05-24 | `2026-05-24/p0-followup-chip-E/` | P0 follow-up chip E — `local_sentientia_live` aria-live regions |
| 2026-05-24 | `2026-05-24/p1-followup-chip-D/` | P2 #19 follow-up — inline-timing → tokens (chip-D, 2026-05-24) |
| 2026-05-24 | `2026-05-24/p1-followup-chip-H/` | Visual evidence — P1 follow-up chip H |
| 2026-05-24 | `2026-05-24/p1-followup-chip-I/` | P1 #13 — Dark-Mode Token-Cascade Refactor |
| 2026-05-24 | `2026-05-24/p1-followup-chip-J/` | P1 #10 — `_surface-profile.scss` decomposition (Chip J) |
| 2026-05-24 | `2026-05-24/p1-followup-chip-K/` | P1 #11 / F-10 — `_surface-login.scss` `!important` refactor |
| 2026-05-24 | `2026-05-24/p1-p2-followup-chip-L/` | P1 #14 + P2 #21 follow-up — chip L (2026-05-24) |
| 2026-05-24 | `2026-05-24/p1-p2-followup-chip-M/` | P1 #15 + P2 #22 follow-up — chip M (2026-05-24) |
| 2026-05-24 | `2026-05-24/wave3-chip-N/` | P1 #17 + P2 #23 follow-up — wave3-chip-N (2026-05-24) |
| 2026-05-24 | `2026-05-24/wave3-chip-O/` | P2 #18 — `_moodle-overrides.scss` `!important` Reduction |
| 2026-05-24 | `2026-05-24/wave3-chip-P/` | P2 #19 — prefers-reduced-motion stylelint enforcement (chip-P, 2026-05-24) |
| 2026-05-24 | `2026-05-24/wave3-chip-Q/` | Wave-3 chip Q — P2 #20 / F-20 coursebannerimage XSS sanitisation verification (2026-05-24) |
| 2026-05-25 | `2026-05-25/E4-multiple-choice/` | Phase E.4 — Multiple Choice Question Type (Visual Evidence) |
| 2026-05-25 | `2026-05-25/audit-walk/` | Visual evidence — 2026-05-25 Wave B2 P1 re-audit walk |
| 2026-05-25 | `2026-05-25/chip-I-darkmode-token-cascade/` | Chip-I — Dark-mode token-cascade refactor (P1 #13) |
| 2026-05-25 | `2026-05-25/chip-K-surface-login-importants/` | Chip-K — `_surface-login.scss` `!important` refactor (P1 #11 / F-10) |
| 2026-05-25 | `2026-05-25/chip-M-sentientia-live-tokens/` | Chip-M — `sentientia_live` tokens + table a11y (P1 #15 + P2 #22) |
| 2026-05-25 | `2026-05-25/chip-O-closeout-importants/` | Chip-O closeout — `_moodle-overrides.scss` !important reduction buckets 5+6 |
| 2026-05-25 | `2026-05-25/chip-P-stylelint-reduced-motion/` | Chip-P — prefers-reduced-motion stylelint rule |
| 2026-05-25 | `2026-05-25/nvda-verification/` | *(older root)* NVDA Verification Evidence — 2026-05-25 (Attempt #1 — SKIPPED) |
| 2026-05-25 | `2026-05-25/p0-B-bizlms-focus-visible/` | P0-B — `_bizlms-admin.scss` :focus-visible siblings |
| 2026-05-25 | `2026-05-25/p0-C-dashboard-chart-js-block/` | P0-C — Dashboard chart init → `{{#js}}` block |
| 2026-05-25 | `2026-05-25/p1-D-inline-timing-tokens/` | #256 / P1-D — Inline-timing → tokens (P2 #19 follow-up) |
| 2026-05-25 | `2026-05-25/p1-i255-locale-parity/` | #255 / P1 — Locale parity restored to 178/178 (kn / mr / sw) |
| 2026-05-25 | `2026-05-25/p2-I-drawer-mustache-52/` | P2-I — `drawer.mustache` Moodle 5.2 backport |
| 2026-05-25 | `2026-05-25/p3-M-ai-quiz-scaffold/` | P3-M — `local_sentientia_aiquiz` scaffold (Phase G.1) |
| 2026-05-25 | `2026-05-25/p3-N-calendar-oauth/` | P3-N — Calendar Sync Phase 2 OAuth scaffolding (Tier 2.6) |
| 2026-05-25 | `2026-05-25/p3-O-leaderboard-notifications/` | P3-O — Leaderboard L.1 rank-change notifications |
| 2026-05-25 | `2026-05-25/p3-Q-m365-graph-scaffold/` | P3-Q — `local_sentientia_m365` OAuth + Graph scaffold (Workstream C.1) |
| 2026-05-25 | `2026-05-25/p3-R-sentientia-live-question-types/` | P3-R — `sentientia_live` question-type stubs (Phase E.4-E.9) |
| 2026-05-25 | `2026-05-25/wave-c2-p2-wordcloud/` | Visual evidence — Word Cloud (Phase E.5) |
| 2026-05-25 | `2026-05-25/wave-c5-leaderboard-l1-e2e/` | Wave C5 — `local_sentientia_leaderboard` L.1 End-to-End Wiring + Verification |
| 2026-05-27 | `2026-05-27/` | Visual Evidence — 2026-05-27 |
| 2026-05-27 | `2026-05-27/` | *(older root)* Visual / test evidence — 2026-05-27 |
| 2026-05-29 | `2026-05-29/` | Visual evidence — 2026-05-29 |
| 2026-06-01 | `2026-06-01/` | Visual evidence — 2026-06-01 |
| 2026-06-02 | `2026-06-02/` | Visual evidence — 2026-06-02 |
| 2026-06-09 | `2026-06-09/` | (no README — 33 capture file(s)) |
| 2026-06-10 | `2026-06-10/` | Visual evidence — 2026-06-10 |
| 2026-06-15 | `2026-06-15/` | Public Learner Visual Audit — from scratch (2026-06-15) |
| 2026-06-16 | `2026-06-16/` | Visual evidence — 2026-06-16 — Revised Brand Book Phase 1 (teal retirement) |
| 2026-07-22 | `2026-07-22/` | Visual evidence — 2026-07-22 — Signup page UI repair + split-panel redesign + course-player fixes |
| 2026-08-03 | `2026-08-03/` | Visual evidence — 2026-08-03 — UI-NAV-AUDIT execution (all phases) |
| 2026-08-04 | `2026-08-04/` | Visual evidence — 2026-08-04 (UI-NAV residue closure + ninja package rebuild) |
| 2026-08-05 | `2026-08-05/` | Visual evidence — 2026-08-05 (Gate #3 aiquiz half + fleet migration + package rebuild) |
| 2026-08-07 | `2026-08-07/` | Visual evidence — 2026-08-07 (KeKa JML hardening, ADR-029) |
| 2026-08-20 | `2026-08-20/` | Visual evidence — 2026-08-20 (Customer-N demo: ADR-028 Phase 2.1 / ADR-021 Gate B) |
| 2026-09-03 | `2026-09-03/uat-stage-a/` | UAT Stage A evidence — 2026-09-03 |
| 2026-09-04 | `2026-09-04/` | (no README — 0 capture file(s)) |
| 2026-09-08 | `2026-09-08/uat-author-t01/` | 2026-09-08 — UAT visual check: course author gets the authoring nav (T-01) |
| 2026-09-08 | `2026-09-08/uat-courses-hindi-recheck/` | 2026-09-08 — UAT on-screen re-check: Manage Courses fix, ZEEA scoping, Hindi dashboard |
