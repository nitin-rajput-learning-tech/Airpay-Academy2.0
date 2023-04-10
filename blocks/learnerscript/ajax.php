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
define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/learnerscript/lib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
use block_learnerscript\local\ls;
use block_learnerscript\local\reportbase;
use block_learnerscript\local\schedule;

global $CFG, $DB, $USER, $OUTPUT, $PAGE;

$rawjson = file_get_contents('php://input');

$requests = json_decode($rawjson, true);

foreach ($requests as $key => $val) {
    if (strpos($key, 'filter_') !== false) {
        $_POST[$key] = $val;
    }
}
if (empty($requests['scheduleid'])) {
	$scheduleid = 0;
} else {
	$scheduleid = $requests['scheduleid'];
}
if (empty($requests['selectedroleid'])) {
	$selectedroleid = '';
} else {
	$selectedroleid = $requests['selectedroleid'];
}
if (empty($requests['roleid'])) {
	$roles = 0;
} else {
	$roles = $requests['roleid'];
}
if (empty($requests['term'])) {
	$search = '';
} else {
	$search = $requests['term'];
}
if (empty($requests['type'])) {
	$type = '';
} else {
	$type = $requests['type'];
}
if (empty($requests['schuserslist'])) {
	$schuserslist = '';
} else {
	$schuserslist = $requests['schuserslist'];
}
if (empty($requests['bullkselectedusers'])) {
	$bullkselectedusers = '';
} else {
	$bullkselectedusers = $requests['bullkselectedusers'];
}
if (empty($requests['validdate'])) {
	$validdate = 0;
} else {
	$validdate = $requests['validdate'];
}
if (empty($requests['page'])) {
	$page = 0;
} else {
	$page = $requests['page'];
}
if (empty($requests['start'])) {
	$start = 0;
} else {
	$start = $requests['start'];
}
if (empty($requests['length'])) {
	$length = 0;
} else {
	$length = $requests['length'];
}
if (empty($requests['courseid'])) {
	$courseid = 0;
} else {
	$courseid = $requests['courseid'];
}
if (empty($requests['frequency'])) {
	$frequency = 0;
} else {
	$frequency = $requests['frequency'];
}
if (empty($requests['instance'])) {
	$instance = 0;
} else {
	$instance = $requests['instance'];
}
if (empty($requests['cmid'])) {
	$cmid = 0;
} else {
	$cmid = $requests['cmid'];
}
if (empty($requests['status'])) {
	$status = '';
} else {
	$status = $requests['status'];
}
if (empty($requests['userid'])) {
	$userid = 0;
} else {
	$userid = $requests['userid'];
}
if (empty($requests['components'])) {
	$components = '';
} else {
	$components = $requests['components'];
}
if (empty($requests['component'])) {
	$component = '';
} else {
	$component = $requests['component'];
}
if (empty($requests['pname'])) {
	$pname = '';
} else {
	$pname = $requests['pname'];
}
if (empty($requests['jsonformdata'])) {
	$jsonformdata = '';
} else {
	$jsonformdata = $requests['jsonformdata'];
}
if (empty($requests['conditions'])) {
	$conditionsdata = '';
} else {
	$conditionsdata = $requests['conditions'];
}
if (empty($requests['advancedcolumn'])) {
	$advancedcolumn = '';
} else {
	$advancedcolumn = $requests['advancedcolumn'];
}
if (empty($requests['export'])) {
	$export = '';
} else {
	$export = $requests['export'];
}
if (empty($requests['ls_fstartdate'])) {
	$lsfstartdate = 0;
} else {
	$lsfstartdate = $requests['ls_fstartdate'];
}
if (empty($requests['ls_fenddate'])) {
	$lsfenddate = 0;
} else {
	$lsfenddate = $requests['ls_fenddate'];
}
if (empty($requests['cid'])) {
	$cid = '';
} else {
	$cid = $requests['cid'];
}
if (empty($requests['reporttype'])) {
	$reporttype = '';
} else {
	$reporttype = $requests['reporttype'];
}
if (empty($requests['categoryid'])) {
	$categoryid = 0;
} else {
	$categoryid = $requests['categoryid'];
}
if (empty($requests['filters'])) {
	$filters = '';
} else {
	$filters = $requests['filters'];
}
if (empty($requests['basicparams'])) {
	$basicparams = '';
} else {
	$basicparams = $requests['basicparams'];
}
if (empty($requests['elementsorder'])) {
	$elementsorder = '';
} else {
	$elementsorder = $requests['elementsorder'];
}
if (empty($requests['contextlevel'])) {
	$contextlevel = 0;
} else {
	$contextlevel = $requests['contextlevel'];
}
if (empty($requests['reportid'])) {
	$reportid = 0;
} else {
	$reportid = $requests['reportid'];
}

if (empty($requests['orgid'])) {
	$orgid = 0;
} else {
	$orgid = $requests['orgid'];
} 
if (empty($requests['currentstate'])) {
	$stateid = 0;
} else {
	$stateid = $requests['currentstate'];
}
if (empty($requests['currentdistrict'])) {
	$districtid = 0;
} else {
	$districtid = $requests['currentdistrict'];
}
if (empty($requests['currentsubdistrict'])) {
	$subdistrictid = 0;
} else {
	$subdistrictid = $requests['currentsubdistrict'];
}


if (empty($requests['departmentid'])) {
	$departmentid = 0;
} else {
	$departmentid = $requests['departmentid'];
}
if (empty($requests['subdepartmentid'])) {
	$subdepartmentid = 0;
} else {
	$subdepartmentid = $requests['subdepartmentid'];
}
if (empty($requests['currentl4depid'])) {
	$currentl4depid = 0;
} else {
	$currentl4depid = $requests['currentl4depid'];
}
if (empty($requests['currentl5depid'])) {
	$currentl5depid = 0;
} else {
	$currentl5depid = $requests['currentl5depid'];
}

$action = $requests['action'];
$reportid = optional_param('reportid', $reportid, PARAM_INT);
$scheduleid = optional_param('scheduleid', $scheduleid, PARAM_INT);
$selectedroleid = optional_param('selectedroleid', $selectedroleid, PARAM_RAW);
$roles = optional_param('roleid', $roles, PARAM_RAW);
$search = optional_param('search', $search, PARAM_TEXT);
$type = optional_param('type', $type, PARAM_TEXT);
$schuserslist = optional_param('schuserslist', $schuserslist, PARAM_RAW);
$bullkselectedusers = optional_param('bullkselectedusers', $bullkselectedusers, PARAM_RAW);
$expireddate = optional_param('validdate', $validdate, PARAM_RAW);
$page = optional_param('page', $page, PARAM_INT);
$start = optional_param('start', $start, PARAM_INT);
$length = optional_param('length', $length, PARAM_INT);
$courseid = optional_param('courseid', $courseid, PARAM_INT);
$frequency = optional_param('frequency', $frequency, PARAM_INT);
$instance = optional_param('instance', $instance, PARAM_INT);
$cmid = optional_param('cmid', $cmid, PARAM_INT);
$status = optional_param('status', $status, PARAM_TEXT);
$userid = optional_param('userid', $userid, PARAM_INT);
$components = optional_param('components', $components, PARAM_RAW);
$component = optional_param('component', $component, PARAM_RAW);
$pname = optional_param('pname', $pname, PARAM_RAW);
$jsonformdata = optional_param('jsonformdata', $jsonformdata, PARAM_RAW);
$conditionsdata = optional_param('conditions', $conditionsdata, PARAM_RAW);
$advancedcolumn = optional_param('advancedcolumn', $advancedcolumn, PARAM_RAW);
$export = optional_param('export', $export, PARAM_RAW);
$ls_fstartdate = optional_param('ls_fstartdate', $lsfstartdate, PARAM_INT);
$ls_fenddate = optional_param('ls_fenddate', $lsfenddate, PARAM_INT);
$cid = optional_param('cid', $cid, PARAM_RAW);
$reporttype = optional_param('reporttype', $reporttype, PARAM_RAW);
$categoryid = optional_param('categoryid', $categoryid, PARAM_INT);
$filters = optional_param('filters', $filters, PARAM_RAW);
$filters = json_decode($filters, true);
$basicparams = optional_param('basicparams', $basicparams, PARAM_RAW);
$basicparams = json_decode($basicparams, true);
$elementsorder = optional_param('elementsorder', $elementsorder, PARAM_RAW);
$contextlevel = optional_param('contextlevel', $contextlevel, PARAM_INT);

//$context = context_system::instance();
$context = (new \local_courses\lib\accesslib())::get_module_context();
$ls = new ls();
require_login();
$PAGE->set_context($context);

$scheduling = new schedule();
$learnerscript = $PAGE->get_renderer('block_learnerscript');

switch ($action) {
case 'rolewiseusers':
	if ((has_capability('block/learnerscript:managereports', $context) || has_capability('block/learnerscript:manageownreports', $context) || is_siteadmin()) && !empty($roles)) {
		$user_list = $scheduling->rolewiseusers($roles, $search, 0, 0, $contextlevel);
		$terms_data = array();
		$terms_data['page'] = $page;
		$terms_data['search'] = $search;
		$terms_data['total_count'] = sizeof($user_list);
		$terms_data['incomplete_results'] = false;
		$terms_data['items'] = $user_list;
		$return = $terms_data;
	} else {
		$terms_data = array();
		$terms_data['error'] = true;
		$terms_data['type'] = 'Warning';
		if (empty($roles)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'Role');
		} else {
			$terms_data['cap'] = true;
			$terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
		}
		$return = $terms_data;
	}
	break;
case 'roleusers':
	if ((has_capability('block/learnerscript:managereports', $context) || has_capability('block/learnerscript:manageownreports', $context) || is_siteadmin()) && !empty($reportid) && !empty($type) && !empty($roles)) {
		$userslist = $scheduling->schroleusers($reportid, $scheduleid, $type, $roles, $search, $bullkselectedusers);
		$terms_data = array();
		$terms_data['total_count'] = sizeof($userslist);
		$terms_data['incomplete_results'] = false;
		$terms_data['items'] = $userslist;
		$return = $terms_data;
	} else {
		$terms_data = array();
		$terms_data['error'] = true;
		$terms_data['type'] = 'Warning';
		if (empty($reportid)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
		} else if (empty($type)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'Type');
		} else if (empty($roles)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'Role');
		} else {
			$terms_data['cap'] = true;
			$terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
		}
		$return = $terms_data;
	}
	break;
case 'viewschuserstable':
	if ((has_capability('block/learnerscript:managereports', $context) || has_capability('block/learnerscript:manageownreports', $context) || is_siteadmin()) && !empty($schuserslist)) {
		$stable = new stdClass();
		$stable->table = true;
		$return = $learnerscript->viewschusers($reportid, $scheduleid, $schuserslist, $stable);
	} else {
		$terms_data = array();
		$terms_data['error'] = true;
		$terms_data['type'] = 'Warning';
		if (empty($schuserslist)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'Schedule Users List');
		} else {
			$terms_data['cap'] = true;
			$terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
		}
		$return = $terms_data;
	}
	break;
case 'manageschusers':
	if ((has_capability('block/learnerscript:managereports', $context) || has_capability('block/learnerscript:manageownreports', $context) || is_siteadmin()) && !empty($reportid)) {
		$reqimage = $OUTPUT->image_url('req');
		//'alt' => get_string('requiredelement', 'form'), 'class' => 'icon', 'title' => get_string('requiredelement', 'form')));

		$roles_list = (new schedule)->reportroles($selectedroleid);
		$selectedusers = (new schedule)->selectesuserslist($schuserslist);
		$scheduledata = new \block_learnerscript\output\scheduledusers($reportid,$reqimage,$roles_list,$selectedusers,$scheduleid);
		$return = $learnerscript->render($scheduledata);
		// $return = $learnerscript->scheduleusers($reportid, $scheduleid, $selectedroleid, $schuserslist);
	} else {
		$terms_data = array();
		$terms_data['error'] = true;
		$terms_data['type'] = 'Warning';
		if (empty($reportid)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
		} else {
			$terms_data['cap'] = true;
			$terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
		}
		$return = $terms_data;
	}
	break;
case 'schreportform':
	// if ((has_capability('block/learnerscript:managereports', $context) || has_capability('block/learnerscript:manageownreports', $context) || is_siteadmin()) && !empty($reportid)) {
	// 	require_once $CFG->dirroot . '/blocks/learnerscript/components/scheduler/schedule_form.php';
	// 	$roles_list = $scheduling->reportroles();
	// 	list($schusers, $schusersids) = $scheduling->userslist($reportid, $scheduleid);
	// 	$exportoptions = $ls->cr_get_export_plugins();
	// 	$frequencyselect = $scheduling->get_options();
	// 	$scheduledreport = $DB->get_record('block_ls_schedule', array('id' => $scheduleid));
	// 	if (!empty($scheduledreport)) {
	// 		$schedule_list = $scheduling->getschedule($scheduledreport->frequency);
	// 	} else {
	// 		$schedule_list = array(null => '--SELECT--');
	// 	}
	// 	$scheduleform = new scheduled_reports_form($CFG->wwwroot . '/blocks/learnerscript/components/scheduler/schedule.php', array('id' => $reportid, 'scheduleid' => $scheduleid, 'AjaxForm' => true, 'roles_list' => $roles_list,
	// 		'schusers' => $schusers, 'schusersids' => $schusersids, 'exportoptions' => $exportoptions, 'schedule_list' => $schedule_list, 'frequencyselect' => $frequencyselect, 'instance' => $instance));

	// 	$return = $scheduleform->render();
	// } else {
	// 	$terms_data = array();
	// 	$terms_data['error'] = true;
	// 	$terms_data['type'] = 'Warning';
	// 	if (empty($reportid)) {
	// 		$terms_data['cap'] = false;
	// 		$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
	// 	} else {
	// 		$terms_data['cap'] = true;
	// 		$terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
	// 	}
	// 	$return = $terms_data;
	// }
	$args = new stdClass();
	$args->reportid = $reportid;
	$args->instance = $instance;
	$args->jsonformdata = $jsonformdata;
	$return = block_learnerscript_schreportform_ajaxform($args);

	break;
case 'scheduledtimings':
	if ((has_capability('block/learnerscript:managereports', $context) || has_capability('block/learnerscript:manageownreports', $context) || is_siteadmin()) && !empty($reportid)) {
		$return = $learnerscript->schedulereportsdata($reportid, $courseid, false, $start, $length, $search['value']);
	} else {
		$terms_data = array();
		$terms_data['error'] = true;
		$terms_data['type'] = 'Warning';
		if (empty($reportid)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
		} else {
			$terms_data['cap'] = true;
			$terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
		}
		$return = $terms_data;
	}
	break;
case 'generate_plotgraph':
	if (!$report = $DB->get_record('block_learnerscript', array('id' => $reportid))) {
		print_error('reportdoesnotexists', 'block_learnerscript');
	}
	require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
	$reportclassname = 'report_' . $report->type;
	$properties = new stdClass();
	$properties->cmid = $cmid;
	$properties->courseid = $courseid;
	$properties->userid = $userid;
	$properties->status = $status;
	if (!empty($ls_fstartdate)) {
		$properties->ls_startdate = $ls_fstartdate;
	} else {
		$properties->ls_startdate = 0;
	}

	if (!empty($ls_enddate)) {
		$properties->ls_enddate = $ls_fenddate;
	} else {
		$properties->ls_enddate = time();
	}
	$reportclass = new $reportclassname($report, $properties);

	$reportclass->create_report();
	$components = $ls->cr_unserialize($reportclass->config->components);
	if ($reporttype == 'table') {
		$datacolumns = array();
		$columnDefs = array();
		$i = 0;
		foreach ($reportclass->finalreport->table->head as $key => $value) {
			$datacolumns[]['data'] = $value;
			$columnDef = new stdClass();
			$align = $reportclass->finalreport->table->align[$i] ? $reportclass->finalreport->table->align[$i] : 'left';
			$wrap = ($reportclass->finalreport->table->wrap[$i] == 'wrap') ? 'break-all' : 'normal';
			$width = ($reportclass->finalreport->table->size[$i]) ? $reportclass->finalreport->table->size[$i] : '';
			$columnDef->className = 'dt-body-' . $align;
			$columnDef->targets = [$i];
			$columnDef->wrap = $wrap;
			$columnDef->width = $width;
			$columnDefs[] = $columnDef;
			$i++;
		}
		if (!empty($reportclass->finalreport->table->head)) {
			$tablehead = $ls->report_tabledata($reportclass->finalreport->table);
			$reporttable = new \block_learnerscript\output\reporttable($tablehead,
				$reportclass->finalreport->table->id,
				'',
				$reportid,
				$reportclass->sql,
				false,
				false,
				null,
				$report->type
			);
			$return = array();
			$return['tdata'] = $learnerscript->render($reporttable);
			$return['columnDefs'] = $columnDefs;
		} else {
			echo "hiii";exit;
			$return['tdata'] = '<div class="alert alert-info">' . get_string("nodataavailable", "block_learnerscript") . '</div>';
		}
	} else {
		$seriesvalues = (isset($components['plot']['elements'])) ? $components['plot']['elements'] : array();
		$i = 0;
		foreach ($seriesvalues as $g) {
			if (($reporttype != '' && $g['id'] == $reporttype) || $i == 0) {
				$return['plot'] = $ls->generate_report_plot($reportclass, $g);
				if ($reporttype != '' && $g['id'] == $reporttype) {
					break;
				}
			}
			$return['plotoptions'][] = array('id' => $g['id'], 'title' => $g['formdata']->chartname, 'pluginname' => $g['pluginname']);
			$i++;
		}
	}
	break;
case 'frequency_schedule':
	$return = $scheduling->getschedule($frequency);
	break;
case 'reportobject':
	if (!$report = $DB->get_record('block_learnerscript', array('id' => $reportid))) {
		print_error('reportdoesnotexists', 'block_learnerscript');
	}
	require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
	$reportclassname = 'report_' . $report->type;
	$properties = new stdClass();
	$reportclass = new $reportclassname($report, $properties);
	$reportclass->create_report();
	$return = $ls->cr_unserialize($reportclass->config->components);
	break;
case 'updatereport':
	if (!$report = $DB->get_record('block_learnerscript', array('id' => $reportid))) {
		print_error('reportdoesnotexists', 'block_learnerscript');
	}
	require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
	$reportclassname = 'report_' . $report->type;
	$properties = new stdClass();
	$reportclass = new $reportclassname($report, $properties);
	$comp = (array) $ls->cr_unserialize($reportclass->config->components);
	$components = json_decode($components, true);
	$plugins = get_list_of_plugins('blocks/learnerscript/components/calcs');
	$orderingplugins = get_list_of_plugins('blocks/learnerscript/components/ordering');

	foreach ( $components['calculations']['elements'] as $k => $calculations) {
		if (empty($calculations['pluginname']) || ($calculations['type'] != 'calculations')){
			unset($components['calculations']['elements'][$k]);
		} else {
			$components['calculations']['elements'][$k]['formdata'] = (object) $components['calculations']['elements'][$k]['formdata'];
		}
	}

    $comp['columns']['elements'] = $components['columns']['elements'];
    $comp['filters']['elements'] = $components['filters']['elements'];
    $comp['calculations']['elements'] = $components['calculations']['elements'];
    $comp['ordering']['elements'] = $components['ordering']['elements'];
    $comparray = ['columns', 'filters', 'calculations', 'ordering'];
    foreach ($comparray as $c) {
        foreach ($comp[$c]['elements'] as $k => $d) {
            if ($c == 'filters') {
                if (empty($d['formdata']['value'])) {
                    unset($comp[$c]['elements'][$k]);
                    continue;
                }
            }
            if ($c == 'calculations') {
                $comp[$c]['elements'][$k]['formdata'] = (object) $comp[$c]['elements'][$k]['formdata'];
                if (empty($d['pluginname']) || ($d['type'] == 'selectedcolumns' && !in_array($d['pluginname'], $plugins)) || empty($comp[$c]['elements'][$k]['formdata'])) {
                    unset($comp[$c]['elements'][$k]);
                    continue;
                }
            }
            if ($c == 'ordering') {
                if (empty($d['pluginname']) || ($d['type'] == 'Ordering' && !in_array($d['pluginname'], $orderingplugins))) {
                    unset($comp[$c]['elements'][$k]);
                    continue;
                }
                unset($comp[$c]['elements'][$k]['orderingcolumn']);
            }
            if ($c != 'calculations') {
                $comp[$c]['elements'][$k]['formdata'] = (object) $d['formdata'];
            }
        }
		if(isset($comp['calculations']['elements']) && !empty($comp['calculations']['elements'])){
        	$comp['calculations']['elements'] = array_values($comp['calculations']['elements']);
		}
	}
	$listofexports = $components['exports'];
	$exportlist = array();
	foreach ($listofexports as $key => $exportoptions) {
		if (!empty($exportoptions['value'])) {
			$exportlist[] = $exportoptions['name'];
		}
	}
	$exports = implode(',', $exportlist);
	$components = $ls->cr_serialize($comp);
	if (empty($listofexports)) {
		$DB->update_record('block_learnerscript', (object) ['id' => $reportid, 'components' => $components]);
	} else {
		$DB->update_record('block_learnerscript', (object) ['id' => $reportid, 'components' => $components, 'export' => $exports]);
	}
	break;
case 'plotforms':
	$args = new stdClass();
	$args->context = $context;
	$args->reportid = $reportid;
	$args->component = $component;
	$args->pname = $pname;
	$args->cid = $cid;
	$args->jsonformdata = $jsonformdata;

	$return = block_learnerscript_plotforms_ajaxform($args);

	break;
case 'updatereport_conditions':
	if (!$report = $DB->get_record('block_learnerscript', array('id' => $reportid))) {
		print_error('reportdoesnotexists', 'block_learnerscript');
	}
	$conditionsdata = json_decode($conditionsdata);
	$conditions = array();
	$conditions['elements'] = array();
	$sqlcon = array();
	$i = 1;
	foreach ($conditionsdata->selectedfields as $elementstr) {

		$element = explode(':', $elementstr);

		$columns = array();
		$columns['id'] = random_string();
		$columns['formdata'] = (object) ['field' => $element[1],
			'operator' => $conditionsdata->selectedcondition->{$elementstr},
			'value' => $conditionsdata->selectedvalue->{$elementstr},
			'submitbutton' => get_string('add')];
		$columns['pluginname'] = $element[0];
		$columns['pluginfullname'] = get_string($element[0], 'block_learnerscript');
		$columns['summary'] = get_string($element[0], 'block_learnerscript');
		$conditions['elements'][] = $columns;
		$sqlcon[] = 'c' . $i;
		$i++;
	}

	$conditions['config'] = (object) ['conditionexpr' => ($conditionsdata->sqlcondition) ? strtolower($conditionsdata->sqlcondition) : implode(' and ', $sqlcon),
		'submitbutton' => get_string('update')];

	$unserialize = $ls->cr_unserialize($report->components);
	$unserialize['conditions'] = $conditions;

	$unserialize = $ls->cr_serialize($unserialize);
	$DB->update_record('block_learnerscript', (object) ['id' => $reportid, 'components' => $unserialize]);
	break;
case 'reportcalculations':
	$checkpermissions = (new reportbase($reportid))->check_permissions($USER->id, $context);
	if ((has_capability('block/learnerscript:managereports', $context) || has_capability('block/learnerscript:manageownreports', $context) || !empty($checkpermissions)) && !empty($reportid)) {
		$properties = new stdClass();
		$reportclass = $ls->create_reportclass($reportid, $properties);
		$reportclass->params = array_merge($filters,$basicparams);
		$reportclass->start = 0;
		$reportclass->length = -1;
		$reportclass->colformat = true;
        $reportclass->calculations = true;
        $reportclass->reporttype = 'table';
		$reportclass->create_report();
		$table = html_writer::table($reportclass->finalreport->calcs);
		$reportname = $DB->get_field('block_learnerscript', 'name', array('id' => $reportid));
		$return = ['table' => $table, 'reportname' => $reportname];
	} else {
		$terms_data = array();
		$terms_data['error'] = true;
		$terms_data['type'] = 'Warning';
		if (empty($reportid)) {
			$terms_data['cap'] = false;
			$terms_data['msg'] = get_string('missingparam', 'block_learnerscript', 'ReportID');
		} else {
			$terms_data['cap'] = true;
			$terms_data['msg'] = get_string('badpermissions', 'block_learnerscript');
		}
		$return = $terms_data;
	}
	break;
case 'advancedcolumns':
	$args = new stdClass();
	// $args->context = $context;
	$args->reportid = $reportid;
	$args->component = $component;
	$args->pname = $advancedcolumn;
	$args->jsonformdata = $jsonformdata;
	$args->cid = '';
	// print_object($args);
	// exit;
	$return = block_learnerscript_plotforms_ajaxform($args);
	break;
case 'courseactivities':
	if ($courseid > 0) {
		$modinfo = get_fast_modinfo($courseid);
		$return[0] = 'Select Activity';
		if (!empty($modinfo->cms)) {
			foreach ($modinfo->cms as $k => $cm) {
				if($cm->visible == 1 && $cm->deletioninprogress == 0){
					$return[$k] = $cm->name;
				}
			}
		}
	} else {
		$return = [];
	}
	break;
/*case 'usercourses':
	if ($reportid) {
        $report = $DB->get_record('block_learnerscript', array('id' => $reportid));
    } 
    if($report->type){
        require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
        $reportclassname = 'report_' . $report->type;
        $properties = new stdClass;
        $reportclass = new $reportclassname($report, $properties);
    }
	if ($userid > 0) {
		$courselist = array_keys(enrol_get_users_courses($userid));
		if(!empty($courselist)) {
			if(!empty($reportclass->rolewisecourses)) {
				$rolecourses = explode(',', $reportclass->rolewisecourses);
				$courselist = array_intersect($courselist, $rolecourses);
			}
			$courseids = implode(',', $courselist);
			$return = $DB->get_records_sql_menu("SELECT id, fullname FROM {course}
		                                           WHERE id <> 1 AND visible = 1 AND id IN ($courseids)");
		} else {
			$return = array();
		}
	} else {
		$pluginclass = new stdClass;
		$pluginclass->singleselection = true;
		$pluginclass->report->type = $report->type;
		$pluginclass->reportclass = $reportclass;
		$return = (new \block_learnerscript\local\querylib)->filter_get_courses($pluginclass);
	}
	break;*/
case 'usercourses':		
	if ($userid > 0) {        
        $sql = "SELECT c.id, c.fullname
						FROM {user} u
						JOIN {role_assignments} ra ON ra.userid = u.id
						JOIN {context} AS ctx ON ctx.id = ra.contextid
						JOIN {course} c ON c.id = ctx.instanceid
						JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
						JOIN {user_enrolments} ue on ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = 0
						JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
						 WHERE u.id = $userid  AND c.visible = 1 ";
        $courses = $DB->get_records_sql_menu($sql);
		
        if (!empty($courses)) {
            $return = array('0' => 'Select course') + $courses;
        } else {
            $return = array(' ' => 'Select course');
        }
    } else {
        $return = array('' => 'Select Course');
    }
	break;	
case 'enrolledusers':
	if ($courseid > 0) {
		$coursecontext = context_course::instance($courseid);
		$studentroleid = $DB->get_field_sql("SELECT id FROM {role} WHERE shortname IN ('employee','student')");
		$enrolledusers = array_keys(get_role_users($studentroleid, $coursecontext));
		$return = array();
		if (!empty($enrolledusers)) {
			$enrolledusers = implode(',', $enrolledusers);
			$return = $DB->get_records_sql_menu("SELECT id, concat(firstname,' ',lastname) as name FROM {user}
	                                           WHERE confirmed = 1 AND deleted=0 AND id IN ($enrolledusers)");
		}
	} else {
		if ($reportid) {
        	$report = $DB->get_record('block_learnerscript', array('id' => $reportid));
	    } 
	    if(!empty($report->type) && $report->type){
	        require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
	        $reportclassname = 'report_' . $report->type;
	        $properties = new stdClass;
	        $reportclass = new $reportclassname($report, $properties);
	    }
		$pluginclass = new stdClass;
		$pluginclass->singleselection = true;
		$pluginclass->report = new stdClass;
		$pluginclass->report->type = !empty($report->type) ? $report->type : '';
		$pluginclass->report->components = $components;
		$pluginclass->reportclass = !empty($reportclass) ? $reportclass : '';
		$return = (new \block_learnerscript\local\querylib)->filter_get_users($pluginclass, false);
	}
	break;
case 'categorycourses':
	if ($categoryid > 0) {
		$courses = $DB->get_records_sql_menu("SELECT id, fullname FROM {course} WHERE open_categoryid = $categoryid AND visible = 1");
		$return = array(0 => 'Select Course') + $courses;
	} else {
		$return = array('' => 'Select Course');
	}
	break;
case 'orgdepts':
	if ($orgid > 0) {
		$sql = "SELECT id, fullname 
				FROM {local_costcenter} 
				WHERE parentid = :parentid AND visible = 1 AND depth = 2";
				
		$departments = $DB->get_records_sql_menu($sql, array('parentid' => $orgid));
        if (!empty($departments)) {
            $return = array(0 => 'All') + $departments;
        } else {
            $return = array(0 => 'Select Department');
        }
	} else {
		$return = array(0 => 'Select Department');
	}
	ksort($return);
	break;
case 'orglearningpath': 
	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_path;
	if ($orgid > 0) {
		$sql = "SELECT lp.id, lp.name 
                FROM {local_learningplan} lp
                WHERE 1 = 1 AND concat('/',lp.open_path,'/') LIKE :costcenterpath";
				
		$departments = $DB->get_records_sql_menu($sql, array('parentid'=>$orgid, 'costcenterpath'=>'%'.$orgid.'%'));
        if (!empty($departments)) {
            $return = array('0' => 'Select Learning path') + $departments;
        } else {
            $return = array('-1' => 'Select Learning path');
        }
	} else {
		$return = array('' => 'Select Learning path');
	}
	break;
case 'deptsubdepartments':
	// $departmentid = isset($departmentid) && $departmentid > 0 ? $departmentid : $USER->open_departmentid;
	if ($departmentid > 0) {
        $sql = "SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = $departmentid AND lc.depth = 3 ";
        $subdepartments = $DB->get_records_sql_menu($sql);
        if (!empty($subdepartments)) {
            $return = array('0' => 'All') + $subdepartments;
        } else {
            $return = array('-1' => 'All');
        }
    } else {
        $return = array('-1' => 'All');
    }
	break;
	case 'deptl4departments':
		if ($subdepartmentid > 0) {
	        $sql = "SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = $subdepartmentid AND lc.depth = 4 ";
	        $l4departments = $DB->get_records_sql_menu($sql);
	        if (!empty($l4departments)) {
	            $return = array('0' => 'All') + $l4departments;
	        } else {
	            $return = array('-1' => 'All');
	        }
	    } else {
	        $return = array('-1' => 'All');
	    }
	break;
	case 'deptl5departments':
		if ($currentl4depid > 0) {
	        $sql = "SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = $currentl4depid AND lc.depth = 5 ";
	        $l5departments = $DB->get_records_sql_menu($sql);
	        if (!empty($l5departments)) {
	            $return = array('0' => 'All') + $l5departments;
	        } else {
	            $return = array('-1' => 'All');
	        }
	    } else {
	        $return = array('-1' => 'All');
	    }
	break;
case 'deptcohorts':
	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_costcenterid;
	// $departmentid = isset($departmentid) && $departmentid > 0 ? $departmentid : $USER->open_departmentid;
	if ($orgid > 0) {
        $sql = "SELECT DISTINCT co.id, co.name 
					FROM {cohort} co 
					JOIN {local_groups} lg ON lg.cohortid = co.id 
					WHERE 1 = 1 AND lg.costcenterid = $orgid "; 

		if ($departmentid > 0) {
			$sql .= " AND lg.departmentid = $departmentid";
		}
        $subdepartments = $DB->get_records_sql_menu($sql);
        if (!empty($subdepartments)) {
            $return = array('0' => 'All') + $subdepartments;
        } else {
            $return = array('-1' => 'Select Cohort');
        }
    } else {
        $return = array('-1' => 'Select Cohort');
    }
	break;
// case 'deponlinecourses': 
// 	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_costcenterid;
// 	if ($orgid > 0) {
// 		$sql = "SELECT c.id, c.fullname AS onlinecourse
//                 FROM {course} c 
//                 JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
//                 WHERE 1 = 1 AND clf.name = 'Online Course' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid IN ($orgid, 0) ";
// 		if ($departmentid > 0) {
// 			$sql .= " AND c.open_departmentid IN ($departmentid, 0) ";
// 		}
// 		if ($subdepartmentid > 0) {
// 			$sql .= " AND c.open_subdepartment IN ($subdepartmentid, 0) ";
// 		}		
// 		$onlinecourses = $DB->get_records_sql_menu($sql, array());
//         if (!empty($onlinecourses)) {
//             $return = array('0' => 'Select Online course') + $onlinecourses;
//         } else {
//             $return = array('-1' => 'Select Online course');
//         }
// 	} else {
// 		$return = array('' => 'Select Online course');
// 	}
// 	break;
// case 'deplabs': 
// 	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_costcenterid;
// 	if ($orgid > 0) {
// 		$sql = "SELECT c.id, c.fullname AS onlinecourse
//                 FROM {course} c 
//                 JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
//                 WHERE 1 = 1 AND clf.name = 'Lab' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid IN ($orgid, 0) ";
// 		if ($departmentid > 0) {
// 			$sql .= " AND c.open_departmentid IN ($departmentid, 0) ";
// 		}
// 		if ($subdepartmentid > 0) {
// 			$sql .= " AND c.open_subdepartment IN ($subdepartmentid, 0) ";
// 		}		
// 		$labs = $DB->get_records_sql_menu($sql, array());
//         if (!empty($labs)) {
//             $return = array('0' => 'Select Lab') + $labs;
//         } else {
//             $return = array('-1' => 'Select Lab');
//         }
// 	} else {
// 		$return = array('' => 'Select Lab');
// 	}
// 	break;
// case 'depassessments': 
// 	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_costcenterid;
// 	if ($orgid > 0) {
// 		$sql = "SELECT c.id, c.fullname AS onlinecourse
//                 FROM {course} c 
//                 JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
//                 WHERE 1 = 1 AND clf.name = 'Assessment' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid IN ($orgid, 0) ";
// 		if ($departmentid > 0) {
// 			$sql .= " AND c.open_departmentid IN ($departmentid, 0) ";
// 		}
// 		if ($subdepartmentid > 0) {
// 			$sql .= " AND c.open_subdepartment IN ($subdepartmentid, 0) ";
// 		}		
// 		$assessments = $DB->get_records_sql_menu($sql, array());
//         if (!empty($assessments)) {
//             $return = array('0' => 'Select Assessment') + $assessments;
//         } else {
//             $return = array('-1' => 'Select Assessment');
//         }
// 	} else {
// 		$return = array('' => 'Select Assessment');
// 	}
// 	break;
case 'depusergroups': 
	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_costcenterid;
	if ($orgid > 0) {
		$sql = "SELECT ch.id, ch.name
                FROM {cohort} ch 
                JOIN {local_groups} lg ON lg.cohortid = ch.id
                WHERE 1 = 1 AND lg.costcenterid = $orgid";
		if ($departmentid > 0) {
			$sql .= " AND lg.departmentid = $departmentid";
		}
		if ($subdepartmentid > 0) {
			$sql .= " AND lg.subdepartmentid = $subdepartmentid";
		}		
		$usergroup = $DB->get_records_sql_menu($sql, array());
        if (!empty($usergroup)) {
            $return = array('0' => 'Select User group') + $usergroup;
        } else {
            $return = array('-1' => 'Select User group');
        }
	} else {
		$return = array('' => 'Select User group');
	}
	break;	
// case 'depwebinars': 
// 	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_costcenterid;
// 	if ($orgid > 0) {
// 		$sql = "SELECT c.id, c.fullname AS webinar
//                 FROM {course} c 
//                 JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
//                 WHERE 1 = 1 AND clf.name = 'Webinar' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') AND c.open_costcenterid = $orgid";
// 		if ($departmentid > 0) {
// 			$sql .= " AND c.open_departmentid = $departmentid";
// 		}
// 		if ($subdepartmentid > 0) {
// 			$sql .= " AND c.open_subdepartment = $subdepartmentid";
// 		}		
// 		$webinars = $DB->get_records_sql_menu($sql, array());
//         if (!empty($webinars)) {
//             $return = array('0' => 'Select Webinar') + $webinars;
//         } else {
//             $return = array('-1' => 'Select Webinar');
//         }
// 	} else {
// 		$return = array('' => 'Select Webinar');
// 	}
// 	break;
case 'depprograms': 
	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_path;
	if ($orgid > 0) {
		$sql = "SELECT lp.id, lp.name 
                FROM {local_learningplan} lp
                WHERE 1 = 1 AND concat('/',lp.open_path,'/') LIKE :costcenterpath";
				
		$programs = $DB->get_records_sql_menu($sql, array('parentid'=>$orgid, 'costcenterpath'=>'%'.$orgid.'%'));
	
        if (!empty($programs)) {
            $return = array('0' => 'Select Program') + $programs;
        } else {
            $return = array('-1' => 'Select Program');
        }
	} else {
		$return = array('' => 'Select Program');
	}
	break;	
	case 'depclassrooms': 
		$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_path;
		if ($orgid > 0) {
			$sql = "SELECT lc.id, lc.name 
					FROM {local_classroom} lc 
					WHERE 1 = 1  AND concat('/',lc.open_path,'/') LIKE :costcenterpath";
			if ($departmentid > 0) {
				$sql .= " AND lc.department = $departmentid";
			}
			if ($subdepartmentid > 0) {
				$sql .= " AND lc.subdepartment = $subdepartmentid";
			}		
			$classrooms = $DB->get_records_sql_menu($sql, array('parentid'=>$orgid, 'costcenterpath'=>'%'.$orgid.'%'));
			if (!empty($classrooms)) {
				$return = array('0' => 'Select Instructor-led Course') + $classrooms;
			} else {
				$return = array('-1' => 'Select Instructor-led Course');
			}
		} else {
			$return = array('' => 'Select Instructor-led Course');
		}
		break;
case 'deplearningpath': 
	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_path;
	if ($orgid > 0) {
		$sql = "SELECT lp.id, lp.name 
                FROM {local_learningplan} lp
                WHERE 1 = 1 AND concat('/',lp.open_path,'/') LIKE :costcenterpath";
	
		$departments = $DB->get_records_sql_menu($sql, array('costcenterpath'=>'%'.$orgid.'%'));
        if (!empty($departments)) {
            $return = array('0' => 'Select Learning path') + $departments;
        } else {
            $return = array('-1' => 'Select Learning path');
        }
	} else {
		$return = array('' => 'Select Learning path');
	}
	break;
case 'departmentcourses': 
	$orgid = isset($orgid) && $orgid > 0 ? $orgid : $USER->open_costcenterid;
	if ($orgid > 0) { 
		$pathdetails = new \stdClass();
		$pathdetails->open_costcenterid = $orgid;
		$pathdetails->open_department = $departmentid;
		$pathdetails->open_subdepartment = $subdepartmentid;
		$pathdetails->open_level4department = $currentl4depid;
		
		local_costcenter_get_costcenter_path($pathdetails);
	
        $orgsql .= " SELECT c.id,c.fullname FROM {course} c WHERE c.visible = 1 AND c.open_coursetype = 0 AND concat(c.open_path,'/') LIKE :costcenterpath"; 
		
        $userssql = " ";
        $userssql .= " SELECT c.id,c.fullname FROM {course} c 
                    JOIN {enrol} e ON e.courseid = c.id AND e.status = 0 
                  JOIN {user_enrolments} ue on ue.enrolid = e.id AND ue.status = 0
                  JOIN {role_assignments}  ra ON ra.userid = ue.userid
                  JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                  JOIN {context} ctx ON ctx.instanceid = c.id 
                  JOIN {user} AS u ON u.id = ue.userid 
                  JOIN {local_costcenter} lc ON concat('/',u.open_path,'/') LIKE :costcenterpath AND lc.depth = 1
                  AND ra.contextid = ctx.id AND ctx.contextlevel = 50 AND c.visible = 1  
                  WHERE 1 = 1 AND c.open_coursetype = 0 ";//LIKE concat('%/',lc.id,'/%')
        

        $orgcourses = $DB->get_records_sql($orgsql,array('costcenterpath'=>$pathdetails->open_path.'/%')); 
		$usercourses = $DB->get_records_sql($userssql,array('costcenterpath'=>$pathdetails->open_path.'/')); 
	
        if (empty($usercourses) && !empty($orgcourses)) {
            $totalcourses = $orgcourses; 
        } else if (!empty($usercourses) && empty($orgcourses)) {
            $totalcourses = $usercourses;
        } else if (!empty($usercourses) && !empty($orgcourses)) {
            $totalcourses = array_merge($usercourses, $orgcourses);
        } else if (!empty($usercourses) && !empty($orgcourses)) {
            $totalcourses = array();
        }
		
        foreach ($totalcourses as $totalcourse) {
            $list[] = $totalcourse->id;
        } 

        $list = !empty($totalcourses) ? implode(',', array_unique($list)) : 0; 
        $sql = "SELECT c.id, c.fullname FROM {course} c WHERE c.id IN (" . $list . ")";
        $courses = $DB->get_records_sql_menu($sql);
		
        if (!empty($courses)) {
            $return = array('0' => 'Select course') + $courses;
        } else {
            $return = array('' => 'Select course');
        }
		
    } else {
        $return = array('' => 'Select Course');
    }
	break; 
case 'departmentusers':
	if ($orgid > 0) { 
		$pathdetails = new \stdClass();
		$pathdetails->open_costcenterid = $orgid;
		$pathdetails->open_department = $departmentid;
		$pathdetails->open_subdepartment = $subdepartmentid;
		$pathdetails->open_level4department = $currentl4depid;
		$pathdetails->open_level5department = $currentl5depid;
		local_costcenter_get_costcenter_path($pathdetails);
        $sql = "SELECT u.id, CONCAT(u.firstname,' ',u.lastname) as employeename
	                FROM {user} u WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 2 AND concat(u.open_path,'/') LIKE :costcenterpath";
        $courses = $DB->get_records_sql_menu($sql, array('costcenterpath'=>$pathdetails->open_path.'/%'));
        if (!empty($courses)) {
            $return = array('0' => 'Select user') + $courses;
        } else {
            $return = array('1' => 'Select user');
        }
    } else {
        $return = array('' => 'Select user');
    }
	break;
case 'designdata':
	$return = array();
	if (!$report = $DB->get_record('block_learnerscript', array('id' => $reportid))) {
		print_error('reportdoesnotexists', 'block_learnerscript');
	}
	require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
	$reportclassname = 'report_' . $report->type;

	$properties = new stdClass();
	$reportclass = new $reportclassname($report, $properties);

	$reportclass->cmid = $cmid || 0;
	$reportclass->courseid = $courseid || SITEID;
	$reportclass->userid = $userid || $USER->id;
	$reportclass->start = 0;
	$reportclass->length = 5;

	if (!empty($ls_fstartdate)) {
		$reportclass->ls_startdate = $ls_fstartdate;
	} else {
		$reportclass->ls_startdate = 0;
	}

	if (!empty($ls_enddate)) {
		$reportclass->ls_enddate = $ls_fenddate;
	} else {
		$reportclass->ls_enddate = time();
	}
	$reportclass->preview = true;
	$reportclass->create_report(null);
	$components = unserialize($reportclass->config->components);
	// $startTime = microtime(true);
	if ($report->type == 'sql') {
		$rows = $reportclass->get_rows();
		if (!empty($rows['rows'])) {
			$return['rows'] = array();
			$return['rows'] = $rows['rows'];
			$reportclass->columns = get_object_vars($return['rows'][0]);
			$reportclass->columns = array_keys($reportclass->columns);
		}
	} else {
		if (!isset($reportclass->columns)) {
			$availablecolumns = $ls->report_componentslist($report, 'columns');
		} else {
			$availablecolumns = $reportclass->columns/* + $ls->report_componentslist($report, 'columns')*/;
		}
		// $reportTable = $reportclass->get_all_elements();
		//$return['rows'] = $reportclass->get_rows($reportTable[0]);
		$return['rows'] = $reportclass->finalreport->table->data;
	}
	$return['reportdata'] = null;
	/*
	 * Calculations data
	 */
	$comp = 'calcs';
	$plugins = get_list_of_plugins('blocks/learnerscript/components/' . $comp);
	$optionsplugins = array();
	foreach ($plugins as $p) {
		require_once($CFG->dirroot . '/blocks/learnerscript/components/' . $comp . '/' . $p . '/plugin.class.php');
		$pluginclassname = 'plugin_' . $p;
		$pluginclass = new $pluginclassname($report);
		if (in_array($report->type, $pluginclass->reporttypes)) {
			if ($pluginclass->unique && in_array($p, $currentplugins)) {
				continue;
			}

			$optionsplugins[get_string($p, 'block_learnerscript')] = $p;
		}
	}
	asort($optionsplugins);
	$return['calculations'] = $optionsplugins;
	// $return['time'] = "Calcluations Time:  " . number_format((microtime(true) - $startTime), 4) . " Seconds\n";
	//Selected columns
	$activecolumns = array();

	if (isset($components['columns']['elements'])) {
		foreach ($components['columns']['elements'] as $key => $value) {
			$value = (array) $value;
			$components['columns']['elements'][$key] = (array) $components['columns']['elements'][$key];

			$components['columns']['elements'][$key]['formdata']->columname = urldecode($value['formdata']->columname);
			$activecolumns[] = $value['formdata']->column;
		}
		$return['selectedcolumns'] = $components['columns']['elements'];
	} else {
		$return['selectedcolumns'] = array();
	}

	//========{conditions}===========
	$return['conditioncolumns'] = $reportclass->setup_conditions();
	//========{conditions end}===========

	//Filters
	$filterdata = array();
	if (isset($components['filters']['elements'])) {
		foreach ($components['filters']['elements'] as $key => $value) {
			$value = (array) $value;
			if ($value['formdata']->value) {
				$filterdata[] = $value['pluginname'];
			}
		}
	}
	$filterplugins = get_list_of_plugins('blocks/learnerscript/components/filters');
	$filteroptions = array();
	if ($reportclass->config->type != 'sql') {
		$filterplugins = $reportclass->filters;
	}
	$filterelements = array();
	if (!empty($filterplugins)) {
		foreach ($filterplugins as $p) {
			require_once($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $p . '/plugin.class.php');
			if (file_exists($CFG->dirroot . '/blocks/learnerscript/components/filters/' . $p . '/form.php')) {
				continue;
			}
			$pluginclassname = 'plugin_' . $p;
			$pluginclass = new $pluginclassname($report);
			// if (in_array($report->type, $pluginclass->reporttypes)) {
				$uniqueid = random_string(15);
				while (strpos($reportclass->config->components, $uniqueid) !== false) {
					$uniqueid = random_string(15);
				}
				$filtercolumns = array();
				$filtercolumns['id'] = $uniqueid;
				$filtercolumns['pluginname'] = $p;
				$filtercolumns['pluginfullname'] = get_string($p, 'block_learnerscript');
				$filtercolumns['summary'] = '';
				$columnss['name'] = get_string($p, 'block_learnerscript');
				$columnss['type'] = 'filters';
				$columnss['value'] = (in_array($p, $filterdata)) ? true : false;
				$filtercolumns['formdata'] = $columnss;
				$filterelements[] = $filtercolumns;
			// }
		}
	}
	$return['filtercolumns'] = $filterelements;
	//Ordering
	$comp = 'ordering';
	$plugins = get_list_of_plugins('blocks/learnerscript/components/ordering');
	$orderingplugin = array();
	// foreach ($plugins as $p) {
	//     require_once($CFG->dirroot . '/blocks/learnerscript/components/' . $comp . '/' . $p . '/plugin.class.php');
	//     $pluginclassname = 'plugin_' . $p;
	//     $pluginclass = new $pluginclassname($report);
	//     if (in_array($report->type, $pluginclass->reporttypes)) {
	//         $orderingplugin[$p] = get_string($p, 'block_learnerscript') ;
	//     }
	// }
	asort($plugins);
	$orderingdata = array();

	foreach ($plugins as $key => $value) {
		require_once($CFG->dirroot . '/blocks/learnerscript/components/ordering/' . $value . '/plugin.class.php');
		$pluginclassname = 'plugin_' . $value;
		$pluginclass = new $pluginclassname($report);
		if (!in_array($report->type, $pluginclass->reporttypes)) {
			continue;
		}
		$tblcolumns = $pluginclass->columns();
		if (!empty($components['ordering']['elements'])) {
			foreach ($components['ordering']['elements'] as $ordercomp) {
				if ($value == $ordercomp['pluginname']) {
					$ordercomp['pluginfullname'] = get_string($value, 'block_learnerscript');
					$ordercomp['orderingcolumn'] = array_keys($tblcolumns);
					$orderingdata[$value] = $ordercomp;
				}
			}
		}
		if (!array_key_exists($value, $orderingdata)) {
			$uniqueid = random_string(15);
			while (strpos($reportclass->config->components, $uniqueid) !== false) {
				$uniqueid = random_string(15);
			}

			$ordering = array();
			$ordering['type'] = 'Ordering';
			$ordering['orderingcolumn'] = array_keys($tblcolumns);
			$ordering['pluginname'] = $value;
			$ordering['pluginfullname'] = get_string($value, 'block_learnerscript');
			$ordering['id'] = $uniqueid;
			$orderingdata[$value] = $ordering;
		}
	}
	$orderingdata = array_values($orderingdata);
	$return['ordercolumns'] = $orderingdata;
	//Columns
	$elements = array();
	if ($report->type == 'sql') {
		$columns = array();
		if (!empty($reportclass->columns)) {
			foreach ($reportclass->columns as $value) {
				$c = [];
				$uniqueid = random_string(15);
				while (strpos($reportclass->config->components, $uniqueid) !== false) {
					$uniqueid = random_string(15);
				}
				$c['id'] = $uniqueid;
				$c['pluginname'] = 'sql';
				$c['pluginfullname'] = 'SQL';
				$c['summary'] = '';

				if (in_array($value, $activecolumns)) {
					$columns['value'] = true;
					$c['type'] = 'selectedcolumns';
				} else {
					$columns['value'] = false;
					$c['type'] = 'columns';
				}
				$columns['columname'] = $value;
				$columns['column'] = $value;
				$columns['heading'] = '';
				$columns['wrap'] = '';
				$columns['align'] = '';
				$columns['size'] = '';
				$c['formdata'] = $columns;
				$elements[] = $c;
			}
		}
	} else {
		$comp = 'columns';
		$cid = '';
		require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/component.class.php');
		$compclass = new component_columns($report->id);
		$i = 0;
		if (!empty($availablecolumns)) {
			foreach ($availablecolumns as $key => $values) {
				if (!isset($reportclass->columns)) {
					$c = [];
					$c['formdata']->column = $key;
					$c['formdata']->columnname = get_string($key, 'block_learnerscript');
					$elements[] = $c;
				} else {
					$columns = array();
					if (is_array($values)) {
						foreach ($values as $value) {
							$c = [];
							$columnform = new stdClass;
							$classname ='';
							$uniqueid = random_string(15);
							while (strpos($reportclass->config->components, $uniqueid) !== false) {
								$uniqueid = random_string(15);
							}
							$c['id'] = $uniqueid;
							$c['pluginname'] = $key;
							$c['pluginfullname'] = get_string($key, 'block_learnerscript');
							$c['summary'] = '';
							if (in_array($value, $activecolumns)) {
									$type = 'selectedcolumns';
								}else{
									$type = 'columns';
								}
							if (file_exists($CFG->dirroot . '/blocks/learnerscript/components/columns/' . $value . '/plugin.class.php')) {
								require_once($CFG->dirroot . '/blocks/learnerscript/components/columns/' . $value . '/plugin.class.php');
								$classname = 'plugin_'.$value;
								$columnform = new $classname($report);
								if ($columnform->type == 'advanced') {
									$c = [];
									$c['formdata'] = new stdClass();
									$c['formdata']->column = $value;
									$c['formdata']->columnname = get_string($key, 'block_learnerscript');
									$elements[] = $c;
									continue;
								} else {
									$c['type'] = $type;
								}
							} else {
								$c['type'] = $type;
							}
							if (in_array($value, $activecolumns)) {
								$columns['value'] = true;
							} else {
								$columns['value'] = false;
							}
							$columns['columname'] = $value;
							$columns['column'] = $value;
							$columns['heading'] = $key;
							$c['formdata'] = $columns;
							$elements[] = $c;
						}
					}
				}
				$i++;
			}
		}
	}
	$return['availablecolumns'] = $elements;
	if (!empty($components['calculations']['elements'])) {
		foreach ($components['calculations']['elements'] as $k => $ocalc) {
			$ocalc = (array) $ocalc;
			$calcpluginname[] = $ocalc['pluginname'];
		}
	} else {
		$components['calculations']['elements'] = array();
		$calcpluginname = array();
	}
	$return['calcpluginname'] = $calcpluginname;
	$return['calccolumns'] = $components['calculations']['elements'];
	//exports
	$exporttypes = array();
	if ($reportclass->exports) {
		$exporttypes = array('pdf', 'csv', 'xls', 'ods');
	}
	$exportlists = array();
	foreach ($exporttypes as $key => $exporttype) {
		$list = array();
		$list['name'] = $exporttype;
		if (in_array($exporttype, explode(',', $report->export))) {
			$list['value'] = true;
		} else {
			$list['value'] = false;
		}
		$exportlists[] = $list;
	}
	$return['exportlist'] = $exportlists;
	break;
case 'sendreportemail':
	$args = new stdClass();
	$args->reportid = $reportid;
	$args->instance = $instance;
	$args->jsonformdata = $jsonformdata;

	$return = block_learnerscript_sendreportemail_ajaxform($args);
	break;
case 'tabsposition':
	$report = $DB->get_record('block_learnerscript', array('id' => $reportid));
	$components = $ls->cr_unserialize($report->components);
	$elements = isset($components[$component]['elements']) ? $components[$component]['elements'] : array();
	$sortedelements = explode(',', $elementsorder);
	$finalelements = array();
	foreach ($elements as $k => $element) {
		$position = array_search($element['id'], $sortedelements);
		$finalelements[$position] = $element;
	}
	ksort($finalelements);
	$components[$component]['elements'] = $finalelements;
	$finalcomponents = $ls->cr_serialize($components);
	$report->components = $finalcomponents;
	$DB->update_record('block_learnerscript', $report);
	break;
case 'contextroles':
	$report = $DB->get_record('block_learnerscript', array('id' => $reportid));
	if($report->type){
        require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
        $reportclassname = 'report_' . $report->type;
        $properties = new stdClass;
        $reportclass = new $reportclassname($report, $properties);
    }
    $excludedroles = $reportclass->excludedroles;
    $return = get_roles_in_context($contextlevel, $excludedroles);
	break;
case 'configureplot':
	$return = array();
	if (!$report = $DB->get_record('block_learnerscript', array('id' => $reportid))) {
		print_error('reportdoesnotexists', 'block_learnerscript');
	}
	require_once($CFG->dirroot . '/blocks/learnerscript/reports/' . $report->type . '/report.class.php');
	$reportclassname = 'report_' . $report->type;

	$properties = new stdClass();
	$reportclass = new $reportclassname($report, $properties);
	$reportclass->create_report();
	$components = unserialize($reportclass->config->components);

	$return['columns'] = $components['columns'];
	$uniqueid = random_string(15);
	while (strpos($reportclass->config->components, $uniqueid) !== false) {
		$uniqueid = random_string(15);
	}
  	$plot[id] = $uniqueid;
    $plot[formdata] = new stdClass();
    $plot[formdata]->chartname = '';
    $plot[formdata]->serieid = '';
    $plot[formdata]->yaxis[] = ['name' => '', 'operator' => '', 'value' => ''];
    $plot[formdata]->showlegend = 0;
    $plot[formdata]->datalabels = 0;
    $plot[formdata]->calcs = null;
    $plot[formdata]->columnsort = null;
    $plot[formdata]->sorting = null;
    $plot[formdata]->limit = null;

     $return['plot'] = $plot;
	break;
	case 'geostates':
		if ($orgid > 0) {
	        $sql = "SELECT ls.id, ls.states_name FROM {local_states} ls WHERE ls.costcenterid = $orgid ";
	        $states = $DB->get_records_sql_menu($sql);
	        if (!empty($states)) {
	            $return = array('0' => 'All') + $states;
	        } else {
	            $return = array('-1' => 'All');
	        }
	    } else {
	        $return = array('-1' => 'All');
	    }
	break;
	case 'geodistrict':
		if ($stateid > 0) {
	        $sql = "SELECT ld.id, ld.district_name FROM {local_district} ld WHERE ld.statesid = $stateid ";
	        $districts = $DB->get_records_sql_menu($sql);
	        if (!empty($districts)) {
	            $return = array('0' => 'All') + $districts;
	        } else {
	            $return = array('-1' => 'All');
	        }
	    } else {
	        $return = array('-1' => 'All');
	    }
	break;
	case 'geosubdistrict':
		if ($districtid > 0) {
	        $sql = "SELECT lsd.id, lsd.subdistrict_name FROM {local_subdistrict} lsd WHERE lsd.districtid = $districtid ";
	        $subdistricts = $DB->get_records_sql_menu($sql);
	        if (!empty($subdistricts)) {
	            $return = array('0' => 'All') + $subdistricts;
	        } else {
	            $return = array('-1' => 'All');
	        }
	    } else {
	        $return = array('-1' => 'All');
	    }
	break;
	case 'geovillage':
		if ($subdistrictid > 0) {
	        $sql = "SELECT lv.id, lv.village_name FROM {local_village} lv WHERE lv.subdistrictid = $subdistrictid ";
	        $villages = $DB->get_records_sql_menu($sql);
	        if (!empty($villages)) {
	            $return = array('0' => 'All') + $villages;
	        } else {
	            $return = array('-1' => 'All');
	        }
	    } else {
	        $return = array('-1' => 'All');
	    }
	break;
}

$json = json_encode($return, JSON_NUMERIC_CHECK);

if ($json) {
	echo $json;
} else {
	echo json_last_error_msg();
}
