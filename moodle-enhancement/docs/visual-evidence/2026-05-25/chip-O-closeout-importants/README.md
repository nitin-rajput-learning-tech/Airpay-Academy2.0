# Chip-O closeout — `_moodle-overrides.scss` !important reduction buckets 5+6

**Chip:** `jolly-meitner-XdiGI` · **Merge:** `4f55c0d3e` · **Date:** 2026-05-24

## What changed

Final two buckets of the `_moodle-overrides.scss` `!important` reduction
campaign: course-header, course-drawer, pagination, table, filter. Brings
running total to **30 active** (-77.9% from baseline 136).

### Bucket 5 — Course header

| Rule | !importants removed |
|------|--------------------:|
| `.course-header h1`     | 4 |
| `.course-summary`       | 3 |
| `.section-summary`      | 2 |
| **Bucket 5 total**      | **9** |

### Bucket 6 — Pagination / table / filter

| Rule | !importants removed |
|------|--------------------:|
| `.pagination .page-link`  | 5 |
| `table.generaltable`      | 4 |
| `.filter form`            | 3 |
| **Bucket 6 total**        | **12** |

### Running total

| Stage | !importants |
|-------|------------:|
| Baseline (audit start)       | 136 |
| After buckets 1–4 (Chip O₁₋₄) | 51  |
| After bucket 5               | 42  |
| **After bucket 6 (this)**    | **30** |

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-buckets-chart.png` | Bar chart showing the seven stages (baseline → O₁ → ... → O₆) with red→amber→green colouring as count drops. Two breakdown cards below list the per-rule removals. |
| `screenshot-desktop-dark.png`          | Same chart in dark mode — bars retain semantic colours; text + border tokens flip |
| `screenshot-mobile-buckets-chart.png`  | 590px viewport — bars narrow, label text remains readable |

## What to look for

1. **The chart slope.** Steep drop from baseline 136 → 96 (O₁) when
   the biggest offenders (button, panel, drawer overrides) were
   refactored. Diminishing returns into buckets 5+6 (12 + 9 removals)
   but the principle is consistent.
2. **Visual diff: zero regression.** This is a refactor — token cascade
   now reaches every rule. Dark-mode overrides apply cleanly without
   an !important escalation race.
3. **Companion to chip-I.** Chip-I (dark-mode token cascade) needed
   the `!important` count brought down before its rules could compose
   correctly. Chip-O is the upstream prerequisite.

## Acceptance

```bash
grep -c "!important" theme/airpayux/scss/moodle/partials/_moodle-overrides.scss
# expected: 30 (down from 136)
```

- ✓ Token cascade reaches every overridden rule
- ✓ Dark-mode rules no longer need their own `!important` escalation
- ✓ Visual regression: zero (per audit walk B2)

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.2 + Appendix C P2 #18
- Predecessor: wave3-chip-O (initial 4-bucket pass on `_moodle-overrides.scss`)
- Companion: chip-I (P1 #13 dark-mode token cascade) — depends on this
