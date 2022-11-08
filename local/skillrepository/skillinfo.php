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
require_once($CFG->dirroot . '/local/skillrepository/renderer.php');
global $CFG, $PAGE;

$PAGE->requires->jquery();
$PAGE->requires->js('/local/skillrepository/js/script.js');
    
$id = required_param('id', PARAM_INT);
$PAGE->set_pagelayout('standard');

$systemcontext =(new \local_skillrepository\lib\accesslib())::get_module_context();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/local/skillrepository/skillinfo.php');

$skill = $DB->get_record('local_skill', array('id' => $id));

if (!has_capability('local/skillrepository:create_skill',(new \local_skillrepository\lib\accesslib())::get_module_context()) && !is_siteadmin()) {
	print_error('Sorry, You are not accessable to this page');
}

/*print_object($skill);
print_object($USER->open_costcenterid);
die();*/


if (!has_capability('local/costcenter:manage_multiorganizations', (new \local_skillrepository\lib\accesslib())::get_module_context())) {
        if($skill->costcenterid!=$USER->open_costcenterid){
         print_error('Sorry, You are not accessable to this page');
        }     
}


require_login();
$PAGE->set_title(get_string('skillinfo', 'local_skillrepository'));
$PAGE->navbar->add(get_string('manage_skills', 'local_skillrepository'),new moodle_url('/local/skillrepository/index.php'));
$PAGE->navbar->add(get_string('skillinfo', 'local_skillrepository'));

$PAGE->set_heading(get_string('skillacquired', 'local_skillrepository'));
echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('local_skillrepository');
echo $renderer->get_skill_info($id);    
echo $OUTPUT->footer();