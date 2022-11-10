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
 * @subpackage local_assignroles
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/lib.php');
/**
 * Function to display the assign role form in popup
 * returns data of the popup 
 */
function local_assignroles_output_fragment_new_assignrole($args)
{
    global $CFG, $DB, $USER;

    $args = (object) $args;
    $context = $args->context;
    $roleid = $args->roleid;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $users = array();
    if (!is_siteadmin()) {
        $costcentersql = "SELECT open_costcenterid FROM {user} WHERE id= :userid ";
        $costcenterid = $DB->get_field_sql($costcentersql, array('userid' => $USER->id));
        /*$records = $DB->get_records('user', array('open_costcenterid' => $costcenterid), $sort='', $fields='username', $limitfrom=0, $limitnum=0);
        foreach ($records as $id => $record) {
           $users[] = $record->username;
        }   */     
    }
   
    $mform = new local_assignroles\form\assignrole(null, array('roleid' => $roleid, 'costcenterid' => $costcenterid), 'post', '', null, true, $formdata);
    $mform->set_data($formdata);
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
/**
 * Function to display the assign role form in popup
 * returns data of the popup 
 */
function local_assignroles_output_fragment_new_costcenterassignrole($args)
{
    global $CFG, $DB, $USER;

    $args = (object) $args;
    $context = $args->context;
    $costcenterid = $args->costcenterid;
    $formtype = $args->formtype;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $users = array();
   
    $mform = new local_assignroles\form\assigncostcenterrole(null, array('costcenterid' => $costcenterid,'formtype' => $formtype), 'post', '', null, true, $formdata);
    $mform->set_data($formdata);
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
/**
 * Function to display the role users in popup
 * returns data of the popup 
 */
function local_assignroles_output_fragment_roleusers_display($args)
{
    global $DB, $CFG, $PAGE, $OUTPUT, $USER;

    $args = (object) $args;
    $context = $args->context;
    $roleid = $args->roleid;
    $rolename = $DB->get_field('role', 'shortname', array('id' => $roleid));
    $rolefullname = $DB->get_field('role', 'name', array('id' => $roleid));
  
    $context = (new \local_assignroles\lib\accesslib())::get_module_context();
    
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)) {
        $sql = "SELECT ra.* FROM {role_assignments} AS ra JOIN {user} AS u on u.id=ra.userid 
            JOIN {local_costcenter} AS c on c.id=u.open_costcenterid
            WHERE ra.roleid=:roleid ";
        $users = $DB->get_records_sql($sql, array('roleid' => $roleid));
    } else if (has_capability('local/costcenter:manage_ownorganization', $context)) {
        $sql = "SELECT ra.* FROM {role_assignments} AS ra JOIN {user} AS u on u.id=ra.userid 
            WHERE ra.roleid=:roleid AND ra.contextid=:contextid AND u.open_costcenterid=:costcenter";
        $users = $DB->get_records_sql($sql, array('roleid' => $roleid, 'contextid' => $context->id, 'costcenter' => $USER->open_costcenterid));
    } else {
        $sql = "SELECT ra.* FROM {role_assignments} AS ra JOIN {user} AS u on u.id=ra.userid 
            WHERE ra.roleid=:roleid AND ra.contextid=:contextid AND u.open_costcenterid=:costcenter AND u.open_departmentid=:department";
        $users = $DB->get_records_sql($sql, array('roleid' => $roleid, 'contextid' => $context->id, 'costcenter' => $USER->open_costcenterid, 'department' => $USER->open_departmentid));
    }


    $templatedata = array();
    $templatedata['roleid'] = $roleid;
    $templatedata['rolename'] = $rolename;
    $templatedata['rolefullname'] = $rolefullname;
    //print_object($users);
    if ($users) {
        $templatedata['enabletable'] = true;
        foreach ($users as $user) {
            $rowdata = array();
            //$user_data_sql = "SELECT u.id,u.firstname,u.lastname,u.email,u.open_employeeid,lc.fullname FROM {user} AS u JOIN {local_costcenter} AS lc ON lc.id=u.open_costcenterid WHERE u.id = :id";
            //print_object($user_data_sql);
            // print_object($user->userid);
            $user_data_sql = "SELECT u.id,u.firstname,u.lastname,u.email,u.open_employeeid,lc.fullname 
                                FROM {role_assignments} AS ra 
                                JOIN {user} AS u ON ra.userid = u.id 
                                JOIN {context} As ct ON ra.contextid = ct.id
                                JOIN {local_costcenter} AS lc ON lc.category=ct.instanceid 
                                WHERE u.id = :id AND ra.roleid = :roleid";
            $userdata = $DB->get_record_sql($user_data_sql, array('id' => $user->userid, 'roleid' => $roleid));
            $fullname = $userdata->firstname . ' ' . $userdata->lastname;
            $rowdata['fullname'] = $fullname;
            $rowdata['employeeid'] = $userdata->open_employeeid;
            $rowdata['email'] = $userdata->email;
            $rowdata['orgname'] = $userdata->fullname;
            $rowdata['userid'] = $user->userid;
            $rowdata['username'] = $fullname;

            if (is_siteadmin()) {
                /*$sql = "SELECT cc.category FROM {local_costcenter} AS cc JOIN {user} AS u on cc.id = u.open_costcenterid 
                         WHERE u.id= :userid ";
                $categoryid = $DB->get_field_sql($sql, array('userid' => $user->userid));
                $catgcontext = \context_coursecat::instance($categoryid);
                $rowdata['contextid'] = $catgcontext->id; */
                $sql = "SELECT instanceid FROM {context} WHERE id = :id ";
                $categoryid = $DB->get_field_sql($sql, array('id' => $user->contextid));

                if ($categoryid == 0) {
                    $usercontext = \context_system::instance();
                } else {
                    $usercontext = \context_coursecat::instance($categoryid);
                }
                $rowdata['contextid'] = $usercontext->id;
            } else {
                $rowdata['contextid'] = $context->id;
            }
            $templatedata['rowdata'][] = $rowdata;
        }
    } else {
        $templatedata['enabletable'] = false;
    }

    $output = $OUTPUT->render_from_template('local_assignroles/popupcontent', $templatedata);

    return $output;
}
/**
 * Function to display the role users in popup
 * returns data of the popup 
 */
function local_assignroles_output_fragment_costcenterroleusers_display($args)
{
    global $DB, $CFG, $PAGE, $OUTPUT, $USER;

    $args = (object) $args;
    $context = $args->context;
    $costcenterid = $args->costcenterid;
   
  
    $context = (new \local_assignroles\lib\accesslib())::get_module_context($costcenterid);
    

    $sql = "SELECT u.id,u.firstname,u.lastname,u.email,u.open_employeeid,ra.roleid,ra.contextid FROM {role_assignments} AS ra JOIN {user} AS u on u.id=ra.userid 
            WHERE  ra.contextid=:contextid ";
    $users = $DB->get_records_sql($sql, array('contextid' => $context->id));
    

    $templatedata = array();
    //print_object($users);
    if ($users) {
        $templatedata['enabletable'] = true;
        foreach ($users as $user) {
            $rowdata = array();
         
            $rolefullname = $DB->get_field('role', 'name', array('id' => $user->roleid));

            $fullname = $user->firstname . ' ' . $user->lastname;

            $rowdata['fullname'] = $fullname;
            $rowdata['fullname'] = $fullname;
            $rowdata['employeeid'] = $user->open_employeeid;
            $rowdata['email'] = $user->email;
            $rowdata['rolefullname'] = $rolefullname;
            $rowdata['userid'] = $user->id;
            $rowdata['username'] = $fullname;
            $rowdata['roleid'] = $user->roleid;

            if (is_siteadmin()) {
          
                $sql = "SELECT instanceid FROM {context} WHERE id = :id ";
                $categoryid = $DB->get_field_sql($sql, array('id' => $user->contextid));

                if ($categoryid == 0) {
                    $usercontext = \context_system::instance();
                } else {
                    $usercontext = \context_coursecat::instance($categoryid);
                }
                $rowdata['contextid'] = $usercontext->id;
            } else {
                $rowdata['contextid'] = $context->id;
            }
            $templatedata['rowdata'][] = $rowdata;
        }
    } else {
        $templatedata['enabletable'] = false;
    }

    $output = $OUTPUT->render_from_template('local_assignroles/costcenterpopupcontent', $templatedata);

    return $output;
}
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_assignroles_leftmenunode()
{

    global $USER, $DB;

    $context = (new \local_assignroles\lib\accesslib())::get_module_context();
    $assignrolesnode = '';
    $userid =  $USER->id;

    if (has_capability('local/assignroles:manageassignroles', $context) || is_siteadmin()) {

        $assignrolesnode .= html_writer::start_tag('li', array('id' => 'id_leftmenu_assign_roles', 'class' => 'pull-left user_nav_div assign_roles'));
        $users_url = new moodle_url('/local/assignroles/index.php');
        $users = html_writer::link($users_url, '<i class="fa fa-user-circle" aria-hidden="true"></i><span class="user_navigation_link_text">' . get_string('pluginname', 'local_assignroles') . '</span>', array('class' => 'user_navigation_link'));
        $assignrolesnode .= $users;
        $assignrolesnode .= html_writer::end_tag('li');
    }
    return array('4' => $assignrolesnode);
}
