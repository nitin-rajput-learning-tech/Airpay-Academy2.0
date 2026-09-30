<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_cart.
 *
 * @package   local_sentientia_cart
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * D1 back-fill (persona pass 2026-09-30): give the authenticated-user role
 * local/sentientia_cart:purchase.
 *
 * db/access.php has listed the `user` archetype for :purchase since the plugin
 * was written (and the README says so), so the archetype list did not change.
 * The role still lacked the row on the persona-pass site. Moodle applies
 * archetype defaults only in update_capabilities(), and only when it inserts
 * the capability itself; the likeliest cause (from the git history and the
 * rename tooling, not confirmed on that database) is that the capability was
 * first registered outside it - an earlier CLI patch or the rename
 * --migrate-caps path - so no upgrade could repair it. The role held :view, so
 * the cart page and order history opened, and :purchase was held only by the
 * custom `employee` and `administrator` roles. A real
 * public-storefront (/77) or ZEEA (/177) learner holds no system role except
 * Authenticated user, so add-to-cart, remove and checkout were all refused
 * with "nopermissions" while the cart page looked enabled.
 *
 * This gives the authenticated-user role the capability at system context, as a
 * fresh install now does. Owner decision 2026-09-30: buying stays gated where it
 * always was - cart_manager::is_enabled_for_user() (the enabled_tenants setting)
 * and the ADR-031 catalogue purchase gate (cart_manager::can_buy_course()) - so
 * holding the capability lets a learner buy only what their own catalogue shows
 * them, in a tenant the cart is switched on for.
 *
 * "The authenticated-user role" is every role of archetype `user`, plus the role
 * with shortname `user` and the role $CFG->defaultuserroleid points at (how
 * Moodle itself identifies Authenticated user) in case a restored BizLMS
 * database renamed the role and cleared its archetype. The guest role, the
 * student archetype and every other custom role are not touched.
 *
 * Idempotent, and it only fills a gap: a role that already has ANY setting for
 * the capability at system context (ALLOW already, or an administrator's PREVENT
 * or PROHIBIT) is left as it is, so nothing is ever downgraded or overridden.
 * A capability that is not registered is skipped. Only :purchase is granted;
 * :viewallorders, :refund and :manageprices are not.
 *
 * Used by upgrade step 2026093001 and by the install hook; a function so
 * tests/purchase_capability_backfill_test.php can prove it without replaying
 * the upgrade.
 *
 * @return int number of grants made (0 when every such role already had a setting)
 */
function local_sentientia_cart_backfill_user_purchase(): int {
    global $CFG, $DB;

    $cap = 'local/sentientia_cart:purchase';
    if (!$DB->record_exists('capabilities', ['name' => $cap])) {
        return 0;
    }

    $syscontext = \context_system::instance();
    $granted = 0;

    $select = 'archetype = :archetype OR shortname = :shortname';
    $params = ['archetype' => 'user', 'shortname' => 'user'];
    $defaultuserroleid = (int) ($CFG->defaultuserroleid ?? 0);
    if ($defaultuserroleid > 0) {
        $select .= ' OR id = :defaultuserroleid';
        $params['defaultuserroleid'] = $defaultuserroleid;
    }
    $roles = $DB->get_records_select('role', $select, $params, 'id ASC', 'id');
    foreach ($roles as $role) {
        if ($DB->record_exists('role_capabilities',
                ['roleid' => $role->id, 'capability' => $cap, 'contextid' => $syscontext->id])) {
            continue;   // An explicit setting stands, whatever it is.
        }
        assign_capability($cap, CAP_ALLOW, $role->id, $syscontext->id, false);
        $granted++;
    }
    if ($granted) {
        $syscontext->mark_dirty();
    }

    return $granted;
}
