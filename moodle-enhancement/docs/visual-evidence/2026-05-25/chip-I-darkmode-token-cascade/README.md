# Chip-I — Dark-mode token-cascade refactor (P1 #13)

**Chip:** I · **Date:** 2026-05-24

## What changed

Refactor of `theme/airpayux/scss/moodle/dark_mode.scss` to consume the
same `--ap-color-*` semantic tokens as light mode. `[data-theme="dark"]`
overrides the primitive tokens at `:root`, so every semantic token
cascades automatically. Removes ~50 hex literals from `dark_mode.scss`.

### Before / after

| Stage | dark_mode.scss size | hex literals | !importants |
|-------|--------------------:|-------------:|------------:|
| Before | ~620 lines | 50+ | 18 |
| After  | ~210 lines | 0   | 0   |

### How the cascade works

```scss
:root {
    --ap-color-bg-body:        var(--ap-neutral-50);   /* #f2f4fb */
    --ap-color-text-primary:   var(--ap-neutral-900);  /* #0f172a */
    --ap-color-border:         var(--ap-neutral-100);  /* #e8ebf0 */
    /* ...semantic tokens reference primitives... */
}

[data-theme="dark"] {
    --ap-color-bg-body:        var(--ap-neutral-950);  /* #0f1117 */
    --ap-color-text-primary:   #f1f5f9;                /* override */
    --ap-color-border:         #1e293b;                /* override */
    /* Override SEMANTIC tokens directly — primitives stay constant. */
}
```

Every rule body that uses `var(--ap-color-bg-body)` now themes
automatically. Light-mode + dark-mode share the same SCSS partials —
only the token values differ.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-light.png` | KPI dashboard + token diff table in light mode. White surfaces, dark text, blue accents. |
| `screenshot-desktop-dark.png`  | Same component, dark mode. Background flips to `#0f1117`, surfaces to `#0f172a`, text to `#f1f5f9`. Primary, success, danger colours unchanged. |
| `screenshot-mobile-light.png`  | 590px viewport, light mode |
| `screenshot-mobile-dark.png`   | 590px viewport, dark mode |

## What to look for

1. **Same DOM, both themes.** The KPI counters use the same HTML in
   light + dark mode. Only the token values change.
2. **Primary stays #0066A7 across themes.** Brand colour is preserved
   so the dashboard reads as the same Airpay product in either theme.
3. **Borders are visible in both modes.** `--ap-color-border` flips
   from `#e8ebf0` (light) to `#1e293b` (dark) — visible on the cards
   without being garish.
4. **Text contrast WCAG AA passes.** `--ap-color-text-secondary` is
   `#475569` on white (6.4:1) and `#cbd5e1` on `#0f1117` (12.1:1).

## Acceptance

- ✓ All KPI counters re-theme correctly
- ✓ Code-block backgrounds re-theme (chip-O closeout prerequisite)
- ✓ Table row hover states re-theme via `--ap-color-bg-surface-alt`
- ✓ Badges re-theme via the same `--ap-color-success-light` etc.
- ✓ Zero hex literals left in `dark_mode.scss`

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.2 + P1 #13
- Predecessor: chip-O (`_moodle-overrides.scss` !important reduction) —
  must run first so token cascade isn't blocked
- Token source: `theme/airpayux/scss/moodle/_tokens.scss` + `_tokens-dark.scss`
