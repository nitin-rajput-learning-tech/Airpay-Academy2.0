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
 * The PWA manifest (theme/sentientia/pix/brand/manifest.json) and install
 * banner (theme/sentientia/templates/footer.mustache) pre-existed Phase B.1.
 * The service worker is the missing piece that makes Chrome treat the
 * site as an installable PWA — without an active SW, beforeinstallprompt
 * never fires.
 *
 * @package local_sentientia_pwa
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_pwa';
// 2026-05-23 Phase B.3 — migrate before_standard_top_of_body_html to
// Moodle 5.2's new hook system (\core\hook\output\...). Added
// classes/hook_callbacks.php + db/hooks.php; lib.php function reduced
// to a 5.1 backward-compat shim that delegates to the hook class.
$plugin->version   = 2026080401;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // Crypto audit non-blocking sweep: NB #7-#15
$plugin->release   = '0.5.3-alpha';    // Phase B.3 hook migration
$plugin->dependencies = [
    'local_sentientia_platform' => 2026051200,  // feature_flags resolver
];
