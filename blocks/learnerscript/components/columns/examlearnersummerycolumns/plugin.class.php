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
use core_completion\progress;
class plugin_examlearnersummerycolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('examlearnersummerycolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('examlearnersummery');
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
        global $DB, $CFG,$OUTPUT, $USER;
        $courserecords = $DB->get_record_sql("SELECT * FROM {course} WHERE id = $row->courseid"); 
        $systemcontext = context_system::instance();
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
        if (!$this->scheduling) {
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                if ($this->reportfilterparams['filter_organization']>0) {
                    $costcenter = " AND c.open_costcenterid IN (".$this->reportfilterparams['filter_organization'].",0) AND u.open_costcenterid=".$this->reportfilterparams['filter_organization'];
                }
                if ($this->reportfilterparams['filter_departments'] > 0) {
                    $dept = " AND c.open_departmentid IN (".$this->reportfilterparams['filter_departments'].",0) AND u.open_departmentid=".$this->reportfilterparams['filter_departments'];
                }
            } else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                  $costcenter = " AND c.open_costcenterid IN (".$USER->open_costcenterid.", 0) AND u.open_costcenterid=".$USER->open_costcenterid; 
                  if ($this->reportfilterparams['filter_departments'] > 0) {
                      $dept = " AND c.open_departmentid IN (".$this->reportfilterparams['filter_departments'].",0) AND u.open_departmentid=".$this->reportfilterparams['filter_departments'];
                  }
            }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
                  $costcenter = " AND c.open_costcenterid IN (".$USER->open_costcenterid.", 0) AND c.open_departmentid IN (".$USER->open_departmentid.", 0) AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid;
            } else {
                $costcenter = " AND c.open_costcenterid IN (".$USER->open_costcenterid.", 0) AND c.open_departmentid IN (".$USER->open_departmentid.", 0) AND u.open_costcenterid = " .$USER->open_costcenterid . " AND u.open_departmentid = ". $USER->open_departmentid ." AND c.open_subdepartment IN (".$USER->open_subdepartment.",0) AND u.open_subdepartment = " .$USER->open_subdepartment;
            }
            if ($this->reportfilterparams['filter_subdepartments'] > 0) {
              $subdept = " AND c.open_subdepartment IN (".$this->reportfilterparams['filter_subdepartments'].",0) AND u.open_subdepartment=".$this->reportfilterparams['filter_subdepartments'];
            } 
        }            
        switch($data->column){
            case 'progress':
              if(!isset($row->progress) && isset($data->subquery)){
                $progress = $DB->get_field_sql($data->subquery);
              }else{
                  $percent = progress::get_course_progress_percentage($courserecords, $row->id);
                  if (!is_null($percent)) {
                      $percent = floor($percent);
                  }else{
                      $percent = 0;
                  }
                    $completionprogress = $percent;
                }
                return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionprogress; progressbar'
            data-labels = 'inprogress, completed' data-link='' >" . $completionprogress . "</div>";
            break;
            case 'completed':
                if(!isset($row->completed) && isset($data->subquery)){
                    $completed = $DB->get_field_sql($data->subquery);
                }else{
                    $completed = $row->{$data->column};
                }
                $row->{$data->column} = !empty($completed) ? strftime('%d-%m-%Y', $completed) : '--';
            break;
            case 'deadline':
                if(!isset($row->deadline) && isset($data->subquery)){
                       $deadline = $DB->get_field_sql($data->subquery);
                   }else{
                    $deadline = $row->{$data->column};
                   }
                   $row->{$data->column} = !empty($deadline) ? strftime('%d-%m-%Y', $deadline) : '--';
            break;
            case 'upcomingexpiry':
                if(!isset($row->upcomingexpiry) && isset($data->subquery)){
                       $upcomingexpiry = $DB->get_field_sql($data->subquery);
                }else{
                  $sql = " SELECT DATE_ADD(FROM_UNIXTIME(cfd.timemodified) , interval cfd.charvalue month) 
                        FROM {customfield_data} cfd 
                        JOIN {customfield_field} cff ON cff.id = cfd.fieldid AND cff.name = 'Valid for (months)'
                        JOIN {course} c on c.id = cfd.instanceid  
                          JOIN {enrol} e ON e.courseid = c.id
                          JOIN {user_enrolments} ue ON ue.enrolid = e.id
                          JOIN {user} u ON u.id = ue.userid
                        WHERE cfd.charvalue != '' AND DATE_ADD(FROM_UNIXTIME(cfd.timemodified) , interval cfd.charvalue month) BETWEEN CURDATE() 
                        AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND cfd.instanceid = {$row->courseid} {$costcentr} {$dept} {$subdept} ";
                   $upcomingexpiry = $DB->get_field_sql($sql);
                   }
                   $row->{$data->column} = !empty($upcomingexpiry) ? date_format(date_create($upcomingexpiry),"d-m-Y") : '--';
            break;
            case 'upcomingendoflife':
                if(!isset($row->upcomingendoflife) && isset($data->subquery)){
                       $upcomingendoflife = $DB->get_field_sql($data->subquery);
                   }else{
                $sql = " SELECT cfd.intvalue AS 'End of Life'
                          FROM {course} c 
                          JOIN {customfield_data} cfd ON c.id = cfd.instanceid
                          JOIN {customfield_field} cff ON cff.id = cfd.fieldid AND cff.name = 'EOL'
                          JOIN {enrol} e ON e.courseid = c.id
                          JOIN {user_enrolments} ue ON ue.enrolid = e.id
                          JOIN {user} u ON u.id = ue.userid
                          WHERE c.id = {$row->courseid} {$costcenter} {$dept} {$subdept} ";
                        // $sql = " SELECT cfd.intvalue
                        //     FROM {customfield_data} cfd 
                        //     JOIN {customfield_field} cff ON cff.id = cfd.fieldid AND cff.name = 'EOL'
                        //     JOIN {course} c on c.id = cfd.instanceid 
                        //     WHERE FROM_UNIXTIME(cfd.intvalue) BETWEEN CURDATE() AND (CURDATE() + 90) AND cfd.instanceid = {$row->courseid}  {$costcentr} {$dept}  ";
                        $upcomingendoflife = $DB->get_field_sql($sql);
                   }
                   $row->{$data->column} = !empty($upcomingendoflife) ? strftime('%d-%m-%Y', $upcomingendoflife) : '--';
            break;                        
        }
            return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

}
