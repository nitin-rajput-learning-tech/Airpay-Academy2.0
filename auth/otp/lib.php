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
    $this->authapi = get_config('auth_otp', 'authserviceip');
    $this->accountset = get_config('auth_otp', 'accountset');
    $this->userkey = get_config('auth_otp', 'userkey');
    $this->secret = get_config('auth_otp', 'secret');


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

  public function validate_application($username,$countrycode) {
    global $DB,$CFG;
    $sql = "SELECT u.id, u.username, u.email, u.phone1 FROM {user} u WHERE u.username= ? AND u.confirmed = 1 AND u.auth = 'otp'";
    $username= $countrycode.$username;
    $validusers = $DB->get_record_sql($sql, [$username]);
    $phonenumber = "%2B".$countrycode.$validusers->phone1;
    $phonelength = strlen($phonenumber);
    $appdetails = new stdClass();
	  if (empty($validusers)) {
      $appdetails->username = $username;
      $desc=get_string('notvalidapplicant', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'warning');
		  return 1;
	  } else if (empty($validusers->phone1)) {
		  $appdetails->username = $username;
      $appdetails->phonenumber = $validusers->phone1;
      $desc=get_string('notvalidphone', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'warning');
		  return 2;
	  } else {
     
      $curl = curl_init();
      $curloptions = $this->get_curl_options($this->apiurl, $phonenumber, $this->token);
      curl_setopt_array($curl, $curloptions);

      $appdetails->username = $validusers->username;
      $appdetails->phonenumber = $phonenumber;
      $response = curl_exec($curl);
   
      $result = json_decode($response);

      if(empty($result)) {
        return 0;
      } else {
          if($result->statusReason != 'OK') {
            $desc = get_string('errorcodefromservice', 'auth_otp', $appdetails, $result->errorDetails);
            $this->local_logs('otp', 'Server', 1, $desc, 'Error');
            curl_close($curl);
            return 0;
          }
      }
       
      $this->update_otp($otp, $phonenumber, $username, $validusers->id,$result->vToken);
      $appdetails->otp = $otp;
      $desc = get_string('otpsendtomobile', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'Success');
      return 3;
		}
    exit;
  }

  public function confirm_account($username,$otp,$vtoken){

    global $DB, $CFG;

      $curl = curl_init();
      $curloptions = $this->auth_user_account($otp,$vtoken);
      curl_setopt_array($curl, $curloptions);
      $response = curl_exec($curl);
   
     
    return $response;

  }

  public function set_account($uid,$userid){

    global $DB, $CFG;

      $curl = curl_init();
      $curloptions = $this->set_user_account($uid,$userid);
      curl_setopt_array($curl, $curloptions);
      $response = curl_exec($curl);
     
      return $response;

  }

	public function validate_otp($username, $otp,$countrycode) {
		global $DB, $CFG;

    $username= $countrycode.$username;

    $sql = "SELECT u.id,u.username,u.email FROM {user} u WHERE u.username = ? AND u.auth = 'otp' ";
    $validusers = $DB->get_record_sql($sql, [$username]);

    $sql1 = "SELECT * FROM {local_otp} op WHERE op.userid = ? ORDER BY id DESC LIMIT 1 ";

    $otptoken = $DB->get_record_sql($sql1, [$validusers->id]);

    $validinfo= $this->confirm_account($username,$otp,$otptoken->vtoken);
    
    $validinfo=json_decode ($validinfo);

    $appdetails = new stdClass();
  
   if($validinfo->isActive){

      $trystatus = $otptoken->trystatus;
       $otpdetails = new stdClass();
       $otpdetails->id = $otptoken->id;
       $otpdetails->uid= $validinfo->UID;
       $otpdetails->trystatus = ++$trystatus;
       $otpdetails->uid= $validinfo->UID;
    
       $DB->update_record('local_otp', $otpdetails);
    
       $appdetails->username = $username;
       $appdetails->otp = $otp;
       $appdetails->trycount = $otpdetails->trystatus;
       $desc=get_string('validotpentered', 'auth_otp', $appdetails);
       $this->local_logs('otp', 'User', 1, $desc, 'Success');
       return 1;

    } else {
        $appdetails->username = $username;
        $appdetails->otp = $otp;
       $desc = get_string('otpnotvalid', 'auth_otp', $appdetails);
       $this->local_logs('otp', 'User', 1, $desc, 'warning');
        return 3;
      }


		// if (!empty($validinfo)) {
	  //   if ($validinfo->trystatus > 3) {
		//     $appdetails->username = $username;
		// 	  $appdetails->otp = $otp;
		// 	  $desc=get_string('otpabovethree', 'auth_otp', $appdetails);
		// 	  $this->local_logs('otp', 'User', 1, $desc, 'moreotp');
	  //     return 2;
		//   } else if ($validinfo->inuse == 1) {
		//     $appdetails->username=$username;
		// 	  $appdetails->otp = $otp;
		// 	  $desc = get_string('incorrectotp', 'auth_otp', $appdetails);
		// 	  $this->local_logs('otp', 'User', 1, $desc, 'warning');
    //     return 4;
    //   } else {
		// 		$trystatus = $validinfo->trystatus;
		// 		$otpdetails = new stdClass();
		// 		$otpdetails->id = $validinfo->id;
		// 		$otpdetails->trystatus = ++$trystatus;
		// 		$DB->update_record('local_otp', $otpdetails);

		// 		$appdetails->username = $username;
		// 		$appdetails->otp = $otp;
		// 		$appdetails->trycount = $otpdetails->trystatus;
		// 		$desc=get_string('validotpentered', 'auth_otp', $appdetails);
		// 		$this->local_logs('otp', 'User', 1, $desc, 'Success');
		// 		return 1;
    //   }
    // } else {

    //   $sql = "SELECT * FROM {local_otp} op WHERE op.username = ? ORDER BY id DESC LIMIT 1 ";
    //   $validinfo = $DB->get_record_sql($sql, [$username]);
    //   $trystatus = $validinfo->trystatus;
    //   $otpdetails = new stdClass();
    //   $otpdetails->id = $validinfo->id;
    //   $otpdetails->trystatus = ++$trystatus;
    //   $DB->update_record('local_otp', $otpdetails);
    //   if($trystatus > 3){
    //     $appdetails->username = $username;
    //     $appdetails->otp = $otp;
    //     $desc=get_string('otpabovethree', 'auth_otp', $appdetails);
    //     $this->local_logs('otp', 'User', 1, $desc, 'warning');
    //     return 2;
    //   } else {
    //     $appdetails->username = $username;
    //     $appdetails->otp = $otp;
    //     $desc = get_string('otpnotvalid', 'auth_otp', $appdetails);
    //     $this->local_logs('otp', 'User', 1, $desc, 'warning');
    //     return 3;
    //   }
    // }
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
    } else if (empty($validusers->phone1)) {
     
      $appdetails->phonenumber = $validusers->phone1;
      $desc = get_string('notvalidphone', 'auth_otp', $appdetails);
      $this->local_logs('otp', 'User', 1, $desc, 'warning');
      return 2;
    } else {
      
      $curloptions = $this->get_curl_options($this->apiurl, $phonenumber, $this->token);
      $curl = curl_init();
      curl_setopt_array($curl, $curloptions);

      $appdetails->username = $validusers->username;
      $appdetails->phonenumber = $phonenumber;
      $response = curl_exec($curl);

    //$result = json_decode($response);

       if ($response->statusReason == 'OK') {

             $appdetails->otp = $otp;
            $desc = get_string('otpsendtomobile', 'auth_otp', $appdetails);
            $this->local_logs('otp', 'User', 1, $desc, 'Success');
            return 3;

        }else {
          $desc = get_string('errorcodefromservice', 'auth_otp', $appdetails, $response->errorDetails);
          $this->local_logs('otp', 'Server', 1, $desc, 'Error');
          trigger_error(curl_error($curl));
          curl_close($curl);
          return 0;
        }
      

      $this->update_otp($otp, $phonenumber, $username, $validusers->id,$response->vToken);

      // $appdetails->otp = $otp;
      // $desc = get_string('otpsendtomobile', 'auth_otp', $appdetails);
      // $this->local_logs('otp', 'User', 1, $desc, 'Success');
      // return 3;
    }
    exit;
  }

  private function get_curl_options($apiurl, $mobile, $token) {
    // $postfields = [
    //   'phoneNumber' => $mobile,
    //   'apikey' => $token,
    // ];
    $apiurl= $apiurl."&apikey=".$token."&phoneNumber=".$mobile;
   // echo $apiurl;
    return [
      CURLOPT_URL => $apiurl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache",
      ),
    ];
  }

  private function auth_user_account($otp,$vtoken) {
   
    $apikey= $this->token;
    $authapi= $this->authapi;
    $hosturl= $authapi."apikey=".$apikey."&code=".$otp."&vToken=".$vtoken;
   // echo $hosturl;
    return [
      CURLOPT_URL => $hosturl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache",
      ),
    ];
  }

  private function set_user_account($uid,$userid) {
   global $CFG;
    $authapi= $this->accountset;
    $apikey= $this->token;
    $userkey= $this->userkey;
    $secret= $this->secret;
    $timestamp= time();
     $hostname = $CFG->wwwroot;
     $firstlogin = gmdate("Y-m-d\TH:i:s\Z", $timestamp);
     $lastlogin = gmdate("Y-m-d\TH:i:s\Z", $timestamp);
     $data =' {"bc_accessApplications":["{\"hostname\":\"'.$hostname.'\",\"firstLogin\":\"'.$firstlogin.'\",\"lastLogin\":\"'.$lastlogin.'\"}"]}';

    $hosturl= $authapi."apikey=".$apikey."&data=".$data."&UID=".$uid."&userKey=".$userkey."&secret=".$secret;
    return [
      CURLOPT_URL => $hosturl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache",
      ),
    ];
  }
  private function update_otp($otp, $phonenumber, $username, $userid,$vtoken) {
    global $DB;
    $otpdetails = new stdClass();
 //   $otpdetails->otpcode = $otp;
    $otpdetails->phonenumber = $phonenumber;
    $otpdetails->username = $username;
    $otpdetails->userid = $userid;
    $otpdetails->timecreated = time();
    $otpdetails->vtoken = $vtoken;

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
