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

    return true;
}
