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
 * program View
 *
 * @package    local_program
 * @copyright  2018 Arun Kumar M <arun@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_program\output;
defined('MOODLE_INTERNAL') || die;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use context_system;

class session_attendance implements renderable, templatable {
    /**
     * [__construct description]
     * @method __construct
     */
    public function __construct($sessionid) {
        $this->context = context_system::instance();
        $this->sessionid = $sessionid;
    }
    /**
     * [export_for_template description]
     * @method export_for_template
     * @param  renderer_base       $output [description]
     * @return [type]                      [description]
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT, $DB;
        $data = new stdClass();
        // $data->output = $OUTPUT;
        $data->sessiondata = $DB->get_record('local_bc_course_sessions',
            array('id' => $this->sessionid));
        $trainer = $DB->get_record('user', array('id' => $data->sessiondata->trainerid));
        $data->sessiondata->duration = round(($data->sessiondata->duration/60) , 2);
        $data->sessiondata->trainername = fullname($trainer);
        return $data;
    }
}