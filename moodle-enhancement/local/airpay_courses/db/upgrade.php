<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_courses_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050901 — Phase F.2: featured-courses widget table.
    if ($oldversion < 2026050901) {
        $table = new xmldb_table('local_airpay_featured_courses');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('sort_order', XMLDB_TYPE_INTEGER, '6', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('label', XMLDB_TYPE_CHAR, '100', null,
                null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_course', XMLDB_KEY_FOREIGN, ['courseid'],
                'course', ['id']);
            $table->add_index('idx_costcenter_sort', XMLDB_INDEX_NOTUNIQUE,
                ['costcenterid', 'sort_order']);
            // (fk_course foreign key already creates an index on courseid;
            //  adding idx_courseid would collide.)

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026050901,
            'local', 'airpay_courses');
    }

    return true;
}
