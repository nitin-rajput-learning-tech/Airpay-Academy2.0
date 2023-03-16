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

class plugin_trainingsoverview extends pluginbase {

    public function init() {
        $this->fullname = get_string('trainingsoverview', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('trainingsoverview');
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
            case 'pastdate':
                $pastdatesql=" SELECT DATE(FROM_UNIXTIME(timestart)) 
                                FROM {local_classroom_sessions} AS cs                            
                                WHERE 1 = 1 AND timefinish < $time AND cs.id = :sessionid";
                $params     = array('sessionid'=>$row->id);
                $pastdate = $DB->get_field_sql($pastdatesql, $params);

                if($pastdate){
                    $row->{$data->column} = $pastdate;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'pasttime':
                $pasttime  = date("H:i:s", $row->timestart) . '-' . date("H:i:s", $row->timefinish);
                if($pasttime){
                    $row->{$data->column} = $pasttime;
                }else{
                    $row->{$data->column} = 0;
                }     
            break; 
            case 'futuredate':
                $futuredatesql=" SELECT DATE(FROM_UNIXTIME(timestart)) 
                                    FROM {local_classroom_sessions} AS cs                            
                                    WHERE 1 = 1 AND timefinish > $time AND cs.id = :sessionid";
                $params     = array('sessionid'=>$row->id);
	            $completedtrainings = $DB->get_field_sql($futuredatesql, $params);

                if($completedtrainings){
                    $row->{$data->column} = $completedtrainings;
                }else{
                    $row->{$data->column} = 0;
                }                 
            break;

            case 'futuretime':
                $futuretime  = date("H:i:s", $row->timestart) . '-' . date("H:i:s", $row->timefinish);
                if($futuretime){
                    $row->{$data->column} = $futuretime;
                }else{
                    $row->{$data->column} = 0;
                }    
            break; 
            case 'sessiontime':
                $sessiontime  = date("H:i:s", $row->timestart) . '-' . date("H:i:s", $row->timefinish);
                if($sessiontime){
                    $row->{$data->column} = $sessiontime;
                }else{
                    $row->{$data->column} = 0;
                }    
            break; 
            
            case 'type':
                $type=get_string('pluginname', 'local_classroom');
                if($row->onlinesession==1){
                    $link=get_string('online', 'local_classroom'); 
                }
                if($type){
                    $row->{$data->column} = $type;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'status':
                $attendancestatus=$DB->get_field_sql("SELECT status  FROM {local_classroom_attendance} ca
                                                        JOIN {user} As u ON u.id = ca.userid
                                                        WHERE classroomid = :classroomid and sessionid = :sessionid  and status = :status",
                                    array('classroomid' => $row->classroomid,'sessionid' =>$row->id,'status' => 1)); 
                if ($row->timefinish <= time() && $attendancestatus == 1) {
                    $status = '<span class="tag tag-success">'.get_string('completed', 'local_classroom').'</span>';
                } else {
                    $status = '<span class="tag tag-warning">'.get_string('pending', 'local_classroom').'</span>';
                }                
                if($status){
                    $row->{$data->column} = $status;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'attendedusers':               
                $params['classroomid'] = $row->classroomid;
                $params['confirmed'] = 1;
                $params['suspended'] = 0;
                $params['deleted'] = 0;
                $sql = " SELECT COUNT(DISTINCT u.id)  FROM {user} AS u
                        JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                         WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                            AND u.deleted = :deleted AND cu.classroomid = :classroomid";
                $classroom_totalusers = $DB->count_records_sql($sql, $params); 
                $attendedsessions_users = $DB->count_records('local_classroom_attendance',
                array('classroomid' => $row->classroomid, 'sessionid' =>$row->id, 'status' => 1));
                $attendedusers = $attendedsessions_users. '/' .$classroom_totalusers;
                if($attendedusers){
                    $row->{$data->column} = $attendedusers;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
        
        default:
            $row->{$data->column} = isset($row->{$data->column}) ? $row->{$data->column} : $row->{$data->column};
            break;  
        }

        return (isset($row->{$data->column}))? $row->{$data->column} : ' N/A ';
    }  
}