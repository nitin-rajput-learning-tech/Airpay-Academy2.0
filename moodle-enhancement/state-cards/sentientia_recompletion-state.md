# State Card — `local_airpay_recompletion`

**Component:** `local_airpay_recompletion`
**Version:** `2026052001` / `1.1.1`  (+P1 #53 Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Periodic course re-completion engine.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

For mandatory compliance courses that need to be re-completed every N
months (cyber security every 12 months, KYC/AML every 6 months, etc.),
this plugin owns:
- Rule definitions (course, period, audience filter, behaviour)
- A reset engine that wipes per-user completion data on the due date
- An append-only audit trail of every reset

Resets fire after a cron walker compares last-completion timestamps
against the per-rule period.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_airpay_recompletion_rules` | Rule definitions (course, period_days, audience filter, on-reset action) |
| `local_airpay_recompletion_history` | Append-only reset audit (user, course, reset reason, before/after state) |

## Capabilities (3)

`local/airpay_recompletion:` `view`, `manage`, `reset`. The dedicated
`:reset` cap lets compliance run an ad-hoc reset without granting full
rule-edit rights.

## Feature flags

None registered.

## Key files

```
local/airpay_recompletion/
├── version.php                                   2026052001 / 1.1.1
├── README.md
├── settings.php
├── index.php                                      Rule list
├── edit.php                                       Edit rule form
├── history.php                                    Reset audit log
├── cli/                                            Manual run + replay
├── classes/
│   ├── recompletion_engine.php                   Reset state machine + audit writer
│   ├── event/                                     Audit events
│   ├── task/                                      Scheduled cron walker
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                2 tables
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── templates/
├── lang/
│   ├── en/local_airpay_recompletion.php
│   └── hi/local_airpay_recompletion.php           (100% parity post-P1 #53)
└── tests/                                         1 PHPUnit class / 7 methods
```

## Tests

1 PHPUnit class, 7 methods. Covers the reset state machine + audit
write.

## Open items

- [ ] Cohort-scoped rules (today: tenant + course only)
- [ ] Per-rule pre-reset notification (warning email N days before reset)
- [ ] Behat coverage of the rule editor
- [ ] PHPUnit extension covering the cron task itself (today: engine
      only)
- [ ] Inline rule status on the course-admin page (preview next reset
      date)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - DPDP erasure detached the reset audit log from the person

**Defect.** `local_sentientia_privacy\privacy_manager::process_deletion()` (the DPDP
right-to-erasure flow) calls a Sentientia provider's `anonymise_data_for_user()` when it has
one and `delete_data_for_user()` otherwise. With no anonymise hook here, every approved erasure
redacted the person's `local_sentientia_recompletion_history` rows to `userid = 0`. A reset
deletes the course completion, grades and quiz attempts, so that row (`previous_timecompleted`)
is the only surviving evidence of each earlier compliance cycle the person completed. The DPDP
flow promises to keep such records against the user row it anonymises in place; redacting them
pooled every erased person's history under 0.

**Fix.** `privacy\provider::anonymise_data_for_user()` added. Table by table:
- `local_sentientia_recompletion_history`, subject column `userid`: DPDP flow KEEPS it as-is.
  Core erasure (`delete_data_for_user()`) still redacts it to 0, as it always has.
- same table, actor column `reset_by_userid` (the admin who pressed reset; NULL = cron):
  anonymised to 0 by both erasures, never used to delete a row. It was previously undeclared
  and untouched, and an admin who had only reset other people's completions was not even
  reachable (`get_contexts_for_userid()` looked at `userid` only). Now declared in metadata,
  found by `get_contexts_for_userid()` / `get_users_in_context()`, and exported (course,
  reason, time only - never the other person's id).
- `local_sentientia_recompletion_rules`: no user column; untouched.
- `get_users_in_context()` no longer reports the redacted `userid = 0` as a user.
- Raw `UPDATE` SQL replaced with `$DB->set_field()` / `set_field_select()`.

New strings (en + hi): `privacy:metadata:..._history:reset_by_userid`,
`:previous_timecompleted`, `:timecreated`, `privacy:export:resets_performed`.

**Test.** `tests/privacy_anonymise_test.php`: history survives `anonymise_data_for_user()`
keyed to the subject, the actor is anonymised, cron rows stay NULL; `delete_data_for_user()`
still leaves nothing naming the subject while the audit row survives; an actor-only admin is
reachable and 0 is never reported as a user. Written, not yet run (shared test DB being
rebuilt). No version bump (class + lang change only). Both trees.


## 2026-09-25 - scorm_reset_test: 5 setup errors were test defects; engine unchanged

**Failure.** 2026-09-24 full run: 5 errors, all `coding_exception: Scorm generator requires a
current user` from `mod/scorm/tests/generator/lib.php:89`, in `test_reset_purges_scorm_attempt_rows`,
`test_reset_purges_scorm_scoes_value_rows`, `test_reset_does_not_touch_other_users_scorm_data`,
`test_reset_does_not_touch_other_courses_scorm_data` (via `seed_scorm_attempt()`) and
`test_reset_purges_activity_completion` (direct `create_module('scorm')`).

**Root cause (test).** mod_scorm's generator stages `singlescobasic.zip` in the CURRENT user's
draft area and refuses when nobody is logged in. Core's own mod_scorm tests call
`setAdminUser()` first; this suite never did, so every SCORM test died before
`recompletion_engine::reset_user_in_course()` ran. The engine was never exercised.

**Second test defect, found by reading ahead.** `seed_scorm_attempt()` looked the SCO up with an
unfiltered `SELECT id FROM {scorm_scoes} WHERE scorm = :sid`. The parsed package yields two rows:
the `<organization>` (scormtype '') and `item_1` (scormtype 'sco'). The lookup took the
organization row as the SCO, and matching two rows fired `get_record_sql()`'s "found more than
one record" `debugging()`, which `advanced_testcase` raises after each test as an "Unexpected
debugging() call detected" notice. Once the first fix landed, all four seeded tests would have
raised it, and their CMI value would have hung off a row that is not a SCO.

**Fix (test only; no assertion changed).** `setUp()` added: `resetAfterTest()` + `setAdminUser()`.
Acting as admin cannot hide anything: `reset_user_in_course()` never reads `$USER` (the
completion_reset event names its user explicitly), and every assertion is keyed to the learner's
userid. The SCO lookup is now `get_field('scorm_scoes', 'id', [scorm, scormtype => 'sco'],
MUST_EXIST)`. The defensive fallback insert checks for a `sco` row too. Engine code checked
against the Moodle 5.1 schema (`scorm_attempt`, `scorm_scoes_value`, `scorm_element`,
`course_modules_completion`, `course_modules_viewed`) and core's event validation: it purges what
the five tests assert, scoped to user x course. Written, not yet run (shared test DB being
rebuilt). No version bump (test-only). Both trees.

**Open (code, not fixed here - outside these failures).** `reset_user_in_course()` ends with
`catch (\Throwable $e) { $tx->rollback($e); return false; }`. `moodle_transaction::rollback()`
rethrows (`moodle_database::rollback_delegated_transaction()` ends in `throw $e`), so
`return false` can never run. Callers that count `false` as skipped/failed (`run_rule()`,
`bulk_reset()`) instead get an exception: one bad user ends that rule's cron batch early
(`run_all()` catches it per rule, counts an error and skips the rule's `last_run_at` update), and
ends a bulk reset part-way through with no `['reset','failed']` result for the caller. Users
already reset keep their history rows; users after the bad one are never processed. The
docblock's "rolls back atomically on a downstream failure" has no test. Needs a decision on the
contract (catch and return false, or let it throw and fix the callers) plus a rollback test.

## 2026-09-25 - ADR-031: rules and history tenant-scoped; tenant rules never reset other tenants

Cross-tenant authority sweep (docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md), 2 confirmed hits, one P0 destructive. The plugin resolved no tenant at all. `:view` (manager archetype) listed every tenant's rules and reset history (names, emails, compliance dates). `:manage` (granted by db/install.php to the tenant-admin role "administrator") opened any rule by id and saved every new rule with `costcenterid` 0, which `recompletion_engine` reads as EVERY tenant: a tenant admin's rule deleted every tenant's completions, SCORM tracking, grades and quiz attempts on the daily cron.

- New `classes/rule_access.php`: `caller_root()` (refuses no tenant), `require_rule()` (a scoped caller opens only their tenant's rules, never a global one), `costcenterid_for_save()` (a scoped caller's new rule carries their tenant; updates keep the stored value; cross-tenant callers unchanged, so site-admin rules stay global), `rules_filter()`, `history_user_filter()`, `course_filter()` / `require_course()`.
- edit.php, index.php and history.php use them; the course picker lists only the caller's tenant's courses (own tree, legacy unpathed, shared to the tenant).
- `:reset` (checked nowhere; bulk_reset.php was never built) is no longer granted by install.php and upgrade step 2026092500 (new db/upgrade.php) revokes every existing grant. `:view` / `:manage` grants stay, now scoped.
- The engine is unchanged: tenant rules already reset only the rule tenant's users (B6). Before deploy, audit existing enabled `costcenterid = 0` rules on UAT/production - they may have been made by a tenant admin through the UI before this fix, and there is no column recording who created them.
- 1.1.2 / 2026092500, depends on local_sentientia_platform 2026092500. Tests: `tests/tenant_scope_test.php` (@group tenant_isolation). Written, not run (shared PHPUnit DB). Both trees.
