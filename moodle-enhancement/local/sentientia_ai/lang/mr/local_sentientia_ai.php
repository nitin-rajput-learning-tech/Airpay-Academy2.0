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
 * Marathi strings for local_sentientia_ai.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_ai
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia AI गेटवे';
$string['sentientia_ai:viewledger'] = 'AI खर्चाचा लेजर पाहा';
$string['sentientia_ai:manage'] = 'AI गेटवेचे रनटाइम कंट्रोल्स मॅनेज करा';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'प्रत्येक Sentientia AI फीचर वापरते ती एकच मध्यवर्ती की. मायग्रेशनदरम्यान प्रत्येक प्लगइनच्या जुन्या की फॉलबॅक म्हणून चालू राहतात; सगळं या एकाच कीवर आणा आणि जुन्या की क्लिअर करा.';
$string['setting_api_key'] = 'API की';
$string['setting_api_key_desc'] = 'Anthropic API की. ही कधीही कमिट केली जात नाही, लॉग केली जात नाही किंवा एरर मेसेजमध्ये दाखवली जात नाही. लाइव्ह कॉलसाठी याशिवाय दोन्ही गेटवे फीचर फ्लॅग ON असणे आणि कोटामध्ये जागा शिल्लक असणे आवश्यक आहे.';
$string['setting_default_model'] = 'डीफॉल्ट मॉडेल';
$string['setting_default_model_desc'] = 'कॉल करणारे फीचर कोणतेही मॉडेल सांगत नसेल तेव्हा वापरले जाणारे मॉडेल (उदा. claude-sonnet-4-6).';
$string['settings_heading_quotas'] = 'फेल-क्लोज्ड खर्च कोटा';
$string['settings_heading_quotas_desc'] = 'स्पेंड लेजरमधील आकड्यांवरून प्रत्येक लाइव्ह कॉलच्या आधीच लागू होणाऱ्या कडक मर्यादा. शून्य किंवा रिकामी व्हॅल्यू म्हणजे एकही लाइव्ह कॉल नाही (कधीही अमर्यादित नाही) — मंजूर AI बजेट ही निर्णयानुसार कडक मर्यादा आहे (मेमो 2026-08-04, Addendum A). कालावधी सर्व्हरच्या लोकल कॅलेंडरनुसार दिवस/महिने असतात. खर्च हे बजेटिंगसाठी प्रायसिंग-मॅपवर आधारित अंदाज आहेत, इनव्हॉइस नाहीत.';
$string['setting_daily_tokens_global'] = 'रोजची टोकन मर्यादा (ग्लोबल)';
$string['setting_daily_tokens_global_desc'] = 'सर्व कस्टमर्स आणि फीचर्स मिळून एका कॅलेंडर दिवसात परवानगी असलेले लाइव्ह टोकन (इनपुट + आउटपुट). 0 = लाइव्ह कॉल ब्लॉक.';
$string['setting_daily_tokens_customer'] = 'रोजची टोकन मर्यादा (प्रत्येक कस्टमरसाठी)';
$string['setting_daily_tokens_customer_desc'] = 'कोणत्याही एका कस्टमरसाठी एका कॅलेंडर दिवसात परवानगी असलेले लाइव्ह टोकन. 0 = लाइव्ह कॉल ब्लॉक.';
$string['setting_monthly_cost_cap'] = 'मासिक खर्च मर्यादा (USD, अंदाजे)';
$string['setting_monthly_cost_cap_desc'] = 'प्रत्येक कॅलेंडर महिन्यासाठी अंदाजे खर्चाची कमाल मर्यादा. लेजरमधील या महिन्यातील आतापर्यंतचा अंदाज या मर्यादेपर्यंत पोहोचला की पुढचे लाइव्ह कॉल नाकारले जातात. 0 = लाइव्ह कॉल ब्लॉक.';
$string['ledger_title'] = 'AI खर्चाचा लेजर';
$string['ledger_intro'] = 'प्रत्येक गेटवे कॉल — मॉक, लाइव्ह, अयशस्वी किंवा नाकारलेला — म्हणजे एक ओळ. प्रत्येक लाइव्ह कॉलच्या आधी याच आकड्यांवरून कोटा लागू केला जातो.';
$string['ledger_today'] = 'आज (लाइव्ह टोकन)';
$string['ledger_month'] = 'या महिन्यात आतापर्यंत (अंदाजे USD)';
$string['ledger_bycomponent'] = 'मागील 30 दिवस, फीचरनुसार';
$string['ledger_recent'] = 'अलीकडचे कॉल';
$string['ledger_col_time'] = 'वेळ';
$string['ledger_col_component'] = 'फीचर';
$string['ledger_col_purpose'] = 'उद्देश';
$string['ledger_col_user'] = 'युजर';
$string['ledger_col_model'] = 'मॉडेल';
$string['ledger_col_tokens'] = 'टोकन (इन/आउट)';
$string['ledger_col_cost'] = 'अंदाजे खर्च';
$string['ledger_col_mode'] = 'मोड';
$string['ledger_col_calls'] = 'कॉल';
$string['ledger_empty'] = 'अजून एकही गेटवे कॉल नोंदवलेला नाही.';
$string['privacy:exportpath'] = 'AI गेटवे वापर';
$string['privacy:metadata:ledger'] = 'युजरने ट्रिगर केलेल्या प्रत्येक AI कॉलसाठी AI गेटवे खर्च लेजर एक ओळ नोंदवतो. प्रॉम्प्ट आणि रिस्पॉन्सचा मजकूर कधीही साठवला जात नाही — फक्त वापराचा हिशोब ठेवला जातो.';
$string['privacy:metadata:ledger:userid'] = 'ज्याच्या ॲक्शनमुळे AI कॉल ट्रिगर झाला तो युजर.';
$string['privacy:metadata:ledger:component'] = 'कॉल करणारे प्लगइन.';
$string['privacy:metadata:ledger:purpose'] = 'कॉलचा जाहीर केलेला उद्देश (उदा. quiz_generation).';
$string['privacy:metadata:ledger:model'] = 'वापरलेले AI मॉडेल.';
$string['privacy:metadata:ledger:prompttokens'] = 'API ने नोंदवलेले इनपुट टोकन.';
$string['privacy:metadata:ledger:completiontokens'] = 'API ने नोंदवलेले आउटपुट टोकन.';
$string['privacy:metadata:ledger:estcost'] = 'कॉलचा USD मधील अंदाजे खर्च.';
$string['privacy:metadata:ledger:mode'] = 'कॉल मॉक, लाइव्ह, अयशस्वी किंवा नाकारलेला होता का.';
$string['privacy:metadata:ledger:timecreated'] = 'कॉल कधी केला गेला.';
$string['privacy:metadata:anthropic'] = 'लाइव्ह मोड चालू असताना, रिस्पॉन्स तयार करण्यासाठी प्रॉम्प्टचा मजकूर Anthropic API ला पाठवला जातो. प्रॉम्प्टमध्ये कर्मचाऱ्यांची PII समाविष्ट करू नये (कॉल करणाऱ्या फीचर्सच्या इनपुट नियमांद्वारे हे लागू केले जाते).';
$string['privacy:metadata:anthropic:prompttext'] = 'AI रिक्वेस्टचा प्रॉम्प्ट मजकूर.';
