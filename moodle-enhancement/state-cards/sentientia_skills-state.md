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
