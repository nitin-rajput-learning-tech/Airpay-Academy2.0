<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
// W1-7 + W1-9 (2026-05-15) — meeting_url/recording_url on sessions PLUS
// emit `classroom_completed` event on STATUS_COMPLETED transition for
// SOX audit + W1-5 evaluation trigger flow.
$plugin->version   = 2026051501;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.8.0';  // W1-7 + W1-9
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
