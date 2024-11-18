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
 * @subpackage local_courses
 */


defined('MOODLE_INTERNAL') || die;

class local_courses_renderer extends plugin_renderer_base {

     /**
     * [render_classroom description]
     * @method render_classroom
     * @param  \local_classroom\output\classroom $page [description]
     * @return [type]                                  [description]
     */
    public function render_courses(\local_courses\output\courses $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/courses', $data);
    }
    /**
     * [render_form_status description]
     * @method render_form_status
     * @param  \local_classroom\output\form_status $page [description]
     * @return [type]                                    [description]
     */
    public function render_form_status(\local_courses\output\form_status $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/form_status', $data);
    }

    /**
     * Display the avialable courses
     *
     * @return string The text to render
     */
    public function get_catalog_courses($filter = false,$view_type='card') {
      global $USER;
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $status = optional_param('status', '', PARAM_RAW);
        $costcenterid = optional_param('costcenterid', '', PARAM_INT);
        $departmentid = optional_param('departmentid', '', PARAM_INT);
        $subdepartmentid = optional_param('subdepartmentid', '', PARAM_INT);
        $l4department = optional_param('l4department', '', PARAM_INT);
        $l5department = optional_param('l5department', '', PARAM_INT);
      
        $templateName = 'local_courses/catalog';
        $cardClass = 'col-md-6 col-12';
        $perpage = 12;
        if($view_type=='table'){
            $templateName = 'local_courses/catalog_table';
            $cardClass = 'tableformat';
            $perpage = 20;
        } 
        $options = array('targetID' => 'manage_courses','perPage' => $perpage, 'cardClass' => 'col-lg-3 col-md-4 col-12 mb-5', 'viewType' => $view_type);
        $options['methodName']='local_courses_courses_view';
        $options['templateName']= $templateName;
        $options = json_encode($options);
        $filterdata = json_encode(array('status' => $status, 'filteropen_costcenterid' => $costcenterid, 'filteropen_department' => $departmentid, 'filteropen_subdepartment' => $subdepartmentid, 'filteropen_level4department' => $l4department, 'filteropen_level5department' => $l5department));
        $dataoptions = json_encode(array('userid' => $USER->id, 'contextid' => $categorycontext->id,'status' => $status, 'filteropen_costcenterid' => $costcenterid, 'filteropen_department' => $departmentid,'filteropen_subdepartment' => $subdepartmentid, 'filteropen_level4department' => $l4department, 'filteropen_level5department' => $l5department));
        // $filterdata = json_encode(array('status'=>$status,'organizations'=>$costcenterid,'departments'=>$departmentid));
        // $dataoptions = json_encode(array('contextid' => $categorycontext->id,'status'=>$status,'costcenterid'=>$costcenterid,'departmentid'=>$departmentid));
        $context = [
                'targetID' => 'manage_courses',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

    /**
     * Display the avialable categories list
     *
     * @return string The text to render
     */
    public function get_categories_list($filter = false,$view_type= 'card') {
        $id = optional_param('id', 0, PARAM_INT);
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        
        // change the display according to moodle 3.6
        // $stable = new stdClass();
        // $stable->thead = true;
        // $stable->start = 0;
        // $stable->length = -1;
        // $stable->search = '';
        // $stable->pagetype ='page';

        $templateName = 'local_courses/categorylist';
        $cardClass = 'col-md-3 col-sm-6';
        $perpage = 10;
        if($view_type=='table'){
            $templateName = 'local_courses/categorylist_catalog_table';
            $cardClass = 'tableformat';
            $perpage = 20;
        } 
       $options = array('targetID' => 'manage_categories','perPage' => $perpage, 'cardClass' => $cardClass, 'viewType' => $view_type );
        $options['methodName']='local_courses_categories_view';
        $options['templateName']= $templateName;
        $options['parentid'] = $id;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $categorycontext->id));
        $context = [
                'targetID' => 'manage_categories',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if($filter){
            return  $context;
        } else {
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }       
    }

    /**
     * Renders html to print list of courses tagged with particular tag
     *
     * @param int $tagid id of the tag
     * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
     *             are displayed on the page and the per-page limit may be bigger
     * @param int $fromctx context id where the link was displayed, may be used by callbacks
     *            to display items in the same context first
     * @param int $ctx context id where to search for records
     * @param bool $rec search in subcontexts as well
     * @param array $displayoptions
     * @return string empty string if no courses are marked with this tag or rendered list of courses
     */
    public function tagged_courses($tagid, $exclusivemode = true, $ctx = 0, $rec = true, $displayoptions = null, $count = 0, $sort='') {
        global $CFG, $DB,$USER;
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        $userorg = array();
        $userdep = array();
        if ($count > 0)
        $sql =" select count(c.id) from {course} c ";
        else
        $sql =" select c.* from {course} c  ";
        $joinsql = $groupby = $orderby = '';
        if (!empty($sort) AND $count == 0) {
          switch($sort) {
            case 'highrate':
            if ($DB->get_manager()->table_exists('local_rating')) {
              $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
              $groupby .= " group by c.id ";
              $orderby .= " order by AVG(rating) desc ";
            }
            break;
            case 'lowrate':
            if ($DB->get_manager()->table_exists('local_rating')) {
              $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_courses' ";
              $groupby .= " group by c.id ";
              $orderby .= " order by AVG(rating) asc ";
            }
            break;
            case 'latest':
            $orderby .= " order by c.timecreated desc ";
            break;
            case 'oldest':
            $orderby .= " order by c.timecreated asc ";
            break;
            default:
            $orderby .= " order by c.timecreated desc ";
            break;
            }
        }

        if(is_siteadmin()){
            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_path
                         JOIN {course_categories} AS cc ON cc.id = c.category
                         where 1 = 1 ";
        }else {

            $condition= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path');

            $joinsql .= " JOIN {local_costcenter} AS co ON co.id = c.open_path
                       JOIN {course_categories} AS cc ON cc.id = c.category
                       WHERE $condition";
        }

        $tagparams = array('tagid' => $tagid, 'itemtype' => 'courses', 'component' => 'local_courses');
        $params = array_merge($userorg, $userdep, $tagparams);

        $where = " AND c.id IN (SELECT t.itemid FROM {tag_instance} t WHERE t.tagid = :tagid AND t.itemtype = :itemtype AND t.component = :component)";

        if ($count > 0) {
            $records = $DB->count_records_sql($sql.$joinsql.$where, $params);
            return $records;
        } else {
            $records = $DB->get_records_sql($sql.$joinsql.$where.$groupby.$orderby, $params);
        }
        
        $tagfeed = new local_tags\output\tagfeed(array(), 'local_courses');
        $img = $this->output->pix_icon('i/course', '');
        foreach ($records as $key => $value) {
          $url = $CFG->wwwroot.'/course/view.php?id='.$value->id.'';
          $imgwithlink = html_writer::link($url, $img);
          $modulename = html_writer::link($url, $value->fullname);
          $coursedetails = get_course_details($value->id);
          $details = $this->render_from_template('local_courses/tagview', $coursedetails);
          $tagfeed->add($imgwithlink, $modulename, $details);
        }
        return $this->output->render_from_template('local_tags/tagfeed', $tagfeed->export_for_template($this->output));

    }
    public function get_parent_category_data($categoryid){
        global $DB;
        $category = $DB->get_record('course_categories', array('id' => $categoryid));
        $data = array();
        $data['category_name'] = strlen($category->name) > 20 ? substr($category->name, 0, 20).'...' : $category->name;
        $data['category_name_title'] = $category->name;
        $data['category_code'] = strlen($category->idnumber) > 20 ? substr($category->idnumber, 0, 20).'...' : $category->idnumber;
        $data['category_code_title'] = $category->idnumber;
        $categorycontext = \context_coursecat::instance($category->id);
        $data['courses'] = html_writer::link('javascript:void(0)', $category->coursecount, array('title' => '', 'alt' => '', 'class'=>'createcoursemodal', 'onclick' =>'(function(e){ require("local_courses/newcategory").courselist({contextid:'.$categorycontext->id.', categoryname: "'.$category->name.'", categoryid: "' . $category->id . '" }) })(event)'));
        $data['subcategory_count'] = $DB->count_records('course_categories', array('parent' => $categoryid)); 
        // $actions = False;
        // if(has_capability('local/courses:manage', $categorycontext)){
        //     $actions = True;
        //     if(!empty($category->visible)){
        //         $visible_value = 0;
        //         $show = True;
        //     }else{
        //         $visible_value = 1;
        //         $show = False;
        //     }
        // }
        return $this->render_from_template('local_courses/parent_template', $data); 
    }

  function display_course_enrolledusers($courseid){
    global $DB;
  
    $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');
    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($courseid);
    $maincheckcontext = (new \local_courses\lib\accesslib())::get_module_context();
    if(is_siteadmin() || ((has_capability('local/courses:enrol',
                                $maincheckcontext)  || is_siteadmin())&&has_capability('local/courses:manage', $maincheckcontext))) {
                $enrolid = $DB->get_field('enrol', 'id', array('courseid' => $courseid ,'enrol' => 'manual'));
                $userenrollment = true;
        }
    $info = array();
    $info['enrolid'] = $enrolid;
    $info['courseid'] = $courseid;
    
    if($certificate_plugin_exist){
      $certificate = $DB->get_field('course', 'open_certificateid', array('id'=>$courseid));
      if($certificate){
        $info['added_certificate'] = true;
      }else{
        $info['added_certificate'] = false;
      }
    }    

    if(is_siteadmin() || (has_capability('local/courses:managecourses',$categorycontext))) {

         $info['actions'] = true;

    }else{

        $info['actions'] = false;

      }
    return $this->render_from_template('local_courses/courseusersview', $info);
  }

  function get_course_enrolledusers($dataobj){
    global $DB, $USER, $OUTPUT, $CFG;

    $countsql = "SELECT COUNT(ue.id) ";

    $selectsql = "SELECT DISTINCT(u.id) as userid,ue.id,u.firstname, u.lastname, u.email, u.open_employeeid, 
            cc.timecompleted";

    $sql = " FROM {course} c
            JOIN {course_categories} cat ON cat.id = c.category
            JOIN {enrol} e ON e.courseid = c.id AND 
                        (e.enrol = 'manual' OR e.enrol = 'self' OR e.enrol = 'classroom' OR e.enrol = 'learningplan' OR e.enrol = 'fee' OR e.enrol = 'auto')
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended=0
            JOIN {local_costcenter} lc ON lc.path = u.open_path
            JOIN {role_assignments} as ra ON ra.userid = u.id
            JOIN {context} AS cxt ON cxt.id=ra.contextid AND cxt.contextlevel = 50 AND cxt.instanceid=c.id
            JOIN {role} as r ON r.id = ra.roleid AND r.shortname = 'employee'
            LEFT JOIN {course_completions} as cc ON cc.course = c.id AND u.id = cc.userid 
            WHERE c.id = :courseid ";
   
    $params = array();
    $params['courseid'] = $dataobj->courseid;

    $categorycontext = (new \local_courses\lib\accesslib())::get_module_context($dataobj->courseid);


    $sql .= (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

    if (!empty($dataobj->search)) {
      $concatsql = " AND ( CONCAT(u.firstname,' ',u.lastname) LIKE '%".$dataobj->search."%' OR
                          u.open_employeeid LIKE '%".$dataobj->search."%' ) ";
    }else{
      $concatsql = '';
    }

    $courseusers = $DB->get_records_sql($selectsql.$sql.$concatsql , $params, $dataobj->start, $dataobj->length);
    $enrolleduserscount = $DB->count_records_sql($countsql.$sql.$concatsql , $params);
    $userslist = array();
    if($courseusers){
      $userslist = array();

      $enrolledcount = $enrolleduserscount;

      $certificate_plugin_exist = \core_component::get_plugin_directory('tool', 'certificate');

      
      if($certificate_plugin_exist){
        $cert_plugin_exists = true;
        $certificate = $DB->get_field('course', 'open_certificateid', array('id'=>$dataobj->courseid));
        if($certificate){
          $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
          $certificate_added = true;
        }else{
          $certificate_added = false;
        }
      }else{
        $cert_plugin_exists = false;
      }
//var_dump($certificate_added);die;
      foreach ($courseusers as $enroluser) {
        $userinfo = array();
        $userinfo[] = $enroluser->firstname.' '.$enroluser->lastname;
        $userinfo[] = $enroluser->open_employeeid;
        $userinfo[] = $enroluser->email;
        if($enroluser->timecompleted){
          $userinfo[] = get_string('completed', 'local_courses');
          $userinfo[] = \local_costcenter\lib::get_userdate('d/m/Y H:i a', $enroluser->timecompleted);
        }else{
          $userinfo[] = get_string('notcompleted', 'local_courses');
          $userinfo[] = 'N/A';
        }

        
        $get_enrolid = "";
        $get_enrolmentod = "";
        $sql = "SELECT ue.id,e.enrol FROM {user_enrolments} as ue
                JOIN {enrol} as e ON e.id = ue.enrolid 
                WHERE e.courseid = $dataobj->courseid AND ue.userid =$enroluser->userid ";
        $userenrolment = $DB->get_records_sql($sql);
        $enrolmethod = array();
        $enroll = array();
        foreach($userenrolment as $userenrol){
          $enroll[] = ucfirst($userenrol->enrol);

         if(is_siteadmin() || (has_capability('local/courses:managecourses', $categorycontext))) {
         $icon = '<i class="icon fa fa-pencil" aria-hidden="true"></i>';
         $array = array('id'=>$dataobj->courseid,'ue'=>$userenrol->id);
         $url = new moodle_url('editenrol.php', $array);
         $options = array('title'=>get_string('edit', 'local_courses'));
         $courseedit = html_writer::link($url, $icon, $options);
         $deleteurl = 'javascript:void(0)';
         $deleteicon = '<i class="icon fa fa-trash fa-fw"></i>';
         $array = array('title'=>get_string('delete'),
                  'alt'=>get_string('delete'),
                  'onclick'=>"(function(e){ require('local_courses/courses').deleteuser({ action:'delete_user',userid:".$userenrol->id.",id:".$dataobj->courseid."}) })(event)");
          $delete = html_writer::link($deleteurl, $deleteicon, $array);
          $enrolmethod[] = $courseedit.$delete/*.$this->render(new local_courses\output\courseevidenceview($dataobj->courseid,$enroluser->userid,'userview'))*/;
       }
        }
        $userinfo[] = implode('<br />',$enroll);

       $userinfo[] = implode(' <br>',$enrolmethod);

       if($cert_plugin_exists && $certificate_added){
          if(!empty($enroluser->timecompleted)){
            $icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
//                        mallikarjun added to download default certificate 
            $certcode = $DB->get_field('tool_certificate_issues', 'code', array('moduleid'=>$dataobj->courseid,'userid'=>$enroluser->userid,'moduletype'=>'course'));
            $array = array('code' =>$certcode);
            $url = new moodle_url('/admin/tool/certificate/view.php', $array);
            $options = array('title'=>get_string('download_certificate', 'local_courses'),'target'=>'_blank');
            $userinfo[] = html_writer::link($url, $icon, $options);
          }else{
            //$icon = '<i class="icon fa fa-download" aria-hidden="true"></i>';
            $url = 'javascript: void(0)';
            $userinfo[] = html_writer::tag($url,get_string('notassigned','local_classroom'));
          }
        }
       //  if(is_siteadmin() || (has_capability('local/courses:managecourses', context_system:: instance()))) {
       //   $icon = '<i class="icon fa fa-pencil" aria-hidden="true"></i>';
       //   $array = array('id'=>$dataobj->courseid,'ue'=>$enroluser->id);
       //   $url = new moodle_url('editenrol.php', $array);
       //   $options = array('title'=>get_string('edit', 'local_courses'));
       //   $courseedit= html_writer::link($url, $icon, $options);
       // }
       //  if(is_siteadmin() || (has_capability('local/courses:managecourses', context_system:: instance()))) {
       //    $deleteurl = 'javascript:void(0)';
       //    $deleteicon = '<i class="icon fa fa-trash fa-fw"></i>';
       //    $array = array('title'=>get_string('delete'),
       //            'alt'=>get_string('delete'),
       //            'onclick'=>"(function(e){ require('local_courses/courses').deleteuser({ action:'delete_user',userid:".$enroluser->id.",id:".$dataobj->courseid."}) })(event)");
       //    $delete = html_writer::link($deleteurl, $deleteicon, $array);
       // }
        //$userinfo[]=$courseedit.$delete;
        $userslist[] = $userinfo;
      }

      $return = array(
          // "recordsTotal" => $enrolleduserscount,
          "recordsFiltered" => $enrolleduserscount,
          "data" => $userslist,
      );
    }else{
      $return = array(
          // "recordsTotal" => $enrolleduserscount,
          "recordsFiltered" => 0,
          "data" => array(),
      );
    }
    return $return;
  }
    public function get_userdashboard_courses($tab, $filter = false,$view_type = 'card') {
        $categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
        
        
        $templateName = 'local_courses/userdashboard_paginated';
        $cardClass = 'col-md-6 col-12';
        $perpage = 6;
        if($view_type=='table'){
            $templateName = 'local_courses/userdashboard_paginated_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
        } 

        $options = array('targetID' => 'dashboard_courses', 'perPage' => $perpage, 'cardClass' =>$cardClass, 'viewType' => $view_type);
        $options['methodName']='local_courses_userdashboard_content_paginated';
        $options['templateName']= $templateName;
        $options['filter'] = $tab;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $categorycontext->id));
        $context = [
                'targetID' => 'dashboard_courses',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }
    /**
     * Render the courseevidenceview
     * @param  courseevidenceview $widget
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_courseevidenceview(\local_courses\output\courseevidenceview $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/courseevidence', $data);
    }
    /**
     * Render the selfcompletion
     * @param  selfcompletion $widget
     * @return bool|string
     * @throws moodle_exception
     */
    protected function render_selfcompletion(\local_courses\output\selfcompletion $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_courses/selfcompletion', $data);
    }

    public function render_courses_index() {
        global $DB, $OUTPUT, $CFG;
        // Fetch visible courses with the 'topics' format from the database
        
        $sql = "SELECT c.* FROM {course} c
                  JOIN {local_dashboardcourses} ldc 
                       ON FIND_IN_SET(c.id, ldc.courseids) > 0
                    WHERE c.visible > 0
              ORDER BY c.id DESC";
        $courses = $DB->get_records_sql($sql);

        $coursesdata = [];
        $tempCourses = [];
        $active = 'active'; // The 'active' class for the first carousel item

        // Loop through courses and group them in sets of three
        foreach ($courses as $course) {
            // Clean up course summary
            $coursesummary = strip_tags(format_text($course->summary));
            $summarystring = strlen($coursesummary) > 100 ? substr($coursesummary, 0, 100) . " ..." : $coursesummary;
            $coursetype = $DB->get_field('local_course_types', 'name', ['id' => $course->open_identifiedas]);

            // Check for course image
            $img = '';
            if (file_exists($CFG->dirroot . '/local/includes.php')) {
                require_once($CFG->dirroot . '/local/includes.php');
                $includes = new user_course_details();
                $courseimage = $includes->course_summary_files($course);
                if (is_object($courseimage)) {
                    $img = $courseimage->out();
                } else {
                    $img = $courseimage;
                }
            }
            if($course->price_status == 1){
                $addtocart = new moodle_url('local/search/coursedetails.php', ['id' => $course->id]);
                $courseprice = $course->courseprice;
            } else {
                $courseprice = '';
            }
            $categoryname = $DB->get_field('course_categories','name',array('id'=>$course->category));
            // Build the current course data
            $courseData = [
                'name' => format_string($course->fullname),
                'summary' => $summarystring,
                'coursetype' => $coursetype,
                'courseimage' => $img,
                'addtocart' => $addtocart,
                'courseprice' => $courseprice,
                'categoryname' => $categoryname,
                'price_status' => $course->price_status == 1 ? TRUE : FALSE,
                'buy' => $addtocart,
                'viewmoreurl' => new moodle_url('local/search/coursedetails.php', ['id' => $course->id])

            ];
            // Assign to courseone, coursetwo, or coursethree
            if (empty($tempCourses['courseone'])) {
                $tempCourses['courseone'] = $courseData; // Add first course to 'courseone'
            } elseif (empty($tempCourses['coursetwo'])) {
                $tempCourses['coursetwo'] = $courseData; // Add second course to 'coursetwo'
            } else {
                $tempCourses['coursethree'] = $courseData; // Add third course to 'coursethree'
                $tempCourses['active'] = $active; // Set active class for the first group
                $coursesdata[] = $tempCourses; // Push the group of three to $coursesdata
                $tempCourses = []; // Reset for the next group
                $active = null; // Remove 'active' class after the first set
            }

        }

        // If there are leftover courses (less than 3), add them
        if (!empty($tempCourses)) {
            $tempCourses['active'] = $active; // Ensure the active class is applied if it's the first set
            $coursesdata[] = $tempCourses;
        }

        // Pass data to the Mustache template
        $context = [
            'courses' => $coursesdata
        ];

        return $OUTPUT->render_from_template('local_courses/nologincourses', $context);
    }
}
