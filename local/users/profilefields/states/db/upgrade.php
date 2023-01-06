<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_usersprofilefields_states_upgrade($oldversion){
    global $CFG, $USER, $DB, $OUTPUT;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2022120104.06) {
        $table = new xmldb_table('local_states');
        $field = new xmldb_field('territoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'costcenterid');
        }
        upgrade_plugin_savepoint(true, 2022120104.06, 'usersprofilefields', 'states');
    }
    return true;

}
