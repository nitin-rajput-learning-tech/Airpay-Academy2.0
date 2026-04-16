<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_classroom_upgrade(int $oldversion): bool {
    // Future schema changes go here.
    // Pattern: if ($oldversion < YYYYMMDDVV) { ... upgrade_plugin_savepoint(...); }
    return true;
}
