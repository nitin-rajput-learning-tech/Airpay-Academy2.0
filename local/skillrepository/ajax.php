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
 * @subpackage local_skillrepository
 */
define('AJAX_SCRIPT', true);
global $PAGE, $USER, $CFG;
require_once(dirname(__FILE__).'/../../config.php');
require_once('lib.php');
$action = required_param('action', PARAM_TEXT);
$id = optional_param('id', 0, PARAM_INT);
 
$name = optional_param('name', '', PARAM_TEXT);
$shortname = optional_param('shortname', '', PARAM_TEXT);
$table = optional_param('table', '', PARAM_RAW);
$dataid = optional_param('dataid', 0, PARAM_INT);
$q = optional_param('q', '', PARAM_RAW);

require_login();
$PAGE->set_url('/local/skillrepository/ajax.php');
$PAGE->set_context((new \local_skillrepository\lib\accesslib())::get_module_context());
$PAGE->set_pagelayout('standard');

$record = new stdClass();
$record->name = $name;
$record->shortname = $shortname;
$repository = new local_skillrepository\event\insertrepository();
$querylib = new \local_skillrepository\local\querylib();
$renderer = $PAGE->get_renderer('local_skillrepository');
switch($action) {
    case 'insert':
        // Checking If Records Already Exists
        $shortnameexist = $repository->skillrepository_opertaions("local_skill_".$table, 'exist', '', 'shortname', $shortname);

        if($shortnameexist){
        $return = "SHORTNAME";
        } else {
            if($id <= 0) {
                $create = $repository->skillrepository_opertaions("local_skill_".$table, 'insert', $record);
                $return = $create;
            }
        }
    break;

    case 'edit':
        $edit = $repository->skillrepository_opertaions("local_skill_".$table, 'fetch-single', '', 'id', $dataid);
        $return = ['data' => ['id'=>$edit->id, 'name' => $edit->name, 'shortname' => $edit->shortname]];
    break;

    case 'update':
        $record->id = $id;
        $update = $repository->skillrepository_opertaions("local_skill_".$table, 'update', $record, 'id', $id);
        $return = $update;
    break;
    case 'search':
        $sql = 'SELECT * FROM {local_skill_'.$table.'} WHERE name LIKE "'.$q.'%"';
        $data = $DB->get_records_sql($sql);
        foreach($data as $d){
            $array[] = ['names'=>$d->name, 'id'=>$d->shortname];
        }
        $terms_data = array();
        $terms_data['total_count']= sizeof($array);
        $terms_data['incomplete_results'] = false;
        $terms_data['items'] = $array;
        $return = $terms_data;
    break;
    case 'deletecategory':
 
        $categoryid = required_param('categoryid', PARAM_INT);
        $return = $DB->delete_records('local_skill_categories',  array('id' => $categoryid));
        // echo json_encode($return);
    break;

    case 'getlevelstable':
        $params = new \stdClass();
        $requestdata            = $_REQUEST;
        $params->perpage        = $requestdata['iDisplayLength'];
        $params->recordsperpage = $requestdata['iDisplayStart'];
        $params->search         = $requestdata['sSearch'];

        $data = $renderer->display_levels_tabledata($params);
        $total = $querylib->get_total_levels_count($params);
        $output = array(
            "sEcho" => intval($requestdata['sEcho']),
            "iTotalRecords" => $total,
            "iTotalDisplayRecords" => $total,
            "aaData" => $data,
        );
        $return = $output;
    break;
    case 'deletelevel':
        $levelid = required_param('levelid', PARAM_INT);
        $deleted = $querylib->delete_level($levelid);
        $return = $deleted;
        $curenttime = time();
        $result = $DB->execute('UPDATE {course} SET open_level = 0, timemodified ='.$curenttime.'  WHERE open_level = '.$levelid);
    break;
    case 'deleteskill':
        $skillid = required_param('skillid', PARAM_INT);
        $curenttime = time();
        $return = $DB->delete_records('local_skill',  array('id' => $skillid));
        $result = $DB->execute('UPDATE {course} SET open_skill = 0, timemodified ='.$curenttime.'  WHERE open_skill = '.$skillid);
    break;
}
echo json_encode($return);