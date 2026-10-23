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
 * Marathi strings for local_sentientia_org.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia संस्था इंजिन';
$string['sentientia_org:manage'] = 'संस्था व्यवस्थापित करा';
$string['sentientia_org:manage_multiorganizations'] = 'अनेक संस्था व्यवस्थापित करा';
$string['sentientia_org:manage_ownorganization'] = 'स्वतःची संस्था व्यवस्थापित करा';
$string['sentientia_org:manage_owndepartments'] = 'स्वतःचे विभाग व्यवस्थापित करा';
$string['sentientia_org:view'] = 'संस्था पहा';
$string['settings_heading'] = 'Airpay संस्था सेटिंग्ज';
$string['settings_heading_desc'] = 'संस्थेची हायरार्की आणि टेनंट व्यवस्थापन कॉन्फिगर करा.';
$string['public_tenant_id'] = 'पब्लिक टेनंट ID';
$string['public_tenant_id_desc'] = 'पब्लिक (गेस्टसाठी दिसणाऱ्या) टेनंटचा कॉस्टसेंटर ID. डीफॉल्ट: ऑटो-डिटेक्ट.';
$string['invalidtenant'] = 'अवैध टेनंट ID';
$string['orgnotfound'] = 'संस्था सापडली नाही';
$string['migrationcomplete'] = 'local_costcenter मधून डेटा मायग्रेशन यशस्वीरीत्या पूर्ण झाले.';
$string['migrationskipped'] = 'मायग्रेशन वगळले — local_sentientia_org टेबलमध्ये आधीच डेटा आहे.';
$string['sourcetablemissing'] = 'सोर्स टेबल local_costcenter अस्तित्वात नाही. मायग्रेट करण्यासाठी कोणताही डेटा नाही.';
$string['addorg'] = 'संस्था जोडा';
$string['editorg'] = 'संस्था एडिट करा';
$string['deleteorg'] = 'संस्था डिलीट करा';
$string['hideorg'] = 'संस्था लपवा';
$string['showorg'] = 'संस्था दाखवा';
$string['heading_basic'] = 'ओळख';
$string['heading_hierarchy'] = 'हायरार्की';
$string['heading_branding'] = 'ब्रँडिंग (ऐच्छिक)';
$string['heading_visibility'] = 'दृश्यता';
$string['org_fullname'] = 'पूर्ण नाव';
$string['org_shortname'] = 'लहान नाव';
$string['shortname_help'] = 'या संस्थेसाठी एक युनिक लहान ओळखकर्ता. पाथ-आधारित फिल्टरिंग आणि URL स्लगमध्ये वापरला जातो. उदा. "AirPay_Acquiring".';
$string['description'] = 'वर्णन';
$string['parent_org'] = 'पॅरेंट संस्था';
$string['parent_org_help'] = 'हायरार्कीमध्ये ही संस्था कुठे असेल ते निवडा. नवीन टेनंट तयार करण्यासाठी "टॉप-लेव्हल टेनंट" निवडा. हायरार्की आपोआप ठरवली जाते — डेप्थ आणि पाथ पॅरेंटकडून येतात.';
$string['top_level_tenant'] = '— टॉप-लेव्हल टेनंट —';
$string['brand_color'] = 'ब्रँड रंग (hex)';
$string['button_color'] = 'बटण रंग (hex)';
$string['hover_color'] = 'होव्हर रंग (hex)';
$string['theme_scheme'] = 'थीम स्कीम';
$string['branding_help'] = 'airpayux थीमसाठी प्रति-टेनंट ओव्हरराइड. साइट डीफॉल्ट वापरण्यासाठी रिकामे सोडा. Hex फॉरमॅट उदा. #0066A7.';
$string['visible'] = 'दृश्यता';
$string['visible_yes'] = 'ॲक्टिव्ह (युजर्सना दिसते)';
$string['visible_no'] = 'लपवलेले (फक्त ॲडमिनसाठी)';
$string['sortorder'] = 'क्रम';
$string['missingrequiredfields'] = 'कृपया सर्व आवश्यक फील्ड भरा.';
$string['name_required'] = 'संस्थेचे नाव आवश्यक आहे.';
$string['invalid_color'] = 'योग्य hex रंग वापरा, उदा. #0066A7.';
$string['invalidparent'] = 'निवडलेली पॅरेंट संस्था अस्तित्वात नाही.';
$string['error_parent_outofscope'] = 'तुमच्या स्वतःच्या संस्थेतील पॅरेंट संस्था निवडा. फक्त प्लॅटफॉर्म (क्रॉस-टेनंट) ॲडमिनिस्ट्रेटरच नवीन टॉप-लेव्हल टेनंट तयार करू शकतो.';
$string['cannotdeletetenant'] = 'टॉप-लेव्हल टेनंट डिलीट करता येत नाहीत. जुना डेटा सुरक्षित ठेवण्यासाठी त्याऐवजी त्यांना लपवा.';
$string['orghaschildren'] = 'डिलीट करता येत नाही: या संस्थेत अजूनही उप-संस्था आहेत. आधी त्या डिलीट करा किंवा दुसरीकडे हलवा.';
$string['orghasusers'] = 'डिलीट करता येत नाही: या संस्थेला अजूनही युजर्स असाइन केलेले आहेत. आधी त्यांना दुसरीकडे असाइन करा.';
$string['confirmdelete'] = '"{$a}" डिलीट करायचे? यामुळे संस्था कायमची काढून टाकली जाईल. उप-संस्था किंवा असाइन केलेले युजर्स असल्यास ही क्रिया थांबवली जाते.';
$string['confirmhide'] = '"{$a}" लपवायचे? सध्याच्या युजर्सचे असाइनमेंट तसेच राहील, पण ही संस्था पिकर आणि फिल्टरमध्ये दिसणार नाही.';
$string['confirmshow'] = '"{$a}" दाखवायचे? ती पिकर आणि फिल्टरमध्ये पुन्हा दिसेल.';
$string['org_created'] = 'संस्था तयार झाली.';
$string['org_updated'] = 'संस्था अपडेट झाली.';
$string['orgdeleted'] = 'संस्था डिलीट झाली.';
$string['orgvisibilitychanged'] = 'संस्थेची दृश्यता अपडेट झाली.';
$string['privacy:metadata'] = 'Airpay sentientia_org प्लगिन त्याच्या स्वतःच्या टेबलमध्ये फक्त एक वैयक्तिक संदर्भ साठवतो: कोहॉर्टचा टेनंट स्कोप शेवटचा बदलणारा ॲडमिनिस्ट्रेटर. संस्था टेबलमध्ये कोणताही वैयक्तिक डेटा नसतो; बाकी युजर स्थिती मुख्य Sentientia LMS टेबलमध्ये असते, जी त्यांचे संबंधित प्रोव्हायडर एक्सपोर्ट करतात.';
$string['privacy:metadata:cohort_scope'] = 'प्रत्येक कोहॉर्टचा टेनंट स्कोप: कोहॉर्ट कोणत्या संस्थेचा आणि विभागांचा आहे, आणि तो शेवटचा कोणी बदलला.';
$string['privacy:metadata:cohort_scope:cohortid'] = 'हा स्कोप ज्या कोहॉर्टचा आहे तो कोहॉर्ट.';
$string['privacy:metadata:cohort_scope:usermodified'] = 'स्कोप शेवटचा बदलणाऱ्या युजरचा id. युजर इरेज केल्यावर हे 0 होते आणि रो तसाच राहतो.';
$string['privacy:metadata:cohort_scope:timemodified'] = 'स्कोप शेवटचा कधी बदलला.';
$string['privacy:subcontext:cohort_scope'] = 'तुम्ही शेवटचे बदललेले कोहॉर्ट टेनंट स्कोप';
$string['org_logo'] = 'संस्थेचा लोगो';
$string['cascade_l1'] = 'संस्था';
$string['cascade_l2'] = 'विभाग';
$string['cascade_l3'] = 'उप-विभाग';
$string['cascade_l4'] = 'लेव्हल 4';
$string['cascade_l5'] = 'लेव्हल 5';
$string['cascade_all_l1'] = 'सर्व संस्था';
$string['cascade_all_l2'] = 'सर्व विभाग';
$string['cascade_all_l3'] = 'सर्व उप-विभाग';
$string['cascade_all_l4'] = 'सर्व लेव्हल-4 युनिट्स';
$string['cascade_all_l5'] = 'सर्व लेव्हल-5 युनिट्स';
