# State Card — local_sentientia_analytics Predictive Extension (P1.2)

**Plugin:** `local_sentientia_analytics`
**Version bump:** `2026052001` → `2026061600` (release `1.0.1-beta` → `1.1.0-beta`)
**Branch:** `claude/gap-analytics-predictive`
**Session date:** 2026-06-16
**Status:** COMPLETE — pending Nitin review + flag flip

---

## What was built

### Gap addressed
GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md §6 P1.2 — Predictive Analytics + Training ROI.
Invince LXP offers predictive completion forecasting and ROI dashboards; Sentientia had
only descriptive KPIs. This session closes that gap.

### New surfaces (all DEFAULT OFF behind feature flags)

| Surface | Flag | Class |
|---------|------|-------|
| At-risk learner forecasting | `sentientia.analytics.predictive.enabled` | `predictive_engine::get_at_risk_users()` |
| Team skill-gap projection | `sentientia.analytics.predictive.enabled` | `predictive_engine::get_skill_gap_projection()` |
| Training ROI dashboard | `sentientia.analytics.roi.enabled` | `roi_calculator::compute()` |

---

## Files added / modified

### New files

| File | Purpose |
|------|---------|
| `classes/predictive_engine.php` | At-risk scoring + skill-gap projection (pure heuristics, no external ML) |
| `classes/roi_calculator.php` | Training ROI computation with transparent, configurable assumptions |
| `classes/task/refresh_predictive_cache.php` | Scheduled task: pre-warms predictive + ROI caches hourly |
| `db/tasks.php` | Registers the scheduled task (runs at :05 each hour) |
| `db/feature_flags.php` | Registers 2 new flags (both DEFAULT OFF) |
| `db/upgrade.php` | Version bump savepoint (no schema changes) |
| `templates/predictive_atrisk.mustache` | At-risk learners panel |
| `templates/predictive_skillgap.mustache` | Team skill-gap panel |
| `templates/roi_dashboard.mustache` | ROI surface with assumptions transparency panel |
| `tests/predictive_engine_test.php` | 9 PHPUnit tests |
| `tests/roi_calculator_test.php` | 12 PHPUnit tests |

### Modified files

| File | Change |
|------|--------|
| `version.php` | `2026052001` → `2026061600`, release `1.1.0-beta` |
| `db/caches.php` | Added `predictive_atrisk`, `predictive_skillgap`, `roi` definitions (10 min TTL) |
| `lang/en/local_sentientia_analytics.php` | 34 new strings for predictive + ROI surfaces |
| `lang/hi/local_sentientia_analytics.php` | 34 Hindi translations (100% parity) |
| `index.php` | Feature-flag gated block that calls predictive_engine + roi_calculator; zero impact when both flags OFF |
| `templates/dashboard.mustache` | Two new mustache partial includes, guarded by `{{#show_predictive}}` and `{{#show_roi}}` |

---

## Architecture decisions

### Predictive model transparency (no black-box)
Four named, weighted signals — each surfaced in the `signals[]` array on every user row:
- `days_since_last_access` — weight 30%
- `completion_rate_gap` — weight 25%
- `overdue_courses` — weight 25%
- `login_velocity_drop` — weight 20%
Weights documented in class constants. L&D analysts can read, audit, and propose
changes. The signals breakdown is rendered in the UI alongside each risk score.

### skillsai optional dependency
`predictive_engine::get_skill_gap_projection()` checks `class_exists('\local_sentientia_skillsai\skill_gap_provider')`
before attempting to use it. If absent (current state), falls back to course-category
coverage heuristic. Both paths return the same array shape.

### ROI assumption configurability
All monetary assumptions use `get_config('local_sentientia_analytics')` with class-
constant fallbacks. Admins can override via plugin settings without code changes.
Every assumption is surfaced in the `<details>` transparency panel in the template.

### Performance (database.md Performance Decision Matrix)
- Predictive aggregation: 3 queries × all-users scope — classified as "dashboard-level aggregate"
  → 10 min application cache + hourly cron pre-warm.
- No N+1: all user-level signals computed in 3 bulk queries using `get_in_or_equal()`.
- ROI: 3 aggregate count queries → 10 min cache.
- Existing descriptive KPIs (5 min cache) are untouched.

### No new DB schema
All computation reads from existing Moodle tables:
`{user}`, `{user_enrolments}`, `{enrol}`, `{course}`, `{course_completions}`,
`{logstore_standard_log}`, `{local_sentientia_org}`, `{course_categories}`.

---

## Feature flags (both DEFAULT OFF)

```
sentientia.analytics.predictive.enabled  — default: false
sentientia.analytics.roi.enabled         — default: false
```

To enable for Airpay (tenant /1):
```
# Via Switchboard UI → Analytics category → toggle ON
# Or via CLI (after verifying cron task runs cleanly):
php admin/cli/feature_flags.php --key=sentientia.analytics.predictive.enabled --tenant=1 --value=true
```

---

## Verification checklist (before enabling on production)

- [ ] `php admin/cli/scheduled_tasks.php --execute='\local_sentientia_analytics\task\refresh_predictive_cache'` runs without error
- [ ] At-risk table renders with correct tenant scoping (no /177 users in /1 view)
- [ ] Skill-gap panel renders (or shows empty state if no org data)
- [ ] ROI panel renders with summary sentence and assumptions accordion
- [ ] Both flags OFF → existing dashboard unchanged (no JS errors, no new sections)
- [ ] Both flags ON → new sections appear below course effectiveness table
- [ ] PHPUnit suite passes: `vendor/bin/phpunit local/sentientia_analytics/tests/`

---

## Next steps

1. Review ROI assumptions with Nitin — adjust `roi_platform_cost`, `roi_hourly_rate` to Airpay actuals via plugin settings
2. Flip `sentientia.analytics.predictive.enabled` ON for tenant 1 after cron verification
3. After `local_sentientia_skillsai` ships, remove the fallback heuristic and rely on the provider
4. Add manager-view page: managers see their direct reports' at-risk scores (capability-gated)
