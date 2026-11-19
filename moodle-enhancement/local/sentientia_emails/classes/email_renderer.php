<?php
/**
 * Central email rendering engine.
 *
 * Renders Mustache content templates, resolves tenant branding,
 * and wraps output in the theme's branded email_html wrapper for preview.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class email_renderer {

    /**
     * Render an email content template with context data.
     *
     * Returns the INNER body HTML (without the branded wrapper).
     * Use this when passing to message_send() — the wrapper is applied
     * automatically by Moodle's email_to_user() via the theme override.
     *
     * @param string $templatename e.g. 'local_sentientia_emails/compliance/deadline_warning'
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
        $context['tenant_logo']    = $CFG->wwwroot . '/theme/sentientia/pix/brand/' . $tenant['logo_filename'];
        $context['primary_color']  = $tenant['primary_color'];
        $context['accent_color']   = $tenant['accent_color'];
        $context['site_url']       = $CFG->wwwroot;
        $context['site_name']      = format_string(get_config('moodle', 'fullname'));
        $context['current_year']   = date('Y');
        $context['privacy_url']    = $CFG->wwwroot . '/local/sentientia_privacy/index.php';
        $context['support_email']  = 'academy@airpay.co.in';

        // Check for DB override before using Mustache file.
        // Strip the 'local_sentientia_emails/' prefix to get the template key.
        $templatekey = str_replace('local_sentientia_emails/', '', $templatename);
        // ADR-031 (2026-09-25): resolve the RECIPIENT's tenant. tenant_config
        // never returned an 'open_path' key, so this was always 0 and only the
        // global override was ever delivered - which is why a tenant admin's
        // "own" template edit had to be a global one. Tenant editors are now
        // confined to their own tenant's override, so it has to reach them.
        $tenantid = $userid > 0 ? tenant_config::tenant_id_for_user($userid) : 0;

        $override = template_manager::get_override($templatekey, $tenantid);
        if ($override && !empty($override->body_html)) {
            // Render the DB override through Mustache engine (supports {{placeholders}}).
            try {
                // Moodle 5.2+ ships Mustache 3.0 (\Mustache\Engine) and no longer autoloads the
                // legacy \Mustache_Engine, which is a class-not-found \Error, not an \Exception;
                // hence the factory and the \Throwable catch (the file fallback below must run).
                // email_engine() (2026-11-19): the override can include this plugin's partials
                // (course card, support footer); the plain engine rendered them as nothing.
                $mustache = mustache_factory::email_engine();
                return $mustache->render($override->body_html, $context);
            } catch (\Throwable $e) {
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
     * The body is what that tenant's learners actually receive (2026-11-19): the tenant's active
     * override, else the global one, else the Mustache file, resolved exactly as render() does
     * for a real send. It used to be the file only, so an edited template previewed as the
     * default and the editor's first preview disagreed with the text it was editing.
     *
     * @param string   $templatename   content template name
     * @param array    $context        template variables
     * @param int      $tenantid       costcenter ID for branding
     * @param int|null $overridetenant tenant whose override applies (0 = the global override only);
     *                                 null = the branding tenant
     * @param string|null $source      out: the body that was actually rendered: 'tenant_override',
     *                                 'global_override' or 'file' (also 'file' when the override is
     *                                 broken and the file was rendered instead, as render() delivers it)
     * @return string fully wrapped HTML email
     */
    public static function render_preview(string $templatename, array $context, int $tenantid = 1,
                                          ?int $overridetenant = null, ?string &$source = null): string {
        global $PAGE, $CFG, $SITE;

        // Render the inner content body.
        $tenant = tenant_config::get($tenantid);
        $context['tenant_name']    = $tenant['name'];
        $context['tenant_logo']    = $CFG->wwwroot . '/theme/sentientia/pix/brand/' . $tenant['logo_filename'];
        $context['primary_color']  = $tenant['primary_color'];
        $context['accent_color']   = $tenant['accent_color'];
        $context['site_url']       = $CFG->wwwroot;
        $context['site_name']      = format_string($SITE->fullname);
        $context['current_year']   = date('Y');
        $context['privacy_url']    = $CFG->wwwroot . '/local/sentientia_privacy/index.php';
        $context['support_email']  = 'academy@airpay.co.in';

        $renderer = $PAGE->get_renderer('core');
        $body = null;
        $source = null;
        $templatekey = str_replace('local_sentientia_emails/', '', $templatename);
        $override = template_manager::get_override($templatekey, $overridetenant ?? $tenantid);
        if ($override && !empty($override->body_html)) {
            try {
                $body = mustache_factory::email_engine()->render($override->body_html, $context);
                $source = $override->source;
            } catch (\Throwable $e) {
                // Same fallback as render(): a broken override is not what gets delivered either.
                debugging('DB template render error: ' . $e->getMessage());
            }
        }
        if ($body === null) {
            $body = $renderer->render_from_template($templatename, $context);
            // Set only once the file rendered: a caller must not label a failed render.
            $source = 'file';
        }

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
                'category' => get_string('category_compliance', 'local_sentientia_emails'),
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
                'category' => get_string('category_notifications', 'local_sentientia_emails'),
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
                'category' => get_string('category_enrollment', 'local_sentientia_emails'),
                'catkey'   => 'enrollment',
                'templates' => [
                    ['key' => 'enrollment/welcome_new_user',         'label' => 'Welcome New User'],
                    ['key' => 'enrollment/course_enrolled',          'label' => 'Course Enrollment'],
                    ['key' => 'enrollment/course_completed',         'label' => 'Course Completed'],
                    ['key' => 'enrollment/learning_path_enrolled',   'label' => 'Learning Path Assigned'],
                    ['key' => 'enrollment/manager_course_completed', 'label' => 'Course Completed (Manager Copy)'],
                ],
            ],
            [
                'category' => get_string('category_account', 'local_sentientia_emails'),
                'catkey'   => 'account',
                'templates' => [
                    ['key' => 'account/password_reset',       'label' => 'Password Reset'],
                    ['key' => 'account/new_device_login',     'label' => 'New Device Login Alert'],
                ],
            ],
            [
                'category' => get_string('category_privacy', 'local_sentientia_emails'),
                'catkey'   => 'privacy',
                'templates' => [
                    ['key' => 'privacy/deletion_request_admin', 'label' => 'Deletion Request (Admin)'],
                    ['key' => 'privacy/data_export_ready',      'label' => 'Data Export Ready'],
                ],
            ],
        ];
    }

    /**
     * Every template key in the registry (get_template_list()), e.g. 'compliance/welcome_enrolled'.
     *
     * @return string[]
     */
    public static function template_keys(): array {
        $keys = [];
        foreach (self::get_template_list() as $cat) {
            foreach ($cat['templates'] as $tpl) {
                $keys[] = $tpl['key'];
            }
        }
        return $keys;
    }

    /**
     * The registry key a request names, or '' when it names none (2026-11-19).
     *
     * The only gate between a request parameter and a template name, a file path or an override row:
     * preview.php, editor.php, preview_ajax.php and the template web services all go through it. A key
     * is accepted only when it is in the registry, never by cleaning it into shape, so nothing outside
     * the e-mail templates (the plugin's own manage/* page templates, another component, a ../ path)
     * can be previewed, read or given an override. preview.php used to clean ?template= with
     * PARAM_ALPHANUMEXT, which drops the "/" every key has ("compliance/welcome_enrolled" became
     * "compliancewelcome_enrolled": "Template error: Sorry, the requested file could not be found"),
     * while editor.php and the web services took the raw value.
     *
     * Also accepted: the legacy ?tpl= spelling with the "/" written as "_" ("compliance_welcome_enrolled").
     *
     * @param string $requested the key as the request gave it
     * @return string the registry key, or '' when it is not one
     */
    public static function known_template_key(string $requested): string {
        $requested = trim($requested);
        if ($requested === '') {
            return '';
        }
        foreach (self::template_keys() as $key) {
            if ($requested === $key || $requested === str_replace('/', '_', $key)) {
                return $key;
            }
        }
        return '';
    }
}
