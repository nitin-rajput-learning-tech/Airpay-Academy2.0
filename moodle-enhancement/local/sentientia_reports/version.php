<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_reports';
// P1 #52 (2026-05-20) — Hindi pack: 34 strings (CRUD form, capabilities,
// errors, status, confirms, toasts, privacy metadata).
$plugin->version   = 2026080401;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.1'; // +P1 #52 Hindi pack
$plugin->dependencies = ['local_sentientia_org' => 2026041600];
