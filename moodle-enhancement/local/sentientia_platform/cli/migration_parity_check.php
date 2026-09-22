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
 * Exit 0 = counts AND value checksums match the baseline.
 * Exit 1 = drift, listed per metric and per table.
 * Exit 2 = counts match but values could not be checked, so the data is
 *          NOT proven intact (old baseline, or a non-MySQL engine).
 * No flags = print current counts and checksums.
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

/**
 * Per-table value checksums.
 *
 * Counts alone cannot see a migration that preserved every row but changed
 * what is IN them -- a truncated column, a collation change mangling
 * non-ASCII names, timestamps shifted by a timezone, grades rounded
 * differently. This sums a CRC over the meaningful columns of each critical
 * table, so any changed value moves the total.
 *
 * SUM(CRC32(...)) rather than BIT_XOR: xor cancels duplicate rows, sum does
 * not. NULLs are given an explicit sentinel because CONCAT_WS skips them,
 * which would let (a, NULL, b) and (a, b, NULL) collide.
 *
 * Floats are rounded before hashing: a float rendered as a string is not
 * guaranteed identical across engine versions, and a spurious drift here
 * would be worse than no check, because it teaches people to ignore it.
 *
 * MySQL and MariaDB only -- CRC32 is not portable. On any other engine this
 * returns null for every table, and the comparison below reports those as
 * SKIPPED rather than counting them as matches.
 *
 * @return array<string,array{rows:int,crc:string|null}>
 */
function sentientia_parity_checksums(): array {
    global $DB, $CFG;

    // Column list per table. Deliberately explicit: adding a column to the
    // schema should not silently change the checksum of an old baseline.
    $tables = [
        'user' => ['id', 'username', 'email', 'firstname', 'lastname',
                   'open_path', 'suspended', 'deleted', 'auth'],
        'course' => ['id', 'shortname', 'fullname', 'category', 'visible',
                     'startdate', 'enddate'],
        'course_categories' => ['id', 'name', 'parent', 'visible'],
        'user_enrolments' => ['id', 'enrolid', 'userid', 'status',
                              'timestart', 'timeend'],
        'course_completions' => ['id', 'userid', 'course', 'timecompleted'],
        'course_modules_completion' => ['id', 'coursemoduleid', 'userid',
                                        'completionstate'],
        'quiz_attempts' => ['id', 'quiz', 'userid', 'attempt', 'state'],
        'badge_issued' => ['id', 'badgeid', 'userid', 'dateissued'],
    ];
    // Grades carry floats; round them so the hash is stable.
    $rounded = [
        'grade_grades' => ['id', 'itemid', 'userid'],
    ];

    $family = $DB->get_dbfamily();
    $out = [];

    foreach (array_merge($tables, $rounded) as $table => $cols) {
        if (!$DB->get_manager()->table_exists($table)) {
            continue;
        }

        $existing = array_keys($DB->get_columns($table));
        $use = array_values(array_intersect($cols, $existing));
        if (empty($use)) {
            continue;
        }

        $rows = (int) $DB->count_records($table);

        if ($family !== 'mysql') {
            // No portable CRC. Say so rather than omit the table, so a
            // comparison on this engine cannot read as a clean pass.
            $out[$table] = ['rows' => $rows, 'crc' => null];
            continue;
        }

        $parts = [];
        foreach ($use as $c) {
            $parts[] = "IFNULL(`{$c}`, '~NULL~')";
        }
        if (isset($rounded[$table])) {
            foreach (['rawgrade', 'finalgrade'] as $f) {
                if (in_array($f, $existing, true)) {
                    $parts[] = "IFNULL(ROUND(`{$f}`, 5), '~NULL~')";
                }
            }
        }
        $expr = 'CONCAT_WS(0x1f, ' . implode(', ', $parts) . ')';

        $crc = $DB->get_field_sql(
            "SELECT COALESCE(SUM(CRC32({$expr})), 0) FROM {" . $table . "}");
        $out[$table] = ['rows' => $rows, 'crc' => (string) $crc];
    }

    return $out;
}

$counts = sentientia_parity_counts();
$checksums = sentientia_parity_checksums();

if ($options['baseline'] !== '') {
    file_put_contents($options['baseline'], json_encode([
        'captured_at' => time(),
        'wwwroot'     => $CFG->wwwroot,
        'release'     => $CFG->release,
        'counts'      => $counts,
        'checksums'   => $checksums,
        'dbfamily'    => $DB->get_dbfamily(),
    ], JSON_PRETTY_PRINT));
    cli_writeln('Baseline saved: ' . $options['baseline']);
    foreach ($counts as $k => $v) {
        cli_writeln(sprintf('  %-24s %d', $k, $v));
    }
    cli_writeln('');
    cli_writeln('Value checksums:');
    foreach ($checksums as $t => $cs) {
        cli_writeln(sprintf('  %-28s rows=%-9d crc=%s', $t, $cs['rows'],
            $cs['crc'] ?? '(unsupported on this engine)'));
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

    // Value checksums. A migration can preserve every count above while
    // changing what is in the rows; the counts cannot see that, so before
    // 2026-09-22 this script printed "data intact" on evidence that could not
    // support it.
    cli_writeln('');
    cli_writeln('Value checksums:');
    $skipped = 0;
    if (empty($base['checksums'])) {
        cli_writeln('  SKIPPED - the baseline predates value checksums.');
        $skipped++;
    } else {
        foreach ($base['checksums'] as $t => $basecs) {
            $now = $checksums[$t] ?? null;
            if ($now === null) {
                cli_writeln(sprintf('  MISSING %-26s in baseline, absent here', $t));
                $drift++;
                continue;
            }
            if ($basecs['crc'] === null || $now['crc'] === null) {
                cli_writeln(sprintf('  SKIPPED %-26s (checksums unsupported on '
                    . 'one side; baseline=%s, here=%s)', $t,
                    $base['dbfamily'] ?? '?', $DB->get_dbfamily()));
                $skipped++;
                continue;
            }
            if ((string) $basecs['crc'] === (string) $now['crc']
                && (int) $basecs['rows'] === (int) $now['rows']) {
                cli_writeln(sprintf('  MATCH   %-26s rows=%d', $t, $now['rows']));
            } else {
                cli_writeln(sprintf('  DRIFT   %-26s rows %d->%d  crc %s->%s',
                    $t, (int) $basecs['rows'], (int) $now['rows'],
                    $basecs['crc'], $now['crc']));
                $drift++;
            }
        }
    }

    cli_writeln('');
    if ($drift > 0) {
        cli_writeln("RESULT: $drift metric(s) DRIFTED - investigate before proceeding.");
        exit(1);
    }
    if ($skipped > 0) {
        // Deliberately NOT "100% parity". Saying so here would be the same
        // defect as the rest of this file's history: a success message the
        // evidence does not support.
        cli_writeln("RESULT: counts match, but $skipped table(s) could not be "
            . 'value-checked. Data is NOT proven intact - re-run with a '
            . 'checksum-capable baseline on MySQL or MariaDB.');
        exit(2);
    }
    cli_writeln('RESULT: 100% PARITY - counts AND value checksums match.');
    exit(0);
}

foreach ($counts as $k => $v) {
    cli_writeln(sprintf('%-24s %d', $k, $v));
}
cli_writeln('');
cli_writeln('Value checksums:');
foreach ($checksums as $t => $cs) {
    cli_writeln(sprintf('  %-28s rows=%-9d crc=%s', $t, $cs['rows'],
        $cs['crc'] ?? '(unsupported on this engine)'));
}
exit(0);
