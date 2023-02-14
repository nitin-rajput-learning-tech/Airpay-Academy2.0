<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This suggested course is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This suggested course is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this suggested course.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course lister block.
 *
 * @author eabyas  <info@eabyas.in>

 * @copyright 2020 Fortech inc
 * @subpackage block_suggested_courses
 */

namespace block_suggested_courses\output;

use block_suggested_courses\plugin;
use course_in_list;
use ddl_exception;
use moodle_url;
use plugin_renderer_base;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class renderer
 * @author eabyas  <info@eabyas.in>

 * @package Bizlms
 * @subpackage block_suggested_courses
 */
final class renderer extends plugin_renderer_base {

    /**
     * Get course image if any
     * @param stdClass $courseobj
     * @return string
     */
    public function course_image_moodle($courseobj) {
        global $CFG;
        $result = '';
        require_once($CFG->libdir.'/coursecatlib.php');
        $course = new course_in_list($courseobj);
        // Check to see if a file has been set on the course level.
        if (($course->id > 0) and $course->has_course_overviewfiles()) {
            foreach ($course->get_course_overviewfiles() as $file) {
                if ($file->is_valid_image()) {
                    $url = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    );
                    $result = $url->out(false);
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Get course image
     * @param stdClass $courseobj
     * @return string
     */
    public function course_image($courseobj) {
        global $CFG;
        require_once($CFG->dirroot.'/course/lib.php');
        if (function_exists('course_get_image')) {
            $imageurl = course_get_image($courseobj);
            $result = $imageurl->out(false);
        } else {
            $result = $this->course_image_moodle($courseobj);
        }
        return $result;
    }

    /**
     * Render course icon
     * @param stdClass $courseobj
     * @return array
     * @throws ddl_exception
     */
    public function course_icon($courseobj) {
        global $CFG;

        $result = [];
        if (plugin::istocourselister()) {
            //$result = courselister_icon_url_and_alt('course', $courseobj->icon);
        }

        return $result;
    }

    /**
     * Render the blockview
     * @param  blockview $widget
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_blockview(blockview $widget) {
        $context = $widget->export_for_template($this);
        return $context;
    }


}
