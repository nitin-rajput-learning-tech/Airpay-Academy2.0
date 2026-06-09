<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Backfill `mdl_local_sentientia_user_type` from existing user state.
 *
 * ADR-017 Phase 1 / C1.1. Schema landed in Phase 0 (C1.0) — this CLI
 * walks every user and writes a row classifying them as one of:
 *   employee | consumer | partner_employee | operator
 *
 * Classification rules (interim — until ADR-017 wires
 * user_type_resolver into the live provisioning paths):
 *
 *   1. is_siteadmin($user)              → operator
 *   2. open_path LIKE '/77%'             → consumer (Public tenant)
 *   3. open_path LIKE '/1%' or '/177%'   → employee (Airpay or ZEEA staff)
 *   4. open_path resolves to a non-Airpay customer subtree
 *                                        → partner_employee
 *   5. NULL open_path AND not siteadmin → employee (defensive default)
 *
 * Usage:
 *   # Dry-run (default): prints CSV, does NOT write
 *   php local/sentientia_platform/cli/classify_existing_users.php
 *
 *   # Save dry-run output to a file for review:
 *   php local/sentientia_platform/cli/classify_existing_users.php > /tmp/classification.csv
 *
 *   # Actually write the rows (requires --commit):
 *   php local/sentientia_platform/cli/classify_existing_users.php --commit
 *
 *   # Re-classify (drop existing rows + re-write — DESTRUCTIVE,
 *   # requires --confirm too):
 *   php local/sentientia_platform/cli/classify_existing_users.php --commit --reclassify --confirm
 *
 * Idempotency: skips users that already have a row in
 * `local_sentientia_user_type` unless --reclassify is passed.
 *
 * @package    local_sentientia_platform
 * @subpackage cli
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'commit'     => false,
    'reclassify' => false,
    'confirm'    => false,
    'limit'      => 0,        // 0 = unbounded
    'verbose'    => false,
    'help'       => false,
], [
    'h' => 'help',
    'v' => 'verbose',
]);

if ($options['help']) {
    cli_writeln(file_get_contents(__FILE__, false, null, 0, 2400));
    exit(0);
}

global $DB;

$dryrun     = !$options['commit'];
$reclassify = (bool) $options['reclassify'];
$verbose    = (bool) $options['verbose'];
$limit      = (int) $options['limit'];

if ($reclassify && !$options['confirm']) {
    cli_error('--reclassify is destructive (deletes existing rows). '
        . 'Pass --confirm to acknowledge.');
}

if ($reclassify) {
    if ($dryrun) {
        cli_writeln('(dry-run) Would TRUNCATE local_sentientia_user_type before re-write.');
    } else {
        $count = $DB->count_records('local_sentientia_user_type');
        $DB->delete_records('local_sentientia_user_type');
        cli_writeln("Deleted $count existing rows.");
    }
}

// ── Customer-zero IDs (Airpay-context tenant prefixes) ──────────────
// Airpay tenant subtree starts at /1; ZEEA at /177; Public at /77.
// Any other top-level path digit is a partner-org tenant subtree.
$airpay_prefixes = ['/1', '/177']; // both = "employee under Airpay customer"
$consumer_prefix = '/77';          // = "consumer (Public)"

// ── Walk users ──────────────────────────────────────────────────────
$sql = "SELECT id, username, email, open_path, deleted, suspended
          FROM {user}
         WHERE id > 1
         ORDER BY id ASC";
$params = [];
if ($limit > 0) {
    $users = $DB->get_records_sql($sql, $params, 0, $limit);
} else {
    $users = $DB->get_records_sql($sql, $params);
}

$counts = [
    'employee'         => 0,
    'consumer'         => 0,
    'partner_employee' => 0,
    'operator'         => 0,
    'skipped_existing' => 0,
    'skipped_deleted'  => 0,
    'errors'           => 0,
];

cli_writeln('userid,username,user_type,reason,suspended,deleted');

$now = time();
foreach ($users as $u) {
    // Skip deleted users — they're effectively offboarded.
    if ($u->deleted) {
        $counts['skipped_deleted']++;
        if ($verbose) {
            cli_writeln(sprintf('%d,%s,(skip),deleted,%d,%d',
                $u->id, $u->username, $u->suspended, $u->deleted));
        }
        continue;
    }

    // Skip already-classified unless --reclassify
    if (!$reclassify
        && $DB->record_exists('local_sentientia_user_type', ['userid' => $u->id])) {
        $counts['skipped_existing']++;
        if ($verbose) {
            cli_writeln(sprintf('%d,%s,(skip),already_classified,%d,%d',
                $u->id, $u->username, $u->suspended, $u->deleted));
        }
        continue;
    }

    // Classify
    $reason   = '';
    $type     = null;

    // Rule 1: siteadmin → operator
    if (is_siteadmin($u->id)) {
        $type   = 'operator';
        $reason = 'is_siteadmin';
    } else {
        $path = (string) ($u->open_path ?? '');

        // Rule 2: /77 subtree → consumer
        if ($path === $consumer_prefix
            || str_starts_with($path, $consumer_prefix . '/')) {
            $type   = 'consumer';
            $reason = 'open_path_77';
        }
        // Rule 3: /1 or /177 subtree → employee (Airpay customer-zero)
        elseif ($path !== '') {
            $is_airpay_path = false;
            foreach ($airpay_prefixes as $prefix) {
                if ($path === $prefix
                    || str_starts_with($path, $prefix . '/')) {
                    $is_airpay_path = true;
                    break;
                }
            }
            if ($is_airpay_path) {
                $type   = 'employee';
                $reason = 'open_path_airpay';
            } else {
                // Rule 4: non-Airpay open_path = partner-org employee
                $type   = 'partner_employee';
                $reason = 'open_path_partner';
            }
        }
        // Rule 5: NULL open_path + not siteadmin → defensive employee
        else {
            $type   = 'employee';
            $reason = 'no_open_path_default';
        }
    }

    $counts[$type]++;

    // Output CSV row
    cli_writeln(sprintf('%d,%s,%s,%s,%d,%d',
        $u->id, $u->username, $type, $reason, $u->suspended, $u->deleted));

    // Commit
    if (!$dryrun) {
        try {
            $DB->insert_record('local_sentientia_user_type', (object) [
                'userid'              => $u->id,
                'user_type'           => $type,
                'provisioning_source' => 'backfill_cli',
                'provisioned_at'      => $now,
                'timecreated'         => $now,
                'timemodified'        => $now,
            ]);
        } catch (\Throwable $e) {
            $counts['errors']++;
            cli_writeln('# ERROR: ' . $u->id . ': ' . $e->getMessage());
        }
    }
}

// ── Summary ─────────────────────────────────────────────────────────
cli_writeln('');
cli_writeln('# ── Summary ──');
cli_writeln('# Mode:               ' . ($dryrun ? 'DRY-RUN (no DB writes)' : 'COMMIT'));
cli_writeln('# Reclassify:         ' . ($reclassify ? 'yes (existing rows wiped)' : 'no'));
cli_writeln('# Employees:          ' . $counts['employee']);
cli_writeln('# Consumers:          ' . $counts['consumer']);
cli_writeln('# Partner employees:  ' . $counts['partner_employee']);
cli_writeln('# Operators:          ' . $counts['operator']);
cli_writeln('# Skipped (existing): ' . $counts['skipped_existing']);
cli_writeln('# Skipped (deleted):  ' . $counts['skipped_deleted']);
cli_writeln('# Errors:             ' . $counts['errors']);
cli_writeln('# Total processed:    ' . array_sum($counts));

if ($dryrun) {
    cli_writeln('');
    cli_writeln('# To actually write the classification, re-run with --commit');
}
