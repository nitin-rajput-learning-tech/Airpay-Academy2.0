<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_users\local;
use html_writer;
class user {
    public function user_profile_content($id, $return = false, $start =0, $limit=5) {
        global $OUTPUT, $PAGE, $CFG, $DB;
        require_once($CFG->dirroot.'/course/renderer.php');
        require_once($CFG->libdir . '/badgeslib.php');

        $returnobj = new \stdClass();
        $returnobj->divid = 'user_profile';
        $returnobj->string = get_string('profile', 'local_users');
        $returnobj->moduletype = 'users';
        $returnobj->targetID = 'display_users';
        $returnobj->userid = $id;
        $returnobj->count = 1;
        $returnobj->usersexist = 1;

        $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
        $userrecord = $DB->get_record('user', array('id' => $id));

        /*user roles*/
        $userroles = get_user_roles($systemcontext, $id);
        if (!empty($userroles)) {
                $rolename  = array();
            foreach ($userroles as $roles) {
                $rolename[] = ucfirst($roles->name);
            }
                $roleinfo = implode(", ", $rolename);
        } else {
            $roleinfo = get_string('employee', 'local_users');
        }
        $sql3 = "SELECT cc.fullname, u.open_employeeid,u.open_costcenterid,
                    u.open_designation, u.open_location,
                    u.open_supervisorid, u.open_group,
                    u.department, u.open_subdepartment ,u.open_departmentid
                     FROM {local_costcenter} cc, {user} u
                    WHERE u.id=:id AND u.open_costcenterid=cc.id";
        $userOrg = $DB->get_record_sql($sql3, array('id' => $id));
        $usercostcenter = $DB->get_field('local_costcenter', 'fullname',  array('id' => $userOrg->open_costcenterid));
        $userdepartment = $DB->get_field('local_costcenter', 'fullname',  array('id' => $userOrg->open_departmentid));
        if (!empty($userrecord->phone1)) {
                $contact = $userrecord->phone1;
        } else {
                $contact = 'N/A';
        }
        if (!empty($userOrg->open_supervisorid)) {
            $get_reporting_username_sql = "SELECT u.id, u.firstname, u.lastname, u.open_employeeid FROM {user}
             as u WHERE  u.id= :open_supervisorid";
                $get_reporting_username = $DB->get_record_sql($get_reporting_username_sql , array('open_supervisorid'
                 => $userOrg->open_supervisorid));
                $reporting_to_empid = isset($get_reporting_username->serviceid) != null ? '
                 ('.$get_reporting_username->open_employeeid.')' : 'N/A';
                $reporting_username = $get_reporting_username->firstname.' '.$get_reporting_username->lastname;
        } else {
                $reporting_username = 'N/A';
        }

        $supervisorname = isset($get_reporting_username->firstname).' '.isset($get_reporting_username->lastname);
        $badgeimage = $OUTPUT->image_url('badgeicon', 'local_users');
        $badgimg = $badgeimage->out_as_local_url();

        $certiconimage = $OUTPUT->image_url('certicon', 'local_users');
        $certimg = $certiconimage->out_as_local_url();
        $usersviewContext = [
            "userid" => $userrecord->id,
            "username" => fullname($userrecord),
            "rolename" => $roleinfo,
            "empid" => $userOrg->open_employeeid != null ? $userOrg->open_employeeid : 'N/A',
            "user_email" => $userrecord->email,
            "organisation" => $usercostcenter ? $usercostcenter : 'N/A',
            "department" => $userdepartment ? $userdepartment : 'N/A',
            "location" => $userrecord->city != null ? $userrecord->city : 'N/A',
            "address" => $userrecord->address != null ? $userrecord->address : 'N/A',
            "phnumber" => $contact,
            "badgesimg" => $badgimg,
            "certimg" => $certimg,
            "supervisorname" => $reporting_username,
        ];

        $data = array();
        $data[] = $usersviewContext;
        $returnobj->navdata = $data;

        return $returnobj;
    }

    public function user_profileskill_content($id, $return = false, $start =0, $limit=5, $positionid=false) {
        global $OUTPUT, $PAGE, $CFG, $DB;
        require_once($CFG->dirroot.'/course/renderer.php');
        require_once($CFG->libdir . '/badgeslib.php');
        $returnobj = new \stdClass();
        $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
        $userrecord = $DB->get_record('user', array('id' => $id));
        $corecomponent = new \core_component();
        $positionpluginexists = $corecomponent::get_plugin_directory('local', 'positions');
        if ($positionpluginexists) {
            $loginuser_position = $DB->get_record_sql("SELECT p.* FROM {local_positions} as p JOIN {user} as u
            on p.id=u.open_positionid WHERE p.domain=u.open_domainid and u.id=$id");
        }
        $comparray = array();
        if ($loginuser_position) {
            if ($positionpluginexists) {
                $sql = "SELECT id,name FROM {local_positions} where sortorder <= '{$loginuser_position->sortorder}'
                 order by sortorder desc";
                $loginuser_next_positions = $DB->get_records_sql($sql, array(), 0, 3);
            }
            if (!empty($positionid)) {
                $positionid = $positionid;
            } else {
                $positionid = $userrecord->open_positionid;
            }
            $sql = "SELECT sc.*, sm.skillid, sm.levelid, sm.positionid FROM {local_skillmatrix} as sm JOIN
             {local_skill_categories} as sc ON sc.id = sm.skill_categoryid where sm.positionid = {$positionid}
              and sm.costcenterid = {$loginuser_position->costcenter} group by sc.id";
            $compitencies = $DB->get_records_sql($sql, array(), $start, $limit);
            $count = count($DB->get_records_sql("SELECT sc.id FROM {local_skillmatrix} as sm JOIN
             {local_skill_categories} as sc ON sc.id=sm.skill_categoryid where sm.positionid= {$positionid}
              and sm.costcenterid={$loginuser_position->costcenter} group by sc.id"));
            foreach ($compitencies as $compitency) {
                $compnames = array();
                $skillnames = array();
                $compitenc_name = html_writer::link('javascript:void(0)', $compitency->name, array('title' => '',
                'onclick' => '(function(e){ require("local_users/newuser").skillslist({ contextid:'.$systemcontext->id.'
                ,costcenterid:'.$loginuser_position->costcenter.',categoryid:'.$compitency->id.',positionid:'.$positionid.'
                , categoryname:"'.$compitency->name.'",userid:'.$id.'}) })(event)'));
                $compnames['comp_name'] = $compitenc_name;
                $compnames['comp_id'] = $compitency->id;
                if ($positionpluginexists) {
                    $domainid = $DB->get_field('local_positions', 'domain', array('id' => $compitency->positionid));
                }
                $sql = "SELECT s.id, s.name FROM {local_skill} as s
                         JOIN {local_skill_categories} as sc ON sc.id=s.category
                         where s.category= {$compitency->id} and s.costcenterid={$loginuser_position->costcenter}";
                $skills = $DB->get_records_sql_menu($sql, array());
                $skillnames = implode(',', $skills);
                $progress = $this->get_competency_percentage($compitency, $positionid, $userrecord->id);
                $compnames['percentage'] = $progress;
                $compnames['skillnames'] = $skillnames;
                $compnames['contextid'] = $systemcontext->id;
                $comparray[] = $compnames;
            }
        }

        $options = array('targetID' => 'display_skilldata');
        $returnobj->userid = $userrecord->id;
        $returnobj->positionid = $positionid;
        $returnobj->count = $count;
        $returnobj->compitencies = $comparray;
        $returnobj->options = $options;
        return $returnobj;
    }
    public function get_competency_percentage($compitency, $positionid, $userid) {
        global $DB;
        $skillLevelSql = "SELECT lsm.* FROM {local_skillmatrix} lsm WHERE lsm.positionid=:positionid AND
         lsm.skill_categoryid = :competencyid ";
        $skillLevels = $DB->get_records_sql($skillLevelSql, array('positionid' => $positionid, 'competencyid'
         => $compitency->id));
        $progress = $completed = 0;
        $total = count($skillLevels);
        if ($skillLevels) {
            foreach ($skillLevels as $skillLevel) {
                $sql = "SELECT cc.id as completionid
                        FROM {course_completions} cc
                         JOIN {course} c ON c.id = cc.course
                         JOIN {enrol} e ON c.id = e.courseid AND e.enrol IN('self','manual','auto')
                         JOIN {user_enrolments} ue ON e.id = ue.enrolid AND ue.userid = cc.userid
                         WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',2,',%')
                         AND cc.timecompleted is not NULL AND c.visible = 1 AND c.id > 1
                         AND cc.userid = :userid AND c.open_level=:level AND open_skill= :skillid
                        ";
                $completedSkill = $DB->record_exists_sql($sql, array('userid' => $userid, 'skillid' =>
                 $skillLevel->skillid, 'level' => $skillLevel->skilllevel));
                if ($completedSkill) {
                    $completed++;
                }
            }
            return round(($completed / $total) * 100, 2);
        } else {
            return 0;
        }
    }

}
