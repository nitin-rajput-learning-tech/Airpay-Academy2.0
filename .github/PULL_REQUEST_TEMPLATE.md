<!--
  ╭──────────────────────────────────────────────────────────────────╮
  │ Airpay Academy / Sentientia LMS — Pull Request                   │
  │                                                                  │
  │ Read CLAUDE.md first if you haven't. The most-relevant rules:    │
  │  - NEVER ship a feature without a feature flag (default OFF)     │
  │  - NEVER break airpay.academy current production behaviour       │
  │  - NEVER touch core Moodle files without an ADR + doc-mod record │
  │  - UI changes ship with screenshots in docs/visual-evidence/     │
  │  - NEVER mark a session "done" without updating PROJECT-STATE.md │
  ╰──────────────────────────────────────────────────────────────────╯
-->

## Summary

<!-- 1-3 bullets: what changed and WHY, not just WHAT. The "why" is
     what your future self will need when this PR shows up in `git log`
     two years from now. -->

-

## Scope

<!-- Tick every box that applies. Each unticked box is a question for
     the reviewer to confirm. -->

- [ ] Plugin code (`local/airpay_*`, `theme/airpayux`, etc.)
- [ ] Core Moodle file modified (requires ADR + entry in `docs/core-mods/`)
- [ ] DB schema change (`db/install.xml` or `db/upgrade.php`)
- [ ] New feature flag in `local_airpay_core/feature_flags`
- [ ] New web-service endpoint
- [ ] UI / SCSS / Mustache change

## Verification

<!-- Confirm each gate. CI runs:
       1. PHP syntax lint on every airpay file
       2. JSON validity + Mustache balance
       3. ws-contract-gate (ADR-009 — datatable contract drift)
       4. version.php bump warning on plugin code changes
     But CI doesn't run a real Moodle install — so the manual gates
     below are not redundant. -->

- [ ] `php -l` on every changed PHP file passes locally
- [ ] Local Moodle (XAMPP) upgrade + purge cache succeeded
- [ ] Tested as the relevant user role (NOT as Site Admin — admin
      bypasses capability checks and hides real bugs)
- [ ] Tested at mobile breakpoint (590px viewport) if UI changed
- [ ] PHPUnit (if applicable) — see `theme/airpayux/tests/README.md`
      for the runbook
- [ ] No new console errors in browser DevTools

## Web-service consumers (if this PR touches `data-region=
"airpay-datatable"` or any `db/services.php`)

<!-- Per ADR-009 — the shared theme_airpayux/datatable client always
     POSTs {search, sort, sortdir, page, perpage, filters}. Every WS
     it consumes MUST declare all 6 with VALUE_DEFAULT. Bug #6 + #10
     + #12 + #13 were all this drift class.

     Local check: `php moodle-enhancement/tools/ci-ws-contract-check.php`
     CI gate: ws-contract-gate (blocks merge on non-zero exit) -->

- [ ] `php moodle-enhancement/tools/ci-ws-contract-check.php` passes
      locally
- [ ] If a new WS endpoint feeds a datatable, its
      `execute_parameters()` matches the canonical shape in
      `local/airpay_request/classes/external/list_mine.php`

## Role detection (if this PR touches anything role-aware)

<!-- Per ADR-009 — never re-implement is_siteadmin / has_capability
     checks. Always consume the shared helper:

       $r = \theme_airpayux\role_detector::detect();
       // $r['issiteadmin'], $r['isldadmin'], $r['isadmin'],
       // $r['ismanager'], $r['islearner'], $r['switched_to_employee']

     Bug #11 happened because layout/dashboard.php and
     classes/sidebar_navigation.php had drifted to disagree. -->

- [ ] Role-aware code consumes `\theme_airpayux\role_detector::detect()`
- [ ] PHPUnit `role_detector_test` still passes locally

## Visual evidence (mandatory for UI changes)

<!-- Per CLAUDE.md — every UI-touching session ends with screenshots
     saved to `docs/visual-evidence/YYYY-MM-DD/` and a README in the
     same folder. Reviewers verify against these before merge. -->

- [ ] Desktop screenshot saved to `docs/visual-evidence/<date>/`
- [ ] Mobile (590px) screenshot saved if responsive-relevant
- [ ] `docs/visual-evidence/<date>/README.md` describes what changed
- [ ] Confirmed the sibling page wasn't regressed by inspecting DOM
      computed styles (not just eyeballing screenshots)

## Documentation

- [ ] `version.php` bumped if plugin code changed
- [ ] `PROJECT-STATE.md` session-changelog updated with commit SHA(s)
- [ ] Lang strings updated in both `lang/en/` AND `lang/hi/` (Hindi
      parity is enforced)
- [ ] State card updated in `state-cards/` if applicable

## Risk + rollback

<!-- If this PR is risky (touches core, changes DB schema, modifies
     auth/security), say so here. Otherwise delete this section. -->

<!--
  - What breaks if this is wrong?
  - How do we roll back? (git revert? DB migration down? cache purge?)
  - Anything time-sensitive? (DST? cron timing? tenant-specific?)
-->

---

Closes #
