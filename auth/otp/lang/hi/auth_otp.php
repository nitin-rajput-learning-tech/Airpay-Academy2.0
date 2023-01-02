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

$string['pluginname'] = 'ओटीपी	';
$string['auth_otpserviceip']	='	ओटीपी सेवा यूआरएल	';
$string['auth_otpserversettings']	='	ओटीपी सेटिंग्स	';
$string['auth_otpservicedescription']	='	TOTP वेब सेवा सर्वर सेटिंग्स यहाँ दी गई हैं।	';
$string['moreproviderlink']	='	किसी अन्य सेवा के साथ साइन-इन करें।	';
$string['signinwithanaccount']	='	से लोगिन करें:	';
$string['noaccountyet']	='	आपको अभी तक साइट का उपयोग करने की अनुमति नहीं है। कृपया अपने व्यवस्थापक से संपर्क करें और उन्हें अपना खाता सक्रिय करने के लिए कहें।	';
$string['applicationid']	='	फ़ोन नंबर:	';
$string['otp']	='	ओटीपी:	';
$string['generateotp']	='	ओटीपी जनरेट करें	';
$string['notvalidapplicant']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->username}
"लॉगिन करने का प्रयास किया। यह मान्य आवेदक आईडी नहीं है	';
$string['astnotvalidapplicant']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->username}
" लॉगिन करने का प्रयास किया। यह AsT-EXT का एजेंट नहीं है	';
$string['notvalidphone']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->phonenumber}
"लॉगिन करने का प्रयास किया। फोन नंबर मान्य नहीं है"
{$a->phonenumber}
"	';
$string['errorcodefromservice']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->phonenumber}
"लॉगिन करने का प्रयास किया। फोन नंबर मान्य नहीं है"
{$a->phonenumber}
"	';
$string['havingagentcode']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->phonenumber}
"लॉगिन करने की कोशिश में एजेंट कोड है।	';
$string['hashexistinuser']	='	उपयोगकर्ता नाम वाला उपयोगकर्ता "
{$a->username}
" पासवर्ड में "#" से लॉगिन करने का प्रयास किया	';
$string['validagent']	='	उपयोगकर्ता नाम वाला उपयोगकर्ता "
{$a->username}
"लॉगिन करने का प्रयास किया। उपयोगकर्ता ओटीपी जनरेट करने के लिए वैध है"	';
$string['otpsendtomobile']	='	ओटीपी "
{$a->otp}
"उपयोगकर्ता फ़ोन नंबर पर भेजें"
{$a->phonenumber}
"और फोन नंबर"
{$a->phonenumber}
". उपयोगकर्ता ओटीपी जनरेट करने के लिए वैध तैयार है	';
$string['otpabovethree']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->username}
"ओटीपी की कोशिश की"
{$a->otp}
"अधिक तो 3 बार।	';
$string['incorrectotp']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->username}
"गलत ओटीपी की कोशिश की"
{$a->otp}
"।	';
$string['validotpentered']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->username}
"सफलतापूर्वक वैध ओटीपी दर्ज किया गया"
{$a->otp}
"।	';
$string['otpnotvalid']	='	फ़ोन नंबर वाला उपयोगकर्ता "
{$a->username}
"अमान्य ओटीपी का प्रयास कर रहा है"
{$a->otp}
".	';
$string['spaceexistinuser']	='	उपयोगकर्ता नाम वाला उपयोगकर्ता "
{$a->username}
पासवर्ड में " " स्पेस के साथ लॉगिन करने की कोशिश की"';

$string['notvalidapplicant']='फ़ोननंबर "{$a->username}" वाले उपयोगकर्ता ने लॉगिन करने का प्रयास किया। यह स्वीकृत नहीं है';
