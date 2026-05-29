<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  DRAFT — MACHINE-ASSISTED HINDI — PENDING L&D REVIEW              ║
 * ║  DO NOT DEPLOY to admin/tool/certificate/lang/hi/ until an Airpay ║
 * ║  L&D Hindi reviewer has signed off (CLAUDE.md §12: "Compliance    ║
 * ║  content needs L&D review before publish").                       ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * C10 P1 / Gap 4 (2026-05-29) — Hindi pack for the vendored
 * `tool_certificate` plugin (the last English-only admin surface on the
 * platform). 173 strings + 3 deprecated, mirroring lang/en at the time
 * of writing.
 *
 * WHY THIS LIVES IN docs/translations/ AND NOT IN THE PLUGIN
 * Moodle's language resolver has no per-plugin feature-flag hook — the
 * instant a `lang/hi/tool_certificate.php` file exists in the plugin
 * dir, every hi-language user sees it. There is no runtime gate. So the
 * ONLY enforceable "keep EN until reviewed" mechanism is to keep this
 * draft OUT of the active lang dir. This staging copy is committed for
 * in-repo review; activation is the deliberate, post-review step
 * documented in docs/translations/README.md.
 *
 * SCOPE CAUTION FOR THE REVIEWER
 * Certificate *content* printed on the PDF (recipient name, course
 * title, dates, signatures) comes from each template's admin-authored
 * elements, NOT from these strings — so reviewing this pack does not
 * change existing issued certificates. These strings drive the admin
 * editor UI, the "My certificates" learner page chrome, and event/
 * notification labels. Translate for clarity; keep all {$a...}
 * placeholders, HTML tags and URLs byte-for-byte intact.
 *
 * @package    tool_certificate
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcertpage'] = 'नया पृष्ठ';
$string['addelement'] = 'तत्व जोड़ें';
$string['addelementwithname'] = '\'{$a}\' तत्व जोड़ें';
$string['after'] = 'के बाद';
$string['aissueswerecreated'] = '{$a} प्रमाणपत्र जारी किए गए';
$string['aligncentre'] = 'केंद्र';
$string['alignleft'] = 'बाएँ';
$string['alignment'] = 'पाठ संरेखण';
$string['alignment_help'] = 'पाठ का दायाँ संरेखण इसका अर्थ यह है कि तत्व के निर्देशांक (स्थिति X और स्थिति Y) पाठ बॉक्स के ऊपरी दाएँ कोने को संदर्भित करेंगे; केंद्र संरेखण में वे ऊपरी मध्य को और बाएँ संरेखण में ऊपरी बाएँ कोने को संदर्भित करेंगे।';
$string['alignright'] = 'दाएँ';
$string['allowfilters'] = 'PDF सामग्री के लिए अनुमत फ़िल्टर';
$string['allowfilters_desc'] = 'केवल चयनित फ़िल्टर (यदि सक्षम हों) प्रमाणपत्र PDF के अंदर के पाठ पर लागू होंगे।';
$string['archived'] = 'संग्रहीत';
$string['availableincourses'] = 'उप-श्रेणियों और पाठ्यक्रमों में उपलब्ध';
$string['availableincourses_help'] = 'इस विकल्प को सक्षम करने पर, (जारी करने की अनुमति वाले) उपयोगकर्ता चयनित श्रेणी के प्रत्येक पाठ्यक्रम में तथा उसकी उप-श्रेणियों के पाठ्यक्रमों में भी इस टेम्पलेट का उपयोग कर सकेंगे। यदि यह विकल्प अक्षम है, तो यह टेम्पलेट केवल चयनित श्रेणी में जारी करने की अनुमति वाले उपयोगकर्ताओं के लिए ही उपलब्ध होगा।';
$string['certificate'] = 'प्रमाणपत्र';
$string['certificate:image'] = 'प्रमाणपत्र छवियाँ प्रबंधित करें';
$string['certificate:issue'] = 'उपयोगकर्ताओं को प्रमाणपत्र जारी करें';
$string['certificate:manage'] = 'प्रमाणपत्र प्रबंधित करें';
$string['certificate:verify'] = 'किसी भी प्रमाणपत्र को सत्यापित करें';
$string['certificate:viewallcertificates'] = 'सभी जारी किए गए प्रमाणपत्र और टेम्पलेट देखें';
$string['certificate_customfield'] = 'प्रमाणपत्र कस्टम फ़ील्ड';
$string['certificatecopy'] = '{$a} (प्रतिलिपि)';
$string['certificateelement'] = 'प्रमाणपत्र तत्व';
$string['certificateimages'] = 'प्रमाणपत्र छवियाँ';
$string['certificates'] = 'प्रमाणपत्र';
$string['certificatesettings'] = 'प्रमाणपत्र सेटिंग्स';
$string['certificatesissues'] = 'जारी किए गए प्रमाणपत्र';
$string['certificatetemplate'] = 'प्रमाणपत्र टेम्पलेट';
$string['certificatetemplatename'] = 'प्रमाणपत्र टेम्पलेट नाम';
$string['certificatetemplates'] = 'प्रमाणपत्र टेम्पलेट';
$string['changeelementsequence'] = 'आगे लाएँ या पीछे ले जाएँ';
$string['code'] = 'कोड';
$string['codewithlink'] = 'लिंक सहित कोड';
$string['coursecategorywithlink'] = 'लिंक सहित पाठ्यक्रम श्रेणी';
$string['createtemplate'] = 'नया प्रमाणपत्र टेम्पलेट';
$string['customfield_previewvalue'] = 'पूर्वावलोकन मान';
$string['customfield_previewvalue_help'] = 'प्रमाणपत्र टेम्पलेट का पूर्वावलोकन करते समय प्रदर्शित किया जाने वाला मान।';
$string['customfield_visible'] = 'दृश्यमान';
$string['customfield_visible_help'] = 'इस फ़ील्ड को प्रमाणपत्र टेम्पलेट पर चुनने की अनुमति दें।';
$string['customfieldsettings'] = 'सामान्य प्रमाणपत्र कस्टम फ़ील्ड सेटिंग्स';
$string['deleteelement'] = 'तत्व हटाएँ';
$string['deleteelementconfirm'] = 'क्या आप वाकई \'{$a}\' तत्व को हटाना चाहते हैं?';
$string['deletepage'] = 'पृष्ठ हटाएँ';
$string['deletepageconfirm'] = 'क्या आप वाकई इस प्रमाणपत्र पृष्ठ को हटाना चाहते हैं?';
$string['deletetemplateconfirm'] = 'क्या आप वाकई प्रमाणपत्र टेम्पलेट \'{$a}\' और उससे संबंधित सभी डेटा को हटाना चाहते हैं? यह क्रिया पूर्ववत नहीं की जा सकती।';
$string['demotmpl'] = 'प्रमाणपत्र डेमो टेम्पलेट';
$string['demotmplawardedon'] = 'प्रदान करने की तिथि';
$string['demotmplawardedto'] = 'यह प्रमाणपत्र प्रदान किया जाता है';
$string['demotmplbackground'] = 'पृष्ठभूमि छवि';
$string['demotmplcoursefullname'] = 'पाठ्यक्रम का पूरा नाम';
$string['demotmpldirector'] = 'विद्यालय निदेशक';
$string['demotmplforcompleting'] = 'पाठ्यक्रम पूरा करने के लिए';
$string['demotmplissueddate'] = 'जारी करने की तिथि';
$string['demotmplqrcode'] = 'QR कोड';
$string['demotmplsignature'] = 'हस्ताक्षर';
$string['demotmplusername'] = 'उपयोगकर्ता नाम';
$string['do_not_show'] = 'न दिखाएँ';
$string['duplicate'] = 'प्रतिलिपि बनाएँ';
$string['duplicatetemplateconfirm'] = 'क्या आप वाकई \'{$a}\' टेम्पलेट की प्रतिलिपि बनाना चाहते हैं?';
$string['editelement'] = '\'{$a}\' संपादित करें';
$string['editelementname'] = 'तत्व नाम संपादित करें';
$string['editpage'] = 'पृष्ठ {$a} संपादित करें';
$string['edittemplatename'] = 'टेम्पलेट नाम संपादित करें';
$string['elementname'] = 'तत्व नाम';
$string['elementname_help'] = 'प्रमाणपत्र संपादित करते समय इस तत्व की पहचान के लिए यह नाम उपयोग किया जाएगा। ध्यान दें कि यह PDF पर प्रदर्शित नहीं होगा।';
$string['elementwidth'] = 'चौड़ाई';
$string['elementwidth_help'] = 'तत्व की चौड़ाई निर्दिष्ट करें। शून्य (0) का अर्थ है कि चौड़ाई पर कोई बंधन नहीं है।';
$string['entitycertificate'] = 'प्रमाणपत्र';
$string['entitycertificateissue'] = 'जारी किया गया प्रमाणपत्र';
$string['eventcertificateissued'] = 'प्रमाणपत्र जारी किया गया';
$string['eventcertificateregenerated'] = 'प्रमाणपत्र पुनः उत्पन्न किया गया';
$string['eventcertificaterevoked'] = 'प्रमाणपत्र निरस्त किया गया';
$string['eventcertificateverified'] = 'प्रमाणपत्र सत्यापित किया गया';
$string['eventtemplatecreated'] = 'टेम्पलेट बनाया गया';
$string['eventtemplatedeleted'] = 'टेम्पलेट हटाया गया';
$string['eventtemplateupdated'] = 'टेम्पलेट अद्यतन किया गया';
$string['expired'] = 'समाप्त';
$string['expiredcertificate'] = 'यह प्रमाणपत्र समाप्त हो चुका है';
$string['expirydate'] = 'समाप्ति तिथि';
$string['expirydatetype'] = 'समाप्ति तिथि प्रकार';
$string['font'] = 'फ़ॉन्ट';
$string['font_help'] = 'इस तत्व को उत्पन्न करते समय उपयोग किया जाने वाला फ़ॉन्ट।';
$string['fontcolour'] = 'रंग';
$string['fontcolour_help'] = 'फ़ॉन्ट का रंग।';
$string['fontsize'] = 'आकार';
$string['fontsize_help'] = 'फ़ॉन्ट का आकार पॉइंट में।';
$string['hideshow'] = 'छिपाएँ/दिखाएँ';
$string['invalidcolour'] = 'अमान्य रंग चुना गया। कृपया एक मान्य HTML रंग नाम, या छह-अंकीय, या तीन-अंकीय हेक्साडेसिमल रंग दर्ज करें।';
$string['invalidelementwidth'] = 'कृपया एक धनात्मक संख्या दर्ज करें।';
$string['invalidheight'] = 'ऊँचाई 0 से बड़ी एक मान्य संख्या होनी चाहिए।';
$string['invalidmargin'] = 'मार्जिन 0 से बड़ी एक मान्य संख्या होनी चाहिए।';
$string['invalidposition'] = 'कृपया स्थिति {$a} के लिए एक धनात्मक संख्या चुनें।';
$string['invalidwidth'] = 'चौड़ाई 0 से बड़ी एक मान्य संख्या होनी चाहिए।';
$string['issuecertificates'] = 'प्रमाणपत्र जारी करें';
$string['issuedcertificates'] = 'जारी किए गए प्रमाणपत्र';
$string['issueddate'] = 'जारी करने की तिथि';
$string['issuelang'] = 'उपयोगकर्ता की भाषा में प्रमाणपत्र जारी करें';
$string['issuelangdesc'] = 'बहुभाषी साइटों पर जब उपयोगकर्ता की भाषा साइट की भाषा से भिन्न होती है, तो प्रमाणपत्र उपयोगकर्ता की भाषा में उत्पन्न किए जाएँगे; अन्यथा सभी प्रमाणपत्र साइट की डिफ़ॉल्ट भाषा में उत्पन्न किए जाएँगे।';
$string['issuenotallowed'] = 'आपको इस टेम्पलेट से प्रमाणपत्र जारी करने की अनुमति नहीं है।';
$string['issueormangenotallowed'] = 'आपको इस टेम्पलेट से प्रमाणपत्र जारी करने या इसे प्रबंधित करने की अनुमति नहीं है।';
$string['leftmargin'] = 'बायाँ मार्जिन';
$string['leftmargin_help'] = 'यह प्रमाणपत्र PDF का बायाँ मार्जिन मिमी में है।';
$string['linkedinorganizationid'] = 'LinkedIn संगठन आईडी';
$string['linkedinorganizationid_desc'] = 'प्रमाणपत्र जारी करने वाले LinkedIn संगठन की आईडी।

मुझे अपनी LinkedIn संगठन आईडी कहाँ मिलेगी?

1.    अपने व्यवसाय के संगठन पृष्ठ के व्यवस्थापक के रूप में LinkedIn में लॉग इन करें
2.    व्यवस्थापक के रूप में लॉग इन होने पर उपयोग किए गए URL की जाँच करें। (URL "https://linkedin.com/company/xxxxxxx/admin" जैसा दिखना चाहिए)
3.    आपकी LinkedIn संगठन आईडी URL में मौजूद सात-अंकीय संख्या होगी (ऊपर दिए गए चरण में "xxxxxxx" के रूप में दर्शाई गई)';
$string['manageelementplugins'] = 'प्रमाणपत्र तत्व प्लगइन प्रबंधित करें';
$string['managetemplates'] = 'प्रमाणपत्र टेम्पलेट प्रबंधित करें';
$string['messageprovider:certificateissued'] = 'प्रमाणपत्र प्राप्त हुआ';
$string['milimeter'] = 'मिमी';
$string['mycertificates'] = 'मेरे प्रमाणपत्र';
$string['mycertificatesdescription'] = 'ये वे प्रमाणपत्र हैं जो आपको ईमेल द्वारा या मैन्युअल रूप से डाउनलोड करके जारी किए गए हैं।';
$string['name'] = 'नाम';
$string['nametoolong'] = 'आपने नाम के लिए अनुमत अधिकतम लंबाई पार कर दी है।';
$string['never'] = 'कभी नहीं';
$string['noimage'] = 'कोई छवि नहीं';
$string['noissueswerecreated'] = 'कोई प्रमाणपत्र जारी नहीं किया गया';
$string['notificationmsgcertificateissued'] = 'नमस्ते {$a->fullname},<br /><br />आपका प्रमाणपत्र उपलब्ध है! आप इसे यहाँ पाएँगे:
<a href="{$a->url}">मेरे प्रमाणपत्र</a>';
$string['notificationsubjectcertificateissued'] = 'आपका प्रमाणपत्र उपलब्ध है!';
$string['notverified'] = 'सत्यापित नहीं';
$string['numberofpages'] = 'पृष्ठों की संख्या';
$string['oneissuewascreated'] = 'एक प्रमाणपत्र जारी किया गया';
$string['page'] = 'पृष्ठ {$a}';
$string['pageheight'] = 'पृष्ठ की ऊँचाई';
$string['pageheight_help'] = 'यह प्रमाणपत्र PDF की ऊँचाई मिमी में है। संदर्भ के लिए, A4 कागज़ 297 मिमी ऊँचा होता है और लेटर 279 मिमी ऊँचा होता है।';
$string['pagewidth'] = 'पृष्ठ की चौड़ाई';
$string['pagewidth_help'] = 'यह प्रमाणपत्र PDF की चौड़ाई मिमी में है। संदर्भ के लिए, A4 कागज़ 210 मिमी चौड़ा होता है और लेटर 216 मिमी चौड़ा होता है।';
$string['pluginname'] = 'प्रमाणपत्र प्रबंधक';
$string['posx'] = 'स्थिति X';
$string['posx_help'] = 'यह वह स्थिति है (मिमी में, ऊपरी बाएँ कोने से) जहाँ आप तत्व के संदर्भ बिंदु को X दिशा में रखना चाहते हैं।';
$string['posy'] = 'स्थिति Y';
$string['posy_help'] = 'यह वह स्थिति है (मिमी में, ऊपरी बाएँ कोने से) जहाँ आप तत्व के संदर्भ बिंदु को Y दिशा में रखना चाहते हैं।';
$string['privacy:metadata:tool_certificate:issues'] = 'जारी किए गए प्रमाणपत्रों की सूची';
$string['privacy:metadata:tool_certificate_issues:code'] = 'प्रमाणपत्र से संबंधित कोड';
$string['privacy:metadata:tool_certificate_issues:expires'] = 'वह समय-चिह्न जब प्रमाणपत्र समाप्त होता है। यदि समाप्त नहीं होता तो 0।';
$string['privacy:metadata:tool_certificate_issues:templateid'] = 'प्रमाणपत्र की आईडी';
$string['privacy:metadata:tool_certificate_issues:timecreated'] = 'वह समय जब प्रमाणपत्र जारी किया गया';
$string['privacy:metadata:tool_certificate_issues:userid'] = 'उस उपयोगकर्ता की आईडी जिसे प्रमाणपत्र जारी किया गया';
$string['reg_wpcertificates'] = 'प्रमाणपत्रों की संख्या ({$a})';
$string['reg_wpcertificatesissues'] = 'जारी किए गए प्रमाणपत्रों की संख्या ({$a})';
$string['regenerate'] = 'पुनः उत्पन्न करें';
$string['regeneratefileconfirm'] = 'क्या आप वाकई इस उपयोगकर्ता को जारी किए गए प्रमाणपत्र को पुनः उत्पन्न करना चाहते हैं?';
$string['regenerateissuefile'] = 'जारी फ़ाइल पुनः उत्पन्न करें';
$string['revoke'] = 'निरस्त करें';
$string['revokecertificateconfirm'] = 'क्या आप वाकई इस उपयोगकर्ता से इस प्रमाणपत्र को निरस्त करना चाहते हैं?';
$string['rightmargin'] = 'दायाँ मार्जिन';
$string['rightmargin_help'] = 'यह प्रमाणपत्र PDF का दायाँ मार्जिन मिमी में है।';
$string['selectdate'] = 'तिथि चुनें';
$string['selectuserstoissuecertificatefor'] = 'प्रमाणपत्र जारी करने के लिए उपयोगकर्ता चुनें';
$string['shared'] = 'साझा';
$string['shareonlinkedin'] = 'LinkedIn पर साझा करें';
$string['show_link_to_certificate_page'] = 'प्रमाणपत्र पृष्ठ का लिंक दिखाएँ';
$string['show_link_to_verification_page'] = 'सत्यापन पृष्ठ का लिंक दिखाएँ';
$string['show_shareonlinkedin'] = 'LinkedIn पर साझा करें दिखाएँ';
$string['show_shareonlinkedin_desc'] = 'क्या "मेरे प्रमाणपत्र" पृष्ठ पर "LinkedIn पर साझा करें" बटन दिखाया जाना चाहिए। प्रमाणपत्र PDF से सीधे लिंक करना अधिक दृश्यात्मक है परंतु समाप्त हो चुके प्रमाणपत्रों के लिए त्रुटियाँ दिखा सकता है।';
$string['status'] = 'स्थिति';
$string['subplugintype_certificateelement'] = 'प्रमाणपत्र तत्व प्लगइन';
$string['subplugintype_certificateelement_plural'] = 'प्रमाणपत्र तत्व प्लगइन';
$string['template'] = 'टेम्पलेट';
$string['templatepermission'] = 'टेम्पलेट तक पहुँचने की अनुमति';
$string['templatepermissionany'] = 'जाँच न करें';
$string['templatepermissionyes'] = 'वर्तमान उपयोगकर्ता की अनुमति जाँचें';
$string['timecreated'] = 'बनाने का समय';
$string['uploadimage'] = 'छवि अपलोड करें';
$string['valid'] = 'मान्य';
$string['validcertificate'] = 'यह प्रमाणपत्र मान्य है';
$string['verified'] = 'सत्यापित';
$string['verify'] = 'सत्यापित करें';
$string['verifycertificates'] = 'प्रमाणपत्र सत्यापित करें';
$string['verifynotallowed'] = 'आपको प्रमाणपत्र सत्यापित करने की अनुमति नहीं है।';
$string['viewcertificate'] = 'प्रमाणपत्र देखें';

// Deprecated since 4.2.
$string['editcertificate'] = 'प्रमाणपत्र टेम्पलेट \'{$a}\' संपादित करें';
$string['issuenewcertificate'] = 'इस टेम्पलेट से प्रमाणपत्र जारी करें';
$string['nopermissionform'] = 'आपको इस फ़ॉर्म तक पहुँचने की अनुमति नहीं है।';
