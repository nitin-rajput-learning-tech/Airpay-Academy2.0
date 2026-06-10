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
