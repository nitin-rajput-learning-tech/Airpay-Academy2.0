# Phase A.4b - Per-file conflict map (5.1.3+ to 5.2)

ADR-011 Phase A.4b deliverable. Built from `5.2-brief-summary.txt` +
`5.2-theme-boost-full.diff` + per-component diffs at
`D:\Claude Local\moodle-5.2-diffs\`.

This document is the input to Phase B.3 work breakdown — each row
becomes a Phase B sub-task.

---

## Headline numbers

```
Total file-level differences:   4,309
  Modified (both sides):        3,760
  Only in 5.1 (our additions):    170
  Only in 5.2 (their additions):  379

By top-level directory:
  lib/         1,956 modified (mostly vendor/aws-sdk + lib/classes)
  mod/           457 modified (activity modules)
  course/        211 modified (course rendering + formats)
  admin/         181 modified
  question/      156 modified
  blocks/        107 modified
  theme/          64 modified (our PRIMARY merge surface)
  grade/          58 modified
  user/           43 modified
  enrol/          41 modified
  customfield/    39 modified
  reportbuilder/  34 modified
  filter/         33 modified
  ai/             24 modified (NEW subsystem in 5.2)
```

---

## Strategic finding: most of lib/ is vendor

The 1,956 file changes in `lib/` are misleading. Spot-check shows the
vast majority are in `lib/aws-sdk/`, which is a vendored copy of the
AWS SDK PHP library that 5.2 refreshed wholesale. We don't touch
`lib/aws-sdk/` — Moodle 5.2 just bumped the vendor library version.

Real lib/ conflict surface is in:
- `lib/classes/output/*` (mustache helpers, renderer base classes)
- `lib/classes/event/*` (new event classes for restricted modules)
- `lib/amd/src/deprecated.js` (matches our P0 #8 borrow shape)
- `lib/amd/src/network_events.js` (new in 5.2)
- `lib/outputrenderers.php` (core_renderer base)
- `lib/moodlelib.php`, `lib/weblib.php`, `lib/sessionlib.php`

We'll diff those individually in Phase B.4 (lib + admin merge session).
Estimate: ~80-150 real conflicts in lib/, not 1956.

---

## Theme conflict surface (the heavy one)

`theme/boost/` has 48 modified files. We fork from boost via
`theme/airpayux/`. Per-file resolution strategy:

### A. boost/classes/output/core_renderer.php

**Risk:** HIGH (we extend it via theme_airpayux/classes/output/core_renderer.php; 2129 lines)
**Strategy:** RE-IMPLEMENT
**Plan:**
1. Diff boost 5.1 → boost 5.2 to identify new methods + changed signatures
2. Re-apply our extension methods on top of new boost 5.2 base
3. Our 7 traits in `classes/output/traits/` are reusable as-is
4. Estimate: 4-6h focused session

### B. boost/lib.php

**Risk:** MEDIUM
**Strategy:** CHERRY-PICK
**Plan:**
1. We have our own `theme_airpayux/lib.php` with custom-changes-post
   processor + a `before_standard_top_of_body_html` hook for P0 #10
2. Diff boost/lib.php 5.1 → 5.2; absorb signature changes; keep ours
3. Estimate: 30 min

### C. boost/layout/columns2.php

**Risk:** HIGH (heavily Sentientia)
**Strategy:** RE-IMPLEMENT on new 5.2 base
**Plan:**
1. Take new 5.2 boost layout as starting point
2. Re-apply Sentientia branding + drawer modifications
3. Estimate: 1h

### D. boost/layout/drawers.php

**Risk:** HIGH (Sentientia drawer redesign)
**Strategy:** RE-IMPLEMENT on new 5.2 base
**Plan:** Same shape as columns2.php. 1h.

### E. boost/layout/login.php

**Risk:** HIGH (Sentientia login redesign)
**Strategy:** RE-IMPLEMENT
**Plan:**
1. Take new 5.2 boost layout
2. Re-apply Sentientia OTP-login flow + slider hero
3. Test against both /login/index.php and /login/forgot_password.php
4. Estimate: 1.5h

### F. boost/templates/columns2.mustache + drawer.mustache + drawers.mustache

**Risk:** HIGH (Sentientia overrides at theme_airpayux/templates/)
**Strategy:** RE-IMPLEMENT on new 5.2 markup
**Plan:**
1. Diff each upstream template 5.1 → 5.2
2. Apply our Sentientia structure (sidebar, drawer chrome, etc.)
3. Estimate: 45 min per template = 2-3h total

### G. boost/templates/courseindexdrawercontrols.mustache + drawer.mustache

**Risk:** MEDIUM
**Strategy:** CHERRY-PICK
**Plan:** Small templates. Cherry-pick upstream changes onto ours.
30 min total.

### H. boost/templates/footer.mustache

**Risk:** MEDIUM (we have a Sentientia footer at theme_airpayux/templates/footer.mustache)
**Strategy:** TAKE OURS (override unchanged)
**Plan:** Confirm our footer template is still referenced; no merge action.
15 min check.

### I. boost/templates/login.mustache

**Risk:** HIGH (Sentientia login form at theme_airpayux/templates/core/loginform.mustache)
**Strategy:** TAKE OURS but graft new 5.2 a11y additions
**Plan:**
1. Compare upstream 5.1 → 5.2 for new ARIA attributes
2. Cherry-pick onto our existing template
3. P0 #5 OAuth2 borrow already covers part of this
4. Estimate: 30 min

### J. boost/templates/navbar.mustache

**Risk:** HIGH (Sentientia navbar at theme_airpayux/templates/navbar.mustache)
**Strategy:** RE-IMPLEMENT on new 5.2 markup
**Plan:**
1. Take upstream 5.2 navbar markup as base
2. Re-apply Sentientia branding, user menu, language switcher
3. Estimate: 1.5h

### K. boost/templates/secure.mustache

**Risk:** LOW (secure layout rarely customised)
**Strategy:** TAKE THEIRS
**Plan:** Diff and merge any small upstream changes. 15 min.

### L. boost/scss/moodle/*.scss (18 files)

**Risk:** MEDIUM (compiled CSS source affects all renderings)
**Strategy:** REBASE — let boost 5.2 SCSS land as new baseline,
re-apply our overrides
**Plan:**
1. Our `scss/moodle/custom_changes.scss` already overrides boost SCSS
2. Take new 5.2 boost SCSS as-is
3. Re-test our overrides still apply correctly
4. SCSS variables in 5.2's `variables.scss` may have changed names —
   update our `_tokens.scss` references
5. Estimate: 3-4h for the full SCSS sweep

### M. boost/amd/src/bs4-compat.js + drawers.js

**Risk:** LOW (we don't override these in airpayux)
**Strategy:** TAKE THEIRS
**Plan:** No merge action; the new boost 5.2 AMDs ship to xampp via the
plugin install. 0 min.

### N. boost/tests/behat/*

**Risk:** NIL (Behat tests, doesn't affect runtime)
**Strategy:** TAKE THEIRS
**Plan:** No merge action.

### O. boost/version.php

**Risk:** NIL
**Strategy:** TAKE THEIRS (upgrade.php handles it)

### P. boost/UPGRADING.md, boost/style/moodle.css, boost/thirdpartylibs.xml

**Risk:** NIL — generated artifacts or docs
**Strategy:** TAKE THEIRS

### Theme/classic + theme root files (font.php, image.php, jquery.php, yui_image.php)

**Risk:** NIL (we don't fork from classic; the root files are dispatchers we don't touch)
**Strategy:** TAKE THEIRS

---

## Theme/airpayux conflict-map summary

| Surface | Files | Strategy | Est hrs |
|---------|-------|----------|---------|
| core_renderer.php | 1 | RE-IMPLEMENT | 6 |
| lib.php | 1 | CHERRY-PICK | 0.5 |
| layout/columns2.php | 1 | RE-IMPLEMENT | 1 |
| layout/drawers.php | 1 | RE-IMPLEMENT | 1 |
| layout/login.php | 1 | RE-IMPLEMENT | 1.5 |
| layout/course.php (ours only) | 1 | TAKE OURS | 0 |
| layout/dashboard.php (ours only) | 1 | TAKE OURS | 0 |
| layout/frontpage.php (ours only) | 1 | TAKE OURS | 0 |
| templates/columns2.mustache | 1 | RE-IMPLEMENT | 0.75 |
| templates/drawer.mustache | 1 | RE-IMPLEMENT | 0.75 |
| templates/drawers.mustache | 1 | RE-IMPLEMENT | 0.75 |
| templates/footer.mustache | 1 | TAKE OURS | 0.25 |
| templates/core/loginform.mustache | 1 | CHERRY-PICK | 0.5 |
| templates/navbar.mustache | 1 | RE-IMPLEMENT | 1.5 |
| templates/core_form/ (52 files) | 52 | RE-IMPLEMENT | 15-20 |
| templates/components/ (11 ours only) | 11 | TAKE OURS | 0 |
| templates/core_courseformat/ (2) | 2 | CHERRY-PICK | 0.5 |
| templates/block_myoverview/ (1, P0 #14) | 1 | DELETE | 0.1 |
| scss/moodle/partials/* (27 ours) | 27 | REBASE | 4 |
| amd/src/*.js (18) | 18 | DELETE 4, KEEP 14 | 1 |
| classes/output/traits/* (7) | 7 | REUSE | 0 |
| pix/* + lang/* | 211+6 | TAKE OURS | 0 |
| **Total** | | | **~36h** |

This matches the ADR-011 Option B estimate (30-40h). Confirmed feasible.

---

## Course / course_format conflict surface (211 files modified upstream)

### Critical files

**course/format/classes/output/local/content/cm/restricted.php (new)**
- 5.2 ships P0 #4 (availability conditions) natively
- Action: DELETE our P0 #4 borrow (scss/partials/_surface-profile.scss
  has the chip; remove on merge)

**course/format/templates/local/content/cm/restricted.mustache (new)**
- Matching template for above

**course/templates/activity_dates.mustache + completion_status.mustache (new)**
- 5.2 P0 #2 borrow (inline activity-info) - natively in templates
- Action: DELETE our P0 #2 inline completion chip code

**course/classes/route/controller/* (new namespace)**
- 5.2 introduces new routing API
- Action: REVIEW - may need to update our airpay_courses cron paths

**course/classes/exception/* (new namespace)**
- 5.2 adds typed exceptions
- Action: REVIEW - existing $exception->message catches still work
  but typed catches would be cleaner

**course/classes/task/reset_course.php (new)**
- 5.2 adds async course reset
- Action: ABSORB - matches the airpay_recompletion completion-reset
  workflow we have. Could replace our cron.

### Estimate

Course/ subsystem: 4-6h merge time. Most of the 211 file count is
behat tests + small touch-ups.

---

## blocks/myoverview conflict surface (P0 #14 superseded)

**blocks/myoverview/tests/behat/block_myoverview_sorting.feature (new)**
- 5.2 has native behat coverage for the new sort options
- Action: Delete our `theme/airpayux/templates/block_myoverview/nav-sort-selector.mustache`
  override (P0 #14 borrow)

Block_myoverview total: 30 min on merge.

---

## backup conflict surface (P0 #11 superseded)

**backup/tests/default_backup_filename_test.php (new)**
**backup/util/dbops/tests/backup_plan_dbops_test.php (new)**
- 5.2 has native filename template support + tests
- Action: Migrate `local_airpay_core\backup_filename` callers to use
  the new core API; delete the helper class

backup/ total: 1-2h migration.

---

## ai/ subsystem (NEW in 5.2)

24 file additions under public/ai/:
- `ai/classes/helper.php` (new)
- `ai/placement/UPGRADING.md` (new)
- `ai/provider/awsbedrock/` (new)
- `ai/provider/gemini/` (new)

Action:
- This is purely additive - no conflict
- After merge, consider replacing parts of our `local_airpay_assistant`
  with the upstream provider abstraction
- Not on critical path for the merge

---

## Removed in 5.2 (must clean up from our fork)

39 files only in 5.1 - audit before delete:

```
Our additions to KEEP (do NOT delete from fork):
  airpay-audit-loginas.php
  admin/tool/certificate (our plugin)
  blocks/airpay_cert_health
  blocks/airpay_compliance
  blocks/airpay_cron_health
  blocks/airpay_trainer
  blocks/learnerscript, learnerscript_lib_PATCHED.php
  blocks/reportdashboard, reportdashboard_dashboard_PATCHED.php
  blocks/reporttiles
  .htaccess, .rnd

Moodle removals - we should delete on merge:
  admin/moodlenet_oauth2_callback.php
  admin/settings/moodlenet.php
  admin/tests/behat/moodlenet_outbound.feature
  admin/tool/moodlenet (subsystem retired)
  admin/tool/tcpdffonts (subsystem retired)
  auth/ldap/cli (subsystem reorganised)
  availability/amd, availability/renderer.php
  badges/endorsement_json.php, badges/lib
  blocks/activity_modules
  (full list in 5.2-brief-summary.txt - search 'Only in /c/xampp')
```

Phase B exit checklist must include the "delete on merge" set.

---

## Phase B work breakdown

Based on this map, Phase B (the merge proper) breaks down as:

| Session | Surface | Files | Est hrs |
|---------|---------|-------|---------|
| B.1 | PHP 8.3 install + upgrade.php smoke + PHPUnit re-init | - | 2 |
| B.2 | Pull upstream + first merge commit + conflict triage | 4,309 | 2 |
| B.3.a | Theme: core_renderer + traits | 1 + 7 | 6 |
| B.3.b | Theme: layouts | 3 (+3 ours) | 4 |
| B.3.c | Theme: top templates (columns2, drawer, footer, navbar) | 7 | 5 |
| B.3.d | Theme: core_form widgets (heaviest) | 52 | 18 |
| B.3.e | Theme: SCSS rebase | 27 | 4 |
| B.3.f | Theme: AMD cleanup (delete 4 borrow shims) | 18 | 1 |
| B.4 | lib/ + admin/ real conflicts (skip vendor) | ~150 | 8 |
| B.5 | course/ + course_format + new routing API | 211 | 6 |
| B.6 | blocks/ (myoverview, others) | 107 | 3 |
| B.7 | grade/ + report/ + reportbuilder/ | ~120 | 4 |
| B.8 | enrol/ + user/ + auth/ | ~120 | 3 |
| B.9 | mod/ activity modules (filter for ones we use) | 457 | 6 |
| B.10 | Backup + filter + customfield + AMD borrow cleanup | ~90 | 3 |
| B.11 | Delete removed-in-5.2 (moodlenet, tcpdffonts, etc.) | ~15 | 1 |
| B.12 | Full Goal A.y re-run + smoke test marathon | - | 4 |
| **TOTAL** | | | **80 hrs (~10 days)** |

This is on the higher end of the Option B estimate (30-40h was theme
only; total merge with lib/course/etc. is closer to 80h). Phase B is
~10 working days end-to-end.

---

## Exit criteria for Phase A.4b

- [x] Brief summary generated (4309 lines, 781 KB)
- [x] theme/boost full diff generated (0.24 MB)
- [x] blocks/myoverview full diff generated (14 KB)
- [x] backup full diff generated (22 KB)
- [ ] lib/ full diff generated (heavy diff still running)
- [ ] course/ full diff generated
- [ ] admin/ full diff generated
- [x] Theme conflict map produced (this doc)
- [x] Phase B work breakdown produced (this doc)
- [x] Per-file resolution strategy assigned

Remaining items (lib/course/admin diffs) are in-flight; their headline
counts already captured from the brief summary. Detailed inspection
of those diffs happens in Phase B.4-B.5.

---

## Decisions for Nitin's review

1. **Phase B duration**: 80 hours / ~10 working days. Confirms ADR-011
   §"Decision log" Option B is the right call - faster paths would
   compromise visual parity.
2. **B.3.d (core_form widgets) is the longest single session (~18h)** -
   could break into 2-3 half-sessions if context budget tight.
3. **Schedule trigger**: Phase B starts as soon as PHP 8.3 lands on
   local XAMPP. The 80-hour Phase B fits ~2 working weeks.

---

## Related

- ADR-011 - Moodle 5.2 wholesale upgrade staging
- PHASE-A1-INVENTORY-2026-05-23.md - Pre-merge file/plugin counts
- PHASE-A3-PHP83-LINT-REPORT.md - PHP 8.3 readiness (clean baseline)
- PHASE-A4-THEME-OVERRIDE-INVENTORY.md - Our side of the conflict
- PHASE-A5-TEST-INVENTORY.md - 91 test files / 620 methods to validate
