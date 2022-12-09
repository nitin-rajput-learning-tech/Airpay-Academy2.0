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
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;
use block_learnerscript\local\reportbase;

class plugin_examlearneroverviewcolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('examlearneroverviewcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('examlearneroverview');
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

    // Data -> Plugin configuration data.
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $USER;
        $context = context_system::instance();
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
                $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
                $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
                $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = $dhs = 1;
            }
        } 
        $costcenter = " ";
        $dept = " ";
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){ 
                if ($this->reportfilterparams['filter_organization']>0) {
                    $costcenter = " AND le1.costcenterid IN (".$this->reportfilterparams['filter_organization'].",0) AND le1.user_costcenterid=".$this->reportfilterparams['filter_organization'];
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND le1.departmentid IN (".$this->reportfilterparams['filter_departments'].",0) AND le1.user_departmentid=".$this->reportfilterparams['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                  $costcenter = " AND le1.costcenterid IN (".$USER->open_costcenterid.", 0) AND le1.user_costcenterid=".$USER->open_costcenterid; 
                  if ($this->reportfilterparams['filter_departments'] > 0) {
                      $dept = " AND le1.departmentid IN (".$this->reportfilterparams['filter_departments'].",0) AND le1.user_departmentid=".$this->reportfilterparams['filter_departments'];
                  }
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                  $costcenter = " AND le1.costcenterid IN (".$USER->open_costcenterid.", 0) AND le1.departmentid IN (".$USER->open_departmentid.", 0) AND le1.user_costcenterid = " .$USER->open_costcenterid . " AND le1.user_departmentid = ". $USER->open_departmentid;
            } else {
                $costcenter = " AND le1.costcenterid IN (".$USER->open_costcenterid.", 0) AND le1.departmentid IN (".$USER->open_departmentid.", 0) AND le1.user_costcenterid = " .$USER->open_costcenterid . " AND le1.user_departmentid = ". $USER->open_departmentid ." AND le1.subdepartment IN (".$USER->open_subdepartment.",0) AND le1.user_subdepartment =" .$USER->open_subdepartment;
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
              $subdept = " AND le1.subdepartment IN (".$this->reportfilterparams['filter_subdepartments'].",0) AND le1.user_subdepartment=".$this->reportfilterparams['filter_subdepartments'];
            } 
        }      
        $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'examlearnersummery'), IGNORE_MULTIPLE);
        $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
        switch($data->column) {
        	/*case 'enrolled': 
        	 if(!isset($row->enrolled) && isset($data->subquery)){
                $enrolled = $DB->get_field_sql($data->subquery);
              }else{
                  $enrolled = $row->{$data->column};
              }
        	$allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'], 'filter_course' => $row->id));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $enrolled;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $enrolled,
                array('href' => $allurl));
              }
        	break;*/
        	case 'completed':
        	if(!isset($row->completed) && isset($data->subquery)){
                $completed = $DB->get_field_sql($data->subquery);
            }else{
                $sql = " SELECT COUNT(DISTINCT le1.userid) AS completed FROM {block_ls_exams} le1 
                                WHERE le1.completiondate != 0 AND le1.examid = $row->id {$costcenter} {$dept} ";
                $completed = $DB->get_field_sql($sql);
            }
        	$allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_course' => $row->id,'filter_status' => 'completed'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $completed;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $completed,
                array('href' => $allurl));
              }
        	break;
        	/*case 'inprogress':
            if(!isset($row->inprogress) && isset($data->subquery)){
                $inprogress = $DB->get_field_sql($data->subquery);
            }else{
                $sql = " SELECT COUNT(DISTINCT le1.userid) FROM {block_ls_exams} le1 
                                WHERE le1.completiondate = 0 AND le1.examid = $row->id {$costcenter} {$dept} ";
                $inprogress = $DB->get_field_sql($sql);
            } 
        	$allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_course' => $row->id, 'filter_status' => 'inprogress'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $inprogress;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $inprogress,
                array('href' => $allurl));
              }
        	break;*/
            case 'upcomingdeadline': 
            if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                $upcomingdeadline = $DB->get_field_sql($data->subquery);
            }else{
                $sql = " SELECT COUNT(DISTINCT le1.userid) AS upcomingdeadline FROM {block_ls_exams} le1 
                                WHERE le1.deadline > UNIX_TIMESTAMP() AND le1.completiondate = 0 
                                AND le1.examid = $row->id {$costcenter} {$dept} ";
                $upcomingdeadline = $DB->get_field_sql($sql);
            }
            $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_course' => $row->id, 'filter_status' => 'upcoming'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $upcomingdeadline;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $upcomingdeadline,
                array('href' => $allurl));
              }
            break;
            case 'overduedeadline': 
            if(!isset($row->overduedeadline) && isset($data->subquery)){
                $overduedeadline = $DB->get_field_sql($data->subquery);
            }else{
                $sql = " SELECT COUNT(DISTINCT le1.userid) AS overduedeadline FROM {block_ls_exams} le1 
                                WHERE le1.deadline < UNIX_TIMESTAMP() AND le1.completiondate = 0 
                                AND le1.deadline != 0
                                AND le1.examid = $row->id {$costcenter} {$dept}  ";
                $overduedeadline = $DB->get_field_sql($sql);
            }
            $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_course' => $row->id, 'filter_status' => 'overdue'));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $overduedeadline;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $overduedeadline,
                array('href' => $allurl));
              }
                break;
          case 'upcomingexpiry': 
            if(!isset($row->upcomingexpiry) && isset($data->subquery)){
                $upcomingexpiry = $DB->get_field_sql($data->subquery);
            }else{
                $sql = " SELECT COUNT(DISTINCT le1.userid) AS upcomingexpiry FROM {block_ls_exams} le1 
                                WHERE le1.upcomingexpiry != 0 
                                AND le1.examid = $row->id {$costcenter} {$dept} ";
                $upcomingexpiry = $DB->get_field_sql($sql);
            }
            $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_course' => $row->id, 'filter_status' => 'upcomingexpiry'));
            if(empty($learnerpermissions) || empty($reportid)){
                $row->{$data->column} = $upcomingexpiry;
            } else{
                $row->{$data->column} = html_writer::tag('a', $upcomingexpiry,
               array('href' => $allurl));
            }            
           break;
          case 'upcomingendoflife': 
            if(!isset($row->upcomingendoflife) && isset($data->subquery)){
                $upcomingendoflife = $DB->get_field_sql($data->subquery);
            }else{
                $sql = " SELECT le1.upcomingeol AS upcomingeol FROM {block_ls_exams} le1 
                                WHERE le1.upcomingeol != 0 AND le1.vendorid!=0 AND le1.completiondate > 0 
                                AND le1.examid = $row->id {$costcenter} {$dept} ";  
                $upcomingendoflife = $DB->get_field_sql($sql);
            }
            $row->{$data->column} = !empty($upcomingendoflife) ? strftime('%d-%m-%Y', $upcomingendoflife) : '--';            
           break;
            case 'status':
              if(!isset($row->status) && isset($data->subquery)){
                $status = $DB->get_field_sql($data->subquery);
              }else{
                  if ($row->status == 1) {
                      $status = 'Announced'; 
                  } else if ($row->status == 2) {
                      $status = 'Active';                    
                  } else if ($row->status == 3) {
                      $status = 'Beta';                    
                  } else if ($row->status == 4) {
                      $status = 'Retired';                    
                  } 
              }
              $row->{$data->column} = !empty($status) ? $status : '--';
            break;           
        }
            return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

}
