<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_recompletion.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_recompletion_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026092500) {
        // ADR-031 (2026-09-25): db/install.php granted :reset to the
        // tenant-admin role ("administrator", a manager-archetype role held at
        // system context). Nothing checks :reset yet (bulk_reset.php was never
        // built), so the grant is inert today - but a future bulk-reset page
        // would inherit it before it is tenant-scoped, and a completion reset
        // is irreversible compliance-record deletion. install.php no longer
        // grants it; revoke every existing grant (archetype and install
        // changes never revoke). Site admins are unaffected. :view and
        // :manage stay: rule_access now scopes them to the holder's tenant.
        $syscontext = \context_system::instance();
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap', ['cap' => 'local/sentientia_recompletion:reset']);
        foreach ($roleids as $roleid) {
            unassign_capability('local/sentientia_recompletion:reset', (int) $roleid, $syscontext->id);
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_recompletion');
    }

    return true;
}
