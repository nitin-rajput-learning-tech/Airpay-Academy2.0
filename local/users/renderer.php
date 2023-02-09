<?php
/*
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
 * @subpackage local_users
 */

class local_users_renderer extends plugin_renderer_base {
    /**
     * Description: Employees profile view in profile.php
     * @param  [int] $id [user id whose profile is viewed]
     * @return [HTML]     [user profile page content]
     */
    public function employees_profile_view($id) {
        global $CFG, $OUTPUT, $DB, $PAGE, $USER;
        require_once($CFG->dirroot.'/course/renderer.php');
        require_once($CFG->libdir . '/badgeslib.php');

        $corecomponent = new core_component();

        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        $userrecord = $DB->get_record('user', array('id' => $id));
        /*user image*/
        $user_image = $OUTPUT->user_picture($userrecord, array('size' => 35, 'link' => false));

        /*user roles*/
        $userroles = get_user_roles($categorycontext, $id);
        if (!empty($userroles)) {
                $rolename  = array();
            foreach ($userroles as $roles) {
                    $rolename[] = ucfirst($roles->name);
            }
                $roleinfo = implode(", ", $rolename);
        } else {
            $roleinfo = "Employee";
        }
        $sql3 = "SELECT u.open_employeeid,
                    u.open_designation, u.open_location,
                    u.open_supervisorid, u.open_group,
                    u.department, u.open_path
                     FROM {user} u
                    WHERE u.id=:id ";
        $userOrg = $DB->get_record_sql($sql3, array('id' => $id));
        $organisationdata = array_filter(explode('/', $userOrg->open_path));
        $organisationnames = array_map(function($orgid){
            return \local_costcenter\lib\accesslib::get_costcenter_info($orgid, 'fullname');
        }, $organisationdata);

        $usercostcenter = $organisationnames[1];
        $userdepartment = $organisationnames[2];
        $usersubdepartment = $organisationnames[3];
        $usercu = $organisationnames[4];
        $userterritory = $organisationnames[5];

        if (!empty($userrecord->phone1)) {
                $contact = $userrecord->phone1;
        } else {
                $contact = 'N/A';
        }
        if (!empty($userOrg->open_supervisorid)) {
            $get_reporting_username_sql = "SELECT u.id, u.firstname, u.lastname, u.open_employeeid
             FROM {user} as u WHERE  u.id= :open_supervisorid";
                $get_reporting_username = $DB->get_record_sql($get_reporting_username_sql , array('open_supervisorid' => $userOrg->open_supervisorid));
                $reporting_to_empid = isset($get_reporting_username->serviceid) != null ? ' ('.$get_reporting_username->open_employeeid.')' : 'N/A';
                $reporting_username = $get_reporting_username->firstname.' '.$get_reporting_username->lastname/*.$reporting_to_empid*/;
        } else {
                $reporting_username = 'N/A';
        }
        $usercontent = new stdClass();
        $core_component = new core_component();
        $local_pluginlist = $core_component::get_plugin_list('local');
        $existingplugin = array();
        $usercontent = array();
        $navigationdata = '';
        foreach ($local_pluginlist as $pluginname => $pluginurl) {
            $userclass = '\local_'.$pluginname.'\local\user';
            if (class_exists($userclass)) {
                $plugininfo = array();
                $pluginclass = new $userclass;
                if (method_exists($userclass, 'user_profile_content')) {
                    $plugindata = $pluginclass->user_profile_content($id, true);
                    $usercontent[] = $plugindata;
                    $plugininfo['userenrolledcount'] = $plugindata->count;
                    $plugininfo['string'] = $plugindata->string;
                    if ($pluginname != 'users') {
                        $existingplugin[$plugindata->sequence] = $plugininfo;
                    }
                    $navigationdata .= isset($plugindata->navdata);
                }
            }
        }

        ksort($existingplugin);
        $existingplugin = array_values($existingplugin);
        if (is_siteadmin() || has_capability('local/users:edit', $categorycontext)) {
            $capabilityedit = 1;
        } else {
            $capabilityedit = 0;
        }
        if (has_capability('moodle/user:loginas', $categorycontext)) {
            $loginasurl = new moodle_url('/course/loginas.php', array('id' => 1, 'user' => $userrecord->id, 'sesskey' => sesskey()));
        } else {
            $loginasurl = false;
        }
        $supervisorname = isset($get_reporting_username->firstname).' '.isset($get_reporting_username->lastname);
        // added by sarath for tabs dispalying
        $core_component = new core_component();
        $plugins = $core_component::get_plugin_list('local');
        $pluginarray = array();
        foreach ($plugins as $key => $valuedata) {
            $userclass = '\local_'.$key.'\local\user';
            if (class_exists($userclass)) {
                $pluginclass = new $userclass;
                if (method_exists($userclass, 'user_profile_content')) {
                    $pluginarray[$key] = true;
                }
            }
        }
        $badgecount = $DB->count_records_sql("SELECT count(id) FROM {badge_issued} WHERE
         userid = :userid", array('userid' => $userrecord->id));
        $stat = $DB->get_field('local_states','states_name',array('id'=>$userrecord->open_states));
        $dist = $DB->get_field('local_district','district_name',array('id'=>$userrecord->open_district));
        $subdist = $DB->get_field('local_subdistrict','subdistrict_name',array('id'=>$userrecord->open_subdistrict));
        $vill = $DB->get_field('local_village','village_name',array('id'=>$userrecord->open_village));
        $options = array('targetID' => 'display_modulesdata');
        if($userrecord->gender == 0){
            $gender = 'Male';
        } else if($userrecord->gender == 1){
            $gender = 'Female';
        } else if($userrecord->gender == 2){
            $gender = 'Other';
        }

        if($userrecord->open_prefix == 1){
            $prefix = 'Mr. ';
        } else if($userrecord->open_prefix == 2){
            $prefix = 'Mrs. ';
        } else if($userrecord->open_prefix == 3){
            $prefix = 'Ms. ';
        }
        $usersviewContext = [
            "userid" => $userrecord->id,
            "username" => fullname($userrecord),
            "userimage" => $user_image,
            "rolename" => $roleinfo,
            "empid" => $userOrg->open_employeeid != null ? $userOrg->open_employeeid : 'N/A',
            "user_email" => $userrecord->email,
            "organisation" => $usercostcenter ? $usercostcenter : 'N/A',
            "department" => $userdepartment ? $userdepartment : 'All',
            "subdepartment" => $usersubdepartment ? $usersubdepartment : 'All',
            "location" => $userrecord->city != null ? $userrecord->city : 'N/A',
            "timezone" => core_date::get_user_timezone($userrecord->timezone),
            "address" => $userrecord->address != null ? $userrecord->address : 'N/A',
            "designation" => $userrecord->open_designation != null ? $userrecord->open_designation : 'N/A',
            "client" => ! empty(trim($userrecord->open_client)) ? $userrecord->open_client : 'N/A',
            "team" => ! empty(trim($userrecord->open_team)) ? $userrecord->open_team : 'N/A',
            "grade" => ! empty(trim($userrecord->open_grade)) ? $userrecord->open_grade : 'N/A',
            "hrmrole" => ! empty(trim($userrecord->open_hrmsrole)) ? $userrecord->open_hrmsrole : 'N/A',
            "zone" => ! empty(trim($userrecord->open_zone)) ? $userrecord->open_zone : 'N/A',
            "region" => ! empty(trim($userrecord->open_region)) ? $userrecord->open_region : 'N/A',
            "level" => ! empty(trim($userrecord->open_level)) ? $userrecord->open_level : 'N/A',
            "branch" => ! empty(trim($userrecord->open_branch)) ? $userrecord->open_branch : 'N/A',
            "subbranch" => ! empty(trim($userrecord->open_subbranch)) ? $userrecord->open_subbranch : 'N/A',
            "skilltype" => ! empty(trim($userrecord->open_skilltype)) ? $userrecord->open_skilltype : 'N/A',
            "employment_type" => ! empty(trim($userrecord->open_employmenttype)) ? $userrecord->open_employmenttype : 'N/A',
            "employment_status" => ! empty(trim($userrecord->open_employmentstatus)) ? $userrecord->open_employmentstatus : 'N/A',
            "userstate" => $stat ? $stat : 'N/A',
            "phnumber" => $contact,
            "badgesimg" => $OUTPUT->image_url('badgeicon', 'local_users'),
            "certimg" => $OUTPUT->image_url('certicon', 'local_users'),
            'navigationdata' => $navigationdata,
            "usercontent" => $usercontent,
            "existingplugin" => $existingplugin,
            "editprofile" => new moodle_url("/user/editadvanced.php", array('id' => $userrecord->id, 'returnto' => 'profile')),
            "prflbgimageurl" => $OUTPUT->image_url('prflbg', 'local_users'),
            "badgescount" => $badgecount,
            "supervisorname" => $reporting_username,
            "capabilityedit" => $capabilityedit,
            "loginasurl" => $loginasurl,
            "options" => $options,
            "joindate"=> $userrecord->open_joindate > 0 ? date('d-M-Y', $userrecord->open_joindate) : 'N/A',
            "dateofbirth"=> $userrecord->open_dateofbirth > 0 ? date('d-M-Y', $userrecord->open_dateofbirth) : 'N/A',
            "pluginslist" => $pluginarray,
            "userterritory" => $userterritory ? $userterritory : 'All',
            "usercu" => $usercu ? $usercu : 'All',
            "userdistrict" => $dist ? $dist : 'N/A',
            "usersubdistrict" => $subdist ? $subdist : 'N/A',
            "uservillage" => $vill ? $vill : 'N/A',
            "gender" => $gender,
            "prefix" => $prefix,
        ];
        $value = $this->render_from_template('local_users/profile', $usersviewContext);

        return $value;
    }


    /**
     * [user_page_top_action_buttons description]
     * @return [html] [top action buttons content]
     */
    public function user_page_top_action_buttons() {
        global $CFG;
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

        return $this->render_from_template('local_users/usertopactions', array('contextid' => $categorycontext->id));
    }
    /**
     * [render_form_status description]
     * @method render_form_status
     * @param  \local_users\output\form_status $page [description]
     * @return [type]                                    [description]
     */
    public function render_form_status(\local_users\output\form_status $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_users/form_status', $data);
    }

    /**
     * [display_users description]
     * @method manageusers_content
     * @param  $filter default false
     * @author  sarath
     */
    public function manageusers_content($filter = false, $view_type='card') {
        global $USER;
        $status = optional_param('status', '', PARAM_RAW);
        $costcenterid = optional_param('costcenterid', '', PARAM_INT);
        $departmentid = optional_param('departmentid', '', PARAM_INT);
        $subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
        $l4department = optional_param('l4department', '', PARAM_INT);
        $l5department = optional_param('l5department', '', PARAM_INT);
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

        $templateName = 'local_users/users_view';
        $cardClass = 'col-md-6 col-12';
        $perpage = 10;
        if ($view_type == 'table') {
            $templateName = 'local_users/users_catalog_table';
            $cardClass = 'tableformat';
            $perpage = 20;
        }

        $options = array('targetID' => 'manage_users1', 'perPage' => $perpage, 'cardClass' => $cardClass, 'viewType' => $view_type);
        $options['methodName'] = 'local_users_manageusers_view';
        $options['templateName'] = $templateName;
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' => $USER->id, 'contextid' => $categorycontext->id,
         'status' => $status, 'filteropen_costcenterid' => $costcenterid, 'filteropen_department' => $departmentid,
          'filteropen_subdepartment' => $subdepartmentid, 'filteropen_level4department' => $l4department, 'filteropen_level5department' => $l5department));
        $filterdata = json_encode(array('status' => $status, 'filteropen_costcenterid' => $costcenterid, 'filteropen_department' =>
         $departmentid, 'filteropen_subdepartment' => $subdepartmentid, 'filteropen_level4department' => $l4department, 'filteropen_level5department' => $l5department));

        $context = [
                'targetID' => 'manage_users1',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if ($filter) {
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    /**
     * [display_sync errors description]
     * @method display_sync errors
     * @param  $filter default false
     * @author  sarath
     */
    public function display_sync_errors($filter = false) {
        global $USER;

        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();


        $options = array('targetID' => 'display_sync', 'perPage' => 10, 'cardClass' => 'tableformat', 'viewType' => 'table');
        $options['methodName'] = 'local_users_syncerrors_view';
        $options['templateName'] = 'local_users/syncerrors';
        $options = json_encode($options);
        $dataoptions = json_encode(array('userid' => $USER->id, 'contextid' => $categorycontext->id));
        $filterdata = json_encode(array());

        $context = [
                'targetID' => 'display_sync',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];

        if ($filter) {
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    /**
     * [display_sync statics description]
     * @method display_sync statics
     * @param  $filter default false
     * @author  sarath
     */
    public function display_sync_statics($filter = false) {
        global $USER;

        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();

        $options = array('targetID' => 'display_syncstatics', 'perPage' => 10, 'cardClass' => 'tableformat', 'viewType' => 'table');
        $options['methodName'] = 'local_users_syncstatics_view';
        $options['templateName'] = 'local_users/syncstatistics';
        $options = json_encode($options);
        $dataoptions = json_encode(array('userid' => $USER->id, 'contextid' => $categorycontext->id));
        $filterdata = json_encode(array());

        $context = [
                'targetID' => 'display_syncstatics',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];

        if ($filter) {
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

     public function employees_skill_profile_view($id) {
        global $CFG, $OUTPUT, $DB, $PAGE, $USER;

        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        $userrecord = $DB->get_record('user', array('id' => $id));
        $corecomponent = new \core_component();
        $positionpluginexists = $corecomponent::get_plugin_directory('local', 'positions');
        if ($positionpluginexists) {
         $loginuser_position = $DB->get_record_sql("SELECT p.* FROM {local_positions} as p JOIN {user} as u
          on p.id = u.open_positionid WHERE p.domain = u.open_domainid and u.id = $userrecord->id");
        }
        $pluginarray = array();
        $comparray = array();
        if ($loginuser_position) {
            $path = explode('/', $loginuser_position->path);
            if ($positionpluginexists) {
             $sql = "SELECT id,name FROM {local_positions} where sortorder <= '{$loginuser_position->sortorder}'
              and domain = {$loginuser_position->domain} and path LIKE '%/$path[1]%' order by sortorder desc";
             $loginuser_next_positions = $DB->get_records_sql($sql, array(),0,3);
            }
            foreach ($loginuser_next_positions as $loginuser_next_position) {
                if($userrecord->open_positionid != $loginuser_next_position->id){
                    $current_position = '';
                } else {
                    $current_position = 'Current Role';

                }
                $positiontabnames = array();
                $positiontabnames['positionname'] = $loginuser_next_position->name;
                $positiontabnames['id'] = $loginuser_next_position->id;
                $positiontabnames['current_position'] = $current_position;
                $pluginarray[] = $positiontabnames;
            }

            $sql = "SELECT sc.*, sm.positionid FROM {local_skillmatrix} as sm JOIN {local_skill_categories}
             as sc ON sc.id=sm.skill_categoryid where sm.positionid=$loginuser_position->id";
            $compitencies = $DB->get_records_sql($sql, array());
            $count = count($compitencies);
            foreach ($compitencies as $compitency) {
                if ($positionpluginexists) {
                    $domainid = $DB->get_field('local_positions', 'domain', array('id' => $compitency->positionid));
                }
                $corecomponent = new \core_component();
                $pluginexists = $corecomponent::get_plugin_directory('local', 'domains');
                if ($pluginexists) {
                    $domain = $DB->get_field('local_domains', 'name', array('id' => $domainid));
                }
                $org = $DB->get_field('local_costcenter', 'fullname', array('id' => $compitency->costcenterid));
                $compnames = array();
                $compnames['comp_name'] = $compitency->name;
                $sql = "SELECT s.name, s.id FROM {local_skill} as s JOIN {local_skill_categories} as sc ON
                 sc.id=s.category where s.category= $compitency->id";
                $skills = $DB->get_records_sql_menu($sql, array());
                if ($skills) {
                    $skillids = implode(',', $skills);
                    $sql = "SELECT distinct(cc.id) as completionid,c.id,c.fullname,c.shortname as code,
                    c.summary,ue.timecreated as enrolldate,cc.timecompleted as completedate
                             FROM {course_completions} AS cc
                            JOIN {course} AS c ON c.id = cc.course
                             JOIN {enrol} AS e ON c.id = e.courseid AND e.enrol IN('self','manual','auto')
                            JOIN {user_enrolments} AS ue ON e.id = ue.enrolid AND ue.userid = cc.userid
                            WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',2,',%')
                             AND cc.timecompleted is not NULL AND c.visible=1 AND c.id>1 AND cc.userid =
                              {$userrecord->id} AND open_skill IN ({$skillids})";
                    $completed_skills = $DB->get_records_sql($sql);
                    $progress = round((count($completed_skills) / count($skills)) * 100, 2);
                } else {
                    $progress = 0;
                }
                $compnames['percentage'] = $progress;
                $comparray[] = $compnames;
            }
        }

        $options = array('targetID' => 'display_skilldata');

        $usersviewContext = [
            "userid" => $userrecord->id,
            "username" => fullname($userrecord),
            "positionslist" => $pluginarray,
            "id" => $loginuser_position->id,
            "contextid" => 1,
            "options" => $options,
            "records" => $comparray,
            "currentposition_id" => $userrecord->open_positionid
        ];

        return $usersviewContext;
     }
}
