<?php

/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This classroom is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This classroom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this classroom.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage local_classroom
 */

namespace local_classroom\output;

require_once($CFG->dirroot . '/local/classroom/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
defined('MOODLE_INTERNAL') || die;

use html_table;
use html_writer;
use local_classroom\classroom;
use plugin_renderer_base;
use user_course_details;
use moodle_url;
use stdClass;
use single_button;
use core_completion\progress;

class renderer extends plugin_renderer_base
{
    /**
     * [render_classroom description]
     * @method render_classroom
     * @param  \local_classroom\output\classroom $page [description]
     * @return [type]                                  [description]
     */
    public function render_classroom(\local_classroom\output\classroom $page)
    {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_classroom/classroom', $data);
    }
    /**
     * [render_form_status description]
     * @method render_form_status
     * @param  \local_classroom\output\form_status $page [description]
     * @return [type]                                    [description]
     */
    public function render_form_status(\local_classroom\output\form_status $page)
    {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_classroom/form_status', $data);
    }
    /**
     * [render_session_attendance description]
     * @method render_session_attendance
     * @param  \local_classroom\output\session_attendance $page [description]
     * @return [type]                                           [description]
     */
    public function render_session_attendance(\local_classroom\output\session_attendance $page)
    {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_classroom/session_attendance', $data);
    }
    /**
     * Display the classroom tabs
     * @return string The text to render
     */
    public function get_classroom_tabs($view_type = 'card')
    {

        global $CFG, $OUTPUT, $DB;
        $stable = new stdClass();
        $stable->thead = true;
        $stable->start = 0;
        $stable->length = -1;
        $stable->search = '';
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
        $templateName = '';
        $cardClass = '';
        $perpage = '';

        $all_tab = $new_tab = $active_tab = $hold_tab = $cancelled_tab = $completed_tab = false;

        $all_tab = true;

        if (has_capability('local/classroom:view_newclassroomtab', $categorycontext)) {
            $new_tab = true;
        }

        $active_tab = true;

        if (has_capability('local/classroom:view_holdclassroomtab', $categorycontext)) {
            $hold_tab = true;
        }

        $cancelled_tab = true;

        $completed_tab = true;

        if (is_siteadmin()) {
            $templateName = 'local_classroom/classrooms_list';
            $cardClass = 'col-md-6 col-12';
            $perpage = 6;
            if ($view_type == 'table') {
                $templateName = 'local_classroom/classrooms_catalog_list';
                $cardClass = 'tableformat';
                $perpage = 12;
            }
        }
        $viewcardlist = false;
        if (is_siteadmin() || has_capability('local/classroom:manageclassroom', $categorycontext)) {
            $perpage = 6;

            $viewcardlist = true;
            if ($view_type == 'card') {
                $cardlisturl = new moodle_url('/local/classroom/index.php?formattype=table');
                $cardlistlabel = get_string('listtype', 'local_classroom');
                $cardlistclass = 'icon fa fa-bars fa-fw';
                $templateName = 'local_classroom/classrooms_list';
            } else {
                $cardlisturl = new moodle_url('/local/classroom/index.php?formattype=card');
                $cardlistlabel = get_string('cardtype', 'local_classroom');
                $cardlistclass = 'icon fa fa-th fa-fw';
                $templateName = 'local_classroom/classrooms_catalog_list';
            }
        }
        $classroomtabslist = [
            //'classroomtabslist' => $tabscontent,
            'contextid' => $categorycontext->id,
            'plugintype' => 'local',
            'plugin_name' => 'classroom',
            'all_tab' => $all_tab,
            'new_tab' => $new_tab,
            'active_tab' => $active_tab,
            'hold_tab' => $hold_tab,
            'cancelled_tab' => $cancelled_tab,
            'completed_tab' => $completed_tab,
            'creataclassroom' => ((has_capability(
                'local/classroom:manageclassroom',
                $categorycontext
            ) && has_capability(
                'local/classroom:createclassroom',
                $categorycontext
            )) || is_siteadmin()) ? true : false,
            'view_type' => $view_type,
            'templatename' => $templateName,
            'cardclass' => $cardClass,
            'perpage' => $perpage,
            'viewcardlist' => $viewcardlist,
            'cardlisturl' => $cardlisturl,
            'cardlistlabel' => $cardlistlabel,
            'cardlistclass' => $cardlistclass

        ];
        if ((has_capability('local/location:manageinstitute', $categorycontext) || has_capability('local/location:viewinstitute', $categorycontext)) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
            $classroomtabslist['location_url'] = $CFG->wwwroot . '/local/location/index.php?component=classroom';
        }
        if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
            $classroomtabslist['request_url'] = $CFG->wwwroot . '/local/request/index.php?component=classroom';
        }
        if (is_siteadmin() || (has_capability('local/classroom:createclassroom', $categorycontext) || has_capability('local/classroom:updateclassroom', $categorycontext) || has_capability('local/classroom:manageclassroom', $categorycontext))) {
            $sql = "SELECT id,name FROM {block_learnerscript} WHERE category = 'local_classroom'";
            $classroomreports = $DB->get_records_sql($sql);
            foreach ($classroomreports as $classroom) {
                $classroomtabslist['reports'][] = ['classroomid' => $classroom->id, 'name' => $classroom->name];
            }
        }

        return $this->render_from_template('local_classroom/classroomtabs', $classroomtabslist);
    }
    /**
     * [viewclassrooms description]
     * @method viewclassrooms
     * @param  [type]         $stable [description]
     * @return [type]                 [description]
     */
    public function viewclassrooms($stable)
    {
        global $OUTPUT, $CFG, $DB;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
        $includesfile = false;
        if (file_exists($CFG->dirroot . '/local/includes.php')) {
            $includesfile = true;
            require_once($CFG->dirroot . '/local/includes.php');
            $includes = new user_course_details();
        }
        if ($stable->thead) {
            $classrooms = (new classroom)->classrooms($stable);
            if ($classrooms['classroomscount'] > 0) {
                $table = new html_table();
                $table->head = array('', '');
                $table->id = 'viewclassrooms';
                $return = html_writer::table($table);
            } else {
                $return = "<div class='alert alert-info text-center'>" . get_string('noclassrooms', 'local_classroom') . "</div>";
            }
        } else {
            $classrooms = (new classroom)->classrooms($stable);
            $data = array();
            $classroomchunks = array_chunk($classrooms['classrooms'], 2);
            $startTime = microtime(true);
            foreach ($classroomchunks as $cr_data) {
                $row = [];
                foreach ($cr_data as $sdata) {
                    $line = array();
                    //-----class room summary image
                    if ($sdata->classroomlogo > 0) {
                        $classesimg = (new classroom)->classroom_logo($sdata->classroomlogo);
                        if ($classesimg == false) {
                            if ($includesfile) {
                                $classesimg = $includes->get_classes_summary_files($sdata);
                            }
                        }
                    } else {
                        if ($includesfile) {
                            $classesimg = $includes->get_classes_summary_files($sdata);
                        }
                    }

                    //-------data variables
                    $classname = $sdata->name;
                    $classname_string = strlen($classname) > 48 ? substr($classname, 0, 48) . "..." : $classname;
                    $usercreated = $sdata->usercreated;

                    // $startdate = \local_costcenter\lib::get_userdate("d/m/Y", $sdata->startdate);
                    // $enddate = \local_costcenter\lib::get_userdate("d/m/Y", $sdata->enddate);
                    $startdate = \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->startdate);
                    $enddate = \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->enddate);


                    $description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($sdata->description));
                    $isdescription = '';
                    if (empty($description)) {
                        $isdescription = false;
                    } else {
                        $isdescription = true;
                        if (strlen($description) > 75) {
                            $decsriptionCut = substr($description, 0, 75);
                            $decsriptionstring = \local_costcenter\lib::strip_tags_custom(html_entity_decode($decsriptionCut), array('overflowdiv' => false, 'noclean' => false, 'para' => false));
                        } else {
                            $decsriptionstring = "";
                        }
                    }

                    $enrolled_users = $sdata->enrolled_users;
                    list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $sdata->open_path);;
                    if (empty($ctr)) {
                        $departmentname = 'All';
                        $departmenttitle = 'All departments';
                    } else {
                        $classroomdepartment = $DB->get_fieldset_select('local_costcenter', 'fullname', " CONCAT(',',$ctr,',') LIKE CONCAT('%,',id,',%') ", array()); //FIND_IN_SET(id, '$sdata->department')
                        $departmentname = (count($classroomdepartment) > 1) ? $classroomdepartment[0] . '...' : $classroomdepartment[0];
                        $departmenttitle = implode(', ', $classroomdepartment);
                    }


                    switch ($sdata->status) {
                        case CLASSROOM_NEW:
                            $line['classroomstatusclass'] = 'classroomnew';
                            $line['crstatustitle'] = get_string('newclasses', 'local_classroom');
                            break;
                        case CLASSROOM_ACTIVE:

                            $line['classroomstatusclass'] = 'classroomactive';
                            $line['crstatustitle'] = get_string('activeclasses', 'local_classroom');

                            break;
                        case CLASSROOM_HOLD:

                            $line['classroomstatusclass'] = 'classroomhold';
                            $line['crstatustitle'] = get_string('holdclasses', 'local_classroom');

                            break;
                        case CLASSROOM_CANCEL:

                            $line['classroomstatusclass'] = 'classroomcancelled';
                            $line['crstatustitle'] = get_string('cancelledclasses', 'local_classroom');

                            break;
                        case CLASSROOM_COMPLETED:

                            $line['classroomstatusclass'] = 'classroomcompleted';
                            $line['crstatustitle'] = get_string('completedclasses', 'local_classroom');

                            break;
                    }
                    $classroom_actionstatus = $this->classroom_actionstatus_markup($sdata);
                    $line['seatallocation'] = empty($sdata->capacity) ? 'N/A' : $sdata->capacity;
                    $line['classesimg'] = $classesimg;
                    $line['classname'] = $classname;
                    $line['classname_string'] = $classname_string;
                    $line['usercreated'] = fullname($user);
                    $line['startdate'] = $startdate;
                    $line['enddate'] = $enddate;
                    $line['description'] =  \local_costcenter\lib::strip_tags_custom(html_entity_decode($sdata->description));
                    $line['descriptionstring'] = $decsriptionstring;
                    $line['isdescription'] = $isdescription;
                    $line['classroom_actionstatus'] = array_values(($classroom_actionstatus));
                    $classroomcoursessql = "SELECT c.id, c.fullname
                                              FROM {course} AS c
                                              JOIN {local_classroom_courses} AS cc ON cc.courseid = c.id
                                             WHERE c.visible = 1 AND cc.classroomid = :classroomid ";

                    $classroomcourses = $DB->get_records_sql($classroomcoursessql, array('classroomid' => $sdata->id), 0, 2);
                    $line['courses'] = array();
                    if (!empty($classroomcourses)) {
                        foreach ($classroomcourses as $classroomcourse) {
                            $courseslimit = true;
                            $coursename = strlen($classroomcourse->fullname) > 15 ? substr($classroomcourse->fullname, 0, 15) . "..." : $classroomcourse->fullname;
                            $line['courses'][] = array('coursesdata' => '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $classroomcourse->id . '" title="' . $classroomcourse->fullname . '">' . $coursename . '</a>');
                        }
                    }
                    $line['enrolled_users'] = $enrolled_users;
                    $line['departmentname'] = $departmentname;
                    $line['departmenttitle'] = $departmenttitle;
                    $line['classroomid'] = $sdata->id;
                    $classroomtrainerssql = "SELECT u.id, u.picture, u.firstname, u.lastname,
                                        u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.imagealt, u.email
                                              FROM {user} AS u
                                              JOIN {local_classroom_trainers} AS ct ON ct.trainerid = u.id
                                              WHERE u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND ct.classroomid = :classroomid ";

                    $classroomtrainers = $DB->get_records_sql($classroomtrainerssql, array('classroomid' => $sdata->id), 0, 2);
                    $line['trainers']  = array();
                    if (!empty($classroomtrainers)) {
                        $trainerslimit = false;
                        foreach ($classroomtrainers as $classroomtrainer) {
                            $trainerslimit = true;
                            $trainername = strlen(fullname($classroomtrainer)) > 8 ? substr(fullname($classroomtrainer), 0, 8) . "..." : fullname($classroomtrainer);
                            $classroomtrainerpic = $OUTPUT->user_picture($classroomtrainer, array('size' => 35, 'class' => 'trainer_img', 'link' => false));
                            $line['trainers'][] = array('classroomtrainerpic' => $classroomtrainerpic, 'trainername' => $trainername, 'trainerdesignation' => '');
                        }
                    }
                    if (count($classroomtrainers) > 2) {
                        $trainerslimit = false;
                        $line['moretrainers'] = array_slice($line['trainers'], 0, 2);
                    }

                    $line['trainerslimit'] = $trainerslimit;
                    $line['editicon'] = $OUTPUT->image_url('t/edit');
                    $line['deleteicon'] = $OUTPUT->image_url('t/delete');
                    $line['assignusersicon'] = $OUTPUT->image_url('t/assignroles');
                    $classroomcompletion_id = $DB->get_field('local_classroom_completion', 'id', array('classroomid' => $sdata->id));
                    if (!$classroomcompletion_id) {
                        $classroomcompletion_id = 0;
                    }

                    $line['classroomcompletion'] = false;
                    $mouse_overicon = false;
                    if ((has_capability('local/classroom:manageclassroom', $categorycontext) || is_siteadmin())) {
                        $line['action'] = true;
                    }

                    if ((has_capability('local/classroom:editclassroom', $categorycontext) || is_siteadmin())) {
                        $line['edit'] =  true;
                        $mouse_overicon = true;
                    }

                    if ((has_capability('local/classroom:deleteclassroom', $categorycontext) || is_siteadmin())) {
                        $line['delete'] =  true;
                        $mouse_overicon = true;
                    }
                    if ((has_capability('local/classroom:manageusers', $categorycontext) || is_siteadmin())) {
                        $line['assignusers'] =  true;
                        $line['assignusersurl'] = new moodle_url("/local/classroom/enrollusers.php?cid=" . $sdata->id . "");
                        $mouse_overicon = true;
                    }
                    if ((has_capability('local/classroom:classroomcompletion', $categorycontext) || is_siteadmin())) {
                        $line['classroomcompletion'] =  true;
                    }
                    $line['classroomcompletion_id'] = $classroomcompletion_id;
                    $line['mouse_overicon'] = $mouse_overicon;
                    $row[] = $this->render_from_template('local_classroom/browseclassroom', $line);
                }
                if (!isset($row[1])) {
                    $row[1] = '';
                }
                $time = number_format((microtime(true) - $startTime), 4);
                $data[] = $row;
            }

            $return = array(
                "recordsTotal" => $classrooms['classroomscount'],
                "recordsFiltered" => $classrooms['classroomscount'],
                "data" => $data,
                "time" => $time
            );
        }
        return $return;
    }

    /**
     * [get_classrooms] to get the Clasrooms by status given
     * @method get_classrooms
     * @param  [type]         $stable [description]
     * @return [type]                 [description]
     */
    // public function get_classrooms($status) {
    //     global $OUTPUT;

    //     $options = json_encode(array('targetID' => 'all', 'templateName' => 'local_classroom/classrooms_list', 'methodName' => 'local_classroom_get_classrooms',  'perPage' => 6, 'cardClass' => 'col-md-6 col-12', 'viewType' => 'card'));
    //     $dataoptions = json_encode(array('status' => $status));

    //     $categorycontext = [
    //                 "targetID" => 'all',
    //                 "options" => $options,
    //                 "dataoptions" => $dataoptions,
    //                 ];
    //     $return = $OUTPUT->render_from_template('local_costcenter/cardPaginate', $categorycontext);
    //     return $return;
    // }


    /**
     * [viewclassroomsessions description]
     * @method viewclassroomsessions
     * @param  [type]                $sessionsdata [description]
     * @param  [type]                $stable      [description]
     * @return [type]                             [description]
     */
    public function viewclassroomsessions($sessions, $classroomid, $stable = null, $triggertype = 'classroom')
    {
        global $OUTPUT, $CFG, $DB, $USER;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
        $data = array();
        $createsession = false;
        if (has_capability('local/classroom:createsession', $categorycontext) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
            $createsession = true;
        }
        if ((has_capability('local/classroom:editsession', $categorycontext) || has_capability('local/classroom:deletesession', $categorycontext) || has_capability('local/classroom:takesessionattendance', $categorycontext)) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
            $createsession = true;
        }
        foreach ($sessions as $sdata) {
            $line = array();
            $line['cfgwwwroot'] = $CFG->wwwroot;
            $line['id'] = $sdata->id;
            $line['name'] = $sdata->name;
            $line['sessionname'] = addslashes($sdata->name);
            if ($triggertype != 'classroom') {
                $classroomid = $sdata->classroomid;
                $line['classroomname'] = $DB->get_field('local_classroom', 'name', array('id' => $classroomid));
            }
            $line['date'] = userdate($sdata->timestart, '%d/%m/%Y');
            $line['starttime'] = userdate($sdata->timestart, "%d/%m/%Y %H:%M");
            $line['endtime'] = userdate($sdata->timefinish, "%d/%m/%Y %H:%M");

            $link = get_string('pluginname', 'local_classroom');
            if ($sdata->onlinesession == 1) {

                $moduleids = $DB->get_field('modules', 'id', array('name' => $sdata->moduletype));
                if ($moduleids) {
                    $moduleid = $DB->get_field('course_modules', 'id', array('instance' => $sdata->moduleid, 'module' => $moduleids));
                    if ($moduleid) {
                        $link = html_writer::link($CFG->wwwroot . '/mod/' . $sdata->moduletype . '/view.php?id=' . $moduleid, get_string('join', 'local_classroom'), array('title' => get_string('join', 'local_classroom')));

                        if (!is_siteadmin() && !has_capability('local/classroom:manageclassroom', $categorycontext)) {
                            $userenrolstatus = $DB->record_exists('local_classroom_users', array('classroomid' => $classroomid, 'userid' => $USER->id));

                            if (!$userenrolstatus) {
                                $link = get_string('join', 'local_classroom');
                            }
                        }
                    }
                }
            }
            $line['link'] = $link;
            $line['room'] = $sdata->room ? $sdata->room : 'N/A';

            $countfields = "SELECT COUNT(DISTINCT u.id) ";
            $params['classroomid'] = $classroomid;
            $params['confirmed'] = 1;
            $params['suspended'] = 0;
            $params['deleted'] = 0;
            $sql = " FROM {user} AS u
                        JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                         WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                            AND u.deleted = :deleted AND cu.classroomid = :classroomid";
            $classroom_totalusers     = $DB->count_records_sql($countfields . $sql, $params);

            //$classroom_totalusers = $DB->count_records('local_classroom_users', array('classroomid' => $classroomid));
            $attendedsessions_users = $DB->count_records(
                'local_classroom_attendance',
                array(
                    'classroomid' => $classroomid,
                    'sessionid' => $sdata->id, 'status' => SESSION_PRESENT
                )
            );



            if (has_capability('local/classroom:manageclassroom', $categorycontext)) {
                if ($sdata->timefinish <= time() && $sdata->attendance_status == 1) {
                    $line['status'] = get_string('completed', 'local_classroom');
                } else {
                    $line['status'] = get_string('pending', 'local_classroom');
                }
            } else {
                $attendance_status = $DB->get_field_sql("SELECT status  FROM {local_classroom_attendance} where classroomid = :classroomid and sessionid = :sessionid and userid = :userid and status = :status", array('classroomid' => $classroomid, 'sessionid' => $sdata->id, 'userid' => $USER->id, 'status' => 1));
                if ($sdata->timefinish <= time() && $attendance_status == 1) {
                    $line['status'] = get_string('completed', 'local_classroom');
                } else {
                    $line['status'] = get_string('pending', 'local_classroom');
                }
            }
            $line['attendacecount'] = $attendedsessions_users . '/' . $classroom_totalusers;
            if ($sdata->trainerid) {
                $trainer = $DB->get_record('user', array('id' => $sdata->trainerid));
                $trainerimg = $OUTPUT->user_picture($trainer, array('size' => 30)) . fullname($trainer);
                $line['trainer'] =  $trainerimg;
            } else {
                $line['trainer'] = "N/A";
            }

            $line['assignrolesicon'] = $line['deleteicon'] = $line['editicon'] = $line['action'] = false;
            if ((has_capability('local/classroom:editsession', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
                $editimg = $OUTPUT->image_url('t/edit');
                $line['editicon'] = $editimg->out_as_local_url();
            }
            if ((has_capability('local/classroom:deletesession', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {

                $deleteimg = $OUTPUT->image_url('t/delete');
                $line['deleteicon'] = $deleteimg->out_as_local_url();
            }
            if ((has_capability('local/classroom:takesessionattendance', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {

                $assignrolesimg = $OUTPUT->image_url('t/assignroles');
                $line['assignrolesicon'] = $assignrolesimg->out_as_local_url();
            }
            if ((has_capability('local/classroom:editsession', $categorycontext) || has_capability('local/classroom:deletesession', $categorycontext) || has_capability('local/classroom:takesessionattendance', $categorycontext)) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
                $line['action'] = true;
            }
            $data[] = $line;
        }
        return array('createsession' => $createsession, 'data' => $data);
    }



    /**
     * [viewclassroomfeedbacks description]
     * @method viewclassroomfeedbacks
     * @param  [type]                   $classroomid [description]
     * @param  [type]                   $stable      [description]
     * @return [type]                                [description]
     */
    public function viewclassroomfeedbacks($feedbacks, $classroomid)
    {
        global $OUTPUT, $CFG, $PAGE, $DB, $USER;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
        $exist = $DB->record_exists('local_classroom', array('id' => $classroomid, 'trainingfeedbackid' => 0));
        $exist_with_tr_fd = $DB->count_records_sql("SELECT count(id) as total FROM {local_classroom_trainers} where classroomid = :classroomid AND feedback_id>0", array('classroomid' => $classroomid));
        $exist_with_tr = $DB->count_records('local_classroom_trainers', array('classroomid' => $classroomid));
        $createfeedback = false;
        if ((has_capability('local/classroom:createfeedback', $categorycontext)) && (has_capability('local/classroom:manageclassroom', $categorycontext)) && ($exist || $exist_with_tr_fd != $exist_with_tr)) {
            $createfeedback = true;
        }
        $data = array();
        foreach ($feedbacks as $sdata) {
            $classroomtrainerssql = "SELECT CONCAT(u.firstname, ' ', u.lastname) AS fullname FROM {user} AS u JOIN {local_classroom_trainers} AS ct ON ct.trainerid = u.id
                    WHERE ct.classroomid = :classroomid AND ct.feedback_id=:feedbackid AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2";
            $params = array();
            $params['classroomid'] = $classroomid;
            $params['feedbackid'] =  $sdata->id;
            $classroomtrainer = $DB->get_field_sql($classroomtrainerssql, $params);

            $line = array();
            if (has_capability('local/classroom:createfeedback', $categorycontext)) {
                $feedbackview = true;
            } else {
                $feedbackview = false;
            }
            $line['cfgwwwroot'] = $CFG->wwwroot;
            $line['id'] = $sdata->id;
            $line['name'] = $sdata->name;
            $line['feedbackview'] = $feedbackview;
            $line['evaluationtype'] = $sdata->evaluationtype;
            if ($sdata->evaluationtype == 1) {
                $feedbacktype = get_string('training_feeddback', 'local_classroom');
                $trainer = "N/A";
            } else {
                $feedbacktype = get_string('trainer_feedback', 'local_classroom');
                $trainer = $classroomtrainer;
            }

            $line['feedbacktype'] = $feedbacktype;
            $line['trainer'] = $trainer;

            $countfields = "SELECT COUNT(DISTINCT u.id) ";
            $params['classroomid'] = $classroomid;
            $params['confirmed'] = 1;
            $params['suspended'] = 0;
            $params['deleted'] = 0;
            $mainsql = $sql = " FROM {user} AS u
                    JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                     WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                        AND u.deleted = :deleted AND cu.classroomid = :classroomid";
            $total_count     = $DB->count_records_sql($countfields . $sql, $params);

            //$total_count=$DB->count_records_sql("SELECT count(id) as total FROM {local_classroom_users} lcu where lcu.classroomid=:classroomid ",array('classroomid' => $classroomid));

            if ($sdata->evaluationtype == 1) {
                $sql .= " AND trainingfeedback = :trainingfeedback";
                $params['trainingfeedback'] = 1;
                $submitted_count     = $DB->count_records_sql($countfields . $sql, $params);
                //$submitted_count=$DB->count_records_sql("SELECT count(id) as total FROM {local_classroom_users} where classroomid = :classroomid AND trainingfeedback = :trainingfeedback",array('trainingfeedback' => 1, 'classroomid' => $classroomid));
            } else {
                $submitted_count = $DB->count_records_sql("SELECT count(fb.id) as total FROM {local_classroom_trainerfb} as fb JOIN {local_classroom_trainers} as f ON f.id=fb.clrm_trainer_id where f.classroomid=:classroomid AND f.feedback_id=:id", array('id' => $sdata->id, 'classroomid' => $classroomid));
            }

            $line['submittedcount'] = "$submitted_count/$total_count";

            if (!has_capability('local/classroom:manageclassroom', $categorycontext)) {
                if ($sdata->evaluationtype == 1) {
                    $sql = $mainsql;

                    $sql .= " AND trainingfeedback = :trainingfeedback and userid= :userid";
                    $params['trainingfeedback'] = 1;
                    $params['userid'] = $USER->id;
                    $submitted_count     = $DB->count_records_sql($countfields . $sql, $params);

                    //$submitted_count=$DB->count_records_sql("SELECT count(id) as total FROM {local_classroom_users} where classroomid = :classroomid AND trainingfeedback = :trainingfeedback and userid= :userid",array('classroomid' => $classroomid,'trainingfeedback' => $trainingfeedback,'userid' => $USER->id));
                } else {
                    $submitted_count = $DB->count_records_sql("SELECT count(fb.id) as total FROM {local_classroom_trainerfb} as fb JOIN {local_classroom_trainers} as f ON f.id=fb.clrm_trainer_id where f.classroomid =:classroomid AND f.feedback_id= :id and fb.userid = :userid", array('classroomid' => $classroomid, 'id' => $sdata->id, 'userid' => $USER->id));
                }
                if ($submitted_count == 0) {
                    $line['url'] = 'complete';
                    $line['string'] = true;
                } else {
                    $line['url'] = 'show_entries';
                    $line['string'] = false;
                }
            } elseif (has_capability('local/classroom:manageclassroom', $categorycontext)) {
                $line['url'] = 'show_entries';
                $line['string'] = false;
            } else {
                $line['url'] = $classroom_evaluationtypes[$sdata->evaluationtype];
                $line['string'] = false;
            }

            $line['deleteicon'] = $line['preview'] = $line['editicon'] = $line['action'] = false;
            if ((has_capability('local/classroom:editfeedback', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {

                $editimg = $OUTPUT->image_url('t/edit');
                $line['editicon'] = $editimg->out_as_local_url();
            }

            if ((has_capability('local/classroom:deletefeedback', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {

                $deleteimg = $OUTPUT->image_url('t/delete');
                $line['deleteicon'] = $deleteimg->out_as_local_url();
            }
            if ((has_capability('local/classroom:editfeedback', $categorycontext) || has_capability('local/classroom:deletefeedback', $categorycontext)) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
                $line['action'] = true;
            }
            $data[] = $line;
        }
        return array('createfeedback' => $createfeedback, 'data' => $data);
    }


    /**
     * [viewclassroomcourses description]
     * @method viewclassroomcourses
     * @param  [type]               $classroomid [description]
     * @return [type]                            [description]
     */
    public function viewclassroomcourses($courses, $classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
        $data = array();
        $assign_courses = false;
        if (has_capability('local/classroom:createcourse', $categorycontext) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
            $assign_courses = true;
        }


        $selfenrolmenttabcap = false;
        if ((has_capability('local/classroom:deletecourse', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
            $selfenrolmenttabcap = true;
        }

        $courseprogress = new progress();
        foreach ($courses as $sdata) {
            $line = array();
            $line['id'] = $sdata->classroomcourseinstance;
            $line['name'] = $sdata->fullname;


            if (is_siteadmin() || has_capability('local/classroom:manageclassroom', $categorycontext)) {

                $countfields = "SELECT cu.id,cu.userid ";
                $params['classroomid'] = $classroomid;
                $params['confirmed'] = 1;
                $params['suspended'] = 0;
                $params['deleted'] = 0;
                $sql = " FROM {user} AS u
                    JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                    WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                    AND u.deleted = :deleted AND cu.classroomid = :classroomid";
                $enrolledusers = $DB->get_records_sql_menu($countfields . $sql, $params);

                // $enrolledusers = $DB->get_records_menu('local_classroom_users',  array('classroomid' =>$classroomid), 'id', 'id, userid'); 

                $course_completions = $DB->get_records_sql_menu("SELECT id,userid  FROM {course_completions} WHERE course = :courseid AND timecompleted IS NOT NULL", array('courseid' => $sdata->id));
                $result = array_intersect($enrolledusers, $course_completions);

                $line['status'] = count($result) . '/' . count($enrolledusers);
            } else {
                $completionstatus = $courseprogress->get_course_progress_percentage($sdata);
                $line['status'] =  $completionstatus !== null ? round($completionstatus, 2) : '--';
            }

            $line['action'] = false;
            if ((has_capability('local/classroom:deletecourse', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {

                $deleteimg = $OUTPUT->image_url('t/delete');
                $line['deleteicon'] = $deleteimg->out_as_local_url();
            }
            if ((has_capability('local/classroom:deletecourse', $categorycontext) || is_siteadmin()) && (has_capability('local/classroom:manageclassroom', $categorycontext))) {
                $line['action'] = true;
            }
            $line['linkpath'] = $CFG->wwwroot . "/course/view.php?id=$sdata->id";
            $data[] = $line;
        }
        return array('selfenrolmenttabcap' => $selfenrolmenttabcap, 'assigncourses' => $assign_courses, 'data' => $data);
    }

    public function classroom_accessble_check($classroom)
    {
        global $DB,$USER;
        $usercostcenterpaths = $DB->get_records('local_userdata', array('userid' => $USER->id));
        $userpathinfo = [];
        foreach ($usercostcenterpaths as $userpath) {
            $userpathinfo = $userpath->costcenterpath;
        }
        $userpath_arr = explode('/', $userpathinfo);
        $module_path = explode('/', $classroom->open_path);
        $modulepathdepth = count($module_path);
        return (in_array($module_path[$modulepathdepth], $userpath_arr));    
    }
    /**
     * Display the classroom view
     * @return string The text to render
     */
    public function get_content_viewclassroom($classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        /* $core_component = new core_component();
        $block_content = '';
        $local_pluginlist = $core_component::get_plugin_list('local');
        $block_pluginlist = $core_component::get_plugin_list('block');
        */

        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);

        $stable = new stdClass();
        $stable->classroomid = $classroomid;
        $stable->thead = false;
        $stable->start = 0;
        $stable->length = 1;
        $classroom = (new classroom)->classrooms($stable);

        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $classroom->open_path);

        $departmentcount = count(array_filter(explode(',', $ctr)));
        $subdepartmentcount = count(array_filter(explode(',', $bu)));
        $daysdiff = 0;
        $unenroll = false;
        $classroom_status = $classroom->status;

        $is_classroom_accessble = $this->classroom_accessble_check($classroom);
        
        if (!has_capability('local/classroom:view_newclassroomtab', $categorycontext) && $classroom_status == 0 && !$is_classroom_accessble) {
            print_error("You don't have permissions to view this page.");
        } elseif (!has_capability('local/classroom:view_holdclassroomtab', $categorycontext) && $classroom_status == 2) {
            print_error("You don't have permissions to view this page.");
        }
        if (empty($classroom)) {
            print_error("Classroom Not Found!");
        }
        if (!has_capability('local/classroom:manageclassroom', $categorycontext) && !is_siteadmin()) {

            $now = time(); // or your date as well
            $your_date = $classroom->startdate;
            $datediff = $now - $your_date;

            $daysdiff = round($datediff / (60 * 60 * 24));

            $exists = $DB->get_field('local_classroom_users', 'usercreated', array('classroomid' => $classroomid, 'userid' => $USER->id));
            if(!$exists && !$is_classroom_accessble){
                print_error("You don't have permissions to view this page.");
            }else{
                if($exists == $USER->id)
                    $unenroll=true;
                else
                    $unenroll=false;
            }
        }
        if (file_exists($CFG->dirroot . '/local/includes.php')) {
            require_once($CFG->dirroot . '/local/includes.php');
        }
        $includes = new user_course_details();
        if ($classroom->classroomlogo > 0) {
            $classroom->classroomlogoimg = (new classroom)->classroom_logo($classroom->classroomlogo);
            if ($classroom->classroomlogoimg == false) {
                $classroom->classroomlogoimg = $includes->get_classes_summary_files($classroom);
            }
        } else {
            $classroom->classroomlogoimg = $includes->get_classes_summary_files($classroom);
        }
        if ($classroom->instituteid > 0) {
            $classroom->classroomlocation = $DB->get_field('local_location_institutes', 'fullname', array('id' => $classroom->instituteid));
        } else {
            $classroom->classroomlocation = 'N/A';
        }
        if (!empty($ctr)) {
            $department = $DB->get_records_sql('SELECT id, fullname FROM {local_costcenter} WHERE id IN(' . $ctr . ')');
            $Department = array();
            foreach ($department as $dep) {
                $Department[] = $dep->fullname;
            }
            $classroomdepartment = implode(',', $Department);
        } else {
            $classroomdepartment =  get_string('all');
        }
        $classroom->classroomdepartment = ($classroomdepartment == '-1' || empty($classroomdepartment)) ? get_string('all') : $classroomdepartment;
        if (!empty($bu)) {
            $bussinessunit = $DB->get_records_sql('SELECT id, fullname FROM {local_costcenter} WHERE id IN(' . $bu . ')');
            $bussinessunitarr = array();
            foreach ($bussinessunit as $bu) {
                $bussinessunitarr[] = $bu->fullname;
            }
            $classroombu = implode(',', $bussinessunitarr);
        } else {
            $classroombu =  get_string('all');
        }
        $classroom->classroombu = ($classroombu == '-1' || empty($classroombu)) ? get_string('all') : $classroombu;
        if (!empty($cu)) {
            $commercialunit = $DB->get_records_sql('SELECT id, fullname FROM {local_costcenter} WHERE id IN(' . $cu . ')');
            $commercialunitarr = array();
            foreach ($commercialunit as $cu) {
                $commercialunitarr[] = $cu->fullname;
            }
            $classroomcu = implode(',', $commercialunitarr);
        } else {
            $classroomcu =  get_string('all');
        }
        $classroom->classroomcu = ($classroomcu == '-1' || empty($classroomcu)) ? get_string('all') : $classroomcu;
        if (!empty($territory)) {
            $territory = $DB->get_records_sql('SELECT id, fullname FROM {local_costcenter} WHERE id IN(' . $territory . ')');
            $territoryarr = array();
            foreach ($territory as $bu) {
                $territoryarr[] = $bu->fullname;
            }
            $classroomterritory = implode(',', $territoryarr);
        } else {
            $classroomterritory =  get_string('all');
        }
        $classroom->classroomterritory = ($classroomterritory == '-1' || empty($classroomterritory)) ? get_string('all') : $classroomterritory;

        if (!empty($classroom->open_states)) {
            $states = $DB->get_records_sql('SELECT id, fullname FROM {local_costcenter} WHERE id IN(' . $classroom->open_states . ')');
            $statesarr = array();
            foreach ($states as $bu) {
                $statesarr[] = $bu->fullname;
            }
            $classroomstates = implode(',', $statesarr);
        } else {
            $classroomstates =  get_string('all');
        }
        $classroom->classroomstates = ($classroomstates == '-1' || empty($classroomstates)) ? get_string('all') : $classroomstates;
        if (!empty($classroom->open_states)) {
            $states = $DB->get_records_sql('SELECT id, states_name FROM {local_states} WHERE id IN(' . $classroom->open_states . ')');
            $statesarr = array();
            foreach ($states as $st) {
                $statesarr[] = $st->states_name;
            }
            $classroomstates = implode(',', $statesarr);
        } else {
            $classroomstates =  get_string('all');
        }
        $classroom->classroomstates = ($classroomstates == '-1' || empty($classroomstates)) ? get_string('all') : $classroomstates;
        if (!empty($classroom->open_district)) {
            $district = $DB->get_records_sql('SELECT id, district_name FROM {local_district} WHERE id IN(' . $classroom->open_district . ')');
            $districtarr = array();
            foreach ($district as $dist) {
                $districtarr[] = $dist->district_name;
            }
            $classroomdistrict = implode(',', $districtarr);
        } else {
            $classroomdistrict =  get_string('all');
        }
        $classroom->classroomdistrict = ($classroomdistrict == '-1' || empty($classroomdistrict)) ? get_string('all') : $classroomdistrict;
        if (!empty($classroom->open_subdistrict)) {
            $subdistrict = $DB->get_records_sql('SELECT id, subdistrict_name FROM {local_subdistrict} WHERE id IN(' . $classroom->open_subdistrict . ')');
            $subdistrictarr = array();
            foreach ($subdistrict as $subdist) {
                $subdistrictarr[] = $subdist->subdistrict_name;
            }
            $classroomsubdistrict = implode(',', $subdistrictarr);
        } else {
            $classroomsubdistrict =  get_string('all');
        }
        $classroom->classroomsubdistrict = ($classroomsubdistrict == '-1' || empty($classroomsubdistrict)) ? get_string('all') : $classroomsubdistrict;

        if (!empty($classroom->open_village)) {
            $village = $DB->get_records_sql('SELECT id, village_name FROM {local_village} WHERE id IN(' . $classroom->open_village . ')');
            $villagearr = array();
            foreach ($village as $vlg) {
                $villagearr[] = $vlg->village_name;
            }
            $classroomvillage = implode(',', $villagearr);
        } else {
            $classroomvillage =  get_string('all');
        }
        $classroom->classroomvillage = ($classroomvillage == '-1' || empty($classroomvillage)) ? get_string('all') : $classroomvillage;



        $classroomtrainerssql = "SELECT u.id, u.picture, u.firstname, u.lastname,
                                        u.firstnamephonetic, u.lastnamephonetic, u.middlename,
                                        u.alternatename, u.imagealt, u.email
                                   FROM {user} AS u
                                   JOIN {local_classroom_trainers} AS ct ON ct.trainerid = u.id
                                  WHERE u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND ct.classroomid = $classroomid";

        $classroomtrainers = $DB->get_records_sql($classroomtrainerssql);
        $totalclassroomtrainers = count($classroomtrainers);
        $classroom->trainerpagination = false;
        if ($totalclassroomtrainers > 3) {
            $classroom->trainerpagination = true;
        }
        $classroom->trainers  = array();
        if (!empty($classroomtrainers)) {
            foreach ($classroomtrainers as $classroomtrainer) {
                $classroomtrainerpic = $OUTPUT->user_picture($classroomtrainer, array('size' => 50, 'class' => 'trainerimg', 'link' => false));
                $classroomtrainername = strlen(fullname($classroomtrainer)) > 10 ? substr(fullname($classroomtrainer), 0, 10) . "..." : fullname($classroomtrainer);
                $classroom->trainers[] = array('classroomtrainerpic' => $classroomtrainerpic, 'trainername' => $classroomtrainername, 'trainerdesignation' => 'Trainer', 'traineremail' => $classroomtrainer->email);
            }
        }
        $return = "";
        $usermodulecontext = (new \local_classroom\lib\accesslib())::get_module_context();
        $classroom->userenrolmentcap = (has_capability('local/classroom:manageclassroom', $categorycontext) && has_capability('local/classroom:manageusers', $categorycontext) && $classroom->status == 0) ? true : false;
        $classroom->selfenrolmentcap = false;
        $userenrolstatus = $DB->record_exists('local_classroom_users', array('classroomid' => $classroom->id, 'userid' => $USER->id));
        if (!has_capability('local/classroom:manageclassroom', $categorycontext)) {            

            $return = false;
            if ($classroom->id > 0 && $classroom->nomination_startdate != 0 && $classroom->nomination_enddate != 0) {
                $params1 = array();
                $params1['classroomid'] = $classroom->id;
                $params1['nomination_startdate'] = time();
                $params1['nomination_enddate'] = time();

                $sql1 = "SELECT * FROM {local_classroom} where id=:classroomid and nomination_startdate<=:nomination_startdate and nomination_enddate >= :nomination_enddate";

                $return = $DB->record_exists_sql($sql1, $params1);
            } elseif ($classroom->id > 0 && $classroom->nomination_startdate == 0 && $classroom->nomination_enddate == 0) {
                $return = true;
            }

            $pending = $DB->record_exists('local_request_records',array('createdbyid'=>$USER->id, 'componentid'=>$classroom->id,'status'=>'PENDING'));
            if ($classroom->approvalreqd == 1 && !$userenrolstatus && $return ) {
                if($pending){
                    $classroom->selfenrolmentcap = '<i title = '.get_string('processing', 'local_classroom').' class="" aria-hidden="true">' . get_string('processing', 'local_classroom').'</i>';
                } else {
                    $classroom->selfenrolmentcap = '<a href="javascript:void(0);" class="" alt = ' . get_string('requestforenroll', 'local_classroom') . ' title = ' . get_string('requestforenroll', 'local_classroom') . ' onclick="(function(e){ require(\'local_request/requestconfirm\').init({action:\'add\', componentid: '.$classroom->id.', component:\'classroom\',componentname:\''.$classroom->name .'\'}) })(event)" ><i class="fa fa-share" aria-hidden="true"></i>' . get_string('requestforenroll', 'local_classroom') . '</a>';
                }

            } elseif($classroom->approvalreqd == 0 && !$userenrolstatus && $return) {
                $classroom->selfenrolmentcap = '<a href="javascript:void(0);" class="" alt = ' . get_string('enroll', 'local_classroom') . ' title = ' . get_string('enroll', 'local_classroom') . ' onclick="(function(e){ require(\'local_classroom/classroom\').ManageclassroomStatus({action:\'selfenrol\', id: ' . $classroom->id . ', classroomid:' . $classroom->id . ',actionstatusmsg:\'classroom_self_enrolment\',classroomname:\'' . $classroom->name . '\'}) })(event)" ><i class="fa fa-pencil-square-o" aria-hidden="true"></i>' . get_string('enroll', 'local_classroom') . '</a>';
            }
            $classroom_capacity_check = (new classroom)->classroom_capacity_check($classroomid);
            if ($classroom_capacity_check && $classroom->status == 1 && !$userenrolstatus) {
                $classroom->selfenrolmentcap = get_string('capacity_check', 'local_classroom');
            }
        }

        $stable = new stdClass();
        $stable->thead = true;
        $stable->start = 0;
        $stable->length = -1;
        $stable->search = '';

        $waitinglist_users_tab = $requested_users_tab = $classroomcompletion = $feedback_tab = $user_tab = $course_tab = $session_tab = $action = $edit = $delete = $assignusers = $assignusersurl = false;
        $session_tab = true;
        if ((has_capability('local/classroom:manageclassroom', $categorycontext) || is_siteadmin()) || $userenrolstatus) {
            $course_tab = true;
        }else{
            $course_tab = false;
        }
        if (has_capability('local/classroom:viewusers', $categorycontext)) {
            $user_tab = true;
        }
        if ((has_capability('local/classroom:manageclassroom', $categorycontext) || is_siteadmin()) || $userenrolstatus) {
            $feedback_tab = true;
        }else{
            $feedback_tab = false;
        }
        // $feedback_tab = true;

        if ((has_capability('local/classroom:manageclassroom', $categorycontext) || is_siteadmin())) {
            $action = true;
        }
        if ($departmentcount > 1 && !(is_siteadmin())) {
            $action  = false;
        }

        if ((has_capability('local/classroom:classroomcompletion', $categorycontext) || is_siteadmin())) {// || $userenrolstatus
            $classroomcompletion =  true;
        }else {
            $classroomcompletion =  false;
        }
        if ((has_capability('local/classroom:editclassroom', $categorycontext) || is_siteadmin())) {
            $edit =  true;
        }

        if ((has_capability('local/classroom:deleteclassroom', $categorycontext) || is_siteadmin())) {
            $delete =  true;
        }


        if ((has_capability('local/classroom:manageusers', $usermodulecontext) || is_siteadmin())) {
            $assignusers =  true;
            $assignusersurl = new moodle_url("/local/classroom/enrollusers.php?cid=" . $classroomid . "");
        }


        if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
            $requested_users_tab = true;
        }

        if ($departmentcount > 1 && !(is_siteadmin())) {
            $requested_users_tab = false;
        }

        $completedwaitingseats = $waitingseats = $waitingseats_progress = 0;
        if ((has_capability('local/classroom:viewwaitinglist_userstab', $categorycontext) || is_siteadmin())) {
            $waitinglist_users_tab = true;
            $seats_sql = "SELECT count(distinct(u.id)) FROM {user} AS u
                                                JOIN {local_classroom_waitlist} AS cu ON cu.userid = u.id
                                                WHERE cu.classroomid = $classroomid AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2 and cu.enrolstatus=0";
            $waitingseats = $DB->count_records_sql($seats_sql);
            $seats_sql .= " AND cu.enrolstatus=1 ";
            $completedwaitingseats = $DB->count_records_sql($seats_sql);

            if (empty($waitingseats) || $waitingseats == 0) {
                $waitingseats_progress = 0;
            } else {
                $waitingseats_progress = round(($completedwaitingseats / $waitingseats) * 100);
            }
        }
        $selfenrolmenttabcap = true;
        if (!has_capability('local/classroom:manageclassroom', $categorycontext)) {

            $selfenrolmenttabcap = false;
        }
        $classroom_actionstatus = $this->classroom_actionstatus_markup($classroom, 'classroom');
        $totalseats = $DB->get_field('local_classroom', 'capacity', array('id' => $classroomid));
        $seats_sql = "SELECT count(distinct(u.id)) FROM {user} AS u
                                                JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                                                WHERE cu.classroomid = $classroomid AND u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND u.id > 2";
        $allocatedseats = $DB->count_records_sql($seats_sql);
        $seats_sql .= " AND cu.completion_status=1 ";
        $completed_seats = $DB->count_records_sql($seats_sql);
        if (!empty($classroom->description)) {
            $description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($classroom->description));
        } else {
            $description = "";
        }
        $isdescription = '';
        if (empty($description)) {
            $isdescription = false;
            $decsriptionstring = "";
        } else {
            $isdescription = true;
            if (strlen($description) > 270) {
                $decsriptionCut = substr($description, 0, 270);
                $decsriptionstring =  \local_costcenter\lib::strip_tags_custom(html_entity_decode($decsriptionCut));
            } else {
                $decsriptionstring = "";
            }
        }

        if (empty($totalseats) || $totalseats == 0 || $allocatedseats == 0) {
            $seats_progress = 0;
            $completion_seats_progress = 0;
        } else {
            $seats_progress = round(($allocatedseats / $totalseats) * 100);

            $completion_seats_progress = round(($completed_seats / $allocatedseats) * 100);
        }

        $classroomcompletion_id = $DB->get_field('local_classroom_completion', 'id', array('classroomid' => $classroomid));
        if (!$classroomcompletion_id) {
            $classroomcompletion_id = 0;
        }
        $classroom_status = (new classroom)->classroom_status_strip($classroomid, $classroom->status);

        $createsession = false;
        if (is_siteadmin() || (has_capability('local/classroom:createsession', $categorycontext) && (has_capability('local/classroom:manageclassroom', $categorycontext)))) {
            $createsession = true;
        }

        if ($departmentcount > 1 && !(is_siteadmin())) {
            $createsession = false;
        }


        $exist = $DB->record_exists('local_classroom', array('id' => $classroomid, 'trainingfeedbackid' => 0));
        $exist_with_tr_fd = $DB->count_records_sql("SELECT count(id) as total FROM {local_classroom_trainers} where classroomid = :classroomid AND feedback_id>0", array('classroomid' => $classroomid));
        $exist_with_tr = $DB->count_records('local_classroom_trainers', array('classroomid' => $classroomid));
        $createfeedback = false;
        if (is_siteadmin() || ((has_capability('local/classroom:createfeedback', $categorycontext)) && (has_capability('local/classroom:manageclassroom', $categorycontext))) && ($exist || $exist_with_tr_fd != $exist_with_tr)) {
            $createfeedback = true;
        }

        if ($departmentcount > 1 && !(is_siteadmin() || has_capability('local/classroom:manageclassroom', $categorycontext))) {
            $createfeedback  = false;
        }

        $assign_courses = false;
        if (is_siteadmin() || (has_capability('local/classroom:createcourse', $categorycontext) && (has_capability('local/classroom:manageclassroom', $categorycontext)))) {
            $assign_courses = true;
        }

        if ($departmentcount > 1 && !(is_siteadmin())) {
            $assign_courses  = false;
        }
        // $unenrolbutton = $this->render_classroom_unenrol_object($classroomid, $USER->id);
        /*$is_selfenrolled = $DB->record_exists("local_classroom_users",  array('userid' => $USER->id, 'classroomid' => $classroomid, 'usercreated' => $USER->id));*/
        if (($action == false && $classroom->status == 1 && ($unenroll == true /*|| $is_selfenrolled*/) /*&& $daysdiff<0*/)) {
            $action = true;
        }

        $ratings_exist = \core_component::get_plugin_directory('local', 'ratings');
        if ($ratings_exist) {
            require_once($CFG->dirroot . '/local/ratings/lib.php');
            $display_ratings = display_rating($classroomid, 'local_classroom');
            $display_like = display_like_unlike($classroomid, 'local_classroom');
            $display_review = display_comment($classroomid, 'local_classroom');
        } else {
            $display_ratings = $display_like = null;
        }

        if (!is_siteadmin()) {
            $switchedrole = $USER->access['rsw']['/1'];
            if ($switchedrole) {
                $userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
            } else {
                $userrole = null;
            }
            //            if(is_null($userrole) || $userrole == 'user'){
            if (is_null($userrole) || $userrole == 'employee') {
                $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');
                if ($certificate_plugin_exist) {
                    if (!empty($classroom->certificateid)) {
                        $certificate_exists = true;
                        $sql = "SELECT id 
                                FROM {local_classroom_users}
                                WHERE classroomid = :classroomid AND userid = :userid
                                AND completion_status = :completion_status ";
                        $params['classroomid'] = $classroom->id;
                        $params['userid'] = $USER->id;
                        $params['completion_status'] = 1;
                        $completed = $DB->record_exists_sql($sql, $params);
                        //            Mallikarjun added to get tool certificate
                        $gcertificateid = $DB->get_field('local_classroom', 'certificateid', array('id' => $classroom->id));
                        $certid = $DB->get_field('tool_certificate_issues', 'code', array('moduleid' => $classroom->id, 'userid' => $USER->id, 'moduletype' => 'classroom'));
                        if ($completed && $certid) {
                            $certificate_download = true;
                        } else {
                            $certificate_download = false;
                        }
                        $certificateid = $classroom->certificateid;
                    }
                }
            }
        }
        $classroomcontext = [
            'classroomcompletion_id' => $classroomcompletion_id,
            'classroom' => $classroom,
            'classroomid' => $classroomid,
            'action' => $action,
            'unenroll' => $unenroll,
            'edit' => $edit,
            'createsession' => $createsession,
            'createfeedback' => $createfeedback,
            'assign_courses' => $assign_courses,
            'classroomcompletion' => $classroomcompletion,
            'delete' => $delete,
            'assignusers' => $assignusers,
            'certificate_download' => $certificate_download,
            'certificate_exists' => $certificate_exists,
            'certificateid' => $certificateid,
            'certid' => $certid,
            'assignusersurl' => $assignusersurl,
            'classroom_actionstatus' => array_values(($classroom_actionstatus)),
            'totalseats' => empty($totalseats) ? 'N/A' : $totalseats,
            'allocatedseats' => $allocatedseats,
            'completed_seats' => $completed_seats,
            'selfenrolmenttabcap' => $selfenrolmenttabcap,
            'description' => $description,
            'descriptionstring' => $decsriptionstring,
            'isdescription' => $isdescription,
            'seats_progress' => $seats_progress,
            'completion_seats_progress' => $completion_seats_progress,
            'feedback_tab' => false,//$feedback_tab,
            'completion_settings_tab' => $classroomcompletion,
            'target_audience_tab' => true,
            'requested_users_tab' => $requested_users_tab,
            'waitinglist_users_tab' => $waitinglist_users_tab,
            'user_tab' => $user_tab,
            'course_tab' => $course_tab,
            'session_tab' => $session_tab,
            'classname' => $classroom->name,
            'classname_string' => $classroom->name,
            'classroom_status' => $classroom_status,
            'seats_image' => $OUTPUT->image_url('GraySeatNew', 'local_classroom'),
            'waitingseats' => $waitingseats,
            'completedwaitingseats' => $completedwaitingseats,
            'waitingseats_progress' => $waitingseats_progress,
            'display_ratings' => $display_ratings,
            'display_like' => $display_like,
            'display_review' => $display_review,
            //'unenrolbutton' => $unenrolbutton
        ];
        $return = $this->render_from_template('local_classroom/classroomContent', $classroomcontext);
        return $return;
    }
    /**
     * [viewclassroomusers description]
     * @method viewclassroomusers
     * @param  [type]             $classroomid [description]
     * @param  [type]             $stable      [description]
     * @return [type]                          [description]
     */
    public function viewclassroomusers($users, $classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
        $data = array();
        $assign_users = false;
        if (has_capability('local/classroom:manageusers',  $categorycontext) && has_capability('local/classroom:manageclassroom',  $categorycontext)) {
            $assign_users = true;
        }
        if (is_siteadmin() || (has_capability('local/classroom:createcourse', $categorycontext) && (has_capability('local/classroom:manageclassroom', $categorycontext)))) {
            $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');
            if ($certificate_plugin_exist) {
                $cl_certificateid = $DB->get_field('local_classroom', 'certificateid', array('id' => $classroomid));
                if ($cl_certificateid) {
                    $mapped_certificate = true;
                } else {
                    $mapped_certificate = false;
                }
            }
        }
        foreach ($users as $sdata) {
            $line = array();
            //            Mallikarjun added to get tool certificate
            $gcertificateid = $DB->get_field('local_classroom', 'certificateid', array('id' => $classroomid));
            $certid = $DB->get_field('tool_certificate_issues', 'code', array('moduleid' => $classroomid, 'userid' => $sdata->id, 'moduletype' => 'classroom'));
            $line['id'] = $sdata->id;
            $line['certificateid'] = $cl_certificateid;
            $line['certid'] = $certid;
            $line['moduleid'] = $classroomid;
            $line['userid'] = $sdata->id;
            $line['name'] = $OUTPUT->user_picture($sdata) . ' ' . fullname($sdata);
            $line['employeeid'] = $sdata->open_employeeid;
            $line['email'] = $sdata->email;
            $supervisor = $DB->get_field('user', "concat(firstname,' ',lastname)", array('id' => $sdata->open_supervisorid));
            $line['supervisor'] = !empty($supervisor) ? $supervisor : '--';
            $line['attendedsessions'] = $sdata->attended_sessions . '/' . $sdata->totalsessions;
            $line['hours'] = $sdata->hours;
            $line['completionstatus'] = $sdata->completion_status == 1 ? true : false;
            if ($sdata->completion_status == 1 && $certid) {
                $line['downloadcertificate'] = true;
            } else {
                $line['downloadcertificate'] = false;
            }

            $data[] = $line;
        }
        return array('assignusers' => $assign_users, 'data' => $data, 'mapped_certificate' => $mapped_certificate);
    }
    /**
     * [classroom_actionstatus_markup description]
     * @method classroom_actionstatus_markup
     * @param  [type]                        $classroom [description]
     * @return [type]                                   [description]
     */
    public function classroom_actionstatus_markup($classroom, $view = "browseclassrooms")
    {
        global $DB, $PAGE, $OUTPUT;

        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroom->id);

        if ($view == "browseclassrooms") {
            $class = "";
        } else {
            $class = "course_extended_menu_itemlink";
        }
        $return = array();
        $classroomcourseexist = $DB->record_exists('local_classroom_courses', array('classroomid' => $classroom->id));
        $classroomsessionsexist = $DB->record_exists('local_classroom_sessions', array('classroomid' => $classroom->id));
        $classroomusersexist = $DB->record_exists('local_classroom_users', array('classroomid' => $classroom->id));
        //if ($classroomcourseexist && $classroomsessionsexist && $classroomusersexist && $classroom->status == 0) {
        $fparams = new stdClass();
        $fparams->classroomid = $classroom->id;
        $fparams->id = $classroom->id;
        $fparams->classroomname = $classroom->name;

        if ($classroom->status == 0 && has_capability('local/classroom:manageclassroom',  $categorycontext) && has_capability('local/classroom:publish',  $categorycontext)) {
            $fparams->action = "1";
            $fparams->actionstatusmsg = 'classroom_active_action';
            $fr = json_encode($fparams, JSON_HEX_APOS);
            $return[] = "<a href='javascript:void(0);' class=$class alt = " . get_string('publish', 'local_classroom') . " title = " . get_string('publish', 'local_classroom') . " 
            onclick='(function(e){ require(\"local_classroom/classroom\").ManageclassroomStatus($fr) })(event)'><i class='icon fa fa-share fa-fw' aria-hidden='true' ></i></a>";
        }
        if ($classroom->status == 2 && has_capability('local/classroom:release_hold',  $categorycontext) && has_capability('local/classroom:manageclassroom',  $categorycontext)) {
            $fparams->action = "1";
            $fparams->actionstatusmsg = 'classroom_release_hold_action';
            $fr = json_encode($fparams, JSON_HEX_APOS);
            $return[] = "<a href='javascript:void(0);' class=$class alt = " . get_string('release_hold', 'local_classroom') . " title = " . get_string('release_hold', 'local_classroom') . " 
            onclick='(function(e){ require(\"local_classroom/classroom\").ManageclassroomStatus($fr) })(event)'><i class='icon fa fa-share fa-fw' aria-hidden='true' title = " . get_string('release_hold', 'local_classroom') . "></i></a>";
        }
        if ($classroom->status == 1) {

            if (has_capability('local/classroom:cancel',  $categorycontext) && has_capability('local/classroom:manageclassroom',  $categorycontext)) {
                $fparams->action = "3";
                $fparams->actionstatusmsg = 'classroom_close_action';
                $fr = json_encode($fparams, JSON_HEX_APOS);
                $return[] = "<a href='javascript:void(0);' class=$class alt = " . get_string('cancel', 'local_classroom') . " title = " . get_string('cancel', 'local_classroom') . " 
            onclick='(function(e){ require(\"local_classroom/classroom\").ManageclassroomStatus($fr) })(event)'><i class='icon fa fa-lock fa-fw' aria-hidden='true' title = " . get_string('cancel', 'local_classroom') . "></i></a>";
            }

            if (has_capability('local/classroom:hold', $categorycontext) && has_capability('local/classroom:manageclassroom',  $categorycontext)) {
                $fparams->action = "2";
                $fparams->actionstatusmsg = 'classroom_hold_action';
                $fr = json_encode($fparams, JSON_HEX_APOS);
                $return[] = "<a href='javascript:void(0);' class=$class alt = " . get_string('hold', 'local_classroom') . " title = " . get_string('hold', 'local_classroom') . " 
            onclick='(function(e){ require(\"local_classroom/classroom\").ManageclassroomStatus($fr) })(event)'><i class='icon fa fa-hand-o-up fa-fw' aria-hidden='true' title = " . get_string('hold', 'local_classroom') . "></i></a>";
            }


            $sessionnotattendancetaken = $DB->record_exists('local_classroom_sessions', array('classroomid' => $classroom->id, 'attendance_status' => 0));
            if (!$sessionnotattendancetaken && $classroom->enddate <= time() && has_capability('local/classroom:complete',  $categorycontext) && has_capability('local/classroom:manageclassroom',  $categorycontext)) {
                $fparams->action = "4";
                $fparams->actionstatusmsg = 'classroom_complete_action';
                $fr = json_encode($fparams, JSON_HEX_APOS);
                $return[] = "<a href='javascript:void(0);' class=$class alt = " . get_string('mark_complete', 'local_classroom') . " title = " . get_string('mark_complete', 'local_classroom') . " 
                onclick='(function(e){ require(\"local_classroom/classroom\").ManageclassroomStatus($fr) })(event)'><i class='icon fa fa-check fa-fw' aria-hidden='true' title = " . get_string('mark_complete', 'local_classroom') . "></i></a>";
            }
        }
        return $return;
    }
    public function viewclassroomattendance($classroomid, $sessionid = 0)
    {
        global $PAGE, $OUTPUT, $DB;
        $classroom = new classroom();
        $attendees = $classroom->classroom_get_attendees($classroomid, $sessionid);
        $return = '';
        if (empty($attendees)) {
            $return .= "<div class='alert alert-info'>" . get_string('noclassroomusers', 'local_classroom') . "</div>";
        } else {
            $return .= '<form method="post" id="formattendance" action="' . $PAGE->url . '">';
            $return .= '<input type="hidden" name="action" value="attendance" />';
            $params = array();
            $params['classroomid'] = $classroomid;
            $sqlsessionconcat = '';
            if ($sessionid > 0) {
                $sqlsessionconcat = " AND id = :sessionid";
                $params['sessionid'] = $sessionid;
            }
            $sessions = $DB->get_fieldset_select(
                'local_classroom_sessions',
                'id',
                'classroomid = :classroomid ' . $sqlsessionconcat,
                $params
            );
            foreach ($attendees as $attendee) {
                if (!$sessionid) {
                    $attendancestatuslist = $DB->get_records_sql('SELECT sessionid, id AS attendanceid, sessionid, status, userid FROM {local_classroom_attendance} WHERE classroomid = :classroomid AND userid = :userid', array('classroomid' => $classroomid, 'userid' => $attendee->id));
                }
                $list = array();
                $list[] = $OUTPUT->user_picture($attendee, array('size' => 30)) .
                    fullname($attendee);
                foreach ($sessions as $session) {
                    if ($sessionid > 0) {
                        $attendanceid = $attendee->attendanceid;
                        $attendancestatus = $attendee->status;
                    } else {
                        $attendanceid = isset($attendancestatuslist[$session]->attendanceid) && $attendancestatuslist[$session]->attendanceid > 0 ? $attendancestatuslist[$session]->attendanceid : 0;
                        $attendancestatus = isset($attendancestatuslist[$session]->status) && $attendancestatuslist[$session]->status > 0 ? $attendancestatuslist[$session]->status : 0;
                    }

                    $encodeddata = base64_encode(json_encode(array(
                        'classroomid' => $classroomid, 'sessionid' => $session,
                        'userid' => $attendee->id, 'attendanceid' => $attendanceid
                    )));
                    $radio = '<input type="hidden" value="' . $encodeddata . '"
                    name="attendeedata[]">';

                    $check_exist = $DB->get_field('local_classroom_attendance', 'id', array('sessionid' => $session, 'userid' => $attendee->id));
                    if ($check_exist) {
                        $checked = '';
                    } else {
                        $checked = 'checked';
                    }

                    if ($attendancestatus == 2) {
                        $checked = '';
                        $status = $sessionid > 0 ? "Absent" : "A";
                        $status = '<span class="tag tag-danger">' . $status . '</span>';
                    } else if ($attendancestatus == 1) {
                        $status = $sessionid > 0 ? "Present" : "P";
                        $checked = 'checked';
                        $status = '<span class="tag tag-success">' . $status . '</span>';
                    } else {
                        $status = $sessionid > 0 ? "Not yet given" : "NY";
                        $status = '<span class="tag tag-warning">' . $status . '</span>';
                    }
                    $radio .= '<input type="checkbox" name="status[' . $encodeddata . ']"
                         ' . $checked  . ' class="checksingle' . $session . '">';
                    if ($sessionid > 0) {
                        $list[] = $status;
                    } else {
                        //$radio .= "<div>$status</div>";
                    }
                    $list[] = $radio;
                }
                $data[] = $list;
            }
            $table = new html_table();
            $script = "";
            if ($sessionid > 0) {
                $table->head = array('Employee', 'Status', 'Attendance<p><input type=checkbox name=checkAll id=checkAll' . $sessionid . '> Select All</p>');
                $script .= html_writer::script("
                         $('#checkAll$sessionid').change(function () {
                                $('.checksingle$sessionid').prop('checked', $(this).prop('checked'));
                         });        
                     ");
            } else {
                $table->head[] = 'Employee';
                foreach ($sessions as $session) {
                    $table->head[] = 'Session ' . $session . '<p><input type=checkbox name=checkAll id=checkAll' . $session . '> Select All</p>';
                    $script .= html_writer::script("
                         $('#checkAll$session').change(function () {
                                $('.checksingle$session').prop('checked', $(this).prop('checked'));
                         });        
                     ");
                }
            }
            $table->data = $data;
            $return .= html_writer::table($table);
            $return .= '<input type="submit" name="submit" value="Submit">';
            $return .= '<input type="submit" name="reset" value="Reset Selected">';
            $return .= '</form>';
            $return .= "<div id='result'></div>" . $script;
        }
        return $return;
    }
    //  public function manageclassroomcategories() {
    //     $stable = new stdClass();
    //     $stable->thead = true;
    //     $stable->start = 0;
    //     $stable->length = -1;
    //     $stable->search = '';
    //     $tabscontent = $this->viewclassrooms($stable);
    //     $classroomtabslist = [
    //         'classroomtabslist' => $tabscontent
    //     ];
    //     return $this->render_from_template('local_classroom/classroomtabs', $classroomtabslist);
    // }
    public function viewclassroomlastchildpopup($classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        $stable = new stdClass();
        $stable->classroomid = $classroomid;
        $stable->thead = false;
        $stable->start = 0;
        $stable->length = 1;
        $classroom = (new classroom)->classrooms($stable);
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
        $classroom_status = $DB->get_field('local_classroom', 'status', array('id' => $classroomid));
        if (!has_capability('local/classroom:view_newclassroomtab', $categorycontext) && $classroom_status == 0) {
            print_error("You don't have permissions to view this page.");
        } elseif (!has_capability('local/classroom:view_holdclassroomtab', $categorycontext) && $classroom_status == 2) {
            print_error("You don't have permissions to view this page.");
        }
        if (empty($classroom)) {
            print_error("Classroom Not Found!");
        }
        if (file_exists($CFG->dirroot . '/local/includes.php')) {
            require_once($CFG->dirroot . '/local/includes.php');
        }
        $includes = new user_course_details();
        if ($classroom->classroomlogo > 0) {
            $classroom->classroomlogoimg = (new classroom)->classroom_logo($classroom->classroomlogo);
            if ($classroom->classroomlogoimg == false) {
                $classroom->classroomlogoimg = $includes->get_classes_summary_files($sdata);
            }
        } else {
            $classroom->classroomlogoimg = $includes->get_classes_summary_files($classroom);
        }

        if ($classroom->instituteid > 0) {
            $classroom->classroomlocation = $DB->get_field('local_location_institutes', 'fullname', array('id' => $classroom->instituteid));
        } else {
            $classroom->classroomlocation = 'N/A';
        }


        if ($classroom->department == -1) {
            $classroom->classroomdepartment = 'All';
            $classroom->classroomdepartmenttitle = 'All';
        } else {
            $classroomdepartment = $DB->get_fieldset_select('local_costcenter', 'fullname', " CONCAT(',',$classroom->department,',') LIKE CONCAT('%,',id,',%') ", array()); //FIND_IN_SET(id, '$classroom->department')
            $classroom->classroomdepartment =  (count($classroomdepartment) > 1) ? $classroomdepartment[0] . '...' : $classroomdepartment[0];
            $classroom->classroomdepartmenttitle = implode(', ', $classroomdepartment);
        }

        $classroomtrainerssql = "SELECT u.id, u.picture, u.firstname, u.lastname,
                                        u.firstnamephonetic, u.lastnamephonetic, u.middlename,
                                        u.alternatename, u.imagealt, u.email
                                   FROM {user} AS u
                                   JOIN {local_classroom_trainers} AS ct ON ct.trainerid = u.id
                                  WHERE u.confirmed = 1 AND u.suspended = 0 AND u.deleted = 0 AND ct.classroomid = :classroomid";

        $classroomtrainers = $DB->get_records_sql($classroomtrainerssql, array('classroomid' => $classroom->id));
        $totalclassroomtrainers = count($classroomtrainers);
        $classroom->trainerpagination = false;
        if ($totalclassroomtrainers > 3) {
            $classroom->trainerpagination = true;
        }
        $trainers  = array();
        if (!empty($classroomtrainers)) {
            foreach ($classroomtrainers as $classroomtrainer) {
                $classroomtrainerpic = $OUTPUT->user_picture($classroomtrainer, array('size' => 50, 'class' => 'trainerimg', 'link' => false));
                $trainers[] = array('classroomtrainerpic' => $classroomtrainerpic, 'trainername' => fullname($classroomtrainer), 'trainerdesignation' => 'Trainer', 'traineremail' => $classroomtrainer->email);
            }
        } else {

            $trainers[] = array('classroomtrainerpic' => '', 'trainername' => '', 'trainerdesignation' => '', 'traineremail' => '');
        }
        $return = "";
        $classroom->userenrolmentcap = (has_capability('local/classroom:manageusers', $categorycontext) && has_capability('local/classroom:manageclassroom', $categorycontext) && $classroom->status == 0) ? true : false;

        $stable = new stdClass();
        $stable->thead = true;
        $stable->start = 0;
        $stable->length = -1;
        $stable->search = '';

        $totalseats = $DB->get_field('local_classroom', 'capacity', array('id' => $classroomid));

        $countfields = "SELECT COUNT(DISTINCT u.id) ";
        $params['classroomid'] = $classroomid;
        $params['confirmed'] = 1;
        $params['suspended'] = 0;
        $params['deleted'] = 0;
        $sql = " FROM {user} AS u
                JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                 WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                    AND u.deleted = :deleted AND cu.classroomid = :classroomid";
        $allocatedseats     = $DB->count_records_sql($countfields . $sql, $params);


        //$allocatedseats=$DB->count_records('local_classroom_users',array('classroomid'=>$classroomid)) ;
        $coursesummary = \local_costcenter\lib::strip_tags_custom(
            $course->summary,
            array('overflowdiv' => false, 'noclean' => false, 'para' => false)
        );
        $description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($classroom->description));
        $isdescription = '';
        if (empty($description)) {
            $isdescription = false;
        } else {
            $isdescription = true;
            if (strlen($description) > 250) {
                $decsriptionCut = substr($description, 0, 250);
                $decsriptionstring =  \local_costcenter\lib::strip_tags_custom(html_entity_decode($decsriptionCut), array('overflowdiv' => false, 'noclean' => false, 'para' => false));;
            } else {
                $decsriptionstring = "";
            }
        }

        if (empty($totalseats) || $totalseats == 0) {
            $seats_progress = 0;
        } else {
            $seats_progress = round(($allocatedseats / $totalseats) * 100);
        }
        $classroomcontext = [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'startdate' => $classroom->startdate,
            'enddate' => $classroom->enddate,
            'classroomlocation' => $classroom->classroomlocation,
            'classroomdepartment' => $classroom->classroomdepartment,
            'trainers' => $trainers[0],
            'classroomid' => $classroomid,
            'totalseats' => empty($totalseats) ? 'N/A' : $totalseats,
            'allocatedseats' => $allocatedseats,
            'description' => $description,
            'descriptionstring' => $decsriptionstring,
            'isdescription' => $isdescription,
            'seats_progress' => $seats_progress,
            'contextid' => $categorycontext->id,
            'linkpath' => $CFG->wwwroot . "/local/classroom/view.php?cid=$classroomid"
        ];
        return $classroomcontext;
        //return $this->render_from_template('local_classroom/classroomview', $classroomcontext);
    }
    /**
     * [viewclassroomcompletion_settings_tab description]
     * @param  [type] $classroomid [description]
     * @return [type]              [description]
     */
    public function viewclassroomcompletion_settings_tab($classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER;
        $completion_settings = (new classroom)->classroom_completion_settings_tab($classroomid);

        return $completion_settings;
    }
    public function viewclassroomtarget_audience_tab($classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER;
        $completion_settings = (new classroom)->classroomtarget_audience_tab($classroomid);

        return $completion_settings;
    }
    public function view_classroom_sessions($classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
        $stable = new \stdClass();
        $stable->search = false;
        $stable->thead = false;
        $sessions = (new classroom)->classroomsessions($classroomid, $stable);
        $out = "";
        if ($sessions['sessionscount'] > 0) {
            $table = new html_table();
            if ((has_capability('local/classroom:manageclassroom', $categorycontext) || is_siteadmin())) {
                $out .= '<table style="border-collapse: collapse;"  width="99%">
                            <thead>
                            <tr>
                            <th class="header c0" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('name') . '</th>
                            <th class="header c1" style="text-align:center;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('date') . '</th>
                            <th class="header c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('type', 'local_classroom') . '</th>
                            <th class="header c3" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('room', 'local_classroom') . '</th>
                            <th class="header c4" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('status', 'local_classroom') . '</th>
                            <th class="header c5 lastcol" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('faculty', 'local_classroom') . '</th>
                            </tr>
                            </thead>';
            } else {
                $out .= '<table style="border-collapse: collapse;"  width="99%">
                            <thead>
                            <tr>
                            <th class="header c0" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('name') . '</th>
                            <th class="header c1" style="text-align:center;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('date') . '</th>
                            <th class="header c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('type', 'local_classroom') . '</th>
                            <th class="header c3" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('room', 'local_classroom') . '</th>
                            <th class="header c4" style="text-align:left;border: 1px solid #dddddd;padding: 8px;" scope="col">' . get_string('status', 'local_classroom') . '</th>
                            </tr>
                            </thead>';
            }
            $out .= '<tbody>';
            foreach ($sessions['sessions'] as $sdata) {
                $out .= '<tr class="">
                        <td class="cell c0" style="text-align:left;border: 1px solid #dddddd;padding: 8px;">' . $sdata->name . '</td>';
                $out .= '<td class="cell c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;">' . \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->timestart) . ' to ' . \local_costcenter\lib::get_userdate("d/m/Y H:i", $sdata->timefinish) . '</td>';

                $link = get_string('pluginname', 'local_classroom');
                if ($sdata->onlinesession == 1) {

                    $moduleids = $DB->get_field('modules', 'id', array('name' => $sdata->moduletype));
                    if ($moduleids) {
                        $moduleid = $DB->get_field('course_modules', 'id', array('instance' => $sdata->moduleid, 'module' => $moduleids));
                        if ($moduleid) {
                            $link = html_writer::link($CFG->wwwroot . '/mod/' . $sdata->moduletype . '/view.php?id=' . $moduleid, get_string('join', 'local_classroom'), array('title' => get_string('join', 'local_classroom')));

                            if (!has_capability('local/classroom:manageclassroom', $categorycontext)) {
                                $userenrolstatus = $DB->record_exists('local_classroom_users', array('classroomid' => $classroomid, 'userid' => $USER->id));

                                if (!$userenrolstatus) {
                                    $link = get_string('join', 'local_classroom');
                                }
                            }
                        }
                    }
                }
                $out .= '<td class="cell c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;">' . $link . '</td>';
                $room = $sdata->room ? $sdata->room : 'N/A';

                $out .= '<td class="cell c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;">' . $room . '</td>';

                if ($sdata->timefinish <= time() && $sdata->attendance_status == 1) {
                    $out .= '<td class="cell c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;">' . get_string('completed', 'local_classroom') . '</td>';
                } else {
                    $out .= '<td class="cell c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;">' . get_string('pending', 'local_classroom') . '</td>';
                }
                if ((has_capability('local/classroom:manageclassroom', $categorycontext) || is_siteadmin())) {
                    $trainer = $DB->get_record('user', array('id' => $sdata->trainerid));

                    $trainername =  $trainer ? fullname($trainer) : 'N/A';

                    $out .= '<td class="cell c2" style="text-align:left;border: 1px solid #dddddd;padding: 8px;">' . $trainername . '</td>';
                }
                $out .= '</tr>';
            }
            $out .= '</tbody></table>';
        }

        return $out;
    }
    /**
     * [viewclassroomusers description]
     * @method viewclassroomusers
     * @param  [type]             $classroomid [description]
     * @param  [type]             $stable      [description]
     * @return [type]                          [description]
     */
    public function viewclassroomwaitinglistusers($users, $classroomid, $stable)
    {
        global $OUTPUT, $CFG, $DB;
        $data = array();
        $i = $stable->start + 1;
        foreach ($users as $sdata) {
            $line = array();
            $line['id'] = $sdata->id;
            $line['name'] = $OUTPUT->user_picture($sdata) . ' ' . fullname($sdata);
            $line['employeeid'] = $sdata->open_employeeid;
            $line['email'] = $sdata->email;
            $supervisor = $DB->get_field('user', "concat(firstname,' ',lastname)", array('id' => $sdata->open_supervisorid));
            $line['supervisor'] = !empty($supervisor) ? $supervisor : '--';
            $line['sortorder'] = $i;
            $line['enroltype'] = ($sdata->enroltype == 1) ? 'Request' : ($sdata->enroltype == 2 ? 'My Team' : 'Self');
            $line['waitingtime'] = $sdata->timecreated ? \local_costcenter\lib::get_userdate('d/m/Y H:i', $sdata->timecreated) : 'N/A';
            $data[] = $line;
            $i++;
        }

        return array('data' => $data);
    }
    public function classroomview_check($classroomid)
    {
        global $OUTPUT, $CFG, $DB, $USER, $PAGE;
        $stable = new stdClass();
        $stable->classroomid = $classroomid;
        $stable->thead = false;
        $stable->start = 0;
        $stable->length = 1;
        $classroom = (new classroom)->classrooms($stable);
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context($classroomid);
        $classroom_status = $DB->get_field('local_classroom', 'status', array('id' => $classroomid));
        if (empty($classroom)) {
            print_error("classroom Not Found!");
        }

        return $classroom;
    }

    /**
     * Renders html to print list of classrooms tagged with particular tag
     *
     * @param int $tagid id of the tag
     * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
     *             are displayed on the page and the per-page limit may be bigger
     * @param int $fromctx context id where the link was displayed, may be used by callbacks
     *            to display items in the same context first
     * @param int $ctx context id where to search for records
     * @param bool $rec search in subcontexts as well
     * @param array $displayoptions
     * @return string empty string if no courses are marked with this tag or rendered list of courses
     */
    public function tagged_classrooms($tagid, $exclusivemode, $ctx, $rec, $displayoptions, $count = 0)
    {
        global $CFG, $DB, $USER;
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
        if ($count > 0)
            $sql = " select count(c.id) from {local_classroom} c ";
        else
            $sql = " select c.* from {local_classroom} c ";

        $where = " where c.id IN (SELECT t.itemid FROM {tag_instance} t WHERE t.tagid = :tagid AND t.itemtype = :itemtype AND t.component = :component)";
        $joinsql = $groupby = $orderby = '';
        if (!empty($sort)) {
            switch ($sort) {
                case 'highrate':
                    if ($DB->get_manager()->table_exists('local_rating')) {
                        $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_classroom' ";
                        $groupby .= " group by c.id ";
                        $orderby .= " order by AVG(rating) desc ";
                    }
                    break;
                case 'lowrate':
                    if ($DB->get_manager()->table_exists('local_rating')) {
                        $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_classroom' ";
                        $groupby .= " group by c.id ";
                        $orderby .= " order by AVG(rating) asc ";
                    }
                    break;
                case 'latest':
                    $orderby .= " order by c.timecreated desc ";
                    break;
                case 'oldest':
                    $orderby .= " order by c.timecreated asc ";
                    break;
                default:
                    $orderby .= " order by c.timecreated desc ";
                    break;
            }
        }
        $whereparams = array();
        $conditionalwhere = '';
        if (!is_siteadmin()) {
            $wherearray = org_dep_sql($categorycontext); // get records department wise
            $whereparams = $wherearray['params'];
            $conditionalwhere = $wherearray['sql'];
        }

        $tagparams = array('tagid' => $tagid, 'itemtype' => 'classroom', 'component' => 'local_classroom');
        $params = array_merge($tagparams, $whereparams);
        if ($count > 0) {
            $records = $DB->count_records_sql($sql . $where . $conditionalwhere, $params);
            return $records;
        } else {
            $records = $DB->get_records_sql($sql . $joinsql . $where . $conditionalwhere . $groupby . $orderby, $params);
        }
        $tagfeed = new \local_tags\output\tagfeed(array(), 'classrooms');
        $img = $this->output->pix_icon('i/course', '');
        foreach ($records as $key => $value) {
            $url = $CFG->wwwroot . '/local/classroom/view.php?cid=' . $value->id . '';
            $imgwithlink = html_writer::link($url, $img);
            $modulename = html_writer::link($url, $value->name);
            $testdetails = get_classroom_details($value->id);
            $details = '';
            $details = $this->render_from_template('local_classroom/tagview', $testdetails);
            $tagfeed->add($imgwithlink, $modulename, $details, $rating);
        }
        return $this->output->render_from_template('local_tags/tagfeed', $tagfeed->export_for_template($this->output));
    }
    public function get_userdashboard_classroom($tab, $filter = false, $view_type = 'card')
    {
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();

        $templateName = 'local_classroom/userdashboard_paginated';
        $cardClass = 'col-md-6 col-12';
        $perpage = 6;
        if ($view_type == 'table') {
            $templateName = 'local_classroom/userdashboard_paginated_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
        }
        $options = array('targetID' => 'dashboard_classrooms', 'perPage' => $perpage, 'cardClass' => $cardClass, 'viewType' => $view_type);
        $options['methodName'] = 'local_classroom_userdashboard_content_paginated';
        $options['templateName'] = $templateName;
        $options['filter'] = $tab;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $categorycontext->id));
        $categorycontext = [
            'targetID' => 'dashboard_classrooms',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];
        if ($filter) {
            return  $categorycontext;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $categorycontext);
        }
    }
}
