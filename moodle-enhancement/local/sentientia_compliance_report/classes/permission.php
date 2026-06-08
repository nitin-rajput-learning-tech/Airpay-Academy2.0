<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_compliance_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Access decisions for the compliance report.
 *
 * Centralises the "may this user export the full matrix?" check so the
 * server-side gate (export.php) and the button-visibility gate (index.php /
 * the dashboard template) are guaranteed to agree — they call the same method.
 *
 * @package    local_sentientia_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {

    /** @var string Capability authorising export of the full compliance matrix. */
    public const EXPORT_CAPABILITY = 'local/sentientia_compliance_report:export';

    /** @var int BizLMS Compliance Officer / OrgAdmin role id (assigned at category context). */
    private const COMPLIANCE_ROLE_ID = 9;

    /**
     * Whether the given user may export the full compliance matrix.
     *
     * The export releases every employee's compliance status (PII) for the
     * user's tenant, so it is gated on {@see self::EXPORT_CAPABILITY}.
     *
     * The capability is checked at system context AND at every course-category
     * context where the user holds a role. The second check is essential:
     * Moodle capabilities only flow *down* the context tree, and the BizLMS
     * Compliance Officer / OrgAdmin shell is assigned at category context
     * (CONTEXT_COURSECAT) — so a system-context check alone would never see it.
     * This mirrors why index.php's view gate uses a raw role_assignments query
     * rather than has_capability() at system context.
     *
     * @param int|null $userid User to test, or null for the current $USER.
     * @return bool
     */
    public static function can_export(?int $userid = null): bool {
        global $USER, $DB;

        $userid = $userid ?: (int) $USER->id;
        $systemcontext = \context_system::instance();

        // Site admins (admin bypass) and any system-level role holding the cap
        // — e.g. course managers granted it at install/upgrade.
        if (has_capability(self::EXPORT_CAPABILITY, $systemcontext, $userid)) {
            return true;
        }

        // Category-context role holders (BizLMS Compliance Officer / OrgAdmin).
        // Only categories where THIS user actually has a role assignment are
        // checked, so this is bounded (typically 1–3 rows), not a category scan.
        $catcontextids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ctx.id
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :uid
                AND ctx.contextlevel = :catlevel",
            ['uid' => $userid, 'catlevel' => CONTEXT_COURSECAT]);

        foreach ($catcontextids as $ctxid) {
            if (has_capability(self::EXPORT_CAPABILITY, \context::instance_by_id($ctxid), $userid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grant the export capability to the roles that should hold it on this
     * deployment. Called from db/install.php (fresh installs) and
     * db/upgrade.php (existing installs). Idempotent.
     *
     * Grants to:
     *   1. Every role that already holds local/courses:manage — this preserves
     *      the exact pre-capability behaviour (the old export gate was
     *      is_siteadmin() || has_capability('local/courses:manage')).
     *   2. The BizLMS Compliance Officer role (id 9), if it exists. The cap is
     *      stored in the role definition (system context) so it resolves
     *      wherever the role is assigned, including category context.
     *
     * Site admins need no grant — they pass via the admin bypass.
     */
    public static function grant_export_to_default_roles(): void {
        global $DB;

        // Ensure the capability is registered before assigning it. Core also
        // syncs capabilities after the install/upgrade callback, but the
        // ordering relative to this method is not guaranteed, so register
        // defensively. update_capabilities() is idempotent.
        update_capabilities('local_sentientia_compliance_report');

        $systemcontext = \context_system::instance();

        // 1) Backward-compatibility: anyone who can manage courses today keeps
        //    export access. (If local/courses:manage is undefined on this
        //    deployment the query simply returns nothing.)
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap AND permission = :perm',
            ['cap' => 'local/courses:manage', 'perm' => CAP_ALLOW]);
        foreach ($roleids as $roleid) {
            assign_capability(self::EXPORT_CAPABILITY, CAP_ALLOW, $roleid, $systemcontext->id, true);
        }

        // 2) BizLMS Compliance Officer / OrgAdmin shell.
        if ($DB->record_exists('role', ['id' => self::COMPLIANCE_ROLE_ID])) {
            assign_capability(self::EXPORT_CAPABILITY, CAP_ALLOW, self::COMPLIANCE_ROLE_ID,
                $systemcontext->id, true);
        }

        $systemcontext->mark_dirty();
    }
}
