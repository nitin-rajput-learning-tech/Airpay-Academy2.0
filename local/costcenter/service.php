<?php
/*
* This file is a part of e abyas Info Solutions.
*
* Copyright e abyas Info Solutions Pvt Ltd, India.
*
* This course_sync is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
*
* This course_sync is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this course_sync.  If not, see <http://www.gnu.org/licenses/>.
*
* @author e abyas  <info@eabyas.com>
*/
/**
 * Defines browse course_syncs
 *
 * @package    local_license
 * @copyright  2021 e abyas  <info@eabyas.com>
 */
//define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../config.php');

global $DB, $CFG, $USER, $PAGE;

require_once($CFG->libdir . '/externallib.php');


define('PREFERRED_RENDERER_TARGET', RENDERER_TARGET_GENERAL);

// $rawjson = file_get_contents('php://input');
// if (!$rawjson) {
//     $lasterror = json_last_error_msg();
//     throw new coding_exception('Invalid json in request: ' . $lasterror);
// }


// $args=array('status'=>'inprogress','searchterm' => "", 'page' => 0, 'perpage' => 15, 'source' => 'mobile');

 $args=array('userid' => 12);

print_r($args);

$tabmethodname="core_block_get_dashboard_blocks";

echo $tabmethodname.'';

$response = external_api::call_external_function($tabmethodname, $args, true);

print_r($response);


$return = array(
               "recordsTotal" => $response['data']['totalcount'],
               "recordsFiltered" => $response['data']['totalcount'],
               "data" => $response['data']['records']
        );
echo json_encode($return);

