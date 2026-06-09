<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #54 (2026-05-20) — Hindi (hi) translations for local_sentientia_request.
// Scope: course/path access request workflow — navigation, capabilities,
// actions, status, SLA labels, approval routing, notifications, errors,
// settings, UI, privacy metadata, event labels.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे कोर्स अनुरोध';

// Navigation.
$string['myrequests']       = 'मेरे कोर्स अनुरोध';
$string['pendingapprovals'] = 'लंबित अनुमोदन';
$string['allrequests']      = 'सभी अनुरोध';

// Capabilities.
$string['sentientia_request:request']       = 'प्रतिबंधित कोर्सेज़ में नामांकन का अनुरोध करें';
$string['sentientia_request:approve']       = 'कोर्स अनुरोध स्वीकृत / अस्वीकृत करें';
$string['sentientia_request:viewall']       = 'टेनेंट में सभी अनुरोध देखें';
$string['sentientia_request:overrideroute'] = 'अनुमोदन रूटिंग ओवरराइड करें';

// Actions.
$string['requestcourse']      = 'एक्सेस अनुरोध करें';
$string['requestcourse_long'] = 'नामांकन का अनुरोध करें';
$string['approve']            = 'स्वीकृत करें';
$string['reject']             = 'अस्वीकृत करें';
$string['cancel_request']     = 'अनुरोध रद्द करें';
$string['reason']             = 'कारण';
$string['reason_help']        = 'अनुमोदक को बताएँ कि आपको इस कोर्स की क्यों आवश्यकता है (न्यूनतम 200 अक्षर)।';
$string['decision_note']      = 'निर्णय नोट';
$string['decision_note_help'] = 'अनुरोधकर्ता को दिखाई देता है। अस्वीकृति के लिए आवश्यक।';

// Status.
$string['status_pending']   = 'लंबित';
$string['status_approved']  = 'स्वीकृत';
$string['status_rejected']  = 'अस्वीकृत';
$string['status_cancelled'] = 'रद्द';
$string['status_expired']   = 'ऑटो-समाप्त';

// SLA labels.
$string['sla_due_in']    = '{$a} में देय';
$string['sla_overdue']   = 'अतिदेय';
$string['sla_decided']   = 'निर्णीत';
$string['sla_48h']       = '48 घंटे SLA';
$string['sla_escalated'] = 'एस्केलेट किया गया';

// Approval routing.
$string['route_manager']     = 'मैनेजर अनुमोदन';
$string['route_courseowner'] = 'कोर्स स्वामी अनुमोदन';
$string['route_admin']       = 'साइट एडमिन अनुमोदन';

// Notifications.
$string['messageprovider:request_submitted'] = 'अनुरोध सबमिट किया गया (अनुरोधकर्ता)';
$string['messageprovider:request_pending']   = 'अनुरोध को आपकी मंज़ूरी चाहिए (अनुमोदक)';
$string['messageprovider:request_decided']   = 'अनुरोध निर्णय (अनुरोधकर्ता)';
$string['messageprovider:request_escalated'] = 'अनुरोध SLA से एस्केलेट हो गया';

// Errors.
$string['error_reasonshort']      = 'कारण कम से कम 20 अक्षर का होना चाहिए।';
$string['error_alreadyenrolled']  = 'आप इस कोर्स या लर्निंग पाथ में पहले से नामांकित हैं।';
$string['error_alreadyrequested'] = 'इस आइटम के लिए आपका पहले से एक लंबित अनुरोध है।';
$string['error_courseunavailable'] = 'यह कोर्स अनुरोध के लिए उपलब्ध नहीं है।';
$string['error_invalidstate']     = 'इस कार्रवाई के लिए अमान्य अनुरोध स्थिति।';
$string['error_outoftenant']      = 'यह कार्रवाई टेनेंट के पार अनुमत नहीं है।';

// P1 #6 (2026-05-16) — polymorphic requests (path support).
$string['error_path_inactive'] = 'यह लर्निंग पाथ सक्रिय नहीं है और इसका अनुरोध नहीं किया जा सकता।';

// Settings.
$string['settings_sla_hours']             = 'एस्केलेशन से पहले SLA घंटे';
$string['settings_sla_hours_desc']        = 'अनुरोध के ऑटो-एस्केलेट होने से पहले अनुमोदक के पास कितना समय है।';
$string['settings_default_approver']      = 'जब कोई मैनेजर नहीं हो तो डिफ़ॉल्ट अनुमोदक';
$string['settings_default_approver_desc'] = 'जिस यूज़र ID को अनुरोध मिलते हैं जब अनुरोधकर्ता का कोई नियुक्त मैनेजर और कोई कोर्स स्वामी नहीं है।';
$string['settings_auto_expire_days']      = 'N दिनों के बाद ऑटो-समाप्त';
$string['settings_auto_expire_days_desc'] = 'इतने दिनों से लंबित अनुरोध स्वचालित रूप से समाप्त हो जाते हैं। अक्षम करने के लिए 0 सेट करें।';

// UI.
$string['no_requests']                 = 'अभी तक कोई अनुरोध नहीं।';
$string['no_pending']                  = 'कोई लंबित अनुमोदन नहीं — आप अपडेट हैं!';
$string['request_table_col_course']    = 'कोर्स';
$string['request_table_col_requester'] = 'अनुरोधकर्ता';
$string['request_table_col_status']    = 'स्थिति';
$string['request_table_col_requested'] = 'अनुरोधित';
$string['request_table_col_decided']   = 'निर्णीत';
$string['request_table_col_approver']  = 'अनुमोदक';
$string['request_table_col_actions']   = 'क्रियाएँ';

// Privacy.
$string['privacy:metadata:local_sentientia_request']                 = 'कोर्स नामांकन अनुरोध';
$string['privacy:metadata:local_sentientia_request:userid']          = 'अनुरोध करने वाला यूज़र';
$string['privacy:metadata:local_sentientia_request:courseid']        = 'अनुरोधित कोर्स';
$string['privacy:metadata:local_sentientia_request:reason']          = 'अनुरोधकर्ता से मुक्त-पाठ कारण';
$string['privacy:metadata:local_sentientia_request:decision_note']   = 'अनुमोदक का निर्णय नोट';
$string['privacy:metadata:local_sentientia_request:approver_userid'] = 'जिस यूज़र ने अनुरोध पर निर्णय लिया';
$string['privacy:metadata:local_sentientia_request:status']          = 'लंबित / स्वीकृत / अस्वीकृत / आदि।';
$string['privacy:metadata:local_sentientia_request:timecreated']     = 'अनुरोध कब रखा गया';

// W1-9 (2026-05-15) — event names.
$string['event_request_submitted'] = 'एक्सेस अनुरोध सबमिट किया गया';
$string['event_request_approved']  = 'एक्सेस अनुरोध स्वीकृत';
$string['event_request_rejected']  = 'एक्सेस अनुरोध अस्वीकृत';
