<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;
use local_sentientia_recompletion\bizlms\mapper;

/**
 * The data of the evidence view (history_detail.php): one reset, and what it deleted.
 *
 * A reset deletes the learner's live completion rows, so the archive is what an auditor is shown for every cycle
 * before the current one. This class reads the archive for one reset (or, for evidence that could not be tied
 * to a reset, one learner and course) and shapes it for the template: a section per item type, each a plain
 * table of cells.
 *
 * Tenant scope is the history page's: the caller must be cross-tenant, or the learner must be inside their
 * tenant (rule_access::history_user_filter). A row the caller may not see is reported exactly like a row that
 * does not exist.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evidence_report {

    /** The flag that turns the view on. */
    public const FLAG = 'sentientia.recompletion.evidence_view';

    /** Rows shown per section; the count of the whole section is always shown. */
    public const SECTION_LIMIT = 200;

    /** Format of every date on the page (the history page's). */
    private const DATETIME = '%d %b %Y %H:%M';

    /** Format of a date with no time of day. */
    private const DATE = '%d %b %Y';

    /**
     * Is the view switched on?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * The history row the caller may see, with the learner's name and the course name.
     *
     * @param int $historyid
     * @return \stdClass|null Null when there is no such row or it is outside the caller's tenant.
     */
    public static function visible_history(int $historyid): ?\stdClass {
        global $DB;
        if (tenant::is_cross_tenant()) {
            $join = 'LEFT JOIN {user} u ON u.id = h.userid';
            $where = '1 = 1';
            $params = [];
        } else {
            $join = 'JOIN {user} u ON u.id = h.userid';
            [$where, $params] = rule_access::history_user_filter('u');
        }
        $row = $DB->get_record_sql(
            "SELECT h.*, u.firstname, u.lastname, u.deleted AS user_deleted, c.fullname AS coursename
               FROM {local_sentientia_recompletion_history} h
               $join
          LEFT JOIN {course} c ON c.id = h.courseid
              WHERE h.id = :evhid AND $where",
            $params + ['evhid' => $historyid]);
        return $row ?: null;
    }

    /**
     * May the caller see evidence of this learner in this course? (For evidence not tied to a reset.)
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|null The learner's name and the course name; null when not visible.
     */
    public static function visible_pair(int $userid, int $courseid): ?\stdClass {
        global $DB;
        if ($userid <= 0) {
            return null;
        }
        [$where, $params] = tenant::is_cross_tenant() ? ['1 = 1', []] : rule_access::history_user_filter('u');
        $row = $DB->get_record_sql(
            "SELECT u.id AS userid, u.firstname, u.lastname, u.deleted AS user_deleted, c.fullname AS coursename
               FROM {user} u
          LEFT JOIN {course} c ON c.id = :evcid
              WHERE u.id = :evuid AND $where",
            $params + ['evuid' => $userid, 'evcid' => $courseid]);
        return $row ?: null;
    }

    /**
     * The header of the page for one reset.
     *
     * @param \stdClass $history As returned by visible_history().
     * @return array The template's header fields (plain text; the template escapes).
     */
    public static function header(\stdClass $history): array {
        global $DB;
        $component = 'local_sentientia_recompletion';
        $userid = (int) $history->userid;
        $resetby = $history->reset_by_userid === null ? 0 : (int) $history->reset_by_userid;
        $inferred = (int) ($history->time_inferred ?? 0) === 1;

        if ($resetby === 0) {
            $by = get_string('evidence_scheduled', $component);
        } else if ($resetby === $userid) {
            $by = get_string('evidence_self', $component);
        } else if (tenant::is_cross_tenant()) {
            $by = (string) $DB->get_field_sql(
                "SELECT " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " FROM {user} u WHERE u.id = :evby",
                ['evby' => $resetby]);
            $by = trim($by) !== '' ? $by : get_string('evidence_admin', $component);
        } else {
            // A tenant admin is not told the name of somebody who may belong to another tenant.
            $by = get_string('evidence_admin', $component);
        }

        return [
            'learner' => $userid > 0 ? self::learner_name($history)
                : get_string('evidence_redacted', $component),
            'course' => $history->coursename !== null ? format_string($history->coursename)
                : get_string('evidence_course_gone', $component),
            'reset_at' => ($inferred ? '~ ' : '') . userdate($history->timecreated, self::DATETIME),
            'inferred' => $inferred,
            'reason' => (string) $history->reason,
            'legacy' => ($history->source ?? 'engine') === 'legacy',
            'reset_by' => $by,
            'previous' => $history->previous_timecompleted
                ? userdate($history->previous_timecompleted, self::DATE) : '-',
            'grades_reset' => (bool) $history->reset_grades,
            'attempts_reset' => (bool) $history->reset_attempts,
        ];
    }

    /**
     * A learner's name as the page shows it: a user who has since been deleted keeps their history (ADR-032), and
     * is marked.
     *
     * @param \stdClass $row A row that carries firstname, lastname and user_deleted.
     * @return string
     */
    public static function learner_name(\stdClass $row): string {
        $name = trim(($row->firstname ?? '') . ' ' . ($row->lastname ?? ''));
        if (!empty($row->user_deleted)) {
            $name = trim($name . ' (' . get_string('badge_deleted_user', 'local_sentientia_recompletion') . ')');
        }
        return $name;
    }

    /**
     * The sections of evidence tied to one reset.
     *
     * @param int $historyid
     * @return array<array{title: string, count: int, headers: string[], rows: array, more: string}>
     */
    public static function sections_for_history(int $historyid): array {
        return self::sections('historyid = :evh', ['evh' => $historyid]);
    }

    /**
     * The sections of evidence of one learner in one course that is not tied to any reset (historyid 0): the
     * log row of the reset was purged and no archived completion was left to infer one from.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public static function sections_for_pair(int $userid, int $courseid): array {
        return self::sections('historyid = 0 AND userid = :evu AND courseid = :evc', ['evu' => $userid, 'evc' => $courseid]);
    }

    /**
     * Evidence sections for a select over the archive table.
     *
     * @param string $select
     * @param array $params
     * @return array
     */
    private static function sections(string $select, array $params): array {
        global $DB;
        $counts = $DB->get_records_sql(
            "SELECT itemtype, COUNT(1) AS n FROM {local_sentientia_recompletion_archive} WHERE $select GROUP BY itemtype",
            $params);
        $component = 'local_sentientia_recompletion';
        $sections = [];
        foreach (mapper::item_types() as $type) {
            if (!isset($counts[$type])) {
                continue;
            }
            $total = (int) $counts[$type]->n;
            $rows = $DB->get_records_select('local_sentientia_recompletion_archive', "($select) AND itemtype = :evtype",
                $params + ['evtype' => $type], 'timeevent ASC, id ASC', '*', 0, self::SECTION_LIMIT);
            $shaped = self::shape($type, array_values($rows));
            $sections[] = [
                'title' => get_string('evidence_type_' . $type, $component),
                'count' => $total,
                'headers' => $shaped['headers'],
                'rows' => $shaped['rows'],
                'more' => $total > count($rows)
                    ? get_string('evidence_more', $component, $total - count($rows)) : '',
            ];
        }
        return $sections;
    }

    /**
     * Turn archive rows of one type into table headers and cells.
     *
     * @param string $type
     * @param \stdClass[] $rows
     * @return array{headers: string[], rows: array<array{cells: string[]}>}
     */
    public static function shape(string $type, array $rows): array {
        global $DB;
        $component = 'local_sentientia_recompletion';
        $string = static fn(string $key): string => get_string($key, $component);
        $when = static fn(?int $time): string => $time ? userdate($time, self::DATETIME) : '-';
        $grade = static fn($value): string => $value === null ? '-' : format_float((float) $value, 2);
        $data = static function (\stdClass $row): array {
            $decoded = json_decode((string) $row->payload, true);
            return is_array($decoded) ? $decoded : [];
        };

        $quizzes = [];
        $scorms = [];
        if (in_array($type, [mapper::QUIZ_ATTEMPT, mapper::QUIZ_GRADE], true)) {
            $ids = array_values(array_unique(array_filter(array_map(static fn($r) => (int) $r->instanceid, $rows))));
            $quizzes = $ids ? $DB->get_records_list('quiz', 'id', $ids, '', 'id, name, grade, sumgrades') : [];
        }
        if ($type === mapper::SCORM_TRACK && $DB->get_manager()->table_exists('scorm')) {
            $ids = array_values(array_unique(array_filter(array_map(static fn($r) => (int) $r->instanceid, $rows))));
            $scorms = $ids ? $DB->get_records_list('scorm', 'id', $ids, '', 'id, name') : [];
        }

        $out = [];
        switch ($type) {
            case mapper::COURSE_COMPLETION:
                $headers = [$string('col_state'), $string('col_enrolled'), $string('col_started'), $string('col_completed')];
                foreach ($rows as $r) {
                    $p = $data($r);
                    $out[] = [$r->state, $when(mapper::timestamp($p['timeenrolled'] ?? null)),
                        $when(mapper::timestamp($p['timestarted'] ?? null)), $when(mapper::timestamp($p['timecompleted'] ?? null))];
                }
                break;
            case mapper::CRITERIA_COMPLETION:
                $headers = [$string('col_criteria'), $string('col_state'), $string('col_grade'), $string('col_when')];
                foreach ($rows as $r) {
                    $out[] = ['#' . (int) $r->instanceid, $r->state, $grade($r->grade), $when($r->timeevent)];
                }
                break;
            case mapper::ACTIVITY_COMPLETION:
                $headers = [$string('col_activity'), $string('col_state'), $string('col_when')];
                $names = self::activity_names($rows);
                foreach ($rows as $r) {
                    $out[] = [$names[(int) $r->cmid] ?? ('#' . (int) $r->cmid), $r->state, $when($r->timeevent)];
                }
                break;
            case mapper::QUIZ_ATTEMPT:
                $headers = [$string('col_quiz'), $string('col_attempt'), $string('col_state'), $string('col_marks'),
                    $string('col_when')];
                foreach ($rows as $r) {
                    $quiz = $quizzes[(int) $r->instanceid] ?? null;
                    $p = $data($r);
                    $out[] = [$quiz ? format_string($quiz->name) : ('#' . (int) $r->instanceid),
                        (string) ($p['attempt'] ?? '-'), $r->state, self::scaled_marks($quiz, $r->grade),
                        $when($r->timeevent)];
                }
                break;
            case mapper::QUIZ_GRADE:
                $headers = [$string('col_quiz'), $string('col_grade'), $string('col_when')];
                foreach ($rows as $r) {
                    $quiz = $quizzes[(int) $r->instanceid] ?? null;
                    $out[] = [$quiz ? format_string($quiz->name) : ('#' . (int) $r->instanceid),
                        $quiz && $quiz->grade !== null ? $grade($r->grade) . ' / ' . $grade($quiz->grade) : $grade($r->grade),
                        $when($r->timeevent)];
                }
                break;
            case mapper::SCORM_TRACK:
                $headers = [$string('col_scorm'), $string('col_element'), $string('col_value'), $string('col_when')];
                foreach ($rows as $r) {
                    $p = $data($r);
                    $scorm = $scorms[(int) $r->instanceid] ?? null;
                    $out[] = [$scorm ? format_string($scorm->name) : ('#' . (int) $r->instanceid), (string) $r->itemkey,
                        (string) ($p['value'] ?? ''), $when($r->timeevent)];
                }
                break;
            case mapper::LTI_GRADE:
                $headers = [$string('col_tool'), $string('col_grade'), $string('col_when')];
                foreach ($rows as $r) {
                    $out[] = ['#' . (int) $r->instanceid, $grade($r->grade), $when($r->timeevent)];
                }
                break;
            case mapper::QUESTIONNAIRE_RESPONSE:
                $headers = [$string('col_questionnaire'), $string('col_state'), $string('col_grade'), $string('col_when')];
                foreach ($rows as $r) {
                    $out[] = ['#' . (int) $r->instanceid, $r->state, $grade($r->grade), $when($r->timeevent)];
                }
                break;
            case mapper::QUESTIONNAIRE_ANSWER:
                $headers = [$string('col_questionnaire'), $string('col_question'), $string('col_answer')];
                foreach ($rows as $r) {
                    $p = $data($r);
                    $answer = array_key_exists('response', $p) ? (string) $p['response'] : (string) $r->state;
                    if ($r->grade !== null) {
                        $answer .= ' (' . $grade($r->grade) . ')';
                    }
                    $out[] = ['#' . (int) $r->instanceid, '#' . (string) $r->itemkey, $answer];
                }
                break;
            case mapper::GRADEBOOK_GRADE:
                $headers = [$string('col_item'), $string('col_grade'), $string('col_when')];
                foreach ($rows as $r) {
                    $out[] = ['#' . (int) $r->instanceid, $grade($r->grade), $when($r->timeevent)];
                }
                break;
            default:
                $headers = [];
        }
        return [
            'headers' => $headers,
            'rows' => array_map(static fn(array $cells): array => ['cells' => array_map('strval', $cells)], $out),
        ];
    }

    /**
     * Quiz marks as BizLMS showed them: the attempt's raw marks scaled by the quiz's grade and sumgrades.
     *
     * @param \stdClass|null $quiz The quiz row (grade, sumgrades), null when the quiz is gone.
     * @param mixed $sumgrades The attempt's raw marks as archived.
     * @return string "12.50 / 20.00", or the raw marks when they cannot be scaled, or "-".
     */
    public static function scaled_marks(?\stdClass $quiz, $sumgrades): string {
        if ($sumgrades === null) {
            return '-';
        }
        $raw = (float) $sumgrades;
        if ($quiz === null || $quiz->sumgrades === null || (float) $quiz->sumgrades <= 0 || $quiz->grade === null) {
            return format_float($raw, 2);
        }
        $scaled = $raw / (float) $quiz->sumgrades * (float) $quiz->grade;
        return format_float($scaled, 2) . ' / ' . format_float((float) $quiz->grade, 2);
    }

    /**
     * Names of the activities the rows point at, by course module id. An activity that has since been deleted
     * has no name and is shown by its number.
     *
     * @param \stdClass[] $rows Archive rows of type activity_completion.
     * @return array<int, string>
     */
    private static function activity_names(array $rows): array {
        $names = [];
        $modinfos = [];
        foreach ($rows as $r) {
            $cmid = (int) $r->cmid;
            $courseid = (int) $r->courseid;
            if ($cmid <= 0 || $courseid <= 0 || isset($names[$cmid])) {
                continue;
            }
            try {
                $modinfos[$courseid] ??= get_fast_modinfo($courseid, -1);
                $cm = $modinfos[$courseid]->cms[$cmid] ?? null;
                if ($cm !== null) {
                    $names[$cmid] = format_string($cm->name);
                }
            } catch (\Throwable $e) {
                // The course is gone: its activities are shown by number.
                unset($e);
            }
        }
        return $names;
    }
}
