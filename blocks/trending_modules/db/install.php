<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_block_trending_modules_install(){
	global $CFG,$DB;
	$dbman = $DB->get_manager(); // loads ddl manager and xmldb classes
	$array = array('course' => 'local_courses', 
		'local_classroom' => 'local_classroom', 
		'local_certification' => 'local_certification', 
		'local_learningplan' => 'local_learningplan', 
		'local_program' => 'local_program');
	$lib = new \block_trending_modules\lib();
	foreach($array AS $key => $value){
		$table = new xmldb_table($key);
		if($dbman->table_exists($table)){
			$condition = '';
			if($key == 'course'){
				$condition = ' id > 1 ';
				$field = new xmldb_field('open_coursetype');
				if($dbman->field_exists($table, $field)){
					$condition .= ' AND open_coursetype = 0 ';
				}
			}
			$records = $DB->get_fieldset_select($key, 'id', $condition, array());
			foreach($records AS $record){
				$lib->trending_modules_crud($record, $value);
			}
		}
	}
		

}