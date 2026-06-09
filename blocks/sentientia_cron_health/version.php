<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_sentientia_cron_health';
$plugin->version   = 2026051300;
$plugin->requires  = 2024042200;          // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
$plugin->dependencies = [
    'local_sentientia_platform' => 2026051200,
];
