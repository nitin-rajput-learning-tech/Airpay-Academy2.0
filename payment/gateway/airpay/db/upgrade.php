<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for the airpay payment gateway
 *
 * @package    paygw_airpay
 * @copyright  2024 Moodle India <support@moodle.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_paygw_airpay_upgrade(int $oldversion): bool
{
    global $DB;

    $dbman = $DB->get_manager();

    // if ($oldversion < 2021052501) {
    //     // Define key paymentid (foreign-unique) to be added to paygw_airpay.
    //     $table = new xmldb_table('paygw_airpay');
    //     $key = new xmldb_key('paymentid', XMLDB_KEY_FOREIGN_UNIQUE, ['paymentid'], 'payments', ['id']);

    //     // Launch add key paymentid.
    //     $dbman->add_key($table, $key);

    //     // airpay savepoint reached.
    //     upgrade_plugin_savepoint(true, 2021052501, 'paygw', 'airpay');
    // }

    // // Automatically generated Moodle v4.0.0 release upgrade line.
    // // Put any upgrade step following this.

    // // Automatically generated Moodle v4.1.0 release upgrade line.
    // // Put any upgrade step following this.

    // // Automatically generated Moodle v4.2.0 release upgrade line.
    // // Put any upgrade step following this.
    // if ($oldversion < 2024100700.02) {
    //     $table = new xmldb_table('paygw_airpay');
    //     $field1 = new xmldb_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field1)) {
    //         $dbman->add_field($table, $field1);
    //     }
    //     $field2 = new xmldb_field('paymentarea', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field2)) {
    //         $dbman->add_field($table, $field2);
    //     }
    //     $field3 = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field3)) {
    //         $dbman->add_field($table, $field3);
    //     }
    //     $field4 = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field4)) {
    //         $dbman->add_field($table, $field4);
    //     }
    //     $field5 = new xmldb_field('accountid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field5)) {
    //         $dbman->add_field($table, $field5);
    //     }
    //     $field6 = new xmldb_field('paymentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field6)) {
    //         $dbman->add_field($table, $field6);
    //     }
    //     $field7 = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field7)) {
    //         $dbman->add_field($table, $field7);
    //     }
    //     $field8 = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field8)) {
    //         $dbman->add_field($table, $field8);
    //     }
    //     $field10 = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field10)) {
    //         $dbman->add_field($table, $field10);
    //     }
    //     $field11 = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field11)) {
    //         $dbman->add_field($table, $field11);
    //     }

    //     // airpay savepoint reached.
    //     upgrade_plugin_savepoint(true, 2024100700.02, 'paygw', 'airpay');
    // }
    // if ($oldversion < 2024100700.03) {
    //     $table = new xmldb_table('paygw_airpay');
    //     $field1 = new xmldb_field('ap_orderid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field1)) {
    //         $dbman->add_field($table, $field1);
    //     }
    //     upgrade_plugin_savepoint(true, 2024100700.03, 'paygw', 'airpay');
    // }
    // if ($oldversion < 2024100700.04) {
    //     $table = new xmldb_table('paygw_airpay');
    //     $field1 = new xmldb_field('cost', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    //     if (!$dbman->field_exists($table, $field1)) {
    //         $dbman->add_field($table, $field1);
    //     }
    //     upgrade_plugin_savepoint(true, 2024100700.04, 'paygw', 'airpay');
    // }
    //  if ($oldversion < 2024100700.05) {
    //     $table = new xmldb_table('paygw_airpay_errorlog');

    //     if ($dbman->table_exists($table)) {
    //         $dbman->drop_table($table);
    //     }
    //     if (!$dbman->table_exists($table)) {
    //         $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    //         $table->add_field('error', XMLDB_TYPE_CHAR,  '255', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('airpay_id', XMLDB_TYPE_CHAR, '255', null, null, null, 0);
    //         $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
    //         $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
    //         $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    //         $dbman->create_table($table);
    //     }
    //     upgrade_plugin_savepoint(true, 2024100700.05, 'paygw', 'airpay');
    //  }
    //  if ($oldversion < 2024100700.06) {
    //     $table = new xmldb_table('paygw_course_enrolmentlog');

    //     if ($dbman->table_exists($table)) {
    //         $dbman->drop_table($table);
    //     }
    //     if (!$dbman->table_exists($table)) {
    //         $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    //         $table->add_field('coursename', XMLDB_TYPE_CHAR,  '255', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
    //         $table->add_field('username', XMLDB_TYPE_CHAR,  '255', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
    //         $table->add_field('transactionid', XMLDB_TYPE_CHAR,  '255', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('ap_orderid', XMLDB_TYPE_CHAR,  '255', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('amount', XMLDB_TYPE_INTEGER,  '20', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('status', XMLDB_TYPE_INTEGER,  '1', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
    //         $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
    //         $dbman->create_table($table);
    //     }
    //     upgrade_plugin_savepoint(true, 2024100700.06, 'paygw', 'airpay');
    //  }

    if ($oldversion < 2024100700.09) {
        $table = new xmldb_table('paygw_airpay_errorlog');
        $field1 = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        $field2 = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        $field3 = new xmldb_field('airpay_id', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field3)) {
            $dbman->change_field_type($table, $field3);
        }
        $field4 = new xmldb_field('order_state', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field4)) {
            $dbman->add_field($table, $field4);
        }
        $field5 = new xmldb_field('paymentarea', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field5)) {
            $dbman->add_field($table, $field5);
        }
        upgrade_plugin_savepoint(true, 2024100700.09, 'paygw', 'airpay');
    }
    return true;
}
