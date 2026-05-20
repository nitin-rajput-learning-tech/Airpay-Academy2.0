<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_notifications';
// P1 #48 (2026-05-20) — Hindi top-up: 53 strings covering capabilities,
// CRUD form, errors, success, and privacy metadata.
$plugin->version   = 2026052001;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.1'; // +P1 #48 Hindi top-up
