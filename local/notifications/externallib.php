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
 * @subpackage local_notifications
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
class local_notifications_external extends external_api {
   
    
    
    
    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_create_notification_form_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'id', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the onlinetests'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
                'form_status' => new external_value(PARAM_INT, 'Form position', 0)
            )
        );
    }
    
    /**
     * Submit the create group form.
     *
     * @param int $contextid The context id for the course.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return int new group id.
    */
    public static function submit_create_notification_form($id, $contextid, $jsonformdata, $form_status) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/local/notifications/lib.php');
 
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_create_notification_form_parameters(),
                                            ['id' => $id, 'contextid' => $contextid, 'jsonformdata' => $jsonformdata, 'form_status' => $form_status]);
 
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
 
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
 
        $data = array();
        parse_str($serialiseddata, $data);
        $warnings = array();
        // print_r($data);
        // The last param is the ajax submitted data.
        $mform = new local_notifications\forms\notification_form(null, array('form_status' => $form_status,'id' => $data['id'],'org'=>$data['costcenterid'],'moduleid'=>$data['moduleid']), 'post', '', null, true, $data);
        
        $validateddata = $mform->get_data();
        $lib = new \notifications();
        if ($validateddata) {
            if ($validateddata->id > 0) {
                $validateddata->usermodified = $USER->id;
                $validateddata->timemodified = time();
                if($form_status == 0){
                    $validateddata->moduleid=$data['moduleid'];
                    $validateddata->body = $validateddata->body['text'];
                   
                    if (is_array($validateddata->moduleid)){
                        $validateddata->moduleid = implode(',',$validateddata->moduleid);
                        $notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$validateddata->notificationid));
                        $notif_type_find=explode('_',$notif_type);
                        $validateddata->moduletype = $notif_type_find[0];
                    }else{
                        $validateddata->moduleid = NULL;
                        $notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$validateddata->notificationid));
                        $notif_type_find=explode('_',$notif_type);
                        $validateddata->moduletype = $notif_type_find[0];
                    }
                    $insert = $lib->insert_update_record('local_notification_info', 'update', $validateddata);
                }else{
                    $validateddata->adminbody = $validateddata->adminbody['text'];
                }
              
                $insert = $lib->insert_update_record('local_notification_info', 'update', $validateddata);
            } else if ($validateddata->id <= 0) {
                $validateddata->moduleid=$data['moduleid'];
                $validateddata->body = $validateddata->body['text'];
               $notificationarr = (array)$validateddata->moduleid;
                if ($validateddata->moduleid){
                    $validateddata->moduleid = implode(',',$notificationarr);
                    $notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$validateddata->notificationid));
                        $notif_type_find=explode('_',$notif_type);
                        $validateddata->moduletype = $notif_type_find[0];
                }else{
                    $validateddata->moduleid = NULL;
                    $notif_type = $DB->get_field('local_notification_type', 'shortname', array('id'=>$validateddata->notificationid));
                    $notif_type_find=explode('_',$notif_type);
                    $validateddata->moduletype = $notif_type_find[0];
                }
                $validateddata->usermodified = $USER->id;
                $validateddata->timemodified = time();
                $insert = $lib->insert_update_record('local_notification_info', 'insert', $validateddata);
            }
            $formheaders = array_keys($mform->formstatus);
            $next = $form_status + 1;
            $nextform = array_key_exists($next, $formheaders);
            if ($nextform !== false/*&& end($formheaders) !== $form_status*/) {
                $form_status = $next;
                $error = false;
            } else {
                $form_status = -1;
                $error = true;
            }

        } else {
            // Generate a warning.
            throw new moodle_exception('Error in submission');
        }

        $return = array(
            // 'error' => $error,
            'id' => $insert,
            'form_status' => $form_status);
 
        return $return;
    }
 
    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_create_notification_form_returns() {
        return new external_single_structure(array(
            'id' => new external_value(PARAM_INT, 'notificationid'),
            'form_status' => new external_value(PARAM_INT, 'form_status'),
        ));
    }



        //////For displaying on index page//////////
      public static function managenotificationsview_parameters() {
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
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function managenotificationsview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/notifications/lib.php');
        require_login();
        $PAGE->set_url('/local/notifications/index.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::managenotificationsview_parameters(),
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
        $result_skill = notification_details($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        $data=$result_skill['data'];
        

        return [
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
    public static function  managenotificationsview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of skills in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    
                                    'notification_id'=>new external_value(PARAM_RAW, 'id in notification', VALUE_OPTIONAL),
                                    'contextid'=>new external_value(PARAM_RAW, 'context id in notification', VALUE_OPTIONAL),
                                    'notification_type' => new external_value(PARAM_RAW, 'type in notification', VALUE_OPTIONAL),
        
                                    'code' => new external_value(PARAM_RAW, 'code', VALUE_OPTIONAL),
                                    'subject' => new external_value(PARAM_RAW, 'subject of notification', VALUE_OPTIONAL),
                                    'organization' => new external_value(PARAM_RAW, 'organization name in notification', VALUE_OPTIONAL),
                                    
                                ), 'individual records', VALUE_OPTIONAL
                            ), 'records info', VALUE_OPTIONAL
                        )
        ]);
    }



}
