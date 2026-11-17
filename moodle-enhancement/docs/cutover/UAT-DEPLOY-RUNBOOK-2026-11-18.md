# UAT deploy runbook: Wednesday 18 November 2026, before the C-suite demo

**For:** Nitin, over his VPN, first thing Wednesday morning. The demo has moved up from Thursday 19 to Wednesday 18
November, so the "Wednesday checklist" in `docs/business/DEMO-SCRIPT-2026-11-19.md` section 6 now runs this morning.
**Prepared:** Tuesday 17 November, night, by Claude (Opus 5.5), from the repository and the record only. Nothing
was run against UAT. The go, every stop and any rollback are Nitin's call.
**Reviewed:** the same night, by a second agent, against the files it cites. Checked locally, no network: the
Option B path list (143 lines, 81 files), the git export of it (all 143 equal their blobs), the deploy tool's dry run
from that export (81 files, no `skip`/`drift`/`ABORT`), the reserved-word scan (PASS, 88 names) and the step 2.1 probe
(against local XAMPP). Corrected: the author-caps probe (it hid a skills AI grant), the tree-drift counts (three files
in Option A, not two), the hash-check counts, the Option B flag count, the rollback commands (no window with old
`version.php` files) and the rule that a rollback always uses the first deploy's backup.
**Ground rules:** Sentientia is not live. UAT holds test personas and test data only. No feature flag is changed, no
task is run by hand, no e-mail is sent, nothing is deleted on the box, and no credentials are typed into
academy2.airpay.ninja by Claude. Nitin signs the personas in himself.

## UAT facts used here

| What | Value | Source |
|---|---|---|
| Site | https://academy2.airpay.ninja, Moodle 5.3 since 2026-10-21 | `docs/operations/uat-upgrade-2026-10-21/README.md:1-7` |
| Code | `/var/www/html/moodle5.2` (old name kept), docroot `public/`, CLIs under `admin/cli/` | `tools/uat/deploy_to_uat.sh:34-36` |
| Runtime | PHP 8.3 under `php8.3-fpm`, `www-data` crontab every minute, 2 GB box, MySQL 8.4 database | `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md:24-27`; `tools/uat/mysql84_reserved_scan.py:5` |
| Access | VPN, then `ssh uat-tunnel` (TOTP, local port 2222) in its own terminal, then `ssh uat-lms` with passwordless sudo | `tools/uat/deploy_to_uat.sh:9-10` |
| Web | Apache listens on port 80 behind the load balancer; vhost logs are `academy2_access.log` / `academy2_error.log` | `docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md:99` |
| Database backup | None possible from SSH (the SSH user cannot read `config.php`). The 10-21 upgrade ran without one, by Nitin's decision. Only IT can take an RDS snapshot | `docs/cutover/UAT-DEPLOY-2026-10-07.md:101-102`; `docs/operations/uat-upgrade-2026-10-21/README.md:6-7` |

## How the deploy tool works (read tonight, not run)

- **Dry run by default.** Without `--yes` it prints the plan and exits before any network call (`deploy_to_uat.sh:19`,
  `:40`, `:101-108`).
- **What it copies.** The paths you give, or `git show`/`git diff --name-only` of `--commit`/`--range`
  (`:52-53`). A leading `moodle-enhancement/` is stripped, so both plugin trees map to `public/<rel>` (`:67`). Only
  `theme|local|blocks|mod|admin|lib/` paths are kept (`:38`, `:68`). A path that is not a file is skipped (`:69-70`),
  so **deleted files are never removed from UAT**.
- **Two trees.** If the same target comes from both trees with different bytes, it aborts with exit 3 unless
  `--prefer-me` or `--prefer-top` is given (`:71-92`). Identical copies: the first one listed is used (`:74`, `:94`).
- **Source bytes.** It copies from the checkout the script sits in (`REPO_ROOT`, `:37`, `:69`, `:115`). This checkout
  has `core.autocrlf=true`: tonight 81 of the 139 files of this push were CRLF in the working tree and differ from the
  git blobs. So this runbook deploys from a fresh export of the git blobs instead (step 0.3).
- **On the box.** Stages, tars and writes a sha256 manifest (`:112-119`); copies both to `/tmp` with one `scp` each
  (`:126-127`); tars the files that already exist to `/tmp/uat-predeploy-backup-<TS>.tgz` (`:133-134`); extracts,
  `chown www-data`, `chmod 644`, then `sha256sum -c` (`:135-139`).
- **Upgrade and purge.** `sudo -u www-data php admin/cli/upgrade.php --non-interactive`, full log in
  `/tmp/uat-upgrade-<TS>.log`, last 40 lines shown. A failed upgrade exits 4 and leaves caches unpurged (`:142-151`).
  Then `purge_caches.php` (`:153`).
- **No record of what is deployed.** The tool writes no marker of the commit it deployed (`:111-157`); the box only
  keeps the `/tmp` files, which a reboot clears. The baseline below is rebuilt from `PROJECT-STATE.md`.
- **Hash tools.** `kit-scratch/uatreh/cmp_uat_git.py` hashes raw git blobs, but only for the theme and two plugins
  (`cmp_uat_git.py:10-12`); step 0.4 below uses the same method (`:14-33`) for this push's list.
  `kit-scratch/uatreh/cmp_uat.py diff` compares two "sha256  path" lists (`cmp_uat.py:28-43`).
  `tools/uat/flag_snapshot.php` is read-only (`flag_snapshot.php:18-24`).
- **Do not use** `tools/uat/uat-purge.ps1` (wrong path `/var/www/sentientia/...`, `uat-purge.ps1:3`) or
  `deploy-to-uat.ps1` (PowerShell is disabled on this machine).

## Baseline: what UAT runs now

- **Last UAT deploy: 2026-11-11 12:46 IST**, 20 files (theme 2026110200 and the full-name user search in 8 plugins),
  backup `/tmp/uat-predeploy-backup-20261111-124641.tgz` (`PROJECT-STATE.md`, 2026-11-11 entry). Git HEAD at that
  time was **`95bd68689`**; the next commit, `68168126b`, is docs only.
- Since the 5.3 upgrade (package from `4f847d434`), the recorded deploys (10-29: 14 files; 10-30: 123 kn/mr/sw
  files from `27ce9fc35`; 11-11: 20 files) cover every deployable file changed up to `95bd68689`, **except the four
  Hindi block packs of `16f339065`** (10-23), which were held back with the kn/mr/sw packs and never sent, and one
  test file (`local/sentientia_users/tests/external/list_users_test.php`, `c36d82c5c`; tests are not loaded by the
  site). Re-checked in the review: `git diff 4f847d434..95bd68689` against the 10-29 comparison lists
  (`kit-scratch/uatreh/cmp_git.txt` / `cmp_remote.txt`: exactly 14 files plus the kn/mr/sw packs differed), the
  10-30 `--commit 27ce9fc35` list (all 123 kn/mr/sw files) and the 11-11 list (`kit-scratch/fullname/uat-deploy-files.txt`).
  PROJECT-STATE records no UAT deploy after 11-11.
- **So BASE = `95bd68689`.** Step 2 checks this on the box before anything is sent.
- **The two trees at BASE.** Three files of this push differed between `local/` and `moodle-enhancement/local/` at
  BASE: `local/sentientia_compliance_report/classes/compliance_engine.php` (Option A only) and
  `local/sentientia_courses/classes/task/course_{overdue,reminder}.php`. UAT runs the `moodle-enhancement/` copy (the
  5.3 package is built from that tree, `tools/packaging/build-standalone.sh:224-226`), and none of the three changed
  between `4f847d434` and BASE. At HEAD the trees are identical for every file of this push.

---

## 1. What goes to UAT

Option A (recommended, "everything developed so far"): `95bd68689..HEAD` after tonight's commit, plus the four
Hindi block packs. **106 files** (both plugin trees are identical for all of them tonight). Option B, if the
Track A commit has not landed or its CI is red: `95bd68689..151b86de7` plus the four packs, 81 files (drop the
rows marked T).

"Demo" says whether the change shows on the persona walk (DEMO-SCRIPT section 3).

| Component | Files | Version on UAT -> after | Why (commit) | Demo |
|---|---|---|---|---|
| theme_sentientia | 15 (1 test) | 2026110200 / 1.0.63-beta -> **2026111602 / 1.0.67-beta** | 2026111200 `8c9908614`: no empty drawer in course edit mode; the footer scrolls with the page. 2026111600 `a0279c602`: the footer clears the floating help button; the course content list starts collapsed at 992 px and below. 2026111601 `f27d3cb11`: dark-mode course title card, phone badge, banner and blocks tab. 2026111602 `f25402639`: the course-header countdown uses the viewer's own enrolment date (bound parameters). Also the CI-only `.stylelintrc.ci.json` (`5b1aebcda`, inert on the server). | **Visible.** Footer on every page no longer stays pinned (the 11-11 observation); at 1280 px it clears the help button; Priya's course-header countdown may show a different date. Phone and dark-mode changes are off the desktop walk. |
| local_sentientia_compliance_report (T) | 11 (3 tests) | 2026092401 / 1.0.5 -> **2026111702 / 1.0.8** | Tonight's commit: an "excluded from all tracking" learner (courseid 0) is exempt in every tracked course (`compliance_engine.php:87`); the export grant and the viewer scope find the org-admin role by shortname `administrator` instead of id 9; upgrade step 2026111702. | **Possibly visible** after the next hourly rebuild: `uat_author_airpay` (Sneha) shows exempt in every course of Joseph's and Meera's matrix. Rahul unchanged. |
| local_sentientia_authoring | 20 (3 tests, 1 test fixture) | 2026092500 / 0.2.2-alpha -> **2026111600 / 0.3.1-alpha** (T) | 2026111200 `079c32679`: ADR-034 course-document schema, validator and repository, two new empty tables, flag OFF. 2026111600 (T): install hook registers caps first; guarded back-fill. | Invisible (Authoring Studio unchanged). |
| local_sentientia_privacy | 12 (1 test) | 2026092500 / 1.0.3 -> **2026111300 / 1.1.0** | `bee62721a` ADR-035 Option A, behind two flags that stay OFF; with both OFF the provider "behaves exactly as 1.0.3 did" (`classes/privacy/provider.php:52`). First time on UAT. | Invisible. |
| local_sentientia_courses | 4 (1 test) | 2026110300 / 1.14.1 -> **2026111600 / 1.14.2** | `2c753b864`: reminder and overdue tasks count from the enrolment date. Both tasks stay disabled. | Invisible. |
| local_sentientia_notifications (T) | 4 (1 test) | 2026100900 / 1.5.1 -> **2026111600 / 1.5.2** | Tonight's commit: digest "compliance deadlines" lines read the right table, behind a new flag that stays OFF; streak read guarded. | Invisible. |
| local_sentientia_skillsai (T) | 5 (1 test) | 2026092500 / 0.1.3-alpha -> **2026111600 / 0.1.4-alpha** | Tonight's commit: install hook registers caps first; guarded back-fill. | Invisible. |
| local_sentientia_cart | 10 (3 tests) | 2026101500 / 1.2.1 -> **2026111200 / 1.2.2** | `e1b1976a9` A5: `order_paid`, `refund_processed` events. | Invisible (payments off). |
| local_sentientia_proctoring | 10 (2 tests) | 2026110300 / 1.0.5 -> **2026111200 / 1.0.6** | `e1b1976a9` A5: `session_flagged`, `review_submitted` events. | Invisible. |
| local_sentientia_platform | 4 (2 tests) | 2026101500 / 1.13.0 -> **2026111300 / 1.13.1** | `e1b1976a9` A5: the tenant audit trail includes actor-less rows. | Invisible (audit trail not on the walk). |
| local_sentientia_ai | 3 (1 test) | 2026092500 / 0.1.1-alpha -> **2026111300 / 0.1.2-alpha** | `ee0c90fa5`: price lookup strips a trailing date from the model name. | Invisible (mock mode). |
| local_sentientia_analytics | 2 | 2026092500 / 1.2.3-beta -> **2026111700 / 1.2.4-beta** | `494754284`: 0 certificates when `tool_certificate` is absent. UAT has it, so nothing changes there. | Invisible. |
| local_sentientia_gamification (T) | 1 test | no version change (2026092500) | Tonight's commit: test group attribute only. | Inert. |
| local_sentientia_pwa | 1 test | no version change (2026092900) | `e2983e3f6`: test only. | Inert. |
| blocks sentientia_cert_health, _compliance, _cron_health, _trainer | 4 lang/hi | no version change | `16f339065` (10-23) Hindi packs, never sent to UAT (the same bytes in both block trees). Drop them (empty `EXTRA`) if you want the strict minimum: then every file count below is 4 lower and step 2.3 shows no `MISSING`. | Only in Hindi, only where those blocks show. |

**Left on UAT, not removed:** `theme/sentientia/templates/flat_navigation.mustache` and `nav-drawer.mustache`,
deleted in git by `648dd42ba` (unreferenced since 10-08). The tool never deletes (see above); they are harmless.
Removing them is a separate decision.

**Note for the demo script:** section 6.A of `DEMO-SCRIPT-2026-11-19.md` was updated the same night and lists the
privacy erasure work as included (committed 11-17, flags OFF). A printed copy that says it is not included is the
older version.

---

## 2. What the upgrade will do to UAT data

Moodle runs only the steps above each component's current version. Checked tonight against the code:

| Step | Effect on UAT | Source |
|---|---|---|
| authoring 2026111200 | Creates two empty tables, `local_sentientia_scd` and `local_sentientia_scd_asset`. No row written. The MySQL 8.4 reserved-word scan of `95bd68689..151b86de7` passed (88 names, 0 hits; re-run in the review); the Track A changes add no table or column, and step 0.2 re-runs the scan on REF. | `local/sentientia_authoring/db/upgrade.php:141-196` |
| authoring 2026111600 (T) | **Grants** `:generate`, `:review`, `:managetemplates` (ALLOW, system context) to role `sentientiaauthor` **only if** that role has no system-context row for any of the three. On UAT the author caps were fixed and checked on 2026-09-08, so expected **no-op**. If the gap is there, Sneha's role gets the caps it should have. The probe (step 2.1) shows it: `authoring caps 0 of 3` means this step grants. | `db/upgrade.php:210-214`, `db/upgradelib.php:70-107` |
| skillsai 2026111600 (T) | **Grants** `:extract` and `:review` to the same role on the same rule, **judged on its own**: it looks only at these two caps, so it can grant even when the authoring step does not. Expected no-op; `skillsai caps 0 of 2` in the probe means it grants. | `local/sentientia_skillsai/db/upgrade.php:75-79`, `db/upgradelib.php:44-75` |
| compliance_report 2026111702 (T) | **Revokes** `:export` from role id 9 **only if** role 9 is neither shortname `administrator` nor manager-archetype **and** holds an ALLOW row for it. On UAT role 9 is `administrator`: **no-op** (the shortname alone is enough). Grants nothing. Separately, the report's on-screen tenant access and the export grant now find the org-admin role by shortname `administrator` instead of id 9 (`viewer_scope.php`, `permission.php:compliance_role_id()`): no change on UAT as long as that shortname is id 9 (probe line `role 'administrator' id`). | `classes/permission.php:175-198` |
| privacy, notifications, courses, cart, proctoring, platform, ai, analytics, theme | Version bump only; no upgrade step. | `db/upgrade.php` of each (no step above the UAT version) |

**For every upgraded component**, Moodle also re-reads capabilities, events, message providers and web services,
and resets its scheduled tasks from `db/tasks.php` (`moodle53/moodle/public/lib/upgradelib.php:635-646`). A task
an admin has customised is left alone; any other gets its default schedule and `disabled` value, and a recomputed
`nextruntime` (`public/lib/classes/task/manager.php:166-203`, `:456-470`). No new capability is added by this push
(`compliance_report/db/access.php` changes comments only). No `db/tasks.php`, `messages.php`, `services.php`,
`events.php`, `hooks.php`, `caches.php` or `settings.php` is in the push, so no task, message provider, web service,
observer, cache definition or admin setting is added or changed (`authoring/classes/author_role.php` also changes
comments only).

**Scheduled tasks whose next run time is recomputed:**
- `local_sentientia_compliance_report\task\refresh_snapshot`, every hour at :00. **Its next run applies the new
  exclusion rule** (Sneha exempt; existing enrolments stay; no e-mail leaves UAT).
- `local_sentientia_notifications\task\process_rules` (hourly) and `daily_digest` (08:30 Mon-Fri). The digest is
  unchanged while its new flag is OFF.
- `local_sentientia_courses\task\course_reminder` and `course_overdue`: default `disabled => 1`
  (`courses/db/tasks.php:31`, `:44`), so they **stay disabled** unless someone customised them on UAT (step 2
  shows it).
- `local_sentientia_analytics` refresh_predictive_cache (hourly), `local_sentientia_cart` issue_due_documents and
  `local_sentientia_platform` publish_cron_health (every 15 min), `local_sentientia_proctoring`
  purge_old_recordings and `local_sentientia_skillsai` rebuild_gap_feed (02:23 and 02:30).
- ai, authoring, privacy and the theme have no tasks.

Cron runs every minute on UAT but stops by itself while an upgrade is pending: "Moodle upgrade pending, cron
execution suspended" (`public/lib/classes/cron.php:69-72`). So no new task starts between the file copy and the end
of the upgrade. A cron run that had already started before the copy finishes its current task on code it had already
loaded; to keep that window empty, do not start step 3 between hh:00 and hh:06 (the hourly compliance rebuild starts
at :00, the analytics cache at :05, the cart and platform tasks every 15 minutes).

**Feature flags.** The registry is read from each plugin's `db/feature_flags.php` and cached
(`local/sentientia_platform/classes/feature_flags.php:589-615`). No upgrade step in this push writes a flag or a
config value. Nobody flips a flag today.

| Flag | Where | On UAT after the deploy |
|---|---|---|
| `sentientia.content.scd.enabled` | new, `authoring/db/feature_flags.php:106` | OFF (default false) |
| `sentientia.privacy.erasure_completeness.enabled` | new, `privacy/db/feature_flags.php:26` | OFF |
| `sentientia.privacy.completion_gap.enabled` | new, `privacy/db/feature_flags.php:39` | OFF |
| `sentientia.notifications.digest_deadlines.enabled` | new (T), `notifications/db/feature_flags.php:47` | OFF |
| `sentientia.dashboard.blocksdrawer.enabled` | read by `theme/sentientia/layout/dashboard.php:168-169` | stays **ON** (Nitin, 10-29) |
| `sentientia.dashboard.skillsrecs.enabled`, `sentientia.ai.gateway.enabled`, `sentientia.authoring.enabled`, `sentientia.authoring.publish.enabled` | read by changed files | stay **ON** (UAT global list of 10-23) |
| `ux.darkMode.enabled`, `sentientia.ai.live_api.enabled`, `sentientia.authoring.live_api`, `sentientia.authoring.tts`, `sentientia.pwa.push.reminders`, `sentientia.pwa.push.overdue`, `sentientia.notifications.smart_rules.enabled` | read by changed files | stay as they are (not in the 10-23 global ON list) |

The before/after snapshot (steps 2.2 and 4) is the proof: only the new lines (four in Option A, three in Option B:
no `digest_deadlines`), all OFF, and no existing line changed. The snapshot shows site-wide values and global
overrides only (`flag_snapshot.php:45-54`, `feature_flags::all()` with no tenant): it does not prove tenant overrides,
but no step of this push writes any override.

---

## 3. Morning sequence

Times are estimates on this machine (git is slow under the antivirus load) and the 2 GB UAT box.
Set these in one Git Bash window and keep it open:

```bash
cd "/d/Claude Local/airpay-ld-os"
BASE=95bd68689
REF=$(git rev-parse --short=9 HEAD)   # Option B: REF=151b86de7. A SHA, so a later commit cannot move it
W="/d/Claude Local/kit-scratch/uat-1118-$REF"; EXP="$W/export"; mkdir -p "$EXP"; echo "$REF" > "$W/REF"; echo "REF=$REF W=$W"
EXTRA="moodle-enhancement/blocks/sentientia_cert_health/lang/hi/block_sentientia_cert_health.php
moodle-enhancement/blocks/sentientia_compliance/lang/hi/block_sentientia_compliance.php
moodle-enhancement/blocks/sentientia_cron_health/lang/hi/block_sentientia_cron_health.php
moodle-enhancement/blocks/sentientia_trainer/lang/hi/block_sentientia_trainer.php"
```
One folder per REF: switching from Option A to B (or back) means a new `REF`, a new `W` and step 0 again, never a
re-export into the old folder. If step 0 ran tonight, in a new window in the morning: `cd` as above, `BASE=95bd68689`,
`REF=<the SHA in the folder name: ls -d "/d/Claude Local/kit-scratch/"uat-1118-*>`, then the `W=` line without its
`echo "$REF" >` part, and `EXTRA` is not needed again. Then re-check only the CI status of 0.1.

### Step 0. Local preparation, no network (15 min; can be done tonight right after the commit)

0.1 **What is on HEAD.**
```bash
git log --oneline -4
git status --porcelain -- local moodle-enhancement/local moodle-enhancement/blocks theme
for c in compliance_report authoring skillsai notifications; do printf '%s ' $c; git show $REF:moodle-enhancement/local/sentientia_$c/version.php | grep -E '^\$plugin->version'; done
for t in local moodle-enhancement/local; do git cat-file -e $REF:$t/sentientia_skillsai/db/upgradelib.php && echo "$t skillsai upgradelib in REF"; done
```
Expect: the Track A commit(s) on top of `151b86de7`; empty status (anything listed would not ride: the deploy sends
git's committed bytes only); versions 2026111702, 2026111600, 2026111600, 2026111600 (Option B: 2026092401,
2026111200, 2026092500, 2026100900). Option A only: both `skillsai upgradelib in REF` lines. That file is new
tonight (untracked until the commit) and skills AI's upgrade step `require_once`s it (`skillsai/db/upgrade.php:76`):
a commit without it makes the UAT upgrade fail half-way, so no line = do not use this REF. Also check in the browser
that GitHub Actions for that commit has both blocking groups (tenant_isolation, privacy_erasure) green. If not:
Option B.

0.2 **Path list and schema scan.**
```bash
{ git diff --name-only --diff-filter=AM $BASE..$REF | grep -E '^(moodle-enhancement/)?(theme|local|blocks|mod|admin|lib)/'; printf '%s\n' "$EXTRA"; } > "$W/paths.txt"
wc -l < "$W/paths.txt"
python -I tools/uat/mysql84_reserved_scan.py --base $BASE --head $REF | tail -3
```
Expect 193 lines (Option A; 143 for B, both trees listed; B checked in the review) and `RESULT: PASS` (usage:
`mysql84_reserved_scan.py:37-39`).

0.3 **Export the exact git bytes** (LF, as committed; avoids the CRLF working tree):
```bash
tr '\n' '\0' < "$W/paths.txt" | xargs -0 git -c core.autocrlf=false archive --format=tar $REF -- tools/uat/deploy_to_uat.sh | tar -x --ignore-zeros -C "$EXP"
```
Tested tonight on the 143-path list of `151b86de7` (by the author and again in the review): 144 files out (the 143
plus the deploy tool, LF by `.gitattributes`), every one equal to its blob, about 10 s.

0.4 **Hash lists from git** (REF for after, BASE for before):
```bash
cat > "$W/blobhash.py" <<'EOF'
import hashlib, subprocess, sys
paths_file, out_dir, ref, label = sys.argv[1:5]
paths = [l.strip() for l in open(paths_file, encoding='utf-8') if l.strip()]
spec = ''.join(ref + ':' + p + '\n' for p in paths).encode()
out = subprocess.run(['git', '-C', 'D:/Claude Local/airpay-ld-os', 'cat-file', '--batch'], input=spec,
                     capture_output=True, check=True).stdout
pos, blobs, rels, absent = 0, {}, set(), 0
for p in paths:
    rel = p[len('moodle-enhancement/'):] if p.startswith('moodle-enhancement/') else p
    rels.add(rel)
    nl = out.index(b'\n', pos); head = out[pos:nl].split()
    if head[-1] == b'missing':
        absent += 1; pos = nl + 1; continue
    size = int(head[2]); body = out[nl + 1:nl + 1 + size]; pos = nl + 2 + size
    h = hashlib.sha256(body).hexdigest()
    if rel in blobs and blobs[rel] != h:
        print('TREE DRIFT:', rel)
        if p.startswith('moodle-enhancement/'): blobs[rel] = h
    blobs.setdefault(rel, h)
open(out_dir + '/blobs-' + label + '.txt', 'w', newline='\n').writelines(blobs[r] + '  ' + r + '\n' for r in sorted(blobs))
open(out_dir + '/rels.txt', 'w', newline='\n').writelines(r + '\n' for r in sorted(rels))
print(len(rels), 'files;', len(blobs), 'hashed at', ref + ';', absent, 'paths added after', ref)
EOF
python -I "$W/blobhash.py" "$W/paths.txt" "$W" $REF head
python -I "$W/blobhash.py" "$W/paths.txt" "$W" $BASE base
```
Expect, from the REF run: `106 files; 106 hashed at <REF>; 0 paths added after <REF>` with no TREE DRIFT line
(Option B: 81 and 81). From the BASE run: `106 files; 73 hashed at 95bd68689; 63 paths added after 95bd68689`
(Option B: `81 files; 53 hashed ...; 53 paths added ...`) and **three** TREE DRIFT lines in Option A:
`local/sentientia_compliance_report/classes/compliance_engine.php` (reconciled tonight) and
`local/sentientia_courses/classes/task/course_{overdue,reminder}.php` (fixed by `2c753b864`); Option B prints only the
two courses lines. The trees differed at `95bd68689`; the script keeps the `moodle-enhancement/` copy, the one UAT
runs. That is expected.

0.5 **Dry run from the export** (no network: `deploy_to_uat.sh:105-108`):
```bash
bash "$EXP/tools/uat/deploy_to_uat.sh" --prefer-me $(cat "$W/paths.txt") 2>&1 | tee "$W/dryrun.txt"
awk -F/ '/^  (theme|local|blocks)\//{print $1"/"$2}' "$W/dryrun.txt" | sort | uniq -c
```
**Look for:** the header `── deploy plan → uat-lms:/var/www/html/moodle5.2/public ──`; the last lines
`upgrade: yes · purge: yes · files: 106` and `DRY RUN — nothing sent.`; **no** `skip`, `drift:` or `ABORT` line;
only the components of section 1 in the count (Option A: 13 local plugins, the theme and the 4 blocks; Option B:
81 files, 9 local plugins, without compliance_report, gamification, notifications and skillsai). (`drift:` lines go
to stderr; the `2>&1` keeps them in the file.) The review ran this dry run on the Option B export: 81 files, the
counts above, nothing on stderr.

### Step 1. VPN and tunnel (5 min)

VPN on. In a separate terminal: `ssh uat-tunnel` (TOTP); leave it open. Then:
```bash
ssh -o BatchMode=yes uat-lms 'hostname; sudo -n true && echo sudo-ok'
```
Expect the UAT host name and `sudo-ok`. The tunnel can close without warning: keep logs on this machine as you go
(the `tee` commands below).

### Step 2. Pre-flight on the box, read-only (10 min)

2.1 **Probe** (prints versions, ids and counts only):
```bash
cat > "$W/probe.php" <<'PHP'
<?php
define('CLI_SCRIPT', true);
require('/var/www/html/moodle5.2/config.php');
$sys = context_system::instance();
foreach (['theme_sentientia', 'local_sentientia_ai', 'local_sentientia_analytics', 'local_sentientia_authoring',
        'local_sentientia_cart', 'local_sentientia_compliance_report', 'local_sentientia_courses',
        'local_sentientia_notifications', 'local_sentientia_platform', 'local_sentientia_privacy',
        'local_sentientia_proctoring', 'local_sentientia_skillsai'] as $c) {
    printf("version %-36s %s\n", $c, get_config($c, 'version'));
}
$r9 = $DB->get_record('role', ['id' => 9], 'shortname, archetype');
printf("role9 %s/%s\n", $r9->shortname ?? '-', $r9->archetype ?? '-');
printf("role 'administrator' id %s\n", $DB->get_field('role', 'id', ['shortname' => 'administrator']) ?: '-');
printf("role9 export allow rows %d\n", $DB->count_records('role_capabilities', ['roleid' => 9, 'contextid' => $sys->id,
    'capability' => 'local/sentientia_compliance_report:export', 'permission' => CAP_ALLOW]));
// One line per back-fill: each upgrade step judges only its own caps.
$aid = (int) $DB->get_field('role', 'id', ['shortname' => 'sentientiaauthor']);
foreach (['authoring' => ['local/sentientia_authoring:generate', 'local/sentientia_authoring:review',
            'local/sentientia_authoring:managetemplates'],
        'skillsai' => ['local/sentientia_skillsai:extract', 'local/sentientia_skillsai:review']] as $plugin => $caps) {
    $n = 0;
    if ($aid) {
        [$in, $p] = $DB->get_in_or_equal($caps, SQL_PARAMS_NAMED);
        $n = $DB->count_records_select('role_capabilities', "roleid = :rid AND contextid = :cid AND capability $in",
            $p + ['rid' => $aid, 'cid' => $sys->id]);
    }
    printf("author role %s: system rows for the %s caps %d of %d\n", $aid ?: '-', $plugin, $n, count($caps));
}
foreach ($DB->get_records_select('task_scheduled', "component IN ('local_sentientia_courses',
        'local_sentientia_compliance_report', 'local_sentientia_notifications')", null, 'classname',
        'id, classname, disabled, customised, nextruntime') as $t) {
    printf("task %s disabled=%d customised=%d next=%s\n", $t->classname, $t->disabled, $t->customised,
        date('Y-m-d H:i', (int) $t->nextruntime));
}
printf("scd table %s\n", $DB->get_manager()->table_exists('local_sentientia_scd') ? 'present' : 'absent');
PHP
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/probe.php" | tee "$W/probe-before.txt"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; echo "is-pending exit $?"'
```
Expect versions: theme 2026110200; ai, analytics, authoring, privacy, skillsai 2026092500; cart, platform
2026101500; compliance_report 2026092401; courses, proctoring 2026110300; notifications 2026100900.
`role9 administrator/...` (the shortname is what matters; the local copy shows `administrator/manager`) and
`role 'administrator' id 9`. Author role lines `3 of 3` and `2 of 2`. A `0 of 3` or `0 of 2` means that back-fill
**will grant** those caps (ALLOW) to the Sentientia Author role in Option A: the intended state, but write it down and
decide before step 3. Any other non-zero count is an administrator's setting and nothing is granted. Both course
tasks `disabled=1 customised=0`. `scd table absent`. `is-pending exit 0` (exit 0 = nothing pending, 2 = pending:
`moodle53/moodle/admin/cli/upgrade.php:91`, `:163-171`). The review ran this probe against local XAMPP (already at the
Option A versions): every line printed, `role9 administrator/manager`, `role 'administrator' id 9`, `3 of 3`, `2 of 2`.

2.2 **Flag snapshot before** (as in `UAT-DEPLOY-2026-10-07.md:37-38`):
```bash
scp -o BatchMode=yes tools/uat/flag_snapshot.php uat-lms:/tmp/flag_snapshot.php
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /tmp/flag_snapshot.php --config=/var/www/html/moodle5.2/config.php' > "$W/flags-before.txt"
```

2.3 **Is UAT at BASE?** (content check, line endings ignored: earlier deploys sent CRLF working-tree bytes; the BASE
blobs of this list have no CR, checked in the review):
```bash
ssh -o BatchMode=yes uat-lms 'cd /var/www/html/moodle5.2/public && while IFS= read -r f; do [ -f "$f" ] && printf "%s  %s\n" "$(tr -d "\r" < "$f" | sha256sum | cut -c1-64)" "$f"; done' < "$W/rels.txt" > "$W/remote-before.txt"
python -I "D:/Claude Local/kit-scratch/uatreh/cmp_uat.py" diff "$W/blobs-base.txt" "$W/remote-before.txt"
```
Expect `local 73 remote 69 errors 0` and `differ/missing on UAT: 4`, the 4 lines being `MISSING` for the 4 block
Hindi packs (Option B: `local 53 remote 49`, the same 4). A `DIFF` is acceptable only on one of the three files that
differed between the trees at BASE (section "Baseline"); it would mean UAT holds the other tree's copy, which this
deploy replaces anyway. A remote count above 69 (49) means a file this push adds is already on UAT: find out why
before deploying.

### Step 3. Deploy (10 min)

Not between hh:00 and hh:06 (section 2, cron).
```bash
bash "$EXP/tools/uat/deploy_to_uat.sh" --yes --prefer-me $(cat "$W/paths.txt") 2>&1 | tee "$W/deploy.log"; echo "deploy exit ${PIPESTATUS[0]}"
TS=$(sed -n 's#^backup: /tmp/uat-predeploy-backup-\(.*\)\.tgz$#\1#p' "$W/deploy.log" | head -1); echo "TS=$TS"
[ -n "$TS" ] && [ ! -s "$W/TS" ] && echo "$TS" > "$W/TS"; echo "rollback TS=$(cat "$W/TS" 2>/dev/null)"   # the FIRST run's TS; see section 4
ssh -o BatchMode=yes uat-lms "grep -E '^-->' /tmp/uat-upgrade-$TS.log | tr '\n' ' '; echo; grep -c '^++ Success' /tmp/uat-upgrade-$TS.log; grep -iE 'error|exception|downgrade' /tmp/uat-upgrade-$TS.log | head -20"
```
Expect, in order: `backup: /tmp/uat-predeploy-backup-<TS>.tgz`; `── checksum verify ──` and one `<path>: OK` per
file (106), no `FAILED`; the upgrade tail with `++ Success` for exactly these 12 components: theme_sentientia and
local_sentientia_ai, analytics, authoring, cart, compliance_report, courses, notifications, platform, privacy,
proctoring, skillsai; then `Command line upgrade from 5.3 ... completed successfully.` and
`✅ deployed 106 file(s)`; `deploy exit 0`; `TS=` and `rollback TS=` both set (they are the same on a first run).
The last command reads the full log on the box (the tool shows only its last 40 lines): the 12 `-->` component
names, `12`, and no line after that. The upgrade itself should take 1 to 3 minutes (11-11:
under a second per component). (Option B: 81 files; 9 components, the same list without compliance_report,
notifications and skillsai; authoring goes to 2026111200.) Exit codes: 3 = tree drift abort (nothing sent), 4 =
upgrade failed, any other non-zero = an `scp`/`ssh` or checksum failure (section 4).

### Step 4. Verify against git (10 min)

```bash
ssh -o BatchMode=yes uat-lms 'cd /var/www/html/moodle5.2/public && xargs sha256sum' < "$W/rels.txt" > "$W/remote-after.txt"
python -I "D:/Claude Local/kit-scratch/uatreh/cmp_uat.py" diff "$W/blobs-head.txt" "$W/remote-after.txt"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/probe.php" | tee "$W/probe-after.txt"
diff "$W/probe-before.txt" "$W/probe-after.txt"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /tmp/flag_snapshot.php --config=/var/www/html/moodle5.2/config.php' > "$W/flags-after.txt"
diff "$W/flags-before.txt" "$W/flags-after.txt"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; echo "is-pending exit $?"'
```
Expect:
- `local 106 remote 106 errors 0` and `differ/missing on UAT: 0` (raw bytes equal the git blobs; Option B: 81). The
  review simulated this comparison on the Option B export: 81/81, 0 differ.
- Probe diff: the 12 version lines at the section 1 values (Option B: 9; authoring 2026111200; compliance_report,
  notifications, skillsai unchanged); both author-role lines unchanged (or `0 of 3` -> `3 of 3` / `0 of 2` -> `2 of 2`
  where step 2.1 showed the gap: Option A only); `role 'administrator' id` and `role9 export allow rows` unchanged;
  both course tasks still `disabled=1 customised=0`; `next=` times may move; `scd table present`.
- Flag diff: exactly four `>` lines (Option B: three, no `digest_deadlines`), the new keys, each
  `default=off resolved=off overrides=-`; no `<` line.
- `is-pending exit 0`: the upgrade finished, cron runs again.

### Step 5. Server smoke (5 min)

```bash
ssh -o BatchMode=yes uat-lms 'for p in /login/index.php /my/ /local/sentientia_catalog/index.php /local/sentientia_compliance_report/index.php; do printf "%s " "$p"; curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" -H "Host: academy2.airpay.ninja" -H "X-Forwarded-Proto: https" "http://127.0.0.1$p"; done'
ssh -o BatchMode=yes uat-lms 'sudo tail -n 20 /var/log/apache2/academy2_error.log'
```
Expect login `200`; the others `200` or `303` (redirect to login); never `500`. The first request after the purge
can take up to a minute while the theme CSS is rebuilt; run the loop twice. The error log should show nothing new
since the deploy time (if the file is not there: `sudo ls /var/log/apache2/`).

Then **VPN off** for the browser (every logged-in UAT check on record was done with the VPN off).

### Step 6. Browser smoke (10 min; Nitin signs in himself)

1. https://academy2.airpay.ninja/login/index.php: loads in the Airpay brand.
2. Priya (`uat_learner_airpay`): `/my/`; open Airpay Product Suite Fundamentals (course header, countdown, footer
   scrolls with the page); F12 console has no red errors.
3. `/local/sentientia_catalog/index.php`: cards render.
4. Meera (`uat_ldadmin_airpay`): `/local/sentientia_compliance_report/index.php`: the matrix renders, Rahul overdue.
5. If any PHP page still shows yesterday's behaviour, OPcache may hold old code: VPN back on (tunnel still open, or
   re-open it), `ssh uat-lms 'sudo systemctl reload php8.3-fpm'` (as in `UAT-UPGRADE-5.3-2026-10-19.md:216`), VPN off
   again for the browser. Earlier deploys took effect without it.

### Step 7. Persona walk (60 min, plus 15 for deck screenshots)

`DEMO-SCRIPT-2026-11-19.md` section 6.C (data per persona) and 6.D (walk 3.1 to 3.8 in order, with a timer). Keep
its "do not click" items (the AML & KYC quiz, approving the request, checkout): each changes Thursday's, now
today's, data. After the next hh:00 rebuild, look at Joseph's matrix again for Sneha (section 1). Screenshots to
`docs/visual-evidence/2026-11-18/` with a README (6.E).

### Timeline and latest safe start

| Block | Minutes |
|---|---|
| Step 0 (no network; skip if done tonight) | 15 |
| Steps 1 to 5 (VPN on) | 40 |
| Steps 6 and 7 (VPN off) | 70 (85 with screenshots) |
| Room set-up, persona windows signed in (DEMO-SCRIPT 6.F) | 15 |
| Buffer for one rollback and re-check | 30 |
| **Total** | **about 2 h 50 to 3 h 05** |

**Latest safe start: demo time minus 3 h 15** (minus 3 h 00 if step 0 was done tonight). Example: demo at 10:30
-> start by 07:15.
**Go/no-go at demo minus 2 h 00:** if step 4 is not green by then, roll back the code (section 4) and walk the
11-11 code. **No server change after demo minus 45 min.**

---

## 4. Stop rules and rollback

Every decision here is Nitin's. Claude can read logs and propose; it does not type credentials on UAT or flip
flags.

| When | Stop rule | Then |
|---|---|---|
| Step 0 | Status not empty, CI red, scan FAIL, any `skip`/`drift`/`ABORT` line, a file count other than 106 (81), or a path outside section 1 | Do not connect. Fix locally or use Option B. |
| Step 2 | A version differs from the expected list, `is-pending exit 2`, a `DIFF` on any file other than the three that differed between the trees at BASE, a remote count above 69 (49), or `role 'administrator' id` not 9 | UAT is not at BASE (or, for the last one, the compliance report's tenant access would change). Do not deploy; find out what changed. Demo on the current UAT code. |
| Step 2 | An author-role line `0 of 3` or `0 of 2` | Not a stop by itself: Option A's back-fill will grant those caps to the Sentientia Author role (the state the role should have). Nitin decides: go with A, or deploy B (no back-fill). |
| Step 3, checksum | Any `FAILED` (the remote block stops before the upgrade, `deploy_to_uat.sh:131-139`) | The files are already extracted and their new `version.php` files are in place, so the upgrade is pending and cron is suspended. Re-run step 3 once. If it fails again: full rollback (A) **with the first TS**, in its "no upgrade ran" form (old `version.php` files restored). |
| Step 3, upgrade | Exit 4, `UPGRADE FAILED` (`:146-150`) | Copy the log first: `ssh uat-lms "tail -n 80 /tmp/uat-upgrade-$TS.log"`. A lock or connection error: re-run once with `ssh uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --non-interactive'`, then `ssh uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/purge_caches.php'`. A failing step in one plugin: stop. Cron stays suspended until the upgrade finishes (`cron.php:69-72`). Decide between a fix with Claude (only if more than 2 h remain) and the deck plus screenshots (DEMO-SCRIPT section 5). |
| Tunnel drops mid-deploy | `ssh`/`scp` errors, no `✅` line | Re-open the tunnel. **First** check that no upgrade is still running from the dropped session: `ssh uat-lms "pgrep -af '[a]dmin/cli/upgrade.php'; ls -lt /tmp/uat-predeploy-backup-*.tgz /tmp/uat-upgrade-*.log"` (a `pgrep` line means it is still running; the `[a]` keeps the command from finding itself). If it is running, wait for it to end (`ssh uat-lms "tail -n 5 /tmp/uat-upgrade-$TS.log"`); never start a second one. Then the `--is-pending` command of step 4: exit 2 = files in place, upgrade not run (or killed): run the upgrade command above, then purge. Exit 0 and the probe shows the new versions: purge only. Exit 0 and the old versions: nothing was extracted; re-run step 3. If the `backup:` line never reached the screen, TS is the newest `/tmp/uat-predeploy-backup-*.tgz` of this morning (the `ls` above). Then step 4. |
| Step 4 | Any `DIFF`/`MISSING` after deploy, an existing flag line changed, a course task enabled, `is-pending exit 2` | Stop; do not change flags or tasks by hand. Re-deploy the differing paths only, or roll back. |
| Step 6 or 7 | A page breaks | If the page belongs to a component in section 1: VPN back on and the tunnel up, component rollback (B) (it purges), VPN off, re-check in the browser. If not: not caused by this deploy; use the DEMO-SCRIPT section 5 fallback for that stop. |

**What cannot be rolled back:** the database. The two new authoring tables, any capability granted by the
back-fills, and the recorded plugin versions stay. They are additive, and the older code runs on them (it does not
read the new tables). The compliance snapshot rows the hourly rebuild rewrote (Sneha exempt) stay until the old
code's next hourly run rewrites them back. For the same reason, **never restore an older `version.php`** once the
upgrade has run: Moodle refuses a downgrade (`public/lib/upgradelib.php:800-801`), `upgrade.php` stops, and cron stays
suspended while the versions disagree (`public/lib/moodlelib.php:7650-7680`, `cron.php:69-72`). Code rollbacks below
keep the new `version.php` files.

**Always the first TS.** Every run of the deploy tool takes a new backup of the files as they are at that moment. A
second run (a re-try of step 3, a re-deploy of differing paths in step 4) backs up the first run's NEW files. Roll
back only from the first run's backup: `TS=$(cat "$W/TS")` (step 3 writes it once and never overwrites it).

**(A) Full code rollback**, from the box backup (about 5 min; `/tmp` does not survive a reboot):
```bash
TS=$(cat "$W/TS"); echo "rolling back to the backup of $TS"
ssh -o BatchMode=yes uat-lms "set -e; cd /var/www/html/moodle5.2/public
  sudo tar -xzf /tmp/uat-predeploy-backup-$TS.tgz --exclude='*/version.php'
  if sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; then echo 'is-pending exit 0'; else echo \"is-pending exit \$? - STOP, caches not purged\"; exit 1; fi
  sudo -u www-data php /var/www/html/moodle5.2/admin/cli/purge_caches.php"
```
Files that existed before get their old content (`deploy_to_uat.sh:133-134` backed up only those), except the
`version.php` files, which keep the new versions the database now records, so no old `version.php` is on disk even
for a moment. `is-pending exit 0` must print before the purge. Files that are new in this push (new classes, tests,
the privacy flag file, the four block packs) stay; the old code does not load them (the privacy flags stay
registered, OFF). If the upgrade did **not** run (checksum failure in step 3), remove `--exclude='*/version.php'`
from the command: then the old `version.php` files are correct and must come back. (The `--exclude` form was tested
with GNU tar 1.35 on this machine.)

**(B) One component**, e.g. the theme (`COMP=local/sentientia_privacy` for a plugin):
```bash
TS=$(cat "$W/TS"); COMP=theme/sentientia
ssh -o BatchMode=yes uat-lms "set -e; cd /var/www/html/moodle5.2/public
  sudo tar -xzf /tmp/uat-predeploy-backup-$TS.tgz --wildcards '$COMP/*' --exclude='$COMP/version.php'
  sudo -u www-data php /var/www/html/moodle5.2/admin/cli/purge_caches.php"
```
Same rule: the new `version.php` stays. Not needed for gamification and pwa (test files only; the site never loads
them) or the block packs (new on UAT, so not in the backup: tar stops with "Not found in archive"; they only add
Hindi strings).

**(C) If `/tmp` was cleared:** redeploy the BASE files from git, without `version.php` and without an upgrade:
```bash
RB="$W/rollback"; mkdir -p "$RB"
grep -v '/version\.php$' "$W/paths.txt" | while IFS= read -r p; do git cat-file -e "$BASE:$p" 2>/dev/null && echo "$p"; done > "$W/rb-paths.txt"
tr '\n' '\0' < "$W/rb-paths.txt" | xargs -0 -r git -c core.autocrlf=false archive --format=tar $BASE -- | tar -x --ignore-zeros -C "$RB"
git -c core.autocrlf=false archive --format=tar HEAD -- tools/uat/deploy_to_uat.sh | tar -x -C "$RB"
bash "$RB/tools/uat/deploy_to_uat.sh" --prefer-me --no-upgrade $(cat "$W/rb-paths.txt")         # dry run first
bash "$RB/tools/uat/deploy_to_uat.sh" --yes --prefer-me --no-upgrade $(cat "$W/rb-paths.txt")
```
Filter `rb-paths.txt` with `grep` for a single component (`-r` stops `xargs` from exporting the whole repository
when the list is empty). Expect three `drift:` lines in Option A, two in Option B (the files that differ between the
trees at BASE, section "Baseline"; `--prefer-me` takes the moodle-enhancement copy, the one UAT ran,
`deploy_to_uat.sh:75-86`). `--no-upgrade` skips `upgrade.php`, the purge still runs (`deploy_to_uat.sh:142`, `:153`).
This deploy takes a backup of its own; it does not change `$W/TS`.

---

## 5. Risks of this push

| Risk | Mitigation |
|---|---|
| UAT runs Moodle 5.3; the theme changes were checked on local 5.1 (screenshots and checks of 11-12 and 11-16), PHPUnit runs on 5.2 in CI; the theme countdown test (11-16) is not recorded as run | The course page and dashboard are in step 6, before the full walk. The theme has no DB step, so rollback (B) for `theme/sentientia` is clean and takes 5 minutes. |
| Theme changes sit on the demo path (footer on every page, course header) | Walk 3.1 and 3.3 first. The footer now scrolls with the content: expected, not a defect. |
| First deploy of Option A (privacy 1.1.0) | Both flags stay OFF and the provider then behaves as 1.0.3 (`provider.php:52`); CI's blocking `privacy_erasure` group passed on `bee62721a`. Do not open, approve or run a data request on UAT today. Rollback (B) with `COMP=local/sentientia_privacy`, no DB step. |
| Tonight's fixes (T) land hours before the push; their new tests run only locally and in CI | Deploy Option A only if the commit's CI is green; otherwise Option B. The upgrade steps they add are guarded and expected to be no-ops on UAT (step 2 shows it). |
| Two upgrade steps (authoring, skills AI) can GRANT capabilities to the Sentientia Author role, each judged on its own caps, and one (compliance report) can REVOKE `:export` from role 9 | Step 2.1 prints both author-role counts and role 9 before anything is sent: `3 of 3`, `2 of 2` and `administrator` mean all three are no-ops. Option B carries none of them. |
| The hourly compliance rebuild applies the new exclusion rule after the deploy | Expected: Sneha exempt everywhere. Read numbers off the screen; do not quote earlier figures. |
| The checkout is CRLF; deploying from it would send bytes that differ from git | Deploy from the git export (step 0.3); step 4 proves byte equality. |
| UAT's real state is rebuilt from the record (the tool keeps no marker) | Step 2 checks versions and content against BASE before sending anything. |
| No database backup | All steps are additive and guarded (section 2). An RDS snapshot needs IT and cannot be had this morning. |
| 2 GB box: first page after the purge is slow | Warm it with the step 5 loop before the walk. |
| PHP-FPM OPcache keeps old code | Earlier deploys took effect at once; reload `php8.3-fpm` only if a page shows old behaviour (step 6.5). |
| The tunnel closes without warning | `tee` every output locally; the "tunnel drops" row in section 4. |
| Cron runs every minute | It suspends itself during the upgrade (`cron.php:69-72`). The two course tasks stay disabled (step 4 checks). No e-mail leaves UAT. |
| Two deleted theme templates remain on the box | Unreferenced; leave them. |
