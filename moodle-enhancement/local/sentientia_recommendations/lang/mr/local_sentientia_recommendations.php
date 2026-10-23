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
 * Marathi strings for local_sentientia_recommendations.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_recommendations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI कोर्स शिफारसी';
$string['sentientia_recommendations:view'] = 'डॅशबोर्डवर AI कोर्स शिफारसी पाहा';
$string['sentientia_recommendations:generate'] = 'लर्नरसाठी AI कोर्स शिफारसी जनरेट करा';
$string['sentientia_recommendations:manage_all'] = 'सर्व लर्नर्सचा AI शिफारस इतिहास मॅनेज करा';
$string['privacy:metadata'] = 'Sentientia LMS चे AI शिफारसी प्लगिन वैयक्तिकृत कोर्स शिफारसी आणि प्रत्येकीसाठी थोडक्यात कारण साठवते. लाइव्ह API फ्लॅग ON असताना, अनामिक केलेले लर्नर प्रोफाइल (रोल, टेनंट, पूर्ण केलेल्या कोर्सचे ID, स्किल टॅग्ज) प्रक्रियेसाठी Anthropic कडे पाठवले जाते.';
$string['privacy:metadata:rec'] = 'प्रत्येक लर्नरसाठी AI ने जनरेट केलेल्या कोर्स शिफारसी.';
$string['privacy:metadata:rec:userid'] = 'ज्या लर्नरसाठी ही शिफारस आहे तो लर्नर.';
$string['privacy:metadata:rec:courseid'] = 'शिफारस केलेला कोर्स.';
$string['privacy:metadata:rec:score'] = 'या शिफारसीला Claude ने दिलेला कॉन्फिडन्स स्कोअर.';
$string['privacy:metadata:rec:reasoning'] = 'हा कोर्स पुढचा योग्य टप्पा का आहे हे सांगणारे थोडक्यात कारण.';
$string['privacy:metadata:rec:tokens'] = 'खर्चाच्या हिशोबासाठी Anthropic API टोकन वापर.';
$string['privacy:metadata:rec:status'] = 'शिफारसीची स्थिती: सक्रिय, नाकारलेली, एनरोल झालेली, कालबाह्य.';
$string['privacy:metadata:rec:generated_at'] = 'शिफारस जनरेट केल्याची वेळ.';
$string['privacy:metadata:rec:timecreated'] = 'शिफारस तयार केल्याची वेळ.';
$string['privacy:metadata:rec:timemodified'] = 'शिफारस शेवटची बदलल्याची वेळ.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — लाइव्ह API फ्लॅग चालू असताना, शिफारसी जनरेट करण्यासाठी अनामिक केलेले लर्नर प्रोफाइल Anthropic कडे पाठवले जाते. प्रत्येक API कॉलआधी कन्फर्मेशनची पायरी आवश्यक आहे.';
$string['privacy:metadata:anthropic:profile_role'] = 'लर्नरचे रोल लेबल (उदा. लर्नर, मॅनेजर).';
$string['privacy:metadata:anthropic:profile_completed'] = 'लर्नरने पूर्ण केलेल्या कोर्सचे ID.';
$string['privacy:metadata:anthropic:profile_skills'] = 'लर्नरच्या नावावर सध्या नोंद असलेले स्किल टॅग्ज.';
$string['privacy:metadata:anthropic:model'] = 'वापरलेला Anthropic मॉडेल आयडेंटिफायर.';
$string['nav_generate'] = 'AI शिफारसी जनरेट करा';
$string['generate_page_title'] = 'AI कोर्स शिफारसी जनरेट करा';
$string['generate_page_heading'] = 'वैयक्तिकृत कोर्स शिफारसी जनरेट करा';
$string['generate_intro'] = 'एक लर्नर निवडा आणि Anthropic Claude कडून वैयक्तिकृत कोर्स शिफारसींची एक बॅच मागवा. प्रत्येक शिफारसीसोबत थोडक्यात कारण असते, जे लर्नर आपल्या डॅशबोर्डवर पाहू शकतो.';
$string['generate_form_targetuser'] = 'टार्गेट लर्नर (यूजर ID)';
$string['generate_form_targetuser_help'] = 'ज्या लर्नरसाठी ही बॅच जनरेट होईल त्याचा यूजर ID. डीफॉल्टनुसार तुमचाच यूजर ID असतो.';
$string['generate_form_num'] = 'शिफारसींची संख्या';
$string['generate_form_num_help'] = '1 ते {$a} दरम्यान. शिफारस करण्याजोग्या कोर्सचा कॅटलॉग खूप लहान असल्यास Claude कमी शिफारसी देऊ शकतो.';
$string['generate_form_model'] = 'मॉडेल';
$string['generate_confirm_label'] = 'या लर्नर प्रोफाइलसह Anthropic ला कॉल करण्यास माझी पुष्टी आहे';
$string['generate_confirm_help'] = 'हा प्रत्येक कॉलसाठीचा कन्फर्मेशन गेट आहे. लाइव्ह API फ्लॅग ON असताना, प्रोफाइल + कॅटलॉगच्या आकारानुसार या सबमिशनसाठी खरे पैसे खर्च होतील. रद्द करण्यासाठी टिक काढा.';
$string['generate_submit'] = 'शिफारसी जनरेट करा';
$string['generate_cancel'] = 'रद्द करा';
$string['generate_success'] = 'शिफारसींची बॅच जनरेट झाली. बॅच ID: {$a->batchid}. {$a->mode} मोडमध्ये {$a->count} शिफारसी तयार झाल्या. टोकन: {$a->tokens_in} इन / {$a->tokens_out} आउट.';
$string['mode_mock_badge'] = 'मॉक मोड — लाइव्ह API कॉल नाही (खऱ्या जनरेशनसाठी sentientia.recommendations.live_api = ON सेट करा)';
$string['mode_live_badge'] = 'लाइव्ह API — Anthropic कॉलचे बिल तुमच्या अकाउंटवर लागेल';
$string['mode_disabled_badge'] = 'फीचर बंद आहे — Switchboard मध्ये sentientia.recommendations.enabled = ON सेट करा';
$string['mode_no_apikey_badge'] = 'API की कॉन्फिगर केलेली नाही — साइट ॲडमिनमध्ये local_sentientia_recommendations | api_key सेट करा';
$string['err_feature_off'] = 'AI शिफारसी फीचर बंद आहे. sentientia.recommendations.enabled चालू करण्यासाठी ॲडमिनिस्ट्रेटरला सांगा.';
$string['err_no_capability'] = 'तुम्हाला AI शिफारसी जनरेट करण्याची परवानगी नाही.';
$string['err_invalid_count'] = 'शिफारसींची संख्या {$a->min} ते {$a->max} दरम्यान असावी.';
$string['err_confirm_required'] = 'API कॉल करण्यासाठी तुम्हाला कन्फर्मेशन चेकबॉक्सवर टिक करावे लागेल.';
$string['err_api_key_not_set'] = 'कोणतीही Anthropic API की कॉन्फिगर केलेली नाही. लाइव्ह कॉल करता येणार नाही.';
$string['err_api_failed'] = 'Anthropic API कॉल अयशस्वी झाला: {$a}';
$string['err_parser_zero'] = 'Claude ने उत्तर दिले, पण वापरता येतील अशा शिफारसी पार्स करता आल्या नाहीत.';
$string['err_cost_cap_reached'] = 'तुमच्या कस्टमर अकाउंटने आज {$a->used} टोकन वापरले आहेत (दैनिक सॉफ्ट कॅप {$a->cap} आहे). उद्या पुन्हा प्रयत्न करा किंवा कॅप वाढवण्यासाठी ॲडमिनला सांगा.';
$string['err_user_not_found'] = 'टार्गेट लर्नर सापडला नाही किंवा डिलीट झाला आहे.';
$string['err_candidates_empty'] = 'या लर्नरसाठी शिफारस करण्याजोगे कोणतेही कोर्स उपलब्ध नाहीत.';
$string['err_profile_invalid'] = 'लर्नर प्रोफाइल अवैध आहे.';
$string['err_profile_contains_pii'] = 'लर्नर प्रोफाइलमध्ये PII पॅटर्न (आधार किंवा PAN) आहेत. ते काढून पुन्हा सबमिट करा.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Anthropic Claude API साठी क्रेडेन्शियल्स. sentientia.recommendations.enabled आणि sentientia.recommendations.live_api दोन्ही ON असतील तेव्हाच ही की वापरली जाते.';
$string['setting_api_key'] = 'Anthropic API की';
$string['setting_api_key_desc'] = 'console.anthropic.com वरून API की पेस्ट करा. ती एन्क्रिप्ट करून साठवली जाते. ही व्हॅल्यू कधीही सोर्स कंट्रोलमध्ये कमिट करू नका.';
$string['setting_default_model'] = 'डीफॉल्ट मॉडेल';
$string['setting_default_model_desc'] = 'जनरेटरने कोणतेही मॉडेल न सांगितल्यास वापरले जाणारे Anthropic मॉडेल. शिफारस: claude-sonnet-4-6.';
$string['setting_max_output_tokens'] = 'कमाल आउटपुट टोकन';
$string['setting_max_output_tokens_desc'] = 'प्रत्येक कॉलसाठी Anthropic max_tokens वरील कडक मर्यादा. डीफॉल्ट 2048.';
$string['settings_heading_limits'] = 'मर्यादा आणि कोटा';
$string['settings_heading_limits_desc'] = 'खर्च आटोक्यात ठेवण्यासाठी प्रति बॅच आणि प्रति कस्टमर मर्यादा.';
$string['setting_max_recommendations'] = 'प्रति बॅच कमाल शिफारसी';
$string['setting_max_recommendations_desc'] = 'प्रत्येक जनरेशनमधील शिफारसींची कमाल मर्यादा. डीफॉल्ट 5.';
$string['setting_max_history_items'] = 'कमाल कम्प्लीशन इतिहास आयटम';
$string['setting_max_history_items_desc'] = 'लर्नरच्या अलीकडच्या किती कम्प्लीशन्स प्रॉम्प्टमध्ये द्यायच्या. डीफॉल्ट 50.';
$string['setting_daily_cost_cap'] = 'प्रति कस्टमर दैनिक टोकन कॅप';
$string['setting_daily_cost_cap_desc'] = 'प्रत्येक कस्टमरसाठी दररोज शिफारस जनरेशनमध्ये वापरल्या जाणाऱ्या टोकनवरील (इनपुट + आउटपुट) सॉफ्ट कॅप. मर्यादा ओलांडल्यानंतर मध्यरात्रीपर्यंत generate.php एरर दाखवते.';
$string['settings_heading_prompt'] = 'प्रॉम्प्ट कॉन्फिगरेशन';
$string['settings_heading_prompt_desc'] = 'प्रॉम्प्टचे व्हर्जन कोडमध्ये ठेवले जातात (prompt_builder::VERSION). हे फ्री-टेक्स्ट फील्ड फक्त माहितीसाठी आहे.';
$string['setting_prompt_template_note'] = 'प्रॉम्प्ट टेम्पलेट नोट';
$string['setting_prompt_template_note_desc'] = 'सध्याच्या प्रॉम्प्ट टेम्पलेटबद्दल मोकळ्या नोट्स — फक्त ॲडमिन UI मध्ये दिसतात. या prompt_builder ला ओव्हरराइड करत नाहीत.';
$string['block_title'] = 'तुमच्यासाठी शिफारसी';
$string['block_empty'] = 'तुमच्यासाठी अजून कोणतीही शिफारस नाही. नंतर पुन्हा पाहा.';
$string['block_disabled'] = 'AI शिफारसी सध्या बंद आहेत.';
$string['block_view_all'] = 'सर्व पाहा';
$string['block_dismiss'] = 'रस नाही';
$string['block_why'] = 'हे का?';
$string['block_score'] = 'मॅच';
$string['tokens_used_today'] = 'आज वापरलेले टोकन (संपूर्ण कस्टमर): {$a->used} / {$a->cap}';
$string['recommendation_card'] = 'शिफारस #{$a}';
