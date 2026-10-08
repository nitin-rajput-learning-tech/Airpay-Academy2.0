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
 * grade), and the file table (owner decision IDN-04, signed key framework.file_rehome_copies). file_rehome copies
 * files in finalise() (the org logo, cohort descriptions, the learning-plan cover, the classroom and program
 * logos): copy-only, insert-only and idempotent, and the originals are never touched. That is a reviewed side
 * effect, not a core write, so an importer that makes the copies implements copies_files and names the exact
 * target file areas; the runner then lets {files} change for that importer in those areas only (files_in_areas())
 * and counts the copies. Every other importer that adds a {files} row trips.
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

    /** The file table, watched for every importer; see the class comment. */
    public const FILES = 'files';

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
        // The file table (IDN-04): allowed only for an importer that implements copies_files, and then only in the
        // target areas it declares. See files_in_areas().
        self::FILES,
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

    /**
     * The target file areas a copies_files importer declares, as 'component/filearea' keys.
     *
     * @param copies_files $importer
     * @return string[] Unique keys, in declaration order.
     */
    public static function declared_file_areas(copies_files $importer): array {
        $keys = [];
        foreach ($importer->allowed_file_areas() as $entry) {
            $keys[(string) ($entry[2] ?? '') . '/' . (string) ($entry[3] ?? '')] = true;
        }
        return array_keys($keys);
    }

    /**
     * Are the declared file areas well formed? Four non-empty strings per entry: [source component, source area,
     * target component, target area]. The registry refuses an importer whose declaration is not.
     *
     * @param copies_files $importer
     * @return bool
     */
    public static function file_areas_well_formed(copies_files $importer): bool {
        foreach ($importer->allowed_file_areas() as $entry) {
            if (!is_array($entry) || count($entry) !== 4) {
                return false;
            }
            foreach ($entry as $part) {
                if (!is_string($part) || trim($part) === '') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * What was added to the file table since a snapshot, split into the declared target areas and everything else.
     *
     * Directory rows (filename '.') the file API creates beside a copy count as part of the area they sit in, and
     * are not counted as copies.
     *
     * @param int $afterid Highest {files} id when the feature started.
     * @param string[] $areas Declared target areas as 'component/filearea' keys (declared_file_areas()).
     * @return array{outside: string[], copied: array<string, int>} outside lists the 'component/filearea' keys that
     *         received rows without being declared; copied has one entry per declared area (0 when nothing landed).
     */
    public static function files_in_areas(int $afterid, array $areas): array {
        global $DB;
        $copied = array_fill_keys($areas, 0);
        $outside = [];
        $rows = $DB->get_records_sql(
            "SELECT MIN(f.id) AS id, f.component, f.filearea,
                    SUM(CASE WHEN f.filename = :dot THEN 0 ELSE 1 END) AS copies
               FROM {files} f
              WHERE f.id > :after
           GROUP BY f.component, f.filearea
           ORDER BY f.component, f.filearea",
            ['dot' => '.', 'after' => $afterid]);
        foreach ($rows as $row) {
            $key = $row->component . '/' . $row->filearea;
            if (array_key_exists($key, $copied)) {
                $copied[$key] = (int) $row->copies;
            } else {
                $outside[] = $key;
            }
        }
        return ['outside' => $outside, 'copied' => $copied];
    }
}
