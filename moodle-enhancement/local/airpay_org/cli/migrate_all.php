<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Master migration — BizLMS → Airpay Enterprise.
 *
 * Copies all BizLMS data to Airpay tables and migrates capabilities.
 * Safe to run multiple times (skips tables that already have data).
 *
 * Usage:
 *   php local/airpay_org/cli/migrate_all.php
 *   php local/airpay_org/cli/migrate_all.php --dry-run
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$dryrun = in_array('--dry-run', $argv ?? []);
$dbman = $DB->get_manager();

cli_heading('BizLMS → Airpay Enterprise Migration');
if ($dryrun) {
    cli_writeln('  ** DRY RUN — no changes will be made **');
}
cli_writeln('');

$results = [];

// ═══════════════════════════════════════════════════════
// 1. local_costcenter → local_airpay_org
// ═══════════════════════════════════════════════════════
$results[] = migrate_table(
    'local_costcenter', 'local_airpay_org',
    [
        'id'           => 'id',
        'fullname'     => 'fullname',
        'shortname'    => 'shortname',
        'description'  => 'description',
        'parentid'     => 'parentid',
        'path'         => 'path',
        'depth'        => 'depth',
        'visible'      => 'visible',
        'costcenter_logo' => 'org_logo',
        'brand_color'  => 'brand_color',
        'button_color' => 'button_color',
        'hover_color'  => 'hover_color',
        'theme_scheme' => 'theme_scheme',
        'sortorder'    => 'sortorder',
        'timecreated'  => 'timecreated',
    ],
    $dryrun
);

// ═══════════════════════════════════════════════════════
// 2. local_classroom → local_sentientia_classroom
// ═══════════════════════════════════════════════════════
$results[] = migrate_table(
    'local_classroom', 'local_sentientia_classroom',
    [
        'id'           => 'id',
        'name'         => 'name',
        'description'  => 'description',
        'costcenterid' => 'costcenterid',
        'departmentid' => 'departmentid',
        'open_path'    => 'open_path',
        'trainerid'    => 'trainerid',
        'location'     => 'location',
        'capacity'     => 'capacity',
        'status'       => 'status',
        'visible'      => 'visible',
        'timecreated'  => 'timecreated',
        'timemodified' => 'timemodified',
    ],
    $dryrun
);

// ═══════════════════════════════════════════════════════
// 3. local_onlinetests → local_sentientia_exams
// ═══════════════════════════════════════════════════════
$results[] = migrate_table(
    'local_onlinetests', 'local_sentientia_exams',
    [
        'id'           => 'id',
        'name'         => 'name',
        'quizid'       => 'quizid',
        'costcenterid' => 'costcenterid',
        'departmentid' => 'departmentid',
        'open_path'    => 'open_path',
        'duration'     => 'duration',
        'passinggrade' => 'passinggrade',
        'status'       => 'status',
        'visible'      => 'visible',
        'timecreated'  => 'timecreated',
        'timemodified' => 'timemodified',
    ],
    $dryrun
);

// ═══════════════════════════════════════════════════════
// 4. local_learningplan → local_sentientia_learningpath
// ═══════════════════════════════════════════════════════
$results[] = migrate_table(
    'local_learningplan', 'local_sentientia_learningpath',
    [
        'id'           => 'id',
        'name'         => 'name',
        'description'  => 'description',
        'costcenterid' => 'costcenterid',
        'departmentid' => 'departmentid',
        'open_path'    => 'open_path',
        'status'       => 'status',
        'visible'      => 'visible',
        'timecreated'  => 'timecreated',
        'timemodified' => 'timemodified',
    ],
    $dryrun
);

// ═══════════════════════════════════════════════════════
// 5. Capability migration (role_capabilities)
// ═══════════════════════════════════════════════════════
cli_heading('Capability migration');

$cap_map = [
    'local/costcenter:manage_multiorganizations' => 'local/airpay_org:manage_multiorganizations',
    'local/costcenter:view'                      => 'local/airpay_org:view',
    'local/costcenter:manage'                    => 'local/airpay_org:manage',
    'local/costcenter:manage_ownorganization'    => 'local/airpay_org:manage_ownorganization',
    'local/costcenter:manage_owndepartments'     => 'local/airpay_org:manage_owndepartments',
    'local/courses:manage'                       => 'local/sentientia_courses:manage',
    'local/courses:enrol'                        => 'local/sentientia_courses:enrol',
    'local/classroom:manageclassroom'            => 'local/sentientia_classroom:manage',
    'local/users:edit'                           => 'local/sentientia_users:edit',
    'local/users:bulkstatuschange'               => 'local/sentientia_users:bulkstatuschange',
];

$cap_migrated = 0;
$cap_skipped = 0;

foreach ($cap_map as $old_cap => $new_cap) {
    // Find existing role_capabilities rows with the old capability.
    $old_rows = $DB->get_records('role_capabilities', ['capability' => $old_cap]);

    if (empty($old_rows)) {
        cli_writeln("  SKIP: {$old_cap} — no rows found");
        $cap_skipped++;
        continue;
    }

    foreach ($old_rows as $row) {
        // Check if new capability already assigned to this role+context.
        $exists = $DB->record_exists('role_capabilities', [
            'roleid'     => $row->roleid,
            'capability' => $new_cap,
            'contextid'  => $row->contextid,
        ]);

        if ($exists) {
            continue;
        }

        if (!$dryrun) {
            $newrow = clone $row;
            unset($newrow->id);
            $newrow->capability = $new_cap;
            $newrow->timemodified = time();
            $DB->insert_record('role_capabilities', $newrow);
        }
        $cap_migrated++;
    }

    $count = count($old_rows);
    cli_writeln("  {$old_cap} → {$new_cap}: {$count} role assignments" . ($dryrun ? ' (dry-run)' : ' MIGRATED'));
}

cli_writeln("  Capabilities: {$cap_migrated} migrated, {$cap_skipped} skipped");
cli_writeln('');

// ═══════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════
cli_heading('Migration Summary');
foreach ($results as $r) {
    $status = $r['status'] === 'migrated' ? 'OK' : strtoupper($r['status']);
    cli_writeln("  [{$status}] {$r['source']} → {$r['target']}: {$r['count']} records");
}
cli_writeln("  [CAPS] {$cap_migrated} capability rows migrated");

if ($dryrun) {
    cli_writeln('');
    cli_writeln('  ** DRY RUN — run without --dry-run to apply changes **');
}

// ═══════════════════════════════════════════════════════
// Helper function
// ═══════════════════════════════════════════════════════

/**
 * Migrate a single table with field mapping.
 *
 * @param string $source      Source table name (no braces)
 * @param string $target      Target table name (no braces)
 * @param array  $fieldmap    source_field => target_field
 * @param bool   $dryrun
 * @return array  {source, target, count, status}
 */
function migrate_table(string $source, string $target, array $fieldmap, bool $dryrun): array {
    global $DB;
    $dbman = $DB->get_manager();

    $result = ['source' => $source, 'target' => $target, 'count' => 0, 'status' => ''];

    // Check source exists.
    if (!$dbman->table_exists($source)) {
        $result['status'] = 'source_missing';
        cli_writeln("  SKIP: {$source} — table does not exist");
        return $result;
    }

    // Check target exists.
    if (!$dbman->table_exists($target)) {
        $result['status'] = 'target_missing';
        cli_writeln("  SKIP: {$target} — table does not exist (run Admin → Notifications first)");
        return $result;
    }

    // Skip if target already has data.
    $existing = $DB->count_records($target);
    if ($existing > 0) {
        $result['count'] = $existing;
        $result['status'] = 'already_populated';
        cli_writeln("  SKIP: {$target} — already has {$existing} records");
        return $result;
    }

    // Count source.
    $sourcecount = $DB->count_records($source);
    if ($sourcecount === 0) {
        $result['status'] = 'source_empty';
        cli_writeln("  SKIP: {$source} — empty table");
        return $result;
    }

    cli_writeln("  Migrating {$sourcecount} records: {$source} → {$target}");

    if ($dryrun) {
        $result['count'] = $sourcecount;
        $result['status'] = 'dry_run';
        return $result;
    }

    // Determine which source fields actually exist.
    $source_columns = $DB->get_columns($source);
    $target_columns = $DB->get_columns($target);
    $valid_map = [];
    foreach ($fieldmap as $src_field => $tgt_field) {
        if (isset($source_columns[$src_field]) && isset($target_columns[$tgt_field])) {
            $valid_map[$src_field] = $tgt_field;
        }
    }

    $now = time();
    $sources = $DB->get_records($source, null, 'id ASC');
    $transaction = $DB->start_delegated_transaction();
    $migrated = 0;

    try {
        foreach ($sources as $src) {
            $record = new stdClass();
            foreach ($valid_map as $src_field => $tgt_field) {
                $record->$tgt_field = $src->$src_field ?? null;
            }
            // Ensure timemodified is set.
            if (isset($target_columns['timemodified']) && empty($record->timemodified)) {
                $record->timemodified = $now;
            }

            $DB->import_record($target, $record);
            $migrated++;
        }

        // Reset auto-increment sequence.
        $DB->get_manager()->reset_sequence($target);
        $transaction->allow_commit();

        $result['count'] = $migrated;
        $result['status'] = 'migrated';

        // Verify.
        $finalcount = $DB->count_records($target);
        if ($finalcount === $sourcecount) {
            cli_writeln("    Verified: {$finalcount}/{$sourcecount} records OK");
        } else {
            cli_writeln("    WARNING: count mismatch — source={$sourcecount}, target={$finalcount}");
        }

    } catch (\Throwable $e) {
        $transaction->rollback($e);
        $result['status'] = 'failed';
        cli_writeln("    FAILED: " . $e->getMessage());
    }

    return $result;
}
