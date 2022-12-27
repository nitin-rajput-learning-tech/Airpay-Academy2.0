<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * List the tool provided 
 *
 * @package   usersprofilefields
 * @subpackage  village
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->dirroot . '/local/users/lib.php');

global $OUTPUT, $DB, $USER, $CFG;
$pageurl = $CFG->wwwroot.'/local/users/profilefields/village/createvillage.php';
$systemcontext = (new \usersprofilefields_village\lib\accesslib())::get_module_context();
$PAGE->set_context($systemcontext);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');

//Header and the navigation bar
$PAGE->set_heading(get_string('managevillage', 'usersprofilefields_village'));
$PAGE->navbar->add(get_string('browseusers', 'local_users'),new \moodle_url('/local/users/index.php'));
$PAGE->navbar->add(get_string('managevillage', 'usersprofilefields_village'));

$PAGE->requires->js_call_amd('usersprofilefields_village/createVillage', 'load', array());
$PAGE->requires->js_call_amd('usersprofilefields_village/createVillage', 'profileTableDataTables', array('id' => 'village_table'));

$villagelib = new \usersprofilefields_village\lib();
echo $OUTPUT->header();

//view village
if(is_siteadmin() || has_capability('usersprofilefields/village:view',$systemcontext)){
    $navbar = masterdata_capabilities('village');
    echo $OUTPUT->render_from_template('local_users/navbar',$navbar);
}else{
    echo print_error('No permission');
}

//create village
if(is_siteadmin() || has_capability('usersprofilefields/village:create',$systemcontext)){
    echo "<ul class='course_extended_menu_list'>
        <li>
            <div class='coursebackup course_extended_menu_itemcontainer'>
                <a id='extended_menu_createusers' title='".get_string('createvillage', 'usersprofilefields_village')."' class='course_extended_menu_itemlink' data-action='createvillagemodal' onclick ='(function(e){ require(\"usersprofilefields_village/createVillage\").init({selector:\"createvillagemodal\", contextid:$systemcontext->id, villageid:0}) })(event)' ><i class='icon fa fa-globe'></i>
                </a>
            </div>
        </li>
    </ul>";
}

//manage village
if(is_siteadmin() || has_capability('usersprofilefields/village:manage',$systemcontext)){
    $pagecontent = $villagelib->village_page_content();
    echo $pagecontent;
}
echo $OUTPUT->footer();