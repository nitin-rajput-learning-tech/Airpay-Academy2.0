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
 * @subpackage local_skillrepository
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_login();
global $DB, $PAGE, $CFG;
$PAGE->set_url('/local/skillrepository/index.php');
$PAGE->set_context(context_system::instance());
// Optional Params
$name = optional_param('name', '', PARAM_RAW);
$shortname = optional_param('shortname', '', PARAM_RAW);
$table = optional_param('table', '', PARAM_RAW);

$record = new stdClass();

$record->name = $name;
$record->shortname = $shortname;
$record->usermodified = $USER->id;
$record->timecreated = time();
$record->timemodified = time();
$record->costcenterid = 1;

// Checking If Records Already Exists
$nameexist = $DB->record_exists("local_skill_" . $table, array('name' => $name));
$shortnameexist = $DB->record_exists("local_skill_" . $table, array('shortname' => $shortname));
if ($nameexist){
    echo "NAME";
} else if ($shortnameexist){
    echo "SHORTNAME";
} else {
    $create = $DB->insert_record("local_skill_" . $table, $record);
    echo "OK";
}
?>