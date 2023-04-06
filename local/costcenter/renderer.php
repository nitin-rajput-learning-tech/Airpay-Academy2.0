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
 * @subpackage local_costcenter
 */

require_once($CFG->dirroot.'/local/costcenter/lib.php');
if(file_exists($CFG->dirroot.'/local/includes.php')){
    require_once($CFG->dirroot.'/local/includes.php');
}
class local_costcenter_renderer extends plugin_renderer_base {

    /**
     * @method treeview
     * @todo To add action buttons
     */
    public function departments_view() {
        global $DB, $CFG, $OUTPUT, $USER,$PAGE;
        $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();

        $costcenter_instance = new costcenter;

         if (is_siteadmin()) {
            $sql = "SELECT distinct(s.id), s.* FROM {local_costcenter} s where parentid=0 ORDER BY s.sortorder DESC";
            $costcenters = $DB->get_records_sql($sql);
        } else if(has_capability('local/costcenter:view', $categorycontext)){

            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='s.path',$costcenterpath=null,$datatype='lowerandsamepath');


            $sql = "SELECT distinct(s.id), s.* FROM {local_costcenter} s where parentid = 0  $costcenterpathconcatsql ORDER BY s.sortorder";

            $costcenters = $DB->get_records_sql($sql);
        }

        if (!is_siteadmin() && empty($costcenters)) {
            print_error('notassignedcostcenter', 'local_costcenter');
        }
        $data = array();
        if(!empty($costcenters)){
            foreach ($costcenters as $costcenter) {
                $line = array();
                $showdepth = 1;
                $line[] = $this->display_department_item($costcenter, $showdepth);
                $data[] = $line;
            }
            $table = new html_table();
            if (has_capability('local/costcenter:manage', $categorycontext)){
                $table->head = array('');
                $table->align = array('left');
                $table->width = '100%';
                $table->data = $data;
                $table->id = 'department-index';
                $output = html_writer::table($table);
            }
        }else{
            $output = html_writer::tag('div', get_string('noorganizationsavailable', 'local_costcenter'), array('class'=>'alert alert-info text-xs-center'));
        }
        return $output;
    }

    /**
     * @method display_department_item
     * @todo To display the all costcenter items
     * @param object $record is costcenter
     * @param boolean $indicate_depth  depth for the costcenter item
     * @return string
     */
    public function display_department_item($record, $indicate_depth = true) {

        global $OUTPUT, $DB, $CFG, $PAGE;
        require_once($CFG->dirroot.'/local/costcenter/lib.php');
        $core_component = new \core_component();

        $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context($record->path);

        $contextid =  $categorycontext->id;


        $rolescount = $DB->count_records_sql("SELECT count(ra.roleid) FROM {context} AS ct JOIN {role_assignments} ra ON ra.contextid = ct.id  AND ct.id = '$contextid'");



        $sql="SELECT id from {local_costcenter} where parentid=?";
        $orgs = $DB->get_records_sql_menu($sql, [$record->id]);

        $departmentcount = count($orgs);


        if($departmentcount > 0){
            $dept_count_link = new moodle_url("/local/costcenter/costcenterview.php?id=".$record->id."");
        }else{
            $dept_count_link = 'javascript:void(0)';
        }

        $subdepartmentcount = 0;

        if($departmentcount){
            list($orgsql, $orgparams) = $DB->get_in_or_equal($orgs, SQL_PARAMS_NAMED, 'param', true, false);
            $subsql = "SELECT id, id as id_val from {local_costcenter} where parentid $orgsql";
            $subids = $DB->get_records_sql_menu($subsql, $orgparams);
            $subdepartmentcount = count($subids);
            if($subdepartmentcount > 0){
            $subdepartmentcount = $subdepartmentcount;
            }else{
            $subdepartmentcount = get_string('not_available', 'local_costcenter');
            }
        } else {
            $subdepartmentcount = get_string('not_available', 'local_costcenter');
        }

        // //this is for all plugins count
        $pluginnavs = local_costcenter_plugins_count($record->id);

        $itemdepth = ($indicate_depth) ? 'depth' . min(10, $record->depth) : 'depth1';
        // @todo get based on item type or better still, don't use inline styles :-(
        $itemicon = $OUTPUT->image_url('/i/item');
        $cssclass = !$record->visible ? 'dimmed' : '';

        $edit = false;
        $delete = false;
        $usercount = $pluginnavs['datacount'];
    
            if ($record->visible) {
                $hide = true;
                $show = false;

                $hideurl = 'javascript:void(0)';
                $showurl = 'javascript:void(0)';
            }else{
                $show = true;
                $hide = false;
                $showurl = 'javascript:void(0)';
                $hideurl = 'javascript:void(0)';

            }
        $action_message = get_string('confirmation_to_disable_'.$record->visible, 'local_costcenter', $record->fullname);

        $del_confirmationmsg = get_string('confirmationmsgfordel', 'local_costcenter',$record->fullname);

        if(has_capability('local/costcenter:update', $categorycontext))
                $edit = true;
        if((has_capability('local/costcenter:delete', $categorycontext)) && $usercount == 0 && $departmentcount == 0)
                $delete = true;

         $viewdeptContext = [
            "coursefileurl" => $OUTPUT->image_url('/course_images/courseimg', 'local_costcenter'),
            "orgname" => format_string($record->fullname),
            "dept_count_link" => $dept_count_link,
            "role_count" => $rolescount,
            "deptcount" => $departmentcount,
            "subdeptcount" => $subdepartmentcount,
            "editicon" => $OUTPUT->image_url('t/edit'),
            "hideicon" => $OUTPUT->image_url('t/hide'),
            "showicon" => $OUTPUT->image_url('t/show'),
            "deleteicon" => $OUTPUT->image_url('t/delete'),
            "hideurl" => $hideurl,
            "showurl" => $showurl,
            "edit" => $edit,
            "hide" => $hide,
            "show" => $show,
            "action_message" => $action_message,
            "delete_message" => $del_confirmationmsg,
            "status" => $record->visible,
            "delete" => $delete,
            "contextid" => $contextid,
            "recordid" => $record->id,
            "hierarchyid" => optional_param('id',0, PARAM_INT),
            "parentid" => $record->parentid,
            "headstring" => 'editcostcen',
            "formtype" => 'organization',
            "assignroles" => (is_siteadmin() || has_capability('local/assignroles:manageassignroles', $categorycontext)),
            "managepermissions" => (is_siteadmin() || has_capability('local/costcenter:managepermissions', $categorycontext)),
        ];


        $viewdeptContext = $viewdeptContext+$pluginnavs;


        return $this->render_from_template('local_costcenter/costcenter_view', $viewdeptContext);
    }

    /**
     * @method get_dept_view_btns
     * @todo To display create icon
     * @param object $id costcenter  id
     * @return string
     */
    public function get_dept_view_btns($id = false) {
        global $PAGE, $USER, $DB;

        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path',$costcenterpath=null,$datatype='lowerandsamepath');

        $exist_sql = "SELECT depth FROM {local_costcenter} WHERE 1=1 $costcenterpathconcatsql ";

        $costcenters_exist = $DB->record_exists_sql($exist_sql);

        if($id){

            $exist_sql .= " AND id=$id ";

            $costcenter = $DB->get_record_sql($exist_sql);
            $depth=$costcenter->depth;
            // print_r($depth);die;

        }else{
            $costcenterpath=null;
            $depth = 1;
        }

        $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context($costcenterpath);
        if ((is_siteadmin()) && $PAGE->pagetype == 'local-costcenter-index'){
            $create_organisation = "<a class='course_extended_menu_itemlink' data-action='createcostcentermodal' data-value='0' title = '".get_string('create_organization','local_costcenter')."' onclick ='(function(e){ require(\"local_costcenter/newcostcenter\").init({selector:\"createcostcentermodal\", contextid:$categorycontext->id, id:0, formtype:\"organization\", headstring:\"adnewcostcenter\"}) })(event)'><span class='createicon'><i class='fa fa-sitemap icon' aria-hidden='true'></i><i class='createiconchild fa fa-plus' aria-hidden='true'></i></span></a>";
        }else{
            $create_organisation = false;
        }

        if($costcenters_exist && $depth<=1 && has_capability('local/costcenter:create', $categorycontext)){
            $headstring = 'addnewdept';
            $title = get_string('createdepartment','local_costcenter');
            $create_department = "<a class='course_extended_menu_itemlink' data-action='createcostcentermodal' data-value='0' title = '$title' onclick ='(function(e){ require(\"local_costcenter/newcostcenter\").init({selector:\"createcostcentermodal\", contextid:$categorycontext->id, id:0, formtype:\"department\", headstring:\"$headstring\"}) })(event)'>
                <i class='icon fa fa-plus-square'></i>
            </a>";
        }else{
            $create_department = false;
        }
        $deptexistsql = "SELECT id FROM {local_costcenter} WHERE depth = 2 ";
        if(!(is_siteadmin())){

            $deptexistsql .= $costcenterpathconcatsql ;
        }
        $deptexist = $DB->record_exists_sql($deptexistsql);

        if($deptexist && $depth<=2 && has_capability('local/costcenter:create', $categorycontext)){
            $headstring = 'addnewsubdept';
                $title = get_string('createsubdepartment','local_costcenter');
                $create_sub_department = "<a class='course_extended_menu_itemlink' data-action='createcostcentermodal' data-value='0' title = '$title' onclick ='(function(e){ require(\"local_costcenter/newcostcenter\").init({selector:\"createcostcentermodal\", contextid:$categorycontext->id, id:0, formtype:\"subdepartment\", headstring:\"$headstring\"}) })(event)'>
                    <i class='icon fa fa-plus'></i>
                </a>";
        }else{
            $create_sub_department = false;
        }
        $deptexistth = "SELECT id FROM {local_costcenter} WHERE depth = 3 ";
        if(!(is_siteadmin())){

            $deptexistth .= $costcenterpathconcatsql ;
        }

        $deptexistone = $DB->record_exists_sql($deptexistth);

        if($deptexistone && $depth<=3 && has_capability('local/costcenter:create', $categorycontext)){
            $headstring = 'addnewsubsubdept';
                $title = get_string('createsubsubdepartment','local_costcenter');
                $create_sub_sub_department = "<a class='course_extended_menu_itemlink' data-action='createcostcentermodal' data-value='0' title = '$title' onclick ='(function(e){ require(\"local_costcenter/newcostcenter\").init({selector:\"createcostcentermodal\", contextid:$categorycontext->id, id:0, formtype:\"subsubdepartment\", headstring:\"$headstring\"}) })(event)'>
                    <i class='icon fa fa-plus-circle'></i>
                </a>";
        }else{
            $create_sub_sub_department = false;
        }

        $deptexistfo = "SELECT id FROM {local_costcenter} WHERE depth = 4 ";
        if(!(is_siteadmin())){

            $deptexistfo .= $costcenterpathconcatsql;
        }
        $deptexisttwo = $DB->record_exists_sql($deptexistfo);
        /*if($deptexisttwo && $depth<=4 && has_capability('local/costcenter:create', $categorycontext)){
            $headstring = 'addnewsubsubsubdept';
                $title = get_string('createsubsubsubdepartment','local_costcenter');
                $create_sub_sub_sub_department = "<a class='course_extended_menu_itemlink' data-action='createcostcentermodal' data-value='0' title = '$title' onclick ='(function(e){ require(\"local_costcenter/newcostcenter\").init({selector:\"createcostcentermodal\", contextid:$categorycontext->id, id:0, formtype:\"subsubsubdepartment\", headstring:\"$headstring\"}) })(event)'>
                    <i class='icon fa fa-plus-square-o'></i>
                </a>";
        }else{
            $create_sub_sub_sub_department = false;
        }*/

        $buttons = array(
            'create_organisation' => $create_organisation,
            'create_department' => $create_department,
            'create_sub_department' => $create_sub_department,
            'create_sub_sub_department' => $create_sub_sub_department,
            //'create_sub_sub_sub_department' => $create_sub_sub_sub_department
        );

       return $this->render_from_template('local_costcenter/viewbuttons', $buttons);
    }


    /**
     * @method get_dept_view_btns
     * @todo To display create icon
     * @param object $id costcenter  id
     * @return string
     */
    public function costcenterview($id, $categorycontext) {
        global $DB, $USER, $OUTPUT, $CFG;
        if (!$depart = $DB->get_record('local_costcenter', array('id' => $id))) {
            print_error('invalidschoolid');
        }
        $edit = false;
        $delete = false;

        $pluginnavs = local_costcenter_plugins_count($id);

        $pathcount = $depart->depth;
        $del_confirmationmsg = get_string('confirmationmsgfordel', 'local_costcenter',$depart->fullname);

        if(has_capability('local/costcenter:update', $categorycontext))
            $edit = true;
        if((has_capability('local/costcenter:delete', $categorycontext)) && $pathcount == 0 && $pluginnavs['datacount'] == 0)
            $delete = true;


        $dept_count_link = '';
        $subdepartment = '';
        $departments_sql="SELECT id,id AS id_val FROM {local_costcenter} WHERE parentid=:parent";
        $departments =$DB->get_records_sql_menu($departments_sql, array('parent' => $id));
        $department = count($departments);
        $roles="SELECT id FROM {role_assignments} WHERE contextid=:contextid";
        $total_roles=count($DB->get_records_sql_menu($roles, array('contextid' => $categorycontext->id)));
        $department = ($department > 0 ? $department : get_string('not_available', 'local_costcenter'));
        $dept_id=implode(',',$departments);

        if($dept_id){
             $subdepartments_sql="SELECT id,id AS id_val FROM {local_costcenter} WHERE parentid IN($dept_id);";
             $subdepartments = $DB->get_records_sql_menu($subdepartments_sql);
             $subdepartment = count($subdepartments);
             $subdepartment = ($subdepartment > 0 ? $subdepartment : get_string('not_available', 'local_costcenter'));
        }

        $dept_count_link = $department;

        $departments = $DB->get_records('local_costcenter', array('parentid' =>$id));
        $totaldepts = count($departments);
        /*data for organization details ends here*/
        $departments_content = array();
        if($totaldepts % 2 == 0){
            $deptclass = '';
        }else{
            $deptclass = 'deptsodd';
        }

        $deptkeys = array_values($departments);
        foreach($deptkeys as $key => $dept){
            $even = false;
            $odd = false;
            if($key % 2 == 0){
                $even = true;
            }
            else{
                $odd = true;
            }

            $path = explode('/',$dept->path);

            $organisationid =$departmentid=$subdepartmentid=$l4departmentid=$l5departmentid=0;

            if(isset($path[1])){

                $organisationid = $path[1];

            }
            if(isset($path[2])){

                $departmentid = $path[2];

            }
            if(isset($path[3])){

                $subdepartmentid = $path[3];

            }
            if(isset($path[4])){

                $l4departmentid = $path[4];

            }
            if(isset($path[5])){

                $l5departmentid = $path[5];

            }
            $departments_array = array();
            $subdepartments = $DB->get_records('local_costcenter', array('parentid' =>$dept->id));

            $subdeptcount =$subdept = count($subdepartments);
            if($subdept){
                $subdept_count_link = $CFG->wwwroot.'/local/costcenter/costcenterview.php?id='.$dept->id;
            }else{
                $subdept_count_link = "javascript:void(0)";
            }
            $subdept = ($subdept > 0 ? $subdept : get_string('not_available', 'local_costcenter'));

            $deparray = local_costcenter_plugins_count($organisationid,$departmentid,$subdepartmentid,$l4departmentid,$l5departmentid);

            $deptedit = false;
            $deptdelete = false;

            $deptdel_confirmationmsg = get_string('confirmationmsgfordel', 'local_costcenter',$dept->fullname);


            if(has_capability('local/costcenter:update', $categorycontext))
                $deptedit = true;
            if((has_capability('local/costcenter:delete', $categorycontext)) && $deparray['datacount'] == 0 && $subdeptcount == 0)
                $deptdelete = true;

           $context = (new \local_costcenter\lib\accesslib())::get_module_context($dept->path);

           $contextid =  $context->id;


            $rolescount = $DB->count_records_sql("SELECT count(ra.roleid) FROM {context} AS ct JOIN {role_assignments} ra ON ra.contextid = ct.id  AND ct.id = '$contextid'");


            $departments_array['subdept'] = $subdept;
            $departments_array['enablesubdepartment_link'] = true;
            $departments_array['subdept_count_link'] = $subdept_count_link;
            $departments_array['departmentparentid'] = $dept->parentid;
            $departments_array['departmentfullname'] = $dept->fullname;
            $departments_array['edit_image_url'] = $OUTPUT->image_url('t/edit');
            $departments_array['even'] = $even;
            $departments_array['odd'] = $odd;
            $departments_array['deptclass'] = $deptclass;
            $departments_array['deptedit'] = $deptedit;

            $departments_array['deptstatus'] = $dept->visible;
            $departments_array['deptdelete'] = $deptdelete;
            $departments_array['deptid'] = $dept->id;
            $departments_array['deptcontextid'] = $contextid;
            $departments_array['deptdel_confirmationmsg'] = $deptdel_confirmationmsg;
            $departments_array['headstring'] = 'update_dipartment';
            $departments_array['formtype'] = 'department';

            $departments_array['role_count'] = $rolescount;
            $departments_content[] = $departments_array+$deparray;
        }

        $costcenter_view_content = [
            "deptcount" => $dept_count_link,
            "subdeptcount" => $subdepartment,
            "deptclass" => $deptclass,
            "roleid" => 'test role',
            "coursefileurl" => $OUTPUT->image_url('/course_images/courseimg', 'local_costcenter'),
            "orgname" => $depart->fullname,
            "edit" => $edit,
            "status" => $depart->visible,
            "delete" => $delete,
            "recordid" => $depart->id,
            "hierarchyid" => optional_param('id',0, PARAM_INT),
            "contextid" => $contextid,
            "parentid" => $depart->parentid,
            "delete_message" => $del_confirmationmsg,
            "departments_content" => $departments_content,
            "headstring" => 'editcostcen',
            "formtype" => 'organization',
            "assignroles" => (is_siteadmin() || has_capability('local/assignroles:manageassignroles', $categorycontext)),
            "managepermissions" => (is_siteadmin() || has_capability('local/costcenter:managepermissions', $categorycontext)),
        ];


        $pluginnavs = local_costcenter_plugins_count($id);
        $costcenter_view_content = $costcenter_view_content+$pluginnavs;
        return $OUTPUT->render_from_template('local_costcenter/departments_view', $costcenter_view_content);
    }
    public function department_view($id, $categorycontext){
        global $DB, $USER, $OUTPUT, $CFG;
        if (!$depart = $DB->get_record('local_costcenter', array('id' => $id))) {
            print_error('invalidschoolid');
        }
        $edit = false;
        $delete = false;

        $pluginnavs = local_costcenter_plugins_count($id);

        $pathcount = $depart->depth;
        $del_confirmationmsg = get_string('confirmationmsgfordel', 'local_costcenter',$depart->fullname);


        if(has_capability('local/costcenter:update', $categorycontext))
            $edit = true;
        if((has_capability('local/costcenter:delete', $categorycontext)) && $pathcount == 0 && $pluginnavs['datacount'] == 0)
            $delete = true;


        $parentpath = explode('/',$DB->get_field('local_costcenter', 'path', array('id' => $id)));


        $parentorgnization =$parentdepartment=$parentsubdepartment=$parentl4department=$parentl5department=0;

        if(isset($parentpath[1])){

            $parentorgnization = $parentpath[1];

        }
        if(isset($parentpath[2])){

            $parentdepartment = $parentpath[2];

        }
        if(isset($parentpath[3])){

            $parentsubdepartment = $parentpath[3];

        }
        if(isset($parentpath[4])){

            $parentl4department = $parentpath[4];

        }
        if(isset($parentpath[5])){

            $parentl5department = $parentpath[5];

        }

        $subdepartment_link = '';
        $subdepartment = '';
        $departments_sql="SELECT id,id AS id_val FROM {local_costcenter} WHERE parentid=:parent";
        $departments =$DB->get_records_sql_menu($departments_sql, array('parent' => $id));
        $department = count($departments);
        $department = ($department > 0 ? $department : get_string('not_available', 'local_costcenter'));

        $subdepartment_link = $department;

        $subdepartments = $DB->get_records('local_costcenter', array('parentid' =>$id));
        $totalsubdepts = count($subdepartments);
        /*data for organization details ends here*/
        $departments_content = array();
        if($totalsubdepts % 2 == 0){
            $deptclass = '';
        }else{
            $deptclass = 'deptsodd';
        }

        $deptkeys = array_values($subdepartments);
        foreach($deptkeys as $key => $dept){
            $even = false;
            $odd = false;
            if($key % 2 == 0){
                $even = true;
            }
            else{
                $odd = true;
            }

            $path = explode('/',$dept->path);

            $organisationid =$departmentid=$subdepartmentid=$l4departmentid=$l5departmentid=0;

            if(isset($path[1])){

                $organisationid = $path[1];

            }
            if(isset($path[2])){

                $departmentid = $path[2];

            }
            if(isset($path[3])){

                $subdepartmentid = $path[3];

            }
            if(isset($path[4])){

                $l4departmentid = $path[4];

            }
            if(isset($path[5])){

                $l5departmentid = $path[5];

            }
            $departments_array = array();
            $subdepartments = $DB->get_records('local_costcenter', array('parentid' =>$dept->id));

            $subdeptcount=$subdept = count($subdepartments);
            if($subdept){
                $subdept_count_link = $CFG->wwwroot.'/local/costcenter/costcenterview.php?id='.$dept->id;
            }else{
                $subdept_count_link = "javascript:void(0)";
            }
            $subdept = ($subdept > 0 ? $subdept : get_string('not_available', 'local_costcenter'));

            $deparray = local_costcenter_plugins_count($organisationid,$departmentid,$subdepartmentid,$l4departmentid,$l5departmentid);

            $deptedit = false;
            $deptdelete = false;


            $deptdel_confirmationmsg = get_string('confirmationmsgfordel', 'local_costcenter',$dept->fullname);

            if(has_capability('local/costcenter:update', $categorycontext))
                $deptedit = true;
            if((has_capability('local/costcenter:delete', $categorycontext)) && $deparray['datacount'] == 0 && $subdeptcount == 0)
                $deptdelete = true;

            $context = (new \local_costcenter\lib\accesslib())::get_module_context($dept->path);

            $contextid =  $context->id;

            $rolescount = $DB->count_records_sql("SELECT count(ra.roleid) FROM {context} AS ct JOIN {role_assignments} ra ON ra.contextid = ct.id  AND ct.id = '$contextid'");

            $departments_array['subdept'] = $subdept;
            $departments_array['headstring'] = 'update_subdept';
            $departments_array['formtype'] = 'subdepartment';
            if($dept->depth == 5){
                $departments_array['headstring'] = 'update_subsubsubdept';
                $departments_array['formtype'] = 'subsubsubdepartment';
            }else if($dept->depth == 4){
                $departments_array['enablesubsubsubdepartment_link'] = true;
                $departments_array['headstring'] = 'update_subsubdept';
                $departments_array['formtype'] = 'subsubdepartment';
            }
            else if($dept->depth == 3){
                $departments_array['enablesubsubdepartment_link'] = true;
            }

            $departments_array['subdept_count_link'] = $subdept_count_link;
            $departments_array['departmentparentid'] = $dept->parentid;
            $departments_array['departmentfullname'] = $dept->fullname;
            $departments_array['edit_image_url'] = $OUTPUT->image_url('t/edit');
            $departments_array['even'] = $even;
            $departments_array['odd'] = $odd;
            $departments_array['deptclass'] = $deptclass;
            $departments_array['deptedit'] = $deptedit;
            $departments_array['deptstatus'] = $dept->visible;
            $departments_array['deptdelete'] = $deptdelete;
            $departments_array['deptid'] = $dept->id;
            //$departments_array['deptaction_message'] = $deptaction_message;
            $departments_array['hide_users'] = FALSE;
            $departments_array['hide_courses'] = FALSE;
            $departments_array['hide_exams'] = TRUE;
            $departments_array['hide_learninplans'] = FALSE;
            $departments_array['hide_feedbacks'] = TRUE;
            $departments_array['hide_classroom'] = FALSE;
            $departments_array['hide_program'] = FALSE;
            $departments_array['hide_certification'] = TRUE;
            $departments_array['role_count'] = $rolescount;
            $departments_array['deptdel_confirmationmsg'] = $deptdel_confirmationmsg;
            $departments_content[] = $departments_array+$deparray;
        }
        $contextid = (new \local_costcenter\lib\accesslib())::get_module_context()->id;
        $costcenter_view_content = [
            'showrols_content' => true,
            'totalsubdepts' => $totalsubdepts,
            "subdeptcount" => $subdepartment,
            "deptclass" => $deptclass,
            "coursefileurl" => $OUTPUT->image_url('/course_images/courseimg', 'local_costcenter'),
            "orgname" => $depart->fullname,
            "edit" => $edit,
            "status" => $depart->visible,
            "delete" => $delete,
            "recordid" => $depart->id,
            "hierarchyid" => optional_param('id',0, PARAM_INT),
            "contextid" => $contextid,
            "parentid" => $depart->parentid,
            "delete_message" => $del_confirmationmsg,
            "departments_content" => $departments_content,
            "headstring" => 'update_dipartment',
            "assignroles" => (is_siteadmin() || has_capability('local/assignroles:manageassignroles', $categorycontext)),
            "managepermissions" => (is_siteadmin() || has_capability('local/costcenter:managepermissions', $categorycontext)),
        ];
        if($depart->depth == 5){
            $costcenter_view_content['headstring'] = 'update_subsubsubsubdipartment';
            // $costcenter_view_content['showsubsubsubdept_content'] = true;
            $costcenter_view_content['formtype'] = 'subsubsubdepartment';
        }else if($depart->depth == 4){
            $costcenter_view_content['headstring'] = 'update_subsubsubdipartment';
            $costcenter_view_content['showsubsubsubdept_content'] = true;
            $costcenter_view_content['formtype'] = 'subsubdepartment';
        }else if($depart->depth == 3){
            $costcenter_view_content['headstring'] = 'update_subsubdipartment';
            $costcenter_view_content['showsubsubdept_content'] = true;
            $costcenter_view_content['formtype'] = 'subdepartment';
        }else if($depart->depth == 2){
            $costcenter_view_content['headstring'] = 'update_subdipartment';
            $costcenter_view_content['showsubdept_content'] = true;
            $costcenter_view_content['formtype'] = 'department';
        }

        $pluginnavs = local_costcenter_plugins_count($parentorgnization,$parentdepartment,$parentsubdepartment,$parentl4department,$parentl5department);
        $costcenter_view_content = $costcenter_view_content+$pluginnavs;
        return $OUTPUT->render_from_template('local_costcenter/departments_view', $costcenter_view_content);
    }
}
