# State Card — `quizaccess_airpay_proctoring`

**Component:** `quizaccess_airpay_proctoring`
**Version:** `2026112002` / `1.3.1` (`quizaccess_sentientia_proctoring`; B2 security levels with review fixes, committed `76e8ec572`; on local XAMPP since 2026-11-20, not on UAT)
**Maturity:** `MATURITY_STABLE`
**Status:** NOT live anywhere and locks learners out when switched on (corrected 2026-11-19; this line said "Live on airpay.academy", which was never true: live airpay.academy is still BizLMS). Keep "Require proctoring" = No on every quiz until post-demo session 4 (B1). Companion to `local_airpay_proctoring`.
**Last refreshed:** 2026-11-19 (stale claims corrected; see the dated section at the end)
**2026-11-20:** B1/B2 code is committed in `76e8ec572` (flags `sentientia.proctoring.enabled` and `sentientia.assessment.secure_mode.enabled` OFF), not yet verified (no PHPUnit, no browser), not on UAT. The header and the 2026-11-19 section describe the rule before that commit.

---

## 2026-11-21 - 20 Nov session wrap-up: local deploy, verification, PHPUnit

Verified server side on local 20 Nov (rule loads, pre-start checks per level, secure acknowledgement). PHPUnit green 21 Nov (3 files, 37 tests). Evidence: `docs/visual-evidence/2026-11-20/session/README.md`.

## Mission

Standard `mod_quiz` access-rule plugin that wires `mod_quiz` into the
`local_airpay_proctoring` engine. Mirrors the upstream `quizaccess_seb`
(Safe Exam Browser) integration pattern.

What actually happens when a teacher sets "Require proctoring" = Yes
(corrected 2026-11-19; the earlier text described a "Start proctored
attempt" button, in-page events for multiple faces and a post-attempt AI
analysis, none of which exist):
- Pre-attempt: `prevent_new_attempt()` hides the start button and shows
  "You must complete the proctoring consent + identity step ..." until a
  proctoring session is `recording`. The consent flow is loaded only from
  `setup_attempt_page()`, which core never runs on the quiz page, so the
  learner cannot start.
- During attempt (if a session somehow exists): `proctor.js` logs focus,
  fullscreen and paste events on the first page only; nothing is blocked.
- Post-attempt: nothing. No finalize, no analysis.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `quizaccess_airpay_proctor` | One row per quiz × proctoring-enabled toggle. Stores per-quiz proctoring settings (consent text, identity check level, event budgets). |

## Capabilities

None declared here. The plugin defers all access decisions to
`local_airpay_proctoring` capabilities.

## Feature flags

None registered.

## Key files

```
mod/quiz/accessrule/airpay_proctoring/
├── version.php                                     2026052401 / 1.1.1
├── README.md
├── rule.php                                        access_rule_base impl
├── db/
│   ├── install.xml                                 1 table
│   └── upgrade.php                                 (Phase B.12 hotfix: defensive table_exists())
├── lang/en/quizaccess_airpay_proctoring.php
└── tests/
    ├── rule_test.php                               9 methods
    └── upgrade_test.php                            4 methods (13 total)
```

## Tests

2 PHPUnit classes, 13 methods. `upgrade_test.php` was added with the
Phase B.12 hotfix to lock in the regression that motivated it.

## Open items

- [ ] Hindi `lang/hi/quizaccess_airpay_proctoring.php` (parity drive
      sweeps every plugin)
- [ ] Behat coverage of the quiz-edit form toggle
- [ ] Settings-form preview of the consent text (currently shown only
      at attempt time)
- [ ] Per-tenant "proctoring tier" (light vs. heavy event capture)
- [ ] Surface the AI verdict directly on the attempt-review screen
      (today: only on the proctor reviewer queue)

## State card created — 2026-05-24

Initial state card. Plugin shipped pre-2026-05; Phase B.12 hotfix is
the most recent touch. Created now as part of the P1 state-card pass.
The full proctoring engine lives in `local_airpay_proctoring` — this
plugin is intentionally thin (mod_quiz integration layer only).

## 2026-10-08 Moodle 5.3 compat FX-13 (twin reconciliation)

`mod/quiz/accessrule/sentientia_proctoring/tests/rule_test.php` in the `moodle-enhancement` twin lacked the `require_once` of `rule.php` that the top-level copy gained in the WF-011 fix (core loads accessrule `rule.php` directly, not through the classmap). The ME copy takes the top-level file; the twins are identical and the extended drift gate (`tools/check-tree-drift.php`, area `mod/quiz/accessrule`) keeps them so. The access rule itself uses only the new quiz classes and is byte-identical across 5.2 and 5.3 (compat report 3.6).
## 2026-11-19 - Stale claims corrected (post-demo plan 2.4, docs only, no version change)

The plugin README (both trees) and this card said the rule redirects to a `consent.php` page, blocks resume on an
expired or flagged session, runs a post-attempt analysis and is live on airpay.academy. None of that is true; the
README now describes `rule.php` as it is, with a local check that shows the lock-out. Admins harden quizzes with
core settings meanwhile: `docs/guides/ASSESSMENT-INTEGRITY-ADMIN-GUIDE.md`. B1 (session 4, ADR-038, flag
`sentientia.proctoring.enabled`) moves consent and identity into the pre-flight check form. Full list of corrected
claims: `sentientia_proctoring-state.md`, 2026-11-19 section.
