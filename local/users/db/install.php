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
    $table = new xmldb_table('local_notification_type');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('parent_module', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('pluginname', XMLDB_TYPE_CHAR, '255', null, null, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $dbman->create_table($table);
    }
    $table = new xmldb_table('local_notification_info');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('open_path', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('notificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_field('moduletype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('moduleid', XMLDB_TYPE_TEXT, null, null, null, null, null);
        // courses
        $table->add_field('reminderdays', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('attach_certificate', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('completiondays', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('enable_cc', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '0');
        $table->add_field('adminbody', XMLDB_TYPE_TEXT, null, null, null, null, '0');
        $table->add_field('attachment_filepath', XMLDB_TYPE_CHAR, null, null, null, null, '0');
        $table->add_field('status', XMLDB_TYPE_INTEGER, 10, null, null, null, '0');
        
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        // $table->add_key('foreign', XMLDB_KEY_FOREIGN, array('costcenterid'));
        $result = $dbman->create_table($table);
    }
    $table = new xmldb_table('local_emaillogs');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('notification_infoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('from_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('to_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        $table->add_field('from_emailid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('to_emailid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('moduletype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('moduleid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('teammemberid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        // courses
        $table->add_field('reminderdays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enable_cc', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('emailbody', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '0');
        $table->add_field('adminbody', XMLDB_TYPE_TEXT, null, null, null, null, '0');
        $table->add_field('attachment_filepath', XMLDB_TYPE_CHAR, null, null, null, null, '0');

        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        $table->add_field('sent_date', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sent_by', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $dbman->create_table($table);
    }
    $table = new xmldb_table('local_notification_strings');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('module', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $dbman->create_table($table);
    }
    // data insertion.
    $time = time();
    $initcontent = array('name' => 'Users','shortname' => 'users', 'parent_module' => '0', 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'users');
    $parentid = $DB->get_field('local_notification_type', 'id', array('shortname' => 'users'));
    if(!$parentid){
        $parentid = $DB->insert_record('local_notification_type', $initcontent);
    }

    $notification_type = array('name' => 'User Welcome Email', 'shortname' => 'users_welcome_email', 'parent_module' => $parentid, 'usercreated' => '2', 'timecreated' => $time, 'usermodified' => 2, 'timemodified' => NULL, 'pluginname' => 'users');

    unset($notification_type['timecreated']);
    if(!$DB->record_exists('local_notification_type',  $notification_type)){
        $notification_type['timecreated'] = $time;
        $DB->insert_record('local_notification_type', $notification_type);
    }
        


    //Adding unenroldate string//
    $strings = array(
        array('name' => '[employee_name]','module' => 'users','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
        array('name' => '[employee_email]','module' => 'users','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
        array('name' => '[employee_username]','module' => 'users','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL),
        array('name' => '[employee_password]','module' => 'users','usercreated' => '2','timecreated' => $time,'usermodified' => 2,'timemodified' => NULL)
    );
    foreach($strings as $string){
        unset($string['timecreated']);
        if(!$DB->record_exists('local_notification_strings', $string)){
            $string_obj = (object)$string;
            $string_obj->timecreated = $time;
            $DB->insert_record('local_notification_strings', $string_obj);
        }
    }
}
