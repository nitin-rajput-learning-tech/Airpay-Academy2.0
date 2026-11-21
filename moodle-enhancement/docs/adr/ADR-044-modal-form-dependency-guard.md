# ADR-044 — Modal-form dependency guard: the first Moodle core patch on 5.3, and how versioned core patches are kept and shipped

- **Status:** Accepted 2026-11-20. Proposed the same day as decision D-14 of the session 0+1 report
  (`docs/visual-evidence/2026-11-19/session-01/README.md`, decision table D-1..D-15); Nitin answered D-14 in chat on
  20 Nov, about 08:30: "Do all": make the root fix in core `lib/form/form.js` (ADR + core-mods record), in addition to
  the page guard.
- **Date:** 2026-11-20
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
- **Implementer:** Claude
- **Amends:** ADR-033 decision 4 ("no edits to existing core files on 5.3"), for one file: `public/lib/form/form.js`.
  Everything else in decision 4 stands (the setuplib guard stays retired on 5.3).
- **Related:** ADR-001 (lifts "never modify core", creates `docs/core-mods/`), ADR-041 (hunk shape and tests for vendor
  patches; this ADR reuses its D3 and D6 for core), `docs/core-mods/README.md` (record format, CI gate).
- **Evidence:** `docs/core-mods/2026-11-20-form-js-dependency-guard.md` (the record: problem, before/after, tests);
  the session 0+1 report, section "Repair after verification (the console TypeError)".

---

## Context

On the course list page, closing a modal form quickly and opening another threw
`TypeError: Cannot read properties of null (reading 'get')` in core `lib/form/form.js`. Core registers a form's
show/hide and disable rules with `M.form.initFormDependencies`, from a deferred `Y.use('mform', ...)` callback. A modal
form (`core_form/modalform`) is destroyed when its modal closes; when the callback runs later, the dependency manager
gets a null form and throws in `initElementsByName`. Any form with a rule can hit it, on any page that loads forms in
modals. Session 0+1 fixed the one page (`local/sentientia_courses/index.php` wraps the function and skips a removed
form). The root cause is in core.

Two facts shape how a core fix can ship:

- **Core is not in git for 5.3.** The repository's top-level `lib/` is a Moodle 4.5.10 snapshot (the CI Playwright
  source root), unchanged since 2026-04-03. The 5.3 package is a vanilla 5.3 tree plus the Sentientia overlay,
  built from git (`tools/packaging/build-standalone.sh`), and the overlay ships no core file. UAT runs 5.3; the local
  XAMPP runs 5.1.3+.
- **The file differs by version.** `form.js` of 4.5.10, 5.1.3+ and 5.2 is one file (SHA-256 `eebdcf57…`); 5.3's differs
  in `_disableElement` (group children). `M.form.initFormDependencies` is the same in all of them.

---

## Decision

### D1 — The fix: skip a form that is no longer in the document

`M.form.initFormDependencies` returns `false` (its existing "no rules" value) when `document.getElementById(formid)`
is null, before anything else. A form that is still on the page is set up exactly as before. One additive hunk of
eight lines, tagged as ADR-041 D3 describes (`// SENTIENTIA-CORE-MOD: <reason>` ... `// END SENTIENTIA-CORE-MOD`).

### D2 — Core hunks only add lines

A core hunk may insert lines; it may not change or move upstream lines. Removing every hunk must give back the
upstream file byte for byte. This makes each patched file checkable against a pinned upstream hash and re-applicable to
a new release by hand in minutes.

### D3 — A patched core file is kept as a full file per Moodle version, with a manifest

- 5.1 / 5.2: the top-level `lib/form/form.js` (same upstream file as the 4.5.10 snapshot).
- 5.3: `moodle-enhancement/core-patches/5.3/public/lib/form/form.js`.
- `moodle-enhancement/core-patches/MANIFEST` lists each patched file: Moodle versions, repository path, path in a tree,
  pinned SHA-256 of the upstream file, record.
- `tools/check-core-patches.sh` checks every listed file (`check`: at least one hunk, markers paired, file minus hunks
  equals the pinned upstream file), puts the patches into a tree (`apply`: only over the pinned upstream file, else it
  refuses and writes nothing) and verifies a tree (`verify-tree`).
- `.gitattributes` keeps `moodle-enhancement/core-patches/**` LF in the working tree, so copies are Moodle's bytes.

### D4 — Deploy routes

- **Package:** `build-standalone.sh` step 3b runs `apply --target <5.3|5.2> --ref <ref>` on the stage (committed files
  only); step 4a runs `verify-tree`. A base release whose file is not the pinned one fails the build.
- **UAT:** `tools/uat/deploy_to_uat.sh --no-upgrade moodle-enhancement/core-patches/5.3/public/lib/form/form.js`, on
  Nitin's go. The script maps `core-patches/5.3/public/<path>` to `public/<path>` and now **refuses top-level `lib/`
  paths** (also from `--commit` / `--range`): those are the 5.1/5.2 files and would undo 5.3 changes on UAT.
- **Local 5.1:** `tools/check-core-patches.sh apply --target 5.1 --tree C:/xampp/htdocs/moodle5`, then purge caches
  (copying into core dirs of the local site is Nitin's call).
- **CI:** `.github/workflows/core-mods.yml` runs `tools/check-core-patches.sh check --ref HEAD` on every pull request
  and push; the existing path gate still requires the record and marker for `lib/form/form.js`.

### D5 — The page guard stays

`local/sentientia_courses/index.php` keeps its wrapper: redundant on a patched core, it still protects that page on a
core that lost the patch (an in-place Moodle upgrade before the hunk is re-applied). Removing it changes plugin code in
both trees for no gain now.

### D6 — No feature flag, no version bump

A core bug fix with no setting takes no flag (as ADR-041 D2 for vendor fixes). `form.js` is a YUI module served
through `lib/javascript.php`: deploy without an upgrade and purge caches (new `jsrev`).

### D7 — Tests

A PHPUnit tripwire in `local/sentientia_courses/tests/modal_form_guard_test.php` (both trees) asserts the page guard
and that the site's `lib/form/form.js` carries the hunk inside `M.form.initFormDependencies`; it is skipped on GitHub
Actions (vanilla Moodle clone). `tools/check-core-patches.sh check` guards the files in git.

### D8 — Moodle releases

A new release that changes a patched file makes `apply` refuse. Re-insert the hunk into the new upstream file, update
the MANIFEST hash, run `check`, record it in the core-mods record. If upstream fixes the defect, remove the patch and
add a "Reverted" note to the record (README rules).

---

## Consequences

- **Positive:** every modal form on the site stops throwing, not one page. Each core patch is findable, checked in CI
  against its upstream file, and cannot ship onto the wrong release (the build and `apply` refuse). The UAT deploy
  script can no longer push a 5.1-era core file onto 5.3.
- **Negative:** the 5.3 package now edits one core file, so "the package only adds files" is no longer true (the
  DEPLOY-README says so). Each Moodle point release needs the `form.js` hash checked. An in-place upgrade on a site
  drops the patch until re-applied (the tripwire test and the page guard cover the gap).
- **Neutral:** `lib/form/form.js` is the first change to the top-level core snapshot since 2026-04-03 (commit
  c59f05348); the 5.3 copy is the first core file kept outside it.

---

## Alternatives considered

- **Keep only page guards** (D-14 as first recommended). Every page with modal forms would need its own copy, and core
  pages that open forms with rules in modals would stay exposed. Nitin chose the core fix.
- **One site-wide wrapper from the theme or a plugin hook.** No core edit, but it must load `form.js` on every page
  (forms or not) and win a load-order race against every queued init; a failure is silent. Rejected.
- **A `.patch` file applied at package time.** Smaller in git, but a UAT deploy needs a real file, and a patch that
  stops applying fails late. The full file plus a pinned upstream hash fails just as early and deploys as it is.
- **Put a 5.3 `form.js` in the top-level `lib/`.** That tree is the 4.5 layout that CI Playwright runs; a 5.3 file
  there would break it. Rejected.
- **Wait for upstream.** Leaves the error on every modal form until an MDL fix lands. Offering the fix upstream is
  still open (open question 1).

---

## Implementation actions

1. Done 2026-11-20 (not committed by this step): the hunk in `lib/form/form.js` and the 5.3 copy; MANIFEST;
   `tools/check-core-patches.sh`; build, deploy and CI wiring; `.gitattributes`; the PHPUnit tripwire (both trees);
   the core-mods record and README index row; this ADR and its index row.
2. Pending, Nitin's call: apply to the local XAMPP and purge caches; run the PHPUnit test; browser check (record,
   "How to verify").
3. Pending, Nitin's go: deploy the 5.3 copy to UAT after checking UAT's `form.js` hash.

---

## References

- `docs/core-mods/2026-11-20-form-js-dependency-guard.md`; `docs/core-mods/README.md`
- `moodle-enhancement/core-patches/MANIFEST`; `tools/check-core-patches.sh`
- `tools/packaging/build-standalone.sh`; `tools/uat/deploy_to_uat.sh`; `.github/workflows/core-mods.yml`
- ADR-001, ADR-033, ADR-041

---

## Open questions for future ADRs

1. File an MDL issue with the fix (ModalForm plus deferred `initFormDependencies`)?
2. Remove the page guard after the 5.3 cutover, once the patch has survived one upgrade?
3. Extend the core-mods path gate to `moodle-enhancement/core-patches/` as well (today `check-core-patches.sh` covers it)?
