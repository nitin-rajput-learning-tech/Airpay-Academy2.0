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

require_login();

$url = new moodle_url('/payment/gateway/airpay/process3.php', []);
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

//THIS IS ADDITIONAL VALIDATION, YOU MAY USE IT.
//$SYSTEM_AMOUNT is amount you will fetch from your database/system against $transactionid
//if( $amount != $SYSTEM_AMOUNT){
// Reponse has been compromised. So treat this transaction as failed.
//$error_msg .= '<tr><td>Amount mismatch in the system.</td></tr>';
//exit();
//}

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
	$enrolplugin = enrol_get_plugin('manual');
	$role = $DB->get_record('role', array('shortname' => 'student'), '*', MUST_EXIST);
	if($order->paymentarea == 'cart'){
		//Checking cart history for payment and enrolling user.
		$cart_history_items = $DB->get_records('local_biz_cart_history',['identifier' => $order->itemid, 'userid' => $order->userid]);
	
		foreach($cart_history_items as $cartitem){

			// Get enrol instance for course.	
    		$instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'courseid'=>$cartitem->itemid, 'enrol'=> 'manual'));

    		// Enrol user.
    		$enrolplugin->enrol_user($instance, $cartitem->userid, $role->id);
			$cartitem->paymentstatus = 2;
			$cartitem->timemodified = time();
			$DB->update_record('local_biz_cart_history',$cartitem);
		}
		$redirecturl = new moodle_url('/local/biz_cart/checkout.php', array('success' => TRUE, 'identifier' => $order->itemid));
		redirect($redirecturl);
	}else if ($order->paymentarea == 'fee'){
			// Get enrol instance for course.	
			$enrolplugin = enrol_get_plugin('fee');
    		$instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'id'=>$order->itemid));
    		// Enrol user.
    		$enrolplugin->enrol_user($instance, $order->userid, $role->id);
			echo $OUTPUT->render_from_template('local_biz_cart/checkout_success', $request);
			// $redirecturl = new moodle_url('/my/dashboard.php', []);
			// redirect($redirecturl);
			
	}

	// echo '<table><font color="green"><tr><td class="tdsuccess"><b>SUCCESS TRANSACTION</b></td></tr></font></table>
	// 	<table>
	// 		<tr><td><b>Variable Name</b></td><td><b>Value</b></td></tr>
	// 		<tr><td>TRANSACTIONID:</td><td> '.$transactionid.'</td></tr>
	// 		<tr><td>APTRANSACTIONID:</td><td> '.$aptransactionid.'</td></tr>
	// 		<tr><td>AMOUNT:</td><td> '.$amount.'</td></tr>
	// 		<tr><td>TRANSACTIONSTATUS:</td><td> '.$transactionstatus.'</td></tr>
	// 		<tr><td>MESSAGE:</td><td> '.$message.'</td></tr>
	// 		<tr><td>CUSTOMVAR:</td><td> '.$customvar.'</td></tr>
	// 	</table>';
}else{
if($error_msg){
	// echo '<table><font color="red"><b>ERROR:</b> '.$error_msg.'</font></table>';
	// echo '<table>
	// 		<tr><td><b>Variable Name</b></td><td><b> Value</b></td></tr>
	// 		<tr><td>TRANSACTIONID:</td><td> '.$transactionid.'</td></tr>
	// 		<tr><td>APTRANSACTIONID:</td><td> '.$aptransactionid.'</td></tr>
	// 		<tr><td>AMOUNT:</td><td> '.$amount.'</td></tr>
	// 		<tr><td>TRANSACTIONSTATUS:</td><td> '.$transactionstatus.'</td></tr>
	// 		<tr><td>CUSTOMVAR:</td><td> '.$customvar.'</td></tr>
	// 	</table>';
	$order->status = 1;
	$DB->update_record('paygw_airpay', $order);
	$error = new stdClass();
	$error->error = $error_msg;
	$error->airpay_id = $order->id;
	$error->timecreated = time();
	$DB->insert_record('paygw_airpay_errorlog', $error);
	echo $OUTPUT->render_from_template('local_biz_cart/checkout_failed', $request);

}

		// Process Failed Transaction
		// echo '<table><font color="red"><tr><td class="tdfail"><b>FAILED TRANSACTION</b></td></tr></font></table>
		// <table>
		// 	<tr><td><b>Variable Name</b></td><td><b>Value</b></td></tr>
		// 	<tr><td>TRANSACTIONID:</td><td> '.$transactionid.'</td></tr>
		// 	<tr><td>APTRANSACTIONID:</td><td> '.$aptransactionid.'</td></tr>
		// 	<tr><td>AMOUNT:</td><td> '.$amount.'</td></tr>
		// 	<tr><td>TRANSACTIONSTATUS:</td><td> '.$transactionstatus.'</td></tr>
		// 	<tr><td>MESSAGE:</td><td> '.$message.'</td></tr>
		// 	<tr><td>CUSTOMVAR:</td><td> '.$customvar.'</td></tr>
		// </table>';
}
echo $OUTPUT->footer();
