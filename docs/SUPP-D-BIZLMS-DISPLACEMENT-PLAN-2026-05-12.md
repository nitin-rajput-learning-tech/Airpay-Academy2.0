# Supplement D — BizLMS Displacement Detailed Plan

Companion to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`
Section 2.2 + Section 12.1. Augments `moodle-enhancement/FORK-PLAN.md`
with sequenced execution detail for the Q3 2026 displacement of the
remaining BizLMS plugins.

**Current state (12 May 2026):**

- 22 BizLMS plugins on disk, all **disabled** in `mdl_config_plugins`
  (commit `308dbfaa2` on 17 April 2026 — `Restore correct fork
  core_renderer.php + disable all 22 BizLMS plugins`).
- The `core_renderer.php` (2,339 lines) still calls
  `local_costcenter\accesslib` thirteen times and
  `local_courses\accesslib` five times. These callsites are what
  prevents the BizLMS plugin directories from being deleted outright.
- Two BizLMS schema extensions remain load-bearing: 39 `open_*` columns
  on `mdl_user` and 11 `open_*` columns on `mdl_course`. The platform
  depends on `open_path`, `open_managerid`, `open_costcenterid` on the
  user table at every tenant-scoped operation.

The displacement is the single largest remaining engineering effort on
the platform's roadmap. This document sequences the work over Q3 2026.

## 1. Inventory of remaining BizLMS dependencies

| Layer | Dependency | Callsites | Risk if not displaced |
|---|---|---|---|
| Theme renderer | `local_costcenter\accesslib::*` | 13 in `core_renderer.php` | Platform crashes if `local_costcenter` directory is removed. |
| Theme renderer | `local_courses\accesslib::*` | 5 in `core_renderer.php` | Same. |
| User profile | `mdl_user.open_*` columns | 39 columns referenced across plugins | Tenant scoping breaks. |
| Course | `mdl_course.open_*` columns | 11 columns referenced across plugins | Course visibility breaks. |
| Dashboard | BizLMS userdashboard block | 1 block ref | Dashboard layout breaks. |
| LearnerScript | BizLMS learnerscript block | 1 block ref | Reporting overlay loses some report types. |
| Skills | BizLMS local_skillrepository | Indirect | `airpay_skills` reads from local_skillrepository tables for legacy data. |

Total displacement scope: 13 + 5 = 18 renderer callsites, 50 user/course
schema columns, 2 blocks, 1 indirect plugin reference.

## 2. P0 displacement (critical path)

The 18 renderer callsites are the gating dependency. Until these are
replaced, the BizLMS plugin directories must remain on disk (even
disabled) because Moodle's autoloader will fail to resolve the class
references at theme-render time.

### Week 1 (Q3W1) — Map and design

**Owner:** Head of L&D.

**Deliverable:** Comprehensive mapping document at
`moodle-enhancement/BIZLMS-CALLSITE-MAP.md` listing each callsite, the
function called, what it returns, and the Airpay-owned replacement.

**Mapping pattern:**

```
core_renderer.php:142
  $costcenterid = \local_costcenter\accesslib::get_userid_costcenterid($USER);
  →
  $costcenterid = \local_airpay_core\tenant::root_for_current_user();
```

13 lines of `local_costcenter` calls + 5 lines of `local_courses` calls
= 18 total replacements needed. Each is mechanical; the design work is
in identifying the exact Airpay-owned function to call and (where the
replacement doesn't exist) building it in `local_airpay_org` or
`local_airpay_core`.

### Week 2 (Q3W2) — Ship the helpers + first renderer pass

**Owner:** Head of L&D.

**Deliverables:**

1. New helper methods on `\local_airpay_org\accesslib` (or a new
   `\local_airpay_core\renderer_helpers` class) covering every signature
   the renderer needs. 13 `local_costcenter` signatures + 5
   `local_courses` signatures = 18 new methods.

2. PHPUnit coverage on each new helper proving it returns the same shape
   as the BizLMS original. Snapshot-test pattern: feed identical input
   to both old and new helpers, assert structurally-equal output.

3. First pass on `core_renderer.php` replacing the easy 8 of 18
   callsites (the ones whose signatures map 1:1 to existing
   `local_airpay_org` methods).

### Week 3 (Q3W3) — Renderer second pass + UAT

**Owner:** Head of L&D.

**Deliverables:**

1. Replace the remaining 10 callsites. These require either new helper
   methods or moderate semantic translation (e.g. BizLMS returns a flat
   array; Airpay native returns an org-tree node — adapter required).

2. Run Phase 7 multi-role UAT against the post-displacement codebase.
   Expected: 84/85 baseline maintained.

3. Run all four plugin smokes (cart, proctoring, recompletion, request)
   against the post-displacement codebase. Expected: 84/84 baseline
   maintained.

4. Run the new helper PHPUnit suite. Expected: green.

5. axe-core a11y scan: no critical/serious regressions.

**Gate:** Cannot proceed to Week 4 until UAT 84/85, smoke 84/84,
PHPUnit green, axe-core clean.

## 3. P1 displacement (schema columns)

The 50 `open_*` columns on `mdl_user` and `mdl_course` are the
second-tier dependency. The platform reads these columns at every
tenant-scoped query (`open_path` is the canonical example).

### Week 4 (Q3W4) — Column audit + replacement table design

**Owner:** Head of L&D.

**Deliverable:** A column-by-column audit at
`moodle-enhancement/BIZLMS-COLUMN-MAP.md` listing each of the 50
columns, every callsite that reads it, and the proposed Airpay-owned
replacement.

Three replacement patterns:
- **Pattern A — Keep as-is.** Columns that are foundational and not
  worth moving (e.g. `open_path` is the canonical tenant path; we'll
  rename it to `airpay_path` in a future migration but the read
  semantics stay identical).
- **Pattern B — Move to a side table.** Columns that are airpay-specific
  rather than tenant-scoping (e.g. `open_managerid` becomes
  `local_airpay_users_manager.userid+managerid`). Cleaner separation
  of concerns.
- **Pattern C — Drop.** Columns that are unused or vestigial.

Initial estimate: 20 columns Pattern A (kept), 25 columns Pattern B
(moved), 5 columns Pattern C (dropped).

### Weeks 5-6 (Q3W5-6) — Side-table migrations

**Owner:** Head of L&D.

**Per-column migration steps:**

1. Create the side table in the relevant Airpay plugin's `db/install.xml`.
2. Write a one-off CLI migration script that copies data from the
   BizLMS column to the side table.
3. Run the migration in production (Saturday maintenance window).
4. Update every callsite to read from the side table.
5. After 30-day soak, drop the BizLMS column.

The 30-day soak is the safety net: if a callsite was missed, the
BizLMS column still holds the data and is queryable via the platform's
read-only emergency path.

### Week 7 (Q3W7) — BizLMS plugin directory removal

**Owner:** Head of L&D + IT.

**Deliverable:**

1. After all 18 renderer callsites are replaced and all P1 column
   migrations have completed their 30-day soak: physically delete the
   22 BizLMS plugin directories from `/var/www/moodle/`.
2. Drop the BizLMS-owned tables (`local_costcenter`, `local_users`,
   `local_courses`, etc.). Approximately 40 tables.
3. Drop any remaining `open_*` columns flagged Pattern C (drop).
4. Run a comprehensive verification: Phase 7 UAT + 4 plugin smokes +
   PHPUnit + axe-core.

**This is the cutover-equivalent moment for the displacement.** Treat
it with the same rigour as the v1→v2 production cutover: maintenance
window, backups, rollback plan documented per
`PHASE-8-DEPLOYMENT-RUNBOOK.md` § 7.

## 4. P1+ — Block displacement

After plugins, the two blocks:

### Week 8 (Q3W8) — userdashboard block displacement

**Owner:** Head of L&D.

**Deliverable:** Replace the BizLMS `userdashboard` block with an
Airpay-owned `block_airpay_dashboard`. The existing dashboard PHP at
`theme/airpayux/layout/dashboard.php` already drives most of the
rendering; the block's role is the block-region wrapper.

### Week 9 (Q3W9) — LearnerScript replacement strategy

**Owner:** Head of L&D + Mgmt decision.

**Two options:**

- **Option A — Keep LearnerScript as a bundled third-party block.**
  LearnerScript is an open-source Moodle block (not BizLMS-specific). It
  is the canonical report-builder layer. Keeping it long-term is a
  reasonable choice; document it as a third-party dependency rather than
  a BizLMS dependency. This is the recommended path.

- **Option B — Build an Airpay-native reporting tier.** Significantly
  more work. Only justified if LearnerScript's licensing or upgrade path
  becomes problematic.

The LEARNERSCRIPT-P3-DEFERRAL document at the project root tracks the
features held in abeyance pending this decision.

## 5. P2 — Indirect dependencies

`airpay_skills` reads from `local_skillrepository` tables for legacy
data. After the P0 + P1 work above, this is the only remaining indirect
dependency on a BizLMS plugin.

**Approach:** one-time data migration script that copies
`local_skillrepository_*` rows into `local_airpay_skills_*` tables.
Drop the legacy tables after a 30-day soak.

**Effort:** ≈ 8 hours.

## 6. Cumulative effort estimate

| Workstream | Effort | Calendar |
|---|---|---|
| P0 — renderer callsites | 60-80 h | Q3W1-3 |
| P1 — schema columns | 80-120 h | Q3W4-6 |
| P1 — plugin directory removal | 16 h | Q3W7 |
| Block displacement | 20-30 h | Q3W8 |
| LearnerScript decision + execution | 0-40 h | Q3W9 |
| Indirect (skills repository) | 8 h | parallelisable |

**Total:** 184-294 hours over 9 calendar weeks. Approximately one full-
time engineer working 50% on displacement and 50% on other backlog.

The hire (Decision 13.3) materially changes this calendar. With a
dedicated engineer, displacement compresses to 6 weeks. Without, it
stretches to 12-14 weeks of part-time-from-Head-of-L&D effort.

## 7. Validation gates

After every weekly milestone, every gate below must clear before
proceeding to the next week's work. No exceptions.

| Gate | Pass criterion |
|---|---|
| PHPUnit suite | All existing tests pass + new helper tests added per week |
| Phase 7 multi-role UAT | 84/85 baseline (1 known transient flake) |
| Plugin smokes (cart, proctoring, recompletion, request) | 84/84 baseline |
| axe-core a11y scan | 0 critical, 0 serious violations on the affected surfaces |
| Manual visual check on 3 personas (admin, employee, public-tenant) | No regression visible |
| Capability check on the affected surfaces | Site admin / tenant admin / employee see the right things |

## 8. Risk register specific to this workstream

(Cross-references Supplement A.)

| Risk | Severity | Likelihood | Mitigation |
|---|---|---|---|
| Renderer regression — a replaced callsite returns subtly different data | H | M | Snapshot test pattern (compare BizLMS vs Airpay output structurally on a frozen dataset) |
| Schema migration data loss | H | L | 30-day soak before dropping any column; verified backups before each migration window |
| Performance regression — Airpay-owned helpers slower than BizLMS originals | M | M | k6 baseline before + after each weekly milestone |
| LearnerScript breaks on `local_costcenter` removal — it has its own dependency | M | L | Verified in week 1 mapping; LearnerScript depends on `mdl_course_categories` (Moodle core) not `local_costcenter` |
| Indirect dependency surfaces mid-displacement (a plugin we forgot is using BizLMS) | M | M | Static analysis on the codebase before each week; `grep -r 'local_costcenter\|local_courses\\\\\\\\' moodle-enhancement/local/` runs as a CI check |

## 9. Communication plan

- **Internal:** weekly Friday status email to the leadership team
  summarising the week's milestone, gate-pass results, and the next
  week's plan.
- **External (Public tenant):** displacement is invisible to external
  learners. No comms required.
- **Outage windows:** each schema-column migration is a one-hour
  Saturday-evening maintenance window. Comms template per
  `PHASE-8-DEPLOYMENT-RUNBOOK.md` § 9.

## 10. Done criteria

The workstream is done when:

1. Zero references to `local_costcenter\*` or `local_courses\*` classes
   anywhere in the Airpay-owned codebase (verified by `grep -rn`).
2. Zero `open_*` columns in `mdl_user` or `mdl_course` other than those
   intentionally retained Pattern A.
3. All 22 BizLMS plugin directories deleted from `/var/www/moodle/`.
4. All BizLMS-owned database tables dropped (or renamed to
   `legacy_*` if a Pattern B migration retained the data shape).
5. Full Phase 7 UAT + 4 plugin smokes + PHPUnit suite passes.
6. The `FORK-PLAN.md` updated to reflect completion status.
7. Two-quarter steady-state operation with no regression attributable
   to the displacement.

Until step 7 completes, the BizLMS plugin tarballs remain available in
an "emergency restore" S3 bucket. The bucket is documented in IT's
runbook; access is restricted to the Head of L&D + IT lead.
