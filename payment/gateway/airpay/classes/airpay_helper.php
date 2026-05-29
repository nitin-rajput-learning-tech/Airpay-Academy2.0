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
 * Settings for the airpay payment gateway
 *
 * @package    paygw_airpay
 * @copyright  2024 Moodle India <support@moodle.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_airpay;

defined('MOODLE_INTERNAL') || die();

use core_payment\helper;

require_once $CFG->dirroot . "/payment/gateway/airpay/classes/checksum.php";
require_once $CFG->libdir . '/filelib.php';

class airpay_helper
{
    /**
     * @var integer Payment is pending
     */

    /**
     * @var string
     */
    private $curl;
    public const ORDER_STATUS_PENDING = 0;
    /**
     * @var integer Payment was received.
     */
    public const ORDER_STATUS_PAID = 1;

    /**
     * Get an unprocessed order record - if one already exists - return it.
     *
     * @param string $component
     * @param string $paymentarea
     * @param integer $itemid
     * @return false|\stdClass
     */
    public static function get_unprocessed_order($component, $paymentarea, $itemid, $cost)
    {
        global $USER, $DB;

        $existingorder = $DB->get_record('paygw_airpay', ['component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'userid' => $USER->id,
            'cost' => $cost,
            'status' => self::ORDER_STATUS_PENDING]);
        if ($existingorder) {
            return $existingorder;
        }
        return false;
    }

    /**
     * Create a new order.
     *
     * @param string $component
     * @param string $paymentarea
     * @param integer $itemid
     * @param string $accountid
     * @return \stdClass
     */
    public static function create_order($component, $paymentarea, $itemid, $accountid, $cost)
    {
        global $USER, $DB;
        // Create a new order record.
        $neworder = new \stdClass();
        $neworder->component = $component;
        $neworder->paymentarea = $paymentarea;
        $neworder->itemid = $itemid;
        $neworder->userid = $USER->id;
        $neworder->ap_orderid = time() . '_' . $USER->id;
        $neworder->accountid = $accountid;
        $neworder->cost = $cost;
        $neworder->status = self::ORDER_STATUS_PENDING;
        $neworder->timecreated = time();
        $neworder->modified = $neworder->timecreated;

        $id = $DB->insert_record('paygw_airpay', $neworder);
        $neworder->id = $id;
	    $role = $DB->get_record('role', array('shortname' => 'employee'), '*', MUST_EXIST);
        if($neworder->paymentarea == 'cart'){
            $cart_history_items = $DB->get_records('local_biz_cart_history',['identifier' => $neworder->itemid, 'userid' => $neworder->userid]);
		    foreach($cart_history_items as $cartitem){
                $user = \core_user::get_user($cartitem->userid);
                $course = new \stdClass();
			    $course->courseid = $cartitem->itemid;
			    $course->coursename = $cartitem->itemname;
			    $course->userid = $cartitem->userid;
			    $course->username = $user->firstname . ' ' . $user->lastname;
			    $course->transactionid = 0;
			    $course->ap_orderid = $neworder->ap_orderid;
			    $course->amount = $cartitem->price;
			    $course->status = 1;
			    $course->timecreated = time();
			    $id = $DB->insert_record('paygw_course_enrolmentlog', $course);
                if($id){
                    $error = new \stdClass();
	                $error->error = 'Order id -"'.$neworder->ap_orderid.'" created for course "('.$course->courseid.')" by user with userid "('.$course->userid.')".';
	                $error->airpay_id = $neworder->ap_orderid;
                    $error->courseid = $course->courseid;
                    $error->userid = $course->userid;
                    $error->order_state = 'Order Created';
                    $error->paymentarea = 'cart';
	                $error->timecreated = time();
	                $DB->insert_record('paygw_airpay_errorlog', $error);
                }
            } 
        }else{
            $instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'id'=>$neworder->itemid));
            $user = \core_user::get_user($neworder->userid);
			$coursename = $DB->get_field('course', 'fullname' , ['id' => $instance->courseid]);
            $course = new \stdClass();
			$course->courseid = $instance->courseid;
			$course->coursename = $coursename;
			$course->userid = $neworder->userid;
			$course->username = $user->firstname . ' ' . $user->lastname;
			$course->transactionid = 0;
			$course->ap_orderid = $neworder->ap_orderid;
			$course->amount = $instance->cost;
			$course->status = 1;
			$course->timecreated = time();
			$id = $DB->insert_record('paygw_course_enrolmentlog', $course);
            if($id){
                $error = new \stdClass();
	            $error->error = 'Order id -"'.$neworder->ap_orderid.'" created for course "('.$course->courseid.')" by user with userid "('.$course->userid.')".';
	            $error->airpay_id = $neworder->ap_orderid;
                $error->courseid = $course->courseid;
                $error->userid = $course->userid;
                $error->order_state = 'Order Created';
                $error->paymentarea = 'fee';
	            $error->timecreated = time();
	            $DB->insert_record('paygw_airpay_errorlog', $error);
            }
        }    
        return $neworder;
    }

    public static function update_order($order)
    {
        global $DB;
        $DB->update_record('paygw_airpay', $order);
    }

    /**
     * Check airpay to see if this order has been paid.
     */
    public static function check_payment($config, $order)
    {
        // Moodle sets this to &nbsp; by default easysdk expects '&' see: MDL-71368.
        /*ini_set('arg_separator.output', '&');

        Factory::setOptions(self::options($config));

        try {
        $result = Factory::payment()->common()->query(self::get_orderid($order));
        $responsechecker = new ResponseChecker();
        if ($responsechecker->success($result)) {
        if (!empty($result->tradeStatus) &&
        ($result->tradeStatus === 'TRADE_SUCCESS' || $result->tradeStatus === 'TRADE_FINISHED')) {
        return true;
        } else {
        debugging("Call success, but invalid tradeStatus");
        }
        }
        } catch (Exception $e) {
        debugging("Call failed, " . $e->getMessage());
        }*/
        return false;
    }

    public static function process_payment($order)
    {
        global $DB;
        $payable = helper::get_payable($order->component, $order->paymentarea, $order->itemid);
        $cost = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), helper::get_gateway_surcharge('airpay'));
        $message = '';
        try {
            $paymentid = helper::save_payment($payable->get_account_id(), $order->component, $order->paymentarea,
                $order->itemid, (int) $order->userid, $cost, $payable->get_currency(), 'airpay');

            // Store Alipay extra information.
            $order->paymentid = $paymentid;
            $order->timemodified = time();
            $order->status = self::ORDER_STATUS_PAID;

            $DB->update_record('paygw_airpay', $order);

            helper::deliver_order($order->component, $order->paymentarea, $order->itemid, $paymentid, (int) $order->userid);
            $success = true;
        } catch (\Exception $e) {
            debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $message = get_string('internalerror', 'paygw_airpay');
            $success = false;
        }

        return [
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Generate a unique order id based on timecreated and order->id field.
     *
     * @param \stdClass $order - the order record from paygw_airpay table.
     * @return string
     */
    protected static function get_orderid($order)
    {
        return $order->timecreated . '_' . $order->id;
    }

    /**
     * Return the Airpay payment-form URL for the configured environment.
     *
     * NOTE: Airpay's documented integration uses a single payment endpoint —
     * sandbox vs live is determined by the merchant credentials (`mercid`),
     * not by the URL. The `environment` setting therefore documents intent
     * but does not alter the request URL today. Confirm with Airpay support
     * before introducing a separate sandbox host.
     *
     * @param \stdClass $config Gateway config — `environment` is either
     *                          'sandbox' or 'live'
     * @return string Payment form URL
     */
    public static function get_url($config)
    {
        return "https://payments.airpay.co.in/pay/index.php";
    }
}
