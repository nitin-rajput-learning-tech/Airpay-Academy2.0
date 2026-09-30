<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'सेंटिएंटिया पेज';
$string['privacy_policy'] = 'गोपनीयता नीति';
$string['terms_of_use'] = 'उपयोग की शर्तें';
$string['help_center'] = 'सहायता केंद्र';
$string['contact_us'] = 'हमसे संपर्क करें';

// ── C10 P1 / Gap 3 — tenant-scoped certificate template browser ────
$string['cert_templates_title'] = 'प्रमाणपत्र टेम्पलेट (टेनेंट के अनुसार)';
$string['cert_templates_intro'] = 'प्रमाणपत्र टेम्पलेट उनके टेनेंट दायरे के साथ देखें। संपादन खोलने पर मानक प्रमाणपत्र टेम्पलेट संपादक खुलता है।';
$string['cert_templates_empty'] = 'आपको कोई भी प्रमाणपत्र टेम्पलेट दिखाई नहीं दे रहा है।';
$string['cert_scope_heading'] = 'प्रमाणपत्र टेम्पलेट का टेनेंट दायरा';
$string['cert_scope_heading_desc'] = 'वैकल्पिक मैपिंग जो प्रमाणपत्र टेम्पलेट को टेनेंट तक सीमित करती है। यह केवल तभी लागू होती है जब sentientia.certificate.tenant_scope.enabled फ़ीचर फ़्लैग ON हो।';
$string['cert_template_tenant_map'] = 'टेम्पलेट → टेनेंट मैप (JSON)';
$string['cert_template_tenant_map_desc'] = 'JSON ऑब्जेक्ट जो प्रमाणपत्र टेम्पलेट ID को BizLMS टेनेंट रूट से जोड़ता है: 1 = Airpay, 77 = Public, 177 = ZEEA। जो टेम्पलेट ID सूची में नहीं है, या 0 से जुड़ी है, उसे GLOBAL माना जाता है (हर टेनेंट को दिखाई देती है)। उदाहरण: <code>{"5": 1, "8": 177, "11": 0}</code>। गलत JSON अनदेखा कर दिया जाता है (सब कुछ ग्लोबल माना जाता है)।';
$string['cert_scope_off_notice'] = 'टेनेंट दायरा बंद (OFF) है। हर व्यवस्थापक को सभी टेम्पलेट दिखाई देते हैं — यह मौजूदा प्रोडक्शन व्यवहार जैसा ही है। टेनेंट के अनुसार फ़िल्टर करने के लिए sentientia.certificate.tenant_scope.enabled फ़्लैग चालू करें।';
$string['cert_scope_filtered_notice'] = 'ग्लोबल टेम्पलेट और आपके टेनेंट ({$a}) को सौंपे गए टेम्पलेट दिखाए जा रहे हैं।';
$string['cert_col_template'] = 'टेम्पलेट';
$string['cert_col_scope'] = 'टेनेंट दायरा';
$string['cert_col_issued'] = 'जारी किए गए';
$string['cert_col_actions'] = 'कार्रवाइयाँ';
$string['cert_tenant_global'] = 'ग्लोबल';
$string['cert_action_edit'] = 'संपादित करें';
$string['cert_map_edit_hint'] = 'टेम्पलेट → टेनेंट असाइनमेंट प्लगइन सेटिंग्स में संपादित किए जाते हैं।';
$string['cert_map_edit_link'] = 'टेनेंट मैप संपादित करें';
$string['cert_hidden_count'] = 'आपके टेनेंट दायरे के कारण {$a} टेम्पलेट छिपे हैं।';

// ── QR attendance: what a learner sees after scanning (qr_scan.php) ──
$string['qr_scan_title'] = 'उपस्थिति की पुष्टि';
$string['qr_scan_heading'] = 'उपस्थिति';
$string['qr_back_dashboard'] = 'डैशबोर्ड पर वापस जाएँ';
$string['qr_success_title'] = 'उपस्थिति दर्ज हो गई!';
$string['qr_success_body'] = 'आपकी उपस्थिति सफलतापूर्वक दर्ज कर ली गई है। समय: {$a}।';
$string['qr_already_title'] = 'पहले से चिह्नित है';
$string['qr_already_body'] = 'इस सत्र के लिए आपकी उपस्थिति पहले ही चिह्नित की जा चुकी है, इसलिए इस स्कैन से उसमें कोई बदलाव नहीं हुआ। यदि आपको लगता है कि यह गलत है, तो कृपया अपने ट्रेनर से कहें।';
$string['qr_expired_title'] = 'QR कोड की अवधि समाप्त';
$string['qr_expired_body'] = 'इस QR कोड की अवधि समाप्त हो चुकी है। कृपया अपने ट्रेनर द्वारा दिखाया गया वर्तमान QR कोड स्कैन करें।';
$string['qr_nosession_title'] = 'सत्र नहीं मिला';
$string['qr_nosession_body'] = 'यह सत्र अब मौजूद नहीं है। कृपया अपने ट्रेनर से नया QR कोड माँगें।';
$string['qr_notenrolled_title'] = 'नामांकित नहीं हैं';
$string['qr_notenrolled_body'] = 'आप इस क्लासरूम में नामांकित नहीं हैं, इसलिए आपकी उपस्थिति दर्ज नहीं की गई। कृपया अपने ट्रेनर से संपर्क करें।';
$string['qr_cancelled_title'] = 'क्लासरूम रद्द';
$string['qr_cancelled_body'] = 'यह क्लासरूम रद्द कर दिया गया है, इसलिए आपकी उपस्थिति दर्ज नहीं की गई। कृपया अपने ट्रेनर से संपर्क करें।';
$string['qr_tooearly_title'] = 'उपस्थिति अभी शुरू नहीं हुई';
$string['qr_tooearly_body'] = 'इस सत्र की उपस्थिति {$a} से शुरू होगी, इसलिए आपकी उपस्थिति दर्ज नहीं की गई। कृपया तब QR कोड दोबारा स्कैन करें।';
$string['qr_toolate_title'] = 'उपस्थिति बंद हो चुकी है';
$string['qr_toolate_body'] = 'इस सत्र की उपस्थिति {$a} को बंद हो गई थी, इसलिए आपकी उपस्थिति दर्ज नहीं की गई। कृपया अपने ट्रेनर से संपर्क करें।';
$string['qr_notime_body'] = 'इस सत्र का कोई प्रारंभ समय नहीं है, इसलिए QR कोड से उपस्थिति नहीं ली जा सकती। कृपया अपने ट्रेनर से संपर्क करें।';
$string['qr_error_title'] = 'त्रुटि';
$string['qr_error_unavailable'] = 'इस साइट पर क्लासरूम उपस्थिति उपलब्ध नहीं है।';
$string['qr_error_otherorg'] = 'यह सत्र किसी दूसरे संगठन का है, इसलिए आपकी उपस्थिति दर्ज नहीं की गई।';
$string['qr_error_generic'] = 'उपस्थिति दर्ज नहीं हो सकी। कृपया अपने ट्रेनर से संपर्क करें।';

// ── QR attendance: the trainer's projector page (qr_attendance.php) ──
$string['qr_show_title'] = 'QR उपस्थिति';
$string['qr_show_heading'] = 'उपस्थिति दर्ज करने के लिए स्कैन करें';
$string['qr_show_alt'] = 'QR कोड';
$string['qr_show_nogd'] = 'QR कोड नहीं बन सका। अपने व्यवस्थापक से कहें कि वे जाँच लें कि PHP GD एक्सटेंशन चालू है।';
$string['qr_show_refreshes'] = '{$a} मिनट में रीफ़्रेश होगा';
$string['qr_show_fullscreen'] = 'फ़ुलस्क्रीन';
$string['qr_show_fullscreen_hint'] = 'प्रोजेक्टर के लिए फ़ुलस्क्रीन';
$string['qr_show_refresh'] = 'अभी रीफ़्रेश करें';
$string['qr_show_meta'] = 'सत्र ID: {$a->id} · बनाया गया: {$a->time}';

// Privacy API (null provider).
$string['privacy:metadata'] = 'Sentientia पेज प्लगइन कोई व्यक्तिगत डेटा संग्रहीत नहीं करता। QR उपस्थिति स्कैन क्लासरूम प्लगइन की तालिकाओं में दर्ज होते हैं और उसी प्लगइन के प्राइवेसी प्रदाता में वर्णित हैं।';
