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
