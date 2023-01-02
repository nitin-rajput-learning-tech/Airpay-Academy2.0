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
 * Strings for component 'auth_google', language 'en'
 *
 * @package   auth_adwebservice
 * @author Jerome Mouneyrac
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'OTP';
$string['auth_otpserviceip'] = 'OTP Service URL';
$string['auth_ipusername'] = 'OTP username';
$string['auth_apikey'] = 'OTP API key';
$string['auth_senderid'] = 'OTP API senderid';
$string['auth_entityid'] = 'OTP API entityid';
$string['auth_templateid'] = 'OTP API templateid';
$string['auth_otpserversettings'] ='OTP Settings ';
$string['auth_otpservicedescription'] = 'TOTP Web Service Server Settings are given here. ';
$string['moreproviderlink'] = 'Sign-in with another service.';
$string['signinwithanaccount'] = 'Log in with:';
$string['noaccountyet'] = 'You do not have permission to use the site yet. Please contact your administrator and ask them to activate your account.';
$string['applicationid']='Mobile Number:';
$string['otp']='OTP:';
$string['generateotp']='Generate OTP';
$string['notvalidapplicant']='User with phonenumber "{$a->username}" tried to login. This is not valid applicantionID';
$string['astnotvalidapplicant']='User with phonenumber "{$a->username}" tried to login. This is not agent from AsT-EXT';
$string['notvalidphone']='User with phonenumber "{$a->phonenumber}" tried to login. Mobile Number is not valid "{$a->phonenumber}"';
$string['errorcodefromservice']='User with phonenumber "{$a->phonenumber}" tried to login. Error in OTP server"';
$string['havingagentcode']='User with phonenumber "{$a->phonenumber}" tried to login has Agent Code.';
$string['hashexistinuser']='User with UserName "{$a->username}" tried to login with "#" in Password';
$string['validagent']='User with UserName "{$a->username}" tried to login. User is Valid ready to generate OTP';
$string['otpsendtomobile']='OTP "{$a->otp}" send to User phonenumber "{$a->phonenumber}" and Mobile Number "{$a->phonenumber}". User is Valid ready to generate OTP';

$string['otpabovethree']='User with phonenumber "{$a->username}" tried OTP "{$a->otp}" more then 3 times.';
$string['incorrectotp']='User with phonenumber "{$a->username}" tried incorrect OTP "{$a->otp}" .';
$string['validotpentered']='User with phonenumber "{$a->username}" successfully entered valid OTP "{$a->otp}" .';
$string['otpnotvalid']='User with phonenumber "{$a->username}" is trying invalid OTP "{$a->otp}".';

$string['spaceexistinuser']='User with UserName "{$a->username}" tried to login with space " " in Password';
$string['notvalidapplicant']='User with phonenumber "{$a->username}" tried to login. This is not approved applicantionID';