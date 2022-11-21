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
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG;
require_once($CFG->dirroot . '/lib/moodlelib.php');
/*
 *  @method Create Array Format
 *  @param string $table Database Table Name
 *  @param string $column Database Table Column Name for KEY
 *  @param string $value Database Table Column Name for VALUE
 *  @return array $array contains KEY AND VALUE
 */
function create_array($table, $key, $value) {
    global $DB;
    $data = $DB->get_records('local_skill_' . $table);
    $array[NULL] = '--SELECT--';
    foreach ($data as $d) {
        $array[$d->$key] = $d->$value;
    }
    return $array;
}

/*
 *  @method Database Table Columns List
 *  @param string $table Database Table Name
 *  @return array $columnnames contains KEY AND VALUE
 */
function getTableColumns($table){
	global $DB;

	$tables = $DB->get_tables();
	$currenttable = $tables[$table];

	$columns = $DB->get_columns($tables[$currenttable]);
	    foreach ($columns as $column) {
			$columnnames[$column->name] = $column->name;
		}

	return $columnnames;
}


/*
 *  @method output fragment
 *  @param $args
 *  @return array $args contains KEY AND VALUE
 */
function local_skillrepository_output_fragment_new_skill_repository_form($args){
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
        $heading = 'Update repository';
        $collapse = false;
        $data = $DB->get_record('local_skill', array('id'=>$repositoryid));
        $description=$data->description;
        $data->description=array();
        $data->description['text'] = $description;
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $context,
        'noclean' => false,
        'subdirs' => false,
        'autosave' => false
    ];
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);

    $mform = new local_skillrepository\form\skill_repository_form(null, array('id' => $args->repositoryid, 'editoroptions' => $editoroptions), 'post', '', null, true, $formdata);

    //print_object($data);
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

function local_skillrepository_output_fragment_skill_category_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $categoryid = $args->categoryid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $mform = new local_skillrepository\form\skill_category_form(null, array('id' => $args->categoryid), 'post', '', null, true, $formdata);
    if ($categoryid > 0) {
        $data = $DB->get_record('local_skill_categories', array('id'=>$categoryid));
        $mform->set_data($data);
    }
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
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_skillrepository_leftmenunode(){

    $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
    $skillreponode = '';
    if(has_capability('local/costcenter:manage', $systemcontext) || is_siteadmin()) {
        $skillreponode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_skills', 'class'=>'pull-left user_nav_div skills'));
            $skills_url = new moodle_url('/local/skillrepository/index.php');
            $skill_icon = '<i class="fa fa-hourglass-half" aria-hidden="true"></i>';
            $courses = html_writer::link($skills_url, $skill_icon.'<span class="user_navigation_link_text">'.get_string('manage_skills','local_skillrepository').'</span>',array('class'=>'user_navigation_link'));
            $skillreponode .= $courses;
        $skillreponode .= html_writer::end_tag('li');
    }

    return array('18' => $skillreponode);
}

//Level related functions

function local_skillrepository_output_fragment_level_form($args){
    global $CFG,$DB;
    $args = (object) $args;
    $context = $args->context;
    $levelid = $args->levelid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $mform = new \local_skillrepository\form\levelsform(null, array('id' => $args->levelid), 'post', '', null, true, $formdata);
    if ($levelid > 0) {
        $data = $DB->get_record('local_course_levels', array('id'=>$levelid));
        $mform->set_data($data);
    }
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
function skill_details($tablelimits, $filtervalues){
        global $DB, $PAGE,$USER,$CFG,$OUTPUT;

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $countsql = "SELECT count(sk.id) FROM {local_skill} AS sk WHERE 1=1 ";
        $selectsql = "SELECT sk.*, lc.fullname as organisationname, lsc.name AS skill_catname
            FROM {local_skill} AS sk
            JOIN {local_costcenter} AS lc ON lc.id = sk.costcenterid
            JOIN {local_skill_categories} AS lsc ON lsc.id = sk.category
            WHERE 1=1 ";
        $queryparam = array();

        if(!is_siteadmin()){
            $costcenterid=$DB->get_field('user','open_costcenterid',array('id'=>$USER->id));
            $concatsql .= " AND sk.costcenterid= :usercostcenter ";
            $queryparam['usercostcenter'] = $costcenterid;
        }
        $count = $DB->count_records_sql($countsql.$concatsql, $queryparam);

        $concatsql.=" order by sk.id desc";
        $records = $DB->get_records_sql($selectsql.$concatsql, $queryparam, $tablelimits->start, $tablelimits->length);

        $list=array();
        $data=array();
        if ($records) {
            foreach ($records as $c) {

                $list=array();
                $id = $c->id;
                $usercountsql = "SELECT count(DISTINCT(u.id))
                    FROM {course} c
                    JOIN {course_completions} cc
                    on cc.course = c.id
                    JOIN {user} u
                    on cc.userid = u.id
                    WHERE c.open_skill = {$id} and cc.timecompleted IS NOT NULL ";
                $usercount = $DB->count_records_sql($usercountsql);

                // $skill_catname = $DB->get_field('local_skill_categories', 'name',array('id'=>$c->category));
                // if($skill_catname){
                //     $skill_catname = $skill_catname;
                // }else{
                //     $skill_catname = '---';
                // }

                /*$skillurl = new moodle_url('/local/skillrepository/skillinfo.php', array('id'=>$c->id));
                $skilname = html_writer:: link($skillurl, $c->name, array());*/
               $skilname=$c->name;
               $list['skilname'] = $skilname;
               $list['organisationname'] = $c->organisationname;
               $list['skill_id'] = $c->id;
               $list['achieved_users'] = $usercount;
               $list['shortname']=$c->shortname;
               $list['skill_catname']=$c->skill_catname;
               $data[] = $list;
            }
        }

        return array('count' => $count, 'data' => $data);
}



