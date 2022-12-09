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

/** Learner Script
 * A Moodle block for creating LearnerScript Reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */
require_once("../../config.php");
use \block_learnerscript\local\ls as ls;
$id = required_param('id', PARAM_INT);
$download = optional_param('download', false, PARAM_BOOL);
$format = optional_param('format', '', PARAM_ALPHA);
$courseid = optional_param('courseid', SITEID, PARAM_INT);
$status = optional_param('status', '', PARAM_TEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);
$drillid = optional_param('_drillid', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$cid = optional_param('cid', '', PARAM_ALPHANUM);
$comp = optional_param('comp', '', PARAM_ALPHA);
$pname = optional_param('pname', '', PARAM_ALPHA);

if (!is_siteadmin() && empty($_SESSION['role'])) {
	$rolelist = (new ls)->get_currentuser_roles();
	if (empty($_SESSION['role']) && !empty($rolelist)) {
        $role = empty($_SESSION['role']) ? array_shift($rolelist) : $_SESSION['role'];
    } else {
        $role = '';
    }
    $_SESSION['role'] = $role;
}

$filterrequests = array();
$datefilterrequests = array();
$datefilterrequests['ls_fstartdate'] = 0;
$datefilterrequests['ls_fenddate'] = time();
foreach ($_REQUEST as $key => $val) {
	if (strpos($key, 'filter_') !== false) {
		$filterrequests[$key] = optional_param($key, $val, PARAM_RAW);
	}
	if (strpos($key, 'ls_') !== false) {
		$datefilterrequests[$key] = optional_param($key, $val, PARAM_RAW);
	}
}
if (!$report = $DB->get_record('block_learnerscript', array('id' => $id))) {
	print_error('reportdoesnotexists', 'block_learnerscript');
}

if ($courseid and $report->global) {
	$report->courseid = $courseid;
} else {
	$courseid = $report->courseid;
}
if ($userid > 0) {
	$report->userid = $userid;
}
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error(get_string('nocourseid', 'block_learnerscript'));
}

// Force user login in course (SITE or Course)
// if ($course->id == SITEID) {
// 	require_login();
// 	$context = context_system::instance();
// } else {
// 	require_login($course);
// 	$context = context_course::instance($course->id);
// }
require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_title($report->name);
$PAGE->set_pagelayout('report');

if ($delete && confirm_sesskey()) {
	$components = (new block_learnerscript\local\ls)->cr_unserialize($report->components);
	$elements = isset($components[$comp]['elements']) ? $components[$comp]['elements'] : array();
	foreach ($elements as $index => $e) {
		if ($e['id'] == $cid) {
			if ($delete) {
				unset($elements[$index]);
				break;
			}
			$newindex = ($moveup) ? $index - 1 : $index + 1;
			$tmp = $elements[$newindex];
			$elements[$newindex] = $e;
			$elements[$index] = $tmp;
			break;
		}
	}
	$components[$comp]['elements'] = $elements;
	$report->components = (new block_learnerscript\local\ls)->cr_serialize($components);
	$DB->update_record('block_learnerscript', $report);
	redirect(new moodle_url('/blocks/learnerscript/viewreport.php', array('id' => $id, 'courseid' => $courseid)));
	exit;
}

require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
$properties = new stdClass();
$reportclassname = 'report_' . $report->type;
$reportclass = new $reportclassname($report, $properties);
$reportclass->courseid = $courseid;
if (!$download) {
	$reportclass->start = 0;
	$reportclass->length = 1;
} else {
	$reportclass->length = -1;
}
$reportclass->search = '';
$reportclass->filters = $filterrequests;
$reportclass->basicparamdata = $filterrequests;
$reportclass->status = $status;
$reportclass->ls_startdate = $datefilterrequests['ls_fstartdate'];
$reportclass->ls_enddate = $datefilterrequests['ls_fenddate'];

$reportclass->cmid = $cmid;
$reportclass->userid = $userid;
if (!is_siteadmin() && !$reportclass->check_permissions($USER->id, $context)) {
	print_error("badpermissions", 'block_learnerscript');
}
$basicparamdata = new stdclass;
$request = array_merge($_POST, $_GET);
if ($request){
    foreach ($request as $key => $val) {
        if (strpos($key, 'filter_') !== false) {
        	$plugin = str_replace('filter_', '', $key);
            $basicparamdata->{$key} = $val;
            if(file_exists($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $plugin . '/plugin.class.php') && !empty($val)){
	            require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $plugin . '/plugin.class.php');
	            $classname = 'plugin_' . $plugin;
	            $class = new $classname($reportclass->config);
	            //$selected = get_string('selectedfilter', 'block_learnerscript', ucfirst($plugin));
	            $selected = get_string('selectedfilter', 'block_learnerscript', get_string($plugin, 'block_learnerscript'));
	            $reportclass->selectedfilters[$selected] = $class->selected_filter($val, $request);
        	}
        }
    }
}
$reportclass->params = (array)$basicparamdata;
$reportname = format_string($report->name);

$PAGE->set_url('/blocks/learnerscript/viewreport.php', array('id' => $id));

$download = ($download && $format && strpos($report->export, $format) !== false) ? true : false;

//$PAGE->requires->js('/blocks/learnerscript/js/highcharts/highcharts.js');
$PAGE->requires->js('/blocks/learnerscript/js/highcharts/treemap.js');
$PAGE->requires->js('/blocks/learnerscript/js/highmaps/map.js');
$PAGE->requires->js('/blocks/learnerscript/js/highmaps/world.js');
$PAGE->requires->css('/blocks/reportdashboard/css/radios-to-slider.min.css');
$PAGE->requires->css('/blocks/reportdashboard/css/flatpickr.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/fixedHeader.dataTables.min.css');
$PAGE->requires->css('/blocks/learnerscript/css/responsive.dataTables.min.css');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->css('/blocks/learnerscript/css/select2.min.css', true);
$PAGE->requires->css('/blocks/learnerscript/css/jquery.dataTables.min.css', true);

$PAGE->requires->js_call_amd('block_learnerscript/dependencyfilter', 'load',array());
// No download, build navigation header etc.
if (!$download) {
    $reportshead_start = get_config('block_reportdashboard', 'header_start');
    $reportshead_end = get_config('block_reportdashboard', 'header_end');
    $reportshead_start = empty($reportshead_start) ? '#0d3c56' : $reportshead_start;
    $reportshead_end = empty($reportshead_end) ? '#35779b' : $reportshead_end;

	$columndata = (new ls)->column_definations($reportclass);
	$PAGE->requires->js_call_amd('block_learnerscript/report', 'init',
									array(array('reportid' => $id,
												'filterrequests' => $filterrequests,
												'cols' => $columndata['datacolumns'],
												'columnDefs' => $columndata['columnDefs'],
												'basicparams' =>$reportclass->basicparams
											),
								));

	$reportclass->check_filters_request($_SERVER['REQUEST_URI']);

	require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');
	
	$params = get_reportdashboard();
	$dashboardurl = new moodle_url($CFG->wwwroot .'/blocks/reportdashboard/dashboard.php', $params);
	$PAGE->navbar->add(get_string("reportdashboard", 'block_learnerscript'), $dashboardurl);
	
	// if (has_capability('block/learnerscript:managereports', $context) ||
	// 	(has_capability('block/learnerscript:manageownreports', $context)) &&
	// 	$report->ownerid == $USER->id) {
	// 	$managereporturl = new moodle_url($CFG->wwwroot . '/blocks/learnerscript/managereport.php');
	// 	$PAGE->navbar->add(get_string('managereports', 'block_learnerscript'), $managereporturl);
	// } else {
	// 	$dashboardurl = new moodle_url($CFG->wwwroot . '/blocks/learnerscript/reports.php', array());

	// 	$PAGE->navbar->add(get_string("reports_view", 'block_learnerscript'), $dashboardurl);
	// }
	if ($drillid > 0) {
		$drillreporturl = new moodle_url($CFG->wwwroot . '/blocks/learnerscript/viewreport.php', array('id' => $drillid));
		$drillreportname = $DB->get_field('block_learnerscript', 'name', array('id' => $drillid));
		$PAGE->navbar->add($drillreportname, $drillreporturl);
	}
	$PAGE->navbar->add($report->name);
	$reportsfont = get_config('block_reportdashboard', 'reportsfont');
	if ($reportsfont == 2) { /*selected font as PT Sans*/
		$PAGE->requires->css('/blocks/reportdashboard/fonts/roboto.css');
	} else if ($reportsfont == 1) { /*selected font as Open Sans*/
		$PAGE->requires->css('/blocks/reportdashboard/fonts/roboto.css');
	}
	$PAGE->set_cacheable(true);
	$event = \block_learnerscript\event\view_report::create(array(
		'objectid' => $report->id,
		'context' => $context,
	));
	$event->trigger();
	echo $OUTPUT->header();
	echo '<script src="'.$CFG->wwwroot . '/blocks/learnerscript/js/highcharts/highcharts.js"></script>';
	if ($report->type == 'sql' || $report->type == 'statistics') {
		echo $OUTPUT->heading($report->name);
	} else {
		echo $OUTPUT->heading($report->name.$OUTPUT->help_icon('report_' . $report->type,
			'block_learnerscript'));
	}
	echo html_writer::start_tag('div', array('id'=>'licenceresult'));
	$renderer = $PAGE->get_renderer('block_learnerscript');
	if ($drillid > 0) {
		echo $OUTPUT->single_button($drillreporturl, 'Go back to:' . $drillreportname);
	}
	$disabletable = !empty($report->disabletable) ? $report->disabletable : 0;
	$renderer->viewreport($report, $context, $reportclass);
	echo "<input type='hidden' name='ls_fstartdate' id='ls_fstartdate' value=0 />
    	  <input type='hidden' name='ls_fenddate' id='ls_fenddate' value=".time()." />
    	  <input type='hidden' name='reportid' value=" . $report->id . " />
          <input type='hidden' name='disabletable' id='disabletable' value=" . $disabletable . " />";
	echo html_writer::end_tag('div');
	echo $OUTPUT->footer();
} else {
	$reportclass->reporttype = 'table';
	$reportclass->create_report();
	$exportplugin = $CFG->dirroot . '/blocks/learnerscript/export/' . $format . '/export.php';
	if (file_exists($exportplugin)) {
		require_once($exportplugin);
		$reportclass->finalreport->name = $reportclass->config->name;
		export_report($reportclass, $id);
	}
	die;
}
