<?php
define('AJAX_SCRIPT', true);
define('NO_OUTPUT_BUFFERING', true);
require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $PAGE,$CFG,$USER;
require_login();
$systemcontext = (new \local_learningplan\lib\accesslib())::get_module_context();
$PAGE->set_context($systemcontext);
$course = optional_param('course', 0, PARAM_INT);
$plan = optional_param('planid', 0, PARAM_INT);
$value = optional_param('value', '', PARAM_TEXT);
$start =optional_param('start',0,PARAM_INT);
$length=optional_param('length',0,PARAM_INT);
$manage=optional_param('manage',0,PARAM_INT);
$action = optional_param('action','',PARAM_TEXT);
// $moduletype=optional_param('moduletype','',PARAM_TEXT);
$costcenterid = optional_param('costcenterid', '', PARAM_INT);
// print_r($costcenterid);
// exit;
$selected_subdepts = optional_param('subdepts', null, PARAM_RAW);
$selectedcostcenterid = optional_param('costcenterid', null, PARAM_RAW);
$selecteddepartmentid = optional_param('departmentid', null, PARAM_RAW);
$selectedlearningplan = optional_param('learningplan', null, PARAM_RAW);

$department4level = optional_param('department4level', null, PARAM_RAW);
$department5level = optional_param('department5level', null, PARAM_RAW);
$states = optional_param('states', null, PARAM_RAW);
$district = optional_param('district', null, PARAM_RAW);
$subdistrict = optional_param('subdistrict', null, PARAM_RAW);
$village = optional_param('village', null, PARAM_RAW);
$selectedstatus = optional_param('status', null, PARAM_RAW);
// print_r($selectedstatus);
// exit;
$requestData = $_REQUEST;
$learningplan_lib = new local_learningplan\lib\lib();
try{
switch($action){
    case 'learningplantab':
        $view_renderer = new local_learningplan\render\view();
        $id = required_param('id',  PARAM_INT);
        $tab = required_param('tab',PARAM_TEXT);
        
        $condition = 'manage';
        if($tab == 'courses'){
            $data = $view_renderer->learningplans_courses_tab_content($id, $tab,$condition);
        }else if($tab == 'users'){
            $ajax = required_param('ajax',PARAM_TEXT);
            $data = $view_renderer->learningplans_users_tab_content($id, $tab,$condition,$ajax);
        }else if($tab == 'targetaudiences'){
            $data = $view_renderer->learningplans_target_audience_content($id, $tab,$condition);
        }else if($tab == 'requestedusers'){
            $data = $view_renderer->learningplans_requested_users_content($id, $tab, $condition);
        }
        echo json_encode($data);
    break;

    case 'userselfenrol':
        $userid = required_param('userid',  PARAM_INT);
        $record = new \stdClass();
        $record->planid = $plan;
        $record->userid = $userid;
        $record->timecreated = time();
        $record->usercreated = $userid;
        $record->timemodified = 0;
        $record->usermodified = 0;
        $create_record = $learningplan_lib->assign_users_to_learningplan($record);
        echo json_encode(true);
        exit;
    break;

    case 'publishlearningplan':      
        $learningplan = $DB->get_field('local_learningplan','name',array('id'=>$plan));
        $users_info = $learningplan_lib->get_enrollable_users_to_learningplan($plan);
        foreach($users_info as $userid){
            $data = new \stdClass();
            $data->planid = $plan;
            $data->userid = $userid->id;
            $data->timecreated = time();
            $data->usercreated = $USER->id;
            $data->timemodified = 0;
            $data->usermodified = 0;
            $create_record = $learningplan_lib->assign_users_to_learningplan($data);
        }
        $result=new stdClass();
        $result->learningplan=$learningplan; 
        echo json_encode(true);
    break;
}
if($value=="and"){
    
    $id = $DB->get_field('local_learningplan_courses', 'id', array('planid'=>$plan,'courseid'=>$course));
    // $sql = "UPDATE {local_learningplan_courses} SET nextsetoperator='and' WHERE id=:id";
    // $DB->execute($sql, array('id' => $id->id));
    $updaterecord = new stdClass();
    $updaterecord->id = $id;
    $updaterecord->nextsetoperator = 'and';
    $updaterecord->timemodified = time();
    $DB->update_record('local_learningplan_courses', $updaterecord);
}elseif($value=="or"){
    $id=$DB->get_field('local_learningplan_courses', 'id', array('planid'=>$plan,'courseid'=>$course));
    // $sql="UPDATE {local_learningplan_courses} SET nextsetoperator='or' WHERE id=:id";
    // $DB->execute($sql, array('id' => $id->id)); 
    $updaterecord = new stdClass();
    $updaterecord->id = $id;
    $updaterecord->nextsetoperator = 'or';
    $updaterecord->timemodified = time();
    $DB->update_record('local_learningplan_courses', $updaterecord);
}
if($manage>0){
    $viewtype = optional_param('view_type', 'card', PARAM_TEXT);
    $learningplan_renderer = new local_learningplan\render\view();
    $dataobj = new stdClass();
    $dataobj->start=$_REQUEST['start'];
    $dataobj->length=$_REQUEST['length'];

    $filterdata = new \stdClass();
    $filterdata->subdepartment = !empty($selected_subdepts) ? explode(',', $selected_subdepts) : null;
    $filterdata->organizations = !empty($selectedcostcenterid) ? explode(',', $selectedcostcenterid) : null;
    $filterdata->departments = !empty($selecteddepartmentid) ? explode(',', $selecteddepartmentid) : null;
    $filterdata->learningplan = !empty($selectedlearningplan) ? explode(',', $selectedlearningplan) : null;
    
    $filterdata->department4level = !empty($department4level) ? explode(',', $department4level) : null;
    $filterdata->department5level = !empty($department5level) ? explode(',', $department5level) : null;
    $filterdata->states = !empty($states) ? explode(',', $states) : null;
    $filterdata->district = !empty($district) ? explode(',', $district) : null;
    $filterdata->subdistrict = !empty($subdistrict) ? explode(',', $subdistrict) : null;
    $filterdata->village = !empty($village) ? explode(',', $village) : null;
    $filterdata->status = !empty($selectedstatus) ? explode(',', $selectedstatus) : null;
    // if(!empty($costcenterid)){
    //     $filterdata->organizations = $costcenterid;
    // }

    $condition="manage";
    
      $data=$learningplan_renderer->all_learningplans($condition,$dataobj,true, strval($requestData['search']['value']),$filterdata,$viewtype); 
   
    
    echo json_encode($data);
}
}catch(Exception $e){

 throw new moodle_exception(get_string('lperror_in_fetching_data','local_learningplan'));
    
}


// //This if condition added for moduletype data returning by sharath
// if($moduletype && $plan){
//     //this sql i common for all cases
//     if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
//         $orgsql .= '';
//     }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
//         $orgsql.=" AND costcenter = $USER->open_costcenterid";
//     }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
//          $orgsql.=" AND costcenter = $USER->open_costcenterid AND department = $USER->open_departmentid ";
//     }else{
//         $orgsql.=" AND costcenter = $USER->open_costcenterid AND department = $USER->open_departmentid ";
//     }
//     $orgsql.= " ORDER BY name DESC ";

//     //this query for existing instances or not in this table
//     $sql = "SELECT instance, planid FROM {local_learningplan_courses} WHERE planid = $plan AND moduletype = '$moduletype'";
//     $existing_plan_moduledata = $DB->get_records_sql($sql);

//     //this switch case for checking moduletype
//     switch ($moduletype) {
//         case 'courses':
//             $courses = $learningplan_lib->learningplan_courses_list($plan);
            
//             $options = array();
//             if(!empty($courses)){
//                 foreach ($courses as $key => $value) {
//                     if(!array_key_exists($key, $existing_plan_moduledata)){
//                         $options[$key] = $value;
//                     }
//                 }
//             }
//         break;

//         case 'classrooms':
//             //this classrooms are all classrooms based on hierarchy
//             $sql = "SELECT id, name FROM {local_classroom} WHERE status = 1 $orgsql";
//             $classrooms = $DB->get_records_sql_menu($sql);

//             $options = array();
//             if(!empty($classrooms)){
//                 foreach ($classrooms as $key => $value) {
//                     if(!array_key_exists($key, $existing_plan_moduledata)){
//                         $options[$key] = $value;
//                     }
//                 }
//             }
//         break;

//         case 'programs':
//             //this programs are all programs based on hierarchy
//             $sql = "SELECT id, name FROM {local_program} WHERE status = 1 $orgsql";
//             $programs = $DB->get_records_sql_menu($sql);

//             $options = array();
//             if(!empty($programs)){
//                 foreach ($programs as $key => $value) {
//                     if(!array_key_exists($key, $existing_plan_moduledata)){
//                         $options[$key] = $value;
//                     }
//                 }
//             }
//         break;

//         case 'certifications':
//             $sql = "SELECT id, name FROM {local_certification} WHERE status = 1 $orgsql";
//             $certifications = $DB->get_records_sql_menu($sql);

//             $options = array();
//             if(!empty($certifications)){
//                 foreach ($certifications as $key => $value) {
//                     if(!array_key_exists($key, $existing_plan_moduledata)){
//                         $options[$key] = $value;
//                     }
//                 }
//             }
//         break;
//     }
//     echo json_encode($options);
// }
// //ended here by sharath

