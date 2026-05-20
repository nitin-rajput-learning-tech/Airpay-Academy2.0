<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #32 (2026-05-20) — Hindi (hi) translations for local_airpay_skills.
// Covers strings added by P1 #22 (audit log privacy metadata) and
// P1 #25 (learner self-rate UI), plus the full base lang pack.
//
// Style: formal corporate-Hindi register suitable for L&D / compliance
// contexts. Proper nouns (Hex, NPS, etc.) kept in Latin where idiomatic.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे कौशल मैट्रिक्स';
$string['privacy:metadata'] = 'कौशल प्लगइन उपयोगकर्ता ID से जुड़े कौशल स्तर डेटा संग्रहीत करता है।';

// Capabilities.
$string['airpay_skills:view']      = 'कौशल मैट्रिक्स और गैप विश्लेषण देखें';
$string['airpay_skills:manage']    = 'कौशल श्रेणियाँ और परिभाषाएँ प्रबंधित करें';
$string['airpay_skills:self_rate'] = 'किसी कौशल के लिए स्व-घोषित प्रवीणता स्तर निर्धारित करें (केवल अपनी प्रोफ़ाइल पर)';

// CRUD strings.
$string['addskill']      = 'कौशल जोड़ें';
$string['editskill']     = 'कौशल संपादित करें';
$string['deleteskill']   = 'कौशल हटाएँ';
$string['addcategory']   = 'कौशल श्रेणी जोड़ें';
$string['editcategory']  = 'श्रेणी संपादित करें';
$string['deletecategory'] = 'श्रेणी हटाएँ';

// Form section headings.
$string['heading_skill']    = 'कौशल परिभाषा';
$string['heading_levels']   = 'प्रवीणता स्तर';
$string['heading_category'] = 'श्रेणी पहचान';
$string['heading_visual']   = 'दृश्य शैली';

// Form labels.
$string['skill_name']     = 'कौशल का नाम';
$string['category_name']  = 'श्रेणी का नाम';
$string['description']    = 'विवरण';
$string['category']       = 'श्रेणी';
$string['max_level']      = 'अधिकतम प्रवीणता स्तर';
$string['max_level_help'] = 'इस कौशल के लिए सीखने वाला जिस अधिकतम प्रवीणता स्तर तक पहुँच सकता है। उन कौशलों के लिए 5 का उपयोग करें जहाँ महारत महत्वपूर्ण है; द्विआधारी/केवल-जागरूकता वाले कौशलों के लिए 3 का उपयोग करें।';
$string['icon']           = 'आइकन';
$string['color']          = 'ब्रांड रंग';
$string['color_help']     = 'Hex रंग कोड (जैसे #0066A7)। कौशल सूचियों और गैप विश्लेषण चार्ट में श्रेणी बैज के लिए उपयोग किया जाता है।';
$string['sort_order']     = 'प्रदर्शन क्रम';

// Errors.
$string['missingrequiredfields']  = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['invalidcategory']        = 'चुनी गई श्रेणी मौजूद नहीं है।';
$string['categoryinuse']          = 'श्रेणी हटाई नहीं जा सकती — इसमें अभी भी कौशल असाइन हैं। पहले उन कौशलों को हटाएँ या स्थानांतरित करें।';
$string['color_invalid']          = 'रंग # से शुरू होने वाला hex कोड होना चाहिए (जैसे #0066A7)।';
$string['confirmdeleteskill']     = 'कौशल "{$a}" हटाएँ? यह इस कौशल के सभी भूमिका मैपिंग, कोर्स मैपिंग और सीखने वालों के रिकॉर्ड भी हटा देगा। इसे पूर्ववत नहीं किया जा सकता।';
$string['confirmdeletecategory']  = 'श्रेणी "{$a}" हटाएँ? केवल तभी अनुमति है जब इसमें कोई कौशल असाइन नहीं हो।';

// Success.
$string['skillcreated']    = 'कौशल बनाया गया।';
$string['skillupdated']    = 'कौशल अपडेट किया गया।';
$string['skilldeleted']    = 'कौशल हटा दिया गया।';
$string['categorycreated'] = 'श्रेणी बनाई गई।';
$string['categoryupdated'] = 'श्रेणी अपडेट की गई।';
$string['categorydeleted'] = 'श्रेणी हटा दी गई।';

// Learner-facing labels (kept verbatim from original hi pack).
$string['skills']             = 'कौशल';
$string['skillmatrix']        = 'कौशल मैट्रिक्स';
$string['gapanalysis']        = 'गैप विश्लेषण';
$string['yourskills']         = 'आपके कौशल';
$string['requiredskills']     = 'आपकी भूमिका के लिए आवश्यक';
$string['currentlevel']       = 'वर्तमान स्तर';
$string['requiredlevel']      = 'आवश्यक स्तर';
$string['gap']                = 'अंतर';
$string['met']                = 'पूर्ण';
$string['partial']            = 'प्रगति में';
$string['missing']            = 'शुरू नहीं हुआ';
$string['skillsgap']          = '{$a->total} में से {$a->gaps} कौशल अंतर';
$string['skillsmet']          = '{$a->total} में से {$a->met} कौशल आवश्यक स्तर पर ({$a->percentage}%)';
$string['recommendedcourses'] = 'अंतर भरने के लिए अनुशंसित';
$string['nodesignation']      = 'कोई भूमिका/पदनाम सेट नहीं है। अपनी प्रोफ़ाइल अपडेट करने के लिए अपने प्रबंधक से संपर्क करें।';
$string['noskillsmapped']     = 'आपकी भूमिका के लिए अभी तक कोई कौशल मैप नहीं किया गया। जल्द ही जाँचें।';
$string['teamheatmap']        = 'टीम कौशल हीट मैप';
$string['careerpath']         = 'करियर पथ';

// Privacy provider strings.
$string['privacy:metadata:user_skills']                = 'प्रति उपयोगकर्ता अर्जित कौशल स्तर।';
$string['privacy:metadata:user_skills:userid']         = 'जिस उपयोगकर्ता का कौशल स्तर दर्ज है।';
$string['privacy:metadata:user_skills:skillid']        = 'जो कौशल दर्ज किया जा रहा है।';
$string['privacy:metadata:user_skills:current_level']  = 'वह स्तर जिस पर उपयोगकर्ता को क्रेडिट दिया गया है (1..max_level)।';
$string['privacy:metadata:user_skills:source']         = 'क्या यह कोर्स पूर्णता, मूल्यांकन या मैन्युअल प्रविष्टि से प्राप्त हुआ।';
$string['privacy:metadata:user_skills:source_id']      = 'जिस कोर्स या मूल्यांकन ने स्तर प्रदान किया उसकी ID।';
$string['privacy:metadata:user_skills:timecreated']    = 'जब स्तर पहली बार दर्ज किया गया।';

// P1 #22 (2026-05-16) — skill-level audit log privacy metadata.
$string['privacy:metadata:user_skill_hist']                  = 'किसी उपयोगकर्ता के कौशल स्तर में हर बदलाव का अपेंड-ओनली ऑडिट लॉग। HR को "इस उपयोगकर्ता ने यह स्तर कब हासिल किया?" का उत्तर देने और कंप्लायंस रिपोर्टिंग में सहायक।';
$string['privacy:metadata:user_skill_hist:userid']           = 'जिस उपयोगकर्ता का कौशल स्तर बदला।';
$string['privacy:metadata:user_skill_hist:skillid']          = 'जो कौशल बदला।';
$string['privacy:metadata:user_skill_hist:previous_level']   = 'इस बदलाव से पहले उपयोगकर्ता का स्तर (यदि कोई स्तर नहीं था तो 0)।';
$string['privacy:metadata:user_skill_hist:new_level']        = 'इस बदलाव के बाद उपयोगकर्ता का स्तर।';
$string['privacy:metadata:user_skill_hist:source']           = 'बदलाव क्या ट्रिगर किया (कोर्स पूर्णता, मूल्यांकन, मैन्युअल प्रविष्टि, इम्पोर्ट)।';
$string['privacy:metadata:user_skill_hist:source_id']        = 'जिस कोर्स या मूल्यांकन ने बदलाव ट्रिगर किया उसकी ID।';
$string['privacy:metadata:user_skill_hist:changed_by_userid'] = 'कार्य करने वाले उपयोगकर्ता की ID (प्रबंधक / एडमिन)। जब बदलाव स्वचालित रूप से ट्रिगर हुआ (जैसे कोर्स-पूर्णता ऑब्ज़र्वर द्वारा) तो null।';
$string['privacy:metadata:user_skill_hist:timecreated']      = 'जब बदलाव दर्ज किया गया।';

// P1 #25 (2026-05-20) — learner self-rate UI + error strings.
$string['self_rate']               = 'इस कौशल को स्व-रेट करें';
$string['self_rate_modal_title']   = '{$a} के लिए अपना स्तर सेट करें';
$string['self_rate_intro']         = 'वह स्तर चुनें जो आपकी वर्तमान प्रवीणता को सबसे अच्छा दर्शाता है। स्तर वर्णनात्मक हैं — यदि आप अनिश्चित हैं तो इस कौशल के पेज पर स्तर परिभाषाएँ पढ़ें। ईमानदारी आपके प्रबंधक को आपके लिए प्रशिक्षण की योजना बनाने में सहायता करती है।';
$string['self_rate_current']       = 'आपका वर्तमान स्तर: {$a}';
$string['self_rate_not_yet']       = 'आपने अभी तक खुद को रेट नहीं किया है।';
$string['self_rate_submit']        = 'मेरा स्तर सहेजें';
$string['self_rate_saved']         = 'आपका स्तर सहेज लिया गया है।';
$string['self_rate_level_invalid'] = 'स्तर {$a->level} अनुमत सीमा (1..{$a->max}) से बाहर है।';
$string['self_rate_pick_level']    = 'कृपया पहले एक स्तर चुनें।';
