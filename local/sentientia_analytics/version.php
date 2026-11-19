<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_analytics';
// P1.2 (2026-06-16) — Predictive Analytics + Training ROI surfaces.
$plugin->version   = 2026111900;  // 11-19 QW-ROI: Training ROI card prints its figures (index.php flattened keys the template never read), honest empty states, ROI assumptions settings page (settings.php), every label in en+hi; skill gap counts every category per path, '' scope covers every tenant, no N-squared tags
// 2026111801: 11-18 Phase C (V12): funnel Certified = certificate AND a completion (never above Completed); funnel cache key v3
// 2026111801 also carries Phase C addendum (2026-11-19): PB-3 skill-gap team and skill names are formatted once and escaped once (no literal "&amp;"); PB-4 the department heatmap users label sizes to its text, so the Hindi label is no longer cut.
// 2026111800: 11-18 walk: department compliance read from the compliance scorecard; range pills work; strings en+hi; no "&amp;"
// 2026111700: KPIs + funnel count 0 certificates when tool_certificate is not installed (was a DB exception)
// 2026092500: ADR-031: :viewallorgs unscopes only a cross-tenant caller, at system context only
// 2026092401: N5/N6: refusals name the missing capability or org scope; at-risk "Learner" column header
// 2026092400: :viewallorgs has no default grant (tenant admins are manager-archetype); revoke step
// 2026092201: db/access.php: real capability layer (was gating on retired local/courses:manage)
// 2026061600:
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.2.7-beta';  // 1.2.7: Training ROI card renders its figures + ROI settings; skill-gap counts fixed (QW-ROI)
$plugin->dependencies = [
    // tenant::is_cross_tenant() and tenant::path_descendant_filter().
    'local_sentientia_platform' => ANY_VERSION,
];
