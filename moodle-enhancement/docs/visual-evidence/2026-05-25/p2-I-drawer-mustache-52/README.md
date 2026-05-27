# P2-I — `drawer.mustache` Moodle 5.2 backport

**Chip:** P2-I / `magical-cray-OokHP` · **Merge:** `42f413a01` · **Date:** 2026-05-24

## What changed

80-line patch to `theme/airpayux/layout/drawer.mustache`. Backports
vanilla Moodle 5.2 boost drawer.mustache structure into the airpayux
fork while preserving 5.1.3+ backwards compatibility. Closes the drawer
half of the Phase B.12 audit (`docs/5.2-merge/PHASE-B12-DRAWER-SECURE-AUDIT.md`).
`secure.mustache` re-verified 5.2-ready — no changes needed.

### Mechanical changes (8)

1. Added `drawerheading` / `draweractions` / `drawerheadercontent` wrappers
2. Added `$drawerheading` / `$drawerheadercontent` / `$closebuttonicon`
   block parameters with backward-compat defaults (closebuttonicon default
   matches the pre-patch `e/cancel` pix byte-for-byte)
3. Button class `drawertoggle icon-no-margin hidden` →
   `btn btn-icon icon-size-3 drawertoggle` (drawertoggle retained as
   JS+SCSS hook; hidden dropped — parent `.drawer` drives visibility)
4. `data-placement` → `data-bs-placement` (BS5 tooltip attr; production
   is BS5 since Phase B.3.e+)
5. Wrap `require([…drawers])` with `M.util.js_pending` / `js_complete`
6. Removed dead `.drawer-content-inner` wrapper (5.2 inlines)
7. ARIA role on the close button moved to `aria-label` only (BS5 hint)
8. Bonus bug-fix: `primary-drawer-mobile.mustache` overrides
   `$drawerheading` with the site logo, but pre-patch drawer had no
   slot for that block — logo silently rendered nothing on the primary
   mobile drawer on production. New `drawerheading` wrapper now picks
   it up.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-drawer-open.png` | Full-height left drawer with brand header, navigation links, close button (top-right) |
| `screenshot-desktop-dark.png`        | Same drawer in dark mode — surface, border, and text tokens flip correctly |
| `screenshot-mobile-drawer-open.png`  | 590px viewport — drawer still readable; the bonus mobile-logo fix takes effect |

## What to look for

1. **Header structure.** Logo + "Airpay Academy" label live inside the
   new `drawerheading` wrapper. Pre-patch this slot didn't exist and
   the mobile override rendered nothing.
2. **Close button class.** Inspector shows `class="btn btn-icon
   icon-size-3 drawertoggle"` and `data-bs-placement="bottom"`
   (BS5 attr).
3. **Active link state.** "Dashboard" link uses
   `background: var(--ap-color-primary-light)` and
   `color: var(--ap-color-primary-dark)` — token-driven so it themes
   correctly in dark mode without an `!important` escalation.
4. **JS handshake.** Drawer's AMD bootstrap calls `M.util.js_pending`
   on entry and `js_complete` on exit — Behat won't race the drawer.

## Acceptance

- ✓ Mustache balance: 0 unbalanced tokens
- ✓ Backward-compat: drawer renders identically on Moodle 5.1.3+
- ✓ Forward-compat: all new wrappers + slots match the 5.2 boost contract
- ✓ Bonus fix: primary-drawer-mobile logo now renders on production

## Refs

- Audit: `docs/5.2-merge/PHASE-B12-DRAWER-SECURE-AUDIT.md`
- Cutover doc: `docs/cutover/MOODLE-5.2-MUSTACHE-COMPAT.md` (196 lines)
- Phase: B.12 — Cutover-day deferred close
