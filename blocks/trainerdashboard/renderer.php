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
defined('MOODLE_INTERNAL') || die();
define('SESSION_PRESENT', 1);

class block_trainerdashboard_renderer extends plugin_renderer_base {

    /**
     * Display the trainerdashboard tabs
     * @return string The text to render
     */
    public function get_trainerdashboards($trainerdashboardtype) {
        global $CFG, $OUTPUT,$PAGE,$USER;

        $function='block_trainerdashboard_manager::' . $trainerdashboardtype . '';


        $context = (new \local_costcenter\lib\accesslib())::get_module_context();

        $stable = new stdClass();
        $stable->thead = true;
        $stable->start = 0;
        $stable->length = -1;
        $stable->search = '';
        $stable->pagetype ='page';

        $collapse = true;
        $show = '';
        $filterdata = json_encode(array());

        $dataoptions = array('userid' => $USER->id);

    	$options = array('targetID' => '' . $trainerdashboardtype . 'dashboards','perPage' => 10, 'cardClass' => 'col-xl-4 col-md-6 col-12','viewType' => 'table');

    

        $dataoptions['trainerdashboardstatus'] =$function;
        $options['targetID']='' . $trainerdashboardtype . 'dashboards';
        $options['methodName']='block_trainerdashboard_get_' . $trainerdashboardtype . '';
        $options['templateName']='block_trainerdashboard/' . $trainerdashboardtype . '';

        $carddataoptions = json_encode($dataoptions);
        $cardoptions = json_encode($options);
        $cardparams = array(
            'targetID' => '' . $trainerdashboardtype . 'dashboards',
            'options' => $cardoptions,
            'dataoptions' => $carddataoptions,
            'filterdata' => $filterdata,
        );
        $fncardparams=$cardparams;
       

        $tabs[] = array('active' => 'active','type' => '' . $trainerdashboardtype . 'dashboards', 'filterform' =>'', 'canfilter' => false, 'show' => '','name' => get_string('' . $trainerdashboardtype . 'dashboards', 'block_trainerdashboard'),'trainerdashboardstatus'=>$function);
    

        $cardparams = $fncardparams+array(
            'tabs' => $tabs,
            'contextid' => $context->id,
            'plugintype' => 'block',
            'plugin_name' =>'trainerdashboard',
            'cfg' => $CFG,
            'filters'=>get_string('filters','block_trainerdashboard'));
        return  $this->render_from_template('block_trainerdashboard/trainerdashboardtabs', $cardparams);
    }
    public function get_trainerslist ($trainerslist,$filterdata=null) {

        global $USER,$OUTPUT,$PAGE,$CFG,$DB;

        $time=time();

        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $formattedtrainerslist=array_values($trainerslist['trainerslist']);

        $countsql= "SELECT COUNT(cs.id) 
                    FROM {local_classroom} AS cs
                    JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                    WHERE ct.trainerid = :trainerid ";

        $row = [];
        foreach ($formattedtrainerslist as $records) {
            $record = array();
            $record['userid']=$records->id;
            $record['username']=$records->firstname.' '.$records->firstname;
            $viewmorestatus=json_encode(array('value'=>$record['username']));


            $params     = array('trainerid'=>$records->id);
       
	        try {
	        	$conducted=" AND cs.status IN (4) ";
	            $conductedtrainings = $DB->count_records_sql($countsql.$conducted, $params);

	            $upcoming=" AND cs.status IN (1) AND cs.startdate > $time ";
	            $upcomingtrainings = $DB->count_records_sql($countsql.$upcoming, $params);

                $total=" AND cs.status IN (1,4) ";
                $totaltrainings = $DB->count_records_sql($countsql.$total, $params);

                $totalcountsql= "SELECT SUM((SELECT COUNT(id) FROM {local_classroom_users} where classroomid=cs.id)) as totaluserscovered
                    FROM {local_classroom} AS cs
                    JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                    WHERE ct.trainerid = :trainerid ";

                $totaluserscovered = $DB->get_field_sql($totalcountsql.$total, $params);


	        } catch (dml_exception $ex) {
                $totaltrainings=0;
	            $conductedtrainings = 0;
	            $upcomingtrainings = 0;
                $totaluserscovered=0;
	        }

            $user_picture = new user_picture($records, array('size' => 60, 'class' => 'userpic', 'link'=>false));
        	$user_picture = $user_picture->get_url($PAGE);
        	$userpic = $user_picture->out();
            $record['userpicture']=$userpic;
           
            $record['useremail']=$records->email;
            $record['useropen_employeeid']=$records->open_employeeid;
            $record['total_classroomtrainings']= $totaltrainings;
            $record['completed_classroomtrainings']= $conductedtrainings;
            $record['upcoming_classroomtrainings']= $upcomingtrainings;
            $record['totaluserscovered']= $totaluserscovered ? $totaluserscovered : 0 ;
            $record['viewmorestatus']=$viewmorestatus;
            $row[]=$record;
        }
        $chartdata=array();
        $countsql= "SELECT COUNT(cs.id) 
                    FROM {local_classroom} AS cs
                    JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                    WHERE 2=2 ";

        $totalcountsql= "SELECT SUM((SELECT COUNT(id) FROM {local_classroom_users} where classroomid=cs.id)) as totaluserscovered
                FROM {local_classroom} AS cs
                JOIN {local_classroom_trainers} AS ct ON ct.classroomid=cs.id
                WHERE 2=2 ";

        $innersql=" ";    
        $traininer=0;    



        $innersql .= (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='cs.open_path');

        if(!is_siteadmin() && has_capability('local/classroom:trainer_viewclassroom', $context)){
            $traininer=1;
            $innersql.= " AND ct.trainerid = :trainerid ";
            $params['trainerid'] = $USER->id;
        }

       
        $conducted=" AND cs.status IN (4) ";
        $chartdata['conductedtrainings'] = $DB->count_records_sql($countsql.$innersql.$conducted, $params);

        $upcoming=" AND cs.status IN (1) AND cs.startdate > $time ";
        $chartdata['upcomingtrainings'] = $DB->count_records_sql($countsql.$innersql.$upcoming, $params);

        $total=" AND cs.status IN (1,4) ";
        $chartdata['totaltrainings'] = $DB->count_records_sql($countsql.$innersql.$total, $params);



        $chartdata['totaluserscovered'] = $DB->get_field_sql($totalcountsql.$innersql.$total, $params);


        return array('data'=>array_values($row),'chartdata'=>$chartdata,'traininer'=>$traininer);
    }
    public function get_conductedtrainings ($conductedtrainings,$filterdata=null) {
        global $OUTPUT, $CFG, $DB,$USER;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $formattedconductedtrainings=array_values($conductedtrainings['conductedtrainings']);
        $row = [];
        foreach ($formattedconductedtrainings as $sdata) {
            $line = array();
                $line['cfgwwwroot'] = $CFG->wwwroot;
                $line['id'] = $sdata->id;
                $line['name'] = $sdata->name;
                if($triggertype!='classroom'){
                    $classroomid= $sdata->classroomid;
                    $line['classroomname'] = $DB->get_field('local_classroom','name',array('id'=>$classroomid));
                }
                $line['date'] = date("d/m/Y", $sdata->timestart);
                $line['starttime'] = date("H:i:s", $sdata->timestart);
                $line['endtime'] = date("H:i:s", $sdata->timefinish);
                $link=get_string('pluginname', 'local_classroom');
                if($sdata->onlinesession==1){
                       
                        $moduleids = $DB->get_field('modules', 'id', array('name' =>$sdata->moduletype));
                        if($moduleids){
                            $moduleid = $DB->get_field('course_modules', 'id', array('instance' => $sdata->moduleid, 'module' => $moduleids));
                            if($moduleid){
                                $link=html_writer::link($CFG->wwwroot . '/mod/' .$sdata->moduletype. '/view.php?id=' . $moduleid,get_string('join', 'local_classroom'), array('title' => get_string('join', 'local_classroom')));
                                
                                if (!is_siteadmin() && !has_capability('local/classroom:manageclassroom', $context)) {
                                    $userenrolstatus = $DB->record_exists('local_classroom_users', array('classroomid' => $classroomid, 'userid' => $USER->id));
                                   
                                    if (!$userenrolstatus) {
                                        $link=get_string('join', 'local_classroom');
                            
                                    }
                                }
                                
                            }
                        }   
                }
                $line['link'] = $link;
                $line['room'] = $sdata->room ? $sdata->room : 'N/A';
                
                $countfields = "SELECT COUNT(DISTINCT u.id) ";
                $params['classroomid'] = $classroomid;
                $params['confirmed'] = 1;
                $params['suspended'] = 0;
                $params['deleted'] = 0;
                $sql = " FROM {user} AS u
                        JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                         WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                            AND u.deleted = :deleted AND cu.classroomid = :classroomid";
                $classroom_totalusers     =$DB->count_records_sql($countfields . $sql, $params);

 
                $attendedsessions_users = $DB->count_records('local_classroom_attendance',
                array('classroomid' => $classroomid,
                    'sessionid' =>$sdata->id, 'status' => SESSION_PRESENT));

                if(has_capability('local/classroom:manageclassroom', $context)){
                    if ($sdata->timefinish <= time() && $sdata->attendance_status == 1) {
                        $line['status'] = '<span class="tag tag-success">'.get_string('completed', 'local_classroom').'</span>';
                    } else {
                        $line['status'] = '<span class="tag tag-warning">'.get_string('pending', 'local_classroom').'</span>';
                    }
                }else{
                    $attendance_status=$DB->get_field_sql("SELECT status  FROM {local_classroom_attendance} where classroomid = :classroomid and sessionid = :sessionid and userid = :userid and status = :status",array('classroomid' => $classroomid,'sessionid' =>$sdata->id,'userid' => $USER->id,'status' => 1));
                    if ($sdata->timefinish <= time() && $attendance_status == 1) {
                        $line['status'] = '<span class="tag tag-success">'.get_string('completed', 'local_classroom').'</span>';
                    } else {
                        $line['status'] = '<span class="tag tag-warning">'.get_string('pending', 'local_classroom').'</span>';
                    }
                    
                }
               $line['attendacecount'] = $attendedsessions_users. '/' .$classroom_totalusers;
                if($sdata->trainerid){
                     $trainer = $DB->get_record('user', array('id' => $sdata->trainerid));
                     $trainerimg = $OUTPUT->user_picture($trainer, array('size' => 30)) . fullname($trainer);
                     $line['trainer'] =  $trainerimg;
                }else{
                     $line['trainer'] ="N/A";
                }

            $row[] = $line;
        }
        return array_values($row);
    }
    public function get_depttrainingavg ($depttrainingavg,$filterdata=null) {
        global $USER;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $formatteddepttrainingavg=array_values($depttrainingavg['depttrainingavg']);
        $row = [];
        foreach ($formatteddepttrainingavg as $records) {
            $record = array();
            $record['username']='hi';
         }
        return array_values($row);
    }
    public function get_trainermanhours ($trainermanhours,$filterdata=null) {
        global $USER;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $formattedtrainermanhours=array_values($trainermanhours['trainermanhours']);
        $row = [];
        foreach ($formattedtrainermanhours as $records) {
            $record = array();
            $record['username']='hi';
         }
        return array_values($row);
    }
    public function get_upcomingtrainings ($upcomingtrainings,$filterdata=null) {
        global $OUTPUT, $CFG, $DB,$USER;
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $formattedupcomingtrainings=array_values($upcomingtrainings['upcomingtrainings']);
        $row = [];
        foreach ($formattedupcomingtrainings as $sdata) {
            $line = array();
                $line['cfgwwwroot'] = $CFG->wwwroot;
                $line['id'] = $sdata->id;
                $line['name'] = $sdata->name;
                if($triggertype!='classroom'){
                    $classroomid= $sdata->classroomid;
                    $line['classroomname'] = $DB->get_field('local_classroom','name',array('id'=>$classroomid));
                }
                $line['date'] = date("d/m/Y", $sdata->timestart);
                $line['starttime'] = date("H:i:s", $sdata->timestart);
                $line['endtime'] = date("H:i:s", $sdata->timefinish);
                $link=get_string('pluginname', 'local_classroom');
                if($sdata->onlinesession==1){
                       
                        $moduleids = $DB->get_field('modules', 'id', array('name' =>$sdata->moduletype));
                        if($moduleids){
                            $moduleid = $DB->get_field('course_modules', 'id', array('instance' => $sdata->moduleid, 'module' => $moduleids));
                            if($moduleid){
                                $link=html_writer::link($CFG->wwwroot . '/mod/' .$sdata->moduletype. '/view.php?id=' . $moduleid,get_string('join', 'local_classroom'), array('title' => get_string('join', 'local_classroom')));
                                
                                if (!is_siteadmin() && !has_capability('local/classroom:manageclassroom', $context)) {
                                    $userenrolstatus = $DB->record_exists('local_classroom_users', array('classroomid' => $classroomid, 'userid' => $USER->id));
                                   
                                    if (!$userenrolstatus) {
                                        $link=get_string('join', 'local_classroom');
                            
                                    }
                                }
                                
                            }
                        }   
                }
                $line['link'] = $link;
                $line['room'] = $sdata->room ? $sdata->room : 'N/A';
                
                $countfields = "SELECT COUNT(DISTINCT u.id) ";
                $params['classroomid'] = $classroomid;
                $params['confirmed'] = 1;
                $params['suspended'] = 0;
                $params['deleted'] = 0;
                $sql = " FROM {user} AS u
                        JOIN {local_classroom_users} AS cu ON cu.userid = u.id
                         WHERE u.id > 2 AND u.confirmed = :confirmed AND u.suspended = :suspended
                            AND u.deleted = :deleted AND cu.classroomid = :classroomid";
                $classroom_totalusers     =$DB->count_records_sql($countfields . $sql, $params);

 
                $attendedsessions_users = $DB->count_records('local_classroom_attendance',
                array('classroomid' => $classroomid,
                    'sessionid' =>$sdata->id, 'status' => SESSION_PRESENT));

                if(has_capability('local/classroom:manageclassroom', $context)){
                    if ($sdata->timefinish <= time() && $sdata->attendance_status == 1) {
                        $line['status'] = '<span class="tag tag-success">'.get_string('completed', 'local_classroom').'</span>';
                    } else {
                        $line['status'] = '<span class="tag tag-warning">'.get_string('pending', 'local_classroom').'</span>';
                    }
               
                }else{
                    $attendance_status=$DB->get_field_sql("SELECT status  FROM {local_classroom_attendance} where classroomid = :classroomid and sessionid = :sessionid and userid = :userid and status = :status",array('classroomid' => $classroomid,'sessionid' =>$sdata->id,'userid' => $USER->id,'status' => 1));
                    if ($sdata->timefinish <= time() && $attendance_status == 1) {
                        $line['status'] = '<span class="tag tag-success">'.get_string('completed', 'local_classroom').'</span>';
                    } else {
                        $line['status'] = '<span class="tag tag-warning">'.get_string('pending', 'local_classroom').'</span>';
                    }
                }
               $line['attendacecount'] = $attendedsessions_users. '/' .$classroom_totalusers;
                if($sdata->trainerid){
                     $trainer = $DB->get_record('user', array('id' => $sdata->trainerid));
                     $trainerimg = $OUTPUT->user_picture($trainer, array('size' => 30)) . fullname($trainer);
                     $line['trainer'] =  $trainerimg;
                }else{
                     $line['trainer'] ="N/A";
                }

            $row[] = $line;
         }
        return array_values($row);
    }
    public function get_classrooms ($classroomslist,$filterdata=null) {

        global $USER,$OUTPUT,$PAGE,$CFG,$DB;

        $time=time();

        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $formattedclassroomslist=array_values($classroomslist['classrooms']);

        $row = [];
        foreach ($formattedclassroomslist as $records) {
            $record = array();
            $record['cfgwwwroot'] = $CFG->wwwroot;
            $record['classroomname']=$records->name;
            $record['sessioncount'] = $records->sessionscount;
            $row[]=$record;
         }
        return array_values($row);
    }
}
