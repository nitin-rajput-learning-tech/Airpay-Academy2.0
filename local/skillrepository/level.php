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
global $USER, $CFG, $PAGE, $OUTPUT, $DB;
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/skillrepository/classes/local/querylib.php');

$id = optional_param('id', 0, PARAM_INT);

require_login();
$pageurl = new moodle_url('/local/skillrepository/level.php');
$PAGE->set_url('/local/skillrepository/level.php');
$PAGE->set_context((new \local_skillrepository\lib\accesslib())::get_module_context());
$PAGE->set_pagelayout('standard');

$PAGE->set_title(get_string('levelpluginname', 'local_skillrepository'));
$PAGE->navbar->add(get_string('manage_skills', 'local_skillrepository'),new moodle_url('/local/skillrepository/index.php'));
$PAGE->navbar->add(get_string('manage_level', 'local_skillrepository'));
$PAGE->requires->js_call_amd('local_skillrepository/leveltable', 'leveltable', array());
$heading = get_string('createlevel', 'local_skillrepository');

$locallib = new \local_skillrepository\local\querylib();
$renderer = $PAGE->get_renderer('local_skillrepository');

$PAGE->set_heading(get_string('levels', 'local_skillrepository'));
echo $OUTPUT->header();
//$systemcontext = context_system::instance();
$systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
echo "<ul class='course_extended_menu_list'>
		<li>
          	<div class='coursebackup course_extended_menu_itemcontainer'>
            <a href='".$CFG->wwwroot."/local/skillrepository/index.php' title='".get_string("back")."' class='course_extended_menu_itemlink'>
              <i class='icon fa fa-reply'></i>
            </a>
        	</div>
        </li>
        <li>
        	<div class='coursebackup course_extended_menu_itemcontainer'>
              	<a id='extended_menu_syncstats' title='".get_string('createlevel', 'local_skillrepository')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_skillrepository/leveltable\").init({selector:\"createlevelmodal\", contextid:$systemcontext->id, levelid:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true' aria-label=''></i></a>
          	</div>    	        
        </li>
    </ul>";
echo $renderer->display_levels_tablestructure();
echo $OUTPUT->footer();
