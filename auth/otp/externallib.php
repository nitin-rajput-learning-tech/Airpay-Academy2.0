<?php
require_once("$CFG->libdir/externallib.php");

class auth_otp_external extends external_api {


    public static function auth_otp_generate_parameters() {
        return new external_function_parameters(
                            array('application_id' => new external_value(PARAM_RAW, 'Application ID',VALUE_DEFAULT,'')

                                 )
                    );
    }

    public static function auth_otp_generate($application_id) { 
    //Don't forget to set it as static
        global $CFG, $DB,$USER;

        $params = self::validate_parameters(self::auth_otp_generate_parameters(),
            array('application_id' => $application_id));
		require_once($CFG->dirroot . "/auth/otp/lib.php");
        $otpsend=new otp();
		$getresponse=$otpsend->send_otp_touser($application_id);

        return $getresponse;
    }


    public static function auth_otp_generate_returns() {
         return new external_value(PARAM_RAW, 'otpresponse');
    }

    public static function request_otp_parameters() {
        return new external_function_parameters(
            array(
                'country' => new external_value(PARAM_RAW, 'country'),
                'username' => new external_value(PARAM_RAW, 'username')
            )
        );
    }

    public static function request_otp($country, $username) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::request_otp_parameters(),
        array('country' => $country, 'username' => $username));

        require_once($CFG->dirroot . "/auth/otp/lib.php");
        $otp = new otp();
        // $status = $otp->send_otp_touser($username);
        $status= $otp->validate_application($username, $country);
        return array('status' => $status);
    }

    public static function request_otp_returns() {
        return new external_function_parameters(
            array(
                'status' => new external_value(PARAM_INT, 'status')
            )
        );
    }

    public static function validate_otp_parameters() {
        return new external_function_parameters(
            array(
                'country' => new external_value(PARAM_RAW, 'country'),
                'username' => new external_value(PARAM_RAW, 'username'),
                'password' => new external_value(PARAM_INT, 'password')
            )
        );
    }

    public static function validate_otp($country, $username, $password) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::validate_otp_parameters(),
                                 array('country' => $country, 'username' => $username, 'password' => $password));

        require_once($CFG->dirroot . "/auth/otp/lib.php");
        $otp = new otp();
        $status = $otp->validate_otp($username, $password, $country);

        return array('status' => $status);
    }

    public static function validate_otp_returns() {
        return new external_function_parameters(
            array(
                'status' => new external_value(PARAM_INT, 'status')
            )
        );
    }

    public static function validateuserdetails_parameters() {
        return new external_function_parameters(
            array(
            'username' => new external_value(PARAM_RAW, 'username'),
            'otp' => new external_value(PARAM_RAW, 'otp'),
            'type' => new external_value(PARAM_INT, 'type'),
			'countrycode' => new external_value(PARAM_RAW, 'countrycode')
            )
        );
    }

    public static function validateuserdetails($username, $otp = 0, $type,$countrycode) {
        global $CFG, $DB,$USER;
        $params = self::validate_parameters(self::validateuserdetails_parameters(),
            array('username' => $username, 'otp' => $otp, 'type' => $type,'countrycode'=>$countrycode));
        require_once($CFG->dirroot . "/auth/otp/lib.php");
        $otpsend = new otp();
        if (!empty($username) && $type == 1) {
           $response = $otpsend->validate_application($username,$countrycode);
        } else if (!empty($username) && $type == 2 && !empty($otp)) {
           $response = $otpsend->validate_otp($username, $otp,$countrycode);
        } else {
            $response = '';
        }
        return $response;
    }

    public static function validateuserdetails_returns() {
        return new external_value(PARAM_RAW, 'response');
    }

}
