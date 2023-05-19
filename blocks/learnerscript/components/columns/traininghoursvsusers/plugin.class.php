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

class plugin_traininghoursvsusers extends pluginbase {

    public function init() {
        $this->fullname = get_string('traininghoursvsusers', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('traininghoursvsusers');
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
        global $DB, $CFG,$USER;
        $costcenterpathconcatsql = '';
        if(!is_siteadmin()){
            list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$USER->open_path);
            $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path',$org);               
        }
        switch ($data->column) {
            case 'trainingdays':                
                $query = "SELECT SUM(DATEDIFF(DATE(FROM_UNIXTIME(c.enddate)), DATE(FROM_UNIXTIME(c.startdate))))
                            FROM {local_classroom} c 
                            WHERE YEAR(FROM_UNIXTIME(c.startdate)) = YEAR(FROM_UNIXTIME(c.startdate))
                            AND MONTH(FROM_UNIXTIME(c.startdate)) = MONTH(FROM_UNIXTIME(c.startdate)) AND (c.status = 1 OR c.status = 4) 
                            AND YEAR(FROM_UNIXTIME(c.startdate)) =:cyear AND FROM_UNIXTIME(c.startdate, '%M') = :cmonth  $costcenterpathconcatsql ";
                $params     = array('cyear'=>$row->year , 'cmonth' => $row->month);
               
                $trainingdays = $DB->get_field_sql($query, $params);
                if ($trainingdays) {
                    $row->{$data->column} = $trainingdays;
                } else {
                    $row->{$data->column} = 0;
                }
                break;     
            case 'monthyear':
                $row->{$data->column} = ($row->{$data->column}) ? ($row->{$data->column}) : '--';
            break;
            case 'month':
                $row->{$data->column} = ($row->{$data->column}) ? ($row->{$data->column}) : '--';
            break;
            case 'year':
                $row->{$data->column} = ($row->{$data->column}) ? ($row->{$data->column}) : '--';
            break;
            case 'totaltrainings':
                $query = " SELECT count(id) 
                            FROM {local_classroom} c 
                            WHERE YEAR(FROM_UNIXTIME(c.startdate)) = YEAR(FROM_UNIXTIME(c.startdate))
                            AND MONTH(FROM_UNIXTIME(c.startdate)) = MONTH(FROM_UNIXTIME(c.startdate)) AND (c.status = 1 OR c.status = 4)
                            AND YEAR(FROM_UNIXTIME(c.startdate)) =:cyear AND FROM_UNIXTIME(c.startdate, '%M') = :cmonth  $costcenterpathconcatsql ";
                $params     = array('cyear'=>$row->year , 'cmonth' => $row->month);
            
                $totaltrainings = $DB->get_field_sql($query, $params);
               
                $row->{$data->column} = ($totaltrainings) ? ($totaltrainings) : '--';
            break;
            case 'traininghours':
                $query = "SELECT SUM(cs.duration) 
                            FROM {local_classroom_sessions} cs
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(c.startdate))
                            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(c.startdate)) AND (c.status = 1 OR c.status = 4)
                            AND YEAR(FROM_UNIXTIME(c.startdate)) =:cyear AND FROM_UNIXTIME(c.startdate, '%M') = :cmonth  $costcenterpathconcatsql ";
                $params     = array('cyear'=>$row->year , 'cmonth' => $row->month);
            
                $totaltrainings = $DB->get_field_sql($query, $params);
                // $totaltrainings = intdiv( $totaltrainings, 60).'H :'. ( $totaltrainings % 60) .'M';
                $hours = floor($totaltrainings / 60);
                $minutes = ($totaltrainings % 60);
                $totaltrainings = sprintf("%d:%02d", $hours, $minutes);
                $row->{$data->column} = ( $totaltrainings ) ? sprintf("%d:%02d", $hours, $minutes) : '--';
            break;
            case 'userscovered':
                $query = "SELECT count(distinct cat.userid) 
                            FROM {local_classroom_attendance} cat
                            JOIN {local_classroom_sessions} cs  ON cat.sessionid = cs.id AND cat.status = 1
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE YEAR(FROM_UNIXTIME(cs.timestart)) = YEAR(FROM_UNIXTIME(c.startdate))
                            AND MONTH(FROM_UNIXTIME(cs.timestart)) = MONTH(FROM_UNIXTIME(c.startdate)) AND (c.status = 1 OR c.status = 4) 
                            AND YEAR(FROM_UNIXTIME(c.startdate)) =:cyear AND FROM_UNIXTIME(c.startdate, '%M') = :cmonth  $costcenterpathconcatsql ";
                $params     = array('cyear'=>$row->year , 'cmonth' => $row->month);
            
                $userscovered = $DB->get_field_sql($query, $params);
                $row->{$data->column} = ($userscovered) ? ($userscovered) : '--';
            break;
            default:
                return false;
                break;          
        }

        return (isset($row->{$data->column}))? $row->{$data->column} : ' -- ';
    }
}