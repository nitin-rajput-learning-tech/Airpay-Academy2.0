<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Advanced Analytics';
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

// Capabilities (db/access.php, added 2026-09-22).
$string['sentientia_analytics:view'] = 'View the analytics dashboard';
$string['sentientia_analytics:viewallorgs'] = 'View analytics for every organisation, not only your own';
$string['sentientia_analytics:export'] = 'Download the analytics CSV export';

// Previously hardcoded in index.php / dashboard.mustache.
$string['allbusinessunits'] = 'All Business Units';
$string['exportcsv'] = 'Export CSV';

// Refusals (N5, 2026-09-24). These replace moodle_exception('nopermission'),
// which named a core key that does not exist and rendered as the bare
// identifier "error/nopermission". A missing capability is reported by
// required_capability_exception with core's 'nopermissions'; these two cover
// the refusals that are about org scope rather than capability.
$string['error_noorgscope'] = 'Your account is not assigned to an organisation, so no analytics can be shown. Ask your administrator to set your organisation.';
$string['error_outofscope'] = 'That part of the organisation is outside your access.';

// At-Risk Learners table (N6, 2026-09-24). The column header used core
// 'fullnamedisplay', which is a name-format template, not a label, and
// printed the raw placeholder "{$a->firstname} {$a->lastname}".
$string['atrisk_col_learner'] = 'Learner';

// Dashboard chrome, KPI cards and funnel stages (2026-11-18). These were
// English literals in templates/dashboard.mustache and analytics_manager.
$string['businessunit_label'] = 'Business unit:';
$string['range_label'] = 'Period for the KPI cards';
$string['range_7d'] = '7 days';
$string['range_30d'] = '30 days';
$string['range_90d'] = '90 days';
$string['range_ytd'] = 'YTD';
$string['kpi_active_users'] = 'Active Users';
$string['kpi_new_enrolments'] = 'New Enrolments';
$string['kpi_completions'] = 'Completions';
$string['kpi_certificates'] = 'Certificates';
$string['kpi_vs_previous'] = '{$a} vs previous';
$string['funnel_heading'] = 'Engagement Funnel';
$string['funnel_empty'] = 'No enrolment data available yet.';
$string['funnel_enrolled'] = 'Enrolled';
$string['funnel_started'] = 'Started';
$string['funnel_completed'] = 'Completed';
$string['funnel_certified'] = 'Certified';
$string['heatmap_heading'] = 'Compliance by Department';
$string['heatmap_note'] = 'Completed mandatory-course assignments, as on the compliance report\'s Department Scorecard.';
$string['heatmap_viewusers'] = 'View the people in this department';
$string['heatmap_users'] = '{$a} users';
$string['heatmap_users_one'] = '1 user';
$string['heatmap_empty_title'] = 'No compliance assignments yet';
$string['heatmap_empty'] = 'No department in this view has mandatory-course assignments.';
$string['courseeff_heading'] = 'Course Effectiveness (Top {$a})';
$string['col_course'] = 'Course';
$string['col_enrolled'] = 'Enrolled';
$string['col_completed'] = 'Completed';
$string['col_rate'] = 'Rate';
