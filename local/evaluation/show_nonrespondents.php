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
require_once($CFG->libdir.'/tablelib.php');

////////////////////////////////////////////////////////
//get the params
////////////////////////////////////////////////////////
$id = required_param('id', PARAM_INT);
$subject = optional_param('subject', '', PARAM_CLEANHTML);
$message = optional_param('message', '', PARAM_CLEANHTML);
$format = optional_param('format', FORMAT_MOODLE, PARAM_INT);
$messageuser = optional_param_array('messageuser', false, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$perpage = optional_param('perpage', EVALUATION_DEFAULT_PAGE_COUNT, PARAM_INT);  // how many per page
$showall = optional_param('showall', false, PARAM_INT);  // should we show all users
$PAGE->set_pagelayout('standard');
////////////////////////////////////////////////////////
//get the objects
////////////////////////////////////////////////////////

if (! $evaluation = $DB->get_record("local_evaluations", array("id"=>$id))) {
    print_error('invalidcourselocalule');
}

//this page only can be shown on nonanonymous evaluations in courses
//we should never reach this page
if ($evaluation->anonymous != EVALUATION_ANONYMOUS_NO ) {
    print_error('error');
}

$url = new moodle_url('/local/evaluation/show_nonrespondents.php', array('id'=>$evaluation->id));

$PAGE->set_url($url);

$context = (new \local_evaluation\lib\accesslib())::get_module_context($evaluation->id);

//we need the coursecontext to allow sending of mass mails

require_login();

if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

require_capability('local/evaluation:viewreports', $context);

if ($action == 'sendmessage') {
    $shortname = format_string($evaluation->name, true, array('context' => $context));
    $strevaluations = get_string("name", "local_evaluation");

    $htmlmessage = "<body id=\"email\">";

    $link1 = $CFG->wwwroot.'/local/evaluation/index.php';
    $link2 = $CFG->wwwroot.'/local/evaluation/eval_view.php?id='.$evaluation->id;

    $htmlmessage .= '<div class="navbar">'.
    '<a target="_blank" href="'.$link1.'">'.$strevaluations.'</a> &raquo; '.
    '<a target="_blank" href="'.$link2.'">'.format_string($evaluation->name, true).'</a>'.
    '</div>';

    $htmlmessage .= $message;
    $htmlmessage .= '</body>';

    $good = 1;
    if (is_array($messageuser)) {
        foreach ($messageuser as $userid) {
            $senduser = $DB->get_record('user', array('id'=>$userid));
            $eventdata = new \core\message\message();
            $eventdata->courseid         = $course->id;
            $eventdata->name             = 'message';
            $eventdata->component        = 'local_evaluation';
            $eventdata->userfrom         = $USER;
            $eventdata->userto           = $senduser;
            $eventdata->subject          = $subject;
            $eventdata->fullmessage      = html_to_text($htmlmessage);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml  = $htmlmessage;
            $eventdata->smallmessage     = '';
            $eventdata->courseid         = 0;
            $eventdata->contexturl       = $link2;
            $eventdata->contexturlname   = $evaluation->name;
            $good = $good && message_send($eventdata);
        }
        if (!empty($good)) {
            $msg = $OUTPUT->heading(get_string('messagedselectedusers'));
        } else {
            $msg = $OUTPUT->heading(get_string('messagedselectedusersfailed'));
        }
        redirect($url, $msg, 4);
        exit;
    }
}

////////////////////////////////////////////////////////
//get the responses of given user
////////////////////////////////////////////////////////

/// Print the page header
$PAGE->set_heading($evaluation->name);
$PAGE->set_title($evaluation->name);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($evaluation->name));

/// Print the main part of the page
/// Print the users with no responses
$mygroupid = 0;

// preparing the table for output
$baseurl = new moodle_url('/local/evaluation/show_nonrespondents.php');
$baseurl->params(array('id'=>$id, 'showall'=>$showall));

$tablecolumns = array('userpic', 'fullname', 'status');
$tableheaders = array(get_string('userpic'), get_string('fullnameuser'), get_string('status'));

if (has_capability('moodle/course:bulkmessaging', $context)) {
    $tablecolumns[] = 'select';
    $tableheaders[] = get_string('select');
}

$table = new flexible_table('evaluation-shownonrespondents-'.$evaluation->id);

$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl);

$table->sortable(true, 'lastname', SORT_DESC);
$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'showentrytable');
$table->set_attribute('class', 'generaltable generalbox');
$table->set_control_variables(array(
            TABLE_VAR_SORT    => 'ssort',
            TABLE_VAR_IFIRST  => 'sifirst',
            TABLE_VAR_ILAST   => 'silast',
            TABLE_VAR_PAGE    => 'spage'
            ));

$table->no_sorting('select');
$table->no_sorting('status');

$table->setup();

if ($table->get_sql_sort()) {
    $sort = $table->get_sql_sort();
} else {
    $sort = '';
}

$usedgroupid = false;
$matchcount = evaluation_count_incomplete_users($evaluation, $usedgroupid);
$table->initialbars(false);

if ($showall) {
    $startpage = false;
    $pagecount = false;
} else {
    $table->pagesize($perpage, $matchcount);
    $startpage = $table->get_page_start();
    $pagecount = $table->get_page_size();
}

// Return students record including if they started or not the evaluation.
$students = evaluation_get_incomplete_users($evaluation, $usedgroupid, $sort, $startpage, $pagecount, true);
//####### viewreports-start
//print the list of students
echo $OUTPUT->heading(get_string('non_respondents_students', 'local_evaluation', $matchcount), 4);
echo isset($groupselect) ? $groupselect : '';
echo '<div class="clearer"></div>';

if (empty($students)) {
    echo $OUTPUT->notification(get_string('noexistingparticipants', 'enrol'));
} else {

    $canbulkmessaging = has_capability('moodle/course:bulkmessaging', $context);
    if ($canbulkmessaging) {
        echo '<form class="mform" action="show_nonrespondents.php" method="post" id="evaluation_sendmessageform">';
    }

    foreach ($students as $student) {
        //userpicture and link to the profilepage
        $profileurl = $CFG->wwwroot.'/user/view.php?id='.$student->id.'&amp;course=1';
        $profilelink = '<strong><a href="'.$profileurl.'">'.fullname($student).'</a></strong>';
        $data = array($OUTPUT->user_picture($student, array('courseid' => 1)), $profilelink);

        if ($student->evaluationstarted) {
            $data[] = get_string('started', 'local_evaluation');
        } else {
            $data[] = get_string('not_started', 'local_evaluation');
        }

        //selections to bulk messaging
        if ($canbulkmessaging) {
            $data[] = '<input type="checkbox" class="usercheckbox" name="messageuser[]" value="'.$student->id.'" />';
        }
        $table->add_data($data);
    }
    $table->print_html();

    $allurl = new moodle_url($baseurl);

    if ($showall) {
        $allurl->param('showall', 0);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showperpage', '', EVALUATION_DEFAULT_PAGE_COUNT)),
                                    array(), 'showall');

    } else if ($matchcount > 0 && $perpage < $matchcount) {
        $allurl->param('showall', 1);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showall', '', $matchcount)), array(), 'showall');
    }
    if (has_capability('moodle/course:bulkmessaging', $context)) {
        echo '<div class="buttons"><br />';
        echo '<input type="button" id="checkall" value="'.get_string('selectall').'" /> ';
        echo '<input type="button" id="checknone" value="'.get_string('deselectall').'" /> ';
        echo '</div>';
        echo '<fieldset class="clearfix">';
        echo '<legend class="ftoggler">'.get_string('send_message', 'local_evaluation').'</legend>';
        echo '<div>';
        echo '<label for="evaluation_subject">'.get_string('subject', 'local_evaluation').'&nbsp;</label>';
        echo '<input type="text" id="evaluation_subject" size="50" maxlength="255" name="subject" value="'.s($subject).'" />';
        echo '</div>';
        print_textarea(true, 15, 25, 30, 10, "message", $message);
        print_string('formathtml');
        echo '<input type="hidden" name="format" value="'.FORMAT_HTML.'" />';
        echo '<br /><div class="buttons">';
        echo '<input type="submit" name="send_message" value="'.get_string('send', 'local_evaluation').'" />';
        echo '</div>';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo '<input type="hidden" name="action" value="sendmessage" />';
        echo '<input type="hidden" name="id" value="'.$id.'" />';
        echo '</fieldset>';
        echo '</form>';
        //include the needed js
        $evaljs = array('name'=>'local_evaluation', 'fullpath'=>'/local/evaluation/evaluation.js');
        $PAGE->requires->js_init_call('M.local_evaluation.init_sendmessage', null, false, $evaljs);
    }
}

/// Finish the page

echo $OUTPUT->footer();

