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
 * Class for exporting partial feedback data.
 *
 * @package    local_evaluation
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_evaluation\external;
defined('MOODLE_INTERNAL') || die();

use core\external\exporter;
use renderer_base;
use external_util;
use external_files;

/**
 * Class for exporting partial feedback data (some fields are only viewable by admins).
 *
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_summary_exporter extends exporter {

    protected static function define_properties() {
        return array(
            'id' => array(
                'type' => PARAM_INT,
                'description' => get_string('record_primary_key', 'local_evaluation'),
            ),
            'course' => array(
                'type' => PARAM_INT,
                'description' => get_string('course_feedback_part', 'local_evaluation'),
            ),
            'name' => array(
                'type' => PARAM_TEXT,
                'description' => get_string('only_feedback_name', 'local_evaluation'),
            ),
            'intro' => array(
                'default' => '',
                'type' => PARAM_RAW,
                'description' => get_string('feedback_introduction_text', 'local_evaluation'),
            ),
            'introformat' => array(
                'choices' => array(FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN),
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE,
                'description' => get_string('feedback_introduction_text_format', 'local_evaluation'),
            ),
            'anonymous' => array(
                'type' => PARAM_INT,
                'description' => get_string('whether_feedback_is_anonymous', 'local_evaluation'),
            ),
            'email_notification' => array(
                'type' => PARAM_BOOL,
                'optional' => true,
                'description' => get_string('whether_email_notification_sent_to_teachers', 'local_evaluation'),
            ),
            'multiple_submit' => array(
                'default' => 1,
                'type' => PARAM_BOOL,
                'description' => get_string('whether_multiple_submission_allowed', 'local_evaluation'),
            ),
            'autonumbering' => array(
                'default' => 1,
                'type' => PARAM_BOOL,
                'description' => get_string('whether_questions_auto_numbered', 'local_evaluation'),
            ),
            'site_after_submit' => array(
                'type' => PARAM_TEXT,
                'optional' => true,
                'description' => get_string('next_page_after_submission', 'local_evaluation'),
            ),
            'page_after_submit' => array(
                'type' => PARAM_RAW,
                'optional' => true,
                'description' => get_string('text_display_after_submission', 'local_evaluation'),
            ),
            'page_after_submitformat' => array(
                'choices' => array(FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN),
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE,
                'description' => get_string('text_display_after_submission_format', 'local_evaluation'),
            ),
            'publish_stats' => array(
                'default' => 0,
                'type' => PARAM_BOOL,
                'description' => get_string('whether_stats_published', 'local_evaluation'),
            ),
            'timeopen' => array(
                'type' => PARAM_INT,
                'optional' => true,
                'description' => get_string('allow_answers_from_this_time', 'local_evaluation'),
            ),
            'timeclose' => array(
                'type' => PARAM_INT,
                'optional' => true,
                'description' => get_string('allow_answers_until_this_time', 'local_evaluation'),
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
                'optional' => true,
                'description' => get_string('record_modified_time', 'local_evaluation'),
            ),
            'completionsubmit' => array(
                'default' => 0,
                'type' => PARAM_BOOL,
                'description' => get_string('conditional_automatic_mark_submission', 'local_evaluation'),
            ),
        );
    }

    protected static function define_related() {
        return array(
            'context' => 'context'
        );
    }

    protected static function define_other_properties() {
        return array(
            'coursemodule' => array(
                'type' => PARAM_INT
            ),
            'introfiles' => array(
                'type' => external_files::get_properties_for_exporter(),
                'multiple' => true
            ),
            'pageaftersubmitfiles' => array(
                'type' => external_files::get_properties_for_exporter(),
                'multiple' => true,
                'optional' => true
            ),
        );
    }

    protected function get_other_values(renderer_base $output) {
        $context = $this->related['context'];

        $values = array(
            'coursemodule' => $context->instanceid,
        );

        $values['introfiles'] = external_util::get_area_files($context->id, 'local_evaluation', 'intro', false, false);

        if (!empty($this->data->page_after_submit)) {
            $values['pageaftersubmitfiles'] = external_util::get_area_files($context->id, 'local_evaluation', 'page_after_submit');
        }

        return $values;
    }

    /**
     * Get the formatting parameters for the intro.
     *
     * @return array
     */
    protected function get_format_parameters_for_intro() {
        return [
            'component' => 'local_evaluation',
            'filearea' => 'intro',
        ];
    }

    /**
     * Get the formatting parameters for the page_after_submit.
     *
     * @return array
     */
    protected function get_format_parameters_for_page_after_submit() {
        return [
            'component' => 'local_evaluation',
            'filearea' => 'page_after_submit',
            'itemid' => 0
        ];
    }
}
