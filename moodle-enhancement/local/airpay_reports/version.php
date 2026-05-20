<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_reports';
// P1 #52 (2026-05-20) — Hindi pack: 34 strings (CRUD form, capabilities,
// errors, status, confirms, toasts, privacy metadata).
$plugin->version   = 2026052001;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.1'; // +P1 #52 Hindi pack
$plugin->dependencies = ['local_airpay_org' => 2026041600];
