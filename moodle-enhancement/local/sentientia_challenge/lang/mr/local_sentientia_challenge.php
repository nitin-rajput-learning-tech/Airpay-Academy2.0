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
 * Marathi strings for local_sentientia_challenge.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_challenge
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia गेमिफिकेशन चॅलेंजेस';
$string['sentientia_challenge:view'] = 'चॅलेंजेस आणि लीडरबोर्ड पाहा';
$string['sentientia_challenge:participate'] = 'चॅलेंजेस जॉइन करा आणि सोडा';
$string['sentientia_challenge:manage'] = 'चॅलेंजेस तयार करा, एडिट करा आणि डिलीट करा';
$string['sentientia_challenge:viewall'] = 'सर्व टेनंट्सचे लीडरबोर्ड पाहा';
$string['heading_index'] = 'चॅलेंजेस';
$string['heading_view'] = 'चॅलेंज: {$a}';
$string['heading_leaderboard'] = 'लीडरबोर्ड';
$string['nav_my_challenges'] = 'माझी चॅलेंजेस';
$string['nav_browse'] = 'ब्राउझ करा';
$string['nav_leaderboard'] = 'लीडरबोर्ड';
$string['nav_admin'] = 'चॅलेंजेस मॅनेज करा';
$string['filter_status'] = 'स्टेटस';
$string['filter_status_all'] = 'सर्व';
$string['filter_status_draft'] = 'ड्राफ्ट';
$string['filter_status_active'] = 'ॲक्टिव्ह';
$string['filter_status_archived'] = 'आर्काइव्ह केलेले';
$string['filter_search'] = 'शोधा';
$string['filter_search_placeholder'] = 'चॅलेंजचे नाव';
$string['filter_my'] = 'माझा सहभाग';
$string['filter_my_all'] = 'सर्व चॅलेंजेस';
$string['filter_my_joined'] = 'जॉइन केलेले';
$string['filter_my_completed'] = 'पूर्ण';
$string['filter_my_available'] = 'जॉइन करण्यासाठी उपलब्ध';
$string['status_draft'] = 'ड्राफ्ट';
$string['status_active'] = 'ॲक्टिव्ह';
$string['status_archived'] = 'आर्काइव्ह केलेले';
$string['attempt_enrolled'] = 'एनरोल केलेले';
$string['attempt_in_progress'] = 'सुरू आहे';
$string['attempt_completed'] = 'पूर्ण';
$string['attempt_failed'] = 'अयशस्वी';
$string['attempt_expired'] = 'मुदत संपली';
$string['type_course_completion'] = 'कोर्स कम्प्लीशन';
$string['type_streak'] = 'लॉगिन स्ट्रीक';
$string['type_quiz_score'] = 'क्विझ स्कोअर';
$string['type_custom'] = 'कस्टम';
$string['col_name'] = 'नाव';
$string['col_type'] = 'प्रकार';
$string['col_target'] = 'टार्गेट';
$string['col_points'] = 'पॉइंट्स';
$string['col_status'] = 'स्टेटस';
$string['col_participants'] = 'सहभागी';
$string['col_dates'] = 'तारखा';
$string['col_actions'] = 'ॲक्शन्स';
$string['col_progress'] = 'माझी प्रगती';
$string['form_name'] = 'चॅलेंजचे नाव';
$string['form_shortname'] = 'लहान नाव (slug)';
$string['form_shortname_help'] = 'अंतर्गत आयडेंटिफायर. URL मध्ये वापरला जातो. फक्त अक्षरे, अंक आणि हायफन.';
$string['form_description'] = 'वर्णन';
$string['form_type'] = 'चॅलेंजचा प्रकार';
$string['form_targetcount'] = 'टार्गेट संख्या';
$string['form_targetcount_help'] = 'हे चॅलेंज जिंकण्यासाठी किती पात्र कोर्सेस पूर्ण करावे लागतील.';
$string['form_courseids'] = 'पात्र कोर्सेस';
$string['form_courseids_help'] = 'कोणताही कोर्स मोजण्यासाठी रिकामे ठेवा. नाहीतर फक्त या कोर्सेसचे कम्प्लीशन मोजले जाईल.';
$string['form_pointsreward'] = 'पॉइंट्स रिवॉर्ड';
$string['form_status'] = 'स्टेटस';
$string['form_startdate'] = 'सुरुवात';
$string['form_enddate'] = 'शेवट';
$string['btn_create'] = 'नवीन चॅलेंज';
$string['btn_edit'] = 'एडिट करा';
$string['btn_delete'] = 'डिलीट करा';
$string['btn_view'] = 'पाहा';
$string['btn_join'] = 'चॅलेंज जॉइन करा';
$string['btn_leave'] = 'चॅलेंज सोडा';
$string['btn_publish'] = 'पब्लिश करा (ॲक्टिव्ह)';
$string['btn_archive'] = 'आर्काइव्ह करा';
$string['btn_leaderboard'] = 'लीडरबोर्ड';
$string['tab_overview'] = 'आढावा';
$string['tab_participants'] = 'सहभागी';
$string['tab_leaderboard'] = 'लीडरबोर्ड';
$string['ov_participants'] = 'सहभागी';
$string['ov_completed'] = 'पूर्ण';
$string['ov_completion_pct'] = 'कम्प्लीशन रेट';
$string['ov_avg_progress'] = 'सरासरी प्रगती';
$string['ov_my_progress'] = 'माझी प्रगती';
$string['ov_my_status'] = 'माझा स्टेटस';
$string['ov_my_points'] = 'माझे पॉइंट्स';
$string['ov_top_participant'] = 'टॉप सहभागी';
$string['lb_col_rank'] = 'रँक';
$string['lb_col_user'] = 'युजर';
$string['lb_col_points'] = 'पॉइंट्स';
$string['lb_col_completed'] = 'पूर्ण';
$string['lb_no_entries'] = 'लीडरबोर्डवर अजून एकही एंट्री नाही. पॉइंट्स मिळवायला सुरुवात करण्यासाठी एखादे चॅलेंज जॉइन करा.';
$string['lb_filter_challenge'] = 'चॅलेंज';
$string['lb_filter_challenge_aggregate'] = 'सर्व चॅलेंजेस (एकत्रित)';
$string['lb_filter_tenant'] = 'टेनंट';
$string['lb_filter_tenant_mine'] = 'फक्त माझा टेनंट';
$string['lb_filter_tenant_all'] = 'सर्व टेनंट्स (क्रॉस-टेनंट)';
$string['challenge_created'] = 'चॅलेंज "{$a}" तयार झाले.';
$string['challenge_updated'] = 'चॅलेंज "{$a}" अपडेट झाले.';
$string['challenge_deleted'] = 'चॅलेंज "{$a}" डिलीट झाले.';
$string['joined_challenge'] = 'तुम्ही हे चॅलेंज जॉइन केले.';
$string['left_challenge'] = 'तुम्ही हे चॅलेंज सोडले.';
$string['challenge_completed'] = 'तुम्ही हे चॅलेंज पूर्ण केले!';
$string['err_challenge_not_found'] = 'चॅलेंज सापडले नाही.';
$string['err_challenge_not_active'] = 'हे चॅलेंज सध्या ॲक्टिव्ह नाही.';
$string['err_already_joined'] = 'तुम्ही हे चॅलेंज आधीच जॉइन केले आहे.';
$string['err_not_joined'] = 'तुम्ही हे चॅलेंज जॉइन केलेले नाही.';
$string['err_already_completed'] = 'तुम्ही हे चॅलेंज आधीच पूर्ण केले आहे.';
$string['err_invalid_type'] = 'चॅलेंजचा प्रकार चुकीचा आहे.';
$string['err_invalid_status'] = 'स्टेटस चुकीचा आहे.';
$string['err_targetcount_min'] = 'टार्गेट संख्या किमान 1 असली पाहिजे.';
$string['err_pointsreward_min'] = 'पॉइंट्स रिवॉर्ड 0 किंवा त्यापेक्षा जास्त असला पाहिजे.';
$string['err_filterstoolong'] = 'फिल्टर डेटा मर्यादेपेक्षा जास्त आहे.';
$string['err_shortname_taken'] = 'लहान नाव "{$a}" आधीच वापरात आहे.';
$string['err_outside_cohort'] = 'हे चॅलेंज अशा कोहॉर्टपुरते मर्यादित आहे ज्यात तुम्ही नाही.';
$string['empty_no_challenges'] = 'अजून एकही चॅलेंज नाही. नवीन तयार करण्यासाठी "नवीन चॅलेंज" वर क्लिक करा.';
$string['empty_no_attempts'] = 'अजून कोणीही सहभागी नाही.';
$string['target_x_completions'] = '{$a} कोर्सेस पूर्ण करणे';
$string['rank_position'] = '#{$a}';
$string['points_x'] = '{$a} पॉइंट्स';
$string['attempts_x_completed'] = '{$a} जणांनी पूर्ण केले';
$string['task_recompute_leaderboard'] = 'Airpay चॅलेंज लीडरबोर्ड पुन्हा कॅल्क्युलेट करा';
$string['privacy:metadata:challenges'] = 'ॲडमिन युजर्सनी तयार केलेल्या चॅलेंजच्या व्याख्या (गेमिफिकेशन).';
$string['privacy:metadata:challenges:createdby'] = 'चॅलेंज तयार करणाऱ्या ॲडमिनचा युजर ID.';
$string['privacy:metadata:challenges:name'] = 'चॅलेंजचे डिस्प्ले नाव.';
$string['privacy:metadata:challenges:open_path'] = 'चॅलेंज तयार करताना ते तयार करणाऱ्याचा Airpay टेनंट पाथ.';
$string['privacy:metadata:challenges:timecreated'] = 'चॅलेंज कधी तयार झाले.';
$string['privacy:metadata:attempts'] = 'चॅलेंजमधील प्रत्येक युजरचे एनरोलमेंट + प्रगती.';
$string['privacy:metadata:attempts:challengeid'] = 'युजरने जॉइन केलेले चॅलेंज.';
$string['privacy:metadata:attempts:userid'] = 'सहभागी युजरचा ID.';
$string['privacy:metadata:attempts:status'] = 'सध्याचा स्टेटस (enrolled, in_progress, completed, failed, expired).';
$string['privacy:metadata:attempts:progress'] = 'टार्गेटच्या दिशेने पूर्ण केलेल्या पात्र ॲक्शन्सची संख्या.';
$string['privacy:metadata:attempts:pointsawarded'] = 'पूर्ण केल्यास दिलेले पॉइंट्स.';
$string['privacy:metadata:attempts:timecreated'] = 'युजरने चॅलेंज कधी जॉइन केले.';
$string['privacy:metadata:leaderboard'] = 'आधीच कॅल्क्युलेट केलेले लीडरबोर्ड रँकिंग.';
$string['privacy:metadata:leaderboard:challengeid'] = 'ज्या चॅलेंजचे रँकिंग होत आहे ते चॅलेंज (0 = एकत्रित).';
$string['privacy:metadata:leaderboard:userid'] = 'रँक केलेला युजर.';
$string['privacy:metadata:leaderboard:points'] = 'रँकिंग ठरवणारा पॉइंट्स स्कोअर.';
$string['privacy:metadata:leaderboard:userrank'] = '1 पासून सुरू होणारी रँक पोझिशन.';
