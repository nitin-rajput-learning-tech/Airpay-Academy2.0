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
 * Data migration — copy local_costcenter → local_airpay_org.
 *
 * Run via CLI:
 *   php local/airpay_org/data_migration.php
 *
 * Safe to run multiple times — skips if target already has data.
 * Preserves original IDs so open_path references remain valid.
 *
 * @package    local_airpay_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

$dbman = $DB->get_manager();

// Check source table exists.
if (!$dbman->table_exists('local_costcenter')) {
    cli_writeln(get_string('sourcetablemissing', 'local_airpay_org'));
    exit(0);
}

// Check target table exists.
if (!$dbman->table_exists('local_airpay_org')) {
    cli_error('Target table local_airpay_org does not exist. Run Site Admin → Notifications first.');
}

// Skip if target already has data.
$existing = $DB->count_records('local_airpay_org');
if ($existing > 0) {
    cli_writeln(get_string('migrationskipped', 'local_airpay_org'));
    cli_writeln("  Target already has {$existing} records.");
    exit(0);
}

// Count source records.
$sourcecount = $DB->count_records('local_costcenter');
cli_writeln("Migrating {$sourcecount} records from local_costcenter → local_airpay_org ...");

// Read all source records.
$sources = $DB->get_records('local_costcenter', null, 'id ASC');
$now = time();
$migrated = 0;

$transaction = $DB->start_delegated_transaction();

try {
    foreach ($sources as $src) {
        $record = new stdClass();
        $record->id           = $src->id; // Preserve original ID.
        $record->fullname     = $src->fullname ?? '';
        $record->shortname    = $src->shortname ?? '';
        $record->description  = $src->description ?? '';
        $record->parentid     = $src->parentid ?? 0;
        $record->path         = $src->path ?? '';
        $record->depth        = $src->depth ?? 1;
        $record->visible      = $src->visible ?? 1;
        $record->org_logo     = $src->costcenter_logo ?? null;
        $record->brand_color  = $src->brand_color ?? null;
        $record->button_color = $src->button_color ?? null;
        $record->hover_color  = $src->hover_color ?? null;
        $record->theme_scheme = $src->theme_scheme ?? null;
        $record->sortorder    = $src->sortorder ?? 0;
        $record->timecreated  = $src->timecreated ?? $now;
        $record->timemodified = $now;

        $DB->import_record('local_airpay_org', $record);
        $migrated++;
    }

    // Reset the auto-increment sequence to continue after highest ID.
    $maxid = $DB->get_field_sql("SELECT MAX(id) FROM {local_airpay_org}");
    if ($maxid) {
        $DB->get_manager()->reset_sequence('local_airpay_org');
    }

    $transaction->allow_commit();

    cli_writeln(get_string('migrationcomplete', 'local_airpay_org'));
    cli_writeln("  Migrated: {$migrated}/{$sourcecount} records.");

    // Verify counts match.
    $finalcount = $DB->count_records('local_airpay_org');
    if ($finalcount === $sourcecount) {
        cli_writeln("  Verification: PASS ({$finalcount} records in target).");
    } else {
        cli_writeln("  WARNING: Count mismatch — source={$sourcecount}, target={$finalcount}");
    }

} catch (\Throwable $e) {
    $transaction->rollback($e);
    cli_error("Migration FAILED: " . $e->getMessage());
}
