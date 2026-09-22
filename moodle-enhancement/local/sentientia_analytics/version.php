<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_analytics';
// P1.2 (2026-06-16) — Predictive Analytics + Training ROI surfaces.
$plugin->version   = 2026092201;  // db/access.php: real capability layer (was gating on retired local/courses:manage)
// 2026061600:
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.0-beta';
