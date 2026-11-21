# Core-mod record: `lib/form/form.js`: `M.form.initFormDependencies` skips a form that is no longer in the document

- **Date:** 2026-11-20
- **Author:** Claude (workflow group G-FORMJS). Decided by Nitin in chat on 2026-11-20, about 08:30: decision D-14
  "Do all": make the root fix in core `lib/form/form.js` (ADR + core-mods record), in addition to the page guard
  (`docs/visual-evidence/2026-11-19/session-01/README.md`, "Decisions recorded"). Rules: ADR-044.
- **Kind:** Moodle core patch (not a vendor patch). The first edit to an existing core file on 5.3; ADR-044 amends
  ADR-033 decision 4 for this one file.
- **Feature flag:** none. It is a bug fix to core behaviour that has no setting (ADR-044 D6).
- **Tag at the site:** `// SENTIENTIA-CORE-MOD: <reason>` opening the hunk, `// END SENTIENTIA-CORE-MOD` closing it.

## Files

| Repository path | What it is | Serves |
|---|---|---|
| `lib/form/form.js` | the top-level core tree (the Moodle 4.5.10 snapshot, also the CI Playwright source root) with the hunk. Its upstream content is byte-identical to `public/lib/form/form.js` of 5.1.3+ (`C:/xampp/htdocs/moodle5`) and 5.2 (`C:/xampp/htdocs/moodle5.2`): SHA-256 `eebdcf57…1ef1509` | local XAMPP 5.1, the 5.2 fallback |
| `moodle-enhancement/core-patches/5.3/public/lib/form/form.js` | vanilla 5.3 `public/lib/form/form.js` (`D:/Claude Local/moodle53/moodle`, SHA-256 `cd7a5b63…f1bc2`) with the same hunk | UAT, the 5.3 cutover package |
| `moodle-enhancement/core-patches/MANIFEST` | one line per patched file: Moodle versions, repository path, tree path, pinned upstream SHA-256, this record | the tool below |
| `tools/check-core-patches.sh` | `check` (CI), `apply` and `verify-tree` (package build, local site) | |
| `local/sentientia_courses/tests/modal_form_guard_test.php` and `moodle-enhancement/local/sentientia_courses/tests/modal_form_guard_test.php` | PHPUnit tripwires, same bytes in both trees | |

Wiring changed with it: `tools/packaging/build-standalone.sh` (step 3b applies the patches, step 4a verifies them, the
DEPLOY-README names the patched file), `tools/uat/deploy_to_uat.sh` (maps the 5.3 copy, refuses the top-level `lib/`),
`.github/workflows/core-mods.yml` (runs `tools/check-core-patches.sh check --ref HEAD`), `.gitattributes`
(`moodle-enhancement/core-patches/**` and the tool keep LF in the working tree, so a deploy copies Moodle's bytes).

## The problem

Found in the session 0+1 verification (section "Repair after verification", same README). Course list page, New
course > Cancel > Edit threw in the console:

```
TypeError: Cannot read properties of null (reading 'get')   (lib/form/form.js, initElementsByName)
```

- `MoodleQuickForm_Renderer::finishForm()` (`lib/formslib.php`, 5.1.3+ line 3542, 5.3 line 3513) registers
  `M.form.initFormDependencies` through `js_init_call(..., true, moodleform::get_js_module())`. In a modal form the
  fragment's JS runs that as a deferred `Y.use('mform', ...)` callback, queued behind the shortforms, date selector
  calendar and `form.js` loads. On the slow local box that took seconds, once over a minute.
- `core_form/modalform` destroys the form when the modal closes. The late callback then builds a
  `M.form.dependencyManager` for a form id that is no longer in the document. Its `form` attribute setter returns
  `Y.one('#' + formid)`, which is null, and the initializer's first `this.get('form').get('elements')`
  (`initElementsByName`) throws.
- Any form with a show/hide or disable rule can do it: the date selector's `enddate[enabled]` rule is enough. So it is
  not specific to one page, only most visible on pages that open and close modal forms quickly.
- The page guard (session 0+1) wraps `M.form.initFormDependencies` on `local/sentientia_courses/index.php` only.

## Before (Moodle 5.1.3+ / 5.2 / repository 4.5.10 snapshot line 714; Moodle 5.3 line 727)

```js
M.form.initFormDependencies = function(Y, formid, dependencies) {

    // If the dependencies isn't an array or object we don't want to
    // know about it
    if (!Y.Lang.isArray(dependencies) && !Y.Lang.isObject(dependencies)) {
        return false;
    }
```

## After (hunk at lines 715-722 in `lib/form/form.js`; lines 728-735 in the 5.3 copy)

```js
M.form.initFormDependencies = function(Y, formid, dependencies) {
    // SENTIENTIA-CORE-MOD: skip a form that is no longer in the document. Pages run this from a deferred
    // Y.use('mform') callback. A modal form (core_form/modalform) is removed when its modal closes, and a late
    // call then threw in initElementsByName (null form). Nothing is left to set up, so return as for no rules.
    // See moodle-enhancement/docs/core-mods/2026-11-20-form-js-dependency-guard.md (ADR-044).
    if (!document.getElementById(formid)) {
        return false;
    }
    // END SENTIENTIA-CORE-MOD

    // If the dependencies isn't an array or object we don't want to
    // know about it
    if (!Y.Lang.isArray(dependencies) && !Y.Lang.isObject(dependencies)) {
        return false;
    }
```

The hunk only adds lines; nothing upstream is changed or moved. Removing it gives back the upstream file byte for
byte, which is what `tools/check-core-patches.sh` checks against the pinned hashes. `M.form.initFormDependencies` is
the same function in 4.5.10, 5.1.3+, 5.2 and 5.3 (5.3 differs from 5.1 only in `_disableElement`'s group handling,
around line 271, which the hunk does not touch). `document.getElementById` is used rather than `Y.one('#' + formid)`
because it does not parse the id as a selector; it is also what the page guard uses.

## Behaviour change

- A form that is still on the page: unchanged. The manager is built exactly as before.
- A form that is gone when its deferred setup runs: the function returns `false` (the value core already returns for
  "no rules") and registers no manager. Before, it threw, which left a console error and stopped the rest of that
  callback. Nothing was set up in either case.
- `M.form.updateFormState(formid)` for a skipped form does nothing (its id is not in `M.form.dependencyManagers`), as
  for a form without rules.

## Why core (decision criteria of the README)

1. **Can a plugin reach it?** Only per page, by wrapping the global after `mform` loads, which is the page guard.
   A site-wide wrapper from the theme would load `form.js` on every page, forms or not, and depends on running before
   every queued init (ADR-044, alternatives). Nitin chose the core fix (D-14).
2. **Additive or destructive?** Additive: one early return, eight lines.
3. **Upstream-merge risk:** low. `lib/form/form.js` is a legacy YUI file that changes rarely; the function itself is
   unchanged since 4.5.
4. **Upstream contribution:** yes, a candidate MDL issue (ModalForm plus deferred `initFormDependencies`). Not filed.

## Where the patched file lives and how it is deployed (ADR-044 D3, D4)

The repository carried no patched Moodle core file before this one: the earlier records are vendor-plugin patches,
added files (`my/`), or a hunk applied by hand on the 5.2 instance (`2026-06-11-setuplib-ini-get-bool-guard.md`).
The 5.3 package is a vanilla 5.3 tree plus the overlay, built from git, and the top-level `lib/` is the 4.5.10
snapshot, so a patch in `lib/` alone reaches neither UAT nor the package. Hence one full file per Moodle version:

| Site | File | How |
|---|---|---|
| Local XAMPP 5.1.3+ | `lib/form/form.js` | `bash tools/check-core-patches.sh apply --target 5.1 --tree C:/xampp/htdocs/moodle5`, then `php ../admin/cli/purge_caches.php` (cwd `public/`). Copying into `moodle5/public/lib/` is Nitin's call. The tool writes LF and refuses unless the site still holds the pinned upstream file |
| UAT (5.3) | `moodle-enhancement/core-patches/5.3/public/lib/form/form.js` | `tools/uat/deploy_to_uat.sh --no-upgrade moodle-enhancement/core-patches/5.3/public/lib/form/form.js` (dry run, then `--yes`, on Nitin's go). First check on the box that `sha256sum public/lib/form/form.js` is the pinned 5.3 hash `cd7a5b63…` |
| 5.3 package | the same | `build-standalone.sh --target 5.3`: step 3b runs `apply` with the committed file of `--ref`; step 4a runs `verify-tree`; a base whose `form.js` is not the pinned file fails the build |
| 5.2 fallback package | `lib/form/form.js` | the same steps with `--target 5.2` (vanilla 5.2 `form.js` = the pinned 5.1 hash) |

`deploy_to_uat.sh` now skips any top-level `lib/` path, also when it comes from `--commit` or `--range`: that file is
the 5.1/5.2 version and would undo 5.3's own `_disableElement` changes on UAT.

## Version bump: none

`form.js` is a YUI module (`mform`), served through `lib/javascript.php` and cached by `jsrev`. No schema, capability,
string, template or AMD build changes. Deploy without an upgrade and purge caches (it bumps `jsrev`, so browsers fetch
the new file). `local_sentientia_courses` gains only test files, which need no bump.

## The page guard stays (belt and braces)

The block in `local/sentientia_courses/index.php` (both trees) is unchanged. On a patched core it is redundant: its
wrapper and the core hunk make the same check, one `getElementById` more per form. It is kept because it still
protects that page on a core that lost the patch (a Moodle point release unpacked before the hunk is re-applied), and
because removing it would change plugin code in both trees for no gain. Revisit after the 5.3 cutover, once the patch
has survived one upgrade (ADR-044 open question 2).

## Tests

- **PHPUnit (written, not run):** `local/sentientia_courses/tests/modal_form_guard_test.php`
  1. `test_page_guard_wraps_form_dependency_init`: the page guard is still in `index.php` and loads `mform`.
  2. `test_core_form_js_skips_a_removed_form`: `$CFG->dirroot/lib/form/form.js` carries exactly one hunk and the early
     return sits inside `M.form.initFormDependencies`, before the manager is built. A tripwire: it FAILS on a site
     whose `form.js` is still vanilla (the local XAMPP until the patch is applied there) or lost the patch. Skipped on
     GitHub Actions, whose PHPUnit job runs a vanilla Moodle clone.
  Run from `C:\xampp\htdocs\moodle5\`: `vendor\bin\phpunit --filter modal_form_guard_test`.
- **Repository check (run 2026-11-20, pass):** `bash tools/check-core-patches.sh check` (2 files, 1 hunk each, both
  equal to their pinned upstream file with the hunk removed). Negative cases also run in a scratch copy: an unclosed
  marker, an edit outside the hunk, a file without a hunk and a malformed manifest line each fail; a CRLF copy passes.
- **apply / verify-tree (run 2026-11-20 on scratch trees, pass):** vanilla 5.3 and 5.1 trees are patched and read back
  as the committed files; a second `apply` is a no-op; a 5.1 file under `--target 5.3` and an edited 5.3 file are
  refused and left untouched; `verify-tree` on a vanilla tree fails.
- **Behaviour (scratch, run 2026-11-20 with node 22):** both patched files and both vanilla files were executed in a
  fake YUI/DOM whose manager throws on a null form, as the real one does. Vanilla 5.1 and 5.3: a removed form throws
  "Cannot read properties of null (reading 'get')". Patched: a removed form returns `false` and registers nothing; a
  present form gets its manager; no-rules returns `false`. With the page guard added on top, the results are the same.
  `node --check` passes on both patched files. (Scratch script, not committed.)

## Upgrade-safety (how it merges with a future Moodle pull)

- Grep anchors: `git grep -n SENTIENTIA-CORE-MOD -- lib/form/form.js moodle-enhancement/core-patches/`.
- A Moodle point release that changes `form.js` makes `apply` refuse (the site's or the base's file no longer has the
  pinned hash), so the build fails at step 3b instead of shipping an old core file. Then: take the new upstream file,
  re-insert the hunk at the top of `M.form.initFormDependencies`, update the hash in `MANIFEST`, run
  `tools/check-core-patches.sh check`.
- If upstream fixes the race (a null-form check in `initFormDependencies` or in the manager's initializer, or modal
  forms that set up their rules synchronously), drop the hunk: delete the 5.3 copy and its MANIFEST line, restore
  `lib/form/form.js`, add a "Reverted" note here, and drop test 2.
- If the Moodle release on a site is upgraded in place (UAT), the upgrade replaces `form.js` and the patch is lost:
  re-apply it, and the tripwire test says so until then. The page guard covers the course list meanwhile.
- The top-level `lib/` tree is not updated with Moodle releases (it stays the 4.5.10 snapshot); only its `form.js`
  carries the hunk. `.github/workflows/core-mods.yml` checks both copies on every pull request and push.

## Risks

1. **A wrong form id is silent now.** A form whose id never existed used to throw; now it gets no rules quietly. Either
   way it had no rules, so behaviour is the same; only the console error is gone.
2. **UAT's `form.js` is not the pinned 5.3 file.** Then the deploy would replace a different file. The record's UAT row
   asks for the hash check first; the deploy script backs up the old file (`/tmp/uat-predeploy-backup-*.tgz`).
3. **`deploy_to_uat.sh` no longer deploys top-level `lib/` files.** No top-level `lib/` file had changed since
   2026-04-03 (the tree is a frozen snapshot); a future need goes through `moodle-enhancement/core-patches/5.3/`.

## How to verify

Browser steps (local, after the patch is applied to XAMPP and caches are purged; no flag needed):

1. As site admin, open `http://localhost:8080/local/sentientia_courses/index.php`, DevTools console open. New course,
   then Cancel at once; Edit on a course row, then Cancel at once; repeat three times. Expected: no TypeError.
2. Deterministic check of the core hunk, on a page that loads `form.js` but not the page guard: core's course edit
   page `http://localhost:8080/course/edit.php?id=<a test course>`. In the console:
   `YUI().use('node', function(Y) { console.log(M.form.initFormDependencies(Y, 'no-such-form', {})); });`
   Expected: `false`. On a vanilla `form.js` the same line throws "Cannot read properties of null (reading 'get')".
3. Open the New course form and leave it open: the "Course end date" enable checkbox still enables and disables the
   date fields, and the format selector still disables "Number of sections" for Social. Expected: rules work as before.
4. A core page that opens a form with a show/hide rule in a modal, outside the page guard (for example a course's
   Participants > Enrol users): open and cancel at once, a few times. Expected: no TypeError. The race needs a slow
   load, so a clean console here is the expected result, not proof; step 2 is the proof.

Then `vendor\bin\phpunit --filter modal_form_guard_test` (both tests pass on the patched site).
