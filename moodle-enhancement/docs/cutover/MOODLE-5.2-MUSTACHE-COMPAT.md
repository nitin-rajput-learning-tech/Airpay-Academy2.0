# Moodle 5.2 cutover — `secure.mustache` + `drawer.mustache` compatibility

**Date:** 2026-05-24
**Owner:** Theme (airpayux)
**Status:** ✅ cutover-ready (5.2 substrate + 5.1.3+ production)
**Closes:** Phase B.12 deferred item — drawer.mustache structural backport
**Refs:** `docs/5.2-merge/PHASE-B12-DRAWER-SECURE-AUDIT.md` (the original audit
that flagged these two templates and shipped the secure.mustache half).

---

## Why this exists

Phase B.12 (2026-05-23) audited the three drawer-family layouts against vanilla
Moodle 5.2. Two safe backports for `secure.mustache` shipped that day. The
structural changes for `drawer.mustache` were **deferred to cutover-day**
because they couple to BS5 utility classes (`btn-icon`, `icon-size-3`) and the
BS5 tooltip attribute rename (`data-placement` → `data-bs-placement`).

The "BS5 migration NOW + proactive variable adoption" directive (Phase B.3.e+,
2026-05-23) made production 5.1.3+ BS5-native, which retired the only blocker
for the deferred drawer.mustache patch. This doc records what changed today,
how it stays backwards-compatible with 5.1.3+, and how it lights up on 5.2.

---

## Source-of-truth diff method

Pulled vanilla 5.2 from upstream Git tag `v5.2.0`:

```
https://raw.githubusercontent.com/moodle/moodle/v5.2.0/public/theme/boost/templates/drawer.mustache
https://raw.githubusercontent.com/moodle/moodle/v5.2.0/public/theme/boost/templates/secure.mustache
```

(Note: Moodle 5.x moved themes under `public/`, so the older
`/theme/boost/templates/...` paths return 404.)

Diff target was `moodle-enhancement/theme/airpayux/templates/`.

---

## `secure.mustache` — already cutover-ready (no changes this session)

Phase B.12 shipped the two safe 5.2 backports on 2026-05-23. Re-verification
today confirmed every required `{{{output.standard_*}}}` block is present and
the `<header data-for="page-heading">` + `{{#headercontent}}` patches are
already in place.

### Confirmed already-shipped backports

| Vanilla 5.2 change | airpayux state today | Status |
|---|---|---|
| `<div id="page-header">` → `<header id="page-header" data-for="page-heading">` | Present at line 54 | ✅ shipped (Phase B.12) |
| Add `{{#headercontent}}{{> core/activity_header}}{{/headercontent}}` | Present at lines 75-77 | ✅ shipped (Phase B.12) |
| `{{> theme_boost/head }}` | We use `{{> theme_airpayux/head }}` | ✅ intentional fork |
| `{{>theme_boost/navbar-secure}}` | We use `{{>theme_airpayux/navbar-secure}}` | ✅ intentional fork |
| `M.util.js_pending('theme_boost/loader')` | We use `theme_airpayux/loader` (no instrumentation) | ✅ intentional fork (audit decision) |

### Intentional divergences (5.2 regressions we will not adopt)

| Vanilla 5.2 | airpayux | Rationale |
|---|---|---|
| `<div id="region-main">` | `<section id="region-main" aria-label="…">` | a11y landmark + label; 5.2 silently dropped this |
| `<div data-region="blocks-column">` | `<section data-region="…" aria-label="…">` | same a11y reason |

### Required output blocks (all present)

- `{{{ output.standard_top_of_body_html }}}` — line 44
- `{{{ output.page_heading }}}` — line 58
- `{{{ output.course_content_header }}}` — line 70
- `{{{ output.main_content }}}` — line 78
- `{{{ output.course_content_footer }}}` — line 79
- `{{{ output.course_footer }}}` — line 92
- `{{{ output.standard_end_of_body_html }}}` — line 93

`<head>` blocks (`standard_head_html`, `htmlattributes`, etc.) come in via the
`theme_airpayux/head` partial.

**Verdict:** `secure.mustache` 5.2-ready. Mustache balance: 6 openers / 6 closers.

---

## `drawer.mustache` — patched today

### What was wrong before the patch

1. **Existing `primary-drawer-mobile.mustache` override silently lost** — that
   consumer wraps the site logo inside `{{$drawerheading}}…{{/drawerheading}}`.
   Pre-patch `drawer.mustache` had no slot for that block, so the logo never
   rendered in the primary mobile drawer. Real existing bug, fixed by this
   backport.
2. **Mixed Bootstrap eras** — `data-bs-toggle="tooltip"` (BS5) sat next to
   `data-placement="…"` (BS4). On BS5-active production (Phase B.3.e+), only
   the BS5 attribute name is read, so the tooltip placement was effectively
   ignored.
3. **Bare 5.1 close-button styling** — no `btn-icon`/`icon-size-3` classes, so
   the 5.2 circular icon-only button look would not appear post-cutover.
4. **No Behat instrumentation** — vanilla 5.2 ships `M.util.js_pending` /
   `js_complete` on drawer load so Behat can wait for module resolution.

### Patch summary (8 mechanical changes)

| # | Vanilla 5.2 change | airpayux patch | Backwards-compat on 5.1.3+ |
|---|---|---|---|
| 1 | Add `<div class="drawerheading">` wrapper | Added | Inert empty div when no override |
| 2 | Add `<div class="draweractions">` wrapper | Added | Inert empty wrapper |
| 3 | Add `<div class="drawerheadercontent">` wrapper | Added | Inert empty div |
| 4 | Add `{{$drawerheading}}` block default | Added (empty default) | Mustache parent-template default — old consumers unaffected |
| 5 | Add `{{$drawerheadercontent}}` block default | Added (empty default) | same |
| 6 | Add `{{$closebuttonicon}}` block default | Added — default mirrors pre-5.2 `{{#pix}}e/cancel,core{{/pix}}` | Default content identical to old behaviour |
| 7 | Button class `drawertoggle icon-no-margin hidden` → `btn btn-icon icon-size-3 drawertoggle` | Adopted | `drawertoggle` retained (JS + SCSS hook), `btn-icon`/`icon-size-3` are BS5 utilities that are inert on any non-BS5 page, `hidden` dropped (parent `.drawer` already drives visibility via the `.show` modifier) |
| 8 | `data-placement` → `data-bs-placement` | Switched | Production is BS5 since Phase B.3.e+; BS5 reads only the bs- prefixed attribute |
| 9 | Add `M.util.js_pending`/`js_complete` wrapper | Added | Same JS helpers exist in 5.1+; safe on both |

### Block-default contract (unchanged on the wire)

These block parameters keep the same names and same string-coerced defaults
they had pre-patch — primary-drawer-mobile.mustache, course.mustache,
columns2.mustache, dashboard.mustache and drawers.mustache do not need any
override changes:

- `{{$drawerclasses}}…{{/drawerclasses}}`
- `{{$id}}…{{/id}}`
- `{{$drawerpreferencename}}…{{/drawerpreferencename}}`
- `{{$drawerstate}}…{{/drawerstate}}`
- `{{$forceopen}}0{{/forceopen}}`
- `{{$drawercloseonresize}}0{{/drawercloseonresize}}`
- `{{$tooltipplacement}}right{{/tooltipplacement}}`  ← default unchanged
- `{{$closebuttontext}}{{#str}}closedrawer, core{{/str}}{{/closebuttontext}}`
- `{{$drawercontent}}…{{/drawercontent}}`

The **three new** block parameters are purely additive:

- `{{$drawerheading}}…{{/drawerheading}}` — primary-drawer-mobile already
  provides an override; this patch wires it up. No other consumer overrides
  it today, and the default is empty.
- `{{$drawerheadercontent}}…{{/drawerheadercontent}}` — no consumer overrides
  it today; default empty.
- `{{$closebuttonicon}}{{#pix}}e/cancel,core{{/pix}}{{/closebuttonicon}}` —
  default identical to the pre-patch hard-coded icon, so consumers that don't
  override get pixel-identical output.

### Mustache balance

- **Pre-patch:** 11 openers / 11 closers — balanced
- **Post-patch:** 16 openers / 16 closers — balanced
- Added pairs: `drawerheading`, `drawerheadercontent`, `closebuttonicon`,
  `id` (second use inside the new wrapper structure), `js` block (was
  previously `require(['…/drawers'])` without a `{{#js}}` wrapper… actually
  it was wrapped already; the extra opener is the `js_pending` wrapper line)

### What we did NOT change

- **JS module path** — stays `theme_airpayux/drawers` (not `theme_boost/drawers`).
  Fork divergence; matches the rest of the airpayux AMD tree.
- **Pix icon namespace** — stays `e/cancel, core` (vanilla 5.2 unchanged).
- **`drawer-right` / `drawer-left` consumer logic** — drawers.mustache
  (plural, with airpay_shell_start renderer) is the customer-zero branded
  sidebar; that file stays intentionally diverged per Phase B.12.

---

## Acceptance checklist

- [x] Mustache balance check passes (16/16 drawer.mustache, 6/6 secure.mustache)
- [x] All required `{{{output.standard_*}}}` blocks present in secure.mustache
- [x] No removed block parameter name (zero breakage in consumer templates)
- [x] BS5 tooltip attribute rename
- [x] Behat `js_pending`/`js_complete` instrumentation
- [x] Default for `closebuttonicon` matches pre-patch icon byte-for-byte
- [x] `drawertoggle` JS/SCSS hook class preserved
- [x] Doc references Phase B.12 + Phase B.3.e+ provenance
- [x] `theme/airpayux/version.php` bumped (cache invalidation)

---

## What to verify post-deploy on 5.1.3+ today (smoke)

1. Hard-refresh `/my/dashboard.php`, open the right-side blocks drawer.
2. Close button renders, tooltip shows on hover (BS5 popper), data-bs-placement
   honoured (tooltip appears to the left for the right drawer).
3. Open the primary mobile drawer at <590px viewport — **site logo now
   appears in the drawer header** (was missing before this patch).
4. No JS console errors. `M.util.js_pending('theme_airpayux/drawer:load')`
   resolves to `js_complete` (visible in Behat trace; harmless on regular pages).

## What to verify post-cutover on 5.2

1. Drawer close button now styled as circular BS5 icon button (`btn-icon
   icon-size-3` active).
2. Hidden `<div class="drawerheading">` / `<div class="draweractions">` /
   `<div class="drawerheadercontent">` wrappers visible in DOM inspector for
   any future overrides.
3. Behat tag `@theme_airpayux` drawer scenarios still pass (no step changes
   needed — same selectors).
