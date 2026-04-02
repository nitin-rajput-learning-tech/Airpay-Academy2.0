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
require_once('lib.php');

class local_skillrepository_renderer extends plugin_renderer_base {

    /*
 *  @method display table for showing repositories
 *  @return skill repository table
 */
    public function display_table() {
        global $DB, $CFG, $OUTPUT,$USER, $PAGE;
        $repository = new local_skillrepository\event\insertrepository();

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        if(is_siteadmin()){
            $skill = $repository->skillrepository_opertaions('local_skill', 'fetch-multiple','','','');
        } else {
            $open_path=$DB->get_field('user','open_path',array('id'=>$USER->id));
            $costcenterid=explode('/',$open_path)[1];
            $object=1;
            $skill = $repository->skillrepository_opertaions('local_skill', 'fetch-multiple',$object,'costcenterid',$costcenterid);
        }
        // Create Table Format
        $table = new html_table();
        $table->id = 'skill_repository';
        $table->attributes['class'] = 'generaltable';
        $table->head = [get_string('skill_name', 'local_skillrepository'),
                        get_string('achievedusercount', 'local_skillrepository'),
                        get_string('shortname', 'local_skillrepository'),
                        get_string('category', 'local_skillrepository'),
                        get_string('actions')
                        ];
        $table->align = array('left' ,'left', 'left', 'center');
        if ($skill) {
            foreach ($skill as $c) {
                $id = $c->id;
                $usercountsql = "SELECT count(u.id)
                FROM {course} c
                JOIN {course_completions} cc
                on cc.course = c.id
                JOIN {user} u
                on cc.userid = u.id
                WHERE c.open_skill = {$id} and cc.timecompleted IS NOT NULL
                ";
                $usercount = $DB->count_records_sql($usercountsql);
                $actions = html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('t/edit'),'title' => get_string('edit'), 'data-action' => 'createrepositorymodal', 'class'=>'createrepositorymodal', 'data-value'=>$id, 'class' => 'iconsmall', 'onclick' =>'(function(e){ require("local_skillrepository/newrepository").init({selector:"createrepositorymodal", contextid:1, repositoryid:'.$c->id.'}) })(event)'));

                $deleteurl = "javascript:void(0)";
                $deleteiconurl = $OUTPUT->image_url('t/delete');
                $deleteicon = html_writer:: empty_tag('img', array('src'=>$deleteiconurl));
                $actions .= ' ';

                $actions .= html_writer:: link($deleteurl, $deleteicon, array('onclick' => '(function(e){ require("local_skillrepository/newrepository").deleteskill({selector:"deleteskill", contextid:1, skillid:'.$c->id.', name:"test"}) })(event)'));

                $skill_catname = $DB->get_field('local_skill_categories', 'name',array('id'=>$c->category));
                if($skill_catname){
                    $skill_catname = $skill_catname;
                }else{
                    $skill_catname = '---';
                }
                $skillurl = new moodle_url('/local/skillrepository/skillinfo.php', array('id'=>$c->id));
                $skilname = html_writer:: link($skillurl, $c->name, array());
                $table->data[] = [$skilname,$usercount, $c->shortname, $skill_catname, $actions];
            }
            $skillstable =  html_writer::table($table);
        } else
            $skillstable = '';
            return $skillstable;
    }


    //Using service.php showing data on index page instead of ajax datatables
    public function manageskills_content($filter = false){
        global $USER;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_skills','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_skillrepository_manageskills_view';
        $options['templateName']='local_skillrepository/skills_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_skills',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    // Building Popup Form
    /*
    *  @method popup_window_form to open popup
     */
    public function popup_window_form() {
        global $CFG;

        $popupform = '<div id="dialog_box" style="display:none;">
            <form autocomplete="off" name = "skillcategory" method="post" accept-charset="utf-8" id="popup_form" class="mform" >
                <fieldset class="clearfix collapsible" id="id_displayinfo">
                    <div class="fcontainer clearfix">
                        <div id="fitem_id_name" class="fitem required fitem_ftext ">
                            <div class="fitemtitle">
                                <label for="id_name">Name<img class="req" title="Required field" alt="Required field" src="'.$CFG->wwwroot.'/theme/image.php/lnt/core/1461248966/req"></label>
                            </div>
                            <div class="felement ftext">
                                <span id="id_error_name" class="error" tabindex="0" style="display:none;"> You must supply a value here.</span>
                                <input name="name" type="text" id="id_name">
                             </div>
                           </div>
                        <div id="fitem_id_shortname" class="fitem required fitem_ftext ">
                            <div class="fitemtitle">
                                <label for="id_shortname">Short Name<img class="req" title="Required field" alt="Required field" src="'.$CFG->wwwroot.'/theme/image.php/lnt/core/1461248966/req"> </label>
                            </div>
                            <div class="felement ftext">
                                <span id="id_error_shortname" class="error" tabindex="0" style="display:none;"> You must supply a value here.</span>
                                <input name="shortname" type="text" id="id_shortname">
                            </div>
                        </div>
                            <input id="cat" name="category" type="hidden" class="set_cat">
                            <input name="id" id="id" type="hidden">
                    </div>
                </fieldset>
                <fieldset class="hidden">
                    <div>
                        <div id="fgroup_id_buttonar" class="fitem fitem_actionbuttons fitem_fgroup">
                            <div class="felement fgroup">
                                <input name="submitbutton" value="Submit" type="button" id="id_submitbutton" onclick="addSkillCategory();">
                            </div>
                        </div>
                            <div class="fdescription required">There are required fields in this form marked <img alt="Required field" src="'.$CFG->wwwroot.'/theme/image.php/lnt/core/1461248966/req">.</div>
                    </div>
                </fieldset>
            </form>
        </div>';

        return $popupform;
    }


/*
 *  @method view_skill_categories to show skill categories
 *  @return skill categories table
 */
    public function view_skill_categories(){
        global $DB, $OUTPUT,$USER;

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        if(!is_siteadmin()){
        $concatsql = (new \local_skillrepository\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lsc.open_path');
            $skill_categories = $DB->get_records_sql("SELECT lsc.* from {local_skill_categories} AS lsc where 1 $concatsql order by lsc.id desc");
            $head = [];
        } else{
            $skill_categories = $DB->get_records_sql("SELECT lsc.*,lc.fullname as orginsationname from {local_skill_categories} AS lsc JOIN {local_costcenter} AS lc ON lc.id = lsc.costcenterid order by lsc.id desc");
            $isadmin = true;
            $head = [get_string('organisation', 'local_costcenter')];
        }
        if($skill_categories){
            $data = array();
            foreach($skill_categories as $skill_category){
                $row = array();
                if($isadmin){
                    $row[] = $skill_category->orginsationname;
                }
                $row[] = $skill_category->name;
                $row[] = $skill_category->shortname;
                $editurl = "javascript:void(0)";
                $editiconurl = $OUTPUT->image_url('t/editinline');
                $editicon = html_writer:: empty_tag('img', array('src'=>$editiconurl));
                $actions = html_writer:: link($editurl,  $editicon,  array('title'=>get_string('edit','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").init({selector:"createcategorymodal", contextid:'.$systemcontext->id.', categoryid:'.$skill_category->id.'}) })(event)'));

                $deleteurl = "javascript:void(0)";
                $deleteiconurl = $OUTPUT->image_url('i/trash');

                $deleteicon = html_writer:: empty_tag('img', array('src'=>$deleteiconurl));
                $actions .= ' ';

                $actions .= html_writer:: link($deleteurl, $deleteicon, array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/newcategory").deletecategory({selector:"deletecategory", contextid:'.$systemcontext->id.', categoryid:'.$skill_category->id.', name:"'.$skill_category->name.'"}) })(event)'));

                $row[] =  $actions;

                $data[] = $row;
            }

            $table = new html_table();
            $table->id = 'skill_categories';
            $table->width = '100%';
            $table->align = array('left','center','center','center');
            $head = array_merge($head, array(get_string('fullname'), get_string('shortname', 'local_skillrepository'), get_string('actions')));
            $table->head = $head;
            $table->data = $data;
            $skill_categoriesview =  html_writer::table($table);
        }else{
            $skill_categoriesview = html_writer::tag('div', get_string('nodataavailable', 'local_skillrepository'),array('class'=>'emptymsg'));
        }

        return $skill_categoriesview;
    }
/*
 *  @method get skill info
 *  @param [integer] $skillid
 *  @return skill data
 */
    public function get_skill_info($skillid){
        global $DB, $USER;
        $skillrecord = $DB->get_record('local_skill', array('id'=>$skillid));
        $subskill_category = $DB->get_record('local_skill_categories', array('id'=>$skillrecord->category));
        $parent_skill_category = $DB->get_record('local_skill_categories', array('id'=>$subskill_category->parentid));
        $skilldata = '';
        $skilldata .= html_writer:: tag('h2', $skillrecord->name);
        $skilldata .= '<div class="skill_addinfo">';
        $skilldata .= html_writer:: start_tag('table', array('id'=>'skilldetails'));
        $skilldata .= html_writer:: start_tag('tr', array());
        $skilldata .= html_writer:: tag('td', get_string('fullname').': <b>'.$skillrecord->name.'</b>', array());
        $skilldata .= html_writer:: tag('td', get_string('shortname').': <b>'.$skillrecord->shortname.'</b>', array());
        $skilldata .= html_writer:: end_tag('tr');
        $skilldata .= html_writer:: start_tag('tr', array());
        $skilldata .= html_writer:: tag('td', get_string('category').': <b>'.$subskill_category->name.'</b>', array());
        $skilldata .= html_writer:: end_tag('tr');
        $skilldata .= html_writer:: end_tag('table');
        if(!empty($skillrecord->description)){
            $skilldata .= html_writer:: tag('div', $skillrecord->description, array('class'=>'skill_descr'));
        }
        $skilldata .= '</div>';

        $sql = "SELECT c.id, c.fullname, c.open_skill from
                {course} c
                where c.open_coursetype = 0 AND c.open_skill = $skillid";

        $skill_courses = $DB->get_records_sql($sql);

        if($skill_courses){
            $sk_courses = array();
            foreach($skill_courses as $skill_course){
                $sk_courses[] = $skill_course->fullname;
            }
            $skill_courses = implode(', ', $sk_courses);
        }else{
            $skill_courses = "<span class = 'noskillcoursesmsg'>".get_string('nodata', 'local_skillrepository')."</span>";
        }
        $skilldata .= "<div class='skillcourses'><b>".get_string('skill_courses', 'local_skillrepository').': </b>'.$skill_courses."</div>";
        $userpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');
        $sql = "SELECT u.id, u.open_employeeid, c.fullname, u.firstname, u.lastname, cc.timecompleted
            FROM {course} c
            JOIN {course_completions} cc
            on cc.course = c.id
            JOIN {user} u
            on cc.userid = u.id
            WHERE c.open_skill = $skillid and cc.timecompleted IS NOT NULL $userpathconcatsql $costcenterpathconcatsql";

        $skill_compl_courses = $DB->get_records_sql($sql);

        $skilldata .= html_writer::tag('h4', get_string('achievedusercount', 'local_skillrepository'));

        $data = array();
        if($skill_compl_courses){
            foreach($skill_compl_courses as $skill_compl_course){
                $row = array();
                $row[] = $skill_compl_course->firstname.' '.$skill_compl_course->lastname;
                $row[] = $skill_compl_course->open_employeeid;
                $row[] = $skill_compl_course->fullname;
                $completeddate = date('d M Y', $skill_compl_course->timecompleted);
                $row[] = $completeddate;
                $data[] = $row;
            }
            $table = new html_table();
            $table->id = 'additionalinfo';
            $table->head = array(get_string('employeename', 'local_skillrepository'), get_string('employeeid', 'local_skillrepository'), get_string('course'), 'Date Acquired');
            $table->data = $data;
            $skilldata .= html_writer::table($table);
        }else{
            $skilldata .= html_writer::tag('div', get_string('norecords', 'local_skillrepository'),array('class'=>'emptymsg'));
        }

        return $skilldata;
    }
    public function get_top_action_buttons_skills(){
        global $CFG;

        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $data =  "<ul class='course_extended_menu_list'>
                <li>
                    <div class='coursebackup course_extended_menu_itemcontainer'>
                          <a id='extended_menu_syncstats' title='".get_string('adnewrepository', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_skillrepository/newrepository\").init({selector:\"createrepositorymodal\", contextid:$systemcontext->id, repositoryid:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true' aria-label=''></i>
                          </a>
                      </div>
                </li>
                <li>
                    <div class='coursebackup course_extended_menu_itemcontainer'>
                        <a id='extended_menu_syncerrors' title='".get_string('managelevel', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot ."/local/skillrepository/level.php'><i class='icon fa fa-users fa-fw' aria-hidden='true' aria-label=''></i>
                        </a>
                    </div>
                </li>
                <li>
                    <div class='coursebackup course_extended_menu_itemcontainer'>
                        <a id='extended_menu_syncusers' title='".get_string('manage_skill_category', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot ."/local/skillrepository/skill_category.php'><i class='icon fa fa-cubes' aria-hidden='true' aria-label=''></i>
                        </a>
                    </div>
                </li>
            </ul>";
        return $data;
    }
    //Levels related functions
    public function display_levels_tablestructure(){
        $table = new \html_table();
        $table->id = 'all_levels_display_table';
        if(is_siteadmin()){
            $head = [get_string('organisation', 'local_costcenter')];
        }else{
            $head = [];
        }
        $table->head = array_merge($head, array(get_string('levelname', 'local_skillrepository'),get_string('levelcode', 'local_skillrepository'), get_string('createdby', 'local_skillrepository'),get_string('actions')));
        $table = \html_writer::table($table);
        return $table;
    }
    public function display_levels_tabledata($params){
        global $CFG;
        $querylib = new \local_skillrepository\local\querylib();
        $displaydata = $querylib->get_table_contents($params);
        $tabledata = array();
        foreach($displaydata as $level){
            $actions = '';
            $canedit = $querylib->can_edit_level($level->id);
            if($canedit){
                $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
                $editicon = "<i class='fa fa-pencil fa-fw'></i>";
                $actions .= \html_writer::link('javascript:void(0)', $editicon, array('title'=>get_string('edit','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/leveltable").init({ contextid:'.$systemcontext->id.',levelid: '.$level->id.', levelname: "'.$level->name.'"}) })(event)'));
            }
            $candelete = $querylib->can_delete_level($level->id);
            if($candelete){
                $deleteicon ="<i class='fa fa-trash fa-fw'></i>";
                $actions .= \html_writer::link('javascript:void(0)', $deleteicon, array('title'=>get_string('delete','local_skillrepository'),'onclick' => '(function(e){ require("local_skillrepository/leveltable").deletelevel({levelid: '.$level->id.', levelname: "'.$level->name.'"}) })(event)'));
            }

            $data = array();
            if(is_siteadmin()){
                $data[] = $level->organisationname;
            }
            $data[] = $level->name;
            $data[] = $level->code;
            $data[] = $level->username;
            $data[] = $actions;
            $tabledata[] = $data;
         }
         return $tabledata;
    }

    public function manageskillslevel_content($filter = false){
        global $USER;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_skills_level','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_skillrepository_manageskillslevel_view';
        $options['templateName']='local_skillrepository/skills_level_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_skills_level',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    public function manageskillscategory_content($filter = false){
        global $USER;
        $systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_skills_category','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_skillrepository_manageskillscategory_view';
        $options['templateName']='local_skillrepository/skills_cat_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_skills_category',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

}
