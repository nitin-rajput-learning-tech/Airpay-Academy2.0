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
    // public static function get_available_modules_parameters(){
    //     return new external_function_parameters([
    //         'contextid' => new external_value(PARAM_INT, 'The context id for the course', VALUE_OPTIONAL, SYSCONTEXTID),
    //         'filter' => new external_multiple_structure(
    //             new external_single_structure([
    //                 'name' => new external_value(PARAM_TEXT, 'The filter name'),
    //                 'value' => new external_value(PARAM_TEXT, 'The filter value')
    //             ])
    //         )
    //     ]);
    // }
    // public static function get_available_modules($contextid, $filters){
    //     $params = self::validate_parameters(self::get_available_modules_parameters(),
    //                                         ['contextid' => $contextid, 'filter' => $filter]);
    //     $context = context::instance_by_id($params['contextid'], MUST_EXIST);
    //     // We always must call validate_context in a webservice.
    //     self::validate_context($context);
    //     $selectedfilter = array_map(function(){}, $filters);
    //     foreach($filters AS $filter){
    //         $selectedfilter
    //     }

    // }
    // public static function get_available_modules_returns(){}


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
                'catcode' => new external_value(PARAM_TEXT, 'Category Code'),
                'tagcatname' => new external_value(PARAM_TEXT, 'Tag Category Name'),
                'itemslist' => new external_multiple_structure(
                    new external_single_structure([
                        'tagitemid' => new external_value(PARAM_TEXT, 'Tag Item Id'),
                        'tagitemname' => new external_value(PARAM_TEXT, 'Tag Item name'),
                        'tagitemname' => new external_value(PARAM_TEXT, 'Tag Item shortname'),
                        'coursecount' => new external_value(PARAM_INT, 'Count of modules')
                    ])
                )
            ])
        );
    }


    public static function enrol_user_to_module_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the course', VALUE_OPTIONAL, SYSCONTEXTID)
            )
        );
    }
    public static function enrol_user_to_module(){}
    public static function enrol_user_to_module_returns(){

    }
}