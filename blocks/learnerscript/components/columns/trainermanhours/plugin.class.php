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

class plugin_trainermanhours extends pluginbase {

    public function init() {
        $this->fullname = get_string('trainermanhours', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('trainermanhours');
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
        global $CFG,$DB;   
        switch ($data->column) {
            case 'traininghours':                
                $query = "SELECT SUM(round(cs.duration/60, 2)) 
                            FROM {local_classroom_sessions} cs
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME($row->startdate))
                            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME($row->startdate)) AND c.status IN (1,4)
                            AND cs.trainerid = :trainerid AND c.id = :classroomid";
                $params     = array('trainerid'=>$row->userid , 'classroomid' => $row->classroomid);
                $traininghours = $DB->get_field_sql($query, $params);
                if ($traininghours) {
                    $row->{$data->column} = $traininghours;
                } else {
                    $row->{$data->column} = 0;
                }
                break;
            case 'userscovered':
               
                $query = "SELECT count(distinct cat.userid) 
                            FROM {local_classroom_attendance} cat
                            JOIN {local_classroom_sessions} cs  ON cat.sessionid = cs.id AND cat.status = 1
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME($row->startdate))
                            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME($row->startdate)) AND c.status IN (1,4)
                            AND cs.trainerid = :trainerid AND c.id = :classroomid";
                $params     = array('trainerid'=>$row->userid , 'classroomid' => $row->classroomid);
                $userscovered = $DB->count_records_sql($query, $params);
                if ($userscovered) {
                    $row->{$data->column} = $userscovered;
                } else {
                    $row->{$data->column} = 0;
                }
                break;
           
            default:
                return false;
                break;          
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }

}
