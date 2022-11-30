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
 * Class for exporting a feedback tmp response.
 *
 * @package    local_evaluation
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_evaluation\external;
defined('MOODLE_INTERNAL') || die();

use core\external\exporter;

/**
 * Class for exporting a feedback tmp response.
 *
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_valuetmp_exporter extends exporter {

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
            'course_id' => array(
                'type' => PARAM_INT,
                'description' => get_string('course_id_record_belongs_to', 'local_evaluation'),
            ),
            'item' => array(
                'type' => PARAM_INT,
                'description' => get_string('responded_item_id', 'local_evaluation'),
            ),
            'completed' => array(
                'type' => PARAM_INT,
                'description' => get_string('reference_to_evaluation_table', 'local_evaluation'),
            ),
            'tmp_completed' => array(
                'type' => PARAM_INT,
                'description' => get_string('old_file_not_used_anymore', 'local_evaluation'),
            ),
            'value' => array(
                'type' => PARAM_RAW,
                'description' => get_string('response_value', 'local_evaluation'),
            ),
        );
    }
}
