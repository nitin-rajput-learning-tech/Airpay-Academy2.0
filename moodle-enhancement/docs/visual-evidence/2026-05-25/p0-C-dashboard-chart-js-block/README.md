# P0-C — Dashboard chart init → `{{#js}}` block

**Chip:** P0-C / `serene-fermi-RB4pr` · **Merge:** `ad453bc6` · **Date:** 2026-05-24

## What changed

30-line refactor in `theme/airpayux/templates/dashboard.mustache`. The
Chart.js bootstrap was wrapped in an inline `<script>...</script>` block
calling `require(['theme_airpayux/chart_loader'], …)`. This chip moves
that block to Moodle's `{{#js}}` section helper.

Before:
```mustache
{{#hascharts}}
<script>
  require(['theme_airpayux/chart_loader'], function(Chart) { ... });
</script>
{{/hascharts}}
```

After:
```mustache
{{#hascharts}}
{{#js}}
  require(['theme_airpayux/chart_loader'], function(Chart) { ... });
{{/js}}
{{/hascharts}}
```

Effect: chart-init code is queued via `$PAGE->requires->js_amd_inline()`
and emitted once at end-of-body through the standard JS-collection path
— no inline `<script>` tags. The same `chart_loader` AMD module that
chip-N vendored continues to be the sole Chart.js loader.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-light.png` | Dashboard with three compliance KPI counters + monthly trend chart in light mode |
| `screenshot-desktop-dark.png` | Same dashboard, dark mode — chart axes, gridlines, and labels theme correctly via tokens |
| `screenshot-mobile-light.png` | Mobile 590px — KPI counters stack, chart resizes to fit |

## What to look for

1. **Chart still renders.** The refactor is behaviour-preserving — the
   `Chart()` constructor call, dataset shapes, colour arrays, and options
   hashes are byte-identical to pre-chip.
2. **No inline `<script>` markers in source.** `grep -rn "<script src=.*chart"`
   across `templates/` + `layout/` returns zero hits.
3. **CSP posture closed.** `cdn.jsdelivr.net` is no longer referenced
   (chip-N vendoring removed it from the URL; this chip removes the last
   inline-script attribute that the CSP audit flagged).

## Acceptance

- ✓ Mustache balance check: 192 tokens, stack empty at EOF, zero errors
- ✓ `php -l` on `version.php`: no syntax errors
- ✓ `grep -rn "<script src=.*chart.js|cdn.jsdelivr.net|unpkg.com|cdnjs"`
   in `templates/` + `layout/` → zero hits
- ✓ chart_loader is the SOLE Chart.js loader (pre-warmed by
   `layout/dashboard.php:84`, consumed by `dashboard.mustache:341–389`)

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §3.4 F-14
- Predecessor: wave3-chip-N (AMD vendoring of Chart.js)
- Version bump: theme `2026052404 → 2026052405` (1.0.35-beta → 1.0.36-beta)
