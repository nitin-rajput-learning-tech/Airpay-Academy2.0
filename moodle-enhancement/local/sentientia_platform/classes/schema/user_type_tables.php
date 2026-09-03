<?php
// This file is part of Sentientia LMS.
//
// Sentientia LMS is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. Distributed WITHOUT ANY WARRANTY. See the GNU GPL for
// more details. <http://www.gnu.org/licenses/>.

namespace local_sentientia_platform\schema;

defined('MOODLE_INTERNAL') || die();

use database_manager;
use xmldb_table;

/**
 * The polymorphic user-type tables (ADR-017 v1), creatable on demand.
 *
 * History: these five tables were introduced in db/upgrade.php step 2026052801 only, so every
 * site that already had the plugin got them while a FRESH install did not (UAT Stage A,
 * 2026-09-03: `user_type_factory::for_user()` fell back to 'employee' for every account and the
 * public-signup consumer insert failed silently). db/install.php now calls ensure() so a fresh
 * install and an upgraded install end up with the same schema. Idempotent: every table is
 * guarded with table_exists(), so calling it from both install.php and the seed CLI is safe.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_type_tables {

    /** @var string[] */
    public const TABLES = [
        'local_sentientia_user_type',
        'local_sentientia_employee_profile',
        'local_sentientia_consumer_profile',
        'local_sentientia_partner_employee_profile',
        'local_sentientia_operator_profile',
    ];

    /**
     * Create every missing user-type table.
     *
     * @param database_manager $dbman
     * @return string[] names of the tables created
     */
    public static function ensure(database_manager $dbman): array {
        $created = [];

        $t = new xmldb_table('local_sentientia_user_type');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $t->add_field('user_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $t->add_field('provisioning_source', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'unknown');
            $t->add_field('provisioned_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $t->add_index('idx_type', XMLDB_INDEX_NOTUNIQUE, ['user_type']);
            $dbman->create_table($t);
            $created[] = $t->getName();
        }

        $t = new xmldb_table('local_sentientia_employee_profile');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $t->add_field('employee_id', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $t->add_field('department', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $t->add_field('job_title', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $t->add_field('manager_userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $t->add_field('hire_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $t->add_field('cost_center_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $t->add_key('fk_manager', XMLDB_KEY_FOREIGN, ['manager_userid'], 'user', ['id']);
            $t->add_index('idx_dept', XMLDB_INDEX_NOTUNIQUE, ['department']);
            $dbman->create_table($t);
            $created[] = $t->getName();
        }

        $t = new xmldb_table('local_sentientia_consumer_profile');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $t->add_field('interests_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $t->add_field('weekly_goal', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
            $t->add_field('referral_source', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $t->add_field('consent_marketing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('consent_leaderboard', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('payment_history_url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $dbman->create_table($t);
            $created[] = $t->getName();
        }

        $t = new xmldb_table('local_sentientia_partner_employee_profile');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $t->add_field('customer_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $t->add_field('partner_employee_id', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $t->add_field('partner_department', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $t->add_field('partner_job_title', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $t->add_field('partner_manager_userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $t->add_field('partner_hire_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $t->add_field('cost_center_path', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $t->add_key('fk_manager', XMLDB_KEY_FOREIGN, ['partner_manager_userid'], 'user', ['id']);
            $t->add_index('idx_customer', XMLDB_INDEX_NOTUNIQUE, ['customer_id']);
            $dbman->create_table($t);
            $created[] = $t->getName();
        }

        $t = new xmldb_table('local_sentientia_operator_profile');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $t->add_field('operator_role', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $t->add_field('contact_phone', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $t->add_field('oncall_for_customer_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $t->add_key('uk_userid', XMLDB_KEY_UNIQUE, ['userid']);
            $t->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $dbman->create_table($t);
            $created[] = $t->getName();
        }

        return $created;
    }
}
