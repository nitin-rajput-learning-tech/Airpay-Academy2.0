<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS PWA — service worker + push notifications + offline.
 *
 * Stream B (Tier 1 #2). Phase B.1 ships the service worker scaffold +
 * registration plumbing. Phase B.2 adds the push subscription backend
 * + WS endpoint + DB table. Phase B.3 wires the push sender into the
 * existing notification pipeline.
 *
 * The PWA manifest (theme/airpayux/pix/brand/manifest.json) and install
 * banner (theme/airpayux/templates/footer.mustache) pre-existed Phase B.1.
 * The service worker is the missing piece that makes Chrome treat the
 * site as an installable PWA — without an active SW, beforeinstallprompt
 * never fires.
 *
 * @package local_sentientia_pwa
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_pwa';
$plugin->version   = 2026052001;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.1.0-beta';   // Phase B.1 — service worker + registration
$plugin->dependencies = [
    'local_airpay_core' => 2026051200,  // feature_flags resolver
];
