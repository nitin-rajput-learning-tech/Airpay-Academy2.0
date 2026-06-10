<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SANDBOX-KIT (rollout-gate Phase 2) — data-intact parity check for the
 * ninja-sandbox migration rehearsal and the eventual live replacement.
 *
 * Captures the counts that define "existing Academy users' data intact":
 * per-tenant active users, plus global courses, enrolments, completions,
 * certificate issues, quiz attempts, badges issued, and SCORM attempts.
 *
 * Usage (run on the SOURCE deployment before migration):
 *   php migration_parity_check.php --baseline=/path/baseline.json
 *
 * Then on the TARGET (sandbox after restore+upgrade, or live after cutover):
 *   php migration_parity_check.php --compare=/path/baseline.json
 *
 * Exit 0 = every metric matches the baseline; exit 1 = drift listed.
 * No flags = print current counts only.
 *
 * @package local_sentientia_platform
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['baseline' => '', 'compare' => '', 'help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)));
}
if ($options['help']) {
    cli_writeln('Data-intact parity check. --baseline=FILE to save, --compare=FILE to verify.');
    exit(0);
}

global $DB;

/** Collect the parity metric set. */
function sentientia_parity_counts(): array {
    global $DB;
    $c = [];

    // Per-tenant active (non-deleted) users, tenant = leading open_path segment.
    foreach ([1 => 'airpay', 77 => 'public', 177 => 'zeea'] as $root => $label) {
        $c["users_tenant_{$label}"] = (int) $DB->count_records_select('user',
            "deleted = 0 AND (" . $DB->sql_like('open_path', ':p1') . " OR open_path = :p2)",
            ['p1' => "/{$root}/%", 'p2' => "/{$root}"]);
    }
    $c['users_total_active'] = (int) $DB->count_records('user', ['deleted' => 0]);
    $c['users_suspended']    = (int) $DB->count_records('user', ['deleted' => 0, 'suspended' => 1]);

    $c['courses']            = (int) $DB->count_records('course');
    $c['course_categories']  = (int) $DB->count_records('course_categories');
    $c['enrolments']         = (int) $DB->count_records('user_enrolments');
    $c['completions']        = (int) $DB->count_records('course_completions');
    $c['module_completions'] = (int) $DB->count_records('course_modules_completion');
    $c['quiz_attempts']      = (int) $DB->count_records('quiz_attempts');
    $c['scorm_attempts']     = (int) $DB->count_records('scorm_attempt');
    $c['badges_issued']      = (int) $DB->count_records('badge_issued');
    $c['grade_grades']       = (int) $DB->count_records('grade_grades');

    // Certificates: tool_certificate issues if installed (the customer cert stack).
    foreach (['tool_certificate_issues', 'customcert_issues'] as $t) {
        if ($DB->get_manager()->table_exists($t)) {
            $c["cert_{$t}"] = (int) $DB->count_records($t);
        }
    }

    // Sentientia product tables that carry user data worth proving intact.
    foreach (['local_sentientia_courses_remind_sent' => 'remind_audit',
              'local_sentientia_feature_flags'        => 'feature_flag_rows'] as $t => $k) {
        if ($DB->get_manager()->table_exists($t)) {
            $c[$k] = (int) $DB->count_records($t);
        }
    }
    return $c;
}

$counts = sentientia_parity_counts();

if ($options['baseline'] !== '') {
    file_put_contents($options['baseline'], json_encode([
        'captured_at' => time(),
        'wwwroot'     => $CFG->wwwroot,
        'release'     => $CFG->release,
        'counts'      => $counts,
    ], JSON_PRETTY_PRINT));
    cli_writeln('Baseline saved: ' . $options['baseline']);
    foreach ($counts as $k => $v) {
        cli_writeln(sprintf('  %-24s %d', $k, $v));
    }
    exit(0);
}

if ($options['compare'] !== '') {
    $base = json_decode(@file_get_contents($options['compare']), true);
    if (!$base || empty($base['counts'])) {
        cli_error('Cannot read baseline file: ' . $options['compare']);
    }
    cli_writeln('Baseline: ' . ($base['wwwroot'] ?? '?') . ' @ '
        . userdate($base['captured_at'] ?? 0) . ' (' . ($base['release'] ?? '?') . ')');
    cli_writeln('Current:  ' . $CFG->wwwroot . ' (' . $CFG->release . ')');
    $drift = 0;
    foreach ($base['counts'] as $k => $expected) {
        $got = $counts[$k] ?? null;
        if ($got === (int) $expected) {
            cli_writeln(sprintf('  MATCH %-24s %d', $k, $got));
        } else {
            cli_writeln(sprintf('  DRIFT %-24s expected %s got %s', $k,
                var_export((int) $expected, true), var_export($got, true)));
            $drift++;
        }
    }
    // New metrics present now but absent from the baseline are informational.
    foreach (array_diff_key($counts, $base['counts']) as $k => $v) {
        cli_writeln(sprintf('  NEW   %-24s %d (not in baseline)', $k, $v));
    }
    cli_writeln($drift === 0
        ? 'RESULT: 100% PARITY — data intact.'
        : "RESULT: $drift metric(s) DRIFTED — investigate before proceeding.");
    exit($drift === 0 ? 0 : 1);
}

foreach ($counts as $k => $v) {
    cli_writeln(sprintf('%-24s %d', $k, $v));
}
exit(0);
