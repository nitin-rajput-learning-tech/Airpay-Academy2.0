<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #54 (2026-05-20) — Hindi (hi) translations for local_airpay_org.
// Scope: organization engine — capabilities, settings, CRUD form,
// hierarchy + branding overrides, errors, confirmations, privacy metadata.

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'एयरपे ऑर्गनाइज़ेशन इंजन';

// Capabilities.
$string['airpay_org:manage']                    = 'संगठन प्रबंधित करें';
$string['airpay_org:manage_multiorganizations'] = 'कई संगठन प्रबंधित करें';
$string['airpay_org:manage_ownorganization']    = 'अपना संगठन प्रबंधित करें';
$string['airpay_org:manage_owndepartments']     = 'अपने विभाग प्रबंधित करें';
$string['airpay_org:view']                      = 'संगठन देखें';

// Settings.
$string['settings_heading']      = 'एयरपे ऑर्गनाइज़ेशन सेटिंग्स';
$string['settings_heading_desc'] = 'संगठन पदानुक्रम और टेनेंट प्रबंधन कॉन्फ़िगर करें।';
$string['public_tenant_id']      = 'Public टेनेंट ID';
$string['public_tenant_id_desc'] = 'Public (अतिथि-सामना करने वाले) टेनेंट के लिए costcenter ID। डिफ़ॉल्ट: ऑटो-डिटेक्ट।';

// Errors.
$string['invalidtenant']         = 'अमान्य टेनेंट ID';
$string['orgnotfound']           = 'संगठन नहीं मिला';
$string['migrationcomplete']     = 'local_costcenter से डेटा माइग्रेशन सफलतापूर्वक पूर्ण।';
$string['migrationskipped']      = 'माइग्रेशन छोड़ा गया — local_airpay_org तालिका में पहले से डेटा है।';
$string['sourcetablemissing']    = 'स्रोत तालिका local_costcenter मौजूद नहीं है। माइग्रेट करने के लिए कोई डेटा नहीं।';

// CRUD actions.
$string['addorg']    = 'संगठन जोड़ें';
$string['editorg']   = 'संगठन संपादित करें';
$string['deleteorg'] = 'संगठन हटाएँ';
$string['hideorg']   = 'संगठन छिपाएँ';
$string['showorg']   = 'संगठन दिखाएँ';

// Form section headings.
$string['heading_basic']      = 'पहचान';
$string['heading_hierarchy']  = 'पदानुक्रम';
$string['heading_branding']   = 'ब्रांडिंग (वैकल्पिक)';
$string['heading_visibility'] = 'दृश्यता';

// Form labels.
$string['org_fullname']     = 'पूरा नाम';
$string['org_shortname']    = 'संक्षिप्त नाम';
$string['shortname_help']   = 'इस संगठन के लिए एक अद्वितीय संक्षिप्त पहचानकर्ता। पथ-आधारित फ़िल्टरिंग और URL स्लग में उपयोग होता है। जैसे "AirPay_Acquiring"।';
$string['description']      = 'विवरण';
$string['parent_org']       = 'मूल संगठन';
$string['parent_org_help']  = 'चुनें कि यह संगठन पदानुक्रम में कहाँ बैठता है। नया टेनेंट बनाने के लिए "टॉप-लेवल टेनेंट" चुनें। पदानुक्रम स्वचालित रूप से गणना होता है — गहराई और पथ मूल से वंशागत होते हैं।';
$string['top_level_tenant'] = '— टॉप-लेवल टेनेंट —';
$string['brand_color']      = 'ब्रांड रंग (hex)';
$string['button_color']     = 'बटन रंग (hex)';
$string['hover_color']      = 'हॉवर रंग (hex)';
$string['theme_scheme']     = 'थीम स्कीम';
$string['branding_help']    = 'airpayux थीम के लिए प्रति-टेनेंट ओवरराइड। साइट डिफ़ॉल्ट इस्तेमाल करने के लिए खाली छोड़ें। Hex प्रारूप जैसे #0066A7।';
$string['visible']          = 'दृश्यता';
$string['visible_yes']      = 'सक्रिय (यूज़र्स को दिखाई देता है)';
$string['visible_no']       = 'छिपा (केवल एडमिन)';
$string['sortorder']        = 'सॉर्ट क्रम';

// CRUD-specific errors.
$string['missingrequiredfields'] = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['name_required']         = 'संगठन का नाम आवश्यक है।';
$string['invalid_color']         = 'एक मान्य hex रंग का उपयोग करें, जैसे #0066A7।';
$string['invalidparent']         = 'चयनित मूल संगठन मौजूद नहीं है।';
$string['cannotdeletetenant']    = 'टॉप-लेवल टेनेंट हटाए नहीं जा सकते। ऐतिहासिक डेटा बरकरार रखने के लिए इन्हें छिपाएँ।';
$string['orghaschildren']        = 'हटा नहीं सकते: इस संगठन में अभी भी उप-संगठन हैं। पहले उन्हें हटाएँ या स्थानांतरित करें।';
$string['orghasusers']           = 'हटा नहीं सकते: इस संगठन में अभी भी यूज़र्स असाइन हैं। पहले उन्हें पुनः असाइन करें।';

// Confirmation dialogs.
$string['confirmdelete'] = '"{$a}" हटाएँ? यह संगठन को स्थायी रूप से हटा देगा। यदि इसमें उप-संगठन या असाइन किए गए यूज़र्स हैं तो कार्रवाई अवरुद्ध है।';
$string['confirmhide']   = '"{$a}" छिपाएँ? मौजूदा यूज़र्स अपनी असाइनमेंट बनाए रखते हैं, लेकिन संगठन पिकर और फ़िल्टर में नहीं दिखेगा।';
$string['confirmshow']   = '"{$a}" दिखाएँ? यह पिकर और फ़िल्टर में फिर से दिखेगा।';

// Toast messages.
$string['org_created']          = 'संगठन बनाया गया।';
$string['org_updated']          = 'संगठन अपडेट किया गया।';
$string['orgdeleted']           = 'संगठन हटा दिया गया।';
$string['orgvisibilitychanged'] = 'संगठन दृश्यता अपडेट की गई।';

// Privacy.
$string['privacy:metadata'] = 'एयरपे संगठन प्लगइन प्लगइन-स्वामित्व वाली तालिकाओं में व्यक्तिगत डेटा संग्रहीत नहीं करता है; यूज़र स्थिति संबंधित प्रदाताओं द्वारा निर्यात की गई कोर Moodle तालिकाओं पर रहती है।';
