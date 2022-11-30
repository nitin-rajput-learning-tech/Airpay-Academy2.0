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
 * Class for exporting a feedback temporary completion record.
 *
 * @package    local_evaluation
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_evaluation\external;
defined('MOODLE_INTERNAL') || die();

use core\external\exporter;

/**
 * Class for exporting a feedback temporary completion record.
 *
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_completedtmp_exporter extends exporter {

    /**
     * Return the list of properties.
     *
     * @return array list of properties
     */
    protected static function define_properties() {
        return array(
            'id' => array(
                'type' => PARAM_INT,
                'description' => get_string('record_id', 'local_evaluation'),
            ),
            'feedback' => array(
                'type' => PARAM_INT,
                'description' => get_string('feedback_instance_records_belongs_to', 'local_evaluation'),
            ),
            'userid' => array(
                'type' => PARAM_INT,
                'description' => get_string('user_who_completed_feedback', 'local_evaluation'),
            ),
            'guestid' => array(
                'type' => PARAM_RAW,
                'description' => get_string('guests_session_key', 'local_evaluation'),
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
                'description' => get_string('last_time_feedback_completed', 'local_evaluation'),
            ),
            'random_response' => array(
                'type' => PARAM_INT,
                'description' => get_string('response_number', 'local_evaluation'),
            ),
            'anonymous_response' => array(
                'type' => PARAM_INT,
                'description' => get_string('anonymous_response', 'local_evaluation'),
            ),
            'courseid' => array(
                'type' => PARAM_INT,
                'description' => get_string('course_id_feedback_completed', 'local_evaluation'),
            ),
        );
    }
}
