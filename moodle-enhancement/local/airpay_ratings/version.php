<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_ratings';
// W1-3 (2026-05-15) — add write endpoint (submit_rating WS, capability,
// interactive AMD widget). Version bump triggers Moodle to register the new
// db/services.php and db/access.php files.
$plugin->version   = 2026051500;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
