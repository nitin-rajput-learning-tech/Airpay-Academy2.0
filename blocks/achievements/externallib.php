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
 * Feedback external API
 *
 * @package    local_onlinetests
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
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
class block_achievements_external extends external_api {
   
    //////For displaying on index page//////////
    public static function manageachievementblockviewpoints_parameters() {
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
     * Gets the list of skills based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of skills
     */
    public static function manageachievementblockviewpoints(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/blocks/achievements/classes/local/lib.php');

        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageachievementblockviewpoints_parameters(),
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
        $result_achievement = point_details($stable,$filtervalues);
        $totalcount = $result_achievement['count'];
        if($totalcount>0){
            $data=$result_achievement['data'];
        }else{
            $data=array();  //No data available in table
        }
        
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    public static function  manageachievementblockviewpoints_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'points_title'=>new external_value(PARAM_RAW, 'skill name', VALUE_OPTIONAL),
                        'points_credit'=>new external_value(PARAM_RAW, 'Level of your skill', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }

    //////////////////////////////////
    public static function manageachievementblockviewbadges_parameters() {
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
    public static function manageachievementblockviewbadges(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/blocks/achievements/classes/local/lib.php');
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageachievementblockviewbadges_parameters(),
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
        $result_badges = badges_details($stable,$filtervalues);
        $data=$result_badges['data'];
        $totalcount = $result_badges['count'];
        if($totalcount>0){
            $data=$result_badges['data'];
        }else{
            $data=array();  //No data available in table
        }
        
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];
    }


    public static function  manageachievementblockviewbadges_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'imageurl' => new external_value(PARAM_RAW, 'badge url', VALUE_OPTIONAL),
                        'badge_name'=>new external_value(PARAM_RAW, 'badge name', VALUE_OPTIONAL),
                        'badge_name_str'=>new external_value(PARAM_RAW, 'badge name str', VALUE_OPTIONAL),
                        'uniquehash'=>new external_value(PARAM_RAW, 'more', VALUE_OPTIONAL),
                        'issued_on'=>new external_value(PARAM_RAW, 'more', VALUE_OPTIONAL),
                        'issued_by'=>new external_value(PARAM_RAW, 'more', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }

    //////////////////////certifications////////////
    public static function manageachievementblockviewcertifications_parameters() {
        return new external_function_parameters([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results to return', VALUE_DEFAULT, 0),
            'contextid' => new external_value(PARAM_INT, 'contextid'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
        ]);
    }
    public static function manageachievementblockviewcertifications(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/blocks/achievements/classes/local/lib.php');
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::manageachievementblockviewcertifications_parameters(),
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
        $result_certi = certification_details($stable,$filtervalues);
        $totalcount = $result_certi['count'];
        if($totalcount>0){
            $data=$result_certi['data'];
            $status='';
        }else{
            $data=array();  //No data available in table
            $status=get_string('nocertifications_achieved', 'block_achievements');//'No certifications Achieved yet'
        }
        
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
            'status'=>$status,
        ];

    }


    public static function  manageachievementblockviewcertifications_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'module_id'=>new external_value(PARAM_RAW, 'badge name', VALUE_OPTIONAL),
                        'certificate_code'=>new external_value(PARAM_RAW, 'more', VALUE_OPTIONAL),
                        'certificate_name'=>new external_value(PARAM_RAW, 'more', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }


    public static function get_user_certificates_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id'),
           
        ]);
    }
    public static function get_user_certificates($userid) {
        global $CFG,$PAGE;
        require_once($CFG->dirroot . '/blocks/achievements/classes/local/lib.php');
        $PAGE->set_context($contextid);        
        
        $result_certi = certification_details($stable,$filtervalues);
        $totalcount = $result_certi['count'];

        $data=$result_certi['data'];          
        $arraylen = sizeof($data);
        for ($i=0; $i < $arraylen; $i++) {
            $data[$i]['certificate_download'] =  $CFG->wwwroot."/admin/tool/certificate/view.php?code=".$data[$i]['certificate_code'];
        }
       
        return [
            'totalcount' => $totalcount,
            'records' =>$data,            
        ];

    }


    public static function  get_user_certificates_returns() {
        return new external_single_structure([
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'records' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'module_id'=>new external_value(PARAM_RAW, 'module id ', VALUE_OPTIONAL),
                        'module_type'=>new external_value(PARAM_RAW, 'module type ', VALUE_OPTIONAL),
                        'certificate_code'=>new external_value(PARAM_RAW, 'certificate code', VALUE_OPTIONAL),
                        'certificate_name'=>new external_value(PARAM_RAW, 'certificate name', VALUE_OPTIONAL),
                        'certificate_download'=>new external_value(PARAM_RAW, 'certificate download url', VALUE_OPTIONAL),
                    )
                )
            )
        ]);
    }








}
