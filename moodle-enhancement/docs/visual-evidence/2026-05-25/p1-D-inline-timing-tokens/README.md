# #256 / P1-D — Inline-timing → tokens (P2 #19 follow-up)

**Chip:** `clever-dijkstra-8Iczy` · **Merge:** `a6c7f1bb1` · **Date:** 2026-05-24

## What changed

54 inline transition timings across 9 `_surface-*.scss` partials migrated
to `var(--ap-transition-{quick|default|slow})`. WCAG 2.3.3 *Animation
from Interactions* (AAA) now compliant via the token cascade in
`_tokens.scss:258–265`:

```scss
@media (prefers-reduced-motion: reduce) {
  :root {
    --ap-duration-quick:    0ms;
    --ap-duration-default:  0ms;
    --ap-duration-slow:     0ms;
  }
}
```

Effect: hardcoded `transition: all 0.2s ease` literals no longer animate
when the OS reports `prefers-reduced-motion: reduce` (Windows Settings
→ Accessibility → Visual effects → Animation effects: OFF, or macOS
Reduce motion).

## Files touched (per chip-P stylelint inventory)

| Partial | Before | After |
|---------|-------:|------:|
| `_surface-courses.scss`   | 9 inline | 0 |
| `_surface-dashboard.scss` | 8 inline | 0 |
| `_surface-profile.scss`   | 7 inline | 0 |
| `_surface-login.scss`     | 6 inline | 0 |
| `_surface-catalog.scss`   | 6 inline | 0 |
| `_surface-modal.scss`     | 5 inline | 0 |
| `_surface-navbar.scss`    | 5 inline | 0 |
| `_surface-footer.scss`    | 4 inline | 0 |
| `_surface-cards.scss`     | 4 inline | 0 |
| **Total**                 | **54**   | **0** |

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-light.png` | Side-by-side before/after SCSS code blocks + 3 live cards using `--ap-transition-{quick/default/slow}` |
| `screenshot-desktop-dark.png`  | Same content rendered in dark mode — code blocks still legible, accent tokens consistent |
| `screenshot-mobile-light.png`  | 590px viewport — code blocks scroll horizontally; live cards stack |

## What to look for

1. **`var()` references replace every literal.** The "after" card lists
   `var(--ap-transition-quick)`, `var(--ap-duration-quick)`, etc. No
   `0.2s`, `150ms`, or `200ms` literals.
2. **The three duration tiers map cleanly.**
   - `quick` (150ms) — buttons, links, hovers
   - `default` (250ms) — modals, drawers
   - `slow` (400ms) — page transitions
3. **The stylelint rule (chip-P) backstops this.** Any new commit that
   reintroduces an inline literal fires `declaration-property-value-disallowed-list`.

## Acceptance

```bash
npx stylelint --config theme/airpayux/.stylelintrc.json \
              "theme/airpayux/scss/moodle/partials/_surface-*.scss"
# expected: zero violations
```

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.7 + P2 #19
- Companion: chip-P (the enforcing stylelint rule)
- WCAG 2.3.3 Animation from Interactions (AAA): https://www.w3.org/WAI/WCAG21/Understanding/animation-from-interactions.html
- Token source: `theme/airpayux/scss/moodle/_tokens.scss:195–264`
