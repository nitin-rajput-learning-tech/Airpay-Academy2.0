<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pages;

defined('MOODLE_INTERNAL') || die();

/**
 * Access gate and page setup for the tenant-scoped certificate template browser.
 *
 * WHY THIS EXISTS
 * ---------------
 * Persona pass 2026-09-30, defect D6. certificate_templates.php lets in anyone
 * who holds tool/certificate:manage (a tenant admin's L&D role has it) and then
 * called admin_externalpage_setup('local_sentientia_pages_cert_templates').
 * That external page is registered in settings.php inside `if ($hassiteconfig)`
 * and with the capability moodle/site:config, so for a user WITHOUT
 * moodle/site:config the admin tree never contains it and core throws
 * `accessdenied` (lib/adminlib.php, admin_externalpage_setup()). The page's own
 * gate said yes and the page setup said no; the user saw "access denied" on a
 * page they were entitled to open.
 *
 * The fix is to ask the same question core asks. A user who holds
 * moodle/site:config still gets the admin-tree page (navigation, search box,
 * breadcrumbs - exactly as before); everyone else the gate lets in gets an
 * ordinary system-context page.
 *
 * @package    local_sentientia_pages
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cert_templates_access {

    /** Name of the admin_externalpage registered in settings.php. */
    public const EXTERNALPAGE = 'local_sentientia_pages_cert_templates';

    /**
     * May the current user open the certificate template browser at all?
     *
     * Site admins, moodle/site:config holders, and holders of the vendored
     * certificate plugin's manage capability (tenant admins). What each of them
     * then SEES is decided by the page (tenant scope flag).
     *
     * @param \context $context System context.
     * @return bool
     */
    public static function can_view(\context $context): bool {
        return is_siteadmin()
            || has_capability('moodle/site:config', $context)
            || has_capability('tool/certificate:manage', $context);
    }

    /**
     * Is the admin_externalpage registered for this user?
     *
     * settings.php registers it under `if ($hassiteconfig)` with
     * moodle/site:config, so this is exactly the set of users for whom
     * admin_externalpage_setup() can succeed. Do not widen it without also
     * widening that registration.
     *
     * @param \context $context System context.
     * @return bool
     */
    public static function uses_admin_tree(\context $context): bool {
        return has_capability('moodle/site:config', $context);
    }

    /**
     * Set the page up: through the admin tree where core will accept it,
     * as an ordinary system-context page otherwise.
     *
     * Call after the {@see self::can_view()} gate and before any output.
     *
     * @param \context $context System context.
     * @param \moodle_url $url The page URL.
     * @param string $title Title, heading and breadcrumb text (non-tree branch).
     * @return void
     */
    public static function setup_page(\context $context, \moodle_url $url, string $title): void {
        global $CFG, $PAGE;

        if (self::uses_admin_tree($context)) {
            require_once($CFG->libdir . '/adminlib.php');
            admin_externalpage_setup(self::EXTERNALPAGE, '', null, '', ['pagelayout' => 'admin']);
            return;
        }

        $PAGE->set_context($context);
        $PAGE->set_pagelayout('admin');
        $PAGE->set_url($url);
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        $PAGE->navbar->add($title, $url);
    }
}
