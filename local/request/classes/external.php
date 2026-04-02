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
 * @subpackage local_request
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
use local_request\api\requestapi;
class local_request_external extends external_api {
	public static function view_availiable_request_parameters(){
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
	public static function view_availiable_request(
		$options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata){
		global $OUTPUT, $CFG, $DB,$USER,$PAGE;
		require_login();
		$PAGE->set_context($contextid);
		$params = self::validate_parameters(
            self::view_availiable_request_parameters(),
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
        $filtervalues = (object)$filtervalues;
        $filtervalues->courseid = $decodedata->courseid;
        if($decodedata->component){
          $filtervalues->request = $decodedata->component;
        }

        try{
          $stable = new \stdClass();
          $stable->thead = true;
  		    $stable->start = $offset;
  		    $stable->length = $limit;
          $requestview = new \local_request\output\requestview;
          $requests_content = $requestview->get_requestdetails($stable,$filtervalues);
          $data = $requests_content['record'];
          $totalcount = $requests_content['requestcount'];
          $deny_capability = $requests_content['deny_capability'];
          $approve_capability = $requests_content['approve_capability'];
        }catch(Exception $e){
          throw new moodle_exception(get_string('error_in_fetching_listofrequests','local_request'));
        }
        return [
          'totalcount' => $totalcount,
	        'filterdata' => $filterdata,
	        'records' =>$data,
	        'options' => $options,
	        'dataoptions' => $dataoptions,
	        'deny_capability' => $deny_capability,
	        'approve_capability' => $approve_capability
        ];
	}
	public static function view_availiable_request_returns(){
		return new external_single_structure([
          'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
          'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
          'totalcount' => new external_value(PARAM_INT, 'total number of challenges in result set'),
          'filterdata' => new external_value(PARAM_RAW, 'total number of challenges in result set'),
          // 'records' => new external_single_structure(
          //         array(
                  	'records' => new external_multiple_structure(
                          new external_single_structure(
                              array(
                                  'id' => new external_value(PARAM_RAW, 'id', VALUE_OPTIONAL),
                                  'status' => new external_value(PARAM_RAW, 'status', VALUE_OPTIONAL),
                                  'enablebutton' => new external_value(PARAM_INT, 'enablebutton'),
                                  'approvestatus' => new external_value(PARAM_INT, 'approvestatus', VALUE_OPTIONAL),
                                  'rejectstatus' => new external_value(PARAM_INT, 'rejectstatus' , VALUE_OPTIONAL),
                                  'compname' => new external_value(PARAM_RAW, 'compname' , VALUE_OPTIONAL),
                                  'requestedby' => new external_value(PARAM_INT, 'requestedby', VALUE_OPTIONAL),
                                  'requesteddate' => new external_value(PARAM_RAW, 'requesteddate', VALUE_OPTIONAL),
                                  'componentid' => new external_value(PARAM_INT, 'componentid', VALUE_OPTIONAL),
                                  'requesteduser' => new external_value(PARAM_RAW, 'requesteduser', VALUE_OPTIONAL),
                                  'responder' => new external_value(PARAM_RAW, 'responder', VALUE_OPTIONAL),
                                  'respondeddate' => new external_value(PARAM_RAW, 'respondeddate', VALUE_OPTIONAL),
                                  'componentname' => new external_value(PARAM_RAW, 'componentname', VALUE_OPTIONAL),
                                  'componenticonclass' => new external_value(PARAM_RAW, 'componenticonclass', VALUE_OPTIONAL),
                                  'customimage_required' => new external_value(PARAM_RAW, 'customimage required', VALUE_OPTIONAL),
                                  'daysdone' =>  new external_value(PARAM_RAW, 'daysdone', VALUE_OPTIONAL),
                                  'responded'  => new external_value(PARAM_INT, 'responded', VALUE_OPTIONAL),
                              )
                          )
                      ),
                    
                  // )
              // )
          	'deny_capability' => new external_value(PARAM_INT, 'deny_capability', VALUE_OPTIONAL),
          	'approve_capability' => new external_value(PARAM_INT, 'approve_capability', VALUE_OPTIONAL),

      ]);
	}

  public static function enrol_component_parameters(){
    return new external_function_parameters([
                'component' => new external_value(PARAM_RAW, 'component'),
                'componentid' => new external_value(PARAM_INT, 'componentid'),
                'action' => new external_value(PARAM_RAW, 'action'),
                'id' => new external_value(PARAM_INT, 'Id',
                    VALUE_DEFAULT, 0)
            ]);
  }
  public static function enrol_component(
        $component,
        $componentid,
        $action,
        $id = 0){
    global $OUTPUT, $CFG, $DB,$USER,$PAGE;
    $params = self::validate_parameters(
            self::enrol_component_parameters(),
            [
                'component' => $component,
                'componentid' => $componentid,
                'action' => $action,
                'id' => $id
            ]
        );
      $updatedid = requestapi::create($component, $componentid);
      return array('status' => $updatedid);
  }
  public static function enrol_component_returns(){
    return new external_single_structure([
          'status' => new external_value(PARAM_INT, 'Status')
      ]);
  }

}
