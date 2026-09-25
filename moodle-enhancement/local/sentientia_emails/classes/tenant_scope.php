<?php
/**
 * ADR-031 tenant scope for the notification management surfaces.
 *
 * The plugin's capabilities (:manage, :manage_templates, :manage_rules) say
 * WHAT a user may do. Until 2026-09-25 holding one of them also decided
 * WHERE: manage.php honoured any ?tenant=, editor.php defaulted to the global
 * (every-tenant) override, and rule toggle/delete/save acted on any rule id.
 * Every tenant admin holds these through a manager-archetype role at system
 * context, so every tenant admin could read every tenant's delivery log and
 * rewrite what every tenant's learners are emailed.
 *
 * Now \local_sentientia_platform\tenant::is_cross_tenant() alone decides
 * WHERE. Everyone else is confined to their own tenant root, and a caller
 * whose tenant does not resolve gets nothing (fail closed).
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

class tenant_scope {

    /**
     * The tenant a management page operates on.
     *
     * Cross-tenant callers keep the requested tenant, where 0 means "all
     * tenants" (or, for templates, the global override). Everyone else is
     * clamped to their own tenant root whatever they asked for.
     *
     * @param int $requested the ?tenant= value
     * @return int a tenant root id, or 0 (cross-tenant callers only)
     * @throws \moodle_exception error_outoftenant when a scoped caller has no tenant
     */
    public static function resolve(int $requested = 0): int {
        if (tenant::is_cross_tenant()) {
            return $requested;
        }
        $root = tenant::root_for_current_user();
        if ($root <= 0) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return $root;
    }

    /**
     * The tenant a scoped reader is confined to, or null for "no restriction".
     *
     * @return int|null null = cross-tenant; 0 = no resolvable tenant (show nothing); N = tenant root
     */
    public static function reader_tenant(): ?int {
        if (tenant::is_cross_tenant()) {
            return null;
        }
        return max(0, tenant::root_for_current_user());
    }

    /**
     * May the current user WRITE something (a rule, a template override)
     * that applies to $tenantid? 0 = global = every tenant = cross-tenant only.
     *
     * @param int $tenantid
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_can_write_tenant(int $tenantid): void {
        if (tenant::is_cross_tenant()) {
            return;
        }
        if ($tenantid <= 0) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        tenant::require_access($tenantid);
    }

    /**
     * May the current user READ this rule? A global rule (tenant 0) fires
     * for every tenant's users, so everyone may see it; another tenant's
     * rule is refused.
     *
     * @param \stdClass $rule a local_sentientia_email_rules row
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_can_view_rule(\stdClass $rule): void {
        if ((int) $rule->tenant_id === 0 || tenant::is_cross_tenant()) {
            return;
        }
        tenant::require_access((int) $rule->tenant_id);
    }

    /**
     * May the current user toggle, edit or delete this rule? Global rules
     * and other tenants' rules are cross-tenant only.
     *
     * @param \stdClass $rule a local_sentientia_email_rules row
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_can_modify_rule(\stdClass $rule): void {
        self::require_can_write_tenant((int) $rule->tenant_id);
    }

    /**
     * Load a rule the current user may modify.
     *
     * @param int $ruleid
     * @return \stdClass
     * @throws \moodle_exception invalidrecord / error_outoftenant
     */
    public static function modifiable_rule(int $ruleid): \stdClass {
        $rule = rule_manager::get_rule($ruleid);
        if (!$rule) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'local_sentientia_email_rules');
        }
        self::require_can_modify_rule($rule);
        return $rule;
    }
}
