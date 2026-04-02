<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * LearnerScript Lib
 *
 * @package    block_learnerscript
 * @copyright  2017 eAbyas Info Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * [block_learnerscript_pluginfile description]
 * @param  [type] $course        [description]
 * @param  [type] $cm            [description]
 * @param  [type] $context       [description]
 * @param  [type] $filearea      [description]
 * @param  [type] $args          [description]
 * @param  [type] $forcedownload [description]
 * @param  array  $options       [description]
 * @return [type]                [description]
 */

function compliances($costcenter=NULL, $department=NULL, $subdepartment=NULL) {
    global $DB, $CFG, $USER;
    $systemcontext = context_system::instance(); 
    $sql= "SELECT lc.id as cid,lc.name as compliancename 
            FROM {local_compliance} lc 
            JOIN {local_courses_orgwisevendors} lco ON lco.contentvendorid = lc.contentvendor
            WHERE 1=1 ";   
    if (isset($_SESSION['compliance_id_array']) && !empty($_SESSION['compliance_id_array'])){
        $sql .= " AND lc.id IN (".$_SESSION['compliance_id_array'].")";
    }
    if (!is_siteadmin()) {
        $ohs = $dh = 1;
    }
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) { 
        if($costcenter > 0){
            $sql .= " AND lc.costcenter IN (". $costcenter.", 0) AND lco.costcenterid = " . $costcenter;
            $dashboardcostcenter .= " AND u.open_costcenterid =".$costcenter;
        }  
    } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {

        $sql .= " AND lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lco.costcenterid = " . $USER->open_costcenterid;
        $dashboardcostcenter = " AND u.open_costcenterid  =". $USER->open_costcenterid;

    } else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {

        $sql .= "  AND  lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lc.department IN (". $USER->open_departmentid.", -1) AND lco.costcenterid = " . $USER->open_costcenterid;
        $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid;

        if($subdepartment > 0) {
            $sql .= " AND mlc.subdepartment IN (". $subdepartment.", -1) ";
            $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
        }
    } else { 

        $sql .= "  AND  lc.costcenter IN (". $USER->open_costcenterid.", 0) AND lc.department IN (". $USER->open_departmentid.", -1) AND lc.subdepartment IN (".$USER->open_subdepartment.",-1) AND lco.costcenterid = " . $USER->open_costcenterid;
        $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid." AND u.open_subdepartment = ".$USER->open_subdepartment;
    }
    if ($department > 0) {
        $sql .= " AND lc.department IN (". $department.", -1) ";
        $dashboardcostcenter .= " AND u.open_departmentid = ".$department;
    }
    if ($subdepartment > 0) {
        $sql .= " AND lc.subdepartment IN (". $subdepartment.", -1) ";
        $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
    }
     $compliances = $DB->get_records_sql($sql);
    $i = 1;
    foreach($compliances as $compliance) {
        $section_values=array();
        $sections=$DB->get_records_sql_menu("SELECT mlcs.id 
            FROM {local_compliance_sections} mlcs 
            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid 
            WHERE mlc.id= $compliance->cid");
        $sections = array_keys($sections);
        $totalsectionpercentage=0;
        foreach ($sections as $id => $record) {
            $sql = $DB->get_field_sql("SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' 
                FROM {compliance_user_sec_comp} mlusc 
                JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'certification' AND mlcm.sectionid = mlcs.id
                JOIN {local_certification_users} AS lcu ON lcu.certificationid = mlcm.moduleid AND mlusc.userid = lcu.userid
                JOIN {user} u ON u.id = mlusc.userid 
                WHERE 1=1 {$dashboardcostcenter} AND mlcs.id= {$record} AND lcu.completion_status =1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP()) GROUP BY mlusc.sectionid
                UNION
                SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' 
                FROM {compliance_user_sec_comp} mlusc 
                JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'course' AND mlcm.sectionid = mlcs.id
                JOIN {course} c ON c.id = mlcm.moduleid
                JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = mlusc.userid
                JOIN {user} u ON u.id = mlusc.userid 
                WHERE 1=1 {$dashboardcostcenter} AND cc.timecompleted IS NOT NULL AND mlcs.id='".$record."' GROUP BY mlusc.sectionid");
                $spercentage=ROUND($sql*100,2);
            if($spercentage>100) {
                $spercentage=100;
            }
            array_push($section_values,$spercentage);
            $totalsectionpercentage=$totalsectionpercentage+$spercentage;
        }
        $max_value=max($section_values);
        $compliancepercentage = ROUND(($totalsectionpercentage/count($sections)));
        if (empty($sections)) {
            $compliancepercentage=0;
        }
        $percentage = !empty($compliancepercentage) ? ROUND($compliancepercentage, 0) : '0';
 if ($percentage>100) { 
            $percentage = 100; 
        }
        $compliance_criteria = $DB->get_field_sql("SELECT sectiontracking 
            FROM {local_compliance_completion} mlcc 
            WHERE mlcc.complianceid=$compliance->cid");
        if ($compliance_criteria== 'AND' || $compliance_criteria== 'OR') {
            $percentage=ROUND($max_value,0);
        }
        $row['compliancename'] = $compliance->compliancename;
        $row['id'] = $compliance->cid;
        $compliance->compliancepercentage=$percentage;
        if ($compliance->compliancepercentage == 100) {
            $row['percentage'] = 'complete';
        } elseif($compliance->compliancepercentage >= 80 && $compliance->compliancepercentage < 100) {
            $row['percentage'] = 'pending';
        } elseif($compliance->compliancepercentage < 80) {
            $row['percentage'] = 'inprogress';
        }
        if($i == 1) {
            $row['status'] = 'active';
            $i++;
        } else {
            $row['status'] = '';
        }
        $data[] = $row;
    } 
    return $data;    
}

function overcompliances($complianceid, $costcenter=NULL, $department=NULL, $subdepartment=NULL) {
    global $DB, $CFG, $USER;
    $systemcontext = context_system::instance();
    if (!is_siteadmin()) {
        $ohs = $dh = 1;
    }
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) { 
        if ($costcenter > 0) {
            $sql .= " AND mlc.costcenter IN (".$costcenter.",0)"; 
            $dashboardcostcenter .= " AND u.open_costcenterid =".$costcenter; 
        }    
    } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {

        $sql .= " AND mlc.costcenter IN (".$USER->open_costcenterid.",0)";
        $dashboardcostcenter = " AND u.open_costcenterid  =". $USER->open_costcenterid;

    }else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {

        $sql .= "  AND  mlc.costcenter IN (". $USER->open_costcenterid.", 0) AND mlc.department IN (". $USER->open_departmentid.", -1) ";
        $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid;
        if ($subdepartment > 0) {
            $sql .= " AND mlc.subdepartment IN (". $subdepartment.", -1) ";
            $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
        }
    } else { 
        $sql .= "  AND  mlc.costcenter IN (". $USER->open_costcenterid.", 0) AND mlc.department IN (". $USER->open_departmentid.", -1) AND mlc.subdepartment IN (". $USER->open_subdepartment.", -1) ";
        $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid." AND u.open_subdepartment = ".$USER->open_subdepartment;
    }
    if ($department > 0) {
        $sql .= " AND mlc.department IN (". $department.", -1) ";
        $dashboardcostcenter .= " AND u.open_departmentid = ".$department;
    }
    if ($subdepartment > 0) {
        $sql .= " AND mlc.subdepartment IN (". $subdepartment.", -1) ";
        $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
    }   
      
    if (!$complianceid){
            $complianceid=0;
    }
        $section_values=array();
         $sections=$DB->get_records_sql_menu("SELECT mlcs.id 
            FROM {local_compliance_sections} mlcs 
            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid 
            WHERE mlc.id=$complianceid");
         $sections = array_keys($sections);
         $totalsectionpercentage=0;
       foreach ($sections as $id => $record) {
         $sql = $DB->get_field_sql("SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' 
                FROM {compliance_user_sec_comp} mlusc 
                JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'certification' AND mlcm.sectionid = mlcs.id 
                JOIN {local_certification_users} AS lcu ON lcu.certificationid = mlcm.moduleid AND mlusc.userid = lcu.userid
                JOIN {user} u ON u.id = mlusc.userid 
                WHERE 1=1 {$dashboardcostcenter} AND mlcs.id= {$record} AND lcu.completion_status =1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP()) GROUP BY mlusc.sectionid
            
            UNION

            SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as 'sectionpercenatge' 
                FROM {compliance_user_sec_comp} mlusc 
                JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid 
                JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
                JOIN {local_compliance_modules} mlcm ON mlcm.complianceid = mlc.id AND mlcm.modulename = 'course' AND mlcm.sectionid = mlcs.id
                JOIN {course} c ON c.id = mlcm.moduleid
                JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = mlusc.userid
                JOIN {user} u ON u.id = mlusc.userid 
                WHERE 1=1 {$dashboardcostcenter} AND cc.timecompleted IS NOT NULL AND mlcs.id='".$record."' GROUP BY mlusc.sectionid");
            $spercentage=ROUND($sql*100,2);
            if($spercentage>100){ $spercentage=100;}
                array_push($section_values,$spercentage);
                $totalsectionpercentage=$totalsectionpercentage+$spercentage;
        }
        $max_value=max($section_values);
        $overallpercentage = ROUND(($totalsectionpercentage/count($sections)));
        if(empty($sections)){
            $overallpercentage=0;
        }
         
        $overall['percentage'] = !empty($overallpercentage) ? ROUND($overallpercentage, 0) : '0';
        if($overall['percentage']>100){
            $overall['percentage']=100;
        }
        $compliance_criteria = $DB->get_field_sql("SELECT sectiontracking 
            FROM {local_compliance_completion} mlcc 
            WHERE mlcc.complianceid=$complianceid");
        if($compliance_criteria== 'AND' || $compliance_criteria== 'OR'){
            $overall['percentage']=ROUND($max_value,0);
        }        
        $overall['compliancename'] = $DB->get_field_sql("SELECT name 
            FROM {local_compliance} 
            WHERE id =". $complianceid);
    return $overall;
}

function compliancetracking($complianceid) {
    global $DB, $CFG;
    $compliancetracking = $DB->get_field_sql("SELECT sectiontracking 
        FROM {local_compliance_completion} lcs 
        WHERE lcs.complianceid =". $complianceid);
    $track = !empty($compliancetracking) ? $compliancetracking : 'N/A';
    if ($track == 'AND' || $track == 'OR') {
        $tracking = 'Compliance will be achieved when any of the below sections are achieved';
    } else if($track == 'ALL') {
        $tracking = 'Compliance will be achieved when all the below sections are achieved';
    } else {
        $tracking = '';
    }
    return $tracking;
}
function sections($complianceid, $costcenter=NULL, $department=NULL,$subdepartment=NULL) {
    global $DB, $CFG, $USER;
    $systemcontext = context_system::instance();
    if (!is_siteadmin()) {
        $ohs = 1;
    }
    if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) { 
        if($costcenter>0) {
                $sql .=" AND lc.costcenter IN (".$costcenter.", 0) ";
                $dashboardcostcenter = " AND u.open_costcenterid =".$costcenter;
            }            
    } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) {
        $dashboardcostcenter = " AND u.open_costcenterid  =". $USER->open_costcenterid;
    }else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) {
         $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid;
    } else { 
        $dashboardcostcenter .= " AND u.open_costcenterid =". $USER->open_costcenterid ." AND u.open_departmentid =". $USER->open_departmentid." AND u.open_subdepartment =".$USER->open_subdepartment;
    }
    if($department > 0) {
        $sql .= " AND lc.department IN (". $department.", -1) ";
        $dashboardcostcenter .= " AND u.open_departmentid = ".$department;
    }
    if($subdepartment > 0) {
        $sql .= " AND lc.subdepartment IN (". $subdepartment.", -1) ";
        $dashboardcostcenter .= " AND u.open_subdepartment = ".$subdepartment;
    }
    $sections = $DB->get_records_sql("SELECT id, name 
        FROM {local_compliance_sections} lcs 
        WHERE lcs.complianceid =". $complianceid);
     foreach($sections as $section) {
            $row['id'] = $section->id;
            $row['sectionname'] = $section->name;
            $requirement = $DB->get_record_sql("SELECT mlcs.name as section, mlcs.userscount as requiredusers
                FROM {local_compliance_sections} mlcs
                WHERE mlcs.id = ".$section->id);
            $secreqs = $DB->get_records_sql("SELECT id, coursetracking, certificationtracking, moduletracking 
                FROM {local_compliance_sec_comp} 
                WHERE sectionid = {$section->id} 
                ORDER BY id ASC ");
            $track = array();
            $tracking = array();
            foreach($secreqs as $secreq){
                if(!empty($secreq->coursetracking)) {
                    $tracking['crs'] = $secreq->coursetracking;    
                }
                if(!empty($secreq->certificationtracking)) {
                    $tracking['cert'] = $secreq->certificationtracking;    
                }
                $track[] = $tracking;
                $modulereq = $secreq->moduletracking;
            }
            $requirementtracking = end($track);
            $textcrs = array();
            $textcertcriteria = array();
            if(!empty($secreqs)){
                    switch (strtolower($requirementtracking['crs'])) {
                        case 'all':
                            $textcrs[] = 'ALL';
                            break;
                        case 'or':
                            $textcrs[] = 'ANY';
                        break;
                        case 'and':
                            $textcrs[] = 'SELECTED';
                        break;
                    }
                    switch (strtolower($requirementtracking['cert'])) {
                        case 'all':
                            $textcertcriteria[] = 'ALL';
                            break;
                        case 'or':
                            $textcertcriteria[] = 'ANY';
                        break;
                        case 'and':
                            $textcertcriteria[] = 'SELECTED';
                        break;
                    }
            } else {
                $textcrs[] = '';
                $textcertcriteria[] = '';
            }
            $criteriacrs = implode('', $textcrs);
            $criteriacert = implode('', $textcertcriteria);
            $row['users'] = !empty($requirement->requiredusers) ? $requirement->requiredusers : 'N/A';
            $row['coursereq'] = !empty($criteriacrs) ? $criteriacrs : '';
            $row['certificationreq'] = !empty($criteriacert) ? $criteriacert : '';
            $expirydate = strtotime("+90 days");
            $secmodules = $DB->get_records_sql("SELECT c.id AS id, c.fullname AS crscert, mlcm.modulename AS modulename, 0 AS upcomingexpiry, 0 AS eol, 
                    (SELECT COUNT(DISTINCT cc.userid) 
                        FROM {course_completions} AS cc 
                        JOIN {user} AS u ON u.id= cc.userid 
                        WHERE 1=1 {$dashboardcostcenter} AND cc.course = c.id AND cc.timecompleted IS NOT NULL) AS completedusers
                    FROM {local_compliance_modules} AS mlcm
                    JOIN {course} AS c ON c.id = mlcm.moduleid AND mlcm.modulename = 'course'
                    LEFT JOIN {course_completions} AS cc ON cc.course = c.id
                    WHERE mlcm.sectionid = {$section->id}
                    GROUP BY mlcm.moduleid
                    UNION
                    SELECT lc.id AS id, lc.name AS crscert, mlcm.modulename AS modulename,
                        (SELECT COUNT(lc.expirydate)
                        FROM {local_certification_users} AS lc 
                        JOIN {user} AS u ON u.id = lc.userid
                        WHERE lc.certificationid = cc.certificationid {$dashboardcostcenter} AND lc.completion_status > 0 AND lc.expirydate BETWEEN UNIX_TIMESTAMP() AND $expirydate) AS upcomingexpiry,
                        (SELECT COUNT(lc1.eol) 
                        FROM {local_certification} lc1
                        WHERE lc1.id = cc.certificationid AND from_unixtime(lc1.eol) BETWEEN CURDATE() AND (CURDATE() + 90)) AS eol,
                        (SELECT COUNT(lcc.id) 
                        FROM {local_certification_users} lcc
                        JOIN {user} AS u ON u.id = lcc.userid 
                        WHERE 1=1 {$dashboardcostcenter} AND lcc.certificationid = lc.id AND lcc.completion_status =1 AND (lcc.expirydate =0 OR lcc.expirydate >= UNIX_TIMESTAMP())) AS completedusers
                    FROM {local_compliance_modules} mlcm
                    JOIN {local_certification} lc ON lc.id = mlcm.moduleid AND mlcm.modulename='certification'
                    LEFT JOIN {local_certification_users} cc ON cc.certificationid = lc.id
                    WHERE mlcm.sectionid = {$section->id}
                    GROUP BY mlcm.moduleid");
    $da = array();
    $compliancecoursesid = $DB->get_field('block_learnerscript', 'id', array('type' => 'compliancecourseuserslist'), IGNORE_MULTIPLE);
    $compliancecertid = $DB->get_field('block_learnerscript', 'id', array('type' => 'compliancecertificationuserslist'), IGNORE_MULTIPLE);
    foreach($secmodules as $secmodule) {
        $ro['course'] = '';
        $ro['cert'] = '';
        $ro['eol'] = '';
        $compliancecourses = new moodle_url('/blocks/learnerscript/viewreport.php', array('id' => $compliancecoursesid, 'filter_course' => $secmodule->id));
        $compliancecert = new moodle_url('/blocks/learnerscript/viewreport.php', array('id' => $compliancecertid, 'filter_certificates' => $secmodule->id));
        $ro['crscert'] = $secmodule->crscert;
        $compcerturl = new moodle_url('/blocks/learnerscript/viewreport.php', array());
        $ro['completedusers'] = $secmodule->completedusers;
        $ro['compliancecourses'] = $compliancecourses;
        $ro['compliancecert'] = $compliancecert;
        $ro['coursereportid'] = $compliancecoursesid;
        $ro['certreportid'] = $compliancecertid;
        $ro['certfilterid'] = $secmodule->id;
        $ro['compliancecoursesid'] = $compliancecoursesid;
        $ro['compliancecertid'] = $compliancecertid;
        $ro['compcerturl'] = $compcerturl;       
        if($secmodule->completedusers > 0 && !empty($secmodule->upcomingexpiry)){
            $ro['eol'] = 'present';
        }
        if($secmodule->modulename == 'course'){
            $ro['course'] = 'present';
        }else{
            $ro['cert'] = 'present';
        }
        $da[] = $ro;
    }      
    $row['test'] = $da;
    $sqlsec= $DB->get_field_sql(
            "SELECT ((SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as sectionpercenatge
            FROM {compliance_user_sec_comp} mlusc 
            JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid
            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
            JOIN {local_compliance_modules} lcm ON lcm.complianceid = mlc.id AND lcm.modulename = 'certification' AND lcm.sectionid = mlcs.id
            JOIN {local_certification_users} lcu ON lcu.certificationid = lcm.moduleid AND mlusc.userid = lcu.userid
            JOIN {user} u ON u.id = mlusc.userid
            WHERE  1 = 1 {$dashboardcostcenter} AND mlusc.sectionid ={$section->id} AND lcu.completion_status =1 AND (lcu.expirydate =0 OR lcu.expirydate >= UNIX_TIMESTAMP()))
            
            +

            (SELECT (count(DISTINCT mlusc.userid)/mlcs.userscount) as sectionpercenatge
            FROM {compliance_user_sec_comp} mlusc 
            JOIN {local_compliance_sections} mlcs ON mlcs.id = mlusc.sectionid
            JOIN {local_compliance} mlc ON mlc.id = mlcs.complianceid
            JOIN {local_compliance_modules} lcm ON lcm.complianceid = mlc.id AND lcm.modulename = 'course' AND lcm.sectionid = mlcs.id
            JOIN {course} c ON c.id = lcm.moduleid
            JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = mlusc.userid
            JOIN {user} u ON u.id = mlusc.userid
            WHERE  1 = 1 {$dashboardcostcenter} AND cc.timecompleted IS NOT NULL AND mlusc.sectionid = {$section->id}))as total");
        $row['secpercentage'] = !empty($secpercentage) ? ROUND($secpercentage, 0) : '0'; 
        if($row['secpercentage']>100) {
            $row['secpercentage']=100;
        }
        $data[] = $row;
    }
    return $data;    
}
