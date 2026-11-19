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

namespace theme_sentientia\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Which of a user's enrolled courses the user teaches rather than takes.
 *
 * Surface walk 2026-11-18 (V16): trainers and course authors on UAT are enrolled in their courses only as
 * editingteacher, so the learner dashboard counted those courses as their own learning ("Not started 0%" cards and a
 * "0 of 1" completion ring). The dashboard lists them under "Courses you teach" instead, and keeps them out of the
 * learner figures (a bug fix with no flag: Nitin, decision D3).
 *
 * A course counts as taught when the user holds a role with the editingteacher or teacher archetype in that course's
 * own context, and no role with the student archetype there. Only course-context role assignments count:
 *
 *   - capabilities cannot decide it, because the Airpay trainer role is assigned at system level with
 *     moodle/grade:viewall, so it would make every course a taught one;
 *   - UAT trainers hold the employee role at system level, which says nothing about a single course;
 *   - a user who is both teacher and student in one course is still learning it, so it stays a learning course.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teaching_courses {

    /** @var string[] Role archetypes that make a course a taught one. */
    private const TEACH_ARCHETYPES = ['editingteacher', 'teacher'];

    /** @var string Role archetype that keeps a course a learning one. */
    private const LEARN_ARCHETYPE = 'student';

    /**
     * The ids, out of $courseids, of the courses $userid teaches and does not take.
     *
     * @param int $userid the user whose role assignments are read
     * @param int[] $courseids the candidate courses (the user's enrolled courses); no other id is ever returned
     * @return int[] course ids, a subset of $courseids
     */
    public static function course_ids(int $userid, array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (empty($courseids) || $userid <= 0) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'tc');
        $params += ['uid' => $userid, 'courselevel' => CONTEXT_COURSE];

        $rs = $DB->get_recordset_sql(
            "SELECT ra.id, ctx.instanceid AS courseid, r.archetype
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :courselevel
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :uid
                AND ctx.instanceid $insql",
            $params);

        $teach = [];
        $learn = [];
        try {
            foreach ($rs as $row) {
                $courseid = (int) $row->courseid;
                if (in_array((string) $row->archetype, self::TEACH_ARCHETYPES, true)) {
                    $teach[$courseid] = true;
                } else if ((string) $row->archetype === self::LEARN_ARCHETYPE) {
                    $learn[$courseid] = true;
                }
            }
        } finally {
            $rs->close();
        }

        return array_map('intval', array_keys(array_diff_key($teach, $learn)));
    }
}
