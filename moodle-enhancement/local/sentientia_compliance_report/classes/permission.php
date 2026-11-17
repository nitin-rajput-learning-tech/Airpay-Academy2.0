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

    /**
     * @var string Shortname of the BizLMS org-admin role (L&D / tenant admin,
     * archetype manager), which the code used to call the "Compliance
     * Officer / OrgAdmin" role. It is id 9 in the Airpay production data and
     * on UAT. It is looked up by shortname because an id means nothing on
     * another install: on a fresh Sentientia install id 9 is the first role a
     * plugin creates, the Sentientia Author role.
     */
    public const COMPLIANCE_ROLE_SHORTNAME = 'administrator';

    /**
     * @var int The role id the code hard-coded until 2026-11-17. Used only to
     * find and undo the grant it made to whatever role had that id.
     */
    private const LEGACY_ROLE_ID = 9;

    /**
     * Whether the given user may export the full compliance matrix.
     *
     * The export releases every employee's compliance status (PII) for the
     * user's tenant, so it is gated on {@see self::EXPORT_CAPABILITY}.
     *
     * The capability is checked at system context AND at every course-category
     * context where the user holds a role. The second check is essential:
     * Moodle capabilities only flow *down* the context tree, and the BizLMS
     * org-admin role is assigned at category context (CONTEXT_COURSECAT) too,
     * so a system-context check alone would never see it.
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

        // Category-context role holders (the BizLMS org-admin role).
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
     * The id of the BizLMS org-admin role on this site, or null where there is
     * none (every fresh Sentientia install, every new customer).
     *
     * @return int|null
     */
    public static function compliance_role_id(): ?int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => self::COMPLIANCE_ROLE_SHORTNAME]);
        return $roleid ? (int) $roleid : null;
    }

    /**
     * Grant the export capability to the roles that should hold it on this
     * deployment. Called from db/install.php (fresh installs) and
     * db/upgrade.php (existing installs). Idempotent.
     *
     * Grants to:
     *   1. Every role that holds local/courses:manage. Dead on a fresh install
     *      and on the local production-data copy (checked 2026-11-17): ADR-025
     *      renamed the BizLMS capability local/sentientia_courses:manage and no
     *      Sentientia plugin declares the old name. It can still fire on the
     *      Stage B target, an in-place upgrade of the BizLMS database whose
     *      missing-from-disk BizLMS plugins are kept, so their role_capabilities
     *      rows survive. On the Airpay data those holders are understood to be
     *      manager-archetype roles, which the db/access.php default covers
     *      anyway. Kept, not repointed at the new name: granting bulk-PII export
     *      to every holder of local/sentientia_courses:manage is a policy call.
     *   2. The BizLMS org-admin role, by shortname, if this site has it. The
     *      cap is stored in the role definition (system context) so it
     *      resolves wherever the role is assigned, including category context.
     *      On a site without that role nothing is granted here.
     *
     * The `manager` archetype default in db/access.php covers core managers,
     * and site admins need no grant — they pass via the admin bypass.
     */
    public static function grant_export_to_default_roles(): void {
        global $DB;

        // Ensure the capability is registered before assigning it. Core also
        // syncs capabilities after the install/upgrade callback, but the
        // ordering relative to this method is not guaranteed, so register
        // defensively. update_capabilities() is idempotent.
        update_capabilities('local_sentientia_compliance_report');

        $systemcontext = \context_system::instance();

        // 1) Dead except on an in-place upgraded BizLMS database (see above).
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap AND permission = :perm',
            ['cap' => 'local/courses:manage', 'perm' => CAP_ALLOW]);
        foreach ($roleids as $roleid) {
            assign_capability(self::EXPORT_CAPABILITY, CAP_ALLOW, $roleid, $systemcontext->id, true);
        }

        // 2) The BizLMS org-admin role, only where it exists. Until 2026-11-17
        //    this granted to role id 9 whatever that role was: on a fresh
        //    install, the Sentientia Author role.
        $complianceroleid = self::compliance_role_id();
        if ($complianceroleid !== null) {
            assign_capability(self::EXPORT_CAPABILITY, CAP_ALLOW, $complianceroleid,
                $systemcontext->id, true);
        }

        $systemcontext->mark_dirty();
    }

    /**
     * Undo the export grant the pre-2026-11-17 code made to role id 9 on a site
     * where id 9 is not the BizLMS org-admin role. Called once from
     * db/upgrade.php. Idempotent.
     *
     * Leaves the grant alone when:
     *   - there is no role 9;
     *   - role 9 is the org-admin role (the Airpay data: the grant was meant);
     *   - role 9 has the `manager` archetype: the db/access.php archetype
     *     default gives it the capability anyway, so the grant was not wrong;
     *   - role 9's permission is anything but ALLOW (an admin set it; the old
     *     code only ever wrote ALLOW).
     *
     * An admin who deliberately granted :export to a role that happens to be
     * id 9 loses it here and must grant it again; the old code's grant and a
     * deliberate one leave identical rows.
     *
     * @return bool true when a grant was removed
     */
    public static function revoke_misdirected_export_grant(): bool {
        global $DB;

        $role = $DB->get_record('role', ['id' => self::LEGACY_ROLE_ID], 'id, shortname, archetype');
        if (!$role
                || $role->shortname === self::COMPLIANCE_ROLE_SHORTNAME
                || $role->archetype === 'manager') {
            return false;
        }

        $systemcontext = \context_system::instance();
        if (!$DB->record_exists('role_capabilities', [
                'roleid' => $role->id,
                'contextid' => $systemcontext->id,
                'capability' => self::EXPORT_CAPABILITY,
                'permission' => CAP_ALLOW,
            ])) {
            return false;
        }

        unassign_capability(self::EXPORT_CAPABILITY, (int) $role->id, $systemcontext->id);
        $systemcontext->mark_dirty();
        return true;
    }
}
