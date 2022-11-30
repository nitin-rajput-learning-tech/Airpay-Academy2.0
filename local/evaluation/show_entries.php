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
 * @subpackage local_evaluation
 */


require_once("../../config.php");
require_once("lib.php");
global $PAGE, $DB, $USER;
////////////////////////////////////////////////////////
//get the params
////////////////////////////////////////////////////////
$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', false, PARAM_INT);
$showcompleted = optional_param('showcompleted', false, PARAM_INT);
$deleteid = optional_param('delete', null, PARAM_INT);
$courseid = optional_param('courseid', null, PARAM_INT);

////////////////////////////////////////////////////////
//get the objects
////////////////////////////////////////////////////////

$baseurl = new moodle_url('/local/evaluation/show_entries.php', array('id' => $id));
$PAGE->set_url(new moodle_url($baseurl, array('userid' => $userid, 'showcompleted' => $showcompleted,
        'delete' => $deleteid)));
$PAGE->set_pagelayout('standard');
$context = (new \local_evaluation\lib\accesslib())::get_module_context();
$PAGE->set_context($context);
require_login();
$evaluation = $DB->get_record('local_evaluations', array('id'=>$id));
if (empty($evaluation)) {
  print_error(get_string('feedback_not_found', 'local_evaluation'));
}

if (!has_capability('local/evaluation:viewanalysepage', $context) ) {
    print_error(get_string('no_permission_to_view_this_page', 'local_evaluation'));
}
if ($evaluation->plugin === "classroom"){
    $classroom = $DB->get_record('local_classroom', array('id' => $evaluation->instance));
    if (empty($classroom)) {
        print_error(get_string('classroom_not_found', 'local_evaluation'));
    }
    if ((has_capability('local/classroom:manageclassroom', $context)) && (!is_siteadmin()
        && (!has_capability('local/classroom:manage_multiorganizations', $context)
            && !has_capability('local/costcenter:manage_multiorganizations', $context)))) {
            if($classroom->costcenter!=$USER->open_costcenterid){
             print_error(get_string('no_permissions', 'local_evaluation'));
            }

            if ((has_capability('local/classroom:manage_owndepartments', $context)
             || has_capability('local/costcenter:manage_owndepartments', $context))) {
                if($classroom->department!=$USER->open_departmentid){
                    print_error(get_string('no_permissions', 'local_evaluation'));
                }
            }
    }
}elseif ($evaluation->plugin === "program"){
    $program = $DB->get_record('local_program', array('id' => $evaluation->instance));
    if (empty($program)) {
        print_error(get_string('program_not_found', 'local_evaluation'));
    }
    if ((has_capability('local/program:manageprogram', $context)) && (!is_siteadmin()
        && (!has_capability('local/program:manage_multiorganizations', $context)
            && !has_capability('local/costcenter:manage_multiorganizations', $context)))) {
            if($program->costcenter!=$USER->open_costcenterid){
             print_error(get_string('no_permissions', 'local_evaluation'));
            }

            if ((has_capability('local/classroom:manage_owndepartments', $context)
             || has_capability('local/costcenter:manage_owndepartments', $context))) {
                if($program->department!=$USER->open_departmentid){
                    print_error(get_string('no_permissions', 'local_evaluation'));
                }
            }
    }
}elseif ($evaluation->plugin === "certification"){
    $certification = $DB->get_record('local_certification', array('id' => $evaluation->instance));
    if (empty($certification)) {
        print_error(get_string('certification_not_found', 'local_evaluation'));
    }
    if ((has_capability('local/certification:managecertification', $context)) && (!is_siteadmin()
        && (!has_capability('local/certification:manage_multiorganizations', $context)
            && !has_capability('local/costcenter:manage_multiorganizations', $context)))) {
            if($certification->costcenter!=$USER->open_costcenterid){
             print_error(get_string('no_permissions', 'local_evaluation'));
            }

            if ((has_capability('local/classroom:manage_owndepartments', $context)
             || has_capability('local/costcenter:manage_owndepartments', $context))) {
                if($certification->department!=$USER->open_departmentid){
                    print_error(get_string('no_permissions', 'local_evaluation'));
                }
            }
    }
}else{
    $feedback = $DB->get_record('local_evaluations',array('id' => $id));
    $superusersql = "SELECT le.id FROM {local_evaluations} AS le
      JOIN {local_evaluation_users} as leu ON leu.evaluationid=le.id
      JOIN {user} AS u ON u.id=leu.userid 
      WHERE le.evaluationmode LIKE :evaluationmode AND u.open_supervisorid = :userid AND le.id = :evaluationid ";
    $paramssuperuser = array('evaluationmode' => 'SP','userid' => $USER->id, 'evaluationid' => $id);
    if(!is_siteadmin() && has_capability('local/evaluation:viewanalysepage', $context) && has_capability('local/costcenter:manage_ownorganization', $context)){
      if($feedback->costcenterid != $USER->open_costcenterid){
        print_error(get_string('no_permission_to_view_this_page', 'local_evaluation'));
      }
    }else if(!is_siteadmin() && has_capability('local/evaluation:viewanalysepage', $context) && ! has_capability('local/costcenter:manage_ownorganization', $context) && has_capability('local/costcenter:manage_owndepartments', $context)){
        if($feedback->departmentid != $USER->open_departmentid){
            print_error(get_string('no_permission_to_view_this_page', 'local_evaluation'));
        }
    }else if(!is_siteadmin() && !($DB->record_exists('local_evaluation_users', array('userid' => $USER->id, 'evaluationid' => $id)) || $DB->record_exists_sql($superusersql, $paramssuperuser))){
        print_error(get_string('no_permission_to_view_this_page', 'local_evaluation'));
    }
    
}

if ($deleteid) {
    // This is a request to delete a reponse.
    require_capability('local/evaluation:deletesubmissions', $context);
    require_sesskey();
    $evaluationstructure = new local_evaluation_completion($evaluation, true, $deleteid);
    evaluation_delete_completed($evaluationstructure->get_completed(), $evaluation);
    redirect($baseurl);
} else if ($showcompleted || $userid) {

    // Viewing individual response.
    $evaluationstructure = new local_evaluation_completion($evaluation, true, $showcompleted, $userid);
} else {
    // Viewing list of reponses.
    $evaluationstructure = new local_evaluation_structure($evaluation);
}

$responsestable = new local_evaluation_responses_table($evaluationstructure);
$anonresponsestable = new local_evaluation_responses_anon_table($evaluationstructure);

if ($responsestable->is_downloading()) {
    $responsestable->download();
}
if ($anonresponsestable->is_downloading()) {
    $anonresponsestable->download();
}

// Print the page header.
navigation_node::override_active_url($baseurl);
$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);
if ($evaluation->instance == 0) {
  $PAGE->navbar->add(get_string("manageevaluation", 'local_evaluation'), new moodle_url('index.php'));
} else {
  if ($evaluation->plugin === "classroom")
  $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?cid='.$evaluation->instance.''));
  elseif ($evaluation->plugin === "program")
  $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?pid='.$evaluation->instance.''));
  elseif ($evaluation->plugin === "certification")
  $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?ctid='.$evaluation->instance.''));
  else
  $PAGE->navbar->add(ucwords($evaluation->plugin), new moodle_url('/local/'.$evaluation->plugin.'/view.php?id='.$evaluation->instance.''));
}
$PAGE->navbar->add($evaluation->name);
$PAGE->navbar->add(get_string('viewresponse','local_evaluation'));
echo $OUTPUT->header();
/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

if ($showcompleted) {
    // Print the response of the given user.
    $completedrecord = $evaluationstructure->get_completed();

    if ($userid) {
        $usr = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
        $responsetitle = userdate($completedrecord->timemodified) . ' (' . fullname($usr) . ')';
    } else {
        $responsetitle = get_string('responses', 'local_evaluation') ;
    }

    echo $OUTPUT->heading($responsetitle, 4);

    $form = new local_evaluation_complete_form(local_evaluation_complete_form::MODE_VIEW_RESPONSE,
            $evaluationstructure, 'evaluation_viewresponse_form');
    $form->display();

    list($prevresponseurl, $returnurl, $nextresponseurl) = $userid ?
            $responsestable->get_reponse_navigation_links($completedrecord) :
            $anonresponsestable->get_reponse_navigation_links($completedrecord);

    echo html_writer::start_div('response_navigation');
    echo $prevresponseurl ? html_writer::link($prevresponseurl, get_string('previous'), ['class' => 'prev_response']) : '';
    if($evaluation->evaluationmode == 'SP'){
      $frommyteam = optional_param('myteam',null,PARAM_TEXT);
      if($frommyteam){
        $returnurl = new moodle_url('/local/myteam/team.php',array());
      }else{
        $returnurl = new moodle_url('/local/evaluation/show_entries.php',array('id'=>$evaluation->id,'sesskey'=>sesskey()));
      }
    }
    echo html_writer::link($returnurl, get_string('back'), ['class' => 'back_to_list btn btn-primary']);
    echo $nextresponseurl ? html_writer::link($nextresponseurl, get_string('next'), ['class' => 'next_response']) : '';
    echo html_writer::end_div();
} else {
    // Print the list of responses.
    // Show non-anonymous responses (always retrieve them even if current evaluation is anonymous).
    $totalrows = $responsestable->get_total_responses_count();
    if (!$evaluationstructure->is_anonymous() || $totalrows) {
        $responsestable->display();
    }

    // Show anonymous responses (always retrieve them even if current evaluation is not anonymous).
    $evaluationstructure->shuffle_anonym_responses();
    $totalrows = $anonresponsestable->get_total_responses_count();
    if ($evaluationstructure->is_anonymous() || $totalrows) {
        echo $OUTPUT->heading(get_string('anonymous_entries', 'local_evaluation', $totalrows), 4);
        $anonresponsestable->display();
    }

}
if (!$showcompleted) {
    if ($evaluation->instance != 0) {
      if ($evaluation->plugin === "classroom")
      $path = new moodle_url('/local/'.$evaluation->plugin.'/view.php?cid='.$evaluation->instance.'');
      elseif ($evaluation->plugin === "program")
      $path = new moodle_url('/local/'.$evaluation->plugin.'/view.php?pid='.$evaluation->instance.'');
      elseif ($evaluation->plugin === "certification")
      $path = new moodle_url('/local/'.$evaluation->plugin.'/view.php?ctid='.$evaluation->instance.'');
      else
      $path = new moodle_url('/local/'.$evaluation->plugin.'/view.php?id='.$evaluation->instance.'');
    } else {
      if (!has_capability('local/evaluation:edititems', $context) OR !has_capability('local/evaluation:createpublictemplate', $context) )
      $path = new moodle_url('/local/evaluation/index.php');
      else
      $path = new moodle_url('/local/evaluation/eval_view.php', array('id'=>$evaluation->id));
    }

    echo html_writer::link($path, get_string('back'), array('class'=>'backurl btn btn-primary'));
}

// Finish the page.
echo $OUTPUT->footer();

