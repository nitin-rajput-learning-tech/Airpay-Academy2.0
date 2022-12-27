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
class local_search_external extends external_api {
    public static function get_available_modules_parameters(){}
    public static function get_available_modules(){}
    public static function get_available_modules_returns(){}


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
    }
    public static function get_filter_elements_returns(){}


    public static function enrol_user_to_module_parameters(){}
    public static function enrol_user_to_module(){}
    public static function enrol_user_to_module_returns(){}
}