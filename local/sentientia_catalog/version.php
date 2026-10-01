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
// ADR-032 exams code fix 3 (2026-09-30) — catalog_manager lists ordinary courses only: get_courses(),
// get_trending(), get_new() and get_categories() skip open_coursetype = 1 (BizLMS's online-exam and forum
// pseudo-courses, decision exams.forum_pseudocourses = exclude_from_catalog), as BizLMS's own catalog did. A parity
// fix of what a restored database would otherwise offer, so no flag; no schema/cap change, no lang string.
// Purge local_sentientia_catalog caches on deploy (trending, new_courses and categories are cached).
$plugin->version   = 2026100100;  // ADR-032: catalog lists ordinary courses only (no exam or forum pseudo-courses); no schema/cap change
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.7-beta';
// tenant::is_cross_tenant() arrived in platform 2026092500 (ADR-031).
$plugin->dependencies = [
    'local_sentientia_platform' => 2026092500,
];
