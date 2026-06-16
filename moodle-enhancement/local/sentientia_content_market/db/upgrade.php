<?php
/**
 * Upgrade steps for local_sentientia_content_market.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_content_market_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Initial install handled by install.xml — no upgrade steps yet.
    // Future schema changes go here following the pattern:
    //
    //   if ($oldversion < 2026061601) {
    //       $table = new xmldb_table('local_sentientia_cm_item');
    //       $field = new xmldb_field('new_col', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'status');
    //       if (!$dbman->field_exists($table, $field)) {
    //           $dbman->add_field($table, $field);
    //       }
    //       upgrade_plugin_savepoint(true, 2026061601, 'local', 'sentientia_content_market');
    //   }

    return true;
}
