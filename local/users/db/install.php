<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_local_users_install(){
    global $CFG, $USER, $DB, $OUTPUT;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes
    $table = new xmldb_table('user');
    if ($dbman->table_exists($table)) {

          $field1 = new xmldb_field('open_path');
          $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          $dbman->add_field($table, $field1);

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

          $field11 = new xmldb_field('open_state');
          $field11->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field11);


          $field13 = new xmldb_field('open_jobfunction');
          $field13->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field13);

          $field14 = new xmldb_field('open_group');
          $field14->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field14);

          $field18 = new xmldb_field('open_qualification');
          $field18->set_attributes(XMLDB_TYPE_CHAR, '200', null, null, null, null);
          $dbman->add_field($table, $field18);


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

          $field = new xmldb_field('open_states');
          $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field)) {
              $dbman->add_field($table, $field);
          }

          $field1 = new xmldb_field('open_district');
          $field1->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field1)) {
              $dbman->add_field($table, $field1);
          }

          $field2 = new xmldb_field('open_subdistrict');
          $field2->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field2)) {
              $dbman->add_field($table, $field2);
          }

          $field3 = new xmldb_field('open_village');
          $field3->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
          if (!$dbman->field_exists($table, $field3)) {
              $dbman->add_field($table, $field3);
          }
          $field5 = new xmldb_field('open_joindate');
          $field5->set_attributes(XMLDB_TYPE_CHAR, '512', null, null, null, null);
          if (!$dbman->field_exists($table, $field5)) {
              $dbman->add_field($table, $field5);
          }
          $field6 = new xmldb_field('open_dateofbirth');
          $field6->set_attributes(XMLDB_TYPE_CHAR, '512', null, null, null, null);
          if (!$dbman->field_exists($table, $field6)) {
              $dbman->add_field($table, $field6);
          }
          $field7 = new xmldb_field('gender');
          $field7->set_attributes(XMLDB_TYPE_CHAR, '512', null, null, null, null);
          if (!$dbman->field_exists($table, $field7)) {
              $dbman->add_field($table, $field7);
          }
          $field8 = new xmldb_field('open_employmenttype');
          $field8->set_attributes(XMLDB_TYPE_CHAR, '512', null, null, null, null);
          if (!$dbman->field_exists($table, $field8)) {
              $dbman->add_field($table, $field8);
          }
          $prefix = new xmldb_field('open_prefix');
          $prefix->set_attributes(XMLDB_TYPE_CHAR, '512', null, null, null, null);
          if (!$dbman->field_exists($table, $prefix)) {
              $dbman->add_field($table, $prefix);
          }
          $orgactive = new xmldb_field('open_orgactive');
          $orgactive->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
          if (!$dbman->field_exists($table, $orgactive)) {
              $dbman->add_field($table, $orgactive);
          }

    }
}
