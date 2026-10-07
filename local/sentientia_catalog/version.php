<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_catalog';
// C4 (2026-05-29) — public guest storefront LXP restyle: new
// db/feature_flags.php (sentientia.catalog.public_lxp.enabled, default
// OFF) + public.php flag-branched to reuse the member catalog's
// airpay-catalog__* card + carousel language + 16 lang strings.
// C4 follow-up (2026-05-29) — fixed the malformed add-to-cart URL in the
// legacy (flag-OFF) grid: a double '?' (course.php?id=N?action=...) meant
// 'action' was never parsed and add-to-cart no-oped on paid courses; now
// built via moodle_url() like the LXP path already does.
// QA-walk P1 (2026-05-29) — one-click free self-enrolment for internal
// tenants: new classes/enrolment.php (policy + manual-enrol key bypass),
// feature flag sentientia.catalog.free_oneclick_enrol.enabled (default OFF),
// course.php 'enrolnow' action, public.php grid button routing, and a fix to
// cart.php's enrollfree silent-failure (enrol_self no-op on key-gated courses
// reported false success). +4 lang strings ×5 languages.
// C2 fix (2026-09-03) — UAT-SECURITY-POSTURE-2026-09-03.md Critical finding
// C2 (cross-tenant self-enrolment): new catalog_manager::assert_course_
// visible_to_viewer() tenant-visibility guard, called from course.php's
// course lookup, commerce::add_to_cart(), and enrolment::enrol_now() before
// any enrolment write. Degrades to visible=1-only when course.open_path is
// absent (vanilla schema). No new lang strings (uses core 'nopermissions').
// ADR-031 (2026-09-25) — tenant resolution fails closed: only a cross-tenant
// viewer is unscoped; guests are the Public tenant; an unresolved tenant sees
// and enrols in nothing. Purge local_sentientia_catalog caches on deploy.
// Persona pass 2026-09-30 (TRIAGE bundle "Catalog mobile", D8/D10/D12) -- fixes
// only, no flag, no schema/cap change: (D8) the mobile filter bottom sheet no
// longer opens on load (catalog.mustache closes the <details> at <=590px);
// (D12) the NEW/completed badge sits left of the bookmark heart instead of
// under it (course_card.mustache + styles.css); (D10) the category grid item
// gets min-width:0 so a long category name no longer pushes the page 10px past
// a 390px viewport. Bump so the upgrade purges the plugin CSS + template cache.
// Persona pass D2 (2026-09-30) — storefront basket -> order cart hand-off: new
// classes/checkout_bridge.php, flag sentientia.catalog.storefront_checkout.enabled
// (default OFF, db/feature_flags.php), cart.php "Proceed to checkout" branch and
// action, +8 lang strings (en + hi; 2 of them are review-round notices: price differs at
// checkout, free lines left in the basket). OFF: cart.php is unchanged. No schema/cap change.
// Purge local_sentientia_catalog caches and the string cache on deploy.
// ADR-032 course_lookups reader fixes (2026-10-01): category_manager reads local_sentientia_course_category
// (the BizLMS local_custom_category fallback is gone) and its two list methods are bounded by the caller's
// tenant; card type labels from local_sentientia_course_type via the exploded open_identifiedas list, behind the
// default-OFF flag sentientia.catalog.course_type_labels.enabled (OFF: cards unchanged). No schema/cap change.
// The categories table needs local_sentientia_courses 2026100101. Purge local_sentientia_catalog caches on deploy.
// 2026093002: catalog mobile fixes (D8/D10/D12) + D2 storefront checkout bridge behind a default-OFF flag; no schema/cap change
// ADR-032 exams code fix 3 (2026-09-30) — catalog_manager lists ordinary courses only: get_courses(),
// get_trending(), get_new() and get_categories() skip open_coursetype = 1 (BizLMS's online-exam and forum
// pseudo-courses, decision exams.forum_pseudocourses = exclude_from_catalog), as BizLMS's own catalog did. A parity
// fix of what a restored database would otherwise offer, so no flag; no schema/cap change, no lang string.
// Purge local_sentientia_catalog caches on deploy (trending, new_courses and categories are cached).
// 2026100701: owner decision cart.price_source (2026-10-07), a confirmed revenue hole closed. commerce::get_course_price()
// reads the enabled enrol_fee instance (cost, currency; the order cart's own rule), with the config setting
// course_price_<id> only as the fallback for a course that has no fee instance. Before, every course priced through
// enrol_fee (66 in the April 2026 copy, 61 of them Public, INR 100-499) read as Free, the basket stored it as is_free
// and the 'enrollfree' action (no flag) enrolled it through enrolment::enrol_now(), whose paid-course re-check used
// the same config-only price. enrol_now() now also refuses any course the order cart prices
// (cart_manager::get_course_price(), guarded by class_exists). Restores what production shows and charges today, so no
// flag; the storefront_checkout flag stays OFF. No schema/cap change, no lang string. Purge caches on deploy.
// 2026100702: owner decision CRS-14 (2026-10-07). commerce::get_public_catalog() (the public guest storefront) lists ordinary
// courses only, in its COUNT and its SELECT (catalog_manager::ordinary_courses_condition(), the public form of the condition the
// browse lists already use): on the April 2026 copy 5 Public-tenant exam courses would otherwise show to guests, and none can be
// bought or joined (no fee instance, guest and self enrolment disabled). The learner's in-progress rail KEEPS an enrolled
// pseudo-course (Sentientia's exam pages are manager and teacher only, so the enrolled course is a learner's only path to an
// assigned exam) and labels it "Exam" or "Forum" from open_module (+2 lang strings, en + hi) instead of "E-Learning". Same release,
// the "readers that count enrolments" item: get_in_progress() groups by course (no more duplicate rows or debugging notice,
// no more rail shorter than its limit) and counts active enrolments on enabled instances only; the popularity counts of the
// storefront, the homepage picks, get_courses() and get_trending() count learners once (COUNT DISTINCT userid) with the same
// two status filters. A parity fix of what a restored database would otherwise show, so no flag; no schema/cap change.
// Purge local_sentientia_catalog caches on deploy (in_progress, trending, new_courses and categories are cached).
$plugin->version   = 2026100702;  // CRS-14 + readers: storefront excludes pseudo-courses, in-progress rail labels them and lists a course once; no schema/cap change
// 2026100701: cart.price_source: the enrol_fee cost is the price; enrol_now refuses a course the order cart prices; no schema/cap change
// 2026100102: ADR-032 course_lookups reader fixes + course_type_labels flag (default OFF) and exams: catalog lists ordinary courses only; no schema/cap change
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.9-beta';  // 1.0.9: CRS-14 storefront/rail + enrolment-count readers (was 1.0.8-beta)
// tenant::is_cross_tenant() arrived in platform 2026092500 (ADR-031).
$plugin->dependencies = [
    'local_sentientia_platform' => 2026092500,
];
