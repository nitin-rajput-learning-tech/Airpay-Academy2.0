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
 * Marathi strings for local_sentientia_leaderboard.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_leaderboard
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — रिअल-टाइम लीडरबोर्ड';
$string['sentientia_leaderboard:view'] = 'माझ्या टेनंटमधील लीडरबोर्ड पाहा';
$string['sentientia_leaderboard:manageboard'] = 'माझ्या टेनंटमध्ये लीडरबोर्ड तयार करा, एडिट करा आणि डिलीट करा';
$string['sentientia_leaderboard:promoteboard'] = 'लीडरबोर्ड कस्टमर-व्यापी दिसेल असा प्रमोट करा';
$string['sentientia_leaderboard:viewall'] = 'सर्व टेनंटमधील लीडरबोर्ड पाहा (HR ॲनालिटिक्स)';
$string['type_quiz'] = 'क्विझ टॉप स्कोअरर्स';
$string['type_completion'] = 'सर्वात जलद पूर्ण करणारे';
$string['type_skill'] = 'मिळवलेले स्किल पॉइंट्स';
$string['type_quiz_desc'] = 'एका क्विझमधील सर्वोत्तम स्कोअरनुसार लर्नर्सना रँक द्या. टाय झाल्यास लागलेल्या वेळेनुसार ठरवले जाते.';
$string['type_completion_desc'] = 'कोर्स किती लवकर पूर्ण केला यानुसार लर्नर्सना रँक द्या. कमी वेळ = चांगले.';
$string['type_skill_desc'] = 'ठराविक तारखांच्या कालावधीत मिळवलेल्या स्किल पॉइंट्सनुसार लर्नर्सना रँक द्या.';
$string['scope_course'] = 'कोर्स';
$string['scope_tenant'] = 'टेनंट';
$string['scope_customer'] = 'कस्टमर-व्यापी';
$string['status_active'] = 'ॲक्टिव्ह';
$string['status_disabled'] = 'बंद';
$string['status_archived'] = 'आर्काइव्ह केलेले';
$string['col_rank'] = 'रँक';
$string['col_user'] = 'लर्नर';
$string['col_points'] = 'पॉइंट्स';
$string['col_score'] = 'स्कोअर';
$string['col_time'] = 'लागलेला वेळ';
$string['col_progress'] = 'प्रगती';
$string['col_skills'] = 'लेव्हल वाढलेली स्किल्स';
$string['heading_index'] = 'लीडरबोर्ड';
$string['heading_create'] = 'लीडरबोर्ड तयार करा';
$string['heading_edit'] = 'लीडरबोर्ड एडिट करा';
$string['heading_view'] = 'लीडरबोर्ड';
$string['label_name'] = 'बोर्डचे नाव';
$string['label_type'] = 'बोर्डचा प्रकार';
$string['label_scope'] = 'स्कोप';
$string['label_course'] = 'कोर्स';
$string['label_quiz'] = 'क्विझ';
$string['label_skills'] = 'स्किल्स (कॉमाने वेगळे केलेले ID; सर्वांसाठी रिकामे सोडा)';
$string['label_window_start'] = 'स्कोअरिंग कालावधीची सुरुवात';
$string['label_window_end'] = 'स्कोअरिंग कालावधीचा शेवट';
$string['label_recompute'] = 'पुन्हा कॅल्क्युलेट करण्याचे अंतर (सेकंद)';
$string['label_top_n'] = 'टॉप N दाखवा';
$string['label_show_my_rank'] = 'पाहणाऱ्याला त्याची स्वतःची रँक दाखवा';
$string['label_optout'] = 'पब्लिक लीडरबोर्डमधून मला लपवा';
$string['label_optout_desc'] = 'हे टिक केल्यास, प्रत्येक पब्लिक लीडरबोर्डवरून तुमचे नाव लपवले जाते. तुम्हाला पॉइंट्स मिळत राहतील, पण इतर लर्नर्सना तुमची रँकिंग दिसणार नाही.';
$string['action_create'] = 'बोर्ड तयार करा';
$string['action_save'] = 'बदल सेव्ह करा';
$string['action_delete'] = 'डिलीट करा';
$string['action_view'] = 'पाहा';
$string['action_recompute'] = 'आत्ताच पुन्हा कॅल्क्युलेट करा';
$string['anonymous'] = 'निनावी लर्नर';
$string['you'] = 'तुम्ही';
$string['your_rank'] = 'तुमची रँक: {$a}';
$string['no_rank_optout'] = 'तुम्ही पब्लिक लीडरबोर्डमधून ऑप्ट-आउट केले आहे.';
$string['no_rank_no_entry'] = 'या बोर्डवर तुमची अजून रँकिंग नाही.';
$string['no_entries'] = 'अजून कोणतीही रँकिंग नाही — पुढच्या वेळी पुन्हा कॅल्क्युलेट झाल्यावर परत पाहा.';
$string['last_recomputed_at'] = 'शेवटचे अपडेट: {$a}';
$string['live_indicator'] = 'लाइव्ह — रिअल टाइममध्ये अपडेट होत आहे';
$string['polling_fallback'] = 'दर 30 सेकंदांनी अपडेट होते';
$string['feature_disabled'] = 'लीडरबोर्ड्स चालू नाहीत. sentientia.leaderboards.enabled सुरू करण्यासाठी ॲडमिनला सांगा.';
$string['type_disabled'] = 'हा बोर्ड प्रकार चालू नाही.';
$string['block_title'] = 'लीडरबोर्ड';
$string['block_choose'] = 'एक लीडरबोर्ड निवडा';
$string['block_none'] = 'तुमच्या टेनंटमध्ये कोणताही लीडरबोर्ड उपलब्ध नाही.';
$string['error_invalidtype'] = 'चुकीचा बोर्ड प्रकार. यापैकी एक असणे आवश्यक आहे: quiz, completion, skill.';
$string['error_invalidscope'] = 'चुकीचा स्कोप. यापैकी एक असणे आवश्यक आहे: course, tenant, customer.';
$string['error_invalidwindow'] = 'स्कोअरिंग कालावधीचा शेवट सुरुवातीनंतर असायला हवा.';
$string['error_invalidrecompute'] = 'पुन्हा कॅल्क्युलेट करण्याचे अंतर किमान 30 सेकंद असायला हवे.';
$string['error_typenotenabled'] = 'हा बोर्ड प्रकार ॲडमिनने सुरू केलेला नाही.';
$string['error_quiznotscoped'] = 'क्विझ बोर्ड एका ठराविक क्विझपुरते मर्यादित असायला हवेत (quizid > 0).';
$string['error_completionnotscoped'] = 'कम्प्लीशन बोर्ड एका ठराविक कोर्सपुरते मर्यादित असायला हवेत (courseid > 0).';
$string['error_noboard'] = 'लीडरबोर्ड सापडला नाही.';
$string['error_outoftenant'] = 'तुम्ही दुसऱ्या टेनंटचा लीडरबोर्ड पाहू शकत नाही.';
$string['error_cantpromote'] = 'बोर्ड कस्टमर-व्यापी करण्याची परवानगी तुम्हाला नाही.';
$string['error_invalidpayload'] = 'इव्हेंट जर्नलसाठी चुकीचा पेलोड डेटा.';
$string['invalid_event_type'] = 'अज्ञात इव्हेंट प्रकार: {$a}';
$string['task_recompute_due_boards'] = 'वेळ झालेले लीडरबोर्ड पुन्हा कॅल्क्युलेट करा (Sentientia)';
$string['task_purge_old_events'] = 'जुने लीडरबोर्ड SSE इव्हेंट्स काढून टाका (Sentientia)';
$string['event_rankings_updated'] = 'लीडरबोर्ड रँकिंग अपडेट झाली';
$string['messageprovider:rank_change'] = 'लीडरबोर्ड रँक बदल';
$string['msg_top10_subject'] = '{$a->boardname} वर तुम्ही टॉप {$a->new_rank} मध्ये पोहोचलात!';
$string['msg_top10_body'] = 'छान काम — तुम्ही "{$a->boardname}" वर #{$a->new_rank} रँकसह टॉप 10 मध्ये पोहोचलात. आणखी वर जाण्यासाठी असेच चालू ठेवा.';
$string['msg_moveup_subject'] = '{$a->boardname} वर तुम्ही {$a->delta} स्थान वर गेलात';
$string['msg_moveup_body'] = '"{$a->boardname}" वर तुम्ही रँक #{$a->old_rank} वरून #{$a->new_rank} वर पोहोचलात — {$a->delta} स्थानांची झेप. शाब्बास!';
$string['msg_movedown_subject'] = '{$a->boardname} वर तुम्ही {$a->delta} स्थान खाली आलात';
$string['msg_movedown_body'] = '"{$a->boardname}" वर तुमची रँक #{$a->old_rank} वरून #{$a->new_rank} वर घसरली ({$a->delta} स्थान खाली). पुन्हा वर यायचंय? बोर्ड उघडा आणि शिकत राहा.';
$string['preference_optout'] = 'पब्लिक लीडरबोर्डमधून मला लपवा';
$string['privacy:metadata:lb_entries'] = 'एका युजरसाठी कॅशे केलेली लीडरबोर्ड रँकिंग';
$string['privacy:metadata:lb_entries:userid'] = 'ज्या युजरला रँक दिली जात आहे तो युजर';
$string['privacy:metadata:lb_entries:boardid'] = 'ही रँकिंग ज्या लीडरबोर्डची आहे तो लीडरबोर्ड';
$string['privacy:metadata:lb_entries:points'] = 'बोर्डमध्ये मिळवलेले पॉइंट्स';
$string['privacy:metadata:lb_entries:userrank'] = 'युजरची रँक';
$string['privacy:metadata:lb_entries:last_recomputed'] = 'ही रो शेवटची कधी कॅल्क्युलेट झाली';
$string['privacy:metadata:lb_optouts'] = 'पब्लिक यादीत दिसण्यापासून प्रत्येक युजरचे ऑप्ट-आउट';
$string['privacy:metadata:lb_optouts:userid'] = 'ऑप्ट-आउट केलेला युजर';
$string['privacy:metadata:lb_optouts:customerid'] = 'ऑप्ट-आउटचा कस्टमर स्कोप';
$string['privacy:metadata:lb_optouts:timeoptedout'] = 'युजरने ऑप्ट-आउट केल्याची वेळ';
$string['privacy:metadata:lb_boards'] = 'लीडरबोर्ड, प्रत्येक लीडरबोर्ड कोणी तयार केला यासह.';
$string['privacy:metadata:lb_boards:ownerid'] = 'लीडरबोर्डच्या मालक युजरचा ID.';
$string['privacy:metadata:lb_boards:name'] = 'लीडरबोर्डचे नाव.';
$string['privacy:metadata:lb_notify_log'] = 'तुम्हाला पाठवलेल्या रँक-बदल नोटिफिकेशन्सची नोंद.';
$string['privacy:metadata:lb_notify_log:userid'] = 'ज्या युजरला कळवले त्याचा ID.';
$string['privacy:metadata:lb_notify_log:boardid'] = 'नोटिफिकेशन कोणत्या लीडरबोर्डबद्दल होते.';
$string['privacy:metadata:lb_notify_log:last_sent'] = 'शेवटचे नोटिफिकेशन कधी पाठवले गेले.';
$string['privacy:metadata:lb_notify_log:last_old_rank'] = 'बदलापूर्वीची तुमची रँक.';
$string['privacy:metadata:lb_notify_log:last_new_rank'] = 'बदलानंतरची तुमची रँक.';
$string['privacy:metadata:lb_notify_log:last_reason'] = 'नोटिफिकेशन का पाठवले गेले.';
