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

class plugin_trainerslist extends pluginbase {

    public function init() {
        $this->fullname = get_string('trainerslist', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('trainerslist');
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
    
        global $DB;

        $time=time();
        switch ($data->column) {
            case 'totaltrainings':
                $totalsql=" SELECT COUNT(cs.id) 
                            FROM {local_classroom} AS cs
                            JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                            WHERE ct.trainerid = :trainerid AND cs.status IN (1,4) ";
                $params     = array('trainerid'=>$row->id);
                $totaltrainings = $DB->count_records_sql($totalsql, $params);

                if($totaltrainings){
                    $row->{$data->column} = $totaltrainings;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'completedtrainings':
                $completedsql = " SELECT COUNT(cs.id) 
                                    FROM {local_classroom} AS cs
                                    JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                                    WHERE ct.trainerid = :trainerid AND cs.status IN (4) ";
                $params     = array('trainerid'=>$row->id);
	            $completedtrainings = $DB->count_records_sql($completedsql, $params);

                if($completedtrainings){
                    $row->{$data->column} = $completedtrainings;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'upcomingtrainings':
                $upcomingsql =" SELECT COUNT(cs.id) 
                                FROM {local_classroom} AS cs
                                JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                                WHERE ct.trainerid = :trainerid  AND cs.status IN (1) AND cs.startdate > $time ";
                $params     = array('trainerid'=>$row->id);
	            $upcomingtrainings = $DB->count_records_sql($upcomingsql, $params);
                if($upcomingtrainings){
                    $row->{$data->column} = $upcomingtrainings;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'userscovered':
                $totalcountsql= "SELECT SUM((SELECT COUNT(DISTINCT(id)) FROM {local_classroom_users} where classroomid=cs.id)) as totaluserscovered
                                    FROM {local_classroom} AS cs
                                    JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                                    WHERE ct.trainerid = :trainerid AND cs.status IN (1,4) ";
                $params     = array('trainerid'=>$row->id);
                $userscovered = $DB->get_field_sql($totalcountsql, $params);
                if($userscovered){
                    $row->{$data->column} = $userscovered;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
        default:
            $row->{$data->column} = isset($row->{$data->column}) ? $row->{$data->column} : $row->{$data->column};
            break;  
        }

        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }   
}