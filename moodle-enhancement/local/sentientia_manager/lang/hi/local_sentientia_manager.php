<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #52 (2026-05-20) — Hindi (hi) translations for local_sentientia_manager.
// Scope: team dashboard, request/allocation workflow, error messages,
// and privacy metadata for both requests + allocations tables.

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'एयरपे मैनेजर डैशबोर्ड';
$string['myteam']           = 'मेरी टीम';
$string['teamlearning']     = 'टीम लर्निंग डैशबोर्ड';

// Empty-state — manager dashboard with no direct reports (QA Walk T-03).
$string['emptyteam_title']   = 'अभी तक आपको कोई टीम सदस्य नहीं सौंपा गया है।';
$string['emptyteam_message'] = 'प्रत्यक्ष अधीनस्थ यूज़र प्रोफ़ाइल में सुपरवाइज़र फ़ील्ड के माध्यम से सौंपे जाते हैं।';
$string['privacy:metadata'] = 'मैनेजर डैशबोर्ड प्लगइन अनुपालन / ऑडिट उद्देश्यों के लिए मैनेजर द्वारा किए गए नामांकन अनुरोध और कोर्स आवंटन रिकॉर्ड करता है।';

// Capability descriptions.
$string['sentientia_manager:view']     = 'टीम डैशबोर्ड + अनुरोध + आवंटन देखें';
$string['sentientia_manager:approve']  = 'नामांकन अनुरोध स्वीकृत / अस्वीकृत करें';
$string['sentientia_manager:allocate'] = 'अधीनस्थों को कोर्स असाइन करें';

// Message providers (db/messages.php).
$string['messageprovider:request_decided']     = 'आपके नामांकन अनुरोध का परिणाम';
$string['messageprovider:allocation_assigned'] = 'आपके मैनेजर द्वारा असाइन किया गया कोर्स';

// Phase B errors.
$string['duplicaterequest']        = 'इस कोर्स के लिए आपका पहले से एक लंबित अनुरोध है।';
$string['alreadydecided']          = 'इस अनुरोध पर पहले ही निर्णय लिया जा चुका है।';
$string['notdirectreport']         = 'चुना गया यूज़र आपका प्रत्यक्ष अधीनस्थ नहीं है।';
$string['duplicateallocation']     = 'इस यूज़र के लिए उस कोर्स का आवंटन पहले से मौजूद है।';
$string['manualenrolnotavailable'] = 'इस कोर्स में मैनुअल नामांकन सक्षम नहीं है। कोर्स की नामांकन विधियों में इसे कॉन्फ़िगर करें।';
$string['filterstoolong']          = 'फ़िल्टर ब्लॉब 4 KB सीमा से अधिक है।';

// Privacy provider strings — requests.
$string['privacy:metadata:requests']                 = 'मैनेजर अनुमोदन की प्रतीक्षा में शिक्षार्थियों से नामांकन अनुरोध।';
$string['privacy:metadata:requests:userid']          = 'अनुरोध करने वाला शिक्षार्थी।';
$string['privacy:metadata:requests:courseid']        = 'अनुरोधित कोर्स।';
$string['privacy:metadata:requests:managerid']       = 'निर्णय लेने वाला नियुक्त मैनेजर।';
$string['privacy:metadata:requests:status']          = 'pending | approved | rejected | cancelled।';
$string['privacy:metadata:requests:reason']          = 'शिक्षार्थी को कोर्स की आवश्यकता क्यों है (मुक्त पाठ)।';
$string['privacy:metadata:requests:decision_reason'] = 'स्वीकृत/अस्वीकृत करते समय मैनेजर का नोट।';
$string['privacy:metadata:requests:decided_by']      = 'जिस यूज़र ने अनुमोदित/अस्वीकृत किया उसकी ID।';
$string['privacy:metadata:requests:decided_at']      = 'निर्णय कब लिया गया।';
$string['privacy:metadata:requests:timecreated']     = 'अनुरोध कब दर्ज किया गया।';

// Privacy provider strings — allocations.
$string['privacy:metadata:allocations']             = 'मैनेजर-संचालित कोर्स आवंटन प्रत्यक्ष अधीनस्थों को।';
$string['privacy:metadata:allocations:managerid']   = 'आवंटन बनाने वाला मैनेजर।';
$string['privacy:metadata:allocations:userid']      = 'जिस शिक्षार्थी को कोर्स असाइन है।';
$string['privacy:metadata:allocations:courseid']    = 'आवंटित कोर्स।';
$string['privacy:metadata:allocations:due_date']    = 'वैकल्पिक डेडलाइन।';
$string['privacy:metadata:allocations:status']      = 'assigned | in_progress | completed | overdue | cancelled।';
$string['privacy:metadata:allocations:note']        = 'आवंटन से जुड़ा मैनेजर का नोट।';
$string['privacy:metadata:allocations:timecreated'] = 'आवंटन कब बनाया गया।';
