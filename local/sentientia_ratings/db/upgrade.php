<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade steps for local_sentientia_ratings.
 *
 * 2026-06-03 (ADR-022 batch-1 rename, local_airpay_ratings -> local_sentientia_ratings): the rename
 * itself was a DB hand-over (table/config/capability/role-assignment re-point) performed
 * out-of-band; the version bump of that day exists so Moodle's upgrade flow rebuilds the component
 * classmap + re-registers the renamed web service cleanly.
 *
 * 2026-09-30 (ADR-032, BizLMS import, ratings): two new tables to hold what BizLMS kept beyond the
 * star rating, so the import can bring it over without loss. Both are guarded, so a site that
 * already has them (a fresh install creates them from install.xml) is left alone.
 *
 * @package local_sentientia_ratings
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_sentientia_ratings_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026093001) {
        // Reviews: free text per learner and item. No unique key: BizLMS let a learner review an item more
        // than once, and the 2013-era rows are distinct reviews.
        $table = new xmldb_table('local_sentientia_ratings_reviews');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ratearea', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('review', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_item_area', XMLDB_INDEX_NOTUNIQUE, ['itemid', 'ratearea']);
            $table->add_index('idx_user_item_area', XMLDB_INDEX_NOTUNIQUE, ['userid', 'itemid', 'ratearea']);
            $dbman->create_table($table);
        }

        // Reactions: one like or dislike per learner and item.
        $table = new xmldb_table('local_sentientia_ratings_reactions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ratearea', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('likestatus', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_user_item_area', XMLDB_INDEX_UNIQUE, ['userid', 'itemid', 'ratearea']);
            $table->add_index('idx_item_area_status', XMLDB_INDEX_NOTUNIQUE, ['itemid', 'ratearea', 'likestatus']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026093001, 'local', 'sentientia_ratings');
    }

    return true;
}
