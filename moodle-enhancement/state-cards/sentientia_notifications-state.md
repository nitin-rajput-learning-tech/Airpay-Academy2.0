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

None registered directly. Consumes channel-master flags from
`local_airpay_core`:
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
