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
$plugin->version   = 2026052901;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.2-beta';
