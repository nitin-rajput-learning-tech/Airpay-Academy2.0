<?php
/**
 * Central email rendering engine.
 *
 * Renders Mustache content templates, resolves tenant branding,
 * and wraps output in the theme's branded email_html wrapper for preview.
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

class email_renderer {

    /**
     * Render an email content template with context data.
     *
     * Returns the INNER body HTML (without the branded wrapper).
     * Use this when passing to message_send() — the wrapper is applied
     * automatically by Moodle's email_to_user() via the theme override.
     *
     * @param string $templatename e.g. 'local_airpay_emails/compliance/deadline_warning'
     * @param array  $context      template variables
     * @param int    $userid       recipient user ID (for tenant branding lookup)
     * @return string rendered HTML
     */
    public static function render(string $templatename, array $context, int $userid = 0): string {
        global $PAGE, $CFG;

        // Merge tenant branding into context.
        if ($userid > 0) {
            $tenant = tenant_config::get_for_user($userid);
        } else {
            $tenant = tenant_config::get(tenant_config::AIRPAY);
        }
        $context['tenant_name']    = $tenant['name'];
        $context['tenant_logo']    = $CFG->wwwroot . '/theme/airpayux/pix/brand/' . $tenant['logo_filename'];
        $context['primary_color']  = $tenant['primary_color'];
        $context['accent_color']   = $tenant['accent_color'];
        $context['site_url']       = $CFG->wwwroot;
        $context['site_name']      = format_string(get_config('moodle', 'fullname'));
        $context['current_year']   = date('Y');
        $context['privacy_url']    = $CFG->wwwroot . '/local/sentientia_privacy/index.php';
        $context['support_email']  = 'academy@airpay.co.in';

        // Check for DB override before using Mustache file.
        // Strip the 'local_airpay_emails/' prefix to get the template key.
        $templatekey = str_replace('local_airpay_emails/', '', $templatename);
        $tenantid = 0;
        if ($userid > 0) {
            $parts = explode('/', trim($tenant['open_path'] ?? '', '/'));
            $tenantid = (int)($parts[0] ?? 0);
        }

        $override = template_manager::get_override($templatekey, $tenantid);
        if ($override && !empty($override->body_html)) {
            // Render the DB override through Mustache engine (supports {{placeholders}}).
            try {
                $mustache = new \Mustache_Engine();
                return $mustache->render($override->body_html, $context);
            } catch (\Exception $e) {
                // Fallback to file if DB template has syntax errors.
                debugging('DB template render error: ' . $e->getMessage());
            }
        }

        $renderer = $PAGE->get_renderer('core');
        return $renderer->render_from_template($templatename, $context);
    }

    /**
     * Render a FULL email preview (content + branded wrapper).
     *
     * This reproduces exactly what email_to_user() produces,
     * including the theme's email_html.mustache wrapper.
     *
     * @param string $templatename content template name
     * @param array  $context      template variables
     * @param int    $tenantid     costcenter ID for branding
     * @return string fully wrapped HTML email
     */
    public static function render_preview(string $templatename, array $context, int $tenantid = 1): string {
        global $PAGE, $CFG, $SITE;

        // Render the inner content body.
        $tenant = tenant_config::get($tenantid);
        $context['tenant_name']    = $tenant['name'];
        $context['tenant_logo']    = $CFG->wwwroot . '/theme/airpayux/pix/brand/' . $tenant['logo_filename'];
        $context['primary_color']  = $tenant['primary_color'];
        $context['accent_color']   = $tenant['accent_color'];
        $context['site_url']       = $CFG->wwwroot;
        $context['site_name']      = format_string($SITE->fullname);
        $context['current_year']   = date('Y');
        $context['privacy_url']    = $CFG->wwwroot . '/local/sentientia_privacy/index.php';
        $context['support_email']  = 'academy@airpay.co.in';

        $renderer = $PAGE->get_renderer('core');
        $body = $renderer->render_from_template($templatename, $context);

        // Now wrap in the theme's email_html.mustache — same as email_to_user() does.
        $wrappercontext = [
            'body'          => $body,
            'subject'       => $context['subject'] ?? 'Email Preview',
            'sitefullname'  => format_string($SITE->fullname),
            'siteshortname' => format_string($SITE->shortname),
            'sitewwwroot'   => $CFG->wwwroot,
            'toname'        => $context['firstname'] ?? 'Preview User',
            'fromname'      => $tenant['name'],
        ];
        return $renderer->render_from_template('core/email_html', $wrappercontext);
    }

    /**
     * Get the categorized list of all available email templates.
     *
     * @return array [{category, templates: [{key, label}]}]
     */
    public static function get_template_list(): array {
        return [
            [
                'category' => get_string('category_compliance', 'local_airpay_emails'),
                'catkey'   => 'compliance',
                'templates' => [
                    ['key' => 'compliance/welcome_enrolled',    'label' => 'Enrolled in Mandatory Course'],
                    ['key' => 'compliance/reminder_start',      'label' => 'Reminder: Start Course'],
                    ['key' => 'compliance/reminder_halfway',    'label' => 'Halfway to Deadline'],
                    ['key' => 'compliance/deadline_warning',    'label' => 'Deadline Warning (7 days)'],
                    ['key' => 'compliance/overdue_alert',       'label' => 'Overdue Alert'],
                    ['key' => 'compliance/weekly_escalation',   'label' => 'Weekly Escalation (Manager)'],
                ],
            ],
            [
                'category' => get_string('category_notifications', 'local_airpay_emails'),
                'catkey'   => 'notifications',
                'templates' => [
                    ['key' => 'notifications/deadline_approaching',  'label' => 'Deadline Approaching'],
                    ['key' => 'notifications/course_not_started',    'label' => 'Course Not Started'],
                    ['key' => 'notifications/streak_broken',         'label' => 'Streak Broken'],
                    ['key' => 'notifications/manager_nudge',         'label' => 'Manager: Team Overdue'],
                    ['key' => 'notifications/new_course_available',  'label' => 'New Course Available'],
                ],
            ],
            [
                'category' => get_string('category_enrollment', 'local_airpay_emails'),
                'catkey'   => 'enrollment',
                'templates' => [
                    ['key' => 'enrollment/welcome_new_user',         'label' => 'Welcome New User'],
                    ['key' => 'enrollment/course_enrolled',          'label' => 'Course Enrollment'],
                    ['key' => 'enrollment/course_completed',         'label' => 'Course Completed'],
                    ['key' => 'enrollment/learning_path_enrolled',   'label' => 'Learning Path Assigned'],
                ],
            ],
            [
                'category' => get_string('category_account', 'local_airpay_emails'),
                'catkey'   => 'account',
                'templates' => [
                    ['key' => 'account/password_reset',       'label' => 'Password Reset'],
                    ['key' => 'account/new_device_login',     'label' => 'New Device Login Alert'],
                ],
            ],
            [
                'category' => get_string('category_privacy', 'local_airpay_emails'),
                'catkey'   => 'privacy',
                'templates' => [
                    ['key' => 'privacy/deletion_request_admin', 'label' => 'Deletion Request (Admin)'],
                    ['key' => 'privacy/data_export_ready',      'label' => 'Data Export Ready'],
                ],
            ],
        ];
    }
}
