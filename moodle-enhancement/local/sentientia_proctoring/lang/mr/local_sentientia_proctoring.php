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
 * Marathi strings for local_sentientia_proctoring.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_proctoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia प्रॉक्टरिंग';
$string['adminhome'] = 'प्रॉक्टरिंग ॲडमिन';
$string['reviewqueue'] = 'रिव्ह्यू क्यू';
$string['attempts'] = 'प्रॉक्टर्ड प्रयत्न';
$string['mysessions'] = 'माझी प्रॉक्टर्ड सेशन्स';
$string['settings_h'] = 'प्रॉक्टरिंग सेटिंग्ज';
$string['sentientia_proctoring:attempt'] = 'प्रॉक्टर्ड क्विझ द्या';
$string['sentientia_proctoring:viewattempts'] = 'प्रॉक्टरिंग प्रयत्नाचे तपशील पाहा';
$string['sentientia_proctoring:review'] = 'फ्लॅग केलेली प्रॉक्टरिंग सेशन्स रिव्ह्यू करा';
$string['sentientia_proctoring:manage'] = 'प्रॉक्टरिंग सेटिंग्ज + रिव्ह्यूअर असाइनमेंट्स मॅनेज करा';
$string['sentientia_proctoring:bypass'] = 'प्रॉक्टरिंग बायपास करा (आपत्कालीन, ऑडिट लॉग होते)';
$string['consent_title'] = 'रेकॉर्ड होणारी परीक्षा — संमती आवश्यक';
$string['consent_intro'] = 'ही परीक्षा प्रॉक्टर्ड आहे. सुरू करण्यापूर्वी कृपया वाचा आणि संमती द्या.';
$string['consent_l1'] = '<strong>ओळख पडताळणी</strong> — तुम्हाला सरकारी ID चा फोटो आणि एक सेल्फी अपलोड करायला सांगितले जाईल. मॅच स्कोअर काढला जातो आणि फक्त स्कोअर ठेवला जातो.';
$string['consent_l2'] = '<strong>वेबकॅम आणि स्क्रीन मॉनिटरिंग</strong> — परीक्षा चालू असेपर्यंत तुमचा कॅमेरा आणि स्क्रीन रेकॉर्ड केली जाते. ऑडिओ फक्त आवाज फ्लॅग करण्यासाठी कॅप्चर केला जातो.';
$string['consent_l3'] = '<strong>ऑटोमेटेड रिव्ह्यू</strong> — संशयास्पद वर्तनासाठी (एकापेक्षा जास्त चेहरे, फ्रेमबाहेर जाणे, बराच वेळ बॅकग्राउंड आवाज) AI रेकॉर्डिंग्ज स्कॅन करते. फ्लॅग झालेली सेशन्स एक मानवी प्रॉक्टर रिव्ह्यू करतात.';
$string['consent_retention'] = 'रेकॉर्डिंग्ज {$a} दिवस ठेवली जातात आणि नंतर डिलीट केली जातात. ओळखीचे फोटो मॅच झाल्यावर लगेच डिलीट केले जातात.';
$string['consent_accept'] = 'मी या रेकॉर्डिंग अटी वाचल्या आहेत आणि मला त्या मान्य आहेत';
$string['consent_decline'] = 'रद्द करा आणि बाहेर पडा';
$string['consent_proceed'] = 'परीक्षेकडे पुढे जा';
$string['identity_title'] = 'पायरी 1 — ओळख पडताळणी';
$string['identity_id_label'] = 'सरकारी ID (स्पष्ट फोटो, 4 ही कोपरे दिसले पाहिजेत)';
$string['identity_selfie_label'] = 'सेल्फी (चेहरा मध्यभागी, चांगला उजेड)';
$string['identity_submit'] = 'ओळख व्हेरिफाय करा';
$string['identity_processing'] = 'व्हेरिफाय करत आहोत — याला 10-30 सेकंद लागतात...';
$string['identity_passed'] = 'ओळख व्हेरिफाय झाली (मॅच स्कोअर: {$a})';
$string['identity_failed'] = 'ओळख पडताळणी अयशस्वी झाली. कृपया पुन्हा प्रयत्न करा किंवा सपोर्टशी संपर्क साधा.';
$string['identity_lowmatch'] = 'मॅच स्कोअर खूप कमी आहे ({$a}). कृपया चांगल्या उजेडात सेल्फी पुन्हा काढा.';
$string['monitor_title'] = 'पायरी 2 — लाइव्ह मॉनिटरिंग चालू आहे';
$string['monitor_camera'] = 'कॅमेरा चालू';
$string['monitor_mic'] = 'मायक्रोफोन चालू';
$string['monitor_screen'] = 'स्क्रीन रेकॉर्डिंग चालू';
$string['monitor_lockwarn'] = 'हा ब्राउझर टॅब सोडू नका. टॅब बदलल्यास त्याची नोंद होते.';
$string['event_face_lost'] = 'चेहरा फ्रेमबाहेर गेला';
$string['event_multiple_faces'] = 'एकापेक्षा जास्त चेहरे आढळले';
$string['event_tab_switch'] = 'टॅब बदलला';
$string['event_window_blur'] = 'विंडोचा फोकस गेला';
$string['event_mic_noise'] = 'बॅकग्राउंड आवाज अचानक वाढला';
$string['event_clipboard_paste'] = 'पेस्ट आढळले';
$string['event_fullscreen_exit'] = 'फुलस्क्रीनमधून बाहेर पडले';
$string['event_session_start'] = 'सेशन सुरू झाले';
$string['event_session_end'] = 'सेशन संपले';
$string['review_pending'] = 'रिव्ह्यू बाकी';
$string['review_in_progress'] = 'रिव्ह्यू चालू';
$string['review_completed'] = 'रिव्ह्यू झाले';
$string['review_decision'] = 'निर्णय';
$string['review_decision_clean'] = 'क्लीन — कोणतीही अडचण नाही';
$string['review_decision_warn'] = 'इशारा — किरकोळ फ्लॅग्ज';
$string['review_decision_fail'] = 'फसवणूक आढळली — प्रयत्न फेल करा';
$string['review_note'] = 'रिव्ह्यूअरची नोंद';
$string['review_assign'] = 'रिव्ह्यूअर असाइन करा';
$string['status_new'] = 'सुरू झाले नाही';
$string['status_consenting'] = 'संमतीची वाट पाहत आहे';
$string['status_verifying'] = 'ओळख व्हेरिफाय होत आहे';
$string['status_recording'] = 'रेकॉर्डिंग चालू';
$string['status_finished'] = 'पूर्ण झाले';
$string['status_flagged'] = 'रिव्ह्यूसाठी फ्लॅग केले';
$string['status_reviewed'] = 'रिव्ह्यू झाले';
$string['settings_provider'] = 'ओळख पडताळणी प्रोव्हायडर';
$string['settings_provider_desc'] = 'aws = AWS Rekognition (प्रॉडक्शन), mock = लोकल मॉक (टेस्टिंग/डेव्ह). प्रत्येक एन्व्हायर्नमेंटसाठी वेगळे सेट करा.';
$string['settings_aws_region'] = 'AWS रीजन';
$string['settings_aws_key'] = 'AWS ॲक्सेस की ID';
$string['settings_aws_secret'] = 'AWS सीक्रेट ॲक्सेस की';
$string['settings_aws_s3_bucket'] = 'रेकॉर्डिंग्जसाठी S3 बकेट';
$string['settings_match_threshold'] = 'ओळख मॅच थ्रेशोल्ड (%)';
$string['settings_match_threshold_desc'] = 'ओळख पायरी पास होण्यासाठी किमान फेस-मॅच स्कोअर. डीफॉल्ट 85.';
$string['settings_retention_days'] = 'रेकॉर्डिंग ठेवण्याचा कालावधी (दिवस)';
$string['settings_retention_days_desc'] = 'डिलीट करण्यापूर्वी रेकॉर्डिंग्ज किती दिवस ठेवायची. डीफॉल्ट 90.';
$string['settings_recording_chunk_secs'] = 'रेकॉर्डिंग चंक साइझ (सेकंद)';
$string['settings_recording_chunk_secs_desc'] = 'व्हिडिओ चंक्स किती वेळाने अपलोड होतात. डीफॉल्ट 30 सेकंद.';
$string['settings_default_reviewer'] = 'डीफॉल्ट रिव्ह्यूअर यूजर ID';
$string['settings_default_reviewer_desc'] = 'कोणताही विशिष्ट रिव्ह्यूअर असाइन नसल्यास फ्लॅग झालेली सेशन्स ज्या यूजरला मिळतात तो यूजर.';
$string['messageprovider:session_flagged'] = 'प्रॉक्टर्ड सेशन रिव्ह्यूसाठी फ्लॅग झाले';
$string['messageprovider:session_reviewed'] = 'तुमच्या प्रॉक्टर्ड सेशनचा रिव्ह्यू झाला';
$string['messageprovider:identity_failed'] = 'ओळख पडताळणी अयशस्वी झाली';
$string['error_consent_required'] = 'प्रॉक्टर्ड परीक्षा सुरू करण्यापूर्वी संमती आवश्यक आहे.';
$string['error_identity_required'] = 'सुरू करण्यापूर्वी ओळख पडताळणी पूर्ण व्हायला हवी.';
$string['error_no_provider'] = 'कोणताही ओळख प्रोव्हायडर कॉन्फिगर केलेला नाही.';
$string['error_session_not_found'] = 'प्रॉक्टरिंग सेशन सापडले नाही.';
$string['error_session_state'] = 'या कृतीसाठी सेशनची स्थिती चुकीची आहे.';
$string['error_review_not_allowed'] = 'हे सेशन रिव्ह्यू करण्याची तुम्हाला परवानगी नाही.';
$string['privacy:metadata:local_sentientia_proctor_sessions'] = 'प्रॉक्टर्ड परीक्षा सेशन्स';
$string['privacy:metadata:local_sentientia_proctor_identity'] = 'ओळख पडताळणी स्कोअर (मॅचनंतर फोटो डिलीट केले जातात)';
$string['privacy:metadata:local_sentientia_proctor_events'] = 'प्रत्येक प्रयत्नातील वर्तनविषयक इव्हेंट्स';
$string['privacy:metadata:local_sentientia_proctor_recordings'] = 'वेबकॅम/स्क्रीन रेकॉर्डिंग्जच्या S3 की';
$string['privacy:metadata:local_sentientia_proctor_reviews'] = 'मानवी रिव्ह्यूअरच्या नोंदी आणि निर्णय';
$string['privacy:metadata:aws_rekognition'] = 'AWS Rekognition (ओळखीसाठी फेस मॅचिंग)';
$string['privacy:metadata:aws_rekognition:photo'] = 'ID फोटो आणि सेल्फी (मॅचनंतर डिलीट केले जातात, फक्त स्कोअर ठेवला जातो)';
$string['privacy:metadata:aws_s3'] = 'AWS S3 (रेकॉर्डिंग स्टोरेज)';
$string['privacy:metadata:aws_s3:video'] = 'परीक्षा प्रयत्नाचे व्हिडिओ चंक्स';
