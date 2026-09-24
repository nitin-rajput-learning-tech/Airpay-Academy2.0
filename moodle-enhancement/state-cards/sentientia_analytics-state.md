# State Card — `local_airpay_analytics`

**Component:** `local_airpay_analytics`
**Version:** `2026052001` / `1.0.1-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. L&D analytics dashboards.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

L&D analytics dashboards — aggregate views of engagement,
completion rate, time-to-completion, top courses, top tenants. Reads
from core Moodle tables + Airpay tables; no own schema.

Distinct from `local_airpay_reports` (which is a saved-report builder
for ad-hoc queries); this plugin is a curated dashboard with built-in
KPI tiles + drill-down.

## DB tables

None — read-only across `mdl_course`, `mdl_course_completions`,
`mdl_logstore_standard_log`, `local_airpay_user_skill_hist`, etc.

## Capabilities

None declared explicitly. Gate is `moodle/site:viewreports` from core.

## Feature flags

None registered.

## Key files

```
local/airpay_analytics/
├── README.md
├── index.php                                     Dashboard landing
├── drilldown.php                                 Per-KPI drill-down
├── export.php                                    CSV export
├── styles.css
├── classes/
│   ├── analytics_manager.php                     KPI aggregation queries
│   └── privacy/                                  GDPR / DPDP
├── db/
│   └── (no install.xml — read-only plugin; no version.php at top
│         level either, plugin uses defaults from settings.php)
├── templates/
├── lang/
│   ├── en/local_airpay_analytics.php
│   └── hi/local_airpay_analytics.php
└── tests/                                       1 PHPUnit class / 5 methods
```

## Tests

1 PHPUnit class, 5 methods.

## Open items

- [ ] PHPUnit extension for `analytics_manager` query correctness
- [ ] Per-tenant + per-customer dashboard scoping (today: site-wide
      for admin / per-tenant for tenant manager)
- [ ] Time-series charts (today: snapshot tiles only)
- [ ] Behat coverage of the drill-down flow
- [ ] Caching layer — KPI queries against 3,500+ users get slow at peak
- [ ] Replace LearnerScript blocks (legacy reports surface)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

The mandatory-course denominator excluded courses sitting at the tenant root itself, so every department's compliance rate read high.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.


## 2026-09-22 - Capability layer (W1-07): the dashboard was siteadmin-only by accident

This plugin had **no `db/access.php` at all**. All three entry points gated on

```php
is_siteadmin() || has_capability('local/courses:manage', $context)
```

`local/courses:manage` was renamed to `local/sentientia_courses:manage` by ADR-025 and is no longer
declared by any shipped plugin. Confirmed on the local install: a lookup in `{capabilities}` returns
false, and Moodle answers an unknown capability with a `debugging()` notice and `false`. So that half
of the gate had been dead code. Effective access was site admins plus whoever held **hardcoded role
id 9** at a course-category context - and role 9 names a different role on every other Sentientia
deployment, which is the wrong shape for a white-label product. The `manager` role, the dashboard's
whole intended audience, got "nopermission".

Four more live defects surfaced while fixing it, all previously masked by that accidental narrowness:

| # | Defect | Effect |
|---|--------|--------|
| 1 | `?orgid=` branch of `index.php` was not gated at all | any viewer could read another tenant's numbers by editing the query string |
| 2 | `index.php` fell back to `'/' . ($parts[1] ?? '1')` | a user with a missing or malformed `open_path` silently got tenant 1's data |
| 3 | `export.php` fell back to `tenant_manager::get_tenant_path()`, which returns `''`, and `analytics_manager` reads `''` as *no filter* | the same user got a **whole-site** CSV of named learners |
| 4 | `get_course_learners()` ordered by `DESC NULLS LAST` | PostgreSQL/Oracle syntax; MariaDB 10.11 and MySQL 8.0 both reject it, so the Course Analytics drill-down raised `dml_read_exception` on **every** call and had never once worked on either of our database targets. It also carried no tenant filter. |

**Shipped (version 2026092201, release 1.2.0-beta):**

- New `db/access.php`: `:view`, `:viewallorgs`, `:export`. Export is deliberately separate - the CSV
  is a named per-learner dataset, a materially larger disclosure than reading the dashboard - so
  viewers do not get it by default, and the template hides the button behind `can_export`.
- New `classes/permission.php`. Every check is two-step (system context, then the course-category
  contexts where the user actually holds a role), because Moodle capabilities flow *down* the context
  tree and BizLMS assigns its org-admin shell at `CONTEXT_COURSECAT`. Mirrors
  `local_sentientia_compliance_report\permission::can_export()`.
- `visible_org_path()` **fails closed**: it returns `null` when no tenant can be established, and the
  pages refuse. The only two fallbacks available were "tenant 1" and "every tenant", and both are
  somebody else's data.
- `clamp_org_path()` pins a requested org to the viewer's own subtree, `/`-terminated, so `/1` cannot
  authorise `/177`.
- `db/install.php` **and** an upgrade step call `permission::grant_to_default_roles()`. Both are
  needed: a fresh install never runs `db/upgrade.php`, which cost a day during UAT Stage A.
- The back-fill keys on `local/sentientia_courses:manage`, the **real** renamed capability. Note that
  `compliance_report::grant_export_to_default_roles()` still keys on the retired
  `local/courses:manage`, so its step 1 grants nothing while reading as though it preserves something.
  Harmless there only because its step 2 (role id 9) does the actual work.

Verified on the local install after upgrade: all three capabilities registered and held by `manager`
and `administrator`; the course drill-down query now returns rows and honours the org scope.
`tests/permission_test.php` covers all of the above, including that `local/courses:manage` really is
unregistered - the original defect in one assertion.


## 2026-09-24 - `:viewallorgs` had the wrong default (fixed before it reached UAT)

The 2026-09-22 capability layer gave `local/sentientia_analytics:viewallorgs` a `manager` archetype
default, and the back-fill granted it to holders of `local/sentientia_courses:manage`. On this platform
**tenant admins are manager-archetype roles at system context**. UAT confirms it: Meera (Airpay) and Juma
(ZEEA) both hold `administrator` (id 9, archetype manager) at context level 10. So after the deploy every
tenant admin would have read every other tenant's analytics, and the ZEEA admin's CSV export would have
carried all-tenant totals. The fix I wrote to stop a cross-tenant leak would have created one.

Now (2026092400 / 1.2.1-beta): `:viewallorgs` has **no** archetype default; `grant_to_default_roles()`
grants only `:view` and `:export`; a new upgrade step revokes `:viewallorgs` from every role, because
changing an archetype never revokes grants Moodle already applied. Site admins still pass by the admin
bypass.

Verified on local XAMPP, which had run the old step: before the upgrade `:viewallorgs` was held by
manager and administrator; after, by nobody, with `:view` and `:export` unchanged. New tests:
`test_a_manager_archetype_tenant_admin_stays_in_their_tenant` and
`test_the_back_fill_never_grants_viewallorgs`.

UAT pre-flight, read-only: the analytics capabilities are not yet registered there, and Meera's
manager-archetype role means she **keeps** dashboard access once the role-id-9 fallback is gone - the
lock-out risk recorded in the 2026-09-24 evidence file does not materialise.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.
