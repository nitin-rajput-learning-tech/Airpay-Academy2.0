<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_analytics';
// P1.2 (2026-06-16) — Predictive Analytics + Training ROI surfaces.
$plugin->version   = 2026092500;  // ADR-031: :viewallorgs unscopes only a cross-tenant caller, at system context only
// 2026092401: N5/N6: refusals name the missing capability or org scope; at-risk "Learner" column header
// 2026092400: :viewallorgs has no default grant (tenant admins are manager-archetype); revoke step
// 2026092201: db/access.php: real capability layer (was gating on retired local/courses:manage)
// 2026061600:
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.3-beta';
$plugin->dependencies = [
    // tenant::is_cross_tenant() and tenant::path_descendant_filter().
    'local_sentientia_platform' => ANY_VERSION,
];
