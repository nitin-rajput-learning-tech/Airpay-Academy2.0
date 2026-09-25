<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_roles_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026050700) {
        // Initial table — audit log.
        $table = new xmldb_table('local_sentientia_roles_auditlog');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('roleid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('roleshortname', XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL);
            $table->add_field('action',        XMLDB_TYPE_CHAR,    '40', null, XMLDB_NOTNULL);
            $table->add_field('capability',    XMLDB_TYPE_CHAR,    '255');
            $table->add_field('oldpermission', XMLDB_TYPE_INTEGER, '6');
            $table->add_field('newpermission', XMLDB_TYPE_INTEGER, '6');
            $table->add_field('contextid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('targetuserid',  XMLDB_TYPE_INTEGER, '10');
            $table->add_field('changedby',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('reason',        XMLDB_TYPE_TEXT);
            $table->add_field('open_path',     XMLDB_TYPE_CHAR,    '255');
            $table->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_changedby', XMLDB_KEY_FOREIGN, ['changedby'], 'user', ['id']);
            $table->add_index('idx_roleid',      XMLDB_INDEX_NOTUNIQUE, ['roleid']);
            $table->add_index('idx_action',      XMLDB_INDEX_NOTUNIQUE, ['action']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('idx_capability',  XMLDB_INDEX_NOTUNIQUE, ['capability']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050700, 'local', 'sentientia_roles');
    }

    // 2026092500 - ADR-031: take :manage and :assign back from every role.
    //
    // Both defaulted to the manager archetype. Tenant admins hold
    // manager-archetype roles at system context (UAT's "administrator", id 9),
    // so every tenant admin could rewrite the role definitions all tenants share
    // (and re-grant themselves the cross-tenant capabilities revoked elsewhere),
    // and assign any system role to anyone in any tenant. db/access.php now has
    // no default for either, but changing an archetype never revokes what Moodle
    // already applied - hence this explicit revoke. Site admins keep access by
    // the admin bypass; a platform (cross-tenant) role that genuinely needs
    // either must be granted it again, deliberately.
    if ($oldversion < 2026092500) {
        $syscontext = \context_system::instance();
        foreach (['local/sentientia_roles:manage', 'local/sentientia_roles:assign'] as $cap) {
            $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
                'capability = :cap', ['cap' => $cap]);
            foreach ($roleids as $roleid) {
                unassign_capability($cap, (int) $roleid, $syscontext->id);
            }
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_roles');
    }

    return true;
}
