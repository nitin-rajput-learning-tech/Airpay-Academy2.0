<?php
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/lib.php");

class usersprofilefields_states_external extends external_api {
    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_create_states_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'states', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array', false)
            )
        );
    }

    public function submit_create_states($id, $contextid, $jsonformdata){
        global $PAGE, $CFG;
        // We always must pass webservice params through validate_parameters.
        $context = context::instance_by_id($contextid, MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($jsonformdata);

        $data = array();
        parse_str($serialiseddata, $data);
        $warnings = array();
        $mform = new \usersprofilefields_states\forms\states_form(null, array(), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        $stateslib = new \usersprofilefields_states\lib();
        if($validateddata){
            $statesid = $stateslib->create_update_states($validateddata);
        } else {
            // Generate a warning.      
            throw new moodle_exception('Error in creation of states');
        }
        return $statesid;
    }

    public function submit_create_states_returns(){
        return new external_value(PARAM_INT, 'statesid');
    }
}