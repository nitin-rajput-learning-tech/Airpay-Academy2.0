<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Demo / QA seed — populate `local_sentientia_rec_log` with sample
 * AI-generated course recommendations across multiple statuses so the
 * "Recommended for you" dashboard block + future admin landing show
 * real data on local without burning Anthropic tokens.
 *
 * C17 stabilization-audit follow-up (2026-05-28, second wave). The
 * Bucket F probe found `local_sentientia_rec_log` empty on local —
 * the recommendations block always rendered "no recommendations yet".
 *
 * Idempotency: every seeded row uses model = `[DEMO]-claude-mock-seed`
 * (a non-standard model identifier that real generations would never
 * produce). Re-runs skip if seeded rows present. `--purge` removes
 * only rows with that model identifier, never touching real ones.
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_recommendations/cli/seed_demo_recommendations.php
 *   php local/sentientia_recommendations/cli/seed_demo_recommendations.php --purge
 *
 * @package local_sentientia_recommendations
 * @copyright 2026 Airpay Payment Services
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_recommendations\recommendation_engine;

const SEED_MODEL_MARKER = '[DEMO]-claude-mock-seed';

[$options, $unrecognized] = cli_get_params([
    'help'  => false,
    'purge' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || $unrecognized) {
    echo <<<EOT

Seed demo course recommendations into local_sentientia_rec_log.

Usage:
  php seed_demo_recommendations.php          Add 1 active batch (3-5 rows)
                                              + 1 dismissed + 1 enrolled
  php seed_demo_recommendations.php --purge  Remove only seeded rows
  php seed_demo_recommendations.php --help   Show this

Creates a recommendation batch for a real local user across 3-5
real local courses, populated through recommendation_engine::persist_batch()
so the data shape matches what live generation would produce.
Status spread covers active + dismissed + enrolled lifecycle states.

EOT;
    exit(0);
}

global $DB, $CFG;

// ── User: pick any non-deleted, non-suspended user with id > 1. ──
$user = $DB->get_record_sql(
    "SELECT id, firstname, lastname FROM {user}
      WHERE deleted = 0 AND suspended = 0 AND id > 1
   ORDER BY id ASC LIMIT 1");

if (!$user) {
    cli_error("No usable user found.\n");
}

cli_writeln("Learner: user id={$user->id} ({$user->firstname} {$user->lastname})");

// ── Purge mode ────────────────────────────────────────────────────
if ($options['purge']) {
    $rows = $DB->get_records('local_sentientia_rec_log',
        ['model' => SEED_MODEL_MARKER]);
    if (empty($rows)) {
        cli_writeln("No [DEMO] recommendations to purge.");
        exit(0);
    }
    $DB->delete_records('local_sentientia_rec_log', ['model' => SEED_MODEL_MARKER]);
    cli_writeln("Purged " . count($rows) . " recommendation row(s).");
    exit(0);
}

// ── Idempotency check ─────────────────────────────────────────────
$existing = $DB->count_records('local_sentientia_rec_log',
    ['model' => SEED_MODEL_MARKER]);
if ($existing > 0) {
    cli_writeln("$existing [DEMO] recommendation(s) already present. Re-run with --purge first.");
    exit(0);
}

// ── Pick 3-5 candidate courses from the local DB ──────────────────
$candidates = $DB->get_records_sql(
    "SELECT id, fullname, shortname FROM {course}
      WHERE id > 1 AND visible = 1
   ORDER BY id ASC", null, 0, 5);

if (count($candidates) < 1) {
    cli_error("No usable visible courses found.\n");
}

cli_writeln("Candidates: " . count($candidates) . " course(s)");

// ── Build "parsed" recommendation array ───────────────────────────
// Shape matches what response_parser::parse() returns from Claude:
// each item has course_id (int), score (0-100), reasoning (string).
//
// Reasoning copy is intentionally human-readable and references the
// course title so the dashboard block's "Why this?" tooltip surfaces
// something that looks AI-generated even though it's deterministic.
$reasonings = [
    'Foundational pick that closes a known skill gap based on your last 90 days of activity.',
    'Sequel to a course you finished recently — same instructor and depth profile.',
    'High-demand topic across your peer cohort; 78% of teammates have completed it.',
    'Bridges two skill areas you\'ve scored at the intermediate level — natural next step.',
    'Compliance refresher whose previous version you completed > 12 months ago.',
];

$parsed = [];
$rank = 1;
foreach ($candidates as $course) {
    $parsed[] = (object) [
        'course_id' => (int) $course->id,
        'score'     => max(40, 95 - ($rank * 10)),  // 85, 75, 65, 55, 45
        'reasoning' => $reasonings[$rank - 1] ?? 'Recommended next step for your learning path.',
    ];
    $rank++;
}

cli_writeln("\n--- Seeding 1 batch of " . count($parsed) . " recommendations ---");

$batchid = recommendation_engine::persist_batch(
    (int) $user->id,
    $parsed,
    /* tokens_in */  1500,
    /* tokens_out */ 800,
    /* mode */ 'mock',
    /* model */ SEED_MODEL_MARKER
);

if ($batchid === '') {
    cli_error("persist_batch returned empty batchid — something went wrong.\n");
}

cli_writeln("  ✓ batch $batchid persisted (" . count($parsed) . " rows, all status=active)");

// ── Mutate statuses: 1 dismissed + 1 enrolled (using update_status) ─
// Pull the rows we just inserted, then flip a couple to demo lifecycle.
$rows = $DB->get_records('local_sentientia_rec_log',
    ['userid' => (int) $user->id, 'batchid' => $batchid],
    'rank_order ASC');

// NOTE: $DB->get_records() may return numeric columns as strings depending
// on the database driver. Cast to int before any === comparison.
$flipped = 0;
foreach ($rows as $r) {
    $rank = (int) $r->rank_order;
    if ($flipped === 0 && $rank === 2) {
        // Rank 2 → dismissed (learner clicked "not interested")
        if (recommendation_engine::update_status((int) $r->id, (int) $user->id, 'dismissed')) {
            cli_writeln("  ✓ row id={$r->id} (course {$r->courseid}) → dismissed");
            $flipped++;
        }
    } else if ($flipped === 1 && $rank === 3) {
        // Rank 3 → enrolled (learner clicked "enrol")
        if (recommendation_engine::update_status((int) $r->id, (int) $user->id, 'enrolled')) {
            cli_writeln("  ✓ row id={$r->id} (course {$r->courseid}) → enrolled");
            $flipped++;
        }
        break;
    }
}

cli_writeln("\n--- Summary ---");
$total = $DB->count_records('local_sentientia_rec_log');
$active = $DB->count_records('local_sentientia_rec_log', ['status' => 'active']);
$dismissed = $DB->count_records('local_sentientia_rec_log', ['status' => 'dismissed']);
$enrolled = $DB->count_records('local_sentientia_rec_log', ['status' => 'enrolled']);
cli_writeln("Total rows:     $total");
cli_writeln("  active:       $active");
cli_writeln("  dismissed:    $dismissed");
cli_writeln("  enrolled:     $enrolled");
cli_writeln("");
cli_writeln("View on local Moodle:");
cli_writeln("  Add 'Recommended for you' block to user id={$user->id}'s dashboard");
cli_writeln("  $CFG->wwwroot/my/   (logged in as that user)");
cli_writeln("");
cli_writeln("To remove only the [DEMO] rows:");
cli_writeln("  php local/sentientia_recommendations/cli/seed_demo_recommendations.php --purge");
