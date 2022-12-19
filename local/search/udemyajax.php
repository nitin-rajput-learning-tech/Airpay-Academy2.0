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
 * @package   BizLMS
 * @subpackage local_search
 * @author eabyas  <info@eabyas.in>
**/
ini_set("max_execution_time", "-1");
ini_set("memory_limit", "-1");
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
use local_udemysync\udemy_user_verification as udemy_user_verification;
global $DB, $CFG, $USER, $PAGE;
require_once($CFG->dirroot . '/local/udemysync/classes/udemy_user_verification.php');

$action = required_param('action', PARAM_ACTION);
$context = \local_costcenter\lib\accesslib::get_module_context();
require_login();
$PAGE->set_context($context);

switch ($action) {
    case 'udemyuserlicense':
        $verification = new \local_udemysync\udemy_user_verification($USER->email);
        $return = $verification->verify_userlicence(1);
        //$return = plugin::verify_userlicence($USER->email, 1);
        echo json_encode($return);
    break;
}
