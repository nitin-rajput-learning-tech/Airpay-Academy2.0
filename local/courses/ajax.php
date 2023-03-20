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
 * @subpackage local_program
 */

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $CFG, $USER, $PAGE;

$action = required_param('action', PARAM_ACTION);
$courseid = optional_param('courseid', null, PARAM_INT);
$search = optional_param_array('search', '', PARAM_RAW);
$start = optional_param('start', 0, PARAM_INT);
$length = optional_param('length', 10, PARAM_INT);
$context = (new \local_courses\lib\accesslib())::get_module_context($courseid);
require_login();
$PAGE->set_context($context);
$renderer = $PAGE->get_renderer('local_courses');

switch ($action) {
    case 'enrolledusers':
        $dataobj = new stdClass();
        $dataobj->search = $search['value'];
        $dataobj->start = $start;
        $dataobj->length = $length;
        $dataobj->courseid = $courseid;
        
        $return = $renderer->get_course_enrolledusers($dataobj);
    break;
}
echo json_encode($return);
