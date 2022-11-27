<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_classroom
 */

namespace local_classroom\output;
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
        $data->output = $OUTPUT;
        $data->sessiondata = $DB->get_record('local_classroom_sessions', array('id' => $this->sessionid));
        $data_trainers=$DB->get_record('user', array('id' =>$data->sessiondata->trainerid));
        $data->sessiondata->duration = round($data->sessiondata->duration/60, 2);
        $data->sessiondata->trainername=fullname($data_trainers);
        
        return $data;
    }
}