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
 * local local_groups
 *
 * @package    local_groups
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/user/selector/lib.php');
class local_groups_external extends external_api {

		/**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_groupsform_form_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'groupsid', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the groups'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
            
            )
        );
    }
    

    /**
     * form submission of groups name and returns instance of this object
     *
     * @param int $contextid 
     * @param [string] $jsonformdata 
     * @return groups form submits
     */
	public static function submit_groupsform_form($id, $contextid, $jsonformdata){
		global $DB,$PAGE, $CFG;

		require_once($CFG->dirroot . '/local/groups/lib.php');
        // We always must pass webservice params through validate_parameters.
		$context = context::instance_by_id($contextid, MUST_EXIST);
        // We always must call validate_context in a webservice.
		self::validate_context($context);
        $data=array();
        $serialiseddata = json_decode($jsonformdata);
        if(is_object($serialiseddata)){
            $serialiseddata = serialize($serialiseddata);
        }
        parse_str($serialiseddata, $data);
        $warnings = array();   
        $mform = new \local_groups\form\edit_form(null,array(), 'post', '', null, true, $data);
        $valdata = $mform->get_data();
        if($valdata){
            if($valdata->id>0){
                $open_path=$DB->get_field('local_groups','open_path',array('id'=>$valdata->id));
                list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$open_path);

                if($valdata->open_costcenterid !=$org){

                    local_costcenter_get_costcenter_path($valdata);

                }
                local_users_get_userprofile_datafields($valdata,$data); 
                $groupsupdate = local_groups_update_groups($valdata);
            } else{
                local_costcenter_get_costcenter_path($valdata);
                local_users_get_userprofile_datafields($valdata,$data);
				$groupsinsert = local_groups_add_groups($valdata);
			}
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
    public static function submit_groupsform_form_returns() {
        return new external_value(PARAM_INT, 'groups id');
    }
    /**
     * [groups_status_confirm_parameters description]
     * @return [external function param] [parameters for the groups status update]
     */
	public static function groups_status_confirm_parameters() {
		return new external_function_parameters(
			array(
				'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
				'id' => new external_value(PARAM_INT, 'ID of the record', 0),
				'confirm' => new external_value(PARAM_INT, 'confirm',true),
				'actionstatus' => new external_value(PARAM_RAW, 'actionstatus', false),
				'actionstatusmsg' => new external_value(PARAM_RAW, 'actionstatusmsg', false),
			)
		);
	}
	/**
	 * [groups_status_confirm description]
	 * @param  [type] $action  [description]
	 * @param  [int] $id      [id of the groups]
	 * @param  [int] $confirm [confirmation key]
	 * @return [boolean]          [true if success]
	 */
	public static function groups_status_confirm($action, $id, $confirm) {
		global $DB;	
		if ($id) {
			$visible=$DB->get_field('local_groups','visible',array('id'=>$id));
			if($visible==1){
				$visible=0;
			}else{
				$visible=1;
			}
			$sql = "UPDATE {local_groups}
               SET visible =$visible
             WHERE id=$id";
			
			$DB->execute($sql);
			$return = true;
		} else {
			$return = false;
		}
		
		return $return;
	}
	/**
	 * [groups_status_confirm_returns description]
	 * @return [external value] [boolean]
	 */
	public static function groups_status_confirm_returns() {
		return new external_value(PARAM_BOOL, 'return');
	}
	/**
	 * [groups_delete_groups_parameters description]
	 * @return [external value] [params for deleting groups]
	 */
	public static function groups_delete_groups_parameters(){
		return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'userid', 0)
           		)
        );
	}
	/**
	 * [groups_delete_groups description]
	 * @param  [int] $id id of groups to be deleted 
	 * @return [boolean]     [true for success]
	 */
	public static function groups_delete_groups($id){
		global $DB;
		if($id){
			$groupsdelete = $DB->delete_records('local_groups', array('id' => $id));
        	$groupsdelete .= $DB->delete_records('local_groups_permissions', array('groupid' => $id));
			return true;
		}else {
			throw new moodle_exception('Error in deleting');
			return false;
		}
	}
	/**
	 * [groups_delete_groups_returns description]
	 * @return [external value] [boolean]
	 */
	public static function groups_delete_groups_returns() {
		return new external_value(PARAM_BOOL, 'return');
	}

	/**
     * Describes the parameters for departmentlist webservice.
     * @return external_function_parameters
     */
    public static function departmentlist_parameters() {
        return new external_function_parameters(
            array(
                'orgid' => new external_value(PARAM_INT, 'The id for the groups / organization')
            )
        );
    }

    /**
     * departments list
     *
     * @param int $orgid id for the organization
     * @return array 
     */
    public static function departmentlist($orgid) {
        global $DB, $CFG, $USER;
        $orglib = new local_groups\functions\userlibfunctions();
        $departmentlist = $orglib->find_departments_list($orgid);
        $return = array(
            'departments' => json_encode($departmentlist)
            );
        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function departmentlist_returns() {
        return new external_function_parameters(
            array(
                'departments' => new external_value(PARAM_RAW, 'Departmentlist ')
            )
        );
    }


    /**
     * 
     * @return external_function_parameters
     */
    public static function submit_licenceform_parameters() {
        return new external_function_parameters(
            array(
                'jsonformdata' => new external_value(PARAM_RAW, 'The data of licence settings form, encoded as a json array')
            )
        );
    }

    /**
     *
     *
     * @param int $orgid id for the organization
     * @return array 
     */
    public static function submit_licenceform($jsonformdata) {
        global $PAGE;

        $params = self::validate_parameters(self::submit_licenceform_parameters(),
                                            ['jsonformdata' => $jsonformdata]);

        $serialiseddata = json_decode($params['jsonformdata']);
        $data = array();
        parse_str($serialiseddata, $data);
        $PAGE->set_context((new \local_groups\lib\accesslib())::get_module_context());
        $mform = new \local_groups\form\licence_form(null, array(), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        $formdata = data_submitted();
        if ($validateddata) {
	        set_config('serialkey', $validateddata->licencekey, 'local_groups');
	        $licencekeyhash = md5($validateddata->licencekey);
	        set_config('lms_serialkey', $licencekeyhash, 'local_groups');

	        $return = array(
	            'status' => 'success',
	            'licencekey' => $validateddata->licencekey
	            );
        	return $return;
	    }else{
	    	throw new moodle_exception('Error in creation');
	    }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function submit_licenceform_returns() {
        return new external_function_parameters(
            array(
                'status' => new external_value(PARAM_RAW, 'success/fail'),
                'licencekey' => new external_value(PARAM_RAW, ' Licence key ')
            )
        );
    }

    public static function managegroupsview_parameters() {
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


    public static function managegroupsview(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/groups/lib.php');
        require_login();
        $PAGE->set_url('/local/groups/index.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::managegroupsview_parameters(),
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
    $stable->thead = false;
    $stable->start = $offset;
    $stable->length = $limit;
  //  $group_members_count = $DB->count_records('local_groups');
    $local_groups = new local_groups($stable->start, $stable->length, $filtervalues, $showall);
    $group_count=$local_groups->groups['totalgroups'];
    $output = $PAGE->get_renderer('local_groups');
       $result = $output->render($local_groups);
        return [
            'totalcount' => $group_count,
            'records' =>$result,
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata,
        ];

    }

    /**
     * Returns description of method result value.
     */
    public static function  managegroupsview_returns() {
        return new external_single_structure([
            'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
            'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
            'totalcount' => new external_value(PARAM_INT, 'total number of users in result set'),
            'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'actions' => new external_value(PARAM_RAW, 'user  actions', VALUE_OPTIONAL),
                                    'userid' => new external_value(PARAM_RAW, 'userid', VALUE_OPTIONAL),
                                    'groupid' => new external_value(PARAM_RAW, 'groupid', VALUE_OPTIONAL),
                                    'orgname' => new external_value(PARAM_RAW, 'groupname', VALUE_OPTIONAL),
                                    'groupname' => new external_value(PARAM_RAW, 'groupname', VALUE_OPTIONAL),
                                    'userimages' => new external_value(PARAM_RAW, 'user pic', VALUE_OPTIONAL),
                                    'location_url' => new external_value(PARAM_RAW, 'location_url', VALUE_OPTIONAL),
                                    'groupcount' => new external_value(PARAM_RAW, 'total count of users', VALUE_OPTIONAL),
                                    'visible' => new external_value(PARAM_RAW, 'visibility of group', VALUE_OPTIONAL),
                            )
                    )
                )
        ]);
    }
} 
