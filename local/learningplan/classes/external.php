<?php
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
class local_learningplan_external extends external_api {
	public static function submit_learningplan_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'planid', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array', false),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0)
            )
        );
    }
    public function submit_learningplan($id, $contextid, $jsonformdata, $form_status){
        global $DB;
		$categorycontext  = (new \local_learningplan\lib\accesslib())::get_module_context($id);
        // We always must call validate_context in a webservice.
		self::validate_context($categorycontext);
		$serialiseddata = json_decode($jsonformdata);

		$data = array();
        parse_str($serialiseddata, $data);
        $mform = new local_learningplan\forms\learningplan(null, array('form_status' => $form_status, 'id' => $data['id'],'costcenterid' => $data['costcenter']), 'post', '', null, true, $data);
		$validateddata = $mform->get_data();
        $leplib = new local_learningplan\lib\lib();
        if($validateddata){
            // if(!empty($validateddata->open_costcenterid)){
            //     $validateddata->costcenter = $validateddata->open_costcenterid;
            // }
            if($validateddata->id > 0){
                // $get_costcenter = $DB->get_field('local_learningplan', 'open_path', array('id' => $validateddata->id));
                // $costcenterid = explode('/', $get_costcenter)[1];
                // if((($validateddata->form_status == 0) && ($costcenterid != $validateddata->open_costcenterid)) || $validateddata->form_status == 1){
                //     local_costcenter_get_costcenter_path($validateddata);
                // }

                $open_path=$DB->get_field('local_learningplan', 'open_path', array('id' => $validateddata->id));
                list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$open_path);

                if(!empty($validateddata->open_costcenterid) != $org){
                    local_costcenter_get_costcenter_path($validateddata);
                }
                if($validateddata->form_status == 1){
                if((!empty($validateddata->open_costcenterid) != $org) || ($validateddata->open_department !=$ctr) || ($validateddata->open_subdepartment !=$bu) || ($validateddata->open_level4department !=$cu) || ($validateddata->open_level5department !=$territory)){
                    local_costcenter_get_costcenter_path($validateddata);
                }
                    local_users_get_userprofile_datafields($validateddata);
                }
                $lepid = $leplib->update_learning_plan($validateddata);
            } else{
                local_costcenter_get_costcenter_path($validateddata);
                $lepid = $leplib->create_learning_plan($validateddata);
			}
            if(class_exists('\block_trending_modules\lib')){
                $trendingclass = new \block_trending_modules\lib();
                if(method_exists($trendingclass, 'trending_modules_crud')){
                    $trendingclass->trending_modules_crud($lepid, 'local_learningplan');
                }
            }
            $formheaders = array_keys($mform->formstatus);
            $next = $form_status + 1;
            $nextform = array_key_exists($next, $formheaders);
            if ($nextform !== false) {
                $form_status = $next;
                $error = false;
            } else {
                $form_status = -1;
            }
		} else {
			// Generate a warning.
            throw new moodle_exception('Error in creation');
		}
		$return = array(
            'id' => $lepid,
            'form_status' => $form_status);
        return $return;
    }


    public static function submit_learningplan_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'learningplan id'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }


    public static function delete_learningplan_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
                'name' => new external_value(PARAM_RAW, 'name', false),
            )
        );
    }

    public static function delete_learningplan($action, $id, $confirm, $name) {
        global $DB;
        try {
            if ($confirm) {
                $learningplanlib = new local_learningplan\lib\lib();
                $learningplanlib->delete_learning_plan($id);
                if(class_exists('\block_trending_modules\lib')){
                    $trendingclass = new \block_trending_modules\lib();
                    if(method_exists($trendingclass, 'trending_modules_crud')){
                        $plan_object = new stdClass();
                        $plan_object->id = $id;
                        $plan_object->module_type = 'local_learningplan';
                        $plan_object->delete_record = True;
                        $trendingclass->trending_modules_crud($plan_object, 'local_learningplan');
                    }
            }
                $return = true;
            } else {
                $return = false;
            }
        } catch (dml_exception $ex) {
            print_error('deleteerror', 'local_learningplan');
            $return = false;
        }
        return $return;
    }

    public static function delete_learningplan_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }
    public static function toggle_learningplan_parameters() {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'visible' => new external_value(PARAM_TEXT,'Visible or hidden text',false),
                'confirm' => new external_value(PARAM_BOOL, 'Confirm', false),
                'name' => new external_value(PARAM_RAW, 'name', false),
            )
        );
    }
    public static function toggle_learningplan($action, $id, $visible, $confirm, $name) {
        try {
            if ($confirm) {
                $learningplanlib = new local_learningplan\lib\lib();
                $learningplanlib->togglelearningplan($id);
                $return = true;
            } else {
                $return = false;
            }
        } catch (dml_exception $ex) {
            echo 'Message: ' .$ex->getMessage();
            print_error('toggleerror', 'local_learningplan');
            $return = false;
        }
        return $return;
    }
    public static function toggle_learningplan_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }

    public static function lpcourse_enrol_form_parameters() {
        return new external_function_parameters(
            array(
                'planid' => new external_value(PARAM_INT, 'planid', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the courseenrolform, encoded as a json array', false)
            )
        );
    }
    public static function lpcourse_enrol_form($planid,$contextid, $jsonformdata) {
        global $DB;
        $categorycontext =(new \local_learningplan\lib\accesslib())::get_module_context($planid);
        // We always must call validate_context in a webservice.
        self::validate_context($categorycontext);
        $serialiseddata = json_decode($jsonformdata);

        $data = array();
        parse_str($serialiseddata, $data);

        $mform = new local_learningplan\forms\courseenrolform(null,array('planid' => $planid, 'condition' => 'manage'), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        if($validateddata){
             $courses = array();
            foreach($validateddata->learning_plan_courses as $value){

                    $courses[] = $value;

            
            }
            $lib = new local_learningplan\lib\lib();
            $return = $lib->modal_lpcourse_enrol($courses,$planid);
            //$data object extra parameter sending by sarath for moduletype
            //$return = $lib->modal_lpcourse_enrol($courses,$planid,$data);
        }
        else{
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
        return $return;
    }
    public static function lpcourse_enrol_form_returns() {
        return new external_value(PARAM_BOOL, 'return');
    }

    public static function lpcourse_unassign_course_parameters(){
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'ID of the course to be unassigned', 0),
                'planid' => new external_value(PARAM_INT, 'ID of the learningplan', 0),
            )
        );
    }
    public static function lpcourse_unassign_course($courseid,$planid){
        if($courseid>0 && $planid >0){
            $learningplanlib = new local_learningplan\lib\lib();
            $learningplanlib->unassign_delete_courses_to_learningplans($courseid,$planid);
            return true;
        }else{
            throw new moodle_exception('Error in unassigning of course');
            return false;
        }

    }
    public static function lpcourse_unassign_course_returns(){
        return new external_value(PARAM_BOOL, 'return');
    }
	public static function lpcourse_unassign_user_parameters(){
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'ID of the user to be unassigned', 0),
                'planid' => new external_value(PARAM_INT, 'ID of the learningplan', 0),
            )
        );
    }
    public static function lpcourse_unassign_user($userid,$planid){
        if($userid>0 && $planid >0){
            $learningplanlib = new local_learningplan\lib\lib();
            $learningplanlib->unassign_delete_users_to_learningplans($userid,$planid);
            return true;
        }else{
            throw new moodle_exception('Error in unassigning of course');
            return false;
        }

    }
    public static function lpcourse_unassign_user_returns(){
        return new external_value(PARAM_BOOL, 'return');
    }

    /**
     * [userlearningplans_parameters description]
     * @return [type] [description]
     */
    public static function userlearningplans_parameters() {
        return new external_function_parameters(
            array(
                'status' => new external_value(PARAM_ALPHA, 'Status', VALUE_OPTIONAL, 'inprogress'),
                'search' => new external_value(PARAM_RAW, 'search', VALUE_OPTIONAL, ''),
                'page' => new external_value(PARAM_INT, 'page', VALUE_OPTIONAL, 0),
                'perpage' => new external_value(PARAM_INT, 'perpage', VALUE_OPTIONAL, 10)
            )
        );
    }

    /**
     * [userlearningplans description]
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public static function userlearningplans($status = 'inprogress', $search = '', $page = 0, $perpage= 10) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot.'/local/ratings/lib.php');
        //validate parameter
        $params = self::validate_parameters(self::userlearningplans_parameters(),
                        array('status' => $status, 'search' => $search, 'page' => $page, 'perpage' => $perpage));
        list($userlearningplans,$total) = \local_learningplan\learningplan::userlearningplans($status, $search, '',true,$page,$perpage);
        $result = [];
        foreach ($userlearningplans as $lp) {
            $learningplans = array();
            $learningplans['id'] = $lp->id;
            $learningplans['name'] = $lp->name;
            $learningplans['description'] = $lp->description;
            $learningplans['learning_type'] = $lp->learning_type;
            $learningplans['learningplantype'] = $lp->learningplantype;
            $learningplans['credits'] = $lp->open_points;
            $learningplans['mandatory'] = $lp->mandatory;
            $learningplans['optional'] = $lp->optional;
            $modulerating = $DB->get_field('local_ratings_likes', 'module_rating', array('module_id' => $lp->id, 'module_area' => 'local_learningplan'));
            if(!$modulerating){
                 $modulerating = 0;
            }
            $likes = $DB->count_records('local_like', array('likearea'=> 'local_learningplan', 'itemid'=>$lp->id, 'likestatus'=>'1'));
            $dislikes = $DB->count_records('local_like', array('likearea'=> 'local_learningplan', 'itemid'=>$lp->id, 'likestatus'=>'2'));
            $learningplans['rating'] = $modulerating;
            $learningplans['likes'] = $likes;
            $learningplans['dislikes'] = $dislikes;
            $avgratings = get_rating($lp->id, 'local_learningplan');
            $avgrating = $avgratings->avg;
            $ratingusers = $avgratings->count;
            $learningplans['avgrating'] = $avgrating;
            $learningplans['ratingusers'] = $ratingusers;
            $result[] = $learningplans;
        }
        return array('lps' => $result, 'total' => $total);
    }

    /**
     * [userlearningplans_returns description]
     * @return [type] [description]
     */
    public static function userlearningplans_returns() {
        return new external_single_structure(
            array(
                'lps' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Learning Path id'),
                            'name' => new external_value(PARAM_TEXT, 'Learning Path name'),
                            'learning_type' => new external_value(PARAM_INT, 'learning_type'),
                            'learningplantype' => new external_value(PARAM_RAW, 'learningplantype'),
                            'credits' => new external_value(PARAM_RAW, 'Credits'),
                            'description' => new external_value(PARAM_RAW, 'Description'),
                            'optional' => new external_value(PARAM_INT, 'Optional Courses Count'),
                            'mandatory' => new external_value(PARAM_INT, 'Mandatory '),
                            'optional' => new external_value(PARAM_INT, 'Optional Courses Count'),
                            'rating' => new external_value(PARAM_RAW, 'LearningPath rating'),
                            'avgrating' => new external_value(PARAM_FLOAT, 'Course Avg rating'),
                            'ratingusers' => new external_value(PARAM_INT, 'Course rating users'),
                            'likes' => new external_value(PARAM_INT, 'LearningPath Likes'),
                            'dislikes' => new external_value(PARAM_INT, 'LearningPath Dislikes'),
                            ), 'Learning Paths'
                        )
                ),
                'total' => new external_value(PARAM_INT, 'Total')
            )
        );
    }
    /**
     * [userlearningplans_parameters description]
     * @return [type] [description]
     */
    public static function userlearningplancourses_parameters() {
        return new external_function_parameters(
            array(
                'lpid' => new external_value(PARAM_INT, 'lpid'),
                'search' => new external_value(PARAM_RAW, 'search', VALUE_OPTIONAL, ''),
                'page' => new external_value(PARAM_INT, 'page', VALUE_OPTIONAL, 0),
                'perpage' => new external_value(PARAM_INT, 'perpage', VALUE_OPTIONAL, 10),
                'source' => new external_value(PARAM_TEXT, 'source of call', VALUE_OPTIONAL, 'mobile')
            )
        );
    }
    /**
     * [userlearningplans description]
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public static function userlearningplancourses($lpid, $search = '', $page=0, $perpage=10, $source = 'mobile') {
        global $CFG, $DB, $USER;
        //validate parameter
        $params = self::validate_parameters(self::userlearningplancourses_parameters(),
                        array('lpid' => $lpid, 'search' => $search, 'page' => $page, 'perpage' => $perpage, 'source' => $source));
        $lpname = $DB->get_field('local_learningplan', 'name', array('id' => $lpid));
        list($userlearningplans,$total) = \local_learningplan\learningplan::userlearningplancoursesInfo($lpid, $search, $page, $perpage, $source);
        return array('lpcourses' => $userlearningplans, 'lpname' => $lpname, 'total' => $total);
        // return $userlearningplans;
    }

    /**
     * [userlearningplans_returns description]
     * @return [type] [description]
     */
    public static function userlearningplancourses_returns() {
        return new external_single_structure(
            array(
                'lpcourses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Learning Path name'),
                            'fullname' => new external_value(PARAM_RAW, 'learning_type'),
                            'visible' => new external_value(PARAM_RAW, 'Learning Path id'),
                            'sortorder' => new external_value(PARAM_RAW, 'learningplantype'),
                            'lepid' => new external_value(PARAM_RAW, 'Description'),
                            'next' => new external_value(PARAM_RAW, 'Optional Courses Count'),
                            'coursetype' => new external_value(PARAM_RAW, 'Mandatory '),
                            'progress' => new external_value(PARAM_FLOAT, 'Progress percentage'),
                            'summary' => new external_value(PARAM_RAW, 'Course Summary'),
                            'rating' => new external_value(PARAM_INT, 'LearningPath rating'),
                            'likes' => new external_value(PARAM_INT, 'LearningPath Likes'),
                            'dislikes' => new external_value(PARAM_INT, 'LearningPath Dislikes'),
                            'avgrating' => new external_value(PARAM_FLOAT, 'Course Avg rating'),
                            'ratingusers' => new external_value(PARAM_INT, 'Course rating users'),
                            'completedon' => new external_value(PARAM_RAW, 'Course copleted on'),
                            ), 'Learning Paths'
                        )
                ),
                'lpname' => new external_value(PARAM_RAW, 'LearningPath Name'),
                'total' => new external_value(PARAM_INT, 'Total'),
            )
        );
    }
        public static function get_upcominglps_parameters() {
        return new external_function_parameters(
            array(
            )
        );
    }
    /**
     * [userlearningplans description]
     * @param  array  $options [description]
     * @return [type]          [description]
     */
    public static function get_upcominglps() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot.'/local/ratings/lib.php');

        //validate parameter
        $lps = \local_learningplan\learningplan::userlearningplans('inprogress', '', 1,'','','');
        $result = [];
        foreach ($lps as $lp) {
            $learningplans = array();
            $learningplans['id'] = $lp->id;
            $learningplans['name'] = $lp->name;
            $learningplans['description'] = $lp->description;
            $learningplans['learning_type'] = $lp->learning_type;
            $learningplans['learningplantype'] = $lp->learningplantype;
            $learningplans['credits'] = $lp->open_points;
            $learningplans['mandatory'] = $lp->mandatory;
            $learningplans['optional'] = $lp->optional;
            $modulerating = $DB->get_field('local_ratings_likes', 'module_rating', array('module_id' => $lp->id, 'module_area' => 'local_learningplan'));
            if(!$modulerating){
                 $modulerating = 0;
            }
            $likes = $DB->count_records('local_like', array('likearea'=> 'local_learningplan', 'itemid'=>$lp->id, 'likestatus'=>'1'));
            $dislikes = $DB->count_records('local_like', array('likearea'=> 'local_learningplan', 'itemid'=>$lp->id, 'likestatus'=>'2'));
            $learningplans['rating'] = $modulerating;
            $learningplans['likes'] = $likes;
            $learningplans['dislikes'] = $dislikes;
            $avgratings = get_rating($lp->id, 'local_learningplan');
            $avgrating = $avgratings->avg;
            $ratingusers = $avgratings->count;
            $learningplans['avgrating'] = $avgrating;
            $learningplans['ratingusers'] = $ratingusers;
            $result[] = $learningplans;
        }
        return $result;
    }

    /**
     * [userlearningplans_returns description]
     * @return [type] [description]
     */
    public static function get_upcominglps_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Learning Path id'),
                    'name' => new external_value(PARAM_TEXT, 'Learning Path name'),
                    'learning_type' => new external_value(PARAM_INT, 'learning_type'),
                    'learningplantype' => new external_value(PARAM_RAW, 'learningplantype'),
                    'credits' => new external_value(PARAM_RAW, 'Credits'),
                    'description' => new external_value(PARAM_RAW, 'Description'),
                    'optional' => new external_value(PARAM_INT, 'Optional Courses Count'),
                    'mandatory' => new external_value(PARAM_INT, 'Mandatory '),
                    'optional' => new external_value(PARAM_INT, 'Optional Courses Count'),
                    'rating' => new external_value(PARAM_RAW, 'LearningPath rating'),
                    'avgrating' => new external_value(PARAM_FLOAT, 'Course Avg rating'),
                    'ratingusers' => new external_value(PARAM_INT, 'Course rating users'),
                    'likes' => new external_value(PARAM_INT, 'LearningPath Likes'),
                    'dislikes' => new external_value(PARAM_INT, 'LearningPath Dislikes'),
                )
            )
        );
    }
    public static function learningplan_form_option_selector_parameters() {
        $query = new external_value(
            PARAM_RAW,
            'Query string'
        );
        $action = new external_value(
            PARAM_RAW,
            'Action for the classroom form selector'
        );
        $options = new external_value(
            PARAM_RAW,
            'Action for the classroom form selector'
        );
        // $limitfrom = new external_value(
        //  PARAM_INT,
        //  'limitfrom we are fetching the records from',
        //  VALUE_DEFAULT,
        //  0
        // );
        // $limitnum = new external_value(
        //  PARAM_INT,
        //  'Number of records to fetch',
        //  VALUE_DEFAULT,
        //  25
        // );
        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'action' => $action,
            'options' => $options,
            // 'limitfrom' => $limitfrom,
            // 'limitnum' => $limitnum,
        ));
    }

    public static function learningplan_form_option_selector($query, $context, $action, $options/*, $limitfrom = 0, $limitnum = 25*/) {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::learningplan_form_option_selector_parameters(), array(
            'query' => $query,
            'context' => $context,
            'action' => $action,
            'options' => $options
            // 'limitfrom' => $limitfrom,
            // 'limitnum' => $limitnum,
        ));
        $query = $params['query'];
        $action = $params['action'];
        $context = self::get_context_from_params($params['context']);
        $options = $params['options'];
        if (!empty($options)) {
            $formoptions = json_decode($options);
        }

        // $limitfrom = $params['limitfrom'];
        // $limitnum = $params['limitnum'];
        //

        self::validate_context($context);
        if ($query && $action) {
            $return = array();
            switch($action) {

                case 'learningplan_subdepartment_selector':
                    if($formoptions->departments_selected){
                        if(is_array($formoptions->departments_selected) && count($formoptions->departments_selected) > 1){
                            $return = array(-1 => array('id' => -1,'fullname' => 'All'));
                        }else{

                            $departments_selected = is_array($formoptions->departments_selected) ? implode(',', $formoptions->departments_selected) : $formoptions->departments_selected;
                            if($departments_selected == -1 && $formoptions->id){
                                $costcenter = $DB->get_field('local_learningplan', 'open_path', array('id' => $formoptions->id));
                                $subdept_sql = "SELECT id, fullname
                                                FROM {local_costcenter}
                                                WHERE visible = 1 AND parentid IN (SELECT id FROM {local_costcenter} WHERE parentid = :parentid ) ";
                                $params['parentid'] = $costcenter;
                            }else{
                                $subdept_sql = "SELECT id, fullname
                                                FROM {local_costcenter}
                                                WHERE visible = 1
                                                AND CONCAT(',',:departments_selected,',') LIKE CONCAT('%,',parentid,',%') ";
                                // print_object($formoptions->departments_selected);
                                $params['departments_selected'] = $departments_selected;
                            }
                                $depth = $formoptions->depth;
                                if ($depth > 0) {
                                    $subdept_sql .= " AND depth = :depth ";
                                    $params['depth'] = $depth;
                                }
                                if (!empty($query)) {
                                    $subdept_sql .= " AND fullname LIKE :query ";
                                    $params['query'] = '%' . $query . '%';
                                }
                                $return = array(-1 => array('id' => -1,'fullname' => 'All'))+$DB->get_records_sql($subdept_sql, $params);
                        }
                    }
                break;
            }
            return json_encode($return);
        }
    }
    public static function learningplan_form_option_selector_returns() {
        return new external_value(PARAM_RAW, 'data');
    }
    public static function data_for_learningplans_parameters(){
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $filter_offset = new external_value(PARAM_INT, 'Offset value',VALUE_OPTIONAL);
        $filter_limit = new external_value(PARAM_INT, 'Limit value',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        );
        return new external_function_parameters($params);
    }
    public static function data_for_learningplans($filter, $filter_text='', $filter_offset = 0, $filter_limit = 0){
        global $PAGE;

        $params = self::validate_parameters(self::data_for_learningplans_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        ));

        $PAGE->set_context((new \local_learningplan\lib\accesslib())::get_module_context());
        $renderable = new \local_learningplan\output\learningplan_courses($params['filter'],$params['filter_text'], $params['filter_offset'], $params['filter_limit']);
        $output = $PAGE->get_renderer('local_learningplan');

        $data= $renderable->export_for_template($output);

        return $data;
    }
    public static function data_for_learningplans_returns(){
        return new external_single_structure(array (
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'),
            'plan_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'),
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
            'functionname' => new external_value(PARAM_TEXT, 'Function name'),
            'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
            'learningplantemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
            'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, true),
            'moduledetails' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'pathcourses' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'coursename' => new external_value(PARAM_RAW, 'Course Name'),
                                    'coursename_string' => new external_value(PARAM_RAW, 'Course name String')
                                )
                            )
                        ),
                        'lastaccessdate' => new external_value(PARAM_RAW, 'last access date'),
                        'plan_image_url' => new external_value(PARAM_RAW, 'plan_image_url', VALUE_OPTIONAL),
                        'planSummary' => new external_value(PARAM_RAW, 'Plan Summary'),
                        'planFullname' => new external_value(PARAM_RAW, 'Plan Fullname'),
                        'displayPlanFullname' => new external_value(PARAM_RAW, 'Displayed Plan Fullname'),
                        'planUrl' => new external_value(PARAM_URL, 'Plan navigation url'),
                        'rating_element' => new external_value(PARAM_RAW, 'Plan ratings'),
                        'index' => new external_value(PARAM_INT, 'Index of Card'),
                        'course_completedon' => new external_value(PARAM_RAW, 'course_completedon'),
                        'label_name' => new external_value(PARAM_RAW, 'course_completedon'),
                        'ratingavg' => new external_value(PARAM_RAW, 'Plan Ratings'),
                        'completedpathpercent' => new external_value(PARAM_RAW, 'Plan Ratings'),                        
                        'lpstatus' => new external_value(PARAM_RAW, 'Plan Ratings'),                        
                    )
                )
            ),
            'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
            'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
            'index' => new external_value(PARAM_INT, 'number of courses count'),
            'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
            'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
            'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
            'viewMoreCard' => new external_value(PARAM_BOOL, 'More info card to display'),
            'enrolled_url' => new external_value(PARAM_URL, 'enrolled_url for tab'),//added revathi
            'inprogress_url' => new external_value(PARAM_URL, 'inprogress_url for tab'),
            'completed_url' => new external_value(PARAM_URL, 'completed_url for tab'),
        ));
    }
    public static function data_for_learningplans_paginated_parameters(){
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                VALUE_DEFAULT, 0),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'filters applied'),
        ]);
    }
    public static function data_for_learningplans_paginated($options, $dataoptions, $offset = 0, $limit = 0, $contextid, $filterdata){
        global $DB, $PAGE;
        require_login();
        $PAGE->set_context($contextid);

        $decodedoptions = (array)json_decode($options);
        $filter = $decodedoptions['filter'];
        $PAGE->set_url('/local/certification/userdashboard.php', array('tab' => $filter));
        $filter_text = '';
        $filter_offset = $offset;
        $filter_limit = $limit;

        $PAGE->set_context((new \local_learningplan\lib\accesslib())::get_module_context());
        $renderable = new \local_learningplan\output\learningplan_courses($filter, $filter_text, $filter_offset, $filter_limit);
        $output = $PAGE->get_renderer('local_learningplan');

        $data = $renderable->export_for_template($output);
        $totalcount = $renderable->coursesViewCount;
        return [
            'totalcount' => $totalcount,
            'length' => $totalcount,
            'filterdata' => $filterdata,
            'records' => array($data),
            'options' => $options,
            'dataoptions' => $dataoptions,
        ];
    }
    public static function data_for_learningplans_paginated_returns(){
        return new external_single_structure([
        'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
        'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
        'totalcount' => new external_value(PARAM_INT, 'total number of challenges in result set'),
        'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        'records' => new external_multiple_structure(
                new external_single_structure(array (
                    'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),
                    'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),
                    'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'),
                    'plan_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'),
                    // 'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
                    'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
                    'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
                    'functionname' => new external_value(PARAM_TEXT, 'Function name'),
                    'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
                    'learningplantemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                    'enableflow' => new external_value(PARAM_BOOL, "flag for flow enabling", VALUE_DEFAULT, false),
                    'moduledetails' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'pathcourses' => new external_multiple_structure(
                                    new external_single_structure(
                                        array(
                                            'coursename' => new external_value(PARAM_RAW, 'Course Name'),
                                            'coursename_string' => new external_value(PARAM_RAW, 'Course name String')
                                        )
                                    )
                                ),
                                'lastaccessdate' => new external_value(PARAM_RAW, 'last access date'),
                                'planSummary' => new external_value(PARAM_RAW, 'Plan Summary'),
                                'planFullname' => new external_value(PARAM_RAW, 'Plan Fullname'),
                                'displayPlanFullname' => new external_value(PARAM_RAW, 'Displayed Plan Fullname'),
                                'planUrl' => new external_value(PARAM_URL, 'Plan navigation url'),
                                'rating_element' => new external_value(PARAM_RAW, 'Plan ratings'),
                                'course_completedon' => new external_value(PARAM_RAW, 'course_completedon'),
                                'label_name' => new external_value(PARAM_RAW, 'course_completedon'),
                                'ratingavg' => new external_value(PARAM_RAW, 'Plan Ratings'),
                                'completedpathpercent' => new external_value(PARAM_RAW, 'Plan Ratings'),
                                'lpstatus' => new external_value(PARAM_RAW, 'Plan Ratings'),                                
                            )
                        )
                    ),
                    'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                    'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
                    'index' => new external_value(PARAM_INT, 'number of courses count'),
                    'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
                    'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
                    // 'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
                )
            )
        )
    ]);
    }
    public static function get_learningplan_info_parameters(){
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'The id of the module'),
            )
        );
    }
    public static function get_learningplan_info($id){
        global $DB;
        $params = self::validate_parameters(self::get_learningplan_info_parameters(),
            ['id' => $id]);
        return (new \local_learningplan\local\general_lib())->get_learningplan_info($id);
    }
    public static function get_learningplan_info_returns(){
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'The id of the module'),
            'name' => new external_value(PARAM_TEXT, 'name'),
            'shortname' => new external_value(PARAM_TEXT, 'shortname'),
            'summary' => new external_value(PARAM_RAW, 'summary', VALUE_OPTIONAL,
                ''),
            'category' => new external_value(PARAM_TEXT, 'category', VALUE_OPTIONAL,
                ''),
            'bannerimage' => new external_value(PARAM_RAW, 'bannerimage'),
            'points' => new external_value(PARAM_RAW, 'points', VALUE_OPTIONAL, 0),
            'isenrolled' => new external_value(PARAM_BOOL, 'isenrolled'),
            'totalcourses' => new external_value(PARAM_INT, 'totalcourses', VALUE_OPTIONAL, 0),
            'optional' => new external_value(PARAM_INT, 'optional', VALUE_OPTIONAL, 0),
            'mandatory' => new external_value(PARAM_INT, 'mandatory', VALUE_OPTIONAL, 0),
            'startdate' => new external_value(PARAM_INT, 'startdate', VALUE_OPTIONAL, ''),
            'enddate' => new external_value(PARAM_INT, 'enddate', VALUE_OPTIONAL, ''),
            'avgrating' => new external_value(PARAM_FLOAT, 'avgrating', VALUE_OPTIONAL, 0),
            'ratedusers' => new external_value(PARAM_INT, 'ratedusers', VALUE_OPTIONAL, 0),
            'certificateid' => new external_value(PARAM_INT, 'certificateid', VALUE_OPTIONAL, 0),
        ));
    }
}
