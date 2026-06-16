# Visual evidence — 2026-06-16 — Revised Brand Book Phase 1 (teal retirement)

**Change:** adopt the official Revised airpay Brand Book (`BB-revamp-noweb.pdf`) —
Phase 1: retire the off-brand teal accent (`#0f7a73`) → brand bright-blue
(`#1985DD`) / deep-blue (`#0d5da1`) gradients across every user-facing surface.
Primary `#0066A7` + Montserrat already matched the book. See
`docs/audits/brand-revamp-2026-06/REVISED-BRAND-BOOK-2026-06.md`.

## Functional verification (decisive)

The served, compiled theme CSS (`theme/styles.php/sentientia/<rev>/all`, 1.49 MB,
which aggregates theme SCSS + every plugin's `styles.css`) was re-fetched after a
hard `theme_reset_all_caches()` and grepped:

| Check | Result |
|---|---|
| `#0f7a73` (teal accent) | **0** |
| full teal ramp (`#0d6b65 #1fa69c #0a5c56 #074d48 #043e3a #134e4a`) | **0** |
| `#1985dd` (brand bright-blue accent) | 24 |
| `#0d5da1` (brand deep-blue / gradient end) | 26 |
| `#0066a7` (brand primary) | 277 |
| HTTP status / content-type | 200 / `text/css` |
| PHP errors leaked into CSS | none |

## Screenshots (captured 2026-06-16)

- **`01-login-storefront-desktop.png`** — the marquee "landing page". Left hero is
  now a **blue → deep-blue** gradient (was blue → teal); wordmark, feature bullets,
  the `671+/183+/722+` stat tiles, and the **Log in** CTA all render in brand blue.
  **No teal.** ✓
- **`02-catalog-storefront-desktop.png`** — public course catalog (dark mode). All
  functional brand elements — **Enroll free** buttons, `Free` tags, heart icons,
  footer logo — are brand blue. **No accent teal.** ✓
  - _Phase-2 note:_ the decorative **course-card thumbnail gradients** still use a
    multi-hue variety palette (cyan `#0aa3a3`, orange, pink, purple, blue). The
    airpay accent teal `#0f7a73` is gone, but the cyan thumb reads teal-adjacent.
    Aligning this variety set to the brand secondary palette (sky / deep / orange /
    purple) is the open Phase-2 design decision — not a mechanical teal removal.

The functional CSS grep above remains the authoritative "teal is gone" proof; these
screenshots confirm the on-brand blue renders correctly on the two public surfaces.
Authenticated surfaces (dashboard, skills, profile) inherit the same token cascade
+ swept plugin styles — verified teal-free in the compiled CSS that serves them.
