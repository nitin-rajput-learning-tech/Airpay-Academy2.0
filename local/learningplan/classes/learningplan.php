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
 */
namespace local_learningplan;

define('LEARNINGPLAN_NOT_ENROLLED', 0);
define('LEARNINGPLAN_ENROLLED', 1);
define('LEARNINGPLAN_ENROLMENT_REQUEST', 2);
define('LEARNINGPLAN_ENROLMENT_PENDING', 3);

class learningplan {
    /**
     * [userlearningplans description]
     * @param  string $status [description]
     * @param  string $search [description]
     * @return [type]         [description]
     */
    public static function userlearningplans($status = 'inprogress', $search = '', $limit = '', $mobile = false, $page=0, $perpage=10) {
        global $DB, $USER, $CFG;
        $coursesinfo = self::learningplancoursestypeinfo(true);
        $sqlquery = "SELECT llp.id, llp.name, llp.description, llp.learning_type,llp.certificateid ,IF(llp.learning_type = 1, 'Core Courses', 'Elective Courses') AS learningplantype, llp.open_points $coursesinfo ";
        $sqlcount = "SELECT COUNT(llp.id)";
        $userlearningplanssql = " FROM {local_learningplan} llp
                                   JOIN {local_learningplan_user} lla ON llp.id = lla.planid
                                  WHERE userid = :userid AND llp.visible = :visible ";
        if ($status == 'inprogress') {
            $userlearningplanssql .= ' AND lla.completiondate is NULL AND status is NULL';
        } else if ($status == 'completed') {
            $userlearningplanssql .= ' AND lla.completiondate is NOT NULL AND status = 1';
        }
        if (!empty($search)) {
            $userlearningplanssql .= " AND llp.name LIKE '%%$search%%'";
        }
        if ($status == 'inprogress' && $limit) {
            $userlearningplanssql .= " ORDER BY lla.id desc ";
            $limit = 5;
        }
        else {
            $userlearningplanssql .= " ORDER BY lla.id desc";
            $limit = 0;
        }
        $params = array();
        $params['userid'] = $USER->id;
        $params['visible'] = 1;
        if ($mobile) {
             $userlearningplans = $DB->get_records_sql($sqlquery . $userlearningplanssql, $params, $page * $perpage, $perpage);
             $count = $DB->count_records_sql($sqlcount . $userlearningplanssql, $params);
             return array($userlearningplans, $count);

        } else {
            $userlearningplans = $DB->get_records_sql($sqlquery . $userlearningplanssql, $params, 0, $limit);
            return $userlearningplans;

        }
    }
    /**
     * [userlearningplansData description]
     * @return [type] [description]
     */
    public function userlearningplansData($userlearningplans){
        $mylearningplans = array();
        foreach ($userlearningplans as $userlearningplan) {
            $mylearningplan = (array)$userlearningplan;
            $mylearningplan['courses'] = self::userlearningplancourses($userlearningplan->id, '');
            $mylearningplans[] = $mylearningplan;
        }
        return $mylearningplans;
    }
    /**
     * [userlearningplancourses description]
     * @param  string $status [description]
     * @param  string $search [description]
     * @return [type]         [description]
     */
    public static function userlearningplancourses($lpid, $search = '', $page=0, $perpage=10, $source = '') {
        global $DB, $USER, $CFG;
        $query = "SELECT c.id, c.fullname, c.enablecompletion, c.summary, lc.sortorder, lc.id AS lepid, lc.nextsetoperator AS next, IF(lc.nextsetoperator = 'and', 'Mandatory', 'Optional') AS coursetype ";
        $sqlcount = "SELECT COUNT(c.id)";
        $userlearningplancoursessql = " FROM {local_learningplan_courses} lc
                    JOIN {course} c ON c.id = lc.courseid";

        $params = array();
        $params['lpid'] = $lpid;
        $params['visible'] = 1;
        if($source == 'mobile'){
            $userlearningplancoursessql .= " AND c.open_securecourse <> 1 ";
        }
        if(!empty($search)) {
            $userlearningplancoursessql .= " AND c.fullname LIKE '%%$search%%'";
        }
        $userlearningplancoursessql .= " WHERE lc.planid = $lpid ORDER BY lc.sortorder ASC";
        $userlearningplancourses = $DB->get_records_sql($query . $userlearningplancoursessql, $params, $page * $perpage, $perpage);
        $count = $DB->count_records_sql($sqlcount . $userlearningplancoursessql);
        return array($userlearningplancourses, $count);
    }
    public function learningplancoursestypecount($lpid, $type = ''){
        global $DB;
        $params = array();
        $learningplancoursestypecountsql = "SELECT COUNT(lc.id)
                    FROM {local_learningplan_courses} lc
                    JOIN {course} c ON c.id = lc.courseid
                    WHERE lc.planid = :planid " ;
        if ($type == 'and') {
            $learningplancoursestypecountsql .= " AND lc.nextsetoperator = :type ";
            $params['type'] = $type;
        } else if ($type == 'or') {
            $learningplancoursestypecountsql .= " AND lc.nextsetoperator = :type ";
            $params['type'] = $type;
        }
        $params['planid'] = $lpid;

        $learningplancoursestypecount = $DB->count_records_sql($learningplancoursestypecountsql);
        return $learningplancoursestypecount;
    }

    public static function learningplancoursestypeinfo($subquery = true, $selectedtype = ''){
        global $DB;
        if (empty($selectedtype)) {
            $types = array('and', 'or');
        } else {
            $types = array($selectedtype);
        }
        $seperatedquery = ' ';
        if ($subquery) {
            $seperatedquery = ' , ';
        }
        $i = 0;
        $seperatedquery = ' ';
        $learningplancoursestypecountsql = '';
        foreach ($types as $type) {
            if ($subquery || $i == 1) {
                $seperatedquery = ' , ';
            }
            $i++;
            $learningplancoursestypecountsql .= " $seperatedquery (SELECT COUNT(lc.id)
                        FROM {local_learningplan_courses} lc
                        JOIN {course} c ON c.id = lc.courseid
                        WHERE lc.planid = llp.id " ;
            if ($type == 'and') {
                $columntype = 'mandatory';
                $learningplancoursestypecountsql .= " AND lc.nextsetoperator = '$type' ";
            } else if ($type == 'or') {
                $columntype = 'optional';
                $learningplancoursestypecountsql .= " AND lc.nextsetoperator = '$type' ";
            }
            $learningplancoursestypecountsql .= " ) AS " . $columntype ;
        }
        return $learningplancoursestypecountsql;
    }
    public static function userlearningplancoursesInfo($lpid, $search = '', $page=0, $perpage=10, $source = '') {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot.'/local/ratings/lib.php');
        $data = array();
        $learningplan_classes_lib = new \local_learningplan\lib\lib ();
        list($userlearningplancourses, $count) = self::userlearningplancourses($lpid, $search, $page, $perpage, $source);
        foreach ($userlearningplancourses as $userlearningplancourse) {
            $lpcourses = array();
            if ($userlearningplancourse->sortorder > 0 && $userlearningplancourse->next == 'and') {
                $coursestatus = $learningplan_classes_lib->get_previous_course_status($lpid,$userlearningplancourse->sortorder,$userlearningplancourse->id);
                if($coursestatus){
                    $disable_class1=1;
                }else{
                    $restricted= $DB->get_field('local_learningplan','lpsequence',array('id'=>$lpid));
                    if($restricted) {
                            $disable_class1=0;
                    }
                }
            }
            else{
                $disable_class1=1;
            }
            if ($userlearningplancourse->enablecompletion) {
                $progress = \core_completion\progress::get_course_progress_percentage($userlearningplancourse, $USER->id);
            }
            $modulerating = $DB->get_field('local_ratings_likes', 'module_rating', array('module_id' => $userlearningplancourse->id, 'module_area' => 'local_learningplan'));
            if(!$modulerating){
                 $modulerating = 0;
            }
            $ccompletion = $DB->get_field_sql('SELECT timecompleted FROM {course_completions} WHERE course=:courseid AND userid=:userid', array('courseid'=> $userlearningplancourse->id, 'userid'=>$USER->id));
            $completedon = $ccompletion ? $ccompletion: '';
            $likes = $DB->count_records('local_like', array('likearea'=> 'local_courses', 'itemid'=>$userlearningplancourse->id, 'likestatus'=>'1'));
            $dislikes = $DB->count_records('local_like', array('likearea'=> 'local_courses', 'itemid'=>$userlearningplancourse->id, 'likestatus'=>'2'));
            $userlikeinfo = $DB->get_field('local_like','likestatus',array('id' => $userlearningplancourse->id,'likearea' => 'local_courses','userid'=>$USER->id)) ;
            $lpcourses['id'] = $userlearningplancourse->id;
            $lpcourses['fullname'] = $userlearningplancourse->fullname;
            $lpcourses['visible'] = $disable_class1;
            $lpcourses['sortorder'] = $userlearningplancourse->sortorder;
            $lpcourses['lepid'] = $userlearningplancourse->lepid;
            $lpcourses['next'] = $userlearningplancourse->next;
            $lpcourses['coursetype'] = $userlearningplancourse->coursetype;
            $lpcourses['progress'] = $progress;
            $lpcourses['summary'] = $userlearningplancourse->summary;
            $lpcourses['rating'] = $modulerating;
            $lpcourses['likes'] = $likes;
            $lpcourses['dislikes'] = $dislikes;
            $lpcourses['completedon'] = $completedon;
            $avgratings = get_rating($userlearningplancourse->id, 'local_courses');
            $avgrating = $avgratings->avg;
            $ratingusers = $avgratings->count;
            $lpcourses['avgrating'] = $avgrating;
            $lpcourses['ratingusers'] = $ratingusers;
            $lpcourses['likedstatus'] = $userlikeinfo ? $userlikeinfo : '0';
            $data[] = $lpcourses;
        }
        return array($data,$count);
    }

    public function enrol_status($enrol, $learningplan, $userid = 0){
        global $DB, $USER;
        $enrolled = $DB->get_field('local_learningplan_user', 'id', array('planid' => $learningplanid, 'userid' => $USER->id));
        $return = $enrolled ? LEARNINGPLAN_ENROLLED : LEARNINGPLAN_NOT_ENROLLED;
        $component = 'learningplan';
        if ($learningplan->approvalreqd == 1) {
            $requestsql = "SELECT status FROM {local_request_records}
                WHERE componentid = :componentid AND compname LIKE :compname AND
                createdbyid = :createdbyid ORDER BY id DESC ";
            $request = $DB->get_field_sql($requestsql ,array('componentid' => $learningplan->id,'compname' => $component, 'createdbyid' => $USER->id));
            if ($request == 'PENDING') {
                $return = LEARNINGPLAN_ENROLMENT_PENDING;
             } else {
                $return = LEARNINGPLAN_ENROLMENT_REQUEST;
            }
        } else {
            $return = LEARNINGPLAN_NOT_ENROLLED;
        }
        return $return;
    }
    public function get_learningplans($filterdata, $start, $length){
        global $DB, $USER;
        $categorycontext = (new \local_learningplan\lib\accesslib())::get_module_context();
        $filtersql = '';
        if($filterdata){
            if (!empty(array_filter($filterdata->filteropen_costcenterid))) {
                $selectedorganizations = implode(',', array_filter($filterdata->filteropen_costcenterid));
                $organizations = explode(',', $selectedorganizations);
                $orgsql = [];
                foreach ($organizations as $organisation) {
                    $orgsql[] = " concat('/',l.open_path,'/') LIKE :organisationparam_{$organisation}";
                    $lpparams["organisationparam_{$organisation}"] = '%/' . $organisation . '/%';
                }
                if (!empty($orgsql)) {
                    $filtersql .= " AND ( " . implode(' OR ', $orgsql) . " ) ";
                }
            }
            if (!empty(array_filter($filterdata->filteropen_department))) {
                $selecteddepts = implode(',', array_filter($filterdata->filteropen_department));
                $depts = explode(',', $selecteddepts);
                $deptsql = [];
                foreach ($depts as $dept) {
                    $deptsql[] = " concat('/',l.open_path,'/') LIKE :deptparam_{$dept}";
                    $lpparams["deptparam_{$dept}"] = '%/' . $dept . '/%';
                }
                if (!empty($deptsql)) {
                    $filtersql .= " AND ( " . implode(' OR ', $deptsql) . " ) ";
                }
            }
            if (!empty(array_filter($filterdata->filteropen_subdepartment))) {
                $selectedsubdepts = implode(',', array_filter($filterdata->filteropen_subdepartment));
                $subdepts = explode(',', $selectedsubdepts);
                $subdeptsql = [];
                foreach ($subdepts as $subdept) {
                    $subdeptsql[] = " concat('/',l.open_path,'/') LIKE :subdeptparam_{$subdept}";
                    $lpparams["subdeptparam_{$subdept}"] = '%/' . $subdept . '/%';
                }
                if (!empty($subdeptsql)) {
                    $filtersql .= " AND ( " . implode(' OR ', $subdeptsql) . " ) ";
                }
            }

            if (!empty(array_filter($filterdata->filteropen_department4level))) {
                $selecteddepts4 = implode(',', array_filter($filterdata->filteropen_department4level));
                $depts4 = explode(',', $selecteddepts4);
                $depts4sql = [];
                foreach ($depts4 as $dept4) {
                    $depts4sql[] = " concat('/',l.open_path,'/') LIKE :dept4param_{$dept4}";
                    $lpparams["dept4param_{$dept4}"] = '%/' . $dept4 . '/%';
                }
                if (!empty($depts4sql)) {
                    $filtersql .= " AND ( " . implode(' OR ', $depts4sql) . " ) ";
                }
            }

            if (!empty(array_filter($filterdata->filteropen_department5level))) {
                $selecteddepts5 = implode(',', array_filter($filterdata->filteropen_department5level));
                $depts5 = explode(',', $selecteddepts5);
                $depts5sql = [];
                foreach ($depts5 as $dept5) {
                    $depts5sql[] = " concat('/',l.open_path,'/') LIKE :dept5param_{$dept5}";
                    $lpparams["dept5param_{$dept5}"] = '%/' . $dept5 . '/%';
                }
                if (!empty($depts5sql)) {
                    $filtersql .= " AND ( " . implode(' OR ', $depts5sql) . " ) ";
                }
            }
            if (!empty($filterdata->categories)) {
                $selectedcategories = implode(',', $filterdata->categories);
                $filtersql .= " AND l.open_categoryid IN ($selectedcategories) ";
            }
            if (!empty($filterdata->status)) {
                if (!(in_array('active', $filterdata->status) && in_array('inactive', $filterdata->status))) {
                    if (in_array('active', $filterdata->status)) {
                        $filtersql .= " AND l.visible = 1 ";
                    } else if (in_array('inactive', $filterdata->status)) {
                        $filtersql .= " AND l.visible = 0 ";
                    }
                }
            }
        }

        $sql="SELECT l.* FROM {local_learningplan} AS l WHERE 1 = 1 "; 
        $costcenterpathconcatsql = (new \local_learningplan\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='l.open_path');
        if (is_siteadmin()) {
            $sql .= "";
        } else  {
            $sql .= $costcenterpathconcatsql;
        }
        if(!empty($search)){
            $sql .= " AND name LIKE '%%$search%%'";
        }
        $sql .= $filtersql;
        $sql .= " ORDER BY l.id DESC"; 
        $learning_plans = $DB->get_records_sql($sql,$lpparams, $start,$length);
        // if(is_siteadmin()){
        //     $sql="SELECT l.* FROM {local_learningplan} AS l WHERE 1 = 1 "; 
        //     if(!empty($search)){
        //         $sql .= " AND name LIKE '%%$search%%'";
        //     }
        //     $sql .= $filtersql;
        //     $sql .= " ORDER BY l.id DESC";    
        //     $learning_plans = $DB->get_records_sql($sql, [], $start,$length);
        // }elseif(has_capability('local/learningplan:manage',$systemcontext)){
        //     $data = \local_learningplan\render\open::userdetails();
        //     $sql="SELECT l.* FROM {local_learningplan} AS l WHERE concat(',',l.open_path,',') LIKE concat('%,',{$USER->open_costcenterid},',%')";
        //     if(!empty($search)){
        //         $sql .= " AND name LIKE '%%$search%%'";
        //     }
        //     $sql .= $filtersql;
        //     $sql .= " ORDER BY l.id DESC";
        //     $learning_plans = $DB->get_records_sql($sql, [], $start,$length);
        // }elseif(has_capability('local/learningplan:manage',$systemcontext) ){
        //     $sql="SELECT l.* FROM {local_learningplan} AS l WHERE concat(',',l.open_path,',') LIKE concat('%,',{$USER->open_path},',%') AND CONCAT(',',l.department,',') LIKE CONCAT('%,',{$USER->open_departmentid},',%') AND l.id > 0 ";
        //     if(!empty($search)){
        //         $sql .= " AND name LIKE '%%$search%%'";
        //     }
        //     $sql .= $filtersql;
        //     $sql .= "  ORDER BY l.id DESC";
        //     $learning_plans = $DB->get_records_sql($sql, [], $start,$length);
        // }else{
        //     $data = \local_learningplan\render\open::userdetails();
        //     $sql="SELECT * FROM {local_learningplan} AS l WHERE
        //     CONCAT(',',l.open_path,',') LIKE CONCAT('%,',$data->open_path,',%')
        //     CONCAT(',',l.open_group,',') LIKE CONCAT('%,',$data->open_group,',%')
        //     CONCAT(',',l.department,',') LIKE CONCAT('%,',$data->open_departmentid,',%')
        //     CONCAT(',',l.subdepartment,',') LIKE CONCAT('%,',$data->open_subdepartment,',%')
        //     AND l.id > 0";
        //     if(!empty($search)){
        //         $sql .= " AND name LIKE '%%$search%%'";
        //     }
        //     $sql .= $filtersql;
        //     $sql .= ' AND l.visible=1 ORDER BY l.timemodified DESC';
            
        //     $learning_plans = $DB->get_records_sql($sql, array(), $start, $length);
            
        // }
        return $learning_plans;
    }
}
