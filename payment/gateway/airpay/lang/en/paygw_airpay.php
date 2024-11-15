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
$string['privacy:metadata'] = 'The airpay plugin does not store any personal data.';
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
