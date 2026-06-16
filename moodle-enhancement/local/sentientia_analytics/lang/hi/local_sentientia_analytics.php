<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे उन्नत विश्लेषिकी';
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
$string['skillgap_description']  = 'प्रति टीम आवश्यक कौशलों का अनुमानित प्रतिशत जो अभी तक पूरा नहीं हुआ है। पाठ्यक्रम-श्रेणी कवरेज से व्युत्पन्न।';
$string['skillgap_empty']        = 'इस दायरे के लिए कोई कौशल अंतर डेटा उपलब्ध नहीं है।';
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
