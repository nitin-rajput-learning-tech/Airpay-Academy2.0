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
 * @subpackage block_reportdashboard
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/blocks/reportdashboard/reportselect_form.php');
require_once($CFG->dirroot . '/blocks/reportdashboard/reporttiles_form.php');
require_once($CFG->dirroot . '/blocks/learnerscript/classes/observer.php');

use block_reportdashboard\local\reportdashboard;
use block_learnerscript\lsform as lsform;
use block_learnerscript\local\ls as ls;
use block_learnerscript\local\querylib as querylib;
global $CFG, $PAGE, $OUTPUT, $THEME, $ADMIN, $DB, $USER;
$adminediting = optional_param('adminedit', -1, PARAM_BOOL);
$dashboardurl = optional_param('dashboardurl', '', PARAM_RAW_TRIMMED);
$delete = optional_param('delete', '', PARAM_RAW);
$deletereport = optional_param('deletereport', 0, PARAM_INT);
$blockinstanceid = optional_param('blockinstanceid', 0, PARAM_INT);
$reportid = optional_param('reportid', 0, PARAM_INT);
$role = optional_param('role', '', PARAM_RAW);
$sesskey = optional_param('sesskey', '', PARAM_RAW);
$contextlevel = optional_param('contextlevel', 40, PARAM_INT);

require_login();
if (isguestuser()) {
    print_error('noguest');
}
$context = (new \local_costcenter\lib\accesslib())::get_module_context(); //context_system::instance();
$PAGE->set_context($context);

if ($PAGE->user_allowed_editing() && $adminediting != -1) {
    $USER->editing = $adminediting;
}
$user_costcenterid = explode('/',$USER->open_path)[1];
$user_departmentid = explode('/',$USER->open_path)[2];
$user_subdepartmentid = explode('/',$USER->open_path)[3];
if (!is_siteadmin()) {
    // $rolelist = (new ls)->get_currentuser_roles();
    $userroles = get_user_roles($context, $USER->id);
    $roleids = array_column($userroles, 'roleid');
    $rolenames = array_column($userroles, 'shortname');

    $rolelist = array_combine($roleids,$rolenames);
    $emprole = $DB->get_record('role',array('shortname' =>'user'),'id,name,shortname');
    $rolelist[$emprole->id] = $emprole->shortname;

    if (!empty($role) && in_array($role, $rolelist)) {
        $role = empty($role) ? array_shift($rolelist) : $role;
    } else if (empty($role)) {
        $role = empty($role) ? array_shift($rolelist) : $role;
    } else {
        $role = '';
    }
    $_SESSION['role'] = $role;
} else {
    $_SESSION['role'] = $role;
}
if (!is_siteadmin()) {
    // $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
    // if (!empty($scheduledreport)) {
    //     $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
    //     $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
    // } else {
        // $ohs = $dhs = 1;
    // }
        

}
$dashboardcostcenterid = 0;
$dashboarddepartmentid = 0;
$dashboardsubdepartmentid = 0;
$dashboardl4departmentid = 0;
$dashboardl5departmentid = 0;
$dashboardcourseid=0;
$complianceid=0;
    if (is_siteadmin()  || ($_SESSION['role'] == 'manager')) {
        $dashboardcostcenter = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE visible = 1 AND depth = 1  AND parentid = 0 ORDER BY id ASC LIMIT 0, 1");
        $dashboardcostcenterid = $dashboardcostcenter;
    } else {
        $dashboardcostcenter =  $user_costcenterid;
        $dashboardcostcenterid = $dashboardcostcenter;
    }
    $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context(); //context_system::instance();
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $dashboarddepartment = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE visible = 1 AND depth = 2 AND parentid = $dashboardcostcenterid ORDER BY id ASC LIMIT 0, 1");
        $dashboarddepartmentid = $dashboarddepartment;
    } else /*if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs)*/ {
        $dashboarddepartment = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE visible = 1 AND depth = 2 AND parentid = $user_costcenterid ORDER BY id ASC LIMIT 0, 1");
        $dashboarddepartmentid = $dashboarddepartment;
    // } else {
    //     $dashboarddepartment = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE visible = 1 AND depth = 2 AND parentid = $user_costcenterid AND id = :department ORDER BY id ASC LIMIT 0, 1", array('department' => $user_departmentid));
    //     $dashboarddepartmentid = $dashboarddepartment;
    }


$coursels = true;
$parentcheck = false;

$seturl = !empty($role) ? '/blocks/reportdashboard/dashboard.php?role=' . $role : '/blocks/reportdashboard/dashboard.php';
$pagepattentype = !empty($role) ? 'blocks-reportdashboard-dashboard-' . $role . '' : 'blocks-reportdashboard-dashboard';
if ($dashboardurl != '') {
    $seturl = !empty($role) ? '/blocks/reportdashboard/dashboard.php?role=' . $role . '&dashboardurl=' . $dashboardurl .
                        '' : '/blocks/reportdashboard/dashboard.php?dashboardurl=' . $dashboardurl. '';
}
if ($dashboardurl == ''  || $dashboardurl == 'Dashboard') {
    $dashboardurl = 'Dashboard';
}
$subpagepatterntype = $dashboardurl;

$PAGE->set_url($seturl);
$PAGE->set_pagetype($pagepattentype);
$PAGE->set_subpage($subpagepatterntype);
// $PAGE->set_pagelayout('admin');
$PAGE->add_body_class('reportdashboard'); 
if ($dashboardurl == 'Maindashboard') {
  $dashboardhname = 'Main Dashboard';
} else if ($dashboardurl == 'Learnerdashboard') {
  $dashboardhname = 'Learning Dashboard';
} else if ($dashboardurl == 'Examdashboard') {
  $dashboardhname = 'Exam Dashboard';
} else if ($dashboardurl == 'Certification') {
  $dashboardhname = 'Certification Dashboard';
} else if ($dashboardurl == 'Compliances') {
  $dashboardhname = 'Compliance Dashboard';
} else {
  $dashboardhname = $dashboardurl;
}
$heading = get_string('analytics', 'block_reportdashboard') . ' - ' . $dashboardhname;
$PAGE->set_heading($heading);
$PAGE->navbar->ignore_active();
// $navdashboardurl = new moodle_url($seturl);

require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');

$params = get_reportdashboard();
$navurl = new moodle_url('/blocks/reportdashboard/dashboard.php', $params);
$PAGE->navbar->add(get_string('analytics', 'block_learnerscript'), $navurl);
if (!$dashboardurl) {
    $PAGE->navbar->add(get_string('dashboard', 'block_reportdashboard'));
} else {
    $PAGE->navbar->add($dashboardurl);
}
$PAGE->requires->jquery();
//$PAGE->requires->js('/blocks/learnerscript/js/highcharts/highcharts.js');
$PAGE->requires->js_call_amd('block_reportdashboard/reportdashboard', 'init');

// $PAGE->requires->css('/blocks/reportdashboard/css/radios-to-slider.min.css');
// $PAGE->requires->css('/blocks/reportdashboard/css/flatpickr.min.css');

$output = $PAGE->get_renderer('block_reportdashboard');

$regions = array('side-db-first', 'side-db-second', 'side-db-third', 'side-db-four', 'learning-first', 'learning-second', 'learning-third', 'learning-fourth', 'learning-fifth', 'learning-sixth', 'first-maindb', 'learner-first', 'learner-second', 'learner-third', 'learner-fourth', 'learner-fifth', 'learner-sixth', 'learner-maindb', 'ol-one', 'ol-second', 'ol-third', 'ol-fourth', 'ol-fifth', 'ol-sixth', 'reportdb-one', 'reportdb-second',/* 'reportdb-third',*/ 'center-first', 'center-second', 'reports-db-one', 'reports-db-two', 'side-db-main', 'side-db-one', 'side-db-two', 'side-db-three', 'lp-one', 'lp-second', 'lp-third', 'lp-fourth', 'lp-fifth', 'lp-sixth', 'learn-one', 'learn-second', 'learn-third', 'lp-main', 'labs-one', 'labs-second', 'labs-third', 'labs-fourth', 'labs-fifth', 'labs-sixth', 'exam-one', 'exam-second','labs-db-main', 'assess-one', 'assess-second', 'assess-third', 'assess-fourth', 'assess-fifth', 'assess-sixth', 'certifi-one', 'certifi-second', 'certifi-third', 'assess-db-main', 'webinars-one', 'webinars-second', 'webinars-third', 'webinars-fourth', 'webinars-fifth', 'webinars-sixth', 'compliance-one', 'compliance-second', 'compliance-third', 'webinars-db-main', 'classroom-one', 'classroom-second', 'classroom-third', 'classroom-four', 'classroom-main', 'program-one', 'program-second', 'program-third', 'program-four', 'program-fifth', 'program-sixth', 'program-main', 'compliance-first', 'reportcert-one', 'reportcert-two', 'reportcert-third', 'reportcert-four', 'reportcert-fifth', 'cert-one', 'cert-second');
$PAGE->blocks->add_regions($regions);

if ($delete && confirm_sesskey()) {
    $deleteinstance = (new reportdashboard)->delete_dashboard_instances($role, $dashboardurl, $blockinstanceid);
    (new reportdashboard)->delete_widget($deletereport, $blockinstanceid, $reportid);
}

$header = get_string('reports', 'block_reportdashboard');
$PAGE->set_title($header);

$reportsfont = get_config('block_reportdashboard', 'reportsfont');
if ($reportsfont == 2) { // Selected font as PT Sans.
    $PAGE->requires->css('/blocks/reportdashboard/fonts/roboto.css');
} else if ($reportsfont == 1) { // Selected font as Open Sans.
    $PAGE->requires->css('/blocks/reportdashboard/fonts/roboto.css');
}

$reportdashboardstatus = $PAGE->blocks->is_known_block_type('reportdashboard', false);
$reporttilestatus = $PAGE->blocks->is_known_block_type('reporttiles', false);
$data = data_submitted();

$reportdashboard = new reportdashboard;
if ($reportdashboardstatus) {
    if (isset($data->_qf__reportselect_form)) {
        $reportdashboard->add_dashboardblocks($data);
        redirect($PAGE->url);
    }
}
if ($reporttilestatus) {
    if (isset($data->_qf__reporttiles_form)) {
        $reportdashboard->add_tilesblocks($data);
        redirect($PAGE->url);
    }
}


$dataaction = isset($data->action) ? $data->action : '';
if (!empty($data) && $dataaction == 'sendemails') {
    $roleid = 0;
    if (!empty($_SESSION['role'])) {
        $roleid = $DB->get_field('role', 'id', array('shortname' => $_SESSION['role']));
    }
    $userlist = implode(',', $data->email);
    $data->sendinguserid = $userlist;
    $data->exportformat = $data->format;
    $data->frequency = -1;
    $data->schedule = 0;
    $data->exporttofilesystem = 1;
    $data->reportid = $data->reportid;
    $data->timecreated = time();
    $data->timemodified = 0;
    $data->userid = $USER->id;
    $data->roleid = $roleid;
    $data->nextschedule = 0;
    $insert = $DB->insert_record('block_ls_schedule', $data);
    if ($insert) {
        redirect($PAGE->url);
    }
}
echo $OUTPUT->header();
echo '<script src="'.$CFG->wwwroot . '/blocks/learnerscript/js/highcharts/highcharts.js"></script>';

echo html_writer::start_tag('div', array());

if (!empty($role) || is_siteadmin()) {
    $configuredinstances = $DB->count_records('block_instances', array(
                                'pagetypepattern' => $pagepattentype, 'subpagepattern' => $subpagepatterntype));
    $reports = $DB->get_records('block_learnerscript',array('visible'=>1,'global'=>1),'','id');

    $editingon = false;
    if (is_siteadmin() || has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $editingon = true;
        $output = $PAGE->get_renderer('block_reportdashboard');
        $dashboardheader = new \block_reportdashboard\output\dashboardheader((object)array("editingon" => $editingon,'configuredinstances' => $configuredinstances, 'dashboardurl' => $dashboardurl));
        echo  $output->render($dashboardheader);
    }

    if (!empty($reports)) {
        $editingon = false;
        if (is_siteadmin() && isset($USER->editing) && $USER->editing) {
            $editingon = true;
        }
        // $output = $PAGE->get_renderer('block_reportdashboard');
        // $dashboardheader = new \block_reportdashboard\output\dashboardheader((object)array("editingon" => $editingon,'configuredinstances' => $configuredinstances, 'dashboardurl' => $dashboardurl));
        // echo  $output->render($dashboardheader);

        echo html_writer::start_tag('div', array('class' => 'width-container'));
        echo $OUTPUT->blocks('side-db-first', 'width-default width-3');
        echo $OUTPUT->blocks('side-db-second', 'width-default width-3');
        echo $OUTPUT->blocks('side-db-third', 'width-default width-3');
        echo $OUTPUT->blocks('side-db-four', 'width-default width-3');
        echo html_writer::end_tag('div');


       
        echo html_writer::start_tag('div', array('class' => 'width-container'));
        echo $OUTPUT->blocks('learning-first', 'width-default width-2');
        echo $OUTPUT->blocks('learning-second', 'width-default width-2');
        echo $OUTPUT->blocks('learning-third', 'width-default width-2');
        echo $OUTPUT->blocks('learning-fourth', 'width-default width-2');
        echo $OUTPUT->blocks('learning-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('learning-sixth', 'width-default width-2');
        echo html_writer::end_tag('div');
        echo html_writer::start_tag('div', array('class' => 'width-container'));
        echo $OUTPUT->blocks('first-maindb', 'width-default width-12');
        echo html_writer::end_tag('div');
        echo html_writer::start_tag('div', array('class' => 'width-container'));
        echo $OUTPUT->blocks('learner-first', 'width-default width-2');
        echo $OUTPUT->blocks('learner-second', 'width-default width-2');
        echo $OUTPUT->blocks('learner-third', 'width-default width-2');
        echo $OUTPUT->blocks('learner-fourth', 'width-default width-2');
        echo $OUTPUT->blocks('learner-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('learner-sixth', 'width-default width-2');
        echo html_writer::end_tag('div');
        echo html_writer::start_tag('div', array('class' => 'width-container'));
        echo $OUTPUT->blocks('learner-maindb', 'width-default width-12');
        echo html_writer::end_tag('div');
        $style = '';
        $class = '';

        echo html_writer::start_tag('div', array('class' => 'width-container reports-act-graphs ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('ol-one', 'width-default width-2 ml0');
        echo $OUTPUT->blocks('ol-second', 'width-default width-2');
        echo $OUTPUT->blocks('ol-third', 'width-default width-2');
        echo $OUTPUT->blocks('ol-fourth', 'width-default width-2');
        echo $OUTPUT->blocks('ol-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('ol-sixth', 'width-default width-2');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container reports-act-graphs ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('reportdb-one', 'width-default width-4.5 ml0');
        echo $OUTPUT->blocks('reportdb-second', 'width-default width-4.5');
        //echo $OUTPUT->blocks('reportdb-third', 'width-default width-4');
        echo html_writer::end_tag('div');
        echo html_writer::start_tag('div', array('class' => 'width-container reports-act-graphs ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('reportcert-one', 'width-default width-5p');
        echo $OUTPUT->blocks('reportcert-two', 'width-default width-5p');
        echo $OUTPUT->blocks('reportcert-third', 'width-default width-5p');
        echo $OUTPUT->blocks('reportcert-four', 'width-default width-5p');
        echo $OUTPUT->blocks('reportcert-fifth', 'width-default width-5p');
        echo html_writer::end_tag('div');



        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('center-first', 'width-default width-8');
        echo $OUTPUT->blocks('center-second', 'width-default width-4');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('reports-db-one', 'width-default width-6');
        echo $OUTPUT->blocks('reports-db-two', 'width-default width-6');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('side-db-main', 'width-default width-12');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('side-db-one', 'width-default width-6');
        echo $OUTPUT->blocks('side-db-two', 'width-default width-6');
        echo html_writer::end_tag('div');
        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('side-db-three', 'width-default width-12');
        echo html_writer::end_tag('div');
        $style = '';
        $class = '';



        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('lp-one', 'width-default width-2 ml0');
        echo $OUTPUT->blocks('lp-second', 'width-default width-2');
        echo $OUTPUT->blocks('lp-third', 'width-default width-2');
        echo $OUTPUT->blocks('lp-fourth', 'width-default width-2');
        echo $OUTPUT->blocks('lp-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('lp-sixth', 'width-default width-2');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('learn-one', 'width-default width-4 ml0');
        echo $OUTPUT->blocks('learn-second', 'width-default width-4');
        echo $OUTPUT->blocks('learn-third', 'width-default width-4');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('lp-main', 'width-default width-12');
        echo html_writer::end_tag('div');
        $style = '';
        $class = '';
        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('program-one', 'width-default width-2 ml0');
        echo $OUTPUT->blocks('program-second', 'width-default width-2');
        echo $OUTPUT->blocks('program-third', 'width-default width-2');
        echo $OUTPUT->blocks('program-four', 'width-default width-2');
        echo $OUTPUT->blocks('program-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('program-sixth', 'width-default width-2');        
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('program-main', 'width-default width-12');
        echo html_writer::end_tag('div');



        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('labs-one', 'width-default width-2 ml0');
        echo $OUTPUT->blocks('labs-second', 'width-default width-2');
        echo $OUTPUT->blocks('labs-third', 'width-default width-2');
        echo $OUTPUT->blocks('labs-fourth', 'width-default width-2');
        echo $OUTPUT->blocks('labs-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('labs-sixth', 'width-default width-2');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('exam-one', 'width-default width-6 ml0');
        echo $OUTPUT->blocks('exam-second', 'width-default width-6');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('labs-db-main', 'width-default width-12');
        echo html_writer::end_tag('div');
        $style = '';
        $class = '';

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('assess-one', 'width-default width-2 ml0');
        echo $OUTPUT->blocks('assess-second', 'width-default width-2');
        echo $OUTPUT->blocks('assess-third', 'width-default width-2');
        echo $OUTPUT->blocks('assess-fourth', 'width-default width-2');
        echo $OUTPUT->blocks('assess-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('assess-sixth', 'width-default width-2');
        echo html_writer::end_tag('div');
        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('cert-one', 'width-default width-6 ml0');
        echo $OUTPUT->blocks('cert-second', 'width-default width-6');
        echo html_writer::end_tag('div');        

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('certifi-one', 'width-default width-4 ml0');
        echo $OUTPUT->blocks('certifi-second', 'width-default width-4');
        echo $OUTPUT->blocks('certifi-third', 'width-default width-4');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('assess-db-main', 'width-default width-12');
        echo html_writer::end_tag('div');
        $style = '';
        $class = '';

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('webinars-one', 'width-default width-2 ml0');
        echo $OUTPUT->blocks('webinars-second', 'width-default width-2');
        echo $OUTPUT->blocks('webinars-third', 'width-default width-2');
        echo $OUTPUT->blocks('webinars-fourth', 'width-default width-2');
        echo $OUTPUT->blocks('webinars-fifth', 'width-default width-2');
        echo $OUTPUT->blocks('webinars-sixth', 'width-default width-2');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('compliance-one', 'width-default width-4 ml0');
        echo $OUTPUT->blocks('compliance-second', 'width-default width-4');
        echo $OUTPUT->blocks('compliance-third', 'width-default width-4');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('webinars-db-main', 'width-default width-12');
        echo html_writer::end_tag('div');
        $style = '';
        $class = '';

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('classroom-one', 'width-default width-3 ml0');
        echo $OUTPUT->blocks('classroom-second', 'width-default width-3');
        echo $OUTPUT->blocks('classroom-third', 'width-default width-3');
        echo $OUTPUT->blocks('classroom-four', 'width-default width-3');
        echo html_writer::end_tag('div');

        echo html_writer::start_tag('div', array('class' => 'width-container ' . $class, 'style' => $style));
        echo $OUTPUT->blocks('classroom-main', 'width-default width-12');
        echo html_writer::end_tag('div');

    }
    // if ($reportdashboardstatus) {
    //     echo '<div class="reportslist" style="display:none;">';
    //     $rolereports = (new ls)->listofreportsbyrole();
    //     if (!empty($rolereports)) {
    //         $reportselect = new reportselect_form($CFG->wwwroot . $seturl);
    //         $reportselect->display();
    //     } else {
    //         echo '<div class="alert alert-info">' . get_string('customreportsnotavailable', 'block_reportdashboard') . '</div>';
    //     }
    //     echo '</div>';
    // }
    // if ($reporttilestatus) {
    //     echo '<div class="statistics_reportslist" style="display:none;">';
    //     $staticreports = (new ls)->listofreportsbyrole(false, $statistics = true);
    //     if (!empty($staticreports)) {
    //         $reporttiles = new reporttiles_form($CFG->wwwroot . $seturl);
    //         $reporttiles->display();
    //     } else {
    //         echo '<div class="alert alert-info">' . get_string('statisticsreportsnotavailable',
    //                     'block_reportdashboard') . '</div>';
    //     }
    //     echo '</div>';
    // }
    echo "<input type='hidden' name='dashboardurl' id='ls_dashboardurl' class = 'report_dashboardurl'  value='" . $dashboardurl . "' />";
	echo '<div class="reportslist" style="display:none;">';
        echo '</div>';
        echo '<div class="statistics_reportslist" style="display:none;">';
        echo '</div>';    
if($configuredinstances > 0){
        echo "<input type='hidden' name='ls_fstartdate' id='ls_fstartdate' value='0' />";
        echo "<input type='hidden' name='ls_fenddate' id='ls_fenddate' value='" . time() . "' />";
        echo "<input type='hidden' name='filter_course' id='ls_courseid' class = 'report_courses'  value='" . $dashboardcourseid . "' />";
        echo "<input type='hidden' name='filter_organization' id='ls_costcenterid' class = 'report_costcenter'  value='" . $dashboardcostcenterid . "' />";
        echo "<input type='hidden' name='filter_department' id='ls_departmentid' class = 'report_department'  value='" . $dashboarddepartmentid . "' />";
        echo "<input type='hidden' name='filter_subdepartment' id='ls_subdepartmentid' class = 'report_subdepartment'  value='" . $dashboardsubdepartmentid . "' />";
        echo "<input type='hidden' name='filter_compliance' id='ls_complianceid' class = 'report_compliance'  value='" . $complianceid . "' />";
        echo '<div class="loader"></div>';
    }
} else {
    print_error("notasssignedrole", 'block_learnerscript');
}
echo html_writer::end_tag('div');
echo $OUTPUT->footer();
exit;
