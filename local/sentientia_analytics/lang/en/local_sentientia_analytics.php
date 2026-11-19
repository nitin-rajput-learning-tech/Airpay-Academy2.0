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
$string['skillgap_description']  = 'For each team (third level of the organisation), the share of course categories its members are enrolled in but have not completed. Course categories stand in for skills: this card does not yet read the skills catalogue or role mappings.';
$string['skillgap_empty']        = 'No team in this scope has enrolments to compare. The projection needs teams at the third organisation level whose members are enrolled in courses.';
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

// Training ROI card and its settings (QW-ROI, 2026-11-19). The card printed a
// lone "%" and headings with no figures; figures, formulas and sentences are
// now built in the viewer's language by roi_calculator::export_for_template().
$string['roi_assm_course_dev_hours'] = 'Development and upkeep hours per visible course';
$string['roi_assm_cost_per_learner_hour'] = 'Content cost per learner-hour';
$string['roi_default_marker'] = 'default';
$string['roi_defaults_notice'] = 'These figures use the built-in default assumptions, not your organisation\'s own figures. A site administrator can change them.';
$string['roi_settings_link'] = 'Set ROI assumptions';
$string['roi_empty_title_nocompletions'] = 'No completions in this period';
$string['roi_empty_nocompletions'] = 'No course was completed in {$a}, so there is no benefit to set against the costs. Choose a longer period, or check again when learners complete courses.';
$string['roi_empty_title_nocost'] = 'No costs to measure against';
$string['roi_empty_nocost'] = 'Every cost assumption is zero, so ROI cannot be calculated for {$a}. Set the cost assumptions in the ROI settings.';
$string['roi_period_7d'] = 'the last 7 days';
$string['roi_period_30d'] = 'the last 30 days';
$string['roi_period_90d'] = 'the last 90 days';
$string['roi_period_ytd'] = 'the year to date';
$string['roi_summary_positive'] = 'For {$a->period}, every {$a->unit} spent on training returned an estimated {$a->ret}: an ROI of {$a->pct}% (net benefit {$a->net}).';
$string['roi_summary_negative'] = 'For {$a->period}, estimated training costs exceeded estimated benefits by {$a->net} (ROI {$a->pct}%).';
$string['roi_formula_productivity'] = '{$a->completions} course completions × {$a->hours} saved per completion × {$a->rate}';
$string['roi_formula_compliance'] = '{$a->count} completions on or before the course end date × {$a->penalty} penalty avoided';
$string['roi_formula_ld_staff'] = '{$a->courses} visible courses × {$a->hours} of development × {$a->rate}';
$string['roi_formula_platform'] = 'Flat amount from the ROI settings, the same for every period';
$string['roi_formula_content'] = '{$a->learners} learners enrolled in the period × {$a->hours} per course × {$a->rate}';
$string['roi_unit_hours'] = '{$a} h';
$string['roi_unit_per_hour'] = '{$a} per hour';
$string['roi_settings_heading'] = 'Training ROI assumptions';
$string['roi_settings_heading_desc'] = 'The Training ROI card on the analytics dashboard multiplies these figures by counts from the site. Use your organisation\'s own figures. Amounts are in the currency shown on the card. Enter digits with an optional decimal point and no commas, for example 1500 or 2.5.';
$string['roi_hours_saved_per_completion_desc'] = 'Productivity benefit: hours of work saved for each course completion in the period.';
$string['roi_hourly_rate_desc'] = 'Blended cost of one employee hour. Used for the productivity benefit and for the L&D development cost.';
$string['roi_penalty_avoided_desc'] = 'Compliance benefit: penalty or loss avoided for each completion on or before the course end date.';
$string['roi_course_dev_hours_desc'] = 'L&D cost: hours to develop and maintain one course. Multiplied by every visible course, whatever the period.';
$string['roi_platform_cost_desc'] = 'Platform and infrastructure cost. Counted in full for every period (7 days, 30 days, 90 days or year to date).';
$string['roi_cost_per_learner_hour_desc'] = 'Content cost of one learner-hour, for example licences.';
$string['roi_hours_per_course_desc'] = 'Average learning hours per course. Multiplied by the learners whose enrolment started in the period.';
