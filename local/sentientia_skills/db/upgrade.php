<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_skills_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050800 — add per-skill level definitions table.
    // Closes Phase-A of the 2026-05-08 Tier-3 polish stretch.
    if ($oldversion < 2026050800) {
        $tbl = new xmldb_table('local_sentientia_skill_levels');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',           XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('skillid',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_field('level',        XMLDB_TYPE_INTEGER, '2',   null, XMLDB_NOTNULL, null, '1');
            $tbl->add_field('label',        XMLDB_TYPE_CHAR,    '100', null, XMLDB_NOTNULL);
            $tbl->add_field('description',  XMLDB_TYPE_TEXT);
            $tbl->add_field('timemodified', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $tbl->add_key('primary',  XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_skill', XMLDB_KEY_FOREIGN, ['skillid'], 'local_sentientia_skills', ['id']);
            $tbl->add_index('idx_skill_level', XMLDB_INDEX_UNIQUE, ['skillid', 'level']);
            $dbman->create_table($tbl);
        }
        upgrade_plugin_savepoint(true, 2026050800, 'local', 'sentientia_skills');
    }

    // 2026051901 — P1 #22: skill-level audit log table.
    //
    // Adds `local_sentientia_user_skill_hist` — append-only history of every
    // change to a user's skill level. Lets HR answer "when did Alice's
    // Python level go from 2 to 4?" — see audit item #23 in
    // parity-audit-2026-05-15/sentientia_skills.md.
    //
    // The table is append-only; no UPDATE / DELETE except via the
    // privacy provider's user-erasure path (which we'll wire next).
    if ($oldversion < 2026051901) {
        $tbl = new xmldb_table('local_sentientia_user_skill_hist');
        if (!$dbman->table_exists($tbl)) {
            $tbl->add_field('id',                XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $tbl->add_field('userid',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $tbl->add_field('skillid',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $tbl->add_field('previous_level',    XMLDB_TYPE_INTEGER, '2',  null,
                XMLDB_NOTNULL, null, '0');
            $tbl->add_field('new_level',         XMLDB_TYPE_INTEGER, '2',  null,
                XMLDB_NOTNULL, null, '0');
            $tbl->add_field('source',            XMLDB_TYPE_CHAR,    '50', null,
                XMLDB_NOTNULL, null, 'course');
            $tbl->add_field('source_id',         XMLDB_TYPE_INTEGER, '10', null);
            $tbl->add_field('changed_by_userid', XMLDB_TYPE_INTEGER, '10', null);
            $tbl->add_field('timecreated',       XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $tbl->add_key('primary',  XMLDB_KEY_PRIMARY, ['id']);
            $tbl->add_key('fk_user',  XMLDB_KEY_FOREIGN, ['userid'],  'user', ['id']);
            $tbl->add_key('fk_skill', XMLDB_KEY_FOREIGN, ['skillid'],
                'local_sentientia_skills', ['id']);

            // NB: a single-column FK on userid implicitly creates an index
            // on `userid`, so we do NOT declare one here.
            $tbl->add_index('idx_user_skill_t', XMLDB_INDEX_NOTUNIQUE,
                ['userid', 'skillid', 'timecreated']);
            $tbl->add_index('idx_changed_by',   XMLDB_INDEX_NOTUNIQUE,
                ['changed_by_userid']);

            $dbman->create_table($tbl);
        }
        upgrade_plugin_savepoint(true, 2026051901, 'local', 'sentientia_skills');
    }

    // 2026061700 — Revised airpay Brand Book 2026-06: repaint seeded category
    // colours that pre-date the brand revamp.
    //
    // install.php seeds eight category `color` values. Three were off-brand
    // before the 2026-06 revamp and were corrected at SOURCE (install.php), but
    // that only fixes FRESH installs. Tenants seeded earlier still carry the old
    // hex in local_sentientia_skill_cats.color, so their skill-matrix category
    // badges/headers render off-brand. This step migrates those existing rows.
    //
    // Surgical + idempotent: each set_field matches ONE retired hex that no
    // brand-correct install ever produces, so it cannot clobber a colour an
    // admin legitimately chose, and re-running matches nothing.
    //   #0f7a73 retired teal        -> #1985DD brand bright-blue (Financial Literacy)
    //   #7c3aed Tailwind violet     -> #6d58a5 brand purple      (Technical)
    //   #ea580c Tailwind orange-600 -> #ed692b brand orange      (Product Knowledge)
    if ($oldversion < 2026061700) {
        $repaint = [
            '#0f7a73' => '#1985DD',
            '#7c3aed' => '#6d58a5',
            '#ea580c' => '#ed692b',
        ];
        foreach ($repaint as $old => $new) {
            $n = $DB->count_records('local_sentientia_skill_cats', ['color' => $old]);
            if ($n > 0) {
                $DB->set_field('local_sentientia_skill_cats', 'color', $new, ['color' => $old]);
                mtrace("  sentientia_skills: repainted {$n} category colour(s) {$old} -> {$new}");
            }
        }
        upgrade_plugin_savepoint(true, 2026061700, 'local', 'sentientia_skills');
    }

    // 2026092500 - ADR-031: take :manage back from every role.
    // :manage no longer defaults to the manager archetype (db/access.php):
    // the catalogue it edits is shared by every tenant, and tenant admins hold
    // manager-archetype roles. Changing archetypes never revokes what was
    // already granted, so every existing grant would stay a cross-tenant
    // write. Revoke them all; site admins are unaffected. A platform role that
    // should curate the framework is granted it again deliberately.
    if ($oldversion < 2026092500) {
        $syscontext = \context_system::instance();
        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap', ['cap' => 'local/sentientia_skills:manage']);
        foreach ($roleids as $roleid) {
            unassign_capability('local/sentientia_skills:manage', (int) $roleid, $syscontext->id);
        }
        $syscontext->mark_dirty();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'sentientia_skills');
    }

    // 2026093001 - ADR-032 BizLMS import (skills): the schema the skills importer writes to.
    //
    //  - skill_cats.name and skills.name widen from 100 to 255 (the BizLMS source columns are 255 characters).
    //  - skill_cats and skills gain idnumber (the legacy shortname) and open_path (the legacy tenant path).
    //  - local_sentientia_course_levels: the BizLMS course difficulty levels, legacy ids kept because
    //    course.open_level stores them. proficiency is the owner's level-to-skill-level map.
    //  - local_sentientia_skill_interest: one row per learner and skill they said they are interested in.
    //
    // Idempotent: every step checks what exists first, so a re-run (or a database that already has a part of
    // it) changes nothing.
    if ($oldversion < 2026093001) {
        // Widen the names. change_field_precision re-applies cleanly when the column is already 255.
        foreach (['local_sentientia_skill_cats', 'local_sentientia_skills'] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $dbman->change_field_precision($table, $field);
        }

        // idnumber and open_path, plus the idnumber index.
        foreach (['local_sentientia_skill_cats', 'local_sentientia_skills'] as $tablename) {
            $table = new xmldb_table($tablename);
            $idnumber = new xmldb_field('idnumber', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'timecreated');
            if (!$dbman->field_exists($table, $idnumber)) {
                $dbman->add_field($table, $idnumber);
            }
            $openpath = new xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'idnumber');
            if (!$dbman->field_exists($table, $openpath)) {
                $dbman->add_field($table, $openpath);
            }
            $index = new xmldb_index('idx_idnumber', XMLDB_INDEX_NOTUNIQUE, ['idnumber']);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // The BizLMS course levels.
        $table = new xmldb_table('local_sentientia_course_levels');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('code', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('open_path', XMLDB_TYPE_CHAR, '255', null, null);
            $table->add_field('proficiency', XMLDB_TYPE_INTEGER, '2', null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uk_code', XMLDB_INDEX_UNIQUE, ['code']);
            $dbman->create_table($table);
        }

        // Learner interests.
        $table = new xmldb_table('local_sentientia_skill_interest');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('skillid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('fk_skill', XMLDB_KEY_FOREIGN, ['skillid'], 'local_sentientia_skills', ['id']);
            $table->add_index('uk_user_skill', XMLDB_INDEX_UNIQUE, ['userid', 'skillid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026093001, 'local', 'sentientia_skills');
    }

    return true;
}
