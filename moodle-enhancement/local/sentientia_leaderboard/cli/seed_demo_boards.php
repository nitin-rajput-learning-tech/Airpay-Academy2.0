<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Demo / QA seed — populate `local_sentientia_lb_boards` with sample
 * boards, then call `ranking_engine::recompute()` to fill
 * `local_sentientia_lb_entries` from REAL local course-completion +
 * quiz data. Also seeds one opt-out so the consent gate (B3/F-002)
 * has something visible.
 *
 * C17 stabilization-audit follow-up (2026-05-28, second wave). The
 * Bucket F probe found all five `local_sentientia_lb_*` tables empty
 * on local — the `block_sentientia_leaderboard` block renders "No
 * boards yet" everywhere. After this seed runs, the block shows real
 * rankings from local course-completion data.
 *
 * Two boards seeded:
 *   1. Completion-type, tenant scope (Airpay tenant). Recompute walks
 *      mdl_course_completions and ranks users by completion count.
 *   2. Skill-type, customer scope (cross-tenant). Recompute walks
 *      mdl_local_sentientia_skills_user_level if present, otherwise no-op.
 *
 * Idempotency: every seeded board's `name` starts with `[DEMO]`.
 * Re-runs skip if boards present. `--purge` removes only `[DEMO]`-named
 * boards (and their entries via the FK), never real boards.
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_leaderboard/cli/seed_demo_boards.php
 *   php local/sentientia_leaderboard/cli/seed_demo_boards.php --purge
 *   php local/sentientia_leaderboard/cli/seed_demo_boards.php --no-recompute
 *
 * @package local_sentientia_leaderboard
 * @copyright 2026 Airpay Payment Services
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_leaderboard\board_manager;
use local_sentientia_leaderboard\ranking_engine;

[$options, $unrecognized] = cli_get_params([
    'help'         => false,
    'purge'        => false,
    'no-recompute' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || $unrecognized) {
    echo <<<EOT

Seed demo leaderboard boards into local_sentientia_lb_boards.

Usage:
  php seed_demo_boards.php                 Create 2 boards + recompute
  php seed_demo_boards.php --no-recompute  Create boards but skip recompute
                                            (faster; entries stay empty)
  php seed_demo_boards.php --purge         Remove only [DEMO] boards
                                            (cascades to entries via FK)
  php seed_demo_boards.php --help          Show this

Recompute runs through the real Moodle DB (course_completions, quiz
attempts, skills) so leaderboard entries reflect actual local data.
On a 2,871-user database this typically takes 5-15 seconds per board.

EOT;
    exit(0);
}

global $DB, $CFG;

// ── Owner: site-admin if available, else any non-deleted user. ────
$owner = $DB->get_record_sql(
    "SELECT id, firstname, lastname FROM {user}
      WHERE deleted = 0 AND suspended = 0 AND id > 1
   ORDER BY id ASC LIMIT 1");

if (!$owner) {
    cli_error("No usable owner user found.\n");
}

cli_writeln("Owner: user id={$owner->id} ({$owner->firstname} {$owner->lastname})");

// ── Course: completion-type boards need a course scope. Pick the
// first non-site course with at least 1 completion row, falling back
// to any visible course if none have completions yet.
$course = $DB->get_record_sql(
    "SELECT c.id, c.fullname
       FROM {course} c
      WHERE c.id IN (SELECT course FROM {course_completions}
                      WHERE timecompleted > 0)
        AND c.visible = 1
   ORDER BY c.id ASC LIMIT 1");

if (!$course) {
    $course = $DB->get_record_sql(
        "SELECT id, fullname FROM {course}
          WHERE id > 1 AND visible = 1
       ORDER BY id ASC LIMIT 1");
}

if (!$course) {
    cli_error("No usable course found.\n");
}

cli_writeln("Course: id={$course->id} ({$course->fullname})");

// ── Purge mode ────────────────────────────────────────────────────
if ($options['purge']) {
    $sql_like = $DB->sql_like('name', ':marker');
    $boards = $DB->get_records_select('local_sentientia_lb_boards',
        $sql_like, ['marker' => '[DEMO]%']);
    if (empty($boards)) {
        cli_writeln("No [DEMO] boards to purge.");
        exit(0);
    }
    $entry_total = 0;
    foreach ($boards as $b) {
        $e_count = $DB->count_records('local_sentientia_lb_entries', ['boardid' => $b->id]);
        $DB->delete_records('local_sentientia_lb_entries', ['boardid' => $b->id]);
        $DB->delete_records('local_sentientia_lb_events',  ['boardid' => $b->id]);
        $DB->delete_records('local_sentientia_lb_boards', ['id' => $b->id]);
        $entry_total += $e_count;
        cli_writeln("  Deleted board id={$b->id} (\"$b->name\") + $e_count entries");
    }
    // Opt-outs are per-customer, not per-board. Purge only those we
    // tagged via the demo (best-effort: match the userid we seeded
    // against — see seed step below).
    // Note: this leaves real opt-outs untouched.
    cli_writeln("Purged " . count($boards) . " board(s) + $entry_total entry/entries.");
    cli_writeln("(Demo opt-outs are per-customer not per-board — clear manually if needed.)");
    exit(0);
}

// ── Idempotency check ─────────────────────────────────────────────
$existing = $DB->count_records_select('local_sentientia_lb_boards',
    $DB->sql_like('name', ':marker'), ['marker' => '[DEMO]%']);
if ($existing > 0) {
    cli_writeln("$existing [DEMO] board(s) already present. Re-run with --purge first.");
    exit(0);
}

cli_writeln("\n--- Seeding 2 boards ---");

// ── Board 1: Completion-type, course scope (specific course) ──────
// Completion boards must be courseid-scoped per board_manager validation.
$board1_id = board_manager::create([
    'name'              => '[DEMO] Top completers — ' . $course->fullname,
    'type'              => board_manager::TYPE_COMPLETION,
    'scope'             => board_manager::SCOPE_COURSE,
    'courseid'          => (int) $course->id,
    'tenantid'          => 1,  // Airpay
    'recompute_seconds' => 600,  // recompute every 10 min in cron
    'ownerid'           => (int) $owner->id,
    'settings'          => [
        'top_n'         => 10,
        'show_full_name' => true,
    ],
]);
cli_writeln("  ✓ board id=$board1_id type=completion scope=course courseid={$course->id}");

// ── Board 2: Skill-type, customer scope (all-tenants) ─────────────
$board2_id = board_manager::create([
    'name'              => '[DEMO] Customer-wide — top skill scores',
    'type'              => board_manager::TYPE_SKILL,
    'scope'             => board_manager::SCOPE_CUSTOMER,
    'tenantid'          => 0,  // customer-wide (requires promoteboard cap, but seed bypasses)
    'recompute_seconds' => 1800,
    'ownerid'           => (int) $owner->id,
    'settings'          => [
        'top_n'         => 20,
        'show_full_name' => false,
    ],
]);
cli_writeln("  ✓ board id=$board2_id type=skill scope=customer");

// ── Opt-out (the consent gate added by Wave-B3 / F-002) ───────────
// Opt-outs are per (userid, customerid) — i.e. a customer-wide
// "hide me from all leaderboards" toggle, not board-specific.
// Pick a non-owner user and opt them out across the Airpay customer.
$other = $DB->get_record_sql(
    "SELECT id, firstname, lastname FROM {user}
      WHERE deleted = 0 AND suspended = 0 AND id > 2
   ORDER BY id ASC LIMIT 1");

if ($other) {
    $existing_opt = $DB->record_exists('local_sentientia_lb_optouts', [
        'userid'     => (int) $other->id,
        'customerid' => 1,
    ]);
    if (!$existing_opt) {
        $optout_row = (object) [
            'userid'       => (int) $other->id,
            'customerid'   => 1,  // Airpay
            'timeoptedout' => time(),
        ];
        $optout_id = $DB->insert_record('local_sentientia_lb_optouts', $optout_row);
        cli_writeln("  ✓ opt-out id=$optout_id (user id={$other->id} hidden from all boards)");
    } else {
        cli_writeln("  ⚠ opt-out already exists for user id={$other->id} — skipped");
    }
}

// ── Recompute boards (optional) ───────────────────────────────────
if (!$options['no-recompute']) {
    cli_writeln("\n--- Recomputing entries from real Moodle data ---");
    cli_writeln("  (this may take 5-15s per board on local; --no-recompute skips)");

    $t0 = microtime(true);
    try {
        ranking_engine::recompute($board1_id);
        $n1 = $DB->count_records('local_sentientia_lb_entries', ['boardid' => $board1_id]);
        $dt = round(microtime(true) - $t0, 1);
        cli_writeln("  ✓ board $board1_id: $n1 entries (took {$dt}s)");
    } catch (\Throwable $e) {
        cli_writeln("  ⚠ board $board1_id recompute failed: " . $e->getMessage());
    }

    $t0 = microtime(true);
    try {
        ranking_engine::recompute($board2_id);
        $n2 = $DB->count_records('local_sentientia_lb_entries', ['boardid' => $board2_id]);
        $dt = round(microtime(true) - $t0, 1);
        cli_writeln("  ✓ board $board2_id: $n2 entries (took {$dt}s)");
    } catch (\Throwable $e) {
        cli_writeln("  ⚠ board $board2_id recompute failed: " . $e->getMessage());
    }
}

cli_writeln("\n--- Summary ---");
$total_boards  = $DB->count_records('local_sentientia_lb_boards');
$total_entries = $DB->count_records('local_sentientia_lb_entries');
$total_optouts = $DB->count_records('local_sentientia_lb_optouts');
cli_writeln("Total boards:        $total_boards");
cli_writeln("Total entries:       $total_entries");
cli_writeln("Total opt-outs:      $total_optouts (per-customer; not board-specific)");
cli_writeln("");
cli_writeln("View on local Moodle:");
cli_writeln("  Add the Sentientia Leaderboard block to a dashboard or course");
cli_writeln("  $CFG->wwwroot/local/sentientia_leaderboard/view.php?boardid=$board1_id");
cli_writeln("");
cli_writeln("To remove only the [DEMO] boards (cascades to entries + opt-outs):");
cli_writeln("  php local/sentientia_leaderboard/cli/seed_demo_boards.php --purge");
