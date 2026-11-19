# ADR-042 — Training ROI model v2: a two-basis cost ledger, frozen monthly facts, one allocation key, and every figure traceable

- **Status:** Proposed 2026-11-19. Nitin's answers D20 ("build ROI fully"), D21 ("both cost bases") and D22 (manager department view) in the post-demo plan §6a are final and frame this ADR. The choices below follow the recommended answers to Q20 to Q27 (plan §5.5), which still await his choice. Becomes Accepted when he approves or amends them. The flag `sentientia.analytics.roi.enabled` stays OFF for every customer and tenant until v2 (sessions 26a to 26c) is built and accepted; ROI stays out of demos until then (D20).
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services). The financial year and the baseline figures come from Airpay Finance (Q20, Q21; plan §5.4 item 5).
- **Implementer:** Claude
- **Amends:** ADR-032's answer `classroom.costs_as_columns` ("No, legacy table only -> no finance or audit reader", ADR-032 line 1334), and only if Q26 is approved: v2 is that reader, so BizLMS classroom costs found at Stage B become ledger lines (the legacy table stays). No core file changes (ADR-033 decision 4).
- **Related:** ADR-002 (flags), ADR-008 / ADR-021 (customer of a tenant), ADR-029 §6 (the `mandatory` tag, which is not the compliance list), ADR-031 (tenant scope fails closed), ADR-032 (Stage B import), ADR-035 (privacy erasure; its retention decision rests with the DPO), ADR-036 (reserved, not written: Training Programs, the per-training item for classroom training).
- **Evidence:** `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §3.2, §4 (AN3 to AN5), §5.1 (26a to 26d), §5.3, §5.5 (Q20 to Q27), §6a; code in `local/sentientia_analytics/` (version 2026111901, working tree on 19 Nov). Paths below are relative to `moodle-enhancement/` unless marked.

---

## Context

**What Nitin decided (plan §6a, final).** D20: build the ROI model fully (v2); until then ROI is skipped in demos as
"under development". D21: both cost bases, an annual baseline per customer **and** per-training costs where entered.
D22: managers get a department view using the compliance team scope.

**What v1 computes** (`local/sentientia_analytics/classes/roi_calculator.php`; plan §3.2):

| v1 line | Code | What is wrong with it |
|---|---|---|
| L&D staff = visible courses × 40 h × ₹500 | `:168-178`, `:222` | Every visible course (~418) charged in full to every period, 7 days or YTD |
| Platform = flat ₹50,000 | `:223` | Same figure for any period length |
| Content = learners whose enrolment started × 3 h × ₹50 | `:180-186`, `:224` | Enrolment start, not consumption or a licence bill |
| Productivity = completions × 2 h × ₹500 | `:146-151`, `:218` | One assumed figure for every course, including compliance courses |
| Compliance = on-time completions × ₹1,000 | `:153-161`, `:219` | "Mandatory" means any course with an end date, not the compliance list; those completions are also in productivity (double count) |

Further: one hourly rate serves both staff cost and productivity value (`:218`, `:222`); the seven assumptions are
site-wide ("Per-customer assumptions are a follow-up", `settings.php:11`); YTD is the calendar year in PHP's default
timezone (`:461-462`), not the dashboard's own range helper; the currency symbol is a language string
(`lang/en/local_sentientia_analytics.php:39`), so it follows the viewer's language, not the money; there is no
per-training or per-department figure; the flag is read for the viewer (`index.php:173-174`), not for the tenant
being viewed. With the defaults, YTD on the local copy reads benefit about ₹10.2 lakh against cost about ₹84.2
lakh, **ROI about -88%** (plan §3.2). The test data's latest completion is 4 April 2026, so only YTD shows figures.

**What exists to build on.**
- **Compliance list:** `local_compliance_courses` (course, tenant reach, `is_active`, `deadline_days`; session 1 is adding
  a cohort restriction tonight for D4) and the hourly `local_compliance_snapshot` (`status`, `completion_date`,
  `deadline_date`, `department_path`), owned by `local_sentientia_compliance_report\compliance_engine`. The dashboard
  heatmap already reads it through `analytics_manager::compliance_source_available()`. ADR-029's `mandatory` course tag
  means "auto-enrol joiners", not "penalty exposure"; v2 does not use it.
- **Classroom:** sessions with `starttime` / `endtime` (`local/sentientia_classroom/db/install.xml:88`), per-session
  attendance (`:146`), roster `hours` credited by BizLMS (`:130-131`, ADR-032). No cost columns anywhere.
- **Scope and customer:** `\local_sentientia_platform\customer::of_tenant()` and `get_customer_config()` (all three
  tenants are customer 1 while the registry is dormant); `tenant::path_descendant_filter()`;
  `permission::clamp_org_path()` (`classes/permission.php:160`); the compliance `viewer_scope` (site, tenant, team).
- **Gaps:** the top-level `local/sentientia_analytics/` twin lacks the ROI calculator, flag registry, template and
  tests (`tools/tree-drift-baseline.txt:39-48`, `ONLY-ME`; `settings.php` guards with `class_exists`). The privacy
  provider is a `null_provider` (`classes/privacy/provider.php:14-15`), true today because v1 stores nothing.

**Constraints.** Every new surface behind a default-OFF flag; both plugin trees byte-identical; en + hi strings at
parity; every query tenant-scoped, failing closed on an unresolved tenant (ADR-031); no core edits.

---

## Decision

Replace v1 with a ledger-backed model inside `local_sentientia_analytics`. Costs come from two entered bases plus
measured participant time; benefits from the compliance list, assumed productivity and entered impact; measured
activity is frozen monthly; money is computed at view time from facts + ledger + the assumptions in force; every
figure says where it came from.

### D1 — Where it lives

New namespace `\local_sentientia_analytics\roi\` (`ledger`, `facts`, `engine`, `allocator`, `statement`). The engine
is a pure function of (facts, ledger rows, assumptions, period) with no database and no strings, as `model()` is
today, so every formula is unit-tested. `roi_calculator` and the seven `roi_*` settings are retired when 26c lands.
The analytics twin drift is closed in 26a (both trees identical, baseline lines removed) before any ROI file is added.

### D2 — Flags (all default OFF)

| Flag | Gates |
|---|---|
| `sentientia.analytics.roi.enabled` (exists) | The ROI card, drill-downs and statement. v2 replaces v1 behind it; there is no v1/v2 switch |
| `sentientia.analytics.roi.ledger.enabled` (new) | The cost, assumption and impact entry pages, so Finance and L&D can enter figures while the card stays off |
| `sentientia.analytics.roi.team_view.enabled` (new) | Money in the manager department view (Q27); the view itself is AN5's `sentientia.analytics.team_scope.enabled` |

Read with `is_enabled_for(key, customer::of_tenant($t), $t)` for the tenant being viewed. A platform admin's "all
tenants" view lists only the tenants whose flag is on.

### D3 — The ledger: two cost bases, assumptions, impact

| Table | Holds | Key columns |
|---|---|---|
| `local_roi_baseline` | Annual overhead per financial year | `customerid`, `tenantid` (0 = customer-wide), `fyear`, `category` (L&D staff, platform, content licences, vendor retainers, other: Q21), `amount`, `currency` |
| `local_roi_cost` | Direct costs of one training | `tenantid`, `itemtype` (course, classroom), `itemid`, `category` (trainer, venue, vendor, travel, materials, content development, other), `amount`, `currency`, `costdate`, `source` (manual, csv, bizlms) |
| `local_roi_assumption` | Effective-dated assumption values | `customerid`, `tenantid` (0 = customer-wide), `courseid` (0 = all), `akey`, `value`, `validfrom` |
| `local_roi_impact` | Measured business impact of a training | `tenantid`, `itemtype`, `itemid`, `amount`, `attribution_pct`, `monthfrom`, `monthto`, `evidence` |
| `local_roi_fact` | Monthly measured facts (D5) | `month`, `tenantid`, `orgpath`, `itemtype`, `itemid`, measures |

Every ledger row has `createdby`, `usermodified`, `timecreated`, `timemodified`. Short table names follow the
`local_compliance_*` precedent (XMLDB allows 53 characters, `lib/xmldb/xmldb_table.php`). Assumption keys: `loaded_hourly_rate`,
`hours_saved_per_completion`, `penalty_per_breach`, `breach_likelihood`, `default_course_hours`, and the fallback-only
`course_dev_hours` and `ld_hourly_rate`. Per-course values override tenant values, which override customer values.
A change is a new row with a `validfrom` month, so last year's figures keep last year's rates; correcting the past
means dating the row in the past, which the statement then shows as a restatement. The v1 settings seed the first
customer-level set; `platform_cost` and `cost_per_learner_hour` are not carried over (a per-period guess cannot become
an annual figure; the baseline categories replace them).

### D4 — Financial year, periods, currency

- **Financial year:** start month per customer (`customer::get_customer_config('roi_fy_start_month', $customerid, 4)`,
  4 = April, Q20), labelled "FY 2026-27".
- **Periods are whole months:** FY to date (the open month computed live), last FY, last quarter, a chosen month range.
  The dashboard's 7d / 30d / 90d buttons no longer drive the ROI card; an ROI over a week is noise.
- **Currency:** an ISO 4217 code per customer (default INR) with a tenant override; every ledger row carries the
  currency of its scope. No exchange-rate conversion: a total across tenants with different currencies is shown per
  currency. The symbol comes from the code, never from the language pack.

### D5 — Measured facts, frozen monthly

A scheduled task writes `local_roi_fact` per month, tenant, learner org path (at month close), training: completions,
on-time compliance completions, learners, e-learning hours (completions × the course duration from the session 3b
custom field, else `default_course_hours`, with an "assumed" mark) and classroom hours (attended sessions × session
length). The open month is recomputed hourly; a month freezes on the 8th of the next month (a 7-day grace for late
attendance marking). A frozen month changes only through a logged CLI rebuild (for example after the Stage B history
import). Facts hold counts and hours, never money and never a user id. Freezing is needed because the compliance
snapshot and `open_path` are current-state: leavers and movers would otherwise rewrite past months.

The manager view (26d) does not read these facts: a reporting tree (`viewer_scope` team level, a list of user ids) is
not an org subtree. It computes the same measures live for those user ids.

### D6 — Formulas (period P, scope S)

| Line | Formula | Source |
|---|---|---|
| Baseline overhead, per category | Σ over FYs touching P: amount × days(P ∩ FY) ÷ days(FY); shared down by D8 | Entered |
| Direct training cost | Σ cost lines with `costdate` in P on trainings in S | Entered |
| Development estimate (fallback) | Only for an FY with **no** L&D-staff baseline line: courses created in P × `course_dev_hours` × `ld_hourly_rate` | Assumed |
| Participant time | (e-learning hours + classroom hours) × `loaded_hourly_rate` (Q22) | Measured × assumed |
| Compliance benefit | Σ over compliance-list courses reaching S: on-time completions × `penalty_per_breach` × `breach_likelihood` (Q24) | Measured × assumed |
| Productivity benefit | Completions of courses not on the compliance list × `hours_saved_per_completion` × `loaded_hourly_rate` | Measured × assumed |
| Measured impact | Σ entries: amount × `attribution_pct` × months of the entry inside P ÷ months of the entry | Entered |
| ROI % / BCR | (benefit − cost) ÷ cost × 100 / benefit ÷ cost; none when cost is 0 | — |

"On time" means snapshot status completed with `completion_date` ≤ `deadline_date`; exempted rows never count. Which
courses reach which tenant and cohort is asked of `compliance_engine` (`get_active_courses_for_scope()`,
`row_applies()`), never re-implemented. An "L&D spend only" switch removes participant time from cost (Q22). Pre/post
uplift, ratings and NPS (session 18, C5) are shown next to a training as evidence and never turned into money (Q24).
"Courses created in P", not "created or updated": `course.timemodified` moves on any settings save.

### D7 — No double counting

1. A completion earns at most one benefit line: a compliance-list course earns compliance, any other course productivity.
2. A training-month covered by a measured impact entry earns no assumed productivity.
3. In-house development cost is inside the L&D-staff baseline; the development estimate exists only where that line is missing.
4. Baseline categories are overheads not attributable to one training; the entry page says so, and the statement lists
   both bases, so an overlap Finance makes is visible (it cannot be detected automatically).
5. A learner-month belongs to exactly one org path (frozen at month close), so department totals cannot overlap.

### D8 — One allocation key, rollups that reconcile

The only allocation key is **learner-hours in the period** (participant time hours). Customer-wide baseline goes to
tenants by their share; a tenant's overhead to departments and trainings by theirs; a training's direct cost to
departments by that training's learner-hours per department. Overhead in a scope with no learner-hours stays in an
"Unallocated overhead" row, never dropped. Invariants, tested: Σ departments = Σ trainings + unallocated = tenant total;
Σ tenants = customer total, per currency. Per training, "Direct ROI" (direct cost only) and "Full ROI" (with its
overhead share) are shown side by side (Q23). A department is a prefix of the frozen `orgpath` at the depth the viewer
picks (business unit, department, sub-department: the compliance drill-down levels). Programs and learning paths roll
up their member trainings for display; in v2 they carry no cost lines of their own.

### D9 — Explainability

- Every line carries its source: **Measured** (LMS records), **Entered** (ledger; who and when on drill-down for
  holders of the manage capability), **Assumed** (assumption value, marked "default" until changed).
- A "How this is calculated" panel per line prints the formula with the period's numbers substituted, including the
  proration (amount × days ÷ days in FY) and the allocation share.
- The headline reads "Training ROI (model estimate)" with BCR beside ROI %, and "N% of the benefit rests on
  assumptions" (assumed benefit ÷ total benefit).
- Completeness warnings: a baseline category with no line for an FY in the period (a zero line is an answer and clears
  it); trainings with no direct cost; courses without a duration; compliance source missing.
- Drill-down: line → trainings → departments → ledger rows.
- Printable statement and Excel export (core dataformat): period, FY, currency, the assumption set with `validfrom`,
  freeze status per month, restated months, warnings, and the engine version ("ROI model 2.0").

### D10 — Who enters, who sees

- New capabilities: `local/sentientia_analytics:viewroi` (L&D admins and leadership, Q27) and `:manageroi` (tenant
  L&D admins for their own tenant, Q25). Customer-wide rows (`tenantid` 0) need cross-tenant authority (ADR-031).
- Views clamp with `permission::clamp_org_path()`; writes check the row's tenant with `tenant::require_access()`; reads
  use `tenant::path_descendant_filter()`; an unresolved tenant sees and writes nothing.
- Grants through archetypes and `permission::grant_to_default_roles()`, back-filled on upgrade, so Airpay's own
  admin roles hold them (the T-01 lesson: archetype-only grants missed the roles people actually have).
- CSV import for baseline and cost lines: dry run with a per-row report, then apply; the tenant is checked per row.
- Every ledger create, update and delete fires an event carrying old and new amounts, read by the platform audit log.

### D11 — Privacy

Ledger rows hold actor ids and free text (notes, evidence). The provider changes from `null_provider` to a metadata
and request provider: a subject's own entries are exported; on erasure the actor columns are anonymised and the rows
kept, because they are financial records. That retention needs the DPO's confirmation, like the audit-trail retention
ADR-035 leaves to the DPO. Facts hold no user id; the manager view persists nothing. The entry pages ask for no
personal data in notes.

### D12 — Build order

26a: twin drift closed; tables, capabilities, ledger flag, entry pages, CSV import, effective-dated assumptions,
events, privacy provider. 26b: facts task and freeze, engine, allocator, rollups, cache keyed by a per-customer ledger
version. 26c: card, panels, drill-downs, statement and Excel, en + hi, visual evidence. 26d: manager department view.
Tests: golden engine cases (proration across an FY boundary, each D7 rule, the D8 invariants, currency separation,
the missing-baseline fallback), tenant isolation (a /77 admin cannot write /1 or customer-wide rows), flag OFF means
no engine call, privacy provider. Turning any flag on stays Nitin's call.

---

## Consequences

- **Positive:** costs scale with the period; nothing is counted twice; every figure traces to an LMS record, a ledger
  line or a named, dated assumption. Per training, department and tenant views reconcile to one total. FY, currency
  and assumptions are per customer, so the model sells to Enterprise N unchanged. Stage B classroom costs gain a reader
  (Q26). Past months stop moving when people leave or move.
- **Negative:** v2 is only as good as Finance's figures: without a baseline the card shows warnings, not a confident
  number. Five tables, two capabilities, a scheduled task and a real privacy provider replace a stateless card.
  About 10 to 12 developer days (26a to 26d, plan §5.1). Rule D7.4 rests on Finance entering overhead net of training
  costs. The ROI card's period no longer follows the dashboard's range buttons, which needs a line in the card.
- **Neutral:** the flag key is unchanged and stays OFF. The seven v1 assumptions survive as the first assumption set
  where they still mean something.

---

## Alternatives considered

- **Keep v1 and tune the seven numbers.** The flaws are structural (no proration, double count, no per-training view);
  no setting fixes them. D20 asked for a full build. Rejected.
- **One cost basis.** Per-training costs only (Phillips per program, AN4) hide staff, platform and licence overhead and
  overstate ROI; a baseline only gives no per-training view. D21 chose both. Rejected.
- **Compute everything live from the raw tables.** Simple, but past months drift with leavers, movers and the hourly
  compliance rebuild, and department × training views get slow. Rejected for org views; kept for the small manager view.
- **Store computed money in monthly snapshots.** A late baseline or a corrected rate would then need a re-run before it
  shows. Facts are frozen, money is recomputed. Rejected.
- **A separate `local_sentientia_roi` plugin.** Cleaner privacy boundary, but the card, flag, cache, scope code and
  dashboard already live in analytics; a second plugin duplicates them. Rejected.
- **Turn uplift and ratings into money** (Q24 alternative). No defensible conversion; a CFO would strike it. Rejected.
- **Export to Finance's BI tool instead of an in-product model.** Loses tenant scoping and in-product explanation, and
  is not a product feature. The Excel export covers Finance's own tools. Rejected.

---

## Implementation actions

1. This week (no code): Nitin sends Finance the baseline request (FY 2025-26 and 2026-27, by Q21 category, per tenant;
   plan §5.4 item 5), and chooses Q20 to Q27 and open questions 1 to 4.
2. Sessions 26a to 26d as D12, each with tests, both trees, en + hi, a state-card entry and a PROJECT-STATE update.
3. Stage B, if Q26 is approved: an ADR-032 importer step writes BizLMS classroom cost rows as `local_roi_cost` lines
   (`source` = bizlms), and a facts rebuild covers the imported history months.
4. The ADR index row is added by the lead.

---

## References

- Plan: `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §3.2, §4 (AN3, AN4, AN5), §5.1, §5.3, §5.4, §5.5, §6, §6a.
- Code: `local/sentientia_analytics/classes/roi_calculator.php`, `settings.php`, `index.php`, `db/feature_flags.php`,
  `classes/permission.php`, `classes/privacy/provider.php`, `classes/analytics_manager.php`
  (`compliance_source_available()`); `local/sentientia_compliance_report/db/install.xml`,
  `classes/compliance_engine.php`, `classes/viewer_scope.php`; `local/sentientia_classroom/db/install.xml`;
  `local/sentientia_platform/classes/customer.php`, `classes/tenant.php`; `tools/tree-drift-baseline.txt` (repo root).
- ADR-002, ADR-008, ADR-021, ADR-029, ADR-031, ADR-032, ADR-035; ADR-036 when written.
- Phillips ROI methodology (plan §7).

---

## Open questions for future ADRs

1. **ZEEA currency and year:** does ZEEA report in TZS, and on Airpay's financial year or its own?
2. **Public tenant:** its learners are not Airpay employees. Default its `loaded_hourly_rate` to 0 (no productivity, no
   participant time)? Count course sales as a measured benefit line there?
3. **BizLMS course "cost" field** (Q5 sends it to the ledger): a training cost becomes a cost line; if it is a sale
   price it is revenue and stays with the commerce data. Which is it?
4. **Who is "leadership"** for `:viewroi`: an existing role, or a new read-only executive role?
5. **Hourly rate by band** instead of one per tenant: grade is empty in production (plan §5, finding 3), so not before HR sends it.
6. **Recurring compliance courses:** the snapshot holds one row per learner and course; should each annual refresher
   completion earn the compliance benefit again?
7. **Programs and learning paths** with their own cost lines (for example certification fees), or rollups only?
8. **Training Programs (ADR-036):** confirm the classroom is the training item and its companion course's completions
   and hours count toward it, not as a separate course.
