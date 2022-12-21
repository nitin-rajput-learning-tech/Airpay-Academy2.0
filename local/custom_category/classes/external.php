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
 * @subpackage local_custom_category
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
class local_custom_category_external extends external_api {

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_custom_category_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
            )
        );
    }

    public function submit_custom_category_form($contextid, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/custom_category/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_custom_category_form_parameters(), ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);


        $context =(new \local_custom_category\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $data = array();
        parse_str($params['jsonformdata'], $data);
        $warnings = array();

        $mform = new local_custom_category\form\custom_category_form(null, array(), 'post', '', null, true, $data);

        $repositoryinsert  = new local_custom_category\lib();
        $valdata = $mform->get_data();
        // print_r($data);die;
        if($valdata){
            $repositoryinsert->custom_category_opertaions($valdata);
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
    }


    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_custom_category_form_returns() {
        return new external_value(PARAM_INT, 'repository id');
    }

    public static function managecustom_category_parameters() {
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results to return', VALUE_DEFAULT, 0),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function managecustom_category(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/custom_category/lib.php');
        require_login();
        $PAGE->set_url('/local/custom_category/index.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::managecustom_category_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );

        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);
        $stable = new \stdClass();
        $stable->thead = true;
        $stable->start = $offset;
        $stable->length = $limit;
        $result_custom_category = custom_category_details($stable,$filtervalues);
        $totalcount = $result_custom_category['count'];
        $data=$result_custom_category['data'];
        return [
            'is_admin' => is_siteadmin(),
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  managecustom_category_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of custom_category in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'is_admin' => new external_value(PARAM_BOOL, 'Is user an admin flag'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'visible' => new external_value(PARAM_INT, 'visible skill', VALUE_OPTIONAL),
                        'custom_category_id' => new external_value(PARAM_RAW, 'id in custom_category', VALUE_OPTIONAL),
                        'organisationname' => new external_value(PARAM_RAW, 'organisationname of custom_category', VALUE_OPTIONAL),
                        'custom_category_name' => new external_value(PARAM_RAW, 'custom_category', VALUE_OPTIONAL),
                        'shortname' => new external_value(PARAM_RAW, 'shortname of custom_category', VALUE_OPTIONAL),
                        'parent' => new external_value(PARAM_RAW, 'category name in custom_category', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }
}