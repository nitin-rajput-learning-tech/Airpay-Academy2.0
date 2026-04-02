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
 * @subpackage local_custom_category
 */
define('AJAX_SCRIPT', true);
global $PAGE, $USER, $CFG;
require_once(dirname(__FILE__).'/../../config.php');
require_once('lib.php');
$action = required_param('action', PARAM_TEXT);

require_login();
$PAGE->set_url('/local/skillrepository/ajax.php');
$PAGE->set_context((new \local_custom_category\lib\accesslib())::get_module_context());
$PAGE->set_pagelayout('standard');


switch($action) {
    case 'deletecustomcategory':
        $customcategoryid = required_param('skillid', PARAM_INT);
        $return = $DB->delete_records('local_custom_category',  array('id' => $customcategoryid));

    break;
}
echo json_encode($return);