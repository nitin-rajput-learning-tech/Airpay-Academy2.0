# State Card â€” `local_airpay_compliance_report`

**Component:** `local_airpay_compliance_report`
**Version:** `2026111702` / `1.0.8`
**Maturity:** `MATURITY_STABLE`
**Status:** Not live: Sentientia LMS is not in production yet (live airpay.academy is still BizLMS). Compliance training audit + escalation.
**Last refreshed:** 2026-11-17 (export grant keyed on the org-admin shortname, not role id 9; see the last section)

---

## 2026-11-17 - All-courses exclusions applied in the snapshot (1.0.7, 2026111701)

**Defect.** `exclude_user()` (Configure tab, "Excluded Users") writes one `local_compliance_exemptions`
row with `courseid = 0`: "excluded from ALL compliance tracking". `rebuild_snapshot()` looked
exemptions up by the exact `userid_courseid` key, so `userid_0` matched no course, and an excluded
learner went through the whole loop. With `auto_enrol` = 1 (as on the local production copy) that meant:
manual enrolment into every applicable compliance course, the `welcome` mail, `reminder1` / `reminder2`
(daily, because the dedup window is 24 h), `overdue_alert` to the learner on overdue days 1-3,
`weekly_escalation` to their manager on overdue day 7, 14 and so on, and a place in every report. The
local production copy has no victims today (0 active courseid-0 rows). On UAT,
`tools/uat/provision_test_users.php` excludes `uat_author_airpay` and then rebuilds, so that test account
was enrolled and mailed in this way.

**Fix.** `rebuild_snapshot()` gets one more condition: an active, unexpired `courseid = 0` row exempts the
user from every course that applies to their tenant, exactly as a per-course exemption does for one course.
The row gets status `exempted`; there is no enrolment lookup, no auto-enrol and no mail. The `is_active` /
`expiry_date` rule is the same, because the row comes from the same query. The tenant check still runs
first, so a course of another tenant gets no row. Enrolments made before the fix are left in place.

**No upgrade step.** The hourly `refresh_snapshot` task upserts every row for each active user and each
applicable active course, so an excluded learner's rows turn `exempted` on the next run.

**An excluded learner after the fix:**

| View | What it shows |
|---|---|
| Matrix, CSV/XLSX export | still listed. In a tenant-scoped view every course column says Exempted and the row counts as complete (`all_completed`). In the site-wide view another tenant's pinned course shows Not Enrolled and the row is not green (true of every learner) |
| Defaulters | not listed for courses that are still active and apply to their tenant; frozen rows (see "Found, not changed") still show |
| KPI tiles | Exempted +1 per applicable course. They stay in `total`, so they lower the compliance rate. The tiles are cached with no expiry (see below) |
| Department scorecard | in the department total, never completed or overdue, so they lower the rate |
| Manager report | counted in team members and assignments, never completed, so they lower the team rate |
| Theme admin dashboard widget | counted in "assigned", not overdue, so they lower the compliant % |
| Notifications daily digest | already left out (NOT EXISTS on courseid 0 or the course) |

**Cross-tree.** `classes/compliance_engine.php` is reconciled. In `send_escalation()` the ME copy read
`$mc->deadline_date ? ...`, and `local_compliance_courses` has no such column, so every escalation raised
"Undefined property". Both copies now use the top-level `!empty(...)` form and are byte-identical. That
was the only difference between them. Its CONTENT line is removed from `tools/tree-drift-baseline.txt`.

**Tests.** `tests/global_exclusion_test.php` has 5 tests (`@covers` + `@group tenant_isolation`, docblock
only). They were written and linted but not run yet. They cover:
- an excluded learner beside an identical control learner who is not excluded: exempted on every
  tenant course, not auto-enrolled, no message of any kind to the learner, no email-log row, no
  escalation to their manager; plus how that learner shows in the defaulters, KPIs and matrix;
- a per-course exemption, which still covers only its course;
- an expired exclusion, which lets the learner back in and is switched off, beside one that expires
  later and still applies;
- `include_user()` letting the learner back in, with the exempted rows overwritten;
- tenant scope: an excluded ZEEA learner gets rows only for global + ZEEA courses, and another ZEEA
  learner is tracked as before.

**Found, not changed (each is Nitin's call):**
- **Frozen rows are counted.** `rebuild_snapshot()` never deletes rows. A row is left frozen when its
  course is taken off the list, when the learner moves to another tenant and the course no longer
  applies, or when the user is suspended or deleted. The KPIs and the scorecard read the snapshot raw.
  The defaulters list joins `{user}` without a deleted/suspended filter, and its EXISTS does not check
  `is_active`. The matrix lists anyone with any row, so a learner who moved tenant also stays in the
  old tenant's matrix. The manager report and the theme widget filter on the user's current path but
  not on user state or active courses. Only the digest filters these rows out. Proposed fix: the
  rebuild stores its start time in config when it finishes successfully, and every reader adds
  `s.snapshot_date >= :lastrebuild`. That catches all three cases in one condition, with no deletes.
- **Exempted rows are in every denominator.** The rate is completed / total, while the matrix treats an
  exempted cell as done. Because an exemption is checked before completion, an excluded learner who had
  completed a course now counts as exempted rather than completed, so the rate drops a little. Proposed
  fix: completed / (total - exempted). The page text says "excluded from all compliance tracking"; if
  that should mean "not in the reports at all", say so.
- **The KPI tiles never refresh.** index.php caches them through `make_from_params()`, with no TTL and
  no `db/caches.php`, and nothing purges that cache after a rebuild. The tiles therefore show whatever
  was computed after the last cache purge. Proposed fix: purge the `dashboard` area at the end of
  `rebuild_snapshot()`.
- **Managers never get the overdue alert.** The dedup key in `send_escalation()` (user, course, type)
  leaves out `sent_to`. The learner's `overdue_alert` is logged first, so the manager's copy is always
  suppressed; managers only ever get `weekly_escalation`.
- **Auto-enrolment ends at the deadline.** It sets `timeend` to the deadline, and the rebuild accepts any
  enrolment row. An overdue learner is still chased for a course they can no longer open.
- **The "last refreshed" stamp never shows.** index.php reads it from
  `local_sentientia_compliance_snapshot.timemodified`, a table that does not exist.
- **The theme widget's "Mandatory" count includes inactive courses.**
- **Stale comments in the notifications digest.** The digest (`sentientia_notifications`, uncommitted
  work in this checkout) has comments in `daily_digest.php` and its test saying that rebuild_snapshot()
  does not match courseid 0. That is no longer true. Its NOT EXISTS is still right for the hour between
  an exclusion and the next rebuild. The comments are left for that work's owner.

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

## 2026-09-24 - Privacy provider fix (erasure audit)

(1) New `anonymise_data_for_user()` for the DPDP flow. It keeps exemptions (the audit record of why mandatory training was excused, and by whom) with their free-text reason blanked, and deletes the derived snapshot and the email log as before. (2) N7: the Manager Report counted people under a 'Team Items' heading beside assignment counts. It now shows Team members and Assignments separately (`team_members`, `team_assignments`; en + hi strings). This was verified on local data (23 managers, 0 invariant violations) and needs an on-screen check.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.


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

## 2026-09-24 - Line managers see their reporting tree only; configuration is site-admin only (1.0.5, 2026092401)

**Product rule (Nitin, 2026-09-24):** a line manager (L2) sees compliance for their direct reports and
their extended teams, meaning everyone below them in the `open_supervisorid` chain, and nobody else.

**Before.** index.php admitted a line manager through the supervisor relationship and then scoped them
to their whole tenant, so they could read every employee's compliance status.

**Now.** `classes/viewer_scope.php` is the single access decision, and index.php uses it:

| Level | Who | Sees |
|---|---|---|
| site | site admin | every tenant |
| tenant | `local/courses:manage` (where BizLMS declares it), role id 9 at a category, or `moodle/site:viewreports` | own tenant, unchanged |
| team | admitted only as a supervisor | `compliance_engine::get_reporting_tree()`: direct plus indirect reports |

- **Refused:** everyone else, and any non-site-admin whose tenant cannot be resolved. The engine reads
  `''` as the whole site. export.php, still gated on the export capability, refuses that case too.
- **The tree walk** is breadth-first over `open_supervisorid` and bounded to the manager's tenant. It
  is cycle-safe, capped at 32 levels, and walks through deleted middle managers without returning them.
- **The queries.** The five report queries take `?array $userids` (null = unrestricted, `[]` =
  nobody). The KPI cache key includes the scope, so two managers in one tenant no longer share cached
  figures. The scorecard lists only departments the team is in. A banner (`scope_team`, en + hi) tells
  the manager they are seeing their team.
- **Configuration and actions:** the Configure tab and `addcourse` / `removecourse` / `exclude` /
  `include` are site-admin only on the server as well. The tab has only ever been shown to site admins,
  but anyone past the view gate could POST those actions for any user in any tenant, or open
  `?tab=config` and see every tenant's excluded users. These were the N5 review's High and Medium
  findings.

**Tests:** `tests/team_scope_test.php` covers the tree (direct, extended, a deleted middle manager, a
cross-tenant mis-key, a cycle, no tenant), each viewer level, the cache key, and every report query
under a team.

**Still open:** tenant-admin configuration needs tenant-scoped engine mutators first. The filter
dropdowns' per-BU user counts are tenant-wide. They are aggregate counts, not people.

**Same day, after the team-scope review** (20 agents, 4 lenses, 16 findings confirmed, all fixed):
- `?dept` / `?subdept` were never checked. `?dept=<any org id>` listed another tenant's departments,
  with headcounts, in the Sub-department dropdown. This predates today. `clamp_filter_to_tenant()` now
  requires each level to be a real child of the one above.
- Dropdown headcounts follow the scope: a manager sees their team's numbers.
- Page and Export can no longer disagree. An export-capability holder is tenant-level in
  `viewer_scope`, which restores what a category Manager with direct reports saw before today.
  export.php resolves its scope through `viewer_scope` as well.
- A refusal says why. `error_notenant` covers someone who qualifies but has no resolvable
  organisation; `error_noaccess` covers everyone else.
- Two changes for PostgreSQL, CI's database:
  - `get_compliance_matrix()` now selects its ORDER BY columns.
  - `get_manager_report()` uses `HAVING COUNT(...)` in place of an alias, and
    `get_org_hierarchy_level()` groups tenants in PHP instead of MySQL-only
    `SUBSTRING_INDEX` / `CAST ... UNSIGNED`.
- `get_defaulters()` was keyed on `s.userid`, so a person overdue on two courses showed once; it is
  now keyed on `s.id`. The course name comes from a subquery, so a course listed for several entities
  no longer multiplies rows.
- In a team view, the Manager Report keeps the group of a manager who has left, labelled
  `manager_left`, since those reports are in every other tab. Tenant and site views are unchanged.
- The config gate and KPI cache key are now `viewer_scope::can_configure()` / `kpi_cache_key()`,
  and tested.
- `team_scope_test` now:
  - carries `@group tenant_isolation` on the class, where PHPUnit sees it;
  - seeds a real org tree, so the scorecard and dropdown assertions run;
  - covers role 9 at a category, precedence, the tenant-level no-tenant refusal, the clamp, two-course
    defaulters and the left-manager group.

**Delta review of a147145cf** (5 agents): the 16 fixes held, and all 17 tests are predicted to pass on MariaDB and PostgreSQL. Two follow-ups are now fixed:

- Team admission followed ACTIVE direct reports while the tree walks through people who have left. A manager whose direct reports had all left was refused, although active people reported to them further down; 6 such users are in the imported data. Admission now follows the tree: any direct report qualifies as a candidate, and an empty tree is refused (`noaccess`).
- The defaulters test now proves the course-name subquery stops row multiplication.

Noted, not changed: the CI PostgreSQL PHPUnit job uses pre-5.1 paths (no `public/`), so it cannot run these tests yet.

## 2026-10-08 Moodle 5.3 compat FX-18

Every `fputcsv()` / `fgetcsv()` / `str_getcsv()` call in this plugin now passes the `$escape` argument explicitly with the historic default (`',', '"', '\\'`, and `null` for the `fgetcsv` length). The output and the parsing are byte-identical; PHP 8.4 deprecates relying on the default, and the notice would otherwise be written into the CSV stream on a 8.4 host (UAT and production run PHP 8.3). No version bump, no schema change.

## 2026-11-17 - SECURITY: export grant went to role id 9, whatever it was (1.0.8, 2026111702)

**Defect.** `permission::grant_export_to_default_roles()` (run by `db/install.php` and upgrade step 2026052900)
granted `:export` to role id 9 whenever a role with that id existed. `:export` is RISK_PERSONAL: an Excel/CSV of
every employee in the holder's tenant (employee id, email, full name, designation, department, status per mandatory
course). `viewer_scope::level_for()` also admitted any holder of role id 9 at a category to the whole tenant's report.

**Which role is id 9.**
- Airpay production data (local copy, read-only query 2026-11-17): `administrator`, archetype manager, the BizLMS
  org admin. This is the intended holder. `sentientiaauthor` is id 11 there. The code called role 9 the "Compliance
  Officer / OrgAdmin" role; BizLMS has no compliance-officer role, so the shortname to look up is `administrator`.
  It holds `:export` there together with `manager` (id 1).
- UAT: `administrator` (`tools/uat/provision_test_users.php` places it at id 9; ADR-031 notes, 2026-09-30). Not
  re-checked live.
- Fresh install (reasoned from install order, no install run): core creates ids 1-8; plugins install by type, then
  alphabetically, and `local` comes after every other type. The only plugin that creates a role at install time is
  `local_sentientia_authoring` (`author_role::ensure()`, T-01, commit 1dc599466, 2026-09-08). It installs before
  `local_sentientia_compliance_report`, so id 9 is the Sentientia Author role and the install granted it `:export`.

**Exposure.** Every fresh Sentientia install built from code at or after 2026-09-08: the product as packaged for a new
customer (Enterprise N), the 2026-09-10 standalone package, and PHPUnit sites. There, anyone holding the Sentientia
Author role could open the report at tenant level and download the export for their own tenant. They could not see
other tenants. Airpay is not affected: production data, the local copy, UAT (installed 2026-09-03, before T-01) and
the Stage B in-place upgrade all keep id 9 = `administrator`. No real customer runs a fresh install yet.

**Fix.**
- `permission::COMPLIANCE_ROLE_SHORTNAME = 'administrator'` and `permission::compliance_role_id()`. The grant goes to
  that role only where it exists; a vanilla site grants nothing beyond the `manager` archetype default.
- `viewer_scope` uses the same resolver for the category admission (was role id 9).
- Upgrade 2026111702 calls `permission::revoke_misdirected_export_grant()`. It removes ALLOW at system context from
  role 9 only when role 9 is not `administrator` and not manager-archetype (the archetype default would have granted
  that one anyway). It leaves an admin-set PREVENT/PROHIBIT alone and grants nothing. No-op on Airpay data and UAT.
- Step 1 of the grant (holders of `local/courses:manage`) is dead on fresh installs and on the local copy: ADR-025
  renamed the capability and nothing declares the old name. It can still fire on the Stage B in-place target,
  where the kept BizLMS plugins' role_capabilities rows survive. On Airpay data those holders are understood to be
  manager-archetype roles (see local_sentientia_analytics permission.php), not verified. Left in place: pointing it
  at `local/sentientia_courses:manage` would widen bulk-PII export and is Nitin's call.

**Tests.** `permission_test`: vanilla site, the Sentientia Author role does not get `:export` (also checks what the
install step left); the shortname grant works at category context; the revoke removes the misdirected grant and is
idempotent; it keeps the grant where role 9 is `administrator`, where it is manager-archetype, and an admin PROHIBIT.
`team_scope_test`: the compliance admin is found by shortname; role id 9 at a category no longer admits on a vanilla
site. Written, `php -l` and lint-on-save clean, drift gate OK. **Not run yet:** the version bump needs a PHPUnit
re-init, and the shared XAMPP PHPUnit environment was busy with the overnight privacy run.

**Not changed.** `theme/sentientia/layout/dashboard.php:80` also hard-codes `roleid = 9` (it only skips the onboarding
redirect for role-9 holders at a category). `tools/uat/provision_test_users.php` still warns that the compliance
branch needs `administrator` at id 9; that is no longer true.

**Merge with the courseid-0 exclusion fix** (other session, main checkout, 2026111701 / 1.0.7, no upgrade step):
the merged `version.php` is `2026111702` / `1.0.8`, and this upgrade step stays at 2026111702. It is numbered above
2026111701 on purpose, so it also runs on a site that took 2026111701 first.
