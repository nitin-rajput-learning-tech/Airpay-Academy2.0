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
 * TODO describe file process
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

// SECURITY 2026-06-02 — read response fields via Moodle's param API. PARAM_RAW
// preserves the EXACT gateway values (any cleaning would alter the hashed inputs
// and break verification). $response is the canonical array we both inspect below
// and hand to airpay_helper::verify_secure_hash().
$response = [
	'TRANSACTIONID'     => optional_param('TRANSACTIONID', '', PARAM_RAW),
	'APTRANSACTIONID'   => optional_param('APTRANSACTIONID', '', PARAM_RAW),
	'AMOUNT'            => optional_param('AMOUNT', '', PARAM_RAW),
	'TRANSACTIONSTATUS' => optional_param('TRANSACTIONSTATUS', '', PARAM_RAW),
	'MESSAGE'           => optional_param('MESSAGE', '', PARAM_RAW),
	'ap_SecureHash'     => optional_param('ap_SecureHash', '', PARAM_RAW),
	'CHMOD'             => optional_param('CHMOD', '', PARAM_RAW),
	'CUSTOMERVPA'       => optional_param('CUSTOMERVPA', '', PARAM_RAW),
	'CUSTOMVAR'         => optional_param('CUSTOMVAR', '', PARAM_RAW),
];
$transactionid     = trim($response['TRANSACTIONID']);
$aptransactionid   = trim($response['APTRANSACTIONID']);
$amount            = trim($response['AMOUNT']);
$transactionstatus = trim($response['TRANSACTIONSTATUS']);
$message           = trim($response['MESSAGE']);
$ap_SecureHash     = trim($response['ap_SecureHash']);
$chmod             = trim($response['CHMOD']);
$customvar         = trim($response['CUSTOMVAR']);

$error_msg = '';
if(empty($transactionid) || empty($aptransactionid) || empty($amount) || empty($transactionstatus) || empty($ap_SecureHash)){
// Reponse has been compromised. So treat this transaction as failed.
	if(empty($transactionid)){
		 $error_msg = 'Payment Failed - TRANSACTIONID is Empty. /n'; 
	} 
	if(empty($aptransactionid)){
		 $error_msg .=  'Payment Failed - APTRANSACTIONID is Empty. /n'; 
	}
	if(empty($amount)){
		 $error_msg .=  'Payment Failed - AMOUNT is Empty. /n'; 
	}
	if(empty($transactionstatus)){
		 $error_msg .=  'Payment Failed - TRANSACTIONSTATUS is Empty. /n'; 
	}
	if(empty($ap_SecureHash)){
		 $error_msg .=  'Payment Failed - ap_SecureHash is Empty. /n'; 
	}
	$error_msg .= '<tr><td>Variable(s) '. $error_msg.' is/are empty.</td></tr>';
}

// Verify the response secure hash via the gateway helper (SECURITY FIX 2026-06-02).
// The previous inline check was COMPUTED but never ENFORCED — the guard below
// (`if ($error_msg)`) was commented out, so a forged POST carrying
// TRANSACTIONSTATUS=200 (with any/blank hash) was enrolled regardless. We now verify
// via airpay_helper::verify_secure_hash() (which fails closed: missing field or missing
// config => false) AND enforce the guard before any enrolment.
if (!\paygw_airpay\airpay_helper::verify_secure_hash($response)) {
	// Response failed integrity verification — treat as a failed/compromised transaction.
	$error_msg .= '<tr><td><font color="red">Secure Hash verification failed.</font></td></tr>';
}

$order = $DB->get_record('paygw_airpay', ['ap_orderid' => $transactionid]);

// SECURITY FIX 2026-06-02 — ENFORCE the verification guard before fulfilment.
// Enrol ONLY when: the secure-hash + required-field check passed (empty $error_msg),
// AND Airpay reports success (200), AND a matching pending order exists. Previously
// this read `if($transactionstatus == 200)` with the guard commented out, so a forged
// callback granted free, unpaid enrolment.
// HARDENING TODO (robust control, requires Airpay sandbox): add a server-side Order
// Confirmation (Verify API) call here and require it to independently confirm
// status==200 for this orderid/amount before enrolling — the CRC32 above carries no
// secret and is forgeable by design. See docs.airpay.co.in/v4/payments/order-confirmation/.
if (empty($error_msg) && (int) $transactionstatus === 200 && $order) {
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
			
			// Update course record after payment.
			$courserecord = $DB->get_record('paygw_course_enrolmentlog',['courseid' => $cartitem->itemid, 'ap_orderid' => $transactionid, 'userid'=>$user->id]);
			$courserecord->transactionid = $aptransactionid;
			$courserecord->ap_orderid = $transactionid;
			$courserecord->amount = $cartitem->price;
			$courserecord->status = 2;
			$courserecord->timecreated = time();
			$course_payment = $DB->update_record('paygw_course_enrolmentlog', $courserecord);
			
			// Update error/success log for the course after payment.
			if(!empty($course_payment)){
                $error = new \stdClass();
	            $error->error = 'Order id -"'.$transactionid.'" for course "('.$cartitem->itemid.')" by user with userid "('.$user->id.')" is successfull.';
	            $error->airpay_id = $transactionid;
				$error->courseid = $cartitem->itemid;
				$error->order_state = 'Order Successfull';
				$error->paymentarea = 'cart';
                $error->userid = $user->id;
	            $error->timecreated = time();
	            $DB->insert_record('paygw_airpay_errorlog', $error);
            }
		}

		// Redirect after payment.
		$redirecturl = new moodle_url('/local/biz_cart/checkout.php', array('success' => TRUE, 'identifier' => $order->itemid));
		redirect($redirecturl);
	}else if ($order->paymentarea == 'fee'){
			
			// Get enrol instance for course.	
			$enrolplugin = enrol_get_plugin('fee');
    		$instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'id'=>$order->itemid));
    		biz_cart::delete_item_from_cart('local_courses', 'option', $instance->courseid, $order->userid);
    		
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
			
			// Update course record after payment.
			$courserecord = $DB->get_record('paygw_course_enrolmentlog',['courseid' => $instance->courseid, 'ap_orderid' => $transactionid, 'userid'=>$user->id]);
			$courserecord->transactionid = $aptransactionid;
			$courserecord->ap_orderid = $transactionid;
			$courserecord->amount = $instance->cost;
			$courserecord->status = 2;
			$courserecord->timecreated = time();
			$course_payment = $DB->update_record('paygw_course_enrolmentlog', $courserecord);
			
			// Update error/success log for the course after payment.
			if(!empty($course_payment)){
                $error = new \stdClass();
	            $error->error = 'Order id -"'.$transactionid.'" for course "('.$instance->courseid.')" by user with userid "('.$user->id.')" is successfull.';
	            $error->airpay_id = $transactionid;
				$error->courseid = $instance->courseid;
				$error->order_state = 'Order Successfull';
				$error->paymentarea = 'fee';
                $error->userid = $user->id;
	            $error->timecreated = time();
	            $DB->insert_record('paygw_airpay_errorlog', $error);
            }
			echo $OUTPUT->render_from_template('local_biz_cart/checkout_success', []);
	}
}else{
	// Update payment status after failed payment.
	if($order){
		$order->status = 1;
		$DB->update_record('paygw_airpay', $order);
	}

	// If error log then update the error log.
	if($error_msg){
		$error = new stdClass();
		$error->error = $error_msg;
		$error->airpay_id = $order ? $order->id : -1;
		$error->timecreated = time();
		$DB->insert_record('paygw_airpay_errorlog', $error);
	}

	// Redirection after failed payment.
	echo $OUTPUT->render_from_template('local_biz_cart/checkout_failed', []);

}
echo $OUTPUT->footer();
