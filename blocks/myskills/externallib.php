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
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
* @author eabyas <info@eabyas.in>
* @package BizLMS
* @subpackage block_myskills
*/

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/externallib.php');

/**
 * Feedback external functions
 *
 * @package    local_onlinetests
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class block_myskills_external extends external_api {
   
        //////For displaying on index page//////////
      public static function manageblockskillsview_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of skills based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of skills
     */
    public static function manageblockskillsview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/blocks/myskills/classes/local/lib.php');

        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageblockskillsview_parameters(),
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
       
        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $result_skill = myskill_details($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        if($totalcount>0){
            $data = $result_skill['data'];
        }else{
            $data = array();  //No data available in table
        }
        
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }


    public static function  manageblockskillsview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    
                                    'skill_name'=>new external_value(PARAM_RAW, 'skill name', VALUE_OPTIONAL),
                                    'levelname'=>new external_value(PARAM_RAW, 'Level of your skill', VALUE_OPTIONAL),
                                    'course_name' => new external_value(PARAM_RAW, 'course name of your skill', VALUE_OPTIONAL),
                                    'points' => new external_value(PARAM_RAW, 'points you receieved', VALUE_OPTIONAL),
                                    'achieved_on' => new external_value(PARAM_RAW, 'date when you acheived the skill', VALUE_OPTIONAL),
                                    
                                    
                                )
                            )
                        )
        ]);
    }



}
