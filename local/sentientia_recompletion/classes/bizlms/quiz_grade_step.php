<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived quiz grade (quiz_grades): the learner's overall grade on one quiz.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_grade_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.qg';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::QG;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $user = (int) $row->userid;
            if (!self::known_user($ctx, $user)) {
                $out[] = self::orphan_user($id);
                continue;
            }
            $quiz = (int) $row->quiz;
            $course = (int) $row->course > 0 ? (int) $row->course : $evidence->quiz_course($quiz);
            $out[] = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, $course,
                mapper::QUIZ_GRADE, [
                    'cmid' => $evidence->quiz_cmid($quiz),
                    'instanceid' => $quiz,
                    'grade' => mapper::bounded_number($row->grade),
                    'timeevent' => mapper::timestamp($row->timemodified),
                ]));
        }
        return $out;
    }
}
