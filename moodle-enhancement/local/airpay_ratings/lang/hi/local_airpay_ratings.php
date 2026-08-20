<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #51 (2026-05-20) — Hindi (hi) translations for local_airpay_ratings.
// Scope: star-rating widget + write endpoint capability + error messages.

defined('MOODLE_INTERNAL') || die();

$string['pluginname']    = 'एयरपे रेटिंग्स';
$string['rate']          = 'रेट करें';
$string['yourrating']    = 'आपकी रेटिंग';
$string['averagerating'] = 'औसत रेटिंग';
$string['noratings']     = 'अभी तक कोई रेटिंग नहीं';

// W1-3 (2026-05-15) — write endpoint.
$string['airpay_ratings:rate'] = 'कोर्स, क्लासरूम, प्रोग्राम और लर्निंग पाथ पर स्टार रेटिंग सबमिट करें';
$string['invalidrating']       = 'रेटिंग 1 से 5 स्टार के बीच होनी चाहिए';
$string['invalidratearea']     = 'अज्ञात रेटिंग क्षेत्र';
$string['invaliditemid']       = 'अमान्य आइटम जिसकी रेटिंग की जा रही है';
$string['cannotrateasguest']   = 'रेटिंग सबमिट करने के लिए आपको लॉग-इन करना होगा';
$string['ratingsaved']         = 'आपकी रेटिंग सहेज ली गई है';
$string['rateaccessibility']   = '{$a} को 5 में से रेट करें';

// Privacy provider (2026-08-04) — real metadata + export + delete.
$string['privacy:metadata:ratings']              = 'उपयोगकर्ता द्वारा दी गई स्टार रेटिंग';
$string['privacy:metadata:ratings:userid']       = 'जिस उपयोगकर्ता ने रेटिंग दी';
$string['privacy:metadata:ratings:itemid']       = 'जिस आइटम की रेटिंग की गई';
$string['privacy:metadata:ratings:ratearea']     = 'किस प्रकार के आइटम की रेटिंग की गई (कोर्स, क्लासरूम, प्रोग्राम, पाथ)';
$string['privacy:metadata:ratings:rating']       = 'दिया गया 1-5 स्टार मान';
$string['privacy:metadata:ratings:timecreated']  = 'रेटिंग पहली बार कब दी गई';
$string['privacy:metadata:ratings:timemodified'] = 'रेटिंग अंतिम बार कब बदली गई';
