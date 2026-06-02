<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Seed the 4 default mandatory compliance courses from production.
 */
function xmldb_local_airpay_compliance_report_install() {
    global $DB;

    // Grant the export capability to admins / course managers + the BizLMS
    // Compliance Officer role. Runs before the early return below so it always
    // applies on a fresh install.
    \local_airpay_compliance_report\permission::grant_export_to_default_roles();

    if ($DB->count_records('local_compliance_courses') > 0) {
        return;
    }

    $now = time();
    $courses = [
        ['courseid' => 383, 'coursename' => 'POSH Training 2025',                              'deadline_days' => 30, 'sort_order' => 1],
        ['courseid' => 161, 'coursename' => 'IT and Information Security Awareness Training',   'deadline_days' => 30, 'sort_order' => 2],
        ['courseid' => 41,  'coursename' => 'Anti Money Laundering',                            'deadline_days' => 30, 'sort_order' => 3],
        ['courseid' => 256, 'coursename' => 'Phishing Awareness Training',                      'deadline_days' => 30, 'sort_order' => 4],
    ];

    foreach ($courses as $c) {
        // Verify course exists in DB.
        if (!$DB->record_exists('course', ['id' => $c['courseid']])) {
            continue;
        }
        $c['costcenterid']  = 0; // All tenants.
        $c['is_active']     = 1;
        $c['createdby']     = 2; // Siteadmin.
        $c['timecreated']   = $now;
        $c['timemodified']  = $now;
        $DB->insert_record('local_compliance_courses', (object)$c);
    }
}
