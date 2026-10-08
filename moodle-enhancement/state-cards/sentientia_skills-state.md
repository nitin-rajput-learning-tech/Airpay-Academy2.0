# State Card — `local_airpay_skills`

**Component:** `local_airpay_skills`
**Version:** `2026052003` / `1.6.2`  (+ P1 #32 full Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Skills catalog + per-user skill history.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Skills framework — define a per-customer skills catalogue, map skills
to roles (designation matrix) and to courses, capture per-user skill
levels with history tracking. Feeds `local_sentientia_leaderboard`'s
`skill` board type via `local_airpay_user_skill_hist`.

## DB tables (7)

| Table | Purpose |
|-------|---------|
| `local_airpay_skill_cats` | Skill categories (groupings) |
| `local_airpay_skills` | Skill catalogue (per-tenant) |
| `local_airpay_skill_levels` | Per-skill level definitions (e.g. Beginner / Intermediate / Expert) |
| `local_airpay_role_skills` | Role × skill mapping (designation matrix) |
| `local_airpay_course_skills` | Course × skill mapping (what skills a course teaches) |
| `local_airpay_user_skills` | Current per-user skill levels |
| `local_airpay_user_skill_hist` | Append-only history (every level change recorded) |

## Capabilities (3)

`local/airpay_skills:` `view`, `manage`, `self_rate`. Self-rate lets
learners declare their own initial level (cap-limited).

## Feature flags

None registered. Consumed downstream by
`sentientia.leaderboards.type.skill` flag in
`local_sentientia_leaderboard`.

## Key files

```
local/airpay_skills/
├── version.php                                   2026052003 / 1.6.2
├── README.md
├── admin.php                                      Admin operations
├── index.php                                      Skill catalogue list
├── course_mapping.php                             Course × skill mapping UI
├── designation_matrix.php                         Role × skill matrix UI
├── cli/                                            Operations
├── classes/
│   ├── skills_manager.php                        Catalogue CRUD + history writer
│   ├── observer.php                              course_completed → level up
│   ├── external/                                  WS endpoints
│   ├── form/                                      Forms
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                7 tables
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── amd/
├── lang/
│   ├── en/local_airpay_skills.php
│   └── hi/local_airpay_skills.php                 (100% parity post-P1 #32)
└── tests/
    ├── skills_manager_phase_a_test.php           13 methods
    ├── external/list_skills_test.php             5 methods
    └── privacy/provider_test.php                  5 methods (23 total)
```

## Tests

3 PHPUnit classes, 23 methods. `skills_manager_phase_a_test` covers
the catalogue CRUD + level-history writer. `privacy/provider_test`
covers the per-user export + delete.

## Open items

- [ ] Per-customer skill taxonomy presets (today: blank-canvas per
      tenant)
- [ ] Skill gap analysis report (today: catalogue + per-user only)
- [ ] Skill-based course recommendation hook into
      `ai.recommendations.enabled` feed
- [ ] Behat coverage of the designation-matrix grid
- [ ] Skill-decay rule (level lapses after N months without practice)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. The `user_skill_hist` table is the
data source for the `local_sentientia_leaderboard` skill board type.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031: :manage is a platform capability (1.6.4, 2026092500)

Sweep hits `local/sentientia_skills:manage` and `:view` (both CONFIRMED).

- **`:manage`:** the skills catalogue has no tenant column; it is one catalogue shared by every tenant. So `:manage` no longer defaults to the manager archetype (`archetypes => []`, `RISK_DATALOSS` added). Upgrade step 2026092500 `unassign_capability()`s it from every role. Site admins keep it. **Action for Nitin:** grant it deliberately to the platform L&D role, alongside `:crosstenant`.
- **Backfill:** backfilling another user's level (`self_rate_skill`) requires `tenant::require_same_tenant_user()`.
- **Writes and pickers:** course-skill mapping writes check the course's tenant; legacy courses with no path are cross-tenant only. `delete_skill` is cross-tenant only, because it erases every tenant's learners' levels. `search_courses()` and `list_designations()` are scoped.
- **`:view`:** its student default is kept, because learners use view.php to self-rate. The learners tab (`skill_learners()` / `count_skill_learners()`) and the courses tab are tenant-scoped; the learners tab used to show up to 200 names and emails from every tenant.
- **`index.php`:** the `local/courses:manage` path to another user's gap analysis is limited to users in the caller's tenant.
- **CLI:** `cli/smoke_course_mapping.php` now runs as the site admin, because `search_courses()` is session-scoped.
- **Tests:** `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 wave-1 review follow-up (no version change; stays 2026092500)

- **Course picker was half-scoped.** `search_courses()` was tenant-scoped, but `course_mapping.php`'s initial top-25 list, `get_course_summary()` and `list_course_skills()` (page and web service) still named, and listed the mappings of, any tenant's course by id. All four now share one scope, `course_scope_sql()` (`tenant::scope_path()` + `path_descendant_filter()`): new `skills_manager::top_courses()` and `can_view_course()`. A foreign course reads as not found (null / no rows); a caller with no tenant sees none; a cross-tenant caller sees all.
- **Tests:** `test_course_mapping_reads_are_tenant_scoped` in `tests/tenant_scope_test.php`.

**Deploy note (deviation, from the wave-1 review).** Revoking the `:manage` default also takes genuine in-tenant functions away from tenant admins: mapping skills onto their own courses and backfilling their own users' levels. Before this reaches UAT, Nitin must grant `local/sentientia_skills:manage` and `local/sentientia_platform:crosstenant` (and, if wanted, `local/sentientia_skillsai:manage_all`) to the platform L&D role. A tenant admin who should keep in-tenant mapping needs `:manage` granted explicitly; the tenant checks above then keep them in their tenant.

**Still open (in-tenant):** the `:view` student archetype still shows same-tenant learners' names and emails on view.php's learners tab.

## 2026-09-25 - ADR-031 fix-forward 2: gap courses + one legacy-course rule (no version change; stays 2026092500)

From the adversarial review of claude/adr031-assessment-ff.

- **Gap-course recommendations were unscoped.** `get_gap_courses()` joined `course_skills` to `course` with no tenant filter, so a /1 learner was recommended /177 course names and links (skills `index.php`, `lib.php` dashboard data, theme dashboard, `sentientia_users` profile). It now applies the LEARNER's read scope (own tenant + legacy courses; every course for a cross-tenant learner; nothing for a learner with no tenant), and, when someone else is viewing, the viewer's read scope too. The filter runs before the per-skill limit of 2 (now a portable limit argument instead of a raw `LIMIT 2`).
- **One legacy-course rule.** A course with `open_path` NULL or '' (the same thing) was read three different ways (view.php read both, `course_scope_sql()` hid both from reads, skillsai read NULL only). Now: `course_read_scope_sql()` (public) - own tenant + NULL + '' - for view.php's Courses tab and count, `can_view_course()` (`list_course_skills`, `get_course_summary`) and `get_gap_courses()`; `course_write_scope_sql()` / `can_write_course()` - own tenant only - for `require_course_write_scope()` (save/delete mapping) and the mapping page's pickers (`search_courses`, `top_courses`), which exist to choose what to map and so never offer a course the save would refuse. Cross-tenant: everything both ways. `course_scope_sql()` is gone. Backfill (`self_rate_skill` for another user) is user-scoped and was already `require_same_tenant_user()`.
- **Tests:** `tests/tenant_scope_test.php`: writes refuse NULL and '' legacy courses (save and delete), `test_legacy_courses_are_readable_but_not_writable`, `test_gap_courses_are_scoped_for_the_learner`.
- **Found, not fixed (pre-existing, UI):** skills `index.php` reads `get_gap_courses()` rows as objects (`$r->coursename`, `$r->teaches_level`) but they are arrays with `fullname`, so its recommendations render blank. Needs a UI fix with screenshots.
- **Not run here:** PHPUnit.

## 2026-09-25 - ADR-031 follow-up: course mapping is a tenant function, the catalogue is not (1.6.5, 2026092501)

From the cross-cutting review of the merged wave (P1, CONFIRMED): revoking `:manage` also took
skill mapping of their OWN courses away from tenant admins. The documented remedy, "grant `:manage`
explicitly", would have reopened global writes, because only `delete_skill` checked
`is_cross_tenant()`.

- **New capability `local/sentientia_skills:mapcourses`** (manager archetype default, `RISK_CONFIG`,
  en + hi strings). It gates `course_mapping.php` and the `list_course_skills`, `save_course_skill`,
  `delete_course_skill` and `search_courses` web services, through
  `skills_manager::require_map_courses()`, which accepts `:mapcourses` or `:manage`. Every course
  those surfaces touch is still held to the caller's tenant (`require_course_write_scope()`,
  `can_view_course()`, the scoped pickers). Moodle grants the archetype default when the capability
  is installed on upgrade, so manager-archetype tenant admins (UAT role 9) can map their own
  tenant's courses again. db/services.php names `:mapcourses` for those four functions.
- **Catalogue writes are cross-tenant only, in code.** The new
  `skills_manager::require_catalogue_write()` checks `:manage` and then `tenant::is_cross_tenant()`
  (new string `error_catalogueplatformonly`, en + hi). It gates `copy_designation`,
  `delete_category`, `save_designation_skill`, `delete_designation_skill`, `save_skill_level`, the
  four dynamic forms (`edit_skill`, `edit_category`, `edit_skill_level_dynamic_form`,
  `edit_designation_skill_dynamic_form`) and the pages `admin.php`, `designation_matrix.php` and
  `level_definitions.php`. `delete_skill` keeps its own check (`error_outoftenant`). `:manage` stays
  revoked (step 2026092500 is unchanged). Reads of the global catalogue (`list_skills`,
  `get_skill_levels`, `list_designation_skills`) still need `:manage` only.

**Deploy note (corrects the 2026-09-25 one above).** Do NOT grant `:manage` to tenant admins to
restore course mapping. They get it from `:mapcourses` on upgrade. Granting `:manage` to a
tenant-admin role no longer opens catalogue writes (they need `:crosstenant`), but it does open the
catalogue read web services and backfilling another same-tenant user's level (`self_rate_skill`
with a userid). The platform L&D role that curates the framework needs `:manage` AND
`local/sentientia_platform:crosstenant`.

**Still open (UI, not changed here):** course_mapping.php's "Skills Management" back link and
breadcrumb, and theme_sentientia's sidebar "Skills" entry, point at admin.php, which tenant admins
cannot open. A follow-up needs screenshots: hide the link, or point tenant admins at
course_mapping.php. Backfill for tenant admins (`self_rate_skill` for another user) still needs
`:manage`. This is a product decision: it could get its own capability or stay platform-only.

Tests: new `tests/mapcourses_scope_test.php` (`@group tenant_isolation`). The existing
`tenant_scope_test` needs no change (its `:manage` holders also pass `require_map_courses()`). Not
run here (no PHPUnit, as instructed).

## 2026-09-30 - persona pass bundle "Admin gates" (D9)

Branch `claude/persona-fix-admingates`. `index.php` (My Skills, viewing another user's gap analysis):
`$hasmanagecap` used the retired BizLMS `local/courses:manage`; now `local/sentientia_courses:manage`
(ADR-025 successor). The ADR-031 `tenant::require_same_tenant_user()` after the gate is unchanged, so a
holder still cannot open a user in another tenant. Guard: `local_sentientia_platform`
`tests/capability_names_test.php`. No version bump.

## 2026-09-30 - ADR-032 BizLMS import: the skills feature (1.7.0, 2026093001)

Branch `claude/bizlms-import-skills`. Mapping doc section 14. Both trees (`local/` and
`moodle-enhancement/local/`) carry the same files; the 2026061700 repaint step that only the top-level
`db/upgrade.php` had is now in both, and its line is gone from `tools/tree-drift-baseline.txt`.

**Importer** (`classes/bizlms/`, registered in `db/bizlms_import.php`, feature `skills`, depends on `org` and
`recompletion`, not atomic). Steps, in order:

| Step | Source (accounting unit) | Target | Ids |
|---|---|---|---|
| `skills.levels` | `local_course_levels` | `local_sentientia_course_levels` (NEW) | PRESERVE: `course.open_level` stores the id |
| `skills.categories` | `local_skill_categories` | `local_sentientia_skill_cats` | MAP; exact case-insensitive name match to a category the import did not create folds into it |
| `skills.skills` | `local_skill` | `local_sentientia_skills` | MAP; never merged into the 48 seeded skills |
| `skills.course_skills` | `#local_skill.courses` (one group per legacy skill) | `local_sentientia_course_skills` | MAP; one sub-row per further course (`course:<id>`) |
| `skills.user_skills` | `#course_completions.userid` (one group per learner, keyed by the lowest completion id) | `local_sentientia_user_skills` + `_user_skill_hist` | MAP; sub-rows `skill:<id>` and `hist:<id>_<n>` |
| `skills.interests` | `local_interested_skills` (grouped by learner) | `local_sentientia_skill_interest` (NEW) | MAP; one row per skill, sub-rows `skill:<id>` |

- **The level map is the owner's, written out.** `skills.level_proficiency` is approved as a rule (name
  heuristic) but its `csv` is null in the signed file, so the preflight BLOCKS with
  `level_proficiency_csv_missing`; a csv that misses a level blocks with `level_proficiency_csv_incomplete:<ids>`
  and a value outside 1..5 with `level_proficiency_csv_invalid`. Nothing is guessed. While it blocks, the
  preflight warning `level_proficiency_suggested_csv:<id>,<level>;...` applies the heuristic to every level
  name, to paste into the decisions file after review. The csv takes `levelid,proficiency` lines (or `;`, `=`, `:`)
  or a JSON object.
- **Completions -> levels and history.** One chronological pass per learner and skill, like
  `update_from_course()`: a level is only raised; `source` is the owner's label (`import`); a completion that does
  not raise the level writes no history row; the recompletion archive (`local_recompletion_cc`) is history too
  (`skills.history_from_archive`). A learner that already has a row for the skill keeps it (folded, reason
  `native_row_kept`) and the missing history rows are still written. Deleted users are archived, unknown users
  skipped. `course_completions` is claimed as a source only while a BizLMS skill table exists.
- **No side effects.** No `course_completed`, no `skills_manager` call, no enrolment, message or event. Cron must
  still be off for the window (a `course_completed` event after `course_skills` rows exist would write a native
  row stamped `time()`).
- **Schema (version 2026093001, both trees).** `skill_cats` and `skills`: name widened to 255 (form maxlengths
  raised), `+ idnumber` (index), `+ open_path`. NEW `local_sentientia_course_levels` (UNIQUE code) and
  `local_sentientia_skill_interest` (UNIQUE userid, skillid). `upgrade.php` step is guarded and idempotent.
- **Privacy.** `skill_interest` is declared (metadata, users in context, export, all three deletes); en + hi
  strings. The catalogue tables still hold no personal data (`usercreated` / `usermodified` are not copied).

**Reader code fixes of section 14.** (1) catalog level label reads `local_sentientia_course_levels.name`
(`catalog_manager::course_level_label()`); (2) `sentientia_users/skillprofile.php` recommendations use the gap
engine's course read scope (viewer's too), leave out completed courses, and pass the limit to the DB layer;
(3) `view.php` levels tab reads columns `level` / `label`; (4) `index.php` reads the arrays `get_gap_courses()`
returns, which now carry `teaches_level` and `reason`; (5) source codes are lang strings, en + hi; (6) privacy, above;
(7) interests: `skills_manager::get_interest_skills()`, `get_interest_courses()` and `get_recommended_courses()`,
used by the My Skills page, the skill profile chips and the theme's recommendation rail, all behind the EXISTING
flag `sentientia.dashboard.skillsrecs.enabled` (default OFF); (8) not built: the catalogue is shared
(`skills.catalogue_scope = shared`); (9) `sentientia_org/cli/disable_bizlms.php` claims "Merged into
sentientia_skills" only when the `skills` completion marker exists.

**New flag.** `sentientia.skills.heldskills.enabled` (default OFF): My Skills lists what the learner holds (level,
source words, date) when the designation has no role skills to compare against.

**Where this differs from the mapping doc** (none changes what is imported):
- The course links are grouped per LEGACY SKILL (`#local_skill.courses`), not per course (`#course.open_skill`).
  The registry gives a table one owner and the exams feature also derives from core `course` rows, so claiming
  `course` here would make the two importers refuse to load together. The target rows are the same.
- The user-skill group is keyed by the learner's lowest `course_completions.id`; sub-row keys are `skill:<id>`
  and `hist:<id>_<n>` (the doc said subkey `hist`).
- A legacy category merged into a seeded one is recorded `folded` (target = the seed), because `outcome::merge()`
  names a winning SOURCE row.
- `user_skills.timecreated` of a native row is not lowered to the earliest completion: the importer may only
  change rows it created.
- The contract trait's "not applicable" test drops every claimed table, which would include the core table
  `course_completions`, so this plugin's test overrides it and drops the four legacy tables only.

**Tests** (not run here: no PHPUnit, as instructed). `tests/bizlms_import_test.php` (`@group bizlms_import`,
`tenant_isolation`): the importer contract on a seed built from the mapping doc's fixture section, plus the level
map block, the signed file blocking the feature, the tenant rules, the native row, the source label, the
archive switch, the preflight facts and the catalog label. `tests/bizlms_readers_test.php`: the new readers and
both flags. `tests/privacy/provider_test.php` updated (its metadata count was stale) and extended. Fixture:
`tests/fixtures/bizlms/skillrepository.install.xml` (verbatim copy with header; `local_recompletion_cc` appended,
TEXT `interested_skill_ids`). `tests/classes/bizlms/stub_dependency.php` stands in for `org` and `recompletion`.

**Still to do.** Fill `skills.level_proficiency.csv` from the Stage B preflight; accept the needs-owner reasons
after the rehearsal; visual evidence for the My Skills page (held skills, interest chips), the skill page levels
tab and source line, the skill profile chips and the catalog level badge (not captured: nothing was deployed).


## 2026-10-07 - owner decision LRN-07: the level map is filled; the preflight reports any deviation from the rule

Branch `claude/owner-decisions-y`, both trees. No schema, capability or flag change, so **no version bump**. **Written, not run**: PHPUnit runs after the merge. php -l, the fixture-copy gate and the tree gates were run.

**Decision (delegation "self review and decide recommended option", critic-checked).** `skills.level_proficiency.csv` in `docs/cutover/bizlms-import-decisions.json` is now `1,2;2,3;3,4;4,5;5,1;7,2;8,3;9,4;10,5;11,2;12,3;13,4;14,5;15,1;16,2;17,3;18,4`: the approved name rule applied to the 17 April levels (ids 1-5 and 7-18, checked with `level_map::suggest()`), then reviewed. Levels 5 and 15 are general, non-levelled labels and take the default 1. **Level 16 is the plural of the rule word "basic", so the literal word match gives 1; the review sets it to 2, the first rung of the /177 ladder (16, 17 intermediate, 18 advanced).** Both fixture copies (`local/` and `moodle-enhancement/local/sentientia_platform/tests/fixtures/bizlms/`) carry the same file (`tools/check-bizlms-fixture-copies.php` passes). April courses use levels 1-5, 7-10, 16 and 17; levels 11-15 sit under root 80 (not a registered tenant) and no course uses them. Unblocks `skills` and `learningplan` (which depends on it: 2,071 learning-plan enrolments) at the Stage B rehearsal.

**Code.** `importer::preflight_level_map()` adds the warning `level_proficiency_differs_from_rule:<ids>` for every csv entry that differs from what the owner's rule gives for the level's CURRENT name, on every run. The reviewed deviation (16 -> 2) and any level renamed since the csv was written therefore show before the hash is pinned. It is a warning, never a block. A new level id with no csv entry still blocks (`level_proficiency_csv_incomplete`).

**Tests.** `test_the_signed_decisions_file_carries_a_complete_level_map` (the signed copy parses with no problems, holds exactly the 17 April levels, 5 and 15 are 1, 16 is 2); `test_a_csv_entry_that_differs_from_the_owners_rule_is_reported_on_every_run`. The old `test_the_signed_decisions_file_blocks_the_feature_until_the_level_map_is_filled` now skips itself (its own guard: the csv is filled).

**Stage B runbook line.** Re-run `--preflight` for skills on the live backup BEFORE the decisions hash is pinned; if a level is new or renamed, update `csv` (and both fixture copies, then re-pin). ADR-032 "Left unanswered on purpose" (line 1124) and the mapping doc section 14 still say the csv is open: the lead's doc batch records the decision (key `skills.level_proficiency`, the same one, now with its csv and the "[proposed + review]" why).

## 2026-10-08 Moodle 5.3 compat FX-20 (version 2026100801)

The learner self-rate dialog is a `core/modal_save_cancel` dialog. It used `window.bootstrap.Modal` (exists in no tree) and fell back to the theme's Bootstrap 4 jQuery plugin. `templates/view.mustache` now renders an inert hidden source block (`#airpay-self-rate-modal`, `d-none`) holding the localised title, the save label and the level select; the first click on Self-rate builds the dialog and moves the select block into it (ids stay unique), later clicks show the same dialog. The save handler keeps the dialog open while the request runs (spinner on the Save button), shows the existing "pick a level" warning for an empty level, and reloads on success. The title is passed as already-escaped HTML. `amd/src/skill_actions.js` is now identical in both trees (the baselined drift line is removed); `amd/build/skill_actions.min.js` rebuilt (plus a source map). No schema change. Visual evidence owed.