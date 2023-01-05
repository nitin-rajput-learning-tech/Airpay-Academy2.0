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
 * @subpackage blocks_announcement
 */
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
$type = required_param('reason', PARAM_TEXT);
global $DB, $PAGE,$USER,$CFG,$OUTPUT;

$systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
switch($type){
	case 'delete_announcement_modal':
		$id = required_param('id', PARAM_INT);
		$delid = $DB->delete_records('block_announcement', array('id' => $id));
		echo json_encode($delid);
	break;
	case 'change_status_announcement_modal':
		$visible = required_param('visible',PARAM_INT);
		$id = required_param('id',PARAM_INT);
		$dataobject = new \stdClass();
		$dataobject->id = $id;
		$dataobject->visible = $visible;
		$updated = $DB->update_record('block_announcement', $dataobject);
		echo json_encode($updated);
	break;
}