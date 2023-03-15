<?php
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/lib.php");
use block_trending_modules\querylib;
class block_trending_modules_external  extends external_api{
	public static function display_module_content_parameters(){
		return new external_function_parameters(
            array(
                'indexid' => new external_value(PARAM_INT, 'The start index value', 0),
                'limitnum' => new external_value(PARAM_INT, 'The limit value', VALUE_DEFAULT, 3),
                'contextid' => new external_value(PARAM_INT, 'The context id for the module', false),
                'search' => new external_value(PARAM_RAW, 'Search Content', ''),
                'jsondata' => new external_value(PARAM_RAW, 'JSON of filter modules to be searched', VALUE_OPTIONAL, NULL)
            )
        );
	}
	public static function display_module_content($indexid, $limitnum = 3, $contextid, $search, $jsondata = NULL){
		global $PAGE;
		$params = self::validate_parameters(
            self::display_module_content_parameters(),
            [
                'indexid' => $indexid,
                'limitnum' => $limitnum,
                'contextid' => $contextid,
                'search' => $search,
                'jsondata' => $jsondata
            ]
        );
        $context = \context_system::instance();
        $PAGE->set_context($context);
		$lib = new block_trending_modules\lib();
    	$args = new stdClass();
		$args->search = $search;
        $filtervalues = new stdClass();
        $data = json_decode($jsondata);
        $filtervalues->module_tags = $data->module_tags;
        $args->filtervalues = $filtervalues;
        $totalcount = $lib->get_total_modules_count($args);
    	$args->limitfrom = $indexid;
		$args->limitnum = $limitnum;
        $args->rateWidth = 12;
    	$data = $lib->user_trending_modules($args);
		return $records = array('records' => $data, 'enableDesc' => False, 'totalcount' => $totalcount);
	}
	public static function display_module_content_returns(){
		return new external_single_structure([
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'background_logourl' => new external_value(PARAM_RAW, 'Background logo url', VALUE_OPTIONAL),
                        'description_title' => new external_value(PARAM_RAW, 'Title for description', VALUE_OPTIONAL),

                        'description' => new external_value(PARAM_RAW, 'Description of the Module', VALUE_OPTIONAL),
                        'modulename' => new external_value(PARAM_RAW, 'Module Name', VALUE_OPTIONAL),
                        'modulename_title' => new external_value(PARAM_RAW, 'Title for Module Name', VALUE_OPTIONAL),
                        'ratings_content' => new external_value(PARAM_RAW, 'html for the rating display', VALUE_OPTIONAL),
                        'suggestions_btn' => new external_value(PARAM_RAW, 'html for the suggestions', VALUE_OPTIONAL),
                        'moduleidentifier' => new external_value(PARAM_RAW, 'identifer for the info popup', VALUE_OPTIONAL),
                        'selector' => new external_value(PARAM_RAW, 'selector for the info popup', VALUE_OPTIONAL),
                        'moduleid' => new external_value(PARAM_INT, 'id of the module', VALUE_OPTIONAL),
                    )
                )
            ),
            'enableDesc' => new external_value(PARAM_BOOL, 'Flag variable to display description'),
            'totalcount' => new external_value(PARAM_INT, 'Value of total modules')
        ]);
	}
	public static function display_module_paginated_parameters(){
		return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid',
                    VALUE_DEFAULT, 0),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
	}
	public static function display_module_paginated($options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata){
		global $PAGE, $DB;
		$params = self::validate_parameters(
            self::display_module_paginated_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);
        $context = \context_system::instance();
        $PAGE->set_context($context);
        $configdata = $DB->get_field('block_instances', 'configdata', ['id' => $decodedata->instanceid]);
		$lib = new block_trending_modules\lib();
    	$args = new stdClass();
        $args->config = unserialize(base64_decode($configdata));
    	$args->limitfrom = $params['offset'];
		$args->limitnum = $params['limit'];
        $args->rateWidth = 14;
		$args->search = $filtervalues->search_query;
        $args->filtervalues = $filtervalues;
    	$data = $lib->user_trending_modules($args);
    	$totalcount = $lib->get_total_modules_count($args);
    	return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'enableDesc' => True
        ];
	}
	public static function display_module_paginated_returns(){
		return new external_single_structure([
			'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of modules in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'background_logourl' => new external_value(PARAM_RAW, 'Background logo url', VALUE_OPTIONAL),
                        'description_title' => new external_value(PARAM_RAW, 'Title for description', VALUE_OPTIONAL),

                        'description' => new external_value(PARAM_RAW, 'Description of the Module', VALUE_OPTIONAL),
                        'modulename' => new external_value(PARAM_RAW, 'Module Name', VALUE_OPTIONAL),
                        'modulename_title' => new external_value(PARAM_RAW, 'Title for Module Name', VALUE_OPTIONAL),
                        'functionname' => new external_value(PARAM_TEXT, 'function to be triggered on click', VALUE_OPTIONAL),
                        'selector' => new external_value(PARAM_TEXT, 'selector type of the module', VALUE_OPTIONAL),
                        'moduleidentifier' => new external_value(PARAM_TEXT, 'identifier name of the module', VALUE_OPTIONAL),
                        'moduleid' => new external_value(PARAM_TEXT, 'id of the module', VALUE_OPTIONAL),
                        'ratings_content' => new external_value(PARAM_RAW, 'html for the rating display', VALUE_OPTIONAL),
                        'suggestions_btn' => new external_value(PARAM_RAW, 'html for the suggestions', VALUE_OPTIONAL),
                        'modulelink' => new external_value(PARAM_RAW, 'html for the suggestions', VALUE_OPTIONAL)                    
                        )
                )
            ),
            'enableDesc' => new external_value(PARAM_BOOL, 'Flag variable to display description')
        ]);
	}
    public static function alter_popup_status_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id', false),
                'status' => new external_value(PARAM_BOOL, 'Status of the popupshown')
            )
        );
    }
    public static function alter_popup_status($contextid, $status){
        global $PAGE;
        $params = self::validate_parameters(
            self::alter_popup_status_parameters(),
            [
                'contextid' => $contextid,
                'status' => $status
            ]
        );
        $value = $status ? 0 : 1;
        return set_user_preference('force_dontshow_trending_modules', $value);
    }
    public static function alter_popup_status_returns(){
        return new external_value(PARAM_BOOL, 'return');
    }
    public static function get_trending_modules_parameters(){
        return new external_function_parameters(
            array(
                'componentname' => new external_value(PARAM_TEXT, 'component name'),
                'search' => new external_value(PARAM_RAW, 'search', VALUE_OPTIONAL, ''),
                'page' => new external_value(PARAM_INT, 'page', VALUE_OPTIONAL, 0),
                'perpage' => new external_value(PARAM_INT, 'perpage', VALUE_OPTIONAL, 10)
            )
        );
    }
    public static function get_trending_modules($componentname, $search = '', $page=0, $perpage=10){
        global $DB, $USER, $PAGE;
        $params = self::validate_parameters(
            self::get_trending_modules_parameters(),
            [
                'componentname' => $componentname,
                'search' => $search,
                'page' => $page,
                'perpage' => $perpage
            ]
        );
        // var_dump($search);
        $trending_data = (new querylib)->get_trending_modules_query($search, false, $componentname);
        $sql = $trending_data['sql'];
        $ordersql = $trending_data['ordersql'];
        $params = $trending_data['params'];
        $start = $page * $perpage;
        $records = $DB->get_records_sql($sql.$ordersql, $params, $start, $perpage);

        $totaltrending_data = (new querylib)->get_trending_modules_query($search, true, $componentname);
        $totalsql = $totaltrending_data['sql'];
        $totalordersql = $totaltrending_data['ordersql'];
        $totalparams = $totaltrending_data['params'];

        $total = $DB->count_records_sql($totalsql, $totalparams);

        $trendingmodules = array();
        foreach ($records as $record) {
            if ($record->module_type == 'local_courses') {
                $coursecontext = context_course::instance($record->module_id);
                $enrol = new stdClass();
                $enrol->enrolled = is_enrolled($coursecontext, $USER->id);
                $course = $DB->get_record('course', array('id' => $record->module_id,'open_coursetype'=>0));
                $record->enrolstatus = (new \local_courses\courses)->enrol_status($enrol, $course);
            } else if ($record->module_type == 'local_classroom') {
                $fromsql = "SELECT c.*, (SELECT COUNT(DISTINCT cu.userid)
                                          FROM {local_classroom_users} AS cu
                                          WHERE cu.classroomid = c.id
                                      ) AS enrolled_users FROM {local_classroom} AS c
                            WHERE c.id = $record->module_id";
                $classroom = $DB->get_record_sql($fromsql);

                $enrol = new stdClass();

                $record->enrolstatus = (new \local_classroom\classroom)->enrol_status($enrol, $classroom);
            } else if ($record->module_type == 'local_learningplan') {
                $enrol = new stdClass();
                $learningplan = $DB->get_record('local_learningplan', array('id' => $record->module_id));

                $record->enrolstatus = (new \local_learningplan\learningplan)->enrol_status($enrol, $learningplan);
            }
            $trendingmodules[] = $record;
        }
        return array('trendingmodules' => $trendingmodules, 'total' => $total);
    }
    public static function get_trending_modules_returns(){
        return new external_single_structure(
            array(
                'trendingmodules' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' =>  new external_value(PARAM_INT, 'id'),
                            'module_id' =>  new external_value(PARAM_INT, 'Module id'),
                            'module_type' =>  new external_value(PARAM_TEXT, 'Module Type'),
                            'module_name' =>  new external_value(PARAM_TEXT, 'Module Name'),
                            'module_description' =>  new external_value(PARAM_RAW, 'Module Description'),
                            'module_startdate' =>  new external_value(PARAM_RAW, 'Module StartDate'),
                            'module_enddate' =>  new external_value(PARAM_RAW, 'Module EndDate'),
                            'module_visible' =>  new external_value(PARAM_INT, 'Module Visibility'),
                            'module_rating' =>  new external_value(PARAM_INT, 'Module Rating'),
                            'enrolstatus' =>  new external_value(PARAM_INT, 'ENROL STATUS')
                        )
                    )
                ),
                'total' => new external_value(PARAM_INT, 'Total Pages')
            )
        );
    }

}
