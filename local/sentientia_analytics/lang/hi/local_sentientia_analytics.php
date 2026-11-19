<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंटिया उन्नत विश्लेषिकी';
$string['analytics'] = 'विश्लेषिकी डैशबोर्ड';

// P1 #50 (2026-05-20) — Hindi top-up: 1 string (privacy).
$string['privacy:metadata'] = 'विश्लेषिकी प्लगइन मौजूदा डेटा को क्वेरी करता है और व्यक्तिगत डेटा संग्रहीत नहीं करता है।';

// ── P1.2 (2026-06-16) पूर्वानुमानात्मक विश्लेषिकी स्ट्रिंग ──────────
$string['predictive_heading']    = 'पूर्वानुमानात्मक विश्लेषिकी';
$string['atrisk_heading']        = 'जोखिम में शिक्षार्थी';
$string['atrisk_description']    = 'जो शिक्षार्थी पाठ्यक्रम पूर्णता की समय-सीमा चूकने की संभावना रखते हैं, उन्हें संलग्नता संकेतों के आधार पर पूर्वानुमानित किया गया है। स्कोर हालिया गतिविधि (30%), पूर्णता अंतर (25%), अतिदेय पाठ्यक्रम (25%) और संलग्नता वेग (20%) पर आधारित हैं।';
$string['atrisk_empty']          = 'इस अवधि के लिए कोई जोखिम में शिक्षार्थी नहीं मिला।';
$string['risk_score']            = 'जोखिम स्कोर';
$string['risk_band_high']        = 'उच्च जोखिम';
$string['risk_band_medium']      = 'मध्यम जोखिम';
$string['risk_band_low']         = 'निम्न जोखिम';
$string['signals_heading']       = 'जोखिम संकेत';
$string['signal_weight']         = 'भार';
$string['skillgap_heading']      = 'कौशल अंतर अनुमान';
$string['skillgap_description']  = 'हर टीम (संगठन का तीसरा स्तर) के लिए उन पाठ्यक्रम श्रेणियों का हिस्सा, जिनमें उसके सदस्य नामांकित हैं पर जिन्हें उन्होंने पूरा नहीं किया है। पाठ्यक्रम श्रेणियाँ कौशल के स्थान पर उपयोग होती हैं: यह कार्ड अभी कौशल सूची या भूमिका मैपिंग नहीं पढ़ता।';
$string['skillgap_empty']        = 'इस दायरे की किसी भी टीम में तुलना के लिए नामांकन नहीं हैं। अनुमान के लिए संगठन के तीसरे स्तर पर ऐसी टीमें चाहिए जिनके सदस्य पाठ्यक्रमों में नामांकित हों।';
$string['gap_pct']               = 'अंतर';
$string['covered_skills']        = 'कवर किए गए';
$string['required_skills']       = 'आवश्यक';
$string['uncovered_skills']      = 'अनकवर कौशल';
$string['task_refresh_predictive_cache'] = 'Sentientia: पूर्वानुमानात्मक विश्लेषिकी कैश ताज़ा करें';

// ── P1.2 प्रशिक्षण ROI स्ट्रिंग ─────────────────────────────────────
$string['roi_heading']                   = 'प्रशिक्षण ROI';
$string['roi_description']               = 'चुनी गई अवधि के लिए प्रशिक्षण निवेश पर अनुमानित प्रतिफल। सभी धारणाएँ पारदर्शी और कॉन्फ़िगर करने योग्य हैं।';
$string['roi_pct']                       = 'ROI';
$string['roi_net_benefit']               = 'शुद्ध लाभ';
$string['roi_total_benefit']             = 'कुल लाभ';
$string['roi_total_cost']                = 'कुल लागत';
$string['roi_benefits_heading']          = 'लाभ';
$string['roi_costs_heading']             = 'लागत';
$string['roi_assumptions_heading']       = 'मॉडल धारणाएँ';
$string['roi_assumptions_note']          = 'ये आंकड़े कॉन्फ़िगर करने योग्य अनुमान हैं। अपने संगठन के वास्तविक आंकड़ों को दर्शाने के लिए इन्हें प्लगइन सेटिंग्स में समायोजित करें।';
$string['roi_currency_symbol']           = '₹';
$string['roi_benefit_productivity']      = 'उत्पादकता लाभ (बचाया गया समय)';
$string['roi_benefit_compliance']        = 'अनुपालन दंड परिहार';
$string['roi_cost_ld_staff']             = 'L&D कर्मचारी / सामग्री विकास';
$string['roi_cost_platform']             = 'प्लेटफ़ॉर्म और बुनियादी ढाँचा';
$string['roi_cost_content']              = 'सामग्री उपभोग लागत';
$string['roi_cost_platform_flat']        = 'अवधि के लिए फ्लैट शुल्क';
$string['roi_assm_hours_saved']          = 'प्रति पूर्णता बचाए गए घंटे';
$string['roi_assm_hourly_rate']          = 'मिश्रित कर्मचारी प्रति घंटा दर';
$string['roi_assm_penalty']              = 'समय पर पूर्णता प्रति परिहार दंड';
$string['roi_assm_platform_cost']        = 'अवधि के लिए प्लेटफ़ॉर्म लागत';
$string['roi_assm_hours_per_course']     = 'प्रति पाठ्यक्रम औसत सामग्री घंटे';
$string['roi_empty']                     = 'इस अवधि के लिए ROI की गणना करने के लिए अपर्याप्त डेटा।';

// Capabilities (db/access.php, added 2026-09-22).
$string['sentientia_analytics:view'] = 'विश्लेषण डैशबोर्ड देखें';
$string['sentientia_analytics:viewallorgs'] = 'सभी संगठनों का विश्लेषण देखें, केवल अपना नहीं';
$string['sentientia_analytics:export'] = 'विश्लेषण CSV निर्यात डाउनलोड करें';

// Previously hardcoded in index.php / dashboard.mustache.
$string['allbusinessunits'] = 'सभी व्यावसायिक इकाइयाँ';
$string['exportcsv'] = 'CSV निर्यात करें';

// Refusals (N5, 2026-09-24) - see lang/en.
$string['error_noorgscope'] = 'आपका खाता किसी संगठन को असाइन नहीं है, इसलिए कोई विश्लेषण नहीं दिखाया जा सकता। अपना संगठन सेट करवाने के लिए अपने व्यवस्थापक से संपर्क करें।';
$string['error_outofscope'] = 'संगठन का वह हिस्सा आपकी पहुँच से बाहर है।';

// At-Risk Learners table (N6, 2026-09-24) - see lang/en.
$string['atrisk_col_learner'] = 'शिक्षार्थी';

// डैशबोर्ड, KPI कार्ड और फ़नल चरण (2026-11-18) - देखें lang/en.
$string['businessunit_label'] = 'व्यावसायिक इकाई:';
$string['range_label'] = 'KPI कार्डों की अवधि';
$string['range_7d'] = '7 दिन';
$string['range_30d'] = '30 दिन';
$string['range_90d'] = '90 दिन';
$string['range_ytd'] = 'वर्ष में अब तक';
$string['kpi_active_users'] = 'सक्रिय उपयोगकर्ता';
$string['kpi_new_enrolments'] = 'नए नामांकन';
$string['kpi_completions'] = 'पूर्णताएँ';
$string['kpi_certificates'] = 'प्रमाणपत्र';
$string['kpi_vs_previous'] = 'पिछली अवधि की तुलना में {$a}';
$string['funnel_heading'] = 'संलग्नता फ़नल';
$string['funnel_empty'] = 'अभी कोई नामांकन डेटा उपलब्ध नहीं है।';
$string['funnel_enrolled'] = 'नामांकित';
$string['funnel_started'] = 'शुरू किया';
$string['funnel_completed'] = 'पूर्ण';
$string['funnel_certified'] = 'प्रमाणित';
$string['heatmap_heading'] = 'विभाग के अनुसार अनुपालन';
$string['heatmap_note'] = 'पूर्ण किए गए अनिवार्य पाठ्यक्रम असाइनमेंट, अनुपालन रिपोर्ट के विभाग स्कोरकार्ड के अनुसार।';
$string['heatmap_viewusers'] = 'इस विभाग के लोगों को देखें';
$string['heatmap_users'] = '{$a} उपयोगकर्ता';
$string['heatmap_users_one'] = '1 उपयोगकर्ता';
$string['heatmap_empty_title'] = 'अभी कोई अनुपालन असाइनमेंट नहीं';
$string['heatmap_empty'] = 'इस दृश्य के किसी भी विभाग में अनिवार्य पाठ्यक्रम असाइनमेंट नहीं हैं।';
$string['courseeff_heading'] = 'पाठ्यक्रम प्रभावशीलता (शीर्ष {$a})';
$string['col_course'] = 'पाठ्यक्रम';
$string['col_enrolled'] = 'नामांकित';
$string['col_completed'] = 'पूर्ण';
$string['col_rate'] = 'दर';

// प्रशिक्षण ROI कार्ड और उसकी सेटिंग्स (QW-ROI, 2026-11-19)।
$string['roi_assm_course_dev_hours'] = 'प्रति दृश्यमान पाठ्यक्रम विकास और रखरखाव के घंटे';
$string['roi_assm_cost_per_learner_hour'] = 'प्रति शिक्षार्थी-घंटा सामग्री लागत';
$string['roi_default_marker'] = 'डिफ़ॉल्ट';
$string['roi_defaults_notice'] = 'ये आंकड़े अंतर्निहित डिफ़ॉल्ट धारणाओं पर आधारित हैं, आपके संगठन के अपने आंकड़ों पर नहीं। साइट व्यवस्थापक इन्हें बदल सकते हैं।';
$string['roi_settings_link'] = 'ROI धारणाएँ सेट करें';
$string['roi_empty_title_nocompletions'] = 'इस अवधि में कोई पूर्णता नहीं';
$string['roi_empty_nocompletions'] = 'इस अवधि ({$a}) में कोई पाठ्यक्रम पूरा नहीं हुआ, इसलिए लागत के मुकाबले कोई लाभ नहीं है। लंबी अवधि चुनें, या शिक्षार्थियों के पाठ्यक्रम पूरे करने के बाद फिर देखें।';
$string['roi_empty_title_nocost'] = 'तुलना के लिए कोई लागत नहीं';
$string['roi_empty_nocost'] = 'हर लागत धारणा शून्य है, इसलिए इस अवधि ({$a}) के लिए ROI की गणना नहीं की जा सकती। ROI सेटिंग्स में लागत धारणाएँ सेट करें।';
$string['roi_period_7d'] = 'पिछले 7 दिन';
$string['roi_period_30d'] = 'पिछले 30 दिन';
$string['roi_period_90d'] = 'पिछले 90 दिन';
$string['roi_period_ytd'] = 'वर्ष की शुरुआत से अब तक';
$string['roi_summary_positive'] = 'अवधि ({$a->period}): प्रशिक्षण पर खर्च किए गए हर {$a->unit} से अनुमानित {$a->ret} का प्रतिफल मिला, यानी {$a->pct}% का ROI (शुद्ध लाभ {$a->net})।';
$string['roi_summary_negative'] = 'अवधि ({$a->period}): अनुमानित प्रशिक्षण लागत अनुमानित लाभ से {$a->net} अधिक रही (ROI {$a->pct}%)।';
$string['roi_formula_productivity'] = '{$a->completions} पाठ्यक्रम पूर्णताएँ × प्रति पूर्णता बचाए गए {$a->hours} × {$a->rate}';
$string['roi_formula_compliance'] = 'पाठ्यक्रम की अंतिम तिथि तक हुई {$a->count} पूर्णताएँ × {$a->penalty} बचाया गया दंड';
$string['roi_formula_ld_staff'] = '{$a->courses} दृश्यमान पाठ्यक्रम × {$a->hours} विकास × {$a->rate}';
$string['roi_formula_platform'] = 'ROI सेटिंग्स से निश्चित राशि, हर अवधि के लिए समान';
$string['roi_formula_content'] = 'अवधि में नामांकित {$a->learners} शिक्षार्थी × प्रति पाठ्यक्रम {$a->hours} × {$a->rate}';
$string['roi_unit_hours'] = '{$a} घंटे';
$string['roi_unit_per_hour'] = '{$a} प्रति घंटा';
$string['roi_settings_heading'] = 'प्रशिक्षण ROI धारणाएँ';
$string['roi_settings_heading_desc'] = 'विश्लेषिकी डैशबोर्ड का प्रशिक्षण ROI कार्ड इन आंकड़ों को साइट की गिनतियों से गुणा करता है। अपने संगठन के अपने आंकड़ों का उपयोग करें। राशियाँ कार्ड पर दिखाई गई मुद्रा में हैं। केवल अंक और वैकल्पिक दशमलव बिंदु लिखें, अल्पविराम नहीं, जैसे 1500 या 2.5।';
$string['roi_hours_saved_per_completion_desc'] = 'उत्पादकता लाभ: अवधि में हर पाठ्यक्रम पूर्णता पर बचाए गए काम के घंटे।';
$string['roi_hourly_rate_desc'] = 'एक कर्मचारी-घंटे की मिश्रित लागत। उत्पादकता लाभ और L&D विकास लागत, दोनों में उपयोग होती है।';
$string['roi_penalty_avoided_desc'] = 'अनुपालन लाभ: पाठ्यक्रम की अंतिम तिथि तक हुई हर पूर्णता पर बचा दंड या नुकसान।';
$string['roi_course_dev_hours_desc'] = 'L&D लागत: एक पाठ्यक्रम को विकसित करने और बनाए रखने के घंटे। अवधि चाहे जो हो, हर दृश्यमान पाठ्यक्रम से गुणा किया जाता है।';
$string['roi_platform_cost_desc'] = 'प्लेटफ़ॉर्म और बुनियादी ढाँचे की लागत। हर अवधि (7 दिन, 30 दिन, 90 दिन या वर्ष की शुरुआत से अब तक) में पूरी गिनी जाती है।';
$string['roi_cost_per_learner_hour_desc'] = 'एक शिक्षार्थी-घंटे की सामग्री लागत, जैसे लाइसेंस।';
$string['roi_hours_per_course_desc'] = 'प्रति पाठ्यक्रम सीखने के औसत घंटे। उन शिक्षार्थियों से गुणा किया जाता है जिनका नामांकन अवधि में शुरू हुआ।';
