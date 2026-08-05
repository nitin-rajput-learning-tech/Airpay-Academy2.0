# Visual evidence — 2026-08-05 (Gate #3 aiquiz half + fleet migration + package rebuild)

## 1. Phase G.4 — real mod_quiz publisher (aiquiz 0.3.0-alpha/2026080500, commit `8e64d16a1`)

The G.0 "quiz id 0" stub is closed. Verification is PHPUnit-led (the feature is
dormant behind `sentientia.aiquiz.auto_push` = OFF, so there is no learner-visible
UI change to screenshot until the ninja rehearsal flips it):

- **5/5 tests green, 19 assertions** — real hidden quiz cm created, 3/3 slots
  attached, 3 question_bank_entries in the course's default shared bank
  (5.x `mod_qbank`), draft `pushed_quizid` = actual quiz id; rejected questions
  excluded (2/3 pushed); non-approved drafts refused (errorcode
  `push_err_notapproved`); capability denial rolls back transactionally
  (0 quiz rows after refusal); GIFT control-character escaping exact.
- Three defects found+fixed by the suite during development: missing
  `$moduleinfo->module` (mdl_modules id — the mod_form normally injects it;
  without it course_modules.module inserts NULL), `quiz_update_sumgrades()`
  hard-deprecated since 4.2 → `grade_calculator::recompute_quiz_sumgrades()`,
  and a test asserting on the localized message instead of errorcode.
- review.php bootstrap probe: anonymous → HTTP 303 to login (no fatal).

## 2. Parallel-session work landed same day

- 5-consumer gateway migration (`7cf2903d0`): skillsai / recommendations /
  translate / authoring / assistant all at 2026080500, opt-in routing —
  all six AI consumers now route through `local_sentientia_ai`.

## 3. Package rebuild for the DevOps handoff

Approvals: **Priyanka APPROVED**; Matt (CTO) pending. Overlay re-run
(65,384 files at the 5.2 target, AMD gate 0 stale tokens); publisher +
gateway + migrated consumers spot-verified in the 5.2 tree; repackaged as
`Sentientia-LMS-5.2-Complete-Standalone-2026-08-05.zip` (SHA-256 in the
regenerated guidebook PDF; 08-04 zip renamed `.superseded`).

## Ops note

Local console-mode Apache died silently twice in ~14h (no crash log, opcache
off) — restarted via `httpd.exe` both times. Recommend installing Apache as a
Windows service (`httpd.exe -k install`) to end the fragility; does not affect
the Linux ninja target.
