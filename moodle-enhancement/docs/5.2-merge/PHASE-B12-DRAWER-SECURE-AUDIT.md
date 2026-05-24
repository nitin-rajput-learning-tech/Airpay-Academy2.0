# Phase B.12 — drawer/drawers/secure mustache audit (2026-05-24)

**Status:** audit complete + selective backports shipped (NIGHT-RUN-PLAYBOOK A8).

---

## Method

Compared our 3 templates against vanilla Moodle 5.2 boost templates pulled
from the Docker container's `/var/www/moodle/public/theme/boost/templates/`.

```
Template          Lines (ours)   Lines (vanilla 5.2)   Delta
secure.mustache         94                97             +3 vanilla
drawer.mustache         57                71             +14 vanilla
drawers.mustache        98               192            +94 vanilla
```

---

## secure.mustache — 2 safe backports shipped

### What we backported

1. **`<div id="page-header">` → `<header id="page-header" data-for="page-heading">`** — vanilla 5.2 upgraded this to a semantic `<header>` landmark with a `data-for` attribute that 5.2's JS reads for focus management. 5.1 ignores the unknown attribute. Pure HTML semantics improvement, no behavior change on 5.1.

2. **Added `{{#headercontent}}{{> core/activity_header}}{{/headercontent}}`** — vanilla 5.2 ships this block on every layout so activities can surface completion labels + restricted-access conditions in the page header. `headercontent` is null on 5.1 today (the controllers don't populate it), so the conditional renders nothing on 5.1. Cutover-day activity_header support comes "for free" once the underlying controllers set the context key.

### What we did NOT backport from secure.mustache

- `<section id="region-main" aria-label="...">` → `<div id="region-main">` — vanilla 5.2 dropped the section landmark + aria-label. That's an **accessibility regression**. We keep the semantically-correct version.
- `<section data-region="blocks-column" aria-label="...">` → `<div ...>` — same a11y regression. We keep ours.
- `theme_boost/*` partial references — intentional fork divergence (we're not boost).
- `M.util.js_pending` / `js_complete` instrumentation — these depend on 5.2 JS module loader signatures; backporting would create no benefit on 5.1.

---

## drawer.mustache — NOT backported (deferred to cutover-day)

Vanilla 5.2 restructures the drawer header:
- Adds `<div class="drawerheading">` and `<div class="draweractions">` wrappers
- Adds `drawerheadercontent` block parameter
- Adds `closebuttonicon` block parameter (default-override pattern)
- Changes button class `drawertoggle icon-no-margin hidden` → `btn-icon icon-size-3 drawertoggle`
- Changes `data-placement` → `data-bs-placement` (**Bootstrap 5 attribute rename**)

Why deferred:
- The structural DOM changes (drawerheading/draweractions/drawerheadercontent) would break any consumer template that overrides `drawer.mustache` and doesn't know about the new blocks.
- `data-bs-placement` is the BS5 tooltip attribute name. On BS4 (production today), `data-placement` is required. Backporting this would silently break the close-button tooltip on every 5.1 page that uses a drawer.
- Button class change couples to BS5's `btn-icon` modifier, which doesn't exist in BS4.

Cutover-day plan: copy vanilla 5.2 drawer.mustache wholesale, then re-apply only the Sentientia-specific tweaks (none currently — our drawer is structurally close to vanilla 5.1).

---

## drawers.mustache — INTENTIONALLY DIVERGED (no backport)

Vanilla 5.2 inlines all drawer HTML directly in this template (~140 lines of drawer markup). Our airpayux version is structurally different — it delegates to:

```mustache
{{{ output.airpay_shell_start }}}
```

…which is the Sentientia custom sidebar renderer (`core_renderer::airpay_shell_start()` — emits the Sentientia-branded left rail and primary-drawer mobile, both customer-brand-aware via `local_airpay_core::get_customer_branding()`).

Backporting vanilla 5.2 here would **erase the Sentientia sidebar** and revert to vanilla Moodle drawer chrome — that's a product regression, not a forward port.

Cutover-day plan: keep our `airpay_shell_start` invocation as the canonical pattern. If Moodle 5.2's new drawer features (heading slot, action slot) are ever needed, they should be added to `airpay_shell_start` PHP renderer rather than swapped at template level.

---

## Refs

- `docs/5.2-merge/PHASE-B3C-TOP-TEMPLATES-REBASE.md` — original audit that flagged these 3 templates
- `templates/secure.mustache` — 2 safe backports applied at commit time
- `templates/drawer.mustache` — unchanged (deferred)
- `templates/drawers.mustache` — unchanged (intentional divergence)
- `NIGHT-RUN-PLAYBOOK.md` item A8 — completion record
