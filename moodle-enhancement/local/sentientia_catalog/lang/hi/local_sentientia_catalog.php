<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंटिया कोर्स कैटलॉग';
$string['catalog'] = 'कोर्स कैटलॉग';
$string['privacy:metadata'] = 'कैटलॉग प्लगइन व्यक्तिगत डेटा संग्रहीत नहीं करता है।';
$string['search'] = 'कोर्स, विषय, कौशल खोजें...';
$string['continuelearning'] = 'सीखना जारी रखें';
$string['trending'] = 'आपके विभाग में ट्रेंडिंग';
$string['newthismonth'] = 'इस महीने नया';
$string['browsecategory'] = 'श्रेणी से ब्राउज़ करें';
$string['allcourses'] = 'सभी कोर्स';
// Phase C 2026-11-19 (V8-B): श्रेणी टाइल पर कोर्स की गिनती (पहले "{n} All Courses")।
$string['category_coursecount'] = '{$a} कोर्स';
$string['newest'] = 'नवीनतम';
$string['popular'] = 'लोकप्रिय';
$string['atoz'] = 'क-ख';
// Phase C 2026-11-19 (V8-B): फ़िल्टर बार का बटन, उसका गिनती बैज और फ़िल्टर समूह (पहले हार्ड-कोडेड अंग्रेज़ी थे)।
$string['filters_sort'] = 'फ़िल्टर और क्रम';
$string['filters_active_aria'] = '{$a} सक्रिय';
$string['filters_group_aria'] = 'सक्रिय फ़िल्टर और क्रम';
$string['nocourses'] = 'कोई कोर्स नहीं मिला।';
$string['loadmore'] = 'और लोड करें';
$string['viewdetails'] = 'विवरण देखें';
// Phase C 2026-11-19 (V8-B): कोर्स कार्ड का NEW बैज और aria-label (templates/course_card.mustache; पहले हार्ड-कोडेड अंग्रेज़ी थे)।
$string['badge_new'] = 'नया';
$string['badge_new_aria'] = 'नया कोर्स';
$string['card_viewdetails_aria'] = '{$a} का विवरण देखें';
$string['card_enrolled_aria'] = '{$a} लर्नर नामांकित';
$string['enroll'] = 'नामांकन करें';
$string['continue'] = 'जारी रखें';

// Sprint C (2026-05-13) — cross-tenant provenance badge.
$string['provenance_provided_by'] = '{$a} द्वारा प्रदान किया गया';

// C4 (2026-05-29) — public guest storefront LXP restyle (2026-08-04 parity closure).
$string['public_popularpicks'] = 'लोकप्रिय पसंद';
$string['public_browseall'] = 'सभी कोर्स ब्राउज़ करें';
$string['public_coursesavailable'] = '{$a} कोर्स उपलब्ध';
$string['public_details'] = 'विवरण';
$string['public_addtocart'] = 'कार्ट में जोड़ें';
$string['public_enrolfree'] = 'निःशुल्क नामांकन करें';
$string['public_free'] = 'निःशुल्क';
$string['public_scrollleft'] = 'बाएँ स्क्रॉल करें';
$string['public_scrollright'] = 'दाएँ स्क्रॉल करें';
$string['public_cart'] = 'कार्ट ({$a})';
$string['public_nocourses'] = 'कोई कोर्स नहीं मिला';
$string['public_nocourses_hint'] = 'कोई दूसरा खोज शब्द आज़माएँ या सभी कोर्स ब्राउज़ करें।';
$string['public_clearsearch'] = 'खोज साफ़ करें';
$string['public_sort_popular'] = 'लोकप्रिय';
$string['public_sort_newest'] = 'नवीनतम';
$string['public_sort_name'] = 'क-ख';

// QA-walk P1 (2026-05-29) — आंतरिक टेनेंट के लिए एक-क्लिक निःशुल्क नामांकन।
$string['enrol_now_free'] = 'अभी नामांकन करें — निःशुल्क';
$string['enrolled_welcome'] = 'आप नामांकित हो गए हैं — पाठ्यक्रम में आपका स्वागत है!';
$string['enrolled_count'] = '{$a} निःशुल्क पाठ्यक्रम(ों) में नामांकित!';
$string['enrolled_none'] = 'हम आपका निःशुल्क नामांकन पूरा नहीं कर सके। कृपया पुनः प्रयास करें या अपने व्यवस्थापक से संपर्क करें।';

// Persona pass D2 (2026-09-30) — स्टोरफ्रंट बास्केट से ऑर्डर कार्ट में स्थानांतरण
// (फ़्लैग sentientia.catalog.storefront_checkout.enabled, डिफ़ॉल्ट बंद)।
$string['storefront_checkout_button'] = 'चेकआउट पर जाएँ';
$string['storefront_checkout_hint'] = 'आपके सशुल्क कोर्स आपके ऑर्डर कार्ट में चले जाते हैं, जहाँ आप बिलिंग विवरण की पुष्टि करते हैं और भुगतान का तरीका चुनते हैं।';
$string['storefront_checkout_moved'] = '{$a} कोर्स चेकआउट में भेजे गए।';
$string['storefront_checkout_refused'] = '{$a} कोर्स चेकआउट में नहीं भेजे जा सके और अब भी आपकी बास्केट में हैं। हो सकता है वे अभी ऑनलाइन बिक्री के लिए उपलब्ध न हों, या आपके खाते के लिए उपलब्ध न हों।';
$string['storefront_checkout_redundant'] = '{$a} कोर्स आपकी बास्केट से हटा दिए गए क्योंकि आप पहले से उनमें नामांकित हैं।';
$string['storefront_checkout_nothing'] = 'आपकी बास्केट के किसी भी सशुल्क कोर्स को चेकआउट में नहीं भेजा जा सका।';
$string['storefront_checkout_pricediffers'] = '{$a} कोर्स की चेकआउट में कीमत आपकी बास्केट में दिखी कीमत से अलग है। भुगतान करने से पहले इस पृष्ठ पर दी गई राशि जाँच लें।';
$string['storefront_checkout_freeleft'] = 'आपकी बास्केट में अब भी {$a} निःशुल्क कोर्स हैं। वे चेकआउट से नहीं गुजरते: उनमें नामांकन के लिए अपनी बास्केट खोलें।';

// Owner decision CRS-14 (2026-10-07) — सीखने वाले की "प्रगति में" सूची में रहने वाले BizLMS छद्म-कोर्स (open_coursetype 1) का कार्ड लेबल, open_module के अनुसार।
$string['coursetype_exam'] = 'परीक्षा';
$string['coursetype_forum'] = 'फ़ोरम';
// Phase C 2026-11-19 (V8-B): कार्ड पर E-Learning और Classroom प्रकार के लेबल (catalog_manager; पहले लिटरल थे, इसलिए हिंदी कैटलॉग में अंग्रेज़ी दिखती थी)।
$string['coursetype_elearning'] = 'ई-लर्निंग';
$string['coursetype_classroom'] = 'क्लासरूम';

// Persona walk 2026-11-18 — course.php अब /enrol/index.php से लिंक नहीं करता (थीम उसे वापस course.php पर भेजती है):
// जहाँ पृष्ठ पर कुछ भी सीखने वाले का नामांकन नहीं कर सकता, वह यह बताता है, कर्मचारी या स्टोरफ्रंट ग्राहक के लिए अलग शब्दों में
// (_public स्ट्रिंग, Phase C CAT-1 2026-11-19: स्टोरफ्रंट ग्राहक की कोई L&D टीम नहीं होती); और जो नामांकन प्रवेश नहीं देता, वह कारण बताता है।
$string['enrolment_managed'] = 'इस कोर्स में नामांकन आपकी L&D टीम द्वारा प्रबंधित किया जाता है।';
$string['enrolment_managed_public'] = 'यह कोर्स अभी ऑनलाइन नामांकन के लिए खुला नहीं है।';
$string['enrolment_suspended'] = 'इस कोर्स में आपका नामांकन निलंबित है। अपनी L&D टीम से संपर्क करें।';
$string['enrolment_suspended_public'] = 'इस कोर्स तक आपकी पहुँच अभी रोकी गई है। सहायता के लिए कृपया हमसे संपर्क करें।';
$string['enrolment_notstarted'] = 'इस कोर्स में आपका नामांकन {$a} से शुरू होगा।';
$string['enrolment_ended'] = 'इस कोर्स में आपका नामांकन {$a} को समाप्त हो गया। अपनी L&D टीम से संपर्क करें।';
$string['enrolment_ended_public'] = 'इस कोर्स तक आपकी पहुँच {$a} को समाप्त हो गई।';
$string['open_course'] = 'कोर्स खोलें';

// Phase C 2026-11-19 (V8-COURSE) — कोर्स पृष्ठ (course.php) हार्ड-कोडेड अंग्रेज़ी की जगह ये स्ट्रिंग दिखाता है।
$string['course_addedtocart_short'] = 'कार्ट में जोड़ा गया।';
$string['course_addedtocart'] = '{$a} आपके कार्ट में जोड़ा गया।';
$string['course_viewcart'] = 'कार्ट देखें ({$a})';
$string['course_enrolledcount'] = '{$a} नामांकित';
$string['course_selfpaced'] = 'अपनी गति से';
$string['course_onetime'] = 'एकमुश्त';
$string['course_addtocart_price'] = 'कार्ट में जोड़ें — {$a}';
$string['course_login'] = 'एक्सेस के लिए लॉग इन करें';
$string['course_about'] = 'इस कोर्स के बारे में';
$string['course_backtocatalog'] = 'कोर्स कैटलॉग पर वापस जाएँ';

// Phase C 2026-11-19 (V8-MYCOURSES) — मेरे कोर्स (mycourses.php) हार्ड-कोडेड अंग्रेज़ी की जगह ये स्ट्रिंग दिखाता है।
$string['mycourses_filter_label'] = 'अपने कोर्स फ़िल्टर करें';
$string['mycourses_all'] = 'सभी कोर्स';
$string['mycourses_inprogress'] = 'प्रगति में';
$string['mycourses_completed'] = 'पूरे हुए';
$string['mycourses_notstarted'] = 'शुरू नहीं हुए';
$string['mycourses_done'] = 'पूरा';
$string['mycourses_percentcomplete'] = '{$a}% पूरा';
$string['mycourses_review'] = 'दोबारा देखें';
$string['mycourses_continue'] = 'जारी रखें';
$string['mycourses_start'] = 'शुरू करें';
$string['mycourses_pagination'] = 'मेरे कोर्स के पृष्ठ';
$string['mycourses_showing'] = '{$a->total} में से {$a->from}–{$a->to} दिखाए जा रहे हैं';
$string['mycourses_empty_title'] = 'कोई कोर्स नहीं मिला';
$string['mycourses_empty_message'] = 'कोर्स खोजने के लिए कैटलॉग देखें।';
$string['mycourses_browse'] = 'कैटलॉग देखें';
