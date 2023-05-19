<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
class block_trainerdashboard_manager {

	const TRAINERLIST = 'trainerslist';//List the trainer Details
    const CONDUCTEDTRAININGS = 'conductedtrainings';//Count of Training conducted in last 3 to 6Months and their stats details 
    const TRAINERMANHOURS = 'trainermanhours';//Trainer wise Manhours spend list
    const DEPTTRAININGAVG = 'depttrainingavg';//Department wise training averages
    const UPCOMINGTRAININGS = 'upcomingtrainings';//Next 3 to 6monts Scheduled training list and their stats

    public static function trainerslist($stable,$filtervalues){
        global $DB,$USER,$COURSE,$CFG;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();

        $roleid = $DB->get_field('role', 'id', array('shortname' => 'trainer'));

        $countsql = "SELECT COUNT(ra.id) ";

        $fromsql = "SELECT u.id,u.picture,u.imagealt,u.firstname,u.lastname,
        			u.email,u.open_employeeid";

      	$sql=" FROM {role_assignments} AS ra 
	        		JOIN {user} AS u on u.id=ra.userid ";

	    $sql.=" WHERE ra.roleid=:roleid  ";

        if (!empty($stable->search_query)) {
            $fields = array(
                0 => 'u.firstname',
                1 => 'u.lastname'
            );
            $fields = implode(" LIKE '%" . $stable->search_query . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search_query . "%' ";
            $sql .= " AND ($fields) ";
        }
	    $params=array('roleid' => $roleid);

        $sql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

        if(!is_siteadmin() && has_capability('local/classroom:trainer_viewclassroom', $context)){
            $traininer=1;
            $sql.= " AND u.id = :trainerid ";
            $params['trainerid'] = $USER->id;
        }

        try {
            $trainerslistcount = $DB->count_records_sql($countsql.$sql, $params);
             
        
            if ($stable->thead == false) {
    
                $trainerslist = $DB->get_records_sql($fromsql.$sql, $params, $stable->start, $stable->length);
               
            }
        } catch (dml_exception $ex) {
        	 $trainerslist=array();
             $trainerslistcount = 0;
        }
        return compact('trainerslist', 'trainerslistcount');
    } 
    public static function conductedtrainings($stable,$filtervalues){
        global $DB,$USER,$COURSE,$CFG;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
 
        $concatsql = '';
        if (!empty($stable->search)) {
            $fields = array(
                0 => 'cs.name',
                1 => 'cr.name'
            );
            $fields = implode(" LIKE '%" . $stable->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search . "%' ";
            $concatsql .= " AND ($fields) ";
        }
        $params     = array();
        $countsql   = "SELECT COUNT(cs.id) ";
        $fromsql    = "SELECT cs.*, cr.name as room";
        $sql        = " FROM {local_classroom_sessions} AS cs
                LEFT JOIN {user} AS u ON u.id = cs.trainerid
                LEFT JOIN {local_location_room} AS cr ON cr.id = cs.roomid
                WHERE 1 = 1 ";
        $sql .= $concatsql;

        $time=time();
        //$sql .= " AND timefinish < $time";
        //$sql .= " AND cs.timefinish < date_sub(now(), interval 6 month)  ";
        //$sql .= " FROM_UNIXTIME(cs.timefinish,'%Y-%m-%d') > DATE_SUB(CURDATE(), INTERVAL 6 Month) ";
        $enddate = strtotime("-6 months", strtotime(date('Y-m-d')));
        $sql .= " AND cs.timefinish BETWEEN $enddate AND " .time(). " ";

        if (!empty($stable->search_query)) {
            $fields = array(
                0 => 'u.firstname',
                1 => 'u.lastname'
            );
            $fields = implode(" LIKE '%" . $stable->search_query . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search_query . "%' ";
            $sql .= " AND ($fields) ";
        }

        $sql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

        if(!is_siteadmin() &&has_capability('local/classroom:trainer_viewclassroom', $context)){
            $sql.= " AND u.id = :trainerid ";
            $params['trainerid'] = $USER->id;
        }

        try {
            $conductedtrainingscount = $DB->count_records_sql($countsql . $sql, $params);
            if ($stable->thead == false) {
                $sql .= " ORDER BY cs.timestart ASC";
                $conductedtrainings = $DB->get_records_sql($fromsql . $sql, $params, $stable->start, $stable->length);
            }
        } catch (dml_exception $ex) {
            $conductedtrainingscount = 0;
            $conductedtrainings=array();
        }
        return compact('conductedtrainings', 'conductedtrainingscount');
    } 
    public static function depttrainingavg($stable,$filtervalues){
        global $DB,$USER,$COURSE,$CFG;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        try {
             
            // $depttrainingavgcount = $DB->count_records_sql($sql, $params);
        
            // if ($stable->thead == false) {

            //     $depttrainingavg = $DB->get_records_sql($sql, $params, $stable->start, $stable->length);
               
                
            // }
             $depttrainingavg=array();
             $depttrainingavgcount = 0;
        } catch (dml_exception $ex) {
        	 $depttrainingavg=array();
             $depttrainingavgcount = 0;
        }
        return compact('depttrainingavg', 'depttrainingavgcount');
    } 
    public static function trainermanhours($stable,$filtervalues){
        global $DB,$USER,$COURSE,$CFG;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        try {
            $roleid = $DB->get_field('role', 'id', array('shortname' => 'trainer'));
            $trainers = $DB->get_fieldset_sql(" SELECT u.id FROM {role_assignments} AS ra JOIN {user} AS u on u.id=ra.userid WHERE 1=1 AND ra.roleid=:roleid" , array('roleid' =>$roleid));
            $trainerids = implode(',',$trainers);
            $countsql   = "SELECT COUNT(cs.id) ";
            $sql = "SELECT  cs.* ";
            $fromsql =  " FROM {local_classroom_sessions} cs
                            JOIN {user} AS u ON u.id = cs.trainerid
                            JOIN {local_classroom} c ON cs.classroomid = c.id
                            WHERE (c.status = 1 OR c.status = 4) AND cs.trainerid IN ($trainerids)";
            if (!empty($stable->search_query)) {
                $fields = array(
                    0 => 'u.firstname',
                    1 => 'u.lastname'
                );
                $fields = implode(" LIKE '%" . $stable->search_query . "%' OR ", $fields);
                $fields .= " LIKE '%" . $stable->search_query . "%' ";
                $fromsql .= " AND ($fields) ";
            }
            $fromsql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

            if(!is_siteadmin() &&has_capability('local/classroom:trainer_viewclassroom', $context)){
                $fromsql.= " AND u.id = :trainerid ";
                $params['trainerid'] = $USER->id;
            }
    
            $fromsql .= " ORDER BY cs.timestart ASC";
            $trainermanhours = $DB->get_records_sql($sql . $fromsql, $params, $stable->start, $stable->length);
            $trainermanhourscount = $DB->count_records_sql($countsql. $fromsql, array());       
           
            //SUM(round(cs.duration/60, 2)) 
            //$trainermanhours = $DB->get_records_sql($sql. $fromsql, array()); 
            
        } catch (dml_exception $ex) {
        	 $trainermanhours=array();
             $trainermanhourscount = 0;
        }
      
        return compact('trainermanhours', 'trainermanhourscount');
    }
    public static function upcomingtrainings($stable,$filtervalues){
        global $DB,$USER,$COURSE,$CFG;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();

        $concatsql = '';
        if (!empty($stable->search)) {
            $fields = array(
                0 => 'cs.name',
                1 => 'cr.name'
            );
            $fields = implode(" LIKE '%" . $stable->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search . "%' ";
            $concatsql .= " AND ($fields) ";
        }
        $params     = array();
        $countsql   = "SELECT COUNT(cs.id) ";
        $fromsql    = "SELECT cs.*, cr.name as room";
        $sql        = " FROM {local_classroom_sessions} AS cs
                LEFT JOIN {user} AS u ON u.id = cs.trainerid
                LEFT JOIN {local_location_room} AS cr ON cr.id = cs.roomid
                WHERE 1 = 1 ";
        $sql .= $concatsql;

        $time=time();
        //$sql .= " AND timefinish > $time";
        //$sql .= " AND cs.timestart > date_sub(now(), interval 6 month) ";
       //$sql .= " AND FROM_UNIXTIME(cs.timestart,'%Y-%m-%d') BETWEEN CURDATE() AND  DATE_ADD(CURDATE(), interval 6 month) ";
        $enddate = strtotime("+6 months", strtotime(date('Y-m-d')));
        $sql .= " AND cs.timestart BETWEEN " .time(). " AND $enddate  ";

        if (!empty($stable->search_query)) {
            $fields = array(
                0 => 'u.firstname',
                1 => 'u.lastname'
            );
            $fields = implode(" LIKE '%" . $stable->search_query . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search_query . "%' ";
            $sql .= " AND ($fields) ";
        }

        $sql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

        if(!is_siteadmin() &&has_capability('local/classroom:trainer_viewclassroom', $context)){
            $sql.= " AND u.id = :trainerid ";
            $params['trainerid'] = $USER->id;
        }
 
        try {
            $upcomingtrainingscount = $DB->count_records_sql($countsql . $sql, $params);

            if ($stable->thead == false) {
                $sql .= " ORDER BY cs.timestart ASC";
                $upcomingtrainings = $DB->get_records_sql($fromsql . $sql, $params, $stable->start, $stable->length);
            }
        } catch (dml_exception $ex) {
            $upcomingtrainingscount = 0;
            $upcomingtrainings=array();
        }
        return compact('upcomingtrainings', 'upcomingtrainingscount');
    }  
    public static function get_classrooms($stable,$filtervalues){
        global $DB,$USER,$COURSE,$CFG;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();

        $time=time();

        $params     = array();
        $countsql   = "SELECT COUNT(cs.id) ";
        $fromsql    = "SELECT cs.id,cs.name,(SELECT COUNT(id) FROM {local_classroom_sessions} WHERE classroomid=cs.id AND trainerid=$stable->trainerid ) AS sessionscount ";
        $sql        = " FROM {local_classroom} AS cs
                        JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                        WHERE ct.trainerid = :trainerid  ";

        $params['trainerid'] = $stable->trainerid;  
                      
        if (!empty($stable->search_query)) {
            $fields = array(
                0 => 'cs.name',
            );
            $fields = implode(" LIKE '%" . $stable->search_query . "%' OR ", $fields);
            $fields .= " LIKE '%" . $stable->search_query . "%' ";
            $sql .= " AND ($fields) ";
        }

        if($stable->triggertype=='totaltrainings'){
            $sql .= " AND cs.status IN (1,4) ";
        }
        elseif($stable->triggertype=='completedtrainings'){
            $sql .= " AND cs.status IN (4) ";
        }
        elseif($stable->triggertype=='upcomingtrainings'){
             $sql .= " AND cs.status IN (1) AND cs.startdate > $time ";
        }
        
        try {
            $classroomscount = $DB->count_records_sql($countsql . $sql, $params);
            if ($stable->thead == false) {
                $sql .= " ORDER BY cs.id ASC";
                $classrooms = $DB->get_records_sql($fromsql . $sql, $params, $stable->start, $stable->length);
            }
        } catch (dml_exception $ex) {
            $classroomscount = 0;
            $classrooms=array();
        }
        return compact('classrooms', 'classroomscount');
    }
}