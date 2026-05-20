<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_proctoring';
// P1 #55 (2026-05-20) — Hindi pack: 90 strings covering consent flow,
// identity verification, live monitoring, behavioural events, review queue,
// status, settings, notifications, errors, privacy metadata (incl. AWS).
$plugin->version   = 2026052001;
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.2';     // +P1 #55 Hindi pack
$plugin->dependencies = [
    'local_airpay_org'     => 2026040100,
    'local_airpay_privacy' => 2026040100,  // DSR pipeline integration
    'local_airpay_core'    => 2026051200,  // Shared tenant helper
];
