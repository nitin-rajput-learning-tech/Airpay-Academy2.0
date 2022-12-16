<?php
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/lib.php");

class usersprofilefields_district_external extends external_api {
    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_create_district_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'district', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array', false)
            )
        );
    }

    public function submit_create_district($id, $contextid, $jsonformdata){
        global $PAGE, $CFG;
        // We always must pass webservice params through validate_parameters.
        $context = context::instance_by_id($contextid, MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($jsonformdata);

        $data = array();
        parse_str($serialiseddata, $data);
        $warnings = array();
        $mform = new \usersprofilefields_district\forms\district_form(null, array(), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        $districtlib = new \usersprofilefields_district\lib();
        if($validateddata){
            $districtid = $districtlib->create_update_district($validateddata);
        } else {
            // Generate a warning.      
            throw new moodle_exception('Error in creation of district');
        }
        return $districtid;
    }

    public function submit_create_district_returns(){
        return new external_value(PARAM_INT, 'districtid');
    }
}