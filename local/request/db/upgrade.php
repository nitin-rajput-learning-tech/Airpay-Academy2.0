<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_request
 */

function xmldb_local_request_upgrade($oldversion) {
global $CFG, $DB;

$dbman = $DB->get_manager();

$result = true;

if($oldversion < 2013112539){


	//$DB->get_record_sql("ALTER TABLE  mdl_block_request_comments MODIFY  message LONGTEXT ");

	/*$newrec = new stdClass();
	$newrec->varname = 'denytext1';
	$newrec->value = 'You may enter a denial reason here.';
	$DB->insert_record('local_request_config', $newrec, false);

	$newrec = new stdClass();
	$newrec->varname = 'denytext2';
	$newrec->value = 'You may enter a denial reason here.';
	$DB->insert_record('local_request_config', $newrec, false);

	$newrec = new stdClass();
	$newrec->varname = 'denytext3';
	$newrec->value = 'You may enter a denial reason here.';
	$DB->insert_record('local_request_config', $newrec, false);

	$newrec = new stdClass();
	$newrec->varname = 'denytext4';
	$newrec->value = 'You may enter a denial reason here.';
	$DB->insert_record('local_request_config', $newrec, false);

	$newrec = new stdClass();
	$newrec->varname = 'denytext5';
	$newrec->value = 'You may enter a denial reason here.';
	$DB->insert_record('local_request_config', $newrec, false); */


}
  
  //local_request_comments
  if ($oldversion < 2022101800) {

	    $table = new xmldb_table('local_request_comments');
      $table1 = new xmldb_table('local_request_records');
      
      $index = new xmldb_index('instanceid', XMLDB_INDEX_NOTUNIQUE, array('instanceid'));
        if (!$dbman->index_exists($table,$index)) {
            $dbman->add_index($table,$index);
        }

      $index1 = new xmldb_index('createdbyid', XMLDB_INDEX_NOTUNIQUE, array('createdbyid'));
         if (!$dbman->index_exists($table,$index1)) {
            $dbman->add_index($table,$index1);
        }
        
      $index2 = new xmldb_index('createdbyid', XMLDB_INDEX_NOTUNIQUE, array('createdbyid'));

         if (!$dbman->index_exists($table1,$index2)) {
            $dbman->add_index($table1,$index2);
           }

      $index3 = new xmldb_index('componentid', XMLDB_INDEX_NOTUNIQUE, array('componentid'));
          if (!$dbman->index_exists($table1,$index3)) {
            $dbman->add_index($table1,$index3);
           }

        upgrade_plugin_savepoint(true, 2022101800, 'local', 'request_records');
    }

return $result;

}
?>
