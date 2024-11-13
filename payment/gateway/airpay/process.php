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
 * TODO describe file process3
 *
 * @package    paygw_airpay
 * @copyright  2024 Moodle India <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

//require_login();
use local_biz_cart\biz_cart;
$url = new moodle_url('/payment/gateway/airpay/process.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
date_default_timezone_set('Asia/Kolkata');
$PAGE->set_heading($SITE->fullname);
echo $OUTPUT->header();
global $DB;
// This is landing page where you will receive response from airpay. 
// The name of the page should be as per you have configured in airpay system
// All columns are mandatory

$transactionid = trim($_POST['TRANSACTIONID']);
$aptransactionid  = trim($_POST['APTRANSACTIONID']);
$amount  = trim($_POST['AMOUNT']);
$transactionstatus  = trim($_POST['TRANSACTIONSTATUS']);
$message  = trim($_POST['MESSAGE']);
$ap_SecureHash = trim($_POST['ap_SecureHash']);
$chmod = "";
if (isset($_POST['CHMOD'])){
	$chmod = trim($_POST['CHMOD']);
}
if (isset($_POST['CUSTOMVAR'])){
	$customvar = trim($_POST['CUSTOMVAR']);	
}
else{
	$customvar = "";
}

$error_msg = '';
if(empty($transactionid) || empty($aptransactionid) || empty($amount) || empty($transactionstatus) || empty($ap_SecureHash)){
// Reponse has been compromised. So treat this transaction as failed.
	if(empty($transactionid)){
		 $error_msg = 'TRANSACTIONID '; 
	} 
	if(empty($aptransactionid)){
		 $error_msg .=  ' APTRANSACTIONID'; 
	}
	if(empty($amount)){
		 $error_msg .=  ' AMOUNT'; 
	}
	if(empty($transactionstatus)){
		 $error_msg .=  ' TRANSACTIONSTATUS'; 
	}
	if(empty($ap_SecureHash)){
		 $error_msg .=  ' ap_SecureHash'; 
	}
	$error_msg .= '<tr><td>Variable(s) '. $error_msg.' is/are empty.</td></tr>';
}

// Generating Secure Hash
// $mercid = 	Merchant Id, $username = username
// You will find above two keys on the settings page, which we have defined here in config.php
$username = get_config('paygw_airpay','username');
$mercid = get_config('paygw_airpay','mercid');
$Hash_data = $transactionid.':'.$aptransactionid.':'.$amount.':'.$transactionstatus.':'.$message.':'.$mercid.':'.$username;
if($chmod == "upi"){
	$Hash_data = $Hash_data.':'.trim($_POST["CUSTOMERVPA"]);
}
$merchant_secure_hash = sprintf("%u", crc32 ($Hash_data));

//comparing Secure Hash with Hash sent by Airpay
if($ap_SecureHash != $merchant_secure_hash){
	// Reponse has been compromised. So treat this transaction as failed.
	$error_msg .= '<tr><td><font color="red">Secure Hash mismatch.</font></td></tr>';
}

//if($error_msg)
$order = $DB->get_record('paygw_airpay', ['ap_orderid' => $transactionid]);
if($transactionstatus == 200){
	// Process Successfull transaction
	// Updating order after successfull transaction.
	$order->paymentid = $aptransactionid;
	$order->cost = $amount;
	$order->status = 2;
	$DB->update_record('paygw_airpay', $order);
	// get enrol plugin.
	$enrolplugin = enrol_get_plugin('fee');
	$role = $DB->get_record('role', array('shortname' => 'employee'), '*', MUST_EXIST);
	if($order->paymentarea == 'cart'){
		//Checking cart history for payment and enrolling user.
		$cart_history_items = $DB->get_records('local_biz_cart_history',['identifier' => $order->itemid, 'userid' => $order->userid]);
	
		foreach($cart_history_items as $cartitem){

			// Get enrol instance for course.	
    		$instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'courseid'=>$cartitem->itemid, 'enrol'=> 'fee'));

    		// Enrol user.
    		$enrolplugin->enrol_user($instance, $cartitem->userid, $role->id);
			$cartitem->paymentstatus = 2;
			$cartitem->timemodified = time();
			$DB->update_record('local_biz_cart_history',$cartitem);
			// Course Enrol Notification.
			$type = 'course_enrol';
			$notification = new \local_courses\notification();
			$user = core_user::get_user($cartitem->userid);
        	$course = $DB->get_record('course', array('id' => $cartitem->itemid));
        	$notificationdata = $notification->get_existing_notification($course, $type);
        	if ($notificationdata){
          		$notification->send_course_email($course, $user, $type, $notificationdata);
			}
			$courserecord = $DB->get_record('paygw_course_enrolmentlog',['courseid' => $cartitem->itemid, 'ap_orderid' => $transactionid, 'userid'=>$user->id]);
			$courserecord->transactionid = $aptransactionid;
			$courserecord->ap_orderid = $transactionid;
			$courserecord->amount = $cartitem->price;
			$courserecord->status = 2;
			$courserecord->timecreated = time();
			$DB->update_record('paygw_course_enrolmentlog', $courserecord);
		}
		$redirecturl = new moodle_url('/local/biz_cart/checkout.php', array('success' => TRUE, 'identifier' => $order->itemid));
		redirect($redirecturl);
	}else if ($order->paymentarea == 'fee'){
			// Get enrol instance for course.	
			$enrolplugin = enrol_get_plugin('fee');
    		$instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'id'=>$order->itemid));
    		//biz_cart::delete_item_from_cart('local_courses', 'option', $instance->courseid, $order->userid);
    		// Enrol user.
    		$enrolplugin->enrol_user($instance, $order->userid, $role->id);
				// Course Enrol Notification.
			$type = 'course_enrol';
			$notification = new \local_courses\notification();
			$user = core_user::get_user($order->userid);
        	$course = $DB->get_record('course', array('id' => $instance->courseid));
        	$notificationdata = $notification->get_existing_notification($course, $type);
        	if ($notificationdata){
          		$notification->send_course_email($course, $user, $type, $notificationdata);
			}
			$coursename = $DB->get_field('course', 'fullname' , ['id' => $instance->courseid]);
			$courserecord = $DB->get_record('paygw_course_enrolmentlog',['courseid' => $instance->courseid, 'ap_orderid' => $transactionid, 'userid'=>$user->id]);
			$courserecord->transactionid = $aptransactionid;
			$courserecord->ap_orderid = $transactionid;
			$courserecord->amount = $instance->cost;
			$courserecord->status = 2;
			$courserecord->timecreated = time();
			$DB->update_record('paygw_course_enrolmentlog', $courserecord);
			echo $OUTPUT->render_from_template('local_biz_cart/checkout_success', $request);		
	}
}else{
	if($error_msg){
		if($order){
			$order->status = 1;
			$DB->update_record('paygw_airpay', $order);
		}
	
	$error = new stdClass();
	$error->error = $error_msg;
	$error->airpay_id = $order->id ? $order->id : -1;
	$error->timecreated = time();
	$DB->insert_record('paygw_airpay_errorlog', $error);
	echo $OUTPUT->render_from_template('local_biz_cart/checkout_failed', $request);

}
}
echo $OUTPUT->footer();
