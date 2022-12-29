<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_usersprofilefields_district_upgrade($oldversion){
    global $CFG, $USER, $DB, $OUTPUT;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2022120104.03) {
        $table = new xmldb_table('local_district');
        $field = new xmldb_field('costcenterid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2022120104.03, 'usersprofilefields', 'district');
    }
    return true;

}