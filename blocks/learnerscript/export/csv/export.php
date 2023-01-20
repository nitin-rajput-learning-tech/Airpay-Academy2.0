<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/** LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */
ini_set("memory_limit", "-1");
ini_set('max_execution_time', 6000);
use \block_learnerscript\Spout\Common\Type;
use \block_learnerscript\Spout\Writer\WriterFactory;

require_once $CFG->dirroot . '/blocks/learnerscript/lib.php';
require_once $CFG->libdir . '/adminlib.php';
function export_report($reportclass) {
	global $DB, $CFG, $SESSION,$USER;
	$reportdata = $reportclass->finalreport;
	$writer = WriterFactory::create(Type::CSV); // for XLSX files
	require_once $CFG->dirroot . '/lib/excellib.class.php';
	$table = $reportdata->table;
	$filename = $reportdata->name . "_" . Date('d M Y H:i:s', time()) . '.csv';
	$writer->openToBrowser($filename); // stream data directly to the browser

	$reportdata = export_reportdata($writer,$reportdata, $reportclass);

    if (!empty($table->head)) {
		foreach ($table->head as $key => $heading) {
			$head[] = $heading;
		}
		$writer->addRow($head);
	}
     $data = array();
	if(!empty($table->data)) {
		foreach ($table->data as $key => $value) {
			$data[] = array_map(function ($v) {
				return trim(strip_tags($v));
			}, $value);
		}
	}
	$writer->addRows($data); // add a row at a time
	$writer->close();
}
function export_reportdata($writer,$reportdata, $reportclass){
	global $DB, $CFG, $USER;
	$filters =  $reportclass->filters;
	$head = array();
    $report[] = get_string('reportnames','block_learnerscript'); 
	$report[] = $reportdata->name;
	$writer->addRow($report);

    $fileds = 'firstname,lastname';
	$userinfo = $DB->get_record('user',array('id'=>$USER->id),$fileds);
	$downloaded[] = get_string('downloadedby','block_learnerscript');
	$downloaded[] = $userinfo->firstname.' '.$userinfo->lastname;
	$writer->addRow($downloaded);

	$time[] = get_string('downloadedon','block_learnerscript');
	$time[] = Date('d M Y H:i', time());
	$writer->addRow($time);

	$filters_display[] =  get_string('filter','block_learnerscript');
	$writer->addRow($filters_display); 
	if(!empty($filters)){
	foreach($filters  as $key=>$value){
			switch($key){
	            case 'filter_organization':
				 $organization = $DB->get_field('local_costcenter','fullname',array('id'=>$value));
				 $organizations = array();
	             $organizations[] = get_string('organizations','block_learnerscript');
	             $organizations[] = $organization ? $organization : '--';
	             $writer->addRow($organizations);
	             break;
	             case 'filter_departments':
				 $department = $DB->get_field('local_costcenter','fullname',array('id'=>$value));
				 $departments = array();
	             $departments[] = get_string('departments','block_learnerscript');
	             $departments[] = $department ? $department : '--';
	             $writer->addRow($departments);
	             break;
	             case 'filter_subdepartments':
				 $subdepartment = $DB->get_field('local_costcenter','fullname',array('id'=>$value));
				 $subdepartments = array();
	             $subdepartments[] = get_string('subdepartments','block_learnerscript');
	             $subdepartments[] = $subdepartment ? $subdepartment : '--';
	             $writer->addRow($subdepartments);
	             break;
	             case 'filter_level4department':
				 $level4department = $DB->get_field('local_costcenter','fullname',array('id'=>$value));
				 $level4departments = array();
	             $level4departments[] = get_string('level4department','block_learnerscript');
	             $level4departments[] = $level4department ? $level4department : '--';
	             $writer->addRow($level4departments);
	             break;
	             case 'filter_level5department':
				 $level5department = $DB->get_field('local_costcenter','fullname',array('id'=>$value));
				 $level5departments = array();
	             $level5departments[] = get_string('level5department','block_learnerscript');
	             $level5departments[] = $level5department ? $level5department : '--';
	             $writer->addRow($level5departments);
	             break;
	             case 'filter_geostate':
				 $geostate = $DB->get_field('local_states','states_name',array('id'=>$value));
				 $geostates = array();
	             $geostates[] = get_string('geostate','block_learnerscript');
	             $geostates[] = $geostate ? $geostate : '--';
	             $writer->addRow($geostates);
	             break;
	             case 'filter_geodistrict':
				 $geodistrict = $DB->get_field('local_district','district_name',array('id'=>$value));
				 $geodistricts = array();
	             $geodistricts[] = get_string('geodistrict','block_learnerscript');
	             $geodistricts[] = $geodistrict ? $geodistrict : '--';
	             $writer->addRow($geodistricts);
	             break;
	             case 'filter_geosubdistrict':
				 $geosubdistrict = $DB->get_field('local_subdistrict','subdistrict_name',array('id'=>$value));
				 $geosubdistricts = array();
	             $geosubdistricts[] = get_string('geosubdistrict','block_learnerscript');
	             $geosubdistricts[] = $geosubdistrict ? $geosubdistrict : '--';
	             $writer->addRow($geosubdistricts);
	             break;
	             case 'filter_geovillage':
				 $geovillage = $DB->get_field('local_village','village_name',array('id'=>$value));
				 $geovillages = array();
	             $geovillages[] = get_string('geovillage','block_learnerscript');
	             $geovillages[] = $geovillage ? $geovillage : '--';
	             $writer->addRow($geovillages);
	             break;
	             case 'filter_course':
				 $course = $DB->get_field('course','fullname',array('id'=>$value));
				 $coursefilter = array();
	             $coursefilter[] = get_string('courses','block_learnerscript');
	             $coursefilter[] = $course ? $course : '--';
	             $writer->addRow($coursefilter);
	             break;
	             case 'filter_user':
				 $employe = $DB->get_field('user','firstname',array('id'=>$value));
				 $userfilter = array();
	             $userfilter[] = get_string('employe','block_learnerscript');
	             $userfilter[] = $employe ? $employe : '--';
	             $writer->addRow($userfilter);
                 break;
	             case 'filter_classrooms':
				 $classroom = $DB->get_field('local_classroom','name',array('id'=>$value));
				 $classroomfilter = array();
	             $classroomfilter[] = get_string('classroom','block_learnerscript');
	             $classroomfilter[] = $classroom ? $classroom : '--';
	             $writer->addRow($classroomfilter);
	             break;
	             case 'filter_certificates':
				 $certificate = $DB->get_field('local_certification','name',array('id'=>$value));
			     $certificatefilter = array();
	             $certificatefilter[] = get_string('certificate','block_learnerscript');
	             $certificatefilter[] = $certificate ? $certificate : '--';
	             $writer->addRow($certificatefilter);
	             break;
	             case 'filter_programs':
				 $program = $DB->get_field('local_program','name',array('id'=>$value));
			     $programfilter = array();
                 $programfilter[] = get_string('program','block_learnerscript');
	             $programfilter[] = $program ? $program : '--';
	             $writer->addRow($programfilter);
	             break;
	             case 'filter_userstatus':
	             $userstatus[] = get_string('completionstatus','block_learnerscript');
	             if($value == 0){
			       $status = 'Notcompleted';
				 }else{
                   $status = 'Completed';
				 }
				 $userstatus[] = $status ? $status : '--';
	             $writer->addRow($userstatus);
	             break;
                 case 'filter_onlinetests':
				 $onlinetest = $DB->get_field('local_onlinetests','name',array('id'=>$value));
				 $onlinetestfilter = array();
	             $onlinetestfilter[] = get_string('onlinetest','block_learnerscript');
	             $onlinetestfilter[] = $onlinetest ? $onlinetest : '--';
	             $writer->addRow($onlinetestfilter);
	             break;
	             case 'filter_learningplans':
				 $learningplan = $DB->get_field('local_learningplan','name',array('id'=>$value));
				 $learningplanfilter = array();
                 $learningplanfilter[] = get_string('learningplan','block_learnerscript');
	             $learningplanfilter[] = $learningplan ? $learningplan : '--';
	             $writer->addRow($learningplanfilter);
	             break;
	             case 'filter_skills':
				 $skill = $DB->get_field('local_skill','name',array('id'=>$value));
				 $skillfilter = array();
	             $skillfilter[] = get_string('skil','block_learnerscript');
	             $skillfilter[] = $skill ? $skill : '--';
	             $writer->addRow($skillfilter);
	             break;
	             case 'filter_levels':
				 $level = $DB->get_field('local_course_levels','name',array('id'=>$value));
				 $levelfilter = array();
	             $levelfilter[] = get_string('levell','block_learnerscript');
	             $levelfilter[] = $level ? $level : '--';
	             $writer->addRow($levelfilter);
	             break;
	             case 'filter_usermodule':
				 $user = $DB->get_field('user','firstname',array('id'=>$value));
				 $usermodulefilter = array();
				 $usermodulefilter[] = get_string('employe','block_learnerscript');
	             $usermodulefilter[] = $user ? $user : '--';
	             $writer->addRow($usermodulefilter);
                 break;
	             case 'filter_mycoursescolumn':
				 $mycourses = $DB->get_field('course','fullname',array('id'=>$value));
				 $mycoursescolumn = array();
	             $mycoursescolumn[] = get_string('courses','block_learnerscript');
	             $mycoursescolumn[] = $mycourses ? $mycourses : '--';
	             $writer->addRow($mycoursescolumn);
	             break;
	             case 'filter_mylearningplan':
				 $mylearningplans = $DB->get_field('local_learningplan','name',array('id'=>$value));
				 $mylearningplan = array();
	             $mylearningplan[] = get_string('learningplan','block_learnerscript');
	             $mylearningplan[] = $mylearningplans ? $mylearningplans : '--';
	             $writer->addRow($mylearningplan);
                 break;
	             case 'filter_myonlinetestcolumns':
				 $myonlinetest = $DB->get_field('local_onlinetests','name',array('id'=>$value));
				 $myonlinetestcolumns = array();
	             $myonlinetestcolumns[] = get_string('onlinetest','block_learnerscript');
	             $myonlinetestcolumns[] = $myonlinetest ? $myonlinetest : '--';
	             $writer->addRow($myonlinetestcolumns);
	             break;
	             case 'filter_mycertificates':
				 $mycertificate = $DB->get_field('local_certification','name',array('id'=>$value));
				 $mycertificates = array();
	             $mycertificates[] = get_string('certificate','block_learnerscript');
	             $mycertificates[] = $mycertificate ? $mycertificate : '--';
	             $writer->addRow($mycertificates);
                 break;
                case 'filter_myclassroomcolumns':
				 $myclassroom = $DB->get_field('local_classroom','name',array('id'=>$value));
				 $myclassroomcolumns = array();
	             $myclassroomcolumns[] = get_string('classroom','block_learnerscript');
	             $myclassroomcolumns[] = $myclassroom ? $myclassroom : '--';
	             $writer->addRow($myclassroomcolumns);
                 break;
                case 'filter_myprograms':
				 $myprogram = $DB->get_field('local_program','name',array('id'=>$value));
				 $myprograms = array();
	             $myprograms[] = get_string('program','block_learnerscript');
	             $myprograms[] = $myprogram ? $myprogram : '--';
	             $writer->addRow($myprograms);
                 break;
                case 'filter_status';
	             $userstatus[] = get_string('completionstatus','block_learnerscript');
                 switch($value){
                 	case 0:
                 	   $value = get_string('newclasses','local_classroom');
                 	 break;
                 	 case 1:
                 	   $value = get_string('activeclasses','local_classroom');
                 	 break;
                 	 case 2:
                 	   $value = get_string('holdclasses','local_classroom');
                 	 break;
                 	 case 3:
                 	   $value = get_string('cancelledclasses','local_classroom');
                 	 break;
                 	 case 4:
                 	   $value = get_string('completedclasses','local_classroom');
                 	 break;
                  }
                 $userstatus[] =  $value ?  $value : '--';
	             $writer->addRow($userstatus);
	             break;
               }
		     }
	       }
    if(!empty($filter)){
        $writer->addRow($filter);
    }
}