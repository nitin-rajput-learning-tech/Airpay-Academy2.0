<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This courselister is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This courselister is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this courselister.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage local_courses
 */


namespace local_courses\output;

use local_courses\plugin;
use context_course;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use moodle_url;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/completionlib.php');

/**
 * Class view
 * @package   local_courses
 * @copyright 2020 Fortech inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfcompletion implements renderable, templatable {

    /** @var stdClass|null */
    private $courseid;

    private $userid;

    /**
     * blockview constructor.
     * @param stdClass|null $config
     */
    public function __construct($courseid,$userid) {
        $this->courseid = $courseid;
        $this->userid = $userid;
    }

    /**
     * Generate template
     * @param renderer_base $output
     * @return array
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $CFG, $USER;

        $course=$DB->get_record('course',array('id'=>$this->courseid));
        
        $context =array('courseid'=>$this->courseid,'userid'=>$this->userid,'fullname'=>format_string($course->fullname),'disabled'=>true);

        // Get course completion data
 
        $info = new \completion_info($course);
            // Get this user's data
        $completion = $info->get_completion($this->userid, COMPLETION_CRITERIA_TYPE_SELF);


        // Check if self completion is one of this course's criteria
        if (empty($completion)) {

            $context['tittle']= get_string('selfcompletionnotenabled', 'block_selfcompletion');
            //print_object(array(1));
            return $context;
        }
        // Check this user is enroled
        if (!$info->is_tracked_user($this->userid)) {

            $context['tittle']=get_string('nottracked', 'completion');
            //print_object(array(2));
            return $context;
        }

        // Is course complete?
        if ($info->is_course_complete($this->userid)) {

            $context['tittle']=get_string('coursealreadycompleted', 'completion');
            //print_object(array(3));
            return $context;

        // Check if the user has already marked themselves as complete
        } else if ($completion->is_complete()) {

            $context['tittle']=get_string('alreadyselfcompleted', 'block_selfcompletion');
            $context['iscompleted']=true;
             //print_object(array(4));
            return $context;
        // If user is not complete, or has not yet self completed
        } else {
        
            $context['tittle']=get_string('selfcompletion', 'local_courses');

            $context['disabled']=false;

             //print_object(array(5));
            return $context;
            
        }
    }
}
