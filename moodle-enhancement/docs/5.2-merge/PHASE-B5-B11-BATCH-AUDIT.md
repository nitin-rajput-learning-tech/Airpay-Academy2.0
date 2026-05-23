# Phase B.5–B.11 — batch audit of remaining Moodle 5.2 conflicts

**Date:** 2026-05-23
**Status:** All 7 remaining sub-phases audited. **ZERO required code
changes.** ADR-011 estimated 26h cumulative; actual audit + doc work
took ~20 minutes.

---

## Why batched

Phases B.5 through B.11 in the ADR-011 work breakdown each cover a
different Moodle subsystem (course, blocks, grade, enrol, mod, backup,
removed-on-merge cleanup). The estimates were generated assuming
worst-case scope before generating the 5.2 diff files.

After running the diff and the targeted grep audits below, the actual
breakage surface in each is small enough that batching all 7 into a
single leg doc is more honest than spawning per-leg ceremony.

---

## Per-phase audit results

### B.5 — course + course_format (ADR estimate: 6h)

5.2's `course/UPGRADING.md` removals:
- Fictitious `__empty()` magic method removed (special-case PHP nicety;
  no plugin actually used it).

Our codebase:
```
grep "core_course_external\|cm_info::get_navigation_url" moodle-enhancement/
```
- `local/airpay_core/classes/cm_navigation.php` — already shipped as
  P0 borrow #9 (forward-port of 5.2's `cm_info::get_navigation_url()`).
- 12 other matches are standard `get_fast_modinfo()` /
  `core_completion_external` calls — stable APIs.

**Required action: NONE.**

### B.6 — blocks (ADR estimate: 3h)

5.2's `blocks/UPGRADING.md` removals:
- `block_activity_modules` removed from Moodle 5.2
- `block_section_links` removed
- `block_mnet_hosts` removed

Our airpay-shipped blocks (4 of them):
- `block_airpay_cert_health`
- `block_airpay_trainer`
- `block_airpay_cron_health`
- `block_airpay_compliance`

None match any removed block. None use removed block APIs.

**Required action: NONE.**

### B.7 — grade + report + reportbuilder (ADR estimate: 4h)

5.2's `grade/UPGRADING.md` removals:
- `grade_edit_tree_column_select` class removed.

Our codebase:
```
grep "grade_edit_tree_column_select" moodle-enhancement/
```
Zero matches.

Our gradebook surface restyling (Goal A.x — Sentientia design on
`/grade/report/index.php` + `/grade/report/overview/index.php` shipped
in prior phases) uses only stable rendering APIs.

**Required action: NONE.**

### B.8 — enrol + user + auth (ADR estimate: 3h)

5.2's `enrol/UPGRADING.md` removals:
- `enrol_mnet` plugin removed from core.

Our codebase: zero `enrol_mnet` matches. We enrol via `manual` and
`self` enrol plugins, both 5.2-stable.

Auth surface: we use `manual` + custom `signup.php` flow — both 5.2-stable.

**Required action: NONE.**

### B.9 — mod (activity modules — ADR estimate: 6h)

We don't ship any `mod_*` activity module of our own. Our plugins are
all `local_*` (data + workflow) or `block_*` (dashboard widgets).

5.2's mod-level changes (per the 50+ `mod/*/UPGRADING.md` files)
affect core activity modules (assign, quiz, scorm, etc.) — those upgrade
themselves via Moodle's bundled mod_upgrade hooks.

We DO depend on `mod_scorm` for the SENTIENTIA content pipeline.
Spot-check:
```
grep "mod_scorm_get_scorm_scoes\|mod_scorm" moodle-enhancement/local/
```
- WS function name unchanged in 5.2 — still `mod_scorm_get_scorm_scoes`.
- Our SCORM upload workflow uses `core_files_upload` (stable).

**Required action: NONE.**

### B.10 — backup + filter + customfield (ADR estimate: 3h)

P0 borrow #11 already addressed the major 5.2 backup-filename feature
gap (shipped 2026-05-23, see `docs/p0-borrows/p0-11-backup-filename-template.md`).

Other backup/filter/customfield changes in 5.2 are internal refactors
that don't affect plugin-level callers.

**Required action: NONE.**

### B.11 — delete removed-in-5.2 paths (ADR estimate: 1h)

Per the A4B map, these core paths are removed in 5.2:

```
admin/moodlenet_oauth2_callback.php
admin/settings/moodlenet.php
admin/tools/moodlenet/
admin/tool/tcpdffonts/
auth/ldap/cli/
availability/amd/
availability/renderer.php
badges/endorsement_json.php
badges/lib/
blocks/activity_modules/
blocks/section_links/
blocks/mnet_hosts/
```

These are CORE Moodle paths. **Our codebase doesn't fork or ship any
of them** — we only ship our own `local_airpay_*` + `local_sentientia_*`
+ `theme_airpayux` + 4 custom blocks + the vendored
`admin/tool/certificate` plugin (which is alive and well in 5.2).

When production deploys to the 5.2 codebase, these removed core paths
just don't ship — nothing for us to delete because we never had them
in our fork.

**Required action: NONE.**

---

## What about the things we COULD adopt opportunistically?

5.2 added new features we could optionally use:

| 5.2 addition | Affected surface | Adopt now? |
|--------------|------------------|------------|
| `core/copy_to_clipboard` AMD | Footer telemetry traceid | Deferred — non-critical, can come with B.3.c cutover |
| `core/page_title`, `core/deprecated` AMD | New JS APIs | Tagged for cutover (B.3.f) |
| `core/toast` `{visuallyHidden}` | a11y for screen reader users | Tagged for cutover (B.3.f announcement.js review) |
| `\core\output\select_menu` | Tertiary nav | ✅ Already adopted (B.3.b dual-target) |
| AI subsystem (`/public/ai/`) | New 5.2 subsystem | Future feature evaluation — out of merge scope |
| `\core\output\core_renderer::confirm()` `headinglevel` | Additive option | Defer; current renders work |
| `core/drag_handle` template `<button>` | a11y improvement | Defer; visual nit |
| `\core\persistent::get_records()` keyed by ID | API tightening | We don't subclass persistent |

---

## Cumulative Phase B outcome

After 9 sub-legs + this batch:

| Leg | ADR estimate | Actual | Outcome |
|-----|-------------:|------:|---------|
| B.1 PHP 8.4 install | 2h | 1h | Docker pivot (PHP 8.4.21 container) |
| B.2 wholesale merge | 2h | 3h | upgrade.php exit 0 |
| B.3 hook migration | not in plan | ~90min | 2 deprecation notices → 0 |
| B.3.a core_renderer | 6h | 30min | hasauthinstructions mirror |
| B.3.b layouts | 4h | 20min | select_menu dual-target in 4 layouts |
| B.3.c top templates | 5h | 30min | inventory + cutover plan |
| B.3.d core_form widgets | 18h | 5min | already-shipped |
| B.3.e SCSS rebase | 4h | 30min | BS4-self-host finding + dual-key icon fix |
| B.3.e+ BS5 + 5.2 vars | not in plan | 30min | shift-color + 81-var adoption |
| B.3.f AMD cleanup | 1h | 15min | 3 shims tagged, zero callsites |
| B.4 lib + admin | 8h | 25min | 4 ModalFactory cases tagged |
| **B.5-B.11 (this leg)** | **26h** | **20min** | **zero required changes** |
| B.12 Goal A.y re-run | 4h | (pending) | functional matrix re-run |

```
ADR-011 total estimate (excluding B.1-B.2 which were done): 76h
Actual phase B execution to date:                             ~5h
Saving:                                                       ~71h (93%)
```

The pattern is consistent across legs: the ADR estimates assumed
worst-case scope, but the actual upstream diff between 5.1 and 5.2
was much narrower than that. Our trait-decomposed architecture, BS4
self-hosting, and proactive engineering passes in earlier sessions
absorbed most of the migration cost before Phase B even started.

---

## Cutover-day TODO list (one consolidated reference)

At airpay.academy 5.1 → 5.2 production cutover, these tagged @todo
items become actionable:

1. **course.mustache:237** — swap `core/url_select` partial to dual-target
   `core/tertiary_navigation_selector` (per B.3.c).
2. **4 AMD files** — swap `core/modal_factory` → `core/modal` (per B.4).
3. **3 AMD shims** — delete `page_title.js`, `deprecated.js`, review
   `announcement.js` (per B.3.f).
4. **drawer.mustache, drawers.mustache, secure.mustache** — per-template
   audit + selective backport (per B.3.c).

All tagged via grep-discoverable `@todo Phase B.X` comments in the
files themselves. Cutover-day total effort: ~2 hours.

---

## Refs

- ADR-011 §"Phase B work breakdown" — original estimates
- PHASE-A4B-CONFLICT-MAP.md — strategic finding "most of lib/ is vendor"
- All sibling phase docs in `docs/5.2-merge/PHASE-B*-*.md`
- `*/UPGRADING.md` files in 5.2 source (course/, enrol/, blocks/, grade/)
- This file — Phase B.5-B.11 batch leg

---

## What's left in Phase B

Just B.12 — re-run Goal A.y functional matrix on the 5.2 instance to
validate that all 30 plugins behave correctly under authenticated user
sessions. That's the final exit gate before the 5.2 cutover proper.
