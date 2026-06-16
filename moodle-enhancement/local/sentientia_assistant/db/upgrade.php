<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_assistant_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // P1.3 — Agentic Copilot (2026-06-16). Additive: create the
    // agent-action audit table. Purely new — does not touch the
    // pre-existing chat_log / chat_cache tables, so the legacy
    // nav-assistant behaviour is unaffected.
    if ($oldversion < 2026061600) {

        $table = new xmldb_table('local_sentientia_agent_audit');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('costcenterid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('tool', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('args_json', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('proposed_by', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'llm');
        $table->add_field('outcome', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'proposed');
        $table->add_field('detail', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('idempotency_key', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_userid_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        $table->add_index('idx_costcenter', XMLDB_INDEX_NOTUNIQUE, ['costcenterid']);
        $table->add_index('idx_idempotency', XMLDB_INDEX_NOTUNIQUE, ['idempotency_key']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061600, 'local', 'sentientia_assistant');
    }

    return true;
}
