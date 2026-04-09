<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_gamification_upgrade(int $oldversion): bool {
    global $DB;

    // Future upgrades go here.
    // if ($oldversion < 2026041000) { ... }

    return true;
}
