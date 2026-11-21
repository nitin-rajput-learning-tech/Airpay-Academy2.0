# Core-mod record: `tool_certificate` vendor import 4.5.7 -> 5.0.12 (the release UAT runs)

Core-Mods-Upstream-Sync: admin/tool/certificate/ v5.0.12 (2026111000)

- **Date:** 2026-11-20 (re-vendored to 5.0.12 on 2026-11-20)
- **Author:** Claude. Package prepared 19-20 Nov 2026 (post-demo plan `docs/plans/2026-11-19-POST-DEMO-PLAN.md`,
  session 0, group G-CERT-VENDOR); applied 20 Nov 2026 (group G-VENDOR) with
  `revendor_tool_certificate.py --execute`, then `--verify` (both in `D:/Claude Local/kit-scratch/1119/s01/revendor/`).
- **Approved by:** Nitin, decision D-7 of the session 0+1 report
  (`docs/visual-evidence/2026-11-19/session-01/README.md`, "Decisions recorded", 20 Nov ~08:30): apply the re-vendor,
  with a hook change for pre-commit CHECK 2 rather than a one-off `--no-verify`. This also answers ADR-033 open
  question 4.
- **Rules:** ADR-041 D7 (importing a vendor release); README "Upstream Moodle or vendor imports". The sync line above
  goes into the repository in the same commit as the imported files, never before them.
- **Vendor release imported:** tool_certificate 5.0.12, `version.php` 2026111000, `requires = 2025041400.00`
  (Moodle 5.0), `supported = [500, 503]`. Replaces 4.5.7 (2025031804, `supported = [400, 405]`), imported in
  commit 82fee0969 (2 Apr 2026).

## Why

UAT runs tool_certificate 5.0.12 (2026111000); the repository, the package source, carried 4.5.7. The 19 Nov deploys
put the repository's 4.5.7-based patched `index.php` and `classes/output/verify_certificate_result.php` on top of
UAT's 5.0.12, so UAT ran a mix, and anything built from the repository (local XAMPP, the cutover package) would
have shipped a release that is not the one tested on UAT and that declares no support for Moodle 5.x.

## Source of the bytes

- Every file except two: UAT's own copy (`/var/www/html/moodle5.2/public/admin/tool/certificate`, fetched read-only
  with `tar -cz` over `ssh uat-lms` on 19 Nov at 16:28, after the last deploy of the day at 16:19; extracted to
  `kit-scratch/1119/s01/uat-cert/certificate`, 231 files). Later re-fetches failed (tunnel down); no UAT deploy of
  this plugin happened after 16:19.
- `index.php` and `classes/output/verify_certificate_result.php`: UAT's copies were replaced by our 4.5.7-based
  patched files on 19 Nov (15:15 and 16:19 deploys). The script checks that UAT's copies equal git HEAD's and takes
  the copies fetched from UAT before those deploys instead (`kit-scratch/1119/uat2/uat-cert-index.php`,
  `kit-scratch/1119/uat3/uat-vcr.php`).
- **Checked against upstream by git blob SHA-1** (`kit-scratch/1119/s01/revendor/blobcheck.py`, against the tree
  listing of tag v5.0.12 of `moodleworkplace/moodle-tool_certificate`, 228 files): 226 UAT files are byte-identical
  to upstream; the other 2 are the files named above, and both pre-deploy captures are byte-identical to upstream.
  UAT's other 3 files are our tests. So the imported tree is upstream v5.0.12 exactly, plus our hunks.
- Line endings: the script writes UAT's LF bytes; with `core.autocrlf=true` git stores LF, as for every other file.

## What the run did (20 Nov 2026)

| Step | Report (`kit-scratch/1119/s01/revendor/`) | Result |
|---|---|---|
| Dry run | `report-dryrun-20261120-084538.txt` | Selftest OK; repo guards 1-3 OK at HEAD 4df7d4573 (the pinned files are unchanged since af6e65c0e); plan 12 new, 144 changed, 73 unchanged |
| `--execute` | `report-execute-20261120-084638.txt` | Wrote 156 files under `admin/tool/certificate/`; `php -l` OK on the 3 patched files |
| `--verify` | `report-verify-20261120-084812.txt` | **PASS**: repo == UAT byte for byte (CR-insensitive) except the 4 expected files; 0 lint failures over every PHP file in the plugin |

The 4 files that now differ from UAT on purpose: `index.php`, `classes/output/verify_certificate_result.php`,
`element/image/classes/element.php`, `tests/sentientia_verify_on_arrival_test.php` (see "UAT" below).

## Compatibility

| Moodle | Core version | In `requires`? | In `supported`? |
|---|---|---|---|
| Local XAMPP 5.1.3+ | 2025100603.13 (branch 501) | yes (>= 2025041400) | yes |
| UAT / target 5.3 | 2026100500.00 (branch 503) | yes | yes |

The plugin check page no longer shows "not supported" for tool_certificate (4.5.7 declared [400, 405]).
`db/install.xml`, `db/access.php`, `db/services.php`, `db/messages.php` and `db/events.php` are unchanged between the
two releases. `db/upgrade.php` and `db/upgradelib.php` changed, but the last upgrade step is still
`if ($oldversion < 2023071300)`, so the local upgrade (2025031804 -> 2026111000, needed on XAMPP:
`php ../admin/cli/upgrade.php --non-interactive`) runs no schema or data step and records the new version. It also
registers the new `db/mobile.php` handler. UAT is already at 2026111000. 5.0.12 adds no `db/tasks.php`
(`classes/task/regenerate_certificates.php` is an ad hoc task).

Other upstream changes that touch Sentientia:
- `lang/en/tool_certificate.php`: 186 strings against 173 in 4.5.7 (13 added, 1 reworded, none removed; see the
  hi-pack addendum).
- `classes/observer.php`: 5.0.12's `db/events.php` still observes only `\core\event\course_content_deleted`, so the
  release does not issue course certificates on completion either (input for session 1b).
- Four templates changed; none is overridden by the theme. `verify_certificate_results.mustache`, which the theme
  overrides, is unchanged upstream.
- Upstream features that come with the release, already live on UAT, with no flag: regenerate certificates
  (bulk and single, `classes/form/certificate_renew.php`, `classes/task/regenerate_certificates.php`,
  `templates/bulk_action_selector.mustache`) and the Moodle-app "My certificates" handler (`db/mobile.php`,
  `classes/output/mobile.php`, `mobileapp/`). Relevant to the native-app track. Shipping user-visible features with
  no flag is a CLAUDE.md escalation (section 12), and the regenerate feature has a cross-tenant permission gap
  (upstream code, live on UAT): both are open decisions for Nitin, see "Open" below.

New upstream files (12): `.moodle-plugin-ci.yml`, `classes/form/certificate_renew.php`, `classes/output/mobile.php`,
`classes/task/regenerate_certificates.php`, `db/mobile.php`, `mobileapp/js/mycertificates_init.js`,
`mobileapp/styles/mycertificates.css`, `pix/icon-old.svg`, `templates/bulk_action_selector.mustache`,
`templates/mobile_my_certificates_page.mustache`, `tests/output/mobile_test.php`,
`tests/task/regenerate_certificates_test.php`. 144 files changed, 73 are identical.

## The recorded patches (ADR-041 D7 step 2)

| Record | Upstream 5.0.12 | Action | Lines on 5.0.12 |
|---|---|---|---|
| `2026-05-23-certificate-image-imageinfo-guard.md` | still passes `get_imageinfo()` unchecked | **Re-applied** | `element/image/classes/element.php` 29-30 (guard) and 222-235 (non-image guard) |
| `2026-05-29-tool_certificate-hi-pack.md` | (additive, staged, not applied) | No file to re-apply; 14 keys to top up before activation | (none: the pack is not in `lang/hi/`) |
| `2026-09-03-tool-certificate-5.2-reset-caches.md` | declares `reset_caches(): void` (line 296) | **Reverted:** upstream taken | (no marker) |
| `2026-10-08-tool-certificate-duration-defaultunit.md` | spells `'defaultunit' => DAYSECS` (line 637) | **Reverted:** upstream taken, with the guard hunk that existed only for CHECK 2 | (no marker) |
| `2026-11-19-tool-certificate-verify-on-arrival.md` | unchanged behaviour | **Re-applied**; tripwire made layout-independent | `index.php` 50-60 and 69-72 |
| `2026-11-19-tool-certificate-verify-result-escaping.md` | unchanged behaviour | **Re-applied** | `classes/output/verify_certificate_result.php` 35-38 (guard) and 96-102 (escaping) |

Sentientia-owned files kept: `tests/sentientia_verify_on_arrival_test.php` (one assertion changed, below),
`tests/sentientia_verify_result_escaping_test.php`, `tests/behat/sentientia_verify_on_arrival.feature`.

Tripwire change: `test_index_php_carries_the_patch()` asserted the 4.5.7 one-line call
`verify_certificate_form(new moodle_url('/admin/tool/certificate/index.php')`. It now asserts
`~verify_certificate_form\(\s*new moodle_url\('/admin/tool/certificate/index\.php'\),~`, which matches either
layout. The marker count (2) and the condition assertion are unchanged.

Marker inventory after the import (`git grep -n "SENTIENTIA-CORE-MOD" -- admin/tool/certificate/`): 4 in
`index.php`, 4 in `classes/output/verify_certificate_result.php`, 2 in `element/image/classes/element.php` (old-style
tags, kept as recorded), 2 each in the two Sentientia PHPUnit files.

## Files upstream no longer ships (not deleted)

| File | Note |
|---|---|
| `admin/tool/certificate/pix/icon.png` | 5.0.12 ships `pix/icon.svg` (the repo has it too) and `pix/icon-old.svg`. A stale extra file is harmless (Moodle resolves `.svg` before `.png`). |

The import script never deletes. Remove it with `git rm admin/tool/certificate/pix/icon.png` in the import commit
(the lead's call); this record covers the deletion (CHECK 6 passes deletions under the sync prefix).

## Pre-commit hook change (decision D-7)

Upstream's namespaced class files carry no `MOODLE_INTERNAL` line (Moodle's own style), and `--verify`'s hook preview
listed 66 staged files that CHECK 2 would reject. `.claude/hooks/pre-commit.sh` now computes the upstream sync
prefixes once, in `sync_prefixes()` near the top (the block that was inline in CHECK 6, moved with no change to what
it computes), and CHECK 2 skips an unguarded file only when CHECK 6 would skip it as synced: the file is a guarded
core file and sits under a prefix named by a `Core-Mods-Upstream-Sync` line staged new with the commit. Every other
file is checked as before; a line already in HEAD exempts nothing. CHECK 2 has no CI twin, so CI is unchanged. The
hook is per clone: reinstall it before the import commit (`pwsh -File tools/install-hooks.ps1`, or copy
`.claude/hooks/pre-commit.sh` to `.git/hooks/pre-commit`).

Correction (review, 20 Nov): the first version of this change applied the prefix in CHECK 2 without CHECK 6's
exclusions, so the "same rule" claim above was wrong for one case. Under a broad prefix (one sync line per core dir,
`admin/` or `mod/quiz/`, as README.md documents for a Moodle release) CHECK 2 skipped unguarded class and lib files in
our own `admin/tool/sentientia_*/` and `mod/quiz/accessrule/sentientia_*/` plugins, which CHECK 6 never treats as
core. The guarded-dir and "ours" filters now live in one helper, `core_paths()` near the top of the hook: CHECK 6
builds `CORE_STAGED` with it (same three patterns as before), and CHECK 2 skips a file only when `core_paths()` keeps
it and it is under a sync prefix. This import is not affected: every PHP file under `admin/tool/certificate/` (142)
is a core path, so CHECK 2 still skips the same files.

## Version bump

None of ours: `version.php` is upstream's (2026111000). ADR-041 D5: no Sentientia bump.

## UAT (decision D-8, Nitin's go)

UAT already runs 5.0.12, so only the 4 files above differ. Three of them carry behaviour; the image element closes
the UAT image TypeError (UAT's 5.0.12 copy has no guard, so a template image element whose file is not an image
crashes the template page there):

```
tools/uat/deploy_to_uat.sh --no-upgrade \
  admin/tool/certificate/element/image/classes/element.php \
  admin/tool/certificate/index.php \
  admin/tool/certificate/classes/output/verify_certificate_result.php \
  admin/tool/certificate/tests/sentientia_verify_on_arrival_test.php
```

Dry run first, then `--yes`. No upgrade and no version change: UAT's `version.php` is already 2026111000.

## Upgrade-merge notes

- Next import: run the same script with the new release numbers (`EXPECT_RELEASE`, `EXPECT_VERSION`) and the hunks
  in `PATCHES`; it stops on any missing anchor. Refresh its repo-side pins (`UPSTREAM_457_BLOB`, `EXPECTED_REVERT`,
  `PATCHED_TESTS_BASE`) from the commit that carries this record.
- Add a new `Core-Mods-Upstream-Sync` line for the new release in the new import's own record. This line stays (it is
  a record too).

## Open (not in this change)

- `element/digitalsignature/classes/element.php` line 164 has the same unchecked `get_imageinfo()` pattern as the
  image element. Pre-existing upstream; no report of it failing. A further vendor hunk would need its own record.
- The staged Hindi pack: 13 new keys and 1 reworded key to translate and review before activation (hi-pack record).
- **Security, upstream code, already on UAT (decision for Nitin; found in review 20 Nov):** the new "regenerate
  certificates" feature does not check that the issues it regenerates belong to the template whose permission it
  checks, so the check works across tenants. `classes/form/certificate_renew.php`,
  `check_access_for_dynamic_submission()` (lines 100-138):
  - With a non-zero `templateid` in the posted form data, `$issues` stays empty and only `tool/certificate:issue` on
    that template's context is checked. `process_dynamic_submission()` then regenerates whatever `issueids` were
    posted: `regeneratesingleuser` directly (`get_record` by id, then `create_issue_file($issue, true,
    $sendnotification)`); `regenerateselectedusers` through the ad hoc task, whose `execute()` runs as the cron user
    and calls `can_issue((int)$issue->id)`, passing an issue id where a user id is expected.
  - With `templateid=0` and ids from several templates, only the first issue's template is used, and an issue with
    no course falls back to that template's context.
  - Failure case: a user who holds `tool/certificate:issue` on one template's context (a tenant L&D role on its own
    category template) posts `templateid=<that template>`, `issueids=<other tenants' issue ids>`,
    `actiontype=regeneratesingleuser` or `regenerateselectedusers`, `sendnotification=1`. Other tenants' certificate
    PDFs are regenerated, `data.userfullname` is rewritten, `certificate_regenerated` events fire and the holders get
    notification e-mails.
  - Not changed in the import (ADR-041 D7: the import carries upstream bytes and the recorded hunks only). The fix,
    if Nitin approves, is an ADR-041 D2 bug-fix hunk with its own record (`docs/core-mods/<date>-tool-certificate-
    regenerate-scope.md`), `SENTIENTIA-CORE-MOD` markers, a `tests/sentientia_regenerate_scope_test.php` with a
    tripwire, and a README index row; deploy to UAT with `--no-upgrade` on his go. Spec of the hunk: in
    `check_access_for_dynamic_submission()`, for every `actiontype` except `regenerateall`, always load the issues
    named by `issueids` (stop if any id is missing), require each issue's `templateid` to equal the template being
    checked (`templateid` when posted, else the first issue's), and check `can_issue_to_anybody()` in each issue's
    own context (its course context, else the template's). `regenerateall` keeps the template-context check (the
    task selects by `templateid`). Tests: a user with the capability on template A only is refused for an issue of
    template B (single and selected), with `templateid=A` posted and with `templateid=0` and mixed ids; the A-only
    case still passes.
  - The task's `can_issue((int)$issue->id)` call is a second upstream defect (wrong argument; as the cron user the
    capability check always passes). Once the form checks every issue, it no longer decides access; leave it
    upstream's unless Nitin wants it in the same hunk. Report both upstream (moodleworkplace/moodle-tool_certificate).
- **Flag escalation (CLAUDE.md section 12, decision for Nitin):** the import brings user-visible upstream features
  with no Sentientia flag (regenerate certificates, the Moodle-app "My certificates" handler); both already run on
  UAT. ADR-041 D2 keeps new behaviour out of vendor hunks, so a flag cannot go into the vendor code. Options:
  (a) accept them as upstream behaviour of the vendored release, recorded here; (b) hold them back by configuration
  until reviewed (for the app handler, the Moodle app "Disabled features" setting, to be checked; the regenerate
  action is gated by `tool/certificate:issue`, the issuing capability, so no capability override removes it without
  also removing issuing). The permission gap above needs fixing under either option.

## Tests

- `vendor/bin/phpunit --group tool_certificate` from `C:\xampp\htdocs\moodle5\` after the local upgrade (upstream
  5.0.12 tests plus the two Sentientia files with their tripwires). Not run in this step.
- Behat `tests/behat/sentientia_verify_on_arrival.feature` and upstream `verify_certificates.feature`.
- The hook change (run 20 Nov; harness and outputs in `kit-scratch/1119/s01/revendor/hooktest/`; the hook has no
  built-in selftest):
  - 9 scratch-repo cases for CHECKs 1-6 (sync line staged new, sync line already in HEAD, a line in README.md, a
    malformed line, a sibling prefix, the `public/` layout, an unguarded file in another plugin, all files guarded):
    new hook 14/14 assertions pass; the old hook fails exactly the 4 assertions that expect the skip, and matches
    the new hook on the other 10.
  - The import itself, staged into a copy of the index (`GIT_INDEX_FILE`; the real index was not touched): CHECK 1
    PHP syntax OK (124 files); CHECK 2 "66 unguarded file(s) skipped"; CHECK 6 157 files under the sync (the 156
    written plus the `pix/icon.png` deletion); CHECKs 3, 5 and 7-20 pass; CHECK 4 (credential patterns) checked
    with the same patterns by hand: no match.
  - Review fix (`core_paths()`, 20 Nov): 2 more cases in `run_hook_cases.sh`. J: staged sync lines `admin/tool/ v1`
    and `mod/quiz/ v5.3.1` with unguarded `admin/tool/sentientia_x/classes/a.php`,
    `mod/quiz/accessrule/sentientia_p/classes/b.php` and `public/admin/tool/sentientia_y/classes/c.php`: CHECK 2 must
    report all three as "Missing MOODLE_INTERNAL guard" and skip nothing; CHECK 6 "No core file modifications".
    K: the `admin/tool/` line with an unguarded upstream file beside one of ours: CHECK 2 skips the upstream file,
    reports ours, and CHECK 6 counts 1 file under the sync. New hook 22/22 (A-K; `new-review.result`); the hook
    before the fix fails 5 of the 8 J/K assertions, the ones that expect our files to be reported
    (`orig-review-jk.result`). The import's own CHECK 2 result is unchanged: all 142 PHP files under
    `admin/tool/certificate/` pass `core_paths()`.

## How to verify

1. `python -I revendor_tool_certificate.py --verify`: PASS.
2. Local: copy the plugin directory to XAMPP, `php ../admin/cli/upgrade.php --non-interactive` (cwd
   `C:\xampp\htdocs\moodle5\public`), purge caches. Site administration > Plugins > Plugins overview:
   tool_certificate 5.0.12, no "not supported" notice.
3. As admin: open a certificate template that has an image element (no TypeError); the "Issue certificates" dialog
   builds with "Days" preselected for the relative expiry.
4. Logged out: `/admin/tool/certificate/index.php?code=<a local code>` shows "Valid" and the table at once;
   `?code=NOTAVALIDCODE` shows "Not verified".
5. UAT (Nitin's go): the deploy above, then steps 3-4 on UAT.
