<?php
// Kannada locale for local_airpay_core.
// ADR-017 Phase 2 / C1.2 (2026-05-28) — user_type labels + profile field
// labels + onboarding step titles. Strings NOT in this file fall back
// to the English baseline at lang/en/local_airpay_core.php.
//
// Future coverage of the full string set is tracked as a chip in the
// stabilization backlog (kn/mr/sw locale parity for airpay_core).

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay ಕೋರ್';

// User-type labels (ADR-017 Q4 — visible on profile badge)
$string['usertype_employee_label']          = 'ಉದ್ಯೋಗಿ';
$string['usertype_consumer_label']          = 'ಕಲಿಯುಗ';
$string['usertype_partner_employee_label']  = 'ಪಾಲುದಾರ ಸಿಬ್ಬಂದಿ';
$string['usertype_operator_label']          = 'ಆಪರೇಟರ್';

// Profile fields
$string['profile_field_department']         = 'ವಿಭಾಗ';
$string['profile_field_job_title']          = 'ಪದನಾಮ';
$string['profile_field_employee_id']        = 'ಉದ್ಯೋಗಿ ID';
$string['profile_field_manager_name']       = 'ಮ್ಯಾನೇಜರ್';
$string['profile_field_hire_date']          = 'ಸೇರಿದ ದಿನಾಂಕ';
$string['profile_field_interests']          = 'ನಿಮ್ಮ ಆಸಕ್ತಿಗಳು';
$string['profile_field_weekly_goal_hours']  = 'ಸಾಪ್ತಾಹಿಕ ಕಲಿಕೆ ಗುರಿ (ಗಂಟೆಗಳು)';
$string['profile_field_courses_enrolled']   = 'ನೋಂದಾಯಿತ ಕೋರ್ಸುಗಳು';
$string['profile_field_customer_name']      = 'ಸಂಸ್ಥೆ';

// Onboarding steps
$string['onboarding_step_welcome']          = 'ಸ್ವಾಗತ';
$string['onboarding_step_interests']        = 'ನಿಮ್ಮ ವಿಷಯಗಳನ್ನು ಆಯ್ಕೆಮಾಡಿ';
$string['onboarding_step_weekly_goal']      = 'ಸಾಪ್ತಾಹಿಕ ಗುರಿಯನ್ನು ಹೊಂದಿಸಿ';
$string['onboarding_step_finish']           = 'ಎಲ್ಲ ಸಿದ್ಧವಾಗಿದೆ';
