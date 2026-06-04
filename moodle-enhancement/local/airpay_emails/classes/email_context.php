<?php
/**
 * Sample context data for email template previews.
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

class email_context {

    /**
     * Get realistic sample data for a given template.
     *
     * @param string $templatekey e.g. 'compliance/deadline_warning'
     * @return array context variables
     */
    public static function get_sample(string $templatekey): array {
        global $CFG;

        // Shared base context for all previews.
        $base = [
            'firstname'     => 'Priya',
            'lastname'      => 'Singh',
            'fullname'      => 'Priya Singh',
            'email'         => 'priya.singh@airpay.co.in',
            'designation'   => 'Senior Product Analyst',
            'department'    => 'Product & Technology',
            'course_name'   => 'Anti Money Laundering',
            'course_url'    => $CFG->wwwroot . '/course/view.php?id=41',
            'course_id'     => 41,
            'dashboard_url' => $CFG->wwwroot . '/my/dashboard.php',
            'site_url'      => $CFG->wwwroot,
            'subject'       => 'Email Preview',
        ];

        $samples = [
            // ── COMPLIANCE ──
            'compliance/welcome_enrolled' => array_merge($base, [
                'subject'       => 'Enrolled: Anti Money Laundering',
                'deadline_date' => date('d F Y', strtotime('+30 days')),
                'deadline_days' => 30,
                'enrolled_date' => date('d F Y'),
                'course_description' => 'This mandatory compliance course covers anti-money laundering regulations, suspicious transaction reporting, and your obligations under the Prevention of Money Laundering Act (PMLA).',
            ]),
            'compliance/reminder_start' => array_merge($base, [
                'subject'       => 'Reminder: Start Anti Money Laundering',
                'days_since_enrolled' => 7,
                'deadline_date' => date('d F Y', strtotime('+23 days')),
                'deadline_days' => 23,
            ]),
            'compliance/reminder_halfway' => array_merge($base, [
                'subject'       => 'Halfway to deadline: Anti Money Laundering',
                'progress'      => 20,
                'deadline_date' => date('d F Y', strtotime('+15 days')),
                'deadline_days' => 15,
                'days_elapsed'  => 15,
            ]),
            'compliance/deadline_warning' => array_merge($base, [
                'subject'       => 'Due in 7 days: Anti Money Laundering',
                'deadline_date' => date('d F Y', strtotime('+7 days')),
                'deadline_days' => 7,
                'progress'      => 45,
            ]),
            'compliance/overdue_alert' => array_merge($base, [
                'subject'       => 'OVERDUE: Anti Money Laundering',
                'days_overdue'  => 3,
                'deadline_date' => date('d F Y', strtotime('-3 days')),
                'progress'      => 60,
            ]),
            'compliance/weekly_escalation' => array_merge($base, [
                'subject'         => 'Weekly Escalation: Team Compliance Overdue',
                'manager_name'    => 'Binay Upadhyay',
                'overdue_members' => [
                    ['name' => 'Priya Singh', 'course' => 'Anti Money Laundering', 'days_overdue' => 12, 'progress' => 60],
                    ['name' => 'Rithik Shukla', 'course' => 'POSH Training 2025', 'days_overdue' => 5, 'progress' => 0],
                    ['name' => 'Anand Ram', 'course' => 'IT Security Awareness', 'days_overdue' => 3, 'progress' => 30],
                ],
                'team_overdue_count' => 3,
                'reports_url'    => $CFG->wwwroot . '/local/airpay_compliance_report/index.php',
            ]),

            // ── NOTIFICATIONS ──
            'notifications/deadline_approaching' => array_merge($base, [
                'subject'       => 'Deadline approaching: Anti Money Laundering',
                'deadline_date' => date('d F Y', strtotime('+5 days')),
                'deadline_days' => 5,
                'progress'      => 35,
            ]),
            'notifications/course_not_started' => array_merge($base, [
                'subject'         => 'You have not started: Anti Money Laundering',
                'enrolled_date'   => date('d F Y', strtotime('-10 days')),
                'days_since'      => 10,
            ]),
            'notifications/streak_broken' => array_merge($base, [
                'subject'       => 'Your learning streak has ended',
                'streak_days'   => 14,
                'last_login'    => date('d F Y', strtotime('-2 days')),
            ]),
            'notifications/manager_nudge' => array_merge($base, [
                'subject'         => '3 team members have overdue courses',
                'manager_name'    => 'Binay Upadhyay',
                'overdue_count'   => 3,
                'team_url'        => $CFG->wwwroot . '/my/dashboard.php',
                'overdue_members' => [
                    ['name' => 'Priya Singh', 'course' => 'AML Training', 'days_overdue' => 5],
                    ['name' => 'Rithik Shukla', 'course' => 'POSH Training', 'days_overdue' => 3],
                    ['name' => 'Anand Ram', 'course' => 'IT Security', 'days_overdue' => 1],
                ],
            ]),
            'notifications/new_course_available' => array_merge($base, [
                'subject'         => 'New course: Phishing Awareness Training',
                'course_name'     => 'Phishing Awareness Training',
                'course_description' => 'Learn to identify and report phishing emails, social engineering attacks, and other cyber threats targeting financial services employees.',
                'course_url'      => $CFG->wwwroot . '/course/view.php?id=256',
                'added_by'        => 'L&D Team',
            ]),

            // ── ENROLLMENT ──
            'enrollment/welcome_new_user' => array_merge($base, [
                'subject'       => 'Welcome to Airpay Academy',
                'username'      => 'priya.singh',
                'login_url'     => $CFG->wwwroot . '/login/index.php',
                'help_url'      => $CFG->wwwroot . '/local/sentientia_pages/index.php?page=help',
            ]),
            'enrollment/course_enrolled' => array_merge($base, [
                'subject'       => 'You have been enrolled in: Anti Money Laundering',
                'enrolled_by'   => 'L&D Team',
                'start_date'    => date('d F Y'),
            ]),
            'enrollment/course_completed' => array_merge($base, [
                'subject'         => 'Congratulations! You completed Anti Money Laundering',
                'completion_date' => date('d F Y'),
                'score'           => 85,
                'certificate_url' => $CFG->wwwroot . '/mod/customcert/view.php?id=100',
                'has_certificate'  => true,
            ]),
            'enrollment/learning_path_enrolled' => array_merge($base, [
                'subject'         => 'New Learning Path: HR Onboarding Courses',
                'path_name'       => 'HR Onboarding Courses',
                'path_url'        => $CFG->wwwroot . '/local/learningplan/lpathinfo.php?id=15',
                'course_count'    => 5,
                'deadline_date'   => date('d F Y', strtotime('+14 days')),
                'deadline_days'   => 14,
                'courses'         => [
                    ['name' => 'Company Overview & Values'],
                    ['name' => 'Information Security Basics'],
                    ['name' => 'POSH Training 2025'],
                    ['name' => 'Anti Money Laundering'],
                    ['name' => 'Code of Conduct'],
                ],
            ]),

            // ── ACCOUNT ──
            'account/password_reset' => array_merge($base, [
                'subject'    => 'Password Reset Request',
                'reset_url'  => $CFG->wwwroot . '/login/forgot_password.php?token=SAMPLE123',
                'reset_expiry' => '30 minutes',
                'ip_address'  => '203.0.113.42',
            ]),
            'account/new_device_login' => array_merge($base, [
                'subject'    => 'New login to your account',
                'login_time' => date('d F Y, h:i A'),
                'device'     => 'Chrome on Windows 11',
                'ip_address' => '203.0.113.42',
                'location'   => 'Mumbai, India',
            ]),

            // ── PRIVACY ──
            'privacy/deletion_request_admin' => array_merge($base, [
                'subject'       => 'DPDP Request: Account Deletion from Priya Singh',
                'request_type'  => 'Account Deletion',
                'request_date'  => date('d F Y, h:i A'),
                'request_reason' => 'No longer associated with the organization',
                'admin_url'     => $CFG->wwwroot . '/local/airpay_privacy/admin.php',
                'response_deadline' => date('d F Y', strtotime('+30 days')),
            ]),
            'privacy/data_export_ready' => array_merge($base, [
                'subject'        => 'Your data download is ready',
                'download_url'   => $CFG->wwwroot . '/local/airpay_privacy/download.php?token=SAMPLE',
                'download_expiry' => '72 hours',
                'file_size'      => '2.4 MB',
                'data_categories' => [
                    ['category' => 'Profile Information'],
                    ['category' => 'Course Enrollments & Progress'],
                    ['category' => 'Assessment Scores'],
                    ['category' => 'Certificates'],
                    ['category' => 'Login History'],
                ],
            ]),
        ];

        return $samples[$templatekey] ?? $base;
    }
}
