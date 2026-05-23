# Phase B.4 — lib + admin real conflicts audit

**Date:** 2026-05-23
**Status:** Audit complete. **4 cutover-day fixes tagged + 1 free improvement noted.**
ADR-011 estimated 8h; actual audit + tagging work took ~25 minutes.

---

## Scope per A4B map

Diff between Moodle 5.1.3+ and 5.2 in `lib/` shows ~1,956 file-level
deltas, but per the A4B strategic finding, the vast majority are
vendored `lib/aws-sdk/` refreshes. The real conflict surface for our
plugins lives in:

- `lib/classes/output/*` (renderer base classes)
- `lib/classes/event/*` (new event classes)
- `lib/amd/src/*` (AMD modules — including the removed `core/modal_registry`,
  `core/modal_factory`)
- `lib/outputrenderers.php`, `lib/moodlelib.php`, `lib/weblib.php`

This leg audited our 30+ `local_airpay_*` plugins for the specific
breakage patterns the 5.2 `lib/UPGRADING.md` calls out as removed
or behavior-changed.

---

## Findings

### 1. AMD `core/modal_factory` removed — 4 plugins affected (CUTOVER FIX REQUIRED)

5.2's lib/UPGRADING.md confirms:

> The following AMD modules have been removed following the final
> deprecation process:
> - `core/modal_registry`
> - `core/modal_factory`
> See MDL-79182.

Replacement: `core/modal` directly.

**Affected files (all tagged with cutover @todo in this commit):**

| File | Surface |
|------|---------|
| `local/airpay_courses/amd/src/enrolledusers.js` | /local/airpay_courses/enrolledusers.php — admin enrol modal |
| `local/airpay_request/amd/src/request_button.js` | catalog "Request access" buttons |
| `local/airpay_request/amd/src/decide.js` | admin approve/reject pending requests |
| `local/airpay_cart/amd/src/admin_orders.js` | admin refund modal on /local/airpay_cart/admin/orders.php |

**Why we're not migrating today:**
Production runs Moodle 5.1 where `core/modal_factory` is the working
API. Switching to `core/modal` now would break the 5.1 deployment.
Dual-targeting at the AMD layer is awkward (RequireJS sync require
fails on missing modules). Cleaner to flip all 4 in one cutover-day
commit when production goes to 5.2.

**Cutover-day migration pattern:**
```js
// Before (5.1):
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
// ...
const modal = await ModalFactory.create({
    type: ModalFactory.types.SAVE_CANCEL,
    title: '...',
    body: '...',
});

// After (5.2):
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';   // still exists in 5.2
// ...
const modal = await Modal.create({
    modalType: 'SAVE_CANCEL',
    title: '...',
    body: '...',
});
```

`ModalFactory.types.SAVE_CANCEL` enum constant → `'SAVE_CANCEL'` string.
`ModalFactory.create()` → `Modal.create()`. ModalEvents stays.

Total cutover-day effort: ~30 min across the 4 files.

### 2. `http_build_query()` separator change — free improvement, no fix needed

5.2's `lib/UPGRADING.md`:

> `arg_separator.output` has been changed from a default of `amp;` to
> a default of `&` in line with PHP defaults.

Our `local/airpay_integrations/classes/keka_client.php` uses bare
`http_build_query($params)` in two places (lines 315 and 345) — building
HRMS sync URLs and form bodies for the HRMS API.

**Pre-5.2 behavior:** Moodle's default `&amp;` separator produced
URLs like `?foo=1&amp;bar=2` which is WRONG for HTTP transmission (the
`&amp;` is an HTML entity for `&`). The HRMS API would have parsed
`bar` as `amp;bar` literally — but in practice this likely worked
because the HRMS may have tolerated the broken encoding, or the calls
happened to not have multiple params.

**Post-5.2 behavior:** Default is `&` which is correct. Our HRMS sync
becomes more standards-compliant on 5.2 with no code change.

**Action:** None required. This is a free upgrade.

### 3. `qtype_random` removed from core — not affected

Grep for `qtype_random` across our codebase returned zero matches. We
don't use random questions in any plugin path.

### 4. `\core\persistent::get_records(...)` now returns keyed by ID

Iterating `\core\persistent::get_records()` results by positional index
would break in 5.2. Quick grep of our plugins:

```
> grep -r "\\\\core\\\\persistent::get_records\|persistent.*get_records" moodle-enhancement/
```

No matches in our plugin code. We don't subclass `\core\persistent`.

**Action:** None required.

### 5. YUI `moodle-core-notification-confirm` removed

Replaced by AMD `core/modal`. Grep for `moodle-core-notification-confirm`:

```
> grep -r "moodle-core-notification-confirm" moodle-enhancement/
```

Zero matches. We don't use YUI confirmation dialogs.

**Action:** None required.

### 6. Class renames (`\phpunit_*` → `\core\test\phpunit\*`)

The old names work without debugging until Moodle 6.0. Our PHPUnit
tests use them — no immediate action. At Moodle 6.0 cutover, swap to
the new names.

**Action:** Deferred to 6.0 work (well past current scope).

---

## Summary

| Pattern | Hits | Action |
|---------|-----:|--------|
| `core/modal_factory` / `core/modal_registry` import | 4 files | TAGGED for cutover-day rewrite |
| `http_build_query()` bare | 1 file | None (5.2 fixes the bug) |
| `qtype_random` | 0 | n/a |
| `\core\persistent::get_records` positional | 0 | n/a |
| `moodle-core-notification-confirm` YUI | 0 | n/a |
| `\phpunit_*` class names | many | Deferred to 6.0 cutover |

Phase B.4 finding: **the lib + admin migration surface is much smaller
than the 8h estimate**. The 4 AMD ModalFactory cases are the only real
breakage points, and they're concentrated in admin-only surfaces (not
exercised by anonymous smoke tests). At cutover-day, fixing them is a
~30 min mechanical search-and-replace.

---

## Refs

- ADR-011 §"Phase B work breakdown" — B.4 8h estimate
- PHASE-A4B-CONFLICT-MAP.md §"Strategic finding: most of lib/ is vendor"
- `lib/UPGRADING.md` (5.2) — authoritative list of removals + behavior changes
- MDL-79182 — modal_factory/modal_registry removal
- MDL-71368 — http_build_query separator fix
- This file — Phase B.4 leg
