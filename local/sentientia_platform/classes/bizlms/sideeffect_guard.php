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
 * The watched list covers what an event or a notification writes (the log, messages, tasks) and what a core
 * API writes WITHOUT firing an event (a preference, a role capability, a context, a group or cohort member, a
 * grade). One table is deliberately not watched: files. file_rehome copies an organisation logo in finalise()
 * through the file API, which is a reviewed side effect of the org importer; watching the table needs a
 * reviewed core_writes entry for it and changes what --purge-feature may do. Both belong with the org importer.
 *
 * Two traps the snapshot has to know about. Events reach their non-internal
 * observers (the standard log among them) only after the outermost transaction
 * commits, so a feature that runs in one outer transaction is checked once inside
 * it (direct writes roll back with the feature) and again after the commit (event
 * side effects). And the standard log buffers its rows, so every snapshot flushes
 * the log manager first.
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
        // Written by core APIs that fire no event.
        'user_preferences', 'role_capabilities', 'context', 'grade_grades', 'grade_grades_history',
        'groups_members', 'cohort_members',
    ];

    /**
     * Write out the events the log store is still holding.
     *
     * Event side effects do not happen when the event is triggered. The standard log is an observer
     * with 'internal' => false, so it runs only after the outermost transaction has COMMITTED, and its
     * buffered writer then flushes once 50 events are waiting or at shutdown. MAX(id) of the log table
     * therefore stays still while an event fired by a core API sits in the buffer. Disposing the log
     * manager flushes every store; get_log_manager(true) makes the next event start a fresh one.
     *
     * @return void
     */
    public static function flush_event_buffers(): void {
        get_log_manager(true);
    }

    /**
     * MAX(id) of every watched table that exists.
     *
     * @param string[] $extra The importer's own extra tables.
     * @param bool $flush Flush buffered events first. Never while a transaction the caller may still roll
     *        back is open: the flushed rows would be part of it.
     * @return array<string, int> table => highest id (0 when empty)
     */
    public static function snapshot(array $extra = [], bool $flush = true): array {
        global $DB;
        if ($flush) {
            self::flush_event_buffers();
        }
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
