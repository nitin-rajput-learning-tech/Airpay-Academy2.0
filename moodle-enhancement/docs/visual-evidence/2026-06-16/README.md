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

## Phase 2 — login redesign anchor (`03-login-redesign-desktop.png`)

First brand-forward surface (theme v2026061601). The login hero now carries the
brochure's signature elements on top of the blue→deep gradient:
- **Faint "a" monogram watermark** bottom-right (a giant low-opacity Montserrat
  "a" + soft halo disc) — echoes the brand's circular "a" device (brand book p07).
- **Sparing brand-orange accent bar** (`--ap-orange-500 #ed692b`) under the title —
  the book's one allowed "occasional warm pop", used exactly once on the hero.
- Otherwise blue-dominant + white space, per the book's philosophy.

This is the **design anchor** for the full Phase-2 redesign; the same language
(monogram device + sparing orange + blue gradients) is applied to the surfaces below.

## Phase 2 — frontpage + storefront (`04-frontpage-hero-desktop.png`, `05-catalog-cardthumbs-desktop.png`)

Applied the login anchor language across the rest of the landing (theme v2026061602),
Chrome-verified on local XAMPP:
- **Frontpage hero** (`04`): blue→deep gradient, the **orange accent bar under the title**
  (matches login), the faint **"a" monogram** watermark bottom-right, badge + CTAs blue.
- **Catalog storefront** (`05`): the course-card **thumbnail variety palette** is remapped
  from the off-brand cyan/magenta/pink/gold set to **blue-dominant + ONE sparing orange +
  ONE sparing purple** variant — per the book's "blue-dominant, sparing orange/purple".
  The cyan `#0aa3a3` / green / pink / gold are gone.

Together with the login anchor, this is the **full landing + login brand-forward redesign**
(your Phase-2 choice). Live deploy remains Nitin-gated.
