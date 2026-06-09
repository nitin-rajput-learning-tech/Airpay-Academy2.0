<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_courses_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // 2026050901 — Phase F.2: featured-courses widget table.
    if ($oldversion < 2026050901) {
        $table = new xmldb_table('local_sentientia_featured_courses');
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
            'local', 'sentientia_courses');
    }

    // ── Sprint C (2026-05-13) — cross-tenant course sharing ──────────
    // Adds the local_sentientia_courses_tenant_share many-to-many table that
    // lets an Airpay admin "lend" a course to one or more tenants
    // (Public, ZEEA, etc.) without duplicating the course. The catalog
    // query in sentientia_catalog/classes/catalog_manager.php is updated in
    // the same release to UNION shared courses into the per-tenant list.
    if ($oldversion < 2026051302) {
        $table = new xmldb_table('local_sentientia_courses_tenant_share');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('shared_by', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null,
                XMLDB_NOTNULL, null, 'active');
            $table->add_field('timeshared', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_course', XMLDB_KEY_FOREIGN, ['courseid'],
                'course', ['id']);
            $table->add_key('fk_sharer', XMLDB_KEY_FOREIGN, ['shared_by'],
                'user', ['id']);
            // Unique key — a course can be shared to a given tenant only
            // once. Toggling status re-uses the same row.
            $table->add_key('uk_course_tenant', XMLDB_KEY_UNIQUE,
                ['courseid', 'tenant_id']);

            $table->add_index('idx_tenant_status', XMLDB_INDEX_NOTUNIQUE,
                ['tenant_id', 'status']);
            $table->add_index('idx_status_courseid', XMLDB_INDEX_NOTUNIQUE,
                ['status', 'courseid']);

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026051302,
            'local', 'sentientia_courses');
    }

    // ── Sprint D (2026-05-13) — pull/request workflow ────────────────
    // Adds the local_sentientia_courses_requests table that captures a
    // receiving-tenant manager's request to borrow an Airpay course.
    // Approving a request inserts the corresponding row into the
    // Sprint C share table; rejecting closes the request without
    // sharing. Both events are audited via the Moodle logstore.
    if ($oldversion < 2026051303) {
        $table = new xmldb_table('local_sentientia_courses_requests');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('requesting_tenant', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('requester_userid', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null,
                XMLDB_NOTNULL, null, 'pending');
            $table->add_field('decided_by', XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);
            $table->add_field('decision_reason', XMLDB_TYPE_TEXT, null, null,
                null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timedecided', XMLDB_TYPE_INTEGER, '10', null,
                null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_course',    XMLDB_KEY_FOREIGN, ['courseid'],
                'course', ['id']);
            $table->add_key('fk_requester', XMLDB_KEY_FOREIGN, ['requester_userid'],
                'user', ['id']);
            $table->add_key('fk_decider',   XMLDB_KEY_FOREIGN, ['decided_by'],
                'user', ['id']);

            $table->add_index('idx_status_courseid',
                XMLDB_INDEX_NOTUNIQUE, ['status', 'courseid']);
            $table->add_index('idx_tenant_status',
                XMLDB_INDEX_NOTUNIQUE, ['requesting_tenant', 'status']);
            $table->add_index('idx_course_tenant_status',
                XMLDB_INDEX_NOTUNIQUE,
                ['courseid', 'requesting_tenant', 'status']);

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026051303,
            'local', 'sentientia_courses');
    }

    // 2026052001 — P1 #28: deadline-reminder cron tracking table.
    //
    // Closes audit item #14 from
    // parity-audit-2026-05-15/sentientia_courses.md (BizLMS course_reminder
    // task parity). Combined with P1 #21's `open_coursecompletiondays`
    // field on the course form, this is the persistence layer for
    // the nudge cron: one row per (user, course, days_before_bucket,
    // deadline_ts) tuple, written when a reminder fires. The unique
    // index serves as the de-dupe key — the task does an
    // `IF NOT EXISTS` style insert.
    if ($oldversion < 2026052001) {
        $table = new xmldb_table('local_sentientia_courses_remind_sent');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('days_before_deadline', XMLDB_TYPE_INTEGER, '4', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('deadline_ts', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');
            $table->add_field('timesent', XMLDB_TYPE_INTEGER, '10', null,
                XMLDB_NOTNULL, null, '0');

            $table->add_key('primary',   XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user',   XMLDB_KEY_FOREIGN, ['userid'],
                'user', ['id']);
            $table->add_key('fk_course', XMLDB_KEY_FOREIGN, ['courseid'],
                'course', ['id']);

            $table->add_index('idx_user_course_bucket', XMLDB_INDEX_UNIQUE,
                ['userid', 'courseid', 'days_before_deadline', 'deadline_ts']);
            $table->add_index('idx_timesent', XMLDB_INDEX_NOTUNIQUE,
                ['timesent']);

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026052001, 'local', 'sentientia_courses');
    }

    return true;
}
