# Core-mod record: `block_learnerscript` (vendor plugin) x Moodle 5.2+: legacy modal factory port

- **Date:** 2026-10-08 (Moodle 5.3 compatibility pass, finding B6 / fix FX-08, owner option (a))
- **Tag at the sites:** `// SENTIENTIA-CORE-MOD (vendor)`
- **Files** (top-level tree, which is the package source; the sources and their `amd/build` artefacts are rebuilt together):
  - `blocks/learnerscript/amd/src/ajax.js`
  - `blocks/learnerscript/amd/src/ajaxforms.js`
  - `blocks/learnerscript/amd/src/helper.js`
  - `blocks/learnerscript/amd/src/newgroup.js`
  - `blocks/learnerscript/js/design.js` (plain page script, no build file)
  - `blocks/learnerscript/amd/build/{ajax,ajaxforms,helper,newgroup}.min.js` and `.min.js.map`

## Why

The legacy modal factory AMD module was removed in Moodle 5.2 (MDL-79182) and has no alias. Every
`define([... , <modal factory>, ...])` therefore 404s, the dependent module never loads, and every report screen that
needs it (confirm-delete of a report component, "no columns" notice, the AJAX error dialogue, the schedule and
column dialogues that load through `ajaxforms`/`newgroup`) silently does nothing on 5.2 and 5.3. Finding B6 of
`docs/upgrade/MOODLE-5.3-COMPATIBILITY-2026-10-07.md`. The owner chose to port the modules (option (a)) rather than
stop shipping the three vendor report blocks (option (b)).

## What changed (dual-target: identical behaviour on 5.1, 5.2 and 5.3)

`core/modal`, `core/modal_save_cancel` and `core/modal_events` exist unchanged in 5.1, 5.2 and 5.3, and `create()`
returns a native `Promise` on all three (5.1 `public/lib/amd/src/modal.js:193`, 5.3 `:229`).

| File | Before | After |
|---|---|---|
| `ajax.js` | `ModalFactory.create({title, body, footer: ''}).done(modal => { dialogue = modal; dialogue.show(); })` | `Modal.create({title, body, footer: ''}).then(modal => { modal.show(); ... })` with `core/modal`; also drops the implicit global `dialogue` and logs a rejection |
| `helper.js` (`deleteConfirm`) | `ModalFactory.create({title, type: ModalFactory.types.SAVE_CANCEL, body}).done(...)` | `ModalSaveCancel.create({title, body, removeOnClose: true}).then(...)` with `core/modal_save_cancel`; the save handler is unchanged. The close-button title selector also matches the Bootstrap 5 `.btn-close` |
| `ajaxforms.js`, `newgroup.js` | imported the factory but never called it | the unused dependency is removed (positional argument list kept in step) |
| `js/design.js` | `require(['<factory>'], ...)` then `.done(...)` | `require(['core/modal', 'core/modal_events'], ...)` then `.then(...)`; the hidden-event redirect is unchanged |

The four `amd/build` bundles were rebuilt with the repository's AMD toolchain (Node 22, the Moodle 5.3 Gruntfile, LF-normalised
sources, `--force` because the vendor code does not pass Moodle's ESLint). The same toolchain reproduces the committed
build of an unchanged file byte for byte, so the diff is only the intended change. No built file or map names the removed module.

## Not changed

- Plugin `version.php` (`2019052008.5`): AMD changes are picked up by the cache purge that every install and upgrade runs.
- `externallib.php` of `learnerscript` and `reportdashboard` still extend the legacy global `external_api` classes. On 5.3
  they resolve through `lib/db/renamedclasses.php` and only log deprecation debugging (not fatal). Taking a 5.x-compatible
  upstream release is the fix and is tracked under the vendor-upgrade open question.
- The bundled Spout CSV classes call `fputcsv`/`fgetcsv` without `$escape` (PHP 8.4 deprecation only; UAT and production
  run PHP 8.3).

## Upgrade-safety (how it merges with a future vendor update)

- Search for `SENTIENTIA-CORE-MOD (vendor)` in `blocks/learnerscript` to find every site.
- When a vendor release is taken that already uses `core/modal` / `core/modal_save_cancel`, take theirs and drop this patch.
  If the release still imports the factory, re-apply: swap the dependency, change `.done(` to `.then(`, rebuild the four bundles.
- Not a Moodle core file: nothing under `public/lib` is touched.

## Packaging

`tools/packaging/build-standalone.sh` and `moodle-enhancement/tools/overlay-airpay-customs.ps1` now ship `learnerscript`,
`reportdashboard` and `reporttiles` by default (`--without-learnerscript` / `-SkipLearnerscript` opt out). The stage
verification gate "no shipped amd/build names the removed modal factory" (JS and maps) covers them.

## Detected by

A static diff of the 5.1 and 5.3 AMD module lists (`core/modal_factory` and `core/modal_registry` are gone in 5.3) against
every `define`/`require` in the shipped plugins. Not yet exercised at runtime: when a 5.3 instance exists, open a report,
delete a report component (confirmation dialogue, Confirm button), and trigger an AJAX error dialogue.
