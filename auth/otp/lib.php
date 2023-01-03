<?php


 /**
 * OTP authentication plugin version specification.
 *
 * @package    auth
 * @subpackage otp
 * @copyright  2022 Sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class otp {

  public function __construct() {
    $this->apiurl = get_config('auth_otp', 'otpserviceip');
    $this->senderid = get_config('auth_otp', 'senderid');
    $this->token = get_config('auth_otp', 'apikey');
  }

  /*
   * @method local_logs Get logs
   * @param $event
   * @param $module
   * @param $description
   * @param $type
   * @output data will be insert into mdl_local_logs table
   */
  function local_logs($event, $module,$moduleid, $description, $type=NULL){
      global $DB, $USER, $CFG;

      $userid                 = $USER->id;
      $log_data               = new stdClass();
      $log_data->event        = $event;
      $log_data->module       = $module;
	    $log_data->moduleid     = $moduleid;
      $log_data->description  = $description;
      $log_data->type         = $type;
      $log_data->timecreated  = time();
      $log_data->timemodified = time();
      $log_data->usercreated  = $userid;
      $log_data->usermodified = $userid;
      $result = $DB->insert_record('local_logs', $log_data);
  }

  public function validate_application($username) {
    global $DB,$CFG;
    $sql = "SELECT u.id, u.username, u.email, u.phone1 FROM {user} u WHERE u.username= ? AND u.confirmed = 1 AND u.auth = 'otp'";
    $validusers = $DB->get_record_sql($sql, [$username]);

    $phonenumber = preg_replace( '/[^0-9]/', '', $validusers->phone1 );
    $phonelength = strlen($phonenumber);
    $appdetails = new stdClass();
	  if (empty($validusers)) {
      $appdetails->username = $username;
      $desc=get_string('notvalidapplicant', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'warning');
		  return 1;
	  } else if (empty($validusers->phone1) || $phonelength != 10) {
		  $appdetails->username = $username;
      $appdetails->phonenumber = $validusers->phone1;
      $desc=get_string('notvalidphone', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'warning');
		  return 2;
	  } else {
      $string = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $string_shuffled = str_shuffle($string);
      $password = substr($string_shuffled, 1, 7);
      $otp = mt_rand(1001, 9999);
      $msg = "OTP to login into LMS is " . $otp . ". Do not share the OTP with anyone for security reasons.";
      $curl = curl_init();
      $curloptions = $this->get_curl_options($this->apiurl, $phonenumber, $this->token, $this->senderid, $msg);
      curl_setopt_array($curl, $curloptions);

      $appdetails->username = $validusers->username;
      $appdetails->phonenumber = $phonenumber;
      $result = curl_exec($curl);
      if(empty($result)) {
        return 0;
      } else {
          $text = json_decode($result);
          if($text->status != 'OK') {
            $desc = get_string('errorcodefromservice', 'auth_otp', $appdetails) . $text->status;
            $this->local_logs('otp', 'Server', 1, $desc, 'Error');
            curl_close($curl);
            return 0;
          }
      }

      $this->update_otp($otp, $phonenumber, $username, $validusers->id);
      $appdetails->otp = $otp;
      $desc = get_string('otpsendtomobile', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'Success');
      return 3;
		}
    exit;
  }

	public function validate_otp($username, $otp) {
		global $DB, $CFG;
    $sql = "SELECT u.id,u.username,u.email,u.phone1 FROM {user} u WHERE u.username = ? AND u.auth = 'otp' ";
    $validusers = $DB->get_record_sql($sql, [$username]);

    $sql = "SELECT * FROM {local_otp} op WHERE op.userid = ?  AND op.otpcode = ? ORDER BY id DESC LIMIT 1 ";

    $validinfo = $DB->get_record_sql($sql, [$validusers->id, $otp]);
    $appdetails = new stdClass();
		if (!empty($validinfo)) {
	    if ($validinfo->trystatus > 3) {
		    $appdetails->username = $username;
			  $appdetails->otp = $otp;
			  $desc=get_string('otpabovethree', 'auth_otp', $appdetails);
			  $this->local_logs('otp', 'User', 1, $desc, 'moreotp');
	      return 2;
		  } else if ($validinfo->inuse == 1) {
		    $appdetails->username=$username;
			  $appdetails->otp = $otp;
			  $desc = get_string('incorrectotp', 'auth_otp', $appdetails);
			  $this->local_logs('otp', 'User', 1, $desc, 'warning');
        return 4;
      } else {
				$trystatus = $validinfo->trystatus;
				$otpdetails = new stdClass();
				$otpdetails->id = $validinfo->id;
				$otpdetails->trystatus = ++$trystatus;
				$DB->update_record('local_otp', $otpdetails);

				$appdetails->username = $username;
				$appdetails->otp = $otp;
				$appdetails->trycount = $otpdetails->trystatus;
				$desc=get_string('validotpentered', 'auth_otp', $appdetails);
				$this->local_logs('otp', 'User', 1, $desc, 'Success');
				return 1;
      }
    } else {

      $sql = "SELECT * FROM {local_otp} op WHERE op.username = ? ORDER BY id DESC LIMIT 1 ";
      $validinfo = $DB->get_record_sql($sql, [$username]);
      $trystatus = $validinfo->trystatus;
      $otpdetails = new stdClass();
      $otpdetails->id = $validinfo->id;
      $otpdetails->trystatus = ++$trystatus;
      $DB->update_record('local_otp', $otpdetails);
      if($trystatus > 3){
        $appdetails->username = $username;
        $appdetails->otp = $otp;
        $desc=get_string('otpabovethree', 'auth_otp', $appdetails);
        $this->local_logs('otp', 'User', 1, $desc, 'warning');
        return 2;
      } else {
        $appdetails->username = $username;
        $appdetails->otp = $otp;
        $desc = get_string('otpnotvalid', 'auth_otp', $appdetails);
        $this->local_logs('otp', 'User', 1, $desc, 'warning');
        return 3;
      }
    }
  }

  /**
     * [send_otp_touser description]
     * @param  [type] $username [description]
     * @return [type]           [description]
     */
  public function send_otp_touser($username) {
		global $DB, $CFG;
		$appdetails = new stdClass();

    $sql = "SELECT u.id, u.username, u.email, u.phone1 FROM {user} u WHERE u.username = ?  AND u.confirmed = 1 AND u.auth = 'otp' ";
    $validusers = $DB->get_record_sql($sql, [$username]);

    $phonenumber = preg_replace( '/[^0-9]/', '', $validusers->phone1 );
    $phonelength = strlen($phonenumber);

    $appdetails->username = $username;
    //check if user is valid or not.
    if (empty($validusers)) {
      $desc = get_string('notvalidapplicant', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'warning');
      return 1;
    } else if (empty($validusers->phone1) || $phonelength != 10) {

      $appdetails->phonenumber = $validusers->phone1;
      $desc = get_string('notvalidphone', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'warning');
      return 2;
    } else {
      $otp = mt_rand(1001, 9999);

      $msg = "OTP to login into LMS is " . $otp . ". Do not share the OTP with anyone for security reasons.";
      $curloptions = $this->get_curl_options($this->apiurl, $phonenumber, $this->token, $this->senderid, $msg);
      $curl = curl_init();
      curl_setopt_array($curl, $curloptions);

      $appdetails->username = $validusers->username;
      $appdetails->phonenumber = $phonenumber;
      $result = curl_exec($curl);
      if(empty($result)) {
        return 0;
      } else {
        $text = json_decode($result);
        if ($text->status != 'OK') {
          $desc = get_string('errorcodefromservice', 'auth_otp', $appdetails). $text->status;
          $this->local_logs('otp', 'Server', 1, $desc, 'Error');
          trigger_error(curl_error($curl));
          curl_close($curl);
          return 0;
        }
      }

      $this->update_otp($otp, $phonenumber, $username, $validusers->id);

      $appdetails->otp = $otp;
      $desc = get_string('otpsendtomobile', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'Success');
      return 3;
    }
    exit;
  }

  private function get_curl_options($apiurl, $mobile, $token, $senderid, $msg) {
    $postfields = [
      'to' => $mobile,
      'api_key' => $token,
      'sender' => $senderid,
      'method' => 'sms',
      'message' => $msg,
    ];
    return [
      CURLOPT_URL => $apiurl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $postfields,
      CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache",
      ),
    ];
  }
  private function update_otp($otp, $phonenumber, $username, $userid) {
    global $DB;
    $otpdetails = new stdClass();
    $otpdetails->otpcode = $otp;
    $otpdetails->phonenumber = $phonenumber;
    $otpdetails->username = $username;
    $otpdetails->userid = $userid;
    $otpdetails->timecreated = time();

    $exsql = "SELECT * FROM {local_otp} op WHERE userid = ? AND inuse = 0 AND trystatus < 3 ORDER BY id DESC LIMIT 1 ";
    $checkexist = $DB->get_record_sql($exsql, [$userid]);

    if ($checkexist) {
      $otpdetails->id = $checkexist->id;
      $otpdetails->trystatus = 0;
      $otpdetails->timemodified = time();
      $DB->update_record('local_otp', $otpdetails);
    } else {
      $DB->insert_record('local_otp', $otpdetails);
    }
  }
}
