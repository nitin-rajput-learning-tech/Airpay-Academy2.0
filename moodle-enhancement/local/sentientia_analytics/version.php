<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_analytics';
// P1.2 (2026-06-16) — Predictive Analytics + Training ROI surfaces.
$plugin->version   = 2026092400;  // :viewallorgs has no default grant (tenant admins are manager-archetype); revoke step
// 2026061600:
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.1-beta';
