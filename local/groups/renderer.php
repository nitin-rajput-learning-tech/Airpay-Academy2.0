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
 * @subpackage local_groups
 */

class local_groups_renderer extends plugin_renderer_base  {
    
    protected function render_local_groups(local_groups $renderable) {
       /*return $this->display($renderable->context, $renderable->groups,$renderable->showall,$renderable->searchquery,$renderable->page);*/
        return $this->show($renderable->context, $renderable->groups,$renderable->showall,$renderable->searchquery,$renderable->page);
     }  

 public function managegroups_content($filter = false, $view_type = 'card'){
        global $USER;

        $systemcontext =  (new \local_groups\lib\accesslib())::get_module_context();
        $stable = new stdClass();
        $stable->thead = true;
        $stable->start = 0;
        $stable->length = -1;
        $stable->search = '';
        $stable->pagetype =get_string('renderepagetype','local_groups');

        $templateName = 'local_groups/groupstab';
        $cardClass = 'col-md-4 col-sm-6 col-12 card_main';
        $perpage = 12;
        if($view_type=='table'){
            $templateName = 'local_groups/groups_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
        } 

        $options = array('targetID' => 'manage_groups','perPage' => $perpage,'cardClass' => $cardClass, 'viewType' => $view_type);
        
        $options['methodName']='local_groups_managegroups_view';
        $options['templateName']= $templateName; 
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $systemcontext->id));
       
        $context = [
                'targetID' => 'manage_groups',
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

     public function get_group_btns() {
        global $PAGE, $USER, $DB;
        $systemcontext =  (new \local_groups\lib\accesslib())::get_module_context();
        if ((is_siteadmin() || has_capability('moodle/cohort:manage', $systemcontext)) && 
            $PAGE->pagetype == 'local-groups-index'){
            $createdeptpopup = "<a class='course_extended_menu_itemlink' data-action='createcostcentermodal' data-value='0' title = '".get_string('create_group', 'local_groups')."' onclick ='(function(e){ require(\"local_groups/newgroup\").init({contextid:$systemcontext->id, groupsid:0}) })(event)' ><span class='createicon'><i class='fa fa-users icon' aria-hidden='true'></i><i class='createiconchild fa fa-plus' aria-hidden='true'></i></span></a>";
        } else {
            $createdeptpopup = '';
        }
        $buttons = [
            "createdeptpopup" => $createdeptpopup
        ];
        return $this->render_from_template('local_groups/viewbuttons', $buttons);
    }

    public function display($context, $groups, $showall, $searchquery, $page) {
        global $DB, $OUTPUT, $PAGE, $CFG, $USER;
        $output = '';
        $data = array();
        $editcolumnisempty = true;
        $params = array('page' => $page);
        if ($context->id) {
            $params['contextid'] = $context->id;
        }
        if ($searchquery) {
            $params['search'] = $searchquery;
        }
        if ($showall) {
            $params['showall'] = true;
        }
        $baseurl = new moodle_url('/local/groups/index.php', $params);
        foreach($groups['groups'] as $cohort) {
            $line = array();
            $urlparams = array('id' => $cohort->id, 'returnurl' => $baseurl->out_as_local_url());
            $cohortcontext =  (new \local_groups\lib\accesslib())::get_module_context();
           
           // $cohortcontext = context::instance_by_id($cohort->contextid);
            $cohort->description = file_rewrite_pluginfile_urls($cohort->description, 'pluginfile.php', $cohortcontext->id,
                    'cohort', 'description', $cohort->id);
            if ($showall) {
                if ($cohortcontext->contextlevel == CONTEXT_COURSECAT) {
                    $line[] = html_writer::link(new moodle_url('/cohort/index.php' ,
                            array('contextid' => $cohort->contextid)), $cohortcontext->get_context_name(false));
                } else {
                    $line[] = $cohortcontext->get_context_name(false);
                }
            }
            $tmpl = new local_groups\output\cohortname($cohort);
            $line[] = $OUTPUT->render_from_template('core/inplace_editable', $tmpl->export_for_template($OUTPUT));
            $tmpl = new local_groups\output\cohortidnumber($cohort);
            $line[] = $OUTPUT->render_from_template('core/inplace_editable', $tmpl->export_for_template($OUTPUT));
            $description = format_text($cohort->description, $cohort->descriptionformat);
            $descriptionstring = strlen($description) > 50 ? clean_text(substr($description, 0, 50))."..." : $description;
            $descriptiontitle = $description;
            $tooltip=\local_costcenter\lib::strip_tags_custom($descriptiontitle);
            $line[] = '<span title="'.$tooltip.'">'.$descriptionstring.'</span>';
            $group_members_count = $DB->count_records('cohort_members', array('cohortid'=>$cohort->id));
            $line[] = html_writer::link(new moodle_url('/local/groups/assign.php', $urlparams),
                        $group_members_count,
                        array('title' => get_string('assign', 'core_cohort')));
                    $editcolumnisempty = false;
            if (empty($cohort->component)) {
                $line[] = get_string('nocomponent', 'cohort');
            } else {
                $line[] = get_string('pluginname', $cohort->component);
            }
        
            $buttons = array();
            if (empty($cohort->component)) {
                $cohortmanager = has_capability('moodle/cohort:manage', $cohortcontext);
                $cohortcanassign = has_capability('moodle/cohort:assign', $cohortcontext);
        
                
                $showhideurl = new moodle_url('/local/groups/edit.php', $urlparams + array('sesskey' => sesskey()));
                if ($cohortmanager) {
                    $buttons[] = html_writer::link(new moodle_url('/local/groups/edit.php', $urlparams),
                        $OUTPUT->pix_icon('t/edit', get_string('edit')),
                        array('title' => get_string('edit')));



                    $editcolumnisempty = false;
                    if ($cohort->visible) {
                        $showhideurl->param('hide', 1);
                        $visibleimg = $OUTPUT->pix_icon('t/inactive', get_string('inactive'));
                       $buttons[]= html_writer::link(
                        "javascript:void(0)",
                        $OUTPUT->pix_icon('t/hide', get_string('hidecohort','local_groups'), 'moodle', array('title' => '')),
                        array('id' => 'confirmhide' . $cohort->id . '', 'onclick' => '(
                              function(e){
                        require("local_groups/renderselections").hideshow_cohort(' . $cohort->id . ', "' . $cohort->name . '")
                        })(event)'));
                    } else {
                        $showhideurl->param('show', 1);
                        $visibleimg = $OUTPUT->pix_icon('t/active', get_string('active'));
                       $buttons[] = html_writer::link(
                        "javascript:void(0)",
                        $OUTPUT->pix_icon('t/show', get_string('showcohort','local_groups'), 'moodle', array('title' => '')),
                        array('id' => 'confirmshow' . $cohort->id . '', 'onclick' => '(
                              function(e){
                        require("local_groups/renderselections").showhide_cohort(' . $cohort->id . ', "' . $cohort->name . '")
                        })(event)'));
                    }                    
                }



                if ($cohortcanassign) {
                    $buttons[] = html_writer::link(new moodle_url('/local/groups/assign.php', $urlparams),
                        $OUTPUT->pix_icon('i/enrolusers', get_string('assign', 'core_cohort')),
                        array('title' => get_string('assign', 'core_cohort')));
                    $editcolumnisempty = false;



                    $buttons[] = html_writer::link(new moodle_url('/local/groups/mass_enroll.php', $urlparams),
                        $OUTPUT->pix_icon('i/users', get_string('bulk_enroll', 'local_groups')),
                        array('title' => get_string('bulk_enroll', 'local_groups')));
                }



                if ($cohortmanager)
                $buttons[] = html_writer::link(
                                "javascript:void(0)",
                                $OUTPUT->pix_icon('i/delete', get_string('delete'), 'moodle', array('title' => '')),
                                array('id' => 'deleteconfirm' . $cohort->id . '', 'onclick' => '(
                                      function(e){
                        require("local_groups/renderselections").deletecohort(' . $cohort->id . ', "' . $cohort->name . '")
                        })(event)'));
            }



            $line[] = implode(' ', $buttons);
        
            $data[] = $row = new html_table_row($line);
            if (!$cohort->visible) {
                $row->attributes['class'] = 'dimmed_text';
            }
        }
        $table = new html_table();
        $table->head  = array(get_string('name', 'local_groups'), get_string('idnumber', 'local_groups'), get_string('description', 'local_groups'),
                              get_string('memberscount', 'local_groups'), get_string('component', 'local_groups'));
        $table->colclasses = array('leftalign name', 'leftalign id', 'leftalign description', 'leftalign size','centeralign source');
        if ($showall) {
            array_unshift($table->head, get_string('category'));
            array_unshift($table->colclasses, 'leftalign category');
        }
        if (!$editcolumnisempty) {
            $table->head[] = get_string('edit','local_groups');
            $table->colclasses[] = get_string('rendercenteralignaction','local_groups');
        } else {
            // Remove last column from $data.
            foreach ($data as $row) {
                array_pop($row->cells);
            }
        }
        $table->id = get_string('admintableid','local_groups');
        $table->attributes['class'] = get_string('admintablegeneraltable','local_groups');
        $table->data  = $data;
        $output .= html_writer::table($table);
        if(!empty($data)){
            $result = $output;
        } else {
            $result = '<div class="alert alert-info text-center">'.get_string('nogroupstoshow','local_groups').'</div>';
        }
        return $result;
    }

    public function show($context, $groups, $showall, $searchquery, $page){
        global $DB, $OUTPUT, $PAGE, $CFG, $USER;
        $output = '';
        $data = array();
        $systemcontext =  (new \local_groups\lib\accesslib())::get_module_context();
        $editcolumnisempty = true;
        $params = array('page' => $page);
        if ($context->id) {
            $params['contextid'] = $context->id;
        }
        if ($searchquery) {
            $params['search'] = $searchquery->search_query;
        }
        if ($showall) {
            $params['showall'] = true;
        }
        $baseurl = new moodle_url('/local/groups/index.php', $params);
        $cohorts = $groups['groups'];
               
           $row = [];
        foreach($cohorts as $cohort) {
                $line = array();
                $groupname = $cohort->name;
                if(strlen($groupname) >15){
                     $groupname = substr($groupname, 0,15).'...';
                }

                $groupid  =$cohort->idnumber;
                $visible = $cohort->visible;
                if(strlen($groupid) >8){
                     $groupid = clean_text(substr($groupid, 0,8)).'...';
                }
                $orgname = $DB->get_field_sql("SELECT cc.fullname FROM {local_costcenter} AS cc 
                JOIN {local_groups} AS lg ON  concat('/',lg.open_path,'/') LIKE concat('%/',cc.id,'/%') AND cc.depth = 1 WHERE lg.cohortid=".$cohort->id);
        $cohortcontext =  (new \local_groups\lib\accesslib())::get_module_context();
                
             //   $cohortcontext = context::instance_by_id($cohort->contextid);
                $urlparams = array('id' => $cohort->id, 'returnurl' => $baseurl->out_as_local_url());
                $group_members_count = $DB->count_records('cohort_members', array('cohortid'=>$cohort->id));
                $line['groupname'] = \local_costcenter\lib::strip_tags_custom(html_entity_decode($groupname));
                $line['groupid'] = \local_costcenter\lib::strip_tags_custom(html_entity_decode($groupid));               
                $line['orgname'] = $orgname;
                if($visible==1){
                $line['visible'] = $visible;}
                $buttons = array();
                if (empty($cohort->component)) {
                    $cohortmanager = has_capability('moodle/cohort:manage', $cohortcontext);
                    $cohortcanassign = has_capability('moodle/cohort:assign', $cohortcontext);
            
                    
                    $showhideurl = new moodle_url('/local/groups/edit.php', $urlparams + array('sesskey' => sesskey()));
                    if ($cohortmanager) {
                      
                        $buttons[] = html_writer::link('javascript:void(0)',
                            $OUTPUT->pix_icon('t/editinline', get_string('edit')) .html_writer::tag('span',get_string('edit'),array('class'=>'hidestrings')),
                            array('class'=>'dropdown-item','title' => get_string('edit'), 'onclick' => '(function(e){ require("local_groups/newgroup").init({contextid:'.$systemcontext->id.', groupsid:'.$cohort->id.'}) })(event)'));

                        $editcolumnisempty = false;
                        if ($cohort->visible) {
                            $showhideurl->param('hide', 1);
                            $visibleimg = $OUTPUT->pix_icon('t/hide', get_string('inactive'));

                            
                               $buttons[]= html_writer::link(
                                "javascript:void(0)",
                                $OUTPUT->pix_icon('t/hide', get_string('hidecohort','local_groups'), 'moodle',array('title' => '')).html_writer::tag('span',get_string('hidecohort','local_groups'),array('class'=>'hidestrings')),
                                array('class'=>'dropdown-item' ,'id' => 'confirmhide' . $cohort->id . '', 'onclick' => '(
                                      function(e){
                                require("local_groups/renderselections").hideshow_cohort(' . $cohort->id . ', "' . $cohort->name . '")
                                })(event)'));
                               
                        } else {
                            $showhideurl->param('show', 1);
                            $visibleimg = $OUTPUT->pix_icon('t/show', get_string('active'));
                             
                            $buttons[]= html_writer::link(
                                "javascript:void(0)",
                                $OUTPUT->pix_icon('t/show', get_string('showcohort','local_groups'), 'moodle', array('title' => '')).html_writer::tag('span',get_string('showcohort','local_groups'),array('class'=>'hidestrings')),
                                array('class'=>'dropdown-item','id' => 'confirmshow' . $cohort->id . '', 'onclick' => '(
                                      function(e){
                                require("local_groups/renderselections").showhide_cohort(' . $cohort->id . ', "' . $cohort->name . '")
                                })(event)'));
                            
                        }
                    }

                    if ($cohortcanassign) { 
                           $buttons[] = html_writer::link(new moodle_url('/local/groups/assign.php', $urlparams),
                            $OUTPUT->pix_icon('i/enrolusers', get_string('assign', 'core_cohort')) .html_writer::tag('span',get_string('assign', 'core_cohort'),array('class'=>'hidestrings')),
                            array( 'class'=>'dropdown-item','title' => get_string('assign', 'core_cohort')));
                        $editcolumnisempty = false;
                           $buttons[] = html_writer::link(new moodle_url('/local/groups/mass_enroll.php', $urlparams),
                            $OUTPUT->pix_icon('i/users', get_string('bulk_enroll', 'local_groups')) .html_writer::tag('span',get_string('bulk_enroll', 'local_groups'),array('class'=>'hidestrings')),
                            array('class'=>'dropdown-item', 'title' => get_string('bulk_enroll', 'local_groups')));
                    }       
                    if ($cohortmanager) 
                    $buttons[] =  html_writer::link(
                                    "javascript:void(0)",
                                    $OUTPUT->pix_icon('i/delete', get_string('delete'), 'moodle', array('title' => '')) .html_writer::tag('span',get_string('delete'),array('class'=>'hidestrings')),
                                    array('class'=>'dropdown-item','id' => 'deleteconfirm' . $cohort->id . '', 'onclick' => '(
                                          function(e){
                            require("local_groups/renderselections").deletecohort(' . $cohort->id . ', "' . $cohort->name . '")
                            })(event)'));
                     
                }
                    
                


                $line['actions'] = implode(' ', $buttons);

            
                // if (!$cohort->visible) {
                //     $row->attributes['class'] = 'dimmed_text';
                // }
                $imagehtml = '';
                
                $group_images= $DB->get_records('cohort_members', array('cohortid'=>$cohort->id),$sort='id desc', $fields='*', $limitfrom=0, $limitnum=4);
           foreach($group_images as $group_image){
              
                $user_record = $DB->get_record('user',array('id'=>$group_image->userid));

                    $user_picture = new user_picture($user_record, array('size' => 80, 'class' => 'userpic', 'link'=>false));
                    $user_picture = $user_picture->get_url($PAGE);
                       $userpic = $user_picture->out();

                $userimage = html_writer::start_tag('li'); 
                $userimage .= html_writer::tag('img', '', array('src' => $userpic));
                $userimage .= html_writer::end_tag('li'); 

                $imagehtml .= $userimage;
                
                
           }
           $line['userimages'] = $imagehtml;
           $cohort_users = $DB->count_records('cohort_members', array('cohortid'=>$cohort->id));
           $line['groupcount'] = $cohort_users;
           $line['userid']=$cohort->id;
           $line['location_url'] = $CFG->wwwroot . '/local/groups/assign.php?id='.$cohort->id;
        if (has_capability('local/groups:manage',  (new \local_groups\lib\accesslib())::get_module_context()) || is_siteadmin())
            $row[] = $line;
        }
        return $row;
    }
}

