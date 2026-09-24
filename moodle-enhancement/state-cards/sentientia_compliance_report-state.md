# State Card â€” `local_airpay_compliance_report`

**Component:** `local_airpay_compliance_report`
**Version:** `2026052900` / `1.0.0`
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Compliance training audit + escalation.
**Last refreshed:** 2026-06-02 (export gated on a capability â€” PII protection)

---

## Mission

Compliance training audit â€” generate per-tenant snapshots of who's
completed mandatory compliance training (cyber security, sexual
harassment, KYC/AML, etc.), flag overdue learners, send escalation
emails, and surface a manager-facing exemption workflow.

Pairs with `block_airpay_cert_health` (dashboard widget) and
`local_airpay_emails` (delivery pipeline).

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_compliance_courses` | Which courses are flagged as compliance-mandatory (per tenant) |
| `local_compliance_snapshot` | Periodic snapshot of per-(user Ã— course) compliance state |
| `local_compliance_exemptions` | Manager-granted exemptions (with rationale + expiry) |
| `local_compliance_email_log` | Email-send audit (joined with `local_airpay_email_log` for cert PDFs) |

## Capabilities

None declared explicitly. Admin surfaces gate on `moodle/site:config`;
manager surfaces gate on the upstream `local_airpay_manager` cap layer.

## Feature flags

None registered.

## Key files

```
local/airpay_compliance_report/
â”œâ”€â”€ version.php                                   2026041200 / 1.0.0
â”œâ”€â”€ README.md
â”œâ”€â”€ settings.php                                   Admin: tenant exemption defaults
â”œâ”€â”€ styles.css
â”œâ”€â”€ index.php                                      Compliance summary table
â”œâ”€â”€ export.php                                     CSV export
â”œâ”€â”€ classes/
â”‚   â”œâ”€â”€ compliance_engine.php                     Snapshot generator + overdue rules
â”‚   â”œâ”€â”€ task/                                      Scheduled snapshot run
â”‚   â””â”€â”€ privacy/                                   GDPR / DPDP
â”œâ”€â”€ db/
â”‚   â”œâ”€â”€ install.xml                                4 tables
â”‚   â””â”€â”€ upgrade.php
â”œâ”€â”€ templates/
â”œâ”€â”€ lang/
â”‚   â”œâ”€â”€ en/local_airpay_compliance_report.php
â”‚   â””â”€â”€ hi/local_airpay_compliance_report.php
â””â”€â”€ tests/                                         4 PHPUnit methods (single file)
```

## Tests

1 PHPUnit class, 4 methods. Snapshot-engine smoke. Most logic is
exercised indirectly via the integration tests on
`local_airpay_emails` + `block_airpay_cert_health`.

## Open items

- [ ] Cohort-scoped compliance â€” today: per-course, per-tenant only
- [ ] Manager exemption SLA â€” auto-expire stale exemptions
- [ ] Compliance-by-supervisor view (today: by-tenant + by-learner only)
- [ ] PHPUnit coverage extension (today: minimal)
- [ ] Per-tenant overdue-threshold (today: 7 days hardcoded)

## State card created â€” 2026-05-24

Initial state card. Plugin has been live since 2026-04-12; created
now as part of the P1 state-card pass.

## ADR-018 Wave 2 â€” open_path â†’ tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical â€” the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.

## 2026-06-02 â€” Export gated on a capability (PII protection)

The full-matrix export (every employee's compliance status + name/email/employeeid/
department â€” bulk PII) is now gated on a dedicated capability
`local/airpay_compliance_report:export` (RISK_PERSONAL) instead of the old
`is_siteadmin() || has_capability('local/courses:manage')` inline check.

- `classes/permission.php` â€” `can_export()` checks the cap at SYSTEM context AND every
  `CONTEXT_COURSECAT` where the user holds a role (the BizLMS Compliance Officer / OrgAdmin
  shell is assigned at category context, so a system-only check would miss it).
  `grant_export_to_default_roles()` (idempotent; db/install.php + db/upgrade.php) preserves
  the pre-capability access set (`local/courses:manage` holders + Compliance Officer role 9).
- `db/access.php` â€” the capability (manager archetype default).
- `export.php` server gate + `index.php` / `dashboard.mustache` button-visibility call the
  SAME `can_export()`, so they cannot disagree. Line managers VIEW but are NOT granted export.
- lang en + hi (100% parity). version 2026041200 â†’ 2026052900. `tests/permission_test.php`
  6/6 green.

## 2026-06-11 overnight (foolproof WF-008)

- `compliance_engine.php:336` — fixed PHP 8 warning: `\->deadline_date` does not exist on `local_compliance_courses` rows (schema has `deadline_days`); now `!empty()`-guarded.
- Cold-run scale finding recorded in WORKFLOW-TEST-MATRIX (WF-008): `rebuild_snapshot()` sends escalation messages inline per overdue user — thousands of `message_send()` calls on a cold clone; queue/chunk hardening is a follow-up. The 539MB blow-up root cause was the stale-capability debugging-backtrace flood (fixed via repair CLI §2d) + max_allowed_packet=1M local (now 64M).


## 2026-09-10 — Filter-bar defaults localised (1.0.1 / 2026091000)

`templates/dashboard.mustache` had literal "All Business Units / All Departments /
All Sub-Departments / All Entities" option labels → `filter_all_*` strings (en+hi,
both trees). Template-only + strings; both trees identical. Deploy pending.

## 2026-09-16 — Tenant-clamped drill-down, tenant-scoped course columns, report chrome localised (1.0.2 / 2026091600)

UAT screen check (Meera = Airpay L&D admin, Juma = ZEEA admin): the Business Unit filter offered **ZEEA (1)** to
Meera and the matrix listed **every** tenant's mandatory course as a column (Meera saw the Tanzania course, Juma the
three Airpay courses, all as empty "Not Enrolled" cells). Rows/KPIs were scoped (snapshot `department_path`).

- `compliance_engine::get_org_hierarchy_level(1, $parentpath)`: `LIKE '/1%'` (matched `/177`) → exact-or-child
  `(open_path = :pexact OR open_path LIKE :pprefix)` with `sql_like_escape($parentpath) . '/%'`.
  Found by the new test: its `GROUP BY id` resolved to `u.id` (MySQL prefers FROM columns over select aliases),
  so every BU showed **(1)** user regardless of headcount and same-tenant rows collided on the record key —
  now a derived table grouped by `tenantid` (Airpay reads (9) on UAT, not (1)).
- New `get_active_courses_for_scope($orgpath)` (global `costcenterid = 0` + the scope's tenant) feeds
  `get_compliance_matrix()` → the matrix AND `export.php` columns follow the tenant; site admins keep all.
- New `clamp_filter_to_tenant($orgpath, $bu, $dept, $subdept)` — `index.php` resets a foreign `?bu=` to "all of my
  tenant" for scoped admins. New `tenant_id_from_path()`.
- Hindi parity on the report itself: `index.php` KPI labels + redirect messages via `get_string`; `status_label()` via
  `status_*` strings; `templates/dashboard.mustache` — every literal (data-freshness line, filter label, tabs, table
  headers, RAG labels, Configure tab, placeholders, confirm text) → `{{#str}}`; the inline `onclick=confirm()` became a
  `data-confirm` attribute + one delegated handler. 49 new en+hi key pairs → 89/89.
- F-12: `fullname` / `designation` / scorecard `department` / filter + option `name` / config `coursename` +
  `entity_name` are `format_string()`'d and were re-escaped by `{{ }}` (Meera's designation read "Head of L&amp;D"
  on UAT) → triple braces; raw DB slots (course headers, defaulters, manager report) keep `{{ }}`.
- Tests: +5 in `tests/compliance_engine_test.php` (tenant-scoped columns, tenant id parsing, clamp, localised status
  labels, slash-bounded BU list; `add_mandatory_course()` helper takes `$costcenterid`, `add_root_org()` helper).
- Both trees patched identically (the only pre-existing divergence — `pluginname` "Airpay …" in ME vs "Sentientia …"
  top-level, and the `!empty()` deadline guard — is untouched). UAT runs the ME copy → deploy with `--prefer-me`.
  Deployed to UAT 2026-09-17 08:27 (c14c36e85, checksums OK, upgrade Success).

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

Department scorecard counted every sibling department whose id shared a digit prefix (`$dept->path . '%'`: /1/2 swallowed /1/20). Org-children picker used `'%/id/%'`, which needs a slash on both sides and so counted every leaf-node user as zero.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.


## 2026-09-22 - Real privacy provider (was null_provider)

`\core_privacy\local\metadata\null_provider` is not a neutral default. It is a positive assertion
to Moodle's privacy registry that the plugin stores **no** personal data. This plugin owns
four tables - `local_compliance_snapshot`, `local_compliance_exemptions`, `local_compliance_email_log` (which also stores the employee email address the reminder was sent to) and `local_compliance_courses`, each keyed on a user id, so under DPDP a subject-access
request returned nothing from it and an erasure request deleted nothing - both reporting success, and
the registry page confirming the plugin held nothing.

Replaced with a full provider (`metadata\provider` + `request\plugin\provider` +
`request\core_userlist_provider`) implementing export, per-user erasure, bulk erasure and
context-wide deletion.

**Owner versus actor columns.** A column identifying the *data subject* has its rows deleted on
erasure. A column where the subject merely *acted* on someone else's record - an approver, a creator,
a decider - is anonymised to `0` instead, because deleting the row would destroy a third party's
record or a shared configuration row. Both are exported, so the subject still sees everything held
about them. Erasing an approver must not delete the employees whose exemptions they signed.

`tests/privacy_provider_test.php` proves the split: erasing the subject removes their snapshot, email-log and exemption rows and leaves the bystander's alone; erasing the approver leaves both exemptions standing with `approved_by` zeroed, and leaves the mandatory-course configuration intact.

Version bumped to 2026092201 so the cached privacy registry picks up the new tables.

Guarded platform-wide by `local_sentientia_platform\privacy_coverage_test`, which walks every
Sentientia plugin's `install.xml` and fails the build if a plugin declaring a user-identifying column
declares `null_provider`, ships no provider, or declares only some of the tables it owns. Structural
rather than an allowlist, so a new plugin with a copy-pasted `null_provider` fails on its first CI run.

## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - Wave 2 N5: refusals rendered as "error/nopermission"

Both entry points refused with `moodle_exception('nopermission')`. Core has no such key in
`lang/en/error.php` - only the plural `nopermissions`, which takes a `{$a}` - so the user saw the
bare identifier `error/nopermission`.

- `export.php`: `permission::can_export()` is a single capability, so it now throws
  `required_capability_exception(context_system, permission::EXPORT_CAPABILITY, 'nopermissions', '')`,
  which names "Export the compliance report".
- `index.php`: no single capability decides this gate - it mixes site admin, the retired
  `local/courses:manage`, role id 9 at category context, `moodle/site:viewreports` and the
  supervisor relationship - so naming one capability would be false. It now throws the new plugin
  string `error_noaccess` (en + hi, both trees): "You do not have access to the compliance report.
  It is open to compliance administrators, people who can view site reports, and managers with
  people reporting to them." (Review pass: the first wording, "Only compliance administrators, and
  managers ...", left out the `moodle/site:viewreports` way in that the gate honours.)

Still open, not changed here (an access decision, not a message fix): `index.php` still asks
`has_capability('local/courses:manage')`. BizLMS declares it (`local_courses/db/access.php`), so on a site that also runs BizLMS, as
the current airpay.academy stack does, it grants to whoever holds it. Sentientia does not
ship `local_courses`, so on UAT and on a fresh Sentientia install that clause is dead
code that answers false with a debugging notice. The same retired name is what
`permission::grant_export_to_default_roles()` step 1 keys on (see the analytics state card,
2026-09-22).

Version 2026092400. Guarded platform-wide by
`local_sentientia_platform/tests/exception_strings_test.php`.

**Found by the N5 review, pre-existing, NOT fixed here (each is an access decision):**

- **High.** `index.php` runs the admin actions (`addcourse`, `removecourse`, `exclude`, `include`)
  for every user who passes the view gate - any line manager with one direct report, any holder of
  `moodle/site:viewreports`. Only `confirm_sesskey()` protects them, and that passes for the user's
  own session. `compliance_engine::exclude_user()` / `include_user()` / `add_compliance_course()` /
  `remove_compliance_course()` check nothing themselves and are not tenant-scoped, so a line manager
  can POST `action=exclude&userid=<anyone, any tenant>` or deactivate a mandatory course site-wide.
  Needs its own change: gate the actions on `is_siteadmin()` or a new manage capability, and clamp
  to the tenant.
- **Medium.** `index.php` and `export.php` scope with `tenant_manager::get_tenant_path()`, which
  returns `''` for a non-admin whose `open_path` is empty or malformed, and
  `get_compliance_matrix('')` reads that as the whole site (`clamp_filter_to_tenant('')` also
  accepts any `?bu=`). Such a user sees or exports every tenant's matrix. Same defect analytics fixed
  on 2026-09-22 (`visible_org_path()` returning null and the page refusing). Line managers are also
  shown their whole tenant rather than their team.
