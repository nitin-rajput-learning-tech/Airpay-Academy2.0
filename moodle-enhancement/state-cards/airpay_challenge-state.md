# `local_airpay_challenge` State Card

**Component:** `local_airpay_challenge`
**Version:** `2026052201` / `1.1.3-beta`
**Status:** ✓ Phase 1 + Phase 2 shipped + WS-contract aligned 2026-05-22 (Goal A Bug #10)
**Reclassified by Nitin:** stub → PRIORITY → Phase-1 + Phase-2 built
**Last refreshed:** 2026-05-24

---

## What this plugin owns

A gamification engine for Airpay Academy — define course-completion-based
challenges, let learners join, track progress against the qualifying
courses, award points on completion, and surface a per-tenant
leaderboard.

Phase 1 supports **course-completion-based challenges only**. The
schema and engine are designed so Phase 2 can drop in
streak-based and quiz-score-based challenges without schema migration —
they each become new branches inside `challenge_engine::compute_progress()`.

---

## Capabilities

```
local/airpay_challenge:view         read   archetypes: user, student, teacher, manager
local/airpay_challenge:participate  write  archetypes: user, student, teacher, manager
local/airpay_challenge:manage       write  archetype: manager  (RISK_CONFIG | RISK_SPAM)
local/airpay_challenge:viewall      read   archetype: manager  (RISK_PERSONAL)
```

`:viewall` is split from `:view` so that a normal user's leaderboard
auto-scopes to their own tenant, while compliance / HR can opt into a
cross-tenant view.

---

## Database tables

| Table | Purpose |
|---|---|
| `local_airpay_challenge_challenges` | Challenge definitions. Per-tenant via `costcenterid` (0 = global). Indexed on status, costcenterid, shortname. |
| `local_airpay_challenge_attempts` | One row per (challenge, user) join. Snapshots `targetcount` so post-join challenge edits don't retroactively change the goal. Unique index `(challengeid, userid)` enforces single-attempt-per-user. |
| `local_airpay_challenge_leaderboard` | Pre-computed snapshot. `challengeid = 0` means the aggregate (sum across all challenges). Recomputed every 15 min by scheduled task. |

---

## Web service endpoints

```
local_airpay_challenge_list_challenges     read   :view         paginated, search + status filter, tenant-scoped
local_airpay_challenge_get_challenge       read   :view         single challenge with caller progress
local_airpay_challenge_create_challenge    write  :manage       (sesskey)
local_airpay_challenge_update_challenge    write  :manage       (sesskey, partial-update)
local_airpay_challenge_delete_challenge    write  :manage       (sesskey, cascades)
local_airpay_challenge_join_challenge      write  :participate  (sesskey)
local_airpay_challenge_leave_challenge     write  :participate  (sesskey)
local_airpay_challenge_get_leaderboard     read   :view         challenge or aggregate, tenant-scoped
```

---

## Files

```
local/airpay_challenge/
├── version.php
├── lib.php
├── index.php           — admin: paginated list + filter + create
├── view.php            — challenge detail (3 tabs: overview/participants/leaderboard)
├── leaderboard.php     — global leaderboard (per-challenge or aggregate)
├── db/
│   ├── access.php      (4 caps)
│   ├── install.xml     (3 tables)
│   ├── upgrade.php     (idempotent)
│   ├── services.php    (8 WS endpoints)
│   ├── events.php      (course_completed observer)
│   └── tasks.php       (recompute_leaderboard, every 15 min)
├── lang/en/local_airpay_challenge.php   (~95 strings)
├── classes/
│   ├── challenge_engine.php       (~360 LOC) — lifecycle + progress evaluation
│   ├── leaderboard_manager.php    (~160 LOC) — snapshot recompute
│   ├── challenge_renderer.php     — preserves render_challenge_object stub
│   ├── observer.php               — fast-path event handler
│   ├── task/
│   │   └── recompute_leaderboard.php
│   ├── external/                  (8 WS classes)
│   └── form/
│       └── edit_challenge_dynamic_form.php
├── templates/
│   ├── index.mustache
│   ├── view.mustache
│   └── leaderboard.mustache
├── amd/
│   ├── src/challenge_actions.js
│   └── build/challenge_actions.min.js
└── tests/
    ├── challenge_engine_test.php       (24 tests)
    ├── leaderboard_manager_test.php    (5 tests)
    └── external/
        ├── list_challenges_test.php    (6 tests)
        ├── join_challenge_test.php     (5 tests)
        └── get_leaderboard_test.php    (5 tests)
```

Total: ~30 files, ~2500 LOC. PHPUnit method counts after Phase 2:
- `challenge_engine_test`: 23 methods
- `challenge_engine_phase_2_test`: 6 methods (Phase 2 streak / quiz / expiry)
- `leaderboard_manager_test`: 5 methods
- `external/list_challenges_test`: 6 methods
- `external/join_challenge_test`: 5 methods
- `external/get_leaderboard_test`: 5 methods
- `privacy/provider_test`: privacy provider coverage

Total: ~50 PHPUnit methods (up from ~45 at 1.0.0-beta).

---

## Engine design — design choices worth preserving

### Why `targetcount` is snapshotted into the attempt row at join time

If an admin edits a challenge to require 5 completions instead of 3
*after* a user has already joined and reached 3, the user has fairly
earned the original target. Snapshotting at join time means
post-join edits affect only NEW participants. The challenge row
remains the source of truth for points reward (which carries no
similar fairness concern).

### Why progress evaluation runs both event-driven AND cron-driven

Event-driven (`observer.php`) is the fast path: when a course
completion fires, in-progress attempts for that user re-evaluate
immediately. Cron-driven (`recompute_leaderboard.php` every 15 min)
is the catch-up — handles missed events (events disabled, observer
errors, completions inserted directly into `mdl_course_completions`
by an admin). Both paths converge on `evaluate_attempt()` which is
idempotent.

### Why completed status is terminal

Once an attempt is `completed`, points are awarded once, and further
course completions for the same user don't add more points to the
same attempt. This prevents farm-grinding by repeatedly enrolling +
completing easy courses. To get more points, the user has to win a
*different* challenge.

### Why the manager-archetype lockout-protection from `airpay_roles`
   isn't replicated here

`airpay_challenge:manage` is a `RISK_CONFIG`-tagged write cap, but
nothing in this plugin can lock an admin out of the system. The worst
a misconfigured manager can do is delete every challenge, which is
recoverable from a DB backup. So the explicit "block manager from
nuking site:config" pattern doesn't apply.

### Why aggregate leaderboard uses `challengeid = 0`

Reusing the same table for per-challenge AND aggregate views means a
single index strategy and a single query path in
`leaderboard_manager::get_top()`. Avoids carrying a parallel "user
total points" denormalization or having to UNION two query shapes.

---

## Phase-2 status (since 2026-05-07 ship)

| Item | Status |
|---|---|
| Streak-based challenges (daily login tracking) | ✅ shipped (`TYPE_STREAK`) |
| Quiz-score-based challenges (mod_quiz event listener) | ✅ shipped (`TYPE_QUIZ_SCORE`) |
| Challenge expiry: auto-mark `expired` for past-end-date attempts that never completed | ✅ shipped (cron task) |
| `tool_certificate` badge integration on completion | pending |
| FCM push notification when peer overtakes (depends on `airpay_integrations` cleanup first) | pending |
| Front-end leaderboard widget mountable on dashboard / course pages | pending (covered partially by `local_sentientia_leaderboard` Phase L.0) |
| Cohort gating UI (schema field exists; admin form needs cohort autocomplete) | pending |
| Cross-tenant + per-cohort leaderboard combinations | pending |

---

## Verification cycle

```powershell
# 1. PHP lint
& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\moodle5\public\local\airpay_challenge\classes\challenge_engine.php"

# 2. Run upgrade
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\admin\cli\upgrade.php" --non-interactive

# 3. Visual smoke test
# Navigate: http://localhost:8080/moodle/local/airpay_challenge/index.php
# As: site admin
# Click "New challenge" → modal opens → fill name/target/points → Save
# Result: row appears in table, status = Draft
# Click pencil → status = Active → Save
# Result: status badge changes; admin sees Join button on the row
# Click Join → confirmation toast → row shows "Leave challenge"
# Then visit: /local/airpay_challenge/leaderboard.php
# Expected: empty leaderboard (no completions yet)

# 4. PHPUnit
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\public\admin\tool\phpunit\cli\init.php"
cd C:/xampp/htdocs/moodle5
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\vendor\phpunit\phpunit\phpunit" `
    --testsuite local_airpay_challenge_testsuite

# 5. Trigger leaderboard recompute manually
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\admin\cli\scheduled_task.php" `
    --execute='\local_airpay_challenge\task\recompute_leaderboard'
```

---

## How to extend (Phase 2 starting points)

- **Streak-based**: add an event observer for `\core\event\user_loggedin`,
  store last-login dates per user in a new `local_airpay_challenge_streaks`
  table, branch in `compute_progress()` on `type === STREAK`.
- **Quiz-score**: observer for `\mod_quiz\event\attempt_submitted`, read
  the attempt's grade against threshold from the challenge config.
- **Notifications**: when `evaluate_attempt()` transitions status from
  in-progress to completed, dispatch a `\core_message\message` to the
  user (and optionally to their manager via the BizLMS reporting line).
- **Cohort gating UI**: extend `edit_challenge_dynamic_form` with a
  cohort autocomplete element. The schema field `cohortid` and the
  cohort-membership check in `join()` already exist.

---

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version to `2026052201` /
`1.1.3-beta` (was `2026050700` / `1.0.0-beta`). Cumulative changes since
Phase 1 ship:

- **Phase 2** — streak + quiz-score challenge types shipped; auto-expiry
  cron task; new test class `challenge_engine_phase_2_test` (6 methods).
- **Goal A Bug #10 (2026-05-22)** — WS-contract alignment with the
  external-functions audit. Forced version bump to `2026052201`.
- **PHPUnit growth** — total methods ~50 (was ~45 at Phase 1). Privacy
  provider test class also shipped.

No new DB tables (3 retained), no new capabilities (4 retained), no
feature flags registered (this plugin pre-dates the feature-flag
mandate; new behaviour ships behind explicit `status='draft'` rows).
Phase 2 follow-up table updated to reflect what's been delivered vs
what remains.
