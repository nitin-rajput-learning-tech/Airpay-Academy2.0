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
 * @subpackage local_request
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/local/courses/filters_form.php');
$component = optional_param('component', null, PARAM_RAW);
$courseid = optional_param('courseid', '', PARAM_INT);
global $OUTPUT, $PAGE, $USER;
require_login();
$title = get_string('viewrequest', 'local_request');

// Set up the page.
$url = new moodle_url("/local/request/index.php");
$sitecontext =(new \local_request\lib\accesslib())::get_module_context();
$PAGE->set_context($sitecontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_url($url);

$PAGE->set_title($title);
$PAGE->navbar->add(get_string("pluginname", 'local_request'));
$PAGE->set_heading($title);
$PAGE->requires->js_call_amd('local_request/requestconfirm', 'load', array());

/* if(!is_siteadmin() && !has_capability('local/request:approverecord', $sitecontext)){
    redirect($CFG->wwwroot.'/my/dashboard.php');
} */
$output = $PAGE->get_renderer('local_request');
if($courseid){
    if(!(is_siteadmin() || has_capability('local/request:approverecord', $sitecontext))){
        $coursedata = get_course($courseid);
        $usercostcenterid = explode('/',$USER->open_path)[1];
        if($usercostcenterid != $coursedata->open_costcenterid){
            redirect($CFG->wwwroot.'/local/courses/courses.php');
        }
    }
}
echo $output->header();

    $list_params = array();
    $systemcontext =(new \local_request\lib\accesslib())::get_module_context();
    if(has_capability('local/request:viewrecord',$systemcontext) || is_siteadmin()){
        $componentarray = array('elearning', 'classroom', 'learningplan', 'program');
     if((!$component && !$courseid) || in_array($component, $componentarray)){
            $filterparams = $output->render_requestview(true, $courseid, $component);
            if($component=='elearning'){
                 $filters = array('courses');
            }elseif($component=='classroom'){
                 $filters = array('classroom');
            }elseif($component=='learningplan'){
                 $filters = array('learningplan');
            }elseif($component=='program'){
                 $filters = array('program');
            }else{
                 $filters = array('request');
            }
            $filters[] = 'requeststatus';
            $mform = new filters_form($CFG->wwwroot.'/local/request/index.php', array('filterlist'=>$filters,'filterparams' => $filterparams));
            if ($mform->is_cancelled()) {
                redirect($CFG->wwwroot . '/local/request/index.php');
            } else if ($filterdata =  $mform->get_data()){
                if($filterdata){
                    $collapse = false;
                    $show = 'show';
                } else{
                    $collapse = true;
                    $show = '';
                }
                if(!empty($filterdata)){
                    $list_params = $filterdata;
                }else{
                    $collapse = true;
                    $show = '';
                    $list=null;
                }
                $sorting = $filterdata->sorting;
                 //passing options and dataoptions in filter

            }else{
                $collapse = true;
                $show = '';
                $list=null;
            }

            $heading = '<span class="filter-lebel">'.get_string('filters').'</span>';
            echo '<a class="btn-link btn-sm" href="javascript:void(0);" data-toggle="collapse" data-target="#local_request-filter_collapse" aria-expanded="false" aria-controls="local_request-filter_collapse" title="'.get_string('filters','local_request').'">
                <i class="m-0 fa fa-sliders fa-2x" aria-hidden="true"></i>
              </a>';
            echo  '<div class="collapse '.$show.'" id="local_request-filter_collapse">
                        <div id="filters_form" class="card card-body p-2">';
                            $mform->display();
            echo  '</div>
                </div>';
               $list=null;
        }
       echo $output->render_requestview(false,$courseid,$component);
    }
    else{

        throw new moodle_exception('permission denied');
    }




echo $output->footer();
