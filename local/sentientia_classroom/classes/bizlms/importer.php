<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\copies_files;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\file_rehome;
use local_sentientia_platform\bizlms\importer as importer_contract;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

defined('MOODLE_INTERNAL') || die();

/**
 * The classroom feature of the BizLMS data import (ADR-032, mapping doc section 15).
 *
 * It moves the history held in BizLMS's local_classroom_* and local_location_* tables into the
 * Sentientia classroom tables, so classrooms, rosters, sessions, attendance, waiting lists, trainers,
 * linked courses and venues are history a person can see after cutover. It never touches a legacy table
 * and never writes outside its declared targets; the framework's writer, runner and tripwire enforce
 * that, and the static scan (tests/bizlms_import_test.php) keeps this directory clean.
 *
 * Steps, in order (each needs the ones before it through the map):
 *   institutes, rooms          -> local_sentientia_locations         MAP
 *   classrooms                 -> local_sentientia_classroom         PRESERVE (ids are referenced elsewhere)
 *   trainers, courses          -> local_sentientia_classroom_*       MAP
 *   sessions                   -> local_sentientia_classroom_sessions PRESERVE
 *   users                      -> local_sentientia_classroom_users   MAP, duplicates merged
 *   attendance                 -> local_sentientia_classroom_attendance MAP, duplicates merged
 *   waitlist                   -> local_sentientia_classroom_waitlist MAP, renumbered
 *   trainerfb, completion      -> archived
 *
 * Tables not claimed: local_classroom_test_score (a blocker when it holds rows) and local_classroom_categories
 * (declared by no install file); both stay in the legacy database.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements importer_contract, copies_files {

    /** Plugin version that carries the schema this importer writes to (upgrade step 2026093002). */
    public const REQUIRES_VERSION = 2026093002;

    /** Columns the import adds, by table: the target schema must have them before the run. */
    private const TARGET_COLUMNS = [
        'local_sentientia_classroom' => ['shortname', 'trainingstart', 'trainingend', 'timecompleted', 'createdby'],
        'local_sentientia_classroom_users' => ['completion_status', 'timecompleted', 'hours'],
        'local_sentientia_locations' => ['parentid', 'venue_type', 'building'],
    ];

    /**
     * Every column of a legacy table that a step reads. The steps read a row defensively (mapping::int() and
     * mapping::text() turn a missing column into 0 or ''), so a column that a different source schema lacks
     * would otherwise import as 0 or NULL without a word. Preflight blocks instead. The April rehearsal copy
     * (BizLMS 4.1.2 upgraded) has all of them.
     *
     * A column only the optional decisions classroom.pathless = by_costcenter / by_creator read
     * (local_classroom.costcenter) is checked separately in preflight().
     */
    private const REQUIRED_SOURCE_COLUMNS = [
        'local_classroom' => ['name', 'shortname', 'description', 'status', 'open_path', 'visible', 'instituteid',
            'capacity', 'nomination_startdate', 'nomination_enddate', 'startdate', 'enddate', 'completiondate',
            'usercreated', 'timecreated', 'timemodified'],
        'local_classroom_sessions' => ['name', 'classroomid', 'description', 'duration', 'instituteid', 'roomid',
            'timestart', 'timefinish', 'trainerid', 'messagelink', 'recordinglink', 'timecreated', 'timemodified'],
        'local_classroom_users' => ['classroomid', 'userid', 'hours', 'completion_status', 'completiondate',
            'usercreated', 'timecreated', 'timemodified'],
        'local_classroom_attendance' => ['classroomid', 'sessionid', 'userid', 'status', 'usercreated',
            'usermodified', 'timecreated', 'timemodified'],
        'local_classroom_trainers' => ['classroomid', 'trainerid', 'timecreated', 'timemodified'],
        'local_classroom_courses' => ['classroomid', 'courseid', 'timecreated', 'timemodified'],
        'local_classroom_waitlist' => ['classroomid', 'userid', 'sortorder', 'enrolstatus', 'timecreated',
            'timemodified'],
        'local_location_institutes' => ['costcenter', 'fullname', 'address', 'visible', 'institute_type',
            'timecreated', 'timemodified'],
        'local_location_room' => ['instituteid', 'name', 'building', 'address', 'capacity', 'visible',
            'timecreated', 'timemodified'],
    ];

    /**
     * @return string
     */
    public function feature(): string {
        return 'classroom';
    }

    /**
     * @return string
     */
    public function component(): string {
        return 'local_sentientia_classroom';
    }

    /**
     * @return int
     */
    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * Tenant paths are resolved against the organisation tree the org importer fills.
     *
     * @return string[]
     */
    public function depends(): array {
        return ['org'];
    }

    /**
     * @return array<string, source_spec>
     */
    public function sources(): array {
        return [
            'local_location_institutes' => new source_spec('local_location_institutes', false),
            'local_location_room' => new source_spec('local_location_room', false),
            'local_classroom' => new source_spec('local_classroom', true, [
                // CL classes/classroom.php:37-41.
                'status' => [0 => 'new', 1 => 'active', 2 => 'on hold', 3 => 'cancelled', 4 => 'completed'],
            ]),
            'local_classroom_trainers' => new source_spec('local_classroom_trainers', false),
            'local_classroom_courses' => new source_spec('local_classroom_courses', false),
            'local_classroom_sessions' => new source_spec('local_classroom_sessions'),
            'local_classroom_users' => new source_spec('local_classroom_users', true, [
                // CL lib.php:1361. An empty value is a NULL, read as pending.
                'completion_status' => [0 => 'pending', 1 => 'completed', '' => 'null, read as pending'],
            ]),
            'local_classroom_attendance' => new source_spec('local_classroom_attendance', true, [
                // CL classes/classroom.php:42-43, attendance.php:73-77. 0 is the unmarked placeholder.
                'status' => [0 => 'not marked', 1 => 'present', 2 => 'absent', '' => 'null, read as not marked'],
            ]),
            'local_classroom_waitlist' => new source_spec('local_classroom_waitlist', false, [
                // CL classes/classroom.php:1305-1307. An empty value is a NULL, read as waiting.
                'enrolstatus' => [0 => 'waiting', 1 => 'moved to the roster', '' => 'null, read as waiting'],
            ]),
            'local_classroom_trainerfb' => new source_spec('local_classroom_trainerfb', false),
            'local_classroom_completion' => new source_spec('local_classroom_completion', false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function declined_tables(): array {
        return [
            'local_classroom_test_score' => 'no writer or reader in BizLMS and no user column; a table that holds '
                . 'rows blocks the import until the owner decides (preflight)',
            'local_classroom_categories' => 'referenced by BizLMS code but declared by no install file; '
                . 'if it exists it stays in the legacy database',
        ];
    }

    /**
     * @return string[]
     */
    public function target_tables(): array {
        return [
            'local_sentientia_locations',
            'local_sentientia_classroom',
            'local_sentientia_classroom_trainers',
            'local_sentientia_classroom_courses',
            'local_sentientia_classroom_sessions',
            'local_sentientia_classroom_users',
            'local_sentientia_classroom_attendance',
            'local_sentientia_classroom_waitlist',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        return [];
    }

    /**
     * The logo copy finalise() makes: the BizLMS classroom logo file area into this plugin's own, which the
     * pluginfile callback serves. The runner lets {files} grow in that target area and nowhere else (decision IDN-04,
     * signed key framework.file_rehome_copies). It is a reviewed side effect, not a core write, so --purge-feature
     * stays available.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function allowed_file_areas(): array {
        return [['local_classroom', 'classroomlogo', 'local_sentientia_classroom', 'classroomlogo']];
    }

    /**
     * The classroom path is the tenant column the readers scope on. A location carries a root organisation
     * id, not a path, so it is checked by verify() instead.
     *
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return ['local_sentientia_classroom' => 'open_path'];
    }

    /**
     * @return reason[]
     */
    public function reasons(): array {
        return [
            // Skipped: the row could not be imported. The owner accepts the count after the rehearsal.
            new reason('orphan_user', false, true),
            new reason('orphan_classroom', false, true),
            new reason('orphan_session', false, true),
            new reason('orphan_course', false, true),
            new reason('orphan_trainer', false, true),
            new reason('classroom_mismatch', false, true),
            new reason('incomplete_row', false, true),
            new reason('invalid_status', false, true),
            new reason('pathless_skipped', false, true),
            // Merged: a duplicate folded into the row that won.
            new reason('dup_natural_key', false, false),
            new reason('dup_waiting_place', false, false),
            // Archived: deliberately only in the legacy table.
            new reason('submission_marker', false, false),
            new reason('completion_rule_config', false, false),
            new reason('unmarked_placeholder', false, false),
        ];
    }

    /**
     * @return decision[]
     */
    public function decisions(): array {
        return [
            new decision('classroom.status_new_hold',
                'Add draft (5) and on-hold (6) classroom states, or collapse both to active',
                true, null, ['add_5_6', 'collapse_active']),
            new decision('classroom.waitlist_closed',
                'Waiting-list rows of a cancelled or completed classroom: removed, or kept waiting',
                true, null, ['removed', 'waiting']),
            new decision('classroom.waitlist_open',
                'Waiting-list rows of an open classroom: waiting once the auto-promote guard ships, else removed',
                true, null, ['waiting_after_guard_else_removed', 'removed']),
            new decision('classroom.pathless',
                'A classroom whose tenant path cannot be resolved: cross-tenant only, or filed by cost centre or creator',
                true, null, ['cross_tenant_only', 'by_costcenter', 'by_creator']),
            new decision('classroom.costs_as_columns',
                'Copy the food, travel and other cost columns (false: they stay in the legacy table)',
                true, null, [false, true]),
            new decision('tenant.unresolved.classroom',
                'A classroom with no tenant is imported with no path (pathless) or skipped',
                true, null, ['pathless', 'skip']),
        ];
    }

    /**
     * @return bool
     */
    public function atomic(): bool {
        return true;
    }

    /**
     * Fresh step objects on every call: they cache the small venue and trainer tables they read, per run.
     *
     * @return array
     */
    public function steps(): array {
        $venues = new legacy_venues();
        $trainers = new trainer_index();
        return [
            new institute_step(),
            new room_step($venues),
            new classroom_step($venues, $trainers),
            new trainer_step(),
            new course_step(),
            new session_step($venues),
            new user_step(),
            new attendance_step(),
            new waitlist_step(),
            new archive_step('classroom.trainer_feedback', 'local_classroom_trainerfb', 'submission_marker'),
            new archive_step('classroom.completion_rules', 'local_classroom_completion', 'completion_rule_config'),
        ];
    }

    /**
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        $pf = new preflight();

        // A decision this importer does not build: the cost columns were decided as "stay in the legacy table".
        if ($ctx->decision('classroom.costs_as_columns') === true) {
            $pf->block('costs_as_columns_not_built');
        }

        // The schema the steps write to must be there (the plugin version says so; this says it is true).
        foreach (self::TARGET_COLUMNS as $table => $columns) {
            if (!$ctx->legacy->exists($table)) {
                $pf->block('target_table_missing:' . $table);
                continue;
            }
            $have = $ctx->legacy->columns($table);
            foreach ($columns as $column) {
                if (!in_array($column, $have, true)) {
                    $pf->block('target_column_missing:' . $table . '.' . $column);
                }
            }
        }
        foreach ($this->target_tables() as $table) {
            if (!$ctx->legacy->exists($table)) {
                $pf->block('target_table_missing:' . $table);
            }
        }

        // The columns the steps read: a different source schema must block, not import as 0 or NULL.
        foreach (self::REQUIRED_SOURCE_COLUMNS as $table => $columns) {
            if (!$ctx->legacy->exists($table)) {
                continue;
            }
            $have = $ctx->legacy->columns($table);
            foreach ($columns as $column) {
                if (!in_array($column, $have, true)) {
                    $pf->block('missing_column:' . $table . '.' . $column);
                }
            }
        }
        // The cost centre is read only when the signed pathless decision files an unusable path under it.
        if (in_array((string) $ctx->decision('classroom.pathless'), ['by_costcenter', 'by_creator'], true)
                && $ctx->legacy->exists('local_classroom')
                && !in_array('costcenter', $ctx->legacy->columns('local_classroom'), true)) {
            $pf->block('missing_column:local_classroom.costcenter');
        }

        // Tables this importer does not copy.
        if ($ctx->legacy->exists('local_classroom_test_score')) {
            $rows = $ctx->legacy->count('local_classroom_test_score');
            $pf->count('rows:local_classroom_test_score', $rows);
            if ($rows > 0) {
                $pf->block('classroom_test_score_has_rows');
            }
        }
        if ($ctx->legacy->exists('local_classroom_categories')) {
            $pf->count('rows:local_classroom_categories', $ctx->legacy->count('local_classroom_categories'));
            $pf->warn('classroom_categories_stay_in_the_legacy_table');
        }
        return $pf;
    }

    /**
     * Read-only checks on what the run wrote. Only rows the import created or adopted are looked at, so a
     * classroom a person made since does not matter.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        $failures = [];

        // Every imported child points at a parent that exists.
        $children = [
            ['local_sentientia_classroom_sessions', 'classroomid', 'local_sentientia_classroom'],
            ['local_sentientia_classroom_users', 'classroomid', 'local_sentientia_classroom'],
            ['local_sentientia_classroom_trainers', 'classroomid', 'local_sentientia_classroom'],
            ['local_sentientia_classroom_courses', 'classroomid', 'local_sentientia_classroom'],
            ['local_sentientia_classroom_waitlist', 'classroomid', 'local_sentientia_classroom'],
            ['local_sentientia_classroom_attendance', 'sessionid', 'local_sentientia_classroom_sessions'],
        ];
        foreach ($children as [$child, $column, $parent]) {
            $count = $DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {{$child}} c
                   " . self::imported_join('c', $child, 'cm') . "
                  WHERE NOT EXISTS (SELECT 1 FROM {{$parent}} p WHERE p.id = c.{$column})");
            if ($count > 0) {
                $failures[] = "orphan_rows:{$child}.{$column}={$count}";
            }
        }

        // Every imported classroom has a status Sentientia knows.
        $count = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_sentientia_classroom} c
               " . self::imported_join('c', 'local_sentientia_classroom', 'cm') . "
              WHERE c.status NOT IN (0, 1, 2, 5, 6)");
        if ($count > 0) {
            $failures[] = "unknown_classroom_status={$count}";
        }

        // A learner is completed or not: a completion date belongs to a completed row only.
        $count = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_sentientia_classroom_users} u
               " . self::imported_join('u', 'local_sentientia_classroom_users', 'um') . "
              WHERE u.completion_status = 0 AND u.timecompleted IS NOT NULL");
        if ($count > 0) {
            $failures[] = "completion_date_without_completion={$count}";
        }

        // The places still waiting in a classroom are numbered 1..N without a gap.
        $count = $DB->count_records_sql(
            "SELECT COUNT(1) FROM (
                SELECT w.classroomid
                  FROM {local_sentientia_classroom_waitlist} w
                  " . self::imported_join('w', 'local_sentientia_classroom_waitlist', 'wm') . "
                 WHERE w.status = 'waiting'
              GROUP BY w.classroomid
                HAVING MIN(w.position) <> 1 OR MAX(w.position) <> COUNT(1)) gaps");
        if ($count > 0) {
            $failures[] = "waiting_positions_not_dense={$count}";
        }

        // A location's tenant is a registered root organisation, or 0 for none.
        $roots = $DB->get_fieldset_sql(
            "SELECT DISTINCT l.costcenterid
               FROM {local_sentientia_locations} l
               " . self::imported_join('l', 'local_sentientia_locations', 'lm') . "
              WHERE l.costcenterid <> 0");
        foreach ($roots as $root) {
            try {
                \local_sentientia_platform\tenant::assert_valid((int) $root);
            } catch (\Throwable $e) {
                $failures[] = 'location_tenant_not_registered=' . (int) $root;
            }
        }
        return $failures;
    }

    /**
     * A JOIN onto the map that keeps only the rows this feature's import created or adopted.
     *
     * @param string $alias Alias of the target table in the caller's query.
     * @param string $table Target table name without prefix.
     * @param string $tag Alias of the map in the caller's query.
     * @return string
     */
    private static function imported_join(string $alias, string $table, string $tag): string {
        return 'JOIN {' . legacymap::TABLE . "} {$tag} ON {$tag}.targettable = '{$table}' AND {$tag}.targetid = {$alias}.id"
            . " AND {$tag}.feature = 'classroom' AND {$tag}.subkey = ''"
            . " AND {$tag}.outcome IN ('imported', 'adopted')";
    }

    /**
     * Copy each classroom's logo from the BizLMS file area to the Sentientia one. Outside any transaction and
     * idempotent: a file that is already there is left alone, and the originals are never touched.
     *
     * The copy is the one write this importer makes outside its declared tables. {files} is watched for every
     * importer (ADR-032, Side-effect safety 3, decision IDN-04): this importer declares the copy through the
     * copies_files marker (allowed_file_areas()), so a row in any other file area still trips the tripwire, and the
     * run report counts the copies (files_copied).
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
        global $DB;
        if (!$ctx->legacy->exists('local_classroom') || !$ctx->legacy->has_column('local_classroom', 'classroomlogo')) {
            return;
        }
        $systemid = (int) \context_system::instance()->id;
        $after = 0;
        do {
            $page = $ctx->legacy->page('local_classroom', $after, 1000, ['id', 'classroomlogo'],
                ['t.classroomlogo > :blmlogo', ['blmlogo' => 0]]);
            foreach ($page as $id => $row) {
                $after = (int) $id;
                $classroomid = $ctx->map->resolve('local_classroom', (int) $id);
                if ($classroomid === null) {
                    continue;
                }
                $itemid = mapping::int($row, 'classroomlogo');
                $contexts = $DB->get_fieldset_sql(
                    "SELECT DISTINCT contextid
                       FROM {files}
                      WHERE component = :component AND filearea = :filearea AND itemid = :itemid",
                    ['component' => 'local_classroom', 'filearea' => 'classroomlogo', 'itemid' => $itemid]);
                foreach ($contexts as $contextid) {
                    file_rehome::copy_area(
                        ['contextid' => (int) $contextid, 'component' => 'local_classroom',
                            'filearea' => 'classroomlogo', 'itemid' => $itemid],
                        ['contextid' => $systemid, 'component' => 'local_sentientia_classroom',
                            'filearea' => 'classroomlogo', 'itemid' => $classroomid]);
                }
            }
        } while (count($page) === 1000);
    }
}
