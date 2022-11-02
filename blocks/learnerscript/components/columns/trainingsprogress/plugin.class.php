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

class plugin_trainingsprogress extends pluginbase {

    public function init() {
        $this->fullname = get_string('trainingsprogress', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('trainingsprogress');
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
        global $DB, $CFG;
        // print_object($row);
        // print_object($data);
        // $trsql = "SELECT SUM(cs.duration) AS sessionsduration
        //                 FROM {local_classroom_sessions} cs
        //                 JOIN {local_classroom} c ON cs.classroomid = c.id
        //                 WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = $row->year
        //                 AND MONTH(FROM_UNIXTIME(cs.timestart)) = $row->month ";

        // $trainginghrs = $DB->get_record_sql($trsql);
        // $trhours = $trainginghrs->sessionsduration/60;

        // $usrsql = "SELECT count(cat.id) as userscovered
        //                 FROM {local_classroom_attendance} cat
        //                 JOIN {local_classroom_sessions} cs  ON cat.sessionid = cs.id AND cat.status = 1
        //                 JOIN {local_classroom} c ON cs.classroomid = c.id
        //                 WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = $row->year
        //                 AND MONTH(FROM_UNIXTIME(cs.timestart)) = $row->month";
        // $trainedusers = $DB->get_record_sql($usrsql);

        // switch ($data->column) {
        //     case 'traininghours':
        //     if ($trhours)             
        //         $row->{$data->column} = round($trhours, 2);  
        //         else                  
        //         $row->{$data->column} = '--';                    
        //     break;
        //     case 'trainingdays':
        //     if ($trhours)
        //         $row->{$data->column} = round($trhours/8, 2);
        //         else                  
        //         $row->{$data->column} = '--'; 
        //     break;
        //     case 'userscovered':
        //     if ($trainedusers)
        //         $row->{$data->column} = $trainedusers->userscovered;
        //         else                  
        //         $row->{$data->column} = '--'; 
        //     break;
        //     case 'trmonth':                 
        //         $row->{$data->column} = $row->month; 
        //         break;
        //     case 'tryear':                 
        //         $row->{$data->column} = $row->year; 
        //     break;   
        // }
        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
}