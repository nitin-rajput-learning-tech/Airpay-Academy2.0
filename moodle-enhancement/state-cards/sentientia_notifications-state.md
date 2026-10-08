# State Card — `local_airpay_notifications`

**Component:** `local_airpay_notifications`
**Version:** `2026060200` / `1.4.2`  (+ADR-020 W3.4 org-seam migration of manager digests)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Generic notification rule engine.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Generic, rule-driven notification dispatcher — the abstraction layer
that `local_airpay_emails`, `local_airpay_whatsapp`, and
`local_sentientia_pwa` plug into. Lets admins define WHEN + WHAT to
send without per-channel forking; the rule engine routes through
whichever channel is enabled for the user.

Phase C (notifications cleanup) gave this plugin its current shape;
Phase C.1 (2026-05-21) added the WhatsApp / SMS hook.

## DB tables (3)

| Table | Purpose |
|-------|---------|
| `local_airpay_notif_rules` | Rule definitions (trigger, audience, channel-allowlist, template) |
| `local_airpay_notif_log` | Send-attempt audit (per-channel result) |
| `local_airpay_notif_prefs` | Per-user channel-routing prefs (uses tenant defaults if missing) |

## Capabilities (3)

`local/airpay_notifications:` `view`, `manage`, `viewlogs`. The log
read cap is split so compliance can see delivery without edit rights.

## Feature flags

Registers one (`db/feature_flags.php`, since 2026-09-26, see the note at the end):
- `sentientia.notifications.smart_rules.enabled` (default OFF): lets the course_not_started,
  streak_broken and new_course rules send, per tenant.

This card used to say the plugin consumes the channel-master flags below, from
`local_airpay_core`. It does not: no file in the plugin reads them (checked 2026-09-26).
They are registered by `local_sentientia_platform`:
- `engagement.whatsapp.enabled`
- `engagement.sms.enabled`
- `engagement.whatsapp.reminders` (Phase C.1)
- `engagement.whatsapp.overdue` (Phase C.1)

## Key files

```
local/airpay_notifications/
├── version.php                                    2026060200 / 1.4.2
├── README.md
├── lib.php
├── index.php                                       Rule registry admin
├── log_detail.php                                  Per-send detail page
├── logs.php                                        Log table
├── cli/                                            CLI tools (replay, dedup)
├── classes/
│   ├── rule_engine.php                            Trigger resolver + send dispatcher (manager digests group via local_sentientia_core\org — ADR-020 W3.4)
│   ├── rule_manager.php                           Rule CRUD
│   ├── prefs_manager.php                          User pref CRUD
│   ├── external/                                  WS endpoints
│   ├── form/                                      Forms
│   ├── task/                                      Scheduled rule walker
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                3 tables
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── templates/
├── amd/
├── lang/
│   ├── en/local_airpay_notifications.php
│   └── hi/local_airpay_notifications.php          (100% parity post-P1 #48)
└── tests/
    ├── crud_test.php                              5 methods
    ├── rule_engine_phase_c_test.php               10 methods (Phase C)
    └── external/list_rules_test.php               5 methods (20 total)
```

## Tests

3 PHPUnit classes, 20 methods. `rule_engine_phase_c_test` is the
deepest — covers the channel-routing matrix.

## Open items

- [ ] Per-customer channel-allowlist (today: per-tenant only)
- [ ] Inbound-reply handling (WhatsApp / SMS) — Phase C.2
- [ ] Cohort-scoped rules (today: tenant + role-archetype only)
- [ ] Behat coverage of the rule editor form
- [ ] Cron health surface — overdue rule walker showing inside
      `block_airpay_cron_health`
- [ ] Per-channel retry policy (today: best-effort once)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. Phase C cleanup gave this plugin
its current rule-engine shape; Phase C.1 added the WhatsApp hooks.

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.

## ADR-020 Wave 3.4 — manager digests → org seam (2026-06-02)

The two manager-aggregate rules — `rule_monthly_summary` (team snapshot) and
`rule_manager_nudge` (3+ overdue) — previously grouped team members by
`u.open_supervisorid` directly in SQL (`GROUP BY open_supervisorid` + a `JOIN` to
the manager row). They now resolve the manager→reports grouping through
`local_sentientia_core\org::reports_by_manager()` (the W3.4 aggregate primitive)
and aggregate the domain data (completions / overdue) over that map.

Behaviour-identical under the default `org_legacy` flag — **proven on the local
prod-data DB: monthly 117 managers with an exact (team_size, completions) match;
nudge 0==0** — and auto-switches to the Sentientia org model at cutover. Deleted /
nonexistent managers are excluded as before (`record_exists`), and a latent
`LIMIT 0` (unset `batch_limit`) in the nudge was fixed to default 500. 20/20
PHPUnit green. version 2026052001 → 2026060200 / 1.4.2.

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

The new-course broadcast selected its audience with `'/1' . '%'`, so an Airpay course notified the ZEEA tenant's users.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.

## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - Wave 2 N5: nudge.php refusal rendered as "error/nopermission"

`nudge.php` refused a non-manager with `moodle_exception('nopermission')`. Core has no such key in
`lang/en/error.php` - only the plural `nopermissions` - so the user saw the bare identifier
`error/nopermission`. What decides this gate is the supervisor relationship, not a capability, so
it now throws the new plugin string `error_nudge_notyourreport` ("You can only send reminders to
people who report to you.", en + hi, both trees) rather than `required_capability_exception`.

Still open, not changed here: the gate's middle clause asks
`has_capability('local/courses:manage')`, a pre-ADR-025 name. BizLMS declares it (`local_courses/db/access.php`), so on a site that also runs BizLMS, as
the current airpay.academy stack does, it grants to whoever holds it. Sentientia does not
ship `local_courses`, so on UAT and on a fresh Sentientia install it is
dead code (false plus a debugging notice), and effective access is site admins and direct
supervisors. (Review pass: the first version of this note said no shipped plugin declares it.)

Version 2026092400. Guarded platform-wide by
`local_sentientia_platform/tests/exception_strings_test.php`.

## 2026-09-25 - ADR-031 tenant scope (1.5.0, 2026092500)

Sweep hits 30 and 31 (CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25).

- `:manage` no longer defaults to the manager archetype, and upgrade step 2026092500 revokes every
  existing grant. Rules have no tenant column, so every rule fires for every tenant: creating,
  editing, toggling or deleting one is a cross-tenant write, and `rule_manager::require_rule_admin()`
  now requires `tenant::is_cross_tenant()` on each of those paths too. Reading the rule list still
  needs only the capability. Any role that was given `:manage` deliberately must be re-granted.
- `:viewlogs` keeps its manager default (a tenant admin reading their own tenant's log is the
  feature) but `logs.php`, its status badges and `log_detail.php` are confined to the recipient's
  tenant through `classes/log_access.php`. A recipient with no tenant is visible cross-tenant only.
- `test_send` and `preview_rule` refuse a target user outside the caller's tenant (they returned
  any user's name and email, and test_send messaged them); the preview's sample course comes from
  the caller's tenant.
- `nudge.php`: the `local/courses:manage` branch now requires the target to share the caller's
  tenant. The direct-supervisor branch is unchanged.
- `rule_new_course` skips a course whose path has no tenant root (a path of exactly '/' used to
  broadcast to every tenant).

Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 follow-up (still 1.5.0, 2026092500)

Reviewer items on wave 1 (branch `claude/adr031-comms-ff`). No schema or capability change, so
the wave-1 version stands.

- `test_send` (hit 31): `sent_to` now names the recipient and their user id, without their email
  address.
- `preview_rule` (hit 31): the admin-written rule template is passed through
  `clean_text(FORMAT_HTML)` after placeholder substitution. `test_send` mails that same HTML, so
  script tags and event handlers no longer reach an inbox or the admin UI.
- New tests: a scoped `:manage` holder who targets a user in another tenant with `test_send` gets
  `error_outoftenant`, and nothing is sent or logged (checked with a message sink). A caller with
  no tenant can message nobody but themselves. A same-tenant colleague still receives the test.

**Release note for Nitin (operational).** The notifications 2026092500 and pwa 2026092500 upgrades
revoke `:manage` from EVERY role at system context. After deploy, tenant admins lose
`notifications/index.php`, rule management, `test_send` / `preview_rule` and the push log. They
keep `:viewlogs` (own tenant's log). If a platform L&D role must keep these, re-grant `:manage` to
it after the upgrade. Rule writes also need `local/sentientia_platform:crosstenant` on that role.

**Not fixed, needs Nitin's call:** `rule_engine::rule_course_not_started`, `rule_streak_broken`
and `rule_new_course` build `"... LIMIT " . (int) get_config(...,'batch_limit') ?: 500`. That
parses as `("... LIMIT 0") ?: ...` because `batch_limit` is defined nowhere. All three rules
therefore run `LIMIT 0` and have never sent anything. `db/install.php` seeds "Course not started",
"Streak at risk" and "New course available" as ENABLED rules on `inapp`, and the `smart_alert`
provider defaults email and popup ON. Correcting the precedence would switch on up to 500
messages per rule per hourly run on UAT's imported production users. So it is left as it is,
pending a decision: disable those seeded rules first, or put the fix behind a flag.
(Decided and fixed 2026-09-26, behind a flag: see the last section.)

## 2026-09-25 - ADR-031 follow-up 3: learning-path-stalled rule reads the real table (still 1.5.0, 2026092500)

Reviewer item (P2, CONFIRMED, pre-existing drift) on branch `claude/adr031-comms3-ff`. No schema or
capability change, no version bump.

- The moodle-enhancement copy of `rule_engine::rule_learning_path_stalled()` (the one UAT serves)
  checked and queried `local_airpay_lp_users`, which no install.xml defines, and filtered on
  `lu.timemodified`. `table_exists()` was false, so the rule silently sent nothing on UAT. Both
  copies also compared the integer `status` with `'enrolled'` / `'in_progress'`: an error on
  PostgreSQL, and on MySQL both strings cast to 0, so in-progress learners never matched.
- Now: `local_sentientia_learningpath_users`, `lu.timecreated`, and
  `lu.status IN (:stnew, :stprog)` bound to `path_manager::ENROL_NEW` / `ENROL_INPROGRESS`. Docblock
  corrected.
- The top-level copy was made the source and copied over the ME copy, so `rule_engine.php` is now
  identical in both trees and its line is drained from `tools/tree-drift-baseline.txt`. That also
  brings the top tree's white-label change (W-A batch 3) to the ME copy: the inactive-user subject is
  `'We miss you on ' . format_string(get_site()->fullname)` instead of the hard-coded
  'We miss you on Airpay Academy'. `templates/prefs.mustache` is still drifted and stays baselined.

Tests: new `tests/learning_path_stalled_test.php` (`@group tenant_isolation`). A not-started /1
learner and an in-progress /177 learner past the window are nudged, and each message names only
their own path. A completed learner and one who joined today are not nudged. A roster with nobody
stalled sends and logs nothing. `rule_engine_phase_c_test::test_learning_path_stalled_skips_when_table_missing`
still holds: the table now exists, but it has no rows.

**Deploy note:** once the ME tree ships, any enabled `learning_path_stalled` rule on UAT starts
sending, for the first time, to learners who joined an incomplete path more than `trigger_days` ago.
Each run is capped by `batch_limit` (default 500). The send dedup allows one message per learner
per 24 hours, so a stalled learner is nudged about once a day until they complete. Check that the
rule is enabled on purpose before deploying.

## 2026-09-26 - Decision 4: smart-rule LIMIT fixed, sending behind a default-OFF flag (still 1.5.0, 2026092500)

Branch `claude/notifications-limit-flag`. Nitin delegated the call ("take the decision as needed,
recommended"). The recommended option was taken: fix the precedence bug, and gate sending behind a
new flag, so nothing is sent until Nitin flips it.

**Decided, and why**

- Fix the LIMIT, don't leave the rules dead. `rule_course_not_started`, `rule_streak_broken` and
  `rule_new_course` now pass the cap to the DB API as `limitnum`, not as concatenated SQL.
  `rule_engine::batch_limit()` reads `local_sentientia_notifications/batch_limit`. Unset, zero,
  negative or non-numeric falls back to 500. Anything higher is capped at 5000. A limitnum of 0
  would mean "no limit", so 0 is never passed.
- Gate the sending, don't disable the seeded rules. Disabling them would have meant a data change
  on every site, and it would hide rules an admin had deliberately enabled. The new flag
  `sentientia.notifications.smart_rules.enabled` (`db/feature_flags.php`, default OFF) keeps the
  rules and their rows as they are. With the flag OFF, each of the three rules returns
  `['sent' => 0, 'skipped' => 0]` before any query. No message goes out and no log row is written,
  not even the `sending` claim. That matches what users saw before (the rules sent nothing).
- The flag resolves per tenant root, with an explicit scope, because cron has no meaningful `$USER`.
  The rules message only users whose `open_path` is under a root where it is ON, using
  `tenant::path_descendant_filter()`, so the boundary is correct. A user with no recognised root is
  never messaged (ADR-031 fail closed). `new_course` announces a course only when the course's own
  tenant is ON, and only to that tenant. A tenant-level OFF override beats a global ON.
- `rule_course_not_started` was keyed on `ue.userid`. So a learner who had not started two courses
  got one nudge, plus a duplicate-key debugging notice. It is now keyed on a `userid_courseid` pair
  built with `sql_concat()`, which is portable to MySQL and PostgreSQL. An unused `$yesterday` was
  removed from `rule_streak_broken`.
- No version bump. `db/feature_flags.php` is not an upgrade step, install.xml, access.php or
  services.php. `feature_flags::load_registry()` reads it at runtime, behind a 60-second MUC cache
  that the deploy's cache purge clears. The version is already 2026092500.

**Other rules checked for the same bug:** none have it. `cert_expired`, `peer_completion_celebration`
(default 100), `compliance_overdue`, `certificate_expiring`, `ilt_feedback_pending`,
`learning_path_stalled`, `enrolment_anniversary`, `inactive_user`, `quiz_low_score`, `manager_nudge`
and `monthly_summary` all write `(int) (get_config(...) ?: N)`, with the parentheses in the right
place. They are unchanged. They still interpolate the int into `LIMIT $batchlimit`, and a negative
`batch_limit` would make their SQL invalid. That is latent, because the key is set nowhere.

**Tests:** new `tests/smart_rules_flag_test.php` (`@group tenant_isolation`), in both trees:
- The flag is registered, and OFF by default in every tenant.
- With the flag OFF and matching data, all three rules send nothing and log nothing. With the flag
  ON and `batch_limit` unset, the same data does send.
- ON for tenant 1 only: only tenant 1 users are messaged. A 177 user or a user with no tenant gets
  nothing, and the 177 course is not announced.
- ON globally: each new course reaches only its own tenant.
- A tenant-level OFF beats a global ON.
- `batch_limit = 2` caps each rule at 2 per run, and all of those are in-tenant.
- `batch_limit()` bounds.
- Two not-started courses produce two nudges.

Not run locally (low-CPU mode). The CI `tenant_isolation` group runs them.

**Before flipping the flag (Nitin's call; never flipped here):**
- Cadence. `send()` dedups the same rule, user and course for 24 hours only. So a learner who has
  not started a course is nudged about once a day until they start it. A lapsed streak (3 or more,
  last counted 2 or more days ago) gets "at risk" every day until the user logs in again. The
  streak row is not reset in between.
- Starvation above the cap. The queries do not exclude rows already notified. With more than
  `batch_limit` matches, the same arbitrary 500 can be picked every hour and skipped as
  duplicates, so the rest are never reached. On UAT, `new_course` for a large tenant hits this.
  Fix before flipping: exclude recently logged pairs in the SQL, or raise `batch_limit`.
- The `smart_alert` provider defaults popup and email ON. Flip for one tenant first, for example
  `feature_flags::set('sentientia.notifications.smart_rules.enabled', 1, true)` on UAT, and watch
  `logs.php`. To change the cap:
  `php admin/cli/cfg.php --component=local_sentientia_notifications --name=batch_limit --set=200`.

## 2026-09-30 - persona pass bundle "Admin gates" (D9)

Branch `claude/persona-fix-admingates`. `nudge.php`: the capability branch used the retired BizLMS
`local/courses:manage`; now `local/sentientia_courses:manage` (ADR-025 successor). The ADR-031 tenant
match after the gate and the direct-supervisor rule are unchanged. Guard: `local_sentientia_platform`
`tests/capability_names_test.php`. No version bump.

## 2026-09-30 (ADR-032) - ILT feedback rule ignores imported sessions

`rule_ilt_feedback_pending` has no upper age limit on the session (only `endtime < now - trigger_days`) and its
24-hour dedupe repeats daily, so once the BizLMS classroom import brings years of sessions and rosters in, a rule
switched on would ask every person who ever attended for feedback. The query now excludes sessions the import
created (`local_sentientia_platform\bizlms\provenance::not_imported_sql`, guarded by `class_exists` and a
`table_exists` on the map). New test `test_ilt_feedback_ignores_sessions_the_bizlms_import_brought_in`. Code
only, no version bump. Both trees. Not run here.


## 2026-10-07 - owner decision LRN-11: the stalled-path nudge never targets imported enrolments or switched-off paths

`rule_learning_path_stalled` selected every not-completed path enrolment older than `trigger_days`, with no path-status filter and no import filter. Once `smart_rules` is switched on it would have nudged about 760 April learners about old BizLMS plans (BizLMS sent no such message), and learners on archived paths too. Decision `learningplan.stalled_nudge_scope` = `native_rows_on_active_paths`:
- the query adds `lp.status = STATUS_ACTIVE AND lp.visible = 1` (archived and hidden paths are never nudged: a plain bug fix);
- and excludes enrolments the import created (`provenance::not_imported_sql('lu', 'local_sentientia_learningpath_users')`), guarded by `class_exists` and a `table_exists` on the map, the same pattern the ILT feedback rule uses. An admin can still nudge an imported learner by hand.
`smart_rules` is default OFF and the rule is not seeded, so nothing fires today; this lands before it is ever flipped. A later, separate decision can include imported rows with a cutoff counted from cutover. Tests: `learning_path_stalled_test` (imported row, archived path, hidden path not selected; native row and a second active path of a mixed learner selected; empty import map). Code only, **no version bump**. Both trees. Written, not run.