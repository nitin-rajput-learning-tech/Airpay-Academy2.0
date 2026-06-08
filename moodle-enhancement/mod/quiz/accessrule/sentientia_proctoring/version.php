<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'quizaccess_sentientia_proctoring';
$plugin->version   = 2026052401;     // Phase B.12 hotfix — defensive table_exists() in upgrade.php
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.1';
$plugin->dependencies = [
    'local_sentientia_proctoring' => 2026051201,
];
