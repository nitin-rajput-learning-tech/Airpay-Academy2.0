<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #51 (2026-05-20) — Hindi (hi) translations for local_sentientia_ratings.
// Scope: star-rating widget + write endpoint capability + error messages.

defined('MOODLE_INTERNAL') || die();

$string['pluginname']    = 'एयरपे रेटिंग्स';
$string['rate']          = 'रेट करें';
$string['yourrating']    = 'आपकी रेटिंग';
$string['averagerating'] = 'औसत रेटिंग';
$string['noratings']     = 'अभी तक कोई रेटिंग नहीं';

// W1-3 (2026-05-15) — write endpoint.
$string['sentientia_ratings:rate'] = 'कोर्स, क्लासरूम, प्रोग्राम और लर्निंग पाथ पर स्टार रेटिंग सबमिट करें';
$string['invalidrating']       = 'रेटिंग 1 से 5 स्टार के बीच होनी चाहिए';
$string['invalidratearea']     = 'अज्ञात रेटिंग क्षेत्र';
$string['invaliditemid']       = 'अमान्य आइटम जिसकी रेटिंग की जा रही है';
$string['cannotrateasguest']   = 'रेटिंग सबमिट करने के लिए आपको लॉग-इन करना होगा';
$string['ratingsaved']         = 'आपकी रेटिंग सहेज ली गई है';
$string['rateaccessibility']   = '{$a} को 5 में से रेट करें';

// Privacy metadata (classes/privacy/provider.php, added 2026-09-22).
// Replaced a null_provider that wrongly asserted this plugin held no
// personal data. Every table below is keyed on a user id.
$string['privacy:metadata:ratings'] = 'किसी पाठ्यक्रम या अन्य वस्तु को कर्मचारी द्वारा दी गई रेटिंग।';
$string['privacy:metadata:ratings:userid'] = 'इस रिकॉर्ड से संबंधित उपयोगकर्ता की आईडी।';
$string['privacy:metadata:ratings:itemid'] = 'जिस वस्तु को रेट किया गया उसकी आईडी।';
$string['privacy:metadata:ratings:ratearea'] = 'किस प्रकार की वस्तु रेट की गई।';
$string['privacy:metadata:ratings:rating'] = 'उपयोगकर्ता द्वारा दी गई रेटिंग।';
$string['privacy:metadata:ratings:timecreated'] = 'रिकॉर्ड कब बनाया गया।';
$string['privacy:metadata:ratings:timemodified'] = 'रिकॉर्ड अंतिम बार कब बदला गया।';
