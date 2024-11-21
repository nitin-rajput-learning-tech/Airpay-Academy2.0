<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings for the airpay payment gateway
 *
 * @package    paygw_airpay
 * @copyright  2024 Moodle India <support@moodle.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core_payment\helper;
use paygw_airpay\airpay_helper;
use local_biz_cart\event\payment_added;
require_once __DIR__ . '/../../../config.php';
require_once $CFG->libdir . '/filelib.php';
global $DB, $USER, $PAGE, $CFG, $OUTPUT;
$component = required_param('component', PARAM_ALPHANUMEXT);
$paymentarea = required_param('paymentarea', PARAM_ALPHANUMEXT);
$itemid = required_param('itemid', PARAM_INT);
$description = required_param('description', PARAM_TEXT);
$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'airpay');
$payable = helper::get_payable($component, $paymentarea, $itemid);
$surcharge = helper::get_gateway_surcharge('airpay');
$cost = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
$order = airpay_helper::get_unprocessed_order($component, $paymentarea, $itemid, $cost);
if (empty($order)) {
    // Create a new order.
    $order = airpay_helper::create_order($component, $paymentarea, $itemid, $payable->get_account_id(), $cost);
}

$username = $config->username;
$password = $config->password;
$secret = $config->secret;
$mercid = $config->mercid;
if(!empty($config->merdom)) {
    $merdom = $config->merdom;
}
$buyerEmail = trim($USER->email);
$buyerPhone = trim($USER->phone1);
$buyerFirstName = trim($USER->firstname);
$buyerLastName = trim($USER->lastname);
$buyerAddress = ''; /* trim($_POST['buyerAddress']) */;
$amount = $cost;
$buyerCity = $USER->city ? trim($USER->city) : '';
$buyerState = $USER->state ? trim($USER->state) : '';
$buyerPinCode = $USER->pincode ? trim($USER->pincode) : '';
$buyerCountry = $USER->country ? trim($USER->country) : '';
$orderid = trim($order->ap_orderid); //Your System Generated Order ID
// $hiddenmod = trim($_POST['directindexvar']);
$currency = '356';
$isocurrency = 'INR';
$classchecksum = new \checksum;
$alldata = $buyerEmail . $buyerFirstName . $buyerLastName . $buyerAddress . $buyerCity . $buyerState . $buyerCountry . $amount . $orderid;
$privatekey = $classchecksum->encrypt($username . ":|:" . $password, $secret);
$keySha256 = $classchecksum->encryptSha256($username . "~:~" . $password);
$checksum = $classchecksum->calculateChecksumSha256($alldata . date('Y-m-d'), $keySha256);
$hiddenmod = "";
if(!empty($order)){
    if($order->paymentarea == 'cart'){
		$cart_history_items = $DB->get_records('local_biz_cart_history',['identifier' => $order->itemid, 'userid' => $order->userid]);
		foreach($cart_history_items as $cartitem){
                $error = new \stdClass();
	            $error->error = 'Payment request sent to airpay for order id -"'.$order->ap_orderid.'" for course "('.$cartitem->itemid.')" by user with userid "('.$order->userid.')".';
	            $error->airpay_id = $order->ap_orderid;
				$error->courseid = $cartitem->itemid;
                $error->order_state = 'Order Initiated';
                $error->paymentarea = 'cart';
                $error->userid = $order->userid;
	            $error->timecreated = time();
	            $DB->insert_record('paygw_airpay_errorlog', $error);
            }
		}else if ($order->paymentarea == 'fee'){
                $context = context_system::instance();
	            $role = $DB->get_record('role', array('shortname' => 'employee'), '*', MUST_EXIST);
                $enrolplugin = enrol_get_plugin('fee');
    		    $instance = $DB->get_record('enrol', array('roleid'=>$role->id, 'id'=>$order->itemid));
                $error = new \stdClass();
	            $error->error = 'Payment request sent to airpay for order id -"'. $order->ap_orderid.'" for course "('.$instance->courseid.')" by user with userid "('.$order->userid.')".';
	            $error->airpay_id = $order->ap_orderid;
				$error->courseid = $instance->courseid;
                $error->order_state = 'Order Initiated';
                $error->paymentarea = 'fee';
                $error->userid = $order->userid;
	            $error->timecreated = time();
	            $DB->insert_record('paygw_airpay_errorlog', $error);
                $event = payment_added::create([
                        'context' => $context,
                        'userid' => $order->userid,
                        'courseid' => $instance->courseid,
                        'relateduserid' => $order->userid,
                        'objectid' => $order->id,
                        'other' => [
                            'identifier' => $order->id,
                            'itemid' => $instance->courseid,
                            'component' => $component,
                        ],
                    ]);

                    $event->trigger();
	}
}
// $request = [
//     "privatekey" => $privatekey,
//     "mercid" => $mercid,
//     "orderid" => $order->ap_orderid,
//     "currency" => $currency,
//     "isocurrency" => $isocurrency,
//     "chmod" => $hiddenmod,
//     "buyerEmail" => trim($USER->email),
//     "buyerPhone" => trim($USER->phone1),
//     "buyerFirstName" => trim($USER->firstname),
//     "buyerLastName" => trim($USER->lastname),
//     "buyerAddress" => $buyerAddress,
//     "amount" => $cost,
//     "buyerCity" => $buyerCity,
//     "buyerPinCode" => $buyerPinCode,
//     "buyerCountry" => $buyerCountry,
//     "checksum" => $checksum,
//     "buyerState" => $buyerState,
//  ];
//  print_r($request);exit;
//  echo $OUTPUT->render_from_template('paygw_airpay/payment_form', $request);
echo '
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Airpay</title>
</head>
<body onload="javascript:submitForm()">
<center>
<table width="500px;">
    <tr>
        <td align="center" valign="middle">Do Not Refresh or Press Back <br/> Redirecting to Airpay</td>
    </tr>
    <tr>
        <td align="center" valign="middle">
            <form action="https://payments.airpay.co.in/pay/index.php" method="post" id = "auto_submit_form">
                <input type="hidden" name="privatekey" value="' . $privatekey . '">
                <input type="hidden" name="mercid" value="' . $mercid . '">
                <input type="hidden" name="orderid" value="' . $orderid . '">
                <input type="hidden" name="currency" value="' . $currency . '">
                <input type="hidden" name="isocurrency" value="' . $isocurrency . '">
                <input type="hidden" name="chmod" value="' . $hiddenmod . '">
                <input type="hidden" name="buyerEmail" value="' . $buyerEmail . '">
                <input type="hidden" name="buyerPhone" value="' . $buyerPhone . '">
                <input type="hidden" name="buyerFirstName" value="' . $buyerFirstName . '">
                <input type="hidden" name="buyerLastName" value="' . $buyerLastName . '">
                <input type="hidden" name="buyerAddress" value="' . $buyerAddress . '">
                <input type="hidden" name="amount" value="' . $amount . '">
                <input type="hidden" name="buyerCity" value="' . $buyerCity . '">
                <input type="hidden" name="buyerPinCode" value="' . $buyerPinCode . '">
                <input type="hidden" name="buyerCountry" value="' . $buyerCountry . '">
                <input type="hidden" name="checksum" value="' . $checksum . '">
                <input type="hidden" name="buyerState" value="' . $buyerState . '">';
                if(!empty($merdom)) {
                    echo '<input type="hidden" name="mer_dom" value="'. base64_encode($merdom) .'">';
                }

echo '</form>
        </td>

    </tr>

</table>

</center>
</body>
';
echo '<script type="text/javascript">
function submitForm(){
            var form = document.forms[0];
            form.submit();
        }
</script>';
