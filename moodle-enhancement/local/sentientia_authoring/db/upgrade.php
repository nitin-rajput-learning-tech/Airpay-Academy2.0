<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_authoring.
 *
 * P0.3.0 (2026-06-16) is the install baseline — the five tables are created
 * by db/install.xml, so there are no upgrade steps yet. This file exists to
 * satisfy the local-plugin checklist (CLAUDE.md §6) and to give future schema
 * revisions a home that follows the version-checked savepoint pattern from
 * .claude/rules/database.md.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run the local_sentientia_authoring upgrade steps.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_sentientia_authoring_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // No upgrade steps yet — P0.3.0 is the install baseline.
    // Template for future revisions (kept as documentation, never runs at
    // the current version):
    //
    // if ($oldversion < 2026XXXXXX) {
    //     $table = new xmldb_table('local_sentientia_auth_question');
    //     $field = new xmldb_field('newcol', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'points');
    //     if (!$dbman->field_exists($table, $field)) {
    //         $dbman->add_field($table, $field);
    //     }
    //     upgrade_plugin_savepoint(true, 2026XXXXXX, 'local', 'sentientia_authoring');
    // }

    // 2026061700 — role back-fill (T-01 pattern). The author caps
    // (generate/review/managetemplates) gained the 'teacher' archetype so the
    // airpay `trainer` role (teacher archetype = the SME/author role) can use
    // the GenAI Authoring Studio. Moodle's update_capabilities() only seeds
    // archetype defaults for NEWLY-added caps, so grant explicitly onto the
    // already-existing teacher-archetype roles. Idempotent —
    // assign_capability(overwrite=false) leaves any role that already has an
    // explicit setting untouched.
    if ($oldversion < 2026061700) {
        $context = \context_system::instance();
        $caps = [
            'local/sentientia_authoring:generate',
            'local/sentientia_authoring:review',
            'local/sentientia_authoring:managetemplates',
        ];
        foreach ($DB->get_records('role', ['archetype' => 'teacher']) as $role) {
            foreach ($caps as $cap) {
                assign_capability($cap, CAP_ALLOW, $role->id, $context->id, false);
            }
        }
        $context->mark_dirty();
        upgrade_plugin_savepoint(true, 2026061700, 'local', 'sentientia_authoring');
    }

    // 2026061701 — ship a dedicated, scoped "Sentientia Author" role. SME content
    // authors get ONLY the GenAI author/SME caps (authoring + skillsai) at system
    // context — NOT the broad teacher/manager archetype caps. The new tools gate
    // at CONTEXT_SYSTEM, so this role is assignable at the System level only.
    // Idempotent: created only when the shortname is free; caps re-synced every
    // run; caps whose owning plugin isn't installed are skipped (no orphan rows).
    if ($oldversion < 2026061701) {
        $shortname = 'sentientiaauthor';
        $existing = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        if ($existing) {
            $roleid = (int) $existing;
        } else {
            $roleid = create_role(
                'Sentientia Author',
                $shortname,
                'Content author / SME. Grants the GenAI Authoring Studio and Skills '
                    . 'Intelligence capabilities at system context, without broader '
                    . 'teacher/manager permissions. Assign at the System level to staff '
                    . 'who create learning content.',
                '' // Custom role — no archetype, so it inherits no broad caps.
            );
        }
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $syscontext = \context_system::instance();
        $authorcaps = [
            'local/sentientia_authoring:generate',
            'local/sentientia_authoring:review',
            'local/sentientia_authoring:managetemplates',
            'local/sentientia_skillsai:extract',
            'local/sentientia_skillsai:review',
        ];
        foreach ($authorcaps as $cap) {
            if ($DB->record_exists('capabilities', ['name' => $cap])) {
                assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
            }
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026061701, 'local', 'sentientia_authoring');
    }

    // 2026090700 — T-01 fresh-install + upgrade parity (UAT persona walk
    // 2026-09-07). Consolidate the author-role seed into the shared, idempotent
    // author_role::ensure() and route BOTH install.php and this upgrade step
    // through it, so existing installs and fresh installs provision the same
    // role + caps. Also reconciles the aiquiz caps (generate/review) onto the
    // author role as part of the canonical author cap set (previously granted
    // only by the aiquiz 2026080400 step), and re-pins the role to
    // CONTEXT_SYSTEM. Idempotent — the role and its ALLOW grants are left as-is
    // where already correct.
    if ($oldversion < 2026090700) {
        \local_sentientia_authoring\author_role::ensure();
        upgrade_plugin_savepoint(true, 2026090700, 'local', 'sentientia_authoring');
    }

    // 2026092500 — ADR-031: take :manage_all back from every role. db/access.php
    // no longer grants it to the manager archetype, but changing archetypes
    // never revokes what was already applied (install, or
    // reset_role_capabilities() - which is how UAT's tenant-admin role 9 got
    // it). Every existing grant let a tenant admin review, edit, finalise and
    // publish other tenants' course drafts and rewrite or archive their
    // templates, so revoke them all. Site admins are unaffected; even a
    // deliberate grant now reaches other tenants only for a cross-tenant caller.
    if ($oldversion < 2026092500) {
        require_once(__DIR__ . '/upgradelib.php');
        local_sentientia_authoring_revoke_manage_all();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_authoring');
    }

    // 2026111200 — ADR-034 Stage 1 slice 1: the Sentientia Canonical Course
    // Document. Two new tables, identical to db/install.xml (field order, keys
    // and indexes): local_sentientia_scd (one immutable stored version of a
    // course document per tenant) and local_sentientia_scd_asset (its media
    // file registry, filled by later slices). Additive only: no existing table
    // or row changes, and nothing reads them while the flag
    // sentientia.content.scd.enabled stays OFF (the default).
    if ($oldversion < 2026111200) {
        $table = new xmldb_table('local_sentientia_scd');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('customerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('slug', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('version', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('schemaversion', XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, '0.1');
        $table->add_field('state', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('scd_json', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('sha256', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('fk_usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_index('uq_cust_tenant_slug_ver', XMLDB_INDEX_UNIQUE, ['customerid', 'costcenterid', 'slug', 'version']);
        $table->add_index('idx_costcenterid', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
        $table->add_index('idx_state', XMLDB_INDEX_NOTUNIQUE, ['state']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_sentientia_scd_asset');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scdid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('customerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kind', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'audio');
        $table->add_field('lang', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, '');
        $table->add_field('mimetype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('sha256', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('duration_ms', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('engine', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('voice', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('processing_region', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_scdid', XMLDB_KEY_FOREIGN, ['scdid'], 'local_sentientia_scd', ['id']);
        $table->add_key('fk_usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_key('fk_usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_index('uq_scdid_path', XMLDB_INDEX_UNIQUE, ['scdid', 'path']);
        $table->add_index('idx_customer_tenant', XMLDB_INDEX_NOTUNIQUE, ['customerid', 'costcenterid']);
        $table->add_index('idx_costcenterid', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026111200, 'local', 'sentientia_authoring');
    }

    unset($dbman);

    return true;
}
