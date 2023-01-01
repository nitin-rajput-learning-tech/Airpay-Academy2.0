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
 * @subpackage block_learnerscript
 */

require_once("../../config.php");
use block_learnerscript\local\ls;
use block_learnerscript\output;
global $PAGE, $USER, $DB;

$PAGE->set_url('/blocks/learnerscript/reportsview.php');
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/blocks/learnerscript/css/responsive.bootstrap.min.css');
$title = get_string('managereports', 'block_learnerscript');

require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');
$params = get_reportdashboard();

$reportdashboardurl = new moodle_url("/blocks/reportdashboard/dashboard.php" , $params);
$PAGE->navbar->add(get_string('reportdashboard','block_learnerscript'), $reportdashboardurl);
$PAGE->navbar->add(get_string('managereports','block_learnerscript'));
$PAGE->navbar->ignore_active();
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_cacheable(true);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
require_login();
echo $OUTPUT->header();

$ls = new ls();
$report_details=$ls->get_categories_from_reports();

    $stredit = get_string('edit');
    $strdelete = get_string('delete');
    $strhide = get_string('hide');
    $strshow = get_string('show');
    $strcopy = get_string('duplicate');
    $strexport = get_string('exportreport', 'block_learnerscript');
    $strschedule = get_string('schedulereport', 'block_learnerscript');

    $data=array();
    $systemcontext = context_system::instance();
    if((is_siteadmin() || has_capability('block/learnerscript:managereports',$systemcontext))){
        $data['createreport'] = true;
    }else{
        $data['createreport'] = false;
    }
    if($report_details){
        foreach ($report_details as $r) {
            $report_results=$ls->get_reportdetails_categorywise($r->category);

                switch ($r->category) {
                    case "course":
                        $plugin_exists = core_component::get_plugin_directory('local', 'courses'); 
                        if($plugin_exists){
                            $data['course_exist'] = true;
                        }
                        break;

                    case "user":
                        $plugin_exists = core_component::get_plugin_directory('local', 'users'); 
                        if($plugin_exists){
                            $data['users_exist'] = true;
                        }
                        break;

                    case "local_classroom":
                        $plugin_exists = core_component::get_plugin_directory('local', 'classroom'); 
                        if($plugin_exists){
                            $data['classroom_exist'] = true;
                        }
                        break;

                    case "local_certification":
                        $plugin_exists = core_component::get_plugin_directory('local', 'certification'); 
                        if($plugin_exists){
                            $data['certification_exist'] = true;
                        }
                        break;

                    case "local_program":
                        $plugin_exists = core_component::get_plugin_directory('local', 'program'); 
                        if($plugin_exists){
                            $data['program_exist'] = true;
                        }
                        break;

                    case "local_onlinetests":
                        $plugin_exists = core_component::get_plugin_directory('local', 'onlinetests'); 
                        if($plugin_exists){
                            $data['onlinetests_exist'] = true;
                        }
                        break;

                    case "local_learningplan":
                        $plugin_exists = core_component::get_plugin_directory('local', 'learningplan'); 
                        if($plugin_exists){
                            $data['learningplan_exist'] = true;
                        }
                        break;

                    case "local_evaluations":
                        $plugin_exists = core_component::get_plugin_directory('local', 'evaluation'); 
                        if($plugin_exists){
                            $data['evaluation_exist'] = true;
                        }
                        break;

                    default:
                        $category_name = '';
                }
           
            $alllist=array();
            foreach ($report_results as $results) {
                $list=array();

                /////Action buttons////
                $editcell = '';

                $editcell .= '<a title="' . $stredit . '" class ="icon" href="editreportdetails.php?id=' . $results->id . '"><i class="icon fa fa-pencil fa-fw iconsmall"></i></a>';
                $editcell .= '<a title="' . $strdelete . '" class ="icon"  href="editreportdetails.php?id=' . $results->id . '&amp;delete=1&sesskey=' . $USER->sesskey . '"><i class="icon fa fa-trash iconsmall"></i></a>';

                if (!empty($results->visible)) {
                    $editcell .= '<a title="' . $strhide . '" class ="icon" href="editreportdetails.php?id=' . $results->id . '&hide=1&sesskey=' . $USER->sesskey . '"><i class="icon fa fa-eye fa-fw iconsmall"></i></a> ';
                } else {
                    $editcell .= '<a title="' . $strshow . '" class ="icon" href="editreportdetails.php?id=' . $results->id . '&show=1&sesskey=' . $USER->sesskey . '"><i class="icon fa fa-eye-slash iconsmall"></i></a> ';
                }

                $editcell .= '<a title="' . $strcopy . '" class ="icon" href="editreportdetails.php?id=' . $results->id . '&duplicate=1&sesskey=' . $USER->sesskey . '"><i class="icon fa fa-files-o iconsmall"></i></a>';
                $editcell .= '<a title="' . $strexport . '" class ="icon" href="export.php?id=' . $results->id . '&sesskey=' . $USER->sesskey . '"><i class="icon fa fa-upload iconsmall"></i></a>';

                $properties = new stdClass();
                $properties->courseid = $courseid;
                $reportclass = $ls->create_reportclass($results->id, $properties);
                if ($reportclass->parent && $results->type != 'statistics') {
                    $editcell .= '<a title="' . $strschedule . '" href="./components/scheduler/schedule.php?id=' . $results->id . '&courseid=' . $results->courseid . '&sesskey=' . $USER->sesskey . '"><i class="icon fa fa-calendar iconsmall"></i></a>';
                }

                //////////Download////////////
                $download = '';
                $export = explode(',', $results->export);
                if (!empty($export)) {
                    foreach ($export as $e) {
                        if ($e) {
                            $download .= '<a href="viewreport.php?id=' . $results->id . '&amp;download=1&amp;format=' . $e . '" title="'.(strtoupper($e)).'"><img src="' . $CFG->wwwroot . '/blocks/learnerscript/export/' . $e . '/pix.gif" alt="' . $e . '">&nbsp;' . (strtoupper($e)) . '</a>';
                        } else {
                            $download .= '--';
                        }
                    }
                }
                
                ////For class fav icons/////
                $user_report = false;
                $course_report = false;
                $classroom_report = false;
                $certification_report = false;
                $program_report = false;
                $test_report = false;
                $lp_report = false;
                $lp_report = false;
                $costcenter_report = false;
                $feedback_report = false;
                //Category names
                switch ($r->category) {
                    case "course":
                        $category_name='Course';
                        $plugin_exists = core_component::get_plugin_directory('local', 'courses'); 
                        if($plugin_exists){
                            $course_report = true;
                        }
                        break;

                    case "user":
                        $category_name='User';
                        $plugin_exists = core_component::get_plugin_directory('local', 'users'); 
                        if($plugin_exists){
                            $user_report = true;
                        }
                        break;

                    case "local_classroom":
                        $category_name='Classroom';
                        $plugin_exists = core_component::get_plugin_directory('local', 'classroom'); 
                        if($plugin_exists){
                            $classroom_report = true;
                        }
                        break;

                    case "local_certification":
                        $category_name='Certification';
                        $plugin_exists = core_component::get_plugin_directory('local', 'certification'); 
                        if($plugin_exists){
                            $certification_report = true;
                        }
                        break;

                    case "local_program":
                        $category_name='Program';
                        $plugin_exists = core_component::get_plugin_directory('local', 'program'); 
                        if($plugin_exists){
                            $program_report = true;
                        }
                        break;

                    case "local_onlinetests":
                        $category_name='Online Test';
                        $plugin_exists = core_component::get_plugin_directory('local', 'onlinetests'); 
                        if($plugin_exists){
                            $test_report = true;
                        }
                        break;

                    case "local_learningplan":
                        $category_name='Learning Path';
                        $plugin_exists = core_component::get_plugin_directory('local', 'learningplan'); 
                        if($plugin_exists){
                            $lp_report = true;
                        }
                        break;

                    case "local_evaluations":
                        $category_name='Feedbacks';
                        $plugin_exists = core_component::get_plugin_directory('local', 'evaluation'); 
                        if($plugin_exists){
                            $feedback_report = true;
                        }
                        break;

                    default:
                        $category_name = '';
                }

                $list['category_name']=$category_name;
                $list['classroom_report']=$classroom_report;
                $list['course_report']=$course_report;
                $list['user_report']=$user_report;
                $list['certification_report']=$certification_report;
                $list['program_report']=$program_report;
                $list['test_report']=$test_report;
                $list['lp_report']=$lp_report;
                $list['costcenter_report']=$costcenter_report;
                $list['feedback_report']=$feedback_report;
                $list['report_id']=$results->id;
                $report_name = strlen($results->name) > 20 ? substr($results->name, 0, 20)."..." : $results->name;
                $list['report_name']=$report_name;
                if(strlen($results->name) > 25){
                    $list['report_name_class'] = true;
                }
                $list['reportfullname'] = $results->name;
                $list['actions']=$editcell;
                $list['download']=$download;
                $alllist[]=$list;
            }
            $data[$r->category]=$alllist;
        }
    }       

    $reportresults = $PAGE->get_renderer('block_learnerscript');
    echo $reportresults->viewreportdetails($data);
    
// } else {
//     echo $OUTPUT->heading(get_string('noreportsavailable', 'block_learnerscript'));
// }

echo $OUTPUT->footer();
