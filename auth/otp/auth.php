<?php

/**

 * Authentication with Active Directory through Web Service
 * 
 */
 
 /**
 * ADwebservice authentication plugin version specification.
 *
 * @package    auth
 * @subpackage otp
 * @copyright  2014 Niranjan {niranjan@eabyas.in}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');

/**
 * AD Webservice authentication plugin.
 */
class auth_plugin_otp extends auth_plugin_base {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'otp';
        $this->roleauth = 'auth_otp';
        $this->errorlogtag = '[AUTH OTP] ';
        $this->config = get_config('auth_otp');
    }

    /**
     * Prevent authenticate_user_login() to update the password in the DB
     * @return boolean
     */
    function prevent_local_passwords() {
               return false;

    }

    /**
     * Authenticates user against the selected authentication provide (Ad web service)
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
        global $DB, $CFG;
        $extusername = core_text::convert($username, 'utf-8', $this->config->extencoding);
        $extpassword = core_text::convert($password, 'utf-8', $this->config->extencoding);
		 if (!$username or !$password) {    // Don't allow blank usernames or passwords
            return false;
        }
		
        //retrieve the user matching username
         if ($user = $DB->get_record('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id, 'auth'=>$this->authtype))) {
            
                $exsql="SELECT * from {local_otp} where userid={$user->id} AND inuse=0 order by id desc limit 1 ";
                $checkexist=$DB->get_record_sql($exsql);
                $otpdetails = new stdClass();
                    $otpdetails->id=18;
                    $otpdetails->inuse=100;
                    $otpdetails->timemodified=time();
                    $DB->update_record('local_otp', $otpdetails);

                if($checkexist && ($user->auth == 'otp')){
                    $otpdetails = new stdClass();
                    $otpdetails->id=$checkexist->id;
                    $otpdetails->inuse=1;
                    $otpdetails->timemodified=time();
                    $DB->update_record('local_otp', $otpdetails);
                    return true;
                }else{ 
                    return false;
                }

                   // return validate_internal_user_password($user, $password);

                } else {
                    return false;
                }
		
		

        //username must exist and have the right authentication method
        if (!empty($user) && ($user->auth == 'otp')) {
           
            return true;
        }

        return false;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return true;
    }

   
    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * TODO: as print_auth_lock_options() core function displays an old-fashion HTML table, I didn't bother writing
     * some proper Moodle code. This code is similar to other auth plugins (04/09/11)
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        global $OUTPUT, $CFG;

        // set to defaults if undefined
        if (!isset($config->otpserviceip)) {
            $config->otpserviceip = '';
        }
       
        echo '<table cellspacing="0" cellpadding="5" border="0">
            <tr>
               <td colspan="3">
                    <h2 class="main">';

        print_string('auth_otpserversettings', 'auth_otp');

       

        echo '</h2>
               </td>
            </tr>
            <tr>
                <td align="right"><label for="otpip">';

        print_string('auth_otpserviceip', 'auth_otp');

        echo '</label></td><td>';


        echo html_writer::empty_tag('input',
                array('type' => 'text', 'id' => 'otpserviceip', 'name' => 'otpserviceip',
                    'class' => 'otpserviceip', 'value' => $config->otpserviceip));

        if (isset($err["otpserviceip"])) {
            echo $OUTPUT->error_text($err["otpserviceip"]);
        }

        echo '</td><td>';
        $parse = parse_url($CFG->wwwroot);
      
        echo '</td></tr>';

       
        /// Block field options
        // Hidden email options - email must be set to: locked
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'value' => 'locked',
                    'name' => 'lockconfig_field_lock_email'));

    

        echo '</table>';
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     */
    function process_config($config) {
        // set to defaults if undefined
        if (!isset ($config->otpserviceip)) {
            $config->otpserviceip = '';
        }
        
        // save settings
        set_config('otpserviceip', $config->otpserviceip, 'auth_otp');
      

        return true;
    }

    /**
     * Called when the user record is updated.
     *
     * We check there is no hack-attempt by a user to change his/her email address
     *
     * @param mixed $olduser     Userobject before modifications    (without system magic quotes)
     * @param mixed $newuser     Userobject new modified userobject (without system magic quotes)
     * @return boolean result
     *
     */
    function user_update($olduser, $newuser) {
        if ($olduser->email != $newuser->email) {
            return false;
        } else {
            return true;
        }
    }

}
