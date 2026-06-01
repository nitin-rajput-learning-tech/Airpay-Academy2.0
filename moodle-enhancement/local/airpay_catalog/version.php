<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_catalog';
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
$plugin->version   = 2026060100;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.3-beta';
