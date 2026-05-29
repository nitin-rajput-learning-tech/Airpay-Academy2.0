<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_catalog';
// C4 (2026-05-29) — public guest storefront LXP restyle: new
// db/feature_flags.php (sentientia.catalog.public_lxp.enabled, default
// OFF) + public.php flag-branched to reuse the member catalog's
// airpay-catalog__* card + carousel language + 16 lang strings.
$plugin->version   = 2026052900;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.1-beta';
