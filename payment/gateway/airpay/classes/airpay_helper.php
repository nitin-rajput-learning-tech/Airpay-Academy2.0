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

use core_payment\helper;

require $CFG->dirroot . "/payment/gateway/airpay/classes/checksum.php";
defined('MOODLE_INTERNAL') || die();

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

    protected static function get_url($config)
    {
        if ($config->environment === 'sandbox') {
            return "https://payments.airpay.co.in/pay/index.php";
        } else {
            return "https://payments.airpay.co.in/pay/index.php";
        }
    }
}
