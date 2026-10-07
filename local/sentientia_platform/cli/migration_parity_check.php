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
 *   php migration_parity_check.php --compare=/path/baseline.json --decisions=/path/decisions.json --expect-decisions-hash=SHA256
 *
 * Exit 0 = counts AND value checksums match the baseline.
 * Exit 1 = drift, listed per metric and per table, OR a hard invariant
 *          failed on this deployment whatever the baseline says (today:
 *          message_provider_defaults - providers missing message defaults
 *          must be 0, or message_send() throws for them).
 * Exit 2 = counts match but values could not be checked, so the data is
 *          NOT proven intact (old baseline, a non-MySQL engine, or an
 *          invariant that could not run: printed as SKIPPED with the reason).
 * Exit 3 = refused before anything was compared: the --decisions file cannot be read, or its sha256 is not the one
 *          --expect-decisions-hash names (the cutover must run on the rehearsed decisions).
 * No flags = print current counts and checksums.
 *
 * BizLMS data import (ADR-032, "Parity hooks"; wired 2026-10-07, owner decisions of the courses cluster):
 *   --baseline also stores a fingerprint of every legacy table (row count, MAX(id), CRC over all columns, column list),
 *   taken with no CRC cap, when the import framework is deployed here.
 *   --compare then proves the legacy tables are intact (a changed table or a missing one is drift, a skipped CRC or a
 *   table that is not in the baseline is "not proven", exit 2), runs the bizlms_import invariant (every feature complete,
 *   source = map, no row without a map row, no imported row whose target is gone, tenant paths valid, no legacy source
 *   changed since its step ran, every importer's verify() clean: any problem is exit 1; it needs --decisions=FILE because
 *   verify() reads owner decisions (cart.abandoned, notifications.import_bodies, ...): without the file the invariant is
 *   SKIPPED, exit 2 "not proven", never FAIL, and --expect-decisions-hash=SHA256 pins the file to the rehearsed one and
 *   refuses a different one, exit 3), and EXPLAINS the one difference the
 *   import makes on purpose to a counted table: the manual enrolments the enrolments importer (gap G6) wrote into core
 *   user_enrolments (about 7 733 on the April 2026 copy). The explanation comes from the legacy map and only covers exactly
 *   those rows, in the count and in the checksum (a SUM of per-row CRCs, so the added rows' CRCs must add up); any other
 *   difference stays DRIFT. --crc-max-rows=N skips the CRC of a legacy table above N rows (a skipped CRC is never a pass).
 *
 * @package local_sentientia_platform
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['baseline' => '', 'compare' => '', 'crc-max-rows' => 0, 'decisions' => '', 'expect-decisions-hash' => '',
     'help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)));
}
if ($options['help']) {
    cli_writeln('Data-intact parity check. --baseline=FILE to save, --compare=FILE to verify, '
        . '--crc-max-rows=N to skip the CRC of a legacy table above N rows (never a pass). '
        . 'With --compare, --decisions=FILE (the rehearsed BizLMS import decisions) lets the bizlms_import invariant run '
        . '(without it that invariant is SKIPPED, exit 2) and --expect-decisions-hash=SHA256 refuses any other file (exit 3).');
    exit(0);
}

global $DB;

/**
 * The decisions the BizLMS import ran with, for --compare. Exits 3 (refused) when the file cannot be used or is not the
 * one the caller pinned: comparing against the wrong decisions would answer a different question.
 *
 * @param string $file --decisions
 * @param string $expect --expect-decisions-hash
 * @return \local_sentientia_platform\bizlms\decisions|null Null when no file was given (the invariant is then "not proven").
 */
function sentientia_parity_decisions(string $file, string $expect): ?\local_sentientia_platform\bizlms\decisions {
    $expect = strtolower(trim($expect));
    if ($file === '') {
        if ($expect !== '') {
            cli_writeln('REFUSED: --expect-decisions-hash is the hash of the --decisions file, and none was given.');
            exit(3);
        }
        return null;
    }
    if (!class_exists('\local_sentientia_platform\bizlms\decisions')) {
        cli_writeln('REFUSED: --decisions needs the BizLMS import framework (local_sentientia_platform), which is not deployed here.');
        exit(3);
    }
    try {
        $decisions = \local_sentientia_platform\bizlms\decisions::load($file);
    } catch (\local_sentientia_platform\bizlms\bizlms_exception $e) {
        cli_writeln('REFUSED: ' . $e->getMessage());
        exit(3);
    }
    if ($expect !== '' && !hash_equals($decisions->hash(), $expect)) {
        cli_writeln('REFUSED: decisions_hash_differs_from_the_expected_one (the file is not the rehearsed one).');
        exit(3);
    }
    cli_writeln('Decisions: ' . $file . ' sha256=' . $decisions->hash()
        . ($expect !== '' ? ' (pinned: matches --expect-decisions-hash)'
            : ' (NOT pinned: pass --expect-decisions-hash so the cutover must use the rehearsed file)'));
    return $decisions;
}

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

/**
 * Invariants that must hold on the deployment being checked, whatever the
 * baseline says. They are not compared with the baseline (the source is
 * BizLMS, where they do not apply); any problem is a hard failure.
 *
 * message_provider_defaults: \local_sentientia_platform\message_pref_repair::check()
 * - providers missing a <processor>_provider_<component>_<name>_locked default
 * (message_send() throws for them), legacy site-wide disable switches not
 * carried over, and users' choices stranded under pre-rename names. Found
 * 2026-09-29: 28 of 30 Sentientia providers on a relabelled copy; a count-only
 * parity check could not see it. Repair: repair_task_registrations.php --apply.
 *
 * The check never stops the run. On a BizLMS source box that has the plugin
 * directory but not the tables, or on any DB error, check() can throw; that must
 * not stop --baseline from writing its file. The error is caught and the
 * invariant is reported as SKIPPED with its message (--compare then exits 2,
 * "not proven", never a pass).
 *
 * bizlms_import: parity::compare_invariant(). With the decisions the import ran with it is the whole invariant (a problem is
 * a FAIL); without them it is a string, so SKIPPED and exit 2, never a FAIL: every importer's verify() reads decisions that
 * have no default, and running it on none made a clean import fail (review of 2026-10-07).
 *
 * @param bool $withimport Also run the bizlms_import invariant (--compare only).
 * @param \local_sentientia_platform\bizlms\decisions|null $decisions The decisions the import ran with, if given.
 * @return array<string,string[]|string|null> a list of problems (empty = OK);
 *         null = check not available here; a string = the check could not run,
 *         and the string says why
 */
function sentientia_parity_invariants(bool $withimport = false,
        ?\local_sentientia_platform\bizlms\decisions $decisions = null): array {
    if (!class_exists('\local_sentientia_platform\message_pref_repair')) {
        $out = ['message_provider_defaults' => null];
    } else {
        try {
            $out = ['message_provider_defaults' => \local_sentientia_platform\message_pref_repair::check()];
        } catch (\Throwable $e) {
            $out = ['message_provider_defaults' => 'check could not run: ' . $e->getMessage()];
        }
    }
    if ($withimport) {
        // The bizlms_import invariant (ADR-032, parity hook 2): empty when the database holds no legacy tables.
        if (!class_exists('\local_sentientia_platform\bizlms\parity')) {
            $out['bizlms_import'] = null;
        } else {
            try {
                $out['bizlms_import'] = \local_sentientia_platform\bizlms\parity::compare_invariant($decisions);
            } catch (\Throwable $e) {
                $out['bizlms_import'] = 'check could not run: ' . $e->getMessage();
            }
        }
    }
    return $out;
}

/**
 * Fingerprints of every legacy table (ADR-032, parity hook 1), or null when the import framework is not deployed here or the
 * read failed. Never stops a baseline: a failure is reported by the caller.
 *
 * @param int $crcmaxrows Skip a table's CRC above this many rows; 0 reads every row (what a baseline needs).
 * @return array<string,array>|null
 */
function sentientia_parity_legacy_fingerprints(int $crcmaxrows = 0): ?array {
    if (!class_exists('\local_sentientia_platform\bizlms\parity')) {
        return null;
    }
    try {
        return \local_sentientia_platform\bizlms\parity::legacy_fingerprints($crcmaxrows > 0 ? $crcmaxrows : PHP_INT_MAX);
    } catch (\Throwable $e) {
        cli_writeln('  WARNING legacy table fingerprints could not be taken: ' . $e->getMessage());
        return null;
    }
}

/** Print the invariants; returns [failed, skipped]. */
function sentientia_parity_print_invariants(array $invariants): array {
    $failed = 0;
    $skipped = 0;
    cli_writeln('');
    cli_writeln('Invariants (must hold whatever the baseline says):');
    foreach ($invariants as $k => $problems) {
        if ($problems === null) {
            cli_writeln(sprintf('  SKIPPED %-26s (check not available on this deployment)', $k));
            $skipped++;
        } else if (is_string($problems)) {
            cli_writeln(sprintf('  SKIPPED %-26s (%s)', $k, $problems));
            $skipped++;
        } else if ($problems) {
            cli_writeln(sprintf('  FAIL    %-26s %d problem(s) - must be 0', $k, count($problems)));
            foreach ($problems as $line) {
                cli_writeln('          ' . $line);
            }
            $failed++;
        } else {
            cli_writeln(sprintf('  OK      %-26s 0', $k));
        }
    }
    return [$failed, $skipped];
}

// The decisions are checked first: a refused file (wrong hash) must not cost the counts and checksums of a large database.
$decisions = $options['compare'] !== ''
    ? sentientia_parity_decisions((string) $options['decisions'], (string) $options['expect-decisions-hash'])
    : null;
$counts = sentientia_parity_counts();
$checksums = sentientia_parity_checksums();
// The bizlms_import invariant reads the whole import (every feature's accounting and verify()): only --compare pays for it.
$invariants = sentientia_parity_invariants($options['compare'] !== '', $decisions);

if ($options['baseline'] !== '') {
    // The legacy tables, fingerprinted with no CRC cap (a capped baseline makes every comparison unproven).
    $legacyfingerprints = sentientia_parity_legacy_fingerprints(0);
    file_put_contents($options['baseline'], json_encode([
        'captured_at' => time(),
        'wwwroot'     => $CFG->wwwroot,
        'release'     => $CFG->release,
        'counts'      => $counts,
        'checksums'   => $checksums,
        'dbfamily'    => $DB->get_dbfamily(),
        'legacy_fingerprints' => $legacyfingerprints,
    ], JSON_PRETTY_PRINT));
    cli_writeln('Baseline saved: ' . $options['baseline']);
    cli_writeln($legacyfingerprints === null
        ? 'Legacy table fingerprints: NOT TAKEN (the import framework is not deployed here, or the read failed): '
            . 'the archive proof of the cutover cannot use this baseline.'
        : 'Legacy table fingerprints: ' . count($legacyfingerprints) . ' table(s).');
    foreach ($counts as $k => $v) {
        cli_writeln(sprintf('  %-24s %d', $k, $v));
    }
    cli_writeln('');
    cli_writeln('Value checksums:');
    foreach ($checksums as $t => $cs) {
        cli_writeln(sprintf('  %-28s rows=%-9d crc=%s', $t, $cs['rows'],
            $cs['crc'] ?? '(unsupported on this engine)'));
    }
    // Informational on the source side; enforced by --compare on the target.
    sentientia_parity_print_invariants($invariants);
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

    // What the enrolments import wrote into core user_enrolments on purpose (zero where the import has not run).
    $added = class_exists('\local_sentientia_platform\bizlms\parity')
        ? \local_sentientia_platform\bizlms\parity::imported_enrolments(
            ['id', 'enrolid', 'userid', 'status', 'timestart', 'timeend'])
        : ['rows' => 0, 'crc' => null, 'switched_off' => 0];

    foreach ($base['counts'] as $k => $expected) {
        $got = $counts[$k] ?? null;
        if ($got === (int) $expected) {
            cli_writeln(sprintf('  MATCH %-24s %d', $k, $got));
        } else if ($k === 'enrolments' && $got !== null && $added['rows'] > 0
                && \local_sentientia_platform\bizlms\parity::enrolment_count_explained((int) $expected, $got, $added['rows'])) {
            cli_writeln(sprintf('  EXPLAINED %-20s expected %d got %d: +%d manual enrolments written by the BizLMS import '
                . '(feature enrolments, legacy map outcome imported)', $k, (int) $expected, $got, $added['rows']));
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
            } else if ($t === 'user_enrolments' && $added['rows'] > 0
                    && \local_sentientia_platform\bizlms\parity::enrolment_checksum_explained($basecs, $now, $added)) {
                // The rows the enrolments import wrote, and nothing else: the SUM of per-row CRCs adds up exactly.
                cli_writeln(sprintf('  EXPLAINED %-24s rows %d->%d  crc %s->%s: the +%d manual enrolments of the BizLMS import add up',
                    $t, (int) $basecs['rows'], (int) $now['rows'], $basecs['crc'], $now['crc'], $added['rows']));
            } else {
                cli_writeln(sprintf('  DRIFT   %-26s rows %d->%d  crc %s->%s',
                    $t, (int) $basecs['rows'], (int) $now['rows'],
                    $basecs['crc'], $now['crc']));
                $drift++;
            }
        }
    }

    // The legacy tables (ADR-032, parity hook 1): the archive must be intact, which proves the core hops and the import left it
    // alone. A changed or missing table is drift (exit 1); a skipped CRC or a table the baseline did not have is "not proven".
    cli_writeln('');
    cli_writeln('Legacy tables (the BizLMS archive):');
    if (empty($base['legacy_fingerprints'])) {
        cli_writeln('  SKIPPED - the baseline holds no legacy table fingerprints (taken before the import framework, or where it '
            . 'was not deployed). The archive is NOT proven untouched.');
        $skipped++;
    } else if (!class_exists('\local_sentientia_platform\bizlms\parity')) {
        cli_writeln('  SKIPPED - the import framework is not deployed here, so the legacy tables cannot be compared.');
        $skipped++;
    } else {
        $currentfp = sentientia_parity_legacy_fingerprints((int) $options['crc-max-rows']);
        if ($currentfp === null) {
            cli_writeln('  SKIPPED - the legacy table fingerprints could not be taken here.');
            $skipped++;
        } else {
            $comparison = \local_sentientia_platform\bizlms\parity::compare_fingerprints($base['legacy_fingerprints'], $currentfp);
            $found = \local_sentientia_platform\bizlms\parity::comparison_problems($comparison);
            foreach ($found['hard'] as $line) {
                cli_writeln('  DRIFT   ' . $line);
                $drift++;
            }
            foreach ($found['unproven'] as $line) {
                cli_writeln('  SKIPPED ' . $line);
                $skipped++;
            }
            if (!$found['hard'] && !$found['unproven']) {
                cli_writeln(sprintf('  MATCH   %d legacy table(s), count, MAX(id), columns and CRC', count($base['legacy_fingerprints'])));
            }
        }
    }
    if ($added['rows'] > 0 || $added['switched_off'] > 0) {
        cli_writeln('');
        cli_writeln(sprintf('BizLMS enrolments import: %d manual enrolment(s) written into user_enrolments (counted above); '
            . '%d BizLMS enrol instance(s) switched off (enrol.status, not in the counts or checksums).',
            $added['rows'], $added['switched_off']));
    }

    [$hardfail, $invskipped] = sentientia_parity_print_invariants($invariants);
    $skipped += $invskipped;

    cli_writeln('');
    if ($drift > 0 || $hardfail > 0) {
        if ($drift > 0) {
            cli_writeln("RESULT: $drift metric(s) DRIFTED - investigate before proceeding.");
        }
        if ($hardfail > 0) {
            cli_writeln("RESULT: $hardfail invariant(s) FAILED - see the problems above. message_provider_defaults: run "
                . 'local/sentientia_platform/cli/repair_task_registrations.php --apply. bizlms_import: read the import report '
                . '(local/sentientia_platform/cli/import_bizlms.php --verify --decisions=FILE shows the same problems). '
                . 'Then re-check.');
        }
        exit(1);
    }
    if ($skipped > 0) {
        // Deliberately NOT "100% parity". Saying so here would be the same
        // defect as the rest of this file's history: a success message the
        // evidence does not support.
        cli_writeln("RESULT: counts match, but $skipped table(s)/invariant(s) could not be "
            . 'checked. Data is NOT proven intact - re-run with a '
            . 'checksum-capable baseline on MySQL or MariaDB, on the Sentientia target.');
        exit(2);
    }
    cli_writeln('RESULT: 100% PARITY - counts AND value checksums match'
        . ($added['rows'] > 0 ? ' (the BizLMS enrolments import accounted for exactly, from the legacy map)' : '') . '.');
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
// Print mode stays exit 0; --compare is the gate that fails on an invariant.
sentientia_parity_print_invariants($invariants);
exit(0);
