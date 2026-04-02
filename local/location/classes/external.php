
<?php
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
class local_location_external extends external_api {

        /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_instituteform_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    /**
     * form submission of institute name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return institute form submits
     */
    public static function submit_instituteform_form($contextid, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/location/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_instituteform_form_parameters(),
                                    ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);
        $context = (new \local_location\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

        parse_str($serialiseddata, $data);
        $warnings = array();
         $mform = new local_location\form\instituteform(null, array(), 'post', '', null, true, $data);
        $institutes  = new local_location\event\location();
        $valdata = $mform->get_data();

        if($valdata){
            if($valdata->id>0){

                $institutes->institute_update_instance($valdata);
            } else{

                $institutes->institute_insert_instance($valdata);
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
    public static function submit_instituteform_form_returns() {
        return new external_value(PARAM_INT, 'institute id');
    }

public static function submit_roomform_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    /**
     * form submission of institute name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return institute form submits
     */
    public static function submit_roomform_form($contextid, $jsonformdata){
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/local/location/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_roomform_form_parameters(),
                                    ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);
        $context = (new \local_location\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

        parse_str($serialiseddata, $data);
        $warnings = array();
         $mform = new local_location\form\roomform(null, array(), 'post', '', null, true, $data);
        $rooms  = new local_location\event\location();
        $valdata = $mform->get_data();

        if($valdata){
            if($valdata->id>0){
                $rooms->room_update_instance($valdata);
            } else{
                $rooms->room_insert_instance($valdata);
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
    public static function submit_roomform_form_returns() {
        return new external_value(PARAM_INT, 'room id');
    }
     /**
    * [data_for_program_courses_parameters description]
     * @return parameters for data_for_program_courses
     */
    public static function location_locations_parameters() {
        return new external_function_parameters(
            array(
            )
        );
    }
    public static function location_locations() {
        global $PAGE, $DB, $USER;

        $params = self::validate_parameters(self::location_locations_parameters(), array());

        $costcenterid = explode('/',$USER->open_path)[1];

        $institutes = $DB->get_records('local_location_institutes', array('costcenter' => $costcenterid, 'visible' => 1));

        return array('institutes' => $institutes);
    }
    public static function location_locations_returns() {
        return new external_single_structure(array(
                'institutes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'id'),
                            'fullname' => new external_value(PARAM_RAW, 'fullname'),
                            'address' => new external_value(PARAM_RAW, 'address'),
                            'institute_type' => new external_value(PARAM_INT, 'institute_type')
                        )
                    ), VALUE_DEFAULT, array()
                )
            )
        );
    }
    /**
    * [data_for_program_courses_parameters description]
     * @return parameters for data_for_program_courses
     */
    public static function location_rooms_parameters() {
        return new external_function_parameters(
            array(
            )
        );
    }
    public static function location_rooms() {
        global $PAGE, $DB;

        $params = self::validate_parameters(self::location_rooms_parameters(), array());
        $rooms = $DB->get_records('local_location_room', array('visible' => 1));
        return array('rooms' => $rooms);
    }
    public static function location_rooms_returns() {
        return new external_single_structure(array(
                'rooms' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'id'),
                            'instituteid' => new external_value(PARAM_INT, 'instituteid'),
                            'name' => new external_value(PARAM_RAW, 'name'),
                            'building' => new external_value(PARAM_RAW, 'building'),
                            'address' => new external_value(PARAM_RAW, 'address'),
                            'capacity' => new external_value(PARAM_RAW, 'capacity')
                        )
                    ), VALUE_DEFAULT, array()
                )
            )
        );
    }

}
