<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi strings for enrol_sentientiasub (ADR-023). 100% parity with en.
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia सदस्यता';
$string['pluginname_desc'] = 'आवर्ती-सदस्यता नामांकन। जब तक सदस्य का Airpay मैंडेट सक्रिय रहता है, उसकी पहुँच बनी रहती है; शुल्क विफल होने पर पहुँच निलंबित और रद्द करने पर समाप्त कर दी जाती है। डिफ़ॉल्ट रूप से अक्षम — उपयोग के लिए "sentientia.subscriptions.enabled" फ़ीचर फ़्लैग (प्रति टेनेंट) सक्षम करें।';

// Capabilities.
$string['sentientiasub:config'] = 'Sentientia सदस्यता नामांकन इंस्टेंस कॉन्फ़िगर करें';
$string['sentientiasub:manage'] = 'सदस्यता प्राप्त उपयोगकर्ताओं को प्रबंधित करें';
$string['sentientiasub:unenrol'] = 'सदस्यता प्राप्त उपयोगकर्ताओं को पाठ्यक्रम से हटाएँ';
$string['sentientiasub:unenrolself'] = 'अपनी सदस्यता रद्द करें';

// Settings.
$string['defaultrole'] = 'डिफ़ॉल्ट भूमिका असाइनमेंट';
$string['defaultrole_desc'] = 'सदस्यता सक्रिय रहने के दौरान सदस्य को सौंपी जाने वाली भूमिका (स्कोप = एकल पाठ्यक्रम)।';
$string['allaccesscohort'] = 'ऑल-एक्सेस कोहोर्ट';
$string['allaccesscohort_desc'] = 'सक्रियण पर ऑल-एक्सेस सदस्यों को इस कोहोर्ट में जोड़ा जाता है (निलंबन/रद्दीकरण पर हटाया जाता है)। सदस्यों को पाठ्यक्रम पहुँच देने के लिए इस कोहोर्ट से अपने कैटलॉग में Moodle कोहोर्ट-सिंक (enrol_cohort) कॉन्फ़िगर करें। श्रेणी-स्कोप सदस्यता के लिए, "sentientiasub_cat_<categoryid>" idnumber वाला कोहोर्ट बनाएँ और उसे उस श्रेणी में कोहोर्ट-सिंक करें।';

// Subscription statuses.
$string['status_pending'] = 'लंबित';
$string['status_active'] = 'सक्रिय';
$string['status_suspended'] = 'निलंबित';
$string['status_cancelled'] = 'रद्द';

// Privacy.
$string['privacy:metadata:enrol_sentientiasub_subscription'] = 'उपयोगकर्ता को नामांकन इंस्टेंस से जोड़ने वाले आवर्ती-सदस्यता रिकॉर्ड।';
$string['privacy:metadata:enrol_sentientiasub_subscription:userid'] = 'सदस्यता धारक उपयोगकर्ता।';
$string['privacy:metadata:enrol_sentientiasub_subscription:status'] = 'सदस्यता स्थिति (लंबित, सक्रिय, निलंबित, रद्द)।';
$string['privacy:metadata:enrol_sentientiasub_subscription:amount'] = 'आवर्ती शुल्क राशि।';
$string['privacy:metadata:enrol_sentientiasub_subscription:ap_mandate_id'] = 'आवर्ती प्राधिकरण के लिए Airpay मैंडेट पहचानकर्ता।';
$string['privacy:metadata:enrol_sentientiasub_subscription:timecreated'] = 'सदस्यता रिकॉर्ड कब बनाया गया।';
