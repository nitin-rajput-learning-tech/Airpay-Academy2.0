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
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
* @author eabyas <info@eabyas.in>
* @package BizLMS
* @subpackage block_myskills
*/

define('AJAX_SCRIPT', true);
require(__DIR__.'/../../config.php');
$action = required_param('action', PARAM_ALPHA);
$renderer = new \block_myskills\output\renderer();
switch($action){
	case 'getskillsacquired':
		$params = new \stdClass();
		$requestdata            = $_REQUEST;
        $params->perpage        = $requestdata['iDisplayLength'];
        $params->recordsperpage = $requestdata['iDisplayStart'];
        $params->search         = $requestdata['sSearch'];

        $data = $renderer->display_skill_acquired_data($params);
        $total = $renderer->get_total_skills_count($params);

        $output                 = array(
            "sEcho" => intval($requestdata['sEcho']),
            "iTotalRecords" => $total,
            "iTotalDisplayRecords" => $total,
            "aaData" => $data,
        );
        echo json_encode($output);
    break;
}