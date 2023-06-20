<?php

namespace local_learningplan\render;

use context_module;
use local_learningplan\lib\lib as lib;
use local_learningplan\output\learningplan_courses as output;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use context_system;
use html_writer;
use html_table;
use moodle_url;
use learningplan;
use plugin_renderer_base;
use user_course_details;
//use open;
if (file_exists($CFG->dirroot . '/local/includes.php')) {
	require_once($CFG->dirroot . '/local/includes.php');
}
class view extends plugin_renderer_base
{
	private $lid;
	function __construct()
	{
		global $DB, $CFG, $OUTPUT, $USER, $PAGE;
		$this->db = $DB;
		$this->context = (new \local_learningplan\lib\accesslib())::get_module_context();
		$this->output = $OUTPUT;
		$this->page = $PAGE;
		$this->cfg = $CFG;
		$this->user = $USER;
	}

	public function all_learningplans($condtion, $dataobj, $tableenable = false, $search = null, $filterdata = null, $view_type = 'card')
	{
		global $CFG;
		$status1 = optional_param('status1', '', PARAM_RAW);
		$costcenterid = optional_param('costcenterid', '', PARAM_INT);
		$departmentid = optional_param('departmentid', '', PARAM_INT);
		$subdepartment = optional_param('subdepartmentid', '', PARAM_INT);
		$l4department = optional_param('l4department', '', PARAM_INT);
		$l5department = optional_param('l5department', '', PARAM_INT);
		// print_r($filterdata);
		// exit;



		$categorycontext = $this->context;
		if (($tableenable)) {
			$start = $dataobj->start;
			$length = $dataobj->length;
		}
		$userparams = [];
		$lpparams = [];
		$costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'l.open_path');
		if (is_siteadmin() || has_capability('local/learningplan:view', $categorycontext)) {
			$sql = "SELECT l.* FROM {local_learningplan} AS l WHERE 1 = 1 "; //WHERE l.id > 0

			if (is_siteadmin()) {
				$sql .= "";
			} else {
				$sql .= $costcenterpathconcatsql;
			}
			if (!empty($search)) {
				$sql .= " AND name LIKE '%%$search%%'";
			}
			$assign_users_sql = "SELECT id FROM {local_learningplan} l WHERE 1 = 1 ";
			if ($filterdata) {
				if (!empty($filterdata->organizations) && !empty(array_filter($filterdata->organizations))) {
					$selectedorganizations = implode(',', array_filter($filterdata->organizations));
					$organizations = explode(',', $selectedorganizations);
					$orgsql = [];
					foreach ($organizations as $organisation) {
						$orgsql[] = " concat('/',l.open_path,'/') LIKE :organisationparam_{$organisation}";
						$lpparams["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
						$userparams["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
					}
					if (!empty($orgsql)) {
						$sql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
						$assign_users_sql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
					}
				}
				if (!empty($filterdata->departments) && !empty(array_filter($filterdata->departments))) {
					$selecteddepts = implode(',', array_filter($filterdata->departments));
					$depts = explode(',', $selecteddepts);
					$deptsql = [];
					foreach ($depts as $dept) {
						$deptsql[] = " concat('/',l.open_path,'/') LIKE :deptparam_{$dept}";
						$lpparams["deptparam_{$dept}"] = '%/' . $dept . '/%';
						$userparams["deptparam_{$dept}"] = '%/' . $dept . '/%';
					}
					if (!empty($deptsql)) {
						$sql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
						$assign_users_sql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
					}
				}
				if (!empty($filterdata->subdepartment) && !empty(array_filter($filterdata->subdepartment))) {
					$selectedsubdepts = implode(',', array_filter($filterdata->subdepartment));
					$subdepts = explode(',', $selectedsubdepts);
					$subdeptsql = [];
					foreach ($subdepts as $subdept) {
						$subdeptsql[] = " concat('/',l.open_path,'/') LIKE :subdeptparam_{$subdept}";
						$lpparams["subdeptparam_{$subdept}"] = '%/' . $subdept . '/%';
						$userparams["subdeptparam_{$subdept}"] = '%/' . $subdept . '/%';
					}
					if (!empty($subdeptsql)) {
						$sql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
						$assign_users_sql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
					}
				}

				if (!empty($filterdata->department4level) &&  !empty(array_filter($filterdata->department4level))) {
					$selecteddepts4 = implode(',', array_filter($filterdata->department4level));
					$depts4 = explode(',', $selecteddepts4);
					$depts4sql = [];
					foreach ($depts4 as $dept4) {
						$depts4sql[] = " concat('/',l.open_path,'/') LIKE :dept4param_{$dept4}";
						$lpparams["dept4param_{$dept4}"] = '%/' . $dept4 . '/%';
						$userparams["dept4param_{$dept4}"] = '%/' . $dept4 . '/%';
					}
					if (!empty($depts4sql)) {
						$sql .= " AND ( " . implode(' OR ', $depts4sql) . " ) ";
						$assign_users_sql .= " AND ( " . implode(' OR ', $depts4sql) . " ) ";
					}
				}

				if (!empty($filterdata->department5level) && !empty(array_filter($filterdata->department5level))) {
					$selecteddepts5 = implode(',', array_filter($filterdata->department5level));
					$depts5 = explode(',', $selecteddepts5);
					$depts5sql = [];
					foreach ($depts5 as $dept5) {
						$depts5sql[] = " concat('/',l.open_path,'/') LIKE :dept5param_{$dept5}";
						$lpparams["dept5param_{$dept5}"] = '%/' . $dept5 . '/%';
						$userparams["dept5param_{$dept5}"] = '%/' . $dept5 . '/%';
					}
					if (!empty($depts5sql)) {
						$sql .= " AND ( " . implode(' OR ', $depts5sql) . " ) ";
						$assign_users_sql .= " AND ( " . implode(' OR ', $depts5sql) . " ) ";
					}
				}

				if (!empty($filterdata->categories)) {
					$selectedcategories = implode(',', $filterdata->categories);
					$sql .= " AND l.open_categoryid IN ($selectedcategories) ";
					$assign_users_sql .= " AND l.open_categoryid IN ($selectedcategories) ";
				}

				if (!empty($filterdata->states)) {
					$selectedstates = implode(',', $filterdata->states);
					$sql .= " AND l.open_states IN ($selectedstates) ";
					$assign_users_sql .= " AND l.open_states IN ($selectedstates) ";
				}
				if (!empty($filterdata->district)) {
					$selecteddistrict = implode(',', $filterdata->district);
					$sql .= " AND l.open_district IN ($selecteddistrict) ";
					$assign_users_sql .= " AND l.open_district IN ($selecteddistrict) ";
				}
				if (!empty($filterdata->subdistrict)) {
					$selectedsubdistrict = implode(',', $filterdata->subdistrict);
					$sql .= " AND l.open_subdistrict IN ($selectedsubdistrict) ";
					$assign_users_sql .= " AND l.open_subdistrict IN ($selectedsubdistrict) ";
				}
				if (!empty($filterdata->village)) {
					$selectedvillage = implode(',', $filterdata->village);
					$sql .= " AND l.open_village IN ($selectedvillage) ";
					$assign_users_sql .= " AND l.open_village IN ($selectedvillage) ";
				}

				if (!empty($filterdata->learningplan)) {
					$selectedlearningplan = implode(',', $filterdata->learningplan);
					$sql .= " AND l.id IN ($selectedlearningplan) ";
					$assign_users_sql .= " AND l.id IN ($selectedlearningplan) ";
				}
				if (!empty($filterdata->status)) {
					if (!(in_array('active', $filterdata->status) && in_array('inactive', $filterdata->status))) {
						if (in_array('active', $filterdata->status)) {
							$sql .= " AND l.visible = 1 ";
						} else if (in_array('inactive', $filterdata->status)) {
							$sql .= " AND l.visible = 0 ";
						}
					}
				}
			}
			//end
			$sql .= " ORDER BY l.id DESC";
			// echo $sql;
			// print_r($lpparams);exit;
			if (($tableenable)) {
				$learning_plans = $this->db->get_records_sql($sql, $lpparams, $start, $length);
			} else {
				$learning_plans = $this->db->get_records_sql($sql, $lpparams);
			}

			if (is_siteadmin()) {
				$assign_users_sql .= "";
			} else {
				$assign_users_sql .= $costcenterpathconcatsql;
			}
			if (!empty($search)) {
				$assign_users_sql .= " AND l.name LIKE '%%$search%%'";
			}

			if ($filterdata) {


				if (!empty($filterdata->learningplan)) {
					$selectedlearningplan = implode(',', $filterdata->learningplan);
					$assign_users_sql .= " AND l.id IN ($selectedlearningplan) ";
				}

				if (!empty($filterdata->status)) {
					//$status = explode(',',$filterdata->status);
					if (!(in_array('active', $filterdata->status) && in_array('inactive', $filterdata->status))) {
						if (in_array('active', $filterdata->status)) {
							$assign_users_sql .= " AND l.visible = 1 ";
						} else if (in_array('inactive', $filterdata->status)) {
							$assign_users_sql .= " AND l.visible = 0 ";
						}
					}
				}
			}
			// echo $assign_users_sql;
			// exit;
			$assigned_users = $this->db->get_records_sql($assign_users_sql, $userparams);
		}

		if (empty($learning_plans)) {
			if ($tableenable) {
				return $output = array(
					"sEcho" => intval($requestData['sEcho']),
					"iTotalRecords" => 0,
					"iTotalDisplayRecords" => 0,
					"aaData" => array()
				);
			} else {
				return html_writer::tag('div', get_string('nolearningplans', 'local_learningplan'), array('class' => 'alert alert-info text-center pull-left mt-15', 'style' => 'width:96%;padding-left:2%;padding-right:1%;'));
			}
		} else {
			$sdata = array();
			$table_data = array();

			foreach ($learning_plans as $learning_plan) {
				$row = array();
				$capability1 = $capability2 = $capability3 = false;
				$actions = '';
				$departmentcount = isset($learning_plan->department) ? count(array_filter(explode(',', $learning_plan->department))) : 0;
				//$subdepartmentcount = count(array_filter(explode(',', $learning_plan->subdepartment)));
				$plan_url = new \moodle_url('/local/learningplan/plan_view.php', array('id' => $learning_plan->id));
				$planview_url = new \moodle_url('/local/learningplan/lpathinfo.php', array('id' => $learning_plan->id));

				if (empty($learning_plan->credits)) {
					$plan_credits = get_string('statusna');
				} else {
					$plan_credits = $learning_plan->credits;
				}
				if (empty($learning_plan->usercreated)) {
					$plan_usercreated = get_string('statusna');
				} else {
					$plan_usercreated = $learning_plan->usercreated;
					$user = $this->db->get_record_sql("SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename FROM {user} WHERE id = :plan_usercreated", array('plan_usercreated' => $plan_usercreated));
					$created_user = fullname($user);
				}

				if (!empty($learning_plan->location)) {
					$plan_location = $learning_plan->location;
				} else {
					$plan_location = get_string('statusna');
				}

				$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($learning_plan->id);
				$action_icons = '';
				if (is_siteadmin() || (has_capability('local/learningplan:visible', $categorycontext) && has_capability('local/learningplan:manage', $categorycontext))) {
					$capability1 = true;
				}

				// if($departmentcount > 1 && !(is_siteadmin() || has_capability('local/learningplan:manage',$categorycontext))){
				// 	$capability1 = false;
				// }

				if (has_capability('local/learningplan:update', $categorycontext)) {
					$capability2 = true;
				}
				if ($departmentcount > 1 && !(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext))) {
					$capability2 = false;
				}

				if (has_capability('local/learningplan:delete', $categorycontext)) {
					$capability3 = true;
				}

				if ($departmentcount > 1 && !(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext))) {
					$capability3 = false;
				}
				$can_view = false;
				if (is_siteadmin() || has_capability('local/learningplan:view', $categorycontext)) {
					$can_view = true;
				}

				$planlib = new \local_learningplan\lib\lib();
				$lplanassignedcourses = $planlib->get_learningplan_assigned_courses($learning_plan->id);
				$pathcourses = '';
				if (count($lplanassignedcourses) >= 2) {
					$i = 1;
					$coursespath_context['pathcourses'] = array();
					foreach ($lplanassignedcourses as $assignedcourse) {
						$coursename = $assignedcourse->fullname;
						$coursespath_context['pathcourses'][] = array('coursename' => $coursename, 'coursename_string' => 'C' . $i);
						$i++;
						if ($i > 10) {
							break;
						}
					}

					$pathcourses .= $this->render_from_template('local_learningplan/cousrespath', $coursespath_context);
				}

				$learningplan_content = array();
				$learning_plan_name = strlen($learning_plan->name) > 34 ? clean_text(substr($learning_plan->name, 0, 34)) . "..." : $learning_plan->name;
				$hide_show_icon = $learning_plan->visible ? $this->output->image_url('i/hide') : $this->output->image_url('i/show');
				$title_hide_show = $learning_plan->visible ? get_string('make_inactive', 'local_learningplan') : get_string('make_active', 'local_learningplan');
				$learning_plan_pathname = addslashes($learning_plan_name);
				$learningplan_target_id = 'manage_learningplan';
				$learningplan_content['plan_url'] = $plan_url;
				$learningplan_content['learning_plan_name'] = \local_costcenter\lib::strip_tags_custom($learning_plan_name);
				$learningplan_content['learning_plan_pathname'] = \local_costcenter\lib::strip_tags_custom($learning_plan_pathname);
				$learningplan_content['capability1'] = $capability1;
				$learningplan_content['capability2'] = $capability2;
				$learningplan_content['capability3'] = $capability3;

				$learningplan_content['hide'] = $learning_plan->visible ? true : false;

				$learningplan_content['hide_show_icon_url'] = $hide_show_icon;
				$learningplan_content['title_hide_show'] = $title_hide_show;
				$learningplan_content['delete_icon_url'] = $this->output->image_url('i/delete');

				$learningplan_content['edit_icon_url'] = $this->output->image_url('i/edit');
				$learningplan_content['learning_planid'] = $learning_plan->id;
				//$learningplan_content['plan_type'] = $plan_type;
				$learningplan_content['plan_credits'] = isset($learning_plan->credits);
				$learningplan_content['created_user'] = $created_user;

				list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $learning_plan->open_path);
				/*$learningplan_content['planorg'] = $this->db->get_field('local_costcenter', 'fullname', array('id' => $org));
        $learningplan_content['plansubdpt'] = $this->db->get_field('local_costcenter', 'fullname', array('id' => $bu));
        $learningplan_content['plancu'] = $this->db->get_field('local_costcenter', 'fullname', array('id' => $cu));
        $learningplan_content['planterritory'] = $this->db->get_field('local_costcenter', 'fullname', array('id' => $territory));*/
				$plandpt = $this->db->get_field('local_costcenter', 'fullname', array('id' => $ctr));
				$learningplan_content['plandpt'] = $plandpt ? $plandpt : 'All';
				$learningplan_content['plan_department'] = (empty($plan_department) || $plan_department == '-1') ? 'All' : $plan_department;
				$learningplan_content['plan_shortname_string'] = $learning_plan->shortname ? $learning_plan->shortname : 'NA';
				$learningplan_content['plan_department_string'] = ( empty($plan_department_string) || $plan_department_string == '-1') ? 'All' : $plan_department_string;
				$learningplan_content['plan_subdepartment'] = empty($plan_subdepartment ) ? 'All' : $plan_subdepartment;
				$learningplan_content['plan_url'] = $plan_url;
				$learningplan_content['planview_url'] = $planview_url;
				$learningplan_content['lpcoursespath'] = $pathcourses;
				$learningplan_content['lpcoursescount'] = count($lplanassignedcourses);
				$learningplan_content['can_view'] = $can_view;
				$learningplan_content['enroll_link'] = $CFG->wwwroot . '/local/learningplan/lpusers_enroll.php?lpid=' . $learning_plan->id;


				if ($capability3) {
					$actions .= '<a href="javascript:void(0);" title = ' . get_string('delete', 'local_learningplan') . ' onclick="(function(e){ require(\'local_learningplan/lpcreate\').deleteConfirm({action:\'deleteplan\', id: ' . $learning_plan->id . ',name:\'' . $learning_plan_pathname . '\'}) })(event)" ><i class="fa fa-trash fa-fw" aria-hidden="true"></i></a>';
				}

				if ($capability2) {
					$actions .=  '<a href="javascript:void(0);" title = ' . get_string('edit', 'local_learningplan') . ' onclick="(function(e){ require(\'local_learningplan/lpcreate\').init({selector:\'updatelpmodal\', contextid:1, planid: ' . $learning_plan->id . ', form_status:0 }) })(event)" ><i class="fa fa-pencil fa-fw" aria-hidden="true"></i></a>';
				}

				if ($capability1) {
					if ($learning_plan->visible == 0) {
						$actions .=  '<a href="javascript:void(0);" title = \'' . $title_hide_show . '\' onclick="(function(e){ require(\'local_learningplan/lpcreate\').toggleVisible({action:\'toggleplan\' ,visible:\'hidden\', id: ' . $learning_plan->id . ', name:\'' . $learning_plan_pathname . '\'}) })(event)" ><i class="fa fa-eye-slash icon" aria-hidden="true"></i></a>';
					} else {

						$actions .=  '<a href="javascript:void(0);" title = \'' . $title_hide_show . '\' onclick="(function(e){ require(\'local_learningplan/lpcreate\').toggleVisible({action:\'toggleplan\' ,visible:\'visible\', id:' . $learning_plan->id . ',name:\'' . $learning_plan_pathname . '\'}) })(event)" ><i class="fa fa-eye" aria-hidden="true"></i></a>';
					}
				}

				$actions .=  '<a href="' . $CFG->wwwroot . '/local/learningplan/lpusers_enroll.php?lpid=' . $learning_plan->id . '" title = ' . get_string('le_enrol_users', 'local_learningplan') . '><i class="icon fa fa-user-plus fa-fw" aria-hidden="true"></i></a>';

				if ($view_type == 'card') {
					$row[] = $this->render_from_template('local_learningplan/learninngplan_index_view', $learningplan_content);
					$sdata[] = implode('', $row);
				} else {
					$class = '';
					if ($learning_plan->visible == 0) {
						$class = 'disabled';
					}
					if($can_view){
						$view_url = $CFG->wwwroot . '/local/learningplan/plan_view.php?id=' . $learning_plan->id;
					}else{
						$view_url = $CFG->wwwroot . '/local/learningplan/lpathinfo.php?id=' . $learning_plan->id;
					}

					$row = [html_writer::tag('a', $learning_plan_name, array('href' => $view_url, 'class' => $class)), html_writer::span($learningplan_content['plan_shortname_string'], $class), html_writer::span($learningplan_content['plandpt'], $class), html_writer::span($learningplan_content['lpcoursescount'], $class), html_writer::span($actions,  $class)];
				}
				$table_data[] = $row;
			}
			if ($tableenable) {
				$iTotal = count($assigned_users);
				$iFilteredTotal = $iTotal;
				if ($view_type == 'card') {
					$lpchunk = array_chunk($sdata, 2);
					$chunk = array(array(""));

					if (isset($lpchunk[count($lpchunk) - 1]) && count($lpchunk[count($lpchunk) - 1]) != 2) {

						if (count($lpchunk[count($lpchunk) - 1]) == 1) {

							$lpchunk[count($lpchunk) - 1] = array_merge($lpchunk[count($lpchunk) - 1], $chunk, $chunk);
						} else {
							$lpchunk[count($lpchunk) - 1] = array_merge($lpchunk[count($lpchunk) - 1], $chunk);
						}
					}



					// print_r($assigned_users);
					// exit;
					return $output = array(
						"sEcho" => intval($requestData['sEcho']),
						"iTotalRecords" => $iTotal,
						"iTotalDisplayRecords" => $iFilteredTotal,
						"aaData" => $lpchunk
					);
				} else {
					// print_object($table_data);
					return $output = array(
						"sEcho" => intval($requestData['sEcho']),
						"iTotalRecords" => $iTotal,
						"iTotalDisplayRecords" => $iFilteredTotal,
						"aaData" => $table_data
					);
				}
			}

			$table = new html_table();
			if ($view_type == 'card') {
				$table->id = 'all_learning_plans';
			} else {
				$table->id = 'all_learning_plans_table';
			}
			if ($view_type == 'card') {
				$table->head = array('', '');
			} else {
				$table->head = array(get_string('learningplanname', 'local_learningplan'), get_string('learningplan', 'local_learningplan'), get_string('department', 'local_learningplan'), get_string('assigned_courses', 'local_learningplan'), get_string('actions', 'local_learningplan'));
			}
			$table->data = $table_data;
			$return = html_writer::table($table);
			$filtersubdepts = $filterorganizations = $filterdepartments = $filterstatus = $filterlearningplan = $filter4level = $filter5level = $filterstates = $filterdistrict = $filtersubdistrict = $filtervillage = $filtercategories = '';
			if ($filterdata) {
				if ($filterdata->filteropen_subdepartment) {
					$filtersubdepts = implode(',', $filterdata->filteropen_subdepartment);
				}

				if (isset($filterdata->filteropen_costcenterid) && !empty($filterdata->filteropen_costcenterid && is_array($filterdata->filteropen_costcenterid))) {
					$filterorganizations = implode(',', $filterdata->filteropen_costcenterid);
				}

				if (isset($filterdata->filteropen_department) &&  !empty($filterdata->filteropen_department) && is_array($filterdata->filteropen_department)) {
					$filterdepartments = implode(',', $filterdata->filteropen_department);
				}

				if (isset($filterdata->learningplan) &&  !empty($filterdata->learningplan)  && is_array($filterdata->learningplan)) {
					$filterlearningplan = implode(',', $filterdata->learningplan);
				}

				if (isset($filterdata->filteropen_level4department) && !empty($filterdata->filteropen_level4department) && is_array($filterdata->filteropen_level4department)) {
					$filter4level = implode(',', $filterdata->filteropen_level4department);
				}

				if (isset($filterdata->filteropen_level5department) && !empty($filterdata->filteropen_level5department) && is_array($filterdata->filteropen_level5department)) {
					$filter5level = implode(',', $filterdata->filteropen_level5department);
				}

				if (isset($filterdata->states) &&  !empty($filterdata->states) && is_array($filterdata->states)) {
					$filterstates = implode(',', $filterdata->states);
				}

				if (isset($filterdata->district) && !empty($filterdata->district) && is_array($filterdata->district)) {
					$filterdistrict = implode(',', $filterdata->district);
				}

				if (isset($filterdata->subdistrict) && !empty($filterdata->subdistrict) && is_array($filterdata->subdistrict)) {
					$filtersubdistrict = implode(',', $filterdata->subdistrict);
				}

				if (isset($filterdata->village) && !empty($filterdata->village) && is_array($filterdata->village)) {
					$filtervillage = implode(',', $filterdata->village);
				}

				if (isset($filterdata->status) && !empty($filterdata->status)  && is_array($filterdata->status)) {
					//print_r($filterdata->status);
					$filterstatus = implode(',', $filterdata->status);
				}

				if (isset($filterdata->categories) && !empty($filterdata->categories)  && is_array($filterdata->categories)) {
					$filtercategories = implode(',', $filterdata->categories);
				}
			} else {
				$filtersubdepts = $filterorganizations = $filterdepartments = $filterlearningplan = $filterstatus = $filter4level = $filter5level = $filterstates = $filterdistrict = $filtersubdistrict = $filtervillage = $filtercategories = null;
			}
			if (!empty($subdepartment)) {
				$filtersubdepts = $subdepartment;
			}

			if (!empty($l4department)) {
				$filter4level = $l4department;
			}
			if (!empty($l5department)) {
				$filter5level = $l5department;
			}

			if (!empty($costcenterid)) {
				$filterorganizations = $costcenterid;
			}
			if (!empty($departmentid)) {
				$filterdepartments = $departmentid;
			}

			if (!empty($states)) {
				$filterstates = $states;
			}
			if (!empty($district)) {
				$filterdistrict = $district;
			}
			if (!empty($subdistrict)) {
				$filtersubdistrict = $subdistrict;
			}
			if (!empty($village)) {
				$filtervillage = $village;
			}

			if (!empty($status1)) {
				$filterstatus = $status1;
			}

			if (!empty($filtercategories)) {
				$filtercategories = $filtercategories;
			}

			$return .= html_writer::script('$(document).ready(function(){
										  	
												$("#' . $table->id . '").DataTable({
												
												    "serverSide": true,
												    "language": {
														paginate: {
															"previous": "<",
															"next": ">"
														},
														  "search": "",
                    									  "searchPlaceholder": "' . get_string('search', 'local_learningplan') . '",
                    									  "emptyTable":     "<div class=\'w-100 alert alert-info\'>No Learning Paths Available </div>",
													},
													"ajax": "ajax.php?manage=1&subdepts=' . $filtersubdepts . '&costcenterid=' . $filterorganizations . '&departmentid=' . $filterdepartments . '&learningplan=' . $filterlearningplan . '&status=' . $filterstatus . '&view_type=' . $view_type . '&department4level=' . $filter4level . '&department5level=' . $filter5level . '&states=' . $filterstates . '&district=' . $filterdistrict . '&subdistrict=' . $filtersubdistrict . '&village=' . $filtervillage. '&categories=' . $filtercategories . '",
													"datatype": "json",
													"pageLength": 8,
													
												});
												$("table#all_learning_plans thead").css("display" , "none");
												$("#all_learning_plans_length").css("display" , "none");
										   });');
			$return .= '';
		}
		return $return;
	}

	public function single_plan_view($planid)
	{
		global $CFG, $PAGE;
		$learningplan_lib = new lib();

		$lpimgurl = $learningplan_lib->get_learningplansummaryfile($planid);
		$plan_record = $this->db->get_record('local_learningplan', array('id' => $planid));
		$plan_description = !empty($plan_record->description) ?  \local_costcenter\lib::strip_tags_custom(html_entity_decode($plan_record->description), array('overflowdiv' => false, 'noclean' => false, 'para' => false)) : 'No Description available';
		$plan_objective = !empty($plan_record->objective) ? $plan_record->objective : 'No Objective available';
		/*Count of the enrolled users to LEP*/
		$userpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql('u.open_path');
		$totaluser_sql = "SELECT llu.planid,count(llu.userid) as data FROM {local_learningplan_user} as llu 
			JOIN {user} as u ON u.id=llu.userid 
			WHERE llu.planid = :planid AND u.deleted != :deleted $userpathconcatsql GROUP BY llu.planid ";
		$total_enroled_users = $this->db->get_record_sql($totaluser_sql, array('planid' => $planid, 'deleted' => 1));
		/*Count of the requested users to LEP*/
		$total_completed_users = $this->db->get_records_sql("SELECT id FROM {local_learningplan_user} WHERE completiondate IS NOT NULL
													 AND status = 1 AND planid = $planid");
		$cmpltd = array();
		foreach ($total_completed_users as $completed_users) {
			$cmpltd[] = $completed_users->id;
		}
		$percent = 0;
		if(!empty($total_enroled_users) && $total_enroled_users->data!=0){
			$percent = count($cmpltd)/$total_enroled_users->data * 100;
		}
		$total_requested_users = $this->db->count_records('local_learningplan_approval', array('planid' => $planid));
		/*Count of the courses of LEP*/
		$total_assigned_course = $this->db->count_records('local_learningplan_courses', array('planid' => $planid));

		$total_mandatory_course = $this->db->get_records_sql("SELECT id FROM {local_learningplan_courses} WHERE planid = $planid
													 AND nextsetoperator = 'and'");
		$mandatory = array();
		foreach ($total_mandatory_course as $total_mandatory) {
			$mandatory[] = $total_mandatory->id;
		}
		$total_optional_course = $this->db->get_records_sql("SELECT id FROM {local_learningplan_courses} WHERE planid = $planid
													 AND nextsetoperator = 'or'");
		$optional = array();
		foreach ($total_optional_course as $total_optional) {
			$optional[] = $total_optional->id;
		}

		if (!empty($plan_record->startdate)) {
			$plan_startdate = \local_costcenter\lib::get_userdate("d/m/Y H:i", $plan_record->startdate);
		} else {
			$plan_startdate = get_string('statusna');
		}
		if (!empty($plan_record->enddate)) {
			$plan_enddate = \local_costcenter\lib::get_userdate("d/m/Y H:i", $plan_record->enddate);
		} else {
			$plan_enddate = get_string('statusna');
		}
		// if(empty($plan_record->credits)){
		// 	$plan_credits = 'N/A';
		// }else{
		// 	$plan_credits = $plan_record->credits;
		// }
		if (empty($plan_record->usercreated)) {
			$plan_usercreated = get_string('statusna');
		} else {
			$plan_usercreated = $plan_record->usercreated;
			$user = $this->db->get_record_sql("select * from {user} where id = $plan_usercreated");
			$created_user = fullname($user);
		}
		/*if($plan_record->learning_type == 1){
			$plan_type = 'Core Courses';
		}elseif($plan_record->learning_type == 2){
			$plan_type = 'Elective Courses';
		}*/
		if ($plan_record->approvalreqd == 1) {
			$plan_needapproval = 'Yes';
		} else {
			$plan_needapproval = 'No';
		}
		if (!empty($plan_record->open_group)) {
			$plan_location = $plan_record->open_group;
			$str_len = strlen($plan_record->open_group);
			if ($str_len > 32) {
				$sub_str = clean_text(substr($plan_record->open_group, 0, 32));
			}
		} else {
			$plan_location =  get_string('statusna');
		}




		if (!empty($plan_record->department)) {
			$depart = open::departments($plan_record->department);
			$Dep = array();
			foreach ($depart as $dep) {
				$Dep[] = $dep->fullname;
			}
			$plan_department = implode(',', $Dep);
		} else {
			$plan_department =  get_string('statusna');
		}




		if (!empty($plan_record->subdepartment)) {
			$depart = open::departments($plan_record->subdepartment);
			$Dep = array();
			foreach ($depart as $dep) {
				$Dep[] = $dep->fullname;
			}
			$plan_subdepartment = implode(',', $Dep);
			$str_len = strlen($plan_subdepartment);
			if ($str_len > 32) {
				$sub_str = substr($plan_subdepartment, 0, 32);
				$plan_subdepartment = $substr_subdepartment;
			}
		} else {
			$plan_subdepartment =  get_string('statusna');
		}
		$lplanassignedcourses = $learningplan_lib->get_learningplan_assigned_courses($planid);
		$pathcourses = '';
		if ($lplanassignedcourses) {
			$i = 1;
			$coursespath_context['pathcourses'] = array();
			foreach ($lplanassignedcourses as $assignedcourse) {
				if (count($lplanassignedcourses) >= 2) {
					$coursename = $assignedcourse->fullname;
					$coursespath_context['pathcourses'][] = array('coursename' => $coursename, 'coursename_string' => 'C' . $i);
				}
				$i++;
				if ($i > 10) {
					break;
				}
			}
			$pathcourses .= $this->render_from_template('local_learningplan/cousrespath', $coursespath_context);
		}
		$description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($plan_record->description), array('overflowdiv' => false, 'noclean' => false, 'para' => false));
		$description_string = strlen($description) > 400 ? clean_text(substr($description, 0, 400)) . "..." : $description;
		$ratings_exist = \core_component::get_plugin_directory('local', 'ratings');
		if ($ratings_exist) {
			require_once($CFG->dirroot . '/local/ratings/lib.php');
			$display_ratings = display_rating($planid, 'local_learningplan');
			$display_like = display_like_unlike($planid, 'local_learningplan');
			// $display_like .= display_comment($planid, 'local_learningplan');
			// $PAGE->requires->jquery();
			// $PAGE->requires->js('/local/ratings/js/jquery.rateyo.js');
			// $PAGE->requires->js('/local/ratings/js/ratings.js');
		} else {
			$display_ratings = $display_like = '';
		}
		$planview_context = array();
		$planview_context['lpnameimg'] = $lpimgurl;
		$planview_context['lpname'] = $plan_record->name;
		$planview_context['lpcoursespath'] = $pathcourses;
		$planview_context['description'] = $description_string;
		//$planview_context['plan_type'] = $plan_type;
		$planview_context['plan_learningplancode'] = $plan_record->shortname ? $plan_record->shortname : 'NA';
		$planview_context['plan_needapproval'] = $plan_needapproval;
		$planview_context['plan_credits'] = isset($plan_record->credits);
		$planview_context['created_user'] = $created_user;
		$planview_context['total_assigned_course'] = $total_assigned_course;
		$planview_context['mandatory'] = count($mandatory);
		$planview_context['optional'] = count($optional);
		$planview_context['ratings_exist'] = $ratings_exist;
		$planview_context['display_ratings'] = $display_ratings;
		$planview_context['display_like'] = $display_like;



		$planview_context['plan_department_string'] = ($plan_department == '-1' || empty($plan_department)) ? 'All' : $plan_department;

		$plan_department = strlen($plan_department) > 23 ? clean_text(substr($plan_department, 0, 23)) . "..." : $plan_department;
		$planview_context['plan_department'] = ($plan_department == '-1' || empty($plan_department)) ? 'All' : $plan_department;
		$planview_context['plan_subdepartment'] = $plan_subdepartment;
		$planview_context['plan_location'] = $plan_location;
		$planview_context['total_enroled_users'] = isset($total_enroled_users->data) ? $total_enroled_users->data : 0;
		$planview_context['cmpltd'] = count($cmpltd);
		$planview_context['points'] = $plan_record->open_points ? $plan_record->open_points : 0;
		$planview_context['percent'] =  $percent;
		
		list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $plan_record->open_path);
		$planorg = $this->db->get_field('local_costcenter', 'fullname', array('id' => $org));
		$plandpt = $this->db->get_field('local_costcenter', 'fullname', array('id' => $ctr));
		$plansubdpt = $this->db->get_field('local_costcenter', 'fullname', array('id' => $bu));
		$plancu = $this->db->get_field('local_costcenter', 'fullname', array('id' => $cu));
		$planterritory = $this->db->get_field('local_costcenter', 'fullname', array('id' => $territory));
		$planview_context['planorg'] = $planorg ? $planorg : 'All';
		$planview_context['plandpt'] = $plandpt ? $plandpt : 'All';
		$planview_context['plansubdpt'] = $plansubdpt ? $plansubdpt : 'All';
		$planview_context['plancu'] = $plancu ? $plancu : 'All';
		$planview_context['planterritory'] = $planterritory ? $planterritory : 'All';
		return $this->render_from_template('local_learningplan/lp_planview', $planview_context);
	}
	/** Function For The Tabs View In The Learning
	@param $id=LEP id && $curr_tab=tab name
	Plan**/
	public function plan_tabview($id, $curr_tab, $condition)
	{
		global $PAGE;

		$courses_active = '';
		$users_active = '';
		$bulk_users_active = '';
		$request_users = '';
		if ($curr_tab == 'users') {
			$users_active = ' active ';
		} elseif ($curr_tab == 'courses') {
			$courses_active = ' active ';
		} elseif ($curr_tab == 'request_user') {
			$request_users = ' active';
		}

		$total_enroled_users = $this->db->get_record_sql('SELECT count(llu.userid) as data  FROM {local_learningplan_user} as llu JOIN {user} as u ON u.id=llu.userid WHERE llu.planid=' . $id . ' AND u.deleted!=1');
		$total_requested_users = $this->db->count_records('local_learningplan_approval', array('planid' => $id));
		$total_assigned_course = $this->db->count_records('local_learningplan_courses', array('planid' => $id));
		$return = '';
		$tabs = '<div id="learningplantabs" class="planview_tabscontainer w-full pull-left mt-3">
					<ul class="nav nav-tabs inner_tabs" role="tablist">
						<li class="nav-item learningplan_tabs" role="presentation"  data-module="courses"  data-id="' . $id . '">
							<a class="active nav-link" data-toggle="tab"  href="javascript:void(0)" aria-controls="plan_courses" role="tab">
								' . get_string('courses', 'local_learningplan') . '</a>
						</li>
						<li class="nav-item learningplan_tabs" role="presentation" data-module="users" data-id="' . $id . '">
							<a class="nav-link" data-toggle="tab" href="javascript:void(0)" aria-controls="plan_users" role="tab">
								' . get_string('users', 'local_learningplan') . '
							</a>
						</li>
						<li class="nav-item learningplan_tabs" role="presentation" data-module="targetaudiences" data-id="' . $id . '">
							<a class="nav-link" data-toggle="tab" href="javascript:void(0)" aria-controls="plan_targetaudiences" role="tab">
								' . get_string('target_audience_tab', 'local_learningplan') . '
							</a>
						</li>';

		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
		if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
			$request_renderer = $PAGE->get_renderer('local_request');
			$requestdata = $request_renderer->render_requestview(TRUE, $id, 'learningplan');
			$options = $requestdata['options'];
			$dataoptions = $requestdata['dataoptions'];
			$filterdata = $requestdata['filterdata'];
			$tabs .= "<li class='nav-item learningplan_tabs' role='presentation' data-module='requestedusers' data-id=$id data-options = '" . $options . "' data-dataoptions='" . $dataoptions . "' data-filterdata='" . $filterdata . "'>
							<a class='nav-link' data-toggle='tab' href='javascript:void(0)' aria-controls='requested_users' role='tab'>
								" . get_string('requested_users', 'local_learningplan') . "
							</a>
						</li>
						";
		}
		$tabs .= '</ul>';
		$tabs .= '<div class="tab-content" id="learningplantabscontent">';
		$tabs .= $this->learningplans_courses_tab_content($id, $curr_tab, $condition);
		$tabs .= '</div>';
		$tabs .= '</div>';
		$return .= $tabs;
		return $return;
	}

	/**Function to view of course tab
	$planid=LEP_id $curr_tab="tab name"
	 **/
	public function learningplans_courses_tab_content($planid, $curr_tab, $condition)
	{

		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);
		$return = '';
		$return .= '<div class="tab-pane active mt-15 ml-15" id="plan_courses" role="tabpanel">';
		if (has_capability('local/learningplan:assigncourses', $categorycontext)) {
			$return .= $this->learningplans_assign_courses_form($planid, $condition);
		}
		$return .= '';
		$return .= '<div class="row lp_course-wrapper w-100 ">' . $this->assigned_learningplans_courses($planid) . '</div>';
		$return .= '';
		$return .= '</div>';
		return $return;
	}
	public function learningplans_target_audience_content($planid, $curr_tab, $condition)
	{
		global $OUTPUT, $CFG, $DB, $USER;
		$data = $DB->get_record_sql('SELECT id, open_group, open_hrmsrole,
             open_designation, open_location,open_path,open_states,open_district,open_subdistrict,open_village
             FROM {local_learningplan} WHERE id = ' . $planid);
		list($zero, $org, $ctr, $bu, $cu) = explode("/", $data->open_path);
		if ($ctr == -1 || $ctr == NULL) {
			$department = get_string('audience_department', 'local_learningplan', 'All');
		} else {
			$departments = $DB->get_field_sql("SELECT GROUP_CONCAT(fullname)  FROM {local_costcenter} WHERE id IN ($ctr)");
			$department = get_string('audience_department', 'local_learningplan', $departments);
		}
		if ($bu == -1 || $bu == NULL) {
			$subdepartment = get_string('audience_subdepartment', 'local_learningplan', 'All');
		} else {
			$sql = "SELECT id,fullname FROM {local_costcenter} WHERE id IN ($bu)";
			$subdepartments = $DB->get_records_sql_menu($sql);
			$subdepts = implode(", ", $subdepartments);
			$subdepartment = get_string('audience_subdepartment', 'local_learningplan', $subdepts);
		}

		if ($cu == NULL) {
			$commercial = get_string('audience_commercial', 'local_learningplan', 'All');
		} else {
			$sql = "SELECT id,fullname FROM {local_costcenter} WHERE id IN ($cu)";
			$commercials = $DB->get_records_sql_menu($sql);
			$commercial_list = implode(", ", $commercials);
			$commercial = get_string('audience_commercial', 'local_learningplan', $commercial_list);
		}

		if ($data->open_group == NULL || $data->open_group == -1) {
			$group = get_string('audience_group', 'local_learningplan', 'All');
		} else {
			$sql = "SELECT id,name FROM {cohort} c JOIN {local_groups} g ON g.cohortid = c.id  WHERE g.id IN ($data->open_group)";
			$groups = $DB->get_records_sql_menu($sql);
			$group_list = implode(", ", $groups);
			$group = get_string('audience_group', 'local_learningplan', $group_list);
		}

		if ($data->open_designation == NULL || $data->open_group == -1) {
			$designation = get_string('audience_designation', 'local_learningplan', 'All');
		} else {			
			$designation = get_string('audience_designation', 'local_learningplan', $data->open_designation);
		}
		
		// if($data->open_states > 0){
		//     $sql = "SELECT id,states_name FROM {local_states} WHERE id IN ($data->open_states)";
		//     $open_states = $DB->get_records_sql_menu($sql);
		//     $states = implode(", ", $open_states);
		//     $state = get_string('audience_state','local_learningplan',$states);
		// }else{
		//     $state=get_string('audience_state','local_learningplan','All');
		// }

		// if($data->open_district > 0){
		//     $sql = "SELECT id,district_name FROM {local_district} WHERE id IN ($data->open_district)";
		//     $open_districts = $DB->get_records_sql_menu($sql);
		//     $districts = implode(", ", $open_districts);
		//     $district = get_string('audience_district','local_learningplan',$districts);
		// }else{
		//     $district=get_string('audience_district','local_learningplan','All');
		// }

		// if($data->open_subdistrict > 0){
		//     $sql = "SELECT id,subdistrict_name FROM {local_subdistrict} WHERE id IN ($data->open_subdistrict)";
		//     $open_subdistricts = $DB->get_records_sql_menu($sql);
		//     $subdistricts = implode(", ", $open_subdistricts);
		//     $subdistrict = get_string('audience_sub_disctrict','local_learningplan',$subdistricts);
		// }else{
		//     $subdistrict=get_string('audience_sub_disctrict','local_learningplan','All');
		// }

		// if($data->open_village > 0){
		//     $sql = "SELECT id,village_name FROM {local_village} WHERE id IN ($data->open_village)";
		//     $open_villages = $DB->get_records_sql_menu($sql);
		//     $villages = implode(", ", $open_villages);
		//     $village = get_string('audience_village','local_learningplan',$villages);
		// }else{
		//     $village=get_string('audience_village','local_learningplan','All');
		// }
		/*            if(empty($data->open_group)){
                 $group=get_string('audience_group','local_learningplan','All');
            }else{
                $sql = "SELECT id,name
                 		FROM {cohort} 
                 		WHERE id IN ($data->open_group)";

                $groupslist = $DB->get_records_sql_menu($sql);
                $groups = implode(", ", $groupslist);
                $group = get_string('audience_group','local_learningplan',$groups);
            }
            
            $data->open_hrmsrole =(!empty($data->open_hrmsrole)) ? $hrmsrole=get_string('audience_hrmsrole','local_learningplan',$data->open_hrmsrole) :$hrmsrole=get_string('audience_hrmsrole','local_learningplan','All');
            
            $data->open_designation =(!empty($data->open_designation)) ? $designation=get_string('audience_designation','local_learningplan',$data->open_designation) :$designation=get_string('audience_designation','local_learningplan','All');
            
            $data->open_location =(!empty($data->open_location)) ? $location=get_string('audience_location','local_learningplan',$data->open_location) :$location=get_string('audience_location','local_learningplan','All');*/
		return '<div class="tab-pane active mt-15 ml-15" id="plan_targetaudiences" role="tabpanel">' . $department . $subdepartment . $commercial. $group . $designation . $subdistrict . $village . '</div>';
	}
	/**Function to tab view of bulk users uploads
	$planid=LEP_id $curr_tab="tab name"
	 **/
	public function learningplans_bulk_users_tab_content($planid, $designation, $department, $empnumber, $organization, $email, $band, $subdepartment, $sub_subdepartment)
	{
		$return = '';
		if (!is_null($designation) || !empty($department) || !empty($organization) || !empty($empnumber) || !empty($email) || !empty($band) || !empty($subdepartment) || !empty($sub_subdepartment)) {
			$select_to_users = $this->select_to_users_of_learninplan($planid, $this->user->id, $designation, $department, $empnumber, $organization, $email, $band, $subdepartment, $sub_subdepartment);
			$select_from_users = $this->select_from_users_of_learninplan($planid, $this->user->id, $designation, $department, $empnumber, $organization, $email, $band, $subdepartment, $sub_subdepartment);
		} else {
			$select_to_users = $this->select_to_users_of_learninplan($planid, $this->user->id, $designation, $department, $empnumber, $organization, $email, $band, $subdepartment, $sub_subdepartment);
			$select_from_users = $this->select_from_users_of_learninplan($planid, $this->user->id, $designation, $department, $empnumber, $organization, $email, $band, $subdepartment, $sub_subdepartment);
		}

		$return .= '<div class="user_batches text-center">
					<form  method="post" name="form_name" id="assign_users_' . $planid . '" action="assign_courses_users.php" class="form_class" >
					<input type="hidden"  name="type" value="bulkusers" >
					<input type="hidden"  name="planid" value=' . $planid . ' >
					<fieldset>
					<ul class="button_ul">
					
					<li style="padding:18px; display:none"><label>' . get_string('search', 'local_learningplan') . '</label>
					<input id="textbox" type="text"/>
					</li>
					<li><input type="button" id="select_remove" name="select_all" value="Select All">
					<input type="button" id="remove_select" name="remove_all" value="Remove All">
					</li>
					
					<li>';

		$return .= '<select name="add_users[]" id="select-from" multiple size="15">';

		$return .= '<optgroup label="Selected member list (' . count($select_from_users) . ') "></optgroup>';
		if (!empty($select_from_users)) {
			foreach ($select_from_users as $select_from_user) {
				if ($select_from_user->id == $this->user->id) {
					$trainerid_exist = array();
				} else {
					$trainerid_exist = "";
				}
				if ((empty($trainerid_exist))) {
					$symbol = "";
					$check = $this->db->get_record('local_learningplan_user', array('userid' => $select_from_user->id, 'status' => 1, 'planid' => $planid));
					if ($check) {
						$disable = "disabled";
						$title = "title='User Completed'";
					} else {
						$title = "";
						$disable = "";
					}
					$data_id = preg_replace("/[^0-9,.]/", "", $select_from_user->idnumber);
					$return .= "<option value=$select_from_user->id $disable $title>$symbol $select_from_user->firstname $select_from_user->lastname ($data_id)</option>";
				}
			}
			foreach ($select_from_users as $select_from_user) {
			}
		} else {
			$return .= '<optgroup label="None"></optgroup>';
		}

		$return .=	'</select></li>
					</ul>
					<ul class="button_ul">
						
					<li><input type="submit" name="submit_users" value="add users" id="btn_add" style="width:98px;"></li>                    
					<li><input type="submit" name="submit_users" value="remove users" id="btn_remove"></li>
					</ul>
					
					<ul class="button_ul">
					<li><input type="button" id="select_add" name="select_all" value="Select All">
					<input type="button" id="add_select" name="remove_all" value="Remove All">
					</li>
					<li><select name="remove_users[]" id="select-to" multiple size="15">';

		$return .= '<optgroup label="Selected member list (' . count($select_to_users) . ') "></optgroup>';
		if (count($select_to_users) > 100) {
			$return .= '<optgroup label="Too many users, use search."></optgroup>';
			$select_to_users = array_slice($select_to_users, 0, 100);
		}
		if (!empty($select_to_users)) {
			foreach ($select_to_users as $select_to_user) {
				if ($select_to_user->id == $this->user->id) {
					$trainerid_exist = array();
				} else {
					$trainerid_exist = "";
				}
				$data_id = preg_replace("/[^0-9,.]/", "", $select_to_user->idnumber);
				if ((empty($trainerid_exist))) {
					$symbol = "";
					$return .= "<option  value=$select_to_user->id >$symbol $select_to_user->firstname $select_to_user->lastname ($data_id)</option>";
				}
			}
		} else {
			$return .= '<optgroup label="None"></optgroup>';
		}

		$return .= '</select></li>
					</ul>
					</fieldset>
					</form>
					</div>';

		$return .= "<script>
						$('#btn_add').prop('disabled', true);
						  $('#select-to').on('change', function() {
						  
							 if(this.value!=''){
							  $('#btn_add').prop('disabled', false);
							  $('#btn_remove').prop('disabled', true);
							 }else{
							  $('#btn_add').prop('disabled', true);
							}
						})
						$('#select_add').click(function() {
								 $('#select-to option').prop('selected', true);
								  $('#btn_remove').prop('disabled', true);
								 $('#btn_add').prop('disabled', false);
							});
						$('#add_select').click(function() {
								 $('#select-to option').prop('selected',false);
								 $('#btn_remove').prop('disabled', true);
								 $('#btn_add').prop('disabled', true);
							}); 
						
						$('#btn_remove').prop('disabled', true);
						  $('#select-from').on('change', function() {
							 if(this.value!=''){
							  $('#btn_remove').prop('disabled', false);
							  $('#btn_add').prop('disabled', true);
							 }else{
							  $('#btn_remove').prop('disabled', true);
							}
						})
						$('#select_remove').click(function() {
								 $('#select-from option').prop('selected', true);
								 $('#btn_add').prop('disabled', true);
								 $('#btn_remove').prop('disabled', false);
							});
						$('#remove_select').click(function() {
								 $('#select-from option').prop('selected', false);
								 $('#btn_add').prop('disabled', true);
								 $('#btn_remove').prop('disabled', true);
							});
						
						
					</script>";
		/*to check courses has the Learning plan enrolment or not*/
		$courses = $this->db->get_records('local_learningplan_courses', array('planid' => $planid));

		if ($courses) {/*If courses it self not assignes so to check condition*/
			$table = 'local_learningplan_courses';
			$conditions = array('planid' => $planid);
			$sort = 'id';
			$fields = 'id, courseid';
			$result = $this->db->get_records_menu($table, $conditions, $sort, $fields);
			$count = count($result);
			/*finally get the count of records in total courses*/
			$data = implode(',', $result);
			$sql = "select * from {enrol} where courseid IN ($data) and enrol='learningplan'";
			$check = $this->db->get_records_sql($sql);
			$check_count = count($check);
			/*get the enrol records according to course*/
			if ($check_count == $count) {
				return $return;
			} else {
				//$return_msg ='Please apply Learning plan enrolment to all course';
				return $return_msg;
			}
		}
	}
	public function select_from_users_of_learninplan($planid, $userid, $params, $total = 0, $offset1 = -1, $perpage = -1, $lastitem = 0)
	{
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);
		$users = $this->db->get_record('local_learningplan', array('id' => $planid));
		if ($total == 0) {

			$sql = "SELECT u.id,concat(u.firstname,' ',u.lastname,' ','(',u.idnumber,')') as fullname ";
		} else {
			$sql = "SELECT count(u.id) as total";
		}

		$sql .= " FROM {user} u WHERE u.id >1 AND u.deleted=0 AND u.suspended=0 ";
		$userpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql('u.open_path');
		$costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');
	/* 	if ($lastitem != 0) {
			$sql .= " AND u.id > $lastitem ";
		} */
		if (is_siteadmin()) {
			$sql .= "";
		} else {
			$sql .= $costcenterpathconcatsql . " " . $userpathconcatsql;
		}

		$sql .= " AND u.id in(SELECT userid FROM {local_learningplan_user} WHERE planid=$planid)";

		if (!empty($params['organization'])) {
			$organizations = explode(',', $params['organization']);
			$orgsql = [];
			foreach ($organizations as $organisation) {
				$orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
				$params["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
			}
			if (!empty($orgsql)) {
				$sql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
			}
		}
		if (!empty($params['department'])) {
			$departments = explode(',', $params['department']);
			$deptsql = [];
			foreach ($departments as $department) {
				$deptsql[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
				$params["departmentparam_{$department}"] = '%/' . $department . '/%';
			}
			if (!empty($deptsql)) {
				$sql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
			}
		}

		if (!empty($params['subdepartment'])) {
			$subdepartments = explode(',', $params['subdepartment']);
			$subdeptsql = [];
			foreach ($subdepartments as $subdepartment) {
				$subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
				$params["subdepartmentparam_{$subdepartment}"] = '%/' . $subdepartment . '/%';
			}
			if (!empty($subdeptsql)) {
				$sql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
			}
		}
		if (!empty($params['department4level'])) {
			$depart4level = explode(',', $params['department4level']);
			$department4levelsql = [];
			foreach ($depart4level as $department4level) {
				$department4levelsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
				$params["department4levelparam_{$department4level}"] = '%/' . $department4level . '/%';
			}
			if (!empty($department4levelsql)) {
				$sql .= " AND ( " . implode(' OR ', $department4levelsql) . " ) ";
			}
		}
		if (!empty($params['department5level'])) {
			$depart5level = explode(',', $params['department5level']);
			$department5levelsql = [];
			foreach ($depart5level as $department5level) {
				$department5levelsql[] = " concat('/',u.open_path,'/') LIKE :department5levelparam_{$department5level}";
				$params["department5levelparam_{$department5level}"] = '%/' . $department5level . '/%';
			}
			if (!empty($department5levelsql)) {
				$sql .= " AND ( " . implode(' OR ', $department5levelsql) . " ) ";
			}
		} 
		
		if (!empty($params['states'])) {
			$sql .= " AND u.open_states IN ({$params['states']}) ";
		}
		if (!empty($params['district'])) {
			$sql .= " AND u.open_district IN ({$params['district']}) ";
		}
		if (!empty($params['subdistrict'])) {
			$sql .= " AND u.open_subdistrict IN ({$params['subdistrict']}) ";
		}
		if (!empty($params['village'])) {
			$sql .= " AND u.open_village IN ({$params['village']}) ";
		}

		if (!empty($params['email'])) {
			$sql .= " AND u.id IN ({$params['email']})";
		}
		if (!empty($params['uname'])) {
			$sql .= " AND u.id IN ({$params['uname']})";
		}
		if (!empty($params['idnumber'])) {
			$sql .= " AND u.id IN ({$params['idnumber']})";
		}
		if (!empty($params['location'])) {

			$locations = explode(',', $params['location']);
			list($locationsql, $locationparams) = $this->db->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
			$params = array_merge($params, $locationparams);
			$sql .= " AND u.open_location {$locationsql} ";
		}
		if (!empty($params['hrmsrole'])) {

			$hrmsroles = explode(',', $params['hrmsrole']);
			list($hrmsrolesql, $hrmsroleparams) = $this->db->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
			$params = array_merge($params, $hrmsroleparams);
			$sql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
		}
		if (!empty($params['groups'])) {

			$sql .= " AND u.id IN (select cm.userid from {cohort_members} cm, {user} u where u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0 AND cm.cohortid IN ({$params['groups']}))";
		}
		$order = ' ORDER BY u.firstname ASC ';
		if ($perpage != -1) {
			// $order.="LIMIT $perpage";
		}
		if ($total == 0) {
			$users = $this->db->get_records_sql_menu($sql . $order, $params, $lastitem, $perpage);
		} else {
			$users = $this->db->count_records_sql($sql, $params);
		}

		return $users;
	}
	/*End of the function*/

	/*Function to called in the bulk users upload*/
	public function select_to_users_of_learninplan($planid, $userid, $params, $total = 0, $offset1 = -1, $perpage = -1, $lastitem = 0)
	{
		$users = $this->db->get_record('local_learningplan', array('id' => $planid));
		$us = $users->open_band;
	/* 	$array = explode(',', $us);
		$list = implode("','", $array); */
		$loginuser = $this->user;
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);
		$userpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql('u.open_path');
		$costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');
		if (!is_siteadmin()) {
			$siteadmin_sql = $costcenterpathconcatsql;
		} else {
			$siteadmin_sql = "";
		}
		if ($total == 0) {
			$sql = "SELECT  u.id,concat(u.firstname,' ',u.lastname,' ','(',u.idnumber,')') as fullname ";
		} else {
			$sql = "SELECT count(u.id) as total";
		}
		$sql .= " FROM {user} u WHERE u.id >2 AND u.suspended =0
								 AND u.deleted =0  $siteadmin_sql AND u.id not in ($loginuser->id) $userpathconcatsql";

	/* 	if ($lastitem != 0) {

			$sql .= " AND u.id > $lastitem ";
		} */
		$costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');
		if (is_siteadmin()) {
			$sql .= "";
		} else {
			$sql .= $costcenterpathconcatsql . " " . $userpathconcatsql;
		}


		if (!empty($params['organization'])) {
			$organizations = explode(',', $params['organization']);
			$orgsql = [];
			foreach($organizations AS $organisation){
				$organisationpath = $this->db->get_field("local_costcenter","path",array('id' => $organisation));
				$orgsql[] = " concat('/',u.open_path,'/') LIKE :organisationparam_{$organisation}";
				$params["organisationparam_{$organisation}"] = '%'.$organisationpath.'/%';
			}
			if(!empty($orgsql)){
				$sql .= " AND ( ".implode(' OR ', $orgsql)." ) ";
			}
		}
		if (!empty($params['department'])) {
			$departments = explode(',', $params['department']);
			$deptsql = [];
			foreach($departments AS $department){
				$departmentpath = $this->db->get_field("local_costcenter","path",array('id' => $department));
				$deptsql[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
				$params["departmentparam_{$department}"] = '%'.$departmentpath.'/%';
			}
			if(!empty($deptsql)){
				$sql .= " AND ( ".implode(' OR ', $deptsql)." ) ";
			}
		}
	   
		if (!empty($params['subdepartment'])) {
			$subdepartments = explode(',', $params['subdepartment']);
			$subdeptsql = [];
			foreach($subdepartments AS $subdepartment){
				$subdepartmentpath = $this->db->get_field("local_costcenter","path",array('id' => $subdepartment));
				$subdeptsql[] = " concat('/',u.open_path,'/') LIKE :subdepartmentparam_{$subdepartment}";
				$params["subdepartmentparam_{$subdepartment}"] = '%'.$subdepartmentpath.'/%';
			}
			if(!empty($subdeptsql)){
				$sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
			}
		}
		if (!empty($params['department4level'])) {
			$subdepartments = explode(',', $params['department4level']);
			$subdeptsql = [];
			foreach($subdepartments AS $department4level){
				$department4levelpath = $this->db->get_field("local_costcenter","path",array('id' => $department4level));
				$subdeptsql[] = " concat('/',u.open_path,'/') LIKE :department4levelparam_{$department4level}";
				$params["department4levelparam_{$department4level}"] = '%'.$department4levelpath.'%';
			}
			if(!empty($subdeptsql)){
				$sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
			}
		}
		if (!empty($params['department5level'])) {
			$subdepartments = explode(',', $params['department5level']);
			$subdeptsql = [];
			foreach($subdepartments AS $department5level){
				$department5levelpath = $this->db->get_field("local_costcenter","path",array('id' => $department5level));
				$subdeptsql[] = " concat('/',u.open_path,'/') LIKE :department5levelparam_{$department5level}";
				$params["department5levelparam_{$department5level}"] = '%'.$department5levelpath.'/%';
			}
			if(!empty($subdeptsql)){
				$sql .= " AND ( ".implode(' OR ', $subdeptsql)." ) ";
			}
		}
		if (!empty($params['states'])) {
			$sql .= " AND u.open_states IN ({$params['states']}) ";
		}
		if (!empty($params['district'])) {
			$sql .= " AND u.open_district IN ({$params['district']}) ";
		}
		if (!empty($params['subdistrict'])) {
			$sql .= " AND u.open_subdistrict IN ({$params['subdistrict']}) ";
		}
		if (!empty($params['village'])) {
			$sql .= " AND u.open_village IN ({$params['village']}) ";
		}

		if (!empty($params['email'])) {
			$sql .= " AND u.id IN ({$params['email']})";
		}
		if (!empty($params['uname'])) {
			$sql .= " AND u.id IN ({$params['uname']})";
		}
		if (!empty($params['idnumber'])) {
			$sql .= " AND u.id IN ({$params['idnumber']})";
		}
		if (!empty($params['location'])) {

			$locations = explode(',', $params['location']);
			list($locationsql, $locationparams) = $this->db->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
			$params = array_merge($params, $locationparams);
			$sql .= " AND u.open_location {$locationsql} ";
		}
		if (!empty($params['hrmsrole'])) {

			$hrmsroles = explode(',', $params['hrmsrole']);
			list($hrmsrolesql, $hrmsroleparams) = $this->db->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
			$params = array_merge($params, $hrmsroleparams);
			$sql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
		}
		if (!empty($params['groups'])) {

			$sql .= " AND u.id IN (select cm.userid from {cohort_members} cm, {user} u where u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0 AND cm.cohortid IN ({$params['groups']}))";
		}


		$sql .= " AND u.id not in(SELECT userid FROM {local_learningplan_user} WHERE planid=$planid)";

		$order = ' ORDER BY u.firstname ASC ';
		if ($perpage != -1) {
			// $order.="LIMIT $perpage";
			$limit = $perpage;
		}
		if ($total == 0) {
			$users = $this->db->get_records_sql_menu($sql . $order, $params, $lastitem, $perpage);
		} else {
			$users = $this->db->count_records_sql($sql, $params);
		}
		return $users;
	}
	/*End of the function*/


	/*Function to view the users and assign users*/
	public function learningplans_users_tab_content($planid, $curr_tab, $condition, $ajax)
	{
		global $CFG, $OUTPUT;
		if ($ajax == 0) {
			$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);
			$return = '';
			$return .= '<div class="tab-pane" id="plan_users" role="tabpanel">';
			if (has_capability('local/learningplan:assignhisusers', $categorycontext)) {
				$table = 'local_learningplan_courses';
				$conditions = array('planid' => $planid);
				$sort = 'id';
				$fields = 'id, courseid';
				$result = $this->db->get_records_menu($table, $conditions, $sort, $fields);
				$count = count($result);
				/*finally get the count of records in total courses*/
				$data = implode(',', $result);
				$return .= "<ul class='course_extended_menu_list learningplan'>
		                 <li>
								<div class='coursebackup course_extended_menu_itemcontainer'>
	                   <a id='extended_menu_syncusers' title='" . get_string('le_enrol_users', 'local_learningplan') . "' class='course_extended_menu_itemlink' href='" . $CFG->wwwroot . "/local/learningplan/lpusers_enroll.php?lpid=" . $planid . "'><i class='icon fa fa-user-plus fa-fw' aria-hidden='true' aria-label=''></i></a>
	              	</div>
	              </li></ul>";
			}
			$return .= $this->assigned_learningplans_users($planid, $ajax);
			$return .= '</div>';
		} else {
			$return = $this->assigned_learningplans_users($planid, $ajax);
		}
		return $return;
	}
	/*End of the function*/



	/*Function to view the requested users in learningplan*/
	public function learningplans_requested_users_content($planid, $curr_tab, $condition)
	{
		global $DB, $CFG, $OUTPUT, $PAGE;
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);

		$return = '';
		if ((has_capability('local/request:approverecord', $categorycontext) || is_siteadmin())) {
			$learningplan = $DB->get_records('local_request_records', array('compname' => 'learningplan', 'componentid' => $planid));
			$output = $PAGE->get_renderer('local_request');
			$component = 'learningplan';
			if ($learningplan) {
				$return = $output->render_requestview(false, $planid, $component);
				// $return = json_encode($return);
			} else {
				$return = '<div class="alert alert-info text-center">' . get_string('requestavail', 'local_classroom') . '</div>';
			}
		}
		return $return;
	}
	/*End of the function*/

	public function learningplans_assign_courses_form($planid, $condition)
	{
		global $DB;
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);
		$learningplan  = $DB->get_records('local_learningplan');
		$lpuserexist = $DB->record_exists('local_learningplan_user',array('planid'=>$planid));
		foreach ($learningplan as $plan)
			$departmentcount = isset($plan->department) ? count(array_filter(explode(',', $plan->department))) : 0;
		$subdepartmentcount = isset($plan->subdepartment) ? count(array_filter(explode(',', $plan->subdepartment))) : 0;
		$plan_name = $DB->get_field('local_learningplan', 'name', array('id' => $planid));
		$learningplan_lib = new lib;
		$userscount = $learningplan_lib->get_enrollable_users_count_to_learningplan($planid);
		$return = '';
		$add_learningplancourses = '';
		if(!$lpuserexist){
			$add_learningplancourses = '<ul class="course_extended_menu_list learningplan">
				<li>
				    <div class="course_extended_menu_itemcontainer">
				        <a title="' . get_string('assign_courses', 'local_learningplan') . '" class="course_extended_menu_itemlink" href="javascript:void(0);"
							onclick="(function(e){ require(\'local_learningplan/courseenrol\').init({selector:\'createcourseenrolmodal\', contextid:' . $categorycontext->id . ', planid:' . $planid . ', condition:\'manage\'}) })(event)">
								<i class="icon fa fa-plus" aria-hidden="true"></i>
						</a>
					</div>
				</li>
			</ul>';
		}


		if ($departmentcount > 1 && !(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext))) {
			$add_learningplancourses = '';
		}

		$return .= $add_learningplancourses;
		$return .= '<div class="assign_courses_container">';

		$courses = $learningplan_lib->learningplan_courses_list($planid);


		$return .= '</div>';

		return $return;
	}
	public function get_editand_publish_icons($planid)
	{
		global $DB, $CFG, $PAGE;
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);
		$learningplan = $DB->get_records('local_learningplan');

		foreach ($learningplan as $plan) {
			// $departmentcount = count(array_filter(explode(',',$plan->department)));
			// $subdepartmentcount = count(array_filter(explode(',',$plan->subdepartment)));
			$plan_name = $DB->get_field('local_learningplan', 'name', array('id' => $planid));
			$learningplan_lib = new lib;
			$userscount = $learningplan_lib->get_enrollable_users_count_to_learningplan($planid);


			$learningplaninfo['plan_name'] = $plan_name;
			$learningplaninfo['planid'] = $planid;
			$learningplaninfo['userscount'] = $userscount;
			$learningplaninfo['configpath'] = $CFG->wwwroot;
			$can_manage = has_capability('local/learningplan:manage', $categorycontext);
			$learningplaninfo['can_update'] = (is_siteadmin() || ($can_manage && has_capability('local/learningplan:update', $categorycontext)));
			if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext))) {
				$learningplaninfo['can_update'] = '';
			}

			$learningplaninfo['can_publish'] = (is_siteadmin() || ($can_manage && has_capability('local/learningplan:publishplan', $categorycontext)));
			if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext))) {
				$learningplaninfo['can_publish'] = '';
			}

			$learningplaninfo['can_enrolusers'] = (is_siteadmin() || ($can_manage && has_capability('local/learningplan:assignhisusers', $categorycontext)));

			$challenge_exist = \core_component::get_plugin_directory('local', 'challenge');
			if ($challenge_exist) {
				$enabled =  (int)get_config('', 'local_challenge_enable_challenge');
				if ($enabled) {
					$challenge_render = $PAGE->get_renderer('local_challenge');
					$element = $challenge_render->render_challenge_object('local_learningplan', $planid);
					$learningplaninfo['challenge_element'] = $element;
				} else {
					$learningplaninfo['challenge_element'] = false;
				}
			} else {
				$learningplaninfo['challenge_element'] = false;
			}
			$edit_publish_icons = $this->render_from_template('local_learningplan/learningplan_publish_edit', $learningplaninfo);
		}
		return $edit_publish_icons;
	}

	private function learningplans_assign_users_form($planid, $condition)
	{
		$sql = "SELECT userid, planid FROM {local_learningplan_user} WHERE planid = $planid";
		$existing_plan_users = $this->db->get_records_sql($sql);
		$return = '';
		$assign_button = '<a class="pull-right assigning " onclick="assign_users_form_toggle(' . $planid . ')" id="plan_assign_users_' . $planid . '">' . get_string('assign_users', 'local_learningplan') . '</a>';
		$return .= $assign_button;
		$return .= '<div class="assign_users_container">';
		$return .= '<form autocomplete="off" id="assign_users_' . $planid . '" action="assign_courses_users.php" method="post" class="mform">';
		$return .= '<fieldset class="hidden">
									<div>
										<div id="fitem_id_t_id[]" class="fitem fitem_fselect ">
											<div class="fitemtitle">
												<label for="id_u_id[]">Select users</label>
											</div>
											<div class="felement ftext">
												<select name="learning_plan_users[]" id="id_lpassignusers" size="10" multiple class="learningplan-assign-users">';

		$return .= "</select>
											</div>
										</div>
									</div>
								</fieldset>";
		$return .= '<input type="hidden" name="planid" value=' . $planid . ' />
					            <input type="hidden" name="condtion" value="' . $condition . '" />
								<input type="hidden" name="type" value="assign_users" />';
		$return .= '<fieldset class="hidden">
									<div>
										<div id="fitem_id_submitbutton" class="fitem fitem_actionbuttons fitem_fsubmit">
											<div class="felement fsubmit">
												<input type="submit" class="form-submit" value="Assign" />
											</div>
										</div>';
		$return .= '</div>
								</fieldset>
							</form>';
		$return .= '</div>';
		return $return;
	}
	/**Function to view the  course and functionality with the sortorder @param $planid=LEP_id**/
	public function assigned_learningplans_courses($planid)
	{
		global $DB,$CFG;
		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);

		$learningplan_lib = new lib();

		$includes = new \user_course_details;

		$courses = $learningplan_lib->get_learningplan_assigned_courses($planid);

		//$courses = lib::get_learningplan_assigned_courses($planid);

		$return = '';
		$return .= html_writer::start_tag('div', array('class' => 'col-md-9'));
		$return .= '<form class ="l_form" action="assign_courses_users.php" method="post">';

		if (empty($courses)) {
			$return .= html_writer::tag('div', get_string('nolearningplancourses', 'local_learningplan'), array('class' => 'alert alert-info text-center pull-left', 'style' => 'width:96%;padding-left:2%;padding-right:1%;'));
		} else {

			$table_data = array();
			/**To check the highest sortorder of courses below query written and to compare list of courses**/
			$sql = "SELECT id,sortorder FROM {local_learningplan_courses} WHERE planid = :planid ORDER BY sortorder DESC";
			$find = $this->db->get_records_sql($sql, array('planid' => $planid));
			/****End of the query****/

			/**Below query written to check the users assigned to LEP or NOT and Disable submit button**/
			$userscount = $this->db->get_records('local_learningplan_user', array('planid' => $planid));
			/*end of query*/

			/**The below query has been written taken count if we have submitted condition and later we added new course then submit should open**/
			$courses_zero_count = $this->db->get_records('local_learningplan_courses', array('planid' => $planid, 'nextsetoperator' => 0));
			/*end of query*/
			if ($userscount && (count($courses_zero_count) == 1 || count($courses_zero_count) == 0)) {
				$disbaled_button = "disabled";
			} else {
				$disbaled_button = "";
			}
			/*making list of course*/
			$i = 1;
			$lpcourse_data = '';
			foreach ($courses as $course) {
				if ($course->next == 'and') {
					$select = 'echo checked="checked"';
				} elseif ($course->next == 'or') {
					$select = '';
				}

				$startdiv = '<div class="lp_course_sortorder w-full pull-left" id="dat' . $course->id . '">';
				$enddiv = '<div>';
				$course_url = new \moodle_url('/course/view.php', array('id' => $course->id));
				$course_link = strlen($course->fullname) > 25 ? clean_text(substr($course->fullname, 0, 25)) . "..." : $course->fullname;
				$course_view_link = html_writer::link($course_url, $course_link, array('title' => $course->fullname));
				$course_summary_image_url = $includes->course_summary_files($course);

				$coursesummary = \local_costcenter\lib::strip_tags_custom(
					html_entity_decode($course->summary),
					array('overflowdiv' => false, 'noclean' => false, 'para' => false)
				);
				$course_summary = empty($coursesummary) ? get_string('coure_summary_not_provided', 'local_learningplan') : $coursesummary;

				$course_summary_string = strlen($course_summary) > 125 ? substr($course_summary, 0, 125) . "..." : $course_summary;

				$course_total_activities = $includes->total_course_activities($course->id);
				$course_total_activities_link = html_writer::link($course_url, $course_total_activities, array());

				$actions = '';
				/****actions like delete and move up and down****/
				$buttons = '';
				/****buttons are select box****/

				$unassign_url = '';
				$unassign_link = '';
				if (has_capability('local/learningplan:assigncourses', $categorycontext)) {

					$learningplans = $DB->get_records('local_learningplan');
					foreach ($learningplans as $learningplan)
						//$departmentcount = count(array_filter(explode(',', $learningplan->department)));

					if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext)) /*&& $departmentcount > 1*/) {
						$unassign_url = '';
						$unassign_link = '';
					} elseif ($disbaled_button == "") {

						$unassign_url = new \moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'unassigncourse' => $course->lepid));
						$unassign_link = html_writer::link(
							'javascript:void(0)',
							'<i class="icon fa fa-times fa-fw" aria-hidden="true" title="Un-assign" aria-label="Delete"></i>',
							array('class' => 'pull-right', 'id' => 'unassign_course_' . $course->lepid . '', 'onclick' => '(function(e){ require(\'local_learningplan/lpcreate\').unassignCourses({action:\'unassign_course\' , unassigncourseid:' . $course->lepid . ', planid:' . $planid . ', fullname:"' . $course->fullname . '" }) })(event)')
						);
					}




					if ($course->sortorder == 0) {
						/**condtion to check the sortorder and make arrows of up and down for the first record ot course**/

						if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext)) /*&& $departmentcount > 1*/) {
							$unassign_url1 = '';
							$unassign_link1 = '';
						} else {

							$unassign_url1 = new \moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'instance' => $course->lepid, 'order' => 'down'));
							$unassign_link1 = html_writer::link($unassign_url1, '<i class="icon fas fa-arrow-down" title="Move Down"></i>', array('class' => 'pull-right'));
						}

						if ($disbaled_button == "") {
							$actions .= $unassign_link1; /*Arrows down for first course*/
						/*condition for the select the dropdown if already selected*/
						/*Select box*/

							if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext)) /*&& $departmentcount > 1*/) {
								$buttons .= '';
							} else {
								$buttons .= '<span class="switch_type">
									<label class="switch">
										<input class="switch-input" type="checkbox" id="next_val' . $course->id . '" value="' . $course->id . '" "' . $select . '">
										<span class="switch-label" data-on="Man" data-off="Opt"></span>
											<span class="switch-handle"></span>
									</label>

									<input type="hidden" value="' . $course->lepid . '" id="courseid' . $course->lepid . '" name="row[]">
									<input type="hidden" value="' . $planid . '" name="plan">
								</span>';
							}
						}

						/*End of the select box*/
						$select = '';
					} elseif ($course->sortorder == isset($find->sortorder)) {
						/*condition to check the last course and make the up arrow*/

						if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext)) /*&& $departmentcount > 1*/) {
							$unassign_url2 = '';
							$unassign_link_up = '';
						} else {

							$unassign_url2 = new \moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'instance' => $course->lepid, 'order' => 'up'));
							$unassign_link_up = html_writer::link($unassign_url2, '<i class="icon fas fa-arrow-up" title="Move Up"></i>', array('class' => 'pull-right'));
						}
						if ($disbaled_button == "") {
							$actions .= $unassign_link_up;

							if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext)) /*&& $departmentcount > 1*/) {
								$buttons .= '';
							} else {
								$buttons .= '<span class="switch_type">
									<label class="switch">
										<input class="switch-input" type="checkbox" id="next_val' . $course->id . '" value="' . $course->id . '" "' . $select . '">
										<span class="switch-label" data-on="Man" data-off="Opt"></span>
											<span class="switch-handle"></span>
										</label>

									<input type="hidden" value="' . $course->lepid . '" id="courseid' . $course->lepid . '" name="row[]">
									<input type="hidden" value="' . $planid . '" name="plan">
								</span>';
							}
						}
					} else {
						
						/*Else condition Not for first and last record should have the both arrows*/
						if (!(is_siteadmin() || has_capability('local/learningplan:manage', $categorycontext)) /*&& $departmentcount > 1*/) {
							$unassign_url = '';
							$unassign_link1 = '';
							$unassign_link_down = '';
							$buttons .= '';
						} else {

							$unassign_url2 = new \moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'instance' => $course->lepid, 'order' => 'up'));
							$unassign_link1 = html_writer::link($unassign_url2, '<i class="icon fas fa-arrow-up" title="Move Up"></i>', array('class' => 'pull-right'));

							$unassign_url2 = new \moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'instance' => $course->lepid, 'order' => 'down'));
							$unassign_link_down = html_writer::link($unassign_url2, '<i class="icon fas fa-arrow-down" title="Move Down"></i>', array('class' => 'pull-right'));
							if ($disbaled_button == "") {
								$actions .= $unassign_link_down;
								$actions .= $unassign_link1;
							/*select box*/
								$buttons .= '<span class="switch_type">
									<label class="switch">
										<input class="switch-input" type="checkbox" id="next_val' . $course->id . '" value="' . $course->id . '" "' . $select . '">
										<span class="switch-label" data-on="Man" data-off="Opt"></span>
										<span class="switch-handle"></span>
									</label>

									<input type="hidden" value="' . $course->lepid . '" id="courseid' . $course->lepid . '" name="row[]">
								</span>';
							}
						}
						/*end of the select box*/
						$courseid_condition[] = $course->lepid;
						$select = '';
					}

					$confirmationmsg = get_string('unassign_courses_confirm', 'local_learningplan', $course);

					$actions .= $unassign_link;
				}


				$progress = $includes->user_course_completion_progress($course->id, $this->user->id);
				if (!$progress) {
					$progress = 0;
					$progress_bar_width = " min-width: 0px;";
				} else {
					$progress = round($progress);
					$progress_bar_width = "min-width: 0px;";
				}

				$enrolledusers = $this->db->get_records_menu('local_learningplan_user',  array('planid' => $planid), 'id', 'id, userid');
				if (!empty($enrolledusers)) {
					$course_completions = $this->db->get_records_sql_menu("SELECT id,userid  FROM {course_completions} WHERE course = $course->id AND timecompleted IS NOT NULL");

					$result = array_intersect($enrolledusers, $course_completions);
					$user_completions = round((count($result) / count($enrolledusers)) * 100);
				} else {
					$user_completions = 0;
				}
				$ctime ='';
				if($progress==100){
					$cmpltd_class = 'course_completed';
					$completeflag = true;
					$completedtime = $this->db->get_field('course_completions', 'timecompleted', array('course' => $course->id, 'userid' => $this->user->id));
					if($completedtime){
						$completed_date = \local_costcenter\lib::get_userdate("d/m/Y",$completedtime);
						$ctime = \local_costcenter\lib::get_userdate("h:i a", $completedtime);
					}else{
						$completed_date = '';
						$completeflag = false;
						$ctime ='';
					}

				}else{
				$cmpltd_class = '';
				$completed_date = '';
				$completeflag = false;
				}			
				if ($course->sortorder == 0) {/*Condtion to set the enable to first sortorder*/
					$disable_class1 = ' '; /*Empty has been sent to class*/
				}
				$userpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql('u.open_path');
				$totaluser_sql = "SELECT llu.planid,count(llu.userid) as data FROM {local_learningplan_user} as llu 
				JOIN {user} as u ON u.id=llu.userid 
				WHERE llu.planid = :planid AND u.deleted != :deleted $userpathconcatsql GROUP BY llu.planid ";
				$total_enroled_users = $this->db->get_record_sql($totaluser_sql, array('planid' => $planid, 'deleted' => 1));
				/*Count of the requested users to LEP*/
				$total_completed_users = $this->db->get_records_sql("SELECT id FROM {local_learningplan_user} WHERE completiondate IS NOT NULL
															 AND status = 1 AND planid = $planid");
				$cmpltd = array();
				foreach ($total_completed_users as $completed_users) {
					$cmpltd[] = $completed_users->id;
				}

				$disable_class1 = '';
				$lpcourses_context['disable_class1'] = $disable_class1;
				$lpcourses_context['courseid'] = $course->id;
				$lpcourses_context['course_summary_image_url'] = $course_summary_image_url;
				$lpcourses_context['course_summary_string'] = $course_summary_string;
				$lpcourses_context['course_view_link'] = $course_view_link;
				$lpcourses_context['course_name'] = $course->fullname;
				$lpcourses_context['numbercount'] = $i++;
				$lpcourses_context['buttons'] = $buttons;
				$lpcourses_context['actions'] = $actions;
				$lpcourses_context['submitbuttons'] = isset($submitbuttons);
				$lpcourses_context['progress'] = $user_completions;
				$lpcourses_context['date'] = $completed_date;
				$lpcourses_context['cmpltd_class'] = $cmpltd_class;
				$lpcourses_context['completeflag'] = $completeflag;
				$lpcourses_context['ctime'] = $ctime;
				$lpcourses_context['total_enroled_users'] = $total_enroled_users->data;
				$lpcourses_context['cmpltd'] =count($cmpltd);
				
				$openpath = $this->db->get_field('local_learningplan', 'open_path', array('id' => $planid));
				list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/", $openpath);
				$planorg = $this->db->get_field('local_costcenter', 'fullname', array('id' => $org));
				$plandpt = $this->db->get_field('local_costcenter', 'fullname', array('id' => $ctr));
				$plansubdpt = $this->db->get_field('local_costcenter', 'fullname', array('id' => $bu));
				$plancu = $this->db->get_field('local_costcenter', 'fullname', array('id' => $cu));
				$planterritory = $this->db->get_field('local_costcenter', 'fullname', array('id' => $territory));
				$total_assigned_course = $this->db->count_records('local_learningplan_courses', array('planid' => $planid));

				$total_mandatory_course = $this->db->get_records_sql("SELECT id FROM {local_learningplan_courses} WHERE planid = $planid
													 AND nextsetoperator = 'and'");
				$mandatory = array();
				foreach ($total_mandatory_course as $total_mandatory) {
					$mandatory[] = $total_mandatory->id;
				}

				$total_optional_course = $this->db->get_records_sql("SELECT id FROM {local_learningplan_courses} WHERE planid = $planid
													 AND nextsetoperator = 'or'");
				$optional = array();
				foreach ($total_optional_course as $total_optional) {
					$optional[] = $total_optional->id;
				}
				$ratings_exist = \core_component::get_plugin_directory('local', 'ratings');
		if ($ratings_exist) {
			require_once($CFG->dirroot . '/local/ratings/lib.php');
			$avgratings = get_rating($course->id, 'local_courses');
            $avgrating = $avgratings->avg;
		} else {
			$avgrating  = '';
		}
		
				$plan = $this->db->get_record('local_learningplan', array('id' => $planid));
				if ($plan->open_level > 0) {
					$plan->planlevel = $DB->get_field('local_course_levels', 'name', array('id' => $plan->open_level));
				} else {
					$plan->planlevel = 'N/A';
				}
				if ($plan->open_skill > 0) {
					$plan->planskill = $DB->get_field('local_skill', 'name', array('id' => $plan->open_level));
				} else {
					$plan->planskill = 'N/A';
				}	
				
				$lpcourses_context['total_assigned_course'] = $total_assigned_course;
				$lpcourses_context['mandatory'] = count($mandatory);
				$lpcourses_context['optional'] = count($optional);
				$lpcourses_context['avgrating'] = $avgrating;
				$lpcourses_context['plan_learningplancode'] = $plan->shortname;
				$lpcourses_context['planskill'] = $plan->planskill;
				$lpcourses_context['planlevel'] = $plan->planlevel;
				$lpcourses_context['planorg'] = $planorg ? $planorg : 'All';
				$lpcourses_context['plandpt'] = $plandpt ? $plandpt : 'All';
				$lpcourses_context['plansubdpt'] = $plansubdpt ? $plansubdpt : 'All';
				$lpcourses_context['plancu'] = $plancu ? $plancu : 'All';
				$lpcourses_context['planterritory'] = $planterritory ? $planterritory : 'All';
				$lpcourse_data .= $this->render_from_template('local_learningplan/courestab_content', $lpcourses_context);
				$lpcourse_data .= html_writer::script("$('#next_val" . $course->id . "').click(function() {
											var checked = $(this).is(':checked');
											
										if(checked){
											   var checkbox_value = '';
											   var plan=$planid;
											   var value='and';
											  checkbox_value = $(this).val();
											 
										}else{
										    var plan=$planid;
											var checkbox_value = '';
											 var value='or';
											checkbox_value = $(this).val();
										}
											$.ajax({
											type: 'POST',
											url: M.cfg.wwwroot + '/local/learningplan/ajax.php?course='+checkbox_value+'&planid='+plan+'&value='+value,
											data: { checked : checked },
											success: function(data) {
										
											},
											error: function() {
											},
											complete: function() {
										
											}
											});
										});
										");
			}

			$return .= $lpcourse_data;
			$return .= '</form>';
			$return .= html_writer::end_tag('div');
			$return .= $this->render_from_template('local_learningplan/rightcontainer', $lpcourses_context);
		}


		return $return;
	}
	/******End of the function of the which has sortorder and condition for the courses*******/
	public function assigned_learningplans_users($planid, $ajax)
	{
		global $OUTPUT, $DB;

		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);

		$core_component = new \core_component();
		$certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');
		if ($certificate_plugin_exist) {
			$certid = $DB->get_field('local_learningplan', 'certificateid', array('id' => $planid));
		} else {
			$certid = false;
		}
		if ($ajax == 0) {
			$check = $DB->record_exists('local_learningplan_user', array('planid' => $planid));
			if ($check) {
				$table = new html_table();
				$table->id = 'learning_plan_users';
				$head = array(
					get_string('username', 'local_learningplan'),
					get_string('employee_id', 'local_learningplan'),
					get_string('reportingto', 'local_learningplan'),
					get_string('start_date', 'local_learningplan'),
					get_string('completion_date', 'local_learningplan'),
					get_string('learning_plan_status', 'local_learningplan')
				);
				if ($certid) {
					$head[] = get_string('certificate', 'tool_certificate');
				}
				$table->head = $head;

				if (has_capability('local/learningplan:assignhisusers', $categorycontext)) {
					/*$table->head[] = get_string('learning_plan_actions', 'local_learningplan');*/
				}
				$table->data = array();

				$return = html_writer::table($table);
			} else {
				$return = html_writer::tag('div', get_string('nolearningplanusers', 'local_learningplan'), array('class' => 'alert alert-info text-center pull-left', 'style' => 'width:96%;padding-left:2%;padding-right:1%;'));
			}
		} else {
			$requestData = $_REQUEST;

			$learningplan_lib = new lib();
			$users = $learningplan_lib->get_learningplan_assigned_users($planid, $requestData);

			$return = '';

			$table_data = array();

			foreach ($users as $user) {
				$course_url = new \moodle_url('/local/learningplan/local_learningplan_courses.php', array('planid' => $planid, 'id' => $user->id));
				$courses_link = html_writer::link($course_url, 'View more', array('id' => $user->id));
				if ($user->status == 1) {
					$completed = "Completed";
				}
				$userpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql('u.open_path');
				$user_url = new \moodle_url('/local/users/profile.php', array('id' => $user->id));
				$user_profile_link = html_writer::link($user_url, fullname($user), array());
				$employee_id = empty($user->open_employeeid) ? 'N/A' : $user->open_employeeid;
				$supervisor = $DB->get_field('user', 'concat(firstname," ",lastname)', array('id' => $user->open_supervisorid));
				$supervisorname = empty($supervisor) ? 'N/A' : $supervisor;
				$start_date = empty($user->timecreated) ? 'N/A' : \local_costcenter\lib::get_userdate("d/m/Y H:i", $user->timecreated);
				$completion_date = empty($user->completiondate) ? 'N/A' : '<i class="fa fa-calendar pr-10" aria-hidden="true"></i>' . \local_costcenter\lib::get_userdate("d/m/Y H:i", $user->completiondate);
				$status = empty($user->status) ? 'Not Completed' : $completed;

				// if (has_capability('local/learningplan:assignhisusers', $categorycontext)) {
				// 	$unassign_url = new \moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'unassignuser' => $user->id));
				// 	$unassign_link = html_writer::link($unassign_url,
				// 									html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/delete'), 'class' => 'icon', 'title' => 'Unassign'))
				// 									, array('id' => 'unassign_user_'.$user->id.''));
				// 	$unassign_link = html_writer::link('javascript:void(0)',html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/delete'), 'class' => 'icon', 'title' => 'Unassign')), array('id' => 'unassign_user_'.$user->id.'', 'onclick' => '(function(e){ require(\'local_learningplan/lpcreate\').unassignUsers({action:\'unassign_user\' , unassignuserid:'.$user->id.', planid:'.$planid.', fullname:"'.fullname($user).'" }) })(event)'));

				// 	if($completed=="Completed..."." ".$courses_link){
				// 		$unassign_link1 = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/check'), 'class' => 'icon', 'title' => 'Completed'));
				// 		$actions = $unassign_link;
				// 	}
				// 	$confirmationmsg = get_string('unassign_users_confirm','local_learningplan', $user);

				// 	$this->page->requires->event_handler("#unassign_user_".$user->id, 'click', 'M.util.moodle_show_user_confirm_dialog',
				// 										array(
				// 										'message' => $confirmationmsg,
				// 										'callbackargs' => array('planid' =>$planid, 'userid' =>$user->id)
				// 									));
				// 	/*This query amd condition is used to check the completed users should not be deleted*/
				// 	$check=$this->db->get_record('local_learningplan_user',array('userid'=>$user->id,'status'=>1,'planid'=>$planid));
				// 	if($check){
				// 	$actions = $unassign_link1;
				// 	}else{
				// 	$actions = $unassign_link;
				// 	}

				// 	$table_header = get_string('learning_plan_actions', 'local_learningplan');
				// }else{
				// 	$actions = '';
				// 	$table_header = '';
				// }

				$table_row = array();
				$table_row[] = $user_profile_link;
				$table_row[] = $employee_id;
				$table_row[] = $supervisorname;
				$table_row[] = '<i class="fa fa-calendar pr-10" aria-hidden="true"></i>' . $start_date;
				$table_row[] = $completion_date;
				$table_row[] = $status;
				// if (has_capability('local/learningplan:assignhisusers', $categorycontext)) {
				// 	if(empty($actions)){
				// 		$actions="N/A";
				// 	}
				// 	$table_row[] = $actions;
				// }
				$icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
				if ($user->completiondate) {
					//                        mallikarjun added to download default certificate 
					//						$array = array('ctid'=>$certid, 'mtype'=>'learningplan','mid'=>$planid,'uid'=>$user->id);
					//						$url = new moodle_url('/local/certificates/view.php',$array);
					$certcode = $DB->get_field('tool_certificate_issues', 'code', array('moduleid' => $planid, 'userid' => $user->id, 'moduletype' => 'learningplan'));
					$array = array('code' => $certcode);
					$url = new moodle_url('/admin/tool/certificate/view.php', $array);
					$downloadlink = html_writer::link($url, $icon, array('title' => get_string('download_certificate', 'tool_certificate')));
				} else {
					$downloadlink = get_string('notassigned', 'local_classroom');
				}
				$table_row[] = $downloadlink;

				$table_data[] = $table_row;
			}
			$sql = "SELECT count(lu.id) as total FROM {local_learningplan_user} as lu JOIN {user} u ON u.id = lu.userid WHERE lu.planid = $planid AND u.deleted=0 AND u.suspended=0 $userpathconcatsql";
			if ($requestData['search']['value'] != "") {
				$sql .= " and ((CONCAT(u.firstname, ' ',u.lastname) LIKE '%" . $requestData['search']['value'] . "%'))";
			}
			$iTotal = $DB->get_field_sql($sql);
			$iFilteredTotal = $iTotal;  // when there is no search parameter then total number rows = total number filtered rows.
			$return = array(
				"sEcho" => intval(isset($requestData['sEcho'])),
				"iTotalRecords" => $iTotal,
				"iTotalDisplayRecords" => $iFilteredTotal,
				"aaData" => $table_data
			);
		}
		return $return;
	}

	public function assigned_learningplans_courses_employee_view($planid, $userid, $condition)
	{
		global $CFG, $DB;
		require_once($CFG->dirroot . '/local/learningplan/lib.php');
		if (file_exists($CFG->dirroot . '/local/includes.php')) {
			require_once($CFG->dirroot . '/local/includes.php');
		}

		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);


		$learningplan_lib = new lib();
		$includes = new user_course_details;

		$courses = lib::get_learningplan_assigned_courses($planid);
		$return = '';
		if (empty($courses)) {
			$return .= html_writer::tag('div', get_string('nolearningplancourses', 'local_learningplan'), array('class' => 'alert alert-info text-center pull-left', 'style' => 'width:96%;padding-left:2%;padding-right:1%;'));
		} else {
			$table_data = array();
			foreach ($courses as $course) {
				/**************To show course completed or not********/
				$sql = "select id from {course_completions} as cc where userid=" . $this->user->id . " and course=" . $course->id . " and timecompleted!=''";

				$completed = $this->db->get_record_sql($sql);

				$course_url = new moodle_url('/course/view.php', array('id' => $course->id));
				$course_view_link = html_writer::link($course_url, $course->fullname, array());
				$course_summary_image_url = $includes->course_summary_files($course);
				$course_summary = empty($course->objective) ? get_string('coure_summary_not_provided', 'local_learningplan') : $course->summary;
				$course_objective = empty($course->objective) ? get_string('coure_objective_not_provided', 'local_learningplan') : $course->objective;
				$course_total_activities = $includes->total_course_activities($course->id);
				$course_total_activities_link = html_writer::link($course_url, $course_total_activities, array());
				$course_completed_activities = $includes->user_course_completed_activities($course->id, $userid);
				$course_completed_activities_link = html_writer::link($course_url, $course_completed_activities, array());
				$course_pending_activities = $course_total_activities - $course_completed_activities;
				$course_pending_activities_link = html_writer::link($course_url, $course_pending_activities, array());

				$actions = '';
				$buttons = '';
				/*Select box*/
				if ($course->next == 'or') {
					$select = 'selected';
				} else {
					$select = '';
				}/*condition for the select the dropdown if already selected*/
				/*Select box*/
				if ($course->next == 'or' || $course->next == 'and') {

					if ($course->next == 'and') {
						$buttons .= '<h4 class="course_sort_status"><span class="label label-default mandatory-course" >' . get_string('mandatory', 'local_learningplan') . '</span></h4>';
					} elseif ($course->next == 'or') {
						$buttons .= '<h4 class="course_sort_status"><span class="label label-default optional-course" >' . get_string('optional') . '</span></h4>';
					}
				}
				/*End of the select box*/
				if (has_capability('local/learningplan:assigncourses', $categorycontext)) {
					if ($condition == 'view') {
					} else {

						$unassign_url = new moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'unassigncourse' => $course->id));
						$unassign_link = html_writer::link(
							$unassign_url,
							html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/delete'), 'class' => 'icon', 'title' => 'Unassign')),
							array(
								'class' => 'pull-right',
								'id' => 'unassign_course_' . $course->id . ''
							)
						);
						$confirmationmsg = get_string('unassign_courses_confirm', 'local_learningplan', $course);

						$this->page->requires->event_handler(
							"#unassign_course_" . $course->id,
							'click',
							'M.util.moodle_show_course_confirm_dialog',
							array(
								'message' => $confirmationmsg,
								'callbackargs' => array('planid' => $planid, 'courseid' => $course->id)
							)
						);
						$actions = $unassign_link;
					}
				}




				$table_row = array();
				$course_data = '';
				if ($course->sortorder == 0) {/*Condtion to set the enable to first sortorder*/
					$disable_class1 = ' '; /*Empty has been sent to class*/
				}

				$course_data .= '<div class="course_complete_info row-fluid pull-left ' . $disable_class1 . '" id="course_info_' . $course->id . '">';
				$course_data .= '<h4>' . $course_view_link . $actions . '' . $buttons . '</h4>';
				if ($course->sortorder !== '') {/*Condition to check the sortorder and disable the course */

					/**** Function to get the all the course details like the nextsetoperator,sortorder
					@param planid,sortorder,courseid of the record
					 ****/
					$disable_class = $learningplan_lib->get_previous_course_status($planid, $course->sortorder, $course->id);
					$find_completion = $learningplan_lib->get_completed_lep_users($course->id, $planid);


					if ($disable_class->nextsetoperator != '') {/*condition to check not empty*/

						if ($disable_class->nextsetoperator == 'and' && $find_completion == '') {/*Condition to check the nextsetoperator*/
							$restricted = $DB->get_field('local_learningplan', 'lpsequence', array('id' => $planid));

							if ($restricted) {
								if ($course->sortorder >= $disable_class->sortorder) {/*Condition to cehck the sortorder and make all the disable*/
									$disable_class1 = 'course_disabled';
								}
							}
						}
					}
				}
				/* End of the function and condition By Ravi_369*/

				$course_data .= '<div class="course_image_comtainer pull-left span3 desktop-first-column">
										<img class="learningplan_course_image" src="' . $course_summary_image_url . '" title="' . $course->fullname . '"/>
									</div>';
				$course_data .= '<div class="course_data_container pull-left span5 desktop-first-column">';
				$course_data .= '<div class="course_summary">';
				$course_data .= '<div class="clearfix">' . $course_summary . '</div>';
				$course_data .= '</div>';
				$course_data .= '</div>';
				$course_data .= '<div class="course_data_container pull-right col-md-4 desktop-first-column">';
				$course_data .= '<div class="course_activity_details text-right">';
				$course_data .= '<div class="row-fluid"><span style="font-size:18px;line-height:30px;">' . get_string('activities_to_complete', 'local_learningplan') . ' : </span><span style="font-size:25px;">' . $course_total_activities_link . '</span></div>';
				$course_data .= '<div class="row-fluid"><span style="font-size:18px;line-height:30px;">' . get_string('completed_activities', 'local_learningplan') . ' : </span><span style="font-size:25px;">' . $course_completed_activities_link . '</span></div>';
				$course_data .= '<div class="row-fluid"><span style="font-size:18px;line-height:30px;">' . get_string('pending_activities', 'local_learningplan') . ' : </span><span style="font-size:25px;">' . $course_pending_activities_link . '</span></div>';
				$course_data .= '</div>';

				/********LAUNCH button for every courses to enrol********/
				/*First check the enrolment method*/
				$check_course_enrol = $this->db->get_field('enrol', 'id', array('courseid' => $course->id, 'enrol' => 'learningplan'));
				/***Then check the userid***/
				$find_user = $this->db->get_field('user_enrolments', 'id', array('enrolid' => $check_course_enrol, 'userid' => $this->user->id));

				if (!$find_user) {/*Condition to check the user enroled or not*/
					$plan_url = new moodle_url('/local/learningplan/index.php', array('courseid' => $course->id, 'planid' => $planid, 'userid' => $this->user->id));
					$detail = html_writer::link($plan_url, 'Launch', array('class' => 'launch'));
				} else {/*if already enroled then show enroled */
					if (!empty($completed)) {
						$plan_url = "#";
						$detail = html_writer::link($plan_url, 'Completed', array('class' => 'launch'));
					} else {
						$plan_url = "#";
						$detail = html_writer::link($plan_url, 'Enrolled', array('class' => 'launch'));
					}
				}
				$course_data .= $cpmpleted_buttons;
				$course_data .= $detail;
				$course_data .= '</div>';
				$course_data .= '</div>';


				$table_row[] = $course_data;
				$table_data[] = $table_row;
			}
			$table = new html_table();
			$table->head = array('');
			$table->id = 'learning_plan_courses';
			$table->data = $table_data;
			$return .= html_writer::table($table);
			$return .= html_writer::script('$(document).ready(function(){
												//$("table#learning_plan_courses").dataTable({
													//language: {
													//	"paginate": {
													//		"next": ">",
													//		"previous": "<"
													//	  }
													//}
												//	"iDisplayLength": 3,
												//	"aLengthMenu": [[3, 10, 25, 50, -1], [3, 10, 25, 50, "All"]]
												//});
												//$("table#learning_plan_courses thead").css("display" , "none");
										   });');
		}

		return $return;
	}
	public function assigned_learningplans_courses_browse_employee_view($planid, $userid, $condition)
	{
		if (file_exists($CFG->dirroot . '/local/includes.php')) {
			require_once($CFG->dirroot . '/local/includes.php');
		}

		$categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context($planid);

		$learningplan_lib = new local_learningplan\lib\lib();
		$includes = new user_course_details;

		$courses = lib::get_learningplan_assigned_courses($planid);

		$return = '';
		//$return .= html_writer::tag('h3', get_string('assigned_courses', 'local_learningplan'), array());
		if (empty($courses)) {
			$return .= html_writer::tag('div', get_string('nolearningplancourses', 'local_learningplan'), array('class' => 'alert alert-info text-center pull-left', 'style' => 'width:96%;padding-left:2%;padding-right:1%;'));
		} else {
			$table_data = array();
			/**********To disable the links before enrol to plan**********/
			$check = $this->db->get_record('local_learningplan_user', array('userid' => $this->user->id, 'planid' => $planid));
			/*End of query*/
			foreach ($courses as $course) {

				if ($check) {
					$course_url = new moodle_url('/course/view.php', array('id' => $course->id));
				} else {
					$course_url = "#";
				}

				$course_view_link = html_writer::link($course_url, $course->fullname, array());
				$course_summary_image_url = $includes->course_summary_files($course);
				$course_summary = empty($course->objective) ? get_string('coure_summary_not_provided', 'local_learningplan') : \local_costcenter\lib::strip_tags_custom(html_entity_decode($course->summary), array('overflowdiv' => false, 'noclean' => false, 'para' => false));
				$course_objective = empty($course->objective) ? get_string('coure_objective_not_provided', 'local_learningplan') : $course->objective;
				$course_total_activities = $includes->total_course_activities($course->id);
				$course_total_activities_link = html_writer::link($course_url, $course_total_activities, array());
				$course_completed_activities = $includes->user_course_completed_activities($course->id, $userid);
				$course_completed_activities_link = html_writer::link($course_url, $course_completed_activities, array());
				$course_pending_activities = $course_total_activities - $course_completed_activities;
				$course_pending_activities_link = html_writer::link($course_url, $course_pending_activities, array());

				$actions = '';
				$buttons = '';
				/*Select box*/
				if ($course->next == 'or') {
					$select = 'selected';
				} else {
					$select = '';
				}
				/***condition for the select the dropdown if already selected***/

				if ($course->next == 'or' || $course->next == 'and') {

					if ($course->next == 'and') {
						$buttons .= '<h4 class="course_sort_status"><span class="label label-default mandatory-course" >' . get_string('mandatory', 'local_learningplan') . '</span></h4>';
					} elseif ($course->next == 'or') {
						$buttons .= '<h4 class="course_sort_status"><span class="label label-default optional-course" >' . get_string('optional') . '</span></h4>';
					}
				}
				/*End of the select box*/
				if (has_capability('local/learningplan:assigncourses', $categorycontext)) {
					if ($condition == 'view') {
					} else {

						$unassign_url = new moodle_url('/local/learningplan/assign_courses_users.php', array('planid' => $planid, 'unassigncourse' => $course->id));
						$unassign_link = html_writer::link(
							$unassign_url,
							html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/delete'), 'class' => 'icon', 'title' => 'Unassign')),
							array(
								'class' => 'pull-right',
								'id' => 'unassign_course_' . $course->id . ''
							)
						);
						$confirmationmsg = get_string('unassign_courses_confirm', 'local_learningplan', $course);

						$this->page->requires->event_handler(
							"#unassign_course_" . $course->id,
							'click',
							'M.util.moodle_show_course_confirm_dialog',
							array(
								'message' => $confirmationmsg,
								'callbackargs' => array('planid' => $planid, 'courseid' => $course->id)
							)
						);
						$actions = $unassign_link;
					}
				}




				$table_row = array();
				$course_data = '';
				if ($course->sortorder == 0) {/*Condtion to set the enable to first sortorder*/
					$disable_class1 = ' '; /*Empty has been sent to class*/
				}

				$course_data .= '<div class="course_complete_info row-fluid pull-left ' . $disable_class1 . '" id="course_info_' . $course->id . '">';
				$course_data .= '<h4>' . $course_view_link . $actions . '' . $buttons . '</h4>';

				if ($course->sortorder !== '') {/*Condition to check the sortorder and disable the course */

					/**** Function to get the all the course details like the nextsetoperator,sortorder
					@param planid,sortorder,courseid of the record
					 ****/
					$disable_class = $learningplan_lib->get_previous_course_status($planid, $course->sortorder, $course->id);
					$find_completion = $learningplan_lib->get_completed_lep_users($course->id, $planid);



					if ($disable_class->nextsetoperator != '') {/*condition to check not empty*/

						if ($disable_class->nextsetoperator == 'and' && $find_completion == '') {/*Condition to check the nextsetoperator*/

							$restricted = $DB->get_field('local_learningplan', 'lpsequence', array('id' => $planid));
							if ($restricted) {
								if ($course->sortorder >= $disable_class->sortorder) {/*Condition to cehck the sortorder and make all the disable*/
									$disable_class1 = 'course_disabled';
								}
							}
						} else {
						}
					}
				}
				/* End of the function and condition By Ravi_369*/

				$course_data .= '<div class="course_image_comtainer pull-left span3 desktop-first-column">
										<img class="learningplan_course_image" src="' . $course_summary_image_url . '" title="' . $course->fullname . '"/>
									</div>';
				$course_data .= '<div class="course_data_container pull-left span5 desktop-first-column">';
				$course_data .= '<div class="course_summary">';
				$course_data .= '<div class="clearfix">' . $course_summary . '</div>';
				$course_data .= '</div>';
				$course_data .= '</div>';
				$course_data .= '<div class="course_data_container pull-right col-md-4 desktop-first-column">';
				$course_data .= '<div class="course_activity_details text-right">';
				$course_data .= '<div class="row-fluid"><span style="font-size:18px;line-height:30px;">' . get_string('activities_to_complete', 'local_learningplan') . ' : </span><span style="font-size:25px;">' . $course_total_activities_link . '</span></div>';
				$course_data .= '<div class="row-fluid"><span style="font-size:18px;line-height:30px;">' . get_string('completed_activities', 'local_learningplan') . ': </span><span style="font-size:25px;">' . $course_completed_activities_link . '</span></div>';
				$course_data .= '<div class="row-fluid"><span style="font-size:18px;line-height:30px;">' . get_string('pending_activities', 'local_learningplan') . ' : </span><span style="font-size:25px;">' . $course_pending_activities_link . '</span></div>';
				$course_data .= '</div>';

				$course_data .= $detail;
				$course_data .= '</div>';
				$course_data .= '</div>';


				$table_row[] = $course_data;
				$table_data[] = $table_row;
			}

			$table = new html_table();
			$table->head = array('');
			$table->id = 'learning_plan_courses';
			$table->data = $table_data;
			$return .= html_writer::table($table);
			$return .= html_writer::script('$(document).ready(function(){
												//$("table#learning_plan_courses").dataTable({
													//language: {
													//	"paginate": {
													//		"next": ">",
													//		"previous": "<"
													//	  }
													//}
												//	"iDisplayLength": 3,
												//	"aLengthMenu": [[3, 10, 25, 50, -1], [3, 10, 25, 50, "All"]]
												//});
												//$("table#learning_plan_courses thead").css("display" , "none");
										   });');
		}


		return $return;
	}

	public function learningplaninfo_for_employee($planid)
	{
		global $PAGE, $DB, $CFG, $USER;

		$learningplan_lib = new lib();
		$includeslib = new \user_course_details();
		$learningplan_classes_lib = new lib();

		$lplan = $this->db->get_record('local_learningplan', array('id' => $planid));

		// $lptype = $lplan->learning_type == 1 ? 'Core Courses' : 'Elective Courses';
		/*if($lplan->learning_type == 1){
			$lptype = 'Core Courses';
		}elseif($lplan->learning_type == 2){
			$lptype = 'Elective Courses';
		}*/
		$lpapproval = $lplan->approvalreqd == 1 ? get_string('yes') : get_string('no');

		$lpimgurl = $learningplan_classes_lib->get_learningplansummaryfile($planid);

		$mandatarycourses_count = $learningplan_classes_lib->learningplancourses_count($planid, 'and');
		$optionalcourses_count = $learningplan_classes_lib->learningplancourses_count($planid, 'or');
		$lplanassignedcourses = (new lib)->get_learningplan_assigned_courses($planid);

		// $catalogrenderer = $this->page->get_renderer('local_catalog');
		$description = \local_costcenter\lib::strip_tags_custom(html_entity_decode($lplan->description), array('overflowdiv' => false, 'noclean' => false, 'para' => false));
		$description_string = strlen($description) > 220 ? clean_text(substr($description, 0, 220)) . "..." : $description;

		$lpinfo = '';
		$condition = "view";

		/***********The query Check Whether user enrolled to LEP or NOT**********/
		$plan_record = $this->db->get_record('local_learningplan', array('id' => $planid));
		$sql = "select id from {local_learningplan_user} where planid=$planid and userid=" . $this->user->id . "";
		$check = $this->db->get_record_sql($sql);
		/*End of Query*/

		/**The Below query is check the approval status for the LOGIN USERS on the his LEP**/
		$check_approvalstatus = $this->db->get_record('local_learningplan_approval', array('planid' => $plan_record->id, 'userid' => $this->user->id));
		if ($check) {
			/**condition to check user already enrolled to the LEP If Enroled he get option enrolled **/
			$approvalstatus = isset($check_approvalstatus->approvestatus);
			if ($approvalstatus == 1) {
				$back_url = "#";
			} else {
				$back_url = "#";
			}
		} else {
			/****Else he has 4 option like the Send Request or Waiting or Rejected or Enroled****/

			if (!is_siteadmin()) {

				if ($condition != 'manage') {
					/*******condition to check the manage page or browse page******/

					if ($plan_record->approvalreqd == 1  && (!empty($check_approvalstatus)))
					/***** If user has LEP with approve with 1 means request yes and empty not check approval status means he has sent request******/
					{

						$check_users = $learningplan_lib->check_courses_assigned_target_audience($this->user->id, $plan_record->id);
						/****The above Function is to check the user is present in the target audience or not***/

						if ($check_users == 1) {/*if there then he will be shown the options*/

							$check_approvalstatus = $this->db->get_record('local_learningplan_approval', array('planid' => $plan_record->id, 'userid' => $this->user->id));

							if ($check_approvalstatus->approvestatus == 0 && !empty($check_approvalstatus)) {
								$back_url = "#";
							} elseif ($check_approvalstatus->approvestatus == 2 && !empty($check_approvalstatus)) {
								$back_url = "#";
							}

							if (empty($check_approvalstatus)) {

								$back_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $plan_record->id, 'enrolid' => $plan_record->id));
								$notify = new stdClass();
								$notify->name = $plan_record->name;
								// $PAGE->requires->event_handler("#enroll1",
								// 'click', 'M.util.bajaj_show_confirm_dialog', array('message' => get_string('enroll_notify','local_learningplan',$notify),
								// 		 'callbackargs' => array('confirmdelete' =>$plan_record->id)));
							}
						}
					} else if (($plan_record->approvalreqd == 1) && (empty($check_approvalstatus))) {
						$check_users = $learningplan_lib->check_courses_assigned_target_audience($this->user->id, $plan_record->id);

						// if($check_users==1){
						// 	$back_url = new moodle_url('/local/learningplan/index.php', array('approval' => $plan_record->id));	
						// 	$approve=  html_writer::link('Send Request', array('class' => 'pull-right enrol_to_plan nourl','id'=>'request'));
						// 	$notify_info = new stdClass();
						// 	$notify_info->name = $plan_record->name;
						// 	$PAGE->requires->event_handler("#request",
						// 	'click', 'M.util.bajaj_show_confirm_dialog', array('message' => get_string('delete_notify','local_learningplan',$notify_info),
						// 			 'callbackargs' => array('confirmdelete' =>$plan_record->id)));

						// }
					} else if ($plan_record->approvalreqd == 0  && (empty($check_approvalstatus))) {

						$back_url = new moodle_url('/local/learningplan/plan_view.php', array('id' => $plan_record->id, 'enrolid' => $plan_record->id));
						$notify = new stdClass();
						$notify->name = $plan_record->name;
						// $PAGE->requires->event_handler("#enroll",
						// 'click', 'M.util.bajaj_show_confirm_dialog', array('message' => get_string('enroll_notify','local_learningplan',$notify),
						// 		 'callbackargs' => array('confirmdelete' =>$plan_record->id)));
					}
				}
			}
		}
		/** End of condtion **/
		if ($lplan->learning_type == 1) {
			$plan_type = get_string('core_courses', 'local_learningplan');
		} elseif ($lplan->learning_type == 2) {
			$plan_type = get_string('elective_courses', 'local_learningplan');
		}
		if (!empty($lplan->startdate)) {
			$plan_startdate = \local_costcenter\lib::get_userdate("d/m/Y H:i", $lplan->startdate);
		} else {
			$plan_startdate = get_string('statusna');
		}
		if (!empty($lplan->enddate)) {
			$plan_enddate = \local_costcenter\lib::get_userdate("d/m/Y H:i", $lplan->enddate);
		} else {
			$plan_enddate = get_string('statusna');
		}
		$pathcourses = '';
		if (count($lplanassignedcourses) >= 2) {
			$i = 1;
			$coursespath_context['pathcourses'] = array();
			foreach ($lplanassignedcourses as $assignedcourse) {
				$coursename = $assignedcourse->fullname;
				$coursespath_context['pathcourses'][] = array('coursename' => $coursename, 'coursename_string' => 'C' . $i);
				$i++;
				if ($i > 10) {
					break;
				}
			}
			$pathcourses .= $this->render_from_template('local_learningplan/cousrespath', $coursespath_context);
		}
		$enrolled = $this->db->get_field('local_learningplan_user', 'id', array('userid' => $this->user->id, 'planid' => $planid));
		$needenrol = $enrolled ? false : true;
		$ratings_exist = \core_component::get_plugin_directory('local', 'ratings');
		$display_ratings = '';
		$display_like = '';
		$certificate_exists = '';
		$certificate_download = '';
		$certificateid = '';
		if ($ratings_exist) {
			require_once($CFG->dirroot . '/local/ratings/lib.php');
			$display_ratings .= display_rating($planid, 'local_learningplan');
			$display_like .= display_like_unlike($planid, 'local_learningplan');
			$display_like .= display_comment($planid, 'local_learningplan');
			// $PAGE->requires->jquery();
			// $PAGE->requires->js('/local/ratings/js/jquery.rateyo.js');
			// $PAGE->requires->js('/local/ratings/js/ratings.js');
		} else {
			$display_ratings = $display_like = '';
		}

		if (!is_siteadmin()) {

 	  	    $switchedrole = $USER->useraccess['currentroleinfo']['roleid'];
			// $switchedrole = isset($USER->access['rsw']['/1']);
			if ($switchedrole) {
				$userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
			} else {
				$userrole = null;
			}

			$unenrol_flag = false;
			$selfenrolled = $DB->record_exists('local_learningplan_user', array('planid' => $planid, 'userid' => $USER->id, 'usercreated' => $USER->id));
			if ($selfenrolled) {
				$unenrol_flag = true;
			}

		


			//            if(is_null($userrole) || $userrole == 'user'){
			if (is_null($userrole) || $userrole == 'employee') {
				$core_component = new \core_component();
				$certificate_plugin_exist = $core_component::get_plugin_directory('tool', 'certificate');

				if ($certificate_plugin_exist) {
					if (!empty($lplan->certificateid)) {
						$certificate_exists = true;
						$sql = "SELECT id 
		                        FROM {local_learningplan_user}
		                        WHERE planid = :planid AND userid = :userid
		                        AND status = 1 ";
						$completed = $DB->record_exists_sql($sql, array('userid' => $USER->id, 'planid' => $planid));
						//            Mallikarjun added to get tool certificate
						$gcertificateid = $DB->get_field('local_learningplan', 'certificateid', array('id' => $planid));
						if ($completed) {
							$certificateid = $DB->get_field('tool_certificate_issues', 'code', array('moduleid' => $planid, 'userid' => $USER->id, 'moduletype' => 'learningplan'));
							if($certificateid == 0){
	                            $certificate_exists = false;
							}
							$certificate_download = true;
						} else {
							$certificate_download = false;
						}
						//		                $certificateid = $lplan->certificateid;
						// $certificate_download['moduletype'] = 'learningplan';
					}
				}
			}
		}
		$planpercent = output::planpercent($planid,$USER->id);
		$lp_userview = array();
		$lp_userview['planid'] = $planid;
		$lp_userview['userid'] = $this->user->id;
		$lp_userview['needenrol'] = $needenrol;
		$lp_userview['lpname'] = $lplan->name;
		$lp_userview['lpimgurl'] = $lpimgurl;
		$lp_userview['description_string'] = $description_string;
		$lp_userview['lpcoursespath'] = $pathcourses;
		//$lp_userview['lptype'] = $lptype;
		$lp_userview['plan_learningplan_code'] = $lplan->shortname ? $lplan->shortname : 'NA';
		$lp_userview['lpapproval'] = $lpapproval;
		$lp_userview['plan_startdate'] = $plan_startdate;
		$lp_userview['plan_enddate'] = $plan_enddate;
		$lp_userview['lplancredits'] = isset($lplan->credits);
		$lp_userview['mandatarycourses_count'] = isset($mandatarycourses_count) ? $mandatarycourses_count : 0;
		$lp_userview['optionalcourses_count'] = isset($optionalcourses_count) ? $optionalcourses_count : 0;
		$lp_userview['display_ratings'] = $display_ratings;
		$lp_userview['display_like'] = $display_like;
		$lp_userview['certificate_exists'] = $certificate_exists;
		$lp_userview['certificate_download'] = $certificate_download;
		$lp_userview['unenrol_flag'] = $unenrol_flag;
		$lp_userview['certificateid'] = $certificateid;
		$lp_userview['planpercent'] = isset($planpercent) ? $planpercent : 0;
		$challenge_exist = \core_component::get_plugin_directory('local', 'challenge');
		if ($challenge_exist) {
			$enabled =  (int)get_config('', 'local_challenge_enable_challenge');
			if ($enabled) {
				$challenge_render = $PAGE->get_renderer('local_challenge');
				$element = $challenge_render->render_challenge_object('local_learningplan', $planid);
				$lp_userview['challenge_element'] = $element;
			} else {
				$lp_userview['challenge_element'] = false;
			}
		} else {
			$lp_userview['challenge_element'] = false;
		}
		$lpinfo .= $this->render_from_template('local_learningplan/planview_user', $lp_userview);
		$test = '';
		$test .= '<div class="row my-4">';
		$test .= '<div class="col-md-9 lp_course-wrapper w-100 ">';
		if ($lplanassignedcourses) {
			$i = 1;
			foreach ($lplanassignedcourses as $assignedcourse) {
				$courseimgurl = $includeslib->course_summary_files($assignedcourse);

				$lp_userviewcoures = array();
				$coursesummary = \local_costcenter\lib::strip_tags_custom(html_entity_decode($assignedcourse->summary), array('overflowdiv' => false, 'noclean' => false, 'para' => false));
				$course_summary = empty($coursesummary) ? get_string('coure_summary_not_provided', 'local_learningplan') : $coursesummary;

				$course_summary_string = strlen($course_summary) > 125 ? clean_text(substr($course_summary, 0, 125)) . "..." : $course_summary;
				$c_category = $this->db->get_field('course_categories', 'name', array('id' => $assignedcourse->category));

				$coursetypes = $this->db->get_field('local_coursedetails', 'identifiedas', array('courseid' => $assignedcourse->id));
				if ($coursetypes) {
					$types = array();
					$ctypes = explode(',', $coursetypes);
					$identify = array();
					$identify['1'] = get_string('mooc');
					$identify['2'] = get_string('ilt');
					$identify['3'] = get_string('elearning');
					$identify['4'] = get_string('learningplan');
					foreach ($ctypes as $ctype) {
						$types[] = $identify[$ctype];
					}
				}


				$coursepageurl = new \moodle_url('/course/view.php', array('id' => $assignedcourse->id));
				if ($assignedcourse->next == 'and') {
					$optional_or_mandtry = "<span class='mandatory' title = '" . get_string('mandatory', 'local_learningplan') . "'>M</span>";
				} else {
					$optional_or_mandtry = "<span class='optional' title = '" . get_string('optional') . "'>OP</span>";
				}
				/**To make course link enable after the enrolled to lep**/
				$check = $this->db->get_field('local_learningplan_user', 'id', array('userid' => $this->user->id, 'planid' => $planid));
				if ($check) {
					$enrol = $this->db->get_field('enrol', 'id', array('courseid' => $assignedcourse->id, 'enrol' => 'learningplan'));
					/**The three enrolment added bcos we need to get link in any of enrolment so.There was issues in production**/
					$selfenrol = $this->db->get_field('enrol', 'id', array('courseid' => $assignedcourse->id, 'enrol' => 'self'));
					$autoenrol = $this->db->get_field('enrol', 'id', array('courseid' => $assignedcourse->id, 'enrol' => 'auto'));
					$manualenrol = $this->db->get_field('enrol', 'id', array('courseid' => $assignedcourse->id, 'enrol' => 'manual'));
					$learningplanenrol = $this->db->get_field('enrol', 'id', array('courseid' => $assignedcourse->id, 'enrol' => 'learningplan'));

					$sql = "SELECT id FROM {user_enrolments} WHERE userid={$this->user->id} AND enrolid IN ('$enrol','$selfenrol','$autoenrol','$manualenrol','$learningplanenrol')";

					$enrolledcourse = $this->db->get_field_sql($sql);

					$rname = format_string($assignedcourse->fullname);
					if ($rname > substr(($rname), 0, 23)) {
						$fullname = substr(($rname), 0, 23) . '...';
					} else {
						$fullname = $rname;
					}
					if ($enrolledcourse) {

						$courselink = html_writer::link($coursepageurl, $fullname, array('class' => 'coursesubtitle', 'title' => $assignedcourse->fullname));
					} else {
						/**Through course Link also user can enroll the course **/
						$coursepageurl = new moodle_url('/local/learningplan/index.php', array('courseid' => $assignedcourse->id, 'planid' => $lplan->id, 'userid' => $this->user->id));
						$courselink = html_writer::link($coursepageurl, $fullname, array('class' => 'coursesubtitle', 'title' => $assignedcourse->fullname));
					}
				} else {
					$rname = format_string($assignedcourse->fullname);
					if ($rname > substr(($rname), 0, 23)) {
						$fullname = substr(($rname), 0, 23) . '...';
					} else {
						$fullname = $rname;
					}
					$coursepageurl = "#";
					$courselink = html_writer::link($coursepageurl, $fullname, array('class' => 'coursesubtitle', 'title' => $assignedcourse->fullname));
				}

				$progressbar = $includeslib->user_course_completion_progress($assignedcourse->id, $this->user->id);
				if (!$progressbar) {
					$progressbarval = 0;
					$progress_bar_width = "min-width: 0px;";
				} else {
					$progressbarval = round($progressbar);
					$progress_bar_width = "min-width: 20px;";
				}
				/**To show course completed or not**/
				$sql = "SELECT id,timecompleted FROM {course_completions} as cc WHERE userid=" . $this->user->id . " and course=" . $assignedcourse->id . " and timecompleted!=''";

				$completed = $this->db->get_record_sql($sql);
				/**LAUNCH button for every courses to enrol**/
				/*First check the enrolment method*/
				$sql = "SELECT id,id AS id_val FROM {enrol} WHERE courseid = $assignedcourse->id";
				$get_data = $this->db->get_records_sql_menu($sql);
				$data = implode(',', $get_data);

				/**This below query is used to check the user already enroled to course with other enrolments methods**/
				$sql = "SELECT id FROM {user_enrolments} WHERE enrolid IN($data) and userid=" . $this->user->id . "";
				$find_user = $this->db->record_exists_sql($sql);

				/***Then check the userid***/

				if (!$find_user) {/*Condition to check the user enroled or not*/
					$plan_url = new \moodle_url('/local/learningplan/index.php', array('courseid' => $assignedcourse->id, 'planid' => $lplan->id, 'userid' => $this->user->id));
					$launch = html_writer::link($plan_url, 'Launch', array('class' => 'btn btn-sm btn-info pull-right btn-enrol btm-btn '));
				} else {/*if already enroled then show enroled */
					if (!empty($completed)) {
						$plan_url = new \moodle_url('/course/view.php', array('id' => $assignedcourse->id));
						$launch = html_writer::link($plan_url, 'Launch', array('class' => 'btn btn-sm btn-info pull-right btn-enrol btm-btn'));
					} else {
						$plan_url = new \moodle_url('/course/view.php', array('id' => $assignedcourse->id));
						$launch = html_writer::link($plan_url, 'Launch', array('class' => 'btn btn-sm btn-info pull-right btn-enrol btm-btn'));
					}
				}
				$course_data = '';
				if ($assignedcourse->sortorder == 0) {/*Condtion to set the enable to first sortorder*/
					$disable_class1 = ' '; /*Empty has been sent to class*/
				} else {
					$disable_class1 = ' ';
				}
				if ($progressbarval == 100) {
					$cmpltd_class = 'course_completed';
					$cmpltd_btn = 'completed';
					$completeflag = true;
					if ($completed->timecompleted) {
						$completiondate = \local_costcenter\lib::get_userdate("d/m/Y", $completed->timecompleted);
						$ctime = \local_costcenter\lib::get_userdate("h:i a", $completed->timecompleted);
					} else {
						$completed_date = '';
						$completeflag = false;
						$ctime = '';
					}
				} else {
					$cmpltd_class = '';
					$cmpltd_btn = '';
					$completiondate = '';
					$completeflag = false;
					$ctime = '';
				}
				if ($assignedcourse->sortorder > 0 && $assignedcourse->next == 'and') {/*Condition to check the sortorder and disable the course */
					/**** Function to get the all the course details like the nextsetoperator,sortorder
			@param planid,sortorder,courseid of the record
					 ****/
					$disable_class = $learningplan_classes_lib->get_previous_course_status($planid, $assignedcourse->sortorder, $assignedcourse->id);
					if ($disable_class) {
						$disable_class1 = "";
					} else {

						$restricted = $DB->get_field('local_learningplan', 'lpsequence', array('id' => $planid));
						if ($restricted) {
							$disable_class1 = 'course_disabled';
						}
					}
				} else {
					$disable_class1 = "";
				}
				$enroldisable_class1 = 'enrolled';
				if ($needenrol) {
					$enroldisable_class1 = 'not_enrolled course_disabled';
				}
				if ($ratings_exist) {
					require_once($CFG->dirroot . '/local/ratings/lib.php');
					$avgratings = get_rating($assignedcourse->id, 'local_courses');
					$avgrating = $avgratings->avg;
				} else {
					$avgrating  = '';
				}
                
				$lp_userviewcoures['disable_class1'] = $disable_class1;
				$lp_userviewcoures['needenrol'] = $needenrol;
				$lp_userviewcoures['enroldisable_class1'] = $enroldisable_class1;
				$lp_userviewcoures['cmpltd_class'] = $cmpltd_class;
				$lp_userviewcoures['cmpltd_btn'] = $cmpltd_btn;
				$lp_userviewcoures['progressbar'] = $progressbarval;
				$lp_userviewcoures['courseimgurl'] = $courseimgurl;
				$lp_userviewcoures['courselink'] = $courselink;
				$lp_userviewcoures['completiondate'] = $completiondate;
				$lp_userviewcoures['optional_or_mandtry'] = $optional_or_mandtry;
				$lp_userviewcoures['course_summary_string'] = $course_summary_string;
				$lp_userviewcoures['mandatarycourses_count'] = isset($mandatarycourses_count) ? $mandatarycourses_count : 0;
				$lp_userviewcoures['optionalcourses_count'] = isset($optionalcourses_count) ? $optionalcourses_count : 0;
				$lp_userviewcoures['plan_learningplan_code'] = $lplan->shortname ? $lplan->shortname : 'NA';
				$lp_userviewcoures['lplancredits'] = isset($lplan->credits) ? $lplan->credits : 'N/A';
				$lp_userviewcoures['completeflag'] = $completeflag;
				$lp_userviewcoures['avgrating'] =$avgrating;
				$lp_userviewcoures['ctime'] =$ctime;
				
				/**To disable the The status like Launch || Enrolled || Completed || before enrol to plan**/
				$check = $this->db->get_field('local_learningplan_user', 'id', array('userid' => $this->user->id, 'planid' => $planid));
				/*End of query*/
				$test .= $this->render_from_template('local_learningplan/planview_usercourses', $lp_userviewcoures);
			}
		}
		$test .= '</div>';
		$test .= '<div class="col-md-3 lp_bottom_container">';
		$test .= $this->render_from_template('local_learningplan/lprightcontainer', $lp_userviewcoures);
		$test .= '</div>';
		$test .= '</div>';
		$lpinfo .= $test;
		return $lpinfo;
	}
	public function display_unenrol_button($planid, $planname)
	{
		global $DB, $USER;
		$selfenrolled = $DB->record_exists('local_learningplan_user', array('planid' => $planid, 'userid' => $USER->id, 'usercreated' => $USER->id));
		if (!$selfenrolled) {
			return null;
		}
		$categorycontext = ((new \local_learningplan\lib\accesslib())::get_module_context($planid));
		$object = html_writer::link('javascript:void(0)', '<i class="icon fa fa-user-times" aria-hidden="true" aria-label="" title ="' . get_string('unenrol', 'local_learningplan') . '"></i>', array('class' => 'course_extended_menu_itemlink unenrolself_module', 'onclick' => '(function(e){ require(\'local_learningplan/courseenrol\').unEnrolUser({planid: ' . $planid . ', userid:' . $USER->id . ', planname:\'' . $planname . '\'}) })(event)'));
		$container = html_writer::div($object, '', array('class' => 'course_extended_menu_itemcontainer text-xs-center'));
		$liTag = html_writer::tag('li', $container);
		return html_writer::tag('ul', $liTag, array('class' => 'course_extended_menu_list'));
	}
	public function lpathinfo_for_employee($planid)
	{
		global $PAGE, $DB, $CFG, $USER;

		$learningplan_lib = new lib();
		$includeslib = new \user_course_details();
		$learningplan_classes_lib = new lib();

		$lplan = $this->db->get_record('local_learningplan', array('id' => $planid), '*', MUST_EXIST);

		$lpimgurl = $learningplan_classes_lib->get_learningplansummaryfile($planid);
		$mandatarycourses_count = $learningplan_classes_lib->learningplancourses_count($planid, 'and');
		$optionalcourses_count = $learningplan_classes_lib->learningplancourses_count($planid, 'or');
		$lplanassignedcourses = $learningplan_lib->get_learningplan_assigned_courses($planid);


		$description = $lplan->description;
		$lpinfo = '';
		if ($lplan->learning_type == 1) {
			$plan_type = 'Core Courses';
		} elseif ($lplan->learning_type == 2) {
			$plan_type = 'Elective Courses';
		}
		if (!empty($lplan->startdate)) {
			$plan_startdate = date('d/m/Y', $lplan->startdate);
		} else {
			$plan_startdate = 'N/A';
		}
		if (!empty($lplan->enddate)) {
			$plan_enddate = date('d/m/Y', $lplan->enddate);
		} else {
			$plan_enddate = 'N/A';
		}
		$pathcourses = '';
		if (count($lplanassignedcourses) >= 2) {
			$i = 1;
			$coursespath_context['pathcourses'] = array();
			foreach ($lplanassignedcourses as $assignedcourse) {
				$coursename = $assignedcourse->fullname;
				$coursespath_context['pathcourses'][] = array('coursename' => $coursename, 'coursename_string' => 'C' . $i);
				$i++;
				if ($i > 10) {
					break;
				}
			}
			$pathcourses .= $this->render_from_template('local_learningplan/cousrespath', $coursespath_context);
		}
		$enrolled = $this->db->get_field('local_learningplan_user', 'id', array('userid' => $this->user->id, 'planid' => $planid));
		$ratings_exist = \core_component::get_plugin_directory('local', 'ratings');
		if ($ratings_exist) {
			require_once($CFG->dirroot . '/local/ratings/lib.php');
			$display_ratings .= display_rating($planid, 'local_learningplan');
			$display_like .= display_like_unlike($planid, 'local_learningplan');
			$display_like .= display_comment($planid, 'local_learningplan');
		} else {
			$display_ratings = $display_like = '';
		}

		// if (!is_siteadmin()) {
		// 	// $switchedrole = $USER->access['rsw']['/1'];
 	  	//     $switchedrole = $USER->useraccess['currentroleinfo']['roleid'];
		// 	if ($switchedrole) {
		// 		$userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
		// 	} else {
		// 		$userrole = 'employee';
		// 	}
		// }
		// $lp_userview = array();
		// $lp_userview['planid'] = $planid;
		// $lp_userview['userid'] = $this->user->id;
		// $enrolled = $DB->record_exists('local_learningplan_user', array('planid' => $planid, 'userid' => $USER->id));
		// $selfenrol_check =  $DB->get_field('local_learningplan', 'selfenrol', array('id' => $planid));
		// if (!is_siteadmin() && !$enrolled && $selfenrol_check && $userrole=='employee') {
		// 	$lp_userview['needenroluser'] = true;
		// 	$lp_userview['enrollbtn'] = \local_learningplan\output\search::get_enrollbtn($lplan);
		// }

		$lp_userview['component'] = $component = 'learningplan';
		$lp_userview['action'] = 'add';
		if ($lplan->approvalreqd == 1) {
			$requestsql = "SELECT status FROM {local_request_records}
				WHERE componentid = :componentid AND compname LIKE :compname AND
				createdbyid = :createdbyid ORDER BY id DESC ";
			$request = $DB->get_field_sql($requestsql, array('componentid' => $planid, 'compname' => $component, 'createdbyid' => $USER->id));

			if ($request == 'PENDING') {
				$lp_userview['pending'] = true;
			} else {
				$lp_userview['requestbtn'] = true;
			}
		} else {
			$lp_userview['requestbtn'] = false;
		}

		$lp_userview['lpname'] = $lplan->name;
		$lp_userview['lpimgurl'] = $lpimgurl;
		$lp_userview['description_string'] = $description;
		$lp_userview['lpcoursespath'] = $pathcourses;
		$lp_userview['plan_learningplan_code'] = $lplan->shortname ? $lplan->shortname : 'NA';
		$lp_userview['mandatarycourses_count'] = $mandatarycourses_count;
		$lp_userview['optionalcourses_count'] = $optionalcourses_count;
		$lp_userview['display_ratings'] = $display_ratings;
		$lp_userview['display_like'] = $display_like;
		$lp_userview['lplancredits'] = ($lplan->open_points > 0) ? $lplan->open_points : 'N/A';
		$challenge_exist = \core_component::get_plugin_directory('local', 'challenge');
		if ($challenge_exist) {
			$challenge_render = $PAGE->get_renderer('local_challenge');
			$element = $challenge_render->render_challenge_object('local_learningplan', $planid);
			$lp_userview['challenge_element'] = $element;
		} else {
			$lp_userview['challenge_element'] = false;
		}
		$lpinfo .= $this->render_from_template('local_learningplan/lpathview_user', $lp_userview);
		$test = '';
		$test .='<div class="row my-4 lpathcourse_wrapper">';
		$test .= '<div class="lp_course-wrapper w-100 col-md-9">';
		if ($lplanassignedcourses) {
			$i = 1;
			foreach ($lplanassignedcourses as $assignedcourse) {
				$courseimgurl = $includeslib->course_summary_files($assignedcourse);
				$lp_userviewcoures = array();
				$coursesummary = strip_tags(html_entity_decode($assignedcourse->summary), array('overflowdiv' => false, 'noclean' => false, 'para' => false));
				$course_summary = empty($coursesummary) ? 'Course summary not provided' : $coursesummary;
				$course_summary_string = strlen($course_summary) > 125 ? clean_text(substr($course_summary, 0, 125)) . "..." : $course_summary;
				if ($assignedcourse->next == 'and') {
					$optional_or_mandtry = "<span class='mandatory' title = 'Mandatory'>M</span>";
				} else {
					$optional_or_mandtry = "<span class='optional' title = 'Optional'>OP</span>";
				}

				$rname = format_string($assignedcourse->fullname);
				if ($rname > substr(($rname), 0, 23)) {
					$fullname = substr(($rname), 0, 23) . '...';
				} else {
					$fullname = $rname;
				}
				$course_name_string = strlen($fullname) > 125 ? clean_text(substr($fullname, 0, 125)) . "..." : $fullname;
				$enroldisable_class1 = 'enrolled';
				if (!is_siteadmin()) {
					   $switchedrole = $USER->useraccess['currentroleinfo']['roleid'];
					if ($switchedrole) {
						$userrole = $DB->get_field('role', 'shortname', array('id' => $switchedrole));
					} else {
						$userrole = 'employee';
					}
				}
				$enrolled = $DB->record_exists('local_learningplan_user', array('planid' => $planid, 'userid' => $USER->id));
				$selfenrol_check =  $DB->get_field('local_learningplan', 'selfenrol', array('id' => $planid));
				if (!is_siteadmin() && !$enrolled && $selfenrol_check && $userrole=='employee') {
					$lp_userviewcoures['needenroluser'] = true;
					$lp_userviewcoures['enrollbtn'] = \local_learningplan\output\search::get_enrollbtn($lplan);
					}
				$lp_userviewcoures['enroldisable_class1'] = $enroldisable_class1;
				$lp_userviewcoures['courseimgurl'] = $courseimgurl;
				$lp_userviewcoures['courselink'] = $course_name_string;
				$lp_userviewcoures['optional_or_mandtry'] = $optional_or_mandtry;
				$lp_userviewcoures['course_summary_string'] = $course_summary_string;
				$lp_userviewcoures['mandatarycourses_count'] = $mandatarycourses_count;
				$lp_userviewcoures['optionalcourses_count'] = $optionalcourses_count;
				$test .= $this->render_from_template('local_learningplan/lpathcourse', $lp_userviewcoures);
			}
		}
		$test .= '</div>';
		$test .= '<div class="col-md-3 lp_bottom_container">';
		$test .= $this->render_from_template('local_learningplan/lpathbottomcontent', $lp_userviewcoures);
		$test .= '</div>';
		$test .= '</div>';
		$lpinfo .= $test;

		return $lpinfo;
	}
}
