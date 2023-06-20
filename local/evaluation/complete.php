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
global $CFG, $USER;
if(file_exists($CFG->dirroot.'/local/lib.php')){
    require_once($CFG->dirroot.'/local/lib.php');
}

evaluation_init_evaluation_session();
require_login();
$id = required_param('id', PARAM_INT);
$gopage = optional_param('gopage', 0, PARAM_INT);
$gopreviouspage = optional_param('gopreviouspage', null, PARAM_RAW);
$mode = optional_param('mode', 1, PARAM_INT); // 1=> complete mode, 2=>preview mode
$classid = optional_param('classid', 0, PARAM_INT); // ILT id
$teamuserid = optional_param('teamuserid', 0, PARAM_INT); // ILT id
$evaluation = $DB->get_record("local_evaluations", array("id" => $id), '*', MUST_EXIST);
if (empty($evaluation)) {
  print_error(get_string('feedback_not_found', 'local_evaluation'));
}
$urlparams = array('id' => $evaluation->id, 'gopage' => $gopage,'teamuserid'=>$teamuserid);
$PAGE->set_url('/local/evaluation/complete.php', $urlparams);



$context = (new \local_evaluation\lib\accesslib())::get_module_context();
$PAGE->set_context($context);
$evaluationcompletion = new local_evaluation_completion($evaluation);

// Check whether the evaluation is mapped to the given courseid.
if (!has_capability('local/evaluation:view', $context)) {
  echo $OUTPUT->header();
  echo $OUTPUT->notification(get_string('cannotaccess', 'local_evaluation'));
  echo $OUTPUT->footer();
  exit;
}

if (!$evaluationcompletion->can_complete()) {
  print_error('error');
}
$superusersql = "SELECT le.id FROM {local_evaluations} AS le
  JOIN {local_evaluation_users} as leu ON leu.evaluationid=le.id
  JOIN {user} AS u ON u.id=leu.userid 
  WHERE le.evaluationmode LIKE :evaluationmode AND u.open_supervisorid = :userid AND le.id = :evaluationid ";
$usersql = "SELECT le.id FROM {local_evaluations} AS le
  JOIN {local_evaluation_users} as leu ON leu.evaluationid=le.id
  WHERE le.evaluationmode LIKE :evaluationmode AND le.id = :evaluationid AND leu.userid = :userid ";
$paramsuser = array('userid' => $USER->id, 'evaluationid' => $id, 'evaluationmode' => 'SE');
$paramssuperuser = array('evaluationmode' => 'SP','userid' => $USER->id, 'evaluationid' => $id);

if(!($DB->record_exists_sql($usersql, $paramsuser) || $DB->record_exists_sql($superusersql, $paramssuperuser))){
  print_error(get_string('dont_have_permission', 'local_evaluation'));
}else if(!$evaluation->visible){
  print_error(get_string('dont_have_permission', 'local_evaluation'));// hidden feedback
}

if ($evaluation->instance == 0) {
  if($evaluation->evaluationmode =='SP'){
    $PAGE->navbar->add(get_string('le_myteam', 'local_evaluation'), new moodle_url('/local/myteam/team.php'));
  }else{
    if(is_siteadmin() || has_capability('local/evaluation:addinstance', $context)){
    $PAGE->navbar->add(get_string('manageevaluation', 'local_evaluation'), new moodle_url('index.php'));
  }
  }
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
$PAGE->navbar->add(get_string('evaluation:complete', 'local_evaluation'));
$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);
$PAGE->set_pagelayout('incourse');

// Check if the evaluation is open (timeopen, timeclose).
if (!$evaluationcompletion->is_open()) {
  echo $OUTPUT->header();
  echo $OUTPUT->heading(format_string($evaluation->name));
  echo $OUTPUT->box_start('generalbox boxaligncenter');

  $backurl = evaluation_return_url($evaluation->plugin, $evaluation);
  
  if (!empty($evaluation->timeopen) AND !empty($evaluation->timeclose)) {
    $dates = \local_costcenter\lib::get_userdate('d/m/Y H:i', $evaluation->timeopen).  ' <b>to</b> '  . \local_costcenter\lib::get_userdate('d/m/Y H:i', $evaluation->timeclose);
    echo $OUTPUT->notification(get_string('evaluation_is_not_open_available_from', 'local_evaluation'). ' <b>'.$dates.'</b>');
  } elseif(!empty($evaluation->timeopen) AND empty($evaluation->timeclose)) {
    $dates = \local_costcenter\lib::get_userdate('d/m/Y H:i', $evaluation->timeopen);
    echo $OUTPUT->notification(get_string('evaluation_is_not_open_available_from', 'local_evaluation'). ' <b>'.$dates.'</b>');
  } elseif (empty($evaluation->timeopen) AND !empty($evaluation->timeclose)) {
    $dates = \local_costcenter\lib::get_userdate('d/m/Y H:i', $evaluation->timeclose);
    echo $OUTPUT->notification(get_string('evaluation_is_not_open_closed_on', 'local_evaluation'). ' <b>'.$dates.'</b>');
  }  
  if(is_siteadmin() || has_capability('local/evaluation:addinstance', $context)){  
  echo $OUTPUT->continue_button($backurl);
  }
  echo $OUTPUT->box_end();
  echo $OUTPUT->footer();
  exit;
}


// Check if user is prevented from re-submission.
$cansubmit = $evaluationcompletion->can_submit();

// Initialise the form processing evaluation completion.
if (!$evaluationcompletion->is_empty() && $cansubmit) {
  // Process the page via the form.
  $urltogo = $evaluationcompletion->process_page($gopage, $gopreviouspage,$classid,$teamuserid);
  if ($urltogo !== null) {
      redirect(new moodle_url('/local/evaluation/userdashboard.php?tab=enrolled'));
  }
}

// Print the page header.
echo $OUTPUT->header();

if ($evaluationcompletion->is_empty()) {
  echo '<p align="center">';
  echo $OUTPUT->box_start('generalbox boxaligncenter');
  echo $OUTPUT->notification(get_string('no_items_available_yet', 'local_evaluation'));
  $backurl = evaluation_return_url($evaluation->plugin, $evaluation);  
  if(is_siteadmin() || has_capability('local/evaluation:addinstance', $context)){
    echo $OUTPUT->continue_button($backurl);
    }
  //echo $OUTPUT->continue_button($backurl);
  echo $OUTPUT->box_end();
} else if ($cansubmit) {
  if ($evaluationcompletion->just_completed()) {
    $creator = $DB->get_field('local_evaluation_users','creatorid',array('evaluationid'=>$evaluation->id,'userid'=>$USER->id));
    $type = 'feedback_completed';
    $dataobj = $evaluation->id;
    $fromuserid = $creator;
    $touserid = $USER->id;
    $notification = new \local_evaluation\notification();
    $fromuser = \core_user::get_user($creator);
    $logemail = $notification->evaluation_notification($type, $USER, $fromuser, $evaluation);
    $url = evaluation_return_url($evaluation->plugin, $evaluation);
    // Display information after the submit.
    if ($evaluation->page_after_submit) {
      echo $OUTPUT->box($evaluationcompletion->page_after_submit(), 'generalbox boxaligncenter');
    }
    if ($evaluation->site_after_submit) {
        $url = evaluation_encode_target_url($evaluation->site_after_submit);
    } 
    if(is_siteadmin() || has_capability('local/evaluation:addinstance', $context)){
      echo $OUTPUT->continue_button($backurl);
      }
    //echo $OUTPUT->continue_button($url);
  } else {
    // Display the form with the questions.
    if((!$DB->record_exists('local_evaluation_completed',  array ('userid' => $teamuserid, 'evaluation' => $id)))){
        // Display the form with the questions.
        echo $evaluationcompletion->render_items();
      } else{
        $userrecord = core_user::get_user($teamuserid);
        echo "<div class='alert alert-info'>".get_string('already_submitted_feedback', 'local_evaluation')." <b>". fullname($userrecord)."</b></div>";
      }
  }
} else {
  echo $OUTPUT->box_start('generalbox boxaligncenter');
  echo $OUTPUT->notification(get_string('this_evaluation_is_already_submitted', 'local_evaluation'));
  $backurl = evaluation_return_url($evaluation->plugin, $evaluation);
  if(is_siteadmin() || has_capability('local/evaluation:addinstance', $context)){
  echo $OUTPUT->continue_button($backurl);
  }
  echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
