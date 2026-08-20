<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Hindi strings for local_sentientia_core. Started 2026-08-04 with the
// privacy-provider strings; admin-only settings strings fall back to en.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'Sentientia Core प्लगइन local_sentientia_org_member में संगठन-इकाई सदस्यता पंक्तियाँ (उपयोगकर्ता, इकाई, भूमिका, प्रत्यक्ष प्रबंधक) संग्रहीत करता है। टेनेंट रजिस्ट्री स्वयं (ग्राहक + टेनेंट कॉन्फ़िगरेशन: नाम, root id, स्थिति) में कोई व्यक्तिगत डेटा नहीं है।';

// Privacy provider (2026-08-04) — real metadata + export + delete.
$string['privacy:metadata:org_member']             = 'उपयोगकर्ता की संगठन-इकाई सदस्यता: इकाई, भूमिका और प्रत्यक्ष प्रबंधक';
$string['privacy:metadata:org_member:userid']      = 'सदस्य';
$string['privacy:metadata:org_member:unitid']      = 'जिस संगठन इकाई से उपयोगकर्ता संबंधित है';
$string['privacy:metadata:org_member:role']        = 'इकाई के भीतर उपयोगकर्ता की भूमिका';
$string['privacy:metadata:org_member:managerid']   = 'उपयोगकर्ता का प्रत्यक्ष प्रबंधक';
$string['privacy:metadata:org_member:timecreated'] = 'सदस्यता कब दर्ज की गई';

// ── 2026-08-19 parity closure: pre-existing settings strings ─────────
// (The new hi pack shipped with only the privacy strings — these 8 en
// settings strings pre-dated it. Caught by the lang-parity gate.)
$string['settings_tenant_identity'] = 'टेनेंट पहचान';
$string['setting_legacy_openpath'] = 'BizLMS open_path से टेनेंट रिज़ॉल्व करें (legacy)';
$string['setting_legacy_openpath_desc'] = 'सक्षम होने पर (डिफ़ॉल्ट), Sentientia यूज़र का टेनेंट legacy BizLMS <code>open_path</code> प्रोफ़ाइल फ़ील्ड से रिज़ॉल्व करता है — वर्तमान production व्यवहार के समान। इसे OFF करना ADR-018 Wave 3+ के लिए आरक्षित है जब Sentientia tenant registry बन जाए; तब तक सेवा सुरक्षित रूप से <code>open_path</code> पर ही fallback करती है। Production में ON रखें।';
$string['settings_org'] = 'संगठन पदानुक्रम';
$string['setting_org_legacy'] = 'BizLMS से मैनेजर/संगठन रिज़ॉल्व करें (legacy)';
$string['setting_org_legacy_desc'] = 'सक्षम होने पर (डिफ़ॉल्ट), Sentientia यूज़र का मैनेजर legacy BizLMS <code>open_supervisorid</code> फ़ील्ड से रिज़ॉल्व करता है — वर्तमान production व्यवहार के समान। इसे OFF करना ADR-020 Wave 3.2+ के लिए आरक्षित है जब Sentientia org model बन जाए; तब तक सेवा सुरक्षित रूप से <code>open_supervisorid</code> पर fallback करती है। Production में ON रखें।';
$string['setting_org_dualwrite'] = 'Legacy org graph को Sentientia org model में मिरर करें (dual-write)';
$string['setting_org_dualwrite_desc'] = 'सक्षम होने पर, एक scheduled task legacy BizLMS org graph (<code>open_path</code> cost-center tree + <code>open_supervisorid</code> मैनेजर लिंक) को समय-समय पर Sentientia org model तालिकाओं में मिरर करता है। Legacy graph ही source of truth रहता है — यह केवल अंतिम cutover से पहले नई तालिकाओं को तैयार रखता है। डिफ़ॉल्ट OFF (task no-op): केवल parity जाँच या rehearsal हेतु model भरने के लिए ON करें, फिर किसी flip पर विचार से पहले <code>cli/parity_check_org.php</code> चलाएँ। मैनेजर resolution इससे नहीं बदलता — वह ऊपर वाले "BizLMS से मैनेजर/संगठन रिज़ॉल्व करें (legacy)" flag से ही नियंत्रित है।';
$string['task_reconcile_org'] = 'Legacy graph से Sentientia org model का मिलान करें';
$string['error_outoftenant'] = 'आपके पास इस संसाधन की पहुँच नहीं है — यह किसी अन्य टेनेंट का है।';
$string['sentientia_core:managetenants'] = 'Sentientia tenant registry मैनेज करें';
$string['settings_tenant_registry'] = 'टेनेंट registry';
$string['setting_legacy_registry'] = 'Hardcoded allow-list से टेनेंट सत्यापित करें (legacy)';
$string['setting_legacy_registry_desc'] = 'सक्षम होने पर (डिफ़ॉल्ट), Sentientia टेनेंट roots को legacy hardcoded allow-list (<code>[1, 77, 177]</code>) से सत्यापित करता है — वर्तमान production व्यवहार के समान। इसे OFF करने पर Sentientia tenant registry (नीचे का <em>Manage tenant registry</em> पेज) पढ़ी जाती है। OFF केवल तभी करें जब registry seed हो चुकी हो और <code>cli/parity_check_tenants.php</code> से 100% parity पुष्ट हो (पहले clone DB पर rehearse करें)। Cutover तक production में ON रखें।';
$string['error_invalidtenant'] = 'अज्ञात टेनेंट — यह id tenant registry में नहीं है।';
$string['managetenants'] = 'Tenant registry मैनेज करें';
$string['registry_flag_legacy_on'] = 'Tenant registry निष्क्रिय (DORMANT) है — टेनेंट सत्यापन अभी legacy hardcoded allow-list से होता है। यहाँ प्रबंधित पंक्तियाँ तभी प्रभावी होंगी जब "Hardcoded allow-list से टेनेंट सत्यापित करें (legacy)" सेटिंग OFF की जाए।';
$string['registry_flag_legacy_off'] = 'Tenant registry सक्रिय (LIVE) है — टेनेंट सत्यापन अब इन्हीं पंक्तियों को पढ़ता है। यहाँ किसी टेनेंट को हटाने या निलंबित करने से tenant-scoped संसाधनों की पहुँच तुरंत प्रभावित होती है।';
$string['customers'] = 'ग्राहक';
$string['tenants'] = 'टेनेंट';
$string['tenantcount'] = 'टेनेंट';
$string['customer_missing'] = '(अज्ञात ग्राहक)';
$string['nocustomers'] = 'अभी कोई ग्राहक नहीं। नीचे जोड़ें, या cli/seed_tenants.php चलाएँ।';
$string['notenants'] = 'अभी कोई टेनेंट पंजीकृत नहीं। नीचे जोड़ें, या cli/seed_tenants.php चलाएँ।';
$string['actions'] = 'कार्रवाइयाँ';
$string['suspend'] = 'निलंबित करें';
$string['activate'] = 'सक्रिय करें';
$string['tenant_statuschanged'] = 'टेनेंट स्थिति अपडेट हुई।';
$string['customer_saved'] = 'ग्राहक सहेजा गया।';
$string['tenant_saved'] = 'टेनेंट सहेजा गया।';
$string['addcustomer'] = 'ग्राहक जोड़ें';
$string['addtenant'] = 'टेनेंट जोड़ें';
$string['addcustomer_first'] = 'टेनेंट पंजीकृत करने से पहले एक ग्राहक जोड़ें — हर टेनेंट किसी एक ग्राहक का होना चाहिए।';
$string['status_active'] = 'सक्रिय';
$string['status_suspended'] = 'निलंबित';
$string['status_archived'] = 'संग्रहीत';
$string['field_customername'] = 'ग्राहक नाम';
$string['field_shortname'] = 'संक्षिप्त नाम';
$string['field_shortname_help'] = 'ग्राहक के लिए एक छोटा, अद्वितीय, machine-friendly handle (अक्षर, अंक, _ और -)। उदाहरण: <code>airpay</code>। आंतरिक उपयोग हेतु; learners को नहीं दिखता।';
$string['field_status'] = 'स्थिति';
$string['field_rootid'] = 'टेनेंट root id';
$string['field_rootid_help'] = 'टेनेंट root id। आज यह BizLMS cost-center root है (1 = Airpay, 77 = Public, 177 = ZEEA)। धनात्मक पूर्णांक और registry में अद्वितीय होना चाहिए।';
$string['field_customer'] = 'स्वामी ग्राहक';
$string['field_tenantname'] = 'टेनेंट नाम';
$string['field_idnumber'] = 'बाहरी id (वैकल्पिक)';
$string['field_idnumber_help'] = 'एक वैकल्पिक बाहरी key (उदाहरण: कोई HRMS पहचानकर्ता) जिससे यह टेनेंट किसी upstream sync से round-trip हो सके। लागू न हो तो खाली छोड़ें।';
$string['err_shortname_taken'] = 'यह संक्षिप्त नाम पहले से किसी अन्य ग्राहक द्वारा उपयोग में है।';
$string['err_rootid_positive'] = 'टेनेंट root id एक धनात्मक पूर्णांक होना चाहिए।';
$string['err_rootid_taken'] = 'यह टेनेंट root id पहले से पंजीकृत है।';
