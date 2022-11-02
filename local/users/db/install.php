<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_local_users_install(){
    global $CFG, $USER, $DB, $OUTPUT;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes
    $table = new xmldb_table('user');
    if ($dbman->table_exists($table)) {

        $field1 = new xmldb_field('open_costcenterid');
        $field1->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $dbman->add_field($table, $field1);
        $table->add_key('open_costcenterid', XMLDB_KEY_FOREIGN, array('open_costcenterid'),
         'local_costcenter', array('id'));

          $field2 = new xmldb_field('open_supervisorid');
          $field2->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
          $dbman->add_field($table, $field2);

          $field5 = new xmldb_field('open_employeeid');
          $field5->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          $dbman->add_field($table, $field5);

          $field6 = new xmldb_field('open_usermodified');
          $field6->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
          $dbman->add_field($table, $field6);

          $field7 = new xmldb_field('open_designation');
          $field7->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          $dbman->add_field($table, $field7);

          $field8 = new xmldb_field('open_level');
          $field8->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          $dbman->add_field($table, $field8);

          $field11 = new xmldb_field('open_state');
          $field11->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field11);

          $field12 = new xmldb_field('open_branch');
          $field12->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field12);

          $field13 = new xmldb_field('open_jobfunction');
          $field13->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field13);

          $field14 = new xmldb_field('open_group');
          $field14->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field14);

          $field18 = new xmldb_field('open_qualification');
          $field18->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field18);

          $field19 = new xmldb_field('open_departmentid');
          $field19->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
          $dbman->add_field($table, $field19);
          $table->add_key('open_departmentid', XMLDB_KEY_FOREIGN, array('open_departmentid'),
         'local_costcenter', array('id'));

          $field21 = new xmldb_field('open_subdepartment');
          $field21->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
          $dbman->add_field($table, $field21);
          $table->add_key('open_subdepartment', XMLDB_KEY_FOREIGN, array('open_subdepartment'),
         'local_costcenter', array('id'));

          $field30 = new xmldb_field('open_location');
          $field30->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field30);

          $field31 = new xmldb_field('open_team');
          $field31->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field31);

          $field32 = new xmldb_field('open_client');
          $field32->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field32);

          $field34 = new xmldb_field('open_supervisorempid');
          $field34->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field34);

          $field35 = new xmldb_field('open_band');
          $field35->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field35);

          $field36 = new xmldb_field('open_hrmsrole');
          $field36->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field36);

          $field37 = new xmldb_field('open_zone');
          $field37->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field37);

          $field38 = new xmldb_field('open_region');
          $field38->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field38);

          $field39 = new xmldb_field('open_grade');
          $field39->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field39);

          $field8 = new xmldb_field('open_positionid');
          $field8->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          $dbman->add_field($table, $field8);

          $field8 = new xmldb_field('open_domainid');
          $field8->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          $dbman->add_field($table, $field8);
    }
}
