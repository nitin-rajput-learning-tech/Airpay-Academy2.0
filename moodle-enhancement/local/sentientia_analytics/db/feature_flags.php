<?php
/**
 * Feature flag registry for local_sentientia_analytics.
 *
 * P1.2 (2026-06-16) — predictive surfaces (at-risk forecasting,
 * skill-gap projection) and training ROI. All three are DEFAULT OFF
 * so existing Airpay Academy analytics behaviour is entirely unchanged
 * until an admin explicitly flips the flag via the Switchboard.
 *
 * Resolution: local_sentientia_platform\feature_flags::is_enabled()
 * honours tenant + customer + global override hierarchy (ADR-002).
 *
 * @package local_sentientia_analytics
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ── Analytics: Predictive category ────────────────────────────────

    'sentientia.analytics.predictive.enabled' => [
        'default'     => false,
        'description' => 'Master switch for predictive analytics surfaces in the
                          analytics dashboard: at-risk completion forecasting and
                          team skill-gap projection. When OFF, the existing
                          descriptive KPI dashboard is shown unchanged. When ON,
                          two new tabs/sections appear: "At-Risk Learners" and
                          "Skill Gap Projection". Default OFF — activate after
                          verifying the hourly cache task runs cleanly.',
    ],

    // ── Analytics: ROI category ────────────────────────────────────────

    'sentientia.analytics.roi.enabled' => [
        'default'     => false,
        'description' => 'Training ROI surface in the analytics dashboard.
                          Shows benefit/cost breakdown, ROI%, and configurable
                          assumptions panel. When OFF the ROI tab is hidden
                          and roi_calculator::compute() is never called.
                          Default OFF — enable once ROI assumption defaults have
                          been reviewed by the L&D lead (Nitin) for Airpay.',
    ],

];
