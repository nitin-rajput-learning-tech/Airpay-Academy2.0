<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The side-effect tripwire (ADR-032, "Side-effect safety" 3).
 *
 * Before and after each feature the framework reads MAX(id) of the append-only
 * tables that an observer, a notification, an enrolment or a completion would
 * write to. MAX(id) is O(1) and catches inserts; updates to state tables are
 * caught by the parity checksums at the end. Any change outside the feature's
 * declared targets and reviewed core writes aborts the run before the next
 * feature.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sideeffect_guard {

    /**
     * Append-only tables the import must never write to. The e-mail log is
     * watched too, except for the feature that declares it as a target.
     *
     * @var string[]
     */
    public const TABLES = [
        'logstore_standard_log', 'messages', 'notifications', 'task_adhoc', 'event',
        'user_enrolments', 'role_assignments', 'course_completions', 'course_modules_completion',
        'quiz_attempts', 'badge_issued', 'tool_certificate_issues',
        'local_sentientia_evaluation_triggers', 'local_sentientia_notif_log', 'local_sentientia_email_log',
    ];

    /**
     * MAX(id) of every watched table that exists.
     *
     * @param string[] $extra The importer's own extra tables.
     * @return array<string, int> table => highest id (0 when empty)
     */
    public static function snapshot(array $extra = []): array {
        global $DB;
        $dbman = $DB->get_manager();
        $out = [];
        foreach (array_unique(array_merge(self::TABLES, $extra)) as $table) {
            fingerprint::assert_identifier($table);
            if (!$dbman->table_exists($table)) {
                continue;
            }
            $out[$table] = (int) $DB->get_field_sql('SELECT MAX(id) FROM {' . $table . '}');
        }
        return $out;
    }

    /**
     * Tables whose highest id changed between two snapshots, leaving out the
     * tables the feature is allowed to write.
     *
     * @param array<string, int> $before
     * @param array<string, int> $after
     * @param string[] $allowed Declared targets and reviewed core writes.
     * @return string[] Changed table names.
     */
    public static function violations(array $before, array $after, array $allowed = []): array {
        $allowed = array_flip($allowed);
        $changed = [];
        foreach ($after as $table => $maxid) {
            if (isset($allowed[$table])) {
                continue;
            }
            if (($before[$table] ?? 0) !== $maxid) {
                $changed[] = $table;
            }
        }
        return $changed;
    }
}
