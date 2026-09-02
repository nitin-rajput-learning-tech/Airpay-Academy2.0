# State Card — `local_sentientia_gamification`

**Component:** `local_sentientia_gamification` (renamed from `local_airpay_gamification`, ADR-025 Class B)
**Version:** `2026090200` / `1.0.2-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Deployed on the Airpay Academy customer-zero build. Points + badges + streaks engine.
**Last refreshed:** 2026-09-02 (vanilla-schema crash fix + first PHPUnit)

---

## Mission

Gamification primitives — points awarded for actions (course completion,
quiz attempt, daily login), badges earned by threshold, daily streaks.
Data source for the dashboard "Your achievements" tile and for
`local_sentientia_leaderboard` (indirectly, via the user_skill_hist
hand-off).

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_sentientia_points_log` | Append-only points-event log |
| `local_sentientia_badges` | Badge catalogue (seeded on install by `lib.php`) |
| `local_sentientia_user_badges` | Per-user earned badges (unique userid+badgeid) |
| `local_sentientia_streaks` | Per-user daily-login streak counter + cached total_points |

## Capabilities

None declared explicitly — visibility gated by login.

## Feature flags

Consumed (registered in `local_sentientia_platform`):
- `engagement.gamification.enabled` (master switch — default ON)
- `engagement.gamification.confetti` (course-completion confetti — default ON)

When `engagement.gamification.enabled` is OFF, the dashboard widget
hides itself, observers stop awarding points on course completion,
and the leaderboard link is removed from navigation.

## Schema dependence (BizLMS `open_path`)

`badge_manager` scopes two criteria to the learner's tenant using the
BizLMS-injected `open_path` column on `{user}` / `{course}`
(`compliance_complete`, `leaderboard_top10`). That column is **not** in
any install.xml we own — it comes from the legacy costcenter plugin — so
a vanilla Moodle schema (PHPUnit DB, a non-BizLMS Sentientia customer)
does not have it. Since `2026090200` the lookup probes the schema via
`$DB->get_manager()->field_exists()` and falls back to site-wide scope;
on Airpay production (column present) the SQL is unchanged.

The legacy `classes/leaderboard.php` still reads `open_path` unguarded
(dashboard summary path, not the award chain) — see Open items.

## Key files

```
local/sentientia_gamification/
├── version.php                                  2026090200 / 1.0.2-beta
├── README.md
├── lib.php                                      seed_badges() + dashboard summary
├── styles.css
├── classes/
│   ├── points_manager.php                       Points event writer + tally → check_badges()
│   ├── badge_manager.php                        Badge catalogue + earn engine (schema-safe tenant lookup)
│   ├── leaderboard.php                          (legacy — superseded by local_sentientia_leaderboard)
│   ├── observer.php                             course_completed / quiz / login / cm_viewed → award points
│   └── privacy/provider.php
├── db/
│   ├── install.xml                              4 tables
│   ├── install.php                              seeds 10 default badges
│   ├── events.php                               4 observers
│   └── upgrade.php
├── tests/
│   └── badge_manager_test.php                   award chain, vanilla + BizLMS schema
├── templates/
└── lang/  en · hi · kn · mr · sw
```

Duplicate tree: `local/sentientia_gamification/` mirrors
`moodle-enhancement/local/sentientia_gamification/` — keep both in sync
(the 2026-09-02 fix was applied to both).

## Tests

`tests/badge_manager_test.php` — 2 tests, run via
`--testsuite local_sentientia_gamification_testsuite`:

- `test_course_completed_awards_points_and_badges_on_vanilla_schema` —
  drops `{user}.open_path` for its duration (DDL survives
  `resetAfterTest()`), fires `course_completed`, asserts the observer
  neither throws nor debugs, 100 + 150 points land, the streak row caches
  250, and `first_course` + `leaderboard_top10` badges are earned.
- `test_tenant_scoped_criteria_still_apply_with_bizlms_schema` — with the
  `bizlms_fixture` columns present, a tenant-1 user completing the only
  tenant-1 mandatory course earns `compliance_complete` while a tenant-77
  mandatory course is ignored.

Master-flag coverage remains in `local_sentientia_platform`
feature_flags_test + the dashboard widget tests.

## Open items

- [ ] PHPUnit for `points_manager` award rules (dedup window, level bands)
- [~] PHPUnit for `badge_manager` — award chain + tenant criteria covered
      (2026-09-02); threshold matrix (courses_completed 5/10/25,
      points_total, streak_days, quizzes_perfect) still open
- [ ] `classes/leaderboard.php` (legacy) reads `open_path` unguarded at
      lines 29–92 — same vanilla-schema failure class as the 2026-09-02 fix;
      migrate callers to `local_sentientia_leaderboard` or reuse the
      `field_exists()` guard
- [ ] Cross-plugin: `local_sentientia_evaluation\observer::course_completed`
      has the same `open_path` dependence and debugs on a vanilla schema
      (seen while testing this plugin; not touched here)
- [ ] Tenant prefix match `open_path LIKE '/1%'` also matches `/177…`
      (ZEEA) — pre-existing, behaviour preserved in the 2026-09-02 fix
- [ ] Per-customer points-policy override (today: site-wide rules)
- [ ] Achievement notifications via `local_sentientia_notifications`
      (today: dashboard-only)
- [ ] Streak-restore mechanic (one-time per quarter or admin-granted)

## 2026-09-02 — vanilla-schema crash in `badge_manager` (`2026090200`)

Symptom: `local_sentientia_api` `webhooks_test::test_course_completed_event_enqueues_delivery`
reported an "Unexpected debugging() call detected" PHP notice whose trace
ran `badge_manager.php:80 ← :32 ← points_manager.php:72 ← observer.php:38`.

Root cause: `meets_criteria()` selected `{user}.open_path` for the seeded
"Compliance Champion" badge. The PHPUnit DB has no such column
(`Unknown column 'open_path' in 'SELECT'`), the `dml_read_exception`
escaped the observer, `core\event\manager` swallowed it into
`debugging()`, and the award chain aborted after the first 100 points
(no badge check, no `first_course` bonus). Not a duplicate-row or
undefined-key issue.

Fix: two private helpers, `has_tenant_column()` (DDL `field_exists`,
answered from cached metadata) and `get_user_tenant()` (`get_field` +
the same `explode('/')[1]` / `!empty` parsing as before), used by both
tenant-scoped criteria. Production SQL and params are byte-identical
when the columns exist.

The "1 PHPUnit deprecation" seen in the same run is PHPUnit 11 flagging
`@covers` doc-comment metadata on the api test class (repo-wide
convention, PHPUnit 12 prep) — unrelated to this plugin. The new test
uses `#[CoversClass]` attributes and adds none.

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. The `leaderboard.php` class is
legacy — new code should use `local_sentientia_leaderboard`.
