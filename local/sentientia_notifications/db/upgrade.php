<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_notifications_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050900 — Phase C.2: per-user prefs override + quiet hours.
    if ($oldversion < 2026050900) {
        $table = new xmldb_table('local_sentientia_notif_prefs');

        // Comma-separated list of rule_type values the user opted out of.
        $field = new xmldb_field('disabled_rule_types', XMLDB_TYPE_TEXT,
            null, null, null, null, null, 'digest_frequency');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quiet-hours window — 0..23 hour in user's timezone (NULL = no DND).
        $field2 = new xmldb_field('quiet_hours_start', XMLDB_TYPE_INTEGER,
            '2', null, null, null, null, 'disabled_rule_types');
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        $field3 = new xmldb_field('quiet_hours_end', XMLDB_TYPE_INTEGER,
            '2', null, null, null, null, 'quiet_hours_start');
        if (!$dbman->field_exists($table, $field3)) {
            $dbman->add_field($table, $field3);
        }

        upgrade_plugin_savepoint(true, 2026050900,
            'local', 'sentientia_notifications');
    }

    if ($oldversion < 2026092500) {
        // ADR-031: :manage no longer defaults to the manager archetype
        // (db/access.php), but changing archetypes never revokes what was
        // already granted. Rules are platform-wide, and tenant admins hold
        // manager-archetype roles, so every existing grant lets a tenant
        // admin rewrite, disable or delete every tenant's notifications:
        // revoke them all. Site admins are unaffected. Roles that were given
        // :manage deliberately must be re-granted, and those holders also
        // need local/sentientia_platform:crosstenant to write rules.
        $syscontext = \context_system::instance();
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap', ['cap' => 'local/sentientia_notifications:manage']);
        foreach ($roleids as $roleid) {
            unassign_capability('local/sentientia_notifications:manage', (int) $roleid, $syscontext->id);
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_notifications');
    }

    return true;
}
