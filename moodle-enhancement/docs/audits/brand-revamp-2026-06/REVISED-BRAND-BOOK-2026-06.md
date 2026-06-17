# Revised airpay Brand Book — adoption spec (2026-06-16)

**Source of truth:** `BB-revamp-noweb.pdf` (25-page official airpay Brand Guideline,
provided by Nitin 2026-06-16). Text + key pages extracted to this folder
(`brandbook-text.txt`, `pages/p01,p06,p07,p14,p15.png`).

**This supersedes** `docs/audits/AIRPAY-CORP-BRANDING-STUDY-2026-06-11.md` (which was
reverse-engineered from the airpay.co.in website). Where they differ, **this book wins**
(e.g. brand orange is `#ed692b`, not the website's `#f98613`; primary blue is `#0066A7`).

---

## 1. The headline: we are already 80% there

| Dimension | Brand book | Current Sentientia theme | Verdict |
|---|---|---|---|
| Primary colour | **Airpay Blue `#0066A7`** | `--ap-blue-600: #0066A7` | ✅ **exact match** |
| Primary font | **Montserrat** (Light/Reg/SemiBold/Bold) | `$ap-font: 'Montserrat'` | ✅ **exact match** |
| Body background | white + **blue** tints | `--ap-neutral-50: #f2f4fb` (slight lavender) | ◑ minor shift to blue-tint |
| **Accent colour** | blue-family + sparing orange; **no teal** | `--ap-color-accent: #0f7a73` (teal) | ❌ **off-brand — the main fix** |

So this is a **token refinement**, not a rebuild. The one real correction is the
**teal accent → brand blue-family**, plus adding the brand's secondary palette.

---

## 2. Exact palette (p13–15, verbatim)

### Primary
| Swatch | Hex | RGB | CMYK | Role |
|---|---|---|---|---|
| **Airpay Blue** | `#0066A7` | 0,102,167 | 93,60,7,1 | THE primary — confidence/stability |
| White | `#ffffff` | 255,255,255 | 0,0,0,0 | Primary; **use in smaller proportion than blue** |

### Secondary
| Swatch | Hex | RGB | Note |
|---|---|---|---|
| Bright blue ("for decks") | `#1985DD` | 25,133,221 | secondary blue — UI accents, links-active |
| Sky tint | `#9cdbf4` | 156,219,244 | light fills, tinted sections |
| Deep blue | `#0d5da1` | 13,93,161 | pressed/hover, gradient end |
| **Orange** | `#ed692b` | 237,105,43 | warm pop — **sparing** CTA/emphasis only |
| Grey | `#bababa` | 186,186,186 | neutral dividers/disabled |
| Purple | `#6d58a5` | 109,88,165 | tertiary highlight — sparing |

### Colour philosophy (verbatim intent)
> "subtle and classy … airpay blue is the primary … options of mixing primary with
> secondary in a **gradient** format … shade-plus / shade-minus when it calls for.
> Too much shade changes are not appreciated … **Tints of blue and white are preferred
> with subtle gradients** of other colours." Blue = confidence/stability/creativity;
> white = innovation/success/freedom (smaller proportion than blue).

**Implication for UI:** blue-dominant, white space, **subtle blue→deep-blue gradients**
on hero/CTA blocks, orange and purple used only as occasional accents — never as broad
UI fills.

## 3. Typography
**Montserrat** universally (websites, web-pages, social, print, decks). Weights in use
across the book: Light, Regular, SemiBold (headings/labels), Bold (emphasis, names).
Already the theme font — no change needed beyond confirming weight usage.

## 4. Logo
- Wordmark: lowercase **"airpay"** in bold Airpay Blue. Product suffixes (`payments`,
  `vyaapaar`, `point`) may use blue or **orange**; the "o" in `point` is a target dot device.
- Brand graphic: a large faint circular **"a" monogram** used as a watermark/section device.
- Clearspace: the height of the "a" (= `x`) governs proportion. Don'ts (p8): no crop,
  distort, transparency change, recolour, or re-typeset.

---

## 5. Token mapping → `theme/sentientia/scss/moodle/_tokens.scss`

**Keep** (already correct): `--ap-blue-600 #0066A7`, `--ap-color-primary`, Montserrat.

**Align the blue ramp to the book's secondaries** (exact brand values at the key stops):
- `--ap-blue-200` → `#9cdbf4` (sky tint)
- `--ap-blue-400` → `#1985DD` (bright blue "for decks")
- `--ap-blue-700` → `#0d5da1` (deep blue — gradient end / pressed)

**Add brand secondary ramps** (new primitives):
- `--ap-orange-500: #ed692b` (+ `-light`, `-dark` shades) — warm accent
- `--ap-purple-500: #6d58a5` — tertiary
- (grey #bababa already covered by the neutral ramp)

**Re-point the accent semantic token off teal → brand blue:**
- `--ap-color-accent: var(--ap-blue-400)` (#1985DD)  ← was teal `#0f7a73`
- `--ap-color-accent-hover: var(--ap-blue-600)`
- `--ap-color-accent-light: var(--ap-blue-50)`

**New semantic tokens for the sparing warm/tertiary pops:**
- `--ap-color-highlight: var(--ap-orange-500)` (#ed692b) — CTA emphasis, badges (sparing)
- `--ap-color-highlight-hover`, `--ap-color-tertiary: var(--ap-purple-500)`

**Body background → blue-tint** (per "tints of blue and white preferred"):
- `--ap-neutral-50` stays for compatibility; consider `--ap-color-bg-body` → a blue-tinted
  off-white (`#eef4fb`) for a subtler, on-brand base. (Low-risk; verify contrast.)

**Gradients** (new tokens for hero/CTA, per the book's gradient guidance):
- `--ap-gradient-brand: linear-gradient(135deg, #0066A7 0%, #0d5da1 100%)`
- `--ap-gradient-brand-bright: linear-gradient(135deg, #1985DD 0%, #0066A7 100%)`

The teal ramp (`--ap-teal-*`) is **retained in the file** (unreferenced by the accent
token) so the change is trivially revertible and any stray teal consumer still resolves.

---

## 6. Adoption phases

- **Phase 1 — Token foundation (this change):** align ramps, retire teal from accent,
  add orange/purple/gradient tokens, blue-tint body. Site-wide via the cascade; primary
  + font already match so the visible delta is teal→blue accents + warmer CTA option.
  Reversible; deploy-gated (lands on `production` branch + local clone only, never live
  without Nitin's go).
- **Phase 2 — Brand-forward surface design (needs Nitin's direction):** push the
  **landing/storefront + login** toward the book's brochure aesthetic — blue→deep-blue
  gradient hero, subtle diagonal/corner accents, the circular "a" monogram device, white
  space, sparing orange CTA. This is a creative redesign, not a token swap, and is the
  open decision (see §7).

## 7. Open decision for Nitin
"100% adoption" of the token foundation (Phase 1) is unambiguous and done. The scope of
Phase 2 (how far to restyle the landing/login layouts toward the brochure look) is a
design-direction + effort call — captured as the question posed back to Nitin.

## 8a. Execution log — Phase 1 COMPLETE (2026-06-16)

Phase 1 (token foundation + full teal retirement) was executed and verified end-to-end:

**Token foundation** (`theme/sentientia/scss/moodle/_tokens.scss`, `_tokens-dark.scss`):
- Added brand secondaries `--ap-blue-bright #1985DD`, `--ap-blue-sky #9cdbf4`,
  `--ap-blue-deep #0d5da1`, `--ap-orange-500 #ed692b`, `--ap-purple-500 #6d58a5`.
- Re-pointed `--ap-color-accent` teal→`--ap-blue-bright`; added highlight/tertiary +
  `--ap-gradient-brand*` tokens; hero gradient → all-blue triad.
- **Teal ramp aliased to blue ramp** (`--ap-teal-N: var(--ap-blue-N)`) so every legacy
  `var(--ap-teal-*)` consumer renders brand blue without a per-file edit. Original hex
  kept inline as `/* was #… */` for trivial revert.

**Hardcoded-hex sweep** (scripts in this folder, idempotent + auditable):
- `sweep_teal.py` — theme SCSS: **47** literals across 16 partials.
- `sweep_teal_nonscss.py` — theme PHP/mustache: **12** (email text → AA-safe primary #0066A7).
- `sweep_teal_plugins.py` — plugin styles.css/templates/JS/email-PHP across BOTH source
  trees (`local/`, `moodle-enhancement/local/`) + the deployed XAMPP tree: **203** literals
  in 88 files. (Moodle aggregates plugin `styles.css` into the served theme CSS, so these
  had to be swept for true 100%.)

**Replacement rules:** gradient-end paired with primary → deep `#0d5da1` (book's classy
primary→deep); standalone accent/stroke/fallback → bright `#1985DD` (distinct-from-primary);
teal-light fills → `#e8f4fd`; dark teal → `#0d3a5c`; **email body text → primary `#0066A7`**
(bright `#1985DD` is only ~3.4:1 on white — fails WCAG AA for normal text).

**Verification:** compiled served CSS (`theme/styles.php/sentientia/<rev>/all`, 1.49 MB)
re-fetched after `theme_reset_all_caches()` → **0 teal** (`#0f7a73` + full teal ramp), brand
blues present (`#1985DD`×24, `#0d5da1`×26), HTTP 200 `text/css`, no PHP errors leaked. Deploy
to live remains Nitin-gated (branch + local clone only).

**Deferred to Phase 2 (design call, not teal-removal):**
- Storefront/featured **card-thumb variety gradients** still use a multi-hue decorative
  palette (`#0aa3a3` cyan, `#059669` green, `#1f6feb`). The airpay *teal* is gone; aligning
  this variety set to the brand secondary palette (sky / deep / orange / purple) is a
  design decision.
- Stale `theme/sentientia/scss/moodle/custom_changes_MONOLITH_BACKUP.scss` exists ONLY in
  the deployed XAMPP tree (not the repo, not `@import`ed → not compiled). Harmless; flagged
  for cleanup.

## 8. Refs
- Brand book pages: `pages/p14.png` (colour), `pages/p15.png` (application/gradients),
  `pages/p07.png` (logo lockups), `brandbook-text.txt` (full text).
- Token file: `theme/sentientia/scss/moodle/_tokens.scss`.
- Supersedes: `AIRPAY-CORP-BRANDING-STUDY-2026-06-11.md`.
