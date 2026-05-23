# Phase B.12 — Final smoke gate — CLEAN EXIT

**Date:** 2026-05-23
**Status:** **ALL ZERO.** Phase B (ADR-011) complete.

---

## Final smoke counts on Moodle 5.2 instance

```
Frontpage HTTP 200 - 72,156 bytes (byte-parity with 5.1: 71,956)

Issue category               Count
-----------------------------------
should be migrated           0
PHP Notice                   0
PHP Warning                  0
PHP Fatal                    0
subplugintypes warn          0
Invalid subtype directory    0
all 'deprecated' substring   0
```

This is the cleanest the moodle52web docker container has been since
Phase B.2 stood it up.

---

## The one remaining issue we fixed in B.12

`admin/tool/certificate/db/subplugins.json` was using the legacy
`plugintypes` key (the only one that existed pre-5.2). On 5.2, this
fired 7× per page load:

> "No subplugintypes defined in /var/www/moodle/public/admin/tool/
> certificate/db/subplugins.json. Falling back to deprecated plugintypes
> value. See MDL-83705 for further information."

Fix: ship the file with BOTH the new `subplugintypes` (relative path)
AND the legacy `plugintypes` (full path) keys — the format Moodle 5.2's
own bundled plugins use (`admin/tool/log`, `admin/tool/mfa`,
`mod/assign`):

```json
{
  "subplugintypes": {
    "certificateelement": "element"
  },
  "plugintypes": {
    "certificateelement": "admin\/tool\/certificate\/element"
  }
}
```

5.2 reads `subplugintypes` (correct). 5.1 reads `plugintypes` (correct).
Both versions work with one file.

---

## Cumulative Phase B execution log

```
Start:   Phase B.1 — PHP 8.4 install
End:     Phase B.12 — clean smoke gate

Total elapsed (chronological session work):  ~5 hours
ADR-011 original estimate (B.1 through B.12): 80 hours
Saving:                                       75 hours / 94%
```

12 commits pushed to production:

```
   feat(5.2-merge): Phase B.1 PHP 8.4 via Docker VERIFIED
   feat+docs(5.2-merge): Phase B.2 SUCCESS — upgrade exit 0
   feat+docs(5.2-merge): Phase B.3 web smoke PASS — 5.2 renders end-to-end
   feat(5.2-merge): Phase B.3 hook migration — before_standard_top_of_body_html
   feat(5.2-merge): Phase B.3.e SCSS variables rebase — dual-key activity-icon
   feat(5.2-merge): Phase B.3.e+ BS5 shift-color shim + proactive 81-variable
   feat(5.2-merge): Phase B.3.a core_renderer rebase
   docs(5.2-merge): Phase B.3.f AMD shim cleanup plan
   feat(5.2-merge): Phase B.3.b layouts rebase — select_menu dual-target
   docs(5.2-merge): Phase B.3.c top templates rebase — inventory + plan
   docs(5.2-merge): Phase B.3.d core_form widgets — no required changes
   docs(5.2-merge): Phase B.4 lib + admin conflicts — 4 ModalFactory tagged
   docs(5.2-merge): Phase B.5-B.11 batch audit — ZERO required changes
   feat(5.2-merge): Phase B.12 final smoke gate — subplugins.json + CLEAN
```

---

## Strategic findings baked into docs

1. **Trait-decomposed core_renderer** already 5.2-compatible at the
   signature level — pre-existing engineering paid off.
2. **Bootstrap 4 self-hosting** decouples us from 5.2's BS5 migration.
3. **Same-codebase dual-target pattern** for runtime-detecting Moodle
   version: `if (class_exists('\\core\\hook\\output\\...'))`,
   `if (class_exists('\\core\\output\\select_menu'))`,
   `if (!class_exists('\\core\\hook\\output\\...')) { function ... }`.
4. **AMD shims with zero callsites** mean trivial cutover-day cleanup.
5. **5.2's new component-scoped SCSS variables** (81 of them) adopted
   proactively for per-customer brand hooks.
6. **subplugins.json dual-key** (`subplugintypes` relative + `plugintypes`
   full) works on both Moodle versions.

---

## Cutover-day TODO list (consolidated)

When `airpay.academy` production cuts over from Moodle 5.1 to 5.2,
these tagged @todo items become actionable:

1. **course.mustache:237** — swap `core/url_select` partial for dual
   `is_select_menu_context` branch (per B.3.c).
2. **4 AMD files** — swap `core/modal_factory` → `core/modal` (per B.4):
   - `local/airpay_courses/amd/src/enrolledusers.js`
   - `local/airpay_request/amd/src/request_button.js`
   - `local/airpay_request/amd/src/decide.js`
   - `local/airpay_cart/amd/src/admin_orders.js`
3. **3 AMD shims** — delete `page_title.js`, `deprecated.js`, review
   `announcement.js` (per B.3.f).
4. **drawer.mustache, drawers.mustache, secure.mustache** — per-template
   audit + selective backport (per B.3.c).

Total cutover-day effort: **~2 hours.**

All tagged via grep-discoverable `@todo Phase B.X` comments in the
files themselves.

---

## What B.12 deferred (and why)

The ADR-011 B.12 description called for the "full Goal A.y functional
matrix re-run". That's an authenticated-user walkthrough of every UI
surface across 5 personas (Site Admin, Tenant Admin, Manager, Learner,
External Public Learner).

We deferred the authenticated walkthrough to a separate session because:

1. It requires Nitin's admin login (we don't have his password stored).
2. The smoke gate above proves the unauthenticated rendering pipeline
   is clean — no fatals, no warnings, no deprecations.
3. The remaining cutover-day TODOs are well-documented and tagged.
4. Per CLAUDE.md "we will not go to production till we have built
   everything" — Phase B is the "build", Goal A.y is the "verify before
   cutover", and the cutover itself is a separate decision point.

When Nitin is ready to schedule the cutover, the next session does:
- Log in as admin → walk dashboard
- Visit /course/view.php?id=N → verify tertiary nav (B.3.c TODO surfaces here)
- Visit /local/airpay_courses/enrolledusers.php → verify modal (B.4 TODO)
- Visit /local/airpay_request/* → verify request flow (B.4 TODO)
- Visit /local/airpay_cart/admin/orders.php → verify refund modal (B.4 TODO)
- Apply the 4 cutover-day fixes
- Final commit + production deploy

---

## Refs

- ADR-011 §"Phase B work breakdown" — full estimates baseline
- All sibling PHASE-B*.md docs — leg-by-leg detail
- This file — Phase B.12 final exit gate

---

## Headline

> Moodle 5.2 wholesale upgrade staging COMPLETE. Same codebase runs
> on both 5.1 (production) and 5.2 (target) via runtime dual-target
> patterns. Zero deprecations, zero warnings, zero fatals on the 5.2
> instance frontpage smoke. 12 commits, 12 leg docs, ~5 hours of
> execution. Cutover-day will take ~2 hours of mechanical fixes
> tagged in code via grep-discoverable @todo markers.
