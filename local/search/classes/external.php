<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or localify
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
 * Courses external API
 *
 * @package    local_search
 * @category   external
 * @copyright  eAbyas <www.eabyas.in>
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/local/search/lib.php');
class local_search_external extends external_api {
    public static function get_available_modules_parameters(){
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'The page number for the modules'),
            'filters' => new external_multiple_structure(
                new external_single_structure([
                    "type" => new external_value(PARAM_TEXT, 'The context id for the course', 'Filter type', VALUE_OPTIONAL, ''),
                    "values" => new external_multiple_structure(
                         new external_value(PARAM_TEXT, 'The filter value'), 'Filter options', VALUE_OPTIONAL, ''
                    )
                ]) ,'Filters' ,VALUE_DEFAULT, []
            ),
            'contextid' => new external_value(PARAM_INT, 'The context id for the course', VALUE_DEFAULT, SYSCONTEXTID),
            'pagelimit' => new external_value(PARAM_INT, 'Page length for the modules', VALUE_DEFAULT, 15),
            'query' => new external_value(PARAM_RAW, 'Search criteria for the modules', VALUE_OPTIONAL)
        ]);
    }
    public static function get_available_modules($page, $filters = [], $contextid = SYSCONTEXTID, $pagelimit = 15, $query = ''){
        global $CFG, $DB;
        $params = self::validate_parameters(self::get_available_modules_parameters(),
                                            ['page' => $page, 'filters' => $filters, 'contextid' => $contextid, 'pagelimit' => $pagelimit, 'query' => $query]);
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        if($page>=1)
            $page = $page-1;
        \local_search\output\searchlib::$page = $page;
        \local_search\output\searchlib::$perpage = $pagelimit;
        \local_search\output\searchlib::$search = $query;
        if(file_exists($CFG->dirroot . '/local/includes.php')){
            require_once($CFG->dirroot . '/local/includes.php');
            $includes = new user_course_details();
            \local_search\output\searchlib::$includesobj = $includes;
        }
        \local_search\output\searchlib::$skills = $DB->get_records_menu('local_skill', null, '', 'id, name');
        \local_search\output\searchlib::$levels = $DB->get_records_menu('local_course_levels', null, '', 'id, name');

        $pages = new \local_search\output\allcourses();
        $data = $pages->main_toget_catalogtypes($pagelimit, $filters);
        $totalrecords = $data['numberofrecords'];
        unset($data['numberofrecords']);
        return ['modules' => $data, 'count' => $totalrecords];
    }
    public static function get_available_modules_returns(){
        return new external_single_structure([
            "count" => new external_value(PARAM_INT, 'Count of modules'),
            "modules" => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Module ID'),
                    'module' => new external_value(PARAM_TEXT, 'module'),
                    'fullname' => new external_value(PARAM_TEXT, 'Category Code'),
                    'shortname' => new external_value(PARAM_TEXT, 'Tag Category Name'),
                    'bannerimage' => new external_value(PARAM_RAW, 'Banner image'),
                    'summary' => new external_value(PARAM_RAW, 'Summary Information'),
                    'startdate' => new external_value(PARAM_INT, 'Startdate Information'),
                    'enddate' => new external_value(PARAM_INT, 'Endddate Information'),
                    'avgrating' => new external_value(PARAM_FLOAT, 'Average Ratings', VALUE_OPTIONAL),
                    'ratedusers' => new external_value(PARAM_INT, 'Rated Users', VALUE_OPTIONAL),
                    'likes' => new external_value(PARAM_INT, 'liked Users', VALUE_OPTIONAL, 0),
                    'dislikes' => new external_value(PARAM_INT, 'Disliked Users', VALUE_OPTIONAL, 0),
                    'isenrolled' => new external_value(PARAM_BOOL, 'User enrollment to module', VALUE_OPTIONAL, FALSE),
                    'requeststatus' => new external_value(PARAM_INT, 'User request status to module', VALUE_OPTIONAL, 0),
                    'enrolmethods' => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Module custom enrollment method', VALUE_OPTIONAL), //Self, Request
                            'Enrollment methods info', VALUE_OPTIONAL
                    ),
                    'skill' => new external_value(PARAM_TEXT, 'SKill Information', VALUE_OPTIONAL, ''),
                    'level' => new external_value(PARAM_TEXT, 'level Information', VALUE_OPTIONAL, ''),
                    'canenrolrequest' => new external_value(PARAM_BOOL, 'Can enrol Flag', VALUE_DEFAULT, true),
                    'enrolment_status_message' => new external_value(PARAM_INT, 'Status message for enrollment', VALUE_OPTIONAL, 0),
                ])
            )
        ]);
    }


    public static function get_filter_elements_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the course', VALUE_OPTIONAL, SYSCONTEXTID)
            )
        );
    }
    public static function get_filter_elements($contextid){
        $params = self::validate_parameters(self::get_filter_elements_parameters(),
                                            ['contextid' => $contextid]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $filters = local_search_get_filters();
        // print_object($filters);
        // foreach($filters AS $filter){

        // }
        return $filters;
    }
    public static function get_filter_elements_returns(){
        return new external_multiple_structure(
            new external_single_structure([
                'type' => new external_value(PARAM_TEXT, 'Category Code'),
                'name' => new external_value(PARAM_TEXT, 'Tag Category Name'),
                'options' => new external_multiple_structure(
                    new external_single_structure([
                        'code' => new external_value(PARAM_TEXT, 'Tag Item Id'),
                        'name' => new external_value(PARAM_TEXT, 'Tag Item name'),
                        'count' => new external_value(PARAM_INT, 'Count of modules')
                    ])
                )
            ])
        );
    }


    public static function enrol_user_to_module_parameters(){
        return new external_function_parameters(
            array(
                'moduleid' => new external_value(PARAM_INT, 'The id of the module'),
                'type' => new external_value(PARAM_TEXT, 'Type of module'),
                'enrolmethod' => new external_value(PARAM_TEXT, 'Enrollment method of the module'),
                'contextid' => new external_value(PARAM_INT, 'The context id for the course', VALUE_DEFAULT, SYSCONTEXTID)
            )
        );
    }
    public static function enrol_user_to_module($moduleid, $type, $enrolmethod, $contextid = SYSCONTEXTID){
        $params = self::validate_parameters(self::enrol_user_to_module_parameters(),
                                            ['moduleid' => $moduleid, 'type' => $type, 'enrolmethod' => $enrolmethod,'contextid' => $contextid]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        if($type == 'local_learningpath'){
            $type = 'local_learningplan';
        }
        $classname = '\\'.$type.'\output\search';
        if(class_exists($classname)){
            $class = new $classname();
            if(method_exists($class, 'enrol_user_to_component')){
                $class->enrol_user_to_component($enrolmethod, $moduleid);
            }else{
                throw new Exception("Enrollment not found");
            }
        }else{
            throw new Exception("Type not found");
        }
        return ['status' => true];
    }
    public static function enrol_user_to_module_returns(){
        return new external_single_structure([
            "status" => new external_value(PARAM_BOOL, 'status of the request'),
        ]);
    }

    public static function get_module_info_parameters(){
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'The id of the module'),
                'type' => new external_value(PARAM_TEXT, 'The type of the module'),
            )
        );
    }
    public static function get_module_info($id,$type){
        global $DB;
        $params = self::validate_parameters(self::get_module_info_parameters(),
            ['id' => $id, 'type' => $type]);

        switch($type) {
            case 'local_courses':
            return (new \local_courses\local\general_lib())->get_course_info($id);
            break;

            case 'local_classroom':
            return (new \local_classroom\local\general_lib())->get_classroom_info($id);
            break;

            case 'local_learningpath':
            return (new \local_learningplan\local\general_lib())->get_learningplan_info($id);
            break;

            case 'local_program':
            return (new \local_program\local\general_lib())->get_program_info($id);
            break;

            default:
                throw new \Exception('Unknown Module');
            break;
        }

    }
    public static function get_module_info_returns(){
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'The id of the module'),
            'fullname' => new external_value(PARAM_TEXT, 'fullname'),
            'shortname' => new external_value(PARAM_TEXT, 'shortname'),
            'category' => new external_value(PARAM_TEXT, 'category', VALUE_OPTIONAL, ''),
            'module' => new external_value(PARAM_TEXT, 'module'),
            'bannerimage' => new external_value(PARAM_RAW, 'bannerimage'),
            'points' => new external_value(PARAM_RAW, 'points', VALUE_OPTIONAL, 0),
            'isenrolled' => new external_value(PARAM_BOOL, 'isenrolled'),
            'requeststatus' => new external_value(PARAM_INT, 'User request status to module', VALUE_OPTIONAL, ''),
            'enrolment_status_message' => new external_value(PARAM_INT, 'Status message for enrollment', VALUE_OPTIONAL, ''),
            'totalcourses' => new external_value(PARAM_INT, 'totalcourses', VALUE_OPTIONAL, 0),
            'optional' => new external_value(PARAM_INT, 'optional', VALUE_OPTIONAL, 0),
            'mandatory' => new external_value(PARAM_INT, 'mandatory', VALUE_OPTIONAL, 0),
            'startdate' => new external_value(PARAM_INT, 'startdate', VALUE_OPTIONAL, ''),
            'enddate' => new external_value(PARAM_INT, 'enddate', VALUE_OPTIONAL, ''),
            'summary' => new external_value(PARAM_RAW, 'summary', VALUE_OPTIONAL, ''),
            'avgrating' => new external_value(PARAM_FLOAT, 'avgrating', VALUE_OPTIONAL, 0),
            'ratedusers' => new external_value(PARAM_INT, 'ratedusers', VALUE_OPTIONAL, 0),
            'skill' => new external_value(PARAM_TEXT, 'skill', VALUE_OPTIONAL, ''),
            'level' => new external_value(PARAM_TEXT, 'level', VALUE_OPTIONAL, ''),
        ));
    }
}
