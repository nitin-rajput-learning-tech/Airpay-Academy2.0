<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Advanced Analytics';
$string['analytics'] = 'Analytics Dashboard';
$string['privacy:metadata'] = 'The analytics plugin queries existing data and does not store personal data.';

// ── P1.2 Predictive Analytics strings ─────────────────────────────────
$string['predictive_heading']    = 'Predictive Analytics';
$string['atrisk_heading']        = 'At-Risk Learners';
$string['atrisk_description']    = 'Learners forecast to miss course completion deadlines based on engagement signals. Scores reflect recency (30%), completion gap (25%), overdue courses (25%) and engagement velocity (20%).';
$string['atrisk_empty']          = 'No at-risk learners detected for this period.';
$string['risk_score']            = 'Risk Score';
$string['risk_band_high']        = 'High Risk';
$string['risk_band_medium']      = 'Medium Risk';
$string['risk_band_low']         = 'Low Risk';
$string['signals_heading']       = 'Risk Signals';
$string['signal_weight']         = 'Weight';
$string['skillgap_heading']      = 'Skill Gap Projection';
$string['skillgap_description']  = 'Estimated percentage of required skills not yet covered per team, derived from course-category coverage. Powered by local_sentientia_skillsai when installed.';
$string['skillgap_empty']        = 'No skill gap data available for this scope.';
$string['gap_pct']               = 'Gap';
$string['covered_skills']        = 'Covered';
$string['required_skills']       = 'Required';
$string['uncovered_skills']      = 'Uncovered Skills';
$string['task_refresh_predictive_cache'] = 'Sentientia: Refresh predictive analytics cache';

// ── P1.2 Training ROI strings ──────────────────────────────────────────
$string['roi_heading']                   = 'Training ROI';
$string['roi_description']               = 'Estimated return on training investment for the selected period. All assumptions are transparent and configurable.';
$string['roi_pct']                       = 'ROI';
$string['roi_net_benefit']               = 'Net Benefit';
$string['roi_total_benefit']             = 'Total Benefit';
$string['roi_total_cost']                = 'Total Cost';
$string['roi_benefits_heading']          = 'Benefits';
$string['roi_costs_heading']             = 'Costs';
$string['roi_assumptions_heading']       = 'Model Assumptions';
$string['roi_assumptions_note']          = 'These figures are configurable estimates. Adjust them in plugin settings to reflect your organisation\'s actual figures.';
$string['roi_currency_symbol']           = '₹';
$string['roi_benefit_productivity']      = 'Productivity gain (time saved)';
$string['roi_benefit_compliance']        = 'Compliance penalty avoidance';
$string['roi_cost_ld_staff']             = 'L&D staff / content development';
$string['roi_cost_platform']             = 'Platform & infrastructure';
$string['roi_cost_content']              = 'Content consumption cost';
$string['roi_cost_platform_flat']        = 'Flat fee for the period';
$string['roi_assm_hours_saved']          = 'Hours saved per completion';
$string['roi_assm_hourly_rate']          = 'Blended employee hourly rate';
$string['roi_assm_penalty']              = 'Compliance penalty avoided per on-time completion';
$string['roi_assm_platform_cost']        = 'Platform cost for the period';
$string['roi_assm_hours_per_course']     = 'Average content hours per course';
$string['roi_empty']                     = 'Insufficient data to calculate ROI for this period.';
