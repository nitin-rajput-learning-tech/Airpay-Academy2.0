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

class plugin_learnercertificationscolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('certificationcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('certification');
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
        $expirydate = strtotime("+90 days");
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
        $subdept = " ";
        $time = " ";
        $certification = " ";
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context)){ 
                if ($this->reportfilterparams['filter_organization']>0) {
                    $costcenter = " AND lc.costcenter IN (".$this->reportfilterparams['filter_organization'].",0) AND u.open_costcenterid =". $this->reportfilterparams['filter_organization'];
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND lc.department IN (".$this->reportfilterparams['filter_departments'].", -1) AND u.open_departmentid=".$this->reportfilterparams['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $context) && $ohs) { 
                  $costcenter = " AND lc.costcenter IN (".$USER->open_costcenterid.", 0) AND u.open_costcenterid =". $USER->open_costcenterid; 
                  if ($this->reportfilterparams['filter_departments'] > 0) {
                      $dept = " AND lc.department IN (".$this->reportfilterparams['filter_departments'].", -1) AND u.open_departmentid=".$this->reportfilterparams['filter_departments'];
                  }
            }else if(has_capability('local/costcenter:manage_owndepartments', $context) && $dhs) { 
                 $costcenter = " AND lc.costcenter IN (".$USER->open_costcenterid.",0) AND lc.department IN (".$USER->open_departmentid.", -1) AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =".$USER->open_departmentid;
            } else {
                $costcenter = " AND lc.costcenter IN (".$USER->open_costcenterid.",0) AND lc.department IN (".$USER->open_departmentid.", -1) AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =".$USER->open_departmentid . " AND u.open_subdepartment =" .$USER->open_subdepartment;
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
              $subdept = " AND lc.subdepartment IN (".$this->reportfilterparams['filter_subdepartments'].", -1) AND u.open_subdepartment=".$this->reportfilterparams['filter_subdepartments'];
            } 
        }
        if ($this->reportfilterparams['ls_fstartdate'] >= 0 && $this->reportfilterparams['ls_fenddate']) {
            $time .= " AND lc.timecreated BETWEEN ". $this->reportfilterparams['ls_fstartdate'] ." AND ". $this->reportfilterparams['ls_fenddate'] ;
        } 

        if (!empty($this->reportfilterparams['filter_certificates']) && $this->reportfilterparams['filter_certificates'] > 0) {
            $certificationid = $this->reportfilterparams['filter_certificates'];
            $certification .= " AND lc.id IN ($certificationid) ";
        }

        $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'learnercertificationssummary'), IGNORE_MULTIPLE);
        $learnerpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);
        switch($data->column) {
            case 'learner':
              if(!isset($row->learner) && isset($data->subquery)){
                $learner = $DB->get_field_sql($data->subquery);
              }else{
                  $learner = $row->{$data->column};
              }
              $row->{$data->column} = !empty($learner) ? $learner : '--';
            break;
            case 'enrolments':
                if(!isset($row->enrolments) && isset($data->subquery)){
                    $enrolments = $DB->get_field_sql($data->subquery);
                }else{
                  $enrolments = $row->{$data->column};               
                }
                $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_user' => $row->id));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $enrolments;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $enrolments,
                  array('href' => $allurl));
              }
              break;            
            case 'inprogress':
                if(!isset($row->inprogress) && isset($data->subquery)){
                    $inprogress = $DB->get_field_sql($data->subquery);
                }else{
                    $sql = " SELECT COUNT(DISTINCT lc.id) AS inprogress
                        FROM {local_certification} AS lc 
                        JOIN {local_certification_users} AS lcu ON lc.id = lcu.certificationid
                        JOIN {user} u ON u.id = lcu.userid
                        WHERE lcu.userid = {$row->id} AND lcu.completion_status = 0 {$costcenter} {$dept} {$subdept} {$time}  {$certification}";
                $inprogress = $DB->get_field_sql($sql);
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'inprogress', 'filter_user' => $row->id));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $inprogress;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $inprogress,
                  array('href' => $allurl));
              }
              break;
            case 'completed':
              if(!isset($row->completed) && isset($data->subquery)){
                $completed = $DB->get_field_sql($data->subquery);
              }else{
                  $sql = " SELECT COUNT(DISTINCT lc.id) AS completed
                        FROM {local_certification} AS lc 
                        JOIN {local_certification_users} AS lcu ON lc.id = lcu.certificationid
                        JOIN {user} u ON u.id = lcu.userid
                        WHERE lcu.userid = {$row->id} AND lcu.completion_status = 1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP()) {$costcenter} {$dept} {$subdept} {$time} {$certification} ";
                $completed = $DB->get_field_sql($sql);
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'completed', 'filter_user' => $row->id));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $completed;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $completed,
                  array('href' => $allurl));
              }
              break;
            case 'upcomingexpiry':
              if(!isset($row->upcomingexpiry) && isset($data->subquery)){
                $upcomingexpiry = $DB->get_field_sql($data->subquery);
              }else{
                $sql = "SELECT COUNT(DISTINCT lc.id) AS 'Upcoming expiry'
                        FROM {local_certification} AS lc 
                        JOIN {local_certification_users} AS lcu ON lc.id = lcu.certificationid 
                        JOIN {user} u ON u.id = lcu.userid
                        WHERE lcu.userid = {$row->id} AND lcu.expirydate BETWEEN UNIX_TIMESTAMP() AND {$expirydate} AND lcu.completion_status =1 {$costcenter} {$dept} {$subdept} {$time} {$certification} ";
                $upcomingexpiry = $DB->get_field_sql($sql);
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'upcomingexpiry', 'filter_user' => $row->id));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $upcomingexpiry;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $upcomingexpiry,
                  array('href' => $allurl));
              }
              break;
            break;
            case 'upcomingendoflife':
              if(!isset($row->upcomingendoflife) && isset($data->subquery)){
                $upcomingendoflife = $DB->get_field_sql($data->subquery);
              }else{
                  $sql = " SELECT COUNT(DISTINCT lc.id) AS 'End of Life'
                        FROM {local_certification} AS lc 
                        JOIN {local_certification_users} AS lcu ON lc.id = lcu.certificationid
                        JOIN {user} u ON u.id = lcu.userid 
                        WHERE lcu.userid = {$row->id}  AND lc.eol BETWEEN UNIX_TIMESTAMP() AND {$expirydate} {$costcenter} {$dept} {$subdept} {$time} {$certification} ";
                $upcomingendoflife = $DB->get_field_sql($sql);
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'upcomingendoflife', 'filter_user' => $row->id));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $upcomingendoflife;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $upcomingendoflife,
                  array('href' => $allurl));
              }
              break;
            case 'upcomingdeadline':
              if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                $upcomingdeadline = $DB->get_field_sql($data->subquery);
              }else{
                  $sql = " SELECT COUNT(DISTINCT  lc.id) AS upcomingdeadline
                        FROM {local_certification} AS lc 
                        JOIN {local_certification_users} AS lcu ON lc.id = lcu.certificationid
                        JOIN {user} u ON u.id = lcu.userid
                        WHERE lcu.userid = {$row->id} AND lcu.certdeadline > UNIX_TIMESTAMP() AND lcu.completion_status = 0 AND lcu.certdeadline != 0 {$costcenter} {$dept} {$subdept} {$time} {$certification} ";
                $upcomingdeadline = $DB->get_field_sql($sql);
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'upcoming', 'filter_user' => $row->id));
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
                  $sql = " SELECT COUNT(DISTINCT lc.id) AS overduedeadline
                        FROM {local_certification} AS lc 
                        JOIN {local_certification_users} AS lcu ON lc.id = lcu.certificationid
                        JOIN {user} u ON u.id = lcu.userid
                        WHERE lcu.userid = {$row->id} AND lcu.certdeadline < UNIX_TIMESTAMP() AND lcu.completion_status = 0 AND lcu.certdeadline != 0 {$costcenter} {$dept} {$subdept} {$time} {$certification}  ";
                $overduedeadline = $DB->get_field_sql($sql);
              }
              $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                  array('id' => $reportid, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'overdue', 'filter_user' => $row->id));
              if(empty($learnerpermissions) || empty($reportid)){
                  $row->{$data->column} = $overduedeadline;
              } else{
                  $row->{$data->column} = html_writer::tag('a', $overduedeadline,
                  array('href' => $allurl));
              }
              break;
        }
            return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
