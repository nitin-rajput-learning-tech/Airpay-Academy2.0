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


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - DPDP erasure audit: challenge data is erased, deliberately

**Audit.** `local_sentientia_privacy\privacy_manager::process_deletion()` (the DPDP
right-to-erasure flow) calls a Sentientia provider's `anonymise_data_for_user()` when it has
one, to KEEP learning and compliance records, and `delete_data_for_user()` otherwise. This
provider has no anonymise hook. Audited table by table; the conclusion is that it should not
have one, so NO behaviour change:
- `local_sentientia_challenge_attempts`: ERASED. Voluntary gamification opt-in, not a learning
  or compliance record. The learner can delete their own row at any time
  (`challenge_engine::leave()`, even after completing); `progress` is only a count derived from
  core data (course completions, quiz attempts, course-access days) that the flow keeps in core
  tables. The flow's Step 2 already erases the rest of gamification (points log, badges,
  streaks). Keeping the rows would also put "Deleted User" back on the leaderboard:
  `leaderboard_manager` rebuilds it from attempts every 15 minutes with no deleted-user filter.
- `local_sentientia_challenge_leaderboard`: ERASED. Derived; recomputed from attempts.
- `local_sentientia_challenge_challenges` (author): KEPT, `createdby` anonymised to 0 and
  `open_path` cleared. Tenant scoping reads `costcenterid`, which is untouched.

**Change.** The decision is documented in the provider's class comment, and pinned by
`tests/privacy_anonymise_test.php` (no anonymise hook; only the subject's rows go; the shared
challenge survives in the same tenant; the erased person does not return after the leaderboard
rebuild). Written, not yet run (shared test DB being rebuilt). Comment + test only: no version
bump. Both trees. If attempts are ever to be kept, make the leaderboard rebuild skip deleted
users first.


## 2026-09-25 - get_leaderboard test failures: stale test calls, plus a real fullname() defect

**Failures (full run 2026-09-24, ME tree).** 3 errors + 1 failure in
`tests/external/get_leaderboard_test.php`: `TypeError: get_leaderboard::execute(): Argument #2
($challengeid) must be of type int, string given` (test_returns_top_for_challenge,
test_aggregate_returns_zero_when_no_completions, test_ismine_flag_set_for_caller_row), and
test_filterstoolong_rejected got the same TypeError instead of the `moodle_exception` it expects.

**Root cause 1 - TEST was wrong.** On 2026-05-22 (Goal A Bug #10, commit 89fb2e713) `search`
was put first in `execute()` and `execute_parameters()`, so the endpoint accepts the shared
datatable client contract. The tests, written 2026-05-07, were not updated. They still passed
arguments by position in the old order, so the challenge id went into `$search` and `'mine'` into
`$challengeid`. The WS code is correct: `external_api::call_external_function()` passes
`array_values()` of the validated params by position, and the signature matches the key order
of `execute_parameters()`. The only PHP callers of `execute()` are these tests. Fix: the tests
now use named arguments. test_view_capability_required (not failing, but `execute(0)` had put
`'0'` into `$search`) now uses them too. test_filterstoolong_rejected now also pins the
`err_filterstoolong` message, because `required_capability_exception` is also a
`moodle_exception` and would otherwise satisfy the test. The new
test_execute_signature_matches_parameter_order checks the positional contract the dispatcher
relies on (6 methods now).

**Root cause 2 - CODE was wrong. The TypeError hid it.** `leaderboard_manager::get_top()`
called `fullname()` on an object with only firstname and lastname. In developer mode core raises
`debugging('The following name fields are missing ...')` for every row, which appears as an
"Unexpected debugging() call detected" notice in the tests that reach rows, including
leaderboard_manager_test::test_get_top_returns_paginated. On a site whose `fullnamedisplay`
uses middlename, alternatename or the phonetic fields, the leaderboard also showed the wrong
name. Fix: select every name field with `\core_user\fields::for_name()->get_sql('u', false, '',
'', false)->selects` and pass the row to `fullname()`. `u.email` was selected but never used, so
it is no longer read. Return shape unchanged; no version bump needed (class-only change). Both
trees.

**Verification.** Traced by reading, not run (shared test DB is being rebuilt): php -l clean,
check-tree-drift OK, lang-parity 0 failures (no lang change).

**Open, not fixed (outside this task).** (a) `tenantmode=mine` for a caller with an empty or
non-numeric `open_path` resolves to tenant 0, which `get_top()` treats as unscoped. Such a caller
sees every tenant's leaderboard. This needs a product decision. (b) The class docblock says it
"locks in tenant scoping (mine vs all)", but no test covers `all` vs `mine`, or cross-tenant
isolation.

## 2026-09-25 - Cross-tenant leaderboard closed (1.1.5-alpha, 2026092500)

Two leaks, closed together:

- `:viewall` defaulted to the manager archetype. Tenant admins hold manager-archetype roles (UAT's id-9 "administrator"), so every tenant admin could call `get_leaderboard(tenantmode=all)` and read every tenant's leaderboard, names included. This is the defect `local_sentientia_analytics:viewallorgs` had until 2026-09-24. It now has no default grant, and upgrade step 2026092500 revokes every existing grant. Site admins are unscoped anyway.
- A scoped caller whose open_path did not resolve to a tenant got tenant 0, which `get_top()` and `list_challenges()` read as every tenant. Both web services now return nothing for such a caller; the site admin is unaffected.

Found while the challenge test fixes were reviewed. Covered by `tests/external/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031: challenge writes and by-id reads tenant-bounded (1.1.6-alpha, 2026092501)

`:manage` stays on the manager archetype (per-tenant authoring is legitimate), but it no longer
decides WHERE. `challenge_engine` gains `user_can_see()` / `require_visible()` and `user_can_manage()` /
`require_manageable()`, all routed through `tenant::is_cross_tenant()`:

- **P0** `update_challenge` / `delete_challenge` (and the dynamic form's access check and set_data)
  acted on any id; delete also wiped the challenge's attempts and leaderboard rows. A scoped manager
  now manages only their own tenant's challenges; global (costcenterid 0) challenges are
  cross-tenant only. The list's Edit / Delete buttons follow the same rule.
- `create_challenge` stamped a tenantless caller's challenge as costcenterid 0 = GLOBAL (live to every
  tenant). It now refuses a scoped caller with no tenant, or one naming another tenant or 0.
- `get_challenge`, `view.php` and `join()` loaded any id: they now require the challenge to be global
  or the caller's tenant's (nothing for a tenantless caller). The `leaderboard.php` dropdown is scoped.
- `list_challenges` and `get_leaderboard(tenantmode=all)` unscope only for `is_cross_tenant()`;
  `:viewall` (no default grant since 2026092500) no longer unscopes on its own.

`tests/privacy/provider_test.php` authored its challenge as a tenantless user; it now creates as the
site admin and sets `createdby`. New `tests/tenant_isolation_test.php` (`@group tenant_isolation`).
Declares a dependency on platform 2026092500. Both trees.
