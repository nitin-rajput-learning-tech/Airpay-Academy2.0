<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Hindi strings for local_sentientia_core. Started 2026-08-04 with the
// privacy-provider strings; admin-only settings strings fall back to en.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'Sentientia Core प्लगइन local_sentientia_org_member में संगठन-इकाई सदस्यता पंक्तियाँ (उपयोगकर्ता, इकाई, भूमिका, प्रत्यक्ष प्रबंधक) संग्रहीत करता है। टेनेंट रजिस्ट्री स्वयं (ग्राहक + टेनेंट कॉन्फ़िगरेशन: नाम, root id, स्थिति) में कोई व्यक्तिगत डेटा नहीं है।';

// Privacy provider (2026-08-04) — real metadata + export + delete.
$string['privacy:metadata:org_member']             = 'उपयोगकर्ता की संगठन-इकाई सदस्यता: इकाई, भूमिका और प्रत्यक्ष प्रबंधक';
$string['privacy:metadata:org_member:userid']      = 'सदस्य';
$string['privacy:metadata:org_member:unitid']      = 'जिस संगठन इकाई से उपयोगकर्ता संबंधित है';
$string['privacy:metadata:org_member:role']        = 'इकाई के भीतर उपयोगकर्ता की भूमिका';
$string['privacy:metadata:org_member:managerid']   = 'उपयोगकर्ता का प्रत्यक्ष प्रबंधक';
$string['privacy:metadata:org_member:timecreated'] = 'सदस्यता कब दर्ज की गई';
