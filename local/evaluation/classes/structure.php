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
 * Contains class local_evaluation_structure
 *
 * @package   local_evaluation
 * @author    2019 eabyas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Stores and manipulates the structure of the evaluation or template (items, pages, etc.)
 *
 * @package   local_evaluation
 * @author Sreenivas <sreenivasula@eabyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_evaluation_structure {
    /** @var stdClass record from 'evaluation' table.
     * Reliably has fields: id, timeopen, timeclose, anonymous, completionsubmit.
     * For full object or to access any other field use $this->get_evaluation()
     */
    protected $evaluation;
    /** @var int */
    protected $templateid;
    /** @var array */
    protected $allitems;

    /**
     * Constructor
     *
     * @param stdClass $evaluation evaluation object, in case of the template
     *     this is the current evaluation the template is accessed from
     * @param int $templateid template id if this class represents the template structure
     */
    public function __construct($evaluation, $templateid = null) {
        if ((empty($evaluation->id))) {
            throw new coding_exception('$evaluation must be passed to constructor');
        }
        $this->evaluation = $evaluation;
        $this->templateid = $templateid;
        if (!$evaluation) {
            // If evaluation object was not specified, populate object with fields required for the most of methods.
            // Full instance record can be retrieved by calling local_evaluation_structure::get_evaluation().
            $customdata = ['timeopen' => 0, 'timeclose' => 0, 'anonymous' => 0];
            $this->evaluation->timeopen = $customdata['timeopen'];
            $this->evaluation->timeclose = $customdata['timeclose'];
            $this->evaluation->anonymous = $customdata['anonymous'];
            $this->evaluation->completionsubmit = 0;
        }
    }

    /**
     * Current evaluation
     * @return stdClass
     */
    public function get_evaluation() {
        global $DB;
        if (!isset($this->evaluation->publish_stats) || !isset($this->evaluation->name)) {
            // Make sure the full object is retrieved.
            $this->evaluation = $DB->get_record('local_evaluations', ['id' => $this->evaluation->id], '*', MUST_EXIST);
        }
        return $this->evaluation;
    }

    /**
     * Template id
     * @return int
     */
    public function get_templateid() {
        return $this->templateid;
    }

    /**
     * Is this evaluation open (check timeopen and timeclose)
     * @return bool
     */
    public function is_open() {
        $checktime = time();
        return (!$this->evaluation->timeopen || $this->evaluation->timeopen <= $checktime) &&
            (!$this->evaluation->timeclose || $this->evaluation->timeclose >= $checktime);
    }

    /**
     * Get all items in this evaluation or this template
     * @param bool $hasvalueonly only count items with a value.
     * @return array of objects from evaluation_item with an additional attribute 'itemnr'
     */
    public function get_items($hasvalueonly = false) {
        global $DB;
        if ($this->allitems === null) {
            if ($this->templateid) {
                $this->allitems = $DB->get_records('local_evaluation_item', ['template' => $this->templateid], 'position');
            } else {
                $this->allitems = $DB->get_records('local_evaluation_item', ['evaluation' => $this->evaluation->id], 'position');
            }
            $idx = 1;
            foreach ($this->allitems as $id => $item) {
                $this->allitems[$id]->itemnr = $item->hasvalue ? ($idx++) : null;
            }
        }
        if ($hasvalueonly && $this->allitems) {
            return array_filter($this->allitems, function($item) {
                return $item->hasvalue;
            });
        }
        return $this->allitems;
    }

    /**
     * Is the items list empty?
     * @return bool
     */
    public function is_empty() {
        $items = $this->get_items();
        $displayeditems = array_filter($items, function($item) {
            return $item->typ !== 'pagebreak';
        });
        return !$displayeditems;
    }

    /**
     * Is this evaluation anonymous?
     * @return bool
     */
    public function is_anonymous() {
        return $this->evaluation->anonymous == EVALUATION_ANONYMOUS_YES;
    }

    /**
     * Returns the formatted text of the page after submit or null if it is not set
     *
     * @return string|null
     */
    public function page_after_submit() {
        global $CFG;
        $pageaftersubmit = $this->get_evaluation()->page_after_submit;
        if (empty($pageaftersubmit)) {
            return null;
        }
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        $output = file_rewrite_pluginfile_urls($pageaftersubmit,
                'pluginfile.php', $context->id, 'local_evaluation', 'page_after_submit', 0);
        $draftitemid = file_get_submitted_draft_itemid('page_after_submit');
        $editoroptions = evaluation_get_editor_options();
        return file_prepare_draft_area($draftitemid, $context->id,
                                      'local_evaluation', 'page_after_submit', false,
                                      $editoroptions,
                                      $pageaftersubmit);
    }

    /**
     * Checks if current user is able to view evaluation on this course.
     *
     * @return bool
     */
    public function can_view_analysis() {
        $context = (new \local_evaluation\lib\accesslib())::get_module_context();
        if (has_capability('local/evaluation:viewreports', $context)) {
            return true;
        }

        if (intval($this->get_evaluation()->publish_stats) != 1 || !has_capability('local/evaluation:viewanalysepage', $context)) {
            return false;
        }

        return $this->is_already_submitted(true);
    }

    /**
     * check for multiple_submit = false.
     * if the evaluation is global so the courseid must be given
     *
     * @param bool $anycourseid if true checks if this evaluation was submitted in any course, otherwise checks $this->courseid .
     *     Applicable to frontpage evaluations only
     * @return bool true if the evaluation already is submitted otherwise false
     */
    public function is_already_submitted($anycourseid = false) {
        global $USER, $DB;

        if (!isloggedin() || isguestuser()) {
            return false;
        }

        $params = array('userid' => $USER->id, 'evaluation' => $this->evaluation->id);
        return $DB->record_exists('local_evaluation_completed', $params);
    }

    /**
     * Check whether the evaluation is mapped to the given courseid.
     */
    public function check_course_is_mapped() {
        return false;
    }

    /**
     * If there are any new responses to the anonymous evaluation, re-shuffle all
     * responses and assign response number to each of them.
     */
    public function shuffle_anonym_responses() {
        global $DB;
        $params = array('evaluation' => $this->evaluation->id,
            'random_response' => 0,
            'anonymous_response' => EVALUATION_ANONYMOUS_YES);

        if ($DB->count_records('local_evaluation_completed', $params, 'random_response')) {
            // Get all of the anonymous records, go through them and assign a response id.
            unset($params['random_response']);
            $evaluationcompleteds = $DB->get_records('local_evaluation_completed', $params, 'id');
            shuffle($evaluationcompleteds);
            $num = 1;
            foreach ($evaluationcompleteds as $compl) {
                $compl->random_response = $num++;
                $DB->update_record('local_evaluation_completed', $compl);
            }
        }
    }

    /**
     * Counts records from {evaluation_completed} table for a given evaluation
     *
     * If $groupid or $this->courseid is set, the records are filtered by the group/course
     *
     * @param int $groupid
     * @return mixed array of found completeds otherwise false
     */
    public function count_completed_responses($groupid = 0) {
        global $DB;
        if (intval($groupid) > 0) {
            $query = "SELECT COUNT(DISTINCT fbc.id)
                        FROM {local_evaluation_completed} fbc, {groups_members} gm
                        WHERE fbc.evaluation = :evaluation
                            AND gm.groupid = :groupid
                            AND fbc.userid = gm.userid";
        } else {
            $query = "SELECT COUNT(fbc.id) FROM {local_evaluation_completed} fbc WHERE fbc.evaluation = :evaluation";
        }
        $params = ['evaluation' => $this->evaluation->id];
        return $DB->get_field_sql($query, $params);
    }

    /**
     * For the frontpage evaluation returns the list of courses with at least one completed evaluation
     *
     * @return array id=>name pairs of courses
     */
    public function get_completed_courses() {
        global $DB;

        if ($this->get_evaluation()->course != SITEID) {
            return [];
        }

        if ($this->allcourses !== null) {
            return $this->allcourses;
        }

        $courseselect = "SELECT fbc.courseid
            FROM {evaluation_completed} fbc
            WHERE fbc.evaluation = :evaluationid";

        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');

        $sql = 'SELECT c.id, c.shortname, c.fullname, c.idnumber, c.visible, '. $ctxselect. '
                FROM {course} c
                JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
                WHERE c.id IN ('. $courseselect.') ORDER BY c.sortorder';
        $list = $DB->get_records_sql($sql, ['contextcourse' => CONTEXT_COURSE, 'evaluationid' => $this->get_evaluation()->id]);

        $this->allcourses = array();
        foreach ($list as $course) {
            context_helper::preload_from_record($course);
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id))) {
                // Do not return courses that current user can not see.
                continue;
            }
            $label = get_course_display_name_for_list($course);
            $this->allcourses[$course->id] = $label;
        }
        return $this->allcourses;
    }
}