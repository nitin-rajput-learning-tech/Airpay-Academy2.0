<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_gamification';
// 2026-09-02 — badge_manager: schema-safe tenant lookup (no hard dependence on
// the BizLMS {user}.open_path column); first PHPUnit coverage of the award chain.
$plugin->version   = 2026090200;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.2-beta';
