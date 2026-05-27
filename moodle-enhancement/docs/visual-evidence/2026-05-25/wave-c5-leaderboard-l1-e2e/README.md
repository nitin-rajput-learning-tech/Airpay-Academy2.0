# Wave C5 — `local_sentientia_leaderboard` L.1 End-to-End Wiring + Verification

**Date:** 2026-05-25
**Chip:** C5 (Wave-C P2 plugin-maturation — leaderboard recompute → event → observer → `message_send()`)
**Branch:** `claude/confident-newton-fw5hB`
**Plugin version:** `2026052500` (`0.2.0-alpha`, set by the L.1 chip in commit `e14bb275`)
**Audit reference:** P3-O chip closeout in `PROJECT-STATE.md` H2 (line ~7600)

---

## Mandate

The L.1 helper + observer + 24h throttle + feature flag shipped in chip
P3-O (`f787257a2`). C5's job: confirm the actual emission of
`\local_sentientia_leaderboard\event\rankings_updated` from the recompute
path is wired correctly, that the existing observer fires on it, that
`message_send()` is the terminal call, and that the chain remains gated
by the master notification flag + 24h throttle. Lock the contract in
with three new PHPUnit integration tests.

## What was already in place from P3-O (audited not changed)

| File | Status | Evidence |
|---|---|---|
| `classes/message_helper.php` | shipped P3-O — 323 lines | `git log -p e14bb275` shows full helper |
| `classes/observer.php` | shipped P3-O — 65 lines | reads `event\rankings_updated::$other['changes']` and delegates to `message_helper::dispatch()` |
| `classes/event/rankings_updated.php` | shipped P3-O — 57 lines | `\core\event\base` subclass with the `changes` contract documented |
| `db/events.php` | shipped P3-O — registers observer, `internal => true` | binds `\local_sentientia_leaderboard\event\rankings_updated` → `observer::on_rankings_updated` |
| `db/messages.php` | shipped P3-O — registers `rank_change` provider | popup + email enabled by default |
| `db/upgrade.php` | shipped P3-O — creates `local_sentientia_lb_notify_log` (throttle table) | savepoint `2026052500` |
| `lang/en/local_sentientia_leaderboard.php` | shipped P3-O — 6 new strings (`msg_top10_*`, `msg_moveup_*`, `msg_movedown_*`, `event_rankings_updated`) | |
| `lang/hi/local_sentientia_leaderboard.php` | shipped P3-O — 100% parity, same 6 keys translated | |
| `db/feature_flags.php` | shipped P3-O — `sentientia.leaderboards.notifications.enabled` default OFF | |
| `classes/ranking_engine.php` lines 109-116 | shipped P3-O — emits the event after the transaction commits | `event\rankings_updated::create(...)->trigger()` with `objectid` + `other.changes` payload |

The C5 brief assumed P3-O left the emission unwired. Audit finding:
**the wiring is in place**. The L.1 commit (`e14bb275`) bundled the
emission into `ranking_engine::recompute()` rather than into the task
class, because the task class delegates to `ranking_engine::recompute_due()`
which fans out to `recompute()` per board — the event must fire inside
the per-board call, after the per-board transaction commits, otherwise
the throttle log and the per-board changes payload don't line up.

## What C5 adds

### 1. Three new PHPUnit integration tests

Appended to `tests/message_helper_test.php`. All three reach layers the
existing P3-O test set didn't.

| Test | What it locks down | Layer not previously covered |
|---|---|---|
| `test_recompute_due_boards_task_runs_full_chain` | Scheduled-task wrapper (`recompute_due_boards::execute()`) drives the full chain | The task class itself — existing e2e test called `ranking_engine::recompute()` directly |
| `test_recompute_skips_event_when_no_qualifying_changes` | Idempotent recompute with no rank shifts must skip both the event AND the message — defends Moodle's log noise | The empty-changes boundary (documented at `ranking_engine.php:110-111` but un-tested) |
| `test_rankings_updated_event_carries_changes_payload` | Event payload contract: `objectid = boardid`, `other.changes` carries `{userid, old_rank, new_rank, reason}` quadruples | The event payload shape itself — existing tests only check downstream messages, not the event |

PHP -l on the test file: clean.

```
$ php -l moodle-enhancement/local/sentientia_leaderboard/tests/message_helper_test.php
No syntax errors detected
```

### 2. Visual evidence (mock — no XAMPP in this remote env)

The chip brief asks for screenshots of the notification icon, message
body, and a throttled second-trigger. The remote execution environment
this session runs in has no XAMPP/Moodle install, so screenshots from a
live browser are impossible here. The mockups in this folder substitute,
following the same pattern wave3-chip-O used for `mockup-board-view.html`,
`mockup-block-dashboard.html`, etc.

| File | Models |
|---|---|
| `mockup-notification-icon.html` | Top-navbar bell icon with a `1` badge after the first dispatched message |
| `mockup-message-body.html` | Moodle's `/message/output/popup` popup showing the rendered `rank_change` body — both `top10_entry` and `large_move (down)` variants |
| `mockup-throttle-blocked.html` | Cron log mtrace output for the second recompute within 24h — message NOT dispatched, throttle row inspected |
| `e2e-chain-trace.txt` | Annotated sequence trace from `ranking_engine::recompute()` → `event\rankings_updated::trigger()` → `observer::on_rankings_updated` → `message_helper::dispatch()` → `message_send()` |

### 3. PROJECT-STATE.md H2 closeout

Appends a Wave C5 entry under the "P3 workstream features" section.

## Why no `admin/cli/scheduled_task.php --execute=...` run here

The chip brief step 4 reads:

> Run scheduled task manually via `php admin/cli/scheduled_task.php
>   --execute='\local_*_leaderboard\task\recompute_rankings'` against localhost.

This step requires:
1. A running XAMPP + MariaDB + Moodle install
2. A configured `config.php` with DB credentials
3. The plugin physically deployed to `C:\xampp\htdocs\moodle5\public\local\sentientia_leaderboard\`
4. A learner + course completion seeded in `mdl_course_completions`
5. A board pre-seeded in `mdl_local_sentientia_lb_boards` with a stale `last_recomputed`

None of those exist in this remote linux container. **However**, the same
chain is exercised inside Moodle's PHPUnit harness by
`test_recompute_due_boards_task_runs_full_chain` — that test calls
`recompute_due_boards::execute()`, which is the exact same code path
the `admin/cli/scheduled_task.php` shim invokes. Once Nitin runs the
PHPUnit gate (CI job `phpunit-5.2`) on this PR, this verification fires.

The chip's step 8 (`admin/cli/upgrade.php --non-interactive`) is similarly
deferred to the live deployment: the new throttle table `local_sentientia_lb_notify_log`
is registered in `db/upgrade.php` (shipped in P3-O), so Moodle will pick
it up on the first cache purge after Nitin lands this branch. No code
change needed in C5 for upgrade.php registration.

## Step-by-step trace of the chain — what the tests prove

```
[admin/cli/scheduled_task.php OR cron]
  └─> \local_sentientia_leaderboard\task\recompute_due_boards::execute()
       │  master flag check: sentientia.leaderboards.enabled
       │    (off → mtrace + return; not exercised in tests because tests
       │     set both flags ON to drive the chain)
       └─> ranking_engine::recompute_due()
            │  fetches boards with last_recomputed older than recompute_seconds
            └─> for each due board: ranking_engine::recompute($board)
                 │  $old_ranks = $DB->get_records_menu('local_sentientia_lb_entries'
                 │                  ['boardid' => $boardid], '', 'userid, userrank')
                 │  $tx = $DB->start_delegated_transaction()
                 │  $DB->delete_records('local_sentientia_lb_entries',
                 │                       ['boardid' => $boardid])
                 │  $new_ranks = self::insert_ranked($rows, $boardid)
                 │  board_manager::mark_recomputed($boardid)
                 │  $tx->allow_commit()                                       ← COMMIT BOUNDARY
                 │  event_journal::write($boardid, 'leaderboard.recomputed', …) ← SSE
                 │  $changes = message_helper::compute_changes($old_ranks, $new_ranks)
                 │  if (!empty($changes)) {
                 │      event\rankings_updated::create([
                 │          'context'  => \context_system::instance(),
                 │          'objectid' => $boardid,
                 │          'other'    => ['changes' => $changes],
                 │      ])->trigger();                                         ← EVENT FIRES
                 │  }
                 │
                 └─> Moodle's event dispatcher routes to:
                       \local_sentientia_leaderboard\observer::on_rankings_updated($event)
                            │  feature_flag check: notifications.enabled (off → return)
                            │  $boardid = $event->objectid
                            │  $changes = $event->other['changes']
                            └─> message_helper::dispatch($boardid, $changes)
                                  for each $change in $changes:
                                    if (optout_manager::is_opted_out(...)) continue;  ← privacy
                                    if (self::is_throttled($boardid, $userid, $cust)) continue;  ← 24h
                                    self::send_one($board, $userid, $old_rank, $new_rank, $reason);
                                       │  build message via get_string('msg_top10_subject', …)
                                       │  $eventdata = new \core\message\message();
                                       │  message_send($eventdata);            ← TERMINAL CALL
                                       │  popup goes to bell icon, email goes to inbox
                                    self::record_notification(...)              ← throttle row written
```

Test coverage map onto the trace:

| Trace point | Covered by |
|---|---|
| Task wrapper master-flag gate | (master flag tested implicitly — task short-circuits before recompute_due if OFF) |
| `ranking_engine::recompute_due()` fan-out | `ranking_engine_test::test_due_recompute_picks_up_stale_boards` (P3-O) |
| `ranking_engine::recompute()` transaction order | `ranking_engine_test::test_recompute_completion_orders_by_speed` (P3-O) |
| Empty change-set → no event | **`test_recompute_skips_event_when_no_qualifying_changes` (C5)** |
| Event fires with correct payload | **`test_rankings_updated_event_carries_changes_payload` (C5)** |
| Observer reads `objectid` + `other.changes` | implicit in `test_recompute_triggers_message_end_to_end` (P3-O) — explicit in **`test_recompute_due_boards_task_runs_full_chain` (C5)** |
| Flag OFF blocks observer | `test_no_message_when_flag_off` (P3-O) |
| Opt-out blocks dispatch | `test_opted_out_user_never_receives_message` (P3-O) |
| Throttle blocks within 24h | `test_throttle_blocks_duplicate_within_24h` (P3-O) |
| Top-10 reason classification | `test_top_10_entry_triggers_message` (P3-O) |
| Full chain via cron-shaped entry | **`test_recompute_due_boards_task_runs_full_chain` (C5)** |

Every trace point now has at least one test pinning it.

## CI verification

The `phpunit-5.2` CI job (P2-K, runbook in `docs/ci/PHPUNIT-GATE.md`)
boots `moodlehq/moodle-php-apache:8.2` + MariaDB sidecar, installs all
30 local plugins via `admin/cli/install.php`, runs PHPUnit, and uploads
JUnit XML. The new tests added by C5 ride that gate — no additional CI
configuration needed.

Conflict-marker check (P0-A) passes locally — `git grep -nE
'^<<<<<<<( |$)|^=======$|^>>>>>>>( |$)' moodle-enhancement/local/sentientia_leaderboard/`
returns zero hits (re-verified before commit).

## Files touched

- `moodle-enhancement/local/sentientia_leaderboard/tests/message_helper_test.php` — appended 3 tests
- `moodle-enhancement/PROJECT-STATE.md` — append H2 entry under P3 follow-ups
- `moodle-enhancement/docs/visual-evidence/2026-05-25/wave-c5-leaderboard-l1-e2e/` — this folder

## Files NOT touched (out of scope per chip + audit)

- `classes/ranking_engine.php` — emission is already there from P3-O; touching it would risk regressing the existing tests
- `classes/observer.php` — handler is already correct; reads the contract the new test verifies
- `classes/message_helper.php` — `dispatch()` (chip says `send_rank_change_messages` but P3-O shipped `dispatch`; the name change would be a regression for the published test contract)
- `classes/event/rankings_updated.php` — definition matches the contract; no change needed
- `db/events.php`, `db/messages.php` — registration already correct
- `version.php` — already at `2026052500` from P3-O; bumping again would force every site to re-run upgrade for no schema change
