# State Card — P0.2 Adaptive Learning Journeys
**Plugin(s):** `local_sentientia_learningpath` (extended)
**Branch:** `claude/gap-adaptive`
**Date:** 2026-06-16
**Status:** COMPLETE (feature-flagged, default OFF, ready for QA)

---

## What was built

P0.2 from the Competitive Gap Analysis (§6) — converts static path/program
sequences into **performance-pivoting journeys**: branch / accelerate /
remediate based on quiz scores, completion velocity, and a skills-gap feed.

All new code is behind the feature flag `sentientia.learningpath.adaptive.enabled`
(default OFF). When the flag is OFF the plugin behaves exactly as v1.7.1.

---

## Files changed / created

### `local_sentientia_learningpath`

| File | Change |
|------|--------|
| `version.php` | Bumped to `2026061600`, release `1.8.0`; added `local_sentientia_platform` dependency |
| `db/upgrade.php` | Added `2026061600` savepoint: 3 new columns on learningpath table, 3 new columns on courses junction table, new `local_sentientia_lp_adaptive_log` table |
| `db/feature_flags.php` | NEW — registers `sentientia.learningpath.adaptive.enabled` (default OFF) |
| `db/events.php` | NEW — registers quiz attempt observer |
| `db/tasks.php` | NEW — registers `adaptive_sweep` daily cron (02:30) |
| `classes/adaptive/skills_gap_feed.php` | NEW — interface to `local_sentientia_skillsai\gap_engine`; full class_exists() + feature_flags guard; degrades to empty array when skillsai absent |
| `classes/adaptive/velocity_calculator.php` | NEW — completion velocity index (0.0–2.0); linear interpolation against enrolment window; fallback pace model when no dates |
| `classes/adaptive/quiz_signal_reader.php` | NEW — reads `mod_quiz` attempt data; normalises to 0–100 percentage; graceful null when tables absent |
| `classes/adaptive/journey_engine.php` | NEW — decision core; evaluate() + velocity_sweep() entry points; REMEDIATE / ACCELERATE / BRANCH / NO_ACTION logic; writes to adaptive log table |
| `classes/observer.php` | NEW — `mod_quiz\event\attempt_submitted` handler; calls journey_engine::evaluate(); catches all Throwable to never break quiz page |
| `classes/task/adaptive_sweep.php` | NEW — scheduled_task; calls journey_engine::velocity_sweep() |
| `lang/en/local_sentientia_learningpath.php` | Added 21 new strings (adaptive mode, thresholds, pivot labels, privacy metadata) |
| `lang/hi/local_sentientia_learningpath.php` | Added 21 Hindi parity strings (100% parity maintained) |
| `tests/adaptive_journey_test.php` | NEW — 10 test cases: flag-off no-op, velocity math, quiz reader guards, skillsai degradation, tenant isolation, schema assertions, serialise round-trip |

---

## DB schema additions

### `local_sentientia_learningpath` (3 new columns)
| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `adaptive_mode` | TINYINT | 0 | 0=static, 1=adaptive. Existing paths default 0 → zero behaviour change |
| `score_threshold_low` | DECIMAL(5,2) | NULL | Below this quiz % → REMEDIATE. Engine default 50% when NULL |
| `score_threshold_high` | DECIMAL(5,2) | NULL | Above this quiz % → ACCELERATE candidate. Default 80% |

### `local_sentientia_learningpath_courses` (3 new columns)
| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `is_remedial` | TINYINT | 0 | 1 = this is a remedial branch node |
| `is_accelerator` | TINYINT | 0 | 1 = this is a fast-track node |
| `remedial_for_courseid` | INT | NULL | Which main-sequence course this remediates |

### `local_sentientia_lp_adaptive_log` (NEW table)
Full audit log of every pivot decision. Indexed on `(pathid, userid)`, `costcenterid`, `pivot_type`, `timecreated`. Multi-tenant: every row carries `costcenterid`.

---

## Decision logic (flag ON + adaptive_mode=1 path)

```
Signals collected:
  1. quiz_score  — worst/best finalised quiz % in triggered course
  2. velocity    — completion velocity index (0.0–2.0)
  3. gap_data    — skills gap from skillsai (or [] when absent)

Pivot rules (in priority order):
  REMEDIATE  → quiz_score < threshold_low
               OR (velocity < 0.5 AND skills gaps exist)
  BRANCH     → skills gaps exist AND quiz_score < threshold_high
               (gap-addressing courses logged for manager review)
  ACCELERATE → quiz_score >= threshold_high AND velocity >= 1.2 AND no gaps
  NO_ACTION  → everything in range / insufficient data

Acts:
  REMEDIATE  → enrols user in is_remedial=1 course for source_courseid
  ACCELERATE → enrols user in next is_accelerator=1 course (full skip Phase 2)
  BRANCH     → logs intent; manager reviews before enrolment
  NO_ACTION  → logs only
```

---

## Skillsai graceful degradation

`skills_gap_feed` guards every interaction:
1. `class_exists('\local_sentientia_skillsai\gap_engine')` → false → return []
2. `feature_flags::is_enabled('sentientia.skillsai.enabled')` → false → return []
3. Any `\Throwable` from gap_engine → caught → return [], log to debugging()

When skillsai is absent the engine falls back to `quiz_score + velocity` signals only.

---

## Feature flag details

**Key:** `sentientia.learningpath.adaptive.enabled`
**Default:** `false`
**Registry file:** `local_sentientia_learningpath/db/feature_flags.php`
**Resolver:** `\local_sentientia_platform\feature_flags` (5-level: customer × tenant)
**Effect when OFF:** `journey_engine::evaluate()` and `velocity_sweep()` return immediately, zero DB reads/writes, zero log rows.
**Effect when ON:** Full adaptive engine active on any path with `adaptive_mode=1`.

---

## What is NOT done (Phase 2 items)

- Full **sequence skip** for ACCELERATE (requires manager confirmation UI)
- **Manager review UI** for BRANCH decisions (log exists; UI TBD)
- `local_sentientia_recommendations` — the recommendations rationale model was NOT extended in this session (dependency on skillsai P0.1 being live first)
- xAPI statement emission for pivot events (P1.4)

---

## Next session prerequisites

1. P0.1 (`local_sentientia_skillsai`) must be installed for the skills-gap feed to activate.
2. Set `adaptive_mode=1` on desired paths and configure threshold columns.
3. Flip `sentientia.learningpath.adaptive.enabled` to ON in the Switchboard.
4. Run `php admin/cli/upgrade.php --non-interactive` to apply schema.

---

## Test run checklist

```
[ ] php -l all new classes (no syntax errors)
[ ] PHPUnit: php vendor/bin/phpunit local/sentientia_learningpath/tests/adaptive_journey_test.php
[ ] flag-off no-op tests pass (tests 1, 2)
[ ] velocity math tests pass (tests 2a–2e)
[ ] skillsai absent tests pass (tests 4a–4c)
[ ] schema tests pass (tests 8–9, requires upgrade run)
[ ] tenant isolation test passes (test 6)
```
