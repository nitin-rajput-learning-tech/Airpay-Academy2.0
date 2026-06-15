# Airpay Corporate Branding Study — airpay.co.in (2026-06-11)

**Purpose:** Nitin's directive — "study the updated airpay website for branding, we
need to build our landing page, login page, etc in the same theme." This document
extracts the corporate site's design system verbatim and maps it onto the Academy's
Sentientia theme so the landing + login surfaces can be rebuilt in the same look.

**Source:** https://airpay.co.in/ fetched 2026-06-11 (homepage HTML 427 KB +
`assets/css/style.css` 86 KB). Tokens below are copied from the live `:root`
block, not inferred.

**Architecture rule (non-negotiable):** everything here is **customer-Airpay
branding**, not Sentientia product branding. It must flow through the existing
per-customer branding layer (`customer_brand` / theme settings consumed by
`core_renderer`), never hardcoded into the theme, so the white-label product
stays customer-neutral. Ship behind a feature flag, default OFF.

---

## 1. Corporate token sheet (verbatim from `:root`)

```css
--primary: #0066a6;            /* brand blue */
--theme-color: #0066a6;
--theme-light: #4da8e1;        /* light blue */
--body-color: #e9f1f7;         /* page bg — blue-tinted */
--secondary-color: #f98613;    /* ORANGE — CTA / accent */
--main-btn-color: #f98613;
--heading-color: #0c1f2a;      /* near-black navy headings + dark CTA pills */
--text-muted: #545454;
--font-heading: "Montserrat", sans-serif;
--font-body: "Montserrat", sans-serif;
--fs-banner: clamp(2.5rem, 6vw, 4.5rem);
--fs-heading: clamp(2rem, 4vw, 2.8rem);
--fs-subheading: clamp(1rem, 2vw, 1.25rem);
--fw-regular: 400; --fw-medium: 500; --fw-semibold: 600; --fw-bold: 700; --fw-extrabold: 800;
--space-sm: 8px; --space-md: 16px; --space-lg: 32px;
--radius-md: 12px; --radius-pill: 50px;
--shadow-soft: 0px 12px 22px rgba(0, 0, 0, 0.05);
```

Plus, used heavily outside `:root`:

- **"Anton"** (Google display sans) — the signature display face:
  `h2 { font-family: "Anton"; color: var(--theme-color); 400; 65px }`,
  `h3 { "Anton"; 400; 36px }`, card titles 24px. Always weight 400, always
  brand blue or white.
- **#002a45** deep-navy gradient overlays on hero/imagery:
  `linear-gradient(to left/right, #002a45 0%, rgba(0,42,69,.85) …)`.
- Logo: `assets/images/logo.svg`, rendered 140×42.

## 2. Side-by-side with the Academy theme

| Token | Corporate (airpay.co.in) | Academy today (theme tokens) | Verdict |
|---|---|---|---|
| Brand blue | `#0066a6` | `$ap-primary #0066A7` | Effectively identical (1-step delta). Adopt `#0066a6` as the customer-Airpay canonical to be byte-faithful. |
| Accent / CTA | **`#f98613` orange** | `$ap-accent #0f7a73` teal | **THE shift.** Corporate dropped teal for orange. Adopting it re-colors CTAs/badges/success-adjacent accents across the platform. |
| Dark anchor | `#0c1f2a` headings + dark CTA pills; `#002a45` gradients | `$ap-text-primary #1a1a2e` | Adopt `#0c1f2a` for headings/pills on Airpay-branded surfaces; `#002a45` for hero gradient overlays. |
| Page bg | `#e9f1f7` (blue-tint) | `$ap-bg #F2F4FB` (violet-tint) | Adopt on landing/login; verify dashboard contrast before platform-wide. |
| Light blue | `#4da8e1` | `$ap-primary-light #e8f2f9` (tint) | New mid-tone — use for icons/illustration accents. |
| Muted text | `#545454` | `$ap-text-secondary #5a6070` | Near-equal; keep ours (passes the same contrast). |
| Body font | Montserrat 400–800 | Montserrat 400–800 | Already identical. |
| Display font | **Anton 400** (h2 65px, h3 36px, blue) | — (Montserrat 700/800 headings) | New signature. Self-host via theme `@font-face` (no Google CDN at runtime — privacy + perf). |
| Type scale | Fluid `clamp()` banner 2.5–4.5rem | Fixed rem scale | Use clamp() on the landing hero; keep fixed scale in-app. |
| Spacing | 8/16/32 (8px grid) | 8px grid | Already identical. |
| Radius | 12px cards, **50–80px pill buttons**, 20px hero slides | 8–20px, pills at 20px | Adopt true pills (`border-radius: 50px+`) for CTA buttons on branded surfaces. |
| Shadow | `0 12px 22px rgba(0,0,0,.05)` | `$ap-shadow-md 0 4px 12px rgba(0,0,0,.08)` | Softer + larger offset; adopt for landing cards. |

## 3. Component signatures observed

- **CTA button** (`.main-btn`): pill (radius 80px), `padding 10px 28px`,
  **dark navy `#0c1f2a` background**, white Montserrat 500 1rem; **hover flips
  to orange `#f98613`** (0.3s ease-out), icons invert to white. Secondary
  variant inverts (white/outline on dark).
- **Navbar:** transparent over hero → solid white with `--shadow-soft` once
  scrolled (`.nav-down`); Bootstrap-style dropdowns with SVG chevrons; logo
  140×42 left; CTA pill right; hamburger SVG at mobile.
- **Hero:** Swiper carousel, 500px tall, slides rounded **20px**, deep-navy
  `#002a45` gradient overlays for text legibility.
- **Headings pattern:** Anton-blue display heading + Montserrat sub/body.
- **Copy tone:** short, benefit-led, Title Case — "All Your Payments, One
  Seamless Platform." / "Do More with Every Payment". For the Academy:
  same cadence, learning verbs ("All Your Learning, One Seamless Platform").
- **Footer:** 13px/1.75 Montserrat micro-type.

## 4. What changes on the Academy (build plan, flag-gated)

Surfaces, in build order:

1. **Login page** (`templates/core/loginform.mustache` + `login.scss`):
   bg `#e9f1f7`; card radius 12px + `--shadow-soft`; CTA = navy pill with
   orange hover; Anton welcome heading in brand blue; logo.svg lockup.
2. **Public landing / storefront** (`local_sentientia_catalog` public.php +
   frontpage): hero with `#002a45` gradient + clamp() display type (Anton),
   pill CTAs, 20px-radius media, section alternation white / `#e9f1f7`.
3. **Navbar + footer accents:** scroll-solid white navbar with soft shadow;
   CTA pill in navbar; footer micro-type.
4. **In-app accent decision** — see open decisions.

Implementation shape:

- New SCSS partial `_brand-airpay-corp.scss` defining the corporate tokens as
  CSS custom properties **scoped under a customer-brand body class**
  (e.g. `body.brand-airpay`), emitted only when the feature flag
  (`branding_corp_refresh`, default OFF) is enabled for the Airpay customer.
- Anton self-hosted in `theme/sentientia/fonts/` with `font-display: swap`,
  loaded only on flagged surfaces.
- Logo: pull `logo.svg` from corporate (with permission it's the same brand)
  into the customer_brand logo slot — no hardcode.
- Visual evidence per CLAUDE.md (desktop + 590px) for every surface touched.

## 5. Open decisions — [NITIN DECIDES]

1. **Orange accent scope:** landing+login only, or platform-wide replacement
   of teal `#0f7a73`? Teal is currently woven through 600+ compiled rules
   (tags, success-adjacent states, secondary buttons). Recommendation:
   Phase 1 = branded public surfaces only (landing, login, navbar CTA);
   Phase 2 = platform accent swap after a contrast audit (orange on white is
   3.0:1 — FAILS WCAG for small text; usable for large text/buttons with
   white text? `#f98613` vs white text = 2.5:1 — also fails. Corporate uses
   orange as hover/graphic accent, NOT text-on-orange. Any platform-wide swap
   needs a darkened companion token for text-bearing components.)
2. **Anton in-app:** display font on dashboards/headings, or
   landing+login only? (Recommendation: branded marketing surfaces only;
   keep Montserrat in-app for data-density readability.)
3. **Page bg swap** `#F2F4FB` → `#e9f1f7` platform-wide, or branded surfaces
   only?
4. Hero copy for the Academy landing (draft: "All Your Learning, One
   Seamless Platform." — mirrors corporate cadence).

## 6. Contrast pre-checks (for the build session)

- `#0066a6` on white: 5.0:1 — AA pass (text + UI).
- `#0c1f2a` on white: 15.9:1 — AAA.
- White on `#0c1f2a` pills: 15.9:1 — AAA.
- `#f98613` on white: ~2.5:1 — **decorative/large-graphic only; never body
  text, never white-text-on-orange small buttons.** Hover-state on navy pills
  (white text on `#f98613`) is borderline — corporate does it; for WCAG we
  should keep white text but ALSO darken hover orange ~`#c96400` if axe
  flags it on our gate (our a11y gate is stricter than their site).
- `#545454` on `#e9f1f7`: 6.8:1 — AA pass.

---
*Method note: static extraction (HTML + style.css). No JS-rendered styles were
sampled; a Playwright visual capture of airpay.co.in (desktop + 590px) is the
first step of the build session to lock reference screenshots into
docs/visual-evidence/.*
