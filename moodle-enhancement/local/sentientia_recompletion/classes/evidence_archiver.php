<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_recompletion\bizlms\mapper;

/**
 * Keep what a reset is about to delete (ADR-032 owner decision recompletion.engine_archive_before_delete).
 *
 * A reset deletes the learner's course completion, criteria, activity completions and SCORM tracking, and on
 * request their grades and quiz attempts. For every cycle before the current one those rows are the only proof
 * the person completed the course, which is what an auditor asks for. The BizLMS plugin copied them into its
 * local_recompletion_* tables first; the Sentientia engine did not. This class copies them into
 * local_sentientia_recompletion_archive, the table the BizLMS import fills, in the SAME shape (an item type, a few
 * promoted columns and the whole source row as JSON), so one evidence view reads both.
 *
 * It runs inside reset_user_in_course()'s transaction, before the first delete: if the copy fails the reset
 * fails and nothing is deleted. The rows are written with historyid 0 because the history row does not exist yet;
 * the caller attaches them once it has written it (attach()).
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evidence_archiver {

    /** The archive table. */
    public const TABLE = 'local_sentientia_recompletion_archive';

    /** Ids per IN clause when rows are attached to their history row. */
    private const CHUNK = 1000;

    /**
     * Copy everything a reset of one learner in one course will delete.
     *
     * Must be called inside the reset's transaction, before any delete.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $grades True when the reset also deletes the learner's grade rows.
     * @param bool $attempts True when the reset also deletes the learner's quiz attempts.
     * @param int $now The reset time; "archived at" of every row.
     * @return int[] Ids of the archive rows written, in order.
     */
    public static function archive(int $userid, int $courseid, bool $grades, bool $attempts, int $now): array {
        $ids = [];
        array_push($ids, ...self::course_completion($userid, $courseid, $now));
        array_push($ids, ...self::criteria($userid, $courseid, $now));
        array_push($ids, ...self::activities($userid, $courseid, $now));
        array_push($ids, ...self::scorm($userid, $courseid, $now));
        if ($attempts) {
            array_push($ids, ...self::quiz($userid, $courseid, $now));
        }
        if ($grades) {
            array_push($ids, ...self::grades($userid, $courseid, $now));
        }
        return $ids;
    }

    /**
     * Point archive rows at the history row of the reset that deleted what they hold.
     *
     * @param int[] $archiveids As returned by archive().
     * @param int $historyid
     * @return void
     */
    public static function attach(array $archiveids, int $historyid): void {
        global $DB;
        if (!$archiveids || $historyid <= 0) {
            return;
        }
        foreach (array_chunk(array_values(array_map('intval', $archiveids)), self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'eaid');
            $DB->set_field_select(self::TABLE, 'historyid', $historyid, "id $insql", $params);
        }
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return int[]
     */
    private static function course_completion(int $userid, int $courseid, int $now): array {
        global $DB;
        $ids = [];
        foreach ($DB->get_records('course_completions', ['userid' => $userid, 'course' => $courseid], 'id ASC') as $row) {
            $completed = mapper::timestamp($row->timecompleted);
            $ranfrom = $completed ?? mapper::first_timestamp([$row->timestarted, $row->timeenrolled]);
            $ids[] = self::insert($userid, $courseid, mapper::COURSE_COMPLETION, $row, $now, [
                'state' => $completed !== null ? 'complete' : 'incomplete',
                'timeevent' => $ranfrom,
            ]);
        }
        return $ids;
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return int[]
     */
    private static function criteria(int $userid, int $courseid, int $now): array {
        global $DB;
        $ids = [];
        foreach ($DB->get_records('course_completion_crit_compl', ['userid' => $userid, 'course' => $courseid],
                'id ASC') as $row) {
            $completed = mapper::timestamp($row->timecompleted);
            $ids[] = self::insert($userid, $courseid, mapper::CRITERIA_COMPLETION, $row, $now, [
                'instanceid' => (int) $row->criteriaid,
                'state' => $completed !== null ? 'complete' : 'incomplete',
                'grade' => mapper::bounded_number($row->gradefinal),
                'timeevent' => $completed,
            ]);
        }
        return $ids;
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return int[]
     */
    private static function activities(int $userid, int $courseid, int $now): array {
        global $DB;
        $cmids = $DB->get_fieldset_select('course_modules', 'id', 'course = :eacid', ['eacid' => $courseid]);
        if (!$cmids) {
            return [];
        }
        $ids = [];
        foreach (array_chunk($cmids, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'eacm');
            $rows = $DB->get_records_select('course_modules_completion', "userid = :eauid AND coursemoduleid $insql",
                $params + ['eauid' => $userid], 'id ASC');
            foreach ($rows as $row) {
                // The legacy archive added the course to the row, to help a restore; the payload does the same.
                $row->course = $courseid;
                $ids[] = self::insert($userid, $courseid, mapper::ACTIVITY_COMPLETION, $row, $now, [
                    'cmid' => (int) $row->coursemoduleid,
                    'state' => mapper::activity_state($row->completionstate),
                    'timeevent' => mapper::timestamp($row->timemodified),
                ]);
            }
        }
        return $ids;
    }

    /**
     * SCORM tracking: the Moodle 5 tables (scorm_attempt, scorm_scoes_value, scorm_element) and the legacy
     * scorm_scoes_track, whichever exist. A value is archived as one row, with the attempt and element joined in.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return int[]
     */
    private static function scorm(int $userid, int $courseid, int $now): array {
        global $DB;
        $scormids = $DB->get_fieldset_select('scorm', 'id', 'course = :eacid', ['eacid' => $courseid]);
        if (!$scormids) {
            return [];
        }
        $dbman = $DB->get_manager();
        $ids = [];
        [$insql, $params] = $DB->get_in_or_equal($scormids, SQL_PARAMS_NAMED, 'easc');
        $params['eauid'] = $userid;

        if ($dbman->table_exists('scorm_attempt') && $dbman->table_exists('scorm_scoes_value')
                && $dbman->table_exists('scorm_element')) {
            $rows = $DB->get_records_sql(
                "SELECT v.id, v.attemptid, v.scoid, v.value, v.timemodified, a.scormid, a.attempt, a.userid,
                        e.element
                   FROM {scorm_scoes_value} v
                   JOIN {scorm_attempt} a ON a.id = v.attemptid
                   JOIN {scorm_element} e ON e.id = v.elementid
                  WHERE a.userid = :eauid AND a.scormid $insql
               ORDER BY v.id ASC", $params);
            foreach ($rows as $row) {
                $ids[] = self::scorm_row($userid, $courseid, $now, $row, (int) $row->scormid, (int) $row->scoid);
            }
        }
        if ($dbman->table_exists('scorm_scoes_track')) {
            $rows = $DB->get_records_select('scorm_scoes_track', "userid = :eauid AND scormid $insql", $params, 'id ASC');
            foreach ($rows as $row) {
                $ids[] = self::scorm_row($userid, $courseid, $now, $row, (int) $row->scormid, (int) $row->scoid);
            }
        }
        return $ids;
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @param \stdClass $row
     * @param int $scormid
     * @param int $scoid
     * @return int
     */
    private static function scorm_row(int $userid, int $courseid, int $now, \stdClass $row, int $scormid,
                                      int $scoid): int {
        $element = (string) $row->element;
        $value = (string) $row->value;
        $row->course = $courseid;
        return self::insert($userid, $courseid, mapper::SCORM_TRACK, $row, $now, [
            'instanceid' => $scormid,
            'itemkey' => $element,
            'state' => mapper::is_scorm_status($element) ? trim($value) : null,
            'grade' => mapper::is_scorm_score($element) ? mapper::bounded_number($value) : null,
            'timeevent' => mapper::timestamp($row->timemodified),
        ]);
    }

    /**
     * Quiz attempts and the learner's quiz grades of the course's quizzes.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return int[]
     */
    private static function quiz(int $userid, int $courseid, int $now): array {
        global $DB;
        $quizids = $DB->get_fieldset_select('quiz', 'id', 'course = :eacid', ['eacid' => $courseid]);
        if (!$quizids) {
            return [];
        }
        $cmids = $DB->get_records_sql(
            "SELECT cm.instance AS quiz, MIN(cm.id) AS cmid
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE m.name = :eamodule AND cm.course = :eacid
           GROUP BY cm.instance", ['eamodule' => 'quiz', 'eacid' => $courseid]);

        [$insql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'eaqz');
        $params['eauid'] = $userid;
        $ids = [];
        foreach ($DB->get_records_select('quiz_attempts', "userid = :eauid AND quiz $insql", $params, 'id ASC') as $row) {
            $quiz = (int) $row->quiz;
            $row->course = $courseid;
            $ids[] = self::insert($userid, $courseid, mapper::QUIZ_ATTEMPT, $row, $now, [
                'cmid' => isset($cmids[$quiz]) ? (int) $cmids[$quiz]->cmid : null,
                'instanceid' => $quiz,
                'state' => (string) $row->state,
                'grade' => mapper::bounded_number($row->sumgrades),
                'timeevent' => mapper::first_timestamp([$row->timefinish, $row->timemodified, $row->timestart]),
            ]);
        }
        foreach ($DB->get_records_select('quiz_grades', "userid = :eauid AND quiz $insql", $params, 'id ASC') as $row) {
            $quiz = (int) $row->quiz;
            $row->course = $courseid;
            $ids[] = self::insert($userid, $courseid, mapper::QUIZ_GRADE, $row, $now, [
                'cmid' => isset($cmids[$quiz]) ? (int) $cmids[$quiz]->cmid : null,
                'instanceid' => $quiz,
                'grade' => mapper::bounded_number($row->grade),
                'timeevent' => mapper::timestamp($row->timemodified),
            ]);
        }
        return $ids;
    }

    /**
     * The learner's rows of the course's grade items (what reset_grades deletes).
     *
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return int[]
     */
    private static function grades(int $userid, int $courseid, int $now): array {
        global $DB;
        $items = $DB->get_fieldset_select('grade_items', 'id', 'courseid = :eacid', ['eacid' => $courseid]);
        if (!$items) {
            return [];
        }
        $ids = [];
        foreach (array_chunk($items, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'eagi');
            $rows = $DB->get_records_select('grade_grades', "userid = :eauid AND itemid $insql",
                $params + ['eauid' => $userid], 'id ASC');
            foreach ($rows as $row) {
                $row->course = $courseid;
                $ids[] = self::insert($userid, $courseid, mapper::GRADEBOOK_GRADE, $row, $now, [
                    'instanceid' => (int) $row->itemid,
                    'grade' => mapper::bounded_number($row->finalgrade),
                    'timeevent' => mapper::first_timestamp([$row->timemodified, $row->timecreated]),
                ]);
            }
        }
        return $ids;
    }

    /**
     * Write one archive row.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $type One of mapper::item_types().
     * @param \stdClass $source The row being archived; the payload is this, unchanged.
     * @param int $now
     * @param array $fields cmid, instanceid, parentid, itemkey, state, grade, timeevent.
     * @return int The new row's id.
     */
    private static function insert(int $userid, int $courseid, string $type, \stdClass $source, int $now,
                                   array $fields): int {
        global $DB;
        $itemkey = $fields['itemkey'] ?? null;
        $state = $fields['state'] ?? null;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'historyid' => 0,
            'userid' => $userid,
            'courseid' => $courseid,
            'itemtype' => $type,
            'cmid' => $fields['cmid'] ?? null,
            'instanceid' => $fields['instanceid'] ?? null,
            'parentid' => $fields['parentid'] ?? null,
            'itemkey' => $itemkey === null ? null : \core_text::substr((string) $itemkey, 0, 255),
            'state' => $state === null ? null : \core_text::substr((string) $state, 0, 30),
            'grade' => $fields['grade'] ?? null,
            'timeevent' => $fields['timeevent'] ?? null,
            'payload' => mapper::payload($source),
            'timecreated' => $now,
        ]);
    }
}
