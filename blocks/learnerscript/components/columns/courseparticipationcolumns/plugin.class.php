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
  * @subpackage learnerscript
  * @date: 2019
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;

class plugin_courseparticipationcolumns extends pluginbase{
	public function init(){
		$this->fullname = get_string('courseparticipationcolumns', 'block_learnerscript');
		$this->type = 'undefined';
		$this->form = true;
		$this->reporttypes = array('courseparticipation');
	}

	public function summary($data){
		return format_string($data->columname);
	}
	public function colformat($data){
		$align = (isset($data->align))? $data->align : '';
		$size = (isset($data->size))? $data->size : '';
		$wrap = (isset($data->wrap))? $data->wrap : '';
		return array($align,$size,$wrap);
	}
	public function execute($data,$row,$user,$courseid,$starttime=0,$endtime=0){
		global $DB, $USER;
        $context = context_system::instance();
				$reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'learnercoursesoverview'), IGNORE_MULTIPLE);

        $courseoverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
        if ($this->reportfilterparams['filter_organization']) {
            $organization = $this->reportfilterparams['filter_organization'];
            $filter_organization[] = " concat('/',u.open_path,'/') LIKE :organizationparam_{$organization}";
            $this->params["organizationparam_{$organization}"] = '%/'.$organization.'/%';
            $costcenter .= " AND ( ".implode(' OR ', $filter_organization)." ) ";
        }
        if ($this->reportfilterparams['filter_departments'] > 0) {
            $department = $this->reportfilterparams['filter_departments'];
            $filter_department[] = " concat('/',u.open_path,'/') LIKE :departmentparam_{$department}";
            $this->params["departmentparam_{$department}"] = '%/'.$department.'/%';
            $dept.= " AND ( ".implode(' OR ', $filter_department)." ) ";
        }
        if ($this->reportfilterparams['filter_subdepartments'] > 0) {
            $subdepartments = $this->reportfilterparams['filter_subdepartments'];
            $filter_subdepartments[] = " concat('/',u.open_path,'/') LIKE :subdepartmentsparam_{$subdepartments}";
            $this->params["subdepartmentsparam_{$subdepartments}"] = '%/'.$subdepartments.'/%';
            $subdept.= " AND ( ".implode(' OR ', $filter_subdepartments)." ) ";
        }

		switch ($data->column) {
			case 'enrolled':
			     	 if(!isset($row->enrolled)){
		            $enrolled =  $DB->get_field_sql($data->subquery);
		         }else{
		            $enrolled = $row->{$data->column};
		         }
						$allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
							array('id' => $reportid, 'filter_users' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
						if(empty($courseoverviewpermissions) || empty($reportid)){
							$row->{$data->column} = $enrolled;
						} else{
							$row->{$data->column} = html_writer::tag('a', $enrolled,
							array('href' => $allurl));
						}
	         break;
			case 'inprogress':
			     if(!isset($row->inprogress)){
		            $inprogress =  $DB->get_field_sql($data->subquery);
		         }else{
		            $inprogress = $row->{$data->column};
		         }
				    $inprogressurl = new moodle_url('/blocks/learnerscript/viewreport.php',
					  array('id' => $reportid, 'filter_users' => $row->id, 'filter_status' => 'inprogress', 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));

					if(empty($courseoverviewpermissions) || empty($reportid)){
						$row->{$data->column} = $inprogress;
					} else{
						$row->{$data->column} = html_writer::tag('a', $inprogress,
						array('href' => $inprogressurl));
					}

	        break;
			case 'completed':
					if(!isset($row->completed)){
					  $completed =  $DB->get_field_sql($data->subquery);
					}else{
					  $completed = $row->{$data->column};
					}
					$completedurl = new moodle_url('/blocks/learnerscript/viewreport.php',
					array('id' => $reportid, 'filter_users' => $row->id, 'filter_status' => 'completed', 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
					if(empty($courseoverviewpermissions) || empty($reportid)){
						$row->{$data->column} = $completed;
					} else{
						$row->{$data->column} = html_writer::tag('a', $completed,
						array('href' => $completedurl));
					}

	        break;

	        case 'progress':

	        	if(!isset($row->progress)){
		            $progress =  $DB->get_field_sql($data->subquery);
		         }else{
		            $progress = $row->{$data->column};
		         }
		         $progress = empty($progress) ? 0 : $progress;

						return "<div class='spark-report' id='".html_writer::random_id()."' style='width:100px;' data-sparkline='$progress; progressbar'
						data-labels = 'progress' >" . $progress . "</div>";

					break;



		}
		return (isset($row->{$data->column}))? $row->{$data->column} : '';
	}
}
