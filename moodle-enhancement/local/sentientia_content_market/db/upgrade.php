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

    // 2026061601 - make idx_provider_ext per-tenant. The original unique index on
    // (provider, external_id) prevented the same external course from coexisting across
    // tenants and made upsert_item() collide cross-tenant. Widen it to include costcenterid.
    if ($oldversion < 2026061601) {
        $table = new xmldb_table('local_sentientia_cm_item');

        $oldindex = new xmldb_index('idx_provider_ext', XMLDB_INDEX_UNIQUE,
            ['provider', 'external_id']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        $newindex = new xmldb_index('idx_provider_ext', XMLDB_INDEX_UNIQUE,
            ['provider', 'external_id', 'costcenterid']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026061601, 'local', 'sentientia_content_market');
    }

    return true;
}
