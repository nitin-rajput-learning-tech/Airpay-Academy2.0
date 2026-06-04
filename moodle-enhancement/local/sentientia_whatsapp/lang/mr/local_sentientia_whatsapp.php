<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
// मराठी translations — local_sentientia_whatsapp
// Machine quality. Native-speaker review recommended.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एअरपे WhatsApp आणि SMS';

// Page chrome
$string['preferences_pagetitle']   = 'संप्रेषण प्राधान्ये';
$string['preferences_nav']         = 'संप्रेषण प्राधान्ये';
$string['preferences_heading']     = 'एअरपे अकॅडमी तुमच्यापर्यंत कशी पोहोचावी?';
$string['preferences_intro']       = 'एअरपे अकॅडमी तुम्हाला कोणत्या चॅनेलद्वारे कोर्स अपडेट, मुदत स्मरणपत्रे आणि प्रमाणपत्र सूचना पाठवू शकते ते निवडा. ईमेल नेहमी सुरू असतो.';

// Channels
$string['channel_email']           = 'ईमेल';
$string['channel_whatsapp']        = 'WhatsApp';
$string['channel_sms']             = 'SMS';
$string['channel_email_desc']      = 'नेहमी सक्रिय. तुमचा कामाचा ईमेल मूलभूत चॅनेल आहे.';
$string['channel_whatsapp_desc']   = 'जलद ओपन रेट. भारतात DLT अंतर्गत टेम्पलेट्स पूर्व-मंजूर आहेत.';
$string['channel_sms_desc']        = 'एका तासात 95% ओपन रेट. इंटरनेटशिवाय कार्य करते, फील्ड स्टाफसाठी उत्तम.';

// Mobile
$string['mobile_label']            = 'मोबाइल नंबर';
$string['mobile_hint']             = 'WhatsApp आणि SMS साठी आवश्यक. देश कोड समाविष्ट करा (उदा. भारतासाठी +91).';
$string['mobile_invalid']          = 'कृपया देश कोडसह वैध मोबाइल नंबर प्रविष्ट करा (उदा. +919876543210).';

// Primary
$string['prefer_label']            = 'मुख्य चॅनेल';
$string['prefer_hint']             = 'एकापेक्षा जास्त चॅनेल उपलब्ध असताना, हे प्रथम प्रयत्न केले जाते.';

// Consent
$string['dlt_consent_heading']     = 'संमती (भारतात WhatsApp/SMS साठी आवश्यक)';
$string['dlt_consent_body']        = 'ऑप्ट-इन करून, मी TCCCPR 2018 आणि DPDP कायदा 2023 नुसार वर निवडलेल्या चॅनेलवर एअरपे अकॅडमीकडून व्यवहार आणि सेवा संदेश प्राप्त करण्यास सहमत आहे.';
$string['dlt_consent_required']    = 'WhatsApp किंवा SMS वितरण सक्षम करण्यासाठी तुम्हाला संमती विधान स्वीकारणे आवश्यक आहे.';
$string['dlt_consent_logged_at']   = 'संमती नोंदवली: {$a}';

// Disabled
$string['channel_disabled_tenant'] = 'हे चॅनेल सध्या तुमच्या संस्थेसाठी अक्षम केले आहे. सक्षम करण्यासाठी तुमच्या प्रशासकाशी संपर्क साधा.';

// Actions
$string['save_preferences']        = 'प्राधान्ये जतन करा';
$string['preferences_saved']       = 'संप्रेषण प्राधान्ये अद्यतनित केली.';
$string['preferences_unchanged']   = 'जतन करण्यासाठी कोणतेही बदल नाहीत.';

// Settings
$string['settings_pagetitle']         = 'एअरपे WhatsApp आणि SMS — सेटिंग्स';
$string['settings_heading_live_mode'] = 'प्रदाता क्रेडेन्शियल्स';
$string['settings_heading_live_mode_desc'] = 'या की लाइव्ह WhatsApp/SMS पाठवणे सक्षम करतात. दोन्ही की सेट होईपर्यंत प्लगइन मॉक मोडमध्ये चालते.';
$string['settings_karix_api_key']     = 'Karix WhatsApp API की';
$string['settings_karix_api_key_desc'] = 'Karix Business API टोकन. WhatsApp लाइव्ह मोडसाठी आवश्यक.';
$string['settings_msg91_api_key']     = 'MSG91 SMS API की';
$string['settings_msg91_api_key_desc'] = 'MSG91 authkey. SMS लाइव्ह मोडसाठी आवश्यक.';
$string['settings_dlt_pe_id']         = 'DLT प्रिन्सिपल एंटिटी ID';
$string['settings_dlt_pe_id_desc']    = 'तुमच्या संस्थेचा DLT-नोंदणीकृत Principal Entity ID. SMS साठी आवश्यक.';

// Templates
$string['templates_pagetitle']        = 'DLT टेम्पलेट व्यवस्थापक';
$string['templates_heading']          = 'DLT-मंजूर संदेश टेम्पलेट्स';
$string['templates_intro']            = 'टेम्पलेट्स ऑपरेटरसह DLT-नोंदणीकृत असणे आवश्यक आहे. केवळ `approved` टेम्पलेट्स वापरण्यायोग्य आहेत.';
$string['template_status_updated']    = 'टेम्पलेट स्थिती अद्यतनित केली.';
$string['show_body']                  = 'सामग्री दर्शवा';
$string['th_template']                = 'टेम्पलेट की';
$string['th_channel']                 = 'चॅनेल';
$string['th_status']                  = 'स्थिती';
$string['th_dlt_id']                  = 'DLT ID';
$string['th_body']                    = 'सामग्री';
$string['th_actions']                 = 'क्रिया';
$string['btn_submit']                 = 'DLT वर सबमिट करा';
$string['btn_approve']                = 'मंजूर म्हणून चिन्हांकित करा';
$string['btn_reject']                 = 'नाकारा';
$string['btn_redraft']                = 'पुन्हा मसुदा';
$string['approved_ready']             = 'पाठवण्यास तयार';

// Analytics
$string['analytics_pagetitle']        = 'चॅनेल विश्लेषण';
$string['analytics_heading']          = 'WhatsApp / SMS / ईमेल चॅनेल मिश्रण';

// Privacy
$string['privacy:metadata:local_sentientia_user_channel_prefs']
    = 'WhatsApp / SMS / ईमेल चॅनेलसाठी प्रति-वापरकर्ता ऑप्ट-इन प्राधान्ये.';
$string['privacy:metadata:local_sentientia_user_channel_prefs:userid']
    = 'ज्या वापरकर्त्याचे हे प्राधान्य आहे.';
$string['privacy:metadata:local_sentientia_user_channel_prefs:mobile_number']
    = 'देश कोडसह मोबाइल नंबर.';
$string['privacy:metadata:local_sentientia_user_channel_prefs:whatsapp_optin']
    = 'वापरकर्त्याने WhatsApp संदेशांसाठी ऑप्ट-इन केले आहे का.';
$string['privacy:metadata:local_sentientia_user_channel_prefs:sms_optin']
    = 'वापरकर्त्याने SMS संदेशांसाठी ऑप्ट-इन केले आहे का.';
$string['privacy:metadata:local_sentientia_user_channel_prefs:dlt_consent_at']
    = 'वापरकर्त्याने DLT संमती दिल्याची वेळ.';
$string['privacy:metadata:local_sentientia_user_channel_prefs:dlt_consent_text']
    = 'ऑप्ट-इनच्या वेळी संमती भाषेचा स्नॅपशॉट.';

// C14/F-082 stabilization (2026-05-28) - unified admin landing
$string['admin_index_title'] = 'WhatsApp नियंत्रण पॅनेल';
$string['admin_index_intro'] = 'WhatsApp चॅनेल प्रशासनासाठी एकत्रित लँडिंग.';
$string['stats_sent_week'] = 'पाठवले (7 दिवस)';
$string['stats_active_templates'] = 'सक्रिय टेम्पलेट';
$string['stats_failures_24h'] = 'अपयश (24 तास)';
$string['stats_flag_on'] = 'चालू';
$string['stats_flag_off'] = 'बंद';
$string['stats_flag_label'] = 'फीचर फ्लॅग';
$string['admin_index_quicknav'] = 'त्वरित नेव्हिगेशन';
$string['admin_index_link_templates'] = 'DLT टेम्पलेट व्यवस्थापक';
$string['admin_index_link_templates_desc'] = 'टेम्पलेट व्यवस्थापित करा.';
$string['admin_index_link_analytics'] = 'चॅनेल विश्लेषण';
$string['admin_index_link_analytics_desc'] = 'ट्रेंड आणि अपयश दर.';
$string['admin_index_link_settings'] = 'चॅनेल सेटिंग्ज';
$string['admin_index_link_settings_desc'] = 'API की.';
