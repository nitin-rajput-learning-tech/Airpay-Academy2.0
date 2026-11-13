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

$string['amountmismatch'] = 'The amount you attempted to pay does not match the required fee. Your account has not been debited.';
$string['authorising'] = 'Authorising the payment. Please wait...';
$string['brandname'] = 'Brand name';
$string['brandname_help'] = 'An optional label that overrides the business name for the airpay account on the airpay site.';
$string['cannotfetchorderdatails'] = 'Could not fetch payment details from airpay. Your account has not been debited.';
$string['environment'] = 'Environment';
$string['environment_help'] = 'You can set this to Sandbox if you are using sandbox accounts (for testing purpose only).';
$string['gatewaydescription'] = 'airpay is an authorised payment gateway provider for processing credit card transactions.';
$string['gatewayname'] = 'Airpay';
$string['internalerror'] = 'An internal error has occurred. Please contact us.';
$string['live'] = 'Live';
$string['paymentnotcleared'] = 'payment not cleared by airpay.';
$string['pluginname'] = 'Airpay';
$string['pluginname_desc'] = 'The airpay plugin allows you to receive payments via airpay.';
$string['privacy:metadata:paygw_airpay'] = 'Airpay orders: one row per checkout, with the buyer and the order id.';
$string['privacy:metadata:paygw_airpay:userid'] = 'The buyer.';
$string['privacy:metadata:paygw_airpay:component'] = 'The plugin the payment is for.';
$string['privacy:metadata:paygw_airpay:paymentarea'] = 'The payment area (a course fee or the cart).';
$string['privacy:metadata:paygw_airpay:itemid'] = 'The item paid for.';
$string['privacy:metadata:paygw_airpay:ap_orderid'] = 'The Airpay order id.';
$string['privacy:metadata:paygw_airpay:cost'] = 'The amount of the order.';
$string['privacy:metadata:paygw_airpay:paymentid'] = 'The Moodle payment the order became, once paid.';
$string['privacy:metadata:paygw_airpay:status'] = 'The state of the order.';
$string['privacy:metadata:paygw_airpay:timecreated'] = 'When the order was created.';
$string['privacy:metadata:paygw_course_enrolmentlog'] = 'Courses enrolled after an Airpay payment, kept for reconciliation and audit. On erasure the buyer is anonymised and the row stays.';
$string['privacy:metadata:paygw_course_enrolmentlog:userid'] = 'The buyer.';
$string['privacy:metadata:paygw_course_enrolmentlog:username'] = 'The buyer\'s full name at the time of payment.';
$string['privacy:metadata:paygw_course_enrolmentlog:courseid'] = 'The course paid for.';
$string['privacy:metadata:paygw_course_enrolmentlog:coursename'] = 'The name of the course paid for.';
$string['privacy:metadata:paygw_course_enrolmentlog:transactionid'] = 'The Airpay transaction id.';
$string['privacy:metadata:paygw_course_enrolmentlog:ap_orderid'] = 'The Airpay order id.';
$string['privacy:metadata:paygw_course_enrolmentlog:amount'] = 'The amount paid for the course.';
$string['privacy:metadata:paygw_course_enrolmentlog:status'] = 'The state of the payment.';
$string['privacy:metadata:paygw_course_enrolmentlog:timecreated'] = 'When the row was created.';
$string['privacy:metadata:paygw_airpay_errorlog'] = 'Order states and gateway messages for each Airpay order. On erasure the buyer is anonymised, in the message text too, and the row stays.';
$string['privacy:metadata:paygw_airpay_errorlog:userid'] = 'The buyer.';
$string['privacy:metadata:paygw_airpay_errorlog:courseid'] = 'The course of the order.';
$string['privacy:metadata:paygw_airpay_errorlog:airpay_id'] = 'The Airpay order id.';
$string['privacy:metadata:paygw_airpay_errorlog:error'] = 'The message, which can name the buyer\'s user id.';
$string['privacy:metadata:paygw_airpay_errorlog:order_state'] = 'The state of the order.';
$string['privacy:metadata:paygw_airpay_errorlog:paymentarea'] = 'The payment area (a course fee or the cart).';
$string['privacy:metadata:paygw_airpay_errorlog:timecreated'] = 'When the message was logged.';
$string['privacy:metadata:airpay'] = 'At checkout the buyer\'s details are sent to Airpay, the payment processor.';
$string['privacy:metadata:airpay:email'] = 'The buyer\'s e-mail address.';
$string['privacy:metadata:airpay:phone'] = 'The buyer\'s phone number.';
$string['privacy:metadata:airpay:firstname'] = 'The buyer\'s first name.';
$string['privacy:metadata:airpay:lastname'] = 'The buyer\'s last name.';
$string['privacy:metadata:airpay:city'] = 'The buyer\'s city.';
$string['privacy:metadata:airpay:state'] = 'The buyer\'s state.';
$string['privacy:metadata:airpay:country'] = 'The buyer\'s country.';
$string['privacy:metadata:airpay:amount'] = 'The amount to pay.';
$string['privacy:metadata:airpay:orderid'] = 'The order id.';
$string['repeatedorder'] = 'This order has already been processed earlier.';
$string['sandbox'] = 'Sandbox';
$string['mid'] = 'Mid';
$string['mid_help'] = 'The Mid that airpay generated for your application.';
$string['key'] = 'Key';
$string['key_help'] = 'The key that airpay generated for your application.';
$string['iv'] = 'Iv';
$string['iv_help'] = 'The iv that airpay generated for your application.';
$string['terminalId'] = 'Terminal Id';
$string['terminalId_help'] = 'The terminalId that airpay generated for your application.';
$string['username'] = 'Username';
$string['mercid'] = 'Mercid';
$string['merdom'] = 'Merdom';
$string['merdom_help'] = 'The Merdom is that the domain for your application.';
