<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_emails_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026041200) {
        // Create the 4 management tables (overrides, rules, log, prefs).

        // --- Table: local_airpay_email_overrides ---
        $table = new xmldb_table('local_airpay_email_overrides');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('template_key', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255');
            $table->add_field('body_html', XMLDB_TYPE_TEXT);
            $table->add_field('body_text', XMLDB_TYPE_TEXT);
            $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uix_tenant_template', XMLDB_INDEX_UNIQUE, ['tenant_id', 'template_key']);
            $table->add_index('idx_template_key', XMLDB_INDEX_NOTUNIQUE, ['template_key']);
            $dbman->create_table($table);
        }

        // --- Table: local_airpay_email_rules ---
        $table = new xmldb_table('local_airpay_email_rules');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('rule_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('rule_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('trigger_event', XMLDB_TYPE_CHAR, '100');
            $table->add_field('trigger_days', XMLDB_TYPE_INTEGER, '5');
            $table->add_field('channel', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'email');
            $table->add_field('audience', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'learner');
            $table->add_field('template_key', XMLDB_TYPE_CHAR, '100');
            $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('priority', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '50');
            $table->add_field('conditions_json', XMLDB_TYPE_TEXT);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_rule_type', XMLDB_INDEX_NOTUNIQUE, ['rule_type']);
            $table->add_index('idx_tenant', XMLDB_INDEX_NOTUNIQUE, ['tenant_id']);
            $table->add_index('idx_enabled', XMLDB_INDEX_NOTUNIQUE, ['enabled']);
            $dbman->create_table($table);
        }

        // --- Table: local_airpay_email_log ---
        $table = new xmldb_table('local_airpay_email_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('rule_id', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('legacy_type', XMLDB_TYPE_CHAR, '100');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('channel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'email');
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('template_key', XMLDB_TYPE_CHAR, '100');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'sent');
            $table->add_field('error_message', XMLDB_TYPE_TEXT);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            // FK creates implicit index — no separate idx_userid needed.
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_rule_id', XMLDB_INDEX_NOTUNIQUE, ['rule_id']);
            $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('idx_tenant', XMLDB_INDEX_NOTUNIQUE, ['tenant_id']);
            $dbman->create_table($table);
        }

        // --- Table: local_airpay_email_prefs ---
        $table = new xmldb_table('local_airpay_email_prefs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('rule_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('channel_email', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('channel_popup', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('channel_teams', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('channel_push', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('uix_user_ruletype', XMLDB_INDEX_UNIQUE, ['userid', 'rule_type']);
            $dbman->create_table($table);
        }

        // Seed default notification rules.
        $now = time();
        $defaults = [
            ['rule_name' => 'Compliance: Auto-enrol notification', 'rule_type' => 'compliance_enrolled',
             'trigger_event' => 'cron', 'trigger_days' => 0, 'channel' => 'email,popup',
             'audience' => 'learner', 'template_key' => 'compliance/welcome_enrolled', 'priority' => 90],
            ['rule_name' => 'Compliance: Start reminder (7 days)', 'rule_type' => 'compliance_reminder',
             'trigger_event' => 'cron', 'trigger_days' => 7, 'channel' => 'email,popup',
             'audience' => 'learner', 'template_key' => 'compliance/reminder_start', 'priority' => 80],
            ['rule_name' => 'Compliance: Halfway reminder', 'rule_type' => 'compliance_reminder',
             'trigger_event' => 'cron', 'trigger_days' => 15, 'channel' => 'email,popup',
             'audience' => 'learner', 'template_key' => 'compliance/reminder_halfway', 'priority' => 75],
            ['rule_name' => 'Compliance: Deadline warning (7 days left)', 'rule_type' => 'deadline_approaching',
             'trigger_event' => 'cron', 'trigger_days' => -7, 'channel' => 'email,popup',
             'audience' => 'learner', 'template_key' => 'compliance/deadline_warning', 'priority' => 85],
            ['rule_name' => 'Compliance: Overdue alert', 'rule_type' => 'compliance_overdue',
             'trigger_event' => 'cron', 'trigger_days' => 1, 'channel' => 'email,popup',
             'audience' => 'learner', 'template_key' => 'compliance/overdue_alert', 'priority' => 95],
            ['rule_name' => 'Compliance: Weekly manager escalation', 'rule_type' => 'weekly_escalation',
             'trigger_event' => 'cron', 'trigger_days' => 7, 'channel' => 'email',
             'audience' => 'manager', 'template_key' => 'compliance/weekly_escalation', 'priority' => 90],
            ['rule_name' => 'Course not started (10 days)', 'rule_type' => 'course_not_started',
             'trigger_event' => 'cron', 'trigger_days' => 10, 'channel' => 'email,popup',
             'audience' => 'learner', 'template_key' => 'notifications/course_not_started', 'priority' => 50],
            ['rule_name' => 'Streak broken (3+ days)', 'rule_type' => 'streak_broken',
             'trigger_event' => 'cron', 'trigger_days' => 2, 'channel' => 'popup',
             'audience' => 'learner', 'template_key' => 'notifications/streak_broken', 'priority' => 30],
            ['rule_name' => 'Manager nudge: 3+ overdue', 'rule_type' => 'manager_nudge',
             'trigger_event' => 'cron', 'trigger_days' => 3, 'channel' => 'email',
             'audience' => 'manager', 'template_key' => 'notifications/manager_nudge', 'priority' => 70],
            ['rule_name' => 'New course available', 'rule_type' => 'new_course',
             'trigger_event' => '\\core\\event\\course_created', 'trigger_days' => null, 'channel' => 'popup',
             'audience' => 'all', 'template_key' => 'notifications/new_course_available', 'priority' => 40],
        ];
        foreach ($defaults as $rule) {
            $rule['tenant_id']     = 0;
            $rule['enabled']       = 1;
            $rule['usermodified']  = 2;
            $rule['timecreated']   = $now;
            $rule['timemodified']  = $now;
            $DB->insert_record('local_airpay_email_rules', (object)$rule);
        }

        upgrade_plugin_savepoint(true, 2026041200, 'local', 'airpay_emails');
    }

    // ── Sprint B (2026-05-13): course-completion email + ramping reminders ──
    // Adds 2 columns to local_airpay_email_log (attachment_filename,
    // certificate_issue_id) and 3 columns to local_airpay_email_rules
    // (cadence_days_json, max_reminders_per_user, auto_stop_on_completion).
    // Seeds two new default rules: 'course_completed' and 'course_incomplete'.
    if ($oldversion < 2026051301) {

        // --- local_airpay_email_log: attachment columns ---
        $table = new xmldb_table('local_airpay_email_log');

        $field = new xmldb_field('attachment_filename', XMLDB_TYPE_CHAR, '255',
            null, null, null, null, 'error_message');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('certificate_issue_id', XMLDB_TYPE_INTEGER, '10',
            null, null, null, null, 'attachment_filename');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // --- local_airpay_email_rules: cadence + cap + auto-stop columns ---
        $table = new xmldb_table('local_airpay_email_rules');

        $field = new xmldb_field('cadence_days_json', XMLDB_TYPE_CHAR, '255',
            null, null, null, null, 'conditions_json');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('max_reminders_per_user', XMLDB_TYPE_INTEGER, '3',
            null, XMLDB_NOTNULL, null, '0', 'cadence_days_json');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('auto_stop_on_completion', XMLDB_TYPE_INTEGER, '1',
            null, XMLDB_NOTNULL, null, '1', 'max_reminders_per_user');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // --- Seed: course-completed and course-incomplete rules. ---
        // Skip seeding if the rules already exist (admin may have created
        // them manually before upgrade, or this upgrade may be re-running).
        $now = time();
        $sprintb_rules = [
            [
                'rule_name'              => 'Course completed: congratulations + certificate',
                'rule_type'              => 'course_completed',
                'trigger_event'          => '\\core\\event\\course_completed',
                'trigger_days'           => null,
                'channel'                => 'email',
                'audience'               => 'learner',
                'template_key'           => 'enrollment/course_completed',
                'priority'               => 95,
                'cadence_days_json'      => null,
                'max_reminders_per_user' => 1,    // never resend on the same completion
                'auto_stop_on_completion' => 0,   // n/a — this rule IS the completion email
            ],
            [
                'rule_name'              => 'Course incomplete: ramping reminders (1-3-7-14-21)',
                'rule_type'              => 'course_incomplete',
                'trigger_event'          => 'cron',
                'trigger_days'           => null,
                'channel'                => 'email',
                'audience'               => 'learner',
                'template_key'           => 'notifications/course_not_started',
                'priority'               => 60,
                'cadence_days_json'      => '[1,3,7,14,21]',
                'max_reminders_per_user' => 5,
                'auto_stop_on_completion' => 1,
            ],
        ];
        foreach ($sprintb_rules as $rule) {
            if ($DB->record_exists('local_airpay_email_rules',
                    ['rule_type' => $rule['rule_type'], 'tenant_id' => 0])) {
                continue;
            }
            $rule['tenant_id']    = 0;
            $rule['enabled']      = 1;
            $rule['usermodified'] = 2;
            $rule['timecreated']  = $now;
            $rule['timemodified'] = $now;
            $DB->insert_record('local_airpay_email_rules', (object) $rule);
        }

        upgrade_plugin_savepoint(true, 2026051301, 'local', 'airpay_emails');
    }

    // ── Sprint B hotfix (2026-05-13): widen log.status from char(20)
    //    to char(32). The new 'suppressed_completion' value introduced
    //    in 2026051301 is 21 chars and overflows the original 20-char
    //    column on MariaDB strict mode. Caught by PHPUnit's
    //    observer_test::test_mark_reminders_suppressed_on_completion_stamps_sent_rows.
    //
    //    The status column carries an index (idx_status) — Moodle's
    //    ddl_dependency_exception forbids changing a column under an
    //    index. So: drop the index, widen the column, re-add the index.
    if ($oldversion < 2026051302) {
        $table = new xmldb_table('local_airpay_email_log');
        $index = new xmldb_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '32',
            null, XMLDB_NOTNULL, null, 'sent', 'error_message');

        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026051302, 'local', 'airpay_emails');
    }

    return true;
}
