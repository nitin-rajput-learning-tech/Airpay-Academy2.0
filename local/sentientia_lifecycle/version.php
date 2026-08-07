<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_lifecycle';
// 2026080700 — joiner auto-enrol heuristic retired: flag-gated
// (sentientia.lifecycle.autoenrol.enabled, default OFF), mandatory =
// configured course tag, tenant-scoped via open_path (ADR-029).
$plugin->version   = 2026080700;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.1.0-beta';
