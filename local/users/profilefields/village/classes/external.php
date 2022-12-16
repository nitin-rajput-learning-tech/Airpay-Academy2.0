<?php
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/lib.php");

class usersprofilefields_village_external extends external_api {
    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_create_village_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'village', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation', false),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array', false)
            )
        );
    }

    public function submit_create_village($id, $contextid, $jsonformdata){
        global $PAGE, $CFG;
        // We always must pass webservice params through validate_parameters.
        $context = context::instance_by_id($contextid, MUST_EXIST);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($jsonformdata);

        $data = array();
        parse_str($serialiseddata, $data);
        $warnings = array();
        $mform = new \usersprofilefields_village\forms\village_form(null, array(), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        $villagelib = new \usersprofilefields_village\lib();
        if($validateddata){
            $villageid = $villagelib->create_update_village($validateddata);
        } else {
            // Generate a warning.      
            throw new moodle_exception('Error in creation of village');
        }
        return $villageid;
    }

    public function submit_create_village_returns(){
        return new external_value(PARAM_INT, 'villageid');
    }
}