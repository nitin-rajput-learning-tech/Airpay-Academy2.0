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
 * This is the external API for this tool.
 *
 * @package    block_userdashboard
 * @copyright  2018 hemalataha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_userdashboard;
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");


use context;
use context_system;
use context_course;
use context_helper;
use context_user;
use coding_exception;
use external_api;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use external_multiple_structure;
use invalid_parameter_exception;
use required_capability_exception;

use core_cohort\external\cohort_summary_exporter;


/**
 * This is the external API for this tool.
 *
 * @copyright  2018 Hemalatha c arun
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Returns a prepared structure to use a context parameters.
     * @return external_single_structure
     */
    protected static function get_context_parameters() {
        $id = new external_value(
            PARAM_INT,
            'Context ID. Either use this value, or level and instanceid.',
            VALUE_DEFAULT,
            0
        );
        $level = new external_value(
            PARAM_ALPHA,
            'Context level. To be used with instanceid.',
            VALUE_DEFAULT,
            ''
        );
        $instanceid = new external_value(
            PARAM_INT,
            'Context instance ID. To be used with level',
            VALUE_DEFAULT,
            0
        );
        return new external_single_structure(array(
            'contextid' => $id,
            'contextlevel' => $level,
            'instanceid' => $instanceid,
        ));
    }    

    
    /**
     * Returns the description of the 
     data_for_elearning_courses_parameters.
     *
     * @return external_function_parameters.
     */
    public static function data_for_elearning_courses_parameters() {
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


    /**
     * Data to render in the related elearning_courses section.
     *
     * @param int $filter
     * @return array elearning courses list.
     */
    public static function data_for_elearning_courses($filter, $filter_text='', $filter_offset = 0, $filter_limit = 0) {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_elearning_courses_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
            'filter_offset' => $filter_offset,
            'filter_limit' => $filter_limit
        ));
      /*  $competency = api::read_competency($params['competencyid']);
        self::validate_context($competency->get_context()); */
        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\elearning_courses($params['filter'], $params['filter_text'], $params['filter_offset'], $params['filter_limit']);
        $output = $PAGE->get_renderer('block_userdashboard');
       // echo $renderable->export_for_template($renderer);

        $data= $renderable->export_for_template($output);
       
           
     // print_object($data);
        return $data;
    }

    /**
     * Returns description of data_for_elearning_courses_returns() result value.
     *
     * @return external_description
     */
   public static function data_for_elearning_courses_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'elearningtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
                'inprogress_elearning' => new external_value(PARAM_RAW, 'Function name'),
                 'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
                'index' => new external_value(PARAM_INT, 'number of courses count'),
                'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
                'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
        ));

    }  // end of the function data_for_elearning_courses_returns



    /**
     * Returns the description of the 
     data_for_elearning_courses_parameters.
     *
     * @return external_function_parameters.
     */
    public static function data_for_classroom_courses_parameters() {
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        );
        return new external_function_parameters($params);
    }


    /**
     * Data to render in the related elearning_courses section.
     *
     * @param int $filter
     * @return array elearning courses list.
     */
    public static function data_for_classroom_courses($filter, $filter_text='') {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_classroom_courses_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        ));
      /*  $competency = api::read_competency($params['competencyid']);
        self::validate_context($competency->get_context()); */
        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\classroom_courses($params['filter'],$params['filter_text']);
        $output = $PAGE->get_renderer('block_userdashboard');
       // echo $renderable->export_for_template($renderer);

        $data= $renderable->export_for_template($output);
       
           
     // print_object($data);
        return $data;
    }

    /**
     * Returns description of data_for_elearning_courses_returns() result value.
     *
     * @return external_description
     */
   public static function data_for_classroom_courses_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
                'classroomtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                'inprogress_elearning' => new external_value(PARAM_RAW, 'Function name'),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
               'index' => new external_value(PARAM_INT, 'number of courses count'),
               'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
               'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
               'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),

        ));

    }  // end of the function data_for_elearning_courses_returns

/**
 * [data_for_program_courses_parameters description]
 * @return parameters for data_for_program_courses
 */
    public static function data_for_program_courses_parameters() {
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


    public static function data_for_program_courses($filter, $filter_text='') {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_program_courses_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        ));

        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\program_courses($params['filter'],$params['filter_text']);
        $output = $PAGE->get_renderer('block_userdashboard');

        $data= $renderable->export_for_template($output);

        return $data;
    }


    public static function data_for_program_courses_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'programtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                'inprogress_elearning' => new external_value(PARAM_RAW, 'Function name'),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
               'index' => new external_value(PARAM_INT, 'number of courses count'),
               'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
               'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
               'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),

        ));

    }  // end of the function data_for_program_courses_returns
    
    /**
 * [data_for_program_courses_parameters description]
 * @return parameters for data_for_program_courses
 */
    public static function data_for_certification_courses_parameters() {
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        );
        return new external_function_parameters($params);
    }


    public static function data_for_certification_courses($filter, $filter_text='') {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_program_courses_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        ));

        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\certification_courses($params['filter'],$params['filter_text']);
        $output = $PAGE->get_renderer('block_userdashboard');

        $data= $renderable->export_for_template($output);

        return $data;
    }


    public static function data_for_certification_courses_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'certificationtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                'inprogress_elearning' => new external_value(PARAM_RAW, 'Function name'),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
               'index' => new external_value(PARAM_INT, 'number of courses count'),
               'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
               'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
               'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),

        ));

    }  // end of the function data_for_program_courses_returns


/**
 * [data_for_program_courses_parameters description]
 * @return parameters for data_for_program_courses
 */
    public static function data_for_xseed_parameters() {
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        );
        return new external_function_parameters($params);
    }


    public static function data_for_xseed($filter, $filter_text='') {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_xseed_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        ));

        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\xseed($params['filter'],$params['filter_text']);
        $output = $PAGE->get_renderer('block_userdashboard');

        $data= $renderable->export_for_template($output);
      
        return $data;
    }


    public static function data_for_xseed_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'xseedtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
                'inprogress_elearning' => new external_value(PARAM_RAW, 'Function name'),
                // 'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
               'index' => new external_value(PARAM_INT, 'number of courses count'),
               'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
               'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
               'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),

        ));

    }  // end of the function data_for_program_courses_returns


    /**
 * [data_for_learningplan_courses_parameters description]
 * @return parameters for data_for_program_courses
 */
    public static function data_for_learningplan_courses_parameters() {
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        );
        return new external_function_parameters($params);
    }


    public static function data_for_learningplan_courses($filter, $filter_text='') {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_learningplan_courses_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        ));

        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\learningplan_courses($params['filter'],$params['filter_text']);
        $output = $PAGE->get_renderer('block_userdashboard');

        $data= $renderable->export_for_template($output);

        return $data;
    }


    public static function data_for_learningplan_courses_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'),
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'learningplantemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
                'inprogress_elearning' => new external_value(PARAM_RAW, 'Function name'),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
               'index' => new external_value(PARAM_INT, 'number of courses count'),
               'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
               'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
               'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),

         
        ));

    }  // end of the function data_for_program_courses_returns

       /**
 * [data_for_onlinetests_courses_parameters description]
 * @return parameters for data_for_program_courses
 */
    public static function data_for_evaluation_courses_parameters() {
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        );
        return new external_function_parameters($params);
    } // end of data_for_evaluation_courses_parameters.


    public static function data_for_evaluation_courses($filter, $filter_text='') {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_evaluation_courses_parameters(), array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        ));

        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\evaluation_courses($params['filter'],$params['filter_text']);
        $output = $PAGE->get_renderer('block_userdashboard');

        $data= $renderable->export_for_template($output);

        return $data;
    } //end of data_for_evaluation_courses.


    public static function data_for_evaluation_courses_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'),
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'), 
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'evaluationtemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
               'inprogress_elearning' => new external_value(PARAM_RAW , 'Function name'),
               //'sub_tab' => new external_value(PARAM_INT, 'inprogress = 0 & completed = 1'),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               //'table_class' => new external_value(PARAM_TEXT, 'class for the table'),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
               'index' => new external_value(PARAM_INT, 'number of courses count'),
               'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
               'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
               'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
        ));

    }  // end of the function data_for_evaluation_courses_returns

           /**
 * [data_for_onlinetests_courses_parameters description]
 * @return parameters for data_for_program_courses
 */
    public static function data_for_onlinetests_courses_parameters() {
        $filter = new external_value(PARAM_TEXT, 'Filter text');
        $filter_text = new external_value(PARAM_TEXT, 'Filter name',VALUE_OPTIONAL);
        $params = array(
            'filter' => $filter,
            'filter_text' => $filter_text,
        );
        return new external_function_parameters($params);
    } // end of data_for_onlinetests_courses_parameters.


    public static function data_for_onlinetests_courses($filter, $filter_text='') {
        global $PAGE;

        $params = self::validate_parameters(self::data_for_onlinetests_courses_parameters(), array(
                'filter' => $filter,
                'filter_text' => $filter_text,
            )
        );

        $PAGE->set_context((new \local_costcenter\lib\accesslib())::get_module_context());
        $renderable = new output\onlinetests_courses($params['filter'],$params['filter_text']);
        $output = $PAGE->get_renderer('block_userdashboard');

        $data= $renderable->export_for_template($output);

        return $data;
    } //end of data_for_onlinetests_courses.


    public static function data_for_onlinetests_courses_returns() {
     

        return new external_single_structure(array (
    
            'total' => new external_value(PARAM_INT, 'Number of enrolled courses.', VALUE_OPTIONAL),           
            'inprogresscount'=>  new external_value(PARAM_INT, 'Number of inprogress course count.'),  
            'completedcount'=>  new external_value(PARAM_INT, 'Number of complete course count.'), 
            'courses_view_count'=>  new external_value(PARAM_INT, 'Number of courses count.'), 
            'enableslider'=>  new external_value(PARAM_INT, 'Flag for enable the slider.'), 
            'inprogress_elearning_available'=>  new external_value(PARAM_INT, 'Flag to check enrolled course available or not.'),
            'course_count_view'=>  new external_value(PARAM_TEXT, 'to add course count class'),
               'functionname' => new external_value(PARAM_TEXT, 'Function name'),
               'subtab' => new external_value(PARAM_TEXT, 'Sub tab name'),
               'onlineteststemplate' => new external_value(PARAM_INT, 'template name',VALUE_OPTIONAL),
               'inprogress_elearning' => new external_value(PARAM_RAW , 'Function name'),
               //'sub_tab' => new external_value(PARAM_INT, 'inprogress = 0 & completed = 1'),
                'menu_heading' => new external_value(PARAM_TEXT, 'heading string of the dashboard'),
               'nodata_string' => new external_value(PARAM_TEXT, 'no data message'),
               'index' => new external_value(PARAM_INT, 'number of courses count'),
               'filter' => new external_value(PARAM_TEXT, 'filter for display data'),
               'filter_text' => new external_value(PARAM_TEXT, 'filtertext content',VALUE_OPTIONAL),
               'view_more_url' => new external_value(PARAM_URL, 'view_more_url for tab'),
               'certificate_exists' => new external_value(PARAM_RAW ,'certificate_exists',VALUE_OPTIONAL),
               'certificate_download' => new external_value(PARAM_RAW ,'certificate_download',VALUE_OPTIONAL),
               'certificateid' => new external_value (PARAM_RAW, 'certificateid',VALUE_OPTIONAL)
               //'table_class' => new external_value(PARAM_TEXT, 'class for the table'),


        ));

    }  // end of the function data_for_onlinetests_courses_returns
    public static function data_for_allcounts_parameters() {
        return new external_function_parameters(
             array('userid' => new external_value(PARAM_INT, 'UserID')
                )
        );
    }
    public static function data_for_allcounts($userid) {
      global $USER, $DB;
      $counts = array();
      $data = array();
        $completedcourses = lib\elearning_courses::completed_coursenames('', true);
        $inprogresscourses = lib\elearning_courses::inprogress_coursenames('', true, $status);
        $enrolledcourses = lib\elearning_courses::inprogress_coursenames('', true, 'enrolled');
        $inprogress_certificates          = lib\certification::inprogress_certification('');
        $completed_certificates           = lib\certification::completed_certification('');
        $enrolled_certificates            = lib\certification::gettotal_certification('');
        $inprogress_classrooms            = lib\classrooms::inprogress_classrooms('');
        $completed_classrooms             = lib\classrooms::completed_classrooms('');
        $enrolled_classrooms              = lib\classrooms::gettotal_classrooms('');
        $inprogress_programs              = lib\programs::inprogress_programs('');
        $completed_programs               = lib\programs::completed_programs('');
        $inprogress_exams                 = lib\onlinetests::inprogress_onlinetests('');
        $completed_exams                  = lib\onlinetests::completed_onlinetests('');
        $counts['completedcourses']       = count($completedcourses);
        $counts['inprogresscourses']      = count($inprogresscourses);
        $counts['enrolledcourses']        = count($enrolledcourses);
        $counts['inprogresscertificates'] = count($inprogress_certificates);
        $counts['completedcertificates']  = count($completed_certificates);
        $counts['enrolledcertificates']   = $enrolled_certificates;
        $counts['inprogressclassrooms']   = count($inprogress_classrooms);
        $counts['completedclassrooms']    = count($completed_classrooms);
        $counts['enrolledclassrooms']     = $enrolled_classrooms;
        $counts['inprogressprograms']     = count($inprogress_programs);
        $counts['completedprograms']      = count($completed_programs);
        $counts['enrolledprograms']       = count($inprogress_programs)+count($completed_programs);
        $counts['inprogressexams']     = count($inprogress_exams);
        $counts['completedexams']      = count($completed_exams);
        $counts['enrolledexams']       = count($inprogress_exams) + count($completed_exams);
        return array('counts' => $counts);
    }
    public static function data_for_allcounts_returns() {
         return new external_single_structure(
            array(
                'counts' => new external_single_structure(
                    array(
                        'completedcourses'=> new external_value(PARAM_INT, 'Count of completed courses'),
                        'inprogresscourses'=> new external_value(PARAM_RAW, 'Count of inprogress courses'),
                        'enrolledcourses' => new external_value(PARAM_RAW, 'Count of enrolled courses'),
                        'inprogresscertificates' => new external_value(PARAM_RAW, 'Count of inprogress certificates'),
                        'completedcertificates' => new external_value(PARAM_RAW, 'Count of completed certificates'),
                        'enrolledcertificates' => new external_value(PARAM_RAW, 'Count of enrolled certificates'),
                        'inprogressclassrooms' => new external_value(PARAM_RAW, 'Count of inprogress classrooms'),
                        'completedclassrooms' => new external_value(PARAM_RAW, 'Count of completed classrooms'),
                        'enrolledclassrooms' => new external_value(PARAM_RAW, 'Count of enrolled classrooms'),
                        'inprogressprograms' => new external_value(PARAM_RAW, 'Count of inprogress programs'),
                        'completedprograms' => new external_value(PARAM_RAW, 'Count of completed programs'),
                        'enrolledprograms' => new external_value(PARAM_RAW, 'Count of enrolled programs'),
                        'inprogressexams' => new external_value(PARAM_RAW, 'Count of inprogress exams'),
                        'completedexams' => new external_value(PARAM_RAW, 'Count of completed exams'),
                        'enrolledexams' => new external_value(PARAM_RAW, 'Count of enrolled exams'),
                    )
                )
            )
        );
    }

} // end of class
