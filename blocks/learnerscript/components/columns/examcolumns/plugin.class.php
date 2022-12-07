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
/** LearnerScript Reports
  * A Moodle block for creating customizable reports
  * @package blocks
  * @subpackage learnerscript
 * @author: Revanth Kumar
 * @date: 2021
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\ls;
use core_completion\progress;
use block_learnerscript\local\reportbase;

class plugin_examcolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('examcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('exam');
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

    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $USER;
        $context = context_system::instance();
        $reportid = $DB->get_field('block_learnerscript', 'id', array('type' => 'examenrolments'), IGNORE_MULTIPLE);
        $courseoverviewpermissions = empty($reportid) ? false : (new reportbase($reportid))->check_permissions($USER->id, $context);       
        switch ($data->column) {
            // case 'enrolments':
            //     if(!isset($row->enrolments) && isset($data->subquery)){
            //         $enrolments = $DB->get_field_sql($data->subquery);
            //     }else{
            //         $enrolments = $row->{$data->column};
            //     }
            //     $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
            //         array('id' => $reportid, 'filter_coursevendors' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'], 'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments']));
            //     if(empty($courseoverviewpermissions) || empty($reportid)){
            //         $row->{$data->column} = $enrolments;
            //     } else{
            //         $row->{$data->column} = html_writer::tag('a', $enrolments,
            //         array('href' => $allurl));
            //     }
            //     break;
            // case 'inprogress':
            //     if(!isset($row->inprogress) && isset($data->subquery)){
            //         $inprogress = $DB->get_field_sql($data->subquery);
            //     }else{
            //         $inprogress = $row->{$data->column};
            //     }
            //     $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
            //         array('id' => $reportid, 'filter_coursevendors' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'inprogress'));
            //     if(empty($courseoverviewpermissions) || empty($reportid)){
            //         $row->{$data->column} = $inprogress;
            //     } else{
            //         $row->{$data->column} = html_writer::tag('a', $inprogress,
            //         array('href' => $allurl));
            //     }
            //     break;
            case 'completed':
                if(!isset($row->completed) && isset($data->subquery)){
                    $completed = $DB->get_field_sql($data->subquery);
                }else{
                    $completed = $row->{$data->column};
                }
                $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                    array('id' => $reportid, 'filter_coursevendors' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'completed'));
                if(empty($courseoverviewpermissions) || empty($reportid)){
                    $row->{$data->column} = $completed;
                } else{
                    $row->{$data->column} = html_writer::tag('a', $completed,
                    array('href' => $allurl));
                }
                break;
            /*case 'completionpercentage':
                if(!isset($row->completionpercentage) && isset($data->subquery)){
                    $completionpercentage = $DB->get_field_sql($data->subquery);
                }else{
                    $completionpercentage = $row->{$data->column};
                }
                $completionpercentage = !empty($completionpercentage) ? $completionpercentage : 0;
                return "<div class='spark-report' id='".html_writer::random_id()."' data-sparkline='$completionpercentage; progressbar'
                         data-labels = 'inprogress, completed' data-link='' >" . $completionpercentage . "</div>";
                break;*/
            case 'upcomingdeadline':
                if(!isset($row->upcomingdeadline) && isset($data->subquery)){
                    $upcomingdeadline = $DB->get_field_sql($data->subquery);
                }else{
                    $upcomingdeadline = $row->{$data->column};
                }
                $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                    array('id' => $reportid, 'filter_coursevendors' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'upcoming'));
                if(empty($courseoverviewpermissions) || empty($reportid)){
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
                    $overduedeadline = $row->{$data->column};
                }
                $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                    array('id' => $reportid, 'filter_coursevendors' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'overdue'));
                if(empty($courseoverviewpermissions) || empty($reportid)){
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
                    $upcomingexpiry = $row->{$data->column};
                }
                $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                    array('id' => $reportid, 'filter_coursevendors' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'upcomingexpiry'));
                if(empty($courseoverviewpermissions) || empty($reportid)){
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
                    $upcomingendoflife = $row->{$data->column};
                }
                $allurl = new moodle_url('/blocks/learnerscript/viewreport.php',
                    array('id' => $reportid, 'filter_coursevendors' => $row->id, 'filter_organization' => $this->reportfilterparams['filter_organization'], 'filter_departments' => $this->reportfilterparams['filter_departments'],'filter_subdepartments' => $this->reportfilterparams['filter_subdepartments'], 'filter_status' => 'upcomingendoflife'));
                if(empty($courseoverviewpermissions) || empty($reportid)){
                    $row->{$data->column} = $upcomingendoflife;
                } else{
                    $row->{$data->column} = html_writer::tag('a', $upcomingendoflife,
                    array('href' => $allurl));
                }
            break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}
