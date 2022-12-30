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
 * @subpackage local_customform
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG;
require_once($CFG->dirroot . '/lib/moodlelib.php');

function local_custom_category_output_fragment_new_custom_category_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $repositoryid = $args->repositoryid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    if ($args->repositoryid > 0) {
        $heading = get_string('updatecuscategory', 'local_custom_category');
        $data = $DB->get_record('local_custom_category', array('id'=>$repositoryid));
    }

    $mform = new local_custom_category\form\custom_category_form(null, array('id' => $args->repositoryid, 'editoroptions' => $editoroptions, 'open_costcenterid' => $data->costcenterid, 'parentid' => $data->parentid), 'post', '', null, true, $formdata);

    $data->name = $data->fullname;
    $data->open_costcenterid = $data->costcenterid;
    $mform->set_data($data);

    if (!empty($formdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}

//////For display on index page//////////
function custom_category_details($tablelimits, $filtervalues){
    global $DB, $PAGE,$USER,$CFG,$OUTPUT;
    $systemcontext =(new \local_custom_category\lib\accesslib())::get_module_context();
    $countsql = "SELECT count(lcc.id) FROM {local_custom_category} AS lcc WHERE 1=1 ";
    $selectsql = "SELECT lcc.*, lc.fullname as organisationname
        FROM {local_custom_category} AS lcc
        JOIN {local_costcenter} AS lc ON lc.id = lcc.costcenterid
        WHERE 1=1 ";
    $queryparam = array();

    if(!is_siteadmin()){
        $costctrid=$DB->get_field('user','open_path',array('id'=>$USER->id));
        $costcenterid = explode("/",$costctrid);
        $concatsql .= " AND lcc.costcenterid= :usercostcenter ";
        $queryparam['usercostcenter'] = $costcenterid[1];

    }
    if (isset($filtervalues->search_query) && trim($filtervalues->search_query) != '') {
        $concatsql .= " AND (lcc.fullname LIKE :search1 )";
        $queryparam['search1'] = '%'.trim($filtervalues->search_query).'%';
    }
    $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);
    $concatsql.=" order by lcc.id desc";
    $records = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);

    $list=array();
    $data=array();
    if ($records) {
        foreach ($records as $c) {
            $list=array();
            $id = $c->id;
            $parent = $DB->get_field('local_custom_category', 'fullname', array('id' => $c->parentid));
            $list['custom_category_name'] = $c->fullname;
            $list['organisationname'] = $c->organisationname;
            $list['custom_category_id'] = $c->id;
            $list['shortname']=$c->shortname;
            $list['parent']=$parent ? $parent : 'N/A';
            $data[] = $list;
        }
    }
    return array('count' => $count, 'data' => $data);
}
