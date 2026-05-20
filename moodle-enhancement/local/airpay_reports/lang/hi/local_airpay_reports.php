<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #52 (2026-05-20) — Hindi (hi) translations for local_airpay_reports.
// Scope: CRUD form, capabilities, status, error messages, and privacy
// metadata for saved report definitions.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे रिपोर्ट्स';

// Capabilities.
$string['airpay_reports:view']   = 'सहेजी गई रिपोर्ट्स देखें';
$string['airpay_reports:manage'] = 'सहेजी गई रिपोर्ट्स प्रबंधित करें';
$string['airpay_reports:export'] = 'सहेजी गई रिपोर्ट्स को CSV में निर्यात करें';

// CRUD actions.
$string['addreport']      = 'रिपोर्ट बनाएँ';
$string['editreport']     = 'रिपोर्ट संपादित करें';
$string['deletereport']   = 'रिपोर्ट हटाएँ';
$string['archivereport']  = 'रिपोर्ट संग्रहीत करें';
$string['activatereport'] = 'रिपोर्ट सक्रिय करें';

// Form section headings.
$string['heading_basic'] = 'रिपोर्ट पहचान';
$string['heading_type']  = 'रिपोर्ट प्रकार';
$string['heading_scope'] = 'संगठन कार्यक्षेत्र';

// Form labels.
$string['report_name']       = 'रिपोर्ट का नाम';
$string['description']       = 'विवरण';
$string['report_type']       = 'रिपोर्ट प्रकार';
$string['report_type_help']  = 'प्रत्येक बिल्ट-इन प्रकार एक अलग क्वेरी चलाता है। Course Completion यूज़र-वार कोर्स प्रगति सूचीबद्ध करता है। Compliance Overview अनिवार्य प्रशिक्षण दर दिखाता है। User Activity लॉगिन एंगेजमेंट ट्रैक करता है। Enrolment Trend मासिक नामांकन मात्रा दिखाता है।';
$string['organisation']      = 'संगठन (टेनेंट कार्यक्षेत्र)';
$string['organisation_help'] = 'रिपोर्ट को अपने पदानुक्रम में किसी विशिष्ट संगठन तक सीमित करें। हर टेनेंट को शामिल करने के लिए "सभी संगठन" पर रखें।';
$string['status']            = 'स्थिति';
$string['status_active']     = 'सक्रिय';
$string['status_archived']   = 'संग्रहीत';

// Errors.
$string['name_required']         = 'रिपोर्ट का नाम आवश्यक है।';
$string['invalid_report_type']   = 'अमान्य रिपोर्ट प्रकार।';
$string['invalidreport']         = 'रिपोर्ट नहीं मिली।';
$string['invalidreporttype']     = 'अमान्य रिपोर्ट प्रकार।';
$string['missingrequiredfields'] = 'कृपया सभी आवश्यक फ़ील्ड भरें।';

// Confirmation dialogs.
$string['confirmdelete']   = 'रिपोर्ट "{$a}" हटाएँ? यह सहेजी गई परिभाषा को स्थायी रूप से हटा देगा। उत्पन्न CSV निर्यात प्रभावित नहीं होते। इसे पूर्ववत नहीं किया जा सकता।';
$string['confirmarchive']  = '"{$a}" संग्रहीत करें? यह सक्रिय सूची से छिपा दिया जाएगा लेकिन ऑडिट के लिए रखा जाएगा। कभी भी पुनः सक्रिय करें।';
$string['confirmactivate'] = '"{$a}" सक्रिय करें? यह मुख्य रिपोर्ट सूची में दिखेगा और मांग पर चलेगा।';

// Toast messages.
$string['report_created']       = 'रिपोर्ट बनाई गई।';
$string['report_updated']       = 'रिपोर्ट अपडेट की गई।';
$string['reportdeleted']        = 'रिपोर्ट हटा दी गई।';
$string['reportstatuschanged']  = 'रिपोर्ट की स्थिति अपडेट की गई।';

// Privacy.
$string['privacy:metadata'] = 'एयरपे रिपोर्ट्स प्लगइन सहेजी गई रिपोर्ट परिभाषाएँ संग्रहीत करता है, लेकिन सीधे यूज़र डेटा निर्यात नहीं करता। उत्पन्न रिपोर्ट्स मौजूदा कोर प्लेटफ़ॉर्म तालिकाओं से यूज़र गतिविधि को एकत्रित कर सकती हैं।';
