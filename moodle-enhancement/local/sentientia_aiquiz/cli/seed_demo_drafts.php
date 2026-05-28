<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Demo / QA seed — populate `local_sentientia_aiquiz_draft` +
 * `local_sentientia_aiquiz_question` with sample drafts so the
 * generate.php + review.php surfaces (and any future admin-landing
 * dashboard, cf. C16's pattern for sentientia_translate) show real
 * data on local without requiring an Anthropic API key.
 *
 * C17 stabilization-audit follow-up (2026-05-28). The Bucket F probe
 * found both aiquiz tables empty on local — local devs running the
 * generate flow without a real API key (mock-mode only) ended up with
 * partial drafts and no comprehensive review data to play with.
 *
 * Idempotency: every seeded draft's `title` starts with `[DEMO]`.
 * Re-runs skip if drafts present. `--purge` removes only `[DEMO]`-titled
 * drafts (and their questions via the FK), never touching real drafts.
 *
 * Usage (XAMPP):
 *   cd C:\xampp\htdocs\moodle5\public
 *   php local/sentientia_aiquiz/cli/seed_demo_drafts.php
 *   php local/sentientia_aiquiz/cli/seed_demo_drafts.php --purge
 *   php local/sentientia_aiquiz/cli/seed_demo_drafts.php --help
 *
 * @package local_sentientia_aiquiz
 * @copyright 2026 Airpay Payment Services
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_aiquiz\draft_manager;
use local_sentientia_aiquiz\prompt_builder;
use local_sentientia_aiquiz\anthropic_client;

[$options, $unrecognized] = cli_get_params([
    'help'  => false,
    'purge' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || $unrecognized) {
    echo <<<EOT

Seed demo aiquiz drafts into local_sentientia_aiquiz_draft.

Usage:
  php seed_demo_drafts.php          Add 4 sample drafts across all statuses
  php seed_demo_drafts.php --purge  Remove only seeded ([DEMO]) drafts
  php seed_demo_drafts.php --help   Show this

Creates drafts in all four lifecycle states (pending, generated,
approved, failed). The generated + approved drafts also seed 3
sample questions each so review.php has something to render.

EOT;
    exit(0);
}

global $DB, $CFG;

// ── Owner: site admin first row, then any non-deleted user.
$owner = $DB->get_record_sql(
    "SELECT id, firstname, lastname FROM {user}
      WHERE deleted = 0 AND suspended = 0 AND id > 1
   ORDER BY id ASC LIMIT 1");

if (!$owner) {
    cli_error("No usable owner user found.\n");
}

// ── Course: first visible non-site course.
$course = $DB->get_record_sql(
    "SELECT id, fullname FROM {course}
      WHERE id > 1 AND visible = 1
   ORDER BY id ASC LIMIT 1");

if (!$course) {
    // Fallback to site course (id=1) — drafts can be unattached.
    $course = (object) ['id' => 1, 'fullname' => 'Site (fallback)'];
}

cli_writeln("Owner:  user id={$owner->id} ({$owner->firstname} {$owner->lastname})");
cli_writeln("Course: id={$course->id} ({$course->fullname})");

// ── Purge mode ────────────────────────────────────────────────────
if ($options['purge']) {
    $sql_like = $DB->sql_like('title', ':marker');
    $drafts = $DB->get_records_select('local_sentientia_aiquiz_draft',
        $sql_like, ['marker' => '[DEMO]%']);
    if (empty($drafts)) {
        cli_writeln("No [DEMO] drafts to purge.");
        exit(0);
    }
    $q_total = 0;
    foreach ($drafts as $d) {
        $q_count = $DB->count_records('local_sentientia_aiquiz_question', ['draftid' => $d->id]);
        $DB->delete_records('local_sentientia_aiquiz_question', ['draftid' => $d->id]);
        $DB->delete_records('local_sentientia_aiquiz_draft', ['id' => $d->id]);
        $q_total += $q_count;
        cli_writeln("  Deleted draft id={$d->id} (\"$d->title\") + $q_count question(s)");
    }
    cli_writeln("Purged " . count($drafts) . " draft(s) + $q_total question(s).");
    exit(0);
}

// ── Idempotency check ─────────────────────────────────────────────
$existing = $DB->count_records_select('local_sentientia_aiquiz_draft',
    $DB->sql_like('title', ':marker'), ['marker' => '[DEMO]%']);
if ($existing > 0) {
    cli_writeln("$existing [DEMO] draft(s) already present. Re-run with --purge first.");
    exit(0);
}

// ── Sample source content (compliance training paragraph) ──────────
$source_text = <<<EOT
Information security is everyone's responsibility at Airpay. All employees must follow these core principles when handling sensitive customer data: (1) Never share credentials via email or chat. (2) Always lock your workstation when stepping away. (3) Report suspicious emails to the security team immediately. (4) Use strong, unique passwords managed through the company password manager. (5) Encrypt all confidential files at rest and in transit. Following these practices helps maintain customer trust and ensures regulatory compliance with PCI-DSS, ISO 27001, and India's DPDP Act.
EOT;

// ── Sample generated questions (3 questions, multichoice) ─────────
$sample_questions = [
    (object) [
        'qtype'         => 'multichoice',
        'qtext'         => 'Which of the following is the correct way to handle a suspicious email?',
        'qoptions_json' => json_encode([
            'A' => 'Forward it to all colleagues',
            'B' => 'Click any embedded links to investigate',
            'C' => 'Report it to the security team immediately',
            'D' => 'Delete it without reporting',
        ]),
        'qanswer'      => 'C',
        'qexplanation' => 'Reporting to the security team allows them to investigate, warn other employees, and update threat-detection systems.',
    ],
    (object) [
        'qtype'         => 'multichoice',
        'qtext'         => 'Information security at Airpay is the responsibility of:',
        'qoptions_json' => json_encode([
            'A' => 'Only the IT department',
            'B' => 'Only senior management',
            'C' => 'Only employees handling customer data',
            'D' => 'Every employee',
        ]),
        'qanswer'      => 'D',
        'qexplanation' => 'The source content opens with "Information security is everyone\'s responsibility at Airpay."',
    ],
    (object) [
        'qtype'         => 'multichoice',
        'qtext'         => 'Which standards does the source mention as relevant compliance frameworks?',
        'qoptions_json' => json_encode([
            'A' => 'PCI-DSS, ISO 27001, DPDP Act',
            'B' => 'GDPR, HIPAA, SOX',
            'C' => 'SOC 2, NIST, COBIT',
            'D' => 'Only PCI-DSS',
        ]),
        'qanswer'      => 'A',
        'qexplanation' => 'The closing sentence lists "PCI-DSS, ISO 27001, and India\'s DPDP Act" as the relevant frameworks.',
    ],
];

$default_model = anthropic_client::DEFAULT_MODEL;

cli_writeln("\n--- Seeding 4 drafts ---");

// ── Draft 1: pending ──────────────────────────────────────────────
$pending_id = draft_manager::create_pending(
    (int) $owner->id,
    (int) $course->id,
    '[DEMO] Pending — Information Security 101',
    $source_text,
    $default_model,
    /* num_requested */ 5
);
cli_writeln("  ✓ draft id=$pending_id status=pending");

// ── Draft 2: generated (with questions awaiting review) ───────────
$generated_id = draft_manager::create_pending(
    (int) $owner->id,
    (int) $course->id,
    '[DEMO] Generated — Awaiting review',
    $source_text,
    $default_model,
    /* num_requested */ 3
);
draft_manager::persist_questions(
    $generated_id,
    $sample_questions,
    /* tokens_in */ 1200,
    /* tokens_out */ 800,
    /* mode */ 'mock'
);
cli_writeln("  ✓ draft id=$generated_id status=generated (3 questions)");

// ── Draft 3: approved (questions all approved + finalised) ────────
$approved_id = draft_manager::create_pending(
    (int) $owner->id,
    (int) $course->id,
    '[DEMO] Approved — Ready for push',
    $source_text,
    $default_model,
    /* num_requested */ 3
);
draft_manager::persist_questions(
    $approved_id,
    $sample_questions,
    /* tokens_in */ 1200,
    /* tokens_out */ 800,
    /* mode */ 'mock'
);
// Approve each question.
$approved_qs = $DB->get_records('local_sentientia_aiquiz_question',
    ['draftid' => $approved_id], 'sortorder ASC');
foreach ($approved_qs as $q) {
    draft_manager::review_question((int) $q->id, 'approved');
}
draft_manager::finalise_review($approved_id, (int) $owner->id);
cli_writeln("  ✓ draft id=$approved_id status=approved (3 questions reviewed)");

// ── Draft 4: failed ───────────────────────────────────────────────
$failed_id = draft_manager::create_pending(
    (int) $owner->id,
    (int) $course->id,
    '[DEMO] Failed — synthetic API error',
    $source_text,
    $default_model,
    /* num_requested */ 5
);
draft_manager::mark_failed($failed_id,
    'mock_failure_demo — synthetic error for seed demo (no real API call was made)');
cli_writeln("  ✓ draft id=$failed_id status=failed");

cli_writeln("\n--- Summary ---");
cli_writeln("Created 4 drafts (pending + generated + approved + failed).");
cli_writeln("Seeded " . (2 * count($sample_questions)) . " questions across 2 reviewable drafts.");
cli_writeln("");
cli_writeln("View on local Moodle:");
cli_writeln("  $CFG->wwwroot/local/sentientia_aiquiz/generate.php   (draft list)");
cli_writeln("  $CFG->wwwroot/local/sentientia_aiquiz/review.php?draftid=$generated_id   (review the generated draft)");
cli_writeln("");
cli_writeln("To remove only the [DEMO] drafts (and their questions):");
cli_writeln("  php local/sentientia_aiquiz/cli/seed_demo_drafts.php --purge");
