<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Phase 9 (N7 follow-up) upgrade: migrate get_config/set_config based
 * per-quiz settings into a proper relational table.
 */
function xmldb_quizaccess_airpay_proctoring_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026051300) {
        // Migrate every existing `quizaccess_airpay_proctoring`
        // `quiz_<id>_enabled` config entry into the new table.
        $rows = $DB->get_records_select('config_plugins',
            "plugin = :p AND " . $DB->sql_like('name', ':n'),
            ['p' => 'quizaccess_airpay_proctoring', 'n' => 'quiz_%_enabled']);
        $now = time();
        foreach ($rows as $row) {
            // Parse: 'quiz_42_enabled' → 42
            if (!preg_match('/^quiz_(\d+)_enabled$/', $row->name, $m)) {
                continue;
            }
            $quizid = (int) $m[1];
            $enabled = (int) (string) $row->value;
            if ($DB->record_exists('quizaccess_airpay_proctor', ['quizid' => $quizid])) {
                continue;  // already migrated
            }
            $DB->insert_record('quizaccess_airpay_proctor', (object) [
                'quizid'       => $quizid,
                'enabled'      => $enabled,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
            // Drop the now-redundant config entry.
            $DB->delete_records('config_plugins', ['id' => $row->id]);
        }
        upgrade_plugin_savepoint(true, 2026051300, 'quizaccess', 'airpay_proctoring');
    }

    return true;
}
