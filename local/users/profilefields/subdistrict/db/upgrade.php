<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_usersprofilefields_subdistrict_upgrade($oldversion){
    global $CFG, $USER, $DB, $OUTPUT;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2022120100.04) {
        $table = new xmldb_table('local_subdistrict');
        $field = new xmldb_field('costcenterid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field1 = new xmldb_field('statesid');
        $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        upgrade_plugin_savepoint(true, 2022120100.04, 'usersprofilefields', 'subdistrict');
    }
    return true;

}