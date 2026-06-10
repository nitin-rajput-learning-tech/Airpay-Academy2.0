# State Card — `local_airpay_gamification`

**Component:** `local_airpay_gamification`
**Version:** `2026052001` / `1.0.1-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. Points + badges + streaks engine.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

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
| `local_airpay_points_log` | Append-only points-event log |
| `local_airpay_badges` | Badge catalogue (per-tenant) |
| `local_airpay_user_badges` | Per-user earned badges |
| `local_airpay_streaks` | Per-user daily-login streak counter |

## Capabilities

None declared explicitly — visibility gated by login.

## Feature flags

Consumed (registered in `local_airpay_core`):
- `engagement.gamification.enabled` (master switch — default ON)
- `engagement.gamification.confetti` (course-completion confetti — default ON)

When `engagement.gamification.enabled` is OFF, the dashboard widget
hides itself, observers stop awarding points on course completion,
and the leaderboard link is removed from navigation.

## Key files

```
local/airpay_gamification/
├── version.php                                  2026052001 / 1.0.1-beta
├── README.md
├── lib.php
├── styles.css
├── classes/
│   ├── points_manager.php                       Points event writer + tally
│   ├── badge_manager.php                        Badge catalogue + earn engine
│   ├── leaderboard.php                          (legacy — superseded by local_sentientia_leaderboard)
│   └── observer.php                             course_completed → award points
├── db/
│   ├── install.xml                              4 tables
│   └── upgrade.php
├── templates/
└── lang/
    ├── en/local_airpay_gamification.php
    └── hi/local_airpay_gamification.php
```

## Tests

None at the plugin level. Coverage is on the master flag (in
`local_airpay_core` feature_flags_test) + the dashboard widget tests.

## Open items

- [ ] PHPUnit for `points_manager` award rules (priority)
- [ ] PHPUnit for `badge_manager` threshold + dedup
- [ ] Migrate `leaderboard.php` callers to `local_sentientia_leaderboard`
      (legacy class retained for backwards compatibility)
- [ ] Per-customer points-policy override (today: site-wide rules)
- [ ] Achievement notifications via `local_airpay_notifications`
      (today: dashboard-only)
- [ ] Streak-restore mechanic (one-time per quarter or admin-granted)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. The `leaderboard.php` class is
legacy — new code should use `local_sentientia_leaderboard`.
